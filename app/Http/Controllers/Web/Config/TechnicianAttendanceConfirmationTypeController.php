<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\TechnicianAttendanceConfirmationTypes\Services\TechnicianAttendanceConfirmationTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\TechnicianAttendanceConfirmationTypes\StoreTechnicianAttendanceConfirmationTypeRequest;
use App\Http\Requests\Web\Config\TechnicianAttendanceConfirmationTypes\UpdateTechnicianAttendanceConfirmationTypeRequest;
use App\Models\TechnicianAttendanceConfirmationType;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TechnicianAttendanceConfirmationTypeController extends Controller
{
    public function __construct(
        private readonly TechnicianAttendanceConfirmationTypeService $types,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TechnicianAttendanceConfirmationType::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/TechnicianAttendanceConfirmationTypes/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', TechnicianAttendanceConfirmationType::class) ?? false,
                'update' => $request->user()?->can('technician_attendance_confirmation_types.update') ?? false,
                'delete' => $request->user()?->can('technician_attendance_confirmation_types.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TechnicianAttendanceConfirmationType::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->types->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', TechnicianAttendanceConfirmationType::class);

        return Inertia::render('Config/TechnicianAttendanceConfirmationTypes/Create');
    }

    public function store(StoreTechnicianAttendanceConfirmationTypeRequest $request): RedirectResponse
    {
        $this->types->create([
            'name' => $request->string('name')->toString(),
        ]);

        return redirect()
            ->route('config.technician-attendance-confirmation-types.index')
            ->with('success', 'technician_attendance_confirmation_type_created_successfully');
    }

    public function edit(
        Request $request,
        TechnicianAttendanceConfirmationType $confirmation_type,
    ): Response {
        $this->authorize('update', $confirmation_type);

        return Inertia::render('Config/TechnicianAttendanceConfirmationTypes/Edit', [
            'confirmationType' => $this->types->toFormData($confirmation_type),
            'can' => [
                'delete' => $request->user()?->can('delete', $confirmation_type) ?? false,
            ],
        ]);
    }

    public function update(
        UpdateTechnicianAttendanceConfirmationTypeRequest $request,
        TechnicianAttendanceConfirmationType $confirmation_type,
    ): RedirectResponse {
        $this->types->update($confirmation_type, [
            'name' => $request->string('name')->toString(),
        ]);

        return redirect()
            ->route('config.technician-attendance-confirmation-types.index')
            ->with('success', 'technician_attendance_confirmation_type_updated_successfully');
    }

    public function destroy(
        TechnicianAttendanceConfirmationType $confirmation_type,
    ): RedirectResponse {
        $this->authorize('delete', $confirmation_type);

        $this->types->delete($confirmation_type);

        return redirect()
            ->route('config.technician-attendance-confirmation-types.index')
            ->with('success', 'technician_attendance_confirmation_type_deleted_successfully');
    }
}
