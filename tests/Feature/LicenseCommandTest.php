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
        ->expectsPromptsWarning("This license isn't owned by your personal account. Ask whoever owns it to add you to their organization, then run 'org:switch'.")
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
