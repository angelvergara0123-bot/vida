<?php
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');  // sin cache para que cambios sean inmediatos
header('X-Content-Type-Options: nosniff');

$cfg_file = __DIR__ . '/vk-theme.json';
$cfg = file_exists($cfg_file) ? @json_decode(file_get_contents($cfg_file), true) : null;
if (!is_array($cfg)) $cfg = [];

/* ── helpers ─────────────────────────────────────────────────── */
function vkt($cfg, $section, $key, $default) {
    $v = isset($cfg[$section][$key]) ? trim($cfg[$section][$key]) : '';
    return ($v !== '') ? $v : $default;
}
function vktColor($cfg, $key, $default) { return vkt($cfg, 'colors', $key, $default); }
function vktGrad($cfg, $key, $default)  { return vkt($cfg, 'gradients', $key, $default); }
function vktType($cfg, $key, $default)  { return vkt($cfg, 'typography', $key, $default); }
function vktShape($cfg, $key, $default) { return vkt($cfg, 'shape', $key, $default); }
function vktShadow($cfg, $key, $default){ return vkt($cfg, 'shadows', $key, $default); }

/* ── colors ─────────────────────────────────────────────────── */
$vk_deep        = vktColor($cfg, 'vk_deep',         '#3a0f28');
$vk_plum        = vktColor($cfg, 'vk_plum',         '#6b2447');
$vk_rose        = vktColor($cfg, 'vk_rose',         '#c44d8a');
$vk_pink        = vktColor($cfg, 'vk_pink',         '#e27bb5');
$vk_blush       = vktColor($cfg, 'vk_blush',        '#f0c2da');
$vk_cream       = vktColor($cfg, 'vk_cream',        '#fdf6f9');
$vk_petal       = vktColor($cfg, 'vk_petal',        '#fce8f1');
$td             = vktColor($cfg, 'td',               '#1c0e14');
$tm             = vktColor($cfg, 'tm',               '#4a2438');
$ts             = vktColor($cfg, 'ts',               '#7d5a6d');
$tu             = vktColor($cfg, 'tu',               '#b899a8');
$border         = vktColor($cfg, 'border',           '#ead4e0');
$border_light   = vktColor($cfg, 'border_light',     '#f3e5ec');
$card           = vktColor($cfg, 'card',             '#ffffff');
$body_bg_mobile = vktColor($cfg, 'body_bg_mobile',   '#f0eaf2');
$body_bg_desk   = vktColor($cfg, 'body_bg_desktop',  '#f5eef3');
$sidebar_bg     = vktColor($cfg, 'sidebar_bg',       '#3a0f28');

/* ── gradients ─────────────────────────────────────────────── */
$hero_from   = vktGrad($cfg, 'hero_from',   '#3a0f28');
$hero_mid    = vktGrad($cfg, 'hero_mid',    '#6b2447');
$hero_to     = vktGrad($cfg, 'hero_to',     '#8b2d5a');
$acc_from    = vktGrad($cfg, 'accent_from', '#c44d8a');
$acc_to      = vktGrad($cfg, 'accent_to',   '#a83574');

/* ── typography ────────────────────────────────────────────── */
$font_heading    = htmlspecialchars(vktType($cfg, 'font_heading',    'Cormorant Garamond'));
$font_body       = htmlspecialchars(vktType($cfg, 'font_body',       'DM Sans'));
$font_ui         = htmlspecialchars(vktType($cfg, 'font_ui',         '')); // vacío = hereda font_body
$font_size       = vktType($cfg, 'font_size_base', '16px');
$font_size_h     = vktType($cfg, 'font_size_headings', '');            // vacío = no override
$line_height     = vktType($cfg, 'line_height',    '1.6');
// UI font: usa font_body si font_ui está vacío
$font_ui_eff = ($font_ui !== '') ? $font_ui : $font_body;

/* ── shape ─────────────────────────────────────────────────── */
$radius       = vktShape($cfg, 'radius',       '14px');
$radius_large = vktShape($cfg, 'radius_large', '20px');
$sidebar_w    = vktShape($cfg, 'sidebar_w',    '240px');

/* ── shadows ───────────────────────────────────────────────── */
$sh  = vktShadow($cfg, 'sh',  '0 8px 40px rgba(107,36,71,.14)');
$shs = vktShadow($cfg, 'shs', '0 2px 12px rgba(107,36,71,.08)');

/* ── images ────────────────────────────────────────────────── */
$images = isset($cfg['images']) && is_array($cfg['images']) ? $cfg['images'] : [];
$logo_url        = isset($images['logo_url'])        ? trim($images['logo_url'])        : '';
$logo_mobile_url = isset($images['logo_mobile_url']) ? trim($images['logo_mobile_url']) : '';
$favicon_url     = isset($images['favicon_url'])     ? trim($images['favicon_url'])     : '';
$sidebar_letter  = isset($images['sidebar_icon_letter']) ? htmlspecialchars(trim($images['sidebar_icon_letter'])) : 'V';
?>
/* ═══════════════════════════════════════════════════
   vk-theme.css.php — Variables de tema dinámicas
   Generado por tema.php | VidaKushala
═══════════════════════════════════════════════════ */

