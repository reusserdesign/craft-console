<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Exceptions\ConsoleException;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-25');

    $this->releases = Http::response(['name' => 'craftcms/cms', 'releases' => releases()]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('shows the license for an expired project and the version it can reach', function () {
    $path = fakeCraftProject('5.7.1');

    Http::fake([
        craftnet('licenses/cms*') => Http::response(licensePage([cmsLicense([
            'key' => str_repeat('K', 250),
            'expiresOn' => '2026-04-01T00:00:00+00:00',
            'expired' => true,
            'autoRenew' => false,
            'renewalPrice' => '99.00',
        ])])),
        craftnet('package/craftcms/cms*') => $this->releases,
    ]);

    $this->artisan('license', ['path' => $path])
        ->expectsOutputToContain('2026-04-01 (expired)')
        ->expectsPromptsTable(
            ['Craft', 'Latest eligible', 'Latest release', 'Needs renewal'],
            [['5.x (installed)', '5.8.0', '5.12.3', 'Yes (2 newer releases)']],
        )
        ->expectsPromptsWarning('Updates ended 2026-04-01. This site can update as far as Craft 5.8.0. Renewing ($99.00/year) unlocks Craft 5.12.3.')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => ($request->data()['search'] ?? null) === str_repeat('K', 250));
});

it('warns when the installed version is newer than the license allows', function () {
    $path = fakeCraftProject('5.12.0');

    Http::fake([
        craftnet('licenses/cms*') => Http::response(licensePage([cmsLicense([
            'key' => str_repeat('K', 250),
            'expiresOn' => '2026-04-01T00:00:00+00:00',
            'expired' => true,
        ])])),
        craftnet('package/craftcms/cms*') => $this->releases,
    ]);

    $this->artisan('license', ['path' => $path])
        ->expectsPromptsWarning('Craft 5.12.0 is newer than this license allows (5.8.0). Renew ($99.00/year) or the control panel will flag it.')
        ->assertSuccessful();
});

it('reports an active license', function () {
    $path = fakeCraftProject();

    Http::fake([
        craftnet('licenses/cms*') => Http::response(licensePage([cmsLicense(['key' => str_repeat('K', 250)])])),
        craftnet('package/craftcms/cms*') => $this->releases,
    ]);

    $this->artisan('license', ['path' => $path])
        ->expectsPromptsInfo('Updates are active until 2027-03-01, so every release so far (up to Craft 5.12.3) is covered. It renews automatically.')
        ->assertSuccessful();
});

it('searches the organizations you have used when the license is not personal', function () {
    $this->config->set('organizations', ['acme', 'globex']);
    $path = fakeCraftProject();

    Http::fake([
        craftnet('org/acme/licenses/cms*') => Http::response([], 403),
        craftnet('org/globex/licenses/cms*') => Http::response(licensePage([cmsLicense(['key' => str_repeat('K', 250), 'org' => 'globex', 'email' => null])])),
        craftnet('licenses/cms*') => Http::response(licensePage([])),
        craftnet('package/craftcms/cms*') => $this->releases,
    ]);

    $this->artisan('license', ['path' => $path, '--json' => true])
        ->expectsOutputToContain('"org": "globex"')
        ->assertSuccessful();
});

it('explains when nobody you can see owns the license', function () {
    $path = fakeCraftProject();

    Http::fake([craftnet('licenses/cms*') => Http::response(licensePage([]))]);

    $this->artisan('license', ['path' => $path])
        ->expectsPromptsWarning("The Craft license isn't owned by your personal account. Ask whoever owns it to add you to their organization, then run 'org:switch'.")
        ->assertFailed();
});

