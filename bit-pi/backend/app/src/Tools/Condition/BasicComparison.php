<?php

namespace BitApps\Pi\src\Tools\Condition;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


class BasicComparison
{
    public function compare($comparisonOperator, $leftValue)
    {
        switch ($comparisonOperator) {
            case 'exist':
                return !$this->isEmpty($leftValue);

            case 'not-exist':
                return $this->isEmpty($leftValue);

            default:
                return false;
        }
    }

    public function isEmpty($leftValue)
    {
        return empty($leftValue) && !is_numeric($leftValue);
    }
}
