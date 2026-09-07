<?php

declare(strict_types=1);

namespace App\Domain\Config\Brands\Services;

use App\Models\Brand;
use App\Models\BrandMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BrandMessageService
{
    private const DISK = 'local';

    /**
     * @return list<array{
     *     id: int,
     *     body: string,
     *     type: string,
     *     preview_url: string|null,
     *     download_name: string|null,
     *     user_id: int|null,
     *     user_name: string,
     *     is_mine: bool,
     *     time: string,
     *     date: string,
     *     date_label: string,
     *     created_at: string|null
     * }>
     */
    public function listForBrand(Brand $brand, ?User $viewer = null): array
    {
        $canViewFiles = $viewer?->can('viewMessageFiles', $brand) ?? false;
        $canDownloadFiles = $viewer?->can('downloadMessageFiles', $brand) ?? false;

        return $brand->messages()
            ->with('user:id,name')
            ->get()
            ->filter(function (BrandMessage $message) use ($canViewFiles): bool {
                if (! $message->isAttachment()) {
                    return true;
                }

                return $canViewFiles;
            })
            ->map(fn (BrandMessage $message): array => $this->toListItem(
                $message,
                $brand,
                $viewer,
                $canDownloadFiles,
            ))
            ->values()
            ->all();
    }

    public function createText(Brand $brand, User $author, string $body): BrandMessage
    {
        return $brand->messages()->create([
            'user_id' => $author->id,
            'body' => $body,
            'type' => BrandMessage::TYPE_TEXT,
            'attachment_path' => null,
            'attachment_name' => null,
        ]);
    }

    public function createFile(Brand $brand, User $author, UploadedFile $file): BrandMessage
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $directory = 'brand-messages/'.$brand->id.'/'.now()->format('Y-m');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, self::DISK);

        return $brand->messages()->create([
            'user_id' => $author->id,
            'body' => null,
            'type' => $this->typeFromExtension($extension),
            'attachment_path' => $path,
            'attachment_name' => $file->getClientOriginalName() ?: $filename,
        ]);
    }

    public function streamAttachment(Brand $brand, BrandMessage $message): StreamedResponse
    {
        abort_unless($message->brand_id === $brand->id, 404);
        abort_unless($message->isAttachment(), 404);
        abort_if($message->attachment_path === null || $message->attachment_path === '', 404);
        abort_unless(Storage::disk(self::DISK)->exists($message->attachment_path), 404);

        $mime = Storage::disk(self::DISK)->mimeType($message->attachment_path) ?: 'application/octet-stream';
        $filename = $message->attachment_name ?: basename($message->attachment_path);
        $disposition = $message->type === BrandMessage::TYPE_IMAGE ? 'inline' : 'attachment';

        return Storage::disk(self::DISK)->response(
            $message->attachment_path,
            $filename,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * @return array{
     *     id: int,
     *     body: string,
     *     type: string,
     *     preview_url: string|null,
     *     download_name: string|null,
     *     user_id: int|null,
     *     user_name: string,
     *     is_mine: bool,
     *     time: string,
     *     date: string,
     *     date_label: string,
     *     created_at: string|null
     * }
     */
    public function toListItem(
        BrandMessage $message,
        Brand $brand,
        ?User $viewer = null,
        bool $canDownloadFiles = false,
    ): array {
        $created = $message->created_at ?? now();
        $isAttachment = $message->isAttachment();
        $downloadName = $isAttachment
            ? ($message->attachment_name ?: basename((string) $message->attachment_path))
            : null;

        return [
            'id' => $message->id,
            'body' => $isAttachment ? '' : (string) ($message->body ?? ''),
            'type' => $message->type,
            'preview_url' => $isAttachment && $canDownloadFiles
                ? route('brands.messages.file', ['brand' => $brand, 'brandMessage' => $message])
                : null,
            'download_name' => $downloadName,
            'user_id' => $message->user_id,
            'user_name' => (string) ($message->user?->name ?? ''),
            'is_mine' => $viewer !== null && $message->user_id === $viewer->id,
            'time' => $created->format('H:i'),
            'date' => $created->format('Y-m-d'),
            'date_label' => $this->dateLabel($created),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function typeFromExtension(string $extension): string
    {
        if (in_array($extension, ['jpeg', 'jpg', 'png', 'gif', 'bmp', 'svg', 'webp'], true)) {
            return BrandMessage::TYPE_IMAGE;
        }

        if ($extension === 'pdf') {
            return BrandMessage::TYPE_PDF;
        }

        if (in_array($extension, ['doc', 'docx'], true)) {
            return BrandMessage::TYPE_WORD;
        }

        return BrandMessage::TYPE_FILE;
    }

    private function dateLabel(Carbon $date): string
    {
        if ($date->isToday()) {
            return 'today';
        }

        if ($date->isYesterday()) {
            return 'yesterday';
        }

        return $date->format('d/m/Y');
    }
}
