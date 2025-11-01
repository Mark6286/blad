<?php

use Blad\Blad;

// === Setup ===
Blad::setPath(__DIR__ . '/resources/views');
Blad::setCachePath(__DIR__ . '/storage/cache/views'); // or false to disable cache
Blad::setExtension('.tpl.php');
Blad::enableDebug(true);

// === Globals & Directives ===
Blad::setGlobals(['appName' => 'Blad Engine']);
Blad::directive('datetime', fn($expr) => "<?php echo date('F j, Y', strtotime($expr)); ?>");

// === Render ===
Blad::render('home', ['title' => 'Welcome', 'now' => date('Y-m-d')]);

