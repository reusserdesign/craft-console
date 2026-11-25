<?php

use App\Support\CraftProject;
use App\Support\Environment;

afterEach(function () {
    unset($_SERVER['CRAFT_CONSOLE_ORG'], $_ENV['CRAFT_CONSOLE_ORG']);
    putenv('CRAFT_CONSOLE_ORG');
});

it('reads variables from the project .env file', function () {
    $path = fakeCraftProject(files: ['.env' => "CRAFT_CONSOLE_ORG=acme\n"]);

    $environment = new Environment($path.'/config');

    expect($environment->get('CRAFT_CONSOLE_ORG'))->toBe('acme')
        ->and($environment->source('CRAFT_CONSOLE_ORG'))->toBe(realpath($path).'/.env');
});

it('prefers variables exported in the shell', function () {
    $path = fakeCraftProject(files: ['.env' => "CRAFT_CONSOLE_ORG=acme\n"]);
    $_SERVER['CRAFT_CONSOLE_ORG'] = 'globex';

    $environment = new Environment($path);

    expect($environment->get('CRAFT_CONSOLE_ORG'))->toBe('globex')
        ->and($environment->source('CRAFT_CONSOLE_ORG'))->toBe('your shell environment');
});

it('can read a project other than the working directory', function () {
    $path = fakeCraftProject(files: ['.env' => "CRAFT_CONSOLE_ORG=acme\n"]);

    $environment = (new Environment(sys_get_temp_dir()))->useProject(CraftProject::detect($path));

    expect($environment->get('CRAFT_CONSOLE_ORG'))->toBe('acme');
});

it('returns null outside a Craft project', function () {
    expect((new Environment(sys_get_temp_dir()))->get('CRAFT_CONSOLE_ORG'))->toBeNull();
});
