<?php
/**
 * cert-editor.php — Editor de Certificados VidaKushala v2.0
 * Secciones:  1) Diseñar  2) Plantillas  3) Asignación
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>VidaKushala — Editor de Certificados</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Cormorant+Garamond:wght@600;700&display=swap" rel="stylesheet">
<style>
/* ── VARIABLES ───────────────────────────────────────────── */
:root{
  --vk-plum:#3a0f28;--vk-rose:#c44d8a;--vk-pink:#e87ab8;
  --vk-petal:#fce8f1;--card:#fff;
  --grad-accent:linear-gradient(135deg,#c44d8a,#8b2d5a);
  --grad-hero:linear-gradient(135deg,#3a0f28,#7b2560);
  --r:16px;--shs:0 2px 12px rgba(58,15,40,.08);
  --border:#f0e0ea;--td:#2d1020;--ts:#8a6070;--tu:#b899a8;
  --bd:#e0cfd8;--ca:#fff;--fo:#fdf8fb;--mu:#9a7080;--su:#fdf5f8;--tx:#2d1020;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,#fbf5f8,#f3e9f0);min-height:100vh;color:var(--td);}

/* ── LOGIN ──────────────────────────────────────────────── */
#login-screen{position:fixed;inset:0;background:var(--grad-hero);display:flex;align-items:center;justify-content:center;z-index:1000;}
.login-box{background:white;border-radius:24px;padding:2.25rem;width:340px;text-align:center;box-shadow:0 24px 80px rgba(58,15,40,.35);}
.login-box .logo{width:64px;height:64px;border-radius:20px;background:var(--grad-accent);display:flex;align-items:center;justify-content:center;font-size:1.75rem;margin:0 auto 1rem;}
.login-box h2{font-family:'Cormorant Garamond',serif;color:var(--vk-plum);font-size:1.6rem;margin-bottom:.35rem;}

/* ── HEADER ─────────────────────────────────────────────── */
.header{background:var(--grad-hero);color:#fff;padding:1rem 1.5rem;display:flex;align-items:center;gap:.85rem;box-shadow:0 4px 20px rgba(58,15,40,.2);position:sticky;top:0;z-index:100;}
.header-icon{width:44px;height:44px;border-radius:14px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.header h1{font-family:'Cormorant Garamond',serif;font-size:1.35rem;font-weight:700;}
.header-right{margin-left:auto;display:flex;align-items:center;gap:.75rem;}

/* ── LAYOUT ─────────────────────────────────────────────── */
.container{max-width:1400px;margin:0 auto;padding:1.5rem}
.card{background:var(--card);border-radius:var(--r);padding:1.4rem;margin-bottom:1rem;box-shadow:var(--shs);border:1px solid var(--border);}
.card-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem;padding-bottom:.75rem;border-bottom:1.5px solid var(--vk-petal);}
.card-title{font-family:'Cormorant Garamond',serif;font-size:1.15rem;font-weight:700;color:var(--vk-plum);}
.card-hint{font-size:.8rem;color:var(--tu);}

/* ── TOP TABS ────────────────────────────────────────────── */
.main-tabs{display:flex;gap:.4rem;margin-bottom:1.25rem;flex-wrap:wrap;background:var(--card);border-radius:14px;padding:.5rem;box-shadow:var(--shs);border:1px solid var(--border);}
.main-tab{padding:.55rem 1.25rem;border-radius:10px;border:none;background:transparent;cursor:pointer;font-size:.88rem;font-weight:600;color:var(--ts);transition:.18s;font-family:inherit;display:flex;align-items:center;gap:.4rem;}
.main-tab:hover{background:var(--vk-petal);color:var(--vk-rose);}
.main-tab.on{background:var(--grad-accent);color:white;box-shadow:0 4px 14px rgba(196,77,138,.25);}
.panel{display:none}.panel.on{display:block}

/* ── FIELDS / BTNS ──────────────────────────────────────── */
.field{margin-bottom:.9rem;}
.field label{display:block;font-size:.82rem;font-weight:700;color:var(--ts);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.05em;}
.field input,.field select,.field textarea{width:100%;padding:.7rem .95rem;border:1.5px solid var(--border);border-radius:12px;font-family:inherit;font-size:.9rem;color:var(--td);outline:none;transition:.18s;background:white;}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--vk-rose);}
.btn{padding:.7rem 1.4rem;border:none;border-radius:12px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;transition:.18s;display:inline-flex;align-items:center;gap:.4rem;}
.btn-primary{background:var(--grad-accent);color:#fff;box-shadow:0 4px 16px rgba(196,77,138,.28);}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(196,77,138,.36);}
.btn-secondary{background:var(--vk-petal);color:var(--vk-plum);border:1.5px solid rgba(196,77,138,.15);}
.btn-secondary:hover{background:#f8d8eb;}
.btn-danger{background:#fff0f0;color:#c62828;border:1.5px solid #ffcdd2;}
.btn-danger:hover{background:#ffebee;}
.btn-sm{padding:.42rem .9rem;font-size:.8rem;}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none!important;}
.msg{padding:.65rem .95rem;border-radius:10px;font-size:.85rem;margin:.5rem 0;display:flex;align-items:center;gap:.5rem;}
.msg-ok{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;}
.msg-err{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;}
.msg-info{background:var(--vk-petal);color:var(--vk-plum);border:1px solid rgba(196,77,138,.2);}

/* ── EDITING BANNER ─────────────────────────────────────── */
#editing-banner{background:var(--grad-accent);color:white;border-radius:12px;padding:.8rem 1.15rem;margin-bottom:.9rem;display:none;align-items:center;gap:.75rem;flex-wrap:wrap;}
#editing-banner.on{display:flex;}

/* ── EDITOR TOOLBAR ─────────────────────────────────────── */
.ed-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:.4rem;padding:.75rem 1rem;background:var(--su);border-radius:12px;margin-bottom:.85rem;border:1px solid var(--border);}
.ed-toolbar-left{display:flex;gap:.4rem;flex-wrap:wrap;flex:1;}
.ed-msg{font-size:.83rem;padding:.3rem .7rem;border-radius:8px;flex:none;min-width:0;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ed-msg.ok{color:#2e7d32;background:#e8f5e9;}
.ed-msg.err{color:#c62828;background:#ffebee;}

/* ── EDITOR INNER TABS ──────────────────────────────────── */
.ed-tabs{display:flex;gap:.3rem;margin-bottom:.9rem;border-bottom:2px solid var(--bd);padding-bottom:.4rem;flex-wrap:wrap;}
.ed-tab{background:transparent;border:1.5px solid var(--bd);border-bottom:none;border-radius:8px 8px 0 0;padding:.38rem .85rem;cursor:pointer;font-size:.82rem;color:var(--mu);transition:all .15s;font-family:inherit;font-weight:600;}
.ed-tab:hover{border-color:var(--vk-rose);color:var(--vk-rose);}
.ed-tab.active{background:var(--vk-rose);color:#fff;border-color:var(--vk-rose);}
.ed-tab-content{display:none;}
.ed-tab-content.on{display:block;}

/* ── EDITOR LAYOUT ──────────────────────────────────────── */
.ed-layout{display:grid;grid-template-columns:290px 1fr;gap:1rem;min-height:600px;}
@media(max-width:1000px){.ed-layout{grid-template-columns:1fr;}}

/* ── CONTROLS PANEL ─────────────────────────────────────── */
.ctrl-panel{overflow-y:auto;max-height:78vh;padding-right:.2rem;}
.ctrl-sect{border:1px solid var(--bd);border-radius:10px;margin-bottom:.5rem;background:var(--ca);overflow:hidden;}
.ctrl-sect summary{padding:.6rem .8rem;cursor:pointer;font-weight:700;font-size:.82rem;color:var(--tu);list-style:none;display:flex;align-items:center;gap:.4rem;user-select:none;background:var(--fo);}
.ctrl-sect summary::-webkit-details-marker{display:none}
.ctrl-sect summary::before{content:'▶';font-size:.58rem;transition:transform .15s;opacity:.6;}
.ctrl-sect[open] summary::before{transform:rotate(90deg);}
.ctrl-body{padding:.55rem .8rem .8rem;}
.ctrl-field{margin-bottom:.5rem;}
.ctrl-field label{display:block;font-size:.75rem;color:var(--mu);margin-bottom:.2rem;font-weight:600;}
.ctrl-field input[type=text],.ctrl-field input[type=number],.ctrl-field select{width:100%;padding:.32rem .5rem;border:1.5px solid var(--bd);border-radius:7px;background:var(--fo);color:var(--tx);font-size:.82rem;font-family:inherit;}
.ctrl-field input[type=text]:focus,.ctrl-field input[type=number]:focus,.ctrl-field select:focus{border-color:var(--vk-rose);outline:none;}
.ctrl-field input[type=number]{max-width:80px;}
.ctrl-row{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.5rem;align-items:flex-end;}
.ctrl-row .ctrl-field{margin-bottom:0;flex-shrink:0;}
.color-pair{display:flex;gap:.35rem;align-items:center;}
.color-pair input[type=color]{width:36px;height:32px;border:1.5px solid var(--bd);border-radius:7px;cursor:pointer;padding:2px;}
.color-pair input[type=text]{flex:1;font-family:monospace;font-size:.78rem;}
.upload-zone{border:2px dashed var(--bd);border-radius:10px;padding:.75rem;text-align:center;cursor:pointer;color:var(--mu);font-size:.82rem;transition:all .18s;}
.upload-zone:hover{border-color:var(--vk-rose);background:rgba(196,77,138,.04);}
.radio-row{display:flex;gap:.75rem;flex-wrap:wrap;}
.radio-row label{font-size:.82rem;display:flex;align-items:center;gap:.3rem;cursor:pointer;}
.chk-row{display:flex;gap:.75rem;flex-wrap:wrap;}
.chk-row label{font-size:.8rem;display:flex;align-items:center;gap:.25rem;cursor:pointer;}

/* ── CANVAS PANEL ───────────────────────────────────────── */
.preview-panel{display:flex;flex-direction:column;gap:.6rem;}
.canvas-wrap{background:#d0d0d8;border-radius:12px;overflow:visible;padding:.5rem;display:flex;justify-content:center;align-items:flex-start;}
#vk-cert-canvas{max-width:100%;border-radius:6px;box-shadow:0 6px 24px rgba(0,0,0,.22);display:block;}
.preview-data{background:var(--su);border:1px solid var(--bd);border-radius:10px;padding:.7rem .9rem;}
.preview-data-title{font-size:.75rem;font-weight:700;color:var(--mu);margin-bottom:.5rem;display:flex;align-items:center;gap:.35rem;}
.preview-data-grid{display:grid;grid-template-columns:1fr 1fr;gap:.3rem .5rem;}
.preview-data-field{display:flex;align-items:center;gap:.3rem;font-size:.75rem;}
.preview-data-field label{color:var(--mu);white-space:nowrap;min-width:48px;font-weight:600;}
.preview-data-field input{flex:1;padding:.22rem .4rem;border:1.5px solid var(--bd);border-radius:6px;font-size:.74rem;background:var(--fo);color:var(--tx);min-width:0;font-family:inherit;}

/* ── MINI TEMPLATE GALLERY ──────────────────────────────── */
.mini-tpl-bar{display:flex;align-items:center;justify-content:space-between;background:var(--su);border:1px solid var(--bd);border-radius:10px;padding:.55rem .8rem;margin-bottom:.6rem;}
.mini-tpl-bar-label{font-size:.78rem;font-weight:700;color:var(--tu);}
.mini-gallery{display:grid;grid-template-columns:repeat(5,1fr);gap:.3rem;}
.vk-tmpl-card{border:2px solid var(--bd);border-radius:8px;padding:.4rem;cursor:pointer;transition:border-color .15s,box-shadow .15s;background:var(--ca);}
.vk-tmpl-card:hover{border-color:var(--vk-rose);}
.vk-tmpl-card.active{border-color:var(--vk-rose);box-shadow:0 0 0 2px rgba(196,77,138,.3);}
.vk-tmpl-name{font-size:.62rem;color:var(--mu);margin-top:.25rem;text-align:center;line-height:1.2;}
.tmpl-gallery-full{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:.65rem;margin-bottom:1rem;}
.tmpl-gallery-full .vk-tmpl-card{padding:.5rem;}

/* ── FONT PANEL ─────────────────────────────────────────── */
.font-section{margin-bottom:1.5rem;}
.font-section h3{font-size:.88rem;font-weight:700;margin-bottom:.6rem;color:var(--tu);}
.font-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:.5rem;}
.font-card{border:2px solid var(--bd);border-radius:8px;padding:.6rem .8rem;cursor:pointer;transition:border-color .15s,background .15s;display:flex;flex-direction:column;gap:.3rem;background:var(--ca);}
.font-card:hover{border-color:var(--vk-rose);}
.font-card.active{border-color:var(--vk-rose);background:rgba(196,77,138,.07);}
.font-card-name{font-size:.7rem;color:var(--mu);font-weight:600;}

/* ── ASSETS PANEL ───────────────────────────────────────── */
.assets-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:.85rem;}
.asset-card{border:1.5px solid var(--bd);border-radius:12px;padding:1rem;background:var(--ca);}
.asset-card h3{font-size:.88rem;font-weight:700;margin-bottom:.3rem;color:var(--td);}
.asset-card p{font-size:.78rem;color:var(--mu);margin-bottom:.65rem;line-height:1.4;}
.file-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.5rem;margin-top:.5rem;}
.file-card{border:2px solid var(--bd);border-radius:8px;overflow:hidden;cursor:pointer;transition:border-color .15s,transform .12s;}
.file-card:hover{border-color:var(--vk-rose);transform:translateY(-2px);}
.file-card img{width:100%;height:80px;object-fit:cover;display:block;}
.file-card-name{font-size:.67rem;padding:.22rem .3rem;text-align:center;color:var(--mu);word-break:break-all;}

/* ── TEMPLATE LIBRARY ───────────────────────────────────── */
.tpl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin-bottom:1rem;}
.tpl-card{background:#fff;border:2px solid var(--border);border-radius:14px;overflow:hidden;transition:.18s;}
.tpl-card:hover{border-color:var(--vk-rose);transform:translateY(-2px);box-shadow:0 8px 24px rgba(196,77,138,.15);}
.tpl-thumb{width:100%;height:112px;object-fit:cover;display:block;}
.tpl-thumb-ph{width:100%;height:112px;display:flex;align-items:center;justify-content:center;font-size:2.5rem;background:linear-gradient(135deg,var(--vk-petal),#ede8f8);}
.tpl-body{padding:.8rem;}
.tpl-name{font-weight:700;font-size:.88rem;color:var(--vk-plum);margin-bottom:.2rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tpl-meta{font-size:.73rem;color:var(--tu);margin-bottom:.55rem;}
.tpl-badge{display:inline-block;padding:.12rem .5rem;border-radius:20px;font-size:.68rem;font-weight:700;background:var(--vk-petal);color:var(--vk-plum);}
.tpl-badge-used{background:#e8f5e9;color:#2e7d32;}
.tpl-actions{display:flex;gap:.3rem;flex-wrap:wrap;}
.tpl-add{background:transparent;border:2px dashed var(--border);border-radius:14px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;min-height:206px;cursor:pointer;transition:.18s;color:var(--ts);}
.tpl-add:hover{border-color:var(--vk-rose);color:var(--vk-rose);}

/* ── COURSE TABLE ───────────────────────────────────────── */
.course-table{width:100%;border-collapse:collapse;font-size:.84rem;}
.course-table th{background:var(--vk-petal);padding:.55rem .85rem;text-align:left;font-weight:700;color:var(--vk-plum);font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;}
.course-table td{padding:.65rem .85rem;border-bottom:1px solid var(--border);vertical-align:middle;}
.course-table tr:last-child td{border-bottom:none;}
.course-table tr:hover td{background:#fdf5f9;}
.tpl-pill{display:inline-flex;align-items:center;gap:.3rem;background:var(--vk-petal);color:var(--vk-plum);border:1px solid rgba(196,77,138,.2);border-radius:20px;padding:.2rem .7rem;font-size:.76rem;font-weight:600;}
.tpl-pill-default{background:#f0f0f0;color:var(--tu);border-color:#ddd;}

/* ── MODAL ──────────────────────────────────────────────── */
.modal-bg{position:fixed;inset:0;background:rgba(30,0,20,.55);z-index:2000;display:flex;align-items:center;justify-content:center;padding:1rem;}
.modal{background:#fff;border-radius:20px;max-width:440px;width:100%;padding:1.75rem;box-shadow:0 24px 80px rgba(58,15,40,.35);position:relative;}
.modal h3{font-family:'Cormorant Garamond',serif;color:var(--vk-plum);font-size:1.25rem;margin-bottom:1rem;}
.modal-close{position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--tu);line-height:1;}

/* ── MISC ───────────────────────────────────────────────── */
@keyframes spin{to{transform:rotate(360deg)}}
.spin{display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,.4);border-top-color:white;border-radius:50%;animation:spin .6s linear infinite;margin-right:.3rem;vertical-align:middle;}
.spin-dark{border-color:rgba(58,15,40,.2);border-top-color:var(--vk-plum);}
.empty-state{text-align:center;padding:2.5rem;color:var(--tu);}
.empty-state-icon{font-size:2.5rem;margin-bottom:.65rem;}
.empty-state p{font-size:.9rem;}
</style>
<script src="vk-cert-renderer.js" charset="UTF-8"></script>
<script src="vk-cert-editor.js"   charset="UTF-8"></script>
</head>
<body>

<!-- ══ LOGIN ═══════════════════════════════════════════════════════ -->
<div id="login-screen">
  <div class="login-box">
    <div class="logo">🏆</div>
    <h2>Editor de Certificados</h2>
    <p style="color:var(--ts);font-size:.85rem;margin-bottom:1.25rem">VidaKushala · Solo administradores</p>
    <div style="display:flex;border-radius:10px;overflow:hidden;border:1.5px solid var(--border);margin-bottom:1rem">
      <button id="ltab-pass" onclick="switchLoginTab('pass')" style="flex:1;padding:.55rem;border:none;background:var(--grad-accent);color:white;font-family:inherit;font-size:.83rem;font-weight:700;cursor:pointer">🔐 Usuario</button>
      <button id="ltab-tok"  onclick="switchLoginTab('tok')"  style="flex:1;padding:.55rem;border:none;background:white;color:var(--ts);font-family:inherit;font-size:.83rem;font-weight:600;cursor:pointer">🔑 Token</button>
    </div>
    <div id="lpanel-pass">
      <div class="field"><input type="text"     id="login-user" placeholder="Usuario o email" autocomplete="username" style="margin-bottom:.6rem"></div>
      <div class="field"><input type="password" id="login-pass" placeholder="Contraseña" autocomplete="current-password" onkeydown="if(event.key==='Enter')doLogin()"></div>
      <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="doLogin()">Entrar</button>
    </div>
    <div id="lpanel-tok" style="display:none">
      <div class="field"><input type="text" id="login-tok" placeholder="Pega tu vk_tok aquí" style="text-align:center"></div>
      <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="doLoginTok()">Entrar con token</button>
    </div>
    <p id="login-err" style="color:#c62828;font-size:.82rem;margin-top:.65rem;min-height:1.8em;line-height:1.4"></p>
  </div>
</div>

<!-- ══ HEADER ══════════════════════════════════════════════════════ -->
<div class="header">
  <div class="header-icon">🏆</div>
  <div>
    <h1>Editor de Certificados</h1>
    <p style="font-size:.75rem;opacity:.7">VidaKushala · Diseño, plantillas y asignación</p>
  </div>
  <div class="header-right">
    <span id="admin-name" style="font-size:.82rem;opacity:.8"></span>
    <a href="administrar.php" class="btn btn-sm" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.3);text-decoration:none">← Panel</a>
    <button class="btn btn-sm" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.3)" onclick="doLogout()">Salir</button>
  </div>
</div>

<div class="container">

  <!-- ══ TABS PRINCIPALES ════════════════════════════════════════════ -->
  <div class="main-tabs">
    <button class="main-tab on" data-tab="design"    onclick="showMainTab('design')">   🎨 Diseñar certificado</button>
    <button class="main-tab"    data-tab="templates" onclick="showMainTab('templates')">📁 Biblioteca de plantillas</button>
    <button class="main-tab"    data-tab="assign"    onclick="showMainTab('assign')">   📋 Asignar a cursos</button>
  </div>

  <!-- ══ PANEL: DISEÑAR ══════════════════════════════════════════════ -->
  <div class="panel on" id="panel-design">

    <!-- Banner "editando plantilla X" -->
    <div id="editing-banner">
      <span style="font-size:1.1rem">✏️</span>
      <span style="font-weight:600;flex:1">Editando plantilla: <strong id="editing-tpl-name"></strong></span>
      <button class="btn btn-sm" style="background:rgba(255,255,255,.25);color:white;border:1px solid rgba(255,255,255,.4)" onclick="stopEditingTpl()">✕ Volver al diseño global</button>
    </div>

    <!-- Toolbar de acciones -->
    <div class="ed-toolbar">
      <div class="ed-toolbar-left">
        <button class="btn btn-primary" id="vk-save-cert-btn" onclick="vkSaveCertConfig()">💾 Guardar diseño</button>
        <button class="btn btn-secondary" onclick="vkResetCertConfig()" title="Restaurar valores por defecto">↩ Defaults</button>
        <button class="btn btn-secondary" onclick="vkClearCertCache()" title="Eliminar certificados cacheados para regenerarlos" style="border-color:#e87ab8;color:var(--vk-rose)">🗑 Limpiar caché</button>
        <button class="btn btn-secondary" onclick="vkDownloadPreview()" title="Descargar preview como PNG">⬇ PNG</button>
      </div>
      <div id="vk-cert-msg" class="ed-msg"></div>
    </div>

    <!-- Tabs del editor -->
    <div class="ed-tabs">
      <button class="ed-tab active" id="vk-etab-design"    onclick="vkEditorTab('design')">🎨 Diseño</button>
      <button class="ed-tab"        id="vk-etab-templates" onclick="vkEditorTab('templates')">🖼 Plantillas base</button>
      <button class="ed-tab"        id="vk-etab-fonts"     onclick="vkEditorTab('fonts')">🔤 Tipografía</button>
      <button class="ed-tab"        id="vk-etab-assets"    onclick="vkEditorTab('assets')">📁 Imágenes</button>
    </div>

    <!-- ── TAB: DISEÑO ─────────────────────────────────────── -->
    <div class="ed-tab-content on" id="vk-tc-design">
      <div class="ed-layout">

        <!-- Panel de controles (izquierda) -->
        <div class="ctrl-panel" id="vk-ctrl-panel">

          <!-- Mini galería de plantillas base -->
          <div class="mini-tpl-bar">
            <span class="mini-tpl-bar-label">🎨 Plantilla base:</span>
            <button class="btn btn-sm btn-secondary" onclick="vkEditorTab('templates')" style="padding:.25rem .6rem;font-size:.72rem">Ver todas →</button>
          </div>
          <div id="vk-mini-tmpl-gallery" class="mini-gallery" style="margin-bottom:.75rem"></div>

          <!-- Fondo -->
          <details class="ctrl-sect" open>
            <summary>🖼 Fondo del certificado</summary>
            <div class="ctrl-body">
              <div class="ctrl-field">
                <label>Tipo de fondo</label>
                <div class="radio-row">
                  <label><input type="radio" name="vk-bg_type" value="color"    onchange="vkCfgSet('bg_type',this.value);vkBgTypeUpdate()"> Color sólido</label>
                  <label><input type="radio" name="vk-bg_type" value="gradient" onchange="vkCfgSet('bg_type',this.value);vkBgTypeUpdate()"> Degradado</label>
                  <label><input type="radio" name="vk-bg_type" value="image"    onchange="vkCfgSet('bg_type',this.value);vkBgTypeUpdate()"> Imagen</label>
                </div>
              </div>
              <!-- Color / Degradado -->
              <div id="vk-bg-color-row">
                <div class="ctrl-field">
                  <label>Color de fondo</label>
                  <div class="color-pair">
                    <input type="color" id="vk-bg_color" value="#ffffff" oninput="vkCfgSet('bg_color',this.value);vkSyncHex('vk-bg_color','vk-bg_color_hex')">
                    <input type="text"  id="vk-bg_color_hex" value="#ffffff" maxlength="7" oninput="vkCfgSet('bg_color',this.value);vkSyncColor('vk-bg_color_hex','vk-bg_color')">
                  </div>
                </div>
                <div id="vk-gradient-fields" style="display:none">
                  <div class="ctrl-field">
                    <label>Degradado (inicio → fin)</label>
                    <div class="color-pair">
                      <input type="color" id="vk-bg_gradient_from" value="#0f2044" oninput="vkCfgSet('bg_gradient_from',this.value);vkCfgSet('bg_gradient',true)">
                      <span style="font-size:.8rem;color:var(--mu);padding:0 .2rem">→</span>
                      <input type="color" id="vk-bg_gradient_to"   value="#1a3a6b" oninput="vkCfgSet('bg_gradient_to',this.value);vkCfgSet('bg_gradient',true)">
                    </div>
                  </div>
                </div>
              </div>
              <!-- Imagen de fondo -->
              <div id="vk-bg-image-row" style="display:none">
                <div class="upload-zone" onclick="document.getElementById('vk-bg-upload').click()">
                  <div id="vk-upload-zone-inner">📁 Clic para subir imagen de fondo (JPG/PNG)</div>
                </div>
                <input type="file" id="vk-bg-upload" accept="image/*" style="display:none" onchange="vkUploadBgImage(this)">
                <div id="vk-bg-img-thumb" style="margin-top:.45rem"></div>
                <div class="ctrl-field" style="margin-top:.6rem">
                  <label>Oscurecer imagen — <span id="vk-overlay-n">0</span>%</label>
                  <input type="range" id="vk-bg_overlay" min="0" max="70" value="0" style="width:100%" oninput="vkCfgSet('bg_overlay_opacity',+this.value);document.getElementById('vk-overlay-n').textContent=this.value">
                </div>
                <button class="btn btn-sm btn-danger" onclick="vkRemoveBgImage()" style="margin-top:.4rem;width:100%;justify-content:center">✕ Quitar imagen</button>
              </div>
            </div>
          </details>

          <!-- Marco -->
          <details class="ctrl-sect">
            <summary>🔲 Marco decorativo</summary>
            <div class="ctrl-body">
              <div class="ctrl-field">
                <label><input type="checkbox" id="vk-border_enabled" onchange="vkCfgSet('border_enabled',this.checked)" checked> Activar marco</label>
              </div>
              <div class="ctrl-field">
                <label>Color del marco</label>
                <div class="color-pair">
                  <input type="color" id="vk-border_color" value="#6f102a" oninput="vkCfgSet('border_color',this.value);vkSyncHex('vk-border_color','vk-border_color_hex')">
                  <input type="text"  id="vk-border_color_hex" value="#6f102a" maxlength="7" oninput="vkCfgSet('border_color',this.value);vkSyncColor('vk-border_color_hex','vk-border_color')">
                </div>
              </div>
              <div class="ctrl-field">
                <label>Grosor — <span id="vk-border_width_n">18</span>px</label>
                <input type="range" id="vk-border_width" min="2" max="60" value="18" style="width:100%" oninput="vkCfgSet('border_width',+this.value);document.getElementById('vk-border_width_n').textContent=this.value">
              </div>
            </div>
          </details>

          <!-- Título del diploma -->
          <details class="ctrl-sect" open>
            <summary>🏆 Título principal (DIPLOMA/CERTIFICADO)</summary>
            <div class="ctrl-body">
              <div class="ctrl-field">
                <label>Texto del título</label>
                <input type="text" id="vk-header_text" value="DIPLOMA DE FINALIZACIÓN" oninput="vkCfgSet('header_text',this.value)">
              </div>
              <div class="ctrl-field">
                <label>Color</label>
                <div class="color-pair">
                  <input type="color" id="vk-header_color" value="#6f102a" oninput="vkCfgSet('header_color',this.value);vkSyncHex('vk-header_color','vk-header_color_hex')">
                  <input type="text"  id="vk-header_color_hex" value="#6f102a" maxlength="7" oninput="vkCfgSet('header_color',this.value);vkSyncColor('vk-header_color_hex','vk-header_color')">
                </div>
              </div>
              <div class="ctrl-row">
                <div class="ctrl-field"><label>Tamaño (px)</label><input type="number" id="vk-header_font_size" value="38" min="8" max="120" oninput="vkCfgSet('header_font_size',+this.value)" style="width:70px"></div>
                <div class="ctrl-field"><label>Posición Y</label><input type="number" id="vk-header_y" value="110" min="10" max="780" oninput="vkCfgSet('header_y',+this.value)" style="width:70px"></div>
                <div class="ctrl-field" style="align-self:flex-end"><label><input type="checkbox" id="vk-header_line" onchange="vkCfgSet('header_line',this.checked)" checked> Línea deco.</label></div>
              </div>
            </div>
          </details>

          <!-- Subtítulo -->
          <details class="ctrl-sect">
            <summary>📜 Subtítulo (texto bajo el título)</summary>
            <div class="ctrl-body">
              <div class="ctrl-field"><label>Texto</label><input type="text" id="vk-subheader_text" value="SE OTORGA A" oninput="vkCfgSet('subheader_text',this.value)"></div>
              <div class="ctrl-field">
                <label>Color</label>
                <div class="color-pair">
                  <input type="color" id="vk-subheader_color" value="#1a2e5a" oninput="vkCfgSet('subheader_color',this.value);vkSyncHex('vk-subheader_color','vk-subheader_color_hex')">
                  <input type="text"  id="vk-subheader_color_hex" value="#1a2e5a" maxlength="7" oninput="vkCfgSet('subheader_color',this.value);vkSyncColor('vk-subheader_color_hex','vk-subheader_color')">
                </div>
              </div>
              <div class="ctrl-row">
                <div class="ctrl-field"><label>Tamaño</label><input type="number" id="vk-subheader_font_size" value="13" min="6" max="60" oninput="vkCfgSet('subheader_font_size',+this.value)" style="width:70px"></div>
                <div class="ctrl-field"><label>Pos Y</label><input type="number" id="vk-subheader_y" value="158" min="10" max="780" oninput="vkCfgSet('subheader_y',+this.value)" style="width:70px"></div>
              </div>
            </div>
          </details>

          <!-- Nombre del estudiante -->
          <details class="ctrl-sect" open>
            <summary>👤 Nombre del estudiante</summary>
            <div class="ctrl-body">
              <div class="ctrl-field">
                <label>Color</label>
                <div class="color-pair">
                  <input type="color" id="vk-name_color" value="#6f102a" oninput="vkCfgSet('name_color',this.value);vkSyncHex('vk-name_color','vk-name_color_hex')">
                  <input type="text"  id="vk-name_color_hex" value="#6f102a" maxlength="7" oninput="vkCfgSet('name_color',this.value);vkSyncColor('vk-name_color_hex','vk-name_color')">
                </div>
              </div>
              <div class="ctrl-row">
                <div class="ctrl-field"><label>Tamaño</label><input type="number" id="vk-name_font_size" value="46" min="10" max="120" oninput="vkCfgSet('name_font_size',+this.value)" style="width:70px"></div>
                <div class="ctrl-field"><label>Pos Y</label><input type="number" id="vk-name_y" value="340" min="10" max="780" oninput="vkCfgSet('name_y',+this.value)" style="width:70px"></div>
              </div>
              <div class="ctrl-field">
                <label>Alineación</label>
                <select id="vk-name_align" onchange="vkCfgSet('name_align',this.value)">
                  <option value="center">Centrado</option>
                  <option value="left">Izquierda</option>
                </select>
              </div>
              <div class="chk-row">
                <label><input type="checkbox" id="vk-name_italic"    onchange="vkCfgSet('name_italic',this.checked)"    checked> Cursiva</label>
                <label><input type="checkbox" id="vk-name_underline" onchange="vkCfgSet('name_underline',this.checked)" checked> Subrayado</label>
              </div>
            </div>
          </details>

          <!-- Texto de completado -->
          <details class="ctrl-sect">
            <summary>✅ Texto de completado</summary>
            <div class="ctrl-body">
              <div class="ctrl-field"><label><input type="checkbox" id="vk-has_completed_text" onchange="vkCfgSet('has_completed_text',this.checked)" checked> Mostrar texto</label></div>
              <div class="ctrl-field"><label>Texto</label><input type="text" id="vk-completed_text" value="Ha completado satisfactoriamente el curso:" oninput="vkCfgSet('completed_text',this.value)"></div>
              <div class="ctrl-field">
                <label>Color</label>
                <div class="color-pair">
                  <input type="color" id="vk-completed_color" value="#444444" oninput="vkCfgSet('completed_color',this.value);vkSyncHex('vk-completed_color','vk-completed_color_hex')">
                  <input type="text"  id="vk-completed_color_hex" value="#444444" maxlength="7" oninput="vkCfgSet('completed_color',this.value);vkSyncColor('vk-completed_color_hex','vk-completed_color')">
                </div>
              </div>
              <div class="ctrl-row">
                <div class="ctrl-field"><label>Tamaño</label><input type="number" id="vk-completed_font_size" value="13" min="6" max="60" oninput="vkCfgSet('completed_font_size',+this.value)" style="width:70px"></div>
                <div class="ctrl-field"><label>Pos Y</label><input type="number" id="vk-completed_y" value="415" min="10" max="780" oninput="vkCfgSet('completed_y',+this.value)" style="width:70px"></div>
              </div>
            </div>
          </details>

          <!-- Nombre del curso -->
          <details class="ctrl-sect" open>
            <summary>📚 Nombre del curso</summary>
            <div class="ctrl-body">
              <div class="ctrl-field">
                <label>Color</label>
                <div class="color-pair">
                  <input type="color" id="vk-title_color" value="#1a2e5a" oninput="vkCfgSet('title_color',this.value);vkSyncHex('vk-title_color','vk-title_color_hex')">
                  <input type="text"  id="vk-title_color_hex" value="#1a2e5a" maxlength="7" oninput="vkCfgSet('title_color',this.value);vkSyncColor('vk-title_color_hex','vk-title_color')">
                </div>
              </div>
              <div class="ctrl-row">
                <div class="ctrl-field"><label>Tamaño</label><input type="number" id="vk-title_font_size" value="22" min="6" max="80" oninput="vkCfgSet('title_font_size',+this.value)" style="width:70px"></div>
                <div class="ctrl-field"><label>Pos Y</label><input type="number" id="vk-title_y" value="460" min="10" max="780" oninput="vkCfgSet('title_y',+this.value)" style="width:70px"></div>
              </div>
              <div class="ctrl-field">
                <label>Alineación</label>
                <select id="vk-title_align" onchange="vkCfgSet('title_align',this.value)">
                  <option value="center">Centrado</option>
                  <option value="left">Izquierda</option>
                </select>
              </div>
            </div>
          </details>

          <!-- Fecha e ID -->
          <details class="ctrl-sect">
            <summary>📅 Fecha e ID del certificado</summary>
            <div class="ctrl-body">
              <div class="ctrl-field"><label>Etiqueta de fecha</label><input type="text" id="vk-date_label" value="Fecha de finalización:" oninput="vkCfgSet('date_label',this.value)"></div>
              <div class="ctrl-field">
                <label>Color fecha</label>
                <div class="color-pair">
                  <input type="color" id="vk-date_color" value="#555555" oninput="vkCfgSet('date_color',this.value);vkSyncHex('vk-date_color','vk-date_color_hex')">
                  <input type="text"  id="vk-date_color_hex" value="#555555" maxlength="7" oninput="vkCfgSet('date_color',this.value);vkSyncColor('vk-date_color_hex','vk-date_color')">
                </div>
              </div>
              <div class="ctrl-row">
                <div class="ctrl-field"><label>Tamaño</label><input type="number" id="vk-date_font_size" value="12" min="6" max="40" oninput="vkCfgSet('date_font_size',+this.value)" style="width:62px"></div>
                <div class="ctrl-field"><label>X</label><input type="number" id="vk-date_x" value="80" min="0" max="1100" oninput="vkCfgSet('date_x',+this.value)" style="width:62px"></div>
                <div class="ctrl-field"><label>Y</label><input type="number" id="vk-date_y" value="560" min="10" max="780" oninput="vkCfgSet('date_y',+this.value)" style="width:62px"></div>
              </div>
              <div class="ctrl-field">
                <label>Color ID cert.</label>
                <div class="color-pair">
                  <input type="color" id="vk-cert_id_color" value="#888888" oninput="vkCfgSet('cert_id_color',this.value);vkSyncHex('vk-cert_id_color','vk-cert_id_color_hex')">
                  <input type="text"  id="vk-cert_id_color_hex" value="#888888" maxlength="7" oninput="vkCfgSet('cert_id_color',this.value);vkSyncColor('vk-cert_id_color_hex','vk-cert_id_color')">
                </div>
              </div>
              <div class="ctrl-row">
                <div class="ctrl-field"><label>Tamaño ID</label><input type="number" id="vk-cert_id_font_size" value="9" min="4" max="20" oninput="vkCfgSet('cert_id_font_size',+this.value)" style="width:62px"></div>
                <div class="ctrl-field"><label>X</label><input type="number" id="vk-cert_id_x" value="80" min="0" max="1100" oninput="vkCfgSet('cert_id_x',+this.value)" style="width:62px"></div>
                <div class="ctrl-field"><label>Y</label><input type="number" id="vk-cert_id_y" value="578" min="10" max="780" oninput="vkCfgSet('cert_id_y',+this.value)" style="width:62px"></div>
              </div>
            </div>
          </details>

          <!-- Firma -->
          <details class="ctrl-sect">
            <summary>✍️ Firma del instructor</summary>
            <div class="ctrl-body">
              <div class="ctrl-field"><label>Nombre del firmante</label><input type="text" id="vk-signature_label" placeholder="Roberto Carlos Hidalgo" oninput="vkCfgSet('signature_label',this.value)"></div>
              <div class="ctrl-field"><label>Cargo / Rol</label><input type="text" id="vk-signature_role" placeholder="Instructor · VidaKushala" oninput="vkCfgSet('signature_role',this.value)"></div>
              <div class="ctrl-row">
                <div class="ctrl-field"><label>X</label><input type="number" id="vk-signature_x" value="760" min="0" max="1100" oninput="vkCfgSet('signature_x',+this.value)" style="width:68px"></div>
                <div class="ctrl-field"><label>Y</label><input type="number" id="vk-signature_y" value="640" min="10" max="780" oninput="vkCfgSet('signature_y',+this.value)" style="width:68px"></div>
                <div class="ctrl-field"><label>Largo línea</label><input type="number" id="vk-signature_line_w" value="200" min="50" max="400" oninput="vkCfgSet('signature_line_w',+this.value)" style="width:68px"></div>
              </div>
            </div>
          </details>

          <!-- QR -->
          <details class="ctrl-sect">
            <summary>⬛ Código QR de verificación</summary>
            <div class="ctrl-body">
              <div class="ctrl-field"><label><input type="checkbox" id="vk-qr_enabled" onchange="vkCfgSet('qr_enabled',this.checked)" checked> Mostrar código QR</label></div>
              <div class="ctrl-row">
                <div class="ctrl-field"><label>Dist. desde der.</label><input type="number" id="vk-qr_x_from_right" value="50" min="0" max="500" oninput="vkCfgSet('qr_x_from_right',+this.value)" style="width:68px"></div>
                <div class="ctrl-field"><label>Dist. desde abajo</label><input type="number" id="vk-qr_y_from_bottom" value="85" min="0" max="400" oninput="vkCfgSet('qr_y_from_bottom',+this.value)" style="width:68px"></div>
                <div class="ctrl-field"><label>Tamaño</label><input type="number" id="vk-qr_size" value="78" min="40" max="150" oninput="vkCfgSet('qr_size',+this.value)" style="width:68px"></div>
              </div>
            </div>
          </details>

        </div><!-- /ctrl-panel -->

        <!-- Canvas preview (derecha) -->
        <div class="preview-panel">
          <div class="canvas-wrap">
            <canvas id="vk-cert-canvas" width="1122" height="794"></canvas>
          </div>
          <div class="preview-data">
            <div class="preview-data-title">📝 Datos de muestra <span style="font-weight:400;opacity:.7">(solo para previsualizar el diseño)</span></div>
            <div class="preview-data-grid">
              <div class="preview-data-field"><label>Nombre:</label><input type="text" id="vk-prev-name"   value="María González López"              oninput="vkCertPreviewRedraw()"></div>
              <div class="preview-data-field"><label>Curso:</label> <input type="text" id="vk-prev-course" value="Método VidaKushala — Bioenergética" oninput="vkCertPreviewRedraw()"></div>
              <div class="preview-data-field"><label>Fecha:</label> <input type="text" id="vk-prev-date"   value="10 de junio de 2026"               oninput="vkCertPreviewRedraw()"></div>
              <div class="preview-data-field"><label>ID cert.:</label><input type="text" id="vk-prev-id"   value="CERT-A7F3B2D1"                     oninput="vkCertPreviewRedraw()"></div>
            </div>
          </div>
        </div>

      </div><!-- /ed-layout -->
    </div><!-- /vk-tc-design -->

    <!-- ── TAB: PLANTILLAS BASE ─────────────────────────────── -->
    <div class="ed-tab-content" id="vk-tc-templates">
      <div class="card">
        <div class="card-header">
          <span class="card-title">🎨 Plantillas prediseñadas</span>
          <span class="card-hint">Clic para aplicar, luego personaliza en la pestaña Diseño</span>
        </div>
        <div id="vk-tmpl-gallery" class="tmpl-gallery-full"></div>

        <div class="card-header" style="margin-top:1rem">
          <span class="card-title">📁 Imágenes disponibles como fondo</span>
          <span class="card-hint">Clic para usar como imagen de fondo del certificado</span>
        </div>
        <div id="vk-file-gallery" class="file-gallery">
          <div style="color:var(--tu);font-size:.85rem;padding:.5rem">Cargando imágenes del servidor...</div>
        </div>
      </div>
    </div>

    <!-- ── TAB: TIPOGRAFÍA ──────────────────────────────────── -->
    <div class="ed-tab-content" id="vk-tc-fonts">
      <div class="card">
        <div class="font-section">
          <h3>🔤 Tipografía del Título (DIPLOMA / CERTIFICADO)</h3>
          <div class="font-grid">
            <div class="font-card active" data-font="Georgia"         onclick="vkSetFont('title','Georgia')">
              <span style="font-family:'Georgia',serif;font-size:1.4rem">DIPLOMA</span>
              <span class="font-card-name">Georgia (clásica)</span>
            </div>
            <div class="font-card" data-font="Times New Roman"        onclick="vkSetFont('title','Times New Roman')">
              <span style="font-family:'Times New Roman',serif;font-size:1.4rem">DIPLOMA</span>
              <span class="font-card-name">Times New Roman</span>
            </div>
            <div class="font-card" data-font="Palatino Linotype"      onclick="vkSetFont('title','Palatino Linotype')">
              <span style="font-family:'Palatino Linotype',serif;font-size:1.4rem">DIPLOMA</span>
              <span class="font-card-name">Palatino</span>
            </div>
            <div class="font-card" data-font="Arial"                  onclick="vkSetFont('title','Arial')">
              <span style="font-family:'Arial',sans-serif;font-size:1.4rem">DIPLOMA</span>
              <span class="font-card-name">Arial (moderno)</span>
            </div>
            <div class="font-card" data-font="Trebuchet MS"           onclick="vkSetFont('title','Trebuchet MS')">
              <span style="font-family:'Trebuchet MS',sans-serif;font-size:1.4rem">DIPLOMA</span>
              <span class="font-card-name">Trebuchet MS</span>
            </div>
          </div>
        </div>
        <div class="font-section">
          <h3>🔡 Tipografía del cuerpo (textos, fechas, ID)</h3>
          <div class="font-grid">
            <div class="font-card active" data-font-body="Arial"      onclick="vkSetFont('body','Arial')">
              <span style="font-family:'Arial',sans-serif">Ha completado el curso de VidaKushala</span>
              <span class="font-card-name">Arial</span>
            </div>
            <div class="font-card" data-font-body="Verdana"           onclick="vkSetFont('body','Verdana')">
              <span style="font-family:'Verdana',sans-serif">Ha completado el curso de VidaKushala</span>
              <span class="font-card-name">Verdana</span>
            </div>
            <div class="font-card" data-font-body="Tahoma"            onclick="vkSetFont('body','Tahoma')">
              <span style="font-family:'Tahoma',sans-serif">Ha completado el curso de VidaKushala</span>
              <span class="font-card-name">Tahoma</span>
            </div>
            <div class="font-card" data-font-body="Georgia"           onclick="vkSetFont('body','Georgia')">
              <span style="font-family:'Georgia',serif">Ha completado el curso de VidaKushala</span>
              <span class="font-card-name">Georgia</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── TAB: IMÁGENES ────────────────────────────────────── -->
    <div class="ed-tab-content" id="vk-tc-assets">
      <div class="assets-grid">
        <div class="asset-card">
          <h3>🖼 Imagen de fondo personalizada</h3>
          <p>Sube una imagen JPG/PNG de alta resolución (1122×794px recomendado). Se guardará como base64 en la configuración.</p>
          <div class="upload-zone" onclick="document.getElementById('vk-bg-upload-assets').click()">
            <div id="vk-bg-zone-assets-inner">📁 Clic para subir imagen de fondo</div>
          </div>
          <input type="file" id="vk-bg-upload-assets" accept="image/*" style="display:none" onchange="vkUploadBgImageAssets(this)">
          <div id="vk-bg-img-thumb-assets" style="margin-top:.5rem"></div>
          <button class="btn btn-sm btn-danger" onclick="vkRemoveBgImage()" style="margin-top:.6rem;width:100%;justify-content:center">✕ Quitar imagen de fondo</button>
        </div>
        <div class="asset-card">
          <h3>📤 Subir nueva imagen de plantilla al servidor</h3>
          <p>Sube tu propio diseño de certificado para guardarlo en la carpeta <code>cert-templates/</code> del servidor.</p>
          <div class="upload-zone" onclick="document.getElementById('vk-tmpl-upload').click()">
            <div id="vk-tmpl-zone-inner">📤 Clic para subir imagen</div>
          </div>
          <input type="file" id="vk-tmpl-upload" accept="image/*" style="display:none" onchange="vkUploadTemplate(this)">
          <div id="vk-tmpl-upload-preview" style="margin-top:.5rem"></div>
        </div>
      </div>
      <div class="card" style="margin-top:1rem">
        <div class="card-header">
          <span class="card-title">📁 Imágenes en cert-templates/</span>
          <button class="btn btn-sm btn-secondary" onclick="vkLoadFileGallery(true)">🔄 Recargar</button>
        </div>
        <div id="vk-file-gallery-assets" class="file-gallery">
          <div style="color:var(--tu);font-size:.85rem;padding:.5rem">Cargando...</div>
        </div>
      </div>
    </div>

  </div><!-- /panel-design -->


  <!-- ══ PANEL: BIBLIOTECA DE PLANTILLAS ════════════════════════════ -->
  <div class="panel" id="panel-templates">
    <div class="card">
      <div class="card-header">
        <span class="card-title">📁 Plantillas de certificados guardadas</span>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          <button class="btn btn-primary btn-sm" onclick="openCreateModal()">➕ Nueva plantilla</button>
          <button class="btn btn-secondary btn-sm" onclick="cleanDuplicates()">🧹 Limpiar duplicados</button>
          <button class="btn btn-secondary btn-sm" onclick="loadTemplates()">🔄 Actualizar</button>
        </div>
      </div>
      <p style="font-size:.85rem;color:var(--ts);margin-bottom:.9rem">
        Las plantillas te permiten tener diseños distintos para cada curso. Haz clic en <strong>✏️ Editar</strong> para abrir una plantilla en el editor y personalizarla.
      </p>
      <div id="tpl-msg" style="margin-bottom:.65rem"></div>
      <div id="tpl-grid" class="tpl-grid">
        <div class="empty-state" style="grid-column:1/-1"><div class="empty-state-icon">⏳</div><p>Cargando plantillas...</p></div>
      </div>
    </div>
  </div>

  <!-- ══ PANEL: ASIGNACIÓN A CURSOS ══════════════════════════════════ -->
  <div class="panel" id="panel-assign">
    <div class="card">
      <div class="card-header">
        <span class="card-title">📋 Asignación de plantilla por curso</span>
        <button class="btn btn-secondary btn-sm" onclick="loadCourses()">🔄 Actualizar</button>
      </div>
      <p style="font-size:.85rem;color:var(--ts);margin-bottom:1rem">
        Selecciona qué plantilla usará cada curso al generar el certificado del estudiante.
        Si un curso no tiene plantilla asignada, usará el <strong>diseño global</strong>.
      </p>
      <div id="courses-msg" style="margin-bottom:.65rem"></div>
      <div id="courses-body">
        <div class="empty-state"><div class="empty-state-icon">⏳</div><p>Cargando cursos...</p></div>
      </div>
    </div>
  </div>

</div><!-- /container -->

<!-- ══ MODAL: crear / renombrar / duplicar plantilla ═════════════════ -->
<div class="modal-bg" id="tpl-modal" style="display:none">
  <div class="modal">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <h3 id="modal-title">Nueva plantilla</h3>
    <div class="field">
      <label>Nombre de la plantilla</label>
      <input type="text" id="modal-name" placeholder="Ej: Diplomado Premium, Curso Básico...">
    </div>
    <div class="field" id="modal-src-wrap">
      <label>Copiar diseño desde</label>
      <select id="modal-src">
        <option value="">⬜ Diseño en blanco</option>
        <option value="__global__">🌐 Diseño global actual</option>
      </select>
    </div>
    <div style="display:flex;gap:.65rem;margin-top:.75rem">
      <button class="btn btn-primary" onclick="confirmModal()">✅ Confirmar</button>
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
    </div>
    <p id="modal-msg" style="font-size:.82rem;margin-top:.6rem;min-height:1.2em"></p>
  </div>
</div>

<?php
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https' : 'http';
$host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir   = str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if (strpos($dir,'/wp-content/plugins/vk-cors') !== false)
    $vk_plugin_url = $proto.'://'.$host.substr($dir,0,strpos($dir,'/wp-content/plugins/vk-cors')+27).'/';
else
    $vk_plugin_url = $proto.'://'.$host.rtrim($dir,'/').'/wp-content/plugins/vk-cors/';
?>
<script>
var VK_PLUGIN_URL = '<?php echo $vk_plugin_url; ?>';
var API = (window.location.hostname==='localhost'||window.location.hostname==='127.0.0.1')
        ? 'http://localhost:8080/wp-json/vk/v1'
        : 'https://vidakushala.com/wp-json/vk/v1';
var TOK = '';

/* ── AUTH ───────────────────────────────────────────────────── */
function switchLoginTab(t) {
  var isPass = (t === 'pass');
  document.getElementById('lpanel-pass').style.display = isPass ? 'block' : 'none';
  document.getElementById('lpanel-tok').style.display  = isPass ? 'none'  : 'block';
  document.getElementById('ltab-pass').style.cssText   = 'flex:1;padding:.55rem;border:none;cursor:pointer;font-family:inherit;font-size:.83rem;font-weight:700;background:'+(isPass?'var(--grad-accent)':'white')+';color:'+(isPass?'white':'var(--ts)');
  document.getElementById('ltab-tok').style.cssText    = 'flex:1;padding:.55rem;border:none;cursor:pointer;font-family:inherit;font-size:.83rem;font-weight:600;background:'+(!isPass?'var(--grad-accent)':'white')+';color:'+(!isPass?'white':'var(--ts)');
}
async function doLogin() {
  var user = document.getElementById('login-user').value.trim();
  var pass = document.getElementById('login-pass').value;
  var err  = document.getElementById('login-err');
  if (!user || !pass) { err.textContent = 'Ingresa usuario y contraseña'; return; }
  err.textContent = '⏳ Verificando...';
  try {
    var r = await fetch(API+'/login', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:user,password:pass})});
    var d = await r.json();
    if (r.ok && d.token) { TOK = d.token; await verifyAdmin(err); }
    else err.textContent = '✗ '+(d.message||'Credenciales incorrectas');
  } catch(e) { err.textContent = '✗ Error: '+e.message; }
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
    var r = await fetch(API+'/check-admin?vk_token='+encodeURIComponent(TOK));
    var d = await r.json();
    if (r.ok && d.is_admin) {
      try { localStorage.setItem('vk_tok_cert', TOK); } catch(e) {}
      document.getElementById('admin-name').textContent = d.display_name || 'Admin';
      document.getElementById('login-screen').style.display = 'none';
      onLogin();
    } else {
      if (errEl) errEl.textContent = '✗ '+(d.message||'Solo administradores');
      TOK = '';
    }
  } catch(e) { if (errEl) errEl.textContent = '✗ Error: '+e.message; TOK = ''; }
}
function doLogout() { TOK = ''; try { localStorage.removeItem('vk_tok_cert'); } catch(e) {} location.reload(); }
window.addEventListener('load', function() {
  var raw = localStorage.getItem('vk_tok_cert');
  try { var p = JSON.parse(raw); if (typeof p === 'string') raw = p; } catch(e) {}
  if (raw && raw.length > 10) {
    document.getElementById('login-tok').value = raw;
    TOK = raw; verifyAdmin(document.getElementById('login-err'));
  }
});

/* ── MAIN TABS ──────────────────────────────────────────────── */
function showMainTab(name) {
  document.querySelectorAll('.main-tab').forEach(function(t) {
    t.classList.toggle('on', t.dataset.tab === name);
  });
  document.querySelectorAll('.panel').forEach(function(p) { p.classList.remove('on'); });
  var panel = document.getElementById('panel-'+name);
  if (panel) panel.classList.add('on');
  if (name === 'design')    { /* editor already inited */ }
  if (name === 'templates') loadTemplates();
  if (name === 'assign')    loadCourses();
}

/* ── ON LOGIN ───────────────────────────────────────────────── */
function onLogin() {
  // Init editor (only once — guard is in vk-cert-editor.js)
  if (typeof vkCertEditorInit === 'function') vkCertEditorInit();
  // Preload templates
  loadTemplates();
}

/* ── EDITING BANNER ─────────────────────────────────────────── */
function startEditingTpl(key, name) {
  var tpl = TPL_ALL.find(function(t) { return t.key === key; });
  if (!tpl) { showMsg('tpl-msg', '✗ Plantilla no encontrada', 'err'); return; }
  if (typeof VK_CERT === 'undefined') { showMsg('tpl-msg', '✗ El editor aún no cargó. Ve a la pestaña Diseñar primero.', 'err'); return; }
  VK_CERT.cfg = Object.assign(typeof vkCertDefaults==='function' ? vkCertDefaults() : {}, tpl.config || {});
  VK_CERT._editing_tpl_key  = key;
  VK_CERT._editing_tpl_name = name;
  VK_CERT.bgImg = null;
  // Pre-load bg image before redraw to avoid blank-then-image flash
  if (VK_CERT.cfg.bg_type === 'image') {
    var _bgSrc = VK_CERT.cfg.bg_image_data || VK_CERT.cfg.bg_image_url || '';
    if (_bgSrc) {
      var _bgImg = new Image();
      _bgImg.onload  = function() { VK_CERT.bgImg = _bgImg; if (typeof vkCertPreviewRedraw==='function') vkCertPreviewRedraw(); };
      _bgImg.onerror = function() { if (typeof vkCertPreviewRedraw==='function') vkCertPreviewRedraw(); };
      _bgImg.src = _bgSrc;
    }
  }
  if (typeof vkCertFormSync === 'function') vkCertFormSync();
  if (typeof vkCertPreviewRedraw === 'function') vkCertPreviewRedraw();
  // Update save button
  var btn = document.getElementById('vk-save-cert-btn');
  if (btn) { btn.innerHTML = '💾 Guardar en "'+esc(name)+'"'; btn.onclick = function() { saveTplFromEditor(key, name); }; }
  // Show banner
  var banner = document.getElementById('editing-banner');
  document.getElementById('editing-tpl-name').textContent = name;
  if (banner) banner.classList.add('on');
  showMainTab('design');
}
function stopEditingTpl() {
  if (typeof VK_CERT !== 'undefined') { delete VK_CERT._editing_tpl_key; delete VK_CERT._editing_tpl_name; }
  var btn = document.getElementById('vk-save-cert-btn');
  if (btn) { btn.innerHTML = '💾 Guardar diseño'; btn.onclick = function() { if (typeof vkSaveCertConfig==='function') vkSaveCertConfig(); }; }
  var banner = document.getElementById('editing-banner');
  if (banner) banner.classList.remove('on');
  if (typeof vkLoadCertConfig === 'function') vkLoadCertConfig().then(function() {
    if (typeof vkCertFormSync === 'function') vkCertFormSync();
    if (typeof vkCertPreviewRedraw === 'function') vkCertPreviewRedraw();
  });
}
async function saveTplFromEditor(key, name) {
  if (typeof VK_CERT === 'undefined') return;
  var btn = document.getElementById('vk-save-cert-btn');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spin"></span>Guardando...'; }
  try {
    var list = await tplReadAll();
    var idx  = list.findIndex(function(t) { return t.key === key; });
    var now  = new Date().toISOString();
    var cfg  = Object.assign({}, VK_CERT.cfg);
    // Thumb: prefer server URL; skip base64 to keep the template list payload small
    var thumb = cfg.bg_image_url || '';
    if (idx >= 0) { list[idx].config = cfg; list[idx].name = name; list[idx].updated_at = now; if (thumb) list[idx].thumb = thumb; }
    else { list.push({key:key, name:name, config:cfg, thumb:thumb, created_at:now, updated_at:now}); }
    await tplWriteAll(list);
    TPL_ALL = list;
    // Clear cached certs so students regenerate with the updated template design
    var cleared = 0;
    try {
      var cc = await (await fetch(apiUrl('/cert-clear-cache'), {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({vk_token:TOK})})).json();
      cleared = cc.deleted || 0;
    } catch(e3) {}
    var msgEl = document.getElementById('vk-cert-msg');
    var msg = cleared > 0 ? '✅ Guardado en "'+name+'" · '+cleared+' certs regenerados' : '✅ Guardado en "'+name+'"';
    if (msgEl) { msgEl.textContent = msg; msgEl.className = 'ed-msg ok'; setTimeout(function(){msgEl.textContent='';msgEl.className='ed-msg';},4000); }
  } catch(e) {
    var msgEl2 = document.getElementById('vk-cert-msg');
    if (msgEl2) { msgEl2.textContent = '✗ '+e.message; msgEl2.className = 'ed-msg err'; }
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '💾 Guardar en "'+esc(name)+'"'; }
  }
}

/* ── API HELPERS ────────────────────────────────────────────── */
function apiUrl(path) {
  return API+path+(path.indexOf('?')>=0?'&':'?')+'vk_token='+encodeURIComponent(TOK)+'&_t='+Date.now();
}
async function tplReadFull() {
  var r = await fetch(apiUrl('/cert-tpl-read'), {cache:'no-store'});
  if (!r.ok) { var t=''; try{t=await r.text();}catch(e){} throw new Error('HTTP '+r.status+': '+t.substring(0,100)); }
  return r.json();
}
async function tplReadAll()  { var d = await tplReadFull(); return d.templates || []; }
async function tplWriteAll(list) {
  var r = await fetch(apiUrl('/cert-tpl-write'), {method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',body:JSON.stringify({key:'_cert_tpl',data:list})});
  if (!r.ok) { var t=''; try{t=await r.text();}catch(e){} throw new Error('HTTP '+r.status+': '+t.substring(0,120)); }
  return r.json();
}
async function tplReadAssign()    { var d = await tplReadFull(); return d.assignments || {}; }
async function tplWriteAssign(obj) {
  var r = await fetch(apiUrl('/cert-tpl-write'), {method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',body:JSON.stringify({key:'_cert_assign',data:obj})});
  if (!r.ok) { var t=''; try{t=await r.text();}catch(e){} throw new Error('HTTP '+r.status+': '+t.substring(0,120)); }
  return r.json();
}
function tplSlug(name) {
  var map={'á':'a','é':'e','í':'i','ó':'o','ú':'u','ü':'u','ñ':'n','Á':'a','É':'e','Í':'i','Ó':'o','Ú':'u','Ü':'u','Ñ':'n',' ':'_'};
  var s = name.toLowerCase().replace(/[áéíóúüñÁÉÍÓÚÜÑ ]/g, function(c){return map[c]||c;});
  return s.replace(/[^a-z0-9_]+/g,'_').replace(/^_+|_+$/g,'') || 'plantilla';
}
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function showMsg(id, msg, type) {
  var el = document.getElementById(id); if (!el) return;
  el.className = 'msg msg-'+type; el.innerHTML = msg;
  if (type !== 'err') setTimeout(function(){el.textContent='';el.className='';}, 6000);
}

/* ── TEMPLATE LIBRARY ───────────────────────────────────────── */
var TPL_ALL = [];
async function loadTemplates() {
  document.getElementById('tpl-grid').innerHTML = '<div class="empty-state" style="grid-column:1/-1"><div class="empty-state-icon">⏳</div><p>Cargando...</p></div>';
  try {
    var d = await tplReadFull();
    TPL_ALL = d.templates || [];
    renderTemplateGrid();
  } catch(e) {
    document.getElementById('tpl-grid').innerHTML = '<div class="msg msg-err" style="grid-column:1/-1">✗ '+esc(e.message)+'</div>';
  }
}
function renderTemplateGrid() {
  var grid = document.getElementById('tpl-grid');
  grid.innerHTML = '';
  if (!TPL_ALL.length) {
    grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><div class="empty-state-icon">📭</div><p>No hay plantillas guardadas aún.<br>Crea una para empezar.</p></div>';
  }
  TPL_ALL.forEach(function(t) {
    var card = document.createElement('div'); card.className = 'tpl-card';
    // Thumbnail
    if (t.thumb) {
      var img = document.createElement('img'); img.className = 'tpl-thumb'; img.src = t.thumb;
      img.onerror = function(){ this.style.display='none'; var ph=document.createElement('div'); ph.className='tpl-thumb-ph'; ph.textContent='🏆'; card.insertBefore(ph,card.firstChild); };
      card.appendChild(img);
    } else {
      var ph = document.createElement('div'); ph.className = 'tpl-thumb-ph'; ph.textContent = '🏆';
      card.appendChild(ph);
    }
    var body = document.createElement('div'); body.className = 'tpl-body';
    var nm = document.createElement('div'); nm.className = 'tpl-name'; nm.title = t.name; nm.textContent = t.name;
    var meta = document.createElement('div'); meta.className = 'tpl-meta';
    var badge = document.createElement('span');
    badge.className = (t.courses_count > 0) ? 'tpl-badge tpl-badge-used' : 'tpl-badge';
    badge.textContent = (t.courses_count > 0) ? '✅ '+t.courses_count+' curso'+(t.courses_count>1?'s':'') : 'Sin asignar';
    meta.appendChild(badge); body.appendChild(nm); body.appendChild(meta);
    var actions = document.createElement('div'); actions.className = 'tpl-actions';

    var btnEdit = document.createElement('button'); btnEdit.className = 'btn btn-primary btn-sm'; btnEdit.textContent = '✏️ Editar';
    btnEdit.addEventListener('click', (function(k,n){return function(){startEditingTpl(k,n);};})(t.key,t.name));
    actions.appendChild(btnEdit);

    var btnRen = document.createElement('button'); btnRen.className = 'btn btn-secondary btn-sm'; btnRen.title = 'Renombrar'; btnRen.textContent = '📝';
    btnRen.addEventListener('click', (function(k,n){return function(){openRenameModal(k,n);};})(t.key,t.name));
    actions.appendChild(btnRen);

    var btnDup = document.createElement('button'); btnDup.className = 'btn btn-secondary btn-sm'; btnDup.title = 'Duplicar'; btnDup.textContent = '📋';
    btnDup.addEventListener('click', (function(k){return function(){openDuplicateModal(k);};})(t.key));
    actions.appendChild(btnDup);

    var btnDel = document.createElement('button'); btnDel.className = 'btn btn-danger btn-sm'; btnDel.title = 'Eliminar'; btnDel.textContent = '🗑';
    btnDel.addEventListener('click', (function(k,n){return function(){deleteTemplate(k,n);};})(t.key,t.name));
    actions.appendChild(btnDel);

    body.appendChild(actions); card.appendChild(body); grid.appendChild(card);
  });
  // Add card
  var add = document.createElement('div'); add.className = 'tpl-add';
  add.innerHTML = '<div style="font-size:2rem">➕</div><div style="font-weight:700;font-size:.88rem">Nueva plantilla</div><div style="font-size:.78rem;margin-top:.2rem;text-align:center;padding:0 .5rem;opacity:.7">Crea un diseño con nombre personalizado</div>';
  add.addEventListener('click', openCreateModal);
  grid.appendChild(add);
}
async function deleteTemplate(key, name) {
  if (!confirm('¿Eliminar la plantilla "'+name+'"? Esta acción no se puede deshacer.')) return;
  try {
    var list   = await tplReadAll();
    var assign = await tplReadAssign();
    var inUse  = Object.values(assign).filter(function(v){return v===key;}).length;
    if (inUse > 0) { alert('Esta plantilla está asignada a '+inUse+' curso(s). Reasígnalos primero.'); return; }
    await tplWriteAll(list.filter(function(t){return t.key!==key;}));
    showMsg('tpl-msg','✅ Plantilla "'+esc(name)+'" eliminada','ok');
    loadTemplates();
  } catch(e) { showMsg('tpl-msg','✗ '+e.message,'err'); }
}
async function cleanDuplicates() {
  if (!confirm('Eliminar plantillas con nombre duplicado (conserva la más reciente). ¿Continuar?')) return;
  try {
    var list   = await tplReadAll();
    var assign = await tplReadAssign();
    var inUse  = Object.values(assign);
    var seen = {}, toKeep = [];
    list.slice().sort(function(a,b){return (b.created_at||'').localeCompare(a.created_at||'');})
      .forEach(function(t) {
        var k = t.name.trim().toLowerCase();
        if (!seen[k] || inUse.indexOf(t.key) >= 0) { seen[k] = true; toKeep.push(t); }
      });
    var deleted = list.length - toKeep.length;
    if (!deleted) { showMsg('tpl-msg','✅ No se encontraron duplicados.','ok'); return; }
    await tplWriteAll(toKeep);
    showMsg('tpl-msg','✅ '+deleted+' duplicado(s) eliminado(s).','ok');
    loadTemplates();
  } catch(e) { showMsg('tpl-msg','✗ '+e.message,'err'); }
}

/* ── MODAL ──────────────────────────────────────────────────── */
var _modalMode = '', _modalKey = '';
function openCreateModal() {
  _modalMode = 'create'; _modalKey = '';
  document.getElementById('modal-title').textContent = '➕ Nueva plantilla';
  document.getElementById('modal-name').value = '';
  document.getElementById('modal-msg').textContent = '';
  var src = document.getElementById('modal-src');
  src.innerHTML = '<option value="">⬜ Diseño en blanco</option><option value="__global__">🌐 Diseño global actual</option>';
  TPL_ALL.forEach(function(t){var o=document.createElement('option');o.value=t.key;o.textContent='📄 '+t.name;src.appendChild(o);});
  document.getElementById('modal-src-wrap').style.display = '';
  document.getElementById('tpl-modal').style.display = 'flex';
  setTimeout(function(){document.getElementById('modal-name').focus();}, 50);
}
function openRenameModal(key, name) {
  _modalMode = 'rename'; _modalKey = key;
  document.getElementById('modal-title').textContent = '📝 Renombrar plantilla';
  document.getElementById('modal-name').value = name;
  document.getElementById('modal-msg').textContent = '';
  document.getElementById('modal-src-wrap').style.display = 'none';
  document.getElementById('tpl-modal').style.display = 'flex';
  setTimeout(function(){document.getElementById('modal-name').focus();}, 50);
}
function openDuplicateModal(key) {
  _modalMode = 'duplicate'; _modalKey = key;
  var orig = (TPL_ALL.find(function(t){return t.key===key;})||{}).name || key;
  document.getElementById('modal-title').textContent = '📋 Duplicar plantilla';
  document.getElementById('modal-name').value = orig+' (copia)';
  document.getElementById('modal-msg').textContent = '';
  document.getElementById('modal-src-wrap').style.display = 'none';
  document.getElementById('tpl-modal').style.display = 'flex';
  setTimeout(function(){document.getElementById('modal-name').focus();}, 50);
}
function closeModal() { document.getElementById('tpl-modal').style.display = 'none'; }
document.addEventListener('DOMContentLoaded', function() {
  var bg = document.getElementById('tpl-modal');
  if (bg) bg.addEventListener('click', function(e){ if(e.target===bg) closeModal(); });
  var inp = document.getElementById('modal-name');
  if (inp) inp.addEventListener('keydown', function(e){ if(e.key==='Enter') confirmModal(); });
});
async function confirmModal() {
  var name  = document.getElementById('modal-name').value.trim();
  var msgEl = document.getElementById('modal-msg');
  if (!name) { msgEl.style.color='#c62828'; msgEl.textContent='Escribe un nombre'; return; }
  msgEl.style.color = 'var(--ts)'; msgEl.textContent = '⏳ Guardando...';
  try {
    var list = await tplReadAll();
    var now  = new Date().toISOString();
    if (_modalMode === 'create') {
      var srcKey = document.getElementById('modal-src').value;
      var srcCfg = {};
      if (srcKey === '__global__') srcCfg = (typeof VK_CERT!=='undefined') ? Object.assign({},VK_CERT.cfg) : {};
      else if (srcKey) { var found=list.find(function(t){return t.key===srcKey;}); if(found) srcCfg=Object.assign({},found.config||{}); }
      var slug=tplSlug(name), base=slug, i2=2;
      while (list.find(function(t){return t.key===slug;})) slug=base+'_'+i2++;
      list.push({key:slug, name:name, config:srcCfg, thumb:'', created_at:now, updated_at:now});
      await tplWriteAll(list); closeModal(); TPL_ALL=list; renderTemplateGrid();
      showMsg('tpl-msg','✅ Plantilla <strong>'+esc(name)+'</strong> creada. Usa ✏️ Editar para personalizarla.','ok');
    } else if (_modalMode === 'rename') {
      var t2 = list.find(function(t){return t.key===_modalKey;});
      if (t2) { t2.name=name; t2.updated_at=now; }
      await tplWriteAll(list); closeModal(); TPL_ALL=list; renderTemplateGrid();
      showMsg('tpl-msg','✅ Renombrada a <strong>'+esc(name)+'</strong>','ok');
    } else if (_modalMode === 'duplicate') {
      var src2 = list.find(function(t){return t.key===_modalKey;});
      if (!src2) throw new Error('Plantilla original no encontrada');
      var slug3=tplSlug(name), base3=slug3, i3=2;
      while (list.find(function(t){return t.key===slug3;})) slug3=base3+'_'+i3++;
      list.push({key:slug3, name:name, config:JSON.parse(JSON.stringify(src2.config||{})), thumb:src2.thumb||'', created_at:now, updated_at:now});
      await tplWriteAll(list); closeModal(); TPL_ALL=list; renderTemplateGrid();
      showMsg('tpl-msg','✅ Duplicada como <strong>'+esc(name)+'</strong>','ok');
    }
  } catch(e) { msgEl.style.color='#c62828'; msgEl.textContent='✗ '+e.message; }
}

/* ── COURSES PANEL ──────────────────────────────────────────── */
var COURSES_DATA = [], ASSIGN_DATA = {};
async function loadCourses() {
  document.getElementById('courses-body').innerHTML = '<div class="empty-state"><div class="empty-state-icon">⏳</div><p>Cargando cursos...</p></div>';
  try {
    var d = await tplReadFull();
    TPL_ALL      = d.templates   || [];
    COURSES_DATA = d.courses     || [];
    ASSIGN_DATA  = d.assignments || {};
    renderCoursesTable();
  } catch(e) {
    document.getElementById('courses-body').innerHTML = '<div class="msg msg-err">✗ '+esc(e.message)+'</div>';
  }
}
function renderCoursesTable() {
  var el = document.getElementById('courses-body');
  if (!COURSES_DATA.length) {
    el.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📭</div><p>No hay cursos publicados.</p></div>';
    return;
  }
  var tbl = document.createElement('table'); tbl.className = 'course-table';
  tbl.innerHTML = '<thead><tr><th>Curso</th><th style="min-width:140px">Plantilla asignada</th><th style="min-width:200px">Cambiar plantilla</th><th>Guardar</th></tr></thead>';
  var tbody = document.createElement('tbody');
  COURSES_DATA.forEach(function(c) {
    var opts = '<option value="default"'+(c.template_key==='default'?' selected':'')+'>⬜ Default (diseño global)</option>';
    TPL_ALL.forEach(function(t){ opts+='<option value="'+esc(t.key)+'"'+(c.template_key===t.key?' selected':'')+'>📄 '+esc(t.name)+'</option>'; });
    var pillCls = c.template_key==='default' ? 'tpl-pill tpl-pill-default' : 'tpl-pill';
    var tr = document.createElement('tr');
    tr.innerHTML = '<td style="font-weight:700">'+esc(c.title)+'</td>'
      +'<td><span class="'+pillCls+'" id="pill-'+c.id+'">'+(c.template_key==='default'?'⬜':'📄')+' '+esc(c.template_name)+'</span></td>'
      +'<td><select id="sel-'+c.id+'" style="padding:.4rem .65rem;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.83rem;width:100%">'+opts+'</select></td>'
      +'<td style="white-space:nowrap"><button class="btn btn-primary btn-sm" id="cbtn-'+c.id+'">💾 Guardar</button> <span id="cmsg-'+c.id+'" style="font-size:.78rem"></span></td>';
    tbody.appendChild(tr);
  });
  tbl.appendChild(tbody);
  el.innerHTML = '';
  el.appendChild(tbl);
  COURSES_DATA.forEach(function(c) {
    var btn = document.getElementById('cbtn-'+c.id);
    if (btn) btn.addEventListener('click', (function(id){return function(){saveCourseAssign(id);};})(c.id));
  });
}
async function saveCourseAssign(courseId) {
  var sel   = document.getElementById('sel-'+courseId);
  var msgEl = document.getElementById('cmsg-'+courseId);
  var pill  = document.getElementById('pill-'+courseId);
  if (!sel) return;
  var tkey  = sel.value;
  var tname = tkey==='default' ? 'Default (diseño global)' : ((TPL_ALL.find(function(t){return t.key===tkey;})||{}).name||tkey);
  try {
    var assign = await tplReadAssign();
    if (tkey === 'default') delete assign[courseId]; else assign[courseId] = tkey;
    await tplWriteAssign(assign);
    ASSIGN_DATA = assign;
    if (msgEl) { msgEl.style.color='#2e7d32'; msgEl.textContent='✅'; setTimeout(function(){msgEl.textContent='';},3000); }
    if (pill)  { pill.className=tkey==='default'?'tpl-pill tpl-pill-default':'tpl-pill'; pill.innerHTML=(tkey==='default'?'⬜':'📄')+' '+esc(tname); }
    var c = COURSES_DATA.find(function(x){return x.id===courseId;});
    if (c) { c.template_key=tkey; c.template_name=tname; }
  } catch(e) {
    if (msgEl) { msgEl.style.color='#c62828'; msgEl.textContent='✗ '+e.message; }
  }
}

/* ── ASSETS TAB: second upload zone bridges to main vkUploadBgImage ── */
function vkUploadBgImageAssets(input) {
  // Reuse the main upload logic from vk-cert-editor.js
  if (typeof vkUploadBgImage === 'function') {
    vkUploadBgImage(input, 'assets');
  }
}
</script>
</body>
</html>
