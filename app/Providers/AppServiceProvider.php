<?php

namespace App\Providers;

use App\Models\LocalOrder;
use App\Observers\LocalOrderObserver;
use App\Services\Accurate\AccurateClient;
use App\Services\Ai\OpenAiClient;
use App\Services\Payment\MidtransClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AccurateClient::class, function () {
            $config = config('services.accurate');

            return new AccurateClient(
                clientId: $config['client_id'] ?? '',
                clientSecret: $config['client_secret'] ?? '',
                redirectUri: $config['redirect_uri'] ?? '',
                accountBaseUrl: $config['account_base_url'],
                scopes: $config['scopes'],
                tokenExpiryBuffer: $config['token_expiry_buffer_seconds'],
                sessionTtlMinutes: $config['session_ttl_minutes'],
            );
        });

        $this->app->singleton(OpenAiClient::class, function () {
            $config = config('services.openai');

            return new OpenAiClient(
                apiKey: $config['api_key'] ?? '',
                model: $config['model'],
            );
        });

        $this->app->singleton(MidtransClient::class, function () {
            $config = config('services.midtrans');

            return new MidtransClient(
                serverKey: $config['server_key'] ?? '',
                clientKey: $config['client_key'] ?? '',
                isProduction: $config['is_production'],
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LocalOrder::observe(LocalOrderObserver::class);
    }
}
