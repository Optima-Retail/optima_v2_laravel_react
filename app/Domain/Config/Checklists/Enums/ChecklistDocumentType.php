<?php

declare(strict_types=1);

namespace App\Domain\Config\Checklists\Enums;

/**
 * Document a checklist template belongs to.
 *
 * Values: work_order | estimate (legacy modelo OT / Presupuesto).
 */
enum ChecklistDocumentType: string
{
    case WorkOrder = 'work_order';
    case Estimate = 'estimate';

    public function label(): string
    {
        return match ($this) {
            self::WorkOrder => 'Work order',
            self::Estimate => 'Estimate',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
