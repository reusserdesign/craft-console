<?php

use App\Exceptions\CraftnetException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->config->flush();
});

it('saves credentials after verifying them', function () {
    Http::fake([craftnet('licenses/cms*') => Http::response(licensePage([]))]);

    $this->artisan('login', ['--username' => 'austin', '--key' => 'abc123'])
        ->expectsPromptsInfo('Authenticated successfully as austin')
        ->assertSuccessful();

    expect($this->config->get('username'))->toBe('austin')
        ->and($this->config->get('api_key'))->toBe('abc123');

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('austin:abc123'))
        && $request['perPage'] == 1);
});

it('prompts for missing credentials', function () {
    Http::fake([craftnet('licenses/cms*') => Http::response(licensePage([]))]);

    $this->artisan('login')
        ->expectsQuestion('Craft Console username or email', 'austin')
        ->expectsQuestion('Craft Console API key', 'abc123')
        ->assertSuccessful();

    expect($this->config->get('api_key'))->toBe('abc123');
});

it('does not save rejected credentials', function () {
    Http::fake([craftnet('licenses/cms*') => Http::response(['message' => 'Invalid Credentials'], 400)]);

    expect(fn () => $this->artisan('login', ['--username' => 'austin', '--key' => 'wrong'])->run())
        ->toThrow(CraftnetException::class, 'rejected the username or API key');

    expect($this->config->get('api_key'))->toBeNull();
});
