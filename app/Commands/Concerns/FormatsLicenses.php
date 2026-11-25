<?php

namespace App\Commands\Concerns;

use Carbon\CarbonImmutable;
use Throwable;

trait FormatsLicenses
{
    /**
     * Format an API datetime as a date.
     */
    protected function date(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return $value;
        }
    }

    /**
     * Describe when a license expires.
     *
     * @param  array<string, mixed>  $license
     */
    protected function expiry(array $license): string
    {
        if (! ($license['expirable'] ?? false)) {
            return 'Never';
        }

        $date = $this->date($license['expiresOn'] ?? null);

        return ($license['expired'] ?? false) ? "{$date} (expired)" : $date;
    }

    /**
     * Describe who owns a license.
     *
     * @param  array<string, mixed>  $license
     */
    protected function owner(array $license): string
    {
        if (filled($license['org'] ?? null)) {
            return 'org:'.$license['org'];
        }

        return $license['email'] ?? '—';
    }

    /**
     * Shorten a long license key for display in a table.
     */
    protected function shortKey(?string $key): string
    {
        if (blank($key)) {
            return '—';
        }

        return strlen($key) > 16 ? substr($key, 0, 12).'…' : $key;
    }

    /**
     * Determine if a license expires within the given number of days,
     * including licenses that have already expired.
     *
     * @param  array<string, mixed>  $license
     */
    protected function expiresWithin(array $license, int $days): bool
    {
        if (! ($license['expirable'] ?? false) || blank($license['expiresOn'] ?? null)) {
            return false;
        }

        try {
            return CarbonImmutable::parse($license['expiresOn'])->lte(CarbonImmutable::now()->addDays($days));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get the attributes shown for a plugin license.
     *
     * @param  array<string, mixed>  $license
     * @return array<string, mixed>
     */
    protected function pluginLicenseDetails(array $license): array
    {
        return [
            'ID' => $license['id'] ?? null,
            'Plugin' => $license['pluginHandle'] ?? null,
            'Edition' => $license['edition'] ?? null,
            'Key' => $license['key'] ?? null,
            'Trial' => $license['trial'] ?? null,
            'Owner' => $this->owner($license),
            'Craft license ID' => $license['cmsLicenseId'] ?? null,
            'Notes' => $license['notes'] ?? null,
            'Expires' => $this->expiry($license),
            'Auto-renew' => $license['autoRenew'] ?? null,
            'Renewal price' => $license['renewalPrice'] ?? null,
            'Last version' => $license['lastVersion'] ?? null,
            'Last allowed version' => $license['lastAllowedVersion'] ?? null,
            'Last status' => $license['lastStatus'] ?? null,
            'Last activity' => $this->date($license['lastActivityOn'] ?? null),
            'Last renewed' => $this->date($license['lastRenewedOn'] ?? null),
            'Created' => $this->date($license['dateCreated'] ?? null),
        ];
    }
}
