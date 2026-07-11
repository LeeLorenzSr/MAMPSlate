<?php
declare(strict_types=1);

/** Handles optional document, audio, and video files alongside image processing. */
final class MediaUploadProcessor
{
    public function __construct(private string $uploadsRoot, private ImageProcessor $images)
    {
    }

    public function process(array $file, int $maxImageWidth): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('The upload did not complete.');
        }

        $tmpPath = (string)$file['tmp_name'];
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo !== false && str_starts_with((string)($imageInfo['mime'] ?? ''), 'image/')) {
            return $this->images->processUpload($file, $maxImageWidth);
        }

        if (!class_exists('finfo')) {
            throw new RuntimeException('Non-image uploads require the PHP fileinfo extension.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath) ?: '';
        $feature = $this->featureForMime($mime);
        if ($feature === null || !feature($feature)) {
            throw new RuntimeException('This file type is disabled or not supported.');
        }
        $extension = $this->extensionForMime($mime);
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file((string)$file['tmp_name'], $this->uploadsRoot . '/' . $storedName)) {
            throw new RuntimeException('Could not store the upload.');
        }
        return [
            'stored_name' => $storedName,
            'original_name' => mb_substr(basename((string)$file['name']), 0, 255),
            'mime_type' => $mime,
            'file_size' => (int)$file['size'],
            'width' => null,
            'height' => null,
        ];
    }

    private function featureForMime(string $mime): ?string
    {
        return match ($mime) {
            'application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'media_documents',
            'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/flac' => 'media_audio',
            'video/mp4', 'video/webm', 'video/ogg' => 'media_video',
            default => null,
        };
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf', 'text/plain' => 'txt', 'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/wav', 'audio/x-wav' => 'wav', 'audio/mp4' => 'm4a', 'audio/flac' => 'flac',
            'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/ogg' => 'ogv',
            default => 'bin',
        };
    }
}
