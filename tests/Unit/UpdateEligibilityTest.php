<?php

use App\Support\UpdateEligibility;
use Carbon\CarbonImmutable;

function releases(): array
{
    return [
        ['version' => '5.12.3', 'time' => '2026-11-24 17:51:49'],
        ['version' => '5.12.0', 'time' => '2026-09-01 10:00:00'],
        ['version' => '5.8.0', 'time' => '2026-03-01 10:00:00'],
        ['version' => '5.7.1', 'time' => '2026-02-01 10:00:00'],
        ['version' => '4.16.9', 'time' => '2026-06-01 10:00:00'],
        ['version' => '6.0.0-beta.1', 'time' => '2026-11-01 10:00:00'],
    ];
}

it('finds the newest release published before an expired license lapsed', function () {
    $eligibility = new UpdateEligibility(
        ['expirable' => true, 'expiresOn' => '2026-04-01T00:00:00+00:00'],
        releases(),
        CarbonImmutable::parse('2026-11-25'),
    );

    expect($eligibility->hasExpired())->toBeTrue()
        ->and($eligibility->latestEligibleVersion())->toBe('5.8.0')
        ->and($eligibility->latestVersion())->toBe('5.12.3')
        ->and($eligibility->lockedVersions()->all())->toBe(['5.12.3', '5.12.0', '4.16.9'])
        ->and($eligibility->exceeds('5.12.0'))->toBeTrue()
        ->and($eligibility->exceeds('5.7.1'))->toBeFalse();
});

it('covers every release while the license is active', function () {
    $eligibility = new UpdateEligibility(
        ['expirable' => true, 'expiresOn' => '2027-04-01T00:00:00+00:00'],
        releases(),
        CarbonImmutable::parse('2026-11-25'),
    );

    expect($eligibility->hasExpired())->toBeFalse()
        ->and($eligibility->latestEligibleVersion())->toBe('5.12.3')
        ->and($eligibility->lockedVersions())->toBeEmpty();
});

it('treats non-expirable licenses as perpetual', function () {
    $eligibility = new UpdateEligibility(['expirable' => false, 'expiresOn' => null], releases());

    expect($eligibility->isPerpetual())->toBeTrue()
        ->and($eligibility->expiresOn())->toBeNull()
        ->and($eligibility->latestEligibleVersion())->toBe('5.12.3');
});

it('ignores pre-releases', function () {
    $eligibility = new UpdateEligibility(['expirable' => false], releases());

    expect($eligibility->latestVersion())->toBe('5.12.3');
});

it('breaks eligibility down by major version from the installed one', function () {
    $eligibility = new UpdateEligibility(
        ['expirable' => true, 'expiresOn' => '2023-01-01T00:00:00+00:00'],
        [
            ['version' => '5.12.3', 'time' => '2026-11-24 10:00:00'],
            ['version' => '4.16.9', 'time' => '2026-06-01 10:00:00'],
            ['version' => '4.3.5', 'time' => '2022-12-20 10:00:00'],
            ['version' => '4.0.0', 'time' => '2022-05-04 10:00:00'],
            ['version' => '3.9.15', 'time' => '2025-03-01 10:00:00'],
            ['version' => '3.7.63', 'time' => '2022-12-15 10:00:00'],
            ['version' => '2.9.0', 'time' => '2019-01-01 10:00:00'],
        ],
        CarbonImmutable::parse('2026-11-25'),
    );

    expect($eligibility->majors('3.7.0')->all())->toBe([
        ['major' => 3, 'latestEligibleVersion' => '3.7.63', 'latestVersion' => '3.9.15', 'versionsNeedingRenewal' => 1],
        ['major' => 4, 'latestEligibleVersion' => '4.3.5', 'latestVersion' => '4.16.9', 'versionsNeedingRenewal' => 1],
        ['major' => 5, 'latestEligibleVersion' => null, 'latestVersion' => '5.12.3', 'versionsNeedingRenewal' => 1],
    ]);

    // A 3.x release from after expiry is off-limits, even though 4.3.5 is covered.
    expect($eligibility->exceeds('3.9.15'))->toBeTrue()
        ->and($eligibility->exceeds('3.7.63'))->toBeFalse()
        ->and($eligibility->exceeds('5.0.0'))->toBeTrue();
});

it('reads the major from versions and constraints', function () {
    expect(UpdateEligibility::majorOf('3.9.15'))->toBe(3)
        ->and(UpdateEligibility::majorOf('^4.2'))->toBe(4)
        ->and(UpdateEligibility::majorOf(null))->toBeNull();
});
