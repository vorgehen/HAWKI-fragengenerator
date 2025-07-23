<?php

namespace App\Services\AI\Providers\Google;

use App\Services\AI\Providers\ModelUtilities;
use App\Services\AI\Interfaces\FormatterInterface;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;

use App\Services\Attachment\AttachmentService;

class GoogleFormatter implements FormatterInterface
{
    protected $config;
    protected $utils;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->utils = new ModelUtilities($config);
    }


    public function format(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];

        // Load and attach attachment models if any
        $attachmentsMap = $this->loadAttachmentModelsByUuid($messages);


        // Extract system prompt from first message item
        $systemInstruction = [];
        if (isset($messages[0]) && $messages[0]['role'] === 'system') {
            $systemInstruction = [
            'parts' => [
                'text' => $messages[0]['content']['text'] ?? ''
            ]
            ];
            array_shift($messages);
        }

        // Format messages for Google
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => $this->formatMessageContent($message['content'], $attachmentsMap),
                // [
                //     [
                //         'text' => $message['content']['text']
                //     ]
                // ]
            ];
        }


        $payload = [
            'model' => $modelId,
            'system_instruction' => $systemInstruction,
            'contents' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $this->utils->hasTool($modelId, 'stream'),
        ];

        // Set complete optional fields with content (default values if not present in $rawPayload)
        $payload['safetySettings'] = $rawPayload['safetySettings'] ?? [
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_ONLY_HIGH'
            ]
        ];

        $payload['generationConfig'] = $rawPayload['generationConfig'] ?? [
            // 'stopSequences' => ["Title"],
            'temperature' => 1.0,
            'maxOutputTokens' => 800,
            'topP' => 0.8,
            'topK' => 10
        ];

        // Google Search only works with gemini >= 2.0
        // Search tool is context sensitive, this means the llm decides if a search is necessary for an answer
        if ($this->utils->getModelDetails($modelId)['tools']['internet_search']){
            $payload['tools'] = $rawPayload['tools'] ?? [
                [
                    "google_search" => new \stdClass()
                ]
            ];
        }

        return $payload;
    }


    public function formatMessageContent(array $content, array $attachmentsMap): array
    {
        $formatted = [];
        // Add text if present
        if (!empty($content['text'])) {
            $formatted[] = [
                'text' => $content['text'],
            ];
        }

        // Handle attachments
        if (!empty($content['attachments'])) {

            $attachmentService = new AttachmentService();

            foreach ($content['attachments'] as $uuid) {
                $attachment = $attachmentsMap[$uuid] ?? null;
                if (!$attachment) {
                    continue; // skip invalid
                }


                switch ($attachment->type) {
                    case 'image':
                        $file = $attachmentService->retrieve($attachment);
                        $imageData = base64_encode($file);
                        $url = $attachmentService->getFileUrl($attachment);
                        $formatted[] = [
                            'inline_data' =>
                            [
                                "mime_type"=> $attachment->mime,
                                'data' => $imageData,
                            ]
                        ];
                        break;

                    case 'document':
                        $fileContent = $attachmentService->retrieve($attachment, 'md');
                        $html_safe = htmlspecialchars($fileContent);

                        $formatted[] = [
                            'text' => "[\"ATTACHED FILE CONTEXT: \" { $attachment->name }\"]
                                        ---
                                        { {$html_safe} }.
                                        ---",
                        ];
                        break;

                    default:
                        Log::error('bad attachment type');
                        break;
                }
            }
        }

        return $formatted;
    }












        /**
     * Handle special formatting requirements for specific models
     *
     * @param string $modelId
     * @param array $messages
     * @return array
     */
    protected function handleModelSpecificFormatting(string $modelId, array $messages): array
    {
        // Special case for o1-mini: convert system to user
        if ($modelId === 'o1-mini' && isset($messages[0]) && $messages[0]['role'] === 'system') {
            $messages[0]['role'] = 'user';
        }

        return $messages;
    }


#NOTE: THESE CAN GO TO BASE FORMATTER

    private function loadAttachmentModelsByUuid(array $messages): array
    {
        $uuids = collect($messages)
            ->pluck('content.attachments')
            ->filter()
            ->flatten()
            ->unique()
            ->all();

        if (empty($uuids)) {
            return [];
        }

        return Attachment::whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid')
            ->all(); // returns [uuid => AttachmentModel]
    }



}


