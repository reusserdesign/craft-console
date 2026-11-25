<?php

use App\Repositories\ConfigRepository;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every test gets a fresh config file in tests/.craft-console that is
| logged in as a fake user, and stray HTTP requests are blocked so no
| test can reach the real Craftnet API.
|
*/

uses(TestCase::class)
    ->beforeEach(function () {
        Http::preventStrayRequests();

        $this->config = resolve(ConfigRepository::class)
            ->flush()
            ->set('username', 'austin')
            ->set('api_key', 'secret-key');
    })
    ->afterEach(function () {
        $path = resolve(ConfigRepository::class)->path();

        if (file_exists($path)) {
            unlink($path);
        }

        if (is_dir(dirname($path))) {
            rmdir(dirname($path));
        }
    })
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Build a Craftnet API URL pattern for Http::fake().
 */
function craftnet(string $path): string
{
    return 'https://api.craftcms.com/v1/'.$path;
}

/**
 * Build a paginated license list response body.
 *
 * @param  array<int, array<string, mixed>>  $licenses
 * @return array<string, mixed>
 */
function licensePage(array $licenses, ?int $total = null, int $totalPages = 1): array
{
    return [
        'total' => $total ?? count($licenses),
        'totalPages' => $totalPages,
        'licenses' => $licenses,
    ];
}

/**
 * Build a Craft license as the API returns it.
 *
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function cmsLicense(array $attributes = []): array
{
    return array_merge([
        'id' => 1,
        'editionHandle' => 'pro',
        'domain' => 'example.com',
        'email' => 'client@example.com',
        'key' => str_repeat('k', 250),
        'notes' => null,
        'expirable' => true,
        'expired' => false,
        'autoRenew' => true,
        'renewalPrice' => '99.00',
        'lastEdition' => 'pro',
        'lastVersion' => '5.12.3',
        'lastAllowedVersion' => '5.12.3',
        'lastActivityOn' => '2026-11-01T00:00:00+00:00',
        'lastStatus' => 'valid',
        'lastRenewedOn' => null,
        'expiresOn' => '2027-03-01T00:00:00+00:00',
        'dateCreated' => '2025-03-01T00:00:00+00:00',
        'org' => null,
    ], $attributes);
}

/**
 * Build a plugin license as the API returns it.
 *
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function pluginLicense(array $attributes = []): array
{
    return array_merge([
        'id' => 10,
        'pluginHandle' => 'seomatic',
        'edition' => 'standard',
        'trial' => false,
        'email' => 'client@example.com',
        'key' => 'ABCDEFGHIJKLMNOPQRSTUVWX',
        'cmsLicenseId' => 1,
        'notes' => null,
        'expirable' => true,
        'expired' => false,
        'autoRenew' => false,
        'renewalPrice' => '39.00',
        'lastVersion' => null,
        'lastAllowedVersion' => null,
        'lastActivityOn' => null,
        'lastStatus' => null,
        'lastRenewedOn' => null,
        'expiresOn' => '2027-01-15T00:00:00+00:00',
        'dateCreated' => '2026-01-15T00:00:00+00:00',
        'org' => null,
    ], $attributes);
}
