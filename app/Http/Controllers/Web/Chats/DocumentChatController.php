<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Chats;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Chats\Services\DocumentChatService;
use App\Domain\Chats\Support\ChatDomainRegistry;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Chats\StoreDocumentChatAttachmentRequest;
use App\Http\Requests\Web\Chats\StoreDocumentChatMessageRequest;
use App\Models\CompanyRelationship;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\EstimatePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentChatController extends Controller
{
    public function __construct(
        private readonly DocumentChatService $chats,
    ) {}

    public function messages(Request $request, string $type, int $document): JsonResponse
    {
        $documentType = ChatDomainRegistry::fromString($type);
        $user = $this->user($request);
        $this->authorizeDocument($documentType, $document, $user, 'view');

        return response()->json($this->chats->payload($documentType, $document, $user));
    }

    public function storeMessage(StoreDocumentChatMessageRequest $request, string $type, int $document): JsonResponse
    {
        $documentType = ChatDomainRegistry::fromString($type);
        $user = $this->user($request);
        $this->authorizeDocument($documentType, $document, $user, 'update');

        $message = $this->chats->postText(
            $documentType,
            $document,
            $user,
            (string) $request->validated('body'),
            (bool) $request->boolean('is_private'),
        );

        return response()->json(['message' => $message], 201);
    }

    public function storeAttachment(StoreDocumentChatAttachmentRequest $request, string $type, int $document): JsonResponse
    {
        $documentType = ChatDomainRegistry::fromString($type);
        $user = $this->user($request);
        $this->authorizeDocument($documentType, $document, $user, 'update');

        $message = $this->chats->postFile(
            $documentType,
            $document,
            $user,
            $request->file('file'),
            (bool) $request->boolean('is_private'),
        );

        return response()->json(['message' => $message], 201);
    }

    public function markRead(Request $request, string $type, int $document): JsonResponse
    {
        $documentType = ChatDomainRegistry::fromString($type);
        $user = $this->user($request);
        $this->authorizeDocument($documentType, $document, $user, 'view');
        $this->chats->markRead($documentType, $document, $user);

        return response()->json(['ok' => true]);
    }

    public function mute(Request $request, string $type, int $document): JsonResponse
    {
        $documentType = ChatDomainRegistry::fromString($type);
        $user = $this->user($request);
        $this->authorizeDocument($documentType, $document, $user, 'view');
        $this->chats->mute($documentType, $document, $user);

        return response()->json(['ok' => true, 'is_muted' => true]);
    }

    public function unmute(Request $request, string $type, int $document): JsonResponse
    {
        $documentType = ChatDomainRegistry::fromString($type);
        $user = $this->user($request);
        $this->authorizeDocument($documentType, $document, $user, 'view');
        $this->chats->unmute($documentType, $document, $user);

        return response()->json(['ok' => true, 'is_muted' => false]);
    }

    public function downloadAttachment(Request $request, string $type, int $document, int $attachment): StreamedResponse
    {
        $documentType = ChatDomainRegistry::fromString($type);
        $user = $this->user($request);
        $this->authorizeDocument($documentType, $document, $user, 'view');

        return $this->chats->downloadAttachment(
            $documentType,
            $document,
            $attachment,
            $user,
            $request->boolean('inline'),
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user;
    }

    private function authorizeDocument(ChatDocumentType $type, int $documentId, User $user, string $ability): void
    {
        $parent = $this->chats->findParent($type, $documentId);

        if ($type === ChatDocumentType::Technician) {
            abort_unless(
                $parent instanceof CompanyRelationship
                && $parent->kind === CompanyRelationshipKind::Technician,
                404,
            );
        }

        // Estimates reuse work_order chat tables/routes but authorize via EstimatePolicy
        // (WorkOrderPolicy only allows confirmed work orders).
        if ($parent instanceof WorkOrder && $parent->isEstimate()) {
            $policy = app(EstimatePolicy::class);
            $allowed = match ($ability) {
                'view' => $policy->view($user, $parent),
                'update' => $policy->update($user, $parent),
                default => false,
            };

            abort_unless($allowed, 403);

            return;
        }

        $this->authorize($ability, $parent);
    }
}
