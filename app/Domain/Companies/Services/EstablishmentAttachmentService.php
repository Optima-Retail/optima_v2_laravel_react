<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Models\Establishment;
use App\Models\EstablishmentAttachment;
use App\Models\User;
use App\Support\Attachments\AttachmentMime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EstablishmentAttachmentService
{
    private const DISK = 'local';

    /**
     * @return list<array<string, mixed>>
     */
    public function listForEstablishment(Establishment $establishment, bool $includePrivate): array
    {
        return $establishment->attachments()
            ->with('uploader:id,name')
            ->when(! $includePrivate, fn ($query) => $query->where('is_private', false))
            ->get()
            ->map(fn (EstablishmentAttachment $attachment): array => $this->toListItem($establishment, $attachment))
            ->values()
            ->all();
    }

    public function store(
        Establishment $establishment,
        User $uploader,
        UploadedFile $file,
        bool $isPrivate = false,
    ): EstablishmentAttachment {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $directory = 'establishments/'.$establishment->id.'/attachments/'.now()->format('Y-m');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, self::DISK);

        return $establishment->attachments()->create([
            'name' => $file->getClientOriginalName() ?: $filename,
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: null,
            'is_private' => $isPrivate,
            'uploaded_by' => $uploader->id,
        ]);
    }

    public function delete(Establishment $establishment, EstablishmentAttachment $attachment): void
    {
        abort_unless($attachment->establishment_id === $establishment->id, 404);

        DB::transaction(function () use ($attachment): void {
            $path = $attachment->path;
            $attachment->delete();

            if ($path !== '' && Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        });
    }

    public function stream(Establishment $establishment, EstablishmentAttachment $attachment, bool $inline = false): StreamedResponse
    {
        abort_unless($attachment->establishment_id === $establishment->id, 404);
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
    public function toListItem(Establishment $establishment, EstablishmentAttachment $attachment): array
    {
        $downloadUrl = route('establishments.attachments.download', [
            'establishment' => $establishment,
            'attachment' => $attachment,
        ]);

        return [
            'id' => $attachment->id,
            'name' => $attachment->name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'is_private' => (bool) $attachment->is_private,
            'uploaded_by_name' => $attachment->uploader?->name,
            'download_url' => $downloadUrl,
            ...AttachmentMime::previewFields($downloadUrl, $attachment->mime_type, $attachment->name),
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
