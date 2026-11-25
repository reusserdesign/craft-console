<?php

namespace App\Repositories;

use Illuminate\Support\Arr;

class ConfigRepository
{
    public function __construct(protected string $path) {}

    /**
     * Get the path to the configuration file.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get all of the configuration items.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (! file_exists($this->path)) {
            return [];
        }

        return json_decode((string) file_get_contents($this->path), true) ?: [];
    }

    /**
     * Get the given configuration value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * Set the given configuration value.
     */
    public function set(string $key, mixed $value): static
    {
        $config = $this->all();

        Arr::set($config, $key, $value);

        return $this->write($config);
    }

    /**
     * Remove the given configuration value.
     */
    public function forget(string $key): static
    {
        $config = $this->all();

        Arr::forget($config, $key);

        return $this->write($config);
    }

    /**
     * Delete the configuration file.
     */
    public function flush(): static
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }

        return $this;
    }

    /**
     * Write the configuration to disk, readable only by the current user
     * since it holds the Craft Console API key.
     *
     * @param  array<string, mixed>  $config
     */
    protected function write(array $config): static
    {
        if (! is_dir(dirname($this->path))) {
            mkdir(dirname($this->path), 0700, true);
        }

        file_put_contents($this->path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        chmod($this->path, 0600);

        return $this;
    }
}
