<?php

namespace Chameleon\PhpDiff;

class OperationCostCalculator
{
    protected $replaceCost, $insertCost, $deleteCost;

    /** @var StringDistanceCalculatorInterface */
    protected $replaceDistanceCalculator;

    protected $replaceDistanceCoefficient;

    public function __construct($replaceCost = 1, $insertCost = 1, $deleteCost = 1)
    {
        $this->replaceCost = $replaceCost;
        $this->insertCost = $insertCost;
        $this->deleteCost = $deleteCost;
    }

    public function setReplaceDistanceCalculator(StringDistanceCalculatorInterface $replaceDistanceCalculator, $replaceDistanceCoefficient = 2)
    {
        $this->replaceDistanceCalculator = $replaceDistanceCalculator;
        $this->replaceDistanceCoefficient = $replaceDistanceCoefficient;
        return $this;
    }

    public function getReplaceCost($s1, $s2)
    {
        if ($s1 === $s2) {
            return 0;
        } else {
            $cost = $this->replaceCost;
            if ($this->replaceDistanceCalculator) {
                $cost += $this->replaceDistanceCoefficient * $this->replaceDistanceCalculator->calcDistance($s1, $s2);
            }
            return $cost;
        }
    }

    public function getInsertCost($s)
    {
        $cost = $this->insertCost;
        if ($this->replaceDistanceCalculator) {
            $cost += $this->replaceDistanceCalculator->calcDistance('', $s);
        }
        return $cost;
    }

    public function getDeleteCost($s)
    {
        $cost = $this->deleteCost;
        if ($this->replaceDistanceCalculator) {
            $cost += $this->replaceDistanceCalculator->calcDistance($s, '');
        }
        return $cost;
    }
}
