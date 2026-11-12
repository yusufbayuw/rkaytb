<?php

namespace App\Services;

class CompatibleLogParser extends \AchyutN\FilamentLogViewer\Parsers\FileLogParser
{
    protected function parseLogEntry(array $lines, string $file): ?array
    {
        $environment = null;
        if (preg_match('/^(\[[^\]]+\]\s)([a-z0-9_-]+)(\.[a-z]+:)/i', $lines[0] ?? '', $match)) {
            $environment = $match[2];
            $lines[0] = $match[1].str_replace('-', '_', $environment).substr($lines[0], strlen($match[1].$environment));
        }
        try { $row = parent::parseLogEntry($lines, $file); }
        catch (\ValueError) { return null; } // Ignore lines with unknown log levels.
        if ($row && $environment !== null) $row['env'] = $environment;
        return $row;
    }
}
