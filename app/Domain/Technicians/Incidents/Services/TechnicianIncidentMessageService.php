<?php

declare(strict_types=1);

namespace App\Domain\Technicians\Incidents\Services;

use App\Models\TechnicianIncident;
use App\Models\TechnicianIncidentMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TechnicianIncidentMessageService
{
    private const DISK = 'local';

    /**
     * @return list<array<string, mixed>>
     */
    public function listForIncident(TechnicianIncident $incident, ?User $viewer = null): array
    {
        return $incident->messages()
            ->with('user:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (TechnicianIncidentMessage $message): array => $this->toListItem($message, $incident, $viewer))
            ->values()
            ->all();
    }

    public function createText(TechnicianIncident $incident, User $user, string $body): TechnicianIncidentMessage
    {
        return DB::transaction(function () use ($incident, $user, $body): TechnicianIncidentMessage {
            $message = $incident->messages()->create([
                'user_id' => $user->id,
                'body' => $body,
                'type' => TechnicianIncidentMessage::TYPE_TEXT,
                'attachment_path' => null,
                'attachment_name' => null,
            ]);

            return $message->load('user:id,name');
        });
    }

    public function createFile(
        TechnicianIncident $incident,
        User $user,
        UploadedFile $file,
    ): TechnicianIncidentMessage {
        return DB::transaction(function () use ($incident, $user, $file): TechnicianIncidentMessage {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $directory = 'technician-incident-messages/'.$incident->id.'/'.now()->format('Y-m');
            $filename = Str::uuid()->toString().'.'.$extension;
            $path = $file->storeAs($directory, $filename, self::DISK);

            $message = $incident->messages()->create([
                'user_id' => $user->id,
                'body' => '',
                'type' => $this->typeFromExtension($extension),
                'attachment_path' => $path,
                'attachment_name' => $file->getClientOriginalName() ?: $filename,
            ]);

            return $message->load('user:id,name');
        });
    }

    /**
     * @deprecated Use createText() / createFile().
     *
     * @param  array{body: string, type?: string|null, user_id?: int|null}  $data
     */
    public function create(TechnicianIncident $incident, User $user, array $data): TechnicianIncidentMessage
    {
        return $this->createText($incident, $user, (string) $data['body']);
    }

    public function streamAttachment(
        TechnicianIncident $incident,
        TechnicianIncidentMessage $message,
    ): StreamedResponse {
        abort_unless((int) $message->technician_incident_id === (int) $incident->id, 404);
        abort_unless($message->isAttachment(), 404);
        abort_if($message->attachment_path === null || $message->attachment_path === '', 404);
        abort_unless(Storage::disk(self::DISK)->exists($message->attachment_path), 404);

        $mime = Storage::disk(self::DISK)->mimeType($message->attachment_path) ?: 'application/octet-stream';
        $filename = $message->attachment_name ?: basename($message->attachment_path);
        $disposition = $message->type === TechnicianIncidentMessage::TYPE_IMAGE ? 'inline' : 'attachment';

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
     * @return array<string, mixed>
     */
    public function toListItem(
        TechnicianIncidentMessage $message,
        TechnicianIncident $incident,
        ?User $viewer = null,
    ): array {
        $created = $message->created_at ?? now();
        $isAttachment = $message->isAttachment();
        $downloadName = $isAttachment
            ? ($message->attachment_name ?: basename((string) $message->attachment_path))
            : null;

        return [
            'id' => $message->id,
            'technician_incident_id' => $message->technician_incident_id,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'body' => $isAttachment ? '' : (string) ($message->body ?? ''),
            'type' => $message->type ?? TechnicianIncidentMessage::TYPE_TEXT,
            'preview_url' => $isAttachment
                ? route('technician-incidents.messages.file', [
                    'technician_incident' => $incident,
                    'technician_incident_message' => $message,
                ])
                : null,
            'download_name' => $downloadName,
            'is_mine' => $viewer !== null && (int) $message->user_id === (int) $viewer->id,
            'time' => $created->format('H:i'),
            'date' => $created->format('Y-m-d'),
            'date_label' => $this->dateLabel($created),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function typeFromExtension(string $extension): string
    {
        if (in_array($extension, ['jpeg', 'jpg', 'png', 'gif', 'bmp', 'svg', 'webp'], true)) {
            return TechnicianIncidentMessage::TYPE_IMAGE;
        }

        if ($extension === 'pdf') {
            return TechnicianIncidentMessage::TYPE_PDF;
        }

        if (in_array($extension, ['doc', 'docx'], true)) {
            return TechnicianIncidentMessage::TYPE_WORD;
        }

        return TechnicianIncidentMessage::TYPE_FILE;
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
