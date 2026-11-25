<?php

namespace App\Support;

/**
 * Reads CLI settings from environment variables: ones exported in your
 * shell first, then the .env file of the Craft project you're working in.
 */
class Environment
{
    protected ?CraftProject $project = null;

    protected bool $projectResolved = false;

    public function __construct(protected ?string $workingDirectory = null) {}

    /**
     * Use the given project's .env file instead of detecting one from the
     * working directory, such as when a command is given a project path.
     */
    public function useProject(?CraftProject $project): static
    {
        $this->project = $project;
        $this->projectResolved = true;

        return $this;
    }

    /**
     * Get the Craft project whose .env file is read, if any.
     */
    public function project(): ?CraftProject
    {
        if (! $this->projectResolved) {
            $this->project = $this->workingDirectory ? CraftProject::detect($this->workingDirectory) : null;
            $this->projectResolved = true;
        }

        return $this->project;
    }

    /**
     * Get a variable, treating empty values as missing.
     */
    public function get(string $key): ?string
    {
        return $this->exported($key) ?? $this->project()?->dotenv($key);
    }

    /**
     * Describe where a variable's value comes from, or null if it isn't set.
     */
    public function source(string $key): ?string
    {
        if ($this->exported($key) !== null) {
            return 'your shell environment';
        }

        if ($this->project()?->dotenv($key) !== null) {
            return $this->project()->path().'/.env';
        }

        return null;
    }

    /**
     * Read a variable exported in the process environment.
     */
    protected function exported(string $key): ?string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
