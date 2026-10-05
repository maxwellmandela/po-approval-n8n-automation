<?php

namespace App\Providers;

use App\Models\ProcurementRequest;
use App\Policies\ProcurementRequestPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        ProcurementRequest::class => ProcurementRequestPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('manage-users', fn ($user) => $user->role === 'admin');
        Gate::define('view-review-queue', fn ($user) => in_array($user->role, ['procurement', 'approver', 'admin'], true));
        Gate::define('access-budgets', fn ($user) => in_array($user->role, ['procurement', 'approver', 'admin'], true));
    }
}
