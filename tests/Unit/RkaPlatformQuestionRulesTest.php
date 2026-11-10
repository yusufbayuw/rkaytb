<?php

namespace Tests\Unit;

use App\Services\RkaPlatform\QuestionRules;
use PHPUnit\Framework\TestCase;

class RkaPlatformQuestionRulesTest extends TestCase
{
    public function test_rule_matches_structured_context(): void
    {
        $engine = new QuestionRules();
        $rules = ['all' => [
            ['field' => 'program_type', 'operator' => 'eq', 'value' => 'investment'],
            ['field' => 'budget_amount', 'operator' => 'gte', 'value' => 100],
        ]];
        $this->assertTrue($engine->applies($rules, ['program_type' => 'investment', 'budget_amount' => 100]));
        $this->assertFalse($engine->applies($rules, ['program_type' => 'operational', 'budget_amount' => 1000]));
        $this->assertFalse($engine->applies($rules, ['program_type' => 'investment']));
        $this->assertTrue($engine->applies([], []));
    }

    public function test_unknown_rule_field_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QuestionRules())->applies(['all' => [
            ['field' => 'sql_expression', 'operator' => 'eq', 'value' => 'unsafe'],
        ]], []);
    }
}
