<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class HistoricalActivityExporter extends \MrAdder\FilamentLogger\Support\ActivityExporter
{
    protected function streamRows(Builder $query, callable $callback): void
    {
        // Exports use the recorded type/ID and properties, not the live subject.
        // A retired model must not make historical activity impossible to export.
        (clone $query)->without('subject')->with('causer')
            ->chunk((int) config('filament-logger.exports.chunk_size', 500), function ($activities) use ($callback): void {
                foreach ($activities as $activity) $callback($this->formatRow($activity));
            });
    }
}
