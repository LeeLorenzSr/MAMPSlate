<?php
declare(strict_types=1);

/**
 * Validates and stores uploaded image files using the GD extension.
 *
 * Files are stored under {uploadsRoot}/{YYYY}/{MM}/{random}.{ext} and optionally
 * downscaled to a maximum width. Returns metadata for persisting in the media table.
 */
final class ImageProcessor
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(private string $uploadsRoot)
    {
    }

    /**
     * @return array{stored_name: string, original_name: string, mime_type: string, file_size: int, width: int, height: int}
     * @throws RuntimeException When the upload is invalid or cannot be processed.
     */
    public function processUpload(array $file, int $maxWidth): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed. Please try again.');
        }

        $tmpPath = $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Invalid upload.');
        }

        $info = @getimagesize($tmpPath);
        if ($info === false) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }

        $mimeType = $info['mime'];
        if (!isset(self::ALLOWED_MIME[$mimeType])) {
            throw new RuntimeException('Unsupported image type. Use JPEG, PNG, GIF, or WebP.');
        }

        $extension = self::ALLOWED_MIME[$mimeType];
        $width = (int)$info[0];
        $height = (int)$info[1];

        $yearMonth = date('Y/m');
        $targetDir = rtrim($this->uploadsRoot, '/') . '/' . $yearMonth;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
            throw new RuntimeException('Could not create upload directory.');
        }

        $storedName = $yearMonth . '/' . bin2hex(random_bytes(12)) . '.' . $extension;
        $targetPath = rtrim($this->uploadsRoot, '/') . '/' . $storedName;

        if ($maxWidth > 0 && $width > $maxWidth) {
            $this->resizedSave($tmpPath, $mimeType, $targetPath, $width, $height, $maxWidth);
            clearstatcache(true, $targetPath);
            $newInfo = @getimagesize($targetPath);
            if ($newInfo !== false) {
                $width = (int)$newInfo[0];
                $height = (int)$newInfo[1];
            }
        } else {
            if (!@move_uploaded_file($tmpPath, $targetPath)) {
                throw new RuntimeException('Could not save the uploaded file.');
            }
        }

        return [
            'stored_name' => $storedName,
            'original_name' => $file['name'],
            'mime_type' => $mimeType,
            'file_size' => (int)filesize($targetPath),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Process an image from a local filesystem path (e.g. an MCP base64 upload
     * written to a temp file). Same validation/storage as processUpload, but
     * does not require a real HTTP upload.
     */
    public function processLocalFile(string $path, string $originalName, int $maxWidth): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Source image not found.');
        }

        $info = @getimagesize($path);
        if ($info === false) {
            throw new RuntimeException('The file is not a valid image.');
        }

        $mimeType = $info['mime'];
        if (!isset(self::ALLOWED_MIME[$mimeType])) {
            throw new RuntimeException('Unsupported image type. Use JPEG, PNG, GIF, or WebP.');
        }

        $extension = self::ALLOWED_MIME[$mimeType];
        $width = (int)$info[0];
        $height = (int)$info[1];

        $yearMonth = date('Y/m');
        $targetDir = rtrim($this->uploadsRoot, '/') . '/' . $yearMonth;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
            throw new RuntimeException('Could not create upload directory.');
        }

        $storedName = $yearMonth . '/' . bin2hex(random_bytes(12)) . '.' . $extension;
        $targetPath = rtrim($this->uploadsRoot, '/') . '/' . $storedName;

        if ($maxWidth > 0 && $width > $maxWidth) {
            $this->resizedSave($path, $mimeType, $targetPath, $width, $height, $maxWidth);
            clearstatcache(true, $targetPath);
            $newInfo = @getimagesize($targetPath);
            if ($newInfo !== false) {
                $width = (int)$newInfo[0];
                $height = (int)$newInfo[1];
            }
        } elseif (!@copy($path, $targetPath)) {
            throw new RuntimeException('Could not save the image.');
        }

        return [
            'stored_name' => $storedName,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'file_size' => (int)filesize($targetPath),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Validate and store an uploaded image as a square avatar under
     * {uploadsRoot}/profilepics/{random}.{ext}, cropped to $size x $size.
     *
     * @return array{stored_name: string, original_name: string, mime_type: string, file_size: int, width: int, height: int}
     * @throws RuntimeException When the upload is invalid or cannot be processed.
     */
    public function processAvatarUpload(array $file, int $size): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed. Please try again.');
        }

        $tmpPath = $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Invalid upload.');
        }

        $info = @getimagesize($tmpPath);
        if ($info === false) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }

        $mimeType = $info['mime'];
        if (!isset(self::ALLOWED_MIME[$mimeType])) {
            throw new RuntimeException('Unsupported image type. Use JPEG, PNG, GIF, or WebP.');
        }

        $extension = self::ALLOWED_MIME[$mimeType];
        $srcWidth = (int)$info[0];
        $srcHeight = (int)$info[1];

        $targetDir = rtrim($this->uploadsRoot, '/') . '/profilepics';
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
            throw new RuntimeException('Could not create avatar directory.');
        }

        $storedName = 'profilepics/' . bin2hex(random_bytes(12)) . '.' . $extension;
        $targetPath = rtrim($this->uploadsRoot, '/') . '/' . $storedName;

        $source = $this->createImage($tmpPath, $mimeType);
        if ($source === null) {
            throw new RuntimeException('Could not read avatar image.');
        }

        // Center-crop to a square, then resize to $size.
        $side = min($srcWidth, $srcHeight);
        $offsetX = (int)(($srcWidth - $side) / 2);
        $offsetY = (int)(($srcHeight - $side) / 2);

        $resized = imagecreatetruecolor($size, $size);
        if ($mimeType === 'image/png' || $mimeType === 'image/gif' || $mimeType === 'image/webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $size, $size, $transparent);
        }

        imagecopyresampled($resized, $source, 0, 0, $offsetX, $offsetY, $size, $size, $side, $side);
        $this->saveImage($resized, $mimeType, $targetPath);
        imagedestroy($source);
        imagedestroy($resized);

        clearstatcache(true, $targetPath);

        return [
            'stored_name' => $storedName,
            'original_name' => $file['name'],
            'mime_type' => $mimeType,
            'file_size' => (int)filesize($targetPath),
            'width' => $size,
            'height' => $size,
        ];
    }

    /**
     * Validate and store an uploaded cover banner under
     * {uploadsRoot}/coverpics/{random}.{ext}, downscaled to $maxWidth while
     * preserving aspect ratio (no cropping). Mirrors processAvatarUpload's
     * security checks (uploaded-file guard, MIME whitelist, random filename).
     *
     * @return array{stored_name: string, original_name: string, mime_type: string, file_size: int, width: int, height: int}
     * @throws RuntimeException When the upload is invalid or cannot be processed.
     */
    public function processCoverUpload(array $file, int $maxWidth): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed. Please try again.');
        }

        $tmpPath = $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Invalid upload.');
        }

        $info = @getimagesize($tmpPath);
        if ($info === false) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }

        $mimeType = $info['mime'];
        if (!isset(self::ALLOWED_MIME[$mimeType])) {
            throw new RuntimeException('Unsupported image type. Use JPEG, PNG, GIF, or WebP.');
        }

        $extension = self::ALLOWED_MIME[$mimeType];
        $width = (int)$info[0];
        $height = (int)$info[1];

        $targetDir = rtrim($this->uploadsRoot, '/') . '/coverpics';
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
            throw new RuntimeException('Could not create cover photo directory.');
        }

        $storedName = 'coverpics/' . bin2hex(random_bytes(12)) . '.' . $extension;
        $targetPath = rtrim($this->uploadsRoot, '/') . '/' . $storedName;

        if ($maxWidth > 0 && $width > $maxWidth) {
            $this->resizedSave($tmpPath, $mimeType, $targetPath, $width, $height, $maxWidth);
            clearstatcache(true, $targetPath);
            $newInfo = @getimagesize($targetPath);
            if ($newInfo !== false) {
                $width = (int)$newInfo[0];
                $height = (int)$newInfo[1];
            }
        } else {
            if (!@move_uploaded_file($tmpPath, $targetPath)) {
                throw new RuntimeException('Could not save the uploaded file.');
            }
        }

        return [
            'stored_name' => $storedName,
            'original_name' => $file['name'],
            'mime_type' => $mimeType,
            'file_size' => (int)filesize($targetPath),
            'width' => $width,
            'height' => $height,
        ];
    }

    private function resizedSave(string $tmpPath, string $mimeType, string $targetPath, int $width, int $height, int $maxWidth): void
    {
        $source = $this->createImage($tmpPath, $mimeType);
        if ($source === null) {
            throw new RuntimeException('Could not read image for resizing.');
        }

        $newWidth = $maxWidth;
        $newHeight = (int)round($height * ($maxWidth / $width));
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $this->saveImage($resized, $mimeType, $targetPath);
        imagedestroy($source);
        imagedestroy($resized);
    }

    private function createImage(string $path, string $mimeType): \GdImage|null
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => null,
        };
    }

    private function saveImage(\GdImage $image, string $mimeType, string $path): void
    {
        match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $path, 85),
            'image/png' => imagepng($image, $path, 6),
            'image/gif' => imagegif($image, $path),
            'image/webp' => imagewebp($image, $path, 85),
            default => null,
        };
    }
}
