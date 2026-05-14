<?php

namespace App\Providers;

use App\Support\Repositories\EloquentSupportTicketRepository;
use App\Support\Repositories\SupportTicketRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            SupportTicketRepositoryInterface::class,
            EloquentSupportTicketRepository::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
