<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Auth\Permissions\PolicyPermissionSync;
use Illuminate\Console\Command;

final class SyncPermissionsFromPoliciesCommand extends Command
{
    protected $signature = 'permissions:sync-from-policies
                            {--prune : Delete permissions that are no longer discovered from policies}';

    protected $description = 'Create Spatie permissions from App\\Policies method names';

    public function handle(PolicyPermissionSync $sync): int
    {
        $result = $sync->sync(prune: (bool) $this->option('prune'));

        $this->info(sprintf(
            'Synced %d permissions (%d created, %d existing, %d pruned).',
            count($result['all']),
            count($result['created']),
            count($result['existing']),
            count($result['pruned']),
        ));

        if ($result['all'] !== []) {
            $this->table(['Permission'], array_map(fn (string $name) => [$name], $result['all']));
        }

        return self::SUCCESS;
    }
}
