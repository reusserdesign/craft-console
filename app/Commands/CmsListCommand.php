<?php

namespace App\Commands;

class CmsListCommand extends Command
{
    use Concerns\ListsLicenses;

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'List Craft licenses owned by you or the current organization';

    /**
     * The aliases of the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['cms'];

    /**
     * Build the signature of the command.
     */
    protected function buildSignature(): string
    {
        return 'cms:list
            {--org= : Use this organization instead of the current one}
            {--personal : Use your personal account instead of the current organization}
            '.static::listOptions('dateCreated, expiresOn, key, email, domain or editionHandle');
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $org = $this->organization();

        $result = $this->fetchLicenses(
            fn (array $query) => $this->craftnet->cmsLicenses($org, $query),
            [],
            'Retrieving Craft licenses for '.$this->contextLabel($org),
        );

        return $this->renderLicenses(
            $result,
            ['ID', 'Edition', 'Domain', 'Owner', 'Expires', 'Auto-renew', 'Last version'],
            fn (array $license) => [
                (string) $license['id'],
                $this->displayValue($license['editionHandle'] ?? null),
                $this->displayValue($license['domain'] ?? null),
                $this->owner($license),
                $this->expiry($license),
                $this->displayValue($license['autoRenew'] ?? null),
                $this->displayValue($license['lastVersion'] ?? null),
            ],
            'No Craft licenses found for '.$this->contextLabel($org).'.',
        );
    }
}
