<?php

namespace App\Support;

use Dotenv\Dotenv;
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
        foreach (['config/project/project.yaml', 'config/project.yaml'] as $file) {
            $contents = @file_get_contents($this->path.'/'.$file);

            if ($contents === false) {
                continue;
            }

            // Only system.edition is needed, so match it without a YAML parser.
            if (preg_match('/^system:\s*\n((?:[ \t]+.*\n?)*)/m', $contents, $system)
                && preg_match('/^[ \t]+edition:\s*[\'"]?(\w+)/m', $system[1], $edition)) {
                return $edition[1];
            }
        }

        return null;
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

        if ($this->dotenv === null) {
            try {
                $this->dotenv = Dotenv::parse((string) @file_get_contents($this->path.'/.env'));
            } catch (Throwable) {
                $this->dotenv = [];
            }
        }

        return $this->dotenv[$key] ?? null;
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
