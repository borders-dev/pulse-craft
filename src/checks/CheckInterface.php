<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

interface CheckInterface
{
    public function getName(): string;

    public function run(): ?CheckResult;
}
