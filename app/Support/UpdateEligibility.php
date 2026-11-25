<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Works out which Craft releases a license can install. A license that
 * expires still runs, but only on releases published before it expired.
 */
class UpdateEligibility
{
    /**
     * Stable releases, newest first.
     *
     * @var Collection<int, array{version: string, time: CarbonImmutable}>
     */
    protected Collection $releases;

    /**
     * @param  array<string, mixed>  $license
     * @param  array<int, array<string, mixed>>  $releases
     */
    public function __construct(protected array $license, array $releases, protected ?CarbonImmutable $now = null)
    {
        $this->now ??= CarbonImmutable::now();

        $this->releases = collect($releases)
            ->filter(fn ($release) => isset($release['version'], $release['time'])
                && preg_match('/^\d+(\.\d+)*$/', (string) $release['version']))
            ->map(fn ($release) => [
                'version' => (string) $release['version'],
                'time' => CarbonImmutable::parse($release['time'], 'UTC'),
            ])
            ->sort(fn ($a, $b) => version_compare($b['version'], $a['version']))
            ->values();
    }

    /**
     * Determine if the license gets every update, with no expiry.
     */
    public function isPerpetual(): bool
    {
        return ! ($this->license['expirable'] ?? false) || blank($this->license['expiresOn'] ?? null);
    }

    /**
     * Get the date updates stop, or null for a perpetual license.
     */
    public function expiresOn(): ?CarbonImmutable
    {
        return $this->isPerpetual() ? null : CarbonImmutable::parse($this->license['expiresOn']);
    }

    /**
     * Determine if the update period has already ended.
     */
    public function hasExpired(): bool
    {
        $expiresOn = $this->expiresOn();

        return $expiresOn !== null && $expiresOn->lte($this->now);
    }

    /**
     * Get the newest stable release.
     */
    public function latestVersion(): ?string
    {
        return $this->releases->first()['version'] ?? null;
    }

    /**
     * Get the newest release the license can install: the newest release
     * published before it expired, or the newest overall when it is still
     * active or never expires.
     */
    public function latestEligibleVersion(): ?string
    {
        $expiresOn = $this->expiresOn();

        if ($expiresOn === null) {
            return $this->latestVersion();
        }

        return $this->releases->first(fn ($release) => $release['time']->lte($expiresOn))['version'] ?? null;
    }

    /**
     * Get the releases published after the license expired, which need a
     * renewal to install.
     *
     * @return Collection<int, string>
     */
    public function lockedVersions(): Collection
    {
        $expiresOn = $this->expiresOn();

        if ($expiresOn === null) {
            return collect();
        }

        return $this->releases
            ->filter(fn ($release) => $release['time']->gt($expiresOn))
            ->pluck('version')
            ->values();
    }

    /**
     * Break eligibility down by major version, from the installed major
     * (or the oldest known one) up to the newest.
     *
     * @return Collection<int, array{major: int, latestEligibleVersion: ?string, latestVersion: string, versionsNeedingRenewal: int}>
     */
    public function majors(?string $installedVersion = null): Collection
    {
        $expiresOn = $this->expiresOn();
        $from = static::majorOf($installedVersion);

        return $this->releases
            ->groupBy(fn ($release) => static::majorOf($release['version']))
            ->filter(fn ($releases, $major) => $from === null || $major >= $from)
            ->map(function (Collection $releases, int $major) use ($expiresOn) {
                $eligible = $expiresOn === null
                    ? $releases
                    : $releases->filter(fn ($release) => $release['time']->lte($expiresOn));

                return [
                    'major' => $major,
                    'latestEligibleVersion' => $eligible->first()['version'] ?? null,
                    'latestVersion' => $releases->first()['version'],
                    'versionsNeedingRenewal' => $releases->count() - $eligible->count(),
                ];
            })
            ->sortKeys()
            ->values();
    }

    /**
     * Get the major version from a version string or constraint, such as
     * "3.9.15" or "^3.6.0".
     */
    public static function majorOf(?string $version): ?int
    {
        return $version !== null && preg_match('/(\d+)/', $version, $matches) ? (int) $matches[1] : null;
    }

    /**
     * Determine if the given version is newer than the license allows.
     */
    public function exceeds(?string $version): bool
    {
        if (blank($version) || ! preg_match('/^\d+(\.\d+)*$/', $version)) {
            return false;
        }

        // Compare within the same major: a 3.x patch released after expiry is
        // still off-limits even if an older-dated 4.x release is covered.
        $row = $this->majors($version)->firstWhere('major', static::majorOf($version));

        if ($row === null) {
            return false;
        }

        return $row['latestEligibleVersion'] === null
            || version_compare($version, $row['latestEligibleVersion'], '>');
    }
}
