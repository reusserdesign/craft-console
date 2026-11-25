<?php

namespace App\Commands\Concerns;

trait FindsLicenses
{
    /**
     * Resolve a license from an ID, or from a key (or other search term)
     * when the identifier is not numeric. The owner endpoints only accept
     * IDs, so keys are looked up through the list endpoint's search.
     *
     * @param  callable(int|string): array<string, mixed>  $findById
     * @param  callable(array<string, mixed>): array{licenses: array<int, array<string, mixed>>}  $search
     * @return array<string, mixed>
     */
    protected function findLicense(string $identifier, callable $findById, callable $search): array
    {
        if (ctype_digit($identifier)) {
            return $findById((int) $identifier);
        }

        $matches = collect($search(['search' => $identifier, 'perPage' => 10, 'includeTrials' => 1])['licenses'] ?? []);

        $exact = $matches->first(fn ($license) => strcasecmp((string) ($license['key'] ?? ''), $identifier) === 0
            || strcasecmp((string) ($license['domain'] ?? ''), $identifier) === 0);

        if ($exact) {
            return $exact;
        }

        abort_if($matches->isEmpty(), 1, "No license matches [{$identifier}].");

        abort_if($matches->count() > 1, 1, sprintf(
            '[%s] matches %d licenses (IDs %s). Pass an ID or the full key.',
            $identifier,
            $matches->count(),
            $matches->pluck('id')->implode(', '),
        ));

        return $matches->first();
    }
}
