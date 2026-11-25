<?php

namespace App\Commands;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class DeveloperCreateCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'developer:create
        {--plugin= : The plugin handle}
        {--edition= : The plugin edition handle}
        {--email= : The licensee\'s email address}
        {--org= : The licensee\'s organization slug (instead of --email)}
        {--expirable : Make the license expirable}
        {--expires-on= : When an expirable license expires (defaults to one year from now)}
        {--notes= : Notes shared with the customer}
        {--private-notes= : Notes only you can see}
        {--count=1 : How many licenses to create}
        {--json : Output raw JSON}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create licenses for one of your plugins';

    /**
     * The aliases of the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['dev:create'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $plugin = $this->option('plugin') ?: text('Plugin handle', required: true);
        $edition = $this->option('edition') ?: text('Edition', default: 'standard', required: true);
        $org = $this->option('org');
        $email = $this->option('email') ?: ($org ? null : text(
            label: 'Licensee email address',
            required: true,
            validate: fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.',
        ));

        abort_if($email && $org, 1, 'Pass either --email or --org, not both.');

        $count = max(1, (int) $this->option('count'));

        $attributes = array_filter([
            'plugin' => $plugin,
            'edition' => $edition,
            'email' => $email,
            'org' => $org,
            'expirable' => (bool) $this->option('expirable'),
            'expiresOn' => $this->option('expires-on'),
            'notes' => $this->option('notes'),
            'privateNotes' => $this->option('private-notes'),
        ], fn ($value) => ! is_null($value));

        $licenses = $this->spin(
            fn () => collect(range(1, $count))->map(fn () => $this->craftnet->createDeveloperLicense($attributes)),
            $count === 1 ? 'Creating license' : "Creating {$count} licenses",
        );

        if ($this->wantsJson()) {
            $this->outputJson($count === 1 ? $licenses->first() : $licenses->all());

            return self::SUCCESS;
        }

        table(
            ['ID', 'Plugin', 'Edition', 'Owner', 'Key'],
            $licenses->map(fn ($license) => [
                $this->displayValue($license['id'] ?? null),
                $this->displayValue($license['pluginHandle'] ?? $plugin),
                $this->displayValue($license['edition'] ?? $edition),
                $this->owner($license),
                $this->displayValue($license['key'] ?? null),
            ])->all(),
        );

        info($count === 1 ? 'License created' : "{$count} licenses created");

        return self::SUCCESS;
    }
}
