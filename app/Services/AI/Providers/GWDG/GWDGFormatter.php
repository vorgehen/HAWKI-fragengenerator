<?php

namespace App\Services\AI\Providers\GWDG;

use App\Services\AI\Providers\ModelUtilities;
use App\Services\AI\Interfaces\FormatterInterface;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;

use App\Services\Attachment\AttachmentService;

class GWDGFormatter implements FormatterInterface
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

        // Handle special cases for specific models
        $messages = $this->handleModelSpecificFormatting($modelId, $messages);

        // Load and attach attachment models if any
        $attachmentsMap = $this->loadAttachmentModelsByUuid($messages);

        // Format messages for OpenAI
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'],
                'content' => $this->formatMessageContent($message['content'], $attachmentsMap),
            ];
        }

        // Build payload with common parameters
        $payload = [
            'model' => $modelId,
            'messages' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $this->utils->hasTool($modelId, 'stream'),
        ];

        return $payload;
    }


    public function formatMessageContent(array $content, array $attachmentsMap): array
    {
        $formatted = [];
        // Add text if present
        if (!empty($content['text'])) {
            $formatted[] = [
                'type' => 'text',
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
                        $formatted[] = [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:image/jpg;base64, . {$imageData}",
                            ],
                        ];
                        break;

                    case 'document':
                        $fileContent = $attachmentService->retrieve($attachment, 'md');
                        $html_safe = htmlspecialchars($fileContent);

                        $formatted[] = [
                            'type' => "text",
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
        if ($modelId === 'gemma-3-27b-it' && isset($messages[0]) && $messages[0]['role'] === 'system') {
            $messages[0]['role'] = 'assistant';
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


