<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('lists plugin licenses', function () {
    Http::fake([craftnet('licenses/plugins*') => Http::response(licensePage([
        pluginLicense(),
        pluginLicense(['id' => 11, 'pluginHandle' => 'formie', 'trial' => true, 'key' => 'SHORT']),
    ]))]);

    $this->artisan('plugin:list', ['--plugin' => 'seomatic', '--trials' => true])
        ->expectsPromptsTable(
            ['ID', 'Plugin', 'Edition', 'Key', 'Owner', 'Craft license', 'Expires', 'Auto-renew'],
            [
                ['10', 'seomatic', 'standard', 'ABCDEFGHIJKL…', 'client@example.com', '1', '2027-01-15', 'No'],
                ['11', 'formie (trial)', 'standard', 'SHORT', 'client@example.com', '1', '2027-01-15', 'No'],
            ],
        )
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['pluginHandle'] === 'seomatic' && $request['includeTrials'] == 1);
});

it('shows a plugin license as JSON', function () {
    Http::fake([craftnet('org/acme/licenses/plugins/10') => Http::response(['license' => pluginLicense()])]);

    $this->artisan('plugin:show', ['license' => '10', '--org' => 'acme', '--json' => true])
        ->expectsOutputToContain('"pluginHandle": "seomatic"')
        ->assertSuccessful();
});
