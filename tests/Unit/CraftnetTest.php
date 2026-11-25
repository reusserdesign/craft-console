<?php

use App\Clients\Craftnet;
use App\Exceptions\CraftnetException;
use Illuminate\Support\Facades\Http;

function craftnetError(callable $callback): string
{
    try {
        $callback();
    } catch (CraftnetException $e) {
        return $e->getMessage();
    }

    throw new RuntimeException('Expected the request to fail.');
}

it('explains rejected credentials', function (int $status, array $body) {
    Http::fake([craftnet('*') => Http::response($body, $status)]);

    $message = craftnetError(fn () => (new Craftnet('austin', 'bad'))->cmsLicenses());

    expect($message)->toContain('rejected the username or API key');
})->with([
    'craftnet 400' => [400, ['message' => 'Invalid Credentials']],
    'plain 401' => [401, []],
]);

it('includes the API message for other errors', function () {
    Http::fake([craftnet('*') => Http::response(['message' => 'Invalid edition.'], 400)]);

    $message = craftnetError(fn () => (new Craftnet('austin', 'key'))->createDeveloperLicense(['plugin' => 'x']));

    expect($message)->toBe('The Craftnet API responded with HTTP 400. Invalid edition.');
});

it('explains missing resources', function () {
    Http::fake([craftnet('*') => Http::response([], 404)]);

    expect(craftnetError(fn () => (new Craftnet('austin', 'key'))->cmsLicense('acme', 5)))
        ->toStartWith('Not found.');

    Http::assertSent(fn ($request) => $request->url() === craftnet('org/acme/licenses/cms/5'));
});

it('keeps the HTTP status on the exception', function () {
    Http::fake([craftnet('*') => Http::response([], 403)]);

    try {
        (new Craftnet('austin', 'key'))->cmsLicenses('acme');
    } catch (CraftnetException $e) {
        expect($e->status)->toBe(403)->and($e->isNotAccessible())->toBeTrue();

        return;
    }

    throw new RuntimeException('Expected the request to fail.');
});
