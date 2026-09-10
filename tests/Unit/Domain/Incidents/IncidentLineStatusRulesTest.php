<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Incidents;

use App\Domain\Incidents\Support\IncidentLineStatusRules;
use App\Models\IncidentStatus;
use App\Models\IncidentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IncidentLineStatusRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_forbidden_statuses_from_database(): void
    {
        $type = IncidentType::query()->create([
            'name' => 'Controllers',
            'color' => '#FFFFFF',
            'default_priority_id' => null,
            'origin_selectable' => false,
            'origin_options' => [],
            'origin_required' => false,
            'show_related' => false,
        ]);

        $allowed = IncidentStatus::query()->create([
            'name' => 'Abierta',
            'color' => '#f6eac2',
            'lifecycle' => 1,
            'is_open' => true,
        ]);

        $excluded = IncidentStatus::query()->create([
            'name' => 'Feedback Sales',
            'color' => '#a9cef0',
            'lifecycle' => 2,
            'is_open' => false,
        ]);

        $excluded->excludedTypes()->attach($type->id);

        $rules = new IncidentLineStatusRules;

        $this->assertSame([$excluded->id], $rules->forbiddenStatusIdsForType($type->id));
        $this->assertFalse($rules->isStatusAllowedForType($type->id, $excluded->id));
        $this->assertTrue($rules->isStatusAllowedForType($type->id, $allowed->id));
        $this->assertSame([], $rules->forbiddenStatusIdsForType(999));

        $filtered = $rules->filterStatusOptions($type->id, [
            ['id' => $allowed->id, 'label' => 'Open'],
            ['id' => $excluded->id, 'label' => 'Feedback'],
        ]);

        $this->assertSame([$allowed->id], array_column($filtered, 'id'));
    }
}
