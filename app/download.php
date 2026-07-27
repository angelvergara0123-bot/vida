<?php
/**
 * Proxy de descarga — app.vidakushala.com/download.php
 *
 * Recibe la URL real del archivo (fileurl) directamente desde el JS,
 * la valida, detecta extensión/MIME y hace streaming con Content-Disposition.
 * No hace loopback al REST API para evitar lentitud/timeout.
 */

set_time_limit(0);
ignore_user_abort(true);

// ── CORS ──────────────────────────────────────────────────────────────────────
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://app.vidakushala.com','http://localhost:8080','http://localhost:3000'];
if (in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: X-VK-Token');
    http_response_code(204); exit;
}

if (!function_exists('curl_init')) {
    http_response_code(500); exit('cURL no disponible.');
}

// ── Parámetros ────────────────────────────────────────────────────────────────
$post_id = isset($_GET['id'])      ? (int) $_GET['id']              : 0;
$title   = isset($_GET['name'])    ? trim(strip_tags($_GET['name'])) : '';
$fileurl = isset($_GET['fileurl']) ? trim($_GET['fileurl'])          : '';

if (!$post_id) { http_response_code(400); exit('Parámetros inválidos.'); }

// ── MIME map ──────────────────────────────────────────────────────────────────
$mime_map = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip'  => 'application/zip',
    'rar'  => 'application/x-rar-compressed',
    '7z'   => 'application/x-7z-compressed',
    'mp4'  => 'video/mp4',    'mov'  => 'video/quicktime',
    'avi'  => 'video/x-msvideo', 'webm' => 'video/webm',
    'mp3'  => 'audio/mpeg',   'wav'  => 'audio/wav',
    'ogg'  => 'audio/ogg',    'm4a'  => 'audio/mp4',
    'jpg'  => 'image/jpeg',   'jpeg' => 'image/jpeg',
    'png'  => 'image/png',    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml','webp' => 'image/webp',
    'txt'  => 'text/plain',   'csv'  => 'text/csv',
];
$mime_to_ext = [];
foreach ($mime_map as $e => $m) {
    if (!isset($mime_to_ext[$m])) $mime_to_ext[$m] = $e;
}

// ── Resolver URL real del archivo ─────────────────────────────────────────────
$source = $fileurl ?: "https://vidakushala.com/?p={$post_id}";

// Detectar extensión en la URL ya recibida
$url_ext = strtolower(pathinfo(parse_url($source, PHP_URL_PATH), PATHINFO_EXTENSION));

$real_url    = '';
$ext         = '';
$mime        = 'application/octet-stream';
$content_len = 0;

if ($url_ext && isset($mime_map[$url_ext])) {
    // La URL ya apunta al archivo real — solo HEAD para Content-Length
    $real_url = $source;
    $ext      = $url_ext;
    $mime     = $mime_map[$ext];

    $h = curl_init($real_url);
    curl_setopt_array($h, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; VKProxy/1.0)',
    ]);
    curl_exec($h);
    $content_len = (int) curl_getinfo($h, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($h);

} else {
    // URL tipo SDC permalink — seguir redirecciones para obtener URL real
    $h = curl_init($source);
    curl_setopt_array($h, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; VKProxy/1.0)',
        CURLOPT_COOKIEJAR      => '',
        CURLOPT_COOKIEFILE     => '',
    ]);
    curl_exec($h);
    $effective  = curl_getinfo($h, CURLINFO_EFFECTIVE_URL) ?: '';
    $head_ct    = curl_getinfo($h, CURLINFO_CONTENT_TYPE)  ?: '';
    $content_len = (int) curl_getinfo($h, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($h);

    $real_url = $effective ?: $source;

    $eff_ext = strtolower(pathinfo(parse_url($real_url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if ($eff_ext && isset($mime_map[$eff_ext])) {
        $ext  = $eff_ext;
        $mime = $mime_map[$ext];
    } elseif ($head_ct) {
        $head_mime = strtolower(trim(explode(';', $head_ct)[0]));
        if ($head_mime && $head_mime !== 'text/html' && $head_mime !== 'application/json') {
            $mime = $head_mime;
            $ext  = $mime_to_ext[$head_mime] ?? '';
        }
    }
}

if (!$real_url) { http_response_code(404); exit('Archivo no encontrado.'); }

// ── Seguridad: solo vidakushala.com ──────────────────────────────────────────
$dl_host = parse_url($real_url, PHP_URL_HOST) ?? '';
if (!str_ends_with($dl_host, 'vidakushala.com')) {
    http_response_code(403); exit('URL no permitida.');
}

// ── Nombre de descarga (sin doble extensión) ──────────────────────────────────
// Limpiar caracteres inválidos
$title_clean = $title
    ? preg_replace('/[\/\\\\:*?"<>|]/', '_', $title)
    : pathinfo(parse_url($real_url, PHP_URL_PATH), PATHINFO_BASENAME);

// Quitar extensión existente del título para no duplicarla
$title_noext = $ext
    ? preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '', $title_clean)
    : pathinfo($title_clean, PATHINFO_FILENAME);

$base     = $title_noext ?: 'archivo-' . $post_id;
$filename = $base . ($ext ? '.' . $ext : '');

// ── Cabeceras ─────────────────────────────────────────────────────────────────
if (ob_get_level()) ob_end_clean();

header('Content-Type: ' . $mime);
$ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename);
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
if ($content_len > 0) header('Content-Length: ' . $content_len);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// ── Stream ────────────────────────────────────────────────────────────────────
$dl = curl_init($real_url);
curl_setopt_array($dl, [
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 10,
    CURLOPT_TIMEOUT        => 0,          // sin timeout — archivos grandes
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; VKProxy/1.0)',
    CURLOPT_HEADER         => false,
    CURLOPT_WRITEFUNCTION  => function ($ch, $data) { echo $data; flush(); return strlen($data); },
]);
curl_exec($dl);
curl_close($dl);
