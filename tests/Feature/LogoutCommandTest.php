<?php

it('removes saved credentials', function () {
    $this->artisan('logout')
        ->expectsPromptsInfo('Logged out successfully')
        ->assertSuccessful();

    expect($this->config->all())->toBe([]);
});
