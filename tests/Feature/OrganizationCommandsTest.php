<?php

use App\Exceptions\CraftnetException;
use Illuminate\Support\Facades\Http;

it('switches to an organization after checking access', function () {
    Http::fake([craftnet('org/acme/licenses/cms*') => Http::response(licensePage([]))]);

    $this->artisan('org:switch', ['organization' => 'acme'])
        ->expectsPromptsInfo('Switched to the acme organization')
        ->assertSuccessful();

    expect($this->config->get('organization'))->toBe('acme')
        ->and($this->config->get('organizations'))->toBe(['acme']);
});

it('does not switch to an organization you cannot access', function () {
    Http::fake([craftnet('org/nope/licenses/cms*') => Http::response([], 404)]);

    expect(fn () => $this->artisan('org:switch', ['organization' => 'nope'])->run())
        ->toThrow(CraftnetException::class, 'Not found.');

    expect($this->config->get('organization'))->toBeNull();
});

it('switches back to the personal account', function () {
    $this->config->set('organization', 'acme');

    $this->artisan('org:switch', ['--personal' => true])
        ->expectsPromptsInfo('Switched to your personal account')
        ->assertSuccessful();

    expect($this->config->get('organization'))->toBeNull();
});

it('shows the current organization', function () {
    $this->artisan('org:current')
        ->expectsPromptsInfo('You are working within your personal account.');

    $this->config->set('organization', 'acme');

    $this->artisan('org:current')
        ->expectsPromptsInfo('You are working within the acme organization.');
});

it('lists organizations that have been used before', function () {
    $this->config->set('organization', 'acme')->set('organizations', ['acme', 'globex']);

    $this->artisan('org:list')
        ->expectsPromptsTable(['Organization', 'Current'], [
            ['(personal)', ''],
            ['acme', '✓'],
            ['globex', ''],
        ]);
});
