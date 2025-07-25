<?php
namespace App\Services\Attachment\Handlers;

use App\Services\Attachment\Interfaces\AttachmentInterface;

use App\Models\AiConvMsg;
use App\Models\Message;
use App\Models\Attachment;

use App\Services\StorageServices\StorageServiceFactory;
use App\Toolkit\FileConverter\DocumentConverter;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AtchDocumentHandler implements AttachmentInterface
{
    protected $storageService;
    public function __construct(){
        $this->storageService = StorageServiceFactory::create();
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
