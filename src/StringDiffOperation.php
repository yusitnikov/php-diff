<?php

namespace Chameleon\PhpDiff;

class StringDiffOperation
{
    const NONE = '';
    const MATCH = 'MATCH';
    const INSERT = 'INSERT';
    const DELETE = 'DELETE';

    /** @var string */
    public $operation;

    /** @var string */
    public $content;

    public function __construct($operation = self::NONE, $content = '')
    {
        $this->operation = $operation;
        $this->content = $content;
    }
}
