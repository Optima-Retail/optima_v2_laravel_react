<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Banks\Services\BankService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Banks\StoreBankRequest;
use App\Http\Requests\Web\Config\Banks\UpdateBankRequest;
use App\Models\Bank;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BankController extends Controller
{
    public function __construct(
        private readonly BankService $banks,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Bank::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Banks/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Bank::class) ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Bank::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'country_id', 'swift_bic', 'is_active'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search', 'status'],
        );

        return TabulatorResponse::fromPaginator(
            $this->banks->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Bank::class);

        return Inertia::render('Config/Banks/Create', [
            'countryOptions' => $this->banks->countryOptions(),
        ]);
    }

    public function store(StoreBankRequest $request): RedirectResponse
    {
        $this->banks->create($request->validated());

        return redirect()
            ->route('config.banks.index')
            ->with('success', 'bank_created_successfully');
    }

    public function edit(Request $request, Bank $bank): Response
    {
        $this->authorize('update', $bank);

        return Inertia::render('Config/Banks/Edit', [
            'bank' => $this->banks->toFormData($bank),
            'countryOptions' => $this->banks->countryOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $bank) ?? false,
            ],
        ]);
    }

    public function update(UpdateBankRequest $request, Bank $bank): RedirectResponse
    {
        $this->banks->update($bank, $request->validated());

        return redirect()
            ->route('config.banks.index')
            ->with('success', 'bank_updated_successfully');
    }

    public function destroy(Bank $bank): RedirectResponse
    {
        $this->authorize('delete', $bank);

        $this->banks->delete($bank);

        return redirect()
            ->route('config.banks.index')
            ->with('success', 'bank_deleted_successfully');
    }
}
