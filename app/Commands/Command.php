<?php

namespace App\Commands;

use App\Clients\Craftnet;
use App\Repositories\ConfigRepository;
use App\Support\Environment;
use LaravelZero\Framework\Commands\Command as BaseCommand;

abstract class Command extends BaseCommand
{
    use Concerns\FormatsLicenses,
        Concerns\InteractsWithOutput;

    /**
     * The aliases of the command.
     *
     * @var array<int, string>
     */
    protected $aliases = [];

    public function __construct(
        protected ConfigRepository $config,
        protected Craftnet $craftnet,
        protected Environment $environment,
    ) {
        if (method_exists($this, 'buildSignature')) {
            $this->signature = $this->buildSignature();
        }

        parent::__construct();

        $this->setAliases($this->aliases);
    }

    /**
     * Get the organization slug commands should be scoped to, or null for
     * your personal account. The --org and --personal options come first,
     * then CRAFT_CONSOLE_ORG, then the organization saved with 'org:switch'.
     */
    protected function organization(): ?string
    {
        if ($this->hasOption('personal') && $this->option('personal')) {
            return null;
        }

        if ($this->hasOption('org') && filled($this->option('org'))) {
            return $this->option('org');
        }

        return $this->defaultOrganization();
    }

    /**
     * Get the organization used when no option picks one: CRAFT_CONSOLE_ORG
     * (where "personal" means your personal account), then the
     * organization saved with 'org:switch'.
     */
    protected function defaultOrganization(): ?string
    {
        $org = $this->environment->get('CRAFT_CONSOLE_ORG');

        if ($org !== null) {
            return strtolower($org) === 'personal' ? null : $org;
        }

        return $this->config->get('organization');
    }

    /**
     * Describe the account context for status messages.
     */
    protected function contextLabel(?string $org): string
    {
        return $org ? "the {$org} organization" : 'your personal account';
    }
}
