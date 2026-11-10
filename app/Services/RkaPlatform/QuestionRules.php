<?php

namespace App\Services\RkaPlatform;

use InvalidArgumentException;

class QuestionRules
{
    // Explicit, data-only DSL. No SQL, PHP code, eval or user-supplied closures.
    public function applies(array $rules, array $context): bool
    {
        foreach (($rules['all'] ?? []) as $rule) {
            $key = $rule['field'] ?? '';
            $operator = $rule['operator'] ?? 'eq';
            $value = $rule['value'] ?? null;
            if (!in_array($key, ['unit_type', 'program_type', 'activity_type', 'category',
                'budget_amount', 'variance_percent', 'risk', 'is_carry_over'], true)) {
                throw new InvalidArgumentException('Unknown question rule field.');
            }
            if (!array_key_exists($key, $context)) {
                return false;
            }
            $actual = $context[$key];
            $match = match ($operator) {
                'eq' => $actual === $value,
                'neq' => $actual !== $value,
                'in' => is_array($value) && in_array($actual, $value, true),
                'gt' => is_numeric($actual) && is_numeric($value) && $actual > $value,
                'gte' => is_numeric($actual) && is_numeric($value) && $actual >= $value,
                'lt' => is_numeric($actual) && is_numeric($value) && $actual < $value,
                default => throw new InvalidArgumentException('Unknown question rule operator.'),
            };
            if (!$match) return false;
        }
        return true;
    }
}
