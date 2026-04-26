<?php

declare(strict_types=1);

namespace Nexus\Laravel;

use Illuminate\Support\ServiceProvider;
use Nexus\Search\Infrastructure\Provider\ProviderConfigRegistry;

final class NexusServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/nexus.php', 'nexus');

        $this->app->singleton('nexus.provider_configs', function ($app) {
            $config = $app['config']->get('nexus');

            return ProviderConfigRegistry::defaults(
                ieeeApiKey: $config['providers']['ieee']['api_key'] ?? null,
                s2ApiKey: $config['providers']['semantic_scholar']['api_key'] ?? null,
                pubmedApiKey: $config['providers']['pubmed']['api_key'] ?? null,
                mailTo: $config['mail_to'] ?? null,
            );
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/nexus.php' => $this->app->configPath('nexus.php'),
            ], 'nexus-config');
        }
    }
}
