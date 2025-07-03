<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\StorageServices\StorageServiceFactory;
use App\Services\StorageServices\FileHandler;
use App\Services\StorageServices\Interfaces\StorageServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileController extends Controller
{
    protected $storageService;

    public function __construct()
    {
        // Use the factory to create the appropriate storage service
        $this->storageService = StorageServiceFactory::create();
    }

    /**
     * Handle file upload from the client
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleUploadedFile(Request $request)
    {
        // Validate uploaded file
        $validateData = $request->validate([
            'file' => 'required|file|max:10240', // 10MB max file size
            'category' => 'required|string',
        ]);

        try {
            // Get the uploaded file
            $file = $validateData['file'];
            $originalName = $file->getClientOriginalName();
            
            // Generate a unique filename to prevent overwriting
            $uuid = Str::uuid();
            
            // Store file in user-specific directory
            $category = $validateData['category'];
            $userId = Auth::id(); // Get authenticated user ID

            // Store the file
            $stored = $this->storageService->storeFile($file, $originalName, $uuid, $category);

            if (!$stored) {
                throw new \Exception('Failed to store file.');
            }

            $mimeType = $file->getMimeType();
            if(str_contains($mimeType, 'pdf') || str_contains($mimeType, 'word')){

                $fileHandler = new FileHandler();
                $results = $fileHandler->requestPdfToMarkdown($file);
        
                if ($results) {
                    foreach($results as $relativePath => $content){
                        $filename = 'output/' . basename($relativePath);
                        $stored = $this->storageService->storeFile($content, $filename, $uuid, $category);
                    }
                }
                else{
                    throw new \Exception('Failed to convert file.');
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'uuid' => $uuid,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a file from storage
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteFile(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'category' => 'nullable|string'
        ]);
        
        try {
            $filename = $request->input('filename');
            $category = $request->input('category', 'test');
            $userId = Auth::id();
            
            // Ensure users can only delete their own files
            if (!str_starts_with($filename, $userId . '/')) {
                $filename = $userId . '/' . $filename;
            }
            
            $deleted = $this->storageService->deleteFile($filename, $category);
            
            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'File deleted successfully' : 'File not found or could not be deleted'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file: ' . $e->getMessage()
            ], 500);
        }
    }





    public function createDownloadLinkToFile(Request $request){

        $validateData = $request->validate([
            'uuid' => 'required|uuid',
            'category' => 'required|string'
        ]);


        $url = $this->storageService->getFileUrl(
            $validateData['uuid'],
            $validateData['category']
        );

        if (!$url) {
            return response()->json([
                'success' => false,
                'message' => 'File not found or unauthorized access'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'url' => $url
        ]);

    }
}