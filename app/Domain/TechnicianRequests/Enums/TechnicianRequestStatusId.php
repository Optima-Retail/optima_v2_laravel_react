<?php

declare(strict_types=1);

namespace App\Domain\TechnicianRequests\Enums;

/**
 * Legacy estado IDs preserved for migration and default workflows.
 */
enum TechnicianRequestStatusId: int
{
    case RequestOpen = 38;
    case RequestInProgress = 39;
    case RequestFinished = 40;
    case RequestCancelled = 48;
    case ScreeningAppointment = 54;
    case ScreeningDocsPending = 55;
    case ScreeningManagerReview = 56;
    case ScreeningNotSuitable = 57;
    case ScreeningSuitable = 58;
    case ScreeningOpen = 59;

    /**
     * @return list<int>
     */
    public static function closedIds(): array
    {
        return [
            self::RequestFinished->value,
            self::RequestCancelled->value,
            self::ScreeningNotSuitable->value,
            self::ScreeningSuitable->value,
        ];
    }
}
