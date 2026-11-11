<?php

namespace Tests\Unit;

use App\Services\RkaPlatform\SharedCostAllocator;
use PHPUnit\Framework\TestCase;

class RkaPlatformSharedCostAllocatorTest extends TestCase
{
    public function test_allocates_exact_cents_without_creation_of_journal(): void
    {
        $allocator = new SharedCostAllocator();
        $this->assertSame([10 => '40.00', 20 => '80.00'],
            $allocator->calculate('120.00', [10 => 1, 20 => 2]));
    }

    public function test_largest_remainder_breaks_tie_by_unit_id(): void
    {
        $allocator = new SharedCostAllocator();
        $this->assertSame([2 => '0.34', 4 => '0.33', 9 => '0.33'],
            $allocator->calculate('1.00', [9 => 1, 4 => 1, 2 => 1]));
    }

    public function test_zero_cost_remains_zero(): void
    {
        $this->assertSame([1 => '0.00', 5 => '0.00'],
            (new SharedCostAllocator())->calculate('0.00', [1 => 1, 5 => 1]));
    }
}
