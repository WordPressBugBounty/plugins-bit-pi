<?php

namespace BitApps\Pi\src\Tools\Condition;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


class ComparisonFactory
{
    public static function createComparison($leftValue, $rightValue, $conditionString)
    {
        $splitCondition = explode(':', $conditionString);
        if (\count($splitCondition) === 2) {
            $type = $splitCondition[0];
            $operator = $splitCondition[1];
        } else {
            return false;
        }

        switch ($type) {
            case 'basic':
                return (new BasicComparison())->compare($operator, $leftValue);

            case 'text':
                return (new TextComparison())->compare($operator, $leftValue, $rightValue);

            case 'numeric':
                return (new NumericComparison())->compare($operator, $leftValue, $rightValue);

            case 'array':
                return (new ArrayComparison())->compare($operator, $leftValue, $rightValue);

            case 'datetime':
                return (new DateTimeComparison())->compare($operator, $leftValue, $rightValue);

            case 'boolean':
                return (new BooleanComparison())->compare($operator, $leftValue, $rightValue);

            default:
                return false;
        }
    }
}
