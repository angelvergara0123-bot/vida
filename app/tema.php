<?php
/* ═══════════════════════════════════════════════════════════════
   tema.php — Panel de Personalización Visual de VidaKushala
   Solo admin. Lee/escribe vk-theme.json + sirve vk-theme.css.php
═══════════════════════════════════════════════════════════════ */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$THEME_FILE = __DIR__ . '/vk-theme.json';
$UPLOAD_DIR = __DIR__ . '/uploads/theme/';

/* ── API: guardar tema ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['CONTENT_TYPE']) &&
    strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $raw  = file_get_contents('php://input');
    $data = @json_decode($raw, true);
    $out  = ['success' => false, 'message' => ''];
    if (!is_array($data)) {
        $out['message'] = 'JSON inválido'; echo json_encode($out); exit;
    }
    // Load existing
    $cur = file_exists($THEME_FILE) ? @json_decode(file_get_contents($THEME_FILE), true) : [];
    if (!is_array($cur)) $cur = [];
    // Merge only known top-level sections
    foreach (['colors','gradients','typography','shape','shadows','images'] as $s) {
        if (isset($data[$s]) && is_array($data[$s])) {
            $cur[$s] = array_merge(isset($cur[$s]) ? $cur[$s] : [], $data[$s]);
        }
    }
    $cur['_updated'] = date('Y-m-d H:i:s');
    if (!isset($cur['_version'])) $cur['_version'] = 1;
    $ok = file_put_contents($THEME_FILE, json_encode($cur, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $out['success'] = ($ok !== false);
    $out['message'] = $ok !== false ? 'Tema guardado correctamente.' : 'Error al escribir vk-theme.json.';
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

/* ── API: subir imagen ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'upload_image') {
    header('Content-Type: application/json');
    $allowed = ['logo','logo_mobile','favicon','cover'];
    $field   = isset($_POST['field']) ? $_POST['field'] : '';
    if (!in_array($field, $allowed)) { echo json_encode(['success'=>false,'message'=>'Campo inválido']); exit; }
    if (empty($_FILES['file']['tmp_name'])) { echo json_encode(['success'=>false,'message'=>'Sin archivo']); exit; }
    $file = $_FILES['file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg','ico'])) {
        echo json_encode(['success'=>false,'message'=>'Formato no permitido']); exit;
    }
    if ($file['size'] > 2*1024*1024) { echo json_encode(['success'=>false,'message'=>'Archivo demasiado grande (máx 2MB)']); exit; }
    if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0755, true);
    $fname = 'vk-' . $field . '-' . time() . '.' . $ext;
    $dest  = $UPLOAD_DIR . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success'=>false,'message'=>'Error al mover archivo']); exit;
    }
    $url = '/uploads/theme/' . $fname;
    echo json_encode(['success'=>true,'url'=>$url,'message'=>'Imagen subida']);
    exit;
}

/* ── API: obtener tema actual ─────────────────────────────── */
if (!empty($_GET['action']) && $_GET['action'] === 'get') {
    header('Content-Type: application/json');
    $t = file_exists($THEME_FILE) ? @json_decode(file_get_contents($THEME_FILE), true) : [];
    echo json_encode($t ?: new stdClass());
    exit;
}

/* ── API: reset al default ───────────────────────────────── */
if (!empty($_GET['action']) && $_GET['action'] === 'reset') {
    header('Content-Type: application/json');
    if (file_exists($THEME_FILE)) unlink($THEME_FILE);
    echo json_encode(['success'=>true,'message'=>'Tema restablecido a valores predeterminados.']);
    exit;
}

