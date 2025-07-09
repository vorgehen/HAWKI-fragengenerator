<?php


namespace App\Services\AI;

use App\Models\Attachment;
use App\Services\StorageServices\StorageServiceFactory;



class AttachmentFormatter{


    public function handleAttachments($payload)
    {
        $model = $payload['model'];



        $orgMessages = $payload['messages'];
        $newMessages = [];

        foreach ($orgMessages as $msg) {
            if(array_key_exists('attachments', $msg['content']) && count($msg['content']['attachments']) > 0){
                // Assume `$msg` has a structure with optional attachments
                $attachments = $msg['content']['attachments'] ?? [];

                // Add context messages for each attachment
                foreach ($attachments as $uuid) {
                    $atchModel = Attachment::where('uuid', $uuid)->first();
                    if (!$atchModel) {
                        throw new \Exception('Attachment model not found');
                    }
                    // Retrieve Markdown contents
                    $storageService = StorageServiceFactory::create();

                    // (Optional) Check type here
                    if($atchModel->type === 'image') {
                        // Log::debug('image detected');
                        $url = $storageService->getFileUrl($uuid, 'private');
                        $msg = [
                            'role' => $msg['role'],
                            'content'=> [
                                [
                                    'text' => $msg['content']['text'],
                                    'type' => "text"
                                ],
                                [
                                    'image_url' => $url,
                                    'type' => 'image_url'
                                ]
                            ]
                        ];
                    }
                    else{
                        $files = $storageService->retrieveOutputFilesByType($uuid, 'private', 'md');
                        foreach ($files as $fileData) {
                            $fileContent = $fileData['contents'];
                            // Compose an explicit context message - adapt roles as needed
                            $html_safe = htmlspecialchars($fileContent);
                            $newMessages[] = [
                                'role' => 'user',
                                'content' => [
                                    'text' => "ATTACHED FILE CONTEXT: \"{$model->filename}\"
                                    ---
                                    {$html_safe}
                                    ---"
                                    ]
                                ];
                        }

                    }
                }
            }
            // Add the original message itself, now that its context has been inserted
            $newMessages[] = $msg;


        }

        // Replace messages in payload
        $payload['messages'] = $newMessages;
        return $payload;
    }


    private function convertDocumentToMessage(StorageServiceFactory $storageService){

        $files = $storageService->retrieveOutputFilesByType($uuid, 'private', 'md');
        foreach ($files as $fileData) {
            $fileContent = $fileData['contents'];
            // Compose an explicit context message - adapt roles as needed
            $html_safe = htmlspecialchars($fileContent);
            $newMessages[] = [
                'role' => 'user',
                'content' => [
                    'text' => "ATTACHED FILE CONTEXT: \"{$model->filename}\"
                    ---
                    {$html_safe}
                    ---"
                    ]
                ];
        }




    }



}
