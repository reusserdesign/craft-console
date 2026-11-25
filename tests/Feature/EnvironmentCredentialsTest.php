<?php

use App\Support\CraftProject;
use App\Support\Environment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

afterEach(function () {
    foreach (['CRAFT_CONSOLE_ORG', 'CRAFT_CONSOLE_USERNAME', 'CRAFT_CONSOLE_API_KEY'] as $key) {
        unset($_SERVER[$key]);
    }
});

it('uses credentials and the organization from the site .env', function () {
    $this->config->flush();

    $path = fakeCraftProject(files: ['.env' => "CRAFT_CONSOLE_USERNAME=site-user\nCRAFT_CONSOLE_API_KEY=site-key\nCRAFT_CONSOLE_ORG=acme\n"]);

    Http::fake([
        craftnet('org/acme/licenses/cms*') => Http::response(licensePage([cmsLicense(['key' => str_repeat('K', 250)])])),
        craftnet('package/craftcms/cms*') => Http::response(['releases' => []]),
    ]);

    $this->artisan('license', ['path' => $path])->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), craftnet('org/acme/licenses/cms'))
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('site-user:site-key')));
});

it('prefers exported credentials over the site .env', function () {
    $_SERVER['CRAFT_CONSOLE_USERNAME'] = 'shell-user';
    $_SERVER['CRAFT_CONSOLE_API_KEY'] = 'shell-key';

    resolve(Environment::class)->useProject(CraftProject::detect(
        fakeCraftProject(files: ['.env' => "CRAFT_CONSOLE_USERNAME=site-user\nCRAFT_CONSOLE_API_KEY=site-key\n"]),
    ));

    Http::fake([craftnet('licenses/cms*') => Http::response(licensePage([]))]);

    $this->artisan('cms:list')->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('shell-user:shell-key')));
});

it('ignores a username without an API key and falls back to the saved login', function () {
    $_SERVER['CRAFT_CONSOLE_USERNAME'] = 'shell-user';

    Http::fake([craftnet('licenses/cms*') => Http::response(licensePage([]))]);

    $this->artisan('cms:list')->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('austin:secret-key')));
});

it('scopes to CRAFT_CONSOLE_ORG, with personal meaning your own account', function () {
    $this->config->set('organization', 'acme');
    $_SERVER['CRAFT_CONSOLE_ORG'] = 'personal';

    Http::fake([craftnet('licenses/cms*') => Http::response(licensePage([]))]);

    $this->artisan('cms:list')->assertSuccessful();
    $this->artisan('org:current')
        ->expectsPromptsInfo('You are working within your personal account. (CRAFT_CONSOLE_ORG from your shell environment)');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), craftnet('licenses/cms?')));
});
