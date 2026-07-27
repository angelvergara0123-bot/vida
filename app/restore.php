<?php
$file = __DIR__ . '/app.js';
$backup = __DIR__ . '/app.js.bak';
if(file_exists($backup)){
    copy($backup, $file);
    echo "✅ Restaurado. Tamaño: " . filesize($file) . " bytes";
} else {
    echo "❌ No existe backup";
}
