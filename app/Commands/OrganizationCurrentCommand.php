<?php

namespace App\Commands;

use function Laravel\Prompts\info;

class OrganizationCurrentCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'organization:current';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show which account license commands are scoped to';

    /**
     * The aliases of the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['org:current'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $org = $this->config->get('organization');

        info($org
            ? "You are working within the {$org} organization."
            : 'You are working within your personal account.');

        return self::SUCCESS;
    }
}
