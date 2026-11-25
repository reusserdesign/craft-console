<?php

namespace App\Commands\Concerns;

use App\Exceptions\CraftnetException;
use App\Support\CraftProject;
use App\Support\UpdateEligibility;
use Illuminate\Support\Collection;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

trait ReportsPluginLicenses
{
    /**
     * Look up the license and releases for every plugin in the project.
     *
     * @param  Collection<int, string|null>  $contexts
     * @param  array<string, mixed>|null  $cmsLicense
     * @return Collection<int, array<string, mixed>>
     */
    protected function pluginReports(CraftProject $project, Collection $contexts, ?array $cmsLicense): Collection
    {
        $plugins = collect($project->plugins());

        if ($plugins->isEmpty()) {
            return collect();
        }

        return spin(
            fn () => $plugins->map(fn (array $plugin) => $this->pluginReport($plugin, $contexts, $cmsLicense)),
            "Checking {$plugins->count()} plugins",
        );
    }

    /**
     * Build the report for a single plugin.
     *
     * @param  array{handle: string, package: ?string, version: ?string, edition: ?string, enabled: bool, licenseKey: ?string}  $plugin
     * @param  Collection<int, string|null>  $contexts
     * @param  array<string, mixed>|null  $cmsLicense
     * @return array<string, mixed>
     */
    protected function pluginReport(array $plugin, Collection $contexts, ?array $cmsLicense): array
    {
        $license = null;

        if ($plugin['licenseKey']) {
            [$license] = $this->findOwnedLicense(
                $plugin['licenseKey'],
                $contexts,
                $this->craftnet->pluginLicenses(...),
                ['includeTrials' => 1, 'pluginHandle' => $plugin['handle']],
            );
        }

        $releases = null;

        if ($plugin['package']) {
            try {
                $releases = $this->craftnet->package($plugin['package'], ['include' => 'releases'])['releases'] ?? [];
            } catch (CraftnetException $e) {
                // Private packages aren't on Packagist, so Craftnet doesn't know them.
                if (! $e->isNotAccessible()) {
                    throw $e;
                }
            }
        }

        $eligibility = new UpdateEligibility($license ?? ['expirable' => false], $releases ?? []);
        $current = $eligibility->majors($plugin['version'])->firstWhere('major', UpdateEligibility::majorOf($plugin['version']));

        return [
            ...$plugin,
            'licenseKey' => $plugin['licenseKey'] ? $this->shortKey($plugin['licenseKey']) : null,
            'license' => $license,
            'status' => match (true) {
                $license !== null => 'licensed',
                filled($plugin['licenseKey']) => 'not-found',
                default => 'no-key',
            },
            'onPackagist' => $releases !== null,
            'latestEligibleVersion' => $license ? ($current['latestEligibleVersion'] ?? null) : null,
            'latestVersion' => $eligibility->latestVersion(),
            'majors' => $license ? $eligibility->majors($plugin['version'])->all() : [],
            'exceedsLicense' => $license !== null && $eligibility->exceeds($plugin['version']),
            'expired' => $license !== null && $eligibility->hasExpired(),
            'attachedToOtherCraftLicense' => $license && $cmsLicense
                && filled($license['cmsLicenseId'] ?? null)
                && (int) $license['cmsLicenseId'] !== (int) $cmsLicense['id'],
        ];
    }

    /**
     * Render the plugin table and any problems worth calling out.
     *
     * @param  Collection<int, array<string, mixed>>  $reports
     */
    protected function renderPlugins(Collection $reports): void
    {
        if ($reports->isEmpty()) {
            return;
        }

        table(
            ['Plugin', 'Installed', 'Edition', 'Updates until', 'Latest eligible', 'Latest release', 'Renewal'],
            $reports->map(fn (array $report) => [
                $report['handle'].($report['enabled'] ? '' : ' (disabled)'),
                $this->displayValue($report['version']),
                $this->displayValue($report['license']['edition'] ?? $report['edition']),
                match ($report['status']) {
                    'licensed' => $this->expiry($report['license']),
                    'not-found' => 'Not in your accounts',
                    default => 'No license key',
                },
                $report['status'] === 'licensed' ? $this->displayValue($report['latestEligibleVersion'] ?? 'None') : '—',
                $report['onPackagist'] ? $this->displayValue($report['latestVersion']) : 'Not on Packagist',
                $report['license'] ? $this->renewalPrice($report['license']) : '—',
            ])->all(),
        );

        foreach ($reports as $report) {
            $handle = $report['handle'];
            $licensedEdition = $report['license']['edition'] ?? null;

            if ($report['exceedsLicense']) {
                warning("{$handle} {$report['version']} is newer than its license allows (".($report['latestEligibleVersion'] ?? 'none').').');
            }

            if ($licensedEdition && $report['edition'] && strcasecmp($licensedEdition, $report['edition']) !== 0) {
                warning("{$handle} runs the {$report['edition']} edition, but its license is for {$licensedEdition}.");
            }

            if ($report['license']['trial'] ?? false) {
                warning("{$handle} is using a trial license.");
            }

            if ($report['attachedToOtherCraftLicense']) {
                warning("{$handle}'s license is attached to a different Craft license (#{$report['license']['cmsLicenseId']}).");
            }
        }

        $missing = $reports->where('status', 'not-found')->pluck('handle');

        if ($missing->isNotEmpty()) {
            warning('Not found in your accounts: '.$missing->join(', ').'. They may belong to a client or another organization.');
        }
    }

    /**
     * Render the yearly cost of renewing every license found, and the
     * cost of bringing lapsed ones current.
     *
     * @param  array<string, mixed>|null  $cmsLicense
     * @param  Collection<int, array<string, mixed>>  $reports
     */
    protected function renderRenewalSummary(?array $cmsLicense, ?UpdateEligibility $cmsEligibility, Collection $reports): void
    {
        $items = collect();

        if ($cmsLicense && ! $cmsEligibility?->isPerpetual()) {
            $items->push(['name' => 'Craft', 'license' => $cmsLicense, 'expired' => $cmsEligibility?->hasExpired() ?? false]);
        }

        foreach ($reports->where('status', 'licensed') as $report) {
            if ($report['license']['expirable'] ?? false) {
                $items->push(['name' => $report['handle'], 'license' => $report['license'], 'expired' => $report['expired']]);
            }
        }

        $priced = $items->filter(fn ($item) => is_numeric($item['license']['renewalPrice'] ?? null));

        if ($priced->isEmpty()) {
            return;
        }

        $total = $priced->sum(fn ($item) => (float) $item['license']['renewalPrice']);
        $lapsed = $priced->where('expired', true);

        $message = sprintf('Renewing all %d licenses costs %s/year.', $priced->count(), $this->money($total));

        if ($lapsed->isNotEmpty()) {
            $message .= sprintf(
                ' %d %s lapsed (%s): %s to renew.',
                $lapsed->count(),
                $lapsed->count() === 1 ? 'has' : 'have',
                $lapsed->pluck('name')->join(', '),
                $this->money($lapsed->sum(fn ($item) => (float) $item['license']['renewalPrice'])),
            );
        }

        info($message);
    }

    /**
     * Format an amount in US dollars.
     */
    protected function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }
}
