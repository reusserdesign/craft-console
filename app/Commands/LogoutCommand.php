<?php

namespace App\Commands;

use function Laravel\Prompts\info;

class LogoutCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'logout';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Remove your saved Craft Console credentials';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->config->flush();

        info('Logged out successfully');

        return self::SUCCESS;
    }
}
