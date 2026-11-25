<?php

namespace App\Commands;

use App\Clients\Craftnet;
use App\Repositories\ConfigRepository;
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
    ) {
        if (method_exists($this, 'buildSignature')) {
            $this->signature = $this->buildSignature();
        }

        parent::__construct();

        $this->setAliases($this->aliases);
    }

    /**
     * Get the organization slug commands should be scoped to, or null for
     * your personal account. The --org and --personal options take
     * precedence over the organization saved with 'org:switch'.
     */
    protected function organization(): ?string
    {
        if ($this->hasOption('personal') && $this->option('personal')) {
            return null;
        }

        if ($this->hasOption('org') && filled($this->option('org'))) {
            return $this->option('org');
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
