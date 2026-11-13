<?php

declare(strict_types=1);

use App\Models\{RkaCategory, RkaEntry, Unit};
use App\Support\RkaDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!$app->environment('testing') || getenv('RKA_CONCURRENCY_TEST') !== '1'
    || DB::getDefaultConnection() !== 'mysql' || DB::connection()->getDatabaseName() !== 'rka_test') {
    throw new RuntimeException('Concurrency verification requires the explicitly enabled MySQL rka_test database.');
}

function waitForRace(callable $ready): void
{
    $deadline = microtime(true) + 45;
    while (!$ready()) {
        if (microtime(true) > $deadline) throw new RuntimeException('RKA concurrency barrier timed out.');
        usleep(20000);
    }
}

if (($argv[1] ?? null) === '--worker') {
    $directory = realpath($argv[2]);
    if (!$directory || !str_starts_with($directory, realpath(dirname(__DIR__).'/.tools').DIRECTORY_SEPARATOR.'rka-concurrency-')
        || !in_array($argv[3], ['0', '1'], true)) throw new RuntimeException('Invalid concurrency fixture directory.');
    try {
        DB::transaction(function () use ($argv, $directory): void {
            // Both workers establish a repeatable-read snapshot before either saves.
            $record = RkaEntry::findOrFail((int) $argv[4]);
            file_put_contents($directory.'/ready-'.$argv[3], 'ready');
            waitForRace(fn () => file_exists($directory.'/go'));
            usleep((int) $argv[8]);
            $record->update([$argv[5] => $argv[6], 'januari' => $argv[7]]);
        });
        echo json_encode(['status' => 'saved']).PHP_EOL;
    } catch (ValidationException $exception) {
        echo json_encode(['status' => 'conflict', 'fields' => array_keys($exception->errors())]).PHP_EOL;
    }
    exit(0);
}

function race(array $mutations): array
{
    $directory = dirname(__DIR__).'/.tools/rka-concurrency-'.bin2hex(random_bytes(8));
    if (!is_dir(dirname($directory))) mkdir(dirname($directory), 0775);
    mkdir($directory, 0775);
    $workers = [];
    try {
        foreach ($mutations as $index => [$id, $field, $value, $month]) {
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', $directory, (string) $index, (string) $id,
                $field, $value, $month, (string) ($index * 300000)],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), null,
                ['bypass_shell' => true, 'create_new_console' => false]);
            if (!is_resource($process)) throw new RuntimeException('Cannot start RKA concurrency worker.');
            fclose($pipes[0]); $workers[] = [$process, $pipes];
        }
        waitForRace(fn () => file_exists($directory.'/ready-0') && file_exists($directory.'/ready-1'));
        file_put_contents($directory.'/go', 'go');
        $results = [];
        foreach ($workers as $index => [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process); unset($workers[$index]);
            if ($code !== 0) throw new RuntimeException('RKA worker failed: '.$stderr);
            $results[] = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR)['status'];
        }
        return $results;
    } finally {
        foreach ($workers as [$process, $pipes]) {
            proc_terminate($process);
            foreach ([1, 2] as $index) if (is_resource($pipes[$index])) fclose($pipes[$index]);
            proc_close($process);
        }
        foreach (['ready-0', 'ready-1', 'go'] as $file) if (is_file($directory.'/'.$file)) unlink($directory.'/'.$file);
        rmdir($directory);
    }
}

$unit = Unit::create(['nama' => 'Concurrency fixture '.bin2hex(random_bytes(5))]);
$category = RkaCategory::where('code', 'investment')->firstOrFail();
$base = ['category_id' => $category->id, 'unit_nama' => (string) $unit->id];
$root = RkaEntry::create([...$base, 'uraian' => 'Concurrent parent', 'is_parent' => true]);
$a = RkaEntry::create([...$base, 'uraian' => 'Concurrent A', 'parent_id' => $root->id, 'volume' => '1', 'harga_satuan' => '10.0010']);
$b = RkaEntry::create([...$base, 'uraian' => 'Concurrent B', 'parent_id' => $root->id, 'volume' => '1', 'harga_satuan' => '20.0020']);
$results = race([[$a->id, 'volume', '2', '1.11'], [$b->id, 'volume', '3', '2.22']]);
if ($results !== ['saved', 'saved'] || $root->fresh()->total !== '80.01' || $root->fresh()->januari !== '3.33') {
    throw new RuntimeException('Concurrent sibling updates lost a parent aggregate.');
}
$results = race([[$a->id, 'volume', '4', '1.11'], [$a->id, 'harga_satuan', '30', '1.11']]);
sort($results);
$fresh = $a->fresh();
$derived = RkaDecimal::round(bcmul($fresh->volume, $fresh->harga_satuan, 8));
if ($results !== ['conflict', 'saved'] || $fresh->total !== $derived
    || $root->fresh()->total !== bcadd($fresh->total, $b->fresh()->total, 2)) {
    throw new RuntimeException('A stale concurrent edit overwrote newer financial input.');
}
echo 'PASS: concurrent sibling totals/months and stale same-record edits remain consistent.'.PHP_EOL;
