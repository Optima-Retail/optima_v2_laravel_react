<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config\PaymentMethods;

use App\Domain\Config\PaymentMethods\Services\PaymentMethodService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\PaymentMethods\StorePaymentMethodRequest;
use App\Http\Requests\Web\Config\PaymentMethods\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly PaymentMethodService $paymentMethods,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/PaymentMethods/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', PaymentMethod::class) ?? false,
                'update' => $request->user()?->can('payment_methods.update') ?? false,
                'delete' => $request->user()?->can('payment_methods.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'days', 'code', 'is_active'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->paymentMethods->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', PaymentMethod::class);

        return Inertia::render('Config/PaymentMethods/Create');
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $this->paymentMethods->create($request->validated());

        return redirect()
            ->route('config.payment-methods.index')
            ->with('success', 'payment_method_created_successfully');
    }

    public function edit(Request $request, PaymentMethod $paymentMethod): Response
    {
        $this->authorize('update', $paymentMethod);

        return Inertia::render('Config/PaymentMethods/Edit', [
            'paymentMethod' => $this->paymentMethods->toFormData($paymentMethod),
            'can' => [
                'delete' => $request->user()?->can('delete', $paymentMethod) ?? false,
            ],
        ]);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->paymentMethods->update($paymentMethod, $request->validated());

        return redirect()
            ->route('config.payment-methods.index')
            ->with('success', 'payment_method_updated_successfully');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorize('delete', $paymentMethod);

        $this->paymentMethods->delete($paymentMethod);

        return redirect()
            ->route('config.payment-methods.index')
            ->with('success', 'payment_method_deleted_successfully');
    }
}
