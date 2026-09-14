<?php

declare(strict_types=1);

namespace App\Domain\StatusChanges\Services;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Chats\Services\DocumentChatService;
use App\Models\EvaluationStatus;
use App\Models\IncidentStatus;
use App\Models\StatusChangeHistory;
use App\Models\TechnicianIncident;
use App\Models\TechnicianIncidentStatus;
use App\Models\TechnicianRequestStatus;
use App\Models\User;
use App\Models\WorkOrderStatus;
use Illuminate\Support\Facades\Auth;

/**
 * Mirrors legacy HistorialCambioEstado + ProcesaCambiosEstado::registarCambioEnChat:
 * audit row + system line in the document chat History tab.
 *
 * Technician incidents have no own DocumentChat; status lines go to the technician
 * relationship chat (right sidebar on Technicians/Edit).
 */
final class StatusChangeHistoryService
{
    public const DOCUMENT_TECHNICIAN_INCIDENT = 'technician_incident';

    public function __construct(
        private readonly DocumentChatService $chats,
    ) {}

    public function record(
        ChatDocumentType $type,
        int $documentId,
        ?int $oldStatusId,
        ?int $newStatusId,
        ?User $actor = null,
        ?string $justification = null,
        ?float $priceDecreaseAmount = null,
    ): void {
        $this->persistAndPost(
            documentType: $type->value,
            documentId: $documentId,
            oldStatusId: $oldStatusId,
            newStatusId: $newStatusId,
            actor: $actor,
            justification: $justification,
            priceDecreaseAmount: $priceDecreaseAmount,
            chatType: $type,
            chatDocumentId: $documentId,
        );
    }

    public function recordTechnicianIncident(
        int $documentId,
        ?int $oldStatusId,
        ?int $newStatusId,
        ?User $actor = null,
        ?string $justification = null,
    ): void {
        $incident = TechnicianIncident::query()->find($documentId);
        $technicianId = $incident?->technician_id !== null ? (int) $incident->technician_id : null;

        $this->persistAndPost(
            documentType: self::DOCUMENT_TECHNICIAN_INCIDENT,
            documentId: $documentId,
            oldStatusId: $oldStatusId,
            newStatusId: $newStatusId,
            actor: $actor,
            justification: $justification,
            chatType: $technicianId !== null ? ChatDocumentType::Technician : null,
            chatDocumentId: $technicianId,
        );
    }

    private function persistAndPost(
        string $documentType,
        int $documentId,
        ?int $oldStatusId,
        ?int $newStatusId,
        ?User $actor,
        ?string $justification = null,
        ?float $priceDecreaseAmount = null,
        ?ChatDocumentType $chatType = null,
        ?int $chatDocumentId = null,
    ): void {
        if ($newStatusId === null) {
            return;
        }

        if ($oldStatusId !== null && $oldStatusId === $newStatusId) {
            return;
        }

        $actor ??= Auth::user() instanceof User ? Auth::user() : null;

        StatusChangeHistory::query()->create([
            'document_type' => $documentType,
            'document_id' => $documentId,
            'old_status_id' => $oldStatusId,
            'new_status_id' => $newStatusId,
            'user_id' => $actor?->id,
            'justification' => $justification,
            'price_decrease_amount' => $priceDecreaseAmount,
        ]);

        if ($chatType === null || $chatDocumentId === null) {
            return;
        }

        $newLabel = $this->statusLabel($documentType, $newStatusId);
        $isPrivate = $chatType !== ChatDocumentType::WorkOrder;
        [$actorArguments, $actorTranslatable] = $this->actorArguments($actor);

        if ($oldStatusId === null) {
            $this->chats->postSystem(
                type: $chatType,
                documentId: $chatDocumentId,
                templateKey: 'sistema.global.creado_con_estado',
                arguments: [
                    ...$actorArguments,
                    'estado_final' => $newLabel,
                ],
                translatableArguments: $actorTranslatable,
                isPrivate: $isPrivate,
                actor: $actor,
            );

            return;
        }

        $oldLabel = $this->statusLabel($documentType, $oldStatusId);
        $justification = trim((string) $justification);

        if ($justification !== '') {
            $this->chats->postSystem(
                type: $chatType,
                documentId: $chatDocumentId,
                templateKey: 'sistema.global.cambio_estado_con_motivo',
                arguments: [
                    ...$actorArguments,
                    'estado_origen' => $oldLabel,
                    'estado_final' => $newLabel,
                    'motivo' => $justification,
                ],
                translatableArguments: $actorTranslatable,
                isPrivate: $isPrivate,
                actor: $actor,
            );

            return;
        }

        $this->chats->postSystem(
            type: $chatType,
            documentId: $chatDocumentId,
            templateKey: 'sistema.global.cambio_estado',
            arguments: [
                ...$actorArguments,
                'estado_origen' => $oldLabel,
                'estado_final' => $newLabel,
            ],
            translatableArguments: $actorTranslatable,
            isPrivate: $isPrivate,
            actor: $actor,
        );
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, string>|null}
     */
    private function actorArguments(?User $actor): array
    {
        if ($actor?->name) {
            return [['usuario' => $actor->name], null];
        }

        return [[], ['usuario' => 'sistema.global.system_user']];
    }

    private function statusLabel(string $documentType, int $statusId): string
    {
        $name = match ($documentType) {
            ChatDocumentType::WorkOrder->value => WorkOrderStatus::query()->whereKey($statusId)->value('name'),
            ChatDocumentType::Incident->value => IncidentStatus::query()->whereKey($statusId)->value('name'),
            ChatDocumentType::Evaluation->value => EvaluationStatus::query()->whereKey($statusId)->value('name'),
            ChatDocumentType::TechnicianRequest->value => TechnicianRequestStatus::query()->whereKey($statusId)->value('name'),
            self::DOCUMENT_TECHNICIAN_INCIDENT => TechnicianIncidentStatus::query()->whereKey($statusId)->value('name'),
            default => null,
        };

        return is_string($name) && $name !== '' ? $name : (string) $statusId;
    }
}
