<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Brands\Services\BrandMessageService;
use App\Domain\Config\Brands\Services\BrandService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Brands\StoreBrandMessageRequest;
use App\Http\Requests\Web\Config\Brands\StoreBrandRequest;
use App\Http\Requests\Web\Config\Brands\UpdateBrandRequest;
use App\Models\Brand;
use App\Models\BrandMessage;
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

final class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brands,
        private readonly BrandMessageService $messages,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Brand::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Config/Brands/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Brand::class) ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'account_manager_id', 'commercial_manager_id', 'loyalty_meeting_frequency', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search'],
        );

        return TabulatorResponse::fromPaginator(
            $this->brands->paginateForWeb($filters),
        );
    }

    public function clients(Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        return response()->json([
            'data' => $this->brands->clientsForBrand($brand),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Brand::class);

        return Inertia::render('Config/Brands/Create', [
            'userOptions' => $this->brands->userOptions(),
        ]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $this->brands->create($request->validated());

        return redirect()
            ->route('brands.index')
            ->with('success', 'brand_created_successfully');
    }

    public function edit(Request $request, Brand $brand): Response
    {
        $this->authorize('update', $brand);

        $user = $request->user();
        $canViewMessages = $user?->can('viewMessages', $brand) ?? false;

        return Inertia::render('Config/Brands/Edit', [
            'brand' => $this->brands->toFormData($brand),
            'userOptions' => $this->brands->userOptions(),
            'messages' => $canViewMessages
                ? $this->messages->listForBrand($brand, $user)
                : [],
            'can' => [
                'delete' => $user?->can('delete', $brand) ?? false,
                'view_messages' => $canViewMessages,
                'send_messages' => $user?->can('sendMessages', $brand) ?? false,
                'view_message_files' => $user?->can('viewMessageFiles', $brand) ?? false,
                'download_message_files' => $user?->can('downloadMessageFiles', $brand) ?? false,
                'send_message_files' => $user?->can('sendMessageFiles', $brand) ?? false,
            ],
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->brands->update($brand, $request->validated());

        return redirect()
            ->route('brands.index')
            ->with('success', 'brand_updated_successfully');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $this->authorize('delete', $brand);

        $this->brands->delete($brand);

        return redirect()
            ->route('brands.index')
            ->with('success', 'brand_deleted_successfully');
    }

    public function storeMessage(StoreBrandMessageRequest $request, Brand $brand): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if ($request->hasFile('file')) {
            $this->authorize('sendMessageFiles', $brand);

            /** @var UploadedFile $file */
            $file = $request->file('file');
            $this->messages->createFile($brand, $user, $file);
        } else {
            $this->authorize('sendMessages', $brand);
            $this->messages->createText($brand, $user, (string) $request->validated('body'));
        }

        return redirect()
            ->route('brands.edit', $brand)
            ->with('success', 'brand_message_sent_successfully');
    }

    public function showMessageFile(Brand $brand, BrandMessage $brandMessage): StreamedResponse
    {
        $this->authorize('downloadMessageFiles', $brand);

        return $this->messages->streamAttachment($brand, $brandMessage);
    }
}
