<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

interface CheckInterface
{
    public function getName(): string;

    public function run(): ?CheckResult;
}
