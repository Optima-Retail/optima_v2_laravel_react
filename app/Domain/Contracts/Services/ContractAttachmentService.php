<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Services;

use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ContractAttachmentService
{
    private const DISK = 'local';

    /**
     * @return list<array<string, mixed>>
     */
    public function listForContract(Contract $contract): array
    {
        return $contract->attachments()
            ->with('uploader:id,name')
            ->get()
            ->map(fn (ContractAttachment $attachment): array => $this->toListItem($contract, $attachment))
            ->values()
            ->all();
    }

    public function store(Contract $contract, User $uploader, UploadedFile $file): ContractAttachment
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $directory = 'contracts/'.$contract->id.'/attachments/'.now()->format('Y-m');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, self::DISK);

        return $contract->attachments()->create([
            'name' => $file->getClientOriginalName() ?: $filename,
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: null,
            'uploaded_by' => $uploader->id,
        ]);
    }

    public function delete(Contract $contract, ContractAttachment $attachment): void
    {
        abort_unless($attachment->contract_id === $contract->id, 404);

        DB::transaction(function () use ($attachment): void {
            $path = $attachment->path;
            $attachment->delete();

            if ($path !== '' && Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        });
    }

    public function stream(Contract $contract, ContractAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->contract_id === $contract->id, 404);
        abort_if($attachment->path === '', 404);
        abort_unless(Storage::disk(self::DISK)->exists($attachment->path), 404);

        $mime = $attachment->mime_type
            ?: (Storage::disk(self::DISK)->mimeType($attachment->path) ?: 'application/octet-stream');

        return Storage::disk(self::DISK)->response(
            $attachment->path,
            $attachment->name,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'attachment; filename="'.$attachment->name.'"',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Contract $contract, ContractAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'uploaded_by_name' => $attachment->uploader?->name,
            'download_url' => route('contracts.attachments.download', [
                'contract' => $contract,
                'attachment' => $attachment,
            ]),
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
