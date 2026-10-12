<?php

namespace App\Providers;

use App\Http\Middleware\EnsureNotImpersonating;
use App\Models\User;
use App\Services\Mollie\HttpMollieGateway;
use App\Services\Mollie\MollieGateway;
use App\Services\Moneybird\HttpMoneybirdGateway;
use App\Services\Moneybird\MoneybirdGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MollieGateway::class, HttpMollieGateway::class);
        $this->app->bind(MoneybirdGateway::class, HttpMoneybirdGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Superadmins manage every club and all underlying data.
        Gate::before(fn (User $user) => $user->is_superadmin ? true : null);

        // Platform management (add-ons, pricing) is superadmin-only; the hook above grants it.
        Gate::define('manage-platform', fn (User $user): bool => false);

        // Re-apply the impersonation guard to Livewire component updates.
        Livewire::addPersistentMiddleware([EnsureNotImpersonating::class]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
