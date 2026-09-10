<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config\PaymentDocuments;

use App\Domain\Config\PaymentDocuments\Services\PaymentDocumentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\PaymentDocuments\StorePaymentDocumentRequest;
use App\Http\Requests\Web\Config\PaymentDocuments\UpdatePaymentDocumentRequest;
use App\Models\PaymentDocument;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PaymentDocumentController extends Controller
{
    public function __construct(
        private readonly PaymentDocumentService $paymentDocuments,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PaymentDocument::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/PaymentDocuments/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', PaymentDocument::class) ?? false,
                'update' => $request->user()?->can('payment_documents.update') ?? false,
                'delete' => $request->user()?->can('payment_documents.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentDocument::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'is_active'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->paymentDocuments->paginateForWeb($filters),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', PaymentDocument::class);

        return Inertia::render('Config/PaymentDocuments/Create');
    }

    public function store(StorePaymentDocumentRequest $request): RedirectResponse
    {
        $this->paymentDocuments->create($request->validated());

        return redirect()
            ->route('config.payment-documents.index')
            ->with('success', 'payment_document_created_successfully');
    }

    public function edit(Request $request, PaymentDocument $paymentDocument): Response
    {
        $this->authorize('update', $paymentDocument);

        return Inertia::render('Config/PaymentDocuments/Edit', [
            'paymentDocument' => $this->paymentDocuments->toFormData($paymentDocument),
            'can' => [
                'delete' => $request->user()?->can('delete', $paymentDocument) ?? false,
            ],
        ]);
    }

    public function update(UpdatePaymentDocumentRequest $request, PaymentDocument $paymentDocument): RedirectResponse
    {
        $this->paymentDocuments->update($paymentDocument, $request->validated());

        return redirect()
            ->route('config.payment-documents.index')
            ->with('success', 'payment_document_updated_successfully');
    }

    public function destroy(PaymentDocument $paymentDocument): RedirectResponse
    {
        $this->authorize('delete', $paymentDocument);

        $this->paymentDocuments->delete($paymentDocument);

        return redirect()
            ->route('config.payment-documents.index')
            ->with('success', 'payment_document_deleted_successfully');
    }
}
