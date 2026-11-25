<?php

namespace App\Commands;

class DeveloperListCommand extends Command
{
    use Concerns\ListsLicenses;

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'List licenses issued for plugins you develop';

    /**
     * The aliases of the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['dev:list'];

    /**
     * Build the signature of the command.
     */
    protected function buildSignature(): string
    {
        return 'developer:list
            {--plugin= : Only licenses for this plugin handle}
            {--trials : Include trial licenses}
            '.static::listOptions('dateCreated, expiresOn, key, email or pluginHandle');
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $result = $this->fetchLicenses(
            fn (array $query) => $this->craftnet->developerLicenses($query),
            [
                'pluginHandle' => $this->option('plugin'),
                'includeTrials' => $this->option('trials') ? 1 : null,
            ],
            'Retrieving licenses for your plugins',
        );

        return $this->renderLicenses(
            $result,
            ['ID', 'Plugin', 'Edition', 'Key', 'Owner', 'Expires', 'Created'],
            fn (array $license) => [
                (string) $license['id'],
                $this->displayValue($license['pluginHandle'] ?? null).(($license['trial'] ?? false) ? ' (trial)' : ''),
                $this->displayValue($license['edition'] ?? null),
                $this->displayValue($license['key'] ?? null),
                $this->owner($license),
                $this->expiry($license),
                $this->date($license['dateCreated'] ?? null),
            ],
            'No licenses found for your plugins.',
        );
    }
}
