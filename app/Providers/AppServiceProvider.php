<?php

namespace App\Providers;

use App\Services\Accurate\AccurateClient;
use App\Services\Ai\OpenAiClient;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
