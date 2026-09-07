<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Integration;
use Illuminate\Database\Seeder;

/**
 * Legacy IntegracionEnum catalog (optimaback integraciones).
 */
final class IntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $integrations = [
            [
                'id' => 1,
                'name' => 'Global Service Channel',
                'code' => 'service-channel-global',
            ],
            [
                'id' => 2,
                'name' => 'EU Service Channel',
                'code' => 'service-channel-eu',
            ],
        ];

        foreach ($integrations as $integration) {
            Integration::query()->updateOrCreate(
                ['id' => $integration['id']],
                [
                    'name' => $integration['name'],
                    'code' => $integration['code'],
                ],
            );
        }
    }
}
