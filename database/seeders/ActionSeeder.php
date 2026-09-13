<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\QualityScores\Enums\QualityActionId;
use App\Models\Action;
use Illuminate\Database\Seeder;

/**
 * Legacy `acciones` catalog — AccionEnum IDs preserved.
 */
final class ActionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (QualityActionId::cases() as $action) {
            Action::query()->updateOrCreate(
                ['id' => $action->value],
                ['weight_key' => $action->weightKey()],
            );
        }
    }
}
