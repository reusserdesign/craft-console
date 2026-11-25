<?php

namespace App\Commands;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

class OrganizationListCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'organization:list';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'List the organizations you have switched to before';

    /**
     * The aliases of the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['org:list'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $current = $this->config->get('organization');

        $rows = collect($this->config->get('organizations', []))
            ->map(fn ($slug) => [$slug, $slug === $current ? '✓' : ''])
            ->prepend(['(personal)', $current ? '' : '✓']);

        table(['Organization', 'Current'], $rows->all());

        info("The Craftnet API cannot list organizations, so this shows the ones you've used with 'org:switch'.");

        return self::SUCCESS;
    }
}
