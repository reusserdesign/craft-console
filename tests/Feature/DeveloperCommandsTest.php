<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Exceptions\ConsoleException;

it('lists licenses issued for your plugins', function () {
    Http::fake([craftnet('plugin-licenses*') => Http::response(licensePage([pluginLicense()]))]);

    $this->artisan('dev:list', ['--plugin' => 'seomatic'])
        ->expectsPromptsTable(
            ['ID', 'Plugin', 'Edition', 'Key', 'Owner', 'Expires', 'Created'],
            [['10', 'seomatic', 'standard', 'ABCDEFGHIJKLMNOPQRSTUVWX', 'client@example.com', '2027-01-15', '2026-01-15']],
        )
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['pluginHandle'] === 'seomatic');
});

it('shows a license by key', function () {
    Http::fake([craftnet('plugin-licenses/ABCD') => Http::response(['license' => pluginLicense()])]);

    $this->artisan('dev:show', ['license' => 'ABCD', '--json' => true])
        ->expectsOutputToContain('"key": "ABCDEFGHIJKLMNOPQRSTUVWX"')
        ->assertSuccessful();
});

it('creates licenses', function () {
    Http::fake([craftnet('plugin-licenses') => Http::response(['license' => pluginLicense()])]);

    $this->artisan('dev:create', [
        '--plugin' => 'seomatic',
        '--edition' => 'pro',
        '--email' => 'client@example.com',
        '--expirable' => true,
        '--notes' => 'Thanks!',
        '--count' => 2,
    ])
        ->expectsPromptsInfo('2 licenses created')
        ->assertSuccessful();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->data() === [
        'plugin' => 'seomatic',
        'edition' => 'pro',
        'email' => 'client@example.com',
        'expirable' => true,
        'notes' => 'Thanks!',
    ]);
});

it('creates a license for an organization', function () {
    Http::fake([craftnet('plugin-licenses') => Http::response(['license' => pluginLicense(['email' => null, 'org' => 'acme'])])]);

    $this->artisan('dev:create', ['--plugin' => 'seomatic', '--edition' => 'standard', '--org' => 'acme'])
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['org'] === 'acme' && ! isset($request['email']));
});

it('rejects both an email and an organization', function () {
    Http::fake();

    expect(fn () => $this->artisan('dev:create', ['--plugin' => 'seomatic', '--edition' => 'standard', '--org' => 'acme', '--email' => 'a@b.co'])->run())
        ->toThrow(ConsoleException::class, 'Pass either --email or --org, not both.');

    Http::assertNothingSent();
});

it('updates only the given attributes', function () {
    Http::fake([craftnet('plugin-licenses/10') => Http::response(['license' => pluginLicense()])]);

    $this->artisan('dev:update', ['license' => '10', '--edition' => 'pro', '--non-expirable' => true])
        ->expectsPromptsInfo('License updated')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->data() === ['edition' => 'pro', 'expirable' => false]);
});

it('refuses to update without any changes', function () {
    Http::fake();

    expect(fn () => $this->artisan('dev:update', ['license' => '10'])->run())
        ->toThrow(ConsoleException::class, 'Nothing to update.');

    Http::assertNothingSent();
});
