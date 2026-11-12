<?php

namespace App\Services;

use AchyutN\FilamentLogViewer\Contracts\{LogParser, LogProvider, StackTraceParser};

class ReadOnlyLogProvider implements LogProvider
{
    private SafeLocalLogProvider $reader;
    public function __construct(LogParser $parser, StackTraceParser $stack)
    {
        $this->reader = new SafeLocalLogProvider($parser, $stack);
    }
    public function getRows(bool $refresh = false): array { return $this->reader->getRows($refresh); }
    public function getLogsByLevel(string $level): array { return $this->reader->getLogsByLevel($level); }
    public function getCount(string $level = 'all-logs'): ?int { return $this->reader->getCount($level); }
    public function getFiles(): array { return $this->reader->getFiles(); }
    public function getFilesForFilter(): array { return $this->reader->getFilesForFilter(); }
    public function getStackFromRaw(string $rawStack): array { return $this->reader->getStackFromRaw($rawStack); }
    public function skippedFiles(): array { return $this->reader->skippedFiles(); }
}