:root {
  /* ─ Paleta principal ─ */
  --vk-deep:        <?= $vk_deep ?>;
  --vk-plum:        <?= $vk_plum ?>;
  --vk-rose:        <?= $vk_rose ?>;
  --vk-pink:        <?= $vk_pink ?>;
  --vk-blush:       <?= $vk_blush ?>;
  --vk-cream:       <?= $vk_cream ?>;
  --vk-petal:       <?= $vk_petal ?>;

  /* ─ Texto ─ */
  --td:             <?= $td ?>;
  --tm:             <?= $tm ?>;
  --ts:             <?= $ts ?>;
  --tu:             <?= $tu ?>;

  /* ─ Bordes y fondos ─ */
  --border:         <?= $border ?>;
  --border-light:   <?= $border_light ?>;
  --card:           <?= $card ?>;

  /* ─ Gradientes ─ */
  --grad-hero:      linear-gradient(145deg, <?= $hero_from ?>, <?= $hero_mid ?>, <?= $hero_to ?>);
  --grad-accent:    linear-gradient(135deg, <?= $acc_from ?>, <?= $acc_to ?>);

  /* ─ Tipografía ─ */
  --font-heading:   '<?= $font_heading ?>', serif;
  --font-body:      '<?= $font_body ?>', sans-serif;
  --font-ui:        '<?= $font_ui_eff ?>', sans-serif;
  --font-size-base: <?= $font_size ?>;
  --line-height:    <?= $line_height ?>;

  /* ─ Formas ─ */
  --r:              <?= $radius ?>;
  --rl:             <?= $radius_large ?>;
  --sidebar-w:      <?= $sidebar_w ?>;

  /* ─ Sombras ─ */
  --sh:             <?= $sh ?>;
  --shs:            <?= $shs ?>;
}

/* ─ Fondos de body responsivos ─ */
@media (max-width: 1024px) {
  body { background: <?= $body_bg_mobile ?>; }
  #app { background: <?= $body_bg_mobile ?>; }
}
@media (min-width: 1023px) {
  body { background: <?= $body_bg_desk ?>; }
  #desktop-sidebar { background: <?= $sidebar_bg ?>; }
}

/* ═══════════════════════════════════════════════════
   Overrides de tipografía — cubren todos los hardcodes
   en style.css, vk-custom.css e inline styles de index.php
   Usan !important para tener prioridad sobre inline styles.
═══════════════════════════════════════════════════ */

/* ─ Fuente de cuerpo — global ─ */
html, body,
input, select, textarea,
button, .btn, .btn-small, .btn-done, .btn-whatsapp,
.btn-mercado-pago, .btn-paypal, .btn-close-help,
.nav-item, .help-opt,
.field input, .field textarea, .field select,
.tab, .card, .label {
  font-family: '<?= $font_body ?>', sans-serif !important;
}

/* ─ Fuente de encabezados/títulos ─ */
h1, h2, h3, h4, h5, h6,
.section-title,
.login-title,
.login-logo span,
.login-brand-block h1,
.home-hero h2,
.course-detail-body h2,
.product-detail-body h2,
.profile-name,
.stat-num,
.notif-empty h3,
.desktop-page-title,
.poll-title,
.card-title {
  font-family: '<?= $font_heading ?>', serif !important;
}

<?php if ($font_ui_eff !== $font_body): ?>
/* ─ Fuente de UI/botones personalizada ─ */
button, .btn, .btn-small, .btn-done,
.tab, .nav-item, input, select, textarea,
.field input, .field textarea {
  font-family: '<?= $font_ui_eff ?>', sans-serif !important;
}
<?php endif; ?>

/* ─ Tamaño base ─ */
<?php if ($font_size && $font_size !== '16px'): ?>
html { font-size: <?= $font_size ?> !important; }
<?php endif; ?>

/* ─ Tamaño de encabezados ─ */
<?php if ($font_size_h): ?>
h1, h2, h3, h4, h5, h6,
.section-title, .desktop-page-title {
  font-size: <?= $font_size_h ?> !important;
}
<?php endif; ?>

/* ─ Altura de línea ─ */
<?php if ($line_height && $line_height !== '1.6'): ?>
body { line-height: <?= $line_height ?> !important; }
<?php endif; ?>

<?php if ($logo_url): ?>
/* ─ Logo personalizado ─ */
.sidebar-logo-icon::before { content: ''; }
.sidebar-logo-icon {
  background-image: url('<?= htmlspecialchars($logo_url) ?>');
  background-size: cover;
  background-position: center;
  font-size: 0;
}
<?php endif; ?>

<?php if ($logo_mobile_url): ?>
/* ─ Logo móvil personalizado ─ */
.top-bar-logo-icon::before,
.top-bar-logo-icon { font-size: 0; }
.top-bar-logo-icon {
  background-image: url('<?= htmlspecialchars($logo_mobile_url) ?>');
  background-size: cover;
  background-position: center;
}
<?php endif; ?>
