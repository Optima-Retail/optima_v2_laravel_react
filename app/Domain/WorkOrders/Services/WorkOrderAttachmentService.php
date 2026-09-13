<?php

declare(strict_types=1);

namespace App\Domain\WorkOrders\Services;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class WorkOrderAttachmentService
{
    private const DISK = 'local';

    /**
     * @return list<array<string, mixed>>
     */
    public function listForWorkOrder(WorkOrder $workOrder): array
    {
        return $workOrder->attachments()
            ->with('uploader:id,name')
            ->get()
            ->map(fn (WorkOrderAttachment $attachment): array => $this->toListItem($workOrder, $attachment))
            ->values()
            ->all();
    }

    public function store(WorkOrder $workOrder, User $uploader, UploadedFile $file): WorkOrderAttachment
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $directory = 'work-orders/'.$workOrder->id.'/attachments/'.now()->format('Y-m');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, self::DISK);

        return $workOrder->attachments()->create([
            'name' => $file->getClientOriginalName() ?: $filename,
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: null,
            'uploaded_by' => $uploader->id,
        ]);
    }

    public function delete(WorkOrder $workOrder, WorkOrderAttachment $attachment): void
    {
        abort_unless($attachment->work_order_id === $workOrder->id, 404);

        DB::transaction(function () use ($attachment): void {
            $path = $attachment->path;
            $attachment->delete();

            if ($path !== '' && Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        });
    }

    public function stream(WorkOrder $workOrder, WorkOrderAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->work_order_id === $workOrder->id, 404);
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
    public function toListItem(WorkOrder $workOrder, WorkOrderAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'uploaded_by_name' => $attachment->uploader?->name,
            'download_url' => $workOrder->isEstimate()
                ? route('estimates.attachments.download', [
                    'estimate' => $workOrder,
                    'attachment' => $attachment,
                ])
                : route('work-orders.attachments.download', [
                    'work_order' => $workOrder,
                    'attachment' => $attachment,
                ]),
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
