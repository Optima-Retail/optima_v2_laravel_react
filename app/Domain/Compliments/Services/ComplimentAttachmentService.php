<?php

declare(strict_types=1);

namespace App\Domain\Compliments\Services;

use App\Models\Compliment;
use App\Models\ComplimentAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ComplimentAttachmentService
{
    private const DISK = 'local';

    /**
     * @return list<array<string, mixed>>
     */
    public function listForCompliment(Compliment $compliment): array
    {
        return $compliment->attachments()
            ->with('uploader:id,name')
            ->get()
            ->map(fn (ComplimentAttachment $attachment): array => $this->toListItem($compliment, $attachment))
            ->values()
            ->all();
    }

    public function store(Compliment $compliment, User $uploader, UploadedFile $file): ComplimentAttachment
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $directory = 'compliments/'.$compliment->id.'/attachments/'.now()->format('Y-m');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, self::DISK);

        return $compliment->attachments()->create([
            'name' => $file->getClientOriginalName() ?: $filename,
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: null,
            'uploaded_by' => $uploader->id,
        ]);
    }

    public function delete(Compliment $compliment, ComplimentAttachment $attachment): void
    {
        abort_unless($attachment->compliment_id === $compliment->id, 404);

        DB::transaction(function () use ($attachment): void {
            $path = $attachment->path;
            $attachment->delete();

            if ($path !== '' && Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        });
    }

    public function stream(Compliment $compliment, ComplimentAttachment $attachment, bool $inline = false): StreamedResponse
    {
        abort_unless($attachment->compliment_id === $compliment->id, 404);
        abort_if($attachment->path === '', 404);
        abort_unless(Storage::disk(self::DISK)->exists($attachment->path), 404);

        $mime = $attachment->mime_type
            ?: (Storage::disk(self::DISK)->mimeType($attachment->path) ?: 'application/octet-stream');

        $disposition = $inline ? 'inline' : 'attachment';

        return Storage::disk(self::DISK)->response(
            $attachment->path,
            $attachment->name,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => $disposition.'; filename="'.$attachment->name.'"',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Compliment $compliment, ComplimentAttachment $attachment): array
    {
        $downloadUrl = route('compliments.attachments.download', [
            'compliment' => $compliment,
            'attachment' => $attachment,
        ]);

        return [
            'id' => $attachment->id,
            'name' => $attachment->name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'uploaded_by_name' => $attachment->uploader?->name,
            'download_url' => $downloadUrl,
            'view_url' => $downloadUrl.'?inline=1',
            'is_image' => is_string($attachment->mime_type) && str_starts_with($attachment->mime_type, 'image/'),
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
