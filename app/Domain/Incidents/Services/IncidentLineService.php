<?php

declare(strict_types=1);

namespace App\Domain\Incidents\Services;

use App\Domain\Incidents\Support\IncidentLineStatusRules;
use App\Models\Incident;
use App\Models\IncidentLine;
use App\Models\IncidentStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IncidentLineService
{
    public function __construct(
        private readonly IncidentLineStatusRules $lineStatusRules,
    ) {}

    /**
     * @param  array{comment: string, incident_status_id: int}  $data
     */
    public function create(Incident $incident, User $user, array $data): IncidentLine
    {
        if (! $this->lineStatusRules->isStatusAllowedForType(
            $incident->incident_type_id !== null ? (int) $incident->incident_type_id : null,
            (int) $data['incident_status_id'],
        )) {
            throw ValidationException::withMessages([
                'incident_status_id' => [__('validation.in', ['attribute' => 'incident_status_id'])],
            ]);
        }

        return DB::transaction(function () use ($incident, $user, $data): IncidentLine {
            $incident->loadMissing(['lines' => fn ($query) => $query->orderByDesc('ended_at')->orderByDesc('id')]);

            $previous = $incident->lines->first();
            $startedAt = $previous?->ended_at ?? $incident->created_at ?? now();
            $endedAt = now();

            $durationMinutes = max(
                0,
                (int) Carbon::parse($startedAt)->diffInMinutes(Carbon::parse($endedAt), true),
            );

            $line = IncidentLine::query()->create([
                'incident_id' => $incident->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'comment' => $data['comment'],
                'incident_status_id' => $data['incident_status_id'],
                'user_id' => $user->id,
                'duration_minutes' => $durationMinutes,
            ]);

            $status = IncidentStatus::query()->find($data['incident_status_id']);

            $incident->incident_status_id = $data['incident_status_id'];

            if ($status !== null && ! $status->is_open && $incident->closed_at === null) {
                $incident->closed_at = $endedAt;
            }

            if ($status !== null && $status->is_open) {
                $incident->closed_at = null;
            }

            $incident->duration_seconds = ((int) IncidentLine::query()
                ->where('incident_id', $incident->id)
                ->sum('duration_minutes')) * 60;

            $incident->save();

            return $line->load(['user', 'status']);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForIncident(Incident $incident): array
    {
        return $incident->lines()
            ->with(['user', 'status'])
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (IncidentLine $line): array => $this->toListItem($line))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(IncidentLine $line): array
    {
        return [
            'id' => $line->id,
            'incident_id' => $line->incident_id,
            'comment' => $line->comment,
            'incident_status_id' => $line->incident_status_id,
            'status_name' => $line->status?->name,
            'status_color' => $line->status?->color,
            'user_id' => $line->user_id,
            'user_name' => $line->user?->name,
            'started_at' => $line->started_at?->toIso8601String(),
            'ended_at' => $line->ended_at?->toIso8601String(),
            'duration_minutes' => $line->duration_minutes,
            'created_at' => $line->created_at?->toIso8601String(),
        ];
    }
}
