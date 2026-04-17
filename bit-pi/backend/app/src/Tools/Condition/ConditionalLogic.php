<?php

namespace BitApps\Pi\src\Tools\Condition;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Helpers\MixInputHandler;

class ConditionalLogic
{
    private const LOGICAL_OPERATOR = 'logical-operator';

    private const AND_OPERATOR = 'and';

    private const OR_OPERATOR = 'or';

    private const LOGIC = 'logic';

    public function compareLogicExpressions($condition)
    {
        $leftExp = MixInputHandler::replaceMixTagValue(JSON::maybeDecode($condition['leftExp'], true), 'array-first-element');

        $rightExp = MixInputHandler::replaceMixTagValue(JSON::maybeDecode($condition['rightExp'], true), 'array-first-element');

        $comparisonResult = ComparisonFactory::createComparison($leftExp, $rightExp, $condition['operator']);

        return [
            'condition_evaluation' => [
                'leftExp'   => $leftExp,
                'operator'  => $condition['operator'],
                'rightExp'  => $rightExp,
                'condition' => $comparisonResult,
            ],
            'is_logic_match' => $comparisonResult,
        ];
    }

    public static function conditionStatus($conditions)
    {
        $conditionStatus = null;

        $operator = null;

        $conditionInstance = new self();

        $conditionEvaluation = [];

        foreach ($conditions as $condition) {
            $condition = (array) $condition;
            if ($condition['type'] === self::LOGIC) {
                $result = $conditionInstance->compareLogicExpressions($condition);

                $isLogicMatch = $result['is_logic_match'];

                $conditionEvaluation[] = $result['condition_evaluation'];

                if ($operator === self::AND_OPERATOR) {
                    $conditionStatus = $conditionStatus && $isLogicMatch;
                } elseif ($operator === self::OR_OPERATOR) {
                    $conditionStatus = $conditionStatus || $isLogicMatch;
                } else {
                    $conditionStatus = $isLogicMatch;
                }
            } elseif ($condition['type'] === self::LOGICAL_OPERATOR) {
                $operator = $condition['value'];
            }
        }


        return [
            'condition_status'     => $conditionStatus,
            'condition_evaluation' => $conditionEvaluation,
        ];
    }
}
