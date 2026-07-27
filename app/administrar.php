<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>VidaKushala — Panel Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Cormorant+Garamond:wght@600;700&display=swap" rel="stylesheet">
<style>
:root{
  --vk-plum:#3a0f28;--vk-rose:#c44d8a;--vk-pink:#e87ab8;
  --vk-petal:#fce8f1;--card:#fff;
  --grad-accent:linear-gradient(135deg,#c44d8a,#8b2d5a);
  --grad-hero:linear-gradient(135deg,#3a0f28,#7b2560);
  --r:16px;--rl:24px;--shs:0 2px 12px rgba(58,15,40,.08);
  --border:#f0e0ea;--td:#2d1020;--ts:#8a6070;--tu:#b899a8;
  /* Variables del editor de certificados */
  --bd:#e0cfd8;
  --ca:#fff;
  --fo:#fdf8fb;
  --mu:#9a7080;
  --su:#fdf5f8;
  --tx:#2d1020;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,#fbf5f8,#f3e9f0);min-height:100vh;color:var(--td);}
a{color:var(--vk-rose);text-decoration:none;}

/* HEADER */
.header{
  background:var(--grad-hero);color:#fff;
  padding:1rem 1.5rem;display:flex;align-items:center;gap:.85rem;
  box-shadow:0 4px 20px rgba(58,15,40,.2);
}
.header-icon{width:44px;height:44px;border-radius:14px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.header h1{font-family:'DM Sans',sans-serif;font-size:1.35rem;font-weight:700;}
.header p{font-size:.75rem;opacity:.7;margin-top:.1rem;}
.header-right{margin-left:auto;display:flex;align-items:center;gap:.75rem;}

/* CONTAINER */
.container{max-width:1200px;margin:0 auto;padding:1.5rem}
.card{background:var(--card);border-radius:var(--r);padding:1.4rem;margin-bottom:1rem;box-shadow:var(--shs);border:1px solid var(--border);}
.card h2{font-family:'DM Sans',sans-serif;font-size:1.2rem;font-weight:700;color:var(--vk-plum);margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1.5px solid var(--vk-petal);}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.85rem;margin-bottom:1.25rem;}
.stat{background:var(--vk-petal);border-radius:14px;padding:1rem 1.1rem;text-align:center;border:1px solid rgba(196,77,138,.12);}
.stat .num{font-family:'DM Sans',sans-serif;font-size:2rem;font-weight:700;color:var(--vk-plum);}
.stat .lbl{font-size:.75rem;color:var(--ts);font-weight:600;margin-top:.15rem;}
.stat.accent{background:var(--grad-accent);border:none;}
.stat.accent .num,.stat.accent .lbl{color:white;}

/* FIELDS */
.field{margin-bottom:.9rem;}
.field label{display:block;font-size:.82rem;font-weight:700;color:var(--ts);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.05em;}
.field input,.field textarea,.field select{
  width:100%;padding:.7rem .95rem;border:1.5px solid var(--border);
  border-radius:12px;font-family:inherit;font-size:.9rem;color:var(--td);
  outline:none;transition:.18s;background:white;
}
.field input:focus,.field textarea:focus,.field select:focus{border-color:var(--vk-rose);box-shadow:0 0 0 3px rgba(196,77,138,.1);}
.field textarea{resize:vertical;min-height:80px;}

/* BUTTONS */
.btn{padding:.7rem 1.4rem;border:none;border-radius:12px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;transition:.18s;display:inline-flex;align-items:center;gap:.4rem;}
.btn-primary{background:var(--grad-accent);color:#fff;box-shadow:0 4px 16px rgba(196,77,138,.28);}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 24px rgba(196,77,138,.36);}
.btn-secondary{background:var(--vk-petal);color:var(--vk-plum);}
.btn-secondary:hover{background:#f8d8eb;}
.btn-outline{background:white;color:var(--vk-plum);border:1.5px solid var(--vk-rose);}
.btn-sm{padding:.45rem .9rem;font-size:.8rem;}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none!important;}

/* TABS */
.tabs{display:flex;gap:.4rem;margin-bottom:1.25rem;flex-wrap:wrap;}
.tab{padding:.5rem 1.15rem;border-radius:20px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:.83rem;font-weight:600;color:var(--ts);transition:.18s;font-family:inherit;}
.tab.on{background:var(--grad-accent);color:white;border-color:transparent;box-shadow:0 4px 14px rgba(196,77,138,.25);}
.panel{display:none}.panel.on{display:block}

/* GRID */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
@media(max-width:700px){.grid-2{grid-template-columns:1fr;}}

/* TAGS */
.tag{display:inline-block;padding:.18rem .6rem;border-radius:20px;font-size:.72rem;font-weight:700;}
.tag-g{background:#e8f5e9;color:#2e7d32;}
.tag-b{background:var(--vk-petal);color:var(--vk-plum);}
.tag-o{background:#fff8e1;color:#b36b00;}
.tag-r{background:#ffebee;color:#c62828;}

/* TABLE */
.table{width:100%;border-collapse:collapse;font-size:.84rem;}
.table th{background:var(--vk-petal);padding:.55rem .85rem;text-align:left;font-weight:700;color:var(--vk-plum);font-size:.76rem;text-transform:uppercase;letter-spacing:.05em;}
.table td{padding:.6rem .85rem;border-bottom:1px solid var(--border);color:var(--td);}
.table tr:hover td{background:#fdf5f9;}

/* MESSAGES */
.msg{padding:.65rem .95rem;border-radius:10px;font-size:.85rem;margin:.5rem 0;display:flex;align-items:center;gap:.5rem;}
.msg-ok{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;}
.msg-err{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;}
.msg-info{background:var(--vk-petal);color:var(--vk-plum);border:1px solid rgba(196,77,138,.2);}

/* LOGIN */
#login-screen{position:fixed;inset:0;background:var(--grad-hero);display:flex;align-items:center;justify-content:center;z-index:1000;}
.login-box{background:white;border-radius:24px;padding:2.25rem;width:340px;text-align:center;box-shadow:0 24px 80px rgba(58,15,40,.35);}
.login-box .logo{width:64px;height:64px;border-radius:20px;background:var(--grad-accent);display:flex;align-items:center;justify-content:center;font-size:1.75rem;margin:0 auto 1rem;}
.login-box h2{font-family:'DM Sans',sans-serif;color:var(--vk-plum);font-size:1.6rem;margin-bottom:.35rem;}
.login-box p{color:var(--ts);font-size:.85rem;margin-bottom:1.25rem;}

/* AUTO CARDS */
.auto-card{background:var(--vk-petal);border-radius:14px;padding:1.1rem;margin-bottom:.85rem;border:1px solid rgba(196,77,138,.15);}
.auto-card h3{font-size:.92rem;color:var(--vk-plum);font-weight:700;margin-bottom:.4rem;display:flex;align-items:center;gap:.4rem;}
.auto-card .desc{font-size:.8rem;color:var(--ts);margin-bottom:.7rem;}
.switch{position:relative;display:inline-block;width:44px;height:24px;}
.switch input{opacity:0;width:0;height:0}
.slider{position:absolute;cursor:pointer;inset:0;background:#ddd;transition:.3s;border-radius:24px}
.slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;transition:.3s;border-radius:50%}
input:checked+.slider{background:var(--vk-rose)}
input:checked+.slider:before{transform:translateX(20px)}
.auto-toggle{display:flex;align-items:center;gap:.6rem;margin-bottom:.6rem;}

/* LOADER */
.loader{display:inline-block;width:15px;height:15px;border:2.5px solid rgba(255,255,255,.4);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:.35rem;}
@keyframes spin{to{transform:rotate(360deg)}}

/* PREVIEW */
.notif-preview{
  background:#f5f5f5;border-radius:14px;padding:1rem;
  display:flex;align-items:center;gap:.85rem;max-width:400px;
  border:1px solid #e0e0e0;
}
.preview-icon{width:44px;height:44px;border-radius:12px;background:var(--grad-accent);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}

/* KEY ROW */
.key-row{display:flex;gap:.5rem;align-items:center;}
.key-row input{flex:1;}

/* NOTIF HISTORY */
.nh-card{
  display:flex;align-items:flex-start;gap:.75rem;
  padding:.85rem;background:var(--vk-petal);border-radius:12px;
  margin-bottom:.6rem;border:1px solid rgba(196,77,138,.1);
}
.nh-icon{width:38px;height:38px;border-radius:10px;background:var(--grad-accent);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.nh-type-badge{display:inline-block;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.15rem;opacity:.8}
.nh-title{font-size:.88rem;font-weight:700;color:var(--vk-plum);}
.nh-msg{font-size:.8rem;color:var(--ts);line-height:1.4;margin-top:.15rem;}
.nh-meta{font-size:.72rem;color:var(--tu);margin-top:.3rem;display:flex;gap:.5rem;}

/* ── PANEL DE CERTIFICADOS ── */
.cert-canvas-wrap{
  background:#f0f0f0;border-radius:14px;padding:1rem;
  display:flex;justify-content:center;align-items:center;
  border:1.5px solid var(--border);overflow:hidden;
  min-height:220px;
}
#cert-preview{
  max-width:100%;border-radius:8px;
  box-shadow:0 4px 24px rgba(0,0,0,.18);
  display:block;
}
.cert-section{background:var(--vk-petal);border-radius:12px;padding:1rem 1.1rem;margin-bottom:.85rem;border:1px solid rgba(196,77,138,.12);}
.cert-section h3{font-size:.88rem;font-weight:700;color:var(--vk-plum);margin-bottom:.75rem;display:flex;align-items:center;gap:.4rem;}
.cert-row{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;}
@media(max-width:600px){.cert-row{grid-template-columns:1fr;}}
.cert-field{margin-bottom:.5rem;}
.cert-field label{display:block;font-size:.76rem;font-weight:700;color:var(--ts);margin-bottom:.2rem;text-transform:uppercase;letter-spacing:.04em;}
.cert-field input[type=text],.cert-field input[type=number],.cert-field select,.cert-field textarea{
  width:100%;padding:.5rem .75rem;border:1.5px solid var(--border);border-radius:10px;
  font-size:.85rem;font-family:inherit;outline:none;transition:.15s;background:white;
}
.cert-field input:focus,.cert-field select:focus{border-color:var(--vk-rose);}
.cert-field input[type=color]{width:48px;height:36px;border:1.5px solid var(--border);border-radius:8px;padding:2px;cursor:pointer;background:white;}
.cert-field input[type=range]{width:100%;accent-color:var(--vk-rose);}
.color-row{display:flex;align-items:center;gap:.5rem;}
.color-row input[type=text]{flex:1;font-family:monospace;}
.upload-zone{
  border:2px dashed rgba(196,77,138,.35);border-radius:12px;padding:1.5rem 1rem;
  text-align:center;cursor:pointer;transition:.2s;background:rgba(252,232,241,.5);
}
.upload-zone:hover{border-color:var(--vk-rose);background:var(--vk-petal);}
.upload-zone p{font-size:.83rem;color:var(--ts);margin-top:.4rem;}
.upload-zone .icon{font-size:2rem;}
.bg-preview-thumb{width:100%;height:80px;object-fit:cover;border-radius:8px;margin-top:.5rem;border:1px solid var(--border);}


/* ── HISTORY PANEL ─────────────────────────────────────── */
.nh-card {
  display:flex; align-items:flex-start; gap:.75rem;
  padding:.85rem 1rem; background:#fff; border-radius:14px;
  margin-bottom:.55rem; border:1.5px solid var(--border);
  box-shadow:0 1px 5px rgba(58,15,40,.04);
  transition:box-shadow .15s;
}
.nh-card:hover { box-shadow:0 3px 14px rgba(58,15,40,.09); }
.nh-card.is-global { border-color:rgba(100,100,220,.18); }
.nh-icon {
  width:40px; height:40px; border-radius:12px;
  background:var(--grad-accent); display:flex;
  align-items:center; justify-content:center; font-size:1.15rem;
  flex-shrink:0; box-shadow:0 3px 10px rgba(196,77,138,.25);
}
.nh-icon.type-course    { background:linear-gradient(135deg,#43a047,#2e7d32); }
.nh-icon.type-product   { background:linear-gradient(135deg,#fb8c00,#e65100); }
.nh-icon.type-poll      { background:linear-gradient(135deg,#1e88e5,#0d47a1); }
.nh-icon.type-cert      { background:linear-gradient(135deg,#f9a825,#e65100); }
.nh-icon.type-system    { background:linear-gradient(135deg,#78909c,#37474f); }
.nh-icon.type-info      { background:linear-gradient(135deg,#8e24aa,#4a148c); }
.nh-body { flex:1; min-width:0; }
.nh-header { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
.nh-title { font-size:.88rem; font-weight:700; color:var(--vk-plum); line-height:1.3; }
.nh-msg { font-size:.81rem; color:var(--ts); line-height:1.45; margin:.25rem 0; }
.nh-meta { display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; margin-top:.3rem; }
.nh-time { font-size:.72rem; color:var(--tu); }
.nh-tag-global { font-size:.68rem; background:rgba(100,100,220,.1); color:#5555cc; padding:.1rem .45rem; border-radius:10px; font-weight:700; }
.nh-tag-user   { font-size:.68rem; background:var(--vk-petal); color:var(--vk-plum); padding:.1rem .45rem; border-radius:10px; font-weight:700; }
.nh-recipient  { font-size:.72rem; color:var(--ts); }
.nh-del-btn {
  width:28px; height:28px; border-radius:8px; border:none; cursor:pointer;
  background:transparent; color:#c0b0ba; display:flex; align-items:center;
  justify-content:center; transition:.15s; flex-shrink:0;
}
.nh-del-btn:hover { background:#ffebee; color:#c62828; }
.nh-del-btn svg { pointer-events:none; }
.hist-empty { text-align:center; padding:3rem 1rem; color:var(--tu); }
.hist-empty .empty-icon { font-size:2.5rem; margin-bottom:.75rem; }
.hist-empty p { font-size:.85rem; }
.hist-pagination-btn {
  padding:.38rem .85rem; border-radius:10px; border:1.5px solid var(--border);
  background:white; font-size:.83rem; font-weight:600; cursor:pointer;
  color:var(--vk-plum); font-family:inherit; transition:.15s;
}
.hist-pagination-btn:hover { background:var(--vk-petal); }
.hist-pagination-btn.active { background:var(--grad-accent); color:white; border-color:transparent; }
.hist-pagination-btn:disabled { opacity:.4; cursor:not-allowed; }

/* ─── Editor de Certificados ──────────────────────────────── */
.cert-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; padding:.75rem 1rem; background:var(--su); border-radius:10px; margin-bottom:1rem; }
.cert-toolbar-left { display:flex; gap:.5rem; flex-wrap:wrap; }
.cert-msg-bar { font-size:.82rem; padding:.25rem .6rem; }
.cert-editor-layout { display:grid; grid-template-columns:310px 1fr; gap:1rem; min-height:600px; }
@media(max-width:900px){ .cert-editor-layout { grid-template-columns:1fr; } }
.cert-controls-panel { overflow-y:auto; max-height:80vh; padding-right:.25rem; scroll-behavior:smooth; }
.cert-section { border:1px solid var(--bd); border-radius:8px; margin-bottom:.5rem; background:var(--ca); overflow:hidden; }
.cert-section summary { padding:.6rem .8rem; cursor:pointer; font-weight:600; font-size:.83rem; color:var(--tu); list-style:none; display:flex; align-items:center; gap:.4rem; user-select:none; }
.cert-section summary::-webkit-details-marker { display:none; }
.cert-section summary::before { content:'▶'; font-size:.65rem; transition:transform .2s; }
.cert-section[open] summary::before { transform:rotate(90deg); }
.cert-section > :not(summary) { padding:.5rem .8rem .8rem; }
.cert-field { margin-bottom:.5rem; }
.cert-field label { display:block; font-size:.76rem; color:var(--mu); margin-bottom:.2rem; font-weight:500; }
.cert-field input[type="text"],.cert-field input[type="number"],.cert-field select { width:100%; padding:.3rem .5rem; border:1px solid var(--bd); border-radius:6px; background:var(--fo); color:var(--tx); font-size:.82rem; }
.cert-field input[type="number"] { max-width:75px; }
.cert-field input[type="checkbox"] { margin-right:.35rem; }
.cert-field-row { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:.5rem; }
.cert-field-row .cert-field { margin-bottom:0; }
.cert-radio-group { display:flex; gap:.75rem; }
.cert-radio-group label { font-size:.82rem; display:flex; align-items:center; gap:.25rem; }
.color-pair { display:flex; gap:.4rem; align-items:center; }
.color-pair input[type="color"] { width:36px; height:32px; border:none; border-radius:5px; cursor:pointer; padding:2px; }
.color-pair input[type="text"] { flex:1; font-family:monospace; font-size:.8rem; }
.cert-upload-zone { border:2px dashed var(--bd); border-radius:8px; padding:.8rem; text-align:center; cursor:pointer; color:var(--mu); font-size:.82rem; transition:border-color .2s,background .2s; }
.cert-upload-zone:hover { border-color:var(--vk-rose); background:rgba(196,77,138,.05); }
.cert-preview-panel { display:flex; flex-direction:column; gap:.5rem; }
.cert-canvas-container { position:relative; background:#e8e8e8; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; align-items:center; padding:.5rem; }
#cert-preview { max-width:100%; border-radius:6px; box-shadow:0 4px 20px rgba(0,0,0,.18); }
.cert-canvas-hint { font-size:.72rem; color:#999; text-align:center; margin-top:.35rem; }
.cert-cache-btn { border-color:#e87ab8 !important; color:#c44d8a !important; }

/* ─── VK Certificate Editor v3 ────────────────────────── */
.vk-cert-bar { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;padding:.75rem 1rem;background:var(--su);border-radius:10px;margin-bottom:.75rem; }
.vk-cert-bar-left { display:flex;gap:.4rem;flex-wrap:wrap; }
.vk-cache-btn { border-color:#e87ab8!important;color:#c44d8a!important; }
.vk-cert-msg { font-size:.82rem;padding:.25rem .5rem;border-radius:6px;flex:1;min-width:0; }
.vk-cert-msg-ok  { background:#d4edda;color:#155724; }
.vk-cert-msg-err { background:#f8d7da;color:#721c24; }
.vk-cert-msg-info{ background:#d1ecf1;color:#0c5460; }

/* Galería de plantillas */
.vk-tmpl-section { background:var(--su);border-radius:10px;padding:.75rem 1rem;margin-bottom:.75rem; }
.vk-tmpl-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem;font-weight:600;font-size:.88rem; }
.vk-tmpl-hint { font-size:.75rem;color:var(--mu);font-weight:400; }
.vk-tmpl-gallery { display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.5rem; }
.vk-tmpl-card { border:2px solid var(--bd);border-radius:8px;padding:.5rem;cursor:pointer;transition:border-color .18s,box-shadow .18s,transform .12s;background:var(--ca); }
.vk-tmpl-card:hover { border-color:var(--vk-rose);box-shadow:0 4px 12px rgba(196,77,138,.18);transform:translateY(-2px); }
.vk-tmpl-card.active { border-color:var(--vk-rose);box-shadow:0 0 0 3px rgba(196,77,138,.2); }
.vk-tmpl-name { font-size:.73rem;font-weight:600;color:var(--tx);text-align:center;line-height:1.2; }

/* Layout editor */
.vk-editor-layout { display:grid;grid-template-columns:300px 1fr;gap:.75rem;min-height:600px; }
@media(max-width:960px){ .vk-editor-layout { grid-template-columns:1fr; } }

/* Panel de controles */
.vk-ctrl-panel { overflow-y:auto;max-height:75vh;padding-right:.15rem; }
.vk-sect { border:1px solid var(--bd);border-radius:8px;margin-bottom:.45rem;background:var(--ca);overflow:hidden; }
.vk-sect summary { padding:.55rem .7rem;cursor:pointer;font-weight:600;font-size:.82rem;color:var(--tu);list-style:none;display:flex;align-items:center;gap:.35rem;user-select:none; }
.vk-sect summary::-webkit-details-marker{display:none}
.vk-sect summary::before { content:'▶';font-size:.6rem;transition:transform .15s; }
.vk-sect[open] summary::before { transform:rotate(90deg); }
.vk-ctrl-body { padding:.4rem .7rem .7rem; }
.vk-field { margin-bottom:.45rem; }
.vk-field label { display:block;font-size:.75rem;color:var(--mu);margin-bottom:.18rem;font-weight:500; }
.vk-field input[type=text],.vk-field input[type=number],.vk-field select { width:100%;padding:.3rem .45rem;border:1px solid var(--bd);border-radius:5px;background:var(--fo);color:var(--tx);font-size:.8rem; }
.vk-field input[type=number] { max-width:75px; }
.vk-field input[type=checkbox] { margin-right:.3rem; }
.vk-field-row { display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.45rem;align-items:flex-end; }
.vk-field-row .vk-field { margin-bottom:0;flex-shrink:0; }
.vk-radio-row { display:flex;gap:.6rem;flex-wrap:wrap; }
.vk-radio-row label { font-size:.8rem;display:flex;align-items:center;gap:.2rem; }
.vk-color-pair { display:flex;gap:.35rem;align-items:center; }
.vk-color-pair input[type=color] { width:34px;height:30px;border:none;border-radius:5px;cursor:pointer;padding:2px; }
.vk-color-pair input[type=text] { flex:1;font-family:monospace;font-size:.78rem; }
.vk-upload-zone { border:2px dashed var(--bd);border-radius:8px;padding:.65rem;text-align:center;cursor:pointer;color:var(--mu);font-size:.8rem;transition:all .18s; }
.vk-upload-zone:hover { border-color:var(--vk-rose);background:rgba(196,77,138,.04); }
.vk-chk-row { display:flex;gap:.8rem; }
.vk-chk-row label { font-size:.8rem;display:flex;align-items:center;gap:.2rem; }
.vk-drag-tip { font-size:.65rem;background:rgba(196,77,138,.12);color:var(--vk-rose);padding:.1rem .3rem;border-radius:4px;margin-left:.3rem; }
.vk-inline { display:flex;gap:.4rem;align-items:center;flex-wrap:wrap; }

/* Canvas preview */
.vk-preview-panel { display:flex;flex-direction:column;gap:.4rem; }
.vk-canvas-wrap { position:relative;background:#d8d8d8;border-radius:10px;overflow:hidden;padding:.5rem;display:flex;justify-content:center;align-items:flex-start; }
#vk-cert-canvas { max-width:100%;border-radius:5px;box-shadow:0 6px 24px rgba(0,0,0,.2);display:block; }
.vk-canvas-hint { font-size:.72rem;color:#888;text-align:center;padding:.2rem; }

/* Drag handles */


/* Spinner */
.vk-spin { display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,.4);border-top-color:white;border-radius:50%;animation:spin .6s linear infinite;margin-right:.3rem;vertical-align:middle; }
/* ─── Mini galería de plantillas (en Design tab) ──────────────── */
.vk-mini-tmpl { border:1px solid var(--bd);border-radius:10px;padding:.6rem .8rem;margin-bottom:.7rem;background:var(--su); }
.vk-mini-tmpl-header { font-size:.78rem;font-weight:700;color:var(--tu);display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem; }
.vk-mini-toggle { background:none;border:1px solid var(--bd);border-radius:5px;padding:.1rem .45rem;font-size:.7rem;cursor:pointer;color:var(--mu); }
.vk-mini-toggle:hover { border-color:var(--vk-rose);color:var(--vk-rose); }
.vk-mini-tmpl-gallery { display:grid;grid-template-columns:repeat(5,1fr);gap:.3rem; }
.vk-tmpl-card-sm { font-size:.62rem !important; }
.vk-tmpl-card-sm .vk-tmpl-name { font-size:.62rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
/* ─── Editor tabs ──────────────────────────────────────────────────── */
.vk-editor-tabs { display:flex;gap:.3rem;margin-bottom:.75rem;border-bottom:2px solid var(--bd);padding-bottom:.4rem; }
.vk-tab { background:transparent;border:1px solid var(--bd);border-radius:6px 6px 0 0;padding:.35rem .75rem;cursor:pointer;font-size:.8rem;color:var(--mu);transition:all .15s; }
.vk-tab:hover { border-color:var(--vk-rose);color:var(--vk-rose); }
.vk-tab.active { background:var(--vk-rose);color:#fff;border-color:var(--vk-rose);font-weight:600; }
/* Tab content */
.vk-tab-content { min-height:600px; }
/* Preview data */
.vk-preview-data { background:var(--su);border:1px solid var(--bd);border-radius:8px;padding:.6rem .8rem;margin-top:.5rem; }
.vk-pd-title { font-size:.75rem;font-weight:600;color:var(--mu);margin-bottom:.4rem; }
.vk-pd-row { display:grid;grid-template-columns:1fr 1fr;gap:.3rem .5rem; }
.vk-pd-field { display:flex;align-items:center;gap:.3rem;font-size:.75rem; }
.vk-pd-field label { color:var(--mu);white-space:nowrap;min-width:50px; }
.vk-pd-field input { flex:1;padding:.2rem .35rem;border:1px solid var(--bd);border-radius:4px;font-size:.73rem;background:var(--fo);color:var(--tx);min-width:0; }
/* Template gallery (full tab) */
.vk-tmpl-header-bar { display:flex;align-items:center;justify-content:space-between;margin-bottom:.6rem; }
.vk-tmpl-bar-title { font-weight:700;font-size:.9rem; }
.vk-tmpl-bar-hint { font-size:.75rem;color:var(--mu); }
.vk-tmpl-gallery-full { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.6rem;margin-bottom:.8rem; }
/* File gallery */
.vk-file-gallery { display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.5rem; }
.vk-file-loading { padding:1rem;color:var(--mu);text-align:center;display:flex;align-items:center;gap:.5rem;justify-content:center; }
.vk-file-card { border:2px solid var(--bd);border-radius:8px;overflow:hidden;cursor:pointer;transition:border-color .15s,transform .12s; }
.vk-file-card:hover { border-color:var(--vk-rose);transform:translateY(-2px); }
.vk-file-card img { width:100%;height:90px;object-fit:cover;display:block; }
.vk-file-card-name { font-size:.67rem;padding:.2rem .3rem;text-align:center;color:var(--mu);word-break:break-all; }
/* Font cards */
.vk-fonts-panel { padding:.5rem 0; }
.vk-font-section { margin-bottom:1.5rem; }
.vk-font-section h3 { font-size:.9rem;font-weight:700;margin-bottom:.6rem;color:var(--tu); }
.vk-font-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.5rem; }
.vk-font-card { border:2px solid var(--bd);border-radius:8px;padding:.6rem .8rem;cursor:pointer;transition:border-color .15s,background .15s;display:flex;flex-direction:column;gap:.3rem;background:var(--ca); }
.vk-font-card:hover { border-color:var(--vk-rose); }
.vk-font-card.active { border-color:var(--vk-rose);background:rgba(196,77,138,.07); }
.vk-font-name { font-size:.7rem;color:var(--mu);font-weight:500; }
/* Assets grid */
.vk-assets-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:.75rem; }
.vk-asset-card { border:1px solid var(--bd);border-radius:10px;padding:1rem;background:var(--ca); }
.vk-asset-card h3 { font-size:.9rem;font-weight:700;margin-bottom:.3rem; }
.vk-asset-desc { font-size:.78rem;color:var(--mu);margin-bottom:.6rem;line-height:1.4; }

/* ── AI Chat access table ── */
.ac-table{width:100%;border-collapse:collapse;font-size:.84rem;}
.ac-table th{background:var(--vk-petal);padding:.55rem .85rem;text-align:left;font-weight:700;color:var(--vk-plum);font-size:.76rem;text-transform:uppercase;letter-spacing:.05em;}
.ac-table td{padding:.6rem .85rem;border-bottom:1px solid var(--border);vertical-align:middle;}
.ac-table tr:hover td{background:#fdf5f9;}
.badge-on{display:inline-block;padding:.18rem .65rem;border-radius:20px;font-size:.72rem;font-weight:700;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;}
.badge-off{display:inline-block;padding:.18rem .65rem;border-radius:20px;font-size:.72rem;font-weight:700;background:#ffebee;color:#c62828;border:1px solid #ef9a9a;}
.ac-search-row{display:flex;gap:.5rem;margin-bottom:.85rem;flex-wrap:wrap;align-items:center;}
.ac-search-row input{flex:1;padding:.5rem .85rem;border:1.5px solid var(--border);border-radius:10px;font-size:.85rem;font-family:inherit;outline:none;}
.ac-search-row select{padding:.5rem .75rem;border:1.5px solid var(--border);border-radius:10px;font-size:.84rem;font-family:inherit;outline:none;}

/* ── Template name tag ── */
.tmpl-tag{display:inline-block;background:var(--vk-petal);color:var(--vk-plum);border:1px solid rgba(196,77,138,.2);border-radius:8px;padding:.15rem .55rem;font-size:.75rem;font-weight:600;}

/* ══ TEMPLATE MANAGER ══════════════════════════════════════════ */
.tmgr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin-bottom:1rem;}
.tmgr-card{background:#fff;border:2px solid var(--border);border-radius:14px;overflow:hidden;position:relative;transition:.18s;cursor:pointer;}
.tmgr-card:hover{border-color:var(--vk-rose);transform:translateY(-2px);box-shadow:0 8px 24px rgba(196,77,138,.15);}
.tmgr-card.active{border-color:var(--vk-rose);box-shadow:0 0 0 3px rgba(196,77,138,.2);}
.tmgr-thumb{width:100%;height:110px;object-fit:cover;display:block;background:linear-gradient(135deg,#f8e8f0,#ede0f0);}
.tmgr-thumb-placeholder{width:100%;height:110px;display:flex;align-items:center;justify-content:center;font-size:2.5rem;background:linear-gradient(135deg,var(--vk-petal),#ede8f8);}
.tmgr-body{padding:.75rem;}
.tmgr-name{font-weight:700;font-size:.88rem;color:var(--vk-plum);margin-bottom:.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tmgr-meta{font-size:.72rem;color:var(--tu);}
.tmgr-actions{display:flex;gap:.3rem;margin-top:.6rem;flex-wrap:wrap;}
.tmgr-badge{display:inline-block;padding:.1rem .45rem;border-radius:20px;font-size:.68rem;font-weight:700;background:var(--vk-petal);color:var(--vk-plum);}
.tmgr-badge-used{background:#e8f5e9;color:#2e7d32;}
.tmgr-add-card{background:transparent;border:2px dashed var(--border);border-radius:14px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;min-height:200px;cursor:pointer;transition:.18s;color:var(--ts);}
.tmgr-add-card:hover{border-color:var(--vk-rose);color:var(--vk-rose);background:rgba(196,77,138,.03);}
.tmgr-add-icon{font-size:2rem;}

/* Modal overlay */
.vk-modal-bg{position:fixed;inset:0;background:rgba(30,0,20,.55);z-index:2000;display:flex;align-items:center;justify-content:center;padding:1rem;}
.vk-modal{background:#fff;border-radius:20px;max-width:460px;width:100%;padding:1.75rem;box-shadow:0 24px 80px rgba(58,15,40,.35);position:relative;}
.vk-modal h3{font-family:'DM Sans',sans-serif;color:var(--vk-plum);font-size:1.3rem;margin-bottom:1rem;}
.vk-modal-close{position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--tu);}
.vk-modal-close:hover{color:var(--vk-plum);}

/* Course assignment table */
.cca-table{width:100%;border-collapse:collapse;font-size:.84rem;}
.cca-table th{background:var(--vk-petal);padding:.5rem .8rem;text-align:left;font-weight:700;color:var(--vk-plum);font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;}
.cca-table td{padding:.55rem .8rem;border-bottom:1px solid var(--border);vertical-align:middle;}
.cca-table tr:hover td{background:#fdf5f9;}
.tmpl-pill{display:inline-flex;align-items:center;gap:.35rem;background:var(--vk-petal);color:var(--vk-plum);border:1px solid rgba(196,77,138,.2);border-radius:20px;padding:.18rem .65rem;font-size:.75rem;font-weight:600;}
.tmpl-pill-default{background:#f0f0f0;color:var(--tu);border-color:#ddd;}

</style>
</head>
<body>

<!-- LOGIN -->
<div id="login-screen">
  <div class="login-box">
    <div class="logo">🔔</div>
    <h2>Panel Admin</h2>
    <p>VidaKushala — Notificaciones</p>
    <div style="display:flex;border-radius:10px;overflow:hidden;border:1.5px solid var(--border);margin-bottom:1.1rem">
      <button id="ltab-pass" onclick="switchLoginTab('pass')" style="flex:1;padding:.55rem;border:none;background:var(--grad-accent);color:white;font-family:inherit;font-size:.83rem;font-weight:700;cursor:pointer">🔐 Usuario</button>
      <button id="ltab-tok"  onclick="switchLoginTab('tok')"  style="flex:1;padding:.55rem;border:none;background:white;color:var(--ts);font-family:inherit;font-size:.83rem;font-weight:600;cursor:pointer">🔑 Token</button>
    </div>
    <div id="lpanel-pass">
      <div class="field"><input type="text" id="login-user" placeholder="Usuario o email de WordPress" style="margin-bottom:.6rem" autocomplete="username"></div>
      <div class="field" style="position:relative">
        <input type="password" id="login-pass" placeholder="Contraseña" autocomplete="current-password" onkeydown="if(event.key==='Enter')doLoginPass()">
        <button onclick="togglePass()" title="Ver contraseña" style="position:absolute;right:.7rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:var(--tu)">👁</button>
      </div>
      <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="doLoginPass()">Entrar</button>
    </div>
    <div id="lpanel-tok" style="display:none">
      <div class="field"><input type="text" id="login-tok" placeholder="Pega tu vk_tok aquí" style="text-align:center"></div>
      <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="doLoginTok()">Entrar con token</button>
    </div>
    <p id="login-err" style="color:#c62828;font-size:.82rem;margin-top:.65rem;min-height:1.8em;line-height:1.4"></p>
  </div>
</div>

<!-- HEADER -->
<div class="header">
  <div class="header-icon">🔔</div>
  <div><h1>Panel de Notificaciones</h1><p>VidaKushala · app.vidakushala.com</p></div>
  <div class="header-right">
    <span id="admin-name" style="font-size:.82rem;opacity:.8"></span>
    <button class="btn btn-sm" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.3)" onclick="logout()">Salir</button>
  </div>
</div>

<div class="container">

  <div class="stats">
    <div class="stat"><div class="num" id="s-subs">-</div><div class="lbl">Suscriptores</div></div>
    <div class="stat"><div class="num" id="s-devices">-</div><div class="lbl">Dispositivos</div></div>
    <div class="stat"><div class="num" id="s-sent">-</div><div class="lbl">Push enviadas</div></div>
    <div class="stat accent"><div class="num" id="s-key">-</div><div class="lbl">API Key</div></div>
  </div>

  <div class="tabs">
    <div class="tab on"  onclick="showTab('send')">📤 Enviar</div>
    <div class="tab"     onclick="showTab('auto')">🤖 Automatizaciones</div>
    <div class="tab"     onclick="showTab('live')">&#x1F4F9; Clase en Linea</div>
    <div class="tab"     onclick="showTab('history')">📋 Historial</div>
    <div class="tab"     onclick="showTab('users')">👥 Suscriptores</div>
    <div class="tab"     onclick="window.location.href='editor.php'">🏆 Certificados</div>
    <div class="tab"     onclick="showTab('aichat')">💬 AI Chat</div>
    <div class="tab"     onclick="showTab('config')" style="display: none;">⚙️ Configuración</div>
    <div class="tab"     onclick="window.location.href='tema.php'" style="display: none;" >🎨 Tema</div>
  </div>

  <!-- PANEL: ENVIAR -->
  <div class="panel on" id="panel-send">
    <div class="card">
      <h2>📤 Enviar Notificación Push</h2>
      <div class="grid-2">
        <div>
          <div class="field"><label>Destinatario</label>
            <select id="n-target">
              <option value="all">📢 Todos los suscriptores</option>
              <option value="segment">🎯 Segmento específico</option>
              <option value="user">👤 Usuario por email</option>
            </select>
          </div>
          <div class="field" id="f-segment" style="display:none">
            <label>Segmento OneSignal</label>
            <select id="n-segment"><option value="All">All (todos)</option><option value="Subscribed Users">Subscribed Users</option><option value="Active Users">Active Users</option></select>
          </div>
          <div class="field" id="f-user-email" style="display:none">
            <label>Email del usuario</label>
            <input type="email" id="n-user-email" placeholder="usuario@email.com">
          </div>
          <div class="field"><label>Tipo</label>
            <select id="n-type">
              <option value="info">💬 Información general</option>
              <option value="course">📚 Nuevo curso</option>
              <option value="product">🛍️ Nuevo producto</option>
              <option value="poll">📊 Nueva encuesta</option>
              <option value="cert">🏆 Certificado</option>
              <option value="system">🔔 Sistema</option>
            </select>
          </div>
          <div class="field"><label>URL al hacer clic</label>
            <input type="url" id="n-url" value="https://app.vidakushala.com/" placeholder="https://app.vidakushala.com/">
          </div>
        </div>
        <div>
          <div class="field"><label>Título</label><input type="text" id="n-title" value="VidaKushala" placeholder="VidaKushala"></div>
          <div class="field"><label>Mensaje *</label><textarea id="n-message" rows="4" placeholder="Escribe el mensaje..."></textarea></div>
        </div>
      </div>
      <div style="display:flex;gap:.65rem;align-items:center;flex-wrap:wrap">
        <button class="btn btn-primary" id="btn-send" onclick="sendNotification()">&#x1F4E4; Enviar ahora</button>
        <button class="btn btn-secondary" onclick="sendTest()">&#x1F9EA; Prueba a m&iacute;</button>
        <button class="btn btn-secondary" onclick="sendCloneWelcome()" style="background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7" title="Env&iacute;a igual que bienvenida que s&iacute; funciona">&#x1F3AF; Test clon</button>
        <button class="btn btn-secondary" onclick="pushDebugFull()" style="background:#e3f2fd;color:#1565c0;border:1px solid #90caf9">&#x1F9EC; Debug</button>
        <div id="send-msg" style="flex:1"></div>
      </div>
    </div>
    <div class="card">
      <h2>👁️ Vista Previa</h2>
      <div class="notif-preview">
        <div class="preview-icon" id="prev-icon">🔔</div>
        <div>
          <div style="font-weight:700;font-size:.92rem;color:#111" id="prev-title">VidaKushala</div>
          <div style="font-size:.83rem;color:#666;margin-top:.15rem" id="prev-msg">Tu mensaje aquí...</div>
          <div style="font-size:.72rem;color:#999;margin-top:.25rem">app.vidakushala.com · ahora mismo</div>
        </div>
      </div>
    </div>
  </div>

  <!-- PANEL: AUTOMATIZACIONES -->
  <!-- MODAL: ACTIVAR CUENTA MANUAL -->
<div id="activate-modal" style="display:none;position:fixed;inset:0;background:rgba(58,15,40,.7);z-index:9999;align-items:center;justify-content:center;padding:1.5rem">
  <div style="background:#fff;border-radius:20px;padding:1.75rem;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <h3 style="font-size:1.1rem;color:var(--vk-plum);margin-bottom:.75rem">&#x2705; Activar cuenta manualmente</h3>
    <p style="font-size:.85rem;color:var(--ts);margin-bottom:1rem">Ingresa el email del usuario cuya cuenta quieres activar. Úsalo cuando el correo de verificación no llegue.</p>
    <div class="field">
      <label>Email del usuario</label>
      <input type="email" id="activate-email-input" placeholder="usuario@ejemplo.com" onkeydown="if(event.key==='Enter')doActivateUser()">
    </div>
    <div id="activate-msg" style="min-height:1.5em;font-size:.83rem;margin-bottom:.75rem"></div>
    <div style="display:flex;gap:.5rem">
      <button class="btn btn-primary" onclick="doActivateUser()">&#x2705; Activar cuenta</button>
      <button class="btn btn-secondary" onclick="document.getElementById('activate-modal').style.display='none'">Cancelar</button>
    </div>
  </div>
</div>

<!-- PANEL: CLASE EN LINEA -->
  <div class="panel" id="panel-live">
    <div class="card">
      <h2>&#x1F4F9; Clase en Linea</h2>
      <p style="font-size:.85rem;color:var(--ts);margin-bottom:1rem">Envia notificaciones de clase en vivo con enlace directo a la plataforma.</p>
      <div class="field"><label>Destinatarios <span class="req-badge">*</span></label>
        <div style="display:flex;gap:.5rem;margin-bottom:.5rem">
          <button type="button" id="live-tgt-all" class="btn btn-primary btn-sm" onclick="setLiveTarget('all')">Todos</button>
          <button type="button" id="live-tgt-user" class="btn btn-secondary btn-sm" onclick="setLiveTarget('user')">Usuario(s)</button>
        </div>
        <div id="live-user-search-wrap" style="display:none">
          <input type="text" id="live-user-search" placeholder="Buscar usuario..." oninput="searchLiveUsers(this.value)">
          <div id="live-user-results" style="max-height:150px;overflow-y:auto;border:1px solid var(--border-light);border-radius:10px;margin-top:.35rem"></div>
          <div id="live-selected-users" style="margin-top:.35rem;display:flex;flex-wrap:wrap;gap:.35rem"></div>
        </div>
      </div>
      <div class="field"><label>Plataforma</label>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap">
          <button type="button" class="btn btn-secondary btn-sm plat-btn" id="plat-zoom"    onclick="selectPlatform('Zoom')">Zoom</button>
          <button type="button" class="btn btn-secondary btn-sm plat-btn" id="plat-meet"    onclick="selectPlatform('Google Meet')">Google Meet</button>
          <button type="button" class="btn btn-secondary btn-sm plat-btn" id="plat-teams"   onclick="selectPlatform('Teams')">Teams</button>
          <button type="button" class="btn btn-secondary btn-sm plat-btn" id="plat-youtube" onclick="selectPlatform('YouTube')">YouTube</button>
          <button type="button" class="btn btn-secondary btn-sm plat-btn" id="plat-otro"    onclick="selectPlatform('Otro')">Otro</button>
        </div>
        <input type="hidden" id="live-platform" value="Zoom">
      </div>
      <div class="field"><label>Enlace <span class="req-badge">*</span></label><input type="url" id="live-link" placeholder="https://zoom.us/j/..." oninput="detectPlatformFromUrl(this.value)" style="font-family:monospace;font-size:.85rem"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem">
        <div class="field" style="margin-bottom:0"><label>Titulo</label><input type="text" id="live-title" value="Clase en Linea Ahora"></div>
        <div class="field" style="margin-bottom:0"><label>Horario</label><input type="text" id="live-schedule" placeholder="Ej: Hoy 7:00 PM"></div>
      </div>
      <div class="field"><label>Descripcion</label><textarea id="live-message" rows="2" style="resize:vertical">Tu clase en linea esta por comenzar. Haz clic para unirte.</textarea></div>
      <div id="live-preview" style="background:linear-gradient(135deg,#1a0828,#0f1220);border-radius:14px;padding:1rem 1.25rem;margin-bottom:1rem;display:none">
        <div style="font-size:.72rem;color:rgba(240,216,232,.5);margin-bottom:.4rem;text-transform:uppercase">Vista previa</div>
        <div style="font-weight:700;color:#f0d8e8;font-size:.9rem" id="lp-title"></div>
        <div style="color:rgba(240,216,232,.65);font-size:.8rem;margin:.2rem 0" id="lp-msg"></div>
        <a id="lp-link" href="#" target="_blank" style="display:inline-block;background:#c44d8a;color:#fff;border-radius:8px;padding:.25rem .75rem;font-size:.78rem;font-weight:700;text-decoration:none">Unirse ahora</a>
      </div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
        <button class="btn btn-primary" id="btn-send-live" onclick="sendLiveClass()">Enviar notificacion</button>
        <button class="btn btn-secondary" onclick="previewLiveClass()">Vista previa</button>
        <div id="live-msg" style="flex:1;font-size:.85rem;min-height:1.5em"></div>
      </div>
    </div>
  </div>

  <div class="panel" id="panel-auto">
    <div class="card">
      <h2>🤖 Notificaciones Automáticas</h2>
      <p style="font-size:.85rem;color:var(--ts);margin-bottom:.75rem">Eventos que disparan notificaciones automáticamente vía WordPress hooks.</p>

      <!-- Aviso si hay eventos desactivados -->
      <div id="auto-disabled-warn" style="display:flex;background:#fff3cd;border:1.5px solid #ffc107;border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;gap:.75rem;align-items:center;flex-wrap:wrap">
        <span style="font-size:1.2rem">⚠️</span>
        <div style="flex:1;font-size:.84rem;color:#856404">
          <strong>Algunos eventos están desactivados.</strong> Activa los que necesites individualmente o usa el botón para activar todos.
        </div>
        <button id="btn-enable-all" onclick="enableAllAutoNotifs()" class="btn btn-primary btn-sm" style="white-space:nowrap">⚡ Activar todos</button>
        <div id="auto-bulk-msg" style="width:100%;font-size:.82rem;min-height:1em"></div>
      </div>
      <?php
      $autos = [
        ['key'=>'new_course',     'icon'=>'📚','label'=>'Nuevo Curso Publicado',       'desc'=>'Notifica a todos cuando se publica un nuevo curso.',            'tpl'=>'¡Nuevo curso disponible! {TITLE} te espera.'],
        ['key'=>'new_product',    'icon'=>'🛍️','label'=>'Nuevo Producto Publicado',   'desc'=>'Notifica a todos cuando se publica un nuevo producto.',         'tpl'=>'Nuevo producto disponible: {TITLE}'],
        ['key'=>'new_poll',       'icon'=>'📊','label'=>'Nueva Encuesta Disponible',   'desc'=>'Notifica cuando se crea una nueva encuesta en YOP Poll.','tpl'=>'Nueva encuesta: {TITLE}. ¡Comparte tu opinión!'],
        ['key'=>'new_bundle',     'icon'=>'📦','label'=>'Nuevo Paquete Publicado',     'desc'=>'Notifica cuando se publica un nuevo paquete de cursos.',        'tpl'=>'¡Nuevo paquete disponible! {TITLE}.'],
        ['key'=>'cert_issued',    'icon'=>'🏆','label'=>'Certificado Emitido',         'desc'=>'Notifica al usuario cuando completa un curso.',                'tpl'=>'🎓 ¡Tu certificado de {COURSE} está listo!'],
        ['key'=>'course_complete','icon'=>'✅','label'=>'Curso Completado',             'desc'=>'Notifica al usuario al completar un curso.',                   'tpl'=>'¡Felicidades! Completaste el curso {TITLE}.'],
        ['key'=>'progress',       'icon'=>'🎯','label'=>'Hitos de Progreso',           'desc'=>'Notifica al alcanzar 25%, 50%, 75% en un curso.',              'tpl'=>'¡Llevas {PERCENT}% en {COURSE}! Sigue así.'],
        ['key'=>'dir_approved',   'icon'=>'🗂️','label'=>'Publicación del Directorio Aprobada','desc'=>'Notifica al usuario cuando el administrador aprueba su perfil en el directorio. Usa {NAME} para incluir el nombre del perfil.','tpl'=>'¡Tu perfil "{NAME}" ha sido aprobado y ya está visible en el directorio de Vida Kushala! Puedes compartirlo con tus clientes.'],
        ['key'=>'dir_pending',    'icon'=>'🔔','label'=>'Nuevo Perfil Pendiente de Aprobación','desc'=>'Notifica a los administradores cuando un usuario envía o actualiza su perfil en el directorio. Usa {NAME} para el nombre del profesional. Solo los administradores reciben esta notificación.','tpl'=>'📋 {NAME} ha enviado su perfil al directorio y está pendiente de aprobación.'],
      ];
      foreach ($autos as $a) {
        $kid  = str_replace('_','-',$a['key']);
        $tpl  = htmlspecialchars($a['tpl'],ENT_QUOTES,'UTF-8');
        $key  = $a['key'];
        echo '<div class="auto-card">'
          .'<h3>'.htmlspecialchars($a['icon'].$a['label'],ENT_QUOTES,'UTF-8').'</h3>'
          .'<p class="desc">'.htmlspecialchars($a['desc'],ENT_QUOTES,'UTF-8').'</p>';

        // Nota especial para YOP Poll v7
        if ($key === 'new_poll') {
          echo '<div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:.6rem .85rem;font-size:.8rem;color:#1b5e20;margin-bottom:.75rem">'
              .'<strong>ℹ️ YOP Poll v7:</strong> La detección es automática vía interceptor de BD + WP-Cron (cada 5 min). '
              .'Si creaste encuestas antes de activar esta opción, usa el botón <strong>Notificar ahora</strong> abajo.'
              .'</div>';
        }

        echo '<div class="auto-toggle">'
          .'<label class="switch"><input type="checkbox" id="auto-'.$kid.'" onchange="toggleAuto(\''.$key.'\',this.checked)"><span class="slider"></span></label>'
          .'<span style="font-size:.83rem;color:var(--ts)">Activar automáticamente</span>'
          .'</div>'
          .'<div class="field"><label>Plantilla de mensaje</label>'
          .'<input type="text" id="tpl-'.$kid.'" value="'.$tpl.'" placeholder="Usa {TITLE}, {COURSE}, {PERCENT}"></div>'
          .'<button class="btn btn-secondary btn-sm" id="btn-save-'.$kid.'" onclick="saveTemplate(\''.$key.'\')">💾 Guardar plantilla</button>'
          .'<div id="msg-'.$kid.'" style="font-size:.82rem;margin-top:.5rem;min-height:1.3em"></div>';

        // Botón extra para encuestas YOP Poll
        if ($key === 'new_poll') {
          echo '<div style="margin-top:.9rem;padding-top:.9rem;border-top:1px solid var(--border)">'
              .'<p style="font-size:.82rem;color:var(--ts);margin-bottom:.6rem">📋 <strong>Notificar encuesta existente:</strong></p>'
              .'<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">'
              .'<select id="yop-poll-select" style="flex:1;min-width:160px;padding:.42rem .75rem;border:1.5px solid var(--border);border-radius:8px;font-size:.83rem;font-family:inherit">'
              .'<option value="">Cargando encuestas...</option>'
              .'</select>'
              .'<button class="btn btn-primary btn-sm" onclick="notifyYopPoll()">📤 Notificar ahora</button>'
              .'<button class="btn btn-secondary btn-sm" onclick="notifyAllNewPolls()">🔔 Notificar todas las nuevas</button>'
              .'</div>'
              .'<div id="msg-yop-poll" style="font-size:.82rem;margin-top:.5rem;min-height:1.3em"></div>'
              .'</div>';
        }

        // Botón de test por evento
        $pslabel = $key === 'cert_issued' || $key === 'course_complete' || $key === 'progress'
            ? 'Tu cuenta admin' : 'Todos los usuarios';
        echo '<button class="btn btn-secondary btn-sm" onclick="testEvent(\''.$key.'\')" style="margin-top:.5rem;font-size:.78rem;background:rgba(196,77,138,.07);color:var(--vk-plum);border:1px solid rgba(196,77,138,.2)">&#x1F9EA; Probar (' . $pslabel . ')</button>';

        echo '</div>';
      }
      ?>

    </div>

    <!-- Panel de diagnóstico del sistema -->
    <div class="card" style="margin-top:1rem">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:.85rem">
        <h2 style="margin:0;padding:0;border:none">🔬 Diagnóstico del Sistema</h2>
        <button class="btn btn-secondary btn-sm" onclick="loadAutoStatus()">🔄 Verificar estado</button>
      </div>
      <div id="auto-status-body" style="font-size:.83rem;color:var(--tu)">
        Haz clic en "Verificar estado" para ver el diagnóstico completo.
      </div>
    </div>
  </div>

  <!-- PANEL: HISTORIAL -->
  <div class="panel" id="panel-history">
    <!-- Tabs internos: BD vs Push Log -->
    <div style="display:flex;gap:.5rem;margin-bottom:1rem">
      <button class="btn btn-primary btn-sm" id="htab-db" onclick="switchHistTab('db')">🗃 Notificaciones BD</button>
      <button class="btn btn-secondary btn-sm" id="htab-push" onclick="switchHistTab('push')">📡 Log Push</button>
    </div>

    <!-- SUB-PANEL: BD (vk_notifications) -->
    <div id="hpanel-db" class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
        <h2 style="margin:0;padding:0;border:none">🗃 Notificaciones en Base de Datos</h2>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
          <span id="hist-db-count" style="font-size:.8rem;color:var(--tu)"></span>
          <button class="btn btn-secondary btn-sm" onclick="loadHistoryDB()">🔄 Actualizar</button>
          <button class="btn btn-sm" id="btn-del-all-db" onclick="deleteAllNotifsAdmin()" style="background:#ffebee;color:#c62828;border:1px solid #ef9a9a">🗑 Eliminar todas</button>
        </div>
      </div>
      <div style="display:flex;gap:.5rem;margin-bottom:.85rem;flex-wrap:wrap">
        <input type="search" id="hist-db-search" placeholder="Buscar en título o mensaje..." 
          style="flex:1;min-width:160px;padding:.45rem .85rem;border:1.5px solid var(--border);border-radius:10px;font-size:.84rem;font-family:inherit;outline:none"
          oninput="debounceHistDB()" />
        <select id="hist-db-type" onchange="loadHistoryDB()"
          style="padding:.45rem .75rem;border:1.5px solid var(--border);border-radius:10px;font-size:.84rem;font-family:inherit;outline:none">
          <option value="">Todos los tipos</option>
          <option value="course">🎓 Cursos</option>
          <option value="product">🛒 Productos</option>
          <option value="poll">📊 Encuestas</option>
          <option value="cert">📜 Certificados</option>
          <option value="info">ℹ️ General</option>
          <option value="system">⚙️ Sistema</option>
        </select>
        <select id="hist-db-scope" onchange="loadHistoryDB()"
          style="padding:.45rem .75rem;border:1.5px solid var(--border);border-radius:10px;font-size:.84rem;font-family:inherit;outline:none">
          <option value="">Todos</option>
          <option value="global">🌍 Globales</option>
          <option value="personal">👤 Personales</option>
        </select>
      </div>
      <div id="history-db-body"><div style="text-align:center;padding:2rem;color:var(--tu)">Cargando...</div></div>
      <div id="hist-db-pagination" style="display:flex;justify-content:center;gap:.5rem;margin-top:1rem"></div>
    </div>

    <!-- SUB-PANEL: Push Log (vk_push_history option) -->
    <div id="hpanel-push" class="card" style="display:none">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
        <h2 style="margin:0;padding:0;border:none">📡 Log de Envíos Push</h2>
        <div style="display:flex;gap:.5rem;align-items:center">
          <button class="btn btn-secondary btn-sm" onclick="loadHistoryPush()">🔄 Actualizar</button>
          <button class="btn btn-sm" onclick="deleteAllPushHistory()" style="background:#ffebee;color:#c62828;border:1px solid #ef9a9a">🗑 Limpiar log</button>
        </div>
      </div>
      <div style="display:flex;gap:.5rem;margin-bottom:.85rem">
        <select id="hist-push-type" onchange="loadHistoryPush()"
          style="padding:.45rem .75rem;border:1.5px solid var(--border);border-radius:10px;font-size:.84rem;font-family:inherit;outline:none">
          <option value="">Todos los tipos</option>
          <option value="course">🎓 Cursos</option>
          <option value="product">🛒 Productos</option>
          <option value="poll">📊 Encuestas</option>
          <option value="cert">📜 Certificados</option>
          <option value="info">ℹ️ General</option>
          <option value="system">⚙️ Sistema</option>
        </select>
      </div>
      <div id="history-push-body"><div style="text-align:center;padding:2rem;color:var(--tu)">Cargando...</div></div>
    </div>
  </div>

  <!-- PANEL: SUSCRIPTORES -->
  <div class="panel" id="panel-users">
    <div class="card">
      <h2>👥 Usuarios Suscritos a Push</h2>
      <div style="margin-bottom:.85rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <input type="search" id="user-search" placeholder="Buscar por nombre o email..." style="flex:1;min-width:160px;padding:.5rem .85rem;border:1.5px solid var(--border);border-radius:10px;font-size:.85rem;font-family:inherit;outline:none;">
        <button class="btn btn-secondary btn-sm" onclick="loadUsers()">🔄 Actualizar</button>
        <button class="btn btn-sm" onclick="cleanInvalidIds()" id="btn-clean-ids" style="background:#fff3e0;color:#e65100;border:1px solid #ffcc80" title="Eliminar de la BD los player IDs que OneSignal rechazó">🧹 Limpiar IDs inválidos</button>
        <button class="btn btn-sm" onclick="resetSubscribers()" style="background:#fce4ec;color:#c62828;border:1px solid #ef9a9a" title="Elimina TODOS los IDs y fuerza re-registro">&#x1F504; Reset completo suscriptores</button>
        <button class="btn btn-sm" onclick="showActivateModal()" style="background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7" title="Activar manualmente una cuenta pendiente">&#x2705; Activar cuenta</button>
        <span id="clean-ids-msg" style="font-size:.8rem;align-self:center"></span>
      </div>
      <div id="users-body"><div style="text-align:center;padding:2rem;color:var(--tu)">Cargando...</div></div>
    </div>
  </div>

  <!-- PANEL: CERTIFICADOS -->
  <div class="panel" id="panel-certs">

    <!-- Redirect card al editor modular -->
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:420px;text-align:center;padding:2rem;gap:1.25rem">
      <div style="font-size:4rem;line-height:1">🏆</div>
      <h2 style="font-family:'DM Sans',sans-serif;color:var(--vk-plum);font-size:1.8rem;margin:0">Editor de Certificados</h2>
      <p style="color:var(--ts);max-width:420px;line-height:1.55;font-size:.94rem">
        El editor de certificados se ha movido a su propio módulo para una mejor experiencia. Diseña plantillas, personaliza el estilo y gestiona la asignación de certificados por curso.
      </p>
      <a href="editor.php" style="display:inline-flex;align-items:center;gap:.5rem;background:var(--grad-accent);color:#fff;text-decoration:none;padding:.85rem 2rem;border-radius:12px;font-weight:700;font-size:.97rem;box-shadow:0 4px 16px rgba(196,77,138,.3);transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">✏️ Abrir Editor de Certificados</a>
      <p style="color:var(--tu);font-size:.78rem">Se abrirá en la misma ventana. Usa el botón "← Panel Admin" para regresar.</p>
    </div>

    <!-- Sección fantasma para compatibilidad con loadCertCourseAssignments() -->
    <div id="tpl-manager-section" style="display:none"></div>

    <!-- fin panel-certs -->
    <div style="display:none">
      <div class="vk-cert-bar-left">
        <button class="btn btn-primary" id="vk-save-cert-btn" onclick="vkSaveCertConfig()">💾 Guardar diseño</button>
        <button class="btn btn-secondary" onclick="vkResetCertConfig()">↩ Defaults</button>
        <button class="btn btn-outline vk-cache-btn" onclick="vkClearCertCache()">🗑 Cache</button>
        <button class="btn btn-outline" onclick="vkSanitizeCertBg()" title="Detecta y elimina fondos que sean certificados renderizados con demo data">🧹 Fondos</button>
        <button class="btn btn-outline" onclick="vkSetDefaultBg()" title="Establece vidakushala-cert.png como fondo predeterminado en todos los certificados sin fondo personalizado">🖼 Default</button>
        <button class="btn btn-outline" onclick="vkDownloadPreview()">⬇ PNG</button>
      </div>
      <div id="vk-cert-msg" class="vk-cert-msg"></div>
    </div>


  </div><!-- /panel-certs -->

  <!-- PANEL: AI CHAT PREMIUM -->
  <div class="panel" id="panel-aichat">

    <!-- Stats rápidas -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.75rem;margin-bottom:1rem">
      <div class="card" style="margin:0;padding:1rem;text-align:center">
        <div id="ac-stat-total" style="font-size:1.9rem;font-weight:700;color:var(--vk-plum);font-family:'DM Sans',sans-serif">—</div>
        <div style="font-size:.71rem;color:var(--tu);text-transform:uppercase;letter-spacing:.05em;margin-top:.2rem">Usuarios</div>
      </div>
      <div class="card" style="margin:0;padding:1rem;text-align:center">
        <div id="ac-stat-active" style="font-size:1.9rem;font-weight:700;color:#2e7d32;font-family:'DM Sans',sans-serif">—</div>
        <div style="font-size:.71rem;color:var(--tu);text-transform:uppercase;letter-spacing:.05em;margin-top:.2rem">Con acceso</div>
      </div>
      <div class="card" style="margin:0;padding:1rem;text-align:center">
        <div id="ac-stat-inactive" style="font-size:1.9rem;font-weight:700;color:#c62828;font-family:'DM Sans',sans-serif">—</div>
        <div style="font-size:.71rem;color:var(--tu);text-transform:uppercase;letter-spacing:.05em;margin-top:.2rem">Sin acceso</div>
      </div>
      <div class="card" style="margin:0;padding:1rem;text-align:center">
        <div id="ac-stat-status" style="font-size:1.2rem;font-weight:700">—</div>
        <div style="font-size:.71rem;color:var(--tu);text-transform:uppercase;letter-spacing:.05em;margin-top:.2rem">Producto</div>
      </div>
    </div>

    <!-- Configuración del producto -->
    <div class="card">
      <h2>💬 Configuración del AI Chat</h2>
      <div class="grid-2">
        <div>
          <div class="field"><label>Nombre del producto</label><input type="text" id="acp-name" placeholder="AI Chat Premium"></div>
          <div class="field"><label>Descripción</label><textarea id="acp-desc" rows="2" placeholder="Accede al asistente de IA personal..." style="resize:vertical"></textarea></div>
          <div class="field"><label>Precio ($/un pago)</label><input type="text" id="acp-price" placeholder="9.99" style="max-width:160px"></div>
        </div>
        <div>
          <div class="field"><label>Enlace de pago externo</label><input type="url" id="acp-url" placeholder="https://"></div>
          <div class="field"><label>Enlace de contacto / WhatsApp <span style="font-weight:400;color:var(--tu)">(para usuarios sin acceso)</span></label><input type="url" id="acp-contact-url" placeholder="https://wa.me/..."></div>
          <div class="field"><label>URL imagen del producto</label>
            <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.4rem">
              <input type="url" id="acp-image" placeholder="https://"
                oninput="acpImgPreview(this.value)"
                onchange="acpImgPreview(this.value)"
                onpaste="setTimeout(function(){acpImgPreview(document.getElementById('acp-image').value);},60)"
                style="flex:1">
              <img id="acp-img-tag" src="" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:10px;border:1.5px solid var(--border);display:none;flex-shrink:0">
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
              <button type="button" class="btn btn-secondary btn-sm" onclick="acpOpenMediaLibrary()" style="font-size:.77rem">🖼️ Seleccionar de WordPress Media</button>
              <span id="acp-img-msg" style="font-size:.77rem;color:var(--tu);min-height:1em"></span>
            </div>
          </div>
          <div class="field"><label>Estado del servicio</label>
            <select id="acp-status" onchange="acpStatusPreview(this.value)">
              <option value="active">✅ Activo — usuarios pueden acceder</option>
              <option value="inactive">🔴 Inactivo — acceso bloqueado para todos</option>
            </select>
          </div>
        </div>
      </div>
      <div style="display:flex;gap:.65rem;align-items:center;flex-wrap:wrap;margin-top:.75rem">
        <button class="btn btn-primary" onclick="saveAiChatProduct()">💾 Guardar configuración</button>
        <div id="acp-msg" style="flex:1"></div>
      </div>
    </div>

    <!-- Agente IA (MWAI) -->
    <div class="card">
      <h2>🤖 Agente IA (MWAI / AI Engine)</h2>
      <p style="font-size:.84rem;color:var(--ts);margin-bottom:1rem">Shortcode del chatbot que los usuarios verán. Debe existir en <strong>AI Engine → Chatbots</strong>.</p>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="flex:1;margin:0;min-width:220px">
          <label>Shortcode</label>
          <input type="text" id="acp-shortcode" placeholder="[mwai_chatbot id=&quot;default&quot;]">
        </div>
        <div class="field" style="margin:0;min-width:160px">
          <label>Nombre visible del agente</label>
          <input type="text" id="acp-agent-name" placeholder="Asistente VidaKushala">
        </div>
        <button class="btn btn-secondary" onclick="saveAiChatAgent()" style="align-self:flex-end">💾 Guardar</button>
      </div>
      <div id="acp-agent-msg" style="margin-top:.5rem;font-size:.83rem;min-height:1.2em"></div>
      <div style="margin-top:.85rem;padding:.7rem 1rem;background:#f9f3f7;border-radius:10px;border:1px solid var(--border)">
        <p style="font-size:.79rem;color:var(--ts)">💡 <strong>¿Cómo obtener el shortcode?</strong> WordPress → AI Engine → Chatbots → tu bot → copiar shortcode. Ej: <code style="background:var(--vk-petal);padding:.1rem .4rem;border-radius:6px;font-size:.77rem">[mwai_chatbot id="default"]</code></p>
      </div>
    </div>

    <!-- Gestión de accesos -->
    <div class="card">
      <h2>👥 Gestión de Accesos</h2>

      <!-- Dar acceso por email -->
      <div style="background:var(--vk-petal);border-radius:12px;padding:1rem 1.1rem;border:1px solid rgba(196,77,138,.15);margin-bottom:1.25rem">
        <h3 style="font-size:.88rem;font-weight:700;color:var(--vk-plum);margin-bottom:.75rem">➕ Dar acceso a un usuario</h3>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
          <div class="field" style="flex:1;margin:0;min-width:220px;position:relative">
            <label>Buscar usuario (nombre o email)</label>
            <input type="text" id="acp-grant-email" placeholder="Escribe nombre o email..."
              oninput="acGrantDebounce()"
              onkeydown="if(event.key==='Enter'){event.preventDefault();grantAiChatByEmail();}"
              autocomplete="off">
            <div id="acp-grant-drop" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:200;background:#fff;border:1.5px solid var(--border);border-radius:10px;box-shadow:0 6px 24px rgba(80,0,50,.13);max-height:250px;overflow-y:auto;margin-top:.2rem"></div>
          </div>
          <div class="field" style="margin:0">
            <label>Vence el <span style="font-weight:400;color:var(--tu)">(opcional)</span></label>
            <input type="date" id="acp-grant-expiry"
              style="padding:.7rem .95rem;border:1.5px solid var(--border);border-radius:12px;font-family:inherit;font-size:.9rem;outline:none;color:var(--td)">
          </div>
          <button class="btn btn-primary" onclick="grantAiChatByEmail()" style="align-self:flex-end">➕ Dar acceso</button>
        </div>
        <div id="acp-grant-preview" style="display:none;margin-top:.5rem;padding:.45rem .75rem;background:#fff;border-radius:8px;border:1px solid var(--border);font-size:.82rem;align-items:center;gap:.5rem;flex-wrap:wrap"></div>
        <div id="acp-grant-msg" style="margin-top:.5rem;font-size:.83rem;min-height:1.3em"></div>
      </div>

      <!-- Barra de búsqueda y filtros -->
      <div class="ac-search-row" style="margin-bottom:.85rem">
        <input type="search" id="ac-search" placeholder="🔍 Nombre o email…" oninput="acSearchDebounce()">
        <select id="ac-filter" onchange="filterAcTable()">
          <option value="">Todos</option>
          <option value="active">✅ Con acceso</option>
          <option value="inactive">🔴 Sin acceso</option>
        </select>
        <button class="btn btn-secondary btn-sm" onclick="loadAiChatUsers()" title="Recargar">🔄</button>
      </div>

      <div id="aichat-users-body">
        <div style="text-align:center;padding:2rem;color:var(--tu)">Cargando…</div>
      </div>
    </div>
  </div>

  <!-- PANEL: CONFIGURACIÓN -->
  <div class="panel" id="panel-config">
    <div class="card" style="border:2px solid rgba(196,77,138,.25)">
      <h2>⚙️ Configuración OneSignal</h2>
      <div class="field">
        <label>App ID (solo lectura)</label>
        <input type="text" value="5ed3833a-c6c4-4b09-9f3c-3d7778e334b4" readonly style="background:#f5f5f5;color:var(--tu);font-family:monospace;font-size:.85rem;">
      </div>
      <div class="field">
        <label style="font-weight:700;color:var(--vk-plum)">REST API Key <span style="color:#c62828">*</span>
          &nbsp;<a href="https://app.onesignal.com" target="_blank" style="font-size:.75rem;font-weight:400">(obtener en OneSignal → Keys &amp; IDs)</a>
        </label>
        <div style="display:flex;gap:.5rem;align-items:center">
          <input type="text" id="cfg-key" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" style="flex:1;font-family:monospace;font-size:.84rem;letter-spacing:.03em">
          <button class="btn btn-primary" onclick="saveKey()" style="flex-shrink:0;white-space:nowrap">💾 Guardar key</button>
        </div>
        <div id="cfg-msg" style="margin-top:.35rem"></div>
        <div class="msg msg-info" style="margin-top:.65rem"><span>📋</span>
          <div><strong>Pasos:</strong>
            <ol style="margin:.35rem 0 0 1rem;padding:0;font-size:.84rem;line-height:1.7">
              <li>Ve a <a href="https://app.onesignal.com" target="_blank" style="color:var(--vk-plum)">app.onesignal.com</a></li>
              <li>Selecciona tu app → <strong>Settings → Keys &amp; IDs</strong></li>
              <li>Copia la <strong>REST API Key</strong> (no el App ID)</li>
              <li>Pégala arriba y haz clic en <strong>Guardar key</strong></li>
            </ol>
          </div>
        </div>
      </div>
    </div>
    <div class="card">
      <h2>📱 Estado del Service Worker</h2>
      <div id="sw-info"><div style="color:var(--tu);font-size:.85rem">Verificando...</div></div>
      <button class="btn btn-secondary btn-sm" onclick="checkSW()" style="margin-top:.75rem">🔍 Verificar SW</button>
    </div>
  </div>

</div><!-- /container -->

<?php
$vk_plugin_url = '';
if (function_exists('plugin_dir_url')) {
    $vk_plugin_url = plugin_dir_url(__FILE__);
} else {
    $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir   = str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($dir,'/wp-content/plugins/vk-cors') !== false)
        $vk_plugin_url = $proto.'://'.$host.substr($dir,0,strpos($dir,'/wp-content/plugins/vk-cors')+27).'/';
    else
        $vk_plugin_url = $proto.'://'.$host.rtrim($dir,'/').'/wp-content/plugins/vk-cors/';
}
?>
<script>
var VK_PLUGIN_URL = '<?php echo $vk_plugin_url; ?>';
var API = (window.location.hostname==='localhost'||window.location.hostname==='127.0.0.1')
        ? 'http://localhost:8080/wp-json/vk/v1'
        : 'https://vidakushala.com/wp-json/vk/v1';
var TOK='', AUTO_CONFIG={};
var NOTIF_ICONS={course:'🎓',product:'🛒',poll:'📊',cert:'🏆',progress:'📈',info:'ℹ️',system:'⚙️',course_done:'✅',bundle:'📦'};
var NH_COLORS={course:'#1a2e5a',product:'#e65100',poll:'#1565c0',cert:'#b36b00',info:'#6f102a',system:'#546e7a',bundle:'#6a1b9a',progress:'#00695c',course_done:'#2e7d32'};

/* Limpia texto con mojibake (UTF-8 mal interpretado como latin-1) */
function cleanMojibake(str) {
  if (!str) return str;
  // Si no contiene caracteres raros, devolver tal cual
  if (!/[À-Ã][-¿]/.test(str)) return str;
  // Intentar decodificar: interpretar la cadena como latin-1 y re-decodificar como UTF-8
  try {
    // Convertir el string JS (UTF-16) a bytes interpretados como latin-1
    var bytes = new Uint8Array(str.length);
    for (var i = 0; i < str.length; i++) bytes[i] = str.charCodeAt(i) & 0xFF;
    var decoded = new TextDecoder('utf-8', {fatal: false}).decode(bytes);
    // Solo usar si el resultado es legible (menos caracteres raros)
    var mojibakeCount = (str.match(/[À-Ã][-¿]/g) || []).length;
    var newMojibakeCount = (decoded.match(/[À-Ã][-¿]/g) || []).length;
    return newMojibakeCount < mojibakeCount ? decoded : str;
  } catch(e) { return str; }
}
var AC_ALL_USERS=[]; // cache for AI Chat user list

/* ── AUTH ────────────────────────────────────────────── */
function switchLoginTab(t){
  var p=t==='pass';
  document.getElementById('lpanel-pass').style.display=p?'block':'none';
  document.getElementById('lpanel-tok').style.display=p?'none':'block';
  document.getElementById('ltab-pass').style.background=p?'var(--grad-accent)':'white';
  document.getElementById('ltab-pass').style.color=p?'white':'var(--ts)';
  document.getElementById('ltab-tok').style.background=p?'white':'var(--grad-accent)';
  document.getElementById('ltab-tok').style.color=p?'var(--ts)':'white';
  document.getElementById('login-err').textContent='';
}
function togglePass(){var i=document.getElementById('login-pass');i.type=i.type==='password'?'text':'password';}
async function doLoginPass(){
  var user=document.getElementById('login-user').value.trim();
  var pass=document.getElementById('login-pass').value;
  var err=document.getElementById('login-err');
  if(!user||!pass){err.textContent='Ingresa usuario y contraseña';return;}
  err.textContent='⏳ Verificando...';
  try{
    var r=await fetch(API+'/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:user,password:pass})});
    var d=await r.json();
    if(r.ok&&d.token){TOK=d.token;await verifyAdmin(err);}
    else err.textContent='✗ '+(d.message||'Usuario o contraseña incorrectos');
  }catch(e){err.textContent='✗ Error de conexión: '+e.message;}
}
function doLoginTok(){
  var raw=document.getElementById('login-tok').value.trim();
  try{var p=JSON.parse(raw);if(typeof p==='string')raw=p;}catch(e){}
  var err=document.getElementById('login-err');
  if(!raw){err.textContent='Ingresa tu token';return;}
  TOK=raw;err.textContent='⏳ Verificando...';verifyAdmin(err);
}
async function verifyAdmin(errEl){
  try{
    var r=await fetch(API+'/check-admin?vk_token='+encodeURIComponent(TOK));
    var d=await r.json();
    if(r.ok&&d.is_admin){
      try{localStorage.setItem('vk_tok',TOK);}catch(e){}
      document.getElementById('admin-name').textContent=d.display_name||'Admin';
      document.getElementById('login-screen').style.display='none';
      loadAll();
    }else{
      if(errEl)errEl.textContent='✗ '+(d.message||'Solo administradores pueden acceder');
      TOK='';
    }
  }catch(e){if(errEl)errEl.textContent='✗ Error de conexión: '+e.message;TOK='';}
}
function logout(){TOK='';try{localStorage.removeItem('vk_tok');}catch(e){}location.reload();}
window.addEventListener('load',function(){
  var raw=localStorage.getItem('vk_tok');
  try{var p=JSON.parse(raw);if(typeof p==='string')raw=p;}catch(e){}
  if(raw&&typeof raw==='string'&&raw.length>10){
    document.getElementById('login-tok').value=raw;
    TOK=raw;verifyAdmin(document.getElementById('login-err'));
  }
});

/* ── TABS ────────────────────────────────────────────── */
function showTab(name){
  document.querySelectorAll('.tab').forEach(function(t){
    var fn=t.getAttribute('onclick')||'';
    t.classList.toggle('on',fn.indexOf("'"+name+"'")!==-1);
  });
  document.querySelectorAll('.panel').forEach(function(p){p.classList.remove('on');});
  var panel=document.getElementById('panel-'+name);
  if(panel)panel.classList.add('on');
  if(name==='history'){switchHistTab(_histTab);}  // uses new dual-panel history
  if(name==='users')loadUsers();
  if(name==='config')checkSW();
  if(name==='auto'){ loadAutoConfig(); loadAutoStatus(); }
  if(name==='live'){ setLiveTarget('all'); selectPlatform('Zoom'); }
  if(name==='aichat')loadAiChatPanel();
  if(name==='certs'){ /* editor modular en editor.php */ }
}

/* ── DOM READY LISTENERS ────────────────────────────── */
document.addEventListener('DOMContentLoaded',function(){
  var nt=document.getElementById('n-target');
  if(nt)nt.addEventListener('change',function(){
    document.getElementById('f-segment').style.display=this.value==='segment'?'':'none';
    document.getElementById('f-user-email').style.display=this.value==='user'?'':'none';
  });
  var TICONS={course:'📚',product:'🛍️',poll:'📊',cert:'🏆',progress:'🎯',info:'💬',system:'🔔'};
  var ti=document.getElementById('n-title');
  var mi=document.getElementById('n-message');
  var ty=document.getElementById('n-type');
  if(ti)ti.addEventListener('input',function(){document.getElementById('prev-title').textContent=this.value||'VidaKushala';});
  if(mi)mi.addEventListener('input',function(){document.getElementById('prev-msg').textContent=this.value||'Tu mensaje aquí...';});
  if(ty)ty.addEventListener('change',function(){document.getElementById('prev-icon').textContent=TICONS[this.value]||'🔔';});
  var us=document.getElementById('user-search');
  if(us)us.addEventListener('input',loadUsers);
});

/* ── LOAD ALL ────────────────────────────────────────── */
async function loadAll(){loadStats();loadAutoConfig();}
async function loadStats(){
  try{
    var r=await fetch(API+'/push-stats?vk_token='+encodeURIComponent(TOK));
    if(!r.ok)return;
    var d=await r.json();
    document.getElementById('s-subs').textContent=d.total_subscribers??'?';
    document.getElementById('s-devices').textContent=d.total_devices??'?';
    document.getElementById('s-sent').textContent=d.total_sent??'?';
    document.getElementById('s-key').textContent=d.has_api_key?'✓ OK':'✗ Falta';

    // Mostrar aviso urgente si falta la API key
    var warn = document.getElementById('apikey-warning');
    if(!warn){
      warn = document.createElement('div');
      warn.id = 'apikey-warning';
      warn.style.cssText='background:#fff3cd;border:2px solid #ffc107;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;';
      var container = document.querySelector('.container') || document.querySelector('.panel.active') || document.body;
      container.insertBefore(warn, container.firstChild);
    }
    if(!d.has_api_key){
      warn.style.display='flex';
      warn.innerHTML='<span style="font-size:1.5rem">⚠️</span>'
        +'<div style="flex:1"><strong style="color:#856404">Falta la REST API Key de OneSignal</strong>'
        +'<p style="margin:.25rem 0 0;font-size:.85rem;color:#856404">Sin ella no se pueden enviar notificaciones. '
        +'<a href="#" onclick="showTab(\'config\');return false;" style="color:#856404;font-weight:700;text-decoration:underline">Ir a ⚙️ Configuración → pegar la key → Guardar</a></p></div>'
        +'<button onclick="showTab(\'config\')" style="background:#ffc107;border:none;border-radius:8px;padding:.5rem 1rem;font-weight:700;cursor:pointer;font-size:.85rem;white-space:nowrap">⚙️ Configurar ahora</button>';
    } else {
      warn.style.display='none';
    }
  }catch(e){}
}

/* ── ENVIAR ──────────────────────────────────────────── */
async function sendNotification(){
  var title=document.getElementById('n-title').value.trim();
  var msg=document.getElementById('n-message').value.trim();
  var target=document.getElementById('n-target').value;
  var url=document.getElementById('n-url').value.trim()||'https://app.vidakushala.com/';
  var type=document.getElementById('n-type').value;
  if(!msg){showMsg('send-msg','Escribe un mensaje primero','err');return;}
  var btn=document.getElementById('btn-send');
  btn.disabled=true;btn.innerHTML='<span class="loader"></span>Enviando...';
  var body={vk_token:TOK,title:title||'VidaKushala',message:msg,url:url,target:'all',type:type};
  if(target==='segment')body.segment=document.getElementById('n-segment').value;
  else if(target==='user'){
    var email=document.getElementById('n-user-email').value.trim();
    if(!email){showMsg('send-msg','Ingresa un email','err');btn.disabled=false;btn.innerHTML='📤 Enviar ahora';return;}
    body.target='user';body.user_email=email;
  }
  try{
    var r=await fetch(API+'/send-push',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    var d=await r.json();
    if(r.ok&&d.success){
      var recipients2 = d.response&&d.response.recipients ? ' ('+d.response.recipients+' destinatarios)' : '';
      var cleaned2 = d.response&&d.response._cleaned_ids ? ' — se limpiaron '+d.response._cleaned_ids+' IDs obsoletos' : '';
      showMsg('send-msg','\u2705 Enviada'+recipients2+cleaned2,'ok');
      document.getElementById('n-message').value='';loadStats();
    } else {
      // Detectar invalid_player_ids — limpiar y reintentar automáticamente
      var invalidIds = [];
      if (d.response && d.response.errors && d.response.errors.invalid_player_ids) {
        invalidIds = Array.isArray(d.response.errors.invalid_player_ids)
          ? d.response.errors.invalid_player_ids : [d.response.errors.invalid_player_ids];
      } else if (d.message && d.message.indexOf('invalid_player_ids') !== -1) {
        var parts = d.message.split(':');
        if (parts.length > 1) {
          invalidIds = parts.slice(1).join(':').split(',').map(function(s){ return s.trim(); }).filter(Boolean);
        }
      }

      if (invalidIds.length > 0) {
        showMsg('send-msg','\u{1F9F9} Limpiando '+invalidIds.length+' IDs inválidos y reintentando...','info');
        try {
          await fetch(API+'/push-clean-ids?vk_token='+encodeURIComponent(TOK),{
            method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({ids: invalidIds})
          });
        } catch(ce){}
        setTimeout(async function(){
          try{
            var r2=await fetch(API+'/send-push',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
            var d2=await r2.json();
            if(r2.ok&&d2.success){
              var rec = d2.response&&d2.response.recipients ? ' ('+d2.response.recipients+' destinatarios)' : '';
              showMsg('send-msg','\u2705 Enviada'+rec+' — '+invalidIds.length+' IDs obsoletos limpiados','ok');
              document.getElementById('n-message').value='';loadStats();
            } else {
              showMsg('send-msg','\u2717 '+(d2.message||'Error al reenviar. Verifica la REST API Key.'),'err');
            }
          }catch(re){ showMsg('send-msg','\u2717 Error: '+re.message,'err'); }
          btn.disabled=false;btn.innerHTML='\u{1F4E4} Enviar ahora';
        }, 1000);
        return;
      }

      // Error distinto
      var errMsg = d.message || '';
      if (!errMsg && d.response && d.response.errors) {
        errMsg = typeof d.response.errors === 'string'
          ? d.response.errors
          : JSON.stringify(d.response.errors);
      }
      if (!errMsg) errMsg = 'HTTP '+r.status+' — ve a \u2699\uFE0F Configuraci\u00F3n y verifica la REST API Key';
      showMsg('send-msg','\u2717 '+errMsg,'err');
    }
  }catch(e){showMsg('send-msg','\u2717 Error de conexi\u00F3n: '+e.message,'err');}
  btn.disabled=false;btn.innerHTML='\u{1F4E4} Enviar ahora';
}
async function sendTest(){
  showMsg('send-msg','Enviando prueba...','info');
  var testBody = {vk_token:TOK,title:'\u{1F9EA} Test VidaKushala',message:'Notificaci\u00f3n de prueba desde el panel admin.',target:'self'};
  try{
    var r=await fetch(API+'/send-push',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify(testBody)});
    var d=await r.json();
    if(r.ok&&d.success){
      showMsg('send-msg','\u2705 Prueba enviada exitosamente','ok');
    } else {
      // Auto-limpiar IDs inválidos y reintentar
      var invalidIds = [];
      if (d.response && d.response.errors && d.response.errors.invalid_player_ids) {
        invalidIds = Array.isArray(d.response.errors.invalid_player_ids)
          ? d.response.errors.invalid_player_ids : [d.response.errors.invalid_player_ids];
      } else if (d.message && d.message.indexOf('invalid_player_ids') !== -1) {
        var parts = d.message.split(':');
        if (parts.length > 1) invalidIds = parts.slice(1).join(':').split(',').map(function(s){return s.trim();}).filter(Boolean);
      }
      if (invalidIds.length > 0) {
        showMsg('send-msg','\u{1F9F9} Limpiando IDs obsoletos...','info');
        try{
          await fetch(API+'/push-clean-ids?vk_token='+encodeURIComponent(TOK),{
            method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({ids:invalidIds})
          });
        }catch(ce){}
        showMsg('send-msg','\u{1F4E2} IDs limpios. Ahora re-registra tu dispositivo: abre la app y acepta notificaciones.','info');
        return;
      }
      var errMsg = d.message||'';
      if(!errMsg&&d.response&&d.response.errors) errMsg=JSON.stringify(d.response.errors);
      if(!errMsg) errMsg='HTTP '+r.status+' \u2014 configura la REST API Key en \u2699\uFE0F Configuraci\u00F3n';
      showMsg('send-msg','\u2717 '+errMsg,'err');
    }
  }catch(e){showMsg('send-msg','\u2717 Error: '+e.message,'err');}
}

/* Limpiar IDs inválidos desde el panel — consulta a OneSignal cuáles no existen */
async function resetSubscribers() {
  if (!confirm('⚠️ Esto eliminará TODOS los player_ids de la BD.\n\nLos usuarios se re-registrarán automáticamente al abrir la app.\n\n¿Continuar?')) return;
  var msg = document.getElementById('clean-ids-msg');
  if (msg) msg.textContent = '⏳ Reseteando...';
  try {
    var r = await fetch(API + '/push-reset-subscribers', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({vk_token: TOK})
    });
    var d = await r.json();
    if (d.success) {
      if (msg) { msg.style.color = '#2e7d32'; msg.textContent = '✅ ' + d.message; }
      setTimeout(loadUsers, 1000);
    } else {
      if (msg) { msg.style.color = '#c62828'; msg.textContent = '✗ Error'; }
    }
  } catch(e) {
    if (msg) { msg.style.color = '#c62828'; msg.textContent = '✗ ' + e.message; }
  }
}

async function cleanInvalidIds() {
  var btn = document.getElementById('btn-clean-ids');
  var msg = document.getElementById('clean-ids-msg');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Verificando...'; }
  if (msg) { msg.style.color = 'var(--tu)'; msg.textContent = 'Consultando suscriptores...'; }

  try {
    // 1. Obtener todos los player_ids registrados en la BD
    var r = await fetch(API + '/push-subscribers?vk_token=' + encodeURIComponent(TOK));
    var d = await r.json();
    var subs = d.data || [];
    if (!subs.length) {
      if (msg) msg.textContent = 'ℹ️ No hay suscriptores registrados.';
      if (btn) { btn.disabled = false; btn.textContent = '🧹 Limpiar IDs inválidos'; }
      return;
    }

    // 2. Colectar todos los IDs
    var allIds = [];
    subs.forEach(function(s) {
      if (s.player_id) allIds.push(s.player_id);
      if (s.player_ids && Array.isArray(s.player_ids)) allIds = allIds.concat(s.player_ids);
    });
    allIds = [...new Set(allIds)]; // deduplicar

    if (!allIds.length) {
      if (msg) msg.textContent = 'ℹ️ No se encontraron IDs.';
      if (btn) { btn.disabled = false; btn.textContent = '🧹 Limpiar IDs inválidos'; }
      return;
    }

    if (msg) msg.textContent = 'Verificando ' + allIds.length + ' IDs con OneSignal...';

    // 3. Enviar notificación vacía a todos para que OneSignal reporte los inválidos
    //    Usamos una notificación de prueba al segmento + include_subscription_ids
    //    La respuesta de errors.invalid_player_ids nos da los IDs muertos
    var stats = await fetch(API + '/push-stats?vk_token=' + encodeURIComponent(TOK));
    var sd = await stats.json();
    var appId  = sd.app_id || '8b7378c4-ca41-494a-8c10-2627333b6f5c';
    var apiKey = sd.rest_api_key || '';

    // 4. Limpiar directamente vía el endpoint del plugin
    //    El endpoint ya limpia automáticamente cuando el envío falla con invalid_player_ids
    //    Aquí forzamos la limpieza con los IDs conocidos de la BD a través de
    //    un intento de envío de diagnóstico
    var clean = await fetch(API + '/send-push', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        vk_token: TOK,
        title: '.',
        message: '.',
        target: 'all',
        type: 'system',
        _dry_run: true  // flag para que el backend solo valide sin guardar en BD
      })
    });
    var cd = await clean.json();

    // El backend ya limpia automáticamente los IDs inválidos en la respuesta
    if (cd.cleaned_ids > 0 || (cd.response && cd.response.errors && cd.response.errors.invalid_player_ids)) {
      var count = cd.cleaned_ids || (cd.response.errors.invalid_player_ids || []).length;
      if (msg) { msg.style.color = '#2e7d32'; msg.textContent = '✅ Se limpiaron ' + count + ' IDs inválidos. Recarga la lista.'; }
      setTimeout(loadUsers, 1000);
    } else {
      if (msg) { msg.style.color = '#2e7d32'; msg.textContent = '✅ Todos los IDs son válidos (' + allIds.length + ' activos).'; }
    }

  } catch(e) {
    if (msg) { msg.style.color = '#c62828'; msg.textContent = '✗ Error: ' + e.message; }
  }
  if (btn) { btn.disabled = false; btn.textContent = '🧹 Limpiar IDs inválidos'; }
}


/* ── Activación manual de cuenta ── */
function showActivateModal() {
  var modal = document.getElementById('activate-modal');
  if (modal) { modal.style.display='flex'; document.getElementById('activate-email-input').value=''; document.getElementById('activate-msg').textContent=''; }
}

async function doActivateUser() {
  var email = (document.getElementById('activate-email-input')||{}).value || '';
  var msgEl = document.getElementById('activate-msg');
  email = email.trim();
  if (!email) { if(msgEl){msgEl.style.color='#c62828';msgEl.textContent='Ingresa el email';} return; }
  if(msgEl){msgEl.style.color='var(--tu)';msgEl.textContent='Activando...';}
  try {
    var r = await fetch(API+'/admin-activate-user',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({vk_token:TOK,email:email})});
    var d = await r.json();
    if(r.ok && d.success){
      if(msgEl){msgEl.style.color='#2e7d32';msgEl.textContent='&#x2705; '+d.message;}
      setTimeout(function(){ document.getElementById('activate-modal').style.display='none'; loadUsers(); }, 2000);
    } else {
      if(msgEl){msgEl.style.color='#c62828';msgEl.textContent='&#x2717; '+(d.message||'Error');}
    }
  } catch(e) {
    if(msgEl){msgEl.style.color='#c62828';msgEl.textContent='&#x2717; Error: '+e.message;}
  }
}

/* ── Test clon: envía exactamente igual que bienvenida ── */
async function sendCloneWelcome() {
  showMsg('send-msg', '&#x1F3AF; Enviando test clon-bienvenida...', 'info');
  try {
    var r = await fetch(API + '/push-clone-welcome', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({vk_token: TOK})
    });
    var d = await r.json();

    if (!r.ok) {
      showMsg('send-msg', '&#x2717; ' + (d.message || 'Error HTTP ' + r.status), 'err');
      return;
    }

    var results = d.results || [];
    var ok = results.filter(function(r){ return (r.recipients||0) > 0; }).length;
    var total = results.length;
    var lines = [];

    if (ok > 0) {
      lines.push('&#x2705; ' + ok + '/' + total + ' IDs recibieron la notificación');
      lines.push('Si llegó al dispositivo = el sistema funciona igual que bienvenida');
      lines.push('El problema anterior era el método de envío — ya está corregido');
    } else {
      lines.push('&#x26A0; 0/' + total + ' IDs recibieron la notificación');
      results.forEach(function(res) {
        lines.push('ID: ' + res.player_id.substring(0,8) + '... | HTTP: ' + res.http + ' | recipients: ' + (res.recipients||0));
        if (res.errors) lines.push('  Errores: ' + JSON.stringify(res.errors));
      });
      lines.push('');
      lines.push('Esto confirma que el problema NO es el código sino el dispositivo.');
      lines.push('El player_id en la BD no está activo en OneSignal.');
      lines.push('Solución: abre la app, acepta notificaciones de nuevo.');
    }

    var msg = lines.join(' | ');
    showMsg('send-msg', msg, ok > 0 ? 'ok' : 'err');
    console.log('[Clone Test]', results);
  } catch(e) {
    showMsg('send-msg', '&#x2717; Error: ' + e.message, 'err');
  }
}

/* ── Debug completo: muestra respuesta raw de OneSignal ── */
async function pushDebugFull() {
  var msgEl = document.getElementById('send-msg');
  if (msgEl) { msgEl.style.color='#1565c0'; msgEl.textContent='🧬 Enviando debug a OneSignal...'; }

  try {
    var r = await fetch(API+'/push-debug', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({vk_token: TOK})
    });
    var d = await r.json();
    var debug = d.debug || {};

    // Mostrar resultado detallado
    var lines = [];
    lines.push('=== DEBUG ONESIGNAL ===');
    lines.push('App ID: ' + (debug.app_id || 'N/A'));
    lines.push('API Key: ' + (debug.has_rest_key ? '✅ Configurada ('+debug.rest_key_prefix+')' : '✗ NO CONFIGURADA'));
    lines.push('Key length: ' + (debug.rest_key_length || 0) + ' chars');
    lines.push('Player IDs: ' + (debug.player_count || 0) + ' dispositivos');
    lines.push('IDs: ' + JSON.stringify(debug.player_ids_multi || []));
    lines.push('');
    lines.push('HTTP: ' + (debug.http_code || 'N/A'));

    // Mostrar resultado de test por segmento ALL
    if (debug.test_segment_all) {
      var ts = debug.test_segment_all;
      lines.push('');
      lines.push('=== TEST SEGMENTO ALL ===');
      lines.push('HTTP: ' + ts.http);
      lines.push('Destinatarios: ' + (ts.recipients||0));
      if ((ts.recipients||0) > 0) {
        lines.push('✅ FUNCIONA CON SEGMENTO ALL — deberías ver la notificación ahora');
        lines.push('   El sistema push está activo. El problema era el método de targeting.');
      } else {
        lines.push('⚠️ 0 destinatarios incluso con segmento All');
        lines.push('   → Verifica que haya suscriptores en el dashboard de OneSignal');
        lines.push('   → URL: https://app.onesignal.com → tu app → Subscriptions');
      }
      if (ts.response && ts.response.errors) {
        lines.push('Errores: ' + JSON.stringify(ts.response.errors));
      }
    }

    if (debug.onesignal_raw) {
      var raw = debug.onesignal_raw;
      if (raw.id) {
        lines.push('');
        lines.push('=== TEST POR SUBSCRIPTION_ID ===');
        lines.push('Notif ID: ' + raw.id);
        lines.push('Destinatarios: ' + (raw.recipients || 0));
        if ((raw.recipients||0) === 0) {
          lines.push('');
          lines.push('⚠️  PROBLEMA: 0 destinatarios');
          lines.push('   El ID ' + (debug.player_ids_multi||[])[0] + ' no existe en la app de OneSignal.');
          lines.push('   Causas posibles:');
          lines.push('   1. El Service Worker de OneSignal no se registró bien');
          lines.push('   2. El navegador bloqueó el SW en una sesión anterior');
          lines.push('   3. El permiso fue dado pero la suscripción no se completó');
          lines.push('');
          lines.push('   SOLUCIÓN:');
          lines.push('   1. Abre DevTools (F12) → Application → Service Workers');
          lines.push('   2. Haz clic en Unregister en TODOS los SWs que aparezcan');
          lines.push('   3. F12 → Application → Storage → Clear site data');
          lines.push('   4. Cierra DevTools → Ctrl+Shift+R (recarga forzada)');
          lines.push('   5. Inicia sesión en la app → acepta el permiso de notificaciones');
          lines.push('   6. Espera 10 segundos y vuelve a hacer Debug');
        }
      }
      if (raw.errors) {
        lines.push('✗ ERRORES: ' + JSON.stringify(raw.errors));
        if (raw.errors.invalid_player_ids) {
          lines.push('   IDs inválidos — usa el botón Limpiar IDs en pestaña Suscriptores');
        }
      }
    }
    // Estado del player en OneSignal
    if (debug.player_exists_in_onesignal || debug.player_raw) {
      var pc = debug.player_raw || debug.player_exists_in_onesignal || {};
      lines.push('');
      lines.push('=== ESTADO DEL DISPOSITIVO EN ONESIGNAL ===');
      if (pc.id) {
        var devTypes = ['iOS','Android','Amazon','WindowsPhone','Chrome','Safari','Firefox','WP8','Email','iOS_S','Others'];
        var devName  = pc.device_type !== undefined ? (devTypes[pc.device_type]||'Tipo:'+pc.device_type) : 'N/A';
        var lastDt   = pc.last_active ? new Date(pc.last_active*1000).toLocaleString() : 'N/A';
        lines.push('Player ID: ' + pc.id);
        lines.push('Dispositivo: ' + devName);
        lines.push('Último activo: ' + lastDt);
        lines.push('notification_types: ' + (pc.notification_types !== undefined ? pc.notification_types : 'undefined'));
        lines.push('opted_in: ' + (pc.opted_in !== undefined ? pc.opted_in : 'N/A'));
        lines.push('test_type: ' + (pc.test_type !== undefined ? pc.test_type : 'N/A'));
        lines.push('invalid_identifier: ' + (pc.invalid_identifier !== undefined ? pc.invalid_identifier : 'N/A'));
        // Datos raw completos para diagnóstico
        lines.push('');
        lines.push('Datos raw: ' + JSON.stringify(pc, null, 2).substring(0, 500));

        var isSubd = debug.is_subscribed || pc.notification_types === 1 || pc.opted_in === true;
        var isSafari = pc.device_type === 5 || devName === 'Safari';

        lines.push('');
        if (isSubd) {
          lines.push('✅ SUSCRITO — el dispositivo debería recibir notificaciones');
          lines.push('   Si no llegan, puede ser un problema de SW o de permiso del sistema');
          if (isSafari) {
            lines.push('');
            lines.push('   📱 SAFARI DETECTADO — verificar:');
            lines.push('   macOS: Preferencias del Sistema → Notificaciones → Safari → Permitir');
            lines.push('   iOS: Ajustes → Safari → Notificaciones → Permitir');
          }
        } else {
          lines.push('⚠️ NO SUSCRITO (notification_types=' + pc.notification_types + ')');
          if (isSafari) {
            lines.push('   Safari requiere permiso del SISTEMA OPERATIVO además del navegador:');
            lines.push('   macOS: Preferencias del Sistema → Notificaciones → Safari → Activar');
            lines.push('   macOS Sonoma+: Configuración del sistema → Notificaciones → App.vidakushala');
          } else {
            lines.push('   Solución: Ir a chrome://settings/content/notifications → PERMITIR app.vidakushala.com');
          }
          lines.push('');
          lines.push('   O hacer un reset completo:');
          lines.push('   F12 → Application → Service Workers → Unregister TODOS');
          lines.push('   F12 → Application → Storage → Clear site data');
          lines.push('   Ctrl+Shift+R → volver a aceptar notificaciones');
        }
      } else if (pc.errors) {
        lines.push('✗ NO ENCONTRADO: ' + JSON.stringify(pc.errors));
        lines.push('   El ID no existe en la app 5ed3833a de OneSignal');
        lines.push('   → Hacer reset completo del Service Worker (ver pasos arriba)');
      }
    }

    if (debug.wp_error) lines.push('WP Error: ' + debug.wp_error);
    if (debug.error)    lines.push('Error: ' + debug.error);

    var result = lines.join('\n');
    console.log(result);

    // Mostrar en modal
    var existing = document.getElementById('debug-modal');
    if (existing) existing.remove();
    var modal = document.createElement('div');
    modal.id = 'debug-modal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem';
    modal.innerHTML = [
      '<div style="background:#fff;border-radius:16px;padding:1.5rem;max-width:600px;width:100%;max-height:80vh;overflow-y:auto">',
      '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">',
      '<h3 style="margin:0;font-size:1rem">&#x1F9EC; Debug OneSignal</h3>',
      '<button onclick="document.getElementById(' + "'" + 'debug-modal' + "'" + ').remove()" style="background:none;border:none;font-size:1.3rem;cursor:pointer">&times;</button>',
      '</div>',
      '<pre style="white-space:pre-wrap;word-break:break-all;background:#f5f5f5;padding:.75rem;border-radius:8px;margin:0">' + esc(result) + '</pre>',
      '<div style="margin-top:1rem;font-size:.82rem;color:#666">',
      (debug.success && debug.onesignal_raw && (debug.onesignal_raw.recipients||0) > 0
        ? '<span style="color:#2e7d32;font-weight:600">&#x2705; Sistema OK. Deberias ver la notificacion ahora.</span>'
        : '<span style="color:#c62828;font-weight:600">&#x26A0; Hay un problema. Lee los detalles.</span>'),
      '</div></div>'
    ].join('');
    modal.addEventListener('click', function(e){ if(e.target===modal) modal.remove(); });

    if (msgEl) { msgEl.style.color='#1565c0'; msgEl.textContent='🧬 Debug completado — revisa el popup'; }

  } catch(e) {
    if (msgEl) { msgEl.style.color='#c62828'; msgEl.textContent='✗ Error: '+e.message; }
    console.error('[pushDebugFull]', e);
  }
}


async function diagnosePush() {
  showMsg('send-msg', '🔍 Verificando configuración...', 'info');
  var lines = [];
  try {
    // 1. Verificar stats (incluye estado de API key)
    var r = await fetch(API + '/push-stats?vk_token=' + encodeURIComponent(TOK));
    var d = await r.json();
    if (!r.ok) {
      lines.push('✗ Error al contactar el servidor: HTTP ' + r.status);
    } else {
      lines.push('✅ Servidor WordPress: OK');
      var appId  = d.app_id || '';
      var hasKey = d.has_api_key || false;
      var subs   = d.total_subscribers || 0;
      lines.push(appId ? '✅ App ID: ' + appId : '⚠️ App ID no configurado');
      lines.push(hasKey ? '✅ REST API Key: Configurada' : '✗ REST API Key: FALTA — ve a ⚙️ Configuración → OneSignal REST API Key');
      lines.push('👥 Suscriptores: ' + subs + ' | 📲 Dispositivos: ' + (d.total_devices || 0));
      if (!hasKey) lines.push('ℹ️ Sin REST API Key no se pueden enviar notificaciones');
    }
    // 2. Verificar si el usuario actual tiene player_id
    var r2 = await fetch(API + '/push-subscribers?vk_token=' + encodeURIComponent(TOK));
    var d2 = await r2.json();
    var mySub = (d2.data || []).find(function(s){ return s.is_current_user; });
    lines.push(mySub ? '✅ Tu dispositivo: registrado para push' : 'ℹ️ Tu dispositivo: no registrado (normal en panel admin)');
  } catch(e) {
    lines.push('✗ Error de conexión: ' + e.message);
  }
  showMsg('send-msg', lines.join(' | '), lines.some(function(l){return l.startsWith('✗');}) ? 'err' : 'ok');
}

/* ── AUTOMATIZACIONES ────────────────────────────────── */
async function loadAutoConfig(){
  try{
    var r=await fetch(API+'/push-auto-config?vk_token='+encodeURIComponent(TOK));
    var d=await r.json();AUTO_CONFIG=d.data||{};
    var allEnabled = true;
    Object.keys(AUTO_CONFIG).forEach(function(k){
      var cfg=AUTO_CONFIG[k];
      var cb=document.getElementById('auto-'+k.replace(/_/g,'-'));
      var inp=document.getElementById('tpl-'+k.replace(/_/g,'-'));
      if(cb&&cfg){ cb.checked=!!cfg.enabled; if(!cfg.enabled) allEnabled=false; }
      if(inp&&cfg&&cfg.template)inp.value=cfg.template;
    });
    // Mostrar advertencia si hay eventos desactivados
    var warn = document.getElementById('auto-disabled-warn');
    if(warn) warn.style.display = allEnabled ? 'none' : 'flex';
  }catch(e){}
  loadYopPollList();
}

async function toggleAuto(ev, en){
  // Feedback visual inmediato en el label
  var kid = ev.replace(/_/g,'-');
  var msgEl = document.getElementById('msg-'+kid);
  if(msgEl){ msgEl.style.color='#888'; msgEl.textContent='Guardando...'; }
  try{
    var r=await fetch(API+'/push-auto-toggle',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({vk_token:TOK,event:ev,enabled:en})});
    var d=await r.json();
    if(msgEl){
      if(r.ok&&d.success){
        msgEl.style.color=en?'#2e7d32':'#888';
        msgEl.textContent=en?'✅ Activado':'○ Desactivado';
        setTimeout(function(){msgEl.textContent='';},2500);
      } else {
        msgEl.style.color='#c62828'; msgEl.textContent='✗ Error al guardar';
      }
    }
    AUTO_CONFIG[ev]=AUTO_CONFIG[ev]||{};
    AUTO_CONFIG[ev].enabled=en;
  }catch(e){
    if(msgEl){msgEl.style.color='#c62828'; msgEl.textContent='✗ Error de red';}
  }
}

async function enableAllAutoNotifs(){
  var events=['new_course','new_product','new_poll','new_bundle','cert_issued','course_complete','progress'];
  var btn = document.getElementById('btn-enable-all');
  if(btn){ btn.disabled=true; btn.textContent='⏳ Activando...'; }
  var ok=0;
  for(var i=0;i<events.length;i++){
    var ev=events[i];
    try{
      var r=await fetch(API+'/push-auto-toggle',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({vk_token:TOK,event:ev,enabled:true})});
      if(r.ok) ok++;
    }catch(e){}
  }
  if(btn){ btn.disabled=false; btn.textContent='⚡ Activar todos'; }
  if(ok===events.length){
    showMsg && showMsg('auto-bulk-msg','✅ Todos los eventos activados ('+ok+'/'+events.length+')','ok');
    loadAutoConfig();
  } else {
    showMsg && showMsg('auto-bulk-msg','⚠️ Activados '+ok+'/'+events.length,'err');
  }
}
async function saveTemplate(event){
  var key=event.replace(/_/g,'-');
  var inp=document.getElementById('tpl-'+key);
  var msgEl=document.getElementById('msg-'+key);
  var btn=document.getElementById('btn-save-'+key);
  if(!inp)return;
  if(btn){btn.disabled=true;btn.textContent='⏳ Guardando...';}
  try{
    var r=await fetch(API+'/push-auto-template',{method:'POST',headers:{'Content-Type':'application/json; charset=utf-8'},body:JSON.stringify({vk_token:TOK,event:event,template:inp.value})});
    var d=await r.json();
    if(msgEl){msgEl.style.color=r.ok&&d.success?'#2e7d32':'#c62828';msgEl.textContent=r.ok&&d.success?'✅ Guardado':'⚠️ '+(d.message||'Error');setTimeout(function(){msgEl.textContent='';},3000);}
  }catch(e){if(msgEl){msgEl.style.color='#c62828';msgEl.textContent='⚠️ Error de red';}}
  finally{if(btn){btn.disabled=false;btn.textContent='💾 Guardar plantilla';}}
}

/* ═══════════════════════════════════════════════════════════════════
   CLASE EN LINEA
═══════════════════════════════════════════════════════════════════ */
var _livePlatform   = 'Zoom';
var _liveTarget     = 'all';
var _liveUserIds    = [];
var _platformDetect = {
  'zoom.us':'Zoom','meet.google.com':'Google Meet','teams.microsoft.com':'Teams',
  'youtube.com':'YouTube','youtu.be':'YouTube','webex.com':'Webex'
};

function setLiveTarget(t) {
  _liveTarget  = t;
  _liveUserIds = [];
  document.getElementById('live-tgt-all').className  = t==='all'  ? 'btn btn-primary btn-sm'   : 'btn btn-secondary btn-sm';
  document.getElementById('live-tgt-user').className = t==='user' ? 'btn btn-primary btn-sm'   : 'btn btn-secondary btn-sm';
  var wrap = document.getElementById('live-user-search-wrap');
  if (wrap) wrap.style.display = t==='user' ? 'block' : 'none';
}

function selectPlatform(p) {
  _livePlatform = p;
  document.getElementById('live-platform').value = p;
  document.querySelectorAll('.plat-btn').forEach(function(b){
    b.classList.remove('btn-primary'); b.classList.add('btn-secondary');
    b.style.background=''; b.style.color='';
  });
  var id = 'plat-' + p.toLowerCase().replace(' ','-').replace('google-meet','meet');
  var btn = document.getElementById(id);
  if (btn) { btn.classList.add('btn-primary'); btn.classList.remove('btn-secondary'); }
  var ph = {'Zoom':'https://zoom.us/j/...','Google Meet':'https://meet.google.com/xxx-xxxx','Teams':'https://teams.microsoft.com/l/meetup-join/...','YouTube':'https://youtube.com/live/...','Otro':'https://'};
  var inp = document.getElementById('live-link');
  if (inp && !inp.value) inp.placeholder = ph[p] || 'https://...';
}

function detectPlatformFromUrl(url) {
  if (!url) return;
  for (var d in _platformDetect) { if (url.indexOf(d)!==-1) { selectPlatform(_platformDetect[d]); return; } }
}

async function searchLiveUsers(q) {
  var res = document.getElementById('live-user-results');
  if (!q || q.length < 2) { res.innerHTML=''; return; }
  try {
    var r = await fetch(API+'/push-subscribers?vk_token='+encodeURIComponent(TOK));
    var d = await r.json();
    var list = (d.data||[]).filter(function(u){
      return (u.display_name+u.user_email).toLowerCase().includes(q.toLowerCase());
    }).slice(0,8);
    res.innerHTML = list.map(function(u){
      return '<div style="padding:.5rem .75rem;cursor:pointer;border-bottom:1px solid var(--border-light);font-size:.84rem;display:flex;align-items:center;gap:.5rem" onclick="addLiveUser('+u.ID+',\''+esc(u.display_name)+'\')">'
        +'<span style="flex:1"><strong>'+esc(u.display_name)+'</strong><br><span style="color:var(--tu);font-size:.75rem">'+esc(u.user_email)+'</span></span>'
        +'<span style="color:var(--vk-plum);font-size:.75rem">+ Agregar</span></div>';
    }).join('') || '<div style="padding:.75rem;color:var(--tu);font-size:.83rem;text-align:center">Sin resultados</div>';
  } catch(e) {}
}

function addLiveUser(id, name) {
  if (_liveUserIds.indexOf(id) !== -1) return;
  _liveUserIds.push(id);
  var container = document.getElementById('live-selected-users');
  var tag = document.createElement('span');
  tag.style.cssText = 'background:var(--vk-petal);color:var(--vk-plum);border-radius:20px;padding:.2rem .65rem;font-size:.78rem;font-weight:600;display:flex;align-items:center;gap:.35rem';
  tag.innerHTML = esc(name) + ' <span style="cursor:pointer;font-weight:700" onclick="removeLiveUser('+id+',this.parentNode)">x</span>';
  tag.dataset.uid = id;
  container.appendChild(tag);
  document.getElementById('live-user-results').innerHTML = '';
  document.getElementById('live-user-search').value = '';
}

function removeLiveUser(id, el) {
  _liveUserIds = _liveUserIds.filter(function(x){ return x !== id; });
  if (el) el.remove();
}

function previewLiveClass() {
  var title = document.getElementById('live-title').value || 'Clase en Linea';
  var msg   = document.getElementById('live-message').value || '';
  var sched = document.getElementById('live-schedule').value || '';
  var link  = document.getElementById('live-link').value || '#';
  document.getElementById('lp-title').textContent = '['+_livePlatform+'] '+title;
  document.getElementById('lp-msg').textContent   = msg + (sched?' | '+sched:'');
  document.getElementById('lp-link').href = link;
  document.getElementById('live-preview').style.display = 'block';
}

async function sendLiveClass() {
  var link  = (document.getElementById('live-link').value||'').trim();
  var title = (document.getElementById('live-title').value||'Clase en Linea').trim();
  var msg   = (document.getElementById('live-message').value||'').trim();
  var sched = (document.getElementById('live-schedule').value||'').trim();
  var btn   = document.getElementById('btn-send-live');

  if (!link || !link.startsWith('http')) { showMsg('live-msg','Ingresa el enlace de la clase','err'); return; }
  if (_liveTarget==='user' && _liveUserIds.length===0) { showMsg('live-msg','Agrega al menos un usuario destinatario','err'); return; }

  btn.disabled=true; btn.textContent='Enviando...';

  try {
    var body = {vk_token:TOK, title:title, message:msg, link:link, platform:_livePlatform, schedule:sched, target:_liveTarget};
    if (_liveTarget==='user') body.user_ids = _liveUserIds;

    var r = await fetch(API+'/push-live-class',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    var d = await r.json();

    if (r.ok && d.success) {
      var rec = d.recipients>0 ? ' ('+d.recipients+' notificados)' : ' (guardado en BD)';
      showMsg('live-msg','Notificacion enviada' + rec,'ok');
      previewLiveClass();
      // Limpiar seleccion de usuarios
      _liveUserIds=[];
      var sc = document.getElementById('live-selected-users');
      if(sc) sc.innerHTML='';
    } else {
      showMsg('live-msg','Error: '+(d.message||'Intenta de nuevo'),'err');
    }
  } catch(e) { showMsg('live-msg','Error: '+e.message,'err'); }
  btn.disabled=false; btn.textContent='Enviar notificacion';
}

/* ── Diagnóstico completo del sistema ── */
async function loadAutoStatus(){
  var el = document.getElementById('auto-status-body');
  if(!el) return;
  el.innerHTML = '<span style="color:var(--tu)">Verificando...</span>';
  try{
    var r = await fetch(API+'/push-auto-status?vk_token='+encodeURIComponent(TOK));
    var d = await r.json();
    var lines = [];

    lines.push('<div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem .75rem;font-size:.83rem">');

    // API Key
    var keyOk = d.has_api_key;
    lines.push('<div style="grid-column:1/-1;background:'+(keyOk?'#e8f5e9':'#fff3cd')+';border-radius:8px;padding:.5rem .75rem;font-weight:600;color:'+(keyOk?'#2e7d32':'#856404')+'">'+
      (keyOk?'✅ REST API Key: Configurada':'⚠️ REST API Key: FALTA — ve a ⚙️ Configuración')+'</div>');

    // Stats
    lines.push('<div>👥 Suscriptores push: <strong>'+d.subscribers+'</strong></div>');
    lines.push('<div>📬 Notifs en BD: <strong>'+d.notif_count+'</strong></div>');
    lines.push('<div>🔄 Cron encuestas: <strong>'+(d.cron_scheduled?'✅ OK':'✗ No')+'</strong></div>');
    lines.push('<div>🗄️ Tabla BD: <strong>'+(d.table_exists?'✅ OK':'✗ No existe')+'</strong></div>');

    lines.push('</div>');
    lines.push('<div style="margin-top:.75rem;font-size:.82rem;font-weight:600;color:var(--ts)">Estado de eventos y hooks WordPress:</div>');
    lines.push('<div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem;font-size:.8rem;margin-top:.35rem">');

    var evLabels = {
      new_course:'📚 Nuevo Curso', new_product:'🛍️ Nuevo Producto',
      new_poll:'📊 Nueva Encuesta', new_bundle:'📦 Nuevo Paquete',
      cert_issued:'🏆 Certificado', course_complete:'✅ Completado', progress:'🎯 Progreso'
    };
    Object.keys(evLabels).forEach(function(k){
      var en = d.events && d.events[k] && d.events[k].enabled;
      var hk = d.hooks && d.hooks[k];
      var color = en ? '#2e7d32' : '#b36b00';
      lines.push('<div style="padding:.3rem .5rem;background:'+(en?'#e8f5e9':'#fff8e1')+';border-radius:6px;color:'+color+'">'+
        evLabels[k]+'<br><span>'+(en?'✅ Activo':'○ Inactivo')+'</span>'+
        '<span style="margin-left:.5rem;color:'+(hk?'#2e7d32':'#c62828')+'">'+(hk?'🔗 Hook OK':'⚠️ Hook?')+'</span>'+
        '</div>');
    });
    lines.push('</div>');

    el.innerHTML = lines.join('');
  }catch(e){
    el.innerHTML='<span style="color:#c62828">✗ Error: '+esc(e.message)+'</span>';
  }
}

/* ── Probar evento individual ── */
async function testEvent(event){
  var kid = event.replace(/_/g,'-');
  var msgEl = document.getElementById('msg-'+kid);
  if(msgEl){ msgEl.style.color='#888'; msgEl.textContent='⏳ Enviando prueba...'; }
  try{
    var r = await fetch(API+'/push-test-event',{method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({vk_token:TOK,event:event})});
    var d = await r.json();
    if(msgEl){
      if(r.ok&&d.success){
        var dest = d.sent_to==='all' ? 'a todos los usuarios' : 'a tu usuario admin';
        msgEl.style.color='#2e7d32';
        msgEl.textContent='✅ Prueba enviada '+dest;
      } else {
        msgEl.style.color='#c62828';
        msgEl.textContent='✗ '+(d.message||'Error al enviar');
      }
      setTimeout(function(){if(msgEl)msgEl.textContent='';},5000);
    }
  }catch(e){
    if(msgEl){msgEl.style.color='#c62828'; msgEl.textContent='✗ Error de red: '+e.message;}
  }
}

/* ── YOP Poll: cargar lista y notificar manualmente ─────────────── */
async function loadYopPollList(){
  var sel=document.getElementById('yop-poll-select');
  if(!sel)return;
  try{
    var r=await fetch(API+'/notify-poll',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({vk_token:TOK})});
    var d=await r.json();
    var polls=d.polls||[];
    var lastNotified=d.last_notified||0;
    sel.innerHTML='<option value="">-- Seleccionar encuesta --</option>';
    polls.forEach(function(p){
      var isNew = parseInt(p.id)>lastNotified ? ' 🆕':'';
      var opt=document.createElement('option');
      opt.value=p.id;
      opt.textContent='['+p.id+'] '+p.name+isNew+' ('+p.status+')';
      sel.appendChild(opt);
    });
    if(!polls.length){sel.innerHTML='<option value="">Sin encuestas en YOP Poll</option>';}
  }catch(e){if(sel)sel.innerHTML='<option value="">Error al cargar</option>';}
}

async function notifyYopPoll(){
  var sel=document.getElementById('yop-poll-select');
  var msg=document.getElementById('msg-yop-poll');
  var pollId=sel?parseInt(sel.value):0;
  if(!pollId){if(msg){msg.style.color='#c62828';msg.textContent='⚠️ Selecciona una encuesta primero';}return;}
  if(msg){msg.style.color='#888';msg.textContent='⏳ Enviando notificación...';}
  try{
    var r=await fetch(API+'/notify-poll',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({vk_token:TOK,poll_id:pollId})});
    var d=await r.json();
    if(msg){
      if(r.ok&&d.success){
        msg.style.color='#2e7d32';
        msg.textContent='✅ Notificación enviada: "'+d.poll+'"';
      } else {
        msg.style.color='#c62828';
        msg.textContent='⚠️ '+(d.message||'Error al enviar');
      }
      setTimeout(function(){if(msg)msg.textContent='';},5000);
    }
  }catch(e){if(msg){msg.style.color='#c62828';msg.textContent='⚠️ Error de red';}}
}

async function notifyAllNewPolls(){
  var msg=document.getElementById('msg-yop-poll');
  if(!confirm('¿Notificar TODAS las encuestas nuevas (no notificadas aún)?'))return;
  if(msg){msg.style.color='#888';msg.textContent='⏳ Procesando...';}
  try{
    var r=await fetch(API+'/notify-poll',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({vk_token:TOK,all_new:true})});
    var d=await r.json();
    if(msg){
      if(r.ok&&d.success){
        msg.style.color='#2e7d32';
        msg.textContent='✅ Notificadas: '+d.notified+' encuesta(s)';
      }else{
        msg.style.color='#c62828';msg.textContent='⚠️ '+(d.message||'Error');
      }
      setTimeout(function(){if(msg)msg.textContent='';loadYopPollList();},4000);
    }
  }catch(e){if(msg){msg.style.color='#c62828';msg.textContent='⚠️ Error de red';}}
}

/* ── HISTORIAL (renovado) ───────────────────────────── */
var _histTab = 'db';
var _histDBPage = 0;
var _histDBPageSize = 20;
var _histDBTotal = 0;
var _histDebounceTimer = null;

function switchHistTab(tab) {
  _histTab = tab;
  var db   = document.getElementById('hpanel-db');
  var push = document.getElementById('hpanel-push');
  var btnDb   = document.getElementById('htab-db');
  var btnPush = document.getElementById('htab-push');
  if (db)   db.style.display   = tab === 'db'   ? '' : 'none';
  if (push) push.style.display = tab === 'push' ? '' : 'none';
  if (btnDb)   { btnDb.className   = tab==='db'   ? 'btn btn-primary btn-sm'   : 'btn btn-secondary btn-sm'; }
  if (btnPush) { btnPush.className = tab==='push' ? 'btn btn-primary btn-sm'   : 'btn btn-secondary btn-sm'; }
  if (tab === 'db')   loadHistoryDB();
  if (tab === 'push') loadHistoryPush();
}

function debounceHistDB() {
  clearTimeout(_histDebounceTimer);
  _histDBPage = 0;
  _histDebounceTimer = setTimeout(loadHistoryDB, 320);
}

/* ── BD: cargar de vk_notifications ── */
async function loadHistoryDB() {
  var el   = document.getElementById('history-db-body');
  var pgEl = document.getElementById('hist-db-pagination');
  var type  = (document.getElementById('hist-db-type')  || {value:''}).value;
  var scope = (document.getElementById('hist-db-scope') || {value:''}).value;
  var search = (document.getElementById('hist-db-search') || {value:''}).value.trim();
  if (!el) return;
  el.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--tu)"><span class="loader" style="border-color:rgba(196,77,138,.3);border-top-color:var(--vk-rose)"></span> Cargando...</div>';

  try {
    var url = API + '/admin-notifications?vk_token=' + encodeURIComponent(TOK)
      + '&limit=' + _histDBPageSize
      + '&offset=' + (_histDBPage * _histDBPageSize);
    if (type)   url += '&type='   + encodeURIComponent(type);
    if (search) url += '&search=' + encodeURIComponent(search);

    var r = await fetch(url);
    var d = await r.json();
    var list = d.data || [];
    _histDBTotal = d.total || 0;

    // Filter by scope client-side
    if (scope === 'global')   list = list.filter(function(n){ return n.is_global; });
    if (scope === 'personal') list = list.filter(function(n){ return !n.is_global; });

    // Update count
    var countEl = document.getElementById('hist-db-count');
    if (countEl) countEl.textContent = _histDBTotal + ' notificación' + (_histDBTotal !== 1 ? 'es' : '');

    if (!list.length) {
      el.innerHTML = '<div class="hist-empty"><div class="empty-icon">📭</div><p>No hay notificaciones' + (search || type ? ' con esos filtros' : '') + '.</p></div>';
      if (pgEl) pgEl.innerHTML = '';
      return;
    }

    el.innerHTML = list.map(function(n) { return renderNHCard(n); }).join('');

    // Pagination
    if (pgEl) renderDBPagination(pgEl);

  } catch(e) {
    el.innerHTML = '<p style="color:#c62828;padding:1rem">Error: ' + esc(e.message) + '</p>';
  }
}

function renderNHCard(n) {
  var icon  = NOTIF_ICONS[n.type] || '🔔';
  var scope = n.is_global
    ? '<span class="nh-tag-global">🌍 Todos</span>'
    : '<span class="nh-tag-user">👤 ' + esc(n.display_name || 'Usuario') + '</span>';
  var cleanT  = cleanMojibake(n.title || '');
  var dateStr = n.created_at ? n.created_at.substring(0, 16).replace('T', ' ') : '';
  var readBadge = n.is_read
    ? '<span style="font-size:.68rem;color:#2e7d32;background:#e8f5e9;padding:.1rem .4rem;border-radius:8px">✓ Leída</span>'
    : '<span style="font-size:.68rem;color:#b36b00;background:#fff8e1;padding:.1rem .4rem;border-radius:8px">● Sin leer</span>';
  var actionLink = n.action_url
    ? '<a href="' + esc(n.action_url) + '" target="_blank" style="font-size:.72rem;color:var(--vk-rose)">🔗 Ver</a>'
    : '';
  return '<div class="nh-card' + (n.is_global ? ' is-global' : '') + '" id="nhc-' + n.id + '">'
    + '<div class="nh-icon type-' + esc(n.type) + '">' + icon + '</div>'
    + '<div class="nh-body">'
    +   '<div class="nh-header">'
    +     '<span class="nh-title">' + esc(cleanT) + '</span>'
    +     '<button class="nh-del-btn" onclick="deleteNotifAdmin(' + n.id + ')" title="Eliminar">'
    +       '<svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M1.5 1.5l10 10M11.5 1.5l-10 10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
    +     '</button>'
    +   '</div>'
    +   '<div class="nh-msg">' + esc(n.message) + '</div>'
    +   '<div class="nh-meta">'
    +     scope + readBadge
    +     '<span class="nh-time">' + esc(dateStr) + '</span>'
    +     actionLink
    +   '</div>'
    + '</div>'
    + '</div>';
}

function renderDBPagination(pgEl) {
  var totalPages = Math.ceil(_histDBTotal / _histDBPageSize);
  if (totalPages <= 1) { pgEl.innerHTML = ''; return; }
  var html = '';
  html += '<button class="hist-pagination-btn" onclick="histDBGoPage(' + (_histDBPage - 1) + ')" ' + (_histDBPage === 0 ? 'disabled' : '') + '>‹ Anterior</button>';
  var start = Math.max(0, _histDBPage - 2);
  var end   = Math.min(totalPages - 1, _histDBPage + 2);
  for (var i = start; i <= end; i++) {
    html += '<button class="hist-pagination-btn' + (i === _histDBPage ? ' active' : '') + '" onclick="histDBGoPage(' + i + ')">' + (i + 1) + '</button>';
  }
  html += '<button class="hist-pagination-btn" onclick="histDBGoPage(' + (_histDBPage + 1) + ')" ' + (_histDBPage >= totalPages - 1 ? 'disabled' : '') + '>Siguiente ›</button>';
  pgEl.innerHTML = html;
}

function histDBGoPage(p) {
  _histDBPage = Math.max(0, p);
  loadHistoryDB();
}

/* Eliminar notificación individual de la BD (admin) */
async function deleteNotifAdmin(id) {
  var card = document.getElementById('nhc-' + id);
  if (card) {
    card.style.transition = 'all .2s ease';
    card.style.opacity = '0'; card.style.transform = 'translateX(30px)';
    setTimeout(function(){ card.remove(); _histDBTotal = Math.max(0, _histDBTotal - 1); var cEl = document.getElementById('hist-db-count'); if(cEl) cEl.textContent = _histDBTotal + ' notificación' + (_histDBTotal !== 1 ? 'es' : ''); }, 210);
  }
  try {
    await fetch(API + '/admin-notif-delete?vk_token=' + encodeURIComponent(TOK), {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ id: id })
    });
  } catch(e) {}
}

/* Eliminar TODAS de la BD */
async function deleteAllNotifsAdmin() {
  var type = (document.getElementById('hist-db-type') || {value:''}).value;
  var msg  = type ? 'las notificaciones de tipo "' + type + '"' : 'TODAS las notificaciones de la base de datos';
  if (!confirm('¿Eliminar ' + msg + '? Esta acción es permanente e irreversible.')) return;
  var btn = document.getElementById('btn-del-all-db');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Eliminando...'; }
  try {
    await fetch(API + '/admin-notif-delete?vk_token=' + encodeURIComponent(TOK), {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ all: true, type: type || undefined })
    });
    _histDBPage = 0;
    loadHistoryDB();
  } catch(e) { alert('Error: ' + e.message); }
  finally { if (btn) { btn.disabled = false; btn.textContent = '🗑 Eliminar todas'; } }
}

/* ── Push Log ── */
async function loadHistoryPush() {
  var el = document.getElementById('history-push-body');
  var tf = (document.getElementById('hist-push-type') || {value:''}).value;
  if (!el) return;
  el.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--tu)"><span class="loader" style="border-color:rgba(196,77,138,.3);border-top-color:var(--vk-rose)"></span> Cargando...</div>';
  try {
    var r = await fetch(API + '/push-history?vk_token=' + encodeURIComponent(TOK));
    var d = await r.json();
    var list = (d.data || []).filter(function(n, i){ return !tf || n.type === tf; });
    if (!list.length) {
      el.innerHTML = '<div class="hist-empty"><div class="empty-icon">📡</div><p>Sin envíos push registrados.</p></div>';
      return;
    }
    el.innerHTML = list.map(function(n) {
      var icon   = NOTIF_ICONS[n.type] || '🔔';
      var nid    = esc(String(n.id || n.date || ''));
      var cleanT = cleanMojibake(n.title || '');
      var typeLabels = {course:'Curso',product:'Producto',poll:'Encuesta',cert:'Certificado',info:'Info',system:'Sistema',bundle:'Paquete',progress:'Progreso',course_done:'Completado'};
      var typeLabel = typeLabels[n.type] || n.type || 'Info';
      var target = n.target === 'all'
        ? '<span class="nh-tag-global">🌍 Global</span>'
        : (n.target === 'user'
          ? '<span class="nh-tag-user">👤 Personal</span>'
          : '<span class="nh-tag-user">👤 ' + esc(n.target || '') + '</span>');
      return '<div class="nh-card" id="phc-' + nid + '">'
        + '<div class="nh-icon type-' + esc(n.type) + '">' + icon + '</div>'
        + '<div class="nh-body">'
        +   '<div class="nh-header">'
        +     '<div style="flex:1;min-width:0">'
        +       '<span class="nh-type-badge" style="color:' + (NH_COLORS[n.type]||'#6f102a') + '">' + esc(typeLabel) + '</span>'
        +       '<div class="nh-title">' + esc(cleanT) + '</div>'
        +     '</div>'
        +     '<button class="nh-del-btn" onclick="deletePushEntry(&quot;' + nid + '&quot;)" title="Eliminar">'
        +       '<svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M1.5 1.5l10 10M11.5 1.5l-10 10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
        +     '</button>'
        +   '</div>'
        +   '<div class="nh-msg">' + esc(n.message) + '</div>'
        +   '<div class="nh-meta">' + target
        +     (n.recipients ? '<span>👥 ' + n.recipients + '</span>' : '')
        +     '<span class="nh-time">' + esc(n.date || '') + '</span>'
        +   '</div>'
        + '</div></div>';
    }).join('');
  } catch(e) {
    el.innerHTML = '<p style="color:#c62828;padding:1rem">Error: ' + esc(e.message) + '</p>';
  }
}

async function deletePushEntry(id) {
  var card = document.getElementById('phc-' + id);
  if (card) { card.style.opacity = '0'; card.style.transition = 'opacity .2s'; setTimeout(function(){ card.remove(); }, 200); }
  try {
    await fetch(API + '/push-history-delete?vk_token=' + encodeURIComponent(TOK), {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ id: id, source: 'push' })
    });
  } catch(e) {}
}

async function deleteAllPushHistory() {
  if (!confirm('¿Limpiar todo el log de envíos push? Esto no afecta la base de datos de notificaciones.')) return;
  try {
    await fetch(API + '/push-history-delete?vk_token=' + encodeURIComponent(TOK), {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ all: true, source: 'push' })
    });
    loadHistoryPush();
  } catch(e) { alert('Error: ' + e.message); }
}

/* Mantener compatibilidad si algo llama loadHistory() */
function loadHistory() { switchHistTab(_histTab); }

/* ── SUSCRIPTORES ────────────────────────────────────── */
async function loadUsers(){
  var el=document.getElementById('users-body');
  el.innerHTML='<div style="text-align:center;padding:1.5rem;color:var(--tu)">Cargando...</div>';
  try{
    var r=await fetch(API+'/push-subscribers?vk_token='+encodeURIComponent(TOK));
    var d=await r.json(); var list=d.data||[];
    var q=(document.getElementById('user-search').value||'').toLowerCase();
    if(q)list=list.filter(function(u){return(u.display_name+u.user_email).toLowerCase().includes(q);});
    if(!list.length){el.innerHTML='<p style="text-align:center;padding:2rem;color:var(--tu)">Sin suscriptores aún</p>';return;}

    var devIcons = {'Chrome':'&#127760;','Firefox':'&#x1F98A;','Safari':'&#x1F9ED;','Edge':'&#x1F30A;','Móvil':'&#x1F4F1;','Tablet':'&#x1F4BB;','Escritorio':'&#x1F5A5;'};
    var osIcons  = {'Android':'&#x1F4F1;','iOS':'&#x1F34E;','Windows':'&#x1FA9F;','macOS':'&#x1F34F;','Linux':'&#x1F427;'};

    var html = '<div style="font-size:.8rem;color:var(--tu);margin-bottom:.75rem">'
      + '<strong>' + list.length + '</strong> usuario(s) | '
      + '<strong>' + (d.total_devices||0) + '</strong> dispositivo(s)</div>';

    list.forEach(function(u){
      var devices = u.devices || [];
      var devHtml = devices.map(function(dv){
        var bIcon = devIcons[dv.browser] || '&#x1F310;';
        var dIcon = dv.device === 'Móvil' ? '&#x1F4F1;' : dv.device === 'Tablet' ? '&#x1F4BB;' : '&#x1F5A5;';
        var oIcon = osIcons[dv.os] || '&#x1F4BB;';
        var reg   = dv.registered ? dv.registered.substring(0,16) : '';
        return '<span style="display:inline-flex;align-items:center;gap:.3rem;background:rgba(107,36,71,.07);border-radius:8px;padding:.2rem .5rem;margin:.1rem;font-size:.75rem;cursor:pointer" title="ID: '+esc(dv.player_id)+'" onclick="copyText(\''+esc(dv.player_id)+'\')">'
          + dIcon + ' ' + oIcon + ' ' + bIcon
          + ' <span>' + esc(dv.os||'?') + ' · ' + esc(dv.browser||'?') + ' · ' + esc(dv.device||'?') + '</span>'
          + (reg ? ' <span style="color:var(--tu)">'+reg+'</span>' : '')
          + '</span>';
      }).join('');
      if (!devHtml) devHtml = '<span style="color:var(--tu);font-size:.75rem">Sin info de dispositivo</span>';

      html += '<div style="background:#fff;border-radius:12px;border:1px solid var(--border-light);padding:.75rem 1rem;margin-bottom:.6rem">'
        + '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem">'
        + '<div>'
        + '<div style="font-weight:700;font-size:.9rem">'+esc(u.display_name)+'</div>'
        + '<div style="font-size:.78rem;color:var(--tu)">'+esc(u.user_email)+'</div>'
        + '</div>'
        + '<button class="btn btn-secondary btn-sm" onclick="sendToUser(\''+esc(u.user_email)+'\')" style="font-size:.78rem">&#x1F4E4; Enviar</button>'
        + '</div>'
        + '<div style="margin-top:.5rem">'+devHtml+'</div>'
        + '</div>';
    });
    el.innerHTML=html;
  }catch(e){el.innerHTML='<p style="color:#c62828;padding:1rem">Error: '+e.message+'</p>';}
}

function copyText(txt){
  try{ navigator.clipboard.writeText(txt); showToast && showToast('ID copiado'); }
  catch(e){}
}
function sendToUser(email){
  document.getElementById('n-target').value='user';
  document.getElementById('n-user-email').value=email;
  document.getElementById('f-user-email').style.display='';
  showTab('send');
}

/* ═══════════════════════════════════════════════════════════════
   GESTOR DE PLANTILLAS — usa solo endpoints existentes
   Lee/escribe en push-auto-config con claves _cert_tpl y _cert_assign
════════════════════════════════════════════════════════════════ */

function apiUrl(path) {
  return API + path
    + (path.indexOf('?')>=0 ? '&' : '?')
    + 'vk_token=' + encodeURIComponent(TOK)
    + '&_t=' + Date.now();
}

/* Lee plantillas Y cursos en una sola llamada */
async function tplReadFull() {
  var r = await fetch(apiUrl('/cert-tpl-read'), {cache:'no-store'});
  if (!r.ok) {
    var t=''; try{t=await r.text();}catch(e){}
    throw new Error('HTTP ' + r.status + ': ' + t.substring(0,120));
  }
  return r.json(); // {success, templates, assignments, courses}
}

async function tplReadAll() {
  var d = await tplReadFull();
  return d.templates || [];
}

async function tplWriteAll(list) {
  var r = await fetch(apiUrl('/cert-tpl-write'), {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    cache: 'no-store',
    body: JSON.stringify({ key: '_cert_tpl', data: list })
  });
  if (!r.ok) {
    var t=''; try{t=await r.text();}catch(e2){}
    throw new Error('HTTP ' + r.status + ': ' + t.substring(0,120));
  }
  return r.json();
}

async function tplReadAssign() {
  var d = await tplReadFull();
  return d.assignments || {};
}

async function tplWriteAssign(obj) {
  var r = await fetch(apiUrl('/cert-tpl-write'), {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    cache: 'no-store',
    body: JSON.stringify({ key: '_cert_assign', data: obj })
  });
  if (!r.ok) {
    var t=''; try{t=await r.text();}catch(e2){}
    throw new Error('HTTP ' + r.status + ': ' + t.substring(0,120));
  }
  return r.json();
}

function tplSlug(name) {
  var map = {'á':'a','é':'e','í':'i','ó':'o','ú':'u','ü':'u','ñ':'n','Á':'a','É':'e','Í':'i','Ó':'o','Ú':'u','Ü':'u','Ñ':'n',' ':'_'};
  var s = name.toLowerCase().replace(/[áéíóúüñÁÉÍÓÚÜÑ ]/g, function(c){return map[c]||c;});
  s = s.replace(/[^a-z0-9_]+/g,'_').replace(/^_+|_+$/g,'');
  return s || 'plantilla';
}


var _tplManagerLoading = false;
async function loadCertCourseAssignments(){
  if (_tplManagerLoading) return; // prevent concurrent renders
  _tplManagerLoading = true;
  try {
    var certPanel = document.getElementById('panel-certs');
    if (!certPanel) return;
    if (!document.getElementById('tpl-manager-section')) {
      var sec = document.createElement('div');
      sec.id = 'tpl-manager-section';
      certPanel.appendChild(sec);
    }
    // Remove orphaned edit-notice injected before template manager was ready
    var orphan = document.getElementById('tpl-edit-notice');
    if (orphan) orphan.parentNode && orphan.parentNode.removeChild(orphan);

    document.getElementById('tpl-manager-section').innerHTML =
      '<div class="card"><p style="color:var(--tu);text-align:center;padding:1.5rem">⏳ Cargando plantillas...</p></div>';
    // Single call returns templates + assignments + courses
    var full = await tplReadFull();
    TPL_LIST    = full.templates || [];
    TPL_COURSES = full.courses   || [];
    renderTplManager();
  } catch(e) {
    var s = document.getElementById('tpl-manager-section');
    if (s) s.innerHTML = '<div class="card"><div class="msg msg-err">✗ Error: ' + esc(e.message) + '</div></div>';
  } finally {
    _tplManagerLoading = false;
  }
}


function renderTplManager() {
  var sec = document.getElementById('tpl-manager-section');
  if (!sec) return;

  /* ── Tarjetas de plantillas ── */
  var grid = document.createElement('div');
  grid.className = 'tmgr-grid';
  grid.id = 'tpl-cards-grid';

  TPL_LIST.forEach(function(t) {
    var card = document.createElement('div');
    card.className = 'tmgr-card';
    card.id = 'tpl-card-' + t.key;

    // Thumbnail
    var thumbEl;
    if (t.thumb) {
      thumbEl = document.createElement('img');
      thumbEl.className = 'tmgr-thumb';
      thumbEl.src = t.thumb;
      thumbEl.onerror = function() {
        this.style.display = 'none';
        var ph = document.createElement('div');
        ph.className = 'tmgr-thumb-placeholder';
        ph.textContent = '🏆';
        card.insertBefore(ph, card.firstChild);
      };
    } else {
      thumbEl = document.createElement('div');
      thumbEl.className = 'tmgr-thumb-placeholder';
      thumbEl.textContent = '🏆';
    }
    card.appendChild(thumbEl);

    // Body
    var body = document.createElement('div');
    body.className = 'tmgr-body';

    var nameEl = document.createElement('div');
    nameEl.className = 'tmgr-name';
    nameEl.title = t.name;
    nameEl.textContent = t.name;
    body.appendChild(nameEl);

    var metaEl = document.createElement('div');
    metaEl.className = 'tmgr-meta';
    var badge = document.createElement('span');
    badge.className = t.courses_count > 0 ? 'tmgr-badge tmgr-badge-used' : 'tmgr-badge';
    badge.textContent = t.courses_count > 0
      ? '✅ ' + t.courses_count + ' curso' + (t.courses_count > 1 ? 's' : '')
      : 'Sin asignar';
    metaEl.appendChild(badge);
    body.appendChild(metaEl);

    // Actions
    var actions = document.createElement('div');
    actions.className = 'tmgr-actions';

    var btnEdit = document.createElement('button');
    btnEdit.className = 'btn btn-primary btn-sm';
    btnEdit.title = 'Editar diseño en el editor';
    btnEdit.textContent = '✏️ Editar';
    btnEdit.addEventListener('click', (function(key){ return function(){ loadTplIntoEditor(key); }; })(t.key));
    actions.appendChild(btnEdit);

    var btnRename = document.createElement('button');
    btnRename.className = 'btn btn-secondary btn-sm';
    btnRename.title = 'Renombrar';
    btnRename.textContent = '📝';
    btnRename.addEventListener('click', (function(key,name){ return function(){ openRenameTpl(key,name); }; })(t.key, t.name));
    actions.appendChild(btnRename);

    var btnDup = document.createElement('button');
    btnDup.className = 'btn btn-secondary btn-sm';
    btnDup.title = 'Duplicar';
    btnDup.textContent = '📋';
    btnDup.addEventListener('click', (function(key){ return function(){ openDuplicateTpl(key); }; })(t.key));
    actions.appendChild(btnDup);

    var btnDel = document.createElement('button');
    btnDel.className = 'btn btn-sm';
    btnDel.style.cssText = 'background:#fff0f0;color:#c62828;border:1px solid #ffcdd2';
    btnDel.title = 'Eliminar';
    btnDel.textContent = '🗑';
    btnDel.addEventListener('click', (function(key,name){ return function(){ deleteTpl(key,name); }; })(t.key, t.name));
    actions.appendChild(btnDel);

    body.appendChild(actions);
    card.appendChild(body);
    grid.appendChild(card);
  });

  // Tarjeta "Nueva plantilla"
  var addCard = document.createElement('div');
  addCard.className = 'tmgr-add-card';
  addCard.innerHTML = '<div class="tmgr-add-icon">➕</div><div style="font-weight:700;font-size:.88rem">Nueva plantilla</div><div style="font-size:.78rem;margin-top:.2rem;text-align:center;padding:0 .5rem">Crea una plantilla con nombre personalizado</div>';
  addCard.addEventListener('click', openCreateTpl);
  grid.appendChild(addCard);

  /* ── Tabla de asignación por curso ── */
  var tbody = '';
  if (!TPL_COURSES.length) {
    tbody = '<tr><td colspan="4" style="text-align:center;padding:1.5rem;color:var(--tu)">No hay cursos publicados.</td></tr>';
  } else {
    TPL_COURSES.forEach(function(c) {
      var opts = '<option value="default"' + (c.template_key==='default'?' selected':'') + '>⬜ Default (diseño global)</option>';
      TPL_LIST.forEach(function(t) {
        opts += '<option value="' + esc(t.key) + '"' + (c.template_key===t.key?' selected':'') + '>📄 ' + esc(t.name) + '</option>';
      });
      var pillClass = c.template_key==='default' ? 'tmpl-pill tmpl-pill-default' : 'tmpl-pill';
      var pillIcon  = c.template_key==='default' ? '⬜' : '📄';
      tbody += '<tr>'
        + '<td style="font-weight:700">' + esc(c.title) + '</td>'
        + '<td><span class="' + pillClass + '" id="cca-pill-' + c.id + '">' + pillIcon + ' ' + esc(c.template_name) + '</span></td>'
        + '<td><select id="cca-sel-' + c.id + '" style="padding:.4rem .65rem;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.83rem;max-width:200px">' + opts + '</select></td>'
        + '<td><button class="btn btn-primary btn-sm" id="cca-btn-' + c.id + '">💾 Guardar</button> <span id="cca-msg-' + c.id + '" style="font-size:.78rem"></span></td>'
        + '</tr>';
    });
  }

  // Render everything
  sec.innerHTML =
    '<div class="card" style="margin-top:1rem">'
    + '<h2>🏷️ Plantillas de Certificados</h2>'
    + '<p style="font-size:.85rem;color:var(--ts);margin-bottom:.6rem">Crea plantillas con nombre personalizado. Edítalas con el editor de arriba y asígnalas a cada curso.</p>'
    + '<div style="display:flex;gap:.5rem;margin-bottom:.75rem;flex-wrap:wrap">'
    + '<button class="btn btn-secondary btn-sm" onclick="openCreateTpl()">➕ Nueva plantilla</button>'
    + '<button class="btn btn-sm" style="background:#fff8e1;color:#b36b00;border:1px solid #ffe082" onclick="cleanDuplicates()">🧹 Limpiar duplicados</button>'
    + '</div>'
    + '<div id="tpl-manager-msg" style="margin-bottom:.75rem"></div>'
    + '</div>'
    + '<div class="card" style="margin-top:1rem">'
    + '<h2>📋 Asignación de Plantilla por Curso</h2>'
    + '<p style="font-size:.85rem;color:var(--ts);margin-bottom:.75rem">Selecciona qué plantilla usará cada curso al generar el certificado.</p>'
    + (function(){
        if (!TPL_LIST.length) return '';
        var aopts = '<option value="default">⬜ Diseño global (por defecto)</option>';
        TPL_LIST.forEach(function(t){ aopts += '<option value="'+esc(t.key)+'">📄 '+esc(t.name)+'</option>'; });
        return '<div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem;padding:.65rem .9rem;background:rgba(0,0,0,.03);border-radius:10px;border:1px solid var(--border)">'
          + '<strong style="font-size:.83rem;white-space:nowrap">Asignar a TODOS:</strong>'
          + '<select id="assign-all-sel" style="padding:.35rem .7rem;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;font-size:.83rem;flex:1;min-width:180px">'+aopts+'</select>'
          + '<button id="assign-all-btn" class="btn btn-primary btn-sm" onclick="assignAllCourses(document.getElementById(\'assign-all-sel\').value)">📋 Asignar a todos</button>'
          + '</div>';
      })()
    + '<table class="cca-table"><thead><tr><th>Curso</th><th>Plantilla actual</th><th>Cambiar a</th><th>Acción</th></tr></thead>'
    + '<tbody>' + tbody + '</tbody></table>'
    + '</div>';

  // Insert grid after the first card's msg div
  var msgDiv = document.getElementById('tpl-manager-msg');
  if (msgDiv) msgDiv.parentNode.insertBefore(grid, msgDiv.nextSibling);

  // Wire up save buttons for course assignment
  TPL_COURSES.forEach(function(c) {
    var btn = document.getElementById('cca-btn-' + c.id);
    if (btn) btn.addEventListener('click', (function(id){ return function(){ saveCertCourseTemplate(id); }; })(c.id));
  });
}

/* ── Cargar plantilla en el editor (usa POST /cert-config) ── */
async function loadTplIntoEditor(key) {
  try {
    var list = await tplReadAll();
    var found = list.find(function(t){return t.key===key;});
    if (!found) throw new Error('Plantilla no encontrada');
    var d = {name: found.name, config: found.config||{}, key: key};
    if (typeof VK_CERT !== 'undefined') {
      VK_CERT.cfg = Object.assign(vkCertDefaults(), d.config);
      VK_CERT._editing_tpl_key  = key;
      VK_CERT._editing_tpl_name = d.name;

      // Pre-cargar la imagen de fondo ANTES de redibujar para evitar que el renderer
      // use el bgImg de la plantilla anterior (lo que causaba "duplicación visual").
      // Espejea exactamente lo que hace vkLoadCertConfig.
      if (VK_CERT.cfg.bg_type === 'image') {
        var bgSrc = VK_CERT.cfg.bg_image_data || VK_CERT.cfg.bg_image_url || '';
        if (bgSrc) {
          await new Promise(function(resolve) {
            var img = new Image();
            img.onload  = function() { VK_CERT.bgImg = img; resolve(); };
            img.onerror = function() { VK_CERT.bgImg = null; resolve(); };
            img.src = bgSrc;
          });
        } else {
          VK_CERT.bgImg = null;
        }
      } else {
        VK_CERT.bgImg = null;
      }

      if (typeof vkCertFormSync   === 'function') vkCertFormSync();
      if (typeof vkCertPreviewRedraw === 'function') vkCertPreviewRedraw();
      // Actualizar el botón Guardar para que guarde en la plantilla nombrada
      var btn = document.getElementById('vk-save-cert-btn');
      if (btn) {
        btn.innerHTML = '💾 Guardar en "' + esc(d.name) + '"';
        btn.onclick   = function() { saveTplFromEditor(key, d.name); };
      }
      // Show editing notice — prefer the template manager msg div if it exists
      var emsg = document.getElementById('tpl-manager-msg');
      if (!emsg) {
        // Template manager not loaded yet: reuse existing notice or create one
        emsg = document.getElementById('tpl-edit-notice');
        if (!emsg) {
          emsg = document.createElement('div');
          emsg.id = 'tpl-edit-notice';
          // Insert inside panel-certs, before the editor tabs (not inside any tab content)
          var certPanel = document.getElementById('panel-certs');
          var editorTabs = certPanel && certPanel.querySelector('.vk-editor-tabs');
          if (editorTabs) {
            certPanel.insertBefore(emsg, editorTabs);
          } else {
            var certBar = document.querySelector('.vk-cert-bar');
            if (certBar) certBar.parentNode.insertBefore(emsg, certBar.nextSibling);
          }
        }
      }
      if (emsg) {
        emsg.className = 'msg msg-info';
        emsg.innerHTML = '✏️ Editando: <strong>' + esc(d.name) + '</strong> — haz cambios en el editor y pulsa el botón Guardar.';
      }
      // Scroll al editor
      var editorEl = document.getElementById('vk-cert-editor-mount') || document.querySelector('.vk-cert-bar');
      if (editorEl) editorEl.scrollIntoView({behavior:'smooth'});
    }
  } catch(e) {
    showMsg('tpl-manager-msg', '✗ Error: ' + e.message, 'err');
  }
}

/* ── Guardar configuración actual del editor en una plantilla nombrada ── */
async function saveTplFromEditor(key, name) {
  if (typeof VK_CERT === 'undefined') return;
  var btn = document.getElementById('vk-save-cert-btn');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="loader"></span> Guardando...'; }
  try {
    var list = await tplReadAll();
    var idx = list.findIndex(function(t){return t.key===key;});
    if (idx >= 0) {
      list[idx].config = VK_CERT.cfg;
      list[idx].name   = name;
      if (VK_CERT.cfg.bg_image_url) list[idx].thumb = VK_CERT.cfg.bg_image_url;
    } else {
      list.push({key:key,name:name,config:VK_CERT.cfg,thumb:VK_CERT.cfg.bg_image_url||'',created_at:new Date().toISOString()});
    }
    var d = await tplWriteAll(list);
    if (d && d.success) {
      showMsg('tpl-manager-msg', '✅ Plantilla "' + esc(name) + '" guardada correctamente.', 'ok');
      await loadCertCourseAssignments(); // recargar lista
    } else {
      showMsg('tpl-manager-msg', '✗ ' + (d.message || 'Error al guardar'), 'err');
    }
  } catch(e) { showMsg('tpl-manager-msg', '✗ Error de conexión', 'err'); }
  finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '💾 Guardar diseño'; btn.onclick = vkSaveCertConfig; }
    // Clear editing state so vkCertEditorInit guard doesn't skip global config reload
    if (typeof VK_CERT !== 'undefined') { VK_CERT._editing_tpl_key = null; VK_CERT._editing_tpl_name = null; }
  }
}

/* ── Guardar asignación de plantilla a un curso ── */
async function saveCertCourseTemplate(courseId) {
  var sel   = document.getElementById('cca-sel-' + courseId);
  var msgEl = document.getElementById('cca-msg-' + courseId);
  var pill  = document.getElementById('cca-pill-' + courseId);
  if (!sel) return;
  var tkey  = sel.value;
  var tname = tkey === 'default'
    ? 'Default (diseño global)'
    : ((TPL_LIST.find(function(t){return t.key===tkey;})||{}).name || tkey);
  try {
    var assign = await tplReadAssign();
    if (tkey === 'default') delete assign[courseId];
    else assign[courseId] = tkey;
    var d = await tplWriteAssign(assign);
    if (d && d.success) {
      if (msgEl) { msgEl.style.color='#2e7d32'; msgEl.textContent='✅ Guardado'; setTimeout(function(){msgEl.textContent='';},3000); }
      if (pill) {
        pill.className = tkey==='default' ? 'tmpl-pill tmpl-pill-default' : 'tmpl-pill';
        pill.innerHTML = (tkey==='default'?'⬜':'📄') + ' ' + esc(tname);
      }
      // Actualizar datos locales
      var course = TPL_COURSES.find(function(c){return c.id===courseId;});
      if (course) { course.template_key=tkey; course.template_name=tname; }
    } else {
      if (msgEl) { msgEl.style.color='#c62828'; msgEl.textContent='✗ Error'; }
    }
  } catch(e) { if (msgEl) { msgEl.style.color='#c62828'; msgEl.textContent='✗ Red'; } }
}

/* ── Asignar UNA plantilla a TODOS los cursos en una sola operación atómica ── */
async function assignAllCourses(tkey) {
  if (!tkey) return;
  var tname = tkey === 'default'
    ? 'diseño global'
    : ((TPL_LIST.find(function(t){return t.key===tkey;})||{}).name || tkey);
  if (!confirm('¿Asignar la plantilla "' + tname + '" a TODOS los cursos?\nEsto reemplazará las asignaciones individuales de cada curso.')) return;

  var btn = document.getElementById('assign-all-btn');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Asignando...'; }

  try {
    // Construir objeto de asignaciones nuevo con TODOS los cursos → tkey
    var assign = {};
    TPL_COURSES.forEach(function(c) {
      if (tkey === 'default') {
        // 'default' = sin asignación explícita → no guardamos la entrada
      } else {
        assign[c.id] = tkey;
      }
    });

    var d = await tplWriteAssign(assign);
    if (d && d.success) {
      var nm = document.getElementById('tpl-manager-msg');
      if (nm) {
        nm.className = 'msg msg-ok';
        nm.innerHTML = '✅ Plantilla <strong>' + esc(tname) + '</strong> asignada a todos los cursos (' + TPL_COURSES.length + ').';
        setTimeout(function(){nm.textContent='';nm.className='';}, 6000);
      }
      // Recargar UI para reflejar los cambios
      await loadCertCourseAssignments();
    } else {
      alert('Error al asignar: ' + ((d && d.message) || 'desconocido'));
    }
  } catch(e) {
    alert('Error de red: ' + e.message);
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '📋 Asignar a todos'; }
  }
}

/* ── MODAL: crear / renombrar / duplicar ── */
function openCreateTpl() {
  TPL_MODAL_MODE = 'create';
  TPL_MODAL_KEY  = '';
  document.getElementById('tpl-modal-title').textContent = '➕ Nueva plantilla';
  document.getElementById('tpl-modal-name').value = '';
  document.getElementById('tpl-modal-msg').textContent = '';
  // Populate source select
  var src = document.getElementById('tpl-modal-src');
  src.innerHTML = '<option value="">⬜ Diseño en blanco</option><option value="__global__">🌐 Diseño global actual</option>';
  TPL_LIST.forEach(function(t){
    src.innerHTML += '<option value="'+esc(t.key)+'">📄 '+esc(t.name)+'</option>';
  });
  document.getElementById('tpl-modal-src-wrap').style.display = '';
  document.getElementById('tpl-modal').style.display = 'flex';
  document.getElementById('tpl-modal-name').focus();
}
function openRenameTpl(key, currentName) {
  TPL_MODAL_MODE = 'rename';
  TPL_MODAL_KEY  = key;
  document.getElementById('tpl-modal-title').textContent = '📝 Renombrar plantilla';
  document.getElementById('tpl-modal-name').value = currentName;
  document.getElementById('tpl-modal-msg').textContent = '';
  document.getElementById('tpl-modal-src-wrap').style.display = 'none';
  document.getElementById('tpl-modal').style.display = 'flex';
  document.getElementById('tpl-modal-name').focus();
}
function openDuplicateTpl(key) {
  TPL_MODAL_MODE = 'duplicate';
  TPL_MODAL_KEY  = key;
  var original = (TPL_LIST.find(function(t){return t.key===key;})||{}).name || key;
  document.getElementById('tpl-modal-title').textContent = '📋 Duplicar plantilla';
  document.getElementById('tpl-modal-name').value = original + ' (copia)';
  document.getElementById('tpl-modal-msg').textContent = '';
  document.getElementById('tpl-modal-src-wrap').style.display = 'none';
  document.getElementById('tpl-modal').style.display = 'flex';
  document.getElementById('tpl-modal-name').focus();
}
function closeTplModal() {
  document.getElementById('tpl-modal').style.display = 'none';
}
// Cerrar modal al hacer clic en el fondo
document.addEventListener('DOMContentLoaded', function(){
  var bg = document.getElementById('tpl-modal');
  if (bg) bg.addEventListener('click', function(e){ if(e.target===bg) closeTplModal(); });
  // Enter en el input = confirmar
  var inp = document.getElementById('tpl-modal-name');
  if (inp) inp.addEventListener('keydown', function(e){ if(e.key==='Enter') confirmTplModal(); });
});

async function confirmTplModal() {
  var name   = document.getElementById('tpl-modal-name').value.trim();
  var msgEl  = document.getElementById('tpl-modal-msg');
  if (!name) { msgEl.style.color='#c62828'; msgEl.textContent='Escribe un nombre'; return; }
  msgEl.style.color = 'var(--ts)'; msgEl.textContent = '⏳ Guardando...';

  if (TPL_MODAL_MODE === 'create') {
    var srcKey = document.getElementById('tpl-modal-src').value;
    var srcConfig = {};
    if (srcKey === '__global__') {
      // Copiar config global actual del editor
      srcConfig = (typeof VK_CERT !== 'undefined') ? Object.assign({}, VK_CERT.cfg) : {};
    } else if (srcKey) {
      var found = TPL_LIST.find(function(t){return t.key===srcKey;});
      if (found) srcConfig = Object.assign({}, found.config||{});
    }
    try {
      var list = await tplReadAll();
      var slug = tplSlug(name);
      var base = slug; var i2 = 2;
      while (list.find(function(t){return t.key===slug;})) slug = base+'_'+i2++;
      var now = new Date().toISOString();
      list.push({key:slug, name:name, config:srcConfig||{}, thumb:'', created_at:now});
      await tplWriteAll(list);
      closeTplModal();
      await loadCertCourseAssignments();
      var nm = document.getElementById('tpl-manager-msg');
      if(nm){nm.className='msg msg-ok';nm.innerHTML='✅ Plantilla <strong>'+esc(name)+'</strong> creada.';setTimeout(function(){if(nm){nm.textContent='';nm.className='';}},5000);}
    } catch(e) {
      msgEl.style.color='#c62828'; msgEl.textContent='✗ '+e.message;
    }

  } else if (TPL_MODAL_MODE === 'rename') {
          try {
        var list2 = await tplReadAll();
        var t2 = list2.find(function(t){return t.key===TPL_MODAL_KEY;});
        if (t2) t2.name = name;
        await tplWriteAll(list2);
        closeTplModal(); await loadCertCourseAssignments();
        var nm2 = document.getElementById('tpl-manager-msg');
        if(nm2){nm2.className='msg msg-ok';nm2.innerHTML='✅ Renombrada a <strong>'+esc(name)+'</strong>';setTimeout(function(){if(nm2){nm2.textContent='';nm2.className='';}},5000);}
      } catch(e) { msgEl.style.color='#c62828'; msgEl.textContent='✗ '+e.message; }
  } else if (TPL_MODAL_MODE === 'duplicate') {
    try {
      var list3 = await tplReadAll();
      var src3 = list3.find(function(t){return t.key===TPL_MODAL_KEY;});
      if (!src3) throw new Error('Plantilla original no encontrada');
      var slug3 = tplSlug(name); var base3=slug3; var i3=2;
      while(list3.find(function(t){return t.key===slug3;})) slug3=base3+'_'+i3++;
      var now3 = new Date().toISOString();
      list3.push({key:slug3,name:name,config:JSON.parse(JSON.stringify(src3.config||{})),thumb:src3.thumb||'',created_at:now3});
      await tplWriteAll(list3);
      closeTplModal(); await loadCertCourseAssignments();
      var dm = document.getElementById('tpl-manager-msg');
      if(dm){dm.className='msg msg-ok';dm.innerHTML='✅ Duplicada como <strong>'+esc(name)+'</strong>';setTimeout(function(){if(dm){dm.textContent='';dm.className='';}},5000);}
    } catch(e) { msgEl.style.color='#c62828'; msgEl.textContent='✗ '+e.message; }
  }

}

async function deleteTpl(key, name) {
  if (!confirm('¿Eliminar "' + name + '"? Esta acción no se puede deshacer.')) return;
  var m = document.getElementById('tpl-manager-msg');
  try {
    var list   = await tplReadAll();
    var assign = await tplReadAssign();
    var inUse  = Object.values(assign).filter(function(v){return v===key;}).length;
    if (inUse > 0) { alert('Asignada a '+inUse+' curso(s). Reasígnalos primero.'); return; }
    await tplWriteAll(list.filter(function(t){return t.key!==key;}));
    await loadCertCourseAssignments();
    var m2 = document.getElementById('tpl-manager-msg');
    if(m2){m2.className='msg msg-ok';m2.textContent='✅ Eliminada: '+name;setTimeout(function(){m2.textContent='';m2.className='';},4000);}
  } catch(e) {
    if(m){m.className='msg msg-err';m.textContent='✗ '+e.message;}
  }
}

async function cleanDuplicates() {
  if (!confirm('Eliminar plantillas con nombre duplicado (conserva la más reciente). ¿Continuar?')) return;
  var m = document.getElementById('tpl-manager-msg');
  try {
    var list   = await tplReadAll();
    var assign = await tplReadAssign();
    var inUse  = Object.values(assign);
    var seen = {}, toKeep = [];
    list.slice().sort(function(a,b){return (b.created_at||'').localeCompare(a.created_at||'');})
      .forEach(function(t){
        var k=t.name.trim().toLowerCase();
        if(!seen[k]||inUse.indexOf(t.key)>=0){seen[k]=true;toKeep.push(t);}
      });
    if(toKeep.length===list.length){
      if(m){m.className='msg msg-ok';m.textContent='✅ Sin duplicados.';setTimeout(function(){m.textContent='';m.className='';},3000);}
      return;
    }
    await tplWriteAll(toKeep);
    await loadCertCourseAssignments();
    var m2=document.getElementById('tpl-manager-msg');
    if(m2){m2.className='msg msg-ok';m2.textContent='✅ '+(list.length-toKeep.length)+' duplicado(s) eliminado(s).';setTimeout(function(){m2.textContent='';m2.className='';},5000);}
  } catch(e) {
    if(m){m.className='msg msg-err';m.textContent='✗ '+e.message;}
  }
}


/* ── AI CHAT PREMIUM ─────────────────────────────────── */
/* ═══════════════════════════════════════════════════════
   AI CHAT ADMIN — funciones del panel
═══════════════════════════════════════════════════════ */
var AC_ALL_USERS = [];
var _acSearchTimer = null;

/* ── Cargar panel completo ──────────────────────────── */
async function loadAiChatPanel() {
  try {
    var r = await fetch(API + '/aichat-product?vk_token=' + encodeURIComponent(TOK));
    var d = await r.json();
    if (r.ok && d.product) {
      var p = d.product;
      document.getElementById('acp-name').value    = p.name           || 'AI Chat Premium';
      document.getElementById('acp-desc').value    = p.description    || '';
      document.getElementById('acp-price').value   = p.price          || '';
      document.getElementById('acp-url').value         = p.payment_url  || '';
      document.getElementById('acp-contact-url').value = p.contact_url || '';
      document.getElementById('acp-image').value       = p.image       || '';
      document.getElementById('acp-status').value  = p.status         || 'active';
      document.getElementById('acp-woo-id').value  = p.woo_product_id || '';
      // Agente
      if (p.agent_shortcode) document.getElementById('acp-shortcode').value  = p.agent_shortcode;
      if (p.agent_name)      document.getElementById('acp-agent-name').value = p.agent_name;
      // Previews
      acpImgPreview(p.image || '');
      acpStatusPreview(p.status || 'active');
    }
  } catch(e) {}
  loadAiChatUsers();
}

/* ── Guardar configuración del producto ─────────────── */
async function saveAiChatProduct() {
  var body = {
    vk_token:      TOK,
    name:          document.getElementById('acp-name').value.trim(),
    description:   document.getElementById('acp-desc').value.trim(),
    price:         document.getElementById('acp-price').value.trim(),
    payment_url:   document.getElementById('acp-url').value.trim(),
    contact_url:   document.getElementById('acp-contact-url').value.trim(),
    image:         document.getElementById('acp-image').value.trim(),
    status:        document.getElementById('acp-status').value,
    woo_product_id:document.getElementById('acp-woo-id').value.trim()
  };
  try {
    var r = await fetch(API + '/aichat-product?vk_token=' + encodeURIComponent(TOK),
      {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
    var d = await r.json();
    showMsg('acp-msg', r.ok && d.success ? '✅ Configuración guardada' : '✗ ' + (d.message || 'Error'),
      r.ok && d.success ? 'ok' : 'err');
    acpStatusPreview(body.status);
  } catch(e) { showMsg('acp-msg', 'Error de conexión', 'err'); }
}

/* ── Guardar agente IA (shortcode + nombre) ─────────── */
async function saveAiChatAgent() {
  var shortcode = document.getElementById('acp-shortcode').value.trim();
  var agentName = document.getElementById('acp-agent-name').value.trim();
  if (!shortcode) {
    showMsg('acp-agent-msg', '✗ Ingresa el shortcode del chatbot', 'err'); return;
  }
  // Guardar como parte del producto (campo extra) via aichat-product endpoint
  var body = { vk_token: TOK, agent_shortcode: shortcode, agent_name: agentName || 'Asistente VidaKushala' };
  try {
    var r = await fetch(API + '/aichat-product?vk_token=' + encodeURIComponent(TOK),
      {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
    var d = await r.json();
    showMsg('acp-agent-msg', r.ok && d.success ? '✅ Agente guardado' : '✗ ' + (d.message || 'Error'),
      r.ok && d.success ? 'ok' : 'err');
  } catch(e) { showMsg('acp-agent-msg', '✗ Error de conexión', 'err'); }
}

/* ── Previews de UI ─────────────────────────────────── */
function acpImgPreview(url) {
  var img = document.getElementById('acp-img-tag');
  if (!img) return;
  if (url && url.startsWith('http')) {
    img.src = url; img.style.display = 'inline-block';
    img.onerror = function(){ img.style.display = 'none'; };
  } else { img.style.display = 'none'; }
}

/* ── Subir imagen desde dispositivo → endpoint propio ─── */
function acpOpenMediaLibrary() {
  var msgEl = document.getElementById('acp-img-msg');
  var input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/png,image/jpeg,image/webp,image/gif';
  input.onchange = function(e) {
    var file = e.target.files[0];
    if (!file) return;
    // Validar tamaño (máx 5 MB)
    if (file.size > 5 * 1024 * 1024) {
      if (msgEl) msgEl.textContent = '✗ La imagen no puede superar 5 MB';
      return;
    }
    if (msgEl) { msgEl.textContent = '⏳ Subiendo imagen…'; msgEl.style.color = 'var(--tu)'; }
    var fd = new FormData();
    fd.append('image', file);
    fd.append('vk_token', TOK);
    fetch(API + '/aichat-upload-image?vk_token=' + encodeURIComponent(TOK), {
      method: 'POST',
      body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success && data.url) {
        document.getElementById('acp-image').value = data.url;
        acpImgPreview(data.url);
        if (msgEl) {
          msgEl.textContent = '✅ Imagen subida correctamente';
          msgEl.style.color = '#2e7d32';
          setTimeout(function(){ msgEl.textContent = ''; }, 4000);
        }
      } else {
        if (msgEl) { msgEl.textContent = '✗ ' + (data.message || 'Error al subir'); msgEl.style.color = '#c62828'; }
      }
    })
    .catch(function() {
      if (msgEl) { msgEl.textContent = '✗ Error de red. Intenta de nuevo.'; msgEl.style.color = '#c62828'; }
    });
  };
  input.click();
}
function acpStatusPreview(val) {
  var el = document.getElementById('ac-stat-status');
  if (!el) return;
  val = val || (document.getElementById('acp-status') || {}).value || '';
  el.textContent = val === 'active' ? '✅ Activo' : '🔴 Inactivo';
  el.style.color = val === 'active' ? '#2e7d32' : '#c62828';
}

/* ── Cargar usuarios ─────────────────────────────────── */
async function loadAiChatUsers() {
  var el = document.getElementById('aichat-users-body');
  el.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--tu)">Cargando…</div>';
  try {
    var [r1, r2] = await Promise.all([
      fetch(API + '/aichat-users?vk_token=' + encodeURIComponent(TOK)),
      fetch(API + '/push-subscribers?vk_token=' + encodeURIComponent(TOK))
    ]);
    var d1 = await r1.json();
    var d2 = await r2.json();

    var accessUsers = d1.users || [];
    var subscribers = d2.data  || [];

    // Mapa de acceso por email
    var accessMap = {};
    accessUsers.forEach(function(u) {
      accessMap[u.email.toLowerCase()] = { date: u.granted_date, expiry: u.expiry || null, id: u.id };
    });

    // Combinar: suscriptores primero
    var combined = [];
    var seenEmails = {};
    subscribers.forEach(function(u) {
      var email = (u.user_email || '').toLowerCase();
      if (!email || seenEmails[email]) return;
      seenEmails[email] = true;
      var acc = accessMap[email];
      combined.push({
        id:           u.ID || u.id || u.user_id,
        name:         u.display_name || '—',
        email:        u.user_email || '—',
        has_access:   !!acc,
        granted_date: acc ? acc.date   : null,
        expiry:       acc ? acc.expiry : null
      });
    });
    // Agregar usuarios con acceso que no son suscriptores
    accessUsers.forEach(function(u) {
      var email = u.email.toLowerCase();
      if (!seenEmails[email]) {
        seenEmails[email] = true;
        combined.push({
          id: u.id, name: u.display_name || u.email, email: u.email,
          has_access: true, granted_date: u.granted_date, expiry: u.expiry || null
        });
      }
    });

    AC_ALL_USERS = combined;

    // Actualizar stats
    var total  = combined.length;
    var active = combined.filter(function(u){ return u.has_access; }).length;
    var s = document.getElementById('ac-stat-total');   if(s) s.textContent = total;
    var a = document.getElementById('ac-stat-active');  if(a) a.textContent = active;
    var i = document.getElementById('ac-stat-inactive');if(i) i.textContent = total - active;

    renderAcTable(combined);
  } catch(e) {
    el.innerHTML = '<p style="color:#c62828;padding:1rem">✗ Error al cargar: ' + esc(e.message) + '</p>';
  }
}

/* ── Render de tabla ─────────────────────────────────── */
var _acCurrentList = [];
function renderAcTable(list) { _acCurrentList = list; filterAcTable(); }

function acSearchDebounce() {
  clearTimeout(_acSearchTimer);
  _acSearchTimer = setTimeout(filterAcTable, 220);
}

function filterAcTable() {
  var q  = ((document.getElementById('ac-search')  || {}).value || '').toLowerCase();
  var f  = ((document.getElementById('ac-filter')  || {}).value || '');
  var filtered = _acCurrentList.filter(function(u) {
    var matchQ = !q || (u.name + ' ' + u.email).toLowerCase().includes(q);
    var matchF = !f || (f === 'active' && u.has_access) || (f === 'inactive' && !u.has_access);
    return matchQ && matchF;
  });

  var el = document.getElementById('aichat-users-body');
  if (!el) return;

  if (!filtered.length) {
    el.innerHTML = '<p style="text-align:center;padding:2rem;color:var(--tu)">Sin resultados.</p>';
    return;
  }

  var total  = filtered.length;
  var active = filtered.filter(function(u){ return u.has_access; }).length;

  var html = '<p style="font-size:.79rem;color:var(--tu);margin-bottom:.75rem">'
    + total + ' usuario(s) — <span style="color:#2e7d32;font-weight:700">' + active + ' con acceso</span>'
    + ' / <span style="color:#c62828;font-weight:700">' + (total - active) + ' sin acceso</span></p>';

  html += '<table class="ac-table"><thead><tr>'
    + '<th>Usuario</th><th>Email</th><th>Estado</th><th>Desde</th><th>Vence</th><th>Acciones</th>'
    + '</tr></thead><tbody>';

  filtered.forEach(function(u) {
    var badge   = u.has_access
      ? '<span class="badge-on">✅ Activo</span>'
      : '<span class="badge-off">🔴 Sin acceso</span>';
    var dateStr = u.granted_date
      ? '<span style="font-size:.77rem;color:var(--tu)">' + esc(u.granted_date.substring(0,10)) + '</span>'
      : '<span style="color:var(--tu);font-size:.77rem">—</span>';
    var expiryStr = u.expiry
      ? '<span style="font-size:.77rem;color:var(--tu)">' + esc(String(u.expiry).substring(0,10)) + '</span>'
      : '<span style="color:var(--tu);font-size:.77rem">—</span>';

    var btns = u.has_access
      ? '<button class="btn btn-secondary btn-sm" onclick="revokeAiChat(' + u.id + ',\'' + esc(u.email) + '\')" title="Revocar acceso" style="margin-right:.3rem">❌ Revocar</button>'
        + '<button class="btn btn-outline btn-sm" onclick="renewAiChat(' + u.id + ',\'' + esc(u.email) + '\')" title="Renovar acceso">🔄</button>'
      : '<button class="btn btn-primary btn-sm" onclick="grantAiChatById(' + u.id + ')" title="Dar acceso">✅ Dar acceso</button>';

    html += '<tr>'
      + '<td style="font-weight:700;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(u.name) + '">' + esc(u.name) + '</td>'
      + '<td style="font-size:.8rem;color:var(--ts)">' + esc(u.email) + '</td>'
      + '<td>' + badge + '</td>'
      + '<td>' + dateStr + '</td>'
      + '<td>' + expiryStr + '</td>'
      + '<td style="white-space:nowrap">' + btns + '</td>'
      + '</tr>';
  });

  html += '</tbody></table>';
  el.innerHTML = html;
}

/* ── Dar acceso por email ────────────────────────────── */
/* ── Búsqueda en tiempo real de usuarios WordPress ─── */
var _acGrantTimer = null;
var _acGrantUID   = null;

function acGrantDebounce() {
  clearTimeout(_acGrantTimer);
  _acGrantUID = null;
  var preview = document.getElementById('acp-grant-preview');
  if (preview) preview.style.display = 'none';
  var q = (document.getElementById('acp-grant-email') || {}).value || '';
  if (q.trim().length < 2) {
    var drop = document.getElementById('acp-grant-drop');
    if (drop) drop.style.display = 'none';
    return;
  }
  _acGrantTimer = setTimeout(acGrantSearch, 320);
}

async function acGrantSearch() {
  var input = document.getElementById('acp-grant-email');
  var drop  = document.getElementById('acp-grant-drop');
  if (!input || !drop) return;
  var q = input.value.trim();
  if (q.length < 2) { drop.style.display = 'none'; return; }
  drop.style.display = 'block';
  drop.innerHTML = '<div style="padding:.55rem 1rem;color:var(--tu);font-size:.82rem">Buscando...</div>';
  try {
    var r = await fetch(API + '/aichat-search-users?vk_token=' + encodeURIComponent(TOK) + '&q=' + encodeURIComponent(q));
    var d = await r.json();
    if (!r.ok) { drop.innerHTML = '<div style="padding:.6rem 1rem;color:#c62828;font-size:.82rem">' + esc(d.message||'Error') + '</div>'; return; }
    if (!d.users || d.users.length === 0) {
      drop.innerHTML = '<div style="padding:.75rem 1rem;font-size:.82rem;color:var(--tu)">Ningun usuario con <strong>' + esc(q) + '</strong>.</div>';
      return;
    }
    var html = '';
    for (var gi = 0; gi < d.users.length; gi++) {
      var gu = d.users[gi];
      var gba = gu.has_access ? 'Con acceso' : 'Sin acceso';
      var gbc = gu.has_access ? '#2e7d32' : '#c62828';
      var gbg = gu.has_access ? '#e8f5e9' : '#ffebee';
      html += '<div data-uid="' + gu.id + '" data-email="' + esc(gu.email) + '" data-name="' + esc(gu.display_name) + '" data-access="' + (gu.has_access?'1':'0') + '" onclick="acGrantPickEl(this)" style="padding:.5rem .85rem;cursor:pointer;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.5rem;font-size:.83rem">';
      html += '<div style="min-width:0"><div style="font-weight:600">' + esc(gu.display_name) + ' <span style="font-size:.7rem;background:' + gbg + ';color:' + gbc + ';border-radius:4px;padding:.1rem .35rem">' + gba + '</span></div>';
      html += '<div style="color:var(--tu);font-size:.77rem">' + esc(gu.email) + '</div></div></div>';
    }
    drop.innerHTML = html;
  } catch(ge) {
    drop.innerHTML = '<div style="padding:.6rem 1rem;color:#c62828;font-size:.82rem">Error: ' + esc(ge.message) + '</div>';
  }
}

function acGrantPickEl(el) {
  acGrantPick(parseInt(el.getAttribute('data-uid'),10), el.getAttribute('data-email'), el.getAttribute('data-name'), el.getAttribute('data-access')==='1');
}

function acGrantPick(uid, email, name, hasAccess) {
  _acGrantUID = uid;
  var input = document.getElementById('acp-grant-email');
  var drop  = document.getElementById('acp-grant-drop');
  var prev  = document.getElementById('acp-grant-preview');
  if (input) input.value = email;
  if (drop)  drop.style.display = 'none';
  if (prev) {
    prev.style.display = 'flex';
    prev.innerHTML = '<strong>' + esc(name) + '</strong> &nbsp; <span style="color:var(--tu)">' + esc(email) + '</span>'
      + (hasAccess ? ' &nbsp; <span style="font-size:.77rem;background:#e8f5e9;color:#2e7d32;border-radius:6px;padding:.15rem .5rem">Ya tiene acceso</span>' : '');
  }
}

document.addEventListener('click', function(e) {
  var drop = document.getElementById('acp-grant-drop');
  var inp  = document.getElementById('acp-grant-email');
  if (drop && inp && !inp.contains(e.target) && !drop.contains(e.target)) drop.style.display = 'none';
});

async function grantAiChatByEmail() {
  var input  = document.getElementById('acp-grant-email');
  var expiry = (document.getElementById('acp-grant-expiry') || {}).value || '';
  var msgEl  = document.getElementById('acp-grant-msg');
  var drop   = document.getElementById('acp-grant-drop');
  var email  = input ? input.value.trim() : '';

  if (!email) { msgEl.style.color='#c62828'; msgEl.textContent='Escribe el email o nombre del usuario'; return; }
  if (drop) drop.style.display = 'none';

  // ── Caso A: ya seleccionó del dropdown → usar ID directo ──
  if (_acGrantUID) {
    msgEl.style.color = 'var(--tu)';
    msgEl.textContent = '⏳ Otorgando acceso...';
    await doGrantAiChat(_acGrantUID, expiry, msgEl);
    if (input) input.value = '';
    if (document.getElementById('acp-grant-expiry')) document.getElementById('acp-grant-expiry').value = '';
    var preview = document.getElementById('acp-grant-preview');
    if (preview) preview.style.display = 'none';
    _acGrantUID = null;
    return;
  }

  // ── Caso B: email escrito manual → buscar en WordPress ──
  msgEl.style.color = 'var(--tu)';
  msgEl.textContent = '🔍 Buscando "' + email + '" en WordPress...';

  try {
    var r = await fetch(API + '/aichat-find-user?vk_token=' + encodeURIComponent(TOK)
      + '&email=' + encodeURIComponent(email));
    var d = await r.json();

    if (!r.ok || !d.success) {
      msgEl.style.color = '#c62828';
      var hint = '';
      if (d.data && Array.isArray(d.data) && d.data.length)
        hint = '  ¿Quisiste decir: ' + d.data.join(', ') + '?';
      msgEl.textContent = '✗ ' + (d.message || 'Usuario no encontrado en WordPress') + hint;
      return;
    }

    msgEl.style.color = 'var(--tu)';
    msgEl.textContent = '✅ Usuario encontrado: ' + d.display_name + ' — otorgando acceso...';
    await doGrantAiChat(d.id, expiry, msgEl);
    if (input) input.value = '';
    if (document.getElementById('acp-grant-expiry')) document.getElementById('acp-grant-expiry').value = '';
    var preview2 = document.getElementById('acp-grant-preview');
    if (preview2) preview2.style.display = 'none';

  } catch(e) {
    msgEl.style.color = '#c62828';
    msgEl.textContent = '✗ Error de red: ' + e.message;
  }
}

/* ── Dar acceso por ID ───────────────────────────────── */
async function grantAiChatById(uid) {
  await doGrantAiChat(uid, '', null);
  loadAiChatUsers();
}

/* ── Lógica compartida de conceder acceso ────────────── */
async function doGrantAiChat(uid, expiry, msgEl) {
  try {
    var body = { vk_token: TOK, user_id: uid };
    if (expiry) body.expiry = expiry;
    var r = await fetch(API + '/aichat-grant?vk_token=' + encodeURIComponent(TOK),
      {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
    var d = await r.json();
    var ok = r.ok && d.success;
    if (msgEl) {
      msgEl.style.color = ok ? '#2e7d32' : '#c62828';
      msgEl.textContent = ok ? '✅ Acceso concedido correctamente' : '✗ ' + (d.message || 'Error');
      setTimeout(function(){ if(msgEl) msgEl.textContent=''; }, 3500);
    }
    if (ok) loadAiChatUsers();
  } catch(e) {
    if (msgEl) { msgEl.style.color='#c62828'; msgEl.textContent='✗ Error de red'; }
  }
}

/* ── Revocar acceso ──────────────────────────────────── */
async function revokeAiChat(uid, email) {
  if (!confirm('¿Revocar acceso al AI Chat para ' + email + '?')) return;
  try {
    var r = await fetch(API + '/aichat-revoke?vk_token=' + encodeURIComponent(TOK),
      {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({user_id:uid})});
    var d = await r.json();
    showMsg('acp-msg', r.ok && d.success ? '✅ Acceso revocado' : '✗ Error', r.ok && d.success ? 'ok' : 'err');
    if (r.ok && d.success) loadAiChatUsers();
  } catch(e) { showMsg('acp-msg','✗ Error de red','err'); }
}

/* ── Renovar acceso ──────────────────────────────────── */
async function renewAiChat(uid, email) {
  if (!confirm('¿Renovar acceso para ' + email + '?')) return;
  await doGrantAiChat(uid, '', null);
  showMsg('acp-msg','✅ Acceso renovado','ok');
}


async function saveKey(){
  var key=document.getElementById('cfg-key').value.trim();
  if(!key){showMsg('cfg-msg','Pega la REST API Key primero','err');return;}
  // Validación básica de formato OneSignal REST API Key
  if(key.length < 20){showMsg('cfg-msg','La key parece demasiado corta. Verifica que copiaste la REST API Key completa.','err');return;}
  var btn = event && event.target ? event.target : document.querySelector('[onclick="saveKey()"]');
  var origText = btn ? btn.innerHTML : '';
  if(btn){ btn.disabled=true; btn.innerHTML='⏳ Guardando...'; }
  try{
    var r=await fetch(API+'/push-save-key',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({vk_token:TOK,rest_api_key:key})});
    var d=await r.json();
    if(r.ok&&d.success){
      var preview = key.substring(0,8)+'...'+key.substring(key.length-4);
      showMsg('cfg-msg','✅ REST API Key guardada correctamente ('+preview+')','ok');
      document.getElementById('cfg-key').value='';
      loadStats(); // actualizar el indicador de key
    } else {
      showMsg('cfg-msg','✗ '+(d.message||'Error al guardar'),'err');
    }
  }catch(e){showMsg('cfg-msg','✗ Error de conexión: '+e.message,'err');}
  if(btn){ btn.disabled=false; btn.innerHTML=origText||'💾 Guardar key'; }
}
async function checkSW(){
  var el=document.getElementById('sw-info');var html='';
  try{var r=await fetch('/OneSignalSDKWorker.js');html+='<div class="msg msg-ok">✅ OneSignalSDKWorker.js disponible (HTTP '+r.status+')</div>';}
  catch(e){html+='<div class="msg msg-err">✗ OneSignalSDKWorker.js no encontrado</div>';}
  if('serviceWorker' in navigator){
    var regs=await navigator.serviceWorker.getRegistrations();
    if(regs.length)regs.forEach(function(reg){html+='<div class="msg msg-ok">✅ SW: '+reg.scope+'</div>';});
    else html+='<div class="msg msg-info">ℹ️ Service Worker no registrado aún</div>';
  }
  el.innerHTML=html;
}

/* ── HELPERS ─────────────────────────────────────────── */
function showMsg(id,msg,type){
  var el=document.getElementById(id);if(!el)return;
  el.className='msg msg-'+type;el.innerHTML=msg;
  if(type!=='err')setTimeout(function(){el.textContent='';el.className='';},5000);
}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
</script>
</body>
</html>
