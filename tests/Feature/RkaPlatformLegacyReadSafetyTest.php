<?php

namespace Tests\Feature;

use Tests\TestCase;

class RkaPlatformLegacyReadSafetyTest extends TestCase
{
    public function test_legacy_investment_table_renderers_have_no_database_write_side_effects(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/RkaResource.php'));
        $this->assertNotFalse($source);

        $monthStart = strpos($source, 'public static function buatColumn(');
        $tableStart = strpos($source, 'public static function table(');
        $this->assertNotFalse($monthStart);
        $this->assertNotFalse($tableStart);
        $this->assertGreaterThan($monthStart, $tableStart);
        $monthRenderer = substr($source, $monthStart, $tableStart - $monthStart);
        $this->assertStringNotContainsString('->save(', $monthRenderer);
        $this->assertStringContainsString('$record->childrenScoreSum($month)', $monthRenderer);

        $totalStart = strpos($source, "TextColumn::make('total')");
        $totalEnd = strpos($source, "->weight(function (Rka \$record)", $totalStart ?: 0);
        $this->assertNotFalse($totalStart);
        $this->assertNotFalse($totalEnd);
        $totalRenderer = substr($source, $totalStart, $totalEnd - $totalStart);
        $this->assertStringNotContainsString('->save(', $totalRenderer);
        $this->assertStringContainsString("childrenScoreSum('total')", $totalRenderer);
    }
}
