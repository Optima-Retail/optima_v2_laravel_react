<?php

declare(strict_types=1);

namespace App\Domain\Chats\Services;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Chats\Enums\ChatMessageType;
use App\Domain\Chats\Support\ChatDomainRegistry;
use App\Models\User;
use App\Support\Attachments\AttachmentMime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentChatService
{
    private const DISK = 'local';

    /**
     * @return array{chat_id: int, name: string|null, is_muted: bool, has_unread: bool, can_see_private: bool, messages: list<array<string, mixed>>}
     */
    public function payload(ChatDocumentType $type, int $documentId, User $viewer): array
    {
        $chat = $this->ensureChat($type, $documentId);
        $canSeePrivate = (bool) $viewer->is_internal_employee;

        return [
            'chat_id' => $chat->id,
            'name' => $chat->name,
            'is_muted' => $this->isMuted($type, $chat, $viewer),
            'has_unread' => $this->hasUnread($type, $chat, $viewer),
            'can_see_private' => $canSeePrivate,
            'messages' => $this->listMessages($type, $documentId, $chat, $viewer),
        ];
    }

    public function ensureChat(ChatDocumentType $type, int $documentId, ?string $name = null): Model
    {
        $config = ChatDomainRegistry::for($type);
        /** @var class-string<Model> $chatClass */
        $chatClass = $config['chat_class'];
        $fk = $config['parent_fk'];

        $existing = $chatClass::query()->where($fk, $documentId)->first();

        if ($existing !== null) {
            return $existing;
        }

        $parent = $this->findParent($type, $documentId);
        $resolvedName = $name ?? $this->defaultChatName($type, $parent);

        return $chatClass::query()->create([
            $fk => $documentId,
            'name' => $resolvedName,
        ]);
    }

    public function findParent(ChatDocumentType $type, int $documentId): Model
    {
        $config = ChatDomainRegistry::for($type);
        /** @var class-string<Model> $parentClass */
        $parentClass = $config['parent_class'];

        return $parentClass::query()->findOrFail($documentId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMessages(ChatDocumentType $type, int $documentId, Model $chat, User $viewer): array
    {
        $canSeePrivate = (bool) $viewer->is_internal_employee;

        $messages = $chat->messages()
            ->with(['user:id,name', 'attachments'])
            ->when(! $canSeePrivate, function ($query): void {
                // Status-history system lines are private for non-WO docs (prod),
                // but History tab must still be readable by anyone who can open the chat.
                $query->where(function ($inner): void {
                    $inner->where('is_private', false)
                        ->orWhere('type', ChatMessageType::System->value);
                });
            })
            ->orderBy('id')
            ->get();

        return $messages
            ->map(fn (Model $message): array => $this->messageToArray($type, $documentId, $message, $viewer))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function postText(
        ChatDocumentType $type,
        int $documentId,
        User $author,
        string $body,
        bool $isPrivate = false,
    ): array {
        $chat = $this->ensureChat($type, $documentId);

        $message = DB::transaction(function () use ($type, $chat, $author, $body, $isPrivate): Model {
            $message = $chat->messages()->create([
                'user_id' => $author->id,
                'body' => $body,
                'type' => ChatMessageType::Text->value,
                'is_private' => $isPrivate && $author->is_internal_employee,
                'arguments' => null,
                'translatable_arguments' => null,
            ]);

            $this->fanOutUnread($type, $chat, $message, $author);

            return $message->load(['user:id,name', 'attachments']);
        });

        return $this->messageToArray($type, $documentId, $message, $author);
    }

    /**
     * @param  array<string, string>|null  $arguments
     * @param  array<string, string>|null  $translatableArguments
     */
    public function postSystem(
        ChatDocumentType $type,
        int $documentId,
        string $templateKey,
        ?array $arguments = null,
        ?array $translatableArguments = null,
        bool $isPrivate = true,
        ?User $actor = null,
    ): void {
        $chat = $this->ensureChat($type, $documentId);

        $message = $chat->messages()->create([
            'user_id' => $actor?->id,
            'body' => $templateKey,
            'type' => ChatMessageType::System->value,
            'is_private' => $isPrivate,
            'arguments' => $arguments,
            'translatable_arguments' => $translatableArguments,
        ]);

        if ($actor !== null) {
            $this->fanOutUnread($type, $chat, $message, $actor);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function postFile(
        ChatDocumentType $type,
        int $documentId,
        User $author,
        UploadedFile $file,
        bool $isPrivate = false,
    ): array {
        $config = ChatDomainRegistry::for($type);
        $chat = $this->ensureChat($type, $documentId);

        $message = DB::transaction(function () use ($type, $config, $chat, $author, $file, $isPrivate): Model {
            $message = $chat->messages()->create([
                'user_id' => $author->id,
                'body' => null,
                'type' => ChatMessageType::File->value,
                'is_private' => $isPrivate && $author->is_internal_employee,
                'arguments' => null,
                'translatable_arguments' => null,
            ]);

            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $directory = $config['storage_prefix'].'/'.$chat->id.'/'.now()->format('Y-m');
            $filename = Str::uuid()->toString().'.'.$extension;
            $path = $file->storeAs($directory, $filename, self::DISK);

            $message->attachments()->create([
                'name' => $file->getClientOriginalName() ?: $filename,
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: null,
                'uploaded_by' => $author->id,
            ]);

            $this->fanOutUnread($type, $chat, $message, $author);

            return $message->load(['user:id,name', 'attachments']);
        });

        return $this->messageToArray($type, $documentId, $message, $author);
    }

    public function markRead(ChatDocumentType $type, int $documentId, User $user): void
    {
        $chat = $this->ensureChat($type, $documentId);
        $chat->unreadUsers()->detach($user->id);
    }

    public function mute(ChatDocumentType $type, int $documentId, User $user): void
    {
        $chat = $this->ensureChat($type, $documentId);
        $chat->mutedUsers()->syncWithoutDetaching([$user->id]);
        $chat->unreadUsers()->detach($user->id);
    }

    public function unmute(ChatDocumentType $type, int $documentId, User $user): void
    {
        $chat = $this->ensureChat($type, $documentId);
        $chat->mutedUsers()->detach($user->id);
    }

    public function downloadAttachment(
        ChatDocumentType $type,
        int $documentId,
        int $attachmentId,
        User $viewer,
        bool $inline = false,
    ): StreamedResponse {
        $config = ChatDomainRegistry::for($type);
        $chat = $this->ensureChat($type, $documentId);
        /** @var class-string<Model> $attachmentClass */
        $attachmentClass = $config['attachment_class'];

        $attachment = $attachmentClass::query()
            ->whereKey($attachmentId)
            ->whereHas('message', fn ($query) => $query->where('chat_id', $chat->id))
            ->firstOrFail();

        $message = $attachment->message;
        if ($message->is_private && ! $viewer->is_internal_employee) {
            abort(403);
        }

        return Storage::disk(self::DISK)->response(
            $attachment->path,
            $attachment->name,
            [
                'Content-Type' => $attachment->mime_type
                    ?: (Storage::disk(self::DISK)->mimeType($attachment->path) ?: 'application/octet-stream'),
                'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$attachment->name.'"',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function isMuted(ChatDocumentType $type, Model $chat, User $user): bool
    {
        return $chat->mutedUsers()->where('users.id', $user->id)->exists();
    }

    private function hasUnread(ChatDocumentType $type, Model $chat, User $user): bool
    {
        return $chat->unreadUsers()->where('users.id', $user->id)->exists();
    }

    private function fanOutUnread(ChatDocumentType $type, Model $chat, Model $message, User $author): void
    {
        $parent = $this->findParent($type, (int) $chat->{ChatDomainRegistry::for($type)['parent_fk']});
        $recipientIds = $this->involvedUserIds($type, $parent, $author);

        foreach ($recipientIds as $userId) {
            if ($userId === $author->id) {
                continue;
            }

            if ($chat->mutedUsers()->where('users.id', $userId)->exists()) {
                continue;
            }

            $recipient = User::query()->find($userId);
            if ($recipient === null) {
                continue;
            }

            if ($message->is_private && ! $recipient->is_internal_employee) {
                continue;
            }

            $chat->unreadUsers()->syncWithoutDetaching([$userId]);
        }

        $chat->touch();
    }

    /**
     * @return list<int>
     */
    private function involvedUserIds(ChatDocumentType $type, Model $parent, User $author): array
    {
        $ids = [$author->id];

        foreach (['responsible_user_id', 'requester_user_id', 'responsibleUser'] as $field) {
            if (isset($parent->{$field}) && is_numeric($parent->{$field})) {
                $ids[] = (int) $parent->{$field};
            }
        }

        if (method_exists($parent, 'responsibleUser') && $parent->responsible_user_id) {
            $ids[] = (int) $parent->responsible_user_id;
        }

        // Work order collaborators
        if (method_exists($parent, 'collaborators')) {
            $ids = array_merge($ids, $parent->collaborators()->pluck('users.id')->map(fn ($id) => (int) $id)->all());
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function defaultChatName(ChatDocumentType $type, Model $parent): string
    {
        return match ($type) {
            ChatDocumentType::WorkOrder => (string) ($parent->code ?? $parent->subject ?? 'CHAT'),
            ChatDocumentType::Incident => (string) ($parent->subject ?? 'CHAT'),
            ChatDocumentType::Evaluation => (string) ($parent->code ?? 'CHAT'),
            ChatDocumentType::TechnicianRequest => (string) ($parent->code ?? $parent->description ?? 'CHAT'),
            ChatDocumentType::Technician => (string) ($parent->id ?? 'CHAT'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function messageToArray(ChatDocumentType $type, int $documentId, Model $message, User $viewer): array
    {
        $body = $message->body;
        if ($message->type === ChatMessageType::System->value && is_string($body)) {
            $arguments = is_array($message->arguments) ? $message->arguments : [];
            $translatable = is_array($message->translatable_arguments) ? $message->translatable_arguments : [];
            foreach ($translatable as $key => $value) {
                $arguments[$key] = __((string) $value, [], $viewer->locale ?? app()->getLocale());
            }
            $body = __($body, $arguments, $viewer->locale ?? app()->getLocale());
        }

        return [
            'id' => $message->id,
            'type' => $message->type,
            'body' => $body,
            'is_private' => (bool) $message->is_private,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'created_at' => $message->created_at?->toIso8601String(),
            'attachments' => $message->attachments
                ->map(function (Model $attachment) use ($type, $documentId): array {
                    $downloadUrl = route('document-chats.attachments.download', [
                        'type' => $type->value,
                        'document' => $documentId,
                        'attachment' => $attachment->id,
                    ]);

                    return [
                        'id' => $attachment->id,
                        'name' => $attachment->name,
                        'mime_type' => $attachment->mime_type,
                        'size_bytes' => $attachment->size_bytes,
                        'download_url' => $downloadUrl,
                        ...AttachmentMime::previewFields($downloadUrl, $attachment->mime_type, $attachment->name),
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
