<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Incidents;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Chats\Services\DocumentChatService;
use App\Domain\Incidents\Services\IncidentLineService;
use App\Domain\Incidents\Services\IncidentService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Incidents\StoreIncidentLineRequest;
use App\Http\Requests\Web\Incidents\StoreIncidentRequest;
use App\Http\Requests\Web\Incidents\UpdateIncidentRequest;
use App\Models\Incident;
use App\Models\IncidentStatus;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IncidentController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly IncidentService $incidents,
        private readonly IncidentLineService $incidentLines,
        private readonly DocumentChatService $chats,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Incident::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'incident_status_id' => $request->string('incident_status_id')->trim()->toString(),
            'created_from' => $request->string('created_from')->trim()->toString(),
            'created_to' => $request->string('created_to')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 25),
            ]),
        ];

        return Inertia::render('Incidents/Index', [
            'filters' => $filters,
            'incidentStatusOptions' => IncidentStatus::query()
                ->orderBy('lifecycle')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (IncidentStatus $status): array => [
                    'id' => $status->id,
                    'label' => $status->name,
                ])
                ->values()
                ->all(),
            'can' => [
                'create' => $request->user()?->can('create', Incident::class) ?? false,
                'update' => $request->user()?->can('incidents.update') ?? false,
                'delete' => $request->user()?->can('incidents.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Incident::class);

        $owner = $this->activeCompany($request);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'subject', 'control_at', 'closed_at', 'created_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search', 'incident_status_id', 'created_from', 'created_to'],
        );

        return TabulatorResponse::fromPaginator(
            $this->incidents->paginateForWeb($owner, $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Incident::class);

        return Inertia::render('Incidents/Create', [
            'defaultIncidentStatusId' => $this->incidents->defaultIncidentStatusId(),
            'defaultRequesterUserId' => $request->user()?->id,
            'typeWorkflow' => $this->incidents->typeWorkflow(),
            'incidentStatusOptions' => $this->incidents->incidentStatusOptions(),
            'incidentPriorityOptions' => $this->incidents->incidentPriorityOptions(),
            'incidentTypeOptions' => $this->incidents->incidentTypeOptions(),
            'incidentSubtypeOptions' => $this->incidents->incidentSubtypeOptions(),
            'userOptions' => [],
            'establishmentOptions' => [],
            'clientOptions' => [],
            'brandOptions' => [],
            'evaluationOptions' => [],
        ]);
    }

    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $owner = $this->activeCompany($request);
        $data = $request->validated();

        if (($data['requester_user_id'] ?? null) === null) {
            $data['requester_user_id'] = $request->user()?->id;
        }

        if (($data['control_at'] ?? null) === null) {
            $data['control_at'] = now()->format('Y-m-d H:i:s');
        }

        $record = $this->incidents->create($owner, $data);

        return redirect()
            ->route('incidents.edit', $record)
            ->with('success', 'incident_created_successfully');
    }

    public function edit(Request $request, Incident $incident): Response
    {
        $this->authorize('update', $incident);

        $owner = $this->activeCompany($request);
        $user = $request->user();
        $incident->loadMissing(['establishment:id,company_id', 'collaborators']);

        return Inertia::render('Incidents/Edit', [
            'incident' => $this->incidents->toFormData($incident),
            'lines' => $this->incidentLines->listForIncident($incident),
            'typeWorkflow' => $this->incidents->typeWorkflow(),
            'incidentStatusOptions' => $this->incidents->incidentStatusOptions(
                $incident->incident_status_id !== null ? (int) $incident->incident_status_id : null,
            ),
            'lineStatusOptions' => $this->incidents->selectableIncidentStatusOptions($incident),
            'incidentPriorityOptions' => $this->incidents->incidentPriorityOptions(),
            'incidentTypeOptions' => $this->incidents->incidentTypeOptions(),
            'incidentSubtypeOptions' => $this->incidents->incidentSubtypeOptions(),
            'userOptions' => $this->incidents->userOptions($owner, array_values(array_filter([
                ...$incident->collaborators->pluck('id')->map(fn ($id) => (int) $id)->all(),
                $incident->responsible_user_id !== null ? (int) $incident->responsible_user_id : null,
                $incident->qc_responsible_user_id !== null ? (int) $incident->qc_responsible_user_id : null,
                $incident->requester_user_id !== null ? (int) $incident->requester_user_id : null,
            ]))),
            'establishmentOptions' => $this->incidents->establishmentOptions(
                $owner,
                array_values(array_filter([
                    $incident->establishment_id !== null ? (int) $incident->establishment_id : null,
                    $incident->origin_type === 'establishment' && $incident->origin_id !== null
                        ? (int) $incident->origin_id
                        : null,
                ])),
            ),
            'clientOptions' => $this->incidents->clientOptions(
                $owner,
                $incident->origin_type === 'company' && $incident->origin_id !== null
                    ? (int) $incident->origin_id
                    : ($incident->establishment?->company_id !== null
                        ? (int) $incident->establishment->company_id
                        : null),
            ),
            'brandOptions' => $this->incidents->brandOptions(
                $owner,
                $incident->origin_type === 'brand' && $incident->origin_id !== null
                    ? (int) $incident->origin_id
                    : null,
            ),
            'evaluationOptions' => $this->incidents->evaluationOptions(
                $owner,
                $incident->related_type === 'evaluation' && $incident->related_id !== null
                    ? [(int) $incident->related_id]
                    : [],
            ),
            'chat' => $user !== null
                ? $this->chats->payload(ChatDocumentType::Incident, (int) $incident->id, $user)
                : null,
            'can' => [
                'delete' => $user?->can('delete', $incident) ?? false,
                'create_line' => $user?->can('update', $incident) ?? false,
                'post_chat' => $user?->can('update', $incident) ?? false,
            ],
        ]);
    }

    public function storeLine(StoreIncidentLineRequest $request, Incident $incident): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $this->incidentLines->create($incident, $user, $request->validated());

        return redirect()
            ->route('incidents.edit', $incident)
            ->with('success', 'incident_line_created_successfully');
    }

    public function update(UpdateIncidentRequest $request, Incident $incident): RedirectResponse
    {
        $this->incidents->update($incident, $request->validated());

        return redirect()
            ->route('incidents.edit', $incident)
            ->with('success', 'incident_updated_successfully');
    }

    public function destroy(Incident $incident): RedirectResponse
    {
        $this->authorize('delete', $incident);

        $this->incidents->delete($incident);

        return redirect()
            ->route('incidents.index')
            ->with('success', 'incident_deleted_successfully');
    }
}