it('prints only the key without calling the API', function () {
    Http::fake();

    $this->artisan('license', ['path' => fakeCraftProject(), '--key' => true])
        ->expectsOutput(str_repeat('K', 250))
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('fails outside a Craft project', function () {
    expect(fn () => $this->artisan('license', ['path' => sys_get_temp_dir()])->run())
        ->toThrow(ConsoleException::class, 'No Craft project found.');
});

it('reports plugin licenses alongside the Craft license', function () {
    $path = fakeCraftProject('5.7.1', [
        '.env' => "SEOMATIC_LICENSE=SEOKEY123\n",
        'composer.lock' => json_encode(['packages' => [
            ['name' => 'craftcms/cms', 'version' => '5.7.1'],
            ['name' => 'nystudio107/craft-seomatic', 'version' => 'v5.1.0', 'type' => 'craft-plugin', 'extra' => ['handle' => 'seomatic']],
            ['name' => 'verbb/formie', 'version' => '3.0.0', 'type' => 'craft-plugin', 'extra' => ['handle' => 'formie']],
            ['name' => 'acme/private-plugin', 'version' => '1.0.0', 'type' => 'craft-plugin', 'extra' => ['handle' => 'private-plugin']],
        ]]),
        'config/project/project.yaml' => <<<'YAML'
            system:
              edition: pro
            plugins:
              seomatic:
                edition: pro
                enabled: true
                licenseKey: $SEOMATIC_LICENSE
                settings:
                  pluginName: SEO
              formie:
                edition: standard
                enabled: true
                licenseKey: FORMIEKEY
              private-plugin:
                edition: standard
                enabled: false
            YAML,
    ]);

    Http::fake([
        craftnet('licenses/cms*') => Http::response(licensePage([cmsLicense([
            'key' => str_repeat('K', 250),
            'expiresOn' => '2026-04-01T00:00:00+00:00',
            'expired' => true,
            'renewalPrice' => '99.00',
        ])])),
        craftnet('licenses/plugins?*SEOKEY123*') => Http::response(licensePage([pluginLicense([
            'pluginHandle' => 'seomatic',
            'edition' => 'standard',
            'key' => 'SEOKEY123',
            'cmsLicenseId' => 1,
            'expiresOn' => '2026-12-31T00:00:00+00:00',
            'renewalPrice' => '39.00',
        ])])),
        craftnet('licenses/plugins*') => Http::response(licensePage([])),
        craftnet('package/craftcms/cms*') => $this->releases,
        craftnet('package/nystudio107/craft-seomatic*') => Http::response(['releases' => [
            ['version' => '5.1.24', 'time' => '2026-11-24 05:54:35'],
            ['version' => '5.1.0', 'time' => '2026-01-01 00:00:00'],
        ]]),
        craftnet('package/verbb/formie*') => Http::response(['releases' => [['version' => '3.1.0', 'time' => '2026-10-01 00:00:00']]]),
        craftnet('package/acme/private-plugin*') => Http::response(['message' => 'Invalid package name'], 404),
    ]);

    $this->artisan('license', ['path' => $path])
        ->expectsPromptsTable(
            ['Plugin', 'Installed', 'Edition', 'Updates until', 'Latest eligible', 'Latest release', 'Renewal'],
            [
                ['formie', '3.0.0', 'standard', 'Not in your accounts', '—', '3.1.0', '—'],
                ['private-plugin (disabled)', '1.0.0', 'standard', 'No license key', '—', 'Not on Packagist', '—'],
                ['seomatic', '5.1.0', 'standard', '2026-12-31', '5.1.24', '5.1.24', '$39.00/year'],
            ],
        )
        ->expectsPromptsWarning('seomatic runs the pro edition, but its license is for standard.')
        ->expectsPromptsWarning('Not found in your accounts: formie. They may belong to a client or another organization.')
        ->expectsPromptsInfo('Renewing all 2 licenses costs $138.00/year. 1 has lapsed (Craft): $99.00 to renew.')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'licenses/plugins')
        && $request['search'] === 'SEOKEY123'
        && $request['pluginHandle'] === 'seomatic');
});

it('skips plugins with --no-plugins', function () {
    $path = fakeCraftProject('5.7.1', [
        'config/project/project.yaml' => "system:\n  edition: pro\nplugins:\n  formie:\n    licenseKey: FORMIEKEY\n",
    ]);

    Http::fake([
        craftnet('licenses/cms*') => Http::response(licensePage([cmsLicense(['key' => str_repeat('K', 250)])])),
        craftnet('package/craftcms/cms*') => $this->releases,
    ]);

    $this->artisan('license', ['path' => $path, '--no-plugins' => true])->assertSuccessful();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'licenses/plugins'));
});

it('words the renewal summary for a single license', function () {
    $path = fakeCraftProject();

    Http::fake([
        craftnet('licenses/cms*') => Http::response(licensePage([cmsLicense(['key' => str_repeat('K', 250)])])),
        craftnet('package/craftcms/cms*') => $this->releases,
    ]);

    $this->artisan('license', ['path' => $path])
        ->expectsPromptsInfo('Renewing this license costs $99.00/year.')
        ->assertSuccessful();
});
