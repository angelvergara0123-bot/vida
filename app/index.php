<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

/* ═══════════════════════════════════════════════════════════
   MODO MANTENIMIENTO
   - Activa:   sube el archivo  .maintenance  al servidor
   - Desactiva: elimina ese archivo del servidor
   - Acceso admin: agrega  ?vk_admin=A7K9X2P4  a la URL
   - La cookie de admin dura 8 horas en el mismo navegador
═══════════════════════════════════════════════════════════ */
define('VK_MAINT_TOKEN', 'A7K9X2P4');   // ← cambia esta clave
define('VK_MAINT_FILE',  __DIR__ . '/.maintenance');

if (file_exists(VK_MAINT_FILE)) {
    // El admin presenta el token → se guarda cookie y continúa normal
    if (isset($_GET['vk_admin']) && $_GET['vk_admin'] === VK_MAINT_TOKEN) {
        setcookie('vk_admin', VK_MAINT_TOKEN, time() + 28800, '/', '', true, true);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    // Cookie de admin válida → acceso normal
    $adminOk = isset($_COOKIE['vk_admin']) && $_COOKIE['vk_admin'] === VK_MAINT_TOKEN;
    if (!$adminOk) {
        http_response_code(503);
        header('Retry-After: 3600');
        include __DIR__ . '/maintenance.php';
        exit;
    }
}

/* ═══════════════════════════════════════════════════════════
   ACTIVACION DE EMAIL — procesado en PHP (server-side)
   Llama al WordPress REST API directamente desde el servidor.
   Más confiable que depender de JavaScript.
═══════════════════════════════════════════════════════════ */
if (!empty($_GET['activate'])) {
    $token = preg_replace('/[^a-f0-9]/i', '', $_GET['activate']); // solo hex
    if (strlen($token) >= 32) {
        $wp_api = 'https://vidakushala.com/wp-json/vk/v1/activate-email';
        $ctx    = stream_context_create(array(
            'http' => array(
                'method'  => 'POST',
                'header'  => "Content-Type: application/json
Accept: application/json
",
                'content' => json_encode(array('token' => $token)),
                'timeout' => 10,
                'ignore_errors' => true,
            ),
            'ssl' => array('verify_peer' => false),
        ));
        $resp = @file_get_contents($wp_api, false, $ctx);
        $data = $resp ? @json_decode($resp, true) : null;

        if ($data && !empty($data['activated'])) {
            // Activacion exitosa — redirigir con flag para JS
            header('Location: /?activated=1&token=' . urlencode($data['token'] ?? '') .
                   '&uid=' . urlencode($data['user_id'] ?? '') .
                   '&name=' . urlencode($data['display_name'] ?? '') .
                   '&email=' . urlencode($data['email'] ?? '') .
                   '&avatar=' . urlencode($data['avatar_url'] ?? ''));
            exit;
        } else {
            $err_msg = ($data && !empty($data['message'])) ? $data['message'] : 'Enlace invalido o expirado.';
            header('Location: /?activation_error=' . urlencode($err_msg));
            exit;
        }
    }
}

// Activacion exitosa — JS tomará el token de la URL y hará login automático
$_vk_activated    = !empty($_GET['activated']) ? '1' : '';
$_vk_act_token    = !empty($_GET['token'])     ? htmlspecialchars($_GET['token'])  : '';
$_vk_act_uid      = !empty($_GET['uid'])       ? (int)$_GET['uid']                 : 0;
$_vk_act_name     = !empty($_GET['name'])      ? htmlspecialchars($_GET['name'])   : '';
$_vk_act_email    = !empty($_GET['email'])     ? htmlspecialchars($_GET['email'])  : '';
$_vk_act_avatar   = !empty($_GET['avatar'])    ? htmlspecialchars($_GET['avatar']) : '';
$_vk_act_error    = !empty($_GET['activation_error']) ? htmlspecialchars($_GET['activation_error']) : '';

// Recuperacion de contrasena via enlace de correo
$_vk_reset_key   = !empty($_GET['reset_key'])    ? htmlspecialchars($_GET['reset_key'])    : '';
$_vk_reset_login = !empty($_GET['reset_login'])  ? htmlspecialchars($_GET['reset_login'])  : '';

/* ─── Versión de build (cache-busting automático) ────────────── */
$_vk_ver = base_convert((string)max(
    (int)@filemtime(__DIR__ . '/style.css'),
    (int)@filemtime(__DIR__ . '/app.js'),
    (int)@filemtime(__DIR__ . '/vk-custom.css'),
    (int)@filemtime(__DIR__ . '/vk-theme.json')
), 10, 36);

/* ─── Google Fonts dinámico desde vk-theme.json ─────────────── */
$_vk_theme_data = [];
$_vk_theme_path = __DIR__ . '/vk-theme.json';
if (file_exists($_vk_theme_path)) {
    $_vk_theme_data = @json_decode(file_get_contents($_vk_theme_path), true) ?: [];
}
$_vk_fh = isset($_vk_theme_data['typography']['font_heading']) && $_vk_theme_data['typography']['font_heading'] !== ''
    ? trim($_vk_theme_data['typography']['font_heading']) : 'Cormorant Garamond';
$_vk_fb = isset($_vk_theme_data['typography']['font_body']) && $_vk_theme_data['typography']['font_body'] !== ''
    ? trim($_vk_theme_data['typography']['font_body']) : 'DM Sans';
$_vk_fu = isset($_vk_theme_data['typography']['font_ui']) && $_vk_theme_data['typography']['font_ui'] !== ''
    ? trim($_vk_theme_data['typography']['font_ui']) : '';

// Mapeo de fuentes Google Fonts → parámetros de peso
$_vk_gf_map = [
    'Cormorant Garamond' => 'ital,wght@0,400;0,500;0,600;0,700;1,400;1,500',
    'Playfair Display'   => 'ital,wght@0,400;0,600;0,700;1,400;1,600',
    'Merriweather'       => 'ital,wght@0,300;0,400;0,700;1,300;1,400',
    'Lora'               => 'ital,wght@0,400;0,500;0,600;0,700;1,400;1,500',
    'DM Sans'            => 'wght@300;400;500;600;700',
    'Inter'              => 'wght@300;400;500;600;700',
    'Montserrat'         => 'wght@300;400;500;600;700',
    'Poppins'            => 'wght@300;400;500;600;700',
    'Roboto'             => 'ital,wght@0,300;0,400;0,700;1,400',
    'Open Sans'          => 'ital,wght@0,300;0,400;0,600;0,700;1,400',
    'Lato'               => 'ital,wght@0,300;0,400;0,700;1,400',
    'Nunito'             => 'wght@300;400;600;700',
    'Source Sans Pro'    => 'wght@300;400;600;700',
    'Raleway'            => 'wght@300;400;500;600;700',
    'Josefin Sans'       => 'wght@300;400;600;700',
    'Crimson Text'       => 'ital,wght@0,400;0,600;1,400',
    'Libre Baskerville'  => 'ital,wght@0,400;0,700;1,400',
];
// Fuentes del sistema que no necesitan carga externa
$_vk_system_fonts = ['Georgia', 'Times New Roman', 'Arial', 'Verdana', 'Trebuchet MS', 'Helvetica'];

// Construir URL de Google Fonts
$_vk_gf_parts = [];
$_vk_gf_seen  = [];
foreach ([$_vk_fh, $_vk_fb, $_vk_fu] as $_vk_f) {
    if (!$_vk_f || isset($_vk_gf_seen[$_vk_f]) || in_array($_vk_f, $_vk_system_fonts)) continue;
    $_vk_gf_seen[$_vk_f] = true;
    $w = isset($_vk_gf_map[$_vk_f]) ? $_vk_gf_map[$_vk_f] : 'wght@400;700';
    $_vk_gf_parts[] = 'family=' . rawurlencode($_vk_f) . ':' . $w;
}
$_vk_gf_url = $_vk_gf_parts
    ? 'https://fonts.googleapis.com/css2?' . implode('&', $_vk_gf_parts) . '&display=swap'
    : '';

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

<!-- PWA / Android -->
<meta name="theme-color" content="#6b2447">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="VidaKushala">
<link rel="manifest" href="/manifest.json">

<!-- PWA / iOS (Safari) -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="VidaKushala">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-touch-fullscreen" content="yes">

<!-- Iconos iOS — tamaño 180 es el estándar para iPhone actual -->
<link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-192.png">
<link rel="apple-touch-icon" sizes="167x167" href="/icons/icon-192.png">
<link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152.png">
<link rel="apple-touch-icon" sizes="144x144" href="/icons/icon-144.png">
<link rel="apple-touch-icon" sizes="120x120" href="/icons/icon-128.png">
<link rel="apple-touch-icon" sizes="76x76"   href="/icons/icon-96.png">
<link rel="apple-touch-icon"                  href="/icons/icon-192.png">

<!-- Splash screens iOS: se usan solo si existen los archivos en /icons/ -->

<!-- Favicon genérico -->
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
<link rel="icon" type="image/png" sizes="96x96"   href="/icons/icon-96.png">
<link rel="icon" type="image/png" sizes="32x32"   href="/icons/icon-72.png">

<!-- SEO / Open Graph -->
<meta name="description" content="Tu plataforma de crecimiento personal con cursos, certificados y asistente IA.">
<meta property="og:type"        content="website">
<meta property="og:title"       content="VidaKushala">
<meta property="og:description" content="Tu plataforma de crecimiento personal con cursos, certificados y asistente IA.">
<meta property="og:image"       content="https://app.vidakushala.com/icons/icon-512.png">
<meta property="og:url"         content="https://app.vidakushala.com">

<title>VidaKushala</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php if ($_vk_gf_url): ?>
<link href="<?= htmlspecialchars($_vk_gf_url) ?>" rel="stylesheet">
<?php endif; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://accounts.google.com/gsi/client" async defer></script>
<link rel="stylesheet" href="style.css?v=<?= $_vk_ver ?>">
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<link rel="stylesheet" href="vk-custom.css?v=<?= $_vk_ver ?>">
<link rel="stylesheet" href="vk-theme.css.php?v=<?= $_vk_ver ?>">
</head>
<body>
<!-- fb-root removed -->

<div id="app">

<!-- MENÚ DESPLEGABLE MÓVIL — al nivel del app, fuera de .screen para escapar overflow:hidden -->
<div class="mhdr-dropdown" id="mhdr-dropdown">
  <div class="mhdr-dd-header">
    <div class="mhdr-dd-avatar" id="mhdr-dd-avatar"><i class="fas fa-user"></i></div>
    <div>
      <div class="mhdr-dd-name" id="mhdr-dd-name">—</div>
      <div class="mhdr-dd-email" id="mhdr-dd-email">—</div>
    </div>
  </div>
  <div class="mhdr-dd-items">
    <button onclick="goto('home');closeMobileMenu()"><i class="fas fa-home"></i> Inicio</button>
    <button onclick="goto('courses');closeMobileMenu()"><i class="fas fa-book"></i> Mis Cursos</button>
    <button onclick="goto('directory-profile');closeMobileMenu()"><i class="fas fa-address-card"></i> Mi Directorio</button>
    <button onclick="goto('certificates');closeMobileMenu()"><i class="fas fa-award"></i> Mis Certificados</button>
    <button onclick="goto('search');closeMobileMenu()"><i class="fas fa-search"></i> Explorar Cursos</button>
    <button onclick="goto('products');closeMobileMenu()"><i class="fas fa-shopping-cart"></i> Productos</button>
    <button onclick="goto('profile');closeMobileMenu()"><i class="fas fa-user-edit"></i> Editar Perfil</button>
    <button onclick="goto('notifications');closeMobileMenu()"><i class="fas fa-bell"></i> Notificaciones</button>
    <button class="mhdr-dd-logout" onclick="closeMobileMenu();logout()"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</button>
  </div>
</div>

<!-- DESKTOP SIDEBAR -->
<nav id="desktop-sidebar">
  <div class="sidebar-logo">
      <div><img src="https://app.vidakushala.com/logo.png" alt="Logo" style="height: 100px;"></div>
      
    <div class="sidebar-logo-mark">
       
      <div>
        
      </div>
    </div>
  </div>
  <div class="sidebar-nav">
    <button class="snav-item active" id="snav-home" onclick="goto('home')"><span class="snav-icon"><i class="fas fa-home"></i></span>Inicio</button>
    <button class="snav-item" id="snav-courses" onclick="goto('courses')"><span class="snav-icon"><i class="fas fa-book"></i></span>Mis Cursos</button>
    <button class="snav-item" id="snav-search" onclick="goto('search')"><span class="snav-icon"><i class="fas fa-search"></i></span>Explorar Cursos</button>
    <button class="snav-item" id="snav-qa" onclick="goto('qa')"><span class="snav-icon"><i class="fas fa-comments"></i></span>Preguntas y R.</button>
    <button class="snav-item" id="snav-products" onclick="goto('products')"><span class="snav-icon"><i class="fas fa-shopping-cart"></i></span>Productos</button>
    <button class="snav-item" id="snav-polls" onclick="goto('polls')"><span class="snav-icon"><i class="fas fa-pen-to-square"></i></span>Encuestas</button>
    <button class="snav-item" id="snav-documents" onclick="goto('documents')"><span class="snav-icon"><i class="fas fa-book-open"></i></span>Biblioteca</button>
    <button class="snav-item" id="snav-directory" onclick="goto('directory-profile')"><span class="snav-icon"><i class="fas fa-address-card"></i></span>Mi Directorio</button>
    <button class="snav-item" id="snav-chat" onclick="goto('chat')"><span class="snav-icon"><i class="fas fa-robot"></i></span>Chat IA</button>
    <button class="snav-item" id="snav-profile" onclick="goto('profile')"><span class="snav-icon"><i class="fas fa-user"></i></span>Perfil</button>
    <button class="snav-item" id="snav-notifications" onclick="goto('notifications')"><span class="snav-icon"><i class="fas fa-bell"></i></span>Notificaciones<span class="notif-badge" id="notif-badge-sidebar" style="display:none">0</span></button>
    
    <button class="snav-item" onclick="logout()" style="margin-top:auto"><span class="snav-icon"><i class="fas fa-sign-out-alt"></i></span>Cerrar sesión</button>
  </div>
  <div class="sidebar-footer">
    <p class="sidebar-tagline">Aprende, transforma<br>y evoluciona.</p>
  </div>
</nav>

<!-- DESKTOP TOPBAR — barra superior derecha solo en escritorio -->
<div id="desktop-topbar">
  <!-- Buscador -->
  <button class="dtb-btn" onclick="goto('search')" title="Buscar cursos y contenido">
    <i class="fas fa-search"></i>
  </button>

  <!-- Campana de notificaciones -->
  <button class="dtb-btn dtb-notif" onclick="goto('notifications')" title="Notificaciones">
    <i class="fas fa-bell"></i>
    <span id="dtb-notif-badge" style="display:none">0</span>
  </button>

  <!-- Avatar + menú desplegable -->
  <div class="dtb-user" id="dtb-user-menu" onclick="toggleDtbMenu()">
    <div class="dtb-avatar" id="dtb-avatar">
      <i class="fas fa-user"></i>
    </div>
    <i class="fas fa-chevron-down dtb-chevron" id="dtb-chevron"></i>
  </div>
</div>

<!-- Dropdown desktop — FUERA del topbar para evitar conflictos de eventos -->
<div class="dtb-dropdown" id="dtb-dropdown">
  <div class="dtb-dd-header">
    <div class="dtb-dd-name" id="dtb-dd-name">—</div>
    <div class="dtb-dd-email" id="dtb-dd-email">—</div>
  </div>
  <div class="dtb-dd-items">
    <button onclick="goto('home');closeDtbMenu()"><i class="fas fa-home"></i> Inicio</button>
    <button onclick="goto('courses');closeDtbMenu()"><i class="fas fa-book"></i> Mis Cursos</button>
    <button onclick="goto('directory-profile');closeDtbMenu()"><i class="far fa-address-card"></i> Mi Directorio</button>
    <button onclick="goto('certificates');closeDtbMenu()"><i class="fas fa-award"></i> Mis Certificados</button>
    <button onclick="goto('search');closeDtbMenu()"><i class="fas fa-search"></i> Explorar Cursos</button>
    <button onclick="goto('products');closeDtbMenu()"><i class="fas fa-shopping-cart"></i> Productos</button>
    <button onclick="goto('profile');closeDtbMenu()"><i class="fas fa-user-edit"></i> Editar Perfil</button>
    <button onclick="goto('notifications');closeDtbMenu()"><i class="fas fa-bell"></i> Notificaciones</button>
    <button class="dtb-dd-logout" onclick="closeDtbMenu();logout()"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</button>
  </div>
</div>

<div class="desktop-main">

<!-- SPLASH -->
<div id="splash">
  <div style="width:76px;height:76px;border-radius:50%;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 16px rgba(255,255,255,.06);position:relative;z-index:1">
   
  </div>
  <p style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:rgba(255,255,255,.85);font-weight:500;letter-spacing:.04em;position:relative;z-index:1">vidakushala</p>
</div>

<!-- LOGIN -->
<div class="screen" id="screen-login">
  <!-- Desktop left panel (hidden on mobile via media query) -->
  <div class="login-left-panel" style="display:none" id="login-left">
    <div style="position:relative;z-index:1;text-align:center">
      <div style="width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;box-shadow:0 0 0 16px rgba(255,255,255,.06)">
    
         <img src="https://app.vidakushala.com/logo.png" alt="Logo">
      </div>
      <p style="font-size:1.05rem;color:rgba(255,255,255,.65);margin-top:.75rem;font-style:italic;font-family:'Cormorant Garamond',serif">Aprende, transforma y evoluciona.</p>
    </div>
  </div>
  <!-- Login form panel -->
  <div id="login-right" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow-y:auto;background:white;">
    <div class="login-inner" style="max-width:380px">
      <!-- Mobile logo (hidden desktop) -->
      <div id="login-mobile-logo" style="text-align:center;">
        <div ><img src="https://app.vidakushala.com/icons/logo.png" alt="Logo"></div>
        
        <p class="login-sub">Accede a tus cursos fácilmente</p>
      </div>
      <!-- Desktop heading (hidden mobile) -->
      <div id="login-desktop-heading" style="display:none;margin-bottom:1.75rem;text-align:left">
        <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.9rem;color:var(--vk-plum);font-weight:700">Bienvenido de vuelta</h2>
        <p style="font-size:.9rem;color:var(--ts);margin-top:.3rem">Inicia sesión para continuar</p>
        <div style="width:40px;height:3px;background:var(--grad-accent);border-radius:3px;margin-top:.75rem"></div>
      </div>

      <div id="g_id_onload"
        data-client_id="194338099501-6utomonv7go9d2ub4o2c8l1su4936gsp.apps.googleusercontent.com"
        data-callback="handleGoogleResponse" data-auto_prompt="false"></div>
      <div style="display:flex;justify-content:center;margin-bottom:.55rem;width:100%">
        <div class="g_id_signin" data-type="standard" data-shape="rectangular"
          data-theme="outline" data-text="signin_with" data-size="large"
          data-locale="es" data-width="340"></div>
      </div>

      <!-- Botón Facebook Login -->
      <div style="display:flex;justify-content:center;margin-bottom:.65rem;width:100%">
        <button class="vk-fb-btn" onclick="FB.login(function(r){if(r.authResponse)checkFBLoginState();else showToast('Acceso cancelado');},{scope:'public_profile,email'})"
          style="width:340px;max-width:100%;height:40px;background:#1877f2;color:#fff;border:none;border-radius:4px;font-size:.93rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.6rem;font-family:inherit;transition:opacity .2s">
          <div style="display:flex;align-items:center;gap:.55rem;pointer-events:none">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#fff"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.931-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
            <span>Continuar con Facebook</span>
          </div>
        </button>
      </div>

      <div class="divider"><span>o con correo</span></div>
      <div class="field">
        <label>Correo electrónico</label>
        <input type="email" id="login-user" placeholder="correo@ejemplo.com" autocomplete="email">
      </div>
      <div class="field">
        <label>Contraseña</label>
        <div style="position:relative">
          <input type="password" id="login-pass" placeholder="Tu contraseña" autocomplete="current-password" onkeydown="if(event.key==='Enter')loginEmail()" style="padding-right:2.6rem;width:100%">
          <button type="button" onclick="togglePass('login-pass',this)" tabindex="-1" style="position:absolute;right:.6rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:.25rem;color:var(--ts);line-height:1">
            <svg id="eye-login-pass" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>
      <button class="btn btn-primary" id="btn-login" onclick="loginEmail()">Entrar</button>
      <p style="text-align:center;margin-top:.75rem">
        <span style="color:var(--tu);cursor:pointer;font-size:.82rem" onclick="showForgotPasswordModal()">¿Olvidaste tu contraseña?</span>
      </p>
      <p style="text-align:center;margin-top:.35rem;font-size:.85rem;color:var(--ts)">
        ¿No tienes cuenta? <span style="color:var(--vk-rose);font-weight:700;cursor:pointer" onclick="goto('register')">Créala aquí</span>
      </p>
      <p style="margin-top:1.5rem;text-align:center;font-size:.75rem;color:var(--tu)">© 2026 vidakushala</p>
    </div>
  </div>
</div>

<!-- REGISTRO MANUAL -->
<div class="screen" id="screen-register">
  <div style="display:flex;align-items:center;justify-content:center;height:100%;overflow-y:auto;background:white;">
    <div class="login-inner" style="max-width:380px">
      <div style="text-align:center;margin-bottom:1.25rem">
     
        <div><img src="https://app.vidakushala.com/icons/logo.png" alt="Logo"></div>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:1.7rem;color:var(--vk-plum);font-weight:700">Crear cuenta</h1>
      </div>
      <!-- Registro con redes sociales -->
      <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.9rem;width:100%">
        <div style="display:flex;justify-content:center">
          <div class="g_id_signin" data-type="standard" data-shape="rectangular"
            data-theme="outline" data-text="signup_with" data-size="large"
            data-locale="es" data-width="340"></div>
        </div>
        <div style="display:flex;justify-content:center">
          <button class="vk-fb-btn" onclick="FB.login(function(r){if(r.authResponse)checkFBLoginState();else showToast('Acceso cancelado');},{scope:'public_profile,email'})"
            style="width:340px;max-width:100%;height:40px;background:#1877f2;color:#fff;border:none;border-radius:4px;font-size:.93rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.6rem;font-family:inherit;transition:opacity .2s">
            <div style="display:flex;align-items:center;gap:.55rem;pointer-events:none">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="#fff"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.931-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
              <span>Registrarse con Facebook</span>
            </div>
          </button>
        </div>
      </div>
      <div class="divider" style="margin-bottom:.9rem"><span>o con correo</span></div>

      <div style="background:#fff8e6;border-radius:12px;padding:.7rem 1rem;margin-bottom:1.1rem;font-size:.82rem;color:#7d5c00;line-height:1.5;width:100%">
        📋 Nombre y apellido aparecerán en tus <strong>certificados de curso</strong>.
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;width:100%">
        <div class="field">
          <label for="reg-first">Nombre <span class="req-badge">*</span></label>
          <input type="text" id="reg-first" name="first_name" placeholder="Tu nombre" autocomplete="given-name">
        </div>
        <div class="field">
          <label for="reg-last">Apellido <span class="req-badge">*</span></label>
          <input type="text" id="reg-last" name="last_name" placeholder="Tu apellido" autocomplete="family-name">
        </div>
      </div>
      <div class="field" style="width:100%">
        <label>Correo electrónico <span class="req-badge">*</span></label>
        <input type="email" id="reg-email" placeholder="correo@ejemplo.com" autocomplete="email">
      </div>
      <div class="field" style="width:100%">
        <label>Contraseña <span class="req-badge">*</span></label>
        <div style="position:relative">
          <input type="password" id="reg-pass" placeholder="Mínimo 8 caracteres" autocomplete="new-password" style="padding-right:2.6rem;width:100%">
          <button type="button" onclick="togglePass('reg-pass',this)" tabindex="-1" style="position:absolute;right:.6rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:.25rem;color:var(--ts);line-height:1">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>
      <button class="btn btn-primary" id="btn-register" onclick="registroManual()" style="width:100%">Crear mi cuenta</button>
      <p style="text-align:center;margin-top:.85rem;font-size:.85rem;color:var(--ts)">
        ¿Ya tienes cuenta? <span style="color:var(--vk-rose);font-weight:700;cursor:pointer" onclick="goto('login')">Inicia sesión</span>
      </p>
      <p style="margin-top:1.25rem;text-align:center;font-size:.75rem;color:var(--tu)">© 20256 vidakushala</p>
    </div>
  </div>
</div>

<!-- COMPLETAR PERFIL (social) -->
<div class="screen" id="screen-complete-profile">
  <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:2rem 1.75rem;background:linear-gradient(160deg,#fce8f1,white,#fdf6f9)">
    <div id="cp-avatar" style="width:80px;height:80px;border-radius:50%;background:var(--grad-hero);display:flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:1.25rem;box-shadow:0 0 0 14px rgba(196,77,138,.1);overflow:hidden;flex-shrink:0"><i class="fas fa-user"></i></div>
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;color:var(--vk-plum);font-weight:700;margin-bottom:.4rem;text-align:center">¡Ya casi está!</h2>
    <p style="font-size:.9rem;color:var(--ts);margin-bottom:.25rem;text-align:center;line-height:1.6;max-width:280px">Solo necesitamos tu nombre y apellido.</p>
    <p style="font-size:.8rem;color:var(--tu);margin-bottom:1.75rem;text-align:center;line-height:1.5;max-width:280px">Aparecerán en tus <strong style="color:var(--vk-plum)">certificados de curso</strong>.</p>
    <div style="width:100%;max-width:340px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-bottom:.75rem">
        <div class="field" style="margin-bottom:0">
          <label for="cp-first">Nombre <span class="req-badge">*</span></label>
          <input type="text" id="cp-first" name="first_name" placeholder="Tu nombre" autocomplete="given-name" style="margin-bottom:0" onkeydown="if(event.key==='Enter')document.getElementById('cp-last').focus()">
        </div>
        <div class="field" style="margin-bottom:0">
          <label for="cp-last">Apellido <span class="req-badge">*</span></label>
          <input type="text" id="cp-last" name="last_name" placeholder="Tu apellido" autocomplete="family-name" style="margin-bottom:0" onkeydown="if(event.key==='Enter')crearCuenta()">
        </div>
      </div>
      <button class="btn btn-primary" id="btn-complete-profile" onclick="crearCuenta()" style="margin-top:.75rem">Crear mi cuenta →</button>
      <p style="text-align:center;margin-top:.85rem;font-size:.83rem;color:var(--tu)">
        ¿Ya tienes cuenta? <span style="color:var(--vk-rose);font-weight:700;cursor:pointer" onclick="goto('login')">Inicia sesión</span>
      </p>
    </div>
  </div>
</div>

<!-- BIENVENIDA -->
<div class="screen" id="screen-welcome">
  <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:2.5rem 1.75rem;text-align:center;background:linear-gradient(160deg,#fce8f1,white)">
    <div style="font-size:3.5rem;margin-bottom:1.25rem">🎉</div>
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.75rem;color:var(--vk-plum);font-weight:700;margin-bottom:.75rem">¡Bienvenido a VidaKushala!</h2>
    <p style="font-size:.93rem;color:var(--ts);margin-bottom:1.5rem;line-height:1.65;max-width:280px">Tu cuenta fue creada. Tu nombre y apellido aparecerán en los certificados de tus cursos.</p>
    <div style="background:white;border-radius:var(--rl);padding:1.1rem 1.5rem;width:100%;max-width:320px;margin-bottom:1.75rem;box-shadow:var(--shs);border:1px solid var(--border)">
      <p style="font-size:.8rem;color:var(--ts);margin-bottom:.3rem">Registrado como:</p>
      <p style="font-size:1.1rem;font-weight:700;color:var(--vk-plum);font-family:'Cormorant Garamond',serif" id="welcome-name">—</p>
      <p style="font-size:.82rem;color:var(--tu)" id="welcome-email">—</p>
    </div>
    <button class="btn btn-primary" onclick="enterApp()" style="max-width:320px">¡Empezar a aprender! →</button>
  </div>
</div>

<!-- HOME -->
<div class="screen" id="screen-home">
  <div class="top-bar mobile-only mob-hdr" id="mobile-home-topbar">
    <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')"><i class="fas fa-bell"></i><span class="mhdr-notif-badge" id="mhdr-notif-badge" style="display:none">0</span></button>
      <div class="mhdr-user" id="mhdr-user-menu" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar" id="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron" id="mhdr-chevron"></i>
      </div>
    </div>
  </div>

  <div class="scroll-area">
    <div class="home-hero">
      <p class="home-hero-label" style="padding-top:20px;">Bienvenido de vuelta</p>
      <h2 id="home-name">...</h2>
      <p>¿Qué quieres aprender hoy?</p>
    </div>
    <div class="home-grid">
      <div class="menu-card" onclick="goto('courses')"><div class="menu-icon icon-box icon-courses"><i class="fas fa-book"></i></div><div class="menu-text"><h3>Mis Cursos</h3><p>Ver progreso</p></div><i class="fas fa-chevron-right menu-chevron"></i></div>
      <div class="menu-card" onclick="goto('search')"><div class="menu-icon icon-box icon-explore"><i class="fas fa-search"></i></div><div class="menu-text"><h3>Explorar</h3><p>Descubrir cursos</p></div><i class="fas fa-chevron-right menu-chevron"></i></div>
      <div class="menu-card" onclick="goto('qa')"><div class="menu-icon icon-box" style="background: #7c3357;color: #ffff;"><i class="fas fa-comments"></i></div><div class="menu-text"><h3>Preguntas y R.</h3><p>Comunidad</p></div><i class="fas fa-chevron-right menu-chevron"></i></div>
      <div class="menu-card" onclick="goto('products')"><div class="menu-icon icon-box icon-products"><i class="fas fa-shopping-cart"></i></div><div class="menu-text"><h3>Productos</h3><p>Catálogo</p></div><i class="fas fa-chevron-right menu-chevron"></i></div>
      <div class="menu-card" onclick="goto('documents')"><div class="menu-icon icon-box icon-polls"><i class="fas fa-book-open"></i></div><div class="menu-text"><h3>Biblioteca</h3><p>Recursos y archivos</p></div><i class="fas fa-chevron-right menu-chevron"></i></div>
      <div class="menu-card" onclick="goto('chat')"><div class="menu-icon icon-box icon-chat" style="background: #7c3357;color: #ffff;"><i class="fas fa-robot"></i></div><div class="menu-text"><h3>Chat IA</h3><p>Asistente virtual</p></div><i class="fas fa-chevron-right menu-chevron"></i></div>
    </div>
    <!-- Ultimas 3 notificaciones en home -->
    <!-- Últimas 3 notificaciones en home -->
    <div id="home-notifs-section" style="display:none">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem;padding:0 .1rem">
        <div style="display:flex;align-items:center;gap:.4rem">
          <span style="font-size:1rem"><i class="fa-solid fa-bell" style="color: #481531;"></i>
</span>
          <p style="font-size:.85rem;font-weight:700;color:var(--ts);margin:0">Últimas notificaciones</p>
          <span id="home-notif-badge" style="display:none;background:var(--vk-rose);color:#fff;border-radius:20px;font-size:.65rem;font-weight:700;padding:.1rem .45rem;min-width:18px;text-align:center"></span>
        </div>
        <span style="font-size:.78rem;color:var(--vk-rose);cursor:pointer;font-weight:600" onclick="goto('notifications')">Ver todas →</span>
      </div>
      <div id="home-notifs-list"></div>
    </div>
    <div id="last-course-strip" style="display:none" class="continue-strip" onclick="continueLastCourse()">
      <span style="font-size:1.7rem;flex-shrink:0"><i class="fa-solid fa-play" style="color: #7c3357;"></i></span>
      <div style="flex:1"><strong style="display:block;font-size:.9rem;color:var(--vk-plum);font-weight:700">Continuar donde lo dejaste</strong><span id="last-course-name" style="font-size:.8rem;color:var(--ts);margin-top:.1rem;display:block"></span></div>
      <button style="background:var(--vk-rose);color:white;border:none;padding:.5rem 1rem;border-radius:10px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;white-space:nowrap;flex-shrink:0">Ver →</button>
    </div>
    <div id="home-courses-preview"></div>
  </div>
</div>

<!-- Banner PWA instalación — flotante -->
<div id="vk-pwa-banner" style="display:none"></div>

<!-- MIS CURSOS -->
<div class="screen" id="screen-courses">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn mob-back" onclick="goto('home')"><i class="fas fa-arrow-left"></i></button>
     <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="desktop-page-header">
      <div><h2 class="desktop-page-title">Mis Cursos</h2><p class="desktop-page-sub">Toca un curso para continuar</p></div>
    </div>
    <h2 class="section-title mobile-only">Mis Cursos</h2>
    <p class="section-sub mobile-only">Toca un curso para continuar</p>
    <div class="cards-stack" id="courses-list"><div class="spinner-wrap"><div class="spinner"></div>Cargando...</div></div>
  </div>
</div>

<!-- DETALLE CURSO -->
<div class="screen" id="screen-course-detail">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn mob-back" onclick="goto('courses')"><i class="fas fa-arrow-left"></i></button>
    <span class="mob-title" id="detail-title-short">Curso</span>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="course-hero-img" id="course-hero"></div>
    <div class="course-detail-body" id="course-detail-body"></div>
  </div>
</div>

<!-- LECCIÓN -->
<div class="screen" id="screen-lesson">
  <div class="video-topbar">
    <button class="back-btn" style="color:var(--vk-pink)" onclick="backFromLesson()">← Clase</button>
    <span id="lesson-course-label" style="font-size:.85rem;color:#9a8090;font-family:'DM Sans',sans-serif;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
  </div>
  <div class="lesson-desktop-body">
    <div id="vk-video-wrapper">
      <div id="video-container" style="background:#000;width:100%;flex-shrink:0"></div>
      <button id="vk-fs-btn" onclick="vkToggleFullscreen()" title="Pantalla completa" aria-label="Pantalla completa">
        <i class="fas fa-expand" id="vk-fs-icon"></i>
      </button>
    </div>
    <div class="video-info">
      <div class="video-info-top">
        <div class="video-info-text">
          <h3 id="lesson-title">Cargando...</h3>
        </div>
        <button class="btn-done" id="btn-lesson-done" onclick="markLessonDone()">✓ Marcar como vista</button>
      </div>
      <div id="lesson-desc"></div>
      <div id="lesson-attachments"></div>
    </div>
  </div>
</div>

<!-- MODAL VISOR DE ARCHIVOS -->
<div id="vk-file-modal" class="vk-file-modal" role="dialog" aria-modal="true" aria-label="Visor de archivo" onclick="vkFileModalBgClick(event)">
  <div class="vk-file-modal-inner">
    <div class="vk-file-modal-bar">
      <span id="vk-file-modal-title" class="vk-file-modal-title"></span>
      <div class="vk-file-modal-actions">
        <a id="vk-file-modal-dl" href="#" target="_blank" rel="noopener" class="vk-file-modal-btn" title="Descargar">
          <i class="fas fa-download"></i>
        </a>
        <button class="vk-file-modal-btn" onclick="vkCloseFileModal()" title="Cerrar" aria-label="Cerrar">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
    <div id="vk-file-modal-body" class="vk-file-modal-body"></div>
  </div>
</div>

<!-- QUIZ -->
<div class="screen" id="screen-quiz">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn mob-back" onclick="backFromLesson()"><i class="fas fa-arrow-left"></i></button>
    <span class="mob-title" id="quiz-title">Quiz</span>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area" id="quiz-body" style="padding:1.25rem 1.25rem 5rem">
    <div class="spinner-wrap"><div class="spinner"></div>Cargando quiz...</div>
  </div>
</div>

<!-- EXPLORAR -->
<div class="screen" id="screen-search">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn" onclick="goto('home')">← Inicio</button>
   <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="desktop-page-header">
      <div><h2 class="desktop-page-title">Explorar Cursos</h2><p class="desktop-page-sub">Descubre cursos diseñados para tu crecimiento</p></div>
    </div>
    <div class="explore-tools">
      <div class="search-bar-wrap">
        <input class="search-bar" type="search" id="search-input" placeholder="Buscar cursos..." oninput="doSearch(this.value)">
      </div>
      <button class="filter-toggle" type="button" onclick="toggleCourseFilters()">☷ <span>Filtros</span></button>
    </div>
    <div class="mobile-cat-backdrop" id="course-cat-backdrop" onclick="closeCourseFilters()"></div>
    <div class="course-cat-panel is-empty" id="course-cat-panel"></div>
    <div class="cards-stack" id="search-results"><div class="spinner-wrap"><div class="spinner"></div>Cargando...</div></div>
  </div>
</div>

<!-- CURSO PÚBLICO -->
<div class="screen" id="screen-public-course">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn mob-back" onclick="goto('search')"><i class="fas fa-arrow-left"></i></button>
    <span class="mob-title" id="pub-title-short">Curso</span>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="course-hero-img" id="pub-course-hero"></div>
    <div class="course-detail-body" id="pub-course-body"></div>
  </div>
</div>

<!-- PRODUCTOS -->
<div class="screen" id="screen-products">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn" onclick="goto('home')">← Inicio</button>
   <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="desktop-page-header">
      <div><h2 class="desktop-page-title">Catálogo</h2><p class="desktop-page-sub">Cursos y productos de bienestar</p></div>
    </div>
    <h2 class="section-title mobile-only">Catálogo</h2>
    <p class="section-sub mobile-only">Cursos y productos de bienestar</p>
    <div class="explore-tools">
      <div class="search-bar-wrap">
        <input class="search-bar" type="search" id="product-search-input" placeholder="Buscar productos..." oninput="doProductSearch(this.value)">
      </div>
      <button class="filter-toggle" type="button" onclick="toggleProductFilters()">☷ <span>Filtros</span></button>
    </div>
    <div class="mobile-cat-backdrop" id="product-cat-backdrop" onclick="closeProductFilters()"></div>
    <div class="product-cat-panel is-empty" id="product-cat-panel"></div>
    <div style="padding:.5rem 1rem" id="products-list"><div class="spinner-wrap"><div class="spinner"></div>Cargando...</div></div>
  </div>
</div>

<!-- DETALLE PRODUCTO -->
<div class="screen" id="screen-product-detail">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn mob-back" onclick="goto('products')"><i class="fas fa-arrow-left"></i></button>
    <span class="mob-title" id="prod-title-short">Producto</span>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="pkd-layout">
      <div class="product-hero" id="prod-hero"><span style="font-size:5rem">🎓</span></div>
      <div class="product-detail-body" id="prod-body"><div class="spinner-wrap"><div class="spinner"></div></div></div>
    </div>
  </div>
</div>

<!-- PERFIL -->
<div class="screen" id="screen-profile">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn" onclick="goto('home')">← Inicio</button>
    <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="profile-header">
      <div class="profile-avatar" id="profile-avatar"><i class="fas fa-user"></i></div>
      <div class="profile-text">
        <h2 class="profile-name" id="profile-name">...</h2>
        <p class="profile-email" id="profile-email"></p>
        <div class="profile-stats">
          <div class="stat"><div class="stat-num" id="stat-courses">—</div><div class="stat-lbl">Inscritos</div></div>
          <div class="stat"><div class="stat-num" id="stat-completed">—</div><div class="stat-lbl">Completados</div></div>
          <div class="stat" onclick="goto('certificates')" style="cursor:pointer"><div class="stat-num" id="stat-certs"><i class="fa-solid fa-award"></i></div><div class="stat-lbl">Certificados</div></div>
        </div>
      </div>
    </div>
    <div class="profile-body">
      <div class="profile-row" onclick="goto('courses')"><span class="profile-row-icon"><i class="fas fa-book"></i></span><div class="profile-row-info"><strong>Mis cursos</strong><span>Ver todos</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="goto('directory-profile')"><span class="profile-row-icon"><i class="far fa-address-card"></i></span><div class="profile-row-info"><strong>Mi Directorio</strong><span>Mi perfil profesional</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="goto('certificates')"><span class="profile-row-icon"><i class="fa-solid fa-award"></i></span><div class="profile-row-info"><strong>Mis certificados</strong><span>Cursos completados</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="goto('search')"><span class="profile-row-icon"><i class="fas fa-search"></i></span><div class="profile-row-info"><strong>Explorar cursos</strong><span>Descubrir nuevos</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="goto('products')"><span class="profile-row-icon"><i class="fas fa-shopping-cart"></i></span><div class="profile-row-info"><strong>Productos</strong><span>Catálogo</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="goto('settings')"><span class="profile-row-icon"><i class="fa-solid fa-gear"></i></span><div class="profile-row-info"><strong>Editar perfil</strong><span>Nombre, teléfono, bio</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="goto('notifications')"><span class="profile-row-icon"><i class="fas fa-bell"></i></span><div class="profile-row-info"><strong>Notificaciones</strong><span>Preferencias de email</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="vkUpdateApp()"><span class="profile-row-icon"><i class="fas fa-rotate"></i></span><div class="profile-row-info"><strong>Actualizar app</strong><span id="vk-ver-label">Verificando...</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="logout()"><span class="profile-row-icon"><i class="fas fa-sign-out-alt"></i></span><div class="profile-row-info"><strong style="color:#c62828">Cerrar sesión</strong><span>Salir</span></div><span style="color:var(--tu)">›</span></div>
    </div>
  </div>
</div>

<!-- CERTIFICADOS -->
<div class="screen" id="screen-certificates">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn" onclick="goto('profile')">← Perfil</button>
     <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="desktop-page-header">
      <div><h2 class="desktop-page-title">Mis Certificados</h2><p class="desktop-page-sub">Diplomas de cursos completados</p></div>
    </div>
    <div class="cert-list-wrap" style="max-width:var(--page-max);margin:0 auto;padding:1rem 1rem 2rem">
      <div id="cert-list"><div class="spinner-wrap"><div class="spinner"></div>Cargando...</div></div>
      <div style="display:flex;justify-content:center;margin-top:1.5rem">
        <button class="btn btn-outline btn-sm" onclick="loadCertificates()" style="font-size:.78rem;max-width:220px">🔄 Actualizar certificados</button>
      </div>
    </div>
  </div>
</div>

<!-- VISOR CERTIFICADO -->
<div class="screen" id="screen-cert-viewer" style="background:#0d0508">
  <div class="video-topbar">
    <button class="back-btn" style="color:var(--vk-pink)" onclick="goto(_certFrom||'certificates')">← Volver</button>
    <span id="cv-title" style="font-family:'Cormorant Garamond',serif;font-size:.95rem;color:#fff;font-weight:600">Certificado</span>
    <button class="back-btn" style="color:var(--vk-pink)" onclick="shareCert()">Compartir</button>
  </div>
  <div class="scroll-area" style="display:flex;flex-direction:column;align-items:center;padding:1rem 1rem 5rem;background:#0d0508">

    <!-- Estado: Cargando / Generando -->
    <div id="cv-loading" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:3rem;gap:1.25rem;width:100%;max-width:360px">
      <div style="font-size:3.5rem">🎓</div>
      <div class="spinner" style="border-color:#2a0d1e;border-top-color:var(--vk-rose)"></div>
      <p id="cv-loading-text" style="color:#b890a8;font-size:.88rem;text-align:center;line-height:1.5">Cargando certificado...</p>
      <div id="cv-progress-steps" style="display:none;width:100%;gap:.5rem;flex-direction:column;align-items:flex-start">
        <div id="cv-step-1" style="display:flex;align-items:center;gap:.5rem;font-size:.78rem;color:#6b3054">
          <span class="cv-step-icon">⏳</span><span>Obteniendo datos...</span>
        </div>
        <div id="cv-step-2" style="display:flex;align-items:center;gap:.5rem;font-size:.78rem;color:#6b3054">
          <span class="cv-step-icon">⏳</span><span>Cargando renderizador...</span>
        </div>
        <div id="cv-step-3" style="display:flex;align-items:center;gap:.5rem;font-size:.78rem;color:#6b3054">
          <span class="cv-step-icon">⏳</span><span>Renderizando certificado...</span>
        </div>
        <div id="cv-step-4" style="display:flex;align-items:center;gap:.5rem;font-size:.78rem;color:#6b3054">
          <span class="cv-step-icon">⏳</span><span>Capturando imagen...</span>
        </div>
        <div id="cv-step-5" style="display:flex;align-items:center;gap:.5rem;font-size:.78rem;color:#6b3054">
          <span class="cv-step-icon">⏳</span><span>Guardando certificado...</span>
        </div>
      </div>
    </div>

    <!-- Estado: Imagen lista -->
    <div id="cv-img-wrap" style="display:none;width:100%">
      <img id="cv-img" style="width:100%;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.7)" alt="Certificado">
      <div style="display:flex;gap:.65rem;margin-top:1.1rem">
        <button id="cv-btn-pdf" onclick="downloadCertPDF()" class="btn btn-primary"
          style="flex:1;display:flex;align-items:center;justify-content:center;gap:.4rem;font-size:.82rem;padding:.7rem .5rem;min-height:44px">
          📄 Descargar PDF
        </button>
        <button id="cv-btn-jpg" onclick="downloadCertJPG()" class="btn btn-outline"
          style="flex:1;display:flex;align-items:center;justify-content:center;gap:.4rem;font-size:.82rem;padding:.7rem .5rem;min-height:44px;color:var(--vk-pink);border-color:#3d1a2d">
          🖼️ Descargar imagen
        </button>
      </div>
      <p style="text-align:center;font-size:.72rem;color:#4a2035;margin-top:.55rem">
        En móvil: mantén presionada la imagen para guardarla directamente
      </p>
    </div>

    <!-- Estado: Error / Fallback -->
    <div id="cv-fallback" style="display:none;width:100%;text-align:center;padding:1rem">
      <div style="background:#1a0812;border-radius:18px;padding:2rem;border:1.5px solid #3d1a2d">
        <p style="font-size:3rem;margin-bottom:.75rem"><i class="fa-solid fa-award"></i></p>
        <h3 id="cv-course-title" style="color:var(--vk-pink);font-family:'Cormorant Garamond',serif;margin-bottom:.5rem"></h3>
        <p style="color:#b890a8;font-size:.85rem;margin-bottom:1.25rem">Tu certificado está disponible.<br>Presiona el botón para generarlo.</p>
        <button id="cv-fallback-btn" onclick="downloadCertificate(_certCourseId)" class="btn btn-primary" style="width:100%;margin-bottom:.75rem">🔄 Reintentar generación</button>
      </div>
    </div>

  </div>
</div>


<!-- EDITAR PERFIL -->
<div class="screen" id="screen-settings">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn mob-back" onclick="goto('profile')"><i class="fas fa-arrow-left"></i></button>
    <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')"><i class="fas fa-bell"></i><span class="mhdr-notif-badge" style="display:none"></span></button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area" style="padding:1.25rem 1rem 5rem">
    <div class="desktop-page-header">
      <div><h2 class="desktop-page-title">Editar Perfil</h2></div>
    </div>
    <div class="field"><label>Nombre</label><input type="text" id="st-first" placeholder="Tu nombre"></div>
    <div class="field"><label>Apellido</label><input type="text" id="st-last" placeholder="Tu apellido"></div>
    <div class="field"><label>Teléfono</label><input type="tel" id="st-phone" placeholder="+593..."></div>
    <div class="field"><label>Ocupación</label><input type="text" id="st-job" placeholder="Contador, Diseñador..."></div>
    <div class="field"><label>Biografía</label><textarea id="st-bio" rows="3" style="width:100%;padding:.8rem;border:1.5px solid #e8d8e4;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:.93rem;resize:none;outline:none;background:#fafafa;transition:border .15s" placeholder="Sobre ti..."></textarea></div>
    <p style="font-weight:700;color:var(--ts);margin:.75rem 0 .5rem;font-size:.88rem">Cambiar contraseña</p>
    <div class="field"><label>Nueva contraseña</label><input type="password" id="st-pass1" placeholder="Mínimo 8 caracteres"></div>
    <div class="field"><label>Confirmar contraseña</label><input type="password" id="st-pass2" placeholder="Repetir contraseña"></div>
    <button class="btn btn-primary" onclick="saveProfile()">Guardar cambios</button>
    <div id="st-msg" style="text-align:center;margin-top:.75rem;font-size:.88rem"></div>
  </div>
</div>

<!-- NOTIFICACIONES -->
<div class="screen" id="screen-notifications">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn mob-back" onclick="goto('home')"><i class="fas fa-arrow-left"></i></button>
  <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="markAllNotifsRead()" title="Marcar leídas"><i class="fas fa-check-double"></i></button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area notif-scroll">

    <!-- Header -->
    <div class="notif-header">
      <div>
        <h2 class="desktop-page-title">Notificaciones</h2>
        <p class="desktop-page-sub" id="notif-header-sub">Centro de mensajes</p>
      </div>
      <div class="notif-header-actions">
        <button class="notif-action-btn notif-read-all-btn" id="notif-read-all" onclick="markAllNotifsRead()" style="display:none">
          <i class="fas fa-check-double"></i> Le&#237;das
        </button>
        <button class="notif-action-btn" id="notif-del-read" onclick="deleteReadNotifs()" style="display:none;background:rgba(198,40,40,.08);color:#c62828;border:1px solid rgba(198,40,40,.2)" title="Borrar notificaciones leídas">
          <i class="fas fa-trash-alt"></i> Borrar le&#237;das
        </button>
      </div>
    </div>

    <!-- Filtros de tipo -->
    <div class="notif-filter-row" id="notif-filter-row">
      <button class="nfilter-btn nfilter-active" data-type="" onclick="filterNotifs(this,'')">Todas</button>
      <button class="nfilter-btn" data-type="course" onclick="filterNotifs(this,'course')">&#127891; Cursos</button>
      <button class="nfilter-btn" data-type="poll" onclick="filterNotifs(this,'poll')">&#128202; Encuestas</button>
      <button class="nfilter-btn" data-type="product" onclick="filterNotifs(this,'product')">&#128722; Productos</button>
      <button class="nfilter-btn" data-type="cert" onclick="filterNotifs(this,'cert')">&#127942; Certs</button>
    </div>

    <!-- Banner permiso push -->
    <div id="notif-push-banner" style="display:none" class="notif-push-banner">
      <div class="notif-push-inner">
        <span class="notif-push-icon">&#128276;</span>
        <div class="notif-push-text">
          <strong>Activa las notificaciones push</strong>
          <p>Recibe alertas en tiempo real.</p>
        </div>
        <button onclick="activatePushFromBanner()" class="btn-push-enable">Activar</button>
        <button onclick="document.getElementById('notif-push-banner').style.display='none'" class="btn-push-dismiss">&#10005;</button>
      </div>
    </div>

    <!-- Sin leer -->
    <div id="notif-unread-section" style="display:none">
      <div class="notif-section-hd">
        <span class="notif-badge-pill" id="notif-unread-count">0</span>
        <span>Sin leer</span>
      </div>
      <div id="notif-unread-list" class="notif-list"></div>
    </div>

    <!-- Le&#237;das -->
    <div id="notif-read-section" style="display:none">
      <div class="notif-section-hd notif-section-hd--read">
        <span><i class="fas fa-check-circle" style="font-size:.8rem"></i> Le&#237;das</span>
        <div style="display:flex;gap:.5rem;align-items:center">
          <button class="notif-collapse-btn" style="color:#c62828;border-color:rgba(198,40,40,.3)" onclick="deleteAllReadNotifs()" title="Borrar todas las leídas">
            <i class="fas fa-trash-alt" style="font-size:.7rem"></i> Borrar
          </button>
          <button class="notif-collapse-btn" id="notif-collapse-btn" onclick="toggleReadSection()">Ocultar</button>
        </div>
      </div>
      <div id="notif-read-list" class="notif-list"></div>
    </div>

    <!-- Vac&#237;o -->
    <div id="notif-empty" style="display:none" class="notif-empty">
      <div class="notif-empty-icon">&#128276;</div>
      <h3>Todo al d&#237;a</h3>
      <p>No hay notificaciones por ahora.<br>Te avisaremos cuando haya algo nuevo.</p>
    </div>

    <!-- Cargando -->
    <div id="notif-loading" class="notif-loading">
      <div class="spinner"></div>
    </div>

  </div>
</div>

<!-- PAQUETES -->
<div class="screen" id="screen-bundles">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn" onclick="goto('home')">← Inicio</button>
    <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="desktop-page-header">
      <div><h2 class="desktop-page-title">Paquetes</h2><p class="desktop-page-sub">Combos de cursos especiales</p></div>
    </div>
    <div id="bundles-list"><div class="spinner-wrap"><div class="spinner"></div>Cargando...</div></div>
  </div>
</div>

<!-- DETALLE PAQUETE -->
<div class="screen" id="screen-bundle-detail">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn mob-back" onclick="goto('bundles')"><i class="fas fa-arrow-left"></i></button>
    <span class="mob-title" id="bundle-title-short">Paquete</span>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="course-hero-img" id="bundle-hero"></div>
    <div class="course-detail-body" id="bundle-body"><div class="spinner-wrap"><div class="spinner"></div></div></div>
  </div>
</div>

<!-- BOTTOM NAV (mobile only) -->


<!-- ENCUESTAS -->
<div class="screen" id="screen-polls">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn" onclick="goto('home')">← Inicio</button>
   <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="polls-hero">
      <div>
        <h2 class="polls-title">Encuestas</h2>
        <p class="polls-sub">Comparte tu opinión y ayúdanos a mejorar tu experiencia de aprendizaje.</p>
      </div>
      <div class="polls-summary" id="polls-summary"><i class="fa-solid fa-pen-to-square"></i> Cargando...</div>
    </div>
    <div id="polls-list"><div class="spinner-wrap"><div class="spinner"></div>Cargando encuestas...</div></div>
  </div>
</div>

<!-- DETALLE ENCUESTA -->
<div class="screen" id="screen-poll-detail">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn" onclick="goto('polls')">← Encuestas</button>
    <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div id="poll-detail-body" class="poll-detail-wrap"><div class="spinner-wrap"><div class="spinner"></div></div></div>
  </div>
</div>

<!-- DOCUMENTOS -->
<div class="screen" id="screen-documents">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn" onclick="goto('home')"><i class="fas fa-arrow-left"></i></button>
    <div class="mhdr-logo"><img src="icons/logo2.png" alt="VidaKushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="far fa-bell"></i><span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="far fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="desktop-page-header">
      <div><h2 class="desktop-page-title"><i class="fas fa-book-open" style="color:var(--vk-rose)"></i> Biblioteca</h2><p class="desktop-page-sub">Recursos y archivos descargables</p></div>
    </div>
    <div style="padding:0 1rem .75rem;position:sticky;top:0;z-index:5;background:var(--bg)">
      <div style="position:relative">
        <i class="fas fa-magnifying-glass" style="position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:var(--tu);font-size:.85rem;pointer-events:none"></i>
        <input id="docs-search" type="search" placeholder="Buscar en la biblioteca..." oninput="filterDocuments(this.value)"
          style="width:100%;background:var(--card);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:.6rem .75rem .6rem 2.2rem;font-size:.87rem;color:var(--td);outline:none;box-sizing:border-box">
      </div>
    </div>
    <div id="docs-categories" style="padding:0 1rem .75rem;display:flex;gap:.5rem;overflow-x:auto;scrollbar-width:none;flex-wrap:nowrap"></div>
    <div id="docs-list" style="padding:0 1rem 2rem"><div class="spinner-wrap"><div class="spinner"></div>Cargando...</div></div>
  </div>
</div>

<!-- MI DIRECTORIO — PERFIL PROFESIONAL -->
<div class="screen" id="screen-directory-profile">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn mob-back" onclick="goto('home')"><i class="fas fa-arrow-left"></i></button>
    <span class="mob-title">Mi Directorio</span>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="far fa-bell"></i><span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="far fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area">
    <div class="desktop-page-header">
      <div><h2 class="desktop-page-title"><i class="far fa-address-card" style="color:var(--vk-rose)"></i> Mi Perfil Profesional</h2><p class="desktop-page-sub">Aparece en el directorio de VidaKushala</p></div>
    </div>
    <div id="dir-form-wrap" style="max-width:1200px;margin:0 auto;padding:0 1rem 3rem">
      <div class="spinner-wrap"><div class="spinner"></div>Cargando...</div>
    </div>
  </div>
</div>

<!-- PREGUNTAS Y RESPUESTAS -->
<div class="screen" id="screen-qa">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn mob-back" id="qa-mob-back" onclick="vkQA.mobBack()"><i class="fas fa-arrow-left"></i></button>
    <div class="qa-hdr-brand" id="qa-hdr-brand" style="display:none">
      <strong>Comunidad</strong>
      <span>VidaKushala</span>
    </div>
    <div class="mhdr-logo" id="qa-hdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>

  <div class="scroll-area" id="qa-scroll-area" style="padding:0">

    <!-- Headers desktop -->
    <div class="desktop-page-header" id="qa-hdr-feed">
      <div>
        <h2 class="desktop-page-title"><i class="fas fa-comments" style="color:var(--vk-rose)"></i> Preguntas y Respuestas</h2>
        <p class="desktop-page-sub">Comunidad VidaKushala</p>
      </div>
    </div>

    <!-- ══ FEED ══ -->
    <div id="qa-view-feed">
      <!-- Hero card: título + buscador + CTA -->
      <div class="qa-hero-card">
        <h2 class="qa-hero-title">¿Qué deseas aprender o consultar hoy?</h2>
        <div class="qa-search-row">
          <div class="qa-search-wrap">
            <i class="fas fa-search qa-search-icon"></i>
            <input type="text" class="qa-search-input" id="qa-search-input" placeholder="Ejemplo: migraña, diabetes, rodilla, herpes…" oninput="vkQA.filterFeed(this.value)">
          </div>
        </div>
        <div class="qa-cta-btn-wrap">
          <button class="qa-cta-btn" onclick="vkQA.showNew()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Hacer una nueva pregunta
          </button>
        </div>
      </div>
      <!-- Filtros + sort -->
      <div class="qa-feed-controls">
        <div class="filters-label">Filtrar preguntas</div>
        <div class="filters-row" id="qa-filter-bar">
          <button class="chip active" data-filter="all"      onclick="vkQA.setFilter('all',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Todos</button>
          <button class="chip" data-filter="waiting"  onclick="vkQA.setFilter('waiting',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>Sin responder</button>
          <button class="chip" data-filter="resolved" onclick="vkQA.setFilter('resolved',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>Respondidas</button>
          <button class="chip" data-filter="teacher"  onclick="vkQA.setFilter('teacher',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.6 6.6L21 9l-5.2 4.4L17.5 21 12 17.3 6.5 21l1.7-7.6L3 9l6.4-.4z"/></svg>Profesor</button>
          <button class="chip" data-filter="none"     onclick="vkQA.setFilter('none',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>Sin respuestas</button>
        </div>
        <div class="sort-row">
          <span class="sort-count" id="qa-stats-count"><strong>0</strong> preguntas</span>
          <button class="sort-select" id="qa-sort-btn" onclick="vkQA.toggleSort(this)">
            <span>Más recientes</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
          </button>
        </div>
      </div>
      <div id="qa-list-wrap" class="qa-list-wrap">
        <div class="spinner-wrap"><div class="spinner"></div>Cargando preguntas…</div>
      </div>
    </div>

    <!-- ══ DETALLE ══ -->
    <div id="qa-view-detail" style="display:none"></div>

    <!-- ══ NUEVA PREGUNTA ══ -->
    <div id="qa-view-new" style="display:none">
      <div class="qa-nq-layout">
        <!-- Columna principal -->
        <div class="qa-nq-main">
          <div class="qa-nq-section">
            <label class="qa-nq-label" for="qa-nq-title">Título de tu pregunta</label>
            <span class="qa-nq-sublabel">Escribe en pocas palabras qué necesitas saber.</span>
            <input class="qa-nq-input" type="text" id="qa-nq-title" placeholder="Ejemplo: ¿Cómo mejorar mi digestión?" maxlength="120" oninput="vkQA.updateCharCount();vkQA.checkPublish()">
            <p class="qa-char-count" id="qa-title-count">0 / 120</p>
          </div>
          <div class="qa-nq-section">
            <label class="qa-nq-label" for="qa-nq-body">Descripción</label>
            <span class="qa-nq-sublabel">Cuenta con más detalle tu caso o tu duda.</span>
            <textarea class="qa-nq-textarea" id="qa-nq-body" rows="6" placeholder="Escribe aquí los detalles…" oninput="vkQA.checkPublish()"></textarea>
          </div>
          <div class="qa-nq-section">
            <label class="qa-nq-label">Elige un tema</label>
            <span class="qa-nq-sublabel">Selecciona la opción que mejor describe tu pregunta.</span>
            <div class="qa-topic-grid">
              <button class="qa-topic-tile" data-topic="salud" onclick="vkQA.selectTopic(this)"><div class="qa-topic-icon" style="background:rgba(201,48,48,.1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#C93030" stroke-width="1.8" stroke-linecap="round"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z"/><path d="M12 8v4M12 16h.01"/></svg></div><span class="qa-topic-name">Enfermedades</span></button>
              <button class="qa-topic-tile" data-topic="pares" onclick="vkQA.selectTopic(this)"><div class="qa-topic-icon" style="background:rgba(26,148,80,.1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1A9450" stroke-width="1.8" stroke-linecap="round"><circle cx="9" cy="12" r="5"/><circle cx="15" cy="12" r="5"/></svg></div><span class="qa-topic-name">Pares Biomagnéticos</span></button>
              <button class="qa-topic-tile" data-topic="rastreo" onclick="vkQA.selectTopic(this)"><div class="qa-topic-icon" style="background:rgba(0,151,196,.1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0097C4" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></div><span class="qa-topic-name">Rastreo</span></button>
              <button class="qa-topic-tile" data-topic="cursos" onclick="vkQA.selectTopic(this)"><div class="qa-topic-icon" style="background:rgba(123,47,190,.1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7B2FBE" stroke-width="1.8" stroke-linecap="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div><span class="qa-topic-name">Curso</span></button>
              <button class="qa-topic-tile" data-topic="equipos" onclick="vkQA.selectTopic(this)"><div class="qa-topic-icon" style="background:rgba(196,96,10,.1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#C4600A" stroke-width="1.8" stroke-linecap="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></div><span class="qa-topic-name">Equipos</span></button>
              <button class="qa-topic-tile" data-topic="casos" onclick="vkQA.selectTopic(this)"><div class="qa-topic-icon" style="background:rgba(187,22,104,.1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#BB1668" stroke-width="1.8" stroke-linecap="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/><path d="M9 12h6M9 16h4"/></svg></div><span class="qa-topic-name">Casos Clínicos</span></button>
              <button class="qa-topic-tile" data-topic="otro" onclick="vkQA.selectTopic(this)"><div class="qa-topic-icon" style="background:rgba(90,112,128,.1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5A7080" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg></div><span class="qa-topic-name">Otro</span></button>
            </div>
          </div>
          <button class="qa-btn-publish" id="qa-btn-publish" onclick="vkQA.publishQuestion()" disabled>Publicar pregunta</button>
        </div>
        <!-- Sidebar derecha (solo desktop) -->
        <div class="qa-nq-sidebar">
          <div class="qa-sidebar-card">
            <div class="qa-sidebar-card-title">UN CONSEJO</div>
            <p class="qa-consejo-text">Entre más claro sea tu título, más rápido recibirás respuesta de otros miembros o del equipo VidaKushala.</p>
          </div>
          <div class="qa-sidebar-card" style="margin-top:.75rem">
            <div class="qa-sidebar-card-title">BUENAS PREGUNTAS</div>
            <ul class="qa-tips-list">
              <li>Describe el caso con detalle</li>
              <li>Menciona qué ya has intentado</li>
              <li>Incluye edad y síntomas principales</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /scroll-area -->
</div>

<!-- CHAT IA -->
<div class="screen" id="screen-chat">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn" onclick="goto('home')">← Inicio</button>
  <div class="mhdr-logo"><img src="https://app.vidakushala.com/icons/logo2.png" alt="Vida Kushala"></div>
    <div class="mhdr-actions">
      <button class="mhdr-btn" onclick="goto('notifications')" title="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="mhdr-notif-badge" style="display:none"></span>
      </button>
      <div class="mhdr-user" onclick="toggleMobileMenu(this)">
        <div class="mhdr-avatar"><i class="fas fa-user"></i></div>
        <i class="fas fa-chevron-down mhdr-chevron"></i>
      </div>
    </div>
  </div>
  <div class="scroll-area" style="display:flex;flex-direction:column;overflow:hidden;padding-bottom:0;height:100%;">
    <div class="desktop-page-header" style="margin-left: 50px;">
      <div><h2 class="desktop-page-title">Chat IA</h2><p class="desktop-page-sub">Consulta con nuestro Asistente Inteligente</p></div>
    </div>
    <div style="flex:1;position:relative;min-height:0;overflow:hidden;">

      <!-- PANEL A: sin suscripción — oculto por defecto, JS lo muestra -->
      <div id="vk-ai-wall" style="display:none;position:absolute;inset:0;z-index:10;flex-direction:column;align-items:stretch;background:linear-gradient(160deg,#12061a 0%,#1a0828 60%,#0f1220 100%);overflow-y:auto;-webkit-overflow-scrolling:touch;">
        <!-- Wrapper centrado para desktop -->
        <!-- Wrapper: columna en móvil, fila en desktop -->
        <div style="width:100%;display:flex;flex-direction:column;flex:1;">
          <!-- Banner imagen tipo hero -->
          <div id="vk-ai-wall-img" style="width:100%;flex-shrink:0;"></div>
          <!-- Contenido debajo del banner -->
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:1.25rem 1.75rem 2rem;text-align:center;">
            <h2 id="vk-ai-wall-name" style="font-size:1.55rem;color:#f0d8e8;margin:0 0 .5rem;font-weight:700;line-height:1.25;font-family:'Cormorant Garamond',serif"></h2>
            <p id="vk-ai-wall-desc" style="color:rgba(240,216,232,.6);font-size:.88rem;max-width:300px;line-height:1.65;margin:0 auto 1.25rem"></p>
            <div id="vk-ai-wall-price" style="margin-bottom:.75rem"></div>
            <div id="vk-ai-wall-cta" style="width:100%;max-width:300px"></div>
          </div>
        </div>
      </div>

      <!-- PANEL B: chat activo — estructura original intacta -->
      <div id="vkc-chat" style="position:absolute;inset:0;display:flex;flex-direction:column;background:#343541;">
        <div id="vkc-msgs" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;scroll-behavior:smooth;font-family:system-ui,sans-serif;"></div>
        <div style="flex-shrink:0;padding:10px 15px 14px;background:#343541;border-top:1px solid rgba(255,255,255,.1);">
          <div id="vkc-inputwrap" style="display:flex;gap:.5rem;align-items:flex-end;background:#40414f;border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:.52rem .6rem .52rem 1rem;transition:border-color .2s,box-shadow .2s;">
            <textarea id="vkc-ta" rows="1" placeholder="Escribe un mensaje…"
              style="flex:1;resize:none;border:none;outline:none;font-family:system-ui,sans-serif;font-size:15px;color:#fff;background:transparent;line-height:1.5;max-height:200px;overflow-y:auto;padding:0;caret-color:#fff;"
              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();vkcSend();}"
              oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,200)+'px';"
              onfocus="var w=document.getElementById('vkc-inputwrap');w.style.borderColor='rgba(255,255,255,.3)';w.style.boxShadow='0 0 0 2px rgba(255,255,255,.08)';"
              onblur="var w=document.getElementById('vkc-inputwrap');w.style.borderColor='rgba(255,255,255,.1)';w.style.boxShadow='none';"></textarea>
            <button id="vkc-clearbtn" class="mwai-input-submit mwai-has-content" onclick="vkcClear()"
              title="Borrar conversación"
              style="background:transparent;color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.18);border-radius:7px;padding:.38rem .65rem;font-size:.78rem;cursor:pointer;flex-shrink:0;line-height:1.3;transition:all .15s;white-space:nowrap;"
              onmouseover="this.style.color='#fff';this.style.borderColor='rgba(255,255,255,.45)'"
              onmouseout="this.style.color='rgba(255,255,255,.5)';this.style.borderColor='rgba(255,255,255,.18)'"><span>Borrar</span></button>
            <button id="vkc-sendbtn" onclick="vkcSend()"
              style="background:#19c37d;color:#fff;border:none;border-radius:7px;padding:.46rem .8rem;font-size:1.1rem;cursor:pointer;flex-shrink:0;line-height:1;transition:opacity .15s;opacity:.9;"
              onmouseover="this.style.opacity='1'"
              onmouseout="this.style.opacity='.9'">➤</button>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<nav class="bottom-nav" id="bottom-nav" style="display:none">
  <button class="nav-item" id="nav-home"     onclick="goto('home')">    <span class="nav-icon"><i class="fas fa-home"></i></span><span class="nav-label">Inicio</span></button>
  <button class="nav-item" id="nav-courses"  onclick="goto('courses')"> <span class="nav-icon"><i class="fas fa-book"></i></span><span class="nav-label">Cursos</span></button>
  <button class="nav-item" id="nav-products" onclick="goto('products')"><span class="nav-icon"><i class="fas fa-shopping-cart"></i></span><span class="nav-label">Productos</span></button>
  <button class="nav-item" id="nav-polls"    onclick="goto('documents')"><span class="nav-icon"><i class="fas fa-book-open"></i></span><span class="nav-label">Biblioteca</span></button>
  <button class="nav-item" id="nav-search"   onclick="goto('search')">  <span class="nav-icon"><i class="fas fa-search"></i></span><span class="nav-label">Explorar</span></button>
  <button class="nav-item" id="nav-profile"  onclick="goto('profile')"> <span class="nav-icon"><i class="fas fa-user"></i></span><span class="nav-label">Perfil</span></button>
</nav>

<!-- AYUDA -->
<div class="overlay" id="help-overlay">
  <div class="help-sheet">
    <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.45rem;color:var(--vk-plum);margin-bottom:1.2rem;text-align:center;font-weight:700">¿Cómo te ayudamos?</h3>
    <button class="help-opt" onclick="window.open('https://vidakushala.com','_blank');closeHelp()"><span class="ho-icon">🌐</span><div class="ho-label"><strong>Ir al sitio principal</strong><span>vidakushala</span></div></button>
    <button class="help-opt" onclick="window.open('mailto:soporte@vidakushala.com');closeHelp()"><span class="ho-icon">✉️</span><div class="ho-label"><strong>Enviar correo</strong><span>soporte@vidakushala.com</span></div></button>
    <button class="btn-close-help" onclick="closeHelp()">Cerrar</button>
  </div>
</div>

<div class="toast-bar" id="toast"></div>

<!-- MODAL REGISTRO SOCIAL -->
<!-- MODAL: OLVIDÉ MI CONTRASEÑA -->
<div id="modal-forgot-password" style="display:none;position:fixed;inset:0;background:rgba(58,15,40,.75);backdrop-filter:blur(8px);z-index:900;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:22px;padding:2rem 1.5rem;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(58,15,40,.25)">
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:var(--vk-plum);text-align:center;margin-bottom:.5rem">Recuperar contraseña</h2>
    <p style="font-size:.87rem;color:var(--ts);text-align:center;margin-bottom:1.25rem">Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.</p>
    <div class="field">
      <label>Correo electrónico</label>
      <input type="email" id="forgot-email" placeholder="tu@correo.com" autocomplete="email"
        onkeydown="if(event.key==='Enter')sendForgotPassword()">
    </div>
    <div id="forgot-msg" style="min-height:1.2em;font-size:.83rem;margin-bottom:.5rem;text-align:center"></div>
    <button class="btn btn-primary" onclick="sendForgotPassword()" id="btn-forgot" style="margin-bottom:.75rem">Enviar enlace</button>
    <button onclick="closeForgotPasswordModal()" style="width:100%;background:none;border:none;color:var(--tu);font-size:.83rem;cursor:pointer;text-decoration:underline">Cancelar</button>
  </div>
</div>

<!-- PANTALLA: NUEVA CONTRASEÑA (desde enlace del correo) -->
<div id="screen-reset-password" style="display:none;position:fixed;inset:0;background:rgba(58,15,40,.88);backdrop-filter:blur(10px);z-index:960;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:24px;padding:2rem 1.5rem;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(58,15,40,.3)">
    <div style="font-size:2.5rem;text-align:center;margin-bottom:.75rem">&#x1F511;</div>
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:var(--vk-plum);text-align:center;margin-bottom:.5rem">Nueva contraseña</h2>
    <p style="font-size:.87rem;color:var(--ts);text-align:center;margin-bottom:1.25rem">Ingresa y confirma tu nueva contraseña.</p>
    <div class="field">
      <label>Nueva contraseña <span class="req-badge">*</span></label>
      <input type="password" id="reset-pass1" placeholder="Mínimo 8 caracteres" autocomplete="new-password" style="margin-bottom:0">
    </div>
    <div class="field">
      <label>Confirmar contraseña <span class="req-badge">*</span></label>
      <input type="password" id="reset-pass2" placeholder="Repetir contraseña" autocomplete="new-password" style="margin-bottom:0"
        onkeydown="if(event.key==='Enter')doResetPassword()">
    </div>
    <div id="reset-pass-msg" style="min-height:1.2em;font-size:.83rem;margin:.5rem 0;text-align:center;color:#c62828"></div>
    <button class="btn btn-primary" onclick="doResetPassword()" id="btn-reset-pass" style="margin-top:.25rem">Guardar nueva contraseña</button>
  </div>
</div>

<div id="social-register-modal" style="display:none;position:fixed;inset:0;background:rgba(58,15,40,.6);backdrop-filter:blur(6px);z-index:800;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:white;border-radius:24px;padding:2rem 1.5rem;width:100%;max-width:380px;text-align:center;box-shadow:0 20px 60px rgba(58,15,40,.3)">
    <div id="modal-avatar" style="width:72px;height:72px;border-radius:50%;background:var(--grad-hero);display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 1rem;overflow:hidden;box-shadow:0 0 0 10px rgba(196,77,138,.1)"><i class="fas fa-user"></i></div>
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.45rem;color:var(--vk-plum);font-weight:700;margin-bottom:.4rem">¡Ya casi está!</h2>
    <p style="font-size:.88rem;color:var(--ts);margin-bottom:1.4rem;line-height:1.5">Ingresa tus datos para completar el registro.<br><span style="font-size:.8rem;color:var(--tu)">Nombre y teléfono son obligatorios.</span></p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-bottom:.75rem">
      <div class="field" style="margin-bottom:0;text-align:left">
        <label for="modal-first" style="font-size:.85rem">Nombre <span class="req-badge">*</span></label>
        <input type="text" id="modal-first" placeholder="Tu nombre" autocomplete="given-name" style="margin-bottom:0" onkeydown="if(event.key==='Enter')document.getElementById('modal-last').focus()">
      </div>
      <div class="field" style="margin-bottom:0;text-align:left">
        <label for="modal-last" style="font-size:.85rem">Apellido <span class="req-badge">*</span></label>
        <input type="text" id="modal-last" placeholder="Tu apellido" autocomplete="family-name" style="margin-bottom:0" onkeydown="if(event.key==='Enter')document.getElementById('modal-phone').focus()">
      </div>
    </div>
    <div class="field" style="margin-bottom:1rem;text-align:left">
      <label for="modal-phone" style="font-size:.85rem">Teléfono <span class="req-badge">*</span></label>
      <input type="tel" id="modal-phone" placeholder="+52 55 1234 5678" autocomplete="tel" style="margin-bottom:0" onkeydown="if(event.key==='Enter')crearCuenta()">
    </div>
    <button class="btn btn-primary" id="btn-modal-register" onclick="crearCuenta()" style="margin-bottom:.75rem">Crear mi cuenta →</button>
    <button onclick="document.getElementById('social-register-modal').style.display='none';SS.clear();" style="background:none;border:none;color:var(--ts);font-family:'DM Sans',sans-serif;font-size:.85rem;cursor:pointer;text-decoration:underline">Cancelar</button>
  </div>
</div>

<!-- CUENTA PENDIENTE DE VERIFICACION -->
<div id="screen-pending-verification" style="display:none;position:fixed;inset:0;background:rgba(58,15,40,.85);backdrop-filter:blur(10px);z-index:950;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:white;border-radius:24px;padding:2rem 1.5rem;width:100%;max-width:380px;text-align:center;box-shadow:0 20px 60px rgba(58,15,40,.3)">
    <div style="font-size:3rem;margin-bottom:1rem">&#x2709;&#xFE0F;</div>
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:var(--vk-plum);font-weight:700;margin-bottom:.5rem">Verifica tu correo</h2>
    <p style="font-size:.88rem;color:var(--ts);margin-bottom:.35rem;line-height:1.6">Enviamos un enlace de activacion a:</p>
    <p style="font-weight:700;color:var(--vk-plum);margin-bottom:1.25rem;font-size:.95rem" id="pending-email-display">tu@email.com</p>
    <p style="font-size:.83rem;color:var(--tu);margin-bottom:1.5rem;line-height:1.5">Haz clic en el enlace del correo para activar tu cuenta. Revisa tu carpeta de spam si no lo encuentras.</p>
    <button class="btn btn-primary" onclick="resendActivationEmail()" id="btn-resend-activation" style="margin-bottom:.75rem">Reenviar correo de activacion</button>
    <div id="pending-msg" style="font-size:.82rem;min-height:1.2em;margin-bottom:.5rem"></div>
    <button onclick="closePendingScreen()" style="background:none;border:none;color:var(--tu);font-size:.83rem;cursor:pointer;text-decoration:underline">Cerrar e intentar mas tarde</button>
  </div>
</div>

<!-- MODAL PERMISO NOTIFICACIONES -->
<div id="push-prompt-modal" style="display:none;position:fixed;inset:0;background:rgba(58,15,40,.6);backdrop-filter:blur(8px);z-index:900;align-items:center;justify-content:center;padding:1.5rem;opacity:0;transition:opacity 0.4s ease">
  <div style="background:white;border-radius:28px;padding:2.5rem 2rem;width:100%;max-width:400px;text-align:center;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);transform:translateY(20px);transition:transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)" id="push-prompt-box">
    
    <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg, #fff0f5 0%, #ffe4e1 100%);display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 1.5rem;box-shadow:0 0 0 10px rgba(255,182,193,.2), 0 10px 20px rgba(219,112,147,.15);position:relative">
      🔔
      <div style="position:absolute;top:5px;right:5px;width:14px;height:14px;background:#e91e63;border-radius:50%;border:3px solid white"></div>
    </div>
    
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.75rem;color:var(--vk-plum);font-weight:700;margin-bottom:.75rem;line-height:1.2">No te pierdas de nada</h2>
    
    <p style="font-size:1rem;color:var(--ts);margin-bottom:1.5rem;line-height:1.6">
      Activa las notificaciones para enterarte al instante de nuevos <strong>cursos, encuestas y certificados</strong>.
    </p>
    
    <div style="display:flex;flex-direction:column;gap:10px">
      <button onclick="acceptPushPrompt()" style="background:var(--grad-accent);color:white;border:none;padding:1rem 1.5rem;border-radius:16px;font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;box-shadow:0 8px 20px rgba(196,77,138,.3);transition:transform 0.2s">¡Sí, activarlas! 🎉</button>
      <button onclick="closePushPrompt()" style="background:none;border:none;color:var(--ts);padding:.75rem;font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:600;cursor:pointer;opacity:0.8;transition:opacity 0.2s">Quizás más tarde</button>
    </div>
  </div>
</div>

<!-- Barra de salida para iOS fullscreen (fallback) -->
<div id="vk-ios-fs-bar">
  <span>📺 Pantalla completa</span>
  <button onclick="vkExitIosFs()"><i class="fas fa-compress"></i> Salir</button>
</div>

</div><!-- end desktop-main -->
</div><!-- end app -->
<script src="vk-cert-renderer.js"></script>
<script>
window.VK_ACTIVATION = {
  activated: <?php echo !empty($_vk_activated) ? 'true' : 'false'; ?>,
  token:     '<?php echo addslashes($_vk_act_token ?? ''); ?>',
  uid:       <?php echo (int)($_vk_act_uid ?? 0); ?>,
  name:      '<?php echo addslashes($_vk_act_name ?? ''); ?>',
  email:     '<?php echo addslashes($_vk_act_email ?? ''); ?>',
  avatar:    '<?php echo addslashes($_vk_act_avatar ?? ''); ?>',
  error:     '<?php echo addslashes($_vk_act_error ?? ''); ?>'
};
window.VK_RESET = {
  key:   '<?php echo addslashes($_vk_reset_key ?? ''); ?>',
  login: '<?php echo addslashes($_vk_reset_login ?? ''); ?>'
};
</script>
<script>window.VK_BUILD='<?= $_vk_ver ?>';</script>
<script src="app.js?v=<?= $_vk_ver ?>"></script>
<script>
/* ═══════════════════════════════════════════════════════════
   PWA — Service Worker + Banner de instalación
═══════════════════════════════════════════════════════════ */
(function () {

  /* ── Botón manual "Actualizar app" (Profile) ────────────── */
  window.vkUpdateApp = function() {
    if (typeof showToast === 'function') showToast('&#x1F504; Verificando actualizaciones...');
    if (window._vkSwReg) {
      window._vkSwReg.update().then(function() {
        setTimeout(function(){ window.location.reload(true); }, 800);
      }).catch(function() { window.location.reload(true); });
    } else {
      window.location.reload(true);
    }
  };

  /* ── 1. Service Worker ──────────────────────────────────── */
  if ('serviceWorker' in navigator) {
    var _vkWasControlled = !!navigator.serviceWorker.controller;

    // Protección anti-bucle: si recargamos por actualización hace menos de 15 s, no volver a recargar.
    var _vkLastReload = +(sessionStorage.getItem('vk_sw_reload') || 0);
    var _vkReloadReciente = (Date.now() - _vkLastReload) < 15000;

    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw-loader.php?v=<?= $_vk_ver ?>', { scope: '/' })
        .then(function(reg) {
          console.log('[VK SW] Registrado OK, scope:', reg.scope);
          window._vkSwReg = reg;
          setInterval(function() { reg.update(); }, 1800000);
        })
        .catch(function(err) { console.warn('[VK SW] Error:', err.message); });

      // controllerchange se dispara UNA sola vez cuando el nuevo SW toma el control.
      // Es más fiable que esperar el mensaje SW_UPDATED desde el SW.
      navigator.serviceWorker.addEventListener('controllerchange', function() {
        if (!_vkWasControlled || _vkReloadReciente) return;
        // Marcar el timestamp ANTES de recargar para que la próxima carga lo detecte.
        sessionStorage.setItem('vk_sw_reload', Date.now());
        _vkShowReloadNotice();
      });

      _vkSetVerLabel();
    });
  }

  function _vkShowReloadNotice() {
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);z-index:99999;background:#1b4332;color:#fff;border-radius:14px;padding:.75rem 1.5rem;box-shadow:0 4px 20px rgba(0,0,0,.35);font-family:\'DM Sans\',sans-serif;font-size:.9rem;white-space:nowrap';
    t.textContent = '🔄 Nueva versión instalada — actualizando...';
    document.body.appendChild(t);
    _vkSetVerLabel();
    setTimeout(function() { window.location.reload(true); }, 2000);
  }

  function _vkSetVerLabel() {
    var lbl = document.getElementById('vk-ver-label');
    if (lbl) lbl.innerHTML = '<span style="color:#1b8a4a;font-weight:600">✓ App actualizada</span>';
  }

  /* 2. Banner de instalación Android / Chrome (beforeinstallprompt) */
  /* 2. Banner de instalación Android / Chrome (beforeinstallprompt) */
  var _deferredPrompt = null;
  var _dismissed = sessionStorage.getItem('vk_pwa_dismissed');

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    _deferredPrompt = e;
    if (!_dismissed) setTimeout(showInstallBanner, 3500);
  });

  function showInstallBanner() {
    var b = document.getElementById('vk-pwa-banner');
    if (!b) return;
    if (b.dataset.dismissed) return;
    if (b.dataset.built === '1') {
      if (document.getElementById('screen-home').classList.contains('active')) b.style.display = 'flex';
      return;
    }
    if (!document.getElementById('vk-pwa-css')) {
      var s = document.createElement('style');
      s.id = 'vk-pwa-css';
      s.textContent = [
        '@keyframes vkPwaIn{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}',
        '#vk-pwa-banner{position:fixed;bottom:90px;right:0;z-index:8500;display:flex;justify-content:center;padding:0 1rem;pointer-events:none;animation:vkPwaIn .35s ease}',
        '#vk-pwa-card{pointer-events:all;width:100%;max-width:360px;background:#fce9f0;border-radius:26px;padding:1.4rem 1.3rem 1.3rem;position:relative;font-family:"DM Sans",sans-serif;box-shadow:0 12px 40px rgba(58,15,40,.22)}',
        '#vk-pwa-card .pwa-body{display:flex;align-items:flex-start;gap:.95rem}',
        '#vk-pwa-card .pwa-phone{flex-shrink:0;width:68px;height:112px;background:#fff;border-radius:14px;box-shadow:0 4px 14px rgba(58,15,40,.15);display:flex;align-items:center;justify-content:center;overflow:hidden;border:1.5px solid rgba(196,77,138,.18)}',
        '#vk-pwa-card .pwa-phone img{width:56px;height:96px;object-fit:cover;border-radius:10px}',
        '#vk-pwa-card .pwa-text{flex:1;padding-top:.05rem}',
        '#vk-pwa-card .pwa-badge{display:inline-block;font-size:.67rem;font-weight:800;letter-spacing:.09em;color:#7a3558;border:1.5px solid #c47ba0;border-radius:20px;padding:.17rem .62rem;margin-bottom:.5rem}',
        '#vk-pwa-card .pwa-title{font-size:1.12rem;font-weight:800;color:#1e0a12;line-height:1.28;margin:0 0 .38rem}',
        '#vk-pwa-card .pwa-sub{font-size:.81rem;color:#6b3050;line-height:1.5;margin:0}',
        '#vk-pwa-card .pwa-stars{position:absolute;top:.85rem;left:1rem;pointer-events:none;user-select:none;line-height:1}',
        '#vk-pwa-card .pwa-close{position:absolute;top:.75rem;right:.75rem;width:28px;height:28px;border-radius:50%;background:#f5c518;border:none;font-size:.78rem;font-weight:800;color:#3a0f28;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.15);line-height:1}',
        '#vk-pwa-install-btn{width:100%;margin-top:1rem;padding:.85rem;border:none;border-radius:14px;background:#4a1030;color:#fff;font-family:"DM Sans",sans-serif;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;box-shadow:0 6px 18px rgba(58,15,40,.28);transition:background .15s}',
        '#vk-pwa-install-btn:hover{background:#6b2447}',
        '@media(min-width:1023px){#vk-pwa-banner{bottom:2rem}}'
      ].join('');
      document.head.appendChild(s);
    }
    b.dataset.built = '1';
    b.innerHTML = '<div id="vk-pwa-card">'
      + '<div class="pwa-stars"><span style="font-size:1.2rem;color:#f5c518">✦</span> <span style="font-size:.78rem;color:#f5c518;opacity:.65">✦</span></div>'
      + '<button class="pwa-close" onclick="vkDismissPwa()" aria-label="Cerrar">&#x2715;</button>'
      + '<div class="pwa-body">'
      + '<div class="pwa-phone"><img src="/icons/icon-192.png" alt="VidaKushala"></div>'
      + '<div class="pwa-text">'
      + '<span class="pwa-badge">NUEVO</span>'
      + '<p class="pwa-title">Lleva VidaKushala<br>siempre contigo ✨</p>'
      + '<p class="pwa-sub">Instala nuestra app web (PWA) en tu dispositivo y accede más rápido.</p>'
      + '</div>'
      + '</div>'
      + '<button id="vk-pwa-install-btn">'
      + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>'
      + ' Instalar ahora'
      + '</button>'
      + '</div>';
    b.style.display = 'flex';
    document.getElementById('vk-pwa-install-btn').addEventListener('click', vkInstallPwa);
  }
  window.vkInstallPwa = function () {
    if (!_deferredPrompt) return;
    _deferredPrompt.prompt();
    _deferredPrompt.userChoice.then(function (r) {
      if (r.outcome === 'accepted') vkDismissPwa();
      _deferredPrompt = null;
    });
  };

  window.vkDismissPwa = function () {
    var b = document.getElementById('vk-pwa-banner');
    if (b) { b.style.display = 'none'; b.dataset.dismissed = '1'; }
    sessionStorage.setItem('vk_pwa_dismissed', '1');
    _dismissed = '1';
  };

  /* 3. Instrucciones de instalación para iPhone/iPad (Safari) */
  function checkIOSHint() {
    var isIOS      = /iphone|ipad|ipod/i.test(navigator.userAgent);
    var isSafari   = /safari/i.test(navigator.userAgent) && !/chrome|crios|fxios/i.test(navigator.userAgent);
    var standalone = window.navigator.standalone === true;
    var seen       = localStorage.getItem('vk_ios_hint');
    if (isIOS && isSafari && !standalone && !seen) setTimeout(showIOSHint, 5000);
  }

  function showIOSHint() {
    if (document.getElementById('vk-ios-hint')) return;
    var h = document.createElement('div');
    h.id = 'vk-ios-hint';
    h.style.cssText = [
      'position:fixed;bottom:80px;left:1rem;right:1rem;z-index:8500',
      'background:#fff;border-radius:16px;padding:1rem 1.1rem',
      'box-shadow:0 8px 32px rgba(58,15,40,.22)',
      'border:1.5px solid rgba(196,77,138,.2)',
      'font-family:"DM Sans",sans-serif',
      'animation:vkPwaSlide .3s ease'
    ].join(';');
    h.innerHTML = '<div style="display:flex;align-items:flex-start;gap:.7rem">'
      + '<img src="/icons/icon-72.png" style="width:38px;height:38px;border-radius:10px;flex-shrink:0">'
      + '<div style="flex:1">'
      + '<p style="font-weight:700;font-size:.87rem;color:#3a0f28;margin:0 0 .3rem">Instala la app en tu iPhone</p>'
      + '<p style="font-size:.78rem;color:#666;margin:0;line-height:1.5">Toca el ícono <strong>Compartir</strong> ⬆ en Safari y luego <strong>"Añadir a pantalla de inicio"</strong></p>'
      + '</div>'
      + '<button onclick="vkDismissIOS()" style="background:none;border:none;font-size:1.1rem;color:#aaa;cursor:pointer;padding:0;line-height:1;flex-shrink:0">×</button>'
      + '</div>';
    document.body.appendChild(h);
    localStorage.setItem('vk_ios_hint', '1');
  }

  window.vkDismissIOS = function () {
    var h = document.getElementById('vk-ios-hint');
    if (h) h.remove();
  };

  /* Marcar si ya está instalada como PWA */
  if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
    document.body.classList.add('is-pwa');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkIOSHint);
  } else {
    setTimeout(checkIOSHint, 500);
  }
})();
</script>
</body>
</html>