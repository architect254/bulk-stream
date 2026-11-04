<?php

namespace App\Providers;

use App\Application\Dashboard\TerminalDashboardReadModel;
use App\Support\OperationalTime;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TerminalDashboardReadModel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentTimezone::set(fn (): string => OperationalTime::timezone());
    }
}
