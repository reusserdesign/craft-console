<?php

namespace App\Commands;

use function Laravel\Prompts\spin;

class DeveloperShowCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'developer:show
        {license : The license key or ID}
        {--json : Output raw JSON}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show a license issued for one of your plugins';

    /**
     * The aliases of the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['dev:show'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $license = spin(
            fn () => $this->craftnet->developerLicense($this->argument('license')),
            'Retrieving license',
        );

        if ($this->wantsJson()) {
            $this->outputJson($license);

            return self::SUCCESS;
        }

        $this->details($this->pluginLicenseDetails($license));

        return self::SUCCESS;
    }
}
