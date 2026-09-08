<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\FieldHelps\Services\FieldHelpSchemaService;
use App\Domain\Config\FieldHelps\Services\FieldHelpService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\FieldHelps\StoreFieldHelpRequest;
use App\Http\Requests\Web\Config\FieldHelps\UpdateFieldHelpRequest;
use App\Models\FieldHelp;
use App\Support\ListQuery;
use App\Support\Locale;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FieldHelpController extends Controller
{
    public function __construct(
        private readonly FieldHelpService $fieldHelps,
        private readonly FieldHelpSchemaService $schema,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FieldHelp::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'key',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/FieldHelps/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', FieldHelp::class) ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FieldHelp::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'key', 'context', 'is_active', 'created_at'],
            defaultSort: 'key',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->fieldHelps->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', FieldHelp::class);

        return Inertia::render('Config/FieldHelps/Create', [
            'schema' => $this->schema->catalog(),
            'locales' => Locale::options(),
        ]);
    }

    public function store(StoreFieldHelpRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->fieldHelps->create([
            'key' => $validated['key'],
            'context' => $validated['context'] ?? $validated['table'],
            'is_active' => $validated['is_active'],
            'translations' => $validated['translations'],
        ]);

        return redirect()
            ->route('config.field-helps.index')
            ->with('success', 'field_help_created_successfully');
    }

    public function edit(Request $request, FieldHelp $fieldHelp): Response
    {
        $this->authorize('update', $fieldHelp);

        return Inertia::render('Config/FieldHelps/Edit', [
            'fieldHelp' => $this->fieldHelps->toFormData($fieldHelp),
            'schema' => $this->schema->catalog(),
            'locales' => Locale::options(),
            'can' => [
                'delete' => $request->user()?->can('delete', $fieldHelp) ?? false,
            ],
        ]);
    }

    public function update(UpdateFieldHelpRequest $request, FieldHelp $fieldHelp): RedirectResponse
    {
        $validated = $request->validated();

        $this->fieldHelps->update($fieldHelp, [
            'key' => $validated['key'],
            'context' => $validated['context'] ?? $validated['table'],
            'is_active' => $validated['is_active'],
            'translations' => $validated['translations'],
        ]);

        return redirect()
            ->route('config.field-helps.index')
            ->with('success', 'field_help_updated_successfully');
    }

    public function destroy(FieldHelp $fieldHelp): RedirectResponse
    {
        $this->authorize('delete', $fieldHelp);

        $this->fieldHelps->delete($fieldHelp);

        return redirect()
            ->route('config.field-helps.index')
            ->with('success', 'field_help_deleted_successfully');
    }
}
