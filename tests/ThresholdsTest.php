<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use ledgehq\craftledge\checks\CheckResult;
use ledgehq\craftledge\checks\Thresholds;
use PHPUnit\Framework\TestCase;

final class ThresholdsTest extends TestCase
{
    public function testTiersAreInclusive(): void
    {
        $this->assertSame(CheckResult::STATUS_HEALTHY, Thresholds::status(79, 80, 90));
        $this->assertSame(CheckResult::STATUS_DEGRADED, Thresholds::status(80, 80, 90));
        $this->assertSame(CheckResult::STATUS_DEGRADED, Thresholds::status(89, 80, 90));
        $this->assertSame(CheckResult::STATUS_UNHEALTHY, Thresholds::status(90, 80, 90));
    }

    public function testNullTierIsDisabled(): void
    {
        $this->assertSame(CheckResult::STATUS_HEALTHY, Thresholds::status(85, null, 90));
        $this->assertSame(CheckResult::STATUS_DEGRADED, Thresholds::status(95, 80, null));
        $this->assertSame(CheckResult::STATUS_HEALTHY, Thresholds::status(100, null, null));
    }

    public function testCountSemanticsMatchPreviousHardcodedRules(): void
    {
        $this->assertSame(CheckResult::STATUS_HEALTHY, Thresholds::status(0, 1, 11));
        $this->assertSame(CheckResult::STATUS_DEGRADED, Thresholds::status(1, 1, 11));
        $this->assertSame(CheckResult::STATUS_DEGRADED, Thresholds::status(10, 1, 11));
        $this->assertSame(CheckResult::STATUS_UNHEALTHY, Thresholds::status(11, 1, 11));
    }

    public function testWorst(): void
    {
        $this->assertSame(CheckResult::STATUS_UNHEALTHY, Thresholds::worst(CheckResult::STATUS_DEGRADED, CheckResult::STATUS_UNHEALTHY));
        $this->assertSame(CheckResult::STATUS_DEGRADED, Thresholds::worst(CheckResult::STATUS_DEGRADED, CheckResult::STATUS_HEALTHY));
        $this->assertSame(CheckResult::STATUS_HEALTHY, Thresholds::worst(CheckResult::STATUS_HEALTHY, CheckResult::STATUS_HEALTHY));
    }

    public function testDescribe(): void
    {
        $this->assertSame(['degradedAt' => 80, 'unhealthyAt' => null], Thresholds::describe(80, null));
    }
}
