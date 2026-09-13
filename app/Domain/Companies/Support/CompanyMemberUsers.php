<?php

declare(strict_types=1);

namespace App\Domain\Companies\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Users linked to a company via company_user (active membership).
 */
final class CompanyMemberUsers
{
    /**
     * @return Builder<User>
     */
    public static function query(?int $companyId, bool $activeMembershipOnly = true): Builder
    {
        $query = User::query()->whereNull('deleted_at');

        if ($companyId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas('companies', function ($companies) use ($companyId, $activeMembershipOnly): void {
            $companies->where('companies.id', $companyId);

            if ($activeMembershipOnly) {
                $companies->where('company_user.is_active', true);
            }
        });
    }

    /**
     * @param  list<int>  $includeUserIds  Keep currently selected users visible even if membership changed.
     * @return list<array{id: int, label: string}>
     */
    public static function options(
        ?Company $company,
        array $includeUserIds = [],
        bool $activeMembershipOnly = true,
        ?int $excludeUserId = null,
    ): array {
        return self::optionsForCompanyId(
            $company?->id,
            $includeUserIds,
            $activeMembershipOnly,
            $excludeUserId,
        );
    }

    /**
     * @param  list<int>  $includeUserIds
     * @return list<array{id: int, label: string}>
     */
    public static function optionsForCompanyId(
        ?int $companyId,
        array $includeUserIds = [],
        bool $activeMembershipOnly = true,
        ?int $excludeUserId = null,
    ): array {
        $includeUserIds = array_values(array_unique(array_filter(
            array_map('intval', $includeUserIds),
            fn (int $id): bool => $id > 0,
        )));

        $users = self::query($companyId, $activeMembershipOnly)
            ->when($excludeUserId !== null, fn ($query) => $query->whereKeyNot($excludeUserId))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        if ($includeUserIds !== []) {
            $missingIds = array_values(array_diff(
                $includeUserIds,
                $users->pluck('id')->all(),
            ));

            if ($missingIds !== []) {
                $extra = User::query()
                    ->whereNull('deleted_at')
                    ->whereIn('id', $missingIds)
                    ->when($excludeUserId !== null, fn ($query) => $query->whereKeyNot($excludeUserId))
                    ->get(['id', 'name', 'email']);

                $users = $users->concat($extra)->unique('id')->sortBy('name')->values();
            }
        }

        return $users
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'label' => $user->email !== null && $user->email !== ''
                    ? "{$user->name} ({$user->email})"
                    : $user->name,
            ])
            ->values()
            ->all();
    }

    public static function existsRule(?int $companyId): Exists
    {
        return Rule::exists('users', 'id')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($companyId): void {
                if ($companyId === null) {
                    $query->whereRaw('0 = 1');

                    return;
                }

                $query->whereExists(function ($sub) use ($companyId): void {
                    $sub->selectRaw('1')
                        ->from('company_user')
                        ->whereColumn('company_user.user_id', 'users.id')
                        ->where('company_user.company_id', $companyId)
                        ->where('company_user.is_active', true)
                        ->whereNull('company_user.deleted_at');
                });
            });
    }
}
