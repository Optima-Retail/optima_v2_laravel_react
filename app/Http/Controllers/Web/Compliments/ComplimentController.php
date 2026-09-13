<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Compliments;

use App\Domain\Compliments\Services\ComplimentAttachmentService;
use App\Domain\Compliments\Services\ComplimentService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Compliments\StoreComplimentAttachmentRequest;
use App\Http\Requests\Web\Compliments\StoreComplimentRequest;
use App\Http\Requests\Web\Compliments\UpdateComplimentRequest;
use App\Models\Compliment;
use App\Models\ComplimentAttachment;
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

final class ComplimentController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly ComplimentService $compliments,
        private readonly ComplimentAttachmentService $attachments,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Compliment::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'subject_type' => $request->string('subject_type')->trim()->toString(),
            'compliment_type_id' => $request->string('compliment_type_id')->trim()->toString(),
            'created_from' => $request->string('created_from')->trim()->toString(),
            'created_to' => $request->string('created_to')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Compliments/Index', [
            'filters' => $filters,
            'typeOptions' => $this->compliments->typeOptions(),
            'can' => [
                'create' => $request->user()?->can('create', Compliment::class) ?? false,
                'update' => $request->user()?->can('compliments.update') ?? false,
                'delete' => $request->user()?->can('compliments.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Compliment::class);

        $owner = $this->activeCompany($request);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'created_at', 'comment'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search', 'subject_type', 'compliment_type_id', 'created_from', 'created_to'],
        );

        return TabulatorResponse::fromPaginator(
            $this->compliments->paginateForWeb($owner, $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Compliment::class);

        $owner = $this->activeCompany($request);

        $user = $request->user();

        return Inertia::render('Compliments/Create', [
            'typeOptions' => $this->compliments->typeOptions(),
            'brandOptions' => $this->compliments->brandOptions($owner),
            'customerOptions' => $this->compliments->customerOptions($owner),
            'establishmentOptions' => $this->compliments->establishmentOptions($owner),
            'userOptions' => $this->compliments->userOptions($owner),
            'can' => [
                'upload_attachments' => $user?->can('compliments.upload-attachments') ?? false,
            ],
        ]);
    }

    public function store(StoreComplimentRequest $request): RedirectResponse
    {
        $payload = $request->safe()->except('file');
        $compliment = $this->compliments->create($payload);

        $file = $request->file('file');
        $user = $request->user();

        if ($file instanceof UploadedFile && $user !== null && $user->can('uploadAttachments', $compliment)) {
            $this->attachments->store($compliment, $user, $file);
        }

        return redirect()
            ->route('compliments.edit', $compliment)
            ->with('success', 'compliment_created_successfully');
    }

    public function edit(Request $request, Compliment $compliment): Response
    {
        $this->authorize('update', $compliment);

        $owner = $this->activeCompany($request);
        $form = $this->compliments->toFormData($compliment);
        $user = $request->user();
        $canViewAttachments = $user?->can('viewAttachments', $compliment) ?? false;

        return Inertia::render('Compliments/Edit', [
            'compliment' => $form,
            'attachments' => $canViewAttachments
                ? $this->attachments->listForCompliment($compliment)
                : [],
            'typeOptions' => $this->compliments->typeOptions(),
            'brandOptions' => $this->compliments->brandOptions($owner),
            'customerOptions' => $this->compliments->customerOptions($owner),
            'establishmentOptions' => $this->compliments->establishmentOptions($owner),
            'userOptions' => $this->compliments->userOptions($owner, $form['user_ids']),
            'can' => [
                'delete' => $user?->can('delete', $compliment) ?? false,
                'view_attachments' => $canViewAttachments,
                'upload_attachments' => $user?->can('uploadAttachments', $compliment) ?? false,
                'download_attachments' => $user?->can('downloadAttachments', $compliment) ?? false,
                'delete_attachments' => $user?->can('deleteAttachments', $compliment) ?? false,
            ],
        ]);
    }

    public function update(UpdateComplimentRequest $request, Compliment $compliment): RedirectResponse
    {
        $this->compliments->update($compliment, $request->validated());

        return redirect()
            ->route('compliments.index')
            ->with('success', 'compliment_updated_successfully');
    }

    public function destroy(Compliment $compliment): RedirectResponse
    {
        $this->authorize('delete', $compliment);

        $this->compliments->delete($compliment);

        return redirect()
            ->route('compliments.index')
            ->with('success', 'compliment_deleted_successfully');
    }

    public function storeAttachment(StoreComplimentAttachmentRequest $request, Compliment $compliment): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->attachments->store($compliment, $user, $file);

        return redirect()
            ->route('compliments.edit', $compliment)
            ->with('success', 'compliment_attachment_uploaded_successfully');
    }

    public function downloadAttachment(Request $request, Compliment $compliment, ComplimentAttachment $attachment): StreamedResponse
    {
        $this->authorize('downloadAttachments', $compliment);

        return $this->attachments->stream($compliment, $attachment, $request->boolean('inline'));
    }

    public function destroyAttachment(Compliment $compliment, ComplimentAttachment $attachment): RedirectResponse
    {
        $this->authorize('deleteAttachments', $compliment);

        $this->attachments->delete($compliment, $attachment);

        return redirect()
            ->route('compliments.edit', $compliment)
            ->with('success', 'compliment_attachment_deleted_successfully');
    }
}
