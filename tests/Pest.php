<?php

use App\Repositories\ConfigRepository;
use Illuminate\Filesystem\Filesystem;
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
        foreach ($this->tempPaths ?? [] as $temp) {
            (new Filesystem)->deleteDirectory($temp);
        }

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

/**
 * Create a throwaway Craft project on disk and return its path.
 *
 * @param  array<string, string>  $files  Extra files, keyed by relative path
 */
function fakeCraftProject(string $version = '5.7.1', array $files = []): string
{
    $path = sys_get_temp_dir().'/craft-console-project-'.bin2hex(random_bytes(4));

    $files = array_merge([
        'composer.json' => json_encode(['require' => ['craftcms/cms' => '^5.0']]),
        'composer.lock' => json_encode(['packages' => [['name' => 'craftcms/cms', 'version' => $version]]]),
        'config/project/project.yaml' => "dateModified: 1\nsystem:\n  edition: pro\n  live: true\nplugins:\n  seomatic:\n    edition: standard\n",
        'config/license.key' => implode("\n", str_split(str_repeat('K', 250), 50))."\n",
    ], $files);

    foreach ($files as $file => $contents) {
        if (! is_dir(dirname("{$path}/{$file}"))) {
            mkdir(dirname("{$path}/{$file}"), 0755, true);
        }

        file_put_contents("{$path}/{$file}", $contents);
    }

    test()->deleteAfterTest($path);

    return $path;
}
