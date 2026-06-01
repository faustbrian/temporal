<?php declare(strict_types=1);

use Cline\Temporal\Period\Period;
use Cline\Temporal\Time\Time;

it('will expose both imported temporal namespaces', function (): void {
    expect(class_exists(Period::class))->toBeTrue()
        ->and(class_exists(Time::class))->toBeTrue();
});
