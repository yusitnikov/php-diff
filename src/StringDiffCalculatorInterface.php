<?php

namespace Chameleon\PhpDiff;

interface StringDiffCalculatorInterface extends StringDistanceCalculatorInterface
{
    /**
     * @param string $s1
     * @param string $s2
     * @return StringDiffResult
     */
    public function calcDiff($s1, $s2);
}
