<?php

namespace Chameleon\PhpDiff;

interface StringDistanceCalculatorInterface
{
    /**
     * @param string $s1
     * @param string $s2
     * @return int
     */
    public function calcDistance($s1, $s2);
}
