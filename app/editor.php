<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Editor de Certificados — VidaKushala</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Cormorant+Garamond:wght@600;700&display=swap" rel="stylesheet">
<style>
:root {
  --vk-plum:#3a0f28; --vk-rose:#c44d8a; --vk-pink:#e87ab8;
  --vk-petal:#fce8f1; --card:#fff;
  --grad-accent:linear-gradient(135deg,#c44d8a,#8b2d5a);
  --grad-hero:linear-gradient(135deg,#3a0f28,#7b2560);
  --r:14px; --shs:0 2px 12px rgba(58,15,40,.08);
  --border:#f0e0ea; --td:#2d1020; --ts:#8a6070; --tu:#b899a8;
  --bd:#e0cfd8; --ca:#fff; --fo:#fdf8fb; --mu:#9a7080;
  --su:#fdf5f8; --tx:#2d1020;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,#fbf5f8,#f3e9f0);min-height:100vh;color:var(--td);}

/* ── LOGIN ── */
#login-screen{position:fixed;inset:0;background:var(--grad-hero);display:flex;align-items:center;justify-content:center;z-index:1000;}
.login-box{background:#fff;border-radius:24px;padding:2.25rem;width:340px;text-align:center;box-shadow:0 24px 80px rgba(58,15,40,.35);}
.login-box .logo{width:64px;height:64px;border-radius:20px;background:var(--grad-accent);display:flex;align-items:center;justify-content:center;font-size:1.75rem;margin:0 auto 1rem;}
.login-box h2{font-family:'Cormorant Garamond',serif;color:var(--vk-plum);font-size:1.6rem;margin-bottom:.35rem;}
.login-box p{color:var(--ts);font-size:.85rem;margin-bottom:1.25rem;}

/* ── HEADER ── */
.ed-header{background:var(--grad-hero);color:#fff;padding:.85rem 1.5rem;display:flex;align-items:center;gap:.85rem;box-shadow:0 4px 20px rgba(58,15,40,.2);position:sticky;top:0;z-index:100;}
.ed-back{display:flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.14);color:#fff;text-decoration:none;padding:.4rem .9rem;border-radius:20px;font-size:.8rem;font-weight:600;transition:.15s;}
.ed-back:hover{background:rgba(255,255,255,.24);}
.ed-header h1{font-family:'Cormorant Garamond',serif;font-size:1.25rem;font-weight:700;}
.ed-header-right{margin-left:auto;display:flex;align-items:center;gap:.6rem;}
#ed-admin-name{font-size:.8rem;opacity:.75;}

/* ── BUTTONS ── */
.btn{padding:.6rem 1.2rem;border:none;border-radius:10px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit;transition:.18s;display:inline-flex;align-items:center;gap:.35rem;}
.btn-primary{background:var(--grad-accent);color:#fff;box-shadow:0 3px 12px rgba(196,77,138,.28);}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 5px 20px rgba(196,77,138,.36);}
.btn-secondary{background:var(--vk-petal);color:var(--vk-plum);}
.btn-secondary:hover{background:#f8d8eb;}
.btn-outline{background:#fff;color:var(--vk-plum);border:1.5px solid var(--vk-rose);}
.btn-outline:hover{background:var(--vk-petal);}
.btn-ghost{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.28);}
.btn-ghost:hover{background:rgba(255,255,255,.24);}
.btn-sm{padding:.38rem .8rem;font-size:.78rem;}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none!important;}
.btn-danger-sm{background:#fff0f0;color:#c62828;border:1px solid #ffcdd2;padding:.38rem .7rem;font-size:.78rem;}

/* ── TOOLBAR ── */
.ed-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:.4rem;padding:.65rem 1.5rem;background:#fff;border-bottom:1.5px solid var(--border);box-shadow:0 1px 6px rgba(58,15,40,.06);position:sticky;top:56px;z-index:90;}
.ed-toolbar-left{display:flex;gap:.4rem;flex-wrap:wrap;flex:1;}
.ed-toolbar-utils{display:flex;gap:.3rem;flex-wrap:wrap;}
#vk-cert-msg.vk-cert-msg{font-size:.82rem;padding:.22rem .6rem;border-radius:6px;min-height:1.8em;flex:1;min-width:0;}
.ed-msg-ok  {background:#d4edda;color:#155724;}
.ed-msg-err {background:#f8d7da;color:#721c24;}
.ed-msg-info{background:#d1ecf1;color:#0c5460;}

/* ── CONTAINER ── */
.ed-body{max-width:1400px;margin:0 auto;padding:1.25rem 1.5rem;}

/* ── TAB NAV ── */
.ed-tabs{display:flex;gap:.25rem;border-bottom:2px solid var(--border);margin-bottom:1rem;flex-wrap:wrap;}
.ed-tab{background:transparent;border:none;border-bottom:3px solid transparent;padding:.5rem 1.1rem;cursor:pointer;font-size:.84rem;color:var(--ts);font-family:inherit;font-weight:600;transition:all .15s;margin-bottom:-2px;}
.ed-tab:hover{color:var(--vk-rose);}
.ed-tab.on{color:var(--vk-rose);border-bottom-color:var(--vk-rose);}

/* ── TAB CONTENT ── */
.ed-tab-pane{display:none;min-height:500px;}
.ed-tab-pane.on{display:block;}

/* ── EDITOR LAYOUT (2-col) ── */
.ed-layout{display:grid;grid-template-columns:290px 1fr;gap:1rem;align-items:start;}
@media(max-width:900px){.ed-layout{grid-template-columns:1fr;}}

/* ── CONTROL PANEL ── */
.ed-ctrl{overflow-y:auto;max-height:calc(100vh - 160px);padding-right:.2rem;scroll-behavior:smooth;}
.ed-sect{border:1px solid var(--bd);border-radius:9px;margin-bottom:.45rem;background:var(--ca);overflow:hidden;}
.ed-sect summary{padding:.58rem .8rem;cursor:pointer;font-weight:700;font-size:.82rem;color:var(--tx);list-style:none;display:flex;align-items:center;gap:.35rem;user-select:none;}
.ed-sect summary::-webkit-details-marker{display:none}
.ed-sect summary::before{content:'▶';font-size:.6rem;transition:transform .15s;color:var(--mu);}
.ed-sect[open] summary::before{transform:rotate(90deg);}
.ed-sect-body{padding:.4rem .8rem .75rem;}
.ed-subsect{border-top:1px solid var(--border);padding:.55rem 0 0;margin-top:.55rem;}
.ed-subsect:first-child{border-top:none;padding-top:0;margin-top:0;}
.ed-subsect-title{font-size:.73rem;font-weight:700;color:var(--ts);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.45rem;}

/* ── FORM FIELDS ── */
.ed-field{margin-bottom:.42rem;}
.ed-field label{display:block;font-size:.74rem;color:var(--mu);margin-bottom:.16rem;font-weight:500;}
.ed-field input[type=text],.ed-field input[type=number],.ed-field select{width:100%;padding:.3rem .45rem;border:1px solid var(--bd);border-radius:5px;background:var(--fo);color:var(--tx);font-size:.8rem;font-family:inherit;}
.ed-field input[type=number]{max-width:72px;}
.ed-field input[type=checkbox]{margin-right:.3rem;}
.ed-field-row{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.42rem;align-items:flex-end;}
.ed-field-row .ed-field{margin-bottom:0;flex-shrink:0;}
.ed-radio-row{display:flex;gap:.6rem;flex-wrap:wrap;}
.ed-radio-row label{font-size:.8rem;display:flex;align-items:center;gap:.2rem;}
.ed-cpair{display:flex;gap:.3rem;align-items:center;}
.ed-cpair input[type=color]{width:32px;height:28px;border:none;border-radius:5px;cursor:pointer;padding:2px;}
.ed-cpair input[type=text]{flex:1;font-family:monospace;font-size:.77rem;}
.ed-chk-row{display:flex;gap:.7rem;}
.ed-chk-row label{font-size:.8rem;display:flex;align-items:center;gap:.2rem;}
.ed-upload-zone{border:2px dashed var(--bd);border-radius:8px;padding:.65rem;text-align:center;cursor:pointer;color:var(--mu);font-size:.8rem;transition:all .18s;background:rgba(252,232,241,.2);}
.ed-upload-zone:hover{border-color:var(--vk-rose);background:rgba(196,77,138,.05);}

/* ── MINI TEMPLATE GALLERY ── */
.ed-mini-gallery{display:grid;grid-template-columns:repeat(4,1fr);gap:.3rem;margin-top:.45rem;}

/* ── CANVAS AREA ── */
.ed-preview{display:flex;flex-direction:column;gap:.6rem;}
.ed-canvas-wrap{position:relative;background:#d8d8d8;border-radius:12px;overflow:hidden;padding:.6rem;display:flex;justify-content:center;align-items:flex-start;}
#vk-cert-canvas{max-width:100%;border-radius:6px;box-shadow:0 6px 28px rgba(0,0,0,.22);display:block;}
.ed-sample-data{background:var(--su);border:1px solid var(--bd);border-radius:8px;padding:.6rem .9rem;}
.ed-sample-title{font-size:.73rem;font-weight:700;color:var(--mu);margin-bottom:.4rem;}
.ed-sample-grid{display:grid;grid-template-columns:1fr 1fr;gap:.3rem .6rem;}
.ed-sample-field{display:flex;align-items:center;gap:.3rem;font-size:.74rem;}
.ed-sample-field label{color:var(--mu);white-space:nowrap;min-width:46px;}
.ed-sample-field input{flex:1;padding:.2rem .35rem;border:1px solid var(--bd);border-radius:4px;font-size:.72rem;background:var(--fo);color:var(--tx);min-width:0;}

/* ── TEMPLATES TAB ── */
.tmgr-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;}
.tmgr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:.85rem;margin-bottom:1rem;}
.tmgr-card{background:#fff;border:2px solid var(--border);border-radius:14px;overflow:hidden;position:relative;transition:.18s;cursor:pointer;}
.tmgr-card:hover{border-color:var(--vk-rose);transform:translateY(-2px);box-shadow:0 8px 24px rgba(196,77,138,.15);}
.tmgr-card.active{border-color:var(--vk-rose);box-shadow:0 0 0 3px rgba(196,77,138,.2);}
.tmgr-thumb{width:100%;height:108px;object-fit:cover;display:block;background:linear-gradient(135deg,#f8e8f0,#ede0f0);}
.tmgr-thumb-placeholder{width:100%;height:108px;display:flex;align-items:center;justify-content:center;font-size:2.5rem;background:linear-gradient(135deg,var(--vk-petal),#ede8f8);}
.tmgr-body{padding:.7rem;}
.tmgr-name{font-weight:700;font-size:.86rem;color:var(--vk-plum);margin-bottom:.22rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tmgr-meta{font-size:.71rem;color:var(--tu);margin-bottom:.5rem;}
.tmgr-badge{display:inline-block;padding:.1rem .45rem;border-radius:20px;font-size:.67rem;font-weight:700;background:var(--vk-petal);color:var(--vk-plum);}
.tmgr-badge-used{background:#e8f5e9;color:#2e7d32;}
.tmgr-actions{display:flex;gap:.3rem;flex-wrap:wrap;}
.tmgr-add-card{background:transparent;border:2px dashed var(--border);border-radius:14px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.45rem;min-height:188px;cursor:pointer;transition:.18s;color:var(--ts);}
.tmgr-add-card:hover{border-color:var(--vk-rose);color:var(--vk-rose);background:rgba(196,77,138,.03);}
.tmgr-add-icon{font-size:2rem;}

/* ── ASSIGNMENTS TABLE ── */
.cca-wrap{background:#fff;border-radius:12px;border:1.5px solid var(--border);overflow:hidden;margin-top:.75rem;}
.cca-wrap h3{font-size:.9rem;font-weight:700;color:var(--vk-plum);padding:.8rem 1rem;border-bottom:1px solid var(--border);margin:0;}
.cca-inner{padding:.75rem 1rem;}
.cca-table{width:100%;border-collapse:collapse;font-size:.83rem;}
.cca-table th{background:var(--vk-petal);padding:.48rem .75rem;text-align:left;font-weight:700;color:var(--vk-plum);font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;}
.cca-table td{padding:.5rem .75rem;border-bottom:1px solid var(--border);vertical-align:middle;}
.cca-table tr:hover td{background:#fdf5f9;}
.tmpl-pill{display:inline-flex;align-items:center;gap:.3rem;background:var(--vk-petal);color:var(--vk-plum);border:1px solid rgba(196,77,138,.2);border-radius:20px;padding:.15rem .6rem;font-size:.74rem;font-weight:600;}
.tmpl-pill-default{background:#f0f0f0;color:var(--tu);border-color:#ddd;}

/* ── FONTS TAB ── */
.ed-font-section{margin-bottom:1.5rem;}
.ed-font-section h3{font-size:.9rem;font-weight:700;margin-bottom:.65rem;color:var(--ts);}
.ed-font-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(195px,1fr));gap:.5rem;}
.ed-font-card{border:2px solid var(--bd);border-radius:9px;padding:.6rem .85rem;cursor:pointer;transition:border-color .15s,background .15s;display:flex;flex-direction:column;gap:.28rem;background:var(--ca);}
.ed-font-card:hover{border-color:var(--vk-rose);}
.ed-font-card.active{border-color:var(--vk-rose);background:rgba(196,77,138,.07);}
.ed-font-name{font-size:.69rem;color:var(--mu);font-weight:500;}

/* ── RESOURCES TAB ── */
.ed-res-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:.85rem;}
.ed-res-card{border:1.5px solid var(--bd);border-radius:12px;padding:1rem;background:var(--ca);}
.ed-res-card h3{font-size:.88rem;font-weight:700;margin-bottom:.3rem;}
.ed-res-desc{font-size:.78rem;color:var(--mu);margin-bottom:.65rem;line-height:1.4;}
.ed-file-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.45rem;margin-top:.5rem;}
.ed-file-card{border:2px solid var(--bd);border-radius:7px;overflow:hidden;cursor:pointer;transition:border-color .15s,transform .12s;}
.ed-file-card:hover{border-color:var(--vk-rose);transform:translateY(-2px);}
.ed-file-card img{width:100%;height:82px;object-fit:cover;display:block;}
.ed-file-card-name{font-size:.65rem;padding:.18rem .3rem;text-align:center;color:var(--mu);word-break:break-all;}

/* ── MODAL ── */
.vk-modal-bg{position:fixed;inset:0;background:rgba(30,0,20,.55);z-index:2000;display:flex;align-items:center;justify-content:center;padding:1rem;}
.vk-modal{background:#fff;border-radius:20px;max-width:440px;width:100%;padding:1.65rem;box-shadow:0 24px 80px rgba(58,15,40,.35);position:relative;}
.vk-modal h3{font-family:'Cormorant Garamond',serif;color:var(--vk-plum);font-size:1.25rem;margin-bottom:.9rem;}
.vk-modal-close{position:absolute;top:.9rem;right:.9rem;background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--tu);}
.vk-modal-close:hover{color:var(--vk-plum);}
.vk-modal .field{margin-bottom:.8rem;}
.vk-modal .field label{display:block;font-size:.8rem;font-weight:700;color:var(--ts);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.04em;}
.vk-modal .field input,.vk-modal .field select,.vk-modal .field textarea{width:100%;padding:.6rem .85rem;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.88rem;color:var(--td);outline:none;transition:.15s;background:#fff;}
.vk-modal .field input:focus,.vk-modal .field select:focus{border-color:var(--vk-rose);}

/* ── MISC ── */
.msg{padding:.6rem .9rem;border-radius:9px;font-size:.84rem;margin:.45rem 0;display:flex;align-items:center;gap:.45rem;}
.msg-ok {background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;}
.msg-err{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;}
.msg-info{background:var(--vk-petal);color:var(--vk-plum);border:1px solid rgba(196,77,138,.2);}
.vk-tmpl-card{border:2px solid var(--bd);border-radius:7px;padding:.4rem;cursor:pointer;transition:border-color .15s,box-shadow .15s,transform .1s;background:var(--ca);text-align:center;}
.vk-tmpl-card:hover{border-color:var(--vk-rose);transform:translateY(-2px);}
.vk-tmpl-card.active{border-color:var(--vk-rose);box-shadow:0 0 0 3px rgba(196,77,138,.2);}
.vk-tmpl-name{font-size:.65rem;font-weight:600;color:var(--tx);margin-top:.2rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.vk-spin{display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,.4);border-top-color:white;border-radius:50%;animation:spin .6s linear infinite;margin-right:.3rem;vertical-align:middle;}
@keyframes spin{to{transform:rotate(360deg)}}
.loader{display:inline-block;width:13px;height:13px;border:2px solid rgba(255,255,255,.4);border-top-color:white;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:.3rem;}
</style>
<script src="vk-cert-renderer.js" charset="UTF-8"></script>
<script src="vk-cert-editor.js" charset="UTF-8"></script>
</head>
<body>

<!-- ══ LOGIN ══════════════════════════════════════════════════════ -->
<div id="login-screen">
  <div class="login-box">
    <div class="logo">🏆</div>
    <h2>Editor de Certificados</h2>
    <p>VidaKushala — Solo administradores</p>
    <div style="display:flex;border-radius:10px;overflow:hidden;border:1.5px solid var(--border);margin-bottom:1.1rem">
      <button id="ltab-pass" onclick="switchLoginTab('pass')" style="flex:1;padding:.5rem;border:none;background:var(--grad-accent);color:white;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer">🔐 Usuario</button>
      <button id="ltab-tok"  onclick="switchLoginTab('tok')"  style="flex:1;padding:.5rem;border:none;background:white;color:var(--ts);font-family:inherit;font-size:.82rem;font-weight:600;cursor:pointer">🔑 Token</button>
    </div>
    <div id="lpanel-pass">
      <div style="margin-bottom:.6rem"><input type="text" id="login-user" placeholder="Usuario o email" style="width:100%;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.88rem" autocomplete="username"></div>
      <div style="position:relative;margin-bottom:.9rem">
        <input type="password" id="login-pass" placeholder="Contraseña" autocomplete="current-password" onkeydown="if(event.key==='Enter')doLoginPass()" style="width:100%;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.88rem">
        <button onclick="document.getElementById('login-pass').type=document.getElementById('login-pass').type==='password'?'text':'password'" style="position:absolute;right:.7rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:var(--tu)">👁</button>
      </div>
      <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="doLoginPass()">Entrar</button>
    </div>
    <div id="lpanel-tok" style="display:none">
      <div style="margin-bottom:.9rem"><input type="text" id="login-tok" placeholder="Pega tu vk_tok aquí" style="width:100%;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.82rem;text-align:center"></div>
      <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="doLoginTok()">Entrar con token</button>
    </div>
    <p id="login-err" style="color:#c62828;font-size:.82rem;margin-top:.6rem;min-height:1.8em;line-height:1.4"></p>
  </div>
</div>

<!-- ══ HEADER ══════════════════════════════════════════════════════ -->
<div class="ed-header">
  <a href="administrar.php" class="ed-back">← Panel Admin</a>
  <div>
    <h1>🏆 Editor de Certificados</h1>
  </div>
  <div class="ed-header-right">
    <span id="ed-admin-name"></span>
    <button class="btn btn-ghost btn-sm" onclick="logout()">Salir</button>
  </div>
</div>

<!-- ══ TOOLBAR ══════════════════════════════════════════════════════ -->
<div class="ed-toolbar">
  <div class="ed-toolbar-left">
    <button class="btn btn-primary" id="vk-save-cert-btn" onclick="vkSaveCertConfig()">💾 Guardar diseño</button>
    <button class="btn btn-secondary btn-sm" onclick="vkResetCertConfig()">↩ Defaults</button>
    <button class="btn btn-outline btn-sm" onclick="vkDownloadPreview()">⬇ PNG</button>
  </div>
  <div class="ed-toolbar-utils">
    <button class="btn btn-outline btn-sm" onclick="vkClearCertCache()" title="Borrar caché de certificados generados">🗑 Cache</button>
    <button class="btn btn-outline btn-sm" onclick="vkSanitizeCertBg()" title="Elimina fondos que sean certificados renderizados con demo data">🧹 Fondos</button>
    <button class="btn btn-outline btn-sm" onclick="vkSetDefaultBg()" title="Establece la imagen predeterminada como fondo global">🖼 Default</button>
  </div>
  <div id="vk-cert-msg" class="vk-cert-msg"></div>
</div>

<!-- ══ MAIN ══════════════════════════════════════════════════════== -->
<div class="ed-body">

  <!-- Editing notice -->
  <div id="tpl-edit-notice" style="display:none" class="msg msg-info"></div>

  <!-- ── TABS ── -->
  <div class="ed-tabs">
    <button class="ed-tab on" onclick="edTab('design')">🎨 Diseño</button>
    <button class="ed-tab"    onclick="edTab('templates')">📋 Plantillas</button>
    <button class="ed-tab"    onclick="edTab('fonts')">🔤 Tipografía</button>
    <button class="ed-tab"    onclick="edTab('resources')">🖼 Recursos</button>
  </div>

  <!-- ══ TAB: DISEÑO ══════════════════════════════════════════════ -->
  <div class="ed-tab-pane on" id="ed-pane-design">
    <div class="ed-layout">

      <!-- ─ PANEL DE CONTROLES ─ -->
      <div class="ed-ctrl" id="vk-ctrl-panel">

        <!-- Mini galería de plantillas -->
        <div class="ed-sect" style="margin-bottom:.6rem">
          <details open>
            <summary>🎨 Plantilla base</summary>
            <div class="ed-sect-body" style="padding-top:.2rem">
              <div id="vk-mini-tmpl-gallery" class="ed-mini-gallery"></div>
              <button class="btn btn-secondary btn-sm" style="width:100%;margin-top:.5rem;justify-content:center" onclick="edTab('templates')">Ver todas las plantillas →</button>
            </div>
          </details>
        </div>

        <!-- SECCIÓN: APARIENCIA -->
        <details class="ed-sect" open>
          <summary>🖼 Apariencia</summary>
          <div class="ed-sect-body">

            <!-- Fondo -->
            <div class="ed-subsect">
              <div class="ed-subsect-title">Fondo del certificado</div>
              <div class="ed-field">
                <div class="ed-radio-row">
                  <label><input type="radio" name="vk-bg_type" value="color"    onchange="vkCfgSet('bg_type',this.value);vkBgTypeUpdate()"> Color</label>
                  <label><input type="radio" name="vk-bg_type" value="gradient" onchange="vkCfgSet('bg_type',this.value);vkBgTypeUpdate()"> Degradado</label>
                  <label><input type="radio" name="vk-bg_type" value="image"    onchange="vkCfgSet('bg_type',this.value);vkBgTypeUpdate()"> Imagen</label>
                </div>
              </div>
              <div id="vk-bg-color-row">
                <div class="ed-field" id="vk-color-field">
                  <label>Color</label>
                  <div class="ed-cpair">
                    <input type="color" id="vk-bg_color" value="#ffffff" oninput="vkCfgSet('bg_color',this.value);vkSyncHex('vk-bg_color','vk-bg_color_hex')">
                    <input type="text"  id="vk-bg_color_hex" value="#ffffff" maxlength="7" oninput="vkCfgSet('bg_color',this.value);vkSyncColor('vk-bg_color_hex','vk-bg_color')">
                  </div>
                </div>
                <div id="vk-gradient-fields" style="display:none">
                  <div class="ed-field"><label>Degradado</label>
                    <div class="ed-cpair">
                      <input type="color" id="vk-bg_gradient_from" value="#0f2044" oninput="vkCfgSet('bg_gradient_from',this.value);vkCfgSet('bg_gradient',true)">
                      <span style="font-size:.75rem;color:var(--mu)">→</span>
                      <input type="color" id="vk-bg_gradient_to"   value="#1a3a6b" oninput="vkCfgSet('bg_gradient_to',this.value);vkCfgSet('bg_gradient',true)">
                    </div>
                  </div>
                </div>
              </div>
              <div id="vk-bg-image-row" style="display:none">
                <div class="ed-upload-zone" id="vk-upload-zone" onclick="document.getElementById('vk-bg-upload').click()">
                  <div id="vk-upload-zone-inner">📁 Clic para subir imagen de fondo</div>
                </div>
                <input type="file" id="vk-bg-upload" accept="image/*" style="display:none" onchange="vkUploadBgImage(this)">
                <div id="vk-bg-img-thumb"></div>
                <div class="ed-field" style="margin-top:.45rem">
                  <label>Oscurecer (<span id="vk-overlay-n">0</span>%)</label>
                  <input type="range" id="vk-bg_overlay" min="0" max="70" value="0" style="width:100%;accent-color:var(--vk-rose)" oninput="vkCfgSet('bg_overlay_opacity',+this.value);document.getElementById('vk-overlay-n').textContent=this.value">
                </div>
              </div>
            </div>

            <!-- Marco -->
            <div class="ed-subsect">
              <div class="ed-subsect-title">Marco decorativo</div>
              <div class="ed-field"><label><input type="checkbox" id="vk-border_enabled" onchange="vkCfgSet('border_enabled',this.checked)" checked> Mostrar marco doble</label></div>
              <div class="ed-field-row">
                <div class="ed-field">
                  <label>Color</label>
                  <div class="ed-cpair">
                    <input type="color" id="vk-border_color" value="#6f102a" oninput="vkCfgSet('border_color',this.value);vkSyncHex('vk-border_color','vk-border_color_hex')">
                    <input type="text"  id="vk-border_color_hex" value="#6f102a" maxlength="7" style="width:68px" oninput="vkCfgSet('border_color',this.value);vkSyncColor('vk-border_color_hex','vk-border_color')">
                  </div>
                </div>
                <div class="ed-field">
                  <label>Grosor — <span id="vk-border_width_n">18</span>px</label>
                  <input type="range" id="vk-border_width" min="2" max="60" value="18" style="width:100px;accent-color:var(--vk-rose)" oninput="vkCfgSet('border_width',+this.value);document.getElementById('vk-border_width_n').textContent=this.value">
                </div>
              </div>
            </div>

          </div>
        </details>

        <!-- SECCIÓN: TEXTOS -->
        <details class="ed-sect" open>
          <summary>✏️ Textos del certificado</summary>
          <div class="ed-sect-body">

            <!-- Título -->
            <div class="ed-subsect">
              <div class="ed-subsect-title">Título del diploma</div>
              <div class="ed-field"><label>Texto</label><input type="text" id="vk-header_text" value="DIPLOMA DE FINALIZACIÓN" oninput="vkCfgSet('header_text',this.value)"></div>
              <div class="ed-field-row">
                <div class="ed-field"><label>Color</label>
                  <div class="ed-cpair">
                    <input type="color" id="vk-header_color" value="#6f102a" oninput="vkCfgSet('header_color',this.value);vkSyncHex('vk-header_color','vk-header_color_hex')">
                    <input type="text"  id="vk-header_color_hex" value="#6f102a" maxlength="7" style="width:68px" oninput="vkCfgSet('header_color',this.value);vkSyncColor('vk-header_color_hex','vk-header_color')">
                  </div>
                </div>
                <div class="ed-field"><label>Tamaño</label><input type="number" id="vk-header_font_size" value="38" min="8" max="120" oninput="vkCfgSet('header_font_size',+this.value)"></div>
                <div class="ed-field"><label>Pos Y</label><input type="number" id="vk-header_y" value="110" min="10" max="780" oninput="vkCfgSet('header_y',+this.value)"></div>
                <div class="ed-field" style="align-self:flex-end"><label><input type="checkbox" id="vk-header_line" onchange="vkCfgSet('header_line',this.checked)" checked> Línea</label></div>
              </div>
            </div>

            <!-- Subtítulo -->
            <div class="ed-subsect">
              <div class="ed-subsect-title">Subtítulo</div>
              <div class="ed-field"><label>Texto</label><input type="text" id="vk-subheader_text" value="SE OTORGA A" oninput="vkCfgSet('subheader_text',this.value)"></div>
              <div class="ed-field-row">
                <div class="ed-field"><label>Color</label>
                  <div class="ed-cpair">
                    <input type="color" id="vk-subheader_color" value="#1a2e5a" oninput="vkCfgSet('subheader_color',this.value);vkSyncHex('vk-subheader_color','vk-subheader_color_hex')">
                    <input type="text"  id="vk-subheader_color_hex" value="#1a2e5a" maxlength="7" style="width:68px" oninput="vkCfgSet('subheader_color',this.value);vkSyncColor('vk-subheader_color_hex','vk-subheader_color')">
                  </div>
                </div>
                <div class="ed-field"><label>Tamaño</label><input type="number" id="vk-subheader_font_size" value="13" min="6" max="60" oninput="vkCfgSet('subheader_font_size',+this.value)"></div>
                <div class="ed-field"><label>Pos Y</label><input type="number" id="vk-subheader_y" value="158" min="10" max="780" oninput="vkCfgSet('subheader_y',+this.value)"></div>
              </div>
            </div>

            <!-- Nombre del estudiante -->
            <div class="ed-subsect">
              <div class="ed-subsect-title">Nombre del estudiante</div>
              <div class="ed-field-row">
                <div class="ed-field"><label>Color</label>
                  <div class="ed-cpair">
                    <input type="color" id="vk-name_color" value="#6f102a" oninput="vkCfgSet('name_color',this.value);vkSyncHex('vk-name_color','vk-name_color_hex')">
                    <input type="text"  id="vk-name_color_hex" value="#6f102a" maxlength="7" style="width:68px" oninput="vkCfgSet('name_color',this.value);vkSyncColor('vk-name_color_hex','vk-name_color')">
                  </div>
                </div>
                <div class="ed-field"><label>Tamaño</label><input type="number" id="vk-name_font_size" value="46" min="10" max="120" oninput="vkCfgSet('name_font_size',+this.value)"></div>
                <div class="ed-field"><label>Pos Y</label><input type="number" id="vk-name_y" value="340" min="10" max="780" oninput="vkCfgSet('name_y',+this.value)"></div>
              </div>
              <div class="ed-field"><label>Alineación</label>
                <select id="vk-name_align" onchange="vkCfgSet('name_align',this.value)"><option value="center">Centro</option><option value="left">Izquierda</option></select>
              </div>
              <div class="ed-chk-row">
                <label><input type="checkbox" id="vk-name_italic"    onchange="vkCfgSet('name_italic',this.checked)"    checked> Cursiva</label>
                <label><input type="checkbox" id="vk-name_underline" onchange="vkCfgSet('name_underline',this.checked)" checked> Subrayado</label>
              </div>
            </div>

            <!-- Ha completado -->
            <div class="ed-subsect">
              <div class="ed-subsect-title">Texto "Ha completado"</div>
              <div class="ed-field"><label><input type="checkbox" id="vk-has_completed_text" onchange="vkCfgSet('has_completed_text',this.checked)" checked> Mostrar</label></div>
              <div class="ed-field"><label>Texto</label><input type="text" id="vk-completed_text" value="Ha completado satisfactoriamente el curso:" oninput="vkCfgSet('completed_text',this.value)"></div>
              <div class="ed-field-row">
                <div class="ed-field"><label>Color</label>
                  <div class="ed-cpair">
                    <input type="color" id="vk-completed_color" value="#444444" oninput="vkCfgSet('completed_color',this.value);vkSyncHex('vk-completed_color','vk-completed_color_hex')">
                    <input type="text"  id="vk-completed_color_hex" value="#444444" maxlength="7" style="width:68px" oninput="vkCfgSet('completed_color',this.value);vkSyncColor('vk-completed_color_hex','vk-completed_color')">
                  </div>
                </div>
                <div class="ed-field"><label>Tamaño</label><input type="number" id="vk-completed_font_size" value="13" min="6" max="60" oninput="vkCfgSet('completed_font_size',+this.value)"></div>
                <div class="ed-field"><label>Pos Y</label><input type="number" id="vk-completed_y" value="415" min="10" max="780" oninput="vkCfgSet('completed_y',+this.value)"></div>
              </div>
            </div>

            <!-- Nombre del curso -->
            <div class="ed-subsect">
              <div class="ed-subsect-title">Nombre del curso</div>
              <div class="ed-field-row">
                <div class="ed-field"><label>Color</label>
                  <div class="ed-cpair">
                    <input type="color" id="vk-title_color" value="#1a2e5a" oninput="vkCfgSet('title_color',this.value);vkSyncHex('vk-title_color','vk-title_color_hex')">
                    <input type="text"  id="vk-title_color_hex" value="#1a2e5a" maxlength="7" style="width:68px" oninput="vkCfgSet('title_color',this.value);vkSyncColor('vk-title_color_hex','vk-title_color')">
                  </div>
                </div>
                <div class="ed-field"><label>Tamaño</label><input type="number" id="vk-title_font_size" value="22" min="6" max="80" oninput="vkCfgSet('title_font_size',+this.value)"></div>
                <div class="ed-field"><label>Pos Y</label><input type="number" id="vk-title_y" value="460" min="10" max="780" oninput="vkCfgSet('title_y',+this.value)"></div>
              </div>
              <div class="ed-field"><label>Alineación</label>
                <select id="vk-title_align" onchange="vkCfgSet('title_align',this.value)"><option value="center">Centro</option><option value="left">Izquierda</option></select>
              </div>
            </div>

          </div>
        </details>

        <!-- SECCIÓN: DETALLES -->
        <details class="ed-sect">
          <summary>📍 Detalles y posicionamiento</summary>
          <div class="ed-sect-body">

            <!-- Fecha e ID -->
            <div class="ed-subsect">
              <div class="ed-subsect-title">Fecha</div>
              <div class="ed-field"><label>Etiqueta</label><input type="text" id="vk-date_label" value="Fecha de finalización:" oninput="vkCfgSet('date_label',this.value)"></div>
              <div class="ed-field-row">
                <div class="ed-field"><label>Color</label>
                  <div class="ed-cpair">
                    <input type="color" id="vk-date_color" value="#555555" oninput="vkCfgSet('date_color',this.value);vkSyncHex('vk-date_color','vk-date_color_hex')">
                    <input type="text"  id="vk-date_color_hex" value="#555555" maxlength="7" style="width:68px" oninput="vkCfgSet('date_color',this.value);vkSyncColor('vk-date_color_hex','vk-date_color')">
                  </div>
                </div>
                <div class="ed-field"><label>Tam.</label><input type="number" id="vk-date_font_size" value="12" min="6" max="40" oninput="vkCfgSet('date_font_size',+this.value)"></div>
                <div class="ed-field"><label>X</label><input type="number" id="vk-date_x" value="80" min="0" max="1100" oninput="vkCfgSet('date_x',+this.value)"></div>
                <div class="ed-field"><label>Y</label><input type="number" id="vk-date_y" value="560" min="10" max="780" oninput="vkCfgSet('date_y',+this.value)"></div>
              </div>
            </div>
            <div class="ed-subsect">
              <div class="ed-subsect-title">ID del certificado</div>
              <div class="ed-field-row">
                <div class="ed-field"><label>Color</label>
                  <div class="ed-cpair">
                    <input type="color" id="vk-cert_id_color" value="#888888" oninput="vkCfgSet('cert_id_color',this.value);vkSyncHex('vk-cert_id_color','vk-cert_id_color_hex')">
                    <input type="text"  id="vk-cert_id_color_hex" value="#888888" maxlength="7" style="width:68px" oninput="vkCfgSet('cert_id_color',this.value);vkSyncColor('vk-cert_id_color_hex','vk-cert_id_color')">
                  </div>
                </div>
                <div class="ed-field"><label>Tam.</label><input type="number" id="vk-cert_id_font_size" value="9" min="4" max="20" oninput="vkCfgSet('cert_id_font_size',+this.value)"></div>
                <div class="ed-field"><label>X</label><input type="number" id="vk-cert_id_x" value="80" min="0" max="1100" oninput="vkCfgSet('cert_id_x',+this.value)"></div>
                <div class="ed-field"><label>Y</label><input type="number" id="vk-cert_id_y" value="578" min="10" max="780" oninput="vkCfgSet('cert_id_y',+this.value)"></div>
              </div>
            </div>

            <!-- Firma -->
            <div class="ed-subsect">
              <div class="ed-subsect-title">Firma del instructor</div>
              <div class="ed-field"><label>Nombre del firmante</label><input type="text" id="vk-signature_label" placeholder="Roberto Carlos Hidalgo" oninput="vkCfgSet('signature_label',this.value)"></div>
              <div class="ed-field"><label>Cargo / Rol</label><input type="text" id="vk-signature_role" placeholder="Instructor · VidaKushala" oninput="vkCfgSet('signature_role',this.value)"></div>
              <div class="ed-field-row">
                <div class="ed-field"><label>X</label><input type="number" id="vk-signature_x" value="760" min="0" max="1100" oninput="vkCfgSet('signature_x',+this.value)"></div>
                <div class="ed-field"><label>Y</label><input type="number" id="vk-signature_y" value="640" min="10" max="780" oninput="vkCfgSet('signature_y',+this.value)"></div>
                <div class="ed-field"><label>Largo línea</label><input type="number" id="vk-signature_line_w" value="200" min="50" max="400" oninput="vkCfgSet('signature_line_w',+this.value)"></div>
              </div>
            </div>

            <!-- QR -->
            <div class="ed-subsect">
              <div class="ed-subsect-title">Código QR de verificación</div>
              <div class="ed-field"><label><input type="checkbox" id="vk-qr_enabled" onchange="vkCfgSet('qr_enabled',this.checked)" checked> Mostrar QR</label></div>
              <div class="ed-field-row">
                <div class="ed-field"><label>Desde der.</label><input type="number" id="vk-qr_x_from_right" value="50" min="0" max="500" oninput="vkCfgSet('qr_x_from_right',+this.value)"></div>
                <div class="ed-field"><label>Desde abajo</label><input type="number" id="vk-qr_y_from_bottom" value="85" min="0" max="400" oninput="vkCfgSet('qr_y_from_bottom',+this.value)"></div>
                <div class="ed-field"><label>Tamaño</label><input type="number" id="vk-qr_size" value="78" min="40" max="150" oninput="vkCfgSet('qr_size',+this.value)"></div>
              </div>
            </div>

          </div>
        </details>

      </div><!-- /ed-ctrl -->

      <!-- ─ CANVAS + DATOS DE MUESTRA ─ -->
      <div class="ed-preview">
        <div class="ed-canvas-wrap" id="vk-canvas-wrap">
          <canvas id="vk-cert-canvas" width="1122" height="794"></canvas>
        </div>
        <div class="ed-sample-data">
          <div class="ed-sample-title">📝 Datos de muestra (solo para preview del editor)</div>
          <div class="ed-sample-grid">
            <div class="ed-sample-field"><label>Nombre:</label> <input type="text" id="vk-prev-name"   value="María González López"              oninput="vkCertPreviewRedraw()"></div>
            <div class="ed-sample-field"><label>Curso:</label>  <input type="text" id="vk-prev-course" value="Método VidaKushala — Bioenergética" oninput="vkCertPreviewRedraw()"></div>
            <div class="ed-sample-field"><label>Fecha:</label>  <input type="text" id="vk-prev-date"   value="30 de mayo de 2026"                oninput="vkCertPreviewRedraw()"></div>
            <div class="ed-sample-field"><label>ID cert.:</label><input type="text" id="vk-prev-id"    value="CERT-A7F3B2D1"                     oninput="vkCertPreviewRedraw()"></div>
          </div>
        </div>
      </div>

    </div><!-- /ed-layout -->
  </div><!-- /ed-pane-design -->

  <!-- ══ TAB: PLANTILLAS ════════════════════════════════════════ -->
  <div class="ed-tab-pane" id="ed-pane-templates">
    <div class="tmgr-header">
      <div>
        <strong style="font-size:1rem;color:var(--vk-plum)">Plantillas de certificados</strong>
        <p style="font-size:.82rem;color:var(--ts);margin-top:.2rem">Edita cada plantilla en el editor y asígnala a los cursos correspondientes.</p>
      </div>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap">
        <button class="btn btn-primary btn-sm" onclick="openCreateTpl()">➕ Nueva plantilla</button>
        <button class="btn btn-secondary btn-sm" onclick="cleanDuplicates()">🧹 Limpiar duplicados</button>
      </div>
    </div>
    <div id="tpl-manager-msg" style="margin-bottom:.6rem"></div>
    <div id="tpl-cards-grid" class="tmgr-grid"></div>
    <div class="cca-wrap" id="cca-wrap" style="display:none">
      <h3>📋 Asignación de plantilla por curso</h3>
      <div class="cca-inner">
        <div id="cca-all-row" style="display:none;margin-bottom:.85rem;padding:.6rem .9rem;background:rgba(0,0,0,.03);border-radius:9px;border:1px solid var(--border);display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
          <strong style="font-size:.82rem;white-space:nowrap">Asignar a TODOS:</strong>
          <select id="assign-all-sel" style="padding:.35rem .65rem;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:.82rem;flex:1;min-width:160px"></select>
          <button id="assign-all-btn" class="btn btn-primary btn-sm" onclick="assignAllCourses(document.getElementById('assign-all-sel').value)">📋 Asignar a todos</button>
        </div>
        <table class="cca-table"><thead><tr><th>Curso</th><th>Plantilla actual</th><th>Cambiar a</th><th></th></tr></thead>
        <tbody id="cca-tbody"></tbody></table>
      </div>
    </div>
  </div>

  <!-- ══ TAB: TIPOGRAFÍA ════════════════════════════════════════ -->
  <div class="ed-tab-pane" id="ed-pane-fonts">
    <div id="vk-tc-fonts">
      <div class="ed-font-section">
        <h3>🔤 Tipografía del título (DIPLOMA)</h3>
        <div class="ed-font-grid">
          <div class="ed-font-card active" data-font="Georgia"          onclick="vkSetFont('title','Georgia')">
            <span style="font-family:'Georgia',serif;font-size:1.35rem">DIPLOMA</span>
            <span class="ed-font-name">Georgia (clásica)</span></div>
          <div class="ed-font-card" data-font="Times New Roman"         onclick="vkSetFont('title','Times New Roman')">
            <span style="font-family:'Times New Roman',serif;font-size:1.35rem">DIPLOMA</span>
            <span class="ed-font-name">Times New Roman</span></div>
          <div class="ed-font-card" data-font="Palatino Linotype"       onclick="vkSetFont('title','Palatino Linotype')">
            <span style="font-family:'Palatino Linotype',serif;font-size:1.35rem">DIPLOMA</span>
            <span class="ed-font-name">Palatino</span></div>
          <div class="ed-font-card" data-font="Arial"                   onclick="vkSetFont('title','Arial')">
            <span style="font-family:'Arial',sans-serif;font-size:1.35rem">DIPLOMA</span>
            <span class="ed-font-name">Arial (moderno)</span></div>
          <div class="ed-font-card" data-font="Trebuchet MS"            onclick="vkSetFont('title','Trebuchet MS')">
            <span style="font-family:'Trebuchet MS',sans-serif;font-size:1.35rem">DIPLOMA</span>
            <span class="ed-font-name">Trebuchet MS</span></div>
        </div>
      </div>
      <div class="ed-font-section">
        <h3>🔡 Tipografía del cuerpo (fechas, subtítulos)</h3>
        <div class="ed-font-grid">
          <div class="ed-font-card active" data-font-body="Arial"       onclick="vkSetFont('body','Arial')">
            <span style="font-family:'Arial',sans-serif">Ha completado el curso de VidaKushala</span>
            <span class="ed-font-name">Arial</span></div>
          <div class="ed-font-card" data-font-body="Verdana"            onclick="vkSetFont('body','Verdana')">
            <span style="font-family:'Verdana',sans-serif">Ha completado el curso de VidaKushala</span>
            <span class="ed-font-name">Verdana</span></div>
          <div class="ed-font-card" data-font-body="Tahoma"             onclick="vkSetFont('body','Tahoma')">
            <span style="font-family:'Tahoma',sans-serif">Ha completado el curso de VidaKushala</span>
            <span class="ed-font-name">Tahoma</span></div>
          <div class="ed-font-card" data-font-body="Georgia"            onclick="vkSetFont('body','Georgia')">
            <span style="font-family:'Georgia',serif">Ha completado el curso de VidaKushala</span>
            <span class="ed-font-name">Georgia</span></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ TAB: RECURSOS ══════════════════════════════════════════ -->
  <div class="ed-tab-pane" id="ed-pane-resources">
    <div class="ed-res-grid">
      <div class="ed-res-card">
        <h3>🖼 Imagen de fondo personalizada</h3>
        <p class="ed-res-desc">Se escala automáticamente a 1122×794 px. Usa PNG o JPG de alta resolución para mejores resultados.</p>
        <div class="ed-upload-zone" onclick="document.getElementById('vk-bg-upload-assets').click()">
          <div id="vk-bg-zone-assets-inner">📁 Subir imagen de fondo</div>
        </div>
        <input type="file" id="vk-bg-upload-assets" accept="image/*" style="display:none" onchange="vkUploadBgImage(this,'assets')">
        <div id="vk-bg-img-thumb-assets" style="margin-top:.5rem"></div>
      </div>
      <div class="ed-res-card">
        <h3>📤 Subir plantilla personalizada</h3>
        <p class="ed-res-desc">Sube el diseño de tu certificado como imagen base para una nueva plantilla.</p>
        <div class="ed-upload-zone" onclick="document.getElementById('vk-tmpl-upload').click()">
          <div id="vk-tmpl-zone-inner">📤 Subir plantilla</div>
        </div>
        <input type="file" id="vk-tmpl-upload" accept="image/*" style="display:none" onchange="vkUploadTemplate(this)">
        <div id="vk-tmpl-upload-preview" style="margin-top:.5rem"></div>
      </div>
      <div class="ed-res-card" id="ed-file-gallery-card" style="display:none">
        <h3>📂 Fondos disponibles en servidor</h3>
        <p class="ed-res-desc">Imágenes almacenadas en cert-templates/. Haz clic para usar como fondo.</p>
        <div id="vk-file-gallery" class="ed-file-gallery"></div>
        <div id="vk-file-gallery-assets" style="display:none"></div>
      </div>
    </div>
  </div>

  <!-- Template manager section (injected) -->
  <div id="tpl-manager-section" style="display:none"></div>

</div><!-- /ed-body -->

<!-- ══ MODAL: crear / renombrar / duplicar plantilla ══════════════ -->
<div class="vk-modal-bg" id="tpl-modal" style="display:none">
  <div class="vk-modal">
    <button class="vk-modal-close" onclick="closeTplModal()">✕</button>
    <h3 id="tpl-modal-title">Nueva plantilla</h3>
    <div class="field">
      <label>Nombre de la plantilla</label>
      <input type="text" id="tpl-modal-name" placeholder="Ej: Diplomado Premium, Curso Básico..." autofocus>
    </div>
    <div class="field" id="tpl-modal-src-wrap" style="display:none">
      <label>Copiar diseño desde</label>
      <select id="tpl-modal-src">
        <option value="">⬜ Diseño en blanco</option>
        <option value="__global__">🌐 Diseño global actual</option>
      </select>
    </div>
    <div style="display:flex;gap:.6rem;margin-top:.65rem">
      <button class="btn btn-primary" onclick="confirmTplModal()">✅ Confirmar</button>
      <button class="btn btn-secondary" onclick="closeTplModal()">Cancelar</button>
    </div>
    <p id="tpl-modal-msg" style="font-size:.8rem;margin-top:.5rem;min-height:1.2em"></p>
  </div>
</div>

<script>
/* ══ CONFIG ════════════════════════════════════════════════════ */
var API = (window.location.hostname==='localhost'||window.location.hostname==='127.0.0.1')
        ? 'http://localhost:8080/wp-json/vk/v1'
        : 'https://vidakushala.com/wp-json/vk/v1';
var TOK = '';
var TPL_LIST = [], TPL_COURSES = [], TPL_MODAL_MODE = '', TPL_MODAL_KEY = '';

/* ══ AUTH ══════════════════════════════════════════════════════ */
function switchLoginTab(t) {
  var p = t === 'pass';
  document.getElementById('lpanel-pass').style.display = p ? 'block' : 'none';
  document.getElementById('lpanel-tok').style.display  = p ? 'none'  : 'block';
  document.getElementById('ltab-pass').style.background = p ? 'var(--grad-accent)' : 'white';
  document.getElementById('ltab-pass').style.color = p ? 'white' : 'var(--ts)';
  document.getElementById('ltab-tok').style.background  = p ? 'white' : 'var(--grad-accent)';
  document.getElementById('ltab-tok').style.color  = p ? 'var(--ts)' : 'white';
  document.getElementById('login-err').textContent = '';
}
async function doLoginPass() {
  var user = document.getElementById('login-user').value.trim();
  var pass = document.getElementById('login-pass').value;
  var err  = document.getElementById('login-err');
  if (!user || !pass) { err.textContent = 'Ingresa usuario y contraseña'; return; }
  err.textContent = '⏳ Verificando...';
  try {
    var r = await fetch(API + '/login', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:user,password:pass})});
    var d = await r.json();
    if (r.ok && d.token) { TOK = d.token; await verifyAdmin(err); }
    else err.textContent = '✗ ' + (d.message || 'Usuario o contraseña incorrectos');
  } catch(e) { err.textContent = '✗ Error de conexión: ' + e.message; }
}
function doLoginTok() {
  var raw = document.getElementById('login-tok').value.trim();
  try { var p = JSON.parse(raw); if (typeof p === 'string') raw = p; } catch(e) {}
  var err = document.getElementById('login-err');
  if (!raw) { err.textContent = 'Ingresa tu token'; return; }
  TOK = raw; err.textContent = '⏳ Verificando...'; verifyAdmin(err);
}
async function verifyAdmin(errEl) {
  try {
    var r = await fetch(API + '/check-admin?vk_token=' + encodeURIComponent(TOK));
    var d = await r.json();
    if (r.ok && d.is_admin) {
      try { localStorage.setItem('vk_tok', TOK); } catch(e) {}
      document.getElementById('ed-admin-name').textContent = d.display_name || 'Admin';
      document.getElementById('login-screen').style.display = 'none';
      onEditorReady();
    } else {
      if (errEl) errEl.textContent = '✗ ' + (d.message || 'Solo administradores pueden acceder');
      TOK = '';
    }
  } catch(e) { if (errEl) errEl.textContent = '✗ Error de conexión: ' + e.message; TOK = ''; }
}
function logout() { TOK = ''; try { localStorage.removeItem('vk_tok'); } catch(e) {} location.reload(); }
window.addEventListener('load', function() {
  var raw = localStorage.getItem('vk_tok');
  try { var p = JSON.parse(raw); if (typeof p === 'string') raw = p; } catch(e) {}
  if (raw && typeof raw === 'string' && raw.length > 10) {
    document.getElementById('login-tok').value = raw;
    TOK = raw; verifyAdmin(document.getElementById('login-err'));
  }
});

/* ══ INIT ══════════════════════════════════════════════════════ */
function onEditorReady() {
  if (typeof vkCertEditorInit === 'function') vkCertEditorInit();
  setTimeout(loadTplManager, 500);
}

/* ══ TAB NAVIGATION ════════════════════════════════════════════ */
var _edTab = 'design';
function edTab(name) {
  _edTab = name;
  document.querySelectorAll('.ed-tab').forEach(function(t) {
    t.classList.toggle('on', t.getAttribute('onclick') === "edTab('" + name + "')");
  });
  document.querySelectorAll('.ed-tab-pane').forEach(function(p) { p.classList.remove('on'); });
  var pane = document.getElementById('ed-pane-' + name);
  if (pane) pane.classList.add('on');
  if (name === 'templates') loadTplManager();
  if (name === 'resources') loadFileGalleryIntoResources();
}

/* ══ FILE GALLERY: load into resources tab ══════════════════════ */
var _fgLoaded = false, _fgData = null;
async function loadFileGalleryIntoResources() {
  if (_fgLoaded && _fgData) { renderFileGallery(_fgData); return; }
  var card = document.getElementById('ed-file-gallery-card');
  var c    = document.getElementById('vk-file-gallery');
  if (c) c.innerHTML = '<div style="color:var(--mu);font-size:.82rem;padding:.5rem">Cargando...</div>';
  try {
    var r = await fetch(API + '/cert-templates?vk_token=' + encodeURIComponent(TOK) + '&_t=' + Date.now());
    var d = await r.json();
    if (r.ok && d.files && d.files.length) {
      _fgLoaded = true; _fgData = d.files;
      if (card) card.style.display = '';
      renderFileGallery(d.files);
    }
  } catch(e) {}
}
function renderFileGallery(files) {
  var c = document.getElementById('vk-file-gallery');
  if (!c) return;
  c.innerHTML = '';
  files.forEach(function(f) {
    var card = document.createElement('div');
    card.className = 'ed-file-card';
    card.title = f.name;
    card.innerHTML = '<img src="' + esc(f.url) + '" alt="' + esc(f.name) + '"><div class="ed-file-card-name">' + esc(f.name) + '</div>';
    card.addEventListener('click', function() { vkUseServerFileBg(f.url, f.name); edTab('design'); });
    c.appendChild(card);
  });
}

/* ══ TEMPLATE MANAGER ══════════════════════════════════════════ */
function apiUrl(path) {
  return API + path + (path.indexOf('?') >= 0 ? '&' : '?') + 'vk_token=' + encodeURIComponent(TOK) + '&_t=' + Date.now();
}
async function tplReadFull() {
  var r = await fetch(apiUrl('/cert-tpl-read'), {cache:'no-store'});
  if (!r.ok) { var t=''; try{t=await r.text();}catch(e){} throw new Error('HTTP ' + r.status + ': ' + t.substring(0,120)); }
  return r.json();
}
async function tplReadAll() { var d = await tplReadFull(); return d.templates || []; }
async function tplWriteAll(list) {
  var r = await fetch(apiUrl('/cert-tpl-write'), {method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',body:JSON.stringify({key:'_cert_tpl',data:list})});
  if (!r.ok) { var t=''; try{t=await r.text();}catch(e){} throw new Error('HTTP ' + r.status + ': ' + t.substring(0,120)); }
  return r.json();
}
async function tplReadAssign() { var d = await tplReadFull(); return d.assignments || {}; }
async function tplWriteAssign(obj) {
  var r = await fetch(apiUrl('/cert-tpl-write'), {method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',body:JSON.stringify({key:'_cert_assign',data:obj})});
  if (!r.ok) { var t=''; try{t=await r.text();}catch(e){} throw new Error('HTTP ' + r.status + ': ' + t.substring(0,120)); }
  return r.json();
}
function tplSlug(name) {
  var map = {'á':'a','é':'e','í':'i','ó':'o','ú':'u','ü':'u','ñ':'n','Á':'a','É':'e','Í':'i','Ó':'o','Ú':'u','Ü':'u','Ñ':'n',' ':'_'};
  var s = name.toLowerCase().replace(/[áéíóúüñÁÉÍÓÚÜÑ ]/g, function(c){return map[c]||c;});
  s = s.replace(/[^a-z0-9_]+/g,'_').replace(/^_+|_+$/g,'');
  return s || 'plantilla';
}

var _tplLoading = false;
async function loadTplManager() {
  if (_tplLoading) return;
  _tplLoading = true;
  var grid = document.getElementById('tpl-cards-grid');
  if (grid) grid.innerHTML = '<div style="padding:1.5rem;color:var(--tu);text-align:center">⏳ Cargando plantillas...</div>';
  try {
    var full = await tplReadFull();
    TPL_LIST    = full.templates || [];
    TPL_COURSES = full.courses   || [];
    renderTplGrid();
    renderCourseTable();
  } catch(e) {
    showMsg('tpl-manager-msg', '✗ Error: ' + esc(e.message), 'err');
  } finally { _tplLoading = false; }
}

function renderTplGrid() {
  var grid = document.getElementById('tpl-cards-grid');
  if (!grid) return;
  grid.innerHTML = '';
  TPL_LIST.forEach(function(t) {
    var card = document.createElement('div');
    card.className = 'tmgr-card';
    card.id = 'tpl-card-' + t.key;
    var thumbEl;
    if (t.thumb) {
      thumbEl = document.createElement('img');
      thumbEl.className = 'tmgr-thumb'; thumbEl.src = t.thumb;
      thumbEl.onerror = function() { this.style.display='none'; var ph=document.createElement('div'); ph.className='tmgr-thumb-placeholder'; ph.textContent='🏆'; card.insertBefore(ph,card.firstChild); };
    } else {
      thumbEl = document.createElement('div'); thumbEl.className='tmgr-thumb-placeholder'; thumbEl.textContent='🏆';
    }
    card.appendChild(thumbEl);
    var body = document.createElement('div'); body.className='tmgr-body';
    var nameEl = document.createElement('div'); nameEl.className='tmgr-name'; nameEl.title=t.name; nameEl.textContent=t.name;
    body.appendChild(nameEl);
    var meta = document.createElement('div'); meta.className='tmgr-meta';
    var badge = document.createElement('span');
    badge.className = t.courses_count>0 ? 'tmgr-badge tmgr-badge-used' : 'tmgr-badge';
    badge.textContent = t.courses_count>0 ? '✅ '+t.courses_count+' curso'+(t.courses_count>1?'s':'') : 'Sin asignar';
    meta.appendChild(badge); body.appendChild(meta);
    var actions = document.createElement('div'); actions.className='tmgr-actions';
    var bEdit=document.createElement('button'); bEdit.className='btn btn-primary btn-sm'; bEdit.textContent='✏️ Editar';
    bEdit.addEventListener('click',(function(k){return function(){loadTplIntoEditor(k);};})(t.key)); actions.appendChild(bEdit);
    var bRen=document.createElement('button'); bRen.className='btn btn-secondary btn-sm'; bRen.textContent='📝'; bRen.title='Renombrar';
    bRen.addEventListener('click',(function(k,n){return function(){openRenameTpl(k,n);};})(t.key,t.name)); actions.appendChild(bRen);
    var bDup=document.createElement('button'); bDup.className='btn btn-secondary btn-sm'; bDup.textContent='📋'; bDup.title='Duplicar';
    bDup.addEventListener('click',(function(k){return function(){openDuplicateTpl(k);};})(t.key)); actions.appendChild(bDup);
    var bDel=document.createElement('button'); bDel.className='btn btn-danger-sm'; bDel.textContent='🗑'; bDel.title='Eliminar';
    bDel.addEventListener('click',(function(k,n){return function(){deleteTpl(k,n);};})(t.key,t.name)); actions.appendChild(bDel);
    body.appendChild(actions); card.appendChild(body); grid.appendChild(card);
  });
  // Add-card
  var add = document.createElement('div'); add.className='tmgr-add-card';
  add.innerHTML='<div class="tmgr-add-icon">➕</div><div style="font-weight:700;font-size:.86rem">Nueva plantilla</div><div style="font-size:.76rem;margin-top:.2rem;text-align:center;padding:0 .5rem">Con nombre personalizado</div>';
  add.addEventListener('click', openCreateTpl); grid.appendChild(add);
}

function renderCourseTable() {
  var wrap  = document.getElementById('cca-wrap');
  var tbody = document.getElementById('cca-tbody');
  var allRow = document.getElementById('cca-all-row');
  if (!wrap || !tbody) return;
  if (!TPL_COURSES.length) { wrap.style.display='none'; return; }
  wrap.style.display = '';
  // All-assign row
  if (allRow && TPL_LIST.length) {
    var aopts = '<option value="default">⬜ Diseño global (por defecto)</option>';
    TPL_LIST.forEach(function(t){ aopts += '<option value="'+esc(t.key)+'">📄 '+esc(t.name)+'</option>'; });
    var sel = document.getElementById('assign-all-sel');
    if (sel) sel.innerHTML = aopts;
    allRow.style.display = 'flex';
  }
  tbody.innerHTML = '';
  TPL_COURSES.forEach(function(c) {
    var opts = '<option value="default"'+(c.template_key==='default'?' selected':'')+'>⬜ Default (diseño global)</option>';
    TPL_LIST.forEach(function(t){ opts += '<option value="'+esc(t.key)+'"'+(c.template_key===t.key?' selected':'')+'>📄 '+esc(t.name)+'</option>'; });
    var pillClass = c.template_key==='default' ? 'tmpl-pill tmpl-pill-default' : 'tmpl-pill';
    var pillIcon  = c.template_key==='default' ? '⬜' : '📄';
    var tr = document.createElement('tr');
    tr.innerHTML = '<td style="font-weight:700">'+esc(c.title)+'</td>'
      + '<td><span class="'+pillClass+'" id="cca-pill-'+c.id+'">'+pillIcon+' '+esc(c.template_name)+'</span></td>'
      + '<td><select id="cca-sel-'+c.id+'" style="padding:.35rem .6rem;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:.82rem;max-width:190px">'+opts+'</select></td>'
      + '<td><button class="btn btn-primary btn-sm" id="cca-btn-'+c.id+'">💾 Guardar</button> <span id="cca-msg-'+c.id+'" style="font-size:.76rem"></span></td>';
    tbody.appendChild(tr);
  });
  TPL_COURSES.forEach(function(c) {
    var btn = document.getElementById('cca-btn-' + c.id);
    if (btn) btn.addEventListener('click',(function(id){return function(){saveCertCourseTemplate(id);};})(c.id));
  });
}

async function loadTplIntoEditor(key) {
  try {
    var list = await tplReadAll();
    var found = list.find(function(t){return t.key===key;});
    if (!found) throw new Error('Plantilla no encontrada');
    if (typeof VK_CERT !== 'undefined') {
      VK_CERT.cfg = Object.assign(vkCertDefaults(), found.config||{});
      VK_CERT._editing_tpl_key  = key;
      VK_CERT._editing_tpl_name = found.name;
      if (VK_CERT.cfg.bg_type === 'image') {
        var bgSrc = VK_CERT.cfg.bg_image_data || VK_CERT.cfg.bg_image_url || '';
        if (bgSrc) {
          await new Promise(function(resolve) { var img=new Image(); img.onload=function(){VK_CERT.bgImg=img;resolve();}; img.onerror=function(){VK_CERT.bgImg=null;resolve();}; img.src=bgSrc; });
        } else { VK_CERT.bgImg = null; }
      } else { VK_CERT.bgImg = null; }
      if (typeof vkCertFormSync   === 'function') vkCertFormSync();
      if (typeof vkCertPreviewRedraw === 'function') vkCertPreviewRedraw();
      var btn = document.getElementById('vk-save-cert-btn');
      if (btn) { btn.innerHTML='💾 Guardar en "'+esc(found.name)+'"'; btn.onclick=function(){saveTplFromEditor(key,found.name);}; }
      var notice = document.getElementById('tpl-edit-notice');
      if (notice) { notice.style.display=''; notice.className='msg msg-info'; notice.innerHTML='✏️ Editando: <strong>'+esc(found.name)+'</strong> — haz cambios en el editor y pulsa Guardar.'; }
      edTab('design');
    }
  } catch(e) { showMsg('tpl-manager-msg', '✗ Error: ' + esc(e.message), 'err'); }
}

async function saveTplFromEditor(key, name) {
  if (typeof VK_CERT === 'undefined') return;
  var btn = document.getElementById('vk-save-cert-btn');
  if (btn) { btn.disabled=true; btn.innerHTML='<span class="loader"></span> Guardando...'; }
  try {
    var list = await tplReadAll();
    var idx  = list.findIndex(function(t){return t.key===key;});
    if (idx >= 0) { list[idx].config=VK_CERT.cfg; list[idx].name=name; if(VK_CERT.cfg.bg_image_url) list[idx].thumb=VK_CERT.cfg.bg_image_url; }
    else { list.push({key:key,name:name,config:VK_CERT.cfg,thumb:VK_CERT.cfg.bg_image_url||'',created_at:new Date().toISOString()}); }
    var d = await tplWriteAll(list);
    if (d && d.success) {
      showMsg('tpl-manager-msg','✅ Plantilla "'+esc(name)+'" guardada.','ok');
      _tplLoading=false; await loadTplManager();
    } else { showMsg('tpl-manager-msg','✗ '+(d.message||'Error al guardar'),'err'); }
  } catch(e) { showMsg('tpl-manager-msg','✗ Error de conexión','err'); }
  finally {
    if (btn) { btn.disabled=false; btn.innerHTML='💾 Guardar diseño'; btn.onclick=vkSaveCertConfig; }
    if (typeof VK_CERT !== 'undefined') { VK_CERT._editing_tpl_key=null; VK_CERT._editing_tpl_name=null; }
    var notice = document.getElementById('tpl-edit-notice');
    if (notice) notice.style.display = 'none';
  }
}

async function saveCertCourseTemplate(courseId) {
  var sel=document.getElementById('cca-sel-'+courseId), msgEl=document.getElementById('cca-msg-'+courseId), pill=document.getElementById('cca-pill-'+courseId);
  if (!sel) return;
  var tkey=sel.value, tname=tkey==='default'?'Default (diseño global)':((TPL_LIST.find(function(t){return t.key===tkey;})||{}).name||tkey);
  try {
    var assign=await tplReadAssign();
    if (tkey==='default') delete assign[courseId]; else assign[courseId]=tkey;
    var d=await tplWriteAssign(assign);
    if (d&&d.success) {
      if (msgEl){msgEl.style.color='#2e7d32';msgEl.textContent='✅ Guardado';setTimeout(function(){msgEl.textContent='';},3000);}
      if (pill){pill.className=tkey==='default'?'tmpl-pill tmpl-pill-default':'tmpl-pill';pill.innerHTML=(tkey==='default'?'⬜':'📄')+' '+esc(tname);}
      var course=TPL_COURSES.find(function(c){return c.id===courseId;}); if(course){course.template_key=tkey;course.template_name=tname;}
    } else { if (msgEl){msgEl.style.color='#c62828';msgEl.textContent='✗ Error';} }
  } catch(e) { if (msgEl){msgEl.style.color='#c62828';msgEl.textContent='✗ Red';} }
}

async function assignAllCourses(tkey) {
  if (!tkey) return;
  var tname=tkey==='default'?'diseño global':((TPL_LIST.find(function(t){return t.key===tkey;})||{}).name||tkey);
  if (!confirm('¿Asignar "'+tname+'" a TODOS los cursos? Reemplazará asignaciones individuales.')) return;
  var btn=document.getElementById('assign-all-btn'); if(btn){btn.disabled=true;btn.textContent='⏳ Asignando...';}
  try {
    var assign={};
    if (tkey!=='default') TPL_COURSES.forEach(function(c){assign[c.id]=tkey;});
    var d=await tplWriteAssign(assign);
    if (d&&d.success) { _tplLoading=false; await loadTplManager(); showMsg('tpl-manager-msg','✅ Asignada "'+esc(tname)+'" a '+TPL_COURSES.length+' cursos.','ok'); }
    else alert('Error: '+((d&&d.message)||'desconocido'));
  } catch(e) { alert('Error: '+e.message); }
  finally { if(btn){btn.disabled=false;btn.textContent='📋 Asignar a todos';} }
}

/* ── Modal ── */
function openCreateTpl() {
  TPL_MODAL_MODE='create'; TPL_MODAL_KEY='';
  document.getElementById('tpl-modal-title').textContent='➕ Nueva plantilla';
  document.getElementById('tpl-modal-name').value='';
  document.getElementById('tpl-modal-msg').textContent='';
  var src=document.getElementById('tpl-modal-src');
  src.innerHTML='<option value="">⬜ Diseño en blanco</option><option value="__global__">🌐 Diseño global actual</option>';
  TPL_LIST.forEach(function(t){src.innerHTML+='<option value="'+esc(t.key)+'">📄 '+esc(t.name)+'</option>';});
  document.getElementById('tpl-modal-src-wrap').style.display='';
  document.getElementById('tpl-modal').style.display='flex';
  document.getElementById('tpl-modal-name').focus();
}
function openRenameTpl(key,name) {
  TPL_MODAL_MODE='rename'; TPL_MODAL_KEY=key;
  document.getElementById('tpl-modal-title').textContent='📝 Renombrar plantilla';
  document.getElementById('tpl-modal-name').value=name;
  document.getElementById('tpl-modal-msg').textContent='';
  document.getElementById('tpl-modal-src-wrap').style.display='none';
  document.getElementById('tpl-modal').style.display='flex';
  document.getElementById('tpl-modal-name').focus();
}
function openDuplicateTpl(key) {
  TPL_MODAL_MODE='duplicate'; TPL_MODAL_KEY=key;
  var orig=(TPL_LIST.find(function(t){return t.key===key;})||{}).name||key;
  document.getElementById('tpl-modal-title').textContent='📋 Duplicar plantilla';
  document.getElementById('tpl-modal-name').value=orig+' (copia)';
  document.getElementById('tpl-modal-msg').textContent='';
  document.getElementById('tpl-modal-src-wrap').style.display='none';
  document.getElementById('tpl-modal').style.display='flex';
  document.getElementById('tpl-modal-name').focus();
}
function closeTplModal() { document.getElementById('tpl-modal').style.display='none'; }
document.addEventListener('DOMContentLoaded', function(){
  var bg=document.getElementById('tpl-modal');
  if (bg) bg.addEventListener('click',function(e){if(e.target===bg)closeTplModal();});
  var inp=document.getElementById('tpl-modal-name');
  if (inp) inp.addEventListener('keydown',function(e){if(e.key==='Enter')confirmTplModal();});
});
async function confirmTplModal() {
  var name=document.getElementById('tpl-modal-name').value.trim(), msgEl=document.getElementById('tpl-modal-msg');
  if (!name){msgEl.style.color='#c62828';msgEl.textContent='Escribe un nombre';return;}
  msgEl.style.color='var(--ts)';msgEl.textContent='⏳ Guardando...';
  if (TPL_MODAL_MODE==='create') {
    var srcKey=document.getElementById('tpl-modal-src').value, srcCfg={};
    if (srcKey==='__global__') srcCfg=(typeof VK_CERT!=='undefined')?Object.assign({},VK_CERT.cfg):{};
    else if (srcKey){var f=TPL_LIST.find(function(t){return t.key===srcKey;});if(f)srcCfg=Object.assign({},f.config||{});}
    try {
      var list=await tplReadAll(), slug=tplSlug(name), base=slug, i=2;
      while(list.find(function(t){return t.key===slug;})) slug=base+'_'+i++;
      list.push({key:slug,name:name,config:srcCfg||{},thumb:'',created_at:new Date().toISOString()});
      await tplWriteAll(list); closeTplModal(); _tplLoading=false; await loadTplManager();
      showMsg('tpl-manager-msg','✅ Plantilla "'+esc(name)+'" creada.','ok');
    } catch(e){msgEl.style.color='#c62828';msgEl.textContent='✗ '+e.message;}
  } else if (TPL_MODAL_MODE==='rename') {
    try {
      var list2=await tplReadAll(), t2=list2.find(function(t){return t.key===TPL_MODAL_KEY;});
      if(t2) t2.name=name; await tplWriteAll(list2); closeTplModal(); _tplLoading=false; await loadTplManager();
      showMsg('tpl-manager-msg','✅ Renombrada a "'+esc(name)+'".','ok');
    } catch(e){msgEl.style.color='#c62828';msgEl.textContent='✗ '+e.message;}
  } else if (TPL_MODAL_MODE==='duplicate') {
    try {
      var list3=await tplReadAll(), src3=list3.find(function(t){return t.key===TPL_MODAL_KEY;});
      if(!src3) throw new Error('Plantilla original no encontrada');
      var slug3=tplSlug(name),base3=slug3,i3=2;
      while(list3.find(function(t){return t.key===slug3;})) slug3=base3+'_'+i3++;
      list3.push({key:slug3,name:name,config:JSON.parse(JSON.stringify(src3.config||{})),thumb:src3.thumb||'',created_at:new Date().toISOString()});
      await tplWriteAll(list3); closeTplModal(); _tplLoading=false; await loadTplManager();
      showMsg('tpl-manager-msg','✅ Duplicada como "'+esc(name)+'".','ok');
    } catch(e){msgEl.style.color='#c62828';msgEl.textContent='✗ '+e.message;}
  }
}
async function deleteTpl(key,name) {
  if (!confirm('¿Eliminar "'+name+'"? Esta acción no se puede deshacer.')) return;
  try {
    var list=await tplReadAll(), assign=await tplReadAssign();
    var inUse=Object.values(assign).filter(function(v){return v===key;}).length;
    if (inUse>0){alert('Asignada a '+inUse+' curso(s). Reasígnalos primero.');return;}
    await tplWriteAll(list.filter(function(t){return t.key!==key;}));
    _tplLoading=false; await loadTplManager();
    showMsg('tpl-manager-msg','✅ Eliminada: '+esc(name),'ok');
  } catch(e){showMsg('tpl-manager-msg','✗ '+esc(e.message),'err');}
}
async function cleanDuplicates() {
  if (!confirm('Eliminar plantillas con nombre duplicado (conserva la más reciente). ¿Continuar?')) return;
  try {
    var list=await tplReadAll(), assign=await tplReadAssign(), inUse=Object.values(assign), seen={}, toKeep=[];
    list.slice().sort(function(a,b){return (b.created_at||'').localeCompare(a.created_at||'');})
      .forEach(function(t){var k=t.name.trim().toLowerCase();if(!seen[k]||inUse.indexOf(t.key)>=0){seen[k]=true;toKeep.push(t);}});
    if (toKeep.length===list.length){showMsg('tpl-manager-msg','✅ Sin duplicados.','ok');return;}
    await tplWriteAll(toKeep); _tplLoading=false; await loadTplManager();
    showMsg('tpl-manager-msg','✅ '+(list.length-toKeep.length)+' duplicado(s) eliminado(s).','ok');
  } catch(e){showMsg('tpl-manager-msg','✗ '+esc(e.message),'err');}
}

/* ══ UTILITIES ══════════════════════════════════════════════════ */
function showMsg(id, msg, type) {
  var el=document.getElementById(id); if(!el) return;
  el.className='msg msg-'+type; el.innerHTML=msg;
  if (type!=='err') setTimeout(function(){if(el){el.textContent='';el.className='';}},6000);
}
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

</script>
</body>
</html>
