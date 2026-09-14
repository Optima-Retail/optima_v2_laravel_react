<?php

declare(strict_types=1);

namespace App\Support\Attachments;

final class AttachmentMime
{
    /**
     * @param  list<string>  $imageExtensions
     */
    public static function isImage(?string $mimeType, ?string $filename = null, array $imageExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg',
    ]): bool
    {
        if (is_string($mimeType) && str_starts_with(strtolower($mimeType), 'image/')) {
            return true;
        }

        if ($filename === null || $filename === '') {
            return false;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, $imageExtensions, true);
    }

    /**
     * @return array{view_url: string, is_image: bool}
     */
    public static function previewFields(string $downloadUrl, ?string $mimeType, ?string $filename = null): array
    {
        $isImage = self::isImage($mimeType, $filename);

        return [
            'view_url' => $isImage ? $downloadUrl.(str_contains($downloadUrl, '?') ? '&' : '?').'inline=1' : $downloadUrl,
            'is_image' => $isImage,
        ];
    }
}
