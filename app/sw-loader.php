<?php
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Bug 4 fix: usar la versión que index.php pasó como ?v= en vez de recalcularla.
// Esto garantiza que SW y página siempre tienen la misma clave de caché.
if (!empty($_GET['v']) && preg_match('/^[a-z0-9]+$/', $_GET['v'])) {
    $build = $_GET['v'];
} else {
    $files = [__DIR__.'/style.css', __DIR__.'/app.js', __DIR__.'/vk-custom.css', __DIR__.'/vk-theme.json'];
    $mtime = 0;
    foreach($files as $f){ if(file_exists($f)) $mtime = max($mtime, filemtime($f)); }
    $build = base_convert($mtime, 10, 36);
}

// Inyectar versión ANTES del SW para que VK_CACHE la use
echo 'self.VK_BUILD = "' . preg_replace('/[^a-z0-9]/', '', $build) . '";' . "\n";
readfile(__DIR__ . '/sw.js');
