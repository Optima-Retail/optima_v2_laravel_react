<?php

declare(strict_types=1);

namespace App\Domain\DataMigration;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

final class LegacyMigrator
{
    use ImportsSupport;
    use ImportsCatalogs;
    use ImportsExpanded;
    private const OPERATING_TAX_IDS = ['B66409087', 'IE4115369FH'];

    private const GROUPING = [
        'cliente' => 'customer',
        'establecimiento' => 'establishment',
        'ot' => 'work_order',
    ];

    private Connection $legacy;

    private Connection $dest;

    /** @var array<int, int> */
    private array $userMap = [];

    /** @var array<string, int> */
    private array $companyMap = [];

    /** @var array<string, int> */
    private array $relationshipMap = [];

    /** @var array<int, int> */
    private array $brandMap = [];

    /** @var array<int, int> */
    private array $establishmentMap = [];

    /** @var array<int, true> */
    private array $sourceEmployeeIds = [];

    /** @var array<string, array<int, true>> */
    private array $catalogIds = [];

    /** @var array<string, int> */
    private array $teamsByCode = [];

    /** @var array<string, int> */
    private array $usedSlugs = [];

    /** @var array<string, int> */
    private array $usedTaxIds = [];

    /** @var array<string, int> */
    private array $usedUsernames = [];

    /** @var array<string, true> */
    private array $usedEstablishmentCodes = [];

    private int $syntheticId = 1_000_000;

    /** @var list<array{entity: string, legacy_id: string, field: ?string, action: string, reason: string, original_value: ?string}> */
    private array $quarantine = [];

    /** @var array<string, int> */
    private array $counts = [];

    public function __construct(
        private readonly Command $command,
        private readonly bool $execute,
        private readonly int $chunk,
    ) {
        $this->legacy = DB::connection('legacy');
        $this->dest = DB::connection('migration_destination');
    }

