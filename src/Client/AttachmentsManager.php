<?php

declare(strict_types=1);

namespace SapB1\Client;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use SapB1\Exceptions\AttachmentException;

class AttachmentsManager
{
    protected SapB1Client $client;

    /**
     * Create a new AttachmentsManager instance.
     */
    public function __construct(SapB1Client $client)
    {
        $this->client = $client;
    }

    /**
     * Upload a file attachment.
     *
     * @param  string  $endpoint  The entity endpoint (e.g., 'Orders')
     * @param  mixed  $key  The entity key
     * @param  UploadedFile|string  $file  The file to upload (UploadedFile or file path)
     * @param  string|null  $fileName  Optional custom filename
     * @return array<string, mixed> The attachment entry data
     *
     * @throws AttachmentException
     */
    public function upload(string $endpoint, mixed $key, UploadedFile|string $file, ?string $fileName = null): array
    {
        $this->validateFile($file);

        // Get file content and name
        if ($file instanceof UploadedFile) {
            $content = $file->getContent();
            $fileName = $fileName ?? $file->getClientOriginalName();
        } else {
            if (! File::exists($file)) {
                throw new AttachmentException("File not found: {$file}");
            }
            $content = File::get($file);
            $fileName = $fileName ?? basename($file);
        }

        // Create attachment entry
        $attachmentResponse = $this->client->post('Attachments2', [
            'AttachmentsLines' => [
                [
                    'FileName' => pathinfo($fileName, PATHINFO_FILENAME),
                    'FileExtension' => pathinfo($fileName, PATHINFO_EXTENSION),
                ],
            ],
        ]);

        if (! $attachmentResponse->successful()) {
            throw new AttachmentException('Failed to create attachment entry');
        }

        $attachmentEntry = $attachmentResponse->json('AbsoluteEntry');

        // Upload the file content
        $uploadResponse = $this->uploadFileContent($attachmentEntry, $content, $fileName);

        // Link attachment to entity
        if ($endpoint && $key) {
            $this->linkToEntity($endpoint, $key, $attachmentEntry);
        }

        return [
            'AbsoluteEntry' => $attachmentEntry,
            'FileName' => $fileName,
        ];
    }

    /**
     * Upload file content to an attachment entry.
     *
     * @throws AttachmentException
     */
    protected function uploadFileContent(int $attachmentEntry, string $content, string $fileName): Response
    {
        // SAP B1 uses a specific endpoint for file uploads
        $response = $this->client->post("Attachments2({$attachmentEntry})/Attachments2_Content", [
            'Content' => base64_encode($content),
        ]);

        if (! $response->successful()) {
            throw new AttachmentException('Failed to upload file content');
        }

        return $response;
    }

    /**
     * Link an attachment to an entity.
     *
     * @throws AttachmentException
     */
    protected function linkToEntity(string $endpoint, mixed $key, int $attachmentEntry): void
    {
        // Format the key
        $formattedKey = is_string($key) ? "'{$key}'" : $key;

        $response = $this->client->patch("{$endpoint}({$formattedKey})", [
            'AttachmentEntry' => $attachmentEntry,
        ]);

        if (! $response->successful()) {
            throw new AttachmentException("Failed to link attachment to {$endpoint}");
        }
    }

    /**
     * Download an attachment.
     *
     * @return string The file content
     *
     * @throws AttachmentException
     */
    public function download(int $attachmentEntry, ?string $savePath = null): string
    {
        $response = $this->client->get("Attachments2({$attachmentEntry})");

        if (! $response->successful()) {
            throw new AttachmentException("Attachment not found: {$attachmentEntry}");
        }

        $lines = $response->json('AttachmentsLines');
        if (empty($lines)) {
            throw new AttachmentException('No attachment lines found');
        }

        $line = $lines[0];
        $fileName = $line['FileName'].'.'.$line['FileExtension'];

        // Get the file content
        $contentResponse = $this->client->get("Attachments2({$attachmentEntry})/Attachments2_Content");

        if (! $contentResponse->successful()) {
            throw new AttachmentException('Failed to download attachment content');
        }

        $content = $contentResponse->json('Content');
        $decodedContent = base64_decode($content);

        // Save to file if path provided
        if ($savePath !== null) {
            $fullPath = rtrim($savePath, '/').'/'.$fileName;
            File::put($fullPath, $decodedContent);
        }

        return $decodedContent;
    }

    /**
     * List attachments for an entity.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws AttachmentException
     */
    public function list(string $endpoint, mixed $key): array
    {
        // Format the key
        $formattedKey = is_string($key) ? "'{$key}'" : $key;

        $response = $this->client->get("{$endpoint}({$formattedKey})");

        if (! $response->successful()) {
            throw new AttachmentException("Entity not found: {$endpoint}({$key})");
        }

        $attachmentEntry = $response->json('AttachmentEntry');

        if ($attachmentEntry === null || $attachmentEntry === -1) {
            return [];
        }

        $attachmentResponse = $this->client->get("Attachments2({$attachmentEntry})");

        if (! $attachmentResponse->successful()) {
            return [];
        }

        return $attachmentResponse->json('AttachmentsLines') ?? [];
    }

    /**
     * Delete an attachment.
     *
     * @throws AttachmentException
     */
    public function delete(int $attachmentEntry): bool
    {
        $response = $this->client->rawDelete("Attachments2({$attachmentEntry})");

        if (! $response->successful()) {
            throw new AttachmentException("Failed to delete attachment: {$attachmentEntry}");
        }

        return true;
    }

    /**
     * Get attachment metadata.
     *
     * @return array<string, mixed>
     *
     * @throws AttachmentException
     */
    public function metadata(int $attachmentEntry): array
    {
        $response = $this->client->get("Attachments2({$attachmentEntry})");

        if (! $response->successful()) {
            throw new AttachmentException("Attachment not found: {$attachmentEntry}");
        }

        return $response->json() ?? [];
    }

    /**
     * Validate the file before upload.
     *
     * @throws AttachmentException
     */
    protected function validateFile(UploadedFile|string $file): void
    {
        /** @var int $maxSize */
        $maxSize = config('sap-b1.attachments.max_size', 10 * 1024 * 1024);

        /** @var array<int, string> $allowedExtensions */
        $allowedExtensions = config('sap-b1.attachments.allowed_extensions', []);

        if ($file instanceof UploadedFile) {
            $size = $file->getSize();
            $extension = strtolower($file->getClientOriginalExtension());
        } else {
            if (! File::exists($file)) {
                throw new AttachmentException("File not found: {$file}");
            }
            $size = File::size($file);
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        }

        if ($size > $maxSize) {
            throw new AttachmentException("File size exceeds maximum allowed: {$maxSize} bytes");
        }

        if (! empty($allowedExtensions) && ! in_array($extension, $allowedExtensions, true)) {
            throw new AttachmentException("File extension not allowed: {$extension}");
        }
    }
}
