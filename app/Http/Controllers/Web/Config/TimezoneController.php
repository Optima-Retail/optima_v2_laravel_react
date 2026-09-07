<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Timezones\Services\TimezoneService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Timezones\StoreTimezoneRequest;
use App\Http\Requests\Web\Config\Timezones\UpdateTimezoneRequest;
use App\Models\Timezone;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TimezoneController extends Controller
{
    public function __construct(
        private readonly TimezoneService $timezones,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Timezone::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Timezones/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Timezone::class) ?? false,
                'update' => $request->user()?->can('timezones.update') ?? false,
                'delete' => $request->user()?->can('timezones.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Timezone::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'timezone'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->timezones->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Timezone::class);

        return Inertia::render('Config/Timezones/Create');
    }

    public function store(StoreTimezoneRequest $request): RedirectResponse
    {
        $this->timezones->create($request->validated());

        return redirect()
            ->route('config.timezones.index')
            ->with('success', 'timezone_created_successfully');
    }

    public function edit(Request $request, Timezone $timezone): Response
    {
        $this->authorize('update', $timezone);

        return Inertia::render('Config/Timezones/Edit', [
            'timezone' => $this->timezones->toFormData($timezone),
            'can' => [
                'delete' => $request->user()?->can('delete', $timezone) ?? false,
            ],
        ]);
    }

    public function update(UpdateTimezoneRequest $request, Timezone $timezone): RedirectResponse
    {
        $this->timezones->update($timezone, $request->validated());

        return redirect()
            ->route('config.timezones.index')
            ->with('success', 'timezone_updated_successfully');
    }

    public function destroy(Timezone $timezone): RedirectResponse
    {
        $this->authorize('delete', $timezone);

        $this->timezones->delete($timezone);

        return redirect()
            ->route('config.timezones.index')
            ->with('success', 'timezone_deleted_successfully');
    }
}
