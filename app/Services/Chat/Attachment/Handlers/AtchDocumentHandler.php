<?php
namespace App\Services\Chat\Attachment\Handlers;

use Illuminate\Support\Str;

use App\Services\Storage\FileStorageService;
use App\Services\Chat\Attachment\Interfaces\AttachmentInterface;
use App\Toolkit\FileConverter\DocumentConverter;


class AtchDocumentHandler implements AttachmentInterface
{
    public function __construct(
        protected FileStorageService $storageService
    ){
    }

    public function store($file, string $category): array
    {
        // Generate a unique filename to prevent overwriting
        $uuid = Str::uuid();
        $originalName = $file->getClientOriginalName();

        $stored = $this->storageService->storeFile($file, $originalName, $uuid, $category);
        if (!$stored) {
            return [
                'success' => false,
                'message'=> 'Failed to store file.'
            ];
            // throw new \Exception('Failed to store file.');
        }
        $url = $this->storageService->getFileUrl($uuid, $category);
        $results = $this->extractFileContent($file);
        if (!$results) {
            return [
                'success' => false,
                'message'=> 'Failed to extract text from file'
            ];
            // throw new \Exception('Failed to store file.');
        }

        foreach($results as $relativePath => $content){
            $filename = 'output/' . basename($relativePath);
            $stored = $this->storageService->storeFile($content, $filename, $uuid, $category);
        }

        return [
            'success' => true,
            'uuid' => $uuid,
            'url'=> $url
        ];
    }

    public function extractFileContent($file): ?array{
        try{
            $fileConverter = new DocumentConverter();
            $results = $fileConverter->requestDocumentToMarkdown($file);
            return $results;
        }
        catch(\Exception $e){
            return null;
        }
    }

    public function retrieveContext(string $uuid, string $category, $fileType = 'md'): ?string{
        $files = $this->storageService->retrieveOutputFilesByType($uuid, 'private', $fileType);
        $results = [];
        foreach($files as $file){
            $content = $file['contents'];
            $html_safe = htmlspecialchars($content);
            $results[] = $html_safe;
        }
        return $results[0];
    }

}
