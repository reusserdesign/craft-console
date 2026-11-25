<?php

namespace App\Commands;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\suggest;

class OrganizationSwitchCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'organization:switch
        {organization? : The organization slug}
        {--personal : Switch back to your personal account}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Switch to an organization, or back to your personal account';

    /**
     * The aliases of the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['org:switch'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('personal')) {
            $this->config->forget('organization');

            info('Switched to your personal account');

            return self::SUCCESS;
        }

        /** @var array<int, string> $known */
        $known = $this->config->get('organizations', []);

        $slug = $this->argument('organization') ?? suggest(
            label: 'Which organization would you like to switch to?',
            options: $known,
            placeholder: 'The slug from the organization\'s Craft Console URL',
            required: true,
        );

        $slug = strtolower(trim($slug));

        // The API has no endpoint for listing organizations, so confirm
        // access by requesting a single license from the organization.
        spin(fn () => $this->craftnet->cmsLicenses($slug, ['perPage' => 1]), 'Checking organization access');

        $this->config->set('organization', $slug);
        $this->config->set('organizations', collect($known)->push($slug)->unique()->sort()->values()->all());

        info("Switched to the {$slug} organization");

        return self::SUCCESS;
    }
}
