<?php

namespace Chameleon\PhpDiff;

class StringDiffResult
{
    /** @var int */
    public $distance;

    /** @var StringDiffOperation[] */
    public $diff;

    public function __construct($distance, $diff = [])
    {
        $this->distance = $distance;
        $this->diff = $diff;
    }
}
