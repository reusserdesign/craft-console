<?php

namespace App\Providers;

use App\Clients\Craftnet;
use App\Repositories\ConfigRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ConfigRepository::class, function () {
            return new ConfigRepository($this->configPath());
        });

        $this->app->singleton(Craftnet::class, function () {
            return (new Craftnet)->resolveCredentialsUsing(function () {
                $config = $this->app->make(ConfigRepository::class);

                return [
                    $this->env('CRAFT_CONSOLE_USERNAME') ?? $config->get('username'),
                    $this->env('CRAFT_CONSOLE_API_KEY') ?? $config->get('api_key'),
                ];
            });
        });
    }

    /**
     * Resolve where the CLI keeps its configuration file.
     */
    protected function configPath(): string
    {
        if ($path = $this->env('CRAFT_CONSOLE_CONFIG')) {
            return $path;
        }

        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? sys_get_temp_dir();

        return $home.'/.craft-console/config.json';
    }

    /**
     * Read an environment variable, treating empty values as missing.
     */
    protected function env(string $key): ?string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
