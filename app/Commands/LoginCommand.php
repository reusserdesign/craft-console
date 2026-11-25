<?php

namespace App\Commands;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class LoginCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'login
        {--username= : Your Craft Console username or email}
        {--key= : Your Craft Console API key}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Authenticate with Craft Console';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = $this->option('username');
        $key = $this->option('key');

        if (blank($username) || blank($key)) {
            note('Generate an API key in Craft Console under Settings → Profile. It is only shown once.');
        }

        $username = filled($username) ? $username : text(
            label: 'Craft Console username or email',
            required: true,
        );

        $key = filled($key) ? $key : password(
            label: 'Craft Console API key',
            required: true,
        );

        $this->craftnet->setCredentials($username, $key);

        // There is no "current user" endpoint, so a one-result license
        // request is the cheapest way to verify the credentials.
        $this->spin(fn () => $this->craftnet->cmsLicenses(null, ['perPage' => 1]), 'Verifying credentials');

        $this->config->set('username', $username);
        $this->config->set('api_key', $key);

        info("Authenticated successfully as {$username}");

        return self::SUCCESS;
    }
}
