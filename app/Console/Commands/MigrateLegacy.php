<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\DataMigration\LegacyMigrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:migrate-legacy {--execute : Write mapped rows to the SSL destination} {--chunk=200 : Insert chunk size}')]
#[Description('Migrate legacy Optima data (WSL laravel) into the V2 SSL database')]
class MigrateLegacy extends Command
{
    public function handle(): int
    {
        try {
            return (new LegacyMigrator(
                $this,
                (bool) $this->option('execute'),
                max(50, (int) $this->option('chunk')),
            ))->run();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }
    }
}
