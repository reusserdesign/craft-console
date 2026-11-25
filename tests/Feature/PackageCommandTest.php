<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('shows a package without authenticating', function () {
    $this->config->flush();

    Http::fake([craftnet('package/craftcms/cms*') => Http::response([
        'name' => 'craftcms/cms',
        'type' => 'library',
        'repository' => 'https://github.com/craftcms/cms',
        'managed' => true,
        'abandoned' => false,
        'replacementPackage' => null,
        'latestRelease' => ['version' => '5.12.3', 'time' => '2026-11-24 10:00:00'],
    ])]);

    $this->artisan('package', ['name' => 'craftcms/cms'])
        ->expectsPromptsTable(['Attribute', 'Value'], [
            ['Name', 'craftcms/cms'],
            ['Type', 'library'],
            ['Repository', 'https://github.com/craftcms/cms'],
            ['Managed', 'Yes'],
            ['Abandoned', 'No'],
            ['Replacement', '—'],
            ['Latest release', '5.12.3'],
            ['Released', '2026-11-24'],
        ])
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => ! $request->hasHeader('Authorization'));
});

it('requests releases for a constraint', function () {
    Http::fake([craftnet('package/*') => Http::response(['name' => 'craftcms/cms', 'releases' => []])]);

    $this->artisan('package', ['name' => 'craftcms/cms', '--constraint' => '^5.0', '--json' => true])->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['include'] === 'releases' && $request['constraint'] === '^5.0');
});