    public function run(): int
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);
        $this->legacy->disableQueryLog();
        $this->dest->disableQueryLog();

        $this->command->info('Optima Legacy → V2 migration');
        $this->command->line('Source:      legacy');
        $this->command->line('Destination: migration_destination');
        $this->command->line($this->execute
            ? '<fg=yellow>EXECUTE MODE: destination operational tables will be written.</>'
            : '<info>DRY-RUN MODE: no operational rows. Mapping tables are created if missing.</info>');
        $this->command->newLine();

        $legacyMeta = $this->legacy->selectOne('SELECT VERSION() AS version, DATABASE() AS db');
        $destMeta = $this->dest->selectOne('SELECT VERSION() AS version, DATABASE() AS db');
        $this->command->line("Legacy:      {$legacyMeta->version} / {$legacyMeta->db}");
        $this->command->line("Destination: {$destMeta->version} / {$destMeta->db}");

        $or = $this->dest->table('companies')->where('tax_id', 'B66409087')->where('kind', 'operating_company')->first();
        if ($or === null || (int) $or->id !== 1) {
            $this->command->error('STOP: destination company id 1 is not Optima Retail (tax_id B66409087).');

            return Command::FAILURE;
        }
        $this->command->info("Optima Retail confirmed: companies.id={$or->id} tax_id={$or->tax_id}");

        $this->ensureMapTables();
        $this->loadExistingMaps();
        $this->companyMap['empresas:OR'] = (int) $or->id;
        $this->writeMap('migration_company_map', [
            'legacy_source' => 'empresas',
            'legacy_id' => 'OR',
            'new_id' => (int) $or->id,
        ]);

        $this->reloadCatalogIds();
        $this->loadExistingKeys();
        $this->importCatalogs();
        $this->backfillUserTeams();

        $this->migrateUsersPass1();
        $this->migrateBrands();
        $this->migrateUsersPass2();
        $plans = $this->buildIdentityPlans();
        $this->migrateCompanies($plans);
        $this->migrateCustomerRelationships($plans);
        $this->migrateTechnicianRelationships($plans);
        $this->backfillReportedCustomers();
        $this->migrateMemberships((int) $or->id);
        $this->migrateEstablishments();
        $this->importExpanded((int) $or->id);

        if ($this->execute) {
            $this->flushIdMaps();
            $this->flushQuarantine();
        }

        $this->printReport();

        return Command::SUCCESS;
    }

    private function loadCatalogs(): void
    {
        $this->reloadCatalogIds();
    }

    private function loadExistingKeys(): void
    {
        foreach ($this->dest->table('companies')->get(['id', 'slug', 'tax_id']) as $row) {
            if (is_string($row->slug) && $row->slug !== '') {
                $this->usedSlugs[$row->slug] = 1;
            }
            if (is_string($row->tax_id) && $row->tax_id !== '') {
                $this->usedTaxIds[$row->tax_id] = (int) $row->id;
            }
        }
    }

    private function ensureMapTables(): void
    {
        $this->command->info('Mapping tables on destination…');
        $schema = Schema::connection('migration_destination');

        $this->repairOrCreateMapTable($schema, 'migration_user_map', 'legacy_id', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_id');
            $table->unsignedBigInteger('new_id');
            $table->timestamps();
            $table->unique('legacy_id');
            $table->index('new_id');
        });

        $this->repairOrCreateMapTable($schema, 'migration_company_map', 'legacy_source', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_source', 32);
            $table->string('legacy_id', 64);
            $table->unsignedBigInteger('new_id');
            $table->timestamps();
            $table->unique(['legacy_source', 'legacy_id'], 'mig_company_map_unique');
            $table->index('new_id');
        });

        $this->repairOrCreateMapTable($schema, 'migration_company_relationship_map', 'legacy_source', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_source', 32);
            $table->unsignedBigInteger('legacy_id');
            $table->unsignedBigInteger('new_id');
            $table->string('kind', 32);
            $table->timestamps();
            $table->unique(['legacy_source', 'legacy_id'], 'mig_crel_map_unique');
            $table->index('new_id');
        });

        $this->repairOrCreateMapTable($schema, 'migration_brand_map', 'legacy_id', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_id');
            $table->unsignedBigInteger('new_id');
            $table->timestamps();
            $table->unique('legacy_id');
            $table->index('new_id');
        });

        $this->repairOrCreateMapTable($schema, 'migration_establishment_map', 'legacy_id', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_id');
            $table->unsignedBigInteger('new_id');
            $table->timestamps();
            $table->unique('legacy_id');
            $table->index('new_id');
        });

        $this->repairOrCreateMapTable($schema, 'migration_quarantine', 'entity', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 64);
            $table->string('legacy_id', 64)->nullable();
            $table->string('field', 64)->nullable();
            $table->string('action', 64);
            $table->text('reason');
            $table->text('original_value')->nullable();
            $table->timestamps();
            $table->index(['entity', 'legacy_id']);
        });

        $this->repairOrCreateMapTable($schema, 'migration_id_map', 'entity', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 64);
            $table->string('legacy_id', 64);
            $table->unsignedBigInteger('new_id');
            $table->timestamps();
            $table->unique(['entity', 'legacy_id'], 'mig_id_map_unique');
            $table->index('new_id');
        });

        foreach ([
            'database/migrations/2026_09_17_132212_create_migration_user_map_table.php',
            'database/migrations/2026_09_17_132213_create_migration_company_map_table.php',
            'database/migrations/2026_09_17_132214_create_migration_company_relationship_map_table.php',
            'database/migrations/2026_09_17_132215_create_migration_brand_map_table.php',
            'database/migrations/2026_09_17_132216_create_migration_establishment_map_table.php',
            'database/migrations/2026_09_17_140000_create_migration_quarantine_table.php',
            'database/migrations/2026_09_17_160000_create_migration_id_map_table.php',
        ] as $path) {
            try {
                Artisan::call('migrate', [
                    '--database' => 'migration_destination',
                    '--path' => $path,
                    '--force' => true,
                ]);
            } catch (Throwable $e) {
                $this->command->warn('Mapping migration record skipped for '.$path.': '.$e->getMessage());
            }
        }
    }

    /**
     * @param  callable(Blueprint): void  $define
     */
    private function repairOrCreateMapTable(object $schema, string $table, string $requiredColumn, callable $define): void
    {
        if ($schema->hasTable($table)) {
            $incomplete = ! $schema->hasColumn($table, $requiredColumn);
            if ($incomplete && $this->dest->table($table)->count() === 0) {
                $schema->drop($table);
            }
        }

        if (! $schema->hasTable($table)) {
            $schema->create($table, $define);
        }
    }

    private function loadExistingMaps(): void
    {
        foreach ($this->dest->table('migration_user_map')->get(['legacy_id', 'new_id']) as $row) {
            $this->userMap[(int) $row->legacy_id] = (int) $row->new_id;
        }
        foreach ($this->dest->table('migration_company_map')->get(['legacy_source', 'legacy_id', 'new_id']) as $row) {
            $this->companyMap[$row->legacy_source.':'.$row->legacy_id] = (int) $row->new_id;
        }
        foreach ($this->dest->table('migration_company_relationship_map')->get(['legacy_source', 'legacy_id', 'new_id']) as $row) {
            $this->relationshipMap[$row->legacy_source.':'.$row->legacy_id] = (int) $row->new_id;
        }
        foreach ($this->dest->table('migration_brand_map')->get(['legacy_id', 'new_id']) as $row) {
            $this->brandMap[(int) $row->legacy_id] = (int) $row->new_id;
        }
        foreach ($this->dest->table('migration_establishment_map')->get(['legacy_id', 'new_id']) as $row) {
            $this->establishmentMap[(int) $row->legacy_id] = (int) $row->new_id;
        }
        if ($this->destHasTable('migration_id_map')) {
            foreach ($this->dest->table('migration_id_map')->get(['entity', 'legacy_id', 'new_id']) as $row) {
                $this->idMaps[$row->entity][(string) $row->legacy_id] = (int) $row->new_id;
            }
        }
    }

    private function migrateUsersPass1(): void
    {
        foreach ($this->legacy->table('users')->whereNull('deleted_at')->where('empleado_optima', 1)->pluck('id') as $id) {
            $this->sourceEmployeeIds[(int) $id] = true;
        }
        $this->counts['employees'] = count($this->sourceEmployeeIds);
        $this->counts['sso_non_employees'] = $this->legacy->table('users')->whereNull('deleted_at')->where('empleado_optima', 0)->count();

        $sourceTotal = (int) $this->legacy->table('users')->whereNull('deleted_at')->count();
        if (count($this->userMap) >= $sourceTotal && $sourceTotal > 0) {
            $this->skipPhase('Users pass 1', count($this->userMap));
            $this->counts['users_would_insert'] = 0;
            $this->counts['users_would_adopt'] = 0;

            return;
        }

        $todoIds = [];
        $this->legacy->table('users')->whereNull('deleted_at')->orderBy('id')->select(['id'])->chunkById($this->chunk, function ($rows) use (&$todoIds): void {
            foreach ($rows as $row) {
                if (! isset($this->userMap[(int) $row->id])) {
                    $todoIds[] = (int) $row->id;
                }
            }
        });
        if ($todoIds === []) {
            $this->skipPhase('Users pass 1', count($this->userMap));
            $this->counts['users_would_insert'] = 0;
            $this->counts['users_would_adopt'] = 0;

            return;
        }

        $bar = $this->startProgress('Users pass 1 (missing '.count($todoIds).')', count($todoIds));

        $destEmails = [];
        foreach ($this->dest->table('users')->get(['id', 'email', 'username']) as $user) {
            $destEmails[strtolower((string) $user->email)] = (int) $user->id;
            if (is_string($user->username) && $user->username !== '') {
                $this->usedUsernames[strtolower($user->username)] = 1;
            }
        }
        $wouldInsert = 0;
        $wouldAdopt = 0;
        $buffer = [];

        foreach (array_chunk($todoIds, $this->chunk) as $idChunk) {
            $rows = $this->legacy->table('users')->whereNull('deleted_at')->whereIn('id', $idChunk)->orderBy('id')->get();
            foreach ($rows as $row) {
                $bar?->advance();
                if (isset($this->userMap[(int) $row->id])) {
                    continue;
                }

                $email = $this->str($row->email, 255);
                if ($email === null) {
                    $this->quarantine('users', (string) $row->id, 'email', 'skipped', 'email required on destination', (string) ($row->email ?? ''));

                    continue;
                }

                $emailKey = strtolower($email);
                if (isset($destEmails[$emailKey]) && $destEmails[$emailKey] > 0) {
                    $this->userMap[(int) $row->id] = $destEmails[$emailKey];
                    $wouldAdopt++;
                    $this->writeMap('migration_user_map', [
                        'legacy_id' => (int) $row->id,
                        'new_id' => $destEmails[$emailKey],
                    ]);

                    continue;
                }
                if (isset($destEmails[$emailKey])) {
                    $this->quarantine('users', (string) $row->id, 'email', 'skipped', 'duplicate email already claimed in this run', $email);

                    continue;
                }

                $wouldInsert++;
                $destEmails[$emailKey] = -1;
                if (! $this->execute) {
                    $this->userMap[(int) $row->id] = $this->syntheticId++;

                    continue;
                }

                $buffer[] = $this->userInsertRow($row);

                if (count($buffer) >= $this->chunk) {
                    $this->flushUsers($buffer);
                    $buffer = [];
                }
            }
        }

        if ($this->execute && $buffer !== []) {
            $this->flushUsers($buffer);
        }

        $this->finishProgress($bar);

        $this->counts['users_would_insert'] = $wouldInsert;
        $this->counts['users_would_adopt'] = $wouldAdopt;
    }

    /**
     * @param  list<array<string, mixed>>  $buffer
     */
    private function flushUsers(array $buffer): void
    {
        $legacyIds = array_column($buffer, '_legacy_id');
        foreach ($buffer as &$row) {
            unset($row['_legacy_id']);
        }
        unset($row);

        $this->dest->table('users')->insert($buffer);
        $emails = array_column($buffer, 'email');
        $inserted = $this->dest->table('users')->whereIn('email', $emails)->get(['id', 'email']);
        $byEmail = [];
        foreach ($inserted as $user) {
            $byEmail[$user->email] = (int) $user->id;
        }

        $now = now();
        $maps = [];
        foreach ($buffer as $i => $row) {
            $newId = $byEmail[$row['email']] ?? null;
            if ($newId === null) {
                continue;
            }
            $this->userMap[(int) $legacyIds[$i]] = $newId;
            $maps[] = [
                'legacy_id' => (int) $legacyIds[$i],
                'new_id' => $newId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($maps !== []) {
            $this->dest->table('migration_user_map')->insert($maps);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function userInsertRow(object $row): array
    {
        $phone = $this->str($row->telefono, 50);
        if ($row->telefono !== null && $phone === null && trim((string) $row->telefono) !== '') {
            $this->quarantine('users', (string) $row->id, 'phone', 'null_overflow', 'phone longer than 50', (string) $row->telefono);
        }

        $locale = $this->str($row->locale, 10);
        $username = $this->str($row->username, 255);
        if ($username !== null) {
            $usernameKey = strtolower($username);
            if (isset($this->usedUsernames[$usernameKey])) {
                $this->quarantine('users', (string) $row->id, 'username', 'null_collision', 'username already used on destination', $username);
                $username = null;
            } else {
                $this->usedUsernames[$usernameKey] = 1;
            }
        }

        return [
            '_legacy_id' => (int) $row->id,
            'name' => $this->str($row->name, 255) ?? 'User '.$row->id,
            'email' => $this->str($row->email, 255),
            'username' => $username,
            'password' => (string) $row->password,
            'locale' => $locale,
            'manager_id' => null,
            'team_leader_id' => null,
            'team_id' => $this->teamId($row->equipo_id ?? null),
            'timezone_id' => $this->catalog((int) ($row->zona_horaria_id ?? 0), 'timezones'),
            'brand_id' => null,
            'tenant_id' => $this->catalog((int) ($row->tenant_id ?? 0), 'tenants'),
            'phone' => $phone,
            'telephony_phone_number' => isset($row->ringover_telefono) && $row->ringover_telefono !== null
                ? $this->str((string) $row->ringover_telefono, 30)
                : null,
            'pbx_extension' => $this->str($row->pbx_extension ?? null, 20),
            'telegram_user_id' => $this->str($row->telegram_user_id ?? null, 255),
            'external_hr_id' => $this->str($row->external_hr_id ?? null, 255),
            'is_active' => $this->bool($row->activo),
            'is_internal_employee' => $this->bool($row->empleado_optima),
            'is_team_account' => $this->bool($row->es_usuario_equipo),
            'is_preventive_specialist' => $row->es_preventivo === null ? null : $this->bool($row->es_preventivo),
            'performance_factor' => $row->factor_x ?? 1,
            'invoiced_revenue_target' => $row->objetivo_eur_facturados,
            'quality_score' => $row->puntuacion_qc ?? 0,
            'balance' => $row->saldo ?? 0,
            'budget_approval_limit' => $row->budget_approval_limit ?? 0,
            'sso_only' => $this->bool($row->login_unicamente_sso),
            'must_change_password' => $this->bool($row->must_change_password ?? false),
            'totp_secret' => $row->totp_secret,
            'locked_at' => $row->bloqueado_at ?? null,
            'remember_token' => $row->remember_token,
            'email_verified_at' => $row->email_verified_at,
            'active_company_id' => null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    private function migrateUsersPass2(): void
    {
        if (! $this->execute) {
            return;
        }

        $destState = [];
        foreach ($this->dest->table('users')->get(['id', 'manager_id', 'team_leader_id', 'brand_id']) as $user) {
            $destState[(int) $user->id] = $user;
        }

        $query = $this->legacy->table('users')
            ->whereNull('deleted_at')
            ->where(function ($q): void {
                $q->whereNotNull('manager_id')
                    ->orWhereNotNull('team_leader_id')
                    ->orWhereNotNull('marca_id');
            });

        $pending = [];
        $query->orderBy('id')->chunkById($this->chunk, function ($rows) use (&$pending, $destState): void {
            foreach ($rows as $row) {
                $newId = $this->userMap[(int) $row->id] ?? null;
                if ($newId === null || $newId < 1) {
                    continue;
                }
                $current = $destState[$newId] ?? null;
                $wanted = [
                    'manager_id' => ($row->manager_id && isset($this->userMap[(int) $row->manager_id]))
                        ? $this->userMap[(int) $row->manager_id]
                        : null,
                    'team_leader_id' => ($row->team_leader_id && isset($this->userMap[(int) $row->team_leader_id]))
                        ? $this->userMap[(int) $row->team_leader_id]
                        : null,
                    'brand_id' => ($row->marca_id && isset($this->brandMap[(int) $row->marca_id]))
                        ? $this->brandMap[(int) $row->marca_id]
                        : null,
                ];
                $update = [];
                foreach ($wanted as $col => $value) {
                    if ($value === null) {
                        continue;
                    }
                    $existing = $current?->{$col} ?? null;
                    if ((int) $existing !== (int) $value) {
                        $update[$col] = $value;
                    }
                }
                if ($update !== []) {
                    $pending[] = [$newId, $update];
                }
            }
        });

        if ($pending === []) {
            $this->skipPhase('Users pass 2 (manager / brand)', count($this->userMap), 'already linked');

            return;
        }

        $bar = $this->startProgress('Users pass 2 (missing '.count($pending).')', count($pending));
        foreach ($pending as [$newId, $update]) {
            $bar?->advance();
            $this->dest->table('users')->where('id', $newId)->update($update);
        }
        $this->finishProgress($bar);
    }

    private function migrateBrands(): void
    {

        $destByName = [];
        foreach ($this->dest->table('brands')->get(['id', 'name']) as $brand) {
            $destByName[strtoupper(trim((string) $brand->name))] = (int) $brand->id;
        }

        $referencedDeleted = $this->legacy->table('clientes as c')
            ->join('marcas as m', 'm.id', '=', 'c.marca_id')
            ->whereNull('c.deleted_at')
            ->whereNotNull('m.deleted_at')
            ->distinct()
            ->pluck('m.id')
            ->all();

        $query = $this->legacy->table('marcas')
            ->where(function ($q) use ($referencedDeleted): void {
                $q->whereNull('deleted_at');
                if ($referencedDeleted !== []) {
                    $q->orWhereIn('id', $referencedDeleted);
                }
            })
            ->orderBy('id');

        $insert = 0;
        $adopt = 0;
        $rows = $query->get();
        $pending = $rows->filter(fn ($row): bool => ! isset($this->brandMap[(int) $row->id]));
        if ($pending->isEmpty()) {
            $this->skipPhase('Brands', $rows->count());
            $this->counts['brands_would_insert'] = 0;
            $this->counts['brands_would_adopt'] = 0;

            return;
        }

        $bar = $this->startProgress('Brands (missing '.$pending->count().')', $pending->count());

        foreach ($pending as $row) {
            $bar?->advance();
            $name = strtoupper(trim((string) $row->nombre));
            if ($name === '') {
                $this->quarantine('marcas', (string) $row->id, 'name', 'skipped', 'empty name', null);

                continue;
            }

            if (isset($destByName[$name])) {
                $this->brandMap[(int) $row->id] = $destByName[$name];
                $this->writeMap('migration_brand_map', [
                    'legacy_id' => (int) $row->id,
                    'new_id' => $destByName[$name],
                ]);
                $adopt++;

                continue;
            }

            $insert++;
            if (! $this->execute) {
                $this->brandMap[(int) $row->id] = $this->syntheticId++;
                $destByName[$name] = $this->brandMap[(int) $row->id];

                continue;
            }

            $newId = $this->dest->table('brands')->insertGetId([
                'name' => $name,
                'account_manager_id' => $this->mappedUser($row->responsable_id),
                'commercial_manager_id' => $this->mappedUser($row->responsable_comercial_id),
                'loyalty_meeting_frequency' => $this->str($row->periodicidad_reunion_fidelizacion, 255),
                'is_quality_control_contactable' => $this->bool($row->contactable_qc ?? true),
                'send_debt_reminders' => $this->bool($row->send_debt_reminders ?? true),
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->brandMap[(int) $row->id] = $newId;
            $destByName[$name] = $newId;
            $this->writeMap('migration_brand_map', [
                'legacy_id' => (int) $row->id,
                'new_id' => $newId,
            ]);
        }

        $this->finishProgress($bar);

        $this->counts['brands_would_insert'] = $insert;
        $this->counts['brands_would_adopt'] = $adopt;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildIdentityPlans(): array
    {
        $clientes = $this->legacy->table('clientes')->whereNull('deleted_at')->orderBy('id')->get();
        $tecnicos = $this->legacy->table('tecnicos')->whereNull('deleted_at')->orderBy('id')->get();
        $bar = $this->startProgress('NIF identity plan', $clientes->count() + $tecnicos->count());

        $plans = [];
        $nifOwner = [];

        foreach ($clientes as $row) {
            $bar?->advance();
            $nif = Nif::normalize($row->nif);
            $name = Nif::normalizedName($row->razon_social, $row->nombre_comercial);
            $key = null;
            $forceNullTax = false;

            if ($nif !== null && isset($nifOwner[$nif])) {
                $owner = $nifOwner[$nif];
                if ($owner['name'] === $name) {
                    $key = $owner['key'];
                    $plans[$key]['cliente_ids'][] = (int) $row->id;
                    $plans[$key]['share_customer_rel'] = true;
                } else {
                    $key = 'cliente:'.$row->id;
                    $forceNullTax = true;
                    $this->quarantine('clientes', (string) $row->id, 'nif', 'ambiguous_duplicate', 'Same valid NIF as '.$owner['key'].' with a different name', (string) $row->nif);
                }
            } elseif ($nif !== null) {
                $key = 'nif:'.$nif;
                $nifOwner[$nif] = ['key' => $key, 'name' => $name, 'source' => 'cliente'];
            } else {
                $key = 'cliente:'.$row->id;
                if ($row->nif !== null && trim((string) $row->nif) !== '') {
                    $this->quarantine('clientes', (string) $row->id, 'nif', 'placeholder', 'Placeholder/empty NIF', (string) $row->nif);
                }
            }

            if (! isset($plans[$key])) {
                $taxId = $forceNullTax ? null : $this->usableTaxId($nif, (string) $row->id, 'clientes');
                $plans[$key] = [
                    'tax_id' => $taxId,
                    'cliente_ids' => [(int) $row->id],
                    'tecnico_ids' => [],
                    'share_customer_rel' => false,
                    'identity' => $this->clienteIdentity($row),
                ];
            } elseif (! isset($plans[$key]['identity'])) {
                $plans[$key]['identity'] = $this->clienteIdentity($row);
            }
        }

        foreach ($tecnicos as $row) {
            $bar?->advance();
            $nif = Nif::normalize($row->nif);
            $name = Nif::normalizedName($row->nombre_fiscal, $row->nombre_comercial);
            $key = null;

            $forceNullTax = false;
            if ($nif !== null && isset($nifOwner[$nif])) {
                $owner = $nifOwner[$nif];
                if ($owner['source'] === 'cliente' || $owner['name'] === $name) {
                    $key = $owner['key'];
                    $plans[$key]['tecnico_ids'][] = (int) $row->id;
                    $plans[$key]['identity'] = $this->mergeTecnicoIdentity($plans[$key]['identity'] ?? [], $row);
                    $this->bump('companies_merged_nif');
                } else {
                    $key = 'tecnico:'.$row->id;
                    $forceNullTax = true;
                    $this->quarantine('tecnicos', (string) $row->id, 'nif', 'ambiguous_duplicate', 'Same valid NIF as '.$owner['key'].' with a different name', (string) $row->nif);
                }
            } elseif ($nif !== null) {
                $key = 'nif:'.$nif;
                $nifOwner[$nif] = ['key' => $key, 'name' => $name, 'source' => 'tecnico'];
            } else {
                $key = 'tecnico:'.$row->id;
            }

            if (! isset($plans[$key])) {
                $plans[$key] = [
                    'tax_id' => $forceNullTax ? null : $this->usableTaxId($nif, (string) $row->id, 'tecnicos'),
                    'cliente_ids' => [],
                    'tecnico_ids' => [(int) $row->id],
                    'share_customer_rel' => false,
                    'identity' => $this->tecnicoIdentity($row),
                ];
            } elseif (! in_array((int) $row->id, $plans[$key]['tecnico_ids'], true)) {
                $plans[$key]['tecnico_ids'][] = (int) $row->id;
            }
        }

        $this->finishProgress($bar);

        $this->counts['companies_planned'] = count($plans);
        $this->counts['clientes_read'] = $clientes->count();
        $this->counts['tecnicos_read'] = $tecnicos->count();
        $this->counts['ambiguous_nif'] = count(array_filter(
            $this->quarantine,
            fn (array $q): bool => $q['action'] === 'ambiguous_duplicate',
        ));

        return $plans;
    }

    /**
     * @param  array<string, array<string, mixed>>  $plans
     */
    private function migrateCompanies(array $plans): void
    {
        $pending = [];
        $bar = $this->startProgress('Party companies (check maps)', count($plans));
        foreach ($plans as $key => $plan) {
            $bar?->advance();
            $existing = $this->existingCompanyId($plan);
            if ($existing !== null) {
                $this->mapCompanySources($plan, $existing);

                continue;
            }
            $pending[$key] = $plan;
        }
        $this->finishProgress($bar);

        if ($pending === []) {
            $this->skipPhase('Party companies', count($plans));
            $this->counts['companies_would_create'] = 0;

            return;
        }

        $created = 0;
        $bar = $this->startProgress('Party companies (missing '.count($pending).')', count($pending));

        foreach ($pending as $key => $plan) {
            $bar?->advance();
            $created++;
            if (! $this->execute) {
                $this->mapCompanySources($plan, $this->syntheticId++);

                continue;
            }

            $identity = $plan['identity'];
            $taxId = $plan['tax_id'];
            if ($taxId !== null && isset($this->usedTaxIds[$taxId])) {
                $this->quarantine('companies', $key, 'tax_id', 'null_collision', 'tax_id already on destination', $taxId);
                $taxId = null;
            }

            $newId = $this->dest->table('companies')->insertGetId([
                'name' => $identity['name'],
                'tradename' => $identity['tradename'],
                'slug' => $this->uniqueSlug($identity['name']),
                'tax_id' => $taxId,
                'kind' => 'party',
                'country_id' => $identity['country_id'],
                'residence_country_id' => $identity['residence_country_id'],
                'person_type' => $identity['person_type'],
                'email' => $identity['email'],
                'phone' => $identity['phone'],
                'website' => $identity['website'],
                'address_line_1' => $identity['address_line_1'],
                'address_line_2' => $identity['address_line_2'],
                'city' => $identity['city'],
                'province_id' => null,
                'postal_code' => $identity['postal_code'],
                'employee_count' => $identity['employee_count'],
                'is_active' => 1,
                'language_id' => $identity['language_id'],
                'latitude' => $identity['latitude'],
                'longitude' => $identity['longitude'],
                'created_at' => $identity['created_at'],
                'updated_at' => $identity['updated_at'],
            ]);

            if ($taxId !== null) {
                $this->usedTaxIds[$taxId] = $newId;
            }
            $this->mapCompanySources($plan, $newId);
        }

        $this->finishProgress($bar);

        $this->counts['companies_would_create'] = $created;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function existingCompanyId(array $plan): ?int
    {
        foreach ($plan['cliente_ids'] as $id) {
            if (isset($this->companyMap['clientes:'.$id])) {
                return $this->companyMap['clientes:'.$id];
            }
        }
        foreach ($plan['tecnico_ids'] as $id) {
            if (isset($this->companyMap['tecnicos:'.$id])) {
                return $this->companyMap['tecnicos:'.$id];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function mapCompanySources(array $plan, int $newId): void
    {
        foreach ($plan['cliente_ids'] as $id) {
            $key = 'clientes:'.$id;
            if (isset($this->companyMap[$key])) {
                continue;
            }
            $this->companyMap[$key] = $newId;
            $this->writeMap('migration_company_map', [
                'legacy_source' => 'clientes',
                'legacy_id' => (string) $id,
                'new_id' => $newId,
            ]);
        }
        foreach ($plan['tecnico_ids'] as $id) {
            $key = 'tecnicos:'.$id;
            if (isset($this->companyMap[$key])) {
                continue;
            }
            $this->companyMap[$key] = $newId;
            $this->writeMap('migration_company_map', [
                'legacy_source' => 'tecnicos',
                'legacy_id' => (string) $id,
                'new_id' => $newId,
            ]);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $plans
     */
    private function migrateCustomerRelationships(array $plans): void
    {
        $ownerId = $this->companyMap['empresas:OR'];
        $needsWork = false;
        foreach ($plans as $plan) {
            foreach ($plan['cliente_ids'] as $clienteId) {
                if (! isset($this->relationshipMap['clientes:'.$clienteId])) {
                    $needsWork = true;
                    break 2;
                }
            }
        }
        if (! $needsWork) {
            $this->skipPhase('Customer relationships', count($this->relationshipMap), 'already mapped');
            $this->counts['customer_relationships'] = 0;

            return;
        }

        $created = 0;
        $bar = $this->startProgress('Customer relationships', count($plans));

        $clientes = $this->legacy->table('clientes')->whereNull('deleted_at')->orderBy('id')->get()->keyBy('id');

        foreach ($plans as $plan) {
            $bar?->advance();
            $sharedId = null;
            foreach ($plan['cliente_ids'] as $clienteId) {
                if (isset($this->relationshipMap['clientes:'.$clienteId])) {
                    if ($sharedId === null) {
                        $sharedId = $this->relationshipMap['clientes:'.$clienteId];
                    }

                    continue;
                }

                $relatedId = $this->companyMap['clientes:'.$clienteId] ?? null;
                if ($relatedId === null || $relatedId === $ownerId) {
                    $this->quarantine('clientes', (string) $clienteId, 'company', 'skipped', 'Missing party company or owner=related', null);

                    continue;
                }

                if ($plan['share_customer_rel'] && $sharedId !== null) {
                    $this->relationshipMap['clientes:'.$clienteId] = $sharedId;
                    $this->writeMap('migration_company_relationship_map', [
                        'legacy_source' => 'clientes',
                        'legacy_id' => $clienteId,
                        'new_id' => $sharedId,
                        'kind' => 'customer',
                    ]);

                    continue;
                }

                $created++;
                $row = $clientes[$clienteId];
                if (! $this->execute) {
                    if ($sharedId === null) {
                        $sharedId = $this->syntheticId++;
                    }
                    $this->relationshipMap['clientes:'.$clienteId] = $sharedId;

                    continue;
                }

                $newId = $this->dest->table('company_relationships')->insertGetId(
                    $this->customerRelationshipRow($row, $ownerId, $relatedId),
                );
                $this->relationshipMap['clientes:'.$clienteId] = $newId;
                if ($sharedId === null) {
                    $sharedId = $newId;
                }
                $this->writeMap('migration_company_relationship_map', [
                    'legacy_source' => 'clientes',
                    'legacy_id' => $clienteId,
                    'new_id' => $newId,
                    'kind' => 'customer',
                ]);
            }
        }

        $this->finishProgress($bar);

        $this->counts['customer_relationships'] = $created;
    }

    /**
     * @param  array<string, array<string, mixed>>  $plans
     */
    private function migrateTechnicianRelationships(array $plans): void
    {
        $ownerId = $this->companyMap['empresas:OR'];
        $needsWork = false;
        foreach ($plans as $plan) {
            foreach ($plan['tecnico_ids'] as $tecnicoId) {
                if (! isset($this->relationshipMap['tecnicos:'.$tecnicoId])) {
                    $needsWork = true;
                    break 2;
                }
            }
        }
        if (! $needsWork) {
            $this->skipPhase('Technician relationships', count($this->relationshipMap), 'already mapped');
            $this->counts['technician_relationships'] = 0;

            return;
        }

        $created = 0;
        $sharedByCompany = [];
        $bar = $this->startProgress('Technician relationships', count($plans));

        $tecnicos = $this->legacy->table('tecnicos')->whereNull('deleted_at')->orderBy('id')->get()->keyBy('id');

        foreach ($plans as $plan) {
            $bar?->advance();
            foreach ($plan['tecnico_ids'] as $tecnicoId) {
                if (isset($this->relationshipMap['tecnicos:'.$tecnicoId])) {
                    continue;
                }

                $relatedId = $this->companyMap['tecnicos:'.$tecnicoId] ?? null;
                if ($relatedId === null || $relatedId === $ownerId) {
                    $this->quarantine('tecnicos', (string) $tecnicoId, 'company', 'skipped', 'Missing party company or owner=related', null);

                    continue;
                }

                $shareKey = $relatedId.':technician';
                if (isset($sharedByCompany[$shareKey])) {
                    $this->relationshipMap['tecnicos:'.$tecnicoId] = $sharedByCompany[$shareKey];
                    $this->writeMap('migration_company_relationship_map', [
                        'legacy_source' => 'tecnicos',
                        'legacy_id' => $tecnicoId,
                        'new_id' => $sharedByCompany[$shareKey],
                        'kind' => 'technician',
                    ]);

                    continue;
                }

                $created++;
                $row = $tecnicos[$tecnicoId];
                if (! $this->execute) {
                    $sharedByCompany[$shareKey] = $this->syntheticId++;
                    $this->relationshipMap['tecnicos:'.$tecnicoId] = $sharedByCompany[$shareKey];

                    continue;
                }

                $newId = $this->dest->table('company_relationships')->insertGetId(
                    $this->technicianRelationshipRow($row, $ownerId, $relatedId),
                );
                $this->relationshipMap['tecnicos:'.$tecnicoId] = $newId;
                $sharedByCompany[$shareKey] = $newId;
                $this->writeMap('migration_company_relationship_map', [
                    'legacy_source' => 'tecnicos',
                    'legacy_id' => $tecnicoId,
                    'new_id' => $newId,
                    'kind' => 'technician',
                ]);
            }
        }

        $this->finishProgress($bar);

        $this->counts['technician_relationships'] = $created;
    }

    private function backfillReportedCustomers(): void
    {
        if (! $this->execute) {
            return;
        }

        $total = (int) $this->legacy->table('clientes')->whereNull('deleted_at')->whereNotNull('cliente_reportado_id')->count();
        $bar = $this->startProgress('Reported-customer backfill', $total);

        $this->legacy->table('clientes')->whereNull('deleted_at')->whereNotNull('cliente_reportado_id')->orderBy('id')->chunkById($this->chunk, function ($rows) use ($bar): void {
            foreach ($rows as $row) {
                $bar?->advance();
                $relId = $this->relationshipMap['clientes:'.$row->id] ?? null;
                $reported = $this->relationshipMap['clientes:'.$row->cliente_reportado_id] ?? null;
                if ($relId === null || $reported === null) {
                    continue;
                }
                $this->dest->table('company_relationships')->where('id', $relId)->update([
                    'reported_customer_relationship_id' => $reported,
                ]);
            }
        });

        $this->finishProgress($bar);
    }

    private function migrateMemberships(int $orId): void
    {
        $created = 0;
        $total = (int) $this->legacy->table('users')->whereNull('deleted_at')->where('empleado_optima', 1)->count();
        $bar = $this->startProgress('company_user (employees)', $total);

        $this->legacy->table('users')->whereNull('deleted_at')->where('empleado_optima', 1)->orderBy('id')->chunkById($this->chunk, function ($rows) use ($orId, &$created, $bar): void {
            foreach ($rows as $row) {
                $bar?->advance();
                $userId = $this->userMap[(int) $row->id] ?? null;
                if ($userId === null) {
                    continue;
                }
                $created++;
                if (! $this->execute || $userId < 1) {
                    continue;
                }

                $existing = $this->dest->table('company_user')
                    ->where('company_id', $orId)
                    ->where('user_id', $userId)
                    ->first();

                if ($existing) {
                    $this->dest->table('company_user')->where('id', $existing->id)->update([
                        'is_active' => $this->bool($row->activo),
                        'deleted_at' => null,
                    ]);
                } else {
                    $this->dest->table('company_user')->insert([
                        'company_id' => $orId,
                        'user_id' => $userId,
                        'is_active' => $this->bool($row->activo),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->dest->table('users')->where('id', $userId)->whereNull('active_company_id')->update([
                    'active_company_id' => $orId,
                ]);
            }
        });

        $this->finishProgress($bar);

        $this->counts['company_user'] = $created;
    }

    private function migrateEstablishments(): void
    {
        $created = 0;

        foreach ($this->dest->table('establishments')->get(['company_id', 'code']) as $row) {
            if (is_string($row->code) && $row->code !== '') {
                $this->usedEstablishmentCodes[$row->company_id.':'.strtolower($row->code)] = true;
            }
        }

        $sourceTotal = (int) $this->legacy->table('establecimientos')->whereNull('deleted_at')->count();
        if (count($this->establishmentMap) >= $sourceTotal && $sourceTotal > 0) {
            $this->skipPhase('Establishments', count($this->establishmentMap));
            $this->counts['establishments'] = 0;

            return;
        }

        $todoIds = [];
        $this->legacy->table('establecimientos')->whereNull('deleted_at')->orderBy('id')->select(['id'])->chunkById($this->chunk, function ($rows) use (&$todoIds): void {
            foreach ($rows as $row) {
                if (! isset($this->establishmentMap[(int) $row->id])) {
                    $todoIds[] = (int) $row->id;
                }
            }
        });
        if ($todoIds === []) {
            $this->skipPhase('Establishments', count($this->establishmentMap));
            $this->counts['establishments'] = 0;

            return;
        }

        $bar = $this->startProgress('Establishments (missing '.count($todoIds).')', count($todoIds));

        foreach (array_chunk($todoIds, $this->chunk) as $idChunk) {
            $rows = $this->legacy->table('establecimientos')->whereNull('deleted_at')->whereIn('id', $idChunk)->orderBy('id')->get();
            foreach ($rows as $row) {
                $bar?->advance();
                if (isset($this->establishmentMap[(int) $row->id])) {
                    continue;
                }
                $companyId = $this->companyMap['clientes:'.$row->cliente_id] ?? null;
                if ($companyId === null) {
                    $this->quarantine('establecimientos', (string) $row->id, 'company_id', 'skipped', 'cliente not mapped', (string) $row->cliente_id);

                    continue;
                }

                $created++;
                if (! $this->execute) {
                    $this->establishmentMap[(int) $row->id] = $this->syntheticId++;

                    continue;
                }

                $payload = $this->establishmentInsertRow($row, $companyId);
                $legacyId = (int) $payload['_legacy_id'];
                unset($payload['_legacy_id']);
                $newId = $this->dest->table('establishments')->insertGetId($payload);
                $this->establishmentMap[$legacyId] = $newId;
                $this->writeMap('migration_establishment_map', [
                    'legacy_id' => $legacyId,
                    'new_id' => $newId,
                ]);
            }
        }

        $this->finishProgress($bar);

        $this->counts['establishments'] = $created;
    }

    /**
     * @return array<string, mixed>
     */
    private function establishmentInsertRow(object $row, int $companyId): array
    {
        $billingId = null;
        if ($row->cliente_facturacion_id) {
            $billingId = $this->companyMap['clientes:'.$row->cliente_facturacion_id] ?? null;
        }

        $code = $this->str($row->codigo, 64);
        if ($code !== null && $this->execute) {
            $codeKey = $companyId.':'.strtolower($code);
            if (isset($this->usedEstablishmentCodes[$codeKey])) {
                $this->quarantine('establecimientos', (string) $row->id, 'code', 'null_collision', 'duplicate (company_id, code)', $code);
                $code = null;
            } else {
                $this->usedEstablishmentCodes[$codeKey] = true;
            }
        }

        return [
            '_legacy_id' => (int) $row->id,
            'company_id' => $companyId,
            'name' => $this->str($row->nombre, 255) ?? 'Establishment '.$row->id,
            'code' => $code,
            'store_code' => $this->str($row->codigo_tienda, 64),
            'alternate_store_code' => $this->str($row->codigo_tienda_2, 64),
            'phone' => $this->str($row->telefono, 50),
            'email' => $this->str($row->correo, 255),
            'emails' => $row->correos,
            'recipient_emails' => $row->correos_destinatarios,
            'address_line_1' => $this->str($row->direccion_1, 255),
            'address_line_2' => $this->str($row->direccion_2, 255),
            'city' => $this->str($row->poblacion, 255),
            'province_id' => null,
            'postal_code' => $this->str($row->codigo_postal, 20),
            'country_id' => $this->catalog((int) ($row->pais_id ?? 0), 'countries'),
            'timezone_id' => $this->catalog((int) ($row->zona_horaria_id ?? 0), 'timezones'),
            'language_id' => $this->catalog((int) ($row->idioma_id ?? 0), 'languages'),
            'establishment_type_id' => $this->catalog((int) ($row->tipos_establecimiento_id ?? 0), 'establishment_types'),
            'delegation_id' => $this->catalog((int) ($row->delegacion_id ?? 0), 'delegations'),
            'series_id' => $this->catalog((int) ($row->serie_id ?? 0), 'series'),
            'billing_company_id' => $billingId,
            'responsible_user_id' => $this->mappedUser($row->responsable_id),
            'is_active' => $this->bool($row->estado),
            'is_client_priority' => $this->bool($row->importante_cliente),
            'is_reviewed' => $this->bool($row->revisado),
            'is_email_reviewed' => $this->bool($row->revisado_email),
            'has_site_health_and_safety' => $this->bool($row->prl_centro),
            'has_customer_health_and_safety' => $this->bool($row->prl_cliente),
            'is_quality_control_contactable' => $this->bool($row->contactable_qc ?? false),
            'has_parking' => $this->bool($row->parking),
            'is_ulez_zone' => $this->bool($row->ulez),
            'latitude' => $this->coord($row->latitud ?? null),
            'longitude' => $this->coord($row->longitud ?? null),
            'tax_rate' => $row->iva_valor,
            'tax_included' => $this->bool($row->iva_incluido),
            'integration_external_id' => $this->str($row->integracion_relacion_id !== null ? (string) $row->integracion_relacion_id : null, 80),
            'notes' => $row->observaciones,
            'notes_alert' => $this->bool($row->observaciones_aviso),
            'internal_notes' => $row->observaciones_privadas,
            'internal_notes_alert' => $this->bool($row->observaciones_privadas_aviso),
            'voicebot_time_slots' => is_string($row->nora_franjas_horario) || is_array($row->nora_franjas_horario)
                ? (is_string($row->nora_franjas_horario) ? $row->nora_franjas_horario : json_encode($row->nora_franjas_horario))
                : null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerRelationshipRow(object $row, int $ownerId, int $relatedId): array
    {
        return [
            'owner_company_id' => $ownerId,
            'related_company_id' => $relatedId,
            'kind' => 'customer',
            'status' => ((int) $row->estado === 1) ? 'active' : 'inactive',
            'classification' => $this->bool($row->is_intercompany) ? 'intercompany' : 'commercial',
            'brand_id' => $this->brandMap[(int) $row->marca_id] ?? null,
            'delegation_id' => $this->catalog((int) ($row->delegacion_id ?? 0), 'delegations'),
            'billing_language_id' => $this->catalog((int) ($row->billing_language_id ?? 0), 'languages'),
            'series_id' => $this->catalog((int) ($row->serie_id ?? 0), 'series'),
            'integration_id' => $this->catalog((int) ($row->integracion_id ?? 0), 'integrations'),
            'integration_external_id' => $this->str($row->integracion_relacion_id !== null ? (string) $row->integracion_relacion_id : null, 80),
            'reported_customer_relationship_id' => null,
            'corrective_work_order_owner_id' => $this->memberUser($row->responsable_ots_correctivas_id),
            'preventive_work_order_owner_id' => $this->memberUser($row->responsable_ots_preventivas_id),
            'quality_owner_id' => $this->memberUser($row->responsable_qc_id),
            'account_owner_id' => $this->memberUser($row->responsable_cliente_id),
            'commercial_owner_id' => $this->memberUser($row->responsable_comercial_id),
            'notes' => $row->observaciones,
            'internal_notes' => $row->observaciones_privadas,
            'notes_alert' => $this->bool($row->observaciones_aviso),
            'internal_notes_alert' => $this->bool($row->observaciones_privadas_aviso),
            'onboarding_notes' => $row->onboarding,
            'billing_comments' => $row->comentarios_facturacion,
            'archetype' => $row->arquetipo,
            'group_zero_cost_work_orders' => $this->bool($row->agrupar_ots_sin_coste),
            'load_materials_on_corrective' => $this->bool($row->cargar_lm_en_correctivo),
            'group_preventive_and_corrective' => $this->bool($row->agrupar_preventivos_y_correctivos),
            'group_preventives_by' => self::GROUPING[$row->agrupar_preventivos_por ?? ''] ?? null,
            'group_correctives_by' => self::GROUPING[$row->agrupar_correctivos_por ?? ''] ?? null,
            'invoice_at_month_end' => $this->bool($row->facturar_fin_mes),
            'requires_purchase_order' => $this->bool($row->po_requerida),
            'requires_requester' => $this->bool($row->solicitante_requerido),
            'is_franchise' => $this->bool($row->es_franquicia),
            'requires_justification' => $this->bool($row->justificacion_obligatoria),
            'auto_send_invoices' => $this->bool($row->facturacion_envio_automatico),
            'send_invoices_individually' => $this->bool($row->facturacion_envio_individual),
            'send_debt_reminders' => $this->bool($row->send_debt_reminder),
            'is_quality_control_contactable' => $this->bool($row->contactable_qc ?? false),
            'requires_client_informed_check' => $this->bool($row->requires_client_informed_check),
            'requires_intervention_scheduled_check' => $this->bool($row->requires_intervention_scheduled_check),
            'requires_budget_approval_limit' => $this->bool($row->requires_budget_approval_limit),
            'is_intercompany' => $this->bool($row->is_intercompany),
            'quote_close_days' => $this->unsignedInt($row->dias_cierre_presupuesto ?? null),
            'recurring_meeting_frequency' => $this->unsignedInt($row->meetings_frequency_recurring ?? null),
            'sales_feedback_meeting_frequency' => $this->unsignedInt($row->meetings_frequency_feedback_sales ?? null),
            'is_invoicing_reviewed' => $this->bool($row->facturacion_revisada),
            'is_email_reviewed' => $this->bool($row->correo_revisado),
            'deleted_token' => '',
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function technicianRelationshipRow(object $row, int $ownerId, int $relatedId): array
    {
        return [
            'owner_company_id' => $ownerId,
            'related_company_id' => $relatedId,
            'kind' => 'technician',
            'status' => 'active',
            'classification' => 'commercial',
            'delegation_id' => $this->catalog((int) ($row->delegacion_id ?? 0), 'delegations'),
            'tax_rate' => $row->impuesto,
            'rates_notes' => $row->tarifas,
            'notes' => $row->observaciones,
            'internal_notes' => $row->observaciones_privadas,
            'notes_alert' => $this->bool($row->observaciones_aviso ?? false),
            'internal_notes_alert' => $this->bool($row->observaciones_privadas_aviso ?? false),
            'optima_score' => $row->puntuacion_or,
            'customer_score' => $row->puntuacion_cliente,
            'average_score' => $row->puntuacion_media,
            'optima_score_count' => $row->puntuacion_or_num ?? 0,
            'customer_score_count' => $row->puntuacion_cliente_num ?? 0,
            'has_health_and_safety' => $this->bool($row->prl),
            'is_field_technician' => $this->bool($row->es_tecnico),
            'is_creditor' => $this->bool($row->es_acreedor),
            'is_vip' => $this->bool($row->es_vip),
            'is_available_24h' => $this->bool($row->disponible_24h),
            'day_start_at' => $row->hora_dia_inicio,
            'day_end_at' => $row->hora_dia_fin,
            'has_garnishment' => $this->bool($row->tiene_embargo),
            'whatsapp_messaging_authorized' => $this->bool($row->whatsapp_message_authorisation),
            'sourced_by_user_id' => $this->memberUser($row->encontrado_por_id),
            'account_owner_id' => $this->memberUser($row->responsable_id),
            'is_reviewed' => $this->bool($row->revisado),
            'registered_at' => $row->fecha_alta,
            'legacy_status_id' => $row->estado_id,
            'deleted_token' => '',
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clienteIdentity(object $row): array
    {
        $name = $this->str($row->razon_social, 255) ?? $this->str($row->nombre_comercial, 255) ?? 'Company '.$row->id;
        $phone = $this->str($row->telefono, 50);

        return [
            'name' => $name,
            'tradename' => $this->str($row->nombre_comercial, 255),
            'country_id' => $this->catalog((int) ($row->pais_id ?? 0), 'countries'),
            'residence_country_id' => null,
            'person_type' => null,
            'email' => $this->str($row->email, 255),
            'phone' => $phone,
            'website' => null,
            'address_line_1' => $this->str($row->direccion_1, 255),
            'address_line_2' => $this->str($row->direccion_2, 255),
            'city' => $this->str($row->poblacion, 255),
            'postal_code' => $this->str($row->codigo_postal, 20),
            'employee_count' => null,
            'language_id' => $this->catalog((int) ($row->idioma_id ?? 0), 'languages'),
            'latitude' => null,
            'longitude' => null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tecnicoIdentity(object $row): array
    {
        $name = $this->str($row->nombre_fiscal, 255) ?? $this->str($row->nombre_comercial, 255) ?? 'Company '.$row->id;

        return [
            'name' => $name,
            'tradename' => $this->str($row->nombre_comercial, 255),
            'country_id' => $this->catalog((int) ($row->pais_id ?? 0), 'countries'),
            'residence_country_id' => $this->catalog((int) ($row->residence_country_id ?? 0), 'countries'),
            'person_type' => in_array($row->person_type, ['F', 'J'], true) ? $row->person_type : null,
            'email' => $this->str($row->correo, 255),
            'phone' => $this->str($row->telefono, 50),
            'website' => $this->str($row->pagina_web, 255),
            'address_line_1' => $this->str($row->direccion_1, 255),
            'address_line_2' => $this->str($row->direccion_2, 255),
            'city' => $this->str($row->poblacion, 255),
            'postal_code' => $this->str($row->codigo_postal, 20),
            'employee_count' => $this->unsignedInt($row->numero_empleados ?? null),
            'language_id' => $this->catalog((int) ($row->idioma_id ?? 0), 'languages'),
            'latitude' => $this->coord($row->latitud ?? null),
            'longitude' => $this->coord($row->longitud ?? null),
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    private function mergeTecnicoIdentity(array $identity, object $row): array
    {
        $tech = $this->tecnicoIdentity($row);
        foreach (['residence_country_id', 'person_type', 'website', 'employee_count', 'latitude', 'longitude'] as $field) {
            if (($identity[$field] ?? null) === null) {
                $identity[$field] = $tech[$field];
            }
        }

        return $identity;
    }

    private function usableTaxId(?string $nif, string $legacyId, string $entity): ?string
    {
        if ($nif === null) {
            return null;
        }
        if (strlen($nif) > 32) {
            $this->quarantine($entity, $legacyId, 'tax_id', 'null_overflow', 'NIF longer than 32', $nif);

            return null;
        }
        if (in_array($nif, self::OPERATING_TAX_IDS, true)) {
            $this->quarantine($entity, $legacyId, 'tax_id', 'null_operating', 'NIF belongs to an operating company', $nif);

            return null;
        }

        return $nif;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = substr($base, 0, 64);
        $i = 1;
        while (isset($this->usedSlugs[$slug])) {
            $suffix = '-'.$i;
            $slug = substr($base, 0, 64 - strlen($suffix)).$suffix;
            $i++;
        }
        $this->usedSlugs[$slug] = 1;

        return $slug;
    }

    private function catalog(int $id, string $table): ?int
    {
        if ($id <= 0) {
            return null;
        }
        if ($table === 'tenants') {
            if (! isset($this->catalogIds['tenants'])) {
                if (Schema::connection('migration_destination')->hasTable('tenants')) {
                    $this->catalogIds['tenants'] = array_fill_keys(
                        array_map('intval', $this->dest->table('tenants')->pluck('id')->all()),
                        true,
                    );
                } else {
                    $this->catalogIds['tenants'] = [];
                }
            }
        }

        return isset($this->catalogIds[$table][$id]) ? $id : null;
    }

    private function teamId(mixed $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }
        $key = (string) $code;

        return $this->teamsByCode[$key]
            ?? $this->teamsByCode['EQ'.str_pad($key, 2, '0', STR_PAD_LEFT)]
            ?? null;
    }

    private function backfillUserTeams(): void
    {
        if (! $this->execute) {
            return;
        }
        $needCount = (int) $this->dest->table('users')->whereNull('team_id')->count();
        if ($needCount === 0) {
            $this->skipPhase('Users team backfill', count($this->userMap), 'already set');
            $this->counts['users_team_backfill'] = 0;

            return;
        }

        $pending = [];
        $this->legacy->table('users')->whereNull('deleted_at')->whereNotNull('equipo_id')->orderBy('id')->chunkById($this->chunk, function ($rows) use (&$pending): void {
            foreach ($rows as $row) {
                $userId = $this->userMap[(int) $row->id] ?? null;
                $teamId = $this->teamId($row->equipo_id);
                if ($userId === null || $teamId === null || $userId < 1 || $teamId < 1) {
                    continue;
                }
                $pending[] = [$userId, $teamId];
            }
        });
        if ($pending === []) {
            $this->skipPhase('Users team backfill', 0, 'nothing to update');
            $this->counts['users_team_backfill'] = 0;

            return;
        }

        $bar = $this->startProgress('Users team backfill (missing)', count($pending));
        $updated = 0;
        foreach ($pending as [$userId, $teamId]) {
            $bar?->advance();
            $updated += $this->dest->table('users')->where('id', $userId)->whereNull('team_id')->update(['team_id' => $teamId]);
        }
        $this->finishProgress($bar);
        $this->counts['users_team_backfill'] = $updated;
    }

    private function mappedUser(mixed $legacyId): ?int
    {
        if (! $legacyId) {
            return null;
        }

        return $this->userMap[(int) $legacyId] ?? null;
    }

    private function memberUser(mixed $legacyId): ?int
    {
        if (! $legacyId) {
            return null;
        }

        if (! isset($this->sourceEmployeeIds[(int) $legacyId])) {
            return null;
        }

        return $this->userMap[(int) $legacyId] ?? null;
    }

    private function str(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }
        if (strlen($trimmed) > $max) {
            return null;
        }

        return $trimmed;
    }

    private function unsignedInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $int = (int) $value;

        return $int < 0 ? null : $int;
    }

    private function bool(mixed $value): int
    {
        return $value ? 1 : 0;
    }

    private function coord(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function writeMap(string $table, array $row): void
    {
        if (! $this->execute) {
            return;
        }

        $row += ['created_at' => now(), 'updated_at' => now()];

        try {
            $this->dest->table($table)->insertOrIgnore($row);
        } catch (Throwable) {
            // Idempotent reruns.
        }
    }

    private function quarantine(string $entity, string $legacyId, ?string $field, string $action, string $reason, ?string $original): void
    {
        $this->quarantine[] = [
            'entity' => $entity,
            'legacy_id' => $legacyId,
            'field' => $field,
            'action' => $action,
            'reason' => $reason,
            'original_value' => $original,
        ];
    }

    private function bump(string $key): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }

    private function flushQuarantine(): void
    {
        foreach (array_chunk($this->quarantine, 200) as $chunk) {
            $now = now();
            $rows = array_map(fn (array $q): array => $q + ['created_at' => $now, 'updated_at' => $now], $chunk);
            $this->dest->table('migration_quarantine')->insert($rows);
        }
    }

    private function startProgress(string $label, int $total): ?ProgressBar
    {
        $this->command->info($label);

        if ($total <= 0) {
            return null;
        }

        $bar = $this->command->getOutput()->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% elapsed %elapsed:6s% remaining %remaining:6s%');
        $bar->start();

        return $bar;
    }

    private function finishProgress(?ProgressBar $bar): void
    {
        if ($bar !== null) {
            $bar->finish();
        }
        $this->command->newLine(2);
    }

    private function printReport(): void
    {
        $this->command->newLine();
        $this->command->info('Report');
        $rows = [
            ['Users insert', (string) ($this->counts['users_would_insert'] ?? 0)],
            ['Users adopt (email)', (string) ($this->counts['users_would_adopt'] ?? 0)],
            ['Employees (company_user)', (string) ($this->counts['employees'] ?? 0)],
            ['SSO non-employees (no membership)', (string) ($this->counts['sso_non_employees'] ?? 0)],
            ['Clientes read', (string) ($this->counts['clientes_read'] ?? 0)],
            ['Tecnicos read', (string) ($this->counts['tecnicos_read'] ?? 0)],
            ['Companies planned', (string) ($this->counts['companies_planned'] ?? 0)],
            ['Companies create', (string) ($this->counts['companies_would_create'] ?? 0)],
            ['Companies NIF-merged', (string) ($this->counts['companies_merged_nif'] ?? 0)],
            ['Customer relationships', (string) ($this->counts['customer_relationships'] ?? 0)],
            ['Technician relationships', (string) ($this->counts['technician_relationships'] ?? 0)],
            ['Brands insert', (string) ($this->counts['brands_would_insert'] ?? 0)],
            ['Brands adopt', (string) ($this->counts['brands_would_adopt'] ?? 0)],
            ['Establishments', (string) ($this->counts['establishments'] ?? 0)],
            ['Delegations', (string) ($this->counts['delegations'] ?? 0)],
            ['Suppliers created', (string) ($this->counts['suppliers'] ?? 0)],
            ['Brand messages', (string) ($this->counts['brand_messages'] ?? 0)],
            ['Vehicles', (string) ($this->counts['vehicles'] ?? 0)],
            ['Articles', (string) ($this->counts['articles'] ?? 0)],
            ['Contracts', (string) ($this->counts['contracts'] ?? 0)],
            ['Evaluations', (string) ($this->counts['evaluations'] ?? 0)],
            ['Incidents', (string) ($this->counts['incidents'] ?? 0)],
            ['Technician incidents', (string) ($this->counts['technician_incidents'] ?? 0)],
            ['Technician incident messages', (string) ($this->counts['technician_incident_messages'] ?? 0)],
            ['Article languages', (string) ($this->counts['article_languages'] ?? 0)],
            ['Article client prices', (string) ($this->counts['article_clients'] ?? 0)],
            ['Work order lines estimates', (string) ($this->counts['presupuestos_lineas_facturacion'] ?? 0)],
            ['Work order lines OTs', (string) ($this->counts['ots_lineas_facturacion'] ?? 0)],
            ['Work order technicians estimates', (string) ($this->counts['presupuestos_solicitados'] ?? 0)],
            ['Work order technicians OTs', (string) ($this->counts['ot_tecnico'] ?? 0)],
            ['Work orders estimates', (string) ($this->counts['presupuestos'] ?? 0)],
            ['Work orders OTs', (string) ($this->counts['ots'] ?? 0)],
            ['Technician requests', (string) ($this->counts['technician_requests'] ?? 0)],
            ['Compliments', (string) ($this->counts['compliments'] ?? 0)],
            ['Ambiguous NIF groups', (string) ($this->counts['ambiguous_nif'] ?? 0)],
            ['Quarantine rows', (string) count($this->quarantine)],
        ];
        $this->command->table(['Metric', 'Count'], $rows);

        $ambiguous = array_filter($this->quarantine, fn (array $q): bool => $q['action'] === 'ambiguous_duplicate');
        if ($ambiguous !== []) {
            $this->command->warn('Ambiguous NIF sample (first 15):');
            foreach (array_slice($ambiguous, 0, 15) as $row) {
                $this->command->line("  {$row['entity']} {$row['legacy_id']}: {$row['reason']} ({$row['original_value']})");
            }
        }

        $this->command->newLine();
        if ($this->execute) {
            $this->command->info('Execute finished. Mapping tables skip already-imported rows on the next run.');
        } else {
            $this->command->info('Dry-run finished. Start the import with:');
            $this->command->line('  php artisan app:migrate-legacy --execute');
        }
    }
}
