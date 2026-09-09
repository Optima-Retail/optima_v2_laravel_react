<?php

declare(strict_types=1);

namespace App\Domain\Config\Users\Services;

use App\Domain\Companies\Services\CompanyService;
use App\Models\Brand;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\Team;
use App\Models\Timezone;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UserService
{
    /**
     * @param  array{search?: string|null, role?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = trim((string) ($filters['role'] ?? ''));
        $perPage ??= ListQuery::perPage($filters);
        [$sort, $direction] = ListQuery::sort($filters, ['id', 'name', 'email', 'is_active'], 'name');

        return User::query()
            ->with('roles')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', function ($query) use ($role): void {
                $query->role($role);
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, role?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForWeb(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        return $this->paginate($filters, $perPage)
            ->through(fn (User $user): array => $this->toListItem($user));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $companyIds = array_values(array_unique(array_map('intval', $data['company_ids'] ?? [])));
            $user = User::query()->create(Arr::except($data, ['roles', 'company_ids']));

            $user->syncRoles($data['roles']);

            if ($companyIds !== []) {
                $sync = [];
                foreach ($companyIds as $companyId) {
                    $membership = CompanyUser::withTrashed()
                        ->where('company_id', $companyId)
                        ->where('user_id', $user->id)
                        ->first();

                    if ($membership !== null) {
                        if ($membership->trashed()) {
                            $membership->restore();
                        }
                        $membership->forceFill(['is_active' => true])->save();
                    } else {
                        $sync[$companyId] = ['is_active' => true];
                    }
                }

                if ($sync !== []) {
                    $user->companies()->attach($sync);
                }

                if ($user->active_company_id === null) {
                    $user->forceFill(['active_company_id' => $companyIds[0]])->save();
                }
            }

            return $user->load(['roles', 'companies']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $payload = Arr::except($data, ['roles', 'company_ids', '_actor']);

            if (empty($payload['password'])) {
                unset($payload['password']);
            }

            $user->update($payload);
            $user->syncRoles($data['roles']);

            if (array_key_exists('company_ids', $data)) {
                $this->syncCompanyMemberships(
                    $user,
                    array_map('intval', $data['company_ids'] ?? []),
                    $data['_actor'] instanceof User ? $data['_actor'] : null,
                );
            }

            return $user->fresh(['roles', 'companies']) ?? $user;
        });
    }

    /**
     * @param  list<int>  $companyIds
     */
    private function syncCompanyMemberships(User $user, array $companyIds, ?User $actor = null): void
    {
        $companyIds = array_values(array_unique(array_filter($companyIds)));

        if ($actor !== null) {
            $actorIds = $actor->companies()
                ->wherePivot('is_active', true)
                ->pluck('companies.id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $outside = $user->companies()
                ->whereNotIn('companies.id', $actorIds === [] ? [0] : $actorIds)
                ->pluck('companies.id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $companyIds = array_values(array_unique([
                ...$outside,
                ...array_values(array_intersect($companyIds, $actorIds)),
            ]));
        }

        $currentIds = $user->companies()->pluck('companies.id')->map(fn ($id) => (int) $id)->all();

        $toDetach = array_diff($currentIds, $companyIds);
        foreach ($toDetach as $companyId) {
            CompanyUser::query()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->get()
                ->each(fn (CompanyUser $membership) => $membership->delete());
        }

        if ($companyIds !== []) {
            foreach ($companyIds as $companyId) {
                $membership = CompanyUser::withTrashed()
                    ->where('company_id', $companyId)
                    ->where('user_id', $user->id)
                    ->first();

                if ($membership !== null) {
                    if ($membership->trashed()) {
                        $membership->restore();
                    }
                    $membership->forceFill(['is_active' => true])->save();

                    continue;
                }

                $user->companies()->attach($companyId, ['is_active' => true]);
            }
        }

        if ($user->active_company_id !== null && ! in_array((int) $user->active_company_id, $companyIds, true)) {
            $user->forceFill([
                'active_company_id' => $companyIds[0] ?? null,
            ])->save();
        } elseif ($user->active_company_id === null && $companyIds !== []) {
            $user->forceFill(['active_company_id' => $companyIds[0]])->save();
        }
    }

    /**
     * Soft-delete a user. Records are never hard-deleted.
     */
    public function delete(User $user): void
    {
        if ($user->trashed()) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $user->softDeleteSafely();
        });
    }

    /**
     * @return list<string>
     */
    public function roleOptions(): array
    {
        return Role::query()
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     users: list<array{id: int, label: string}>,
     *     teams: list<array{id: int, label: string}>,
     *     timezones: list<array{id: int, label: string}>,
     *     brands: list<array{id: int, label: string}>,
     *     companies: list<array{id: int, label: string}>
     * }
     */
    public function formOptions(?User $editing = null, ?User $actor = null): array
    {
        $companyOptions = [];

        if ($actor !== null) {
            $companyOptions = app(CompanyService::class)->membershipOptionsForUser($actor);
        }

        return [
            'users' => User::query()
                ->when($editing, fn ($query) => $query->whereKeyNot($editing->id))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => ['id' => $user->id, 'label' => "{$user->name} ({$user->email})"])
                ->values()
                ->all(),
            'teams' => Team::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Team $team): array => ['id' => $team->id, 'label' => $team->name])
                ->values()
                ->all(),
            'timezones' => Timezone::query()
                ->orderBy('name')
                ->get(['id', 'name', 'timezone'])
                ->map(fn (Timezone $timezone): array => [
                    'id' => $timezone->id,
                    'label' => "{$timezone->name} ({$timezone->timezone})",
                ])
                ->values()
                ->all(),
            'brands' => Brand::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Brand $brand): array => ['id' => $brand->id, 'label' => $brand->name])
                ->values()
                ->all(),
            'companies' => $companyOptions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(User $user): array
    {
        $user->loadMissing(['roles', 'companies']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'locale' => $user->locale,
            'manager_id' => $user->manager_id,
            'team_leader_id' => $user->team_leader_id,
            'team_id' => $user->team_id,
            'timezone_id' => $user->timezone_id,
            'brand_id' => $user->brand_id,
            'phone' => $user->phone,
            'telephony_phone_number' => $user->telephony_phone_number,
            'pbx_extension' => $user->pbx_extension,
            'telegram_user_id' => $user->telegram_user_id,
            'external_hr_id' => $user->external_hr_id,
            'is_active' => $user->is_active,
            'is_internal_employee' => $user->is_internal_employee,
            'is_team_account' => $user->is_team_account,
            'is_preventive_specialist' => $user->is_preventive_specialist,
            'performance_factor' => $user->performance_factor,
            'invoiced_revenue_target' => $user->invoiced_revenue_target,
            'quality_score' => $user->quality_score,
            'balance' => $user->balance,
            'budget_approval_limit' => $user->budget_approval_limit,
            'sso_only' => $user->sso_only,
            'must_change_password' => $user->must_change_password,
            'roles' => $user->getRoleNames()->values()->all(),
            'company_ids' => $user->companies->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, roles: list<string>, is_active: bool, created_at: string|null}
     */
    public function toListItem(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
            'is_active' => (bool) $user->is_active,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
