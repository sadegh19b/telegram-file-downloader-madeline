<?php

namespace TelegramFileDownloaderMadeline;

class FileHandler
{
    private string $uploadDir;
    private string $baseUrl;
    private int $maxFileSize;
    private array $allowedMimeTypes;

    public function __construct()
    {
        $this->uploadDir = rtrim($_ENV['UPLOAD_DIR'], '/');
        $this->baseUrl = rtrim($_ENV['BASE_URL'], '/');
        $this->maxFileSize = (int)$_ENV['MAX_FILE_SIZE'];
        $this->allowedMimeTypes = $_ENV['ALLOWED_MIME_TYPES'] === '*' 
            ? ['*'] 
            : explode(',', $_ENV['ALLOWED_MIME_TYPES']);
        
        $this->ensureUploadDirectory();
    }

    private function ensureUploadDirectory(): void
    {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function saveFile(string $fileContent, string $filename, string $mimeType): array
    {
        // Validate file size
        if (strlen($fileContent) > $this->maxFileSize) {
            throw new \Exception("File size exceeds the maximum allowed size.");
        }

        // Validate mime type if specific types are set
        if ($this->allowedMimeTypes[0] !== '*' && !in_array($mimeType, $this->allowedMimeTypes)) {
            throw new \Exception("File type not allowed.");
        }

        // Generate safe filename
        $safeFilename = $this->generateSafeFilename($filename);
        $fullPath = $this->uploadDir . '/' . $safeFilename;

        // Save file
        if (file_put_contents($fullPath, $fileContent) === false) {
            throw new \Exception("Failed to save file.");
        }

        return [
            'filename' => $safeFilename,
            'url' => $this->baseUrl . '/' . $safeFilename,
            'size' => strlen($fileContent),
            'mime_type' => $mimeType
        ];
    }

    private function generateSafeFilename(string $filename): string
    {
        // Remove any path components
        $filename = basename($filename);
        
        // Add timestamp to ensure uniqueness
        $info = pathinfo($filename);
        return sprintf(
            '%s_%s.%s',
            preg_replace('/[^a-zA-Z0-9_-]/', '', $info['filename']),
            time(),
            $info['extension'] ?? 'bin'
        );
    }
} 