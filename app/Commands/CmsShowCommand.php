<?php

namespace App\Commands;

use function Laravel\Prompts\spin;

class CmsShowCommand extends Command
{
    use Concerns\FindsLicenses;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'cms:show
        {license : The license ID, key or domain}
        {--org= : Use this organization instead of the current one}
        {--personal : Use your personal account instead of the current organization}
        {--key : Only output the license key}
        {--json : Output raw JSON}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show a Craft license';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $org = $this->organization();

        $license = spin(fn () => $this->findLicense(
            $this->argument('license'),
            fn ($id) => $this->craftnet->cmsLicense($org, $id),
            fn ($query) => $this->craftnet->cmsLicenses($org, $query),
        ), 'Retrieving Craft license');

        if ($this->option('key')) {
            $this->line($license['key'] ?? '');

            return self::SUCCESS;
        }

        if ($this->wantsJson()) {
            $this->outputJson($license);

            return self::SUCCESS;
        }

        $this->details([
            'ID' => $license['id'] ?? null,
            'Edition' => $license['editionHandle'] ?? null,
            'Domain' => $license['domain'] ?? null,
            'Owner' => $this->owner($license),
            'Notes' => $license['notes'] ?? null,
            'Expires' => $this->expiry($license),
            'Auto-renew' => $license['autoRenew'] ?? null,
            'Renewal price' => $license['renewalPrice'] ?? null,
            'Last edition' => $license['lastEdition'] ?? null,
            'Last version' => $license['lastVersion'] ?? null,
            'Last allowed version' => $license['lastAllowedVersion'] ?? null,
            'Last status' => $license['lastStatus'] ?? null,
            'Last activity' => $this->date($license['lastActivityOn'] ?? null),
            'Last renewed' => $this->date($license['lastRenewedOn'] ?? null),
            'Created' => $this->date($license['dateCreated'] ?? null),
        ]);

        $this->line('  <comment>License key</comment> (use --key to output only the key):');
        $this->line('  '.($license['key'] ?? '—'));
        $this->newLine();

        return self::SUCCESS;
    }
}
