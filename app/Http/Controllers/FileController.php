<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\StorageServices\StorageServiceFactory;
use App\Services\StorageServices\Interfaces\StorageServiceInterface;

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
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max file size
            'name' => 'required|string',
            'type' => 'required|string'
        ]);

        try {
            // Get the uploaded file
            $file = $request->file('file');
            $originalName = $request->input('name');
            
            // Generate a unique filename to prevent overwriting
            $uniqueFileName = $this->storageService->generateUniqueFilename($originalName);
            
            // Store file in user-specific directory
            $category = 'test'; // Can be configured based on file type or user requirements
            $userId = Auth::id(); // Get authenticated user ID
            $filePath = $userId ? $userId . '/' . $uniqueFileName : $uniqueFileName;
            
            // Store the file using the storage service
            $stored = $this->storageService->storeFile($file, $filePath, $category);
            error_log('stored');
            if (!$stored) {
                throw new \Exception('Failed to store file.');
            }
            
            // Get the URL for the stored file
            $fileUrl = $this->storageService->getFileUrl($filePath, $category);
            
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'fileUrl' => $fileUrl,
                'originalName' => $originalName,
                'storedName' => $uniqueFileName
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
}