<?php

namespace App\Services\AI\Providers\Ollama;

use App\Services\AI\Providers\ModelUtilities;
use App\Services\AI\Interfaces\FormatterInterface;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;

use App\Services\Attachment\AttachmentService;

class OllamaFormatter implements FormatterInterface
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
            $formatted = $this->formatMessageContent($message['content'], $attachmentsMap);

            if(array_key_exists('images', $formatted)){
                $formattedMessages[] = [
                    'role' => $message['role'],
                    'content' => $formatted['content'],
                    'images' => $formatted['images'],
                ];
            }
            else{
                $formattedMessages[] = [
                    'role' => $message['role'],
                    $formatted['content'],
                ];
            }

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
    $text = '';

    // Add text if present
    if (!empty($content['text'])) {
        $text = $content['text'];
    }

    // Handle attachments
    if (!empty($content['attachments'])) {
        $attachmentService = new AttachmentService();
        $images = [];

        foreach ($content['attachments'] as $uuid) {
            $attachment = $attachmentsMap[$uuid] ?? null;
            if (!$attachment) {
                continue; // Skip invalid attachment
            }

            switch ($attachment->type) {
                case 'image':
                    $file = $attachmentService->retrieve($attachment);
                    $imageData = base64_encode($file);
                    $images[] = $imageData;
                    break;

                case 'document':
                    $fileContent = $attachmentService->retrieve($attachment, 'md');
                    $html_safe = htmlspecialchars($fileContent);

                    $text .= "\n\n[ATTACHED FILE CONTEXT: {$attachment->name}]\n---\n{$html_safe}\n---\n";
                    break;

                default:
                    Log::error(message: 'Bad attachment type: ' . $attachment->type);
                    break;
            }
        }

        // Always add text content
        $formatted['content'] = $text;

        // Add images if any
        if (count($images) > 0) {
            $formatted['images'] = $images;
        }
    } else {
        // If no attachments, only text
        $formatted['content'] = $text;
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


