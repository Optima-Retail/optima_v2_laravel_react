<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Role;
use App\Models\WorkOrder;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        Route::bind('estimate', function (string $value): WorkOrder {
            return WorkOrder::query()
                ->whereKey($value)
                ->where('stage', WorkOrderStage::Estimate->value)
                ->firstOrFail();
        });

        Route::bind('work_order', function (string $value): WorkOrder {
            return WorkOrder::query()
                ->whereKey($value)
                ->where('stage', WorkOrderStage::WorkOrder->value)
                ->firstOrFail();
        });
    }
}
