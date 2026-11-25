<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Exceptions\ConsoleException;

it('lists Craft licenses', function () {
    Http::fake([craftnet('licenses/cms*') => Http::response(licensePage([
        cmsLicense(),
        cmsLicense(['id' => 2, 'domain' => 'acme.test', 'email' => null, 'org' => 'acme', 'expirable' => false, 'autoRenew' => false]),
    ]))]);

    $this->artisan('cms:list')
        ->expectsPromptsTable(
            ['ID', 'Edition', 'Domain', 'Owner', 'Expires', 'Auto-renew', 'Last version'],
            [
                ['1', 'pro', 'example.com', 'client@example.com', '2027-03-01', 'Yes', '5.12.3'],
                ['2', 'pro', 'acme.test', 'org:acme', 'Never', 'No', '5.12.3'],
            ],
        )
        ->expectsPromptsInfo('2 licenses')
        ->assertSuccessful();
});

it('passes filters to the API', function () {
    Http::fake([craftnet('licenses/cms*') => Http::response(licensePage([]))]);

    $this->artisan('cms:list', ['--search' => 'acme', '--edition' => 'pro,team', '--sort' => 'expiresOn', '--asc' => true, '--per-page' => 25])
        ->expectsPromptsWarning('No Craft licenses found for your personal account.');

    Http::assertSent(fn (Request $request) => $request['search'] === 'acme'
        && $request['edition'] === 'pro,team'
        && $request['orderBy'] === 'expiresOn'
        && $request['ascending'] == 1
        && $request['perPage'] == 25
        && $request['page'] == 1);
});

it('scopes to the current organization', function () {
    $this->config->set('organization', 'acme');

    Http::fake([craftnet('org/acme/licenses/cms*') => Http::response(licensePage([]))]);

    $this->artisan('cms:list')->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), craftnet('org/acme/licenses/cms')));
});

it('lets --personal override the current organization', function () {
    $this->config->set('organization', 'acme');

    Http::fake([craftnet('licenses/cms*') => Http::response(licensePage([]))]);

    $this->artisan('cms:list', ['--personal' => true])->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), craftnet('licenses/cms?')));
});

it('fetches every page with --all', function () {
    Http::fake([
        craftnet('licenses/cms?*page=1*') => Http::response(licensePage([cmsLicense()], total: 2, totalPages: 2)),
        craftnet('licenses/cms?*page=2*') => Http::response(licensePage([cmsLicense(['id' => 2])], total: 2, totalPages: 2)),
    ]);

    $this->artisan('cms:list', ['--all' => true, '--json' => true])
        ->expectsOutputToContain('"total": 2')
        ->assertSuccessful();

    Http::assertSentCount(2);
});

it('filters licenses expiring soon', function () {
    CarbonImmutable::setTestNow('2027-02-15');

    Http::fake([craftnet('licenses/cms*') => Http::response(licensePage([
        cmsLicense(['id' => 1, 'expiresOn' => '2027-03-01T00:00:00+00:00']),
        cmsLicense(['id' => 2, 'expiresOn' => '2028-03-01T00:00:00+00:00']),
        cmsLicense(['id' => 3, 'expirable' => false]),
    ]))]);

    $this->artisan('cms:list', ['--expiring' => 30])
        ->expectsPromptsTable(
            ['ID', 'Edition', 'Domain', 'Owner', 'Expires', 'Auto-renew', 'Last version'],
            [['1', 'pro', 'example.com', 'client@example.com', '2027-03-01', 'Yes', '5.12.3']],
        )
        ->expectsPromptsInfo('1 license');

    CarbonImmutable::setTestNow();
});

it('shows a Craft license by ID', function () {
    Http::fake([craftnet('licenses/cms/1') => Http::response(['license' => cmsLicense()])]);

    $this->artisan('cms:show', ['license' => '1'])
        ->expectsOutputToContain(str_repeat('k', 250))
        ->assertSuccessful();
});

it('finds a Craft license by domain and outputs only its key', function () {
    Http::fake([craftnet('licenses/cms?*') => Http::response(licensePage([
        cmsLicense(['id' => 1, 'domain' => 'example.com.au', 'key' => 'other-key']),
        cmsLicense(['id' => 2, 'domain' => 'example.com', 'key' => 'the-key']),
    ]))]);

    $this->artisan('cms:show', ['license' => 'example.com', '--key' => true])
        ->expectsOutput('the-key')
        ->assertSuccessful();
});

it('fails when a search term matches several licenses', function () {
    Http::fake([craftnet('licenses/cms?*') => Http::response(licensePage([
        cmsLicense(['id' => 1, 'domain' => 'a.example.com']),
        cmsLicense(['id' => 2, 'domain' => 'b.example.com']),
    ]))]);

    expect(fn () => $this->artisan('cms:show', ['license' => 'example'])->run())
        ->toThrow(ConsoleException::class, '[example] matches 2 licenses (IDs 1, 2).');
});

it('requires credentials', function () {
    $this->config->flush();

    Http::fake();

    expect(fn () => $this->artisan('cms:list')->run())
        ->toThrow(ConsoleException::class, "You are not logged in. Run 'craft-console login' first.");

    Http::assertNothingSent();
});

it('prints nothing but JSON with --json', function () {
    Http::fake([craftnet('licenses/cms*') => Http::response(licensePage([cmsLicense()]))]);

    Artisan::call('cms:list', ['--json' => true]);

    expect(json_decode(Artisan::output(), true))->toHaveKey('total', 1);
});
