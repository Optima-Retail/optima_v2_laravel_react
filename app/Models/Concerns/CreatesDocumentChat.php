<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Chats\Services\DocumentChatService;

trait CreatesDocumentChat
{
    public static function bootCreatesDocumentChat(): void
    {
        static::created(function ($model): void {
            $type = $model->chatDocumentType();
            if (! $type instanceof ChatDocumentType) {
                return;
            }

            app(DocumentChatService::class)->ensureChat($type, (int) $model->getKey());
        });
    }

    abstract public function chatDocumentType(): ChatDocumentType;
}
