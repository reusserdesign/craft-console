<?php

use App\Support\Spinner;
use Laravel\Prompts\Themes\Default\SpinnerRenderer;

it('renders with the base spinner renderer', function () {
    $renderer = (fn () => $this->getRenderer())->call(new Spinner('Loading'));

    expect($renderer)->toBeInstanceOf(SpinnerRenderer::class);
});
