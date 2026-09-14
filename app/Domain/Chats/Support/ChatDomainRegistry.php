<?php

declare(strict_types=1);

namespace App\Domain\Chats\Support;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Models\CompanyRelationship;
use App\Models\Evaluation;
use App\Models\EvaluationChat;
use App\Models\EvaluationChatMessage;
use App\Models\EvaluationChatMessageAttachment;
use App\Models\Incident;
use App\Models\IncidentChat;
use App\Models\IncidentChatMessage;
use App\Models\IncidentChatMessageAttachment;
use App\Models\TechnicianChat;
use App\Models\TechnicianChatMessage;
use App\Models\TechnicianChatMessageAttachment;
use App\Models\TechnicianRequest;
use App\Models\TechnicianRequestChat;
use App\Models\TechnicianRequestChatMessage;
use App\Models\TechnicianRequestChatMessageAttachment;
use App\Models\WorkOrder;
use App\Models\WorkOrderChat;
use App\Models\WorkOrderChatMessage;
use App\Models\WorkOrderChatMessageAttachment;
use InvalidArgumentException;

final class ChatDomainRegistry
{
    /**
     * @return array{
     *     parent_class: class-string,
     *     chat_class: class-string,
     *     message_class: class-string,
     *     attachment_class: class-string,
     *     parent_fk: string,
     *     storage_prefix: string,
     *     policy_model: class-string
     * }
     */
    public static function for(ChatDocumentType $type): array
    {
        return match ($type) {
            ChatDocumentType::WorkOrder => [
                'parent_class' => WorkOrder::class,
                'chat_class' => WorkOrderChat::class,
                'message_class' => WorkOrderChatMessage::class,
                'attachment_class' => WorkOrderChatMessageAttachment::class,
                'parent_fk' => 'work_order_id',
                'storage_prefix' => 'work-order-chats',
                'policy_model' => WorkOrder::class,
            ],
            ChatDocumentType::Incident => [
                'parent_class' => Incident::class,
                'chat_class' => IncidentChat::class,
                'message_class' => IncidentChatMessage::class,
                'attachment_class' => IncidentChatMessageAttachment::class,
                'parent_fk' => 'incident_id',
                'storage_prefix' => 'incident-chats',
                'policy_model' => Incident::class,
            ],
            ChatDocumentType::Evaluation => [
                'parent_class' => Evaluation::class,
                'chat_class' => EvaluationChat::class,
                'message_class' => EvaluationChatMessage::class,
                'attachment_class' => EvaluationChatMessageAttachment::class,
                'parent_fk' => 'evaluation_id',
                'storage_prefix' => 'evaluation-chats',
                'policy_model' => Evaluation::class,
            ],
            ChatDocumentType::TechnicianRequest => [
                'parent_class' => TechnicianRequest::class,
                'chat_class' => TechnicianRequestChat::class,
                'message_class' => TechnicianRequestChatMessage::class,
                'attachment_class' => TechnicianRequestChatMessageAttachment::class,
                'parent_fk' => 'technician_request_id',
                'storage_prefix' => 'technician-request-chats',
                'policy_model' => TechnicianRequest::class,
            ],
            ChatDocumentType::Technician => [
                'parent_class' => CompanyRelationship::class,
                'chat_class' => TechnicianChat::class,
                'message_class' => TechnicianChatMessage::class,
                'attachment_class' => TechnicianChatMessageAttachment::class,
                'parent_fk' => 'company_relationship_id',
                'storage_prefix' => 'technician-chats',
                'policy_model' => CompanyRelationship::class,
            ],
        };
    }

    public static function fromString(string $type): ChatDocumentType
    {
        $enum = ChatDocumentType::tryFrom($type);

        if ($enum === null) {
            throw new InvalidArgumentException("Unknown chat document type [{$type}].");
        }

        return $enum;
    }
}
