<?php

namespace App\Services\RkaPlatform;

use InvalidArgumentException;

class QuestionRules
{
    private const FIELDS = [
        'unit_type', 'program_type', 'activity_type', 'category',
        'budget_amount', 'variance_percent', 'risk', 'is_carry_over',
    ];
    private const OPERATORS = ['eq','neq','in','gt','gte','lt'];

    public function validateDefinition(array $rules): void
    {
        if (array_diff(array_keys($rules), ['all']) !== []) {
            throw new InvalidArgumentException('Question rule has unsupported keys.');
        }
        $conditions = $rules['all'] ?? [];
        if (!is_array($conditions) || count($conditions) > 40 || !array_is_list($conditions)) {
            throw new InvalidArgumentException('Question rules must be a list of up to 40 conditions.');
        }
        foreach ($conditions as $rule) {
            if (!is_array($rule) || !array_key_exists('value', $rule) ||
                !in_array($rule['field'] ?? null, self::FIELDS, true) ||
                !in_array($rule['operator'] ?? 'eq', self::OPERATORS, true)) {
                throw new InvalidArgumentException('Invalid question rule.');
            }
            if (($rule['operator'] ?? 'eq') === 'in' &&
                (!is_array($rule['value']) || count($rule['value']) > 100 || !array_is_list($rule['value']))) {
                throw new InvalidArgumentException('The in operator requires a bounded list.');
            }
            if (in_array($rule['operator'] ?? 'eq', ['gt','gte','lt'], true) &&
                !is_numeric($rule['value'])) {
                throw new InvalidArgumentException('Numeric comparison requires a numeric value.');
            }
        }
    }

    public function applies(array $rules, array $context): bool
    {
        $this->validateDefinition($rules);
        foreach (($rules['all'] ?? []) as $rule) {
            $key = $rule['field'];
            if (!array_key_exists($key, $context)) return false;
            $actual = $context[$key];
            $value = $rule['value'];
            $match = match ($rule['operator'] ?? 'eq') {
                'eq' => $actual === $value,
                'neq' => $actual !== $value,
                'in' => in_array($actual, $value, true),
                'gt' => is_numeric($actual) && $actual > $value,
                'gte' => is_numeric($actual) && $actual >= $value,
                'lt' => is_numeric($actual) && $actual < $value,
            };
            if (!$match) return false;
        }
        return true;
    }
}
