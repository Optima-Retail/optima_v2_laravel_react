<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Auth;

use App\Domain\Auth\Permissions\PolicyPermissionDiscoverer;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Tests\TestCase;

final class PolicyPermissionDiscovererTest extends TestCase
{
    public function test_it_discovers_permissions_from_policy_methods(): void
    {
        $discoverer = new PolicyPermissionDiscoverer;
        $permissions = $discoverer->discover();

        $this->assertContains(UserPolicy::class, $discoverer->policyClasses());
        $this->assertContains(RolePolicy::class, $discoverer->policyClasses());

        $this->assertSame([
            'banks.create',
            'banks.delete',
            'banks.update',
            'banks.view',
            'brands.create',
            'brands.delete',
            'brands.download-message-files',
            'brands.send-message-files',
            'brands.send-messages',
            'brands.update',
            'brands.view',
            'brands.view-message-files',
            'brands.view-messages',
            'companies.create',
            'companies.delete',
            'companies.update',
            'companies.view',
            'company_relationships.create',
            'company_relationships.delete',
            'company_relationships.update',
            'company_relationships.view',
            'countries.create',
            'countries.delete',
            'countries.update',
            'countries.view',
            'currencies.create',
            'currencies.delete',
            'currencies.update',
            'currencies.view',
            'delegations.create',
            'delegations.delete',
            'delegations.update',
            'delegations.view',
            'establishments.create',
            'establishments.delete',
            'establishments.update',
            'establishments.view',
            'integrations.create',
            'integrations.delete',
            'integrations.update',
            'integrations.view',
            'languages.create',
            'languages.delete',
            'languages.update',
            'languages.view',
            'rating_types.create',
            'rating_types.delete',
            'rating_types.update',
            'rating_types.view',
            'roles.create',
            'roles.delete',
            'roles.update',
            'roles.view',
            'series.create',
            'series.delete',
            'series.update',
            'series.view',
            'teams.create',
            'teams.delete',
            'teams.update',
            'teams.view',
            'timezones.create',
            'timezones.delete',
            'timezones.update',
            'timezones.view',
            'users.create',
            'users.delete',
            'users.update',
            'users.view',
            'work_order_types.create',
            'work_order_types.delete',
            'work_order_types.update',
            'work_order_types.view',
        ], $permissions);
    }
}
