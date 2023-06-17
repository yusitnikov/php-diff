<?php

namespace Chameleon\PhpDiff;

interface StringDiffCalculatorInterface extends StringDistanceCalculatorInterface
{
    public function calcDiff(string $s1, string $s2): StringDiffResult;
}
