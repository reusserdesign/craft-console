<?php

namespace App\Commands;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

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

        if ($source = $this->environment->source('CRAFT_CONSOLE_API_KEY')) {
            warning("CRAFT_CONSOLE_API_KEY is still set in {$source}, so commands there will stay authenticated.");
        }

        return self::SUCCESS;
    }
}
