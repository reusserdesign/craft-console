<?php

namespace App\Commands\Concerns;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

trait InteractsWithOutput
{
    /**
     * Determine if the user asked for JSON output.
     */
    protected function wantsJson(): bool
    {
        return $this->hasOption('json') && (bool) $this->option('json');
    }

    /**
     * Run the callback behind a spinner, unless the output is JSON or
     * isn't a terminal, where spinner frames would corrupt the output.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    protected function spin(callable $callback, string $message = ''): mixed
    {
        if ($this->wantsJson() || ! stream_isatty(STDOUT)) {
            return $callback();
        }

        return \Laravel\Prompts\spin($callback, $message);
    }

    /**
     * Write the given data to the output as pretty-printed JSON.
     */
    protected function outputJson(mixed $data): void
    {
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Render a two-column table of attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function details(array $attributes): void
    {
        table(
            ['Attribute', 'Value'],
            collect($attributes)
                ->map(fn ($value, $label) => [$label, $this->displayValue($value)])
                ->values()
                ->all(),
        );
    }

    /**
     * Render a page footer for paginated results.
     *
     * @param  array{total: int, totalPages: int}  $result
     */
    protected function paginationFooter(array $result, int $page, bool $all): void
    {
        $total = $result['total'];
        $noun = $total === 1 ? 'license' : 'licenses';

        if ($all || $result['totalPages'] <= 1) {
            info("{$total} {$noun}");

            return;
        }

        info("Page {$page} of {$result['totalPages']} ({$total} {$noun}). Use --page or --all to see more.");
    }

    /**
     * Convert a value to a string suitable for a table cell.
     */
    protected function displayValue(mixed $value): string
    {
        return match (true) {
            is_null($value), $value === '' => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }
}
