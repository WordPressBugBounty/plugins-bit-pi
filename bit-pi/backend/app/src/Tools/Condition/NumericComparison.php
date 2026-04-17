<?php

namespace BitApps\Pi\src\Tools\Condition;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


class NumericComparison
{
    public function compare($comparisonOperator, $leftValue, $rightValue)
    {
        switch ($comparisonOperator) {
            case 'equal':
                return $this->equal($leftValue, $rightValue);

            case 'not-equal':
                return !$this->equal($leftValue, $rightValue);

            case 'greater':
                return $this->isGreaterThan($leftValue, $rightValue);

            case 'less':
                return $this->isLessThan($leftValue, $rightValue);

            case 'greater-equal':
                return $this->isGreaterThanOrEqual($leftValue, $rightValue);

            case 'less-equal':
                return $this->isLessThanOrEqual($leftValue, $rightValue);

            default:
                return false;
        }
    }

    public function equal($leftValue, $rightValue)
    {
        if (!$this->isNumeric($leftValue, $rightValue)) {
            return false;
        }

        return $leftValue === $rightValue;
    }

    public function isGreaterThan($leftValue, $rightValue)
    {
        if (!$this->isNumeric($leftValue, $rightValue)) {
            return false;
        }

        return $leftValue > $rightValue;
    }

    public function isLessThan($leftValue, $rightValue)
    {
        if (!$this->isNumeric($leftValue, $rightValue)) {
            return false;
        }

        return $leftValue < $rightValue;
    }

    public function isGreaterThanOrEqual($leftValue, $rightValue)
    {
        if (!$this->isNumeric($leftValue, $rightValue)) {
            return false;
        }

        return $leftValue >= $rightValue;
    }

    public function isLessThanOrEqual($leftValue, $rightValue)
    {
        if (!$this->isNumeric($leftValue, $rightValue)) {
            return false;
        }

        return $leftValue <= $rightValue;
    }

    public function isNumeric($leftValue, $rightValue)
    {
        return is_numeric($leftValue) && is_numeric($rightValue);
    }
}
