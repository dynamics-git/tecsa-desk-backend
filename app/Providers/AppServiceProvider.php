<?php

namespace App\Providers;

use App\Models\SupportTicket;
use App\Policies\SupportTicketPolicy;
use App\Support\Repositories\EloquentSupportTicketRepository;
use App\Support\Repositories\SupportTicketRepositoryInterface;
use App\Support\Services\Conversation\SmtpSupportConversationEmailProvider;
use App\Support\Services\Conversation\SupportConversationEmailProviderInterface;
use Illuminate\Support\Facades\Gate;
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

        $this->app->singleton(
            SupportConversationEmailProviderInterface::class,
            SmtpSupportConversationEmailProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
    }
}
