<?php

namespace Chameleon\PhpDiff;

interface StringDistanceCalculatorInterface
{
    public function calcDistance(string $s1, string $s2): int;
}
