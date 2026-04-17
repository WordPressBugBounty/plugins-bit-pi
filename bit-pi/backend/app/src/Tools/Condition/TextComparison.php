<?php

namespace BitApps\Pi\src\Tools\Condition;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


class TextComparison
{
    public function compare($operator, $leftValue, $rightValue)
    {
        $caseInSensitive = false;
        $operatorSplit = explode('-ci', $operator);

        if (\count($operatorSplit) === 2) {
            $caseInSensitive = true;
            $comparisonOperator = $operatorSplit[0];
        } else {
            $comparisonOperator = $operator;
        }

        switch ($comparisonOperator) {
            case 'equal':
                return $this->equal($leftValue, $rightValue, $caseInSensitive);

            case 'not-equal':
                return !$this->equal($leftValue, $rightValue, $caseInSensitive);

            case 'contains':
                return $this->isContains($leftValue, $rightValue, $caseInSensitive);

            case 'not-contain':
                return !$this->isContains($leftValue, $rightValue, $caseInSensitive);

            case 'starts-with':
                return $this->startWith($leftValue, $rightValue, $caseInSensitive);

            case 'not-start-with':
                return !$this->startWith($leftValue, $rightValue, $caseInSensitive);

            case 'ends-with':
                return $this->endWith($leftValue, $rightValue, $caseInSensitive);

            case 'not-end-with':
                return !$this->endWith($leftValue, $rightValue, $caseInSensitive);

            default:
                return false;
        }
    }

    public function equal($leftValue, $rightValue, $caseInSensitive)
    {
        return $caseInSensitive ? (strcasecmp($leftValue, $rightValue) === 0) : ($leftValue === $rightValue);
    }

    public function isContains($leftValue, $rightValue, $caseInSensitive)
    {
        return $caseInSensitive ? (stripos($leftValue, $rightValue) !== false) : (strpos($leftValue, $rightValue) !== false);
    }

    public function startWith($leftValue, $rightValue, $caseInSensitive)
    {
        if (!$caseInSensitive) {
            return strncmp($leftValue, $rightValue, \strlen($rightValue)) === 0;
        }

        return strncasecmp($leftValue, $rightValue, \strlen($rightValue)) === 0;
    }

    public function endWith($leftValue, $rightValue, $caseInSensitive)
    {
        if (!$caseInSensitive) {
            return substr_compare($leftValue, $rightValue, -\strlen($rightValue), \strlen($rightValue)) === 0;
        }

        return substr_compare(strtolower($leftValue), strtolower($rightValue), -\strlen($rightValue), \strlen($rightValue)) === 0;
    }
}
