<?php

namespace App\Commands;

use function Laravel\Prompts\info;

class DeveloperUpdateCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'developer:update
        {license : The license key or ID}
        {--edition= : Change the plugin edition}
        {--expirable : Make the license expirable}
        {--non-expirable : Make the license never expire}
        {--expires-on= : When an expirable license expires}
        {--notes= : Notes shared with the customer (pass an empty value to clear)}
        {--private-notes= : Notes only you can see (pass an empty value to clear)}
        {--json : Output raw JSON}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Update a license issued for one of your plugins';

    /**
     * The aliases of the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['dev:update'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        abort_if(
            $this->option('expirable') && $this->option('non-expirable'),
            1,
            'Pass either --expirable or --non-expirable, not both.',
        );

        $attributes = array_filter([
            'edition' => $this->option('edition'),
            'expirable' => match (true) {
                $this->option('expirable') => true,
                $this->option('non-expirable') => false,
                default => null,
            },
            'expiresOn' => $this->option('expires-on'),
            'notes' => $this->option('notes'),
            'privateNotes' => $this->option('private-notes'),
        ], fn ($value) => ! is_null($value));

        abort_if($attributes === [], 1, 'Nothing to update. Pass at least one option, such as --edition or --notes.');

        $license = $this->spin(
            fn () => $this->craftnet->updateDeveloperLicense($this->argument('license'), $attributes),
            'Updating license',
        );

        if ($this->wantsJson()) {
            $this->outputJson($license);

            return self::SUCCESS;
        }

        $this->details($this->pluginLicenseDetails($license));

        info('License updated');

        return self::SUCCESS;
    }
}
