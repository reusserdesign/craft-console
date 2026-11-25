<?php

namespace App\Commands;

class PluginListCommand extends Command
{
    use Concerns\ListsLicenses;

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'List plugin licenses owned by you or the current organization';

    /**
     * The aliases of the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['plugins'];

    /**
     * Build the signature of the command.
     */
    protected function buildSignature(): string
    {
        return 'plugin:list
            {--plugin= : Only licenses for this plugin handle}
            {--trials : Include trial licenses}
            {--org= : Use this organization instead of the current one}
            {--personal : Use your personal account instead of the current organization}
            '.static::listOptions('dateCreated, expiresOn, key, email or pluginHandle');
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $org = $this->organization();

        $result = $this->fetchLicenses(
            fn (array $query) => $this->craftnet->pluginLicenses($org, $query),
            [
                'pluginHandle' => $this->option('plugin'),
                'includeTrials' => $this->option('trials') ? 1 : null,
            ],
            'Retrieving plugin licenses for '.$this->contextLabel($org),
        );

        return $this->renderLicenses(
            $result,
            ['ID', 'Plugin', 'Edition', 'Key', 'Owner', 'Craft license', 'Expires', 'Auto-renew'],
            fn (array $license) => [
                (string) $license['id'],
                $this->displayValue($license['pluginHandle'] ?? null).(($license['trial'] ?? false) ? ' (trial)' : ''),
                $this->displayValue($license['edition'] ?? null),
                $this->shortKey($license['key'] ?? null),
                $this->owner($license),
                $this->displayValue($license['cmsLicenseId'] ?? null),
                $this->expiry($license),
                $this->displayValue($license['autoRenew'] ?? null),
            ],
            'No plugin licenses found for '.$this->contextLabel($org).'.',
        );
    }
}
