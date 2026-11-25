<?php

namespace App\Support;

use Laravel\Prompts\Spinner as BaseSpinner;

/**
 * Laravel Prompts' spinner, with the animation stopped before the spinner
 * is erased.
 *
 * The base spinner animates from a forked child process and only kills it
 * in __destruct(), after the parent has already erased the spinner line.
 * The child can paint one more frame in between, which moves the cursor
 * up and clears the line above: the bottom border of a table printed just
 * before the spinner disappeared, and a stray spinner frame was left behind.
 */
class Spinner extends BaseSpinner
{
    /**
     * Render with whatever the active theme uses for the base spinner, since
     * themes register renderers by exact class name.
     */
    protected function getRenderer(): callable
    {
        $renderer = static::$themes[static::$theme][BaseSpinner::class]
            ?? static::$themes['default'][BaseSpinner::class];

        return new $renderer($this);
    }

    protected function resetTerminal(bool $originalAsync, callable|int $originalSignalHandler = SIG_DFL): void
    {
        if (! empty($this->pid)) {
            posix_kill($this->pid, SIGHUP);
            pcntl_waitpid($this->pid, $status);

            // The render loop may have been killed mid-frame, so end any open synchronized update.
            static::writeDirectly("\e[?2026l");

            // Already stopped, so __destruct() has nothing left to kill.
            $this->pid = 0;
        }

        parent::resetTerminal($originalAsync, $originalSignalHandler);
    }
}
