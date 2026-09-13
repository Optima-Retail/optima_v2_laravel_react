<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Compliments\Enums\ComplimentTypeId;
use App\Models\ComplimentType;
use Illuminate\Database\Seeder;

final class ComplimentTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ComplimentTypeId::cases() as $type) {
            ComplimentType::query()->updateOrCreate(
                ['id' => $type->value],
                ['name' => $type->defaultName()],
            );
        }
    }
}
