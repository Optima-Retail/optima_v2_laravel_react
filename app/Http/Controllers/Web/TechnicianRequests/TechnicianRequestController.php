<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\TechnicianRequests;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Chats\Services\DocumentChatService;
use App\Domain\TechnicianRequests\Enums\TechnicianRequestStatusKind;
use App\Domain\TechnicianRequests\Services\TechnicianRequestService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\TechnicianRequests\AttachTechnicianRequestRequest;
use App\Http\Requests\Web\TechnicianRequests\StoreTechnicianRequestRequest;
use App\Http\Requests\Web\TechnicianRequests\UpdateTechnicianRequestRequest;
use App\Models\CompanyRelationship;
use App\Models\TechnicianRequest;
use App\Models\TechnicianRequestStatus;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TechnicianRequestController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly TechnicianRequestService $technicianRequests,
        private readonly DocumentChatService $chats,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TechnicianRequest::class);

        $owner = $this->activeCompany($request);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'technician_request_status_id' => $request->string('technician_request_status_id')->trim()->toString(),
            'technician_request_priority_id' => $request->string('technician_request_priority_id')->trim()->toString(),
            'is_screening' => $request->string('is_screening')->trim()->toString(),
            'responsible_user_id' => $request->string('responsible_user_id')->trim()->toString(),
            'created_from' => $request->string('created_from')->trim()->toString(),
            'created_to' => $request->string('created_to')->trim()->toString(),
            'due_from' => $request->string('due_from')->trim()->toString(),
            'due_to' => $request->string('due_to')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 25),
            ]),
        ];

        return Inertia::render('TechnicianRequests/Index', [
            'filters' => $filters,
            'statusOptions' => TechnicianRequestStatus::query()
                ->orderBy('kind')
                ->orderBy('lifecycle')
                ->orderBy('name')
                ->get(['id', 'name', 'kind'])
                ->map(fn (TechnicianRequestStatus $status): array => [
                    'id' => $status->id,
                    'label' => $status->name,
                    'kind' => $status->kind->value,
                ])
                ->values()
                ->all(),
            'priorityOptions' => $this->technicianRequests->priorityOptions(),
            'userOptions' => $this->technicianRequests->userFilterOptions($owner),
            'can' => [
                'create' => $request->user()?->can('create', TechnicianRequest::class) ?? false,
                'update' => $request->user()?->can('technician_requests.update') ?? false,
                'delete' => $request->user()?->can('technician_requests.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TechnicianRequest::class);

        $owner = $this->activeCompany($request);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'code', 'due_at', 'resolved_at', 'created_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: [
                'search',
                'technician_request_status_id',
                'technician_request_priority_id',
                'is_screening',
                'responsible_user_id',
                'created_from',
                'created_to',
                'due_from',
                'due_to',
            ],
        );

        return TabulatorResponse::fromPaginator(
            $this->technicianRequests->paginateForWeb($owner, $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', TechnicianRequest::class);

        $owner = $this->activeCompany($request);
        $isScreening = $request->boolean('is_screening');

        return Inertia::render('TechnicianRequests/Create', [
            'defaultIsScreening' => $isScreening,
            'defaultStatusId' => $isScreening
                ? 59
                : 38,
            'statusOptions' => $this->technicianRequests->statusOptions(),
            'priorityOptions' => $this->technicianRequests->priorityOptions(),
            'userOptions' => $request->user() !== null
                ? $this->technicianRequests->userOptions($owner, [(int) $request->user()->id])
                : [],
            'languageOptions' => $this->technicianRequests->languageOptions(),
            'countryOptions' => $this->technicianRequests->countryOptions(),
            'serviceTypeOptions' => $this->technicianRequests->serviceTypeOptions(),
            'workOrderOptions' => [],
        ]);
    }

    public function store(StoreTechnicianRequestRequest $request): RedirectResponse
    {
        $owner = $this->activeCompany($request);
        $user = $request->user();
        abort_if($user === null, 403);

        $technicianRequest = $this->technicianRequests->create($owner, $user, $request->validated());

        return redirect()
            ->route('technician-requests.edit', $technicianRequest)
            ->with('success', 'technician_request_created_successfully');
    }

    public function edit(Request $request, TechnicianRequest $technicianRequest): Response
    {
        $this->authorize('update', $technicianRequest);

        $owner = $this->activeCompany($request);
        $user = $request->user();
        $form = $this->technicianRequests->toFormData($technicianRequest);
        $kind = $form['is_screening']
            ? TechnicianRequestStatusKind::Screening->value
            : TechnicianRequestStatusKind::Request->value;

        return Inertia::render('TechnicianRequests/Edit', [
            'technicianRequest' => $form,
            'statusOptions' => $this->technicianRequests->statusOptions(
                $kind,
                $form['technician_request_status_id'] !== null
                    ? (int) $form['technician_request_status_id']
                    : null,
            ),
            'priorityOptions' => $this->technicianRequests->priorityOptions(),
            'userOptions' => $this->technicianRequests->userOptions(
                $owner,
                array_filter([
                    $form['responsible_user_id'] !== null ? (int) $form['responsible_user_id'] : null,
                    $form['requester_user_id'] !== null ? (int) $form['requester_user_id'] : null,
                ]),
            ),
            'languageOptions' => $this->technicianRequests->languageOptions(),
            'countryOptions' => $this->technicianRequests->countryOptions(),
            'serviceTypeOptions' => $this->technicianRequests->serviceTypeOptions(),
            'workOrderOptions' => $this->technicianRequests->workOrderOptions(
                $owner,
                $form['work_order_id'] !== null ? (int) $form['work_order_id'] : null,
            ),
            'technicianOptions' => [],
            'chat' => $user !== null
                ? $this->chats->payload(ChatDocumentType::TechnicianRequest, (int) $technicianRequest->id, $user)
                : null,
            'can' => [
                'delete' => $user?->can('delete', $technicianRequest) ?? false,
                'cancel' => ($user?->can('update', $technicianRequest) ?? false)
                    && ! $form['is_screening']
                    && $form['status_is_open'],
                'create_screening' => ($user?->can('update', $technicianRequest) ?? false)
                    && ! $form['is_screening'],
                'manage_technicians' => $user?->can('update', $technicianRequest) ?? false,
                'post_chat' => $user?->can('update', $technicianRequest) ?? false,
            ],
        ]);
    }

    public function update(UpdateTechnicianRequestRequest $request, TechnicianRequest $technicianRequest): RedirectResponse
    {
        $this->technicianRequests->update($technicianRequest, $request->validated());

        return redirect()
            ->route('technician-requests.edit', $technicianRequest)
            ->with('success', 'technician_request_updated_successfully');
    }

    public function destroy(TechnicianRequest $technicianRequest): RedirectResponse
    {
        $this->authorize('delete', $technicianRequest);

        $this->technicianRequests->delete($technicianRequest);

        return redirect()
            ->route('technician-requests.index')
            ->with('success', 'technician_request_deleted_successfully');
    }

    public function createScreening(Request $request, TechnicianRequest $technicianRequest): RedirectResponse
    {
        $this->authorize('update', $technicianRequest);

        $owner = $this->activeCompany($request);
        $screening = $this->technicianRequests->createScreening($technicianRequest, $owner);

        return redirect()
            ->route('technician-requests.edit', $screening)
            ->with('success', 'technician_request_screening_created_successfully');
    }

    public function cancel(Request $request, TechnicianRequest $technicianRequest): RedirectResponse
    {
        $this->authorize('update', $technicianRequest);

        $this->technicianRequests->cancel($technicianRequest);

        return redirect()
            ->route('technician-requests.edit', $technicianRequest)
            ->with('success', 'technician_request_cancelled_successfully');
    }

    public function attachTechnician(
        AttachTechnicianRequestRequest $request,
        TechnicianRequest $technicianRequest,
    ): RedirectResponse {
        $owner = $this->activeCompany($request);

        $this->technicianRequests->attachTechnician(
            $technicianRequest,
            $owner,
            (int) $request->validated('company_relationship_id'),
        );

        return redirect()
            ->route('technician-requests.edit', $technicianRequest)
            ->with('success', 'technician_request_technician_attached_successfully');
    }

    public function detachTechnician(
        Request $request,
        TechnicianRequest $technicianRequest,
        CompanyRelationship $companyRelationship,
    ): RedirectResponse {
        $this->authorize('update', $technicianRequest);

        $owner = $this->activeCompany($request);
        $this->technicianRequests->detachTechnician(
            $technicianRequest,
            $owner,
            (int) $companyRelationship->id,
        );

        return redirect()
            ->route('technician-requests.edit', $technicianRequest)
            ->with('success', 'technician_request_technician_detached_successfully');
    }
}
