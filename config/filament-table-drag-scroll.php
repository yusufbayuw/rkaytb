<?php

$config = require base_path('vendor/majid-shafik/filament-table-drag-scroll/config/filament-table-drag-scroll.php');
$config['enabled'] = env('RKA_TABLE_DRAG_SCROLL', true);
return $config;
