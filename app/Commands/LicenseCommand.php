<?php

namespace App\Commands;

use App\Exceptions\CraftnetException;
use App\Support\CraftProject;
use App\Support\UpdateEligibility;
use Illuminate\Support\Collection;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class LicenseCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'license
        {path? : Path to a Craft project (defaults to the current directory)}
        {--org= : Only look in this organization}
        {--personal : Only look in your personal account}
        {--key : Only output the license key}
        {--json : Output raw JSON}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show the Craft license for the project in the current directory';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $project = CraftProject::detect($this->argument('path') ?? getcwd());

        abort_if($project === null, 1, 'No Craft project found. Run this inside a Craft project, or pass its path.');

        $local = $project->licenseKey();

        abort_if($local === null, 1, sprintf(
            'No license key found in %s. Craft looks for CRAFT_LICENSE_KEY, CRAFT_LICENSE_KEY_PATH or config/license.key.',
            $project->path(),
        ));

        if ($this->option('key')) {
            $this->line($local['key']);

            return self::SUCCESS;
        }

        if (! $this->wantsJson()) {
            $this->renderProject($project, $local);
        }

        [$license, $searched] = spin(
            fn () => $this->findOwnedLicense($local['key']),
            'Looking up the license in Craft Console',
        );

        $releases = $license ? spin(
            fn () => $this->craftnet->package('craftcms/cms', ['include' => 'releases'])['releases'] ?? [],
            'Retrieving Craft releases',
        ) : [];

        $eligibility = $license ? new UpdateEligibility($license, $releases) : null;
        $installed = $project->craftVersion();

        if ($this->wantsJson()) {
            $this->outputJson($this->jsonSummary($project, $local, $license, $eligibility));

            return $license ? self::SUCCESS : self::FAILURE;
        }

        if ($license === null) {
            warning(sprintf(
                "This license isn't owned by %s. Ask whoever owns it to add you to their organization, then run 'org:switch'.",
                $searched->map(fn ($org) => $this->contextLabel($org))->join(', ', ' or '),
            ));

            return self::FAILURE;
        }

        $this->renderLicense($license, $eligibility, $installed);

        $this->renderVerdict($license, $eligibility, $project);

        return self::SUCCESS;
    }

    /**
     * Search for the license by key across the current context, your
     * personal account and every organization you've switched to.
     *
     * @return array{0: array<string, mixed>|null, 1: Collection<int, string|null>}
     */
    protected function findOwnedLicense(string $key): array
    {
        $contexts = match (true) {
            (bool) $this->option('personal') => collect([null]),
            filled($this->option('org')) => collect([$this->option('org')]),
            default => collect([$this->config->get('organization'), null])
                ->merge($this->config->get('organizations', []))
                ->unique()
                ->values(),
        };

        foreach ($contexts as $org) {
            try {
                $matches = $this->craftnet->cmsLicenses($org, ['search' => $key, 'perPage' => 10])['licenses'] ?? [];
            } catch (CraftnetException $e) {
                // An organization you've lost access to shouldn't stop the
                // search, but anything else (such as bad credentials) should.
                if ($e->isNotAccessible()) {
                    continue;
                }

                throw $e;
            }

            foreach ($matches as $license) {
                if (hash_equals((string) ($license['key'] ?? ''), $key)) {
                    return [$license, $contexts];
                }
            }
        }

        return [null, $contexts];
    }

    /**
     * Show what was found in the project.
     *
     * @param  array{key: string, source: string}  $local
     */
    protected function renderProject(CraftProject $project, array $local): void
    {
        $version = $project->craftVersion();

        table(['Project', ''], [
            ['Path', $project->path()],
            ['Craft version', $version ? ($project->hasLockedVersion() ? $version : "{$version} (from composer.json; no composer.lock)") : '—'],
            ['Edition in project config', $this->displayValue($project->edition())],
            ['License key from', $local['source']],
        ]);
    }

    /**
     * Show the license and the versions it can install.
     *
     * @param  array<string, mixed>  $license
     */
    protected function renderLicense(array $license, UpdateEligibility $eligibility, ?string $installed): void
    {
        table(['License', ''], [
            ['ID', (string) $license['id']],
            ['Edition', $this->displayValue($license['editionHandle'] ?? null)],
            ['Domain', $this->displayValue($license['domain'] ?? null)],
            ['Owner', $this->owner($license)],
            ['Notes', $this->displayValue($license['notes'] ?? null)],
            ['Updates until', $this->expiry($license)],
            ['Renewal price', $this->renewalPrice($license)],
            ['Auto-renew', $this->displayValue($license['autoRenew'] ?? null)],
            ['Installed version', $this->displayValue($installed)],
            ['Last seen running', $this->displayValue($license['lastVersion'] ?? null)],
            ['Last activity', $this->date($license['lastActivityOn'] ?? null)],
        ]);

        $installedMajor = UpdateEligibility::majorOf($installed);

        table(
            ['Craft', 'Latest eligible', 'Latest release', 'Needs renewal'],
            $eligibility->majors($installed)
                ->map(fn ($row) => [
                    $row['major'].'.x'.($row['major'] === $installedMajor ? ' (installed)' : ''),
                    $row['latestEligibleVersion'] ?? 'None',
                    $row['latestVersion'],
                    match (true) {
                        $row['versionsNeedingRenewal'] === 0 => 'No',
                        $row['latestEligibleVersion'] === null => 'Yes (every release)',
                        default => "Yes ({$row['versionsNeedingRenewal']} newer ".str('release')->plural($row['versionsNeedingRenewal']).')',
                    },
                ])
                ->all(),
        );
    }

    /**
     * Sum up what the license means for this project.
     *
     * @param  array<string, mixed>  $license
     */
    protected function renderVerdict(array $license, UpdateEligibility $eligibility, CraftProject $project): void
    {
        $installed = $project->craftVersion();
        $major = UpdateEligibility::majorOf($installed);
        $current = $eligibility->majors($installed)->firstWhere('major', $major);
        $eligible = $current['latestEligibleVersion'] ?? $eligibility->latestEligibleVersion();
        $latest = $eligibility->latestVersion();
        $price = $this->renewalPrice($license);

        if ($eligibility->exceeds($installed) && $project->hasLockedVersion()) {
            warning($eligible
                ? "Craft {$installed} is newer than this license allows ({$eligible}). Renew ({$price}) or the control panel will flag it."
                : "No Craft {$major} release is covered by this license. Renew ({$price}) or the control panel will flag it.");
        }

        $edition = $project->edition();

        if ($edition && filled($license['editionHandle'] ?? null) && strcasecmp($edition, $license['editionHandle']) !== 0) {
            warning("The project runs Craft {$edition}, but the license is for Craft {$license['editionHandle']}.");
        }

        if ($eligibility->isPerpetual()) {
            info("This license gets every update. Craft {$latest} is the latest.");

            return;
        }

        $expiresOn = $eligibility->expiresOn()?->format('Y-m-d');

        if ($eligibility->hasExpired()) {
            $reach = $eligible ? "This site can update as far as Craft {$eligible}." : "No Craft {$major} release is covered.";

            warning("Updates ended {$expiresOn}. {$reach} Renewing ({$price}) unlocks Craft {$latest}.");

            return;
        }

        $renewal = ($license['autoRenew'] ?? false) ? 'It renews automatically' : "Renewal is {$price}";

        info("Updates are active until {$expiresOn}, so every release so far (up to Craft {$latest}) is covered. {$renewal}.");
    }

    /**
     * Format a license's renewal price.
     *
     * @param  array<string, mixed>  $license
     */
    protected function renewalPrice(array $license): string
    {
        $price = $license['renewalPrice'] ?? null;

        if (blank($price)) {
            return '—';
        }

        return is_numeric($price) ? '$'.number_format((float) $price, 2).'/year' : (string) $price;
    }

    /**
     * Build the --json output.
     *
     * @param  array{key: string, source: string}  $local
     * @param  array<string, mixed>|null  $license
     * @return array<string, mixed>
     */
    protected function jsonSummary(CraftProject $project, array $local, ?array $license, ?UpdateEligibility $eligibility): array
    {
        return [
            'project' => [
                'path' => $project->path(),
                'craftVersion' => $project->craftVersion(),
                'edition' => $project->edition(),
                'licenseKeySource' => $local['source'],
            ],
            'license' => $license,
            'updates' => $eligibility ? [
                'perpetual' => $eligibility->isPerpetual(),
                'expired' => $eligibility->hasExpired(),
                'expiresOn' => $eligibility->expiresOn()?->toIso8601String(),
                'latestEligibleVersion' => $eligibility->latestEligibleVersion(),
                'latestVersion' => $eligibility->latestVersion(),
                'versionsNeedingRenewal' => $eligibility->lockedVersions()->all(),
                'majors' => $eligibility->majors($project->craftVersion())->all(),
                'installedExceedsLicense' => $eligibility->exceeds($project->craftVersion()),
            ] : null,
        ];
    }
}
