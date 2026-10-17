<?php

require dirname(__DIR__) . '/src/Ttrpg/Database.php';
require dirname(__DIR__) . '/src/Ttrpg/AdventureHtml.php';
require dirname(__DIR__) . '/src/Ttrpg/AdventureStore.php';
require dirname(__DIR__) . '/src/Ttrpg/AdventurePackage.php';
require dirname(__DIR__) . '/src/Ttrpg/SinisterCitadel.php';

$result = FreeRoll\Ttrpg\SinisterCitadel::install();
$titles = array_map(static function (array $entry): string {
    return $entry['title'];
}, $result['entries']);
fwrite(STDOUT, count($titles) . " entries\n");
fwrite(STDOUT, 'active ' . ($result['tabs']['activeId'] ?? 'none') . "\n");
fwrite(STDOUT, implode(' | ', $titles) . "\n");
