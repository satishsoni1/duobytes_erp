<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Relation::morphMap([
            'user' => \App\Models\User::class,
            'lead' => \App\Models\Lead::class,
            'customer' => \App\Models\Customer::class,
            'quotation' => \App\Models\Quotation::class,
            'sales_order' => \App\Models\SalesOrder::class,
            'purchase_order' => \App\Models\PurchaseOrder::class,
            'installation' => \App\Models\Installation::class,
            'service_ticket' => \App\Models\ServiceTicket::class,
        ]);
        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);
        foreach (array_keys(config('crm.permissions')) as $ability) {
            Gate::define($ability, fn (User $user) => $user->canAccess($ability));
        }

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
