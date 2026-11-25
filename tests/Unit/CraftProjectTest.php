<?php

use App\Support\CraftProject;

it('detects a Craft project from a subdirectory', function () {
    $path = fakeCraftProject();

    expect(CraftProject::detect($path.'/config/project')?->path())->toBe(realpath($path));
});

it('ignores directories that are not Craft projects', function () {
    expect(CraftProject::detect(sys_get_temp_dir()))->toBeNull();
});

it('reads the installed version and edition', function () {
    $project = CraftProject::detect(fakeCraftProject('5.12.3'));

    expect($project->craftVersion())->toBe('5.12.3')
        ->and($project->hasLockedVersion())->toBeTrue()
        ->and($project->edition())->toBe('pro');
});

it('falls back to the composer.json constraint without a lock file', function () {
    $path = fakeCraftProject();
    unlink($path.'/composer.lock');

    $project = CraftProject::detect($path);

    expect($project->craftVersion())->toBe('^5.0')
        ->and($project->hasLockedVersion())->toBeFalse();
});

it('reads license.key without its line breaks', function () {
    $license = CraftProject::detect(fakeCraftProject())->licenseKey();

    expect($license)->toBe(['key' => str_repeat('K', 250), 'source' => 'config/license.key']);
});

it('prefers CRAFT_LICENSE_KEY from the .env file', function () {
    $path = fakeCraftProject(files: ['.env' => "CRAFT_LICENSE_KEY=\"ENVKEY\"\n"]);

    expect(CraftProject::detect($path)->licenseKey())->toBe(['key' => 'ENVKEY', 'source' => 'CRAFT_LICENSE_KEY']);
});

it('follows CRAFT_LICENSE_KEY_PATH', function () {
    $path = fakeCraftProject(files: [
        '.env' => "CRAFT_LICENSE_KEY_PATH=storage/license.key\n",
        'storage/license.key' => "CUSTOM\n",
    ]);

    expect(CraftProject::detect($path)->licenseKey())->toBe(['key' => 'CUSTOM', 'source' => 'storage/license.key']);
});

it('returns null when there is no license key', function () {
    $path = fakeCraftProject();
    unlink($path.'/config/license.key');

    expect(CraftProject::detect($path)->licenseKey())->toBeNull();
});
