<?php

namespace App\Support;

use Dotenv\Dotenv;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Reads what the CLI needs from a Craft project on disk: the installed
 * Craft version, the edition it runs, and its license key.
 */
class CraftProject
{
    /**
     * The parsed .env file, if any.
     *
     * @var array<string, string|null>|null
     */
    protected ?array $dotenv = null;

    /**
     * The parsed project config, if any.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $projectConfig = null;

    public function __construct(protected string $path) {}

    /**
     * Find the Craft project at, or above, the given directory.
     */
    public static function detect(string $path): ?self
    {
        $path = realpath($path) ?: $path;

        while (true) {
            if (static::isCraftProject($path)) {
                return new static($path);
            }

            $parent = dirname($path);

            if ($parent === $path) {
                return null;
            }

            $path = $parent;
        }
    }

    /**
     * Determine if the directory holds a Craft project, by looking for a
     * craftcms/cms requirement in its composer.json.
     */
    protected static function isCraftProject(string $path): bool
    {
        $composer = static::readJson($path.'/composer.json');

        return isset($composer['require']['craftcms/cms']);
    }

    /**
     * Get the project's root directory.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get the installed Craft version from composer.lock, falling back to
     * the version constraint in composer.json.
     */
    public function craftVersion(): ?string
    {
        $lock = static::readJson($this->path.'/composer.lock');

        foreach ($lock['packages'] ?? [] as $package) {
            if (($package['name'] ?? null) === 'craftcms/cms') {
                return ltrim((string) $package['version'], 'v');
            }
        }

        return static::readJson($this->path.'/composer.json')['require']['craftcms/cms'] ?? null;
    }

    /**
     * Determine if the version came from composer.lock rather than a
     * composer.json constraint.
     */
    public function hasLockedVersion(): bool
    {
        $version = $this->craftVersion();

        return $version !== null && preg_match('/^\d+(\.\d+)*$/', $version) === 1;
    }

    /**
     * Get the Craft edition from the project config, if it is set.
     */
    public function edition(): ?string
    {
        return $this->projectConfig()['system']['edition'] ?? null;
    }

    /**
     * Get the plugins in the project config, with the package name and
     * installed version from composer.lock and any license key resolved
     * from its environment variable.
     *
     * @return array<int, array{handle: string, package: ?string, version: ?string, edition: ?string, enabled: bool, licenseKey: ?string}>
     */
    public function plugins(): array
    {
        $packages = collect(static::readJson($this->path.'/composer.lock')['packages'] ?? [])
            ->filter(fn ($package) => ($package['type'] ?? null) === 'craft-plugin' && isset($package['extra']['handle']))
            ->keyBy(fn ($package) => $package['extra']['handle']);

        return collect($this->projectConfig()['plugins'] ?? [])
            ->filter(fn ($config) => is_array($config))
            ->map(fn (array $config, string $handle) => [
                'handle' => $handle,
                'package' => $packages[$handle]['name'] ?? null,
                'version' => isset($packages[$handle]) ? ltrim((string) $packages[$handle]['version'], 'v') : null,
                'edition' => $config['edition'] ?? null,
                'enabled' => (bool) ($config['enabled'] ?? true),
                'licenseKey' => $this->parseEnv($config['licenseKey'] ?? null),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * Read the project config from config/project/project.yaml (Craft
     * 3.5+), or config/project.yaml for older sites.
     *
     * @return array<string, mixed>
     */
    protected function projectConfig(): array
    {
        if ($this->projectConfig !== null) {
            return $this->projectConfig;
        }

        foreach (['config/project/project.yaml', 'config/project.yaml'] as $file) {
            if (is_file($this->path.'/'.$file)) {
                try {
                    return $this->projectConfig = (array) Yaml::parseFile($this->path.'/'.$file);
                } catch (Throwable) {
                    break;
                }
            }
        }

        return $this->projectConfig = [];
    }

    /**
     * Resolve a "$ENV_VAR" reference the way Craft's App::parseEnv() does.
     */
    protected function parseEnv(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^\$(\w+)$/', $value, $matches)) {
            return $this->env($matches[1]);
        }

        return $value;
    }

    /**
     * Get the license key and where it was found, following the same
     * order Craft does: the CRAFT_LICENSE_KEY environment variable, then
     * the file at CRAFT_LICENSE_KEY_PATH, then config/license.key.
     *
     * @return array{key: string, source: string}|null
     */
    public function licenseKey(): ?array
    {
        if (filled($key = $this->env('CRAFT_LICENSE_KEY'))) {
            return ['key' => static::normalizeKey($key), 'source' => 'CRAFT_LICENSE_KEY'];
        }

        $path = $this->env('CRAFT_LICENSE_KEY_PATH');

        if (filled($path)) {
            $path = $this->absolutePath($path);
        } else {
            $configPath = $this->env('CRAFT_CONFIG_PATH');
            $path = ($configPath ? $this->absolutePath($configPath) : $this->path.'/config').'/license.key';
        }

        $contents = @file_get_contents($path);

        if ($contents === false || blank(trim($contents))) {
            return null;
        }

        return ['key' => static::normalizeKey($contents), 'source' => $this->relativePath($path)];
    }

    /**
     * Remove the line breaks Craft adds when it writes license.key.
     */
    protected static function normalizeKey(string $key): string
    {
        return (string) preg_replace('/\s+/', '', $key);
    }

    /**
     * Read an environment variable the way the site would see it: from
     * the process environment first, then the project's .env file.
     */
    protected function env(string $key): ?string
    {
        $value = getenv($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $this->dotenv($key);
    }

    /**
     * Read a variable from the project's .env file only.
     */
    public function dotenv(string $key): ?string
    {
        if ($this->dotenv === null) {
            try {
                $this->dotenv = Dotenv::parse((string) @file_get_contents($this->path.'/.env'));
            } catch (Throwable) {
                $this->dotenv = [];
            }
        }

        $value = $this->dotenv[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Resolve a path relative to the project root.
     */
    protected function absolutePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : $this->path.'/'.ltrim($path, './');
    }

    /**
     * Make a path relative to the project root for display.
     */
    protected function relativePath(string $path): string
    {
        return str_starts_with($path, $this->path.'/') ? substr($path, strlen($this->path) + 1) : $path;
    }

    /**
     * Decode a JSON file, returning an empty array when it is missing.
     *
     * @return array<string, mixed>
     */
    protected static function readJson(string $path): array
    {
        $contents = @file_get_contents($path);

        return $contents === false ? [] : (json_decode($contents, true) ?: []);
    }
}
