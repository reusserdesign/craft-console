<?php

use App\Repositories\ConfigRepository;

it('stores values in a file only the current user can read', function () {
    $config = resolve(ConfigRepository::class)->set('organizations', ['acme']);

    expect($config->get('organizations'))->toBe(['acme'])
        ->and(fileperms($config->path()) & 0777)->toBe(0600);

    $config->forget('organizations');

    expect($config->get('organizations', 'default'))->toBe('default');
});
