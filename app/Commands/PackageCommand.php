<?php

namespace App\Commands;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class PackageCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'package
        {name : The Composer package name, such as craftcms/cms}
        {--releases : Include known releases}
        {--constraint= : Only releases matching this version constraint, such as ^5.0}
        {--stability=stable : Minimum release stability (dev, alpha, beta, RC or stable)}
        {--json : Output raw JSON}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show what Craft knows about a Composer package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $withReleases = $this->option('releases') || filled($this->option('constraint'));

        $package = spin(fn () => $this->craftnet->package($this->argument('name'), [
            'include' => $withReleases ? 'releases' : null,
            'constraint' => $this->option('constraint'),
            'minStability' => $this->option('stability'),
        ]), 'Retrieving package');

        if ($this->wantsJson()) {
            $this->outputJson($package);

            return self::SUCCESS;
        }

        $this->details([
            'Name' => $package['name'] ?? null,
            'Type' => $package['type'] ?? null,
            'Repository' => $package['repository'] ?? null,
            'Managed' => $package['managed'] ?? null,
            'Abandoned' => $package['abandoned'] ?? null,
            'Replacement' => $package['replacementPackage'] ?? null,
            'Latest release' => $package['latestRelease']['version'] ?? null,
            'Released' => $this->date($package['latestRelease']['time'] ?? null),
        ]);

        if ($withReleases && ! empty($package['releases'])) {
            table(
                ['Version', 'Released'],
                collect($package['releases'])
                    ->map(fn ($release) => [
                        $this->displayValue($release['version'] ?? null),
                        $this->date($release['time'] ?? null),
                    ])
                    ->all(),
            );
        }

        return self::SUCCESS;
    }
}
