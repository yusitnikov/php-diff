<?php

namespace Chameleon\PhpDiff;

// https://en.wikipedia.org/wiki/Levenshtein_distance
class LevenshteinDiffCalculator implements StringDiffCalculatorInterface
{
    const SPLIT_LINES_REGEX = '\n\r?';
    const SPLIT_WORDS_REGEX = '\W';
    const SPLIT_CHARS_REGEX = '';

    /** @var int[][] */
    private $matrix;

    /** @var OperationCostCalculator */
    private $operationCostCalculator;

    /** @var string */
    private $separatorRegex;

    /** @var StringDiffCalculatorInterface */
    private $itemDiffCalculator;

    private $startMatch;
    private $endMatch;

    public function __construct(
        $separatorRegex = self::SPLIT_CHARS_REGEX,
        OperationCostCalculator $operationCostCalculator = null,
        StringDiffCalculatorInterface $itemDiffCalculator = null
    )
    {
        $this->separatorRegex = $separatorRegex;
        $this->operationCostCalculator = $operationCostCalculator ?? new OperationCostCalculator();
        $this->itemDiffCalculator = $itemDiffCalculator;
    }

    /**
     * @param string[]|string $s
     * @return string[]
     */
    private function split($s)
    {
        if (is_array($s)) {
            return $s;
        } else {
            return preg_split('/(' . $this->separatorRegex . ')/u', $s, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        }
    }

    /**
     * @param string $s
     * @return bool
     */
    private function isSeparator($s)
    {
        return $this->separatorRegex !== '' && preg_match('/^(?:' . $this->separatorRegex . ')+$/u', $s);
    }

    /**
     * @param string $s1
     * @param string $s2
     * @param bool $keepMatrix
     * @return int
     */
    private function _calcDistance($s1, $s2, $keepMatrix)
    {
        // normalize the input
        $s1 = $this->split($s1);
        $s2 = $this->split($s2);

        // reset the matrix
        $m =& $this->matrix;
        $m = [];

        // temporary variables
        $n1 = count($s1);
        $n2 = count($s2);

        // check for perfect equality
        if ($s1 === $s2) {
            $this->startMatch = $n1;
            $this->endMatch = 0;
            return 0;
        }

        // check for trailing equality
        for ($this->startMatch = 0; $this->startMatch < $n1 && $this->startMatch < $n2 && $s1[$this->startMatch] === $s2[$this->startMatch]; $this->startMatch++) { }
        if ($this->startMatch) {
            $s1 = array_slice($s1, $this->startMatch);
            $n1 -= $this->startMatch;
            $s2 = array_slice($s2, $this->startMatch);
            $n2 -= $this->startMatch;
        }
        for ($this->endMatch = 0; $this->endMatch < $n1 && $this->endMatch < $n2 && $s1[$n1 - 1 - $this->endMatch] === $s2[$n2 - 1 - $this->endMatch]; $this->endMatch++) { }
        if ($this->endMatch) {
            $s1 = array_slice($s1, 0, -$this->endMatch);
            $n1 -= $this->endMatch;
            $s2 = array_slice($s2, 0, -$this->endMatch);
            $n2 -= $this->endMatch;
        }

        // init the first row
        $m[0][0] = 0;
        for ($i2 = 0; $i2 < $n2; $i2++) {
            $m[0][$i2 + 1] = $m[0][$i2] + $this->operationCostCalculator->getInsertCost($s2[$i2]);
        }

        // calc the matrix row by row
        for ($i1 = 0; $i1 < $n1; $i1++) {
            $c1 = $s1[$i1];
            $deleteCost = $this->operationCostCalculator->getDeleteCost($c1);
            $m[$i1 + 1][0] = $m[$i1][0] + $deleteCost;

            for ($i2 = 0; $i2 < $n2; $i2++) {
                $c2 = $s2[$i2];
                $insertCost = $this->operationCostCalculator->getInsertCost($c2);
                $replaceCost = $this->operationCostCalculator->getReplaceCost($c1, $c2);
                $m[$i1 + 1][$i2 + 1] = min(
                    $m[$i1][$i2 + 1] + $deleteCost,
                    $m[$i1 + 1][$i2] + $insertCost,
                    $m[$i1][$i2] + $replaceCost
                );
            }

            if (!$keepMatrix) {
                unset($m[$i1]);
            }
        }

        $distance = $m[$n1][$n2];
        if (!$keepMatrix) {
            $m = [];
        }
        return $distance;
    }

    /**
     * @param string $s1
     * @param string $s2
     * @return int
     */
    public function calcDistance($s1, $s2)
    {
        return $this->_calcDistance($s1, $s2, false);
    }

    /**
     * @param string $s1
     * @param string $s2
     * @return StringDiffResult
     */
    public function calcDiff($s1, $s2)
    {
        // normalize the input
        $s1 = $this->split($s1);
        $s2 = $this->split($s2);

        // calc the distance and the matrix
        $distance = $this->_calcDistance($s1, $s2, true);

        // temporary variables
        $m = $this->matrix;
        $n1 = count($s1);
        $n2 = count($s2);

        // check if calcDistance identified perfect or partial equality
        $startDiff = [];
        if ($this->startMatch) {
            $startDiff[] = new StringDiffOperation(StringDiffOperation::MATCH, implode('', array_slice($s1, 0, $this->startMatch)));
            $s1 = array_slice($s1, $this->startMatch);
            $n1 -= $this->startMatch;
            $s2 = array_slice($s2, $this->startMatch);
            $n2 -= $this->startMatch;
        }
        $endDiff = [];
        if ($this->endMatch) {
            $endDiff[] = new StringDiffOperation(StringDiffOperation::MATCH, implode('', array_slice($s1, -$this->endMatch)));
            $s1 = array_slice($s1, 0, -$this->endMatch);
            $n1 -= $this->endMatch;
            $s2 = array_slice($s2, 0, -$this->endMatch);
            $n2 -= $this->endMatch;
        }

        $diff = [];
        $i1 = $n1;
        $i2 = $n2;
        while ($i1 || $i2) {
            $c1 = $i1 ? $s1[$i1 - 1] : null;
            $c2 = $i2 ? $s2[$i2 - 1] : null;
            $insertCost = $i2 ? $m[$i1][$i2 - 1] + $this->operationCostCalculator->getInsertCost($c2) : PHP_INT_MAX;
            $deleteCost = $i1 ? $m[$i1 - 1][$i2] + $this->operationCostCalculator->getDeleteCost($c1) : PHP_INT_MAX;
            $replaceCost = ($i1 && $i2) ? $m[$i1 - 1][$i2 - 1] + $this->operationCostCalculator->getReplaceCost($c1, $c2) : PHP_INT_MAX;
            // check who is the minimal
            // the order is important!
            switch (min($insertCost, $deleteCost, $replaceCost)) {
                case $insertCost:
                    array_unshift($diff, new StringDiffOperation(StringDiffOperation::INSERT, $c2));
                    --$i2;
                    break;
                case $deleteCost:
                    array_unshift($diff, new StringDiffOperation(StringDiffOperation::DELETE, $c1));
                    --$i1;
                    break;
                case $replaceCost:
                    if ($c1 === $c2) {
                        array_unshift($diff, new StringDiffOperation(StringDiffOperation::MATCH, $c1));
                    } elseif ($this->itemDiffCalculator) {
                        $diff = array_merge($this->itemDiffCalculator->calcDiff($c1, $c2)->diff, $diff);
                    } else {
                        array_unshift(
                            $diff,
                            new StringDiffOperation(StringDiffOperation::DELETE, $c1),
                            new StringDiffOperation(StringDiffOperation::INSERT, $c2)
                        );
                    }
                    --$i1;
                    --$i2;
                    break;
            }
        }

        // free the matrix memory
        $this->matrix = [];

        // merge, re-order and unify the diffs
        $diff = array_merge($startDiff, $diff, $endDiff);
        $mergedDiff = [];
        /** @var StringDiffOperation $item */
        foreach ($diff as $item) {
            $mn = count($mergedDiff);

            $prevItem = $mergedDiff[$mn - 1] ?? new StringDiffOperation();
            $prevPrevItem = $mergedDiff[$mn - 2] ?? new StringDiffOperation();
            $prevPrevPrevItem = $mergedDiff[$mn - 3] ?? new StringDiffOperation();

            if ($item->operation === $prevItem->operation) {
                $prevItem->content .= $item->content;
            } elseif ($item->operation !== StringDiffOperation::MATCH && $prevItem->operation !== StringDiffOperation::MATCH && $item->operation === $prevPrevItem->operation) {
                $prevPrevItem->content .= $item->content;
            } elseif ($item->operation === StringDiffOperation::DELETE && $prevItem->operation === StringDiffOperation::MATCH && $this->isSeparator($prevItem->content) && $prevPrevItem->operation === StringDiffOperation::INSERT && $prevPrevPrevItem->operation === StringDiffOperation::DELETE) {
                $prevPrevPrevItem->content .= $prevItem->content . $item->content;
                $prevPrevItem->content .= $prevItem->content;
                array_pop($mergedDiff);
            } else {
                $mergedDiff[] = $item;
            }
        }

        return new StringDiffResult($distance, $mergedDiff);
    }
}
