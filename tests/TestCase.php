<?php

namespace Tests;

use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Temporary directories to delete after the test.
     *
     * @var array<int, string>
     */
    public array $tempPaths = [];

    /**
     * Delete the given directory once the test finishes.
     */
    public function deleteAfterTest(string $path): void
    {
        $this->tempPaths[] = $path;
    }
}
