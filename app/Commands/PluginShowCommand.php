<?php

namespace App\Commands;

use function Laravel\Prompts\spin;

class PluginShowCommand extends Command
{
    use Concerns\FindsLicenses;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'plugin:show
        {license : The license ID or key}
        {--org= : Use this organization instead of the current one}
        {--personal : Use your personal account instead of the current organization}
        {--key : Only output the license key}
        {--json : Output raw JSON}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show a plugin license';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $org = $this->organization();

        $license = spin(fn () => $this->findLicense(
            $this->argument('license'),
            fn ($id) => $this->craftnet->pluginLicense($org, $id),
            fn ($query) => $this->craftnet->pluginLicenses($org, $query),
        ), 'Retrieving plugin license');

        if ($this->option('key')) {
            $this->line($license['key'] ?? '');

            return self::SUCCESS;
        }

        if ($this->wantsJson()) {
            $this->outputJson($license);

            return self::SUCCESS;
        }

        $this->details($this->pluginLicenseDetails($license));

        return self::SUCCESS;
    }
}
