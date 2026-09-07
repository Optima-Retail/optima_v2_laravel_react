<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Currencies\Services\CurrencyService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Currencies\StoreCurrencyRequest;
use App\Http\Requests\Web\Config\Currencies\UpdateCurrencyRequest;
use App\Models\Currency;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencies,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Currency::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Currencies/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Currency::class) ?? false,
                'update' => $request->user()?->can('currencies.update') ?? false,
                'delete' => $request->user()?->can('currencies.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Currency::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->currencies->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Currency::class);

        return Inertia::render('Config/Currencies/Create');
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $this->currencies->create($request->validated());

        return redirect()
            ->route('config.currencies.index')
            ->with('success', 'currency_created_successfully');
    }

    public function edit(Request $request, Currency $currency): Response
    {
        $this->authorize('update', $currency);

        return Inertia::render('Config/Currencies/Edit', [
            'currency' => $this->currencies->toFormData($currency),
            'can' => [
                'delete' => $request->user()?->can('delete', $currency) ?? false,
            ],
        ]);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $this->currencies->update($currency, $request->validated());

        return redirect()
            ->route('config.currencies.index')
            ->with('success', 'currency_updated_successfully');
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        $this->authorize('delete', $currency);

        $this->currencies->delete($currency);

        return redirect()
            ->route('config.currencies.index')
            ->with('success', 'currency_deleted_successfully');
    }
}
