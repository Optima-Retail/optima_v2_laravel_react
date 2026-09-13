<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Technicians;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Technicians\Incidents\Services\TechnicianIncidentMessageService;
use App\Domain\Technicians\Incidents\Services\TechnicianIncidentService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Technicians\StoreTechnicianIncidentMessageRequest;
use App\Http\Requests\Web\Technicians\StoreTechnicianIncidentRequest;
use App\Http\Requests\Web\Technicians\UpdateTechnicianIncidentStatusRequest;
use App\Http\Requests\Web\Technicians\VerifyTechnicianIncidentRequest;
use App\Models\CompanyRelationship;
use App\Models\TechnicianIncident;
use App\Models\TechnicianIncidentMessage;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TechnicianIncidentController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly TechnicianIncidentService $incidents,
        private readonly TechnicianIncidentMessageService $messages,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TechnicianIncident::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status_id' => $request->string('status_id')->trim()->toString(),
            'created_from' => $request->string('created_from')->trim()->toString(),
            'created_to' => $request->string('created_to')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('TechnicianIncidents/Index', [
            'filters' => $filters,
            'statusOptions' => $this->incidents->statusOptions(),
            'can' => [
                'view' => true,
                'create' => $request->user()?->can('create', TechnicianIncident::class) ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TechnicianIncident::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'due_at', 'responded_at', 'verified_at', 'created_at', 'is_verified'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search', 'technician_id', 'status_id', 'created_from', 'created_to'],
        );

        return TabulatorResponse::fromPaginator(
            $this->incidents->paginateForWeb($this->activeCompany($request), $filters),
        );
    }

    public function dataForTechnician(Request $request, CompanyRelationship $relationship): JsonResponse
    {
        abort_unless($relationship->kind === CompanyRelationshipKind::Technician, 404);
        $this->authorize('view', $relationship);
        $this->authorize('viewAny', TechnicianIncident::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'due_at', 'responded_at', 'verified_at', 'created_at', 'is_verified'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search'],
        );
        $filters['technician_id'] = $relationship->id;

        return TabulatorResponse::fromPaginator(
            $this->incidents->paginateForWeb($this->activeCompany($request), $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', TechnicianIncident::class);

        $owner = $this->activeCompany($request);
        $prefillTechnicianId = $request->integer('technician_id') ?: null;

        if ($prefillTechnicianId !== null) {
            $technician = CompanyRelationship::query()->find($prefillTechnicianId);
            abort_unless(
                $technician !== null
                    && $technician->kind === CompanyRelationshipKind::Technician
                    && (int) $technician->owner_company_id === (int) $owner->id,
                404,
            );
        }

        return Inertia::render('TechnicianIncidents/Create', [
            'defaultTechnicianId' => $prefillTechnicianId,
            'defaultRespondedById' => $request->user()?->id,
            'typeOptions' => $this->incidents->typeOptions(),
            'userOptions' => $this->incidents->userOptions($owner),
            'technicianOptions' => $this->incidents->technicianOptions($owner),
        ]);
    }

    public function store(StoreTechnicianIncidentRequest $request): RedirectResponse
    {
        $incident = $this->incidents->create($request->validated(), $request->user());

        return redirect()
            ->route('technicians.edit', [
                'relationship' => $incident->technician_id,
                'tab' => 'incidents',
                'incident' => $incident->id,
            ])
            ->with('success', 'technician_incident_created_successfully');
    }

    public function show(Request $request, TechnicianIncident $technicianIncident): JsonResponse
    {
        $this->authorize('view', $technicianIncident);

        return response()->json([
            'incident' => $this->incidents->toDetailData($technicianIncident),
            'messages' => $this->messages->listForIncident($technicianIncident, $request->user()),
            'statusOptions' => $this->incidents->statusOptions(
                $technicianIncident->status_id !== null ? (int) $technicianIncident->status_id : null,
            ),
        ]);
    }

    public function updateStatus(
        UpdateTechnicianIncidentStatusRequest $request,
        TechnicianIncident $technicianIncident,
    ): JsonResponse {
        $incident = $this->incidents->updateStatus(
            $technicianIncident,
            (int) $request->validated('status_id'),
        );

        return response()->json([
            'incident' => $this->incidents->toDetailData($incident),
            'statusOptions' => $this->incidents->statusOptions(
                $incident->status_id !== null ? (int) $incident->status_id : null,
            ),
        ]);
    }

    public function verify(
        VerifyTechnicianIncidentRequest $request,
        TechnicianIncident $technicianIncident,
    ): JsonResponse {
        $incident = $this->incidents->verify(
            $technicianIncident,
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'incident' => $this->incidents->toDetailData($incident),
            'statusOptions' => $this->incidents->statusOptions(
                $incident->status_id !== null ? (int) $incident->status_id : null,
            ),
        ]);
    }

    public function storeMessage(
        StoreTechnicianIncidentMessageRequest $request,
        TechnicianIncident $technicianIncident,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 403);

        if ($request->hasFile('file')) {
            /** @var UploadedFile $file */
            $file = $request->file('file');
            $message = $this->messages->createFile($technicianIncident, $user, $file);
        } else {
            $message = $this->messages->createText(
                $technicianIncident,
                $user,
                (string) $request->validated('body'),
            );
        }

        return response()->json([
            'message' => $this->messages->toListItem($message, $technicianIncident, $user),
        ], 201);
    }

    public function showMessageFile(
        TechnicianIncident $technicianIncident,
        TechnicianIncidentMessage $technicianIncidentMessage,
    ): StreamedResponse {
        $this->authorize('view', $technicianIncident);

        return $this->messages->streamAttachment($technicianIncident, $technicianIncidentMessage);
    }
}
