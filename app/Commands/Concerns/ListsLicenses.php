<?php

namespace App\Commands\Concerns;

use App\Clients\Craftnet;
use Illuminate\Support\Collection;

use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

trait ListsLicenses
{
    /**
     * The options shared by every license list command.
     */
    protected static function listOptions(string $sortChoices): string
    {
        return <<<SIGNATURE
            {--search= : Case-insensitive search of keys, emails, notes and domains}
            {--edition= : Comma-delimited edition handles}
            {--before= : Only licenses created before this UTC datetime}
            {--after= : Only licenses created after this UTC datetime}
            {--sort=dateCreated : Sort by {$sortChoices}}
            {--asc : Sort ascending instead of descending}
            {--expiring= : Only licenses that expire within this many days (fetches every page)}
            {--page=1 : The page of results to show}
            {--per-page=100 : Results per page (max 100)}
            {--all : Fetch every page}
            {--json : Output raw JSON}
            SIGNATURE;
    }

    /**
     * Fetch licenses using the shared list options, following pagination
     * when --all or --expiring is given.
     *
     * @param  callable(array<string, mixed>): array{total: int, totalPages: int, licenses: array<int, array<string, mixed>>}  $fetch
     * @param  array<string, mixed>  $query
     * @return array{total: int, totalPages: int, page: int, all: bool, licenses: Collection<int, array<string, mixed>>}
     */
    protected function fetchLicenses(callable $fetch, array $query, string $message): array
    {
        $expiring = $this->option('expiring');
        $all = $this->option('all') || filled($expiring);
        $page = max(1, (int) $this->option('page'));
        $perPage = min(Craftnet::MAX_PER_PAGE, max(1, (int) $this->option('per-page')));

        $query = array_merge($query, [
            'search' => $this->option('search'),
            'edition' => $this->option('edition'),
            'before' => $this->option('before'),
            'after' => $this->option('after'),
            'orderBy' => $this->option('sort'),
            'ascending' => $this->option('asc') ? 1 : 0,
            'perPage' => $all ? Craftnet::MAX_PER_PAGE : $perPage,
        ]);

        $result = $this->spin(function () use ($fetch, $query, $all, $page) {
            $result = $fetch([...$query, 'page' => $all ? 1 : $page]);
            $licenses = collect($result['licenses'] ?? []);

            for ($next = 2; $all && $next <= ($result['totalPages'] ?? 1); $next++) {
                $licenses->push(...($fetch([...$query, 'page' => $next])['licenses'] ?? []));
            }

            return [
                'total' => (int) ($result['total'] ?? $licenses->count()),
                'totalPages' => (int) ($result['totalPages'] ?? 1),
                'licenses' => $licenses,
            ];
        }, $message);

        if (filled($expiring)) {
            $result['licenses'] = $result['licenses']
                ->filter(fn ($license) => $this->expiresWithin($license, (int) $expiring))
                ->sortBy('expiresOn')
                ->values();

            $result['total'] = $result['licenses']->count();
        }

        return [...$result, 'page' => $page, 'all' => $all];
    }

    /**
     * Render fetched licenses as JSON or as a table.
     *
     * @param  array{total: int, totalPages: int, page: int, all: bool, licenses: Collection<int, array<string, mixed>>}  $result
     * @param  array<int, string>  $headers
     * @param  callable(array<string, mixed>): array<int, string>  $row
     */
    protected function renderLicenses(array $result, array $headers, callable $row, string $emptyMessage): int
    {
        if ($this->wantsJson()) {
            $this->outputJson([
                'total' => $result['total'],
                'totalPages' => $result['all'] ? 1 : $result['totalPages'],
                'licenses' => $result['licenses']->values()->all(),
            ]);

            return self::SUCCESS;
        }

        if ($result['licenses']->isEmpty()) {
            warning($emptyMessage);

            return self::SUCCESS;
        }

        table($headers, $result['licenses']->map($row)->all());

        $this->paginationFooter($result, $result['page'], $result['all']);

        return self::SUCCESS;
    }
}
