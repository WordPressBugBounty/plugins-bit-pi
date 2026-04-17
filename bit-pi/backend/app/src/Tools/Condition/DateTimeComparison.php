<?php

namespace BitApps\Pi\src\Tools\Condition;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


class DateTimeComparison
{
    public function compare($comparisonOperator, $leftValue, $rightValue)
    {
        switch ($comparisonOperator) {
            case 'equal':
                return $this->equal($leftValue, $rightValue);

            case 'not-equal':
                return !$this->equal($leftValue, $rightValue);

            case 'later':
                return $this->later($leftValue, $rightValue);

            case 'earlier':
                return $this->earlier($leftValue, $rightValue);

            case 'later-equal':
                return $this->laterEqual($leftValue, $rightValue);

            case 'earlier-equal':
                return $this->earlierEqual($leftValue, $rightValue);

            default:
                return false;
        }
    }

    public function equal($leftValue, $rightValue)
    {
        if (!$this->checkValidDateTime($leftValue, $rightValue)) {
            return false;
        }

        return strtotime($leftValue) === strtotime($rightValue);
    }

    public function later($leftValue, $rightValue)
    {
        if (!$this->checkValidDateTime($leftValue, $rightValue)) {
            return false;
        }

        return strtotime($leftValue) > strtotime($rightValue);
    }

    public function earlier($leftValue, $rightValue)
    {
        if (!$this->checkValidDateTime($leftValue, $rightValue)) {
            return false;
        }

        return strtotime($leftValue) < strtotime($rightValue);
    }

    public function laterEqual($leftValue, $rightValue)
    {
        if (!$this->checkValidDateTime($leftValue, $rightValue)) {
            return false;
        }

        return strtotime($leftValue) >= strtotime($rightValue);
    }

    public function earlierEqual($leftValue, $rightValue)
    {
        return strtotime($leftValue) <= strtotime($rightValue);
    }

    public function checkValidDateTime($leftValue, $rightValue)
    {
        return strtotime($leftValue) !== false && strtotime($rightValue) !== false;
    }
}