/* ── Load current theme for JS ──────────────────────────── */
$current_theme = file_exists($THEME_FILE) ? @json_decode(file_get_contents($THEME_FILE), true) : [];
if (!is_array($current_theme)) $current_theme = [];
$theme_json = json_encode($current_theme, JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panel de Temas — VidaKushala</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Cormorant+Garamond:wght@600;700&display=swap" rel="stylesheet">
<style>
/* ─── DESIGN TOKENS ────────────────────────────────────── */
:root {
  --plum:#3a0f28; --rose:#c44d8a; --pink:#e87ab8;
  --petal:#fce8f1; --card:#fff;
  --grad:linear-gradient(135deg,#c44d8a,#8b2d5a);
  --grad-h:linear-gradient(135deg,#3a0f28,#7b2560);
  --r:12px; --border:#f0e0ea;
  --td:#2d1020; --ts:#8a6070; --tu:#b899a8; --mu:#9a7080;
  --fo:#fdf8fb; --su:#fdf5f8;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,#fbf5f8,#f3e9f0);min-height:100vh;color:var(--td);}

/* ── LOGIN ────────────────────────────────────────────── */
#login-screen{position:fixed;inset:0;background:var(--grad-h);display:flex;align-items:center;justify-content:center;z-index:1000;}
.lbox{background:#fff;border-radius:22px;padding:2rem;width:320px;text-align:center;box-shadow:0 24px 80px rgba(58,15,40,.35);}
.lbox .logo{width:60px;height:60px;border-radius:18px;background:var(--grad);display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto .9rem;}
.lbox h2{font-family:'Cormorant Garamond',serif;color:var(--plum);font-size:1.5rem;margin-bottom:.3rem;}
.lbox p{color:var(--ts);font-size:.82rem;margin-bottom:1.1rem;}

/* ── HEADER ───────────────────────────────────────────── */
.t-header{background:var(--grad-h);color:#fff;padding:.8rem 1.5rem;display:flex;align-items:center;gap:.8rem;box-shadow:0 4px 20px rgba(58,15,40,.2);position:sticky;top:0;z-index:100;}
.t-back{display:flex;align-items:center;gap:.35rem;background:rgba(255,255,255,.14);color:#fff;text-decoration:none;padding:.38rem .85rem;border-radius:18px;font-size:.79rem;font-weight:700;transition:.15s;}
.t-back:hover{background:rgba(255,255,255,.24);}
.t-header h1{font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-weight:700;}
.t-header-right{margin-left:auto;display:flex;align-items:center;gap:.5rem;}
#t-admin-name{font-size:.78rem;opacity:.75;}

/* ── TOOLBAR ──────────────────────────────────────────── */
.t-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:.4rem;padding:.6rem 1.5rem;background:#fff;border-bottom:1.5px solid var(--border);box-shadow:0 1px 6px rgba(58,15,40,.06);position:sticky;top:52px;z-index:90;}
.t-toolbar-left{display:flex;gap:.4rem;flex-wrap:wrap;flex:1;}
#t-msg{font-size:.81rem;padding:.2rem .55rem;border-radius:6px;min-height:1.8em;flex:1;min-width:0;}
.t-msg-ok{background:#d4edda;color:#155724;}
.t-msg-err{background:#f8d7da;color:#721c24;}

/* ── LAYOUT ───────────────────────────────────────────── */
.t-body{max-width:1360px;margin:0 auto;padding:1.25rem 1.5rem;display:grid;grid-template-columns:300px 1fr;gap:1.25rem;align-items:start;}
@media(max-width:920px){.t-body{grid-template-columns:1fr;}}

/* ── SECTIONS NAV ─────────────────────────────────────── */
.t-nav{position:sticky;top:110px;}
.t-nav-list{display:flex;flex-direction:column;gap:.2rem;}
.t-nav-item{padding:.5rem .85rem;border-radius:9px;cursor:pointer;font-size:.83rem;font-weight:600;color:var(--ts);display:flex;align-items:center;gap:.45rem;border:none;background:transparent;font-family:inherit;width:100%;text-align:left;transition:all .15s;}
.t-nav-item:hover{background:var(--petal);color:var(--plum);}
.t-nav-item.on{background:var(--grad);color:#fff;}

/* ── CONTENT PANELS ───────────────────────────────────── */
.t-panels{min-height:600px;}
.t-panel{display:none;}
.t-panel.on{display:block;}

/* ── SECTION CARD ─────────────────────────────────────── */
.t-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:1rem;}
.t-card-header{display:flex;align-items:center;gap:.5rem;padding:.75rem 1rem;border-bottom:1px solid var(--border);background:linear-gradient(135deg,#fdf5f8,#fff);}
.t-card-header h3{font-size:.9rem;font-weight:700;color:var(--plum);}
.t-card-body{padding:.85rem 1rem;}

/* ── FORM ELEMENTS ────────────────────────────────────── */
.t-field{margin-bottom:.75rem;}
.t-field label{display:block;font-size:.74rem;color:var(--mu);margin-bottom:.2rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;}
.t-field input[type=text],.t-field input[type=number],.t-field select{width:100%;padding:.38rem .55rem;border:1.5px solid var(--border);border-radius:7px;background:var(--fo);color:var(--td);font-size:.84rem;font-family:inherit;transition:border .15s;}
.t-field input:focus,.t-field select:focus{border-color:var(--rose);outline:none;}
.t-cpair{display:flex;gap:.3rem;align-items:center;}
.t-cpair input[type=color]{width:36px;height:30px;border:none;border-radius:6px;cursor:pointer;padding:2px;flex-shrink:0;}
.t-cpair input[type=text]{flex:1;font-family:monospace;font-size:.8rem;}
.t-row{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;}
.t-row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.6rem;}
@media(max-width:600px){.t-row,.t-row3{grid-template-columns:1fr;}}
.t-section-title{font-size:.72rem;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.06em;margin:.75rem 0 .4rem;padding-bottom:.2rem;border-bottom:1px solid var(--border);}

/* ── UPLOAD ZONE ──────────────────────────────────────── */
.t-upload{border:2px dashed var(--border);border-radius:10px;padding:.75rem;text-align:center;cursor:pointer;color:var(--mu);font-size:.8rem;transition:all .18s;background:rgba(252,232,241,.15);}
.t-upload:hover{border-color:var(--rose);background:rgba(196,77,138,.04);}
.t-upload-thumb{margin-top:.5rem;max-height:80px;max-width:100%;border-radius:8px;display:none;}

/* ── PREVIEW PANE ─────────────────────────────────────── */
.t-preview-wrap{position:sticky;top:110px;}
.t-preview-header{display:flex;gap:.4rem;margin-bottom:.5rem;flex-wrap:wrap;align-items:center;}
.t-preview-mode{display:none;}
.t-preview-mode.on{display:block;}
.t-preview-btns{display:flex;gap:.3rem;}
.t-prev-btn{padding:.35rem .8rem;border-radius:8px;font-size:.78rem;font-weight:700;border:1.5px solid var(--border);background:#fff;color:var(--ts);cursor:pointer;font-family:inherit;}
.t-prev-btn.on{background:var(--grad);color:#fff;border-color:transparent;}

/* Desktop preview */
.preview-desktop{width:100%;aspect-ratio:16/9;min-height:380px;border-radius:14px;overflow:hidden;border:1.5px solid var(--border);box-shadow:0 4px 20px rgba(58,15,40,.1);position:relative;}

/* Mobile preview */
.preview-mobile{width:320px;max-width:100%;margin:0 auto;border-radius:36px;overflow:hidden;border:4px solid #1a1a1a;box-shadow:0 16px 60px rgba(0,0,0,.3);position:relative;background:#fff;}
.preview-mobile::before{content:'';display:block;height:24px;background:#1a1a1a;position:relative;z-index:10;}
.preview-mobile::after{content:'';display:block;width:60px;height:4px;background:rgba(255,255,255,.3);border-radius:2px;margin:-14px auto 0;position:relative;z-index:11;}

/* Preview iframe */
.preview-iframe{width:100%;border:none;display:block;}
.preview-desktop .preview-iframe{height:100%;min-height:380px;}
.preview-mobile .preview-iframe{height:580px;transform:scale(0.8);transform-origin:top left;width:125%;pointer-events:none;}

/* Colors swatch grid */
.t-swatch-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:.5rem;margin-bottom:.5rem;}
.t-swatch{border-radius:9px;border:1.5px solid var(--border);padding:.6rem .6rem .45rem;cursor:pointer;transition:border-color .15s,box-shadow .15s;}
.t-swatch:hover{border-color:var(--rose);}
.t-swatch-color{height:36px;border-radius:6px;margin-bottom:.3rem;transition:background .2s;}
.t-swatch-name{font-size:.65rem;color:var(--mu);font-weight:600;}
.t-swatch-val{font-size:.7rem;font-family:monospace;color:var(--ts);}

/* Gradient preview */
.t-grad-preview{height:56px;border-radius:10px;margin-bottom:.6rem;transition:background .25s;}

/* Font preview */
.t-font-preview{padding:.7rem .9rem;border-radius:9px;background:var(--su);margin-bottom:.5rem;}
.t-font-preview-heading{font-size:1.4rem;font-weight:700;margin-bottom:.2rem;}
.t-font-preview-body{font-size:.88rem;line-height:1.5;color:var(--ts);}

/* Buttons */
.btn{padding:.6rem 1.2rem;border:none;border-radius:10px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit;transition:.18s;display:inline-flex;align-items:center;gap:.35rem;}
.btn-primary{background:var(--grad);color:#fff;box-shadow:0 3px 12px rgba(196,77,138,.25);}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 5px 20px rgba(196,77,138,.35);}
.btn-secondary{background:var(--petal);color:var(--plum);}
.btn-secondary:hover{background:#f8d8eb;}
.btn-outline{background:#fff;color:var(--plum);border:1.5px solid var(--rose);}
.btn-outline:hover{background:var(--petal);}
.btn-danger{background:#fff0f0;color:#c62828;border:1px solid #ffcdd2;}
.btn-danger:hover{background:#ffebee;}
.btn-sm{padding:.35rem .75rem;font-size:.78rem;}
.btn-ghost{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.28);}
.btn-ghost:hover{background:rgba(255,255,255,.24);}
input[type=text],input[type=password]{font-family:inherit;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:10px;font-size:.87rem;width:100%;outline:none;color:var(--td);margin-bottom:.6rem;}
input[type=text]:focus,input[type=password]:focus{border-color:var(--rose);}
</style>
</head>
<body>

<!-- ── LOGIN ── -->
<div id="login-screen">
  <div class="lbox">
    <div class="logo">🎨</div>
    <h2>Panel de Temas</h2>
    <p>VidaKushala — Solo administradores</p>
    <input type="password" id="login-pass" placeholder="Contraseña de admin" onkeydown="if(event.key==='Enter')doLogin()">
    <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="doLogin()">Entrar</button>
    <p id="login-err" style="color:#c62828;font-size:.8rem;margin-top:.5rem;min-height:1.6em"></p>
  </div>
</div>

<!-- ── HEADER ── -->
<div class="t-header">
  <a href="administrar.php" class="t-back">← Panel Admin</a>
  <h1>🎨 Panel de Temas</h1>
  <div class="t-header-right">
    <span id="t-admin-name"></span>
    <button class="btn btn-ghost btn-sm" onclick="logout()">Salir</button>
  </div>
</div>

<!-- ── TOOLBAR ── -->
<div class="t-toolbar">
  <div class="t-toolbar-left">
    <button class="btn btn-primary" onclick="saveTheme()">💾 Guardar tema</button>
    <button class="btn btn-secondary btn-sm" onclick="previewTheme()">👁 Vista previa</button>
    <button class="btn btn-outline btn-sm" onclick="exportTheme()">⬇ Exportar JSON</button>
    <button class="btn btn-outline btn-sm" onclick="importTheme()">⬆ Importar JSON</button>
    <input type="file" id="import-file" accept=".json" style="display:none" onchange="handleImport(this)">
    <button class="btn btn-danger btn-sm" onclick="resetTheme()">↩ Restablecer</button>
  </div>
  <div id="t-msg"></div>
</div>

<!-- ── MAIN ── -->
<div class="t-body">

  <!-- ── NAV LEFT ── -->
  <div class="t-nav">
    <div class="t-nav-list">
      <button class="t-nav-item on" onclick="tPanel('identity')">🏷 Identidad Visual</button>
      <button class="t-nav-item"    onclick="tPanel('colors')">🎨 Colores Globales</button>
      <button class="t-nav-item"    onclick="tPanel('gradients')">🌈 Degradados</button>
      <button class="t-nav-item"    onclick="tPanel('typography')">🔤 Tipografía</button>
      <button class="t-nav-item"    onclick="tPanel('shape')">📐 Diseño General</button>
      <button class="t-nav-item"    onclick="tPanel('shadows')">💡 Sombras</button>
      <button class="t-nav-item"    onclick="tPanel('preview')">📱 Vista Previa</button>
    </div>
    <div style="margin-top:1.25rem;padding:.85rem;background:#fff;border-radius:12px;border:1px solid var(--border)">
      <div style="font-size:.73rem;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem">Estado del tema</div>
      <div id="theme-status" style="font-size:.8rem;color:var(--ts)">Cargando...</div>
    </div>
  </div>

  <!-- ── PANELS ── -->
  <div class="t-panels">

    <!-- ═══ IDENTIDAD VISUAL ═══ -->
    <div class="t-panel on" id="panel-identity">
      <div class="t-card">
        <div class="t-card-header"><span>🏷</span><h3>Identidad Visual</h3></div>
        <div class="t-card-body">
          <div class="t-row">
            <div>
              <div class="t-section-title">Logo principal (sidebar)</div>
              <div class="t-field">
                <label>URL de imagen</label>
                <input type="text" id="logo_url" placeholder="https://... o /ruta/logo.png" oninput="tSet('images','logo_url',this.value)">
              </div>
              <div class="t-upload" id="upload-logo-zone" onclick="trigUpload('logo')">📤 Subir logo principal<br><small>PNG/SVG recomendado — 40×40px</small>
                <img id="thumb-logo" class="t-upload-thumb">
              </div>
            </div>
            <div>
              <div class="t-section-title">Logo móvil (top bar)</div>
              <div class="t-field">
                <label>URL de imagen</label>
                <input type="text" id="logo_mobile_url" placeholder="https://... o /ruta/logo-m.png" oninput="tSet('images','logo_mobile_url',this.value)">
              </div>
              <div class="t-upload" id="upload-logo-mob-zone" onclick="trigUpload('logo_mobile')">📤 Subir logo móvil<br><small>Cuadrado — 28×28px</small>
                <img id="thumb-logo_mobile" class="t-upload-thumb">
              </div>
            </div>
          </div>
          <div class="t-row" style="margin-top:.75rem">
            <div>
              <div class="t-section-title">Favicon / ícono</div>
              <div class="t-field">
                <label>URL de imagen</label>
                <input type="text" id="favicon_url" placeholder="/icons/icon-96.png" oninput="tSet('images','favicon_url',this.value)">
              </div>
              <div class="t-upload" onclick="trigUpload('favicon')">📤 Subir favicon<br><small>ICO/PNG — 96×96px</small>
                <img id="thumb-favicon" class="t-upload-thumb">
              </div>
            </div>
            <div>
              <div class="t-section-title">Imagen de portada/cover</div>
              <div class="t-field">
                <label>URL de imagen</label>
                <input type="text" id="cover_url" placeholder="https://..." oninput="tSet('images','cover_url',this.value)">
              </div>
              <div class="t-upload" onclick="trigUpload('cover')">📤 Subir imagen de portada<br><small>JPG/PNG — 1200×630px</small>
                <img id="thumb-cover" class="t-upload-thumb">
              </div>
            </div>
          </div>
          <input type="file" id="upload-file" accept="image/*" style="display:none" onchange="handleUpload(this)">
          <div style="margin-top:.85rem;padding:.75rem;background:var(--su);border-radius:9px;border:1px solid var(--border)">
            <div class="t-section-title" style="margin-top:0">Letra del ícono del sidebar (fallback cuando no hay logo)</div>
            <div class="t-field" style="margin-bottom:0">
              <label>Carácter (1 letra)</label>
              <input type="text" id="sidebar_icon_letter" maxlength="2" style="max-width:80px;text-align:center;font-size:1.1rem;font-weight:700" oninput="tSet('images','sidebar_icon_letter',this.value);updateSidebarPreview()">
            </div>
          </div>
        </div>
      </div>
    </div><!-- /panel-identity -->

    <!-- ═══ COLORES GLOBALES ═══ -->
    <div class="t-panel" id="panel-colors">
      <div class="t-card">
        <div class="t-card-header"><span>🎨</span><h3>Colores Globales</h3></div>
        <div class="t-card-body">
          <div class="t-section-title">Paleta de marca</div>
          <div class="t-swatch-grid" id="swatch-brand"></div>
          <div class="t-section-title">Texto</div>
          <div class="t-swatch-grid" id="swatch-text"></div>
          <div class="t-section-title">Fondos y bordes</div>
          <div class="t-swatch-grid" id="swatch-bg"></div>
          <div class="t-section-title">Fondo del body</div>
          <div class="t-row">
            <div class="t-field">
              <label>Body fondo móvil</label>
              <div class="t-cpair">
                <input type="color" id="body_bg_mobile" oninput="tSet('colors','body_bg_mobile',this.value);syncHex('body_bg_mobile','body_bg_mobile_hex')">
                <input type="text" id="body_bg_mobile_hex" placeholder="#f0eaf2" oninput="tSet('colors','body_bg_mobile',this.value);syncColor('body_bg_mobile_hex','body_bg_mobile')">
              </div>
            </div>
            <div class="t-field">
              <label>Body fondo escritorio</label>
              <div class="t-cpair">
                <input type="color" id="body_bg_desktop" oninput="tSet('colors','body_bg_desktop',this.value);syncHex('body_bg_desktop','body_bg_desktop_hex')">
                <input type="text" id="body_bg_desktop_hex" placeholder="#f5eef3" oninput="tSet('colors','body_bg_desktop',this.value);syncColor('body_bg_desktop_hex','body_bg_desktop')">
              </div>
            </div>
          </div>
          <div class="t-field">
            <label>Fondo del sidebar (escritorio)</label>
            <div class="t-cpair">
              <input type="color" id="sidebar_bg" oninput="tSet('colors','sidebar_bg',this.value);syncHex('sidebar_bg','sidebar_bg_hex')">
              <input type="text" id="sidebar_bg_hex" placeholder="#3a0f28" oninput="tSet('colors','sidebar_bg',this.value);syncColor('sidebar_bg_hex','sidebar_bg')">
            </div>
          </div>
        </div>
      </div>
    </div><!-- /panel-colors -->

    <!-- ═══ DEGRADADOS ═══ -->
    <div class="t-panel" id="panel-gradients">
      <div class="t-card">
        <div class="t-card-header"><span>🌈</span><h3>Degradados</h3></div>
        <div class="t-card-body">
          <div class="t-section-title">Degradado hero (fondos principales)</div>
          <div class="t-grad-preview" id="grad-hero-preview"></div>
          <div class="t-row3">
            <div class="t-field"><label>Inicio</label><div class="t-cpair"><input type="color" id="hero_from" oninput="tSet('gradients','hero_from',this.value);updateGradPreviews()"><input type="text" id="hero_from_hex" oninput="tSet('gradients','hero_from',this.value);syncColor('hero_from_hex','hero_from');updateGradPreviews()"></div></div>
            <div class="t-field"><label>Medio</label><div class="t-cpair"><input type="color" id="hero_mid" oninput="tSet('gradients','hero_mid',this.value);updateGradPreviews()"><input type="text" id="hero_mid_hex" oninput="tSet('gradients','hero_mid',this.value);syncColor('hero_mid_hex','hero_mid');updateGradPreviews()"></div></div>
            <div class="t-field"><label>Final</label><div class="t-cpair"><input type="color" id="hero_to" oninput="tSet('gradients','hero_to',this.value);updateGradPreviews()"><input type="text" id="hero_to_hex" oninput="tSet('gradients','hero_to',this.value);syncColor('hero_to_hex','hero_to');updateGradPreviews()"></div></div>
          </div>
          <div class="t-section-title" style="margin-top:.85rem">Degradado accent (botones, acciones)</div>
          <div class="t-grad-preview" id="grad-accent-preview"></div>
          <div class="t-row">
            <div class="t-field"><label>Inicio</label><div class="t-cpair"><input type="color" id="accent_from" oninput="tSet('gradients','accent_from',this.value);updateGradPreviews()"><input type="text" id="accent_from_hex" oninput="tSet('gradients','accent_from',this.value);syncColor('accent_from_hex','accent_from');updateGradPreviews()"></div></div>
            <div class="t-field"><label>Final</label><div class="t-cpair"><input type="color" id="accent_to" oninput="tSet('gradients','accent_to',this.value);updateGradPreviews()"><input type="text" id="accent_to_hex" oninput="tSet('gradients','accent_to',this.value);syncColor('accent_to_hex','accent_to');updateGradPreviews()"></div></div>
          </div>
        </div>
      </div>
    </div><!-- /panel-gradients -->

    <!-- ═══ TIPOGRAFÍA ═══ -->
    <div class="t-panel" id="panel-typography">

      <!-- Vista previa en tiempo real -->
      <div class="t-card">
        <div class="t-card-header"><span>👁</span><h3>Vista previa en tiempo real</h3></div>
        <div class="t-card-body" style="padding:.5rem .85rem .85rem">
          <div id="font-preview-box" style="border:1px solid var(--border);border-radius:10px;overflow:hidden">
            <div style="background:linear-gradient(135deg,var(--plum),#8b2d5a);padding:1rem 1.2rem">
              <div id="fp-heading" style="font-size:1.6rem;font-weight:700;color:#fff;margin-bottom:.2rem;transition:font-family .3s">Título Principal — Encabezados</div>
              <div id="fp-subheading" style="font-size:1rem;font-weight:400;color:rgba(255,255,255,.75);font-style:italic;transition:font-family .3s">Subtítulo elegante con estilo serif</div>
            </div>
            <div style="padding:1rem 1.2rem;background:#fff">
              <div id="fp-body" style="font-size:.9rem;line-height:1.65;color:#3a2030;margin-bottom:.65rem;transition:font-family .3s">Texto de cuerpo regular. Aprende yoga, meditación y bienestar integral con VidaKushala. Transforma tu vida con nuestros cursos.</div>
              <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <button id="fp-btn" style="background:linear-gradient(135deg,#c44d8a,#8b2d5a);color:#fff;border:none;padding:.45rem 1rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:default;transition:font-family .3s">Botón principal</button>
                <button id="fp-btn2" style="background:var(--petal);color:var(--plum);border:none;padding:.45rem 1rem;border-radius:8px;font-size:.82rem;font-weight:600;cursor:default;transition:font-family .3s">Botón secundario</button>
                <input id="fp-input" placeholder="Campo de formulario" readonly style="border:1.5px solid var(--border);border-radius:8px;padding:.38rem .75rem;font-size:.82rem;color:var(--td);background:var(--fo);max-width:180px;transition:font-family .3s">
              </div>
            </div>
          </div>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.65rem;align-items:center">
            <button class="btn btn-secondary btn-sm" onclick="loadFontPreview()">↺ Cargar fuente en vista previa</button>
            <span id="fp-status" style="font-size:.77rem;color:var(--mu)"></span>
          </div>
        </div>
      </div>

      <!-- Fuente de encabezados -->
      <div class="t-card">
        <div class="t-card-header"><span>🅷</span><h3>Fuente de Títulos y Encabezados</h3></div>
        <div class="t-card-body">
          <div id="active-font-heading-badge" style="display:none;margin-bottom:.65rem;padding:.3rem .7rem;background:linear-gradient(135deg,var(--petal),#fff);border:1px solid var(--rose);border-radius:20px;font-size:.75rem;color:var(--plum);font-weight:600;display:inline-block"></div>
          <div class="t-field">
            <label>Fuente activa para h1–h4, títulos de sección</label>
            <select id="font_heading" onchange="tSet('typography','font_heading',this.value);updateFontPreview();loadFontPreview()">
              <optgroup label="── Serif Elegantes ──">
                <option value="Cormorant Garamond">Cormorant Garamond</option>
                <option value="Playfair Display">Playfair Display</option>
                <option value="Merriweather">Merriweather</option>
                <option value="Lora">Lora</option>
                <option value="Crimson Text">Crimson Text</option>
                <option value="Libre Baskerville">Libre Baskerville</option>
                <option value="Georgia">Georgia (sistema)</option>
                <option value="Times New Roman">Times New Roman (sistema)</option>
              </optgroup>
              <optgroup label="── Sans-Serif Modernas ──">
                <option value="DM Sans">DM Sans</option>
                <option value="Inter">Inter</option>
                <option value="Montserrat">Montserrat</option>
                <option value="Poppins">Poppins</option>
                <option value="Raleway">Raleway</option>
                <option value="Josefin Sans">Josefin Sans</option>
              </optgroup>
            </select>
          </div>
          <div id="fp-heading-sample" style="margin-top:.5rem;padding:.65rem 1rem;border-radius:8px;background:var(--su);border:1px solid var(--border)">
            <div style="font-size:1.5rem;font-weight:700;color:var(--plum);transition:font-family .3s" id="fp-h-sample-text">El camino hacia la paz interior</div>
            <div style="font-size:.72rem;color:var(--mu);margin-top:.15rem">Así se verá el título con esta fuente</div>
          </div>
        </div>
      </div>

      <!-- Fuente de cuerpo -->
      <div class="t-card">
        <div class="t-card-header"><span>🅱</span><h3>Fuente de Cuerpo de Texto</h3></div>
        <div class="t-card-body">
          <div id="active-font-body-badge" style="display:none;margin-bottom:.65rem;padding:.3rem .7rem;background:linear-gradient(135deg,var(--petal),#fff);border:1px solid var(--rose);border-radius:20px;font-size:.75rem;color:var(--plum);font-weight:600;display:inline-block"></div>
          <div class="t-field">
            <label>Fuente activa para párrafos, etiquetas, botones y formularios</label>
            <select id="font_body" onchange="tSet('typography','font_body',this.value);updateFontPreview();loadFontPreview()">
              <optgroup label="── Sans-Serif Modernas ──">
                <option value="DM Sans">DM Sans</option>
                <option value="Inter">Inter</option>
                <option value="Roboto">Roboto</option>
                <option value="Open Sans">Open Sans</option>
                <option value="Poppins">Poppins</option>
                <option value="Lato">Lato</option>
                <option value="Nunito">Nunito</option>
                <option value="Montserrat">Montserrat</option>
                <option value="Raleway">Raleway</option>
                <option value="Source Sans Pro">Source Sans Pro</option>
              </optgroup>
              <optgroup label="── Sistema (sin descarga) ──">
                <option value="Arial">Arial</option>
                <option value="Helvetica">Helvetica</option>
                <option value="Verdana">Verdana</option>
              </optgroup>
            </select>
          </div>
          <div id="fp-body-sample" style="margin-top:.5rem;padding:.65rem 1rem;border-radius:8px;background:var(--su);border:1px solid var(--border)">
            <div style="font-size:.9rem;line-height:1.65;color:var(--td);transition:font-family .3s" id="fp-b-sample-text">Aprende yoga, meditación y bienestar. Transforma tu vida con nuestros cursos y talleres.</div>
          </div>
        </div>
      </div>

      <!-- Fuente de UI (opcional) -->
      <div class="t-card">
        <div class="t-card-header"><span>🖱</span><h3>Fuente de UI / Botones / Formularios <span style="font-size:.7em;font-weight:400;opacity:.6">(opcional)</span></h3></div>
        <div class="t-card-body">
          <div class="t-field" style="margin-bottom:.5rem">
            <label>Si está vacío, hereda la fuente de cuerpo</label>
            <select id="font_ui" onchange="tSet('typography','font_ui',this.value);updateFontPreview();loadFontPreview()">
              <option value="">(Igual que cuerpo)</option>
              <optgroup label="── Sans-Serif ──">
                <option value="DM Sans">DM Sans</option>
                <option value="Inter">Inter</option>
                <option value="Roboto">Roboto</option>
                <option value="Open Sans">Open Sans</option>
                <option value="Poppins">Poppins</option>
                <option value="Lato">Lato</option>
                <option value="Nunito">Nunito</option>
                <option value="Montserrat">Montserrat</option>
              </optgroup>
            </select>
          </div>
        </div>
      </div>

      <!-- Tamaños y métricas -->
      <div class="t-card">
        <div class="t-card-header"><span>📏</span><h3>Tamaños y Métricas</h3></div>
        <div class="t-card-body">
          <div class="t-row">
            <div class="t-field">
              <label>Tamaño base del texto (px o rem)</label>
              <input type="text" id="font_size_base" placeholder="16px" oninput="tSet('typography','font_size_base',this.value)">
              <div style="font-size:.7rem;color:var(--mu);margin-top:.15rem">Afecta a todo el body. Ej: 16px, 1rem, 15px</div>
            </div>
            <div class="t-field">
              <label>Altura de línea</label>
              <input type="text" id="line_height" placeholder="1.6" oninput="tSet('typography','line_height',this.value)">
              <div style="font-size:.7rem;color:var(--mu);margin-top:.15rem">Sin unidad. Ej: 1.5, 1.6, 1.75</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Info de aplicación -->
      <div style="padding:.75rem 1rem;background:linear-gradient(135deg,rgba(196,77,138,.08),rgba(107,36,71,.04));border:1px solid rgba(196,77,138,.2);border-radius:12px;font-size:.78rem;color:var(--ts);line-height:1.6">
        <div style="font-weight:700;color:var(--plum);margin-bottom:.35rem">ℹ️ Cómo se aplican las fuentes</div>
        <div>Los cambios se aplican mediante <strong>reglas CSS con prioridad máxima</strong> que sobrescriben todos los estilos hardcodeados. Las fuentes de Google Fonts se cargan automáticamente — no necesitas editar código.</div>
        <div style="margin-top:.35rem"><strong>Scope de aplicación:</strong> frontend público, versión móvil y desktop, componentes dinámicos generados por JavaScript.</div>
        <div style="margin-top:.35rem;color:var(--mu)"><strong>Fuentes de sistema</strong> (Georgia, Arial, Helvetica, Verdana) no requieren descarga y cargará instantáneamente.</div>
      </div>

    </div><!-- /panel-typography -->

    <!-- ═══ DISEÑO GENERAL ═══ -->
    <div class="t-panel" id="panel-shape">
      <div class="t-card">
        <div class="t-card-header"><span>📐</span><h3>Diseño General — Bordes, Formas y Espaciados</h3></div>
        <div class="t-card-body">
          <div class="t-section-title">Radio de esquinas</div>
          <div class="t-row">
            <div class="t-field">
              <label>Radio estándar (--r)</label>
              <div style="display:flex;gap:.5rem;align-items:center">
                <input type="range" id="radius-range" min="0" max="32" step="1" style="flex:1;accent-color:var(--rose)" oninput="this.nextElementSibling.value=this.value+'px';tSet('shape','radius',this.value+'px');updateShapePreview()">
                <input type="text" id="radius" style="max-width:70px" placeholder="14px" oninput="tSet('shape','radius',this.value);updateShapePreview()">
              </div>
            </div>
            <div class="t-field">
              <label>Radio grande (--rl)</label>
              <div style="display:flex;gap:.5rem;align-items:center">
                <input type="range" id="radius_large-range" min="0" max="50" step="1" style="flex:1;accent-color:var(--rose)" oninput="this.nextElementSibling.value=this.value+'px';tSet('shape','radius_large',this.value+'px');updateShapePreview()">
                <input type="text" id="radius_large" style="max-width:70px" placeholder="20px" oninput="tSet('shape','radius_large',this.value);updateShapePreview()">
              </div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;margin:.75rem 0">
            <div id="sp1" style="height:48px;background:var(--petal,#fce8f1);border:1.5px solid var(--border,#ead4e0);transition:border-radius .2s;display:flex;align-items:center;justify-content:center;font-size:.7rem;color:var(--mu)">Card</div>
            <div id="sp2" style="height:48px;background:linear-gradient(135deg,#c44d8a,#8b2d5a);transition:border-radius .2s;display:flex;align-items:center;justify-content:center;font-size:.7rem;color:#fff">Btn</div>
            <div id="sp3" style="height:48px;background:#fff;border:1.5px solid var(--border,#ead4e0);transition:border-radius .2s;display:flex;align-items:center;justify-content:center;font-size:.7rem;color:var(--mu)">Input</div>
            <div id="sp4" style="height:48px;background:linear-gradient(135deg,#3a0f28,#6b2447,#8b2d5a);transition:border-radius .2s;display:flex;align-items:center;justify-content:center;font-size:.7rem;color:#fff">Hero</div>
          </div>
          <div class="t-section-title">Ancho del sidebar</div>
          <div class="t-field">
            <label>Ancho del sidebar de escritorio</label>
            <div style="display:flex;gap:.5rem;align-items:center">
              <input type="range" id="sidebar_w-range" min="180" max="320" step="10" style="flex:1;accent-color:var(--rose)" oninput="this.nextElementSibling.value=this.value+'px';tSet('shape','sidebar_w',this.value+'px')">
              <input type="text" id="sidebar_w" style="max-width:80px" placeholder="240px" oninput="tSet('shape','sidebar_w',this.value)">
            </div>
          </div>
        </div>
      </div>
    </div><!-- /panel-shape -->

    <!-- ═══ SOMBRAS ═══ -->
    <div class="t-panel" id="panel-shadows">
      <div class="t-card">
        <div class="t-card-header"><span>💡</span><h3>Sombras</h3></div>
        <div class="t-card-body">
          <div class="t-section-title">Sombra estándar (--sh) — tarjetas principales</div>
          <div id="shadow-demo1" style="height:72px;background:#fff;border-radius:14px;margin-bottom:.65rem;transition:box-shadow .3s;display:flex;align-items:center;justify-content:center;font-size:.8rem;color:var(--mu);border:1px solid var(--border)">Vista previa de sombra</div>
          <div class="t-field">
            <label>Valor CSS de box-shadow</label>
            <input type="text" id="sh" placeholder="0 8px 40px rgba(107,36,71,.14)" oninput="tSet('shadows','sh',this.value);updateShadowPreviews()">
          </div>
          <div class="t-section-title" style="margin-top:.75rem">Sombra suave (--shs) — elementos secundarios</div>
          <div id="shadow-demo2" style="height:72px;background:#fff;border-radius:14px;margin-bottom:.65rem;transition:box-shadow .3s;display:flex;align-items:center;justify-content:center;font-size:.8rem;color:var(--mu);border:1px solid var(--border)">Vista previa de sombra suave</div>
          <div class="t-field">
            <label>Valor CSS de box-shadow</label>
            <input type="text" id="shs" placeholder="0 2px 12px rgba(107,36,71,.08)" oninput="tSet('shadows','shs',this.value);updateShadowPreviews()">
          </div>
          <div style="margin-top:.65rem;font-size:.78rem;color:var(--ts);padding:.65rem;background:var(--su);border-radius:9px;border:1px solid var(--border)">
            Sintaxis: <code>offset-x offset-y blur-radius [spread] color</code>. Puedes usar <code>rgba()</code> para semitransparencia.
          </div>
        </div>
      </div>
    </div><!-- /panel-shadows -->

    <!-- ═══ VISTA PREVIA ═══ -->
    <div class="t-panel" id="panel-preview">
      <div class="t-card">
        <div class="t-card-header"><span>📱</span><h3>Vista Previa</h3></div>
        <div class="t-card-body">
          <div class="t-preview-header">
            <div class="t-preview-btns">
              <button class="t-prev-btn on" onclick="setPrevMode('desktop',this)">🖥 Desktop</button>
              <button class="t-prev-btn"    onclick="setPrevMode('mobile',this)">📱 Móvil</button>
            </div>
            <button class="btn btn-outline btn-sm" style="margin-left:auto" onclick="reloadPreview()">↺ Actualizar</button>
          </div>
          <div class="t-preview-mode on" id="prev-desktop">
            <div class="preview-desktop">
              <iframe id="iframe-desktop" class="preview-iframe" src="index.php" title="Vista previa desktop"></iframe>
            </div>
          </div>
          <div class="t-preview-mode" id="prev-mobile">
            <div class="preview-mobile">
              <iframe id="iframe-mobile" class="preview-iframe" src="index.php" title="Vista previa móvil"></iframe>
            </div>
          </div>
          <div style="margin-top:.75rem;padding:.65rem;background:var(--su);border-radius:9px;border:1px solid var(--border);font-size:.78rem;color:var(--ts)">
            La vista previa carga la app real. Para ver los cambios, primero guarda el tema y luego haz clic en <strong>Actualizar</strong>.
          </div>
        </div>
      </div>
    </div><!-- /panel-preview -->

  </div><!-- /t-panels -->
</div><!-- /t-body -->

<script>
/* ════════════════════════════════════════════════
   VARIABLES GLOBALES + AUTH
   ════════════════════════════════════════════════ */
var API = (window.location.hostname==='localhost'||window.location.hostname==='127.0.0.1')
        ? 'http://localhost:8080/wp-json/vk/v1'
        : 'https://vidakushala.com/wp-json/vk/v1';
var TOK = '';
var THEME = <?= $theme_json ?>;

/* ── Defaults (sin ningún tema guardado) ─────── */
var DEFAULTS = {
  colors:{
    vk_deep:'#3a0f28', vk_plum:'#6b2447', vk_rose:'#c44d8a',
    vk_pink:'#e27bb5', vk_blush:'#f0c2da', vk_cream:'#fdf6f9', vk_petal:'#fce8f1',
    td:'#1c0e14', tm:'#4a2438', ts:'#7d5a6d', tu:'#b899a8',
    border:'#ead4e0', border_light:'#f3e5ec', card:'#ffffff',
    body_bg_mobile:'#f0eaf2', body_bg_desktop:'#f5eef3', sidebar_bg:'#3a0f28'
  },
  gradients:{hero_from:'#3a0f28',hero_mid:'#6b2447',hero_to:'#8b2d5a',accent_from:'#c44d8a',accent_to:'#a83574'},
  typography:{font_heading:'Cormorant Garamond',font_body:'DM Sans',font_ui:'',font_size_base:'16px',line_height:'1.6'},
  shape:{radius:'14px',radius_large:'20px',sidebar_w:'240px'},
  shadows:{sh:'0 8px 40px rgba(107,36,71,.14)',shs:'0 2px 12px rgba(107,36,71,.08)'},
  images:{logo_url:'',logo_mobile_url:'',favicon_url:'',cover_url:'',sidebar_icon_letter:'V'}
};

function tG(section, key) {
  if (THEME[section] && THEME[section][key] !== undefined && THEME[section][key] !== '') return THEME[section][key];
  return DEFAULTS[section] ? (DEFAULTS[section][key] || '') : '';
}
function tSet(section, key, val) {
  if (!THEME[section]) THEME[section] = {};
  THEME[section][key] = val;
  autoUpdateCSS();
}

/* ════════════════════════════════════════════════
   AUTH
   ════════════════════════════════════════════════ */
function doLogin() {
  var pass = document.getElementById('login-pass').value.trim();
  var err  = document.getElementById('login-err');
  if (!pass) { err.textContent = 'Ingresa tu contraseña'; return; }
  err.textContent = '⏳ Verificando...';
  fetch(API + '/login', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:'admin',password:pass})})
    .then(function(r){return r.json().then(function(d){return {ok:r.ok,d:d};});})
    .then(function(x){
      if (x.ok && x.d.token) {
        TOK = x.d.token;
        return fetch(API + '/check-admin?vk_token=' + encodeURIComponent(TOK)).then(function(r){return r.json().then(function(d){return {ok:r.ok,d:d};});});
      }
      err.textContent = '✗ ' + (x.d.message || 'Credenciales incorrectas'); return null;
    })
    .then(function(x){
      if (!x) return;
      if (x.ok && x.d.is_admin) {
        try { localStorage.setItem('vk_tok',TOK); } catch(e){}
        document.getElementById('t-admin-name').textContent = x.d.display_name || 'Admin';
        document.getElementById('login-screen').style.display = 'none';
        onReady();
      } else {
        err.textContent = '✗ ' + (x.d.message || 'Solo administradores');
        TOK = '';
      }
    })
    .catch(function(e){err.textContent = '✗ Error: ' + e.message;});
}
function logout() { TOK=''; try{localStorage.removeItem('vk_tok');}catch(e){} location.reload(); }
window.addEventListener('load', function() {
  var raw = localStorage.getItem('vk_tok');
  try { var p = JSON.parse(raw); if (typeof p==='string') raw=p; } catch(e) {}
  if (raw && raw.length > 10) {
    TOK = raw;
    fetch(API + '/check-admin?vk_token=' + encodeURIComponent(TOK))
      .then(function(r){return r.json().then(function(d){return {ok:r.ok,d:d};});})
      .then(function(x){
        if (x.ok && x.d.is_admin) {
          document.getElementById('t-admin-name').textContent = x.d.display_name || 'Admin';
          document.getElementById('login-screen').style.display = 'none';
          onReady();
        } else { TOK=''; }
      }).catch(function(){TOK='';});
  }
});

/* ════════════════════════════════════════════════
   INIT
   ════════════════════════════════════════════════ */
function onReady() {
  populateAllFields();
  updateAllPreviews();
  loadFontPreview();
  updateStatus();
}

/* ════════════════════════════════════════════════
   NAV
   ════════════════════════════════════════════════ */
function tPanel(name) {
  document.querySelectorAll('.t-panel').forEach(function(p){p.classList.remove('on');});
  document.querySelectorAll('.t-nav-item').forEach(function(b){b.classList.remove('on');});
  var p = document.getElementById('panel-' + name);
  if (p) p.classList.add('on');
  event.currentTarget.classList.add('on');
}

/* ════════════════════════════════════════════════
   HELPERS: color sync
   ════════════════════════════════════════════════ */
function syncHex(colorId, hexId) {
  var c = document.getElementById(colorId);
  var h = document.getElementById(hexId);
  if (c && h) h.value = c.value;
}
function syncColor(hexId, colorId) {
  var h = document.getElementById(hexId);
  var c = document.getElementById(colorId);
  if (h && c && /^#[0-9a-fA-F]{6}$/.test(h.value)) c.value = h.value;
}

/* ════════════════════════════════════════════════
   POPULATE FIELDS FROM THEME
   ════════════════════════════════════════════════ */
var SWATCHES_BRAND = [
  {key:'vk_deep',label:'Deep (--vk-deep)'},
  {key:'vk_plum',label:'Plum (--vk-plum)'},
  {key:'vk_rose',label:'Rose (--vk-rose)'},
  {key:'vk_pink',label:'Pink (--vk-pink)'},
  {key:'vk_blush',label:'Blush (--vk-blush)'},
  {key:'vk_cream',label:'Cream (--vk-cream)'},
  {key:'vk_petal',label:'Petal (--vk-petal)'},
];
var SWATCHES_TEXT = [
  {key:'td',label:'Texto dark (--td)'},
  {key:'tm',label:'Texto med (--tm)'},
  {key:'ts',label:'Texto sec (--ts)'},
  {key:'tu',label:'Texto muted (--tu)'},
];
var SWATCHES_BG = [
  {key:'border',label:'Borde (--border)'},
  {key:'border_light',label:'Borde claro'},
  {key:'card',label:'Card bg (--card)'},
];

function buildSwatches(containerId, list, section) {
  var el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = '';
  list.forEach(function(s) {
    var val = tG(section, s.key);
    var div = document.createElement('div');
    div.className = 't-swatch';
    div.id = 'swatch-' + s.key;
    div.innerHTML = '<div class="t-swatch-color" id="swatch-c-'+s.key+'" style="background:'+val+'"></div>'
      + '<div class="t-swatch-name">'+s.label+'</div>'
      + '<div style="display:flex;gap:.25rem;margin-top:.3rem;align-items:center">'
      + '<input type="color" style="width:26px;height:22px;border:none;cursor:pointer;padding:1px;border-radius:4px;flex-shrink:0" value="'+val+'" id="c-'+s.key+'" oninput="swatchChange(\''+s.key+'\',\''+section+'\',this.value)">'
      + '<input type="text" style="flex:1;font-family:monospace;font-size:.65rem;padding:.2rem .3rem;border:1px solid var(--border);border-radius:4px;background:var(--fo);min-width:0" value="'+val+'" id="ch-'+s.key+'" oninput="swatchChangeHex(\''+s.key+'\',\''+section+'\',this.value)">'
      + '</div>';
    el.appendChild(div);
  });
}
function swatchChange(key, section, val) {
  tSet(section, key, val);
  var h = document.getElementById('ch-' + key);
  if (h) h.value = val;
  var c = document.getElementById('swatch-c-' + key);
  if (c) c.style.background = val;
}
function swatchChangeHex(key, section, val) {
  if (/^#[0-9a-fA-F]{6}$/.test(val)) {
    tSet(section, key, val);
    var c = document.getElementById('c-' + key);
    if (c) c.value = val;
    var sc = document.getElementById('swatch-c-' + key);
    if (sc) sc.style.background = val;
  }
}

function populateAllFields() {
  buildSwatches('swatch-brand', SWATCHES_BRAND, 'colors');
  buildSwatches('swatch-text',  SWATCHES_TEXT,  'colors');
  buildSwatches('swatch-bg',    SWATCHES_BG,    'colors');

  /* Extra color fields */
  setColorField('body_bg_mobile',  'body_bg_mobile_hex',  tG('colors','body_bg_mobile'));
  setColorField('body_bg_desktop', 'body_bg_desktop_hex', tG('colors','body_bg_desktop'));
  setColorField('sidebar_bg',      'sidebar_bg_hex',      tG('colors','sidebar_bg'));

  /* Gradients */
  setColorField('hero_from',  'hero_from_hex',  tG('gradients','hero_from'));
  setColorField('hero_mid',   'hero_mid_hex',   tG('gradients','hero_mid'));
  setColorField('hero_to',    'hero_to_hex',    tG('gradients','hero_to'));
  setColorField('accent_from','accent_from_hex',tG('gradients','accent_from'));
  setColorField('accent_to',  'accent_to_hex',  tG('gradients','accent_to'));

  /* Typography */
  setSelectVal('font_heading', tG('typography','font_heading'));
  setSelectVal('font_body',    tG('typography','font_body'));
  setSelectVal('font_ui',      tG('typography','font_ui'));
  setInputVal('font_size_base', tG('typography','font_size_base'));
  setInputVal('line_height',    tG('typography','line_height'));
  // Mostrar badges de fuente activa
  showActiveFontBadge('heading', tG('typography','font_heading'));
  showActiveFontBadge('body',    tG('typography','font_body'));

  /* Shape */
  var r  = parseInt(tG('shape','radius'))       || 14;
  var rl = parseInt(tG('shape','radius_large'))  || 20;
  var sw = parseInt(tG('shape','sidebar_w'))     || 240;
  setInputVal('radius',       r  + 'px');
  setInputVal('radius_large', rl + 'px');
  setInputVal('sidebar_w',    sw + 'px');
  setRangeVal('radius-range',       r);
  setRangeVal('radius_large-range', rl);
  setRangeVal('sidebar_w-range',    sw);

  /* Shadows */
  setInputVal('sh',  tG('shadows','sh'));
  setInputVal('shs', tG('shadows','shs'));

  /* Images */
  setInputVal('logo_url',           tG('images','logo_url'));
  setInputVal('logo_mobile_url',    tG('images','logo_mobile_url'));
  setInputVal('favicon_url',        tG('images','favicon_url'));
  setInputVal('cover_url',          tG('images','cover_url'));
  setInputVal('sidebar_icon_letter',tG('images','sidebar_icon_letter') || 'V');
  ['logo','logo_mobile','favicon','cover'].forEach(function(k){
    var url = tG('images', k + '_url');
    if (url) showThumb(k, url);
  });
}
function setColorField(colorId, hexId, val) {
  var c = document.getElementById(colorId);
  var h = document.getElementById(hexId);
  if (c && val) c.value = val;
  if (h && val) h.value = val;
}
function setInputVal(id, val) { var el=document.getElementById(id); if(el&&val!==undefined&&val!=='')el.value=val; }
function setSelectVal(id, val) { var el=document.getElementById(id); if(el&&val){for(var i=0;i<el.options.length;i++){if(el.options[i].value===val){el.selectedIndex=i;break;}}} }
function setRangeVal(id, val) { var el=document.getElementById(id); if(el) el.value=val; }

/* ════════════════════════════════════════════════
   UPDATE PREVIEWS
   ════════════════════════════════════════════════ */
function updateGradPreviews() {
  var hp = document.getElementById('grad-hero-preview');
  var ap = document.getElementById('grad-accent-preview');
  if (hp) hp.style.background = 'linear-gradient(135deg,' + tG('gradients','hero_from') + ',' + tG('gradients','hero_mid') + ',' + tG('gradients','hero_to') + ')';
  if (ap) ap.style.background = 'linear-gradient(135deg,' + tG('gradients','accent_from') + ',' + tG('gradients','accent_to') + ')';
}
/* ── Mapeo Google Fonts → parámetros de peso ── */
var GF_MAP = {
  'Cormorant Garamond': 'ital,wght@0,400;0,600;0,700;1,400',
  'Playfair Display':   'ital,wght@0,400;0,600;0,700;1,400',
  'Merriweather':       'ital,wght@0,300;0,400;0,700;1,400',
  'Lora':               'ital,wght@0,400;0,600;0,700;1,400',
  'Crimson Text':       'ital,wght@0,400;0,600;1,400',
  'Libre Baskerville':  'ital,wght@0,400;0,700;1,400',
  'DM Sans':            'wght@300;400;500;600;700',
  'Inter':              'wght@300;400;500;600;700',
  'Montserrat':         'wght@300;400;500;600;700',
  'Poppins':            'wght@300;400;500;600;700',
  'Raleway':            'wght@300;400;500;600;700',
  'Josefin Sans':       'wght@300;400;600;700',
  'Roboto':             'ital,wght@0,300;0,400;0,700;1,400',
  'Open Sans':          'ital,wght@0,300;0,400;0,600;0,700;1,400',
  'Lato':               'ital,wght@0,300;0,400;0,700;1,400',
  'Nunito':             'wght@300;400;600;700',
  'Source Sans Pro':    'wght@300;400;600;700',
};
var GF_SYSTEM = ['Georgia','Times New Roman','Arial','Helvetica','Verdana','Trebuchet MS'];
var _loadedFonts = {};

function loadGoogleFont(name, callback) {
  if (!name || GF_SYSTEM.indexOf(name) !== -1 || _loadedFonts[name]) {
    if (callback) callback(name);
    return;
  }
  _loadedFonts[name] = true;
  var w = GF_MAP[name] || 'wght@400;700';
  var url = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(name) + ':' + w + '&display=swap';
  var link = document.createElement('link');
  link.rel = 'stylesheet'; link.href = url;
  link.onload = function() { if (callback) callback(name); };
  link.onerror = function() { if (callback) callback(name); };
  document.head.appendChild(link);
}

function showActiveFontBadge(type, fontName) {
  var badge = document.getElementById('active-font-' + type + '-badge');
  if (!badge) return;
  badge.style.display = 'inline-block';
  badge.textContent = '✅ Activa: ' + (fontName || 'DM Sans');
}

function updateFontPreview() {
  var hf = tG('typography','font_heading') || 'Cormorant Garamond';
  var bf = tG('typography','font_body')    || 'DM Sans';
  var uf = tG('typography','font_ui')      || bf;

  // Vista previa principal
  var fpH = document.getElementById('fp-heading');
  var fpSH = document.getElementById('fp-subheading');
  var fpB = document.getElementById('fp-body');
  var fpBtn = document.getElementById('fp-btn');
  var fpBtn2 = document.getElementById('fp-btn2');
  var fpInp = document.getElementById('fp-input');
  if (fpH)   fpH.style.fontFamily   = "'" + hf + "', serif";
  if (fpSH)  fpSH.style.fontFamily  = "'" + hf + "', serif";
  if (fpB)   fpB.style.fontFamily   = "'" + bf + "', sans-serif";
  if (fpBtn) fpBtn.style.fontFamily = "'" + uf + "', sans-serif";
  if (fpBtn2)fpBtn2.style.fontFamily= "'" + uf + "', sans-serif";
  if (fpInp) fpInp.style.fontFamily = "'" + uf + "', sans-serif";

  // Muestras individuales
  var hSample = document.getElementById('fp-h-sample-text');
  var bSample = document.getElementById('fp-b-sample-text');
  if (hSample) hSample.style.fontFamily = "'" + hf + "', serif";
  if (bSample) bSample.style.fontFamily = "'" + bf + "', sans-serif";

  // Badges
  showActiveFontBadge('heading', hf);
  showActiveFontBadge('body',    bf);

  // Estado
  var st = document.getElementById('fp-status');
  if (st) st.textContent = hf + ' / ' + bf;
}

function loadFontPreview() {
  var hf = tG('typography','font_heading') || 'Cormorant Garamond';
  var bf = tG('typography','font_body')    || 'DM Sans';
  var uf = tG('typography','font_ui')      || bf;
  var st = document.getElementById('fp-status');
  if (st) st.textContent = '⏳ Cargando fuentes…';
  var loaded = 0;
  var total  = [hf, bf, uf].filter(function(f,i,a){ return a.indexOf(f)===i && GF_SYSTEM.indexOf(f)===-1; }).length;
  if (!total) { updateFontPreview(); if (st) st.textContent = '✅ Fuentes de sistema cargadas'; return; }
  function onLoad() { loaded++; if (loaded >= total) { updateFontPreview(); if (st) st.textContent = '✅ Fuentes cargadas'; } }
  [hf, bf, uf].forEach(function(f,i,a){ if (a.indexOf(f)===i) loadGoogleFont(f, onLoad); });
}
function updateShapePreview() {
  var r  = tG('shape','radius');
  var rl = tG('shape','radius_large');
  ['sp1','sp3'].forEach(function(id){ var el=document.getElementById(id); if(el) el.style.borderRadius=r; });
  ['sp2'].forEach(function(id){ var el=document.getElementById(id); if(el) el.style.borderRadius='50px'; });
  ['sp4'].forEach(function(id){ var el=document.getElementById(id); if(el) el.style.borderRadius='0 0 '+rl+' '+rl; });
}
function updateShadowPreviews() {
  var d1 = document.getElementById('shadow-demo1');
  var d2 = document.getElementById('shadow-demo2');
  if (d1) d1.style.boxShadow = tG('shadows','sh');
  if (d2) d2.style.boxShadow = tG('shadows','shs');
}
function updateSidebarPreview() {}
function updateAllPreviews() {
  updateGradPreviews();
  updateFontPreview();
  updateShapePreview();
  updateShadowPreviews();
}
function autoUpdateCSS() { /* live CSS update via a style tag */ updateAllPreviews(); }
function updateStatus() {
  var el = document.getElementById('theme-status');
  if (!el) return;
  var updated = (THEME._updated || '').replace('T',' ').substring(0,16);
  el.innerHTML = updated
    ? '✅ Tema activo<br><small style="color:var(--tu)">Actualizado: ' + updated + '</small>'
    : '⬜ Tema predeterminado<br><small style="color:var(--tu)">Sin cambios guardados</small>';
}

/* ════════════════════════════════════════════════
   SAVE
   ════════════════════════════════════════════════ */
function msg(text, type) {
  var el = document.getElementById('t-msg');
  if (!el) return;
  el.textContent = text;
  el.className = type === 'ok' ? 't-msg-ok' : 't-msg-err';
  clearTimeout(el._t);
  el._t = setTimeout(function(){ el.textContent=''; el.className=''; }, 6000);
}
async function saveTheme() {
  try {
    var r = await fetch('tema.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(THEME)});
    var d = await r.json();
    if (d.success) { msg('✅ ' + d.message, 'ok'); THEME._updated = new Date().toLocaleString(); updateStatus(); }
    else msg('✗ ' + d.message, 'err');
  } catch(e) { msg('✗ Error: ' + e.message, 'err'); }
}

/* ════════════════════════════════════════════════
   PREVIEW
   ════════════════════════════════════════════════ */
function previewTheme() { tPanel('preview'); }
function reloadPreview() {
  ['iframe-desktop','iframe-mobile'].forEach(function(id){ var f=document.getElementById(id); if(f){var s=f.src;f.src='';f.src=s;} });
}
function setPrevMode(mode, btn) {
  document.querySelectorAll('.t-preview-mode').forEach(function(el){el.classList.remove('on');});
  document.querySelectorAll('.t-prev-btn').forEach(function(b){b.classList.remove('on');});
  var pm = document.getElementById('prev-' + mode);
  if (pm) pm.classList.add('on');
  if (btn) btn.classList.add('on');
}

/* ════════════════════════════════════════════════
   EXPORT / IMPORT
   ════════════════════════════════════════════════ */
function exportTheme() {
  var blob = new Blob([JSON.stringify(THEME, null, 2)], {type:'application/json'});
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'vk-theme-' + new Date().toISOString().slice(0,10) + '.json';
  a.click();
  URL.revokeObjectURL(a.href);
}
function importTheme() { document.getElementById('import-file').click(); }
function handleImport(input) {
  var file = input.files[0]; if (!file) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    try {
      var data = JSON.parse(e.target.result);
      THEME = data;
      populateAllFields();
      updateAllPreviews();
      msg('✅ Tema importado. Guarda para aplicarlo.', 'ok');
    } catch(ex) { msg('✗ JSON inválido', 'err'); }
  };
  reader.readAsText(file);
  input.value = '';
}

/* ════════════════════════════════════════════════
   RESET
   ════════════════════════════════════════════════ */
async function resetTheme() {
  if (!confirm('¿Restablecer al tema predeterminado? Se perderán todos los cambios guardados.')) return;
  try {
    var r = await fetch('tema.php?action=reset');
    var d = await r.json();
    if (d.success) {
      THEME = {};
      populateAllFields();
      updateAllPreviews();
      msg('✅ ' + d.message, 'ok');
      updateStatus();
    } else msg('✗ ' + d.message, 'err');
  } catch(e) { msg('✗ Error: ' + e.message, 'err'); }
}

/* ════════════════════════════════════════════════
   UPLOAD IMAGES
   ════════════════════════════════════════════════ */
var _uploadField = '';
function trigUpload(field) {
  _uploadField = field;
  document.getElementById('upload-file').click();
}
function handleUpload(input) {
  var file = input.files[0]; if (!file) return;
  var fd = new FormData();
  fd.append('action', 'upload_image');
  fd.append('field',  _uploadField);
  fd.append('file',   file);
  fetch('tema.php', {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      if (d.success) {
        tSet('images', _uploadField + '_url', d.url);
        var inp = document.getElementById(_uploadField + '_url');
        if (inp) inp.value = d.url;
        showThumb(_uploadField, d.url);
        msg('✅ Imagen subida: ' + d.url, 'ok');
      } else msg('✗ ' + d.message, 'err');
    })
    .catch(function(e){ msg('✗ Error al subir: ' + e.message, 'err'); });
  input.value = '';
}
function showThumb(field, url) {
  var thumb = document.getElementById('thumb-' + field);
  if (thumb) { thumb.src = url; thumb.style.display='block'; }
}
</script>
</body>
</html>
