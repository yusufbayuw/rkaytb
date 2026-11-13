<?php

namespace App\Services;

use App\Models\RkaCategory;
use App\Support\RkaDecimal;
use Illuminate\Database\Eloquent\Builder;

class RkaSummary
{
    public function totals(Builder $query): array
    {
        $directions = RkaCategory::pluck('direction', 'id');
        $totals = ['income' => '0.00', 'expense' => '0.00', 'invalid' => 0];
        foreach ((clone $query)->where('is_parent', false)->get() as $entry) {
            try {
                $key = $directions[$entry->category_id];
                $totals[$key] = bcadd($totals[$key], RkaDecimal::stored($entry->total), 2);
            } catch (\Illuminate\Validation\ValidationException) { $totals['invalid']++; }
        }
        $totals['balance'] = bcsub($totals['income'], $totals['expense'], 2);
        return $totals;
    }
}
