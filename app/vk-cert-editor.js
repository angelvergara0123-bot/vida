// ACTUALIZADO: 2026-06-03
/**
 * vk-cert-editor.js — Editor de Certificados v4.0
 * VidaKushala Panel de Administración
 *
 * - Plantillas prediseñadas con preview en canvas (sin imágenes externas)
 * - Imagen de fondo: subir desde PC → se guarda como base64 en la config
 * - Controles reactivos en tiempo real
 * - Sin botones de arrastre que no funcionen
 */

'use strict';

// ─── Estado global ────────────────────────────────────────────────
var VK_CERT = {
  cfg:     {},
  bgImg:   null,   // HTMLImageElement del fondo actual
  logoImg: null,
  sigImg:  null,
  dirty:   false,
  timer:   null
};

// ─── Plantillas prediseñadas (sin URLs de servidor) ───────────────
var VK_TEMPLATES = [
  {
    id: 'vk-rosa',
    name: '🌸 Rosa VidaKushala',
    cfg: {
      bg_type: 'gradient', bg_gradient: true,
      bg_gradient_from: '#fff0f5', bg_gradient_to: '#ffe4f0',
      border_enabled: true, border_color: '#c44d8a', border_width: 20,
      header_text: 'DIPLOMA DE FINALIZACIÓN', header_color: '#6f102a',
      header_font_size: 36, header_y: 105, header_line: true,
      subheader_text: 'VIDA KUSHALA · FORMACIÓN INTEGRAL', subheader_color: '#c44d8a',
      subheader_font_size: 12, subheader_y: 155,
      name_color: '#6f102a', name_font_size: 46, name_y: 310,
      name_align: 'center', name_italic: true, name_underline: true,
      has_completed_text: true,
      completed_text: 'Por completar con éxito el curso:',
      completed_color: '#4a4a4a', completed_font_size: 13, completed_y: 385,
      title_color: '#1a2e5a', title_font_size: 20, title_y: 415, title_align: 'center',
      divider_enabled: false, divider_y: 498,
      date_x: 90, date_y: 545, date_font_size: 11, date_color: '#888',
      cert_id_x: 90, cert_id_y: 560, cert_id_font_size: 9, cert_id_color: '#bbb',
      signature_x: 745, signature_y: 625, signature_line_w: 182,
      signature_label: '', signature_role: '',
      qr_enabled: true, qr_x_from_right: 45, qr_y_from_bottom: 75, qr_size: 73,
      watermark_text: ''
    }
  },
  {
    id: 'vk-elegante',
    name: '✨ Elegante Dorado',
    cfg: {
      bg_type: 'color', bg_color: '#faf8f2',
      border_enabled: true, border_color: '#8b6914', border_width: 22,
      header_text: 'CERTIFICADO DE LOGRO', header_color: '#5c4000',
      header_font_size: 36, header_y: 105, header_line: true,
      subheader_text: 'EDUCACIÓN · CRECIMIENTO · BIENESTAR', subheader_color: '#8b6914',
      subheader_font_size: 12, subheader_y: 155,
      name_color: '#5c4000', name_font_size: 48, name_y: 315,
      name_align: 'center', name_italic: true, name_underline: true,
      has_completed_text: true,
      completed_text: 'Por completar con éxito el programa:',
      completed_color: '#6b5100', completed_font_size: 13, completed_y: 395,
      title_color: '#5c4000', title_font_size: 20, title_y: 430, title_align: 'center',
      divider_enabled: false, divider_y: 510,
      date_x: 90, date_y: 555, date_font_size: 11, date_color: '#8b6914',
      cert_id_x: 90, cert_id_y: 570, cert_id_font_size: 9, cert_id_color: '#b8a060',
      signature_x: 740, signature_y: 635, signature_line_w: 190,
      signature_label: '', signature_role: '',
      qr_enabled: true, qr_x_from_right: 45, qr_y_from_bottom: 75, qr_size: 75,
      watermark_text: ''
    }
  },
  {
    id: 'vk-moderno',
    name: '🔵 Azul Corporativo',
    cfg: {
      bg_type: 'gradient', bg_gradient: true,
      bg_gradient_from: '#0f2044', bg_gradient_to: '#1a3a6b',
      border_enabled: true, border_color: '#4a90d9', border_width: 18,
      header_text: 'CERTIFICADO DE FINALIZACIÓN', header_color: '#ffffff',
      header_font_size: 32, header_y: 105, header_line: true,
      subheader_text: 'VIDA KUSHALA · EXCELENCIA ACADÉMICA', subheader_color: '#90b8e8',
      subheader_font_size: 12, subheader_y: 155,
      name_color: '#ffd700', name_font_size: 44, name_y: 305,
      name_align: 'center', name_italic: true, name_underline: false,
      has_completed_text: true,
      completed_text: 'Por haber completado el curso:',
      completed_color: '#c8daf0', completed_font_size: 12, completed_y: 375,
      title_color: '#ffffff', title_font_size: 19, title_y: 408, title_align: 'center',
      divider_enabled: false, divider_y: 490,
      date_x: 90, date_y: 540, date_font_size: 11, date_color: '#90b8e8',
      cert_id_x: 90, cert_id_y: 556, cert_id_font_size: 9, cert_id_color: '#6090c0',
      signature_x: 740, signature_y: 625, signature_line_w: 185,
      signature_label: '', signature_role: '',
      qr_enabled: true, qr_x_from_right: 45, qr_y_from_bottom: 75, qr_size: 72,
      watermark_text: ''
    }
  },
  {
    id: 'vk-minimalista',
    name: '⬜ Minimalista Blanco',
    cfg: {
      bg_type: 'color', bg_color: '#ffffff',
      border_enabled: true, border_color: '#1a1a1a', border_width: 12,
      header_text: 'CERTIFICADO', header_color: '#1a1a1a',
      header_font_size: 42, header_y: 110, header_line: true,
      subheader_text: 'CERTIFICA QUE LA SIGUIENTE PERSONA', subheader_color: '#666666',
      subheader_font_size: 11, subheader_y: 158,
      name_color: '#1a1a1a', name_font_size: 50, name_y: 310,
      name_align: 'center', name_italic: false, name_underline: true,
      has_completed_text: true,
      completed_text: 'ha completado satisfactoriamente',
      completed_color: '#555555', completed_font_size: 13, completed_y: 385,
      title_color: '#333333', title_font_size: 19, title_y: 415, title_align: 'center',
      divider_enabled: false,
      date_x: 90, date_y: 545, date_font_size: 11, date_color: '#555',
      cert_id_x: 90, cert_id_y: 560, cert_id_font_size: 9, cert_id_color: '#999',
      signature_x: 750, signature_y: 620, signature_line_w: 175,
      signature_label: '', signature_role: '',
      qr_enabled: true, qr_x_from_right: 45, qr_y_from_bottom: 75, qr_size: 70,
      watermark_text: ''
    }
  },
  {
    id: 'vk-verde',
    name: '🌿 Verde Natural',
    cfg: {
      bg_type: 'gradient', bg_gradient: true,
      bg_gradient_from: '#f0f7f0', bg_gradient_to: '#e0f0e8',
      border_enabled: true, border_color: '#2e7d4f', border_width: 18,
      header_text: 'CERTIFICADO DE EXCELENCIA', header_color: '#1b5e34',
      header_font_size: 34, header_y: 105, header_line: true,
      subheader_text: 'VIDA KUSHALA · APRENDIZAJE Y CRECIMIENTO',
      subheader_color: '#2e7d4f', subheader_font_size: 11, subheader_y: 155,
      name_color: '#1b5e34', name_font_size: 46, name_y: 308,
      name_align: 'center', name_italic: true, name_underline: true,
      has_completed_text: true,
      completed_text: 'Por completar con dedicación el curso:',
      completed_color: '#3a5a3a', completed_font_size: 12, completed_y: 382,
      title_color: '#1b5e34', title_font_size: 19, title_y: 415, title_align: 'center',
      divider_enabled: false, divider_y: 495,
      date_x: 90, date_y: 542, date_font_size: 11, date_color: '#4a7a5a',
      cert_id_x: 90, cert_id_y: 557, cert_id_font_size: 9, cert_id_color: '#7aaa8a',
      signature_x: 742, signature_y: 622, signature_line_w: 180,
      signature_label: '', signature_role: '',
      qr_enabled: true, qr_x_from_right: 45, qr_y_from_bottom: 75, qr_size: 73,
      watermark_text: ''
    }
  }
];

// ─── Defaults base ────────────────────────────────────────────────
function vkCertDefaults() {
  return Object.assign({}, VK_TEMPLATES[0].cfg, {
    bg_image_url: '',
    bg_image_data: '',      // base64 de imagen subida desde PC
    bg_overlay_opacity: 0,
    logo_enabled: false, logo_url: '', logo_x: 60, logo_y: 40, logo_w: 100,
    signature_img_url: '',
    font_title: 'Georgia',
    font_body: 'Arial',
    header_line: true,
    name_italic: true,
    name_underline: true
  });
}

// ─── INICIALIZAR el editor ────────────────────────────────────────
var _VK_EDITOR_INITED = false;
var _VK_CFG_LOADED    = false; // true once vkLoadCertConfig succeeded with a valid token
function vkCertEditorInit() {
  // Guard: prevent double-init (called both from window.load and onLogin)
  if (_VK_EDITOR_INITED) {
    if (!document.getElementById('vk-cert-canvas')) return;
    // Load global config if it was never loaded (init ran before TOK was ready)
    // but only if the user is NOT currently editing a named template
    var editingTpl = VK_CERT && VK_CERT._editing_tpl_key;
    if (!_VK_CFG_LOADED && !editingTpl && typeof TOK !== 'undefined' && TOK) {
      vkLoadCertConfig().then(function() {
        vkCertFormSync();
        vkCertPreviewRedraw();
      });
    } else {
      // Sync form with current VK_CERT.cfg (global config or the active template config)
      vkCertFormSync();
      vkCertPreviewRedraw();
    }
    return;
  }
  if (!document.getElementById('vk-cert-canvas')) return; // HTML not ready yet
  _VK_EDITOR_INITED = true;
  VK_CERT.cfg = vkCertDefaults();
  vkRenderTemplateGallery();
  vkLoadCertConfig().then(function() {
    vkCertFormSync();
    vkCertPreviewRedraw();
  });
}

// ─── CARGAR configuración desde servidor ──────────────────────────
async function vkLoadCertConfig() {
  if (typeof TOK === 'undefined' || !TOK) return;
  try {
    var r = await fetch(API + '/cert-config?vk_token=' + encodeURIComponent(TOK) + '&_t=' + Date.now());
    var d = await r.json();
    if (d && d.config && Object.keys(d.config).length) {
      VK_CERT.cfg    = Object.assign(vkCertDefaults(), d.config);
      _VK_CFG_LOADED = true;
      // Pre-load bg image so VK_CERT.bgImg is ready before the first redraw
      if (VK_CERT.cfg.bg_type === 'image') {
        var src = VK_CERT.cfg.bg_image_data || VK_CERT.cfg.bg_image_url || '';
        if (src) {
          await new Promise(function(resolve) {
            var img = new Image();
            img.onload  = function() { VK_CERT.bgImg = img; resolve(); };
            img.onerror = function() { resolve(); };
            img.src = src;
          });
        }
      } else {
        VK_CERT.bgImg = null;
      }
      // Pre-load logo image (logo_data has priority; logo_url as fallback)
      var logoSrc = VK_CERT.cfg.logo_data || VK_CERT.cfg.logo_url || '';
      if (VK_CERT.cfg.logo_enabled && logoSrc) {
        await new Promise(function(resolve) {
          var img = new Image();
          img.onload  = function() { VK_CERT.logoImg = img; resolve(); };
          img.onerror = function() { resolve(); };
          img.src = logoSrc;
        });
      } else {
        VK_CERT.logoImg = null;
      }
      // Pre-load signature image
      var sigSrc = VK_CERT.cfg.signature_img_data || VK_CERT.cfg.signature_img_url || '';
      if (sigSrc) {
        await new Promise(function(resolve) {
          var img = new Image();
          img.onload  = function() { VK_CERT.sigImg = img; resolve(); };
          img.onerror = function() { resolve(); };
          img.src = sigSrc;
        });
      } else {
        VK_CERT.sigImg = null;
      }
    }
  } catch(e) { console.warn('vkLoadCertConfig:', e); }
}

// ─── GUARDAR configuración ────────────────────────────────────────
async function vkSaveCertConfig() {
  if (typeof TOK === 'undefined' || !TOK) return;
  var btn = document.getElementById('vk-save-cert-btn');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="vk-spin"></span> Guardando...'; }
  try {
    var payload = Object.assign({}, VK_CERT.cfg, { vk_token: TOK });
    var r = await fetch(API + '/cert-config', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    var d = await r.json();
    if (r.ok && d.success) {
      var savedCfg = Object.assign(vkCertDefaults(), d.config || VK_CERT.cfg);
      VK_CERT.cfg   = savedCfg;
      VK_CERT.dirty = false;
      // Restore bgImg if the server response confirms an image was saved
      if (savedCfg.bg_type === 'image') {
        var imgSrc = savedCfg.bg_image_data || savedCfg.bg_image_url || '';
        if (imgSrc && !VK_CERT.bgImg) {
          // bgImg was lost (e.g. page reload), reload it
          var img = new Image();
          img.onload = function() { VK_CERT.bgImg = img; vkCertPreviewRedraw(); };
          img.src = imgSrc;
        }
      } else {
        VK_CERT.bgImg = null;
      }
      vkCertFormSync();
      // Clear cert cache
      try {
        var cc = await (await fetch(API + '/cert-clear-cache', {
          method: 'POST', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({vk_token: TOK})
        })).json();
        var msg = cc.deleted > 0
          ? '✅ Guardado · ' + cc.deleted + ' certs regenerados'
          : '✅ Configuración guardada correctamente';
        vkEditorMsg(msg, 'ok');
      } catch(e2) {
        vkEditorMsg('✅ Configuración guardada.', 'ok');
      }
    } else {
      vkEditorMsg('❌ Error: ' + (d.message || 'desconocido'), 'err');
    }
  } catch(e) { vkEditorMsg('❌ Error de conexión', 'err'); }
  if (btn) { btn.disabled = false; btn.innerHTML = '💾 Guardar diseño'; }
}

// ─── RENDER GALLERY de plantillas ─────────────────────────────────
function vkRenderTemplateGallery() {
  function makeCard(t) {
    // Preview mini generado con CSS (degradado o color), sin imágenes externas
    var bgStyle;
    if (t.cfg.bg_gradient) {
      bgStyle = 'background:linear-gradient(135deg,' + t.cfg.bg_gradient_from + ',' + t.cfg.bg_gradient_to + ')';
    } else {
      bgStyle = 'background:' + (t.cfg.bg_color || '#f5f5f5');
    }
    var borderStyle = t.cfg.border_enabled
      ? 'border:3px solid ' + t.cfg.border_color
      : 'border:1px solid #ddd';
    var nameColor = t.cfg.name_color || '#333';
    var headerColor = t.cfg.header_color || '#333';

    return '<div class="vk-tmpl-card" id="tmpl-' + t.id + '" onclick="vkApplyTemplate(\'' + t.id + '\')" title="' + t.name + '">'
      + '<div style="width:100%;height:80px;border-radius:6px;margin-bottom:.35rem;' + bgStyle + ';' + borderStyle + ';display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;overflow:hidden;padding:4px">'
      + '<div style="font-size:9px;font-weight:700;color:' + headerColor + ';letter-spacing:1px;text-align:center;line-height:1.1">' + (t.cfg.header_text || 'CERTIFICADO') + '</div>'
      + '<div style="font-size:10px;font-style:italic;color:' + nameColor + ';text-align:center">Nombre del Alumno</div>'
      + '<div style="font-size:7px;color:' + (t.cfg.title_color || '#555') + ';text-align:center;opacity:.8">Nombre del Curso</div>'
      + '</div>'
      + '<div class="vk-tmpl-name">' + t.name + '</div>'
      + '</div>';
  }

  var mini = document.getElementById('vk-mini-tmpl-gallery');
  if (mini) mini.innerHTML = VK_TEMPLATES.map(makeCard).join('');

  var full = document.getElementById('vk-tmpl-gallery');
  if (full) full.innerHTML = VK_TEMPLATES.map(makeCard).join('');
}

// ─── APLICAR plantilla ────────────────────────────────────────────
function vkApplyTemplate(id) {
  var tmpl = VK_TEMPLATES.find(function(t){ return t.id === id; });
  if (!tmpl) return;
  // Conservar imagen de fondo si el usuario ya subió una desde PC
  var savedBgData = VK_CERT.cfg.bg_image_data;
  var savedBgImg  = VK_CERT.bgImg;
  VK_CERT.cfg = Object.assign(vkCertDefaults(), tmpl.cfg);
  if (savedBgData) {
    VK_CERT.cfg.bg_image_data = savedBgData;
    VK_CERT.cfg.bg_image_url  = '';
    VK_CERT.cfg.bg_type       = 'image';
    VK_CERT.bgImg             = savedBgImg;
  } else {
    VK_CERT.bgImg = null;
  }
  document.querySelectorAll('.vk-tmpl-card').forEach(function(el){ el.classList.remove('active'); });
  document.querySelectorAll('#tmpl-' + id).forEach(function(el){ el.classList.add('active'); });
  vkCertFormSync();
  vkCertPreviewRedraw();
  vkEditorMsg('✔ Plantilla "' + tmpl.name + '" aplicada.', 'ok');
}

// ─── SETTER reactivo ──────────────────────────────────────────────
function vkCfgSet(key, val) {
  VK_CERT.cfg[key] = val;
  VK_CERT.dirty = true;
  vkCertPreviewRedraw();
}

// ─── SYNC form → cfg ──────────────────────────────────────────────
function vkCertFormSync() {
  var cfg = VK_CERT.cfg;
  vkSetRadio('vk-bg_type', cfg.bg_type || 'color');
  vkSetVal('vk-bg_color', cfg.bg_color || '#ffffff');
  vkSetVal('vk-bg_color_hex', cfg.bg_color || '#ffffff');
  vkSetVal('vk-bg_overlay', cfg.bg_overlay_opacity || 0);
  vkSetChk('vk-border_enabled', !!cfg.border_enabled);
  vkSetVal('vk-border_color', cfg.border_color || '#6f102a');
  vkSetVal('vk-border_color_hex', cfg.border_color || '#6f102a');
  vkSetVal('vk-border_width', cfg.border_width || 18);
  vkSetTxt('vk-border_width_n', cfg.border_width || 18);
  vkSetVal('vk-header_text', cfg.header_text || '');
  vkSetVal('vk-header_color', cfg.header_color || '#6f102a');
  vkSetVal('vk-header_color_hex', cfg.header_color || '#6f102a');
  vkSetVal('vk-header_font_size', cfg.header_font_size || 38);
  vkSetVal('vk-header_y', cfg.header_y || 110);
  vkSetChk('vk-header_line', cfg.header_line !== false);
  vkSetVal('vk-subheader_text', cfg.subheader_text || '');
  vkSetVal('vk-subheader_color', cfg.subheader_color || '#1a2e5a');
  vkSetVal('vk-subheader_color_hex', cfg.subheader_color || '#1a2e5a');
  vkSetVal('vk-subheader_font_size', cfg.subheader_font_size || 13);
  vkSetVal('vk-subheader_y', cfg.subheader_y || 158);
  vkSetVal('vk-name_color', cfg.name_color || '#6f102a');
  vkSetVal('vk-name_color_hex', cfg.name_color || '#6f102a');
  vkSetVal('vk-name_font_size', cfg.name_font_size || 46);
  vkSetVal('vk-name_y', cfg.name_y || 340);
  vkSetVal('vk-name_x', cfg.name_x || 80);
  vkSetVal('vk-name_align', cfg.name_align || 'center');
  vkSetChk('vk-name_italic', cfg.name_italic !== false);
  vkSetChk('vk-name_underline', cfg.name_underline !== false);
  vkSetChk('vk-has_completed_text', !!cfg.has_completed_text);
  vkSetVal('vk-completed_text', cfg.completed_text || '');
  vkSetVal('vk-completed_color', cfg.completed_color || '#444444');
  vkSetVal('vk-completed_color_hex', cfg.completed_color || '#444444');
  vkSetVal('vk-completed_font_size', cfg.completed_font_size || 13);
  vkSetVal('vk-completed_y', cfg.completed_y || 415);
  vkSetVal('vk-title_color', cfg.title_color || '#1a2e5a');
  vkSetVal('vk-title_color_hex', cfg.title_color || '#1a2e5a');
  vkSetVal('vk-title_font_size', cfg.title_font_size || 22);
  vkSetVal('vk-title_y', cfg.title_y || 460);
  vkSetVal('vk-title_align', cfg.title_align || 'center');
  vkSetVal('vk-date_label', cfg.date_label || 'Fecha:');
  vkSetVal('vk-date_color', cfg.date_color || '#555555');
  vkSetVal('vk-date_color_hex', cfg.date_color || '#555555');
  vkSetVal('vk-date_font_size', cfg.date_font_size || 12);
  vkSetVal('vk-date_x', cfg.date_x || 80);
  vkSetVal('vk-date_y', cfg.date_y || 560);
  vkSetVal('vk-cert_id_color', cfg.cert_id_color || '#888888');
  vkSetVal('vk-cert_id_color_hex', cfg.cert_id_color || '#888888');
  vkSetVal('vk-cert_id_font_size', cfg.cert_id_font_size || 9);
  vkSetVal('vk-cert_id_x', cfg.cert_id_x || 80);
  vkSetVal('vk-cert_id_y', cfg.cert_id_y || 578);
  vkSetVal('vk-signature_label', cfg.signature_label || '');
  vkSetVal('vk-signature_role', cfg.signature_role || '');
  vkSetVal('vk-signature_x', cfg.signature_x || 760);
  vkSetVal('vk-signature_y', cfg.signature_y || 640);
  vkSetVal('vk-signature_line_w', cfg.signature_line_w || 200);
  vkSetChk('vk-qr_enabled', cfg.qr_enabled !== false);
  vkSetVal('vk-qr_x_from_right', cfg.qr_x_from_right || 50);
  vkSetVal('vk-qr_y_from_bottom', cfg.qr_y_from_bottom || 85);
  vkSetVal('vk-qr_size', cfg.qr_size || 78);

  // Mostrar preview de imagen de fondo guardada
  vkBgThumbUpdate();
  vkBgTypeUpdate();
}

function vkSetVal(id, v)  { var el=document.getElementById(id); if(el&&v!==undefined) el.value=v; }
function vkSetChk(id, v)  { var el=document.getElementById(id); if(el) el.checked=!!v; }
function vkSetTxt(id, v)  { var el=document.getElementById(id); if(el) el.textContent=v; }
function vkSetRadio(name, v) { document.querySelectorAll('[name="'+name+'"]').forEach(function(r){ r.checked=(r.value===v); }); }
function vkSyncHex(colorId, hexId)  { var c=document.getElementById(colorId),h=document.getElementById(hexId); if(c&&h)h.value=c.value; }
function vkSyncColor(hexId, colorId){ var h=document.getElementById(hexId),c=document.getElementById(colorId); if(h&&c&&/^#[0-9a-fA-F]{6}$/.test(h.value))c.value=h.value; }

// ─── TIPO DE FONDO ────────────────────────────────────────────────
function vkBgTypeUpdate() {
  var t = VK_CERT.cfg.bg_type || 'color';
  var colorRow   = document.getElementById('vk-bg-color-row');
  var imgRow     = document.getElementById('vk-bg-image-row');
  var gradFields = document.getElementById('vk-gradient-fields');
  if (colorRow)   colorRow.style.display   = (t === 'image') ? 'none' : '';
  if (imgRow)     imgRow.style.display     = (t === 'image') ? '' : 'none';
  if (gradFields) gradFields.style.display = (t === 'gradient') ? '' : 'none';
}

// ─── PREVIEW de imagen de fondo ───────────────────────────────────
function vkBgThumbUpdate() {
  // Update all thumb zones: main design panel + assets tab
  var ids = ['vk-bg-img-thumb', 'vk-bg-img-thumb-assets'];
  ids.forEach(function(elId) {
    var thumb = document.getElementById(elId);
    if (!thumb) return;
    // Limpiar
    thumb.innerHTML = '';
    if (VK_CERT.cfg.bg_type !== 'image') return;

    var src = VK_CERT.cfg.bg_image_data || VK_CERT.cfg.bg_image_url || '';
    if (!src) {
      thumb.innerHTML = '<div style="padding:.5rem;color:var(--tu);font-size:.8rem;text-align:center">📁 Ninguna imagen cargada</div>';
      return;
    }

    var fname = VK_CERT.cfg.bg_image_data
      ? '📷 Imagen subida desde PC'
      : src.split('/').pop().split('?')[0];

    // Construir preview con DOM (no innerHTML con HTML dentro de onerror)
    var wrap = document.createElement('div');
    wrap.style.cssText = 'border-radius:8px;overflow:hidden;border:2px solid var(--border);background:#f0f0f0;margin-top:.4rem';

    var img = document.createElement('img');
    img.style.cssText = 'width:100%;display:block;max-height:110px;object-fit:cover';
    img.src = src;

    var label = document.createElement('div');
    label.style.cssText = 'font-size:.75rem;padding:.25rem .5rem;color:var(--td);background:rgba(0,0,0,.05);white-space:nowrap;overflow:hidden;text-overflow:ellipsis';
    label.textContent = fname;

    img.onerror = function() {
      img.style.display = 'none';
      label.style.color = '#c62828';
      label.textContent = '⚠️ No se puede previsualizar (la imagen sí se guardará)';
    };

    wrap.appendChild(img);
    wrap.appendChild(label);
    thumb.appendChild(wrap);
  });
}

// ─── CARGAR imagen de fondo desde URL ────────────────────────────
function vkLoadBgImg(url) {
  if (!url) return;
  vkLoadImg(url).then(function(img) {
    VK_CERT.bgImg = img;
    vkCertPreviewRedraw();
  });
}

// ─── CARGAR imagen de fondo desde base64 ─────────────────────────
function vkLoadBgFromData(dataUrl) {
  if (!dataUrl) return;
  var img = new Image();
  img.onload = function() {
    VK_CERT.bgImg = img;
    vkCertPreviewRedraw();
  };
  img.src = dataUrl;
}

// ─── SUBIR IMAGEN DE FONDO DESDE PC ──────────────────────────────
// Sube el archivo al servidor via FormData (evita límite post_max_size).
// El servidor almacena el archivo y devuelve URL + base64 para uso inmediato en canvas.
async function vkUploadBgImage(input, source) {
  if (!input.files || !input.files[0]) return;
  var file = input.files[0];

  if (!file.type.startsWith('image/')) {
    vkEditorMsg('❌ El archivo debe ser una imagen (JPG, PNG, WebP)', 'err');
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    vkEditorMsg('❌ La imagen es muy grande. Máximo 5MB.', 'err');
    return;
  }

  var zoneId = (source === 'assets') ? 'vk-bg-zone-assets-inner' : 'vk-upload-zone-inner';
  var zone = document.getElementById(zoneId);
  if (zone) zone.innerHTML = '<span class="vk-spin"></span> Cargando...';

  try {
    // Leer localmente para preview instantáneo mientras sube al servidor
    var dataUrl = await new Promise(function(resolve, reject) {
      var reader = new FileReader();
      reader.onload  = function(e) { resolve(e.target.result); };
      reader.onerror = function()  { reject(new Error('Error leyendo el archivo')); };
      reader.readAsDataURL(file);
    });

    var finalData = dataUrl;
    if (dataUrl.length > 800000) {
      finalData = await vkCompressImage(dataUrl, 1200, 0.82);
    }

    // Preview inmediato desde datos locales (sin esperar al servidor)
    VK_CERT.cfg.bg_type       = 'image';
    VK_CERT.cfg.bg_image_data = finalData;
    VK_CERT.cfg.bg_image_url  = '';
    vkLoadBgFromData(finalData);
    vkBgTypeUpdate();
    vkBgThumbUpdate();
    document.querySelectorAll('[name="vk-bg_type"]').forEach(function(r) { r.checked = (r.value === 'image'); });

    if (zone) zone.innerHTML = '<span class="vk-spin"></span> Subiendo al servidor...';
    vkEditorMsg('⏳ Subiendo imagen al servidor...', 'ok');

    // Subir al servidor via FormData (evita límite de tamaño de JSON body)
    var ext  = finalData.startsWith('data:image/png')  ? '.png'
             : finalData.startsWith('data:image/webp') ? '.webp' : '.jpg';
    var blob = await fetch(finalData).then(function(r) { return r.blob(); });
    var fd   = new FormData();
    fd.append('image', blob, 'cert-bg' + ext);
    fd.append('vk_token', TOK);

    var r = await fetch(API + '/cert-upload-bg', { method: 'POST', body: fd });
    var d = r.ok ? await r.json() : null;

    if (d && d.success && d.url) {
      // Servidor almacenó el archivo — URL es la referencia canónica
      VK_CERT.cfg.bg_image_url  = d.url;
      VK_CERT.cfg.bg_image_data = d.base64 || finalData;
      if (d.base64 && d.base64 !== finalData) vkLoadBgFromData(d.base64);
      vkBgThumbUpdate();
      vkEditorMsg('✅ Imagen guardada en servidor. Presiona "Guardar diseño" para confirmar.', 'ok');
    } else {
      // Fallo en servidor — base64 local como fallback
      vkEditorMsg('⚠️ Imagen cargada localmente (fallo al subir al servidor). Guarda para intentar persistirla.', 'ok');
    }

    var z1 = document.getElementById('vk-upload-zone-inner');
    var z2 = document.getElementById('vk-bg-zone-assets-inner');
    if (z1) z1.innerHTML = '✅ Imagen cargada — clic para cambiar';
    if (z2) z2.innerHTML = '✅ Imagen cargada — clic para cambiar';

  } catch(e) {
    var z1e = document.getElementById('vk-upload-zone-inner');
    var z2e = document.getElementById('vk-bg-zone-assets-inner');
    if (z1e) z1e.innerHTML = '📁 Clic para subir imagen de fondo';
    if (z2e) z2e.innerHTML = '📁 Clic para subir imagen de fondo';
    vkEditorMsg('❌ Error al cargar la imagen: ' + e.message, 'err');
  }
  input.value = '';
}

// ─── Comprimir imagen en canvas ──────────────────────────────────
function vkCompressImage(dataUrl, maxW, quality) {
  return new Promise(function(resolve) {
    var img = new Image();
    img.onload = function() {
      var w = img.width, h = img.height;
      if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
      var c = document.createElement('canvas');
      c.width = w; c.height = h;
      c.getContext('2d').drawImage(img, 0, 0, w, h);
      resolve(c.toDataURL('image/jpeg', quality));
    };
    img.src = dataUrl;
  });
}

// ─── QUITAR imagen de fondo ───────────────────────────────────────
function vkRemoveBgImage() {
  VK_CERT.cfg.bg_image_data = '';
  VK_CERT.cfg.bg_image_url  = '';
  VK_CERT.cfg.bg_type       = 'color';
  VK_CERT.bgImg             = null;
  document.querySelectorAll('[name="vk-bg_type"]').forEach(function(r) { r.checked = (r.value === 'color'); });
  vkBgTypeUpdate();
  vkBgThumbUpdate();
  var z1 = document.getElementById('vk-upload-zone-inner');
  var z2 = document.getElementById('vk-bg-zone-assets-inner');
  if (z1) z1.innerHTML = '📁 Clic para subir imagen de fondo (JPG/PNG)';
  if (z2) z2.innerHTML = '📁 Clic para subir imagen de fondo';
  vkCertPreviewRedraw();
  vkEditorMsg('Imagen de fondo eliminada.', 'ok');
}

// ─── REDIBUJA el preview ──────────────────────────────────────────
function vkCertPreviewRedraw() {
  clearTimeout(VK_CERT.timer);
  VK_CERT.timer = setTimeout(function() {
    var canvas = document.getElementById('vk-cert-canvas');
    if (!canvas) return;
    var pName   = (document.getElementById('vk-prev-name')   || {}).value || 'María González López';
    var pCourse = (document.getElementById('vk-prev-course') || {}).value || 'Método VidaKushala — Bioenergética';
    var pDate   = (document.getElementById('vk-prev-date')   || {}).value || new Date().toLocaleDateString('es-MX', {year:'numeric',month:'long',day:'numeric'});
    var pId     = (document.getElementById('vk-prev-id')     || {}).value || 'CERT-A7F3B2D1';

    // Si hay imagen en base64 guardada, usarla directamente
    var bgImg = VK_CERT.bgImg;

    vkRenderCertCanvas(canvas, VK_CERT.cfg, {
      student_name:   pName,
      course_title:   pCourse,
      cert_date:      pDate,
      cert_id:        pId,
      validation_url: 'https://vidakushala.com/verify?id=' + pId,
      bg_img:         bgImg,
      logo_img:       VK_CERT.logoImg,
      sig_img:        VK_CERT.sigImg
    });
  }, 60);
}

// ─── LIMPIAR CACHÉ ────────────────────────────────────────────────
async function vkClearCertCache() {
  if (!confirm('¿Limpiar todos los certificados cacheados?\nLos usuarios regenerarán su certificado con el nuevo diseño.')) return;
  try {
    var r = await fetch(API + '/cert-clear-cache', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({vk_token: TOK})
    });
    var d = await r.json();
    vkEditorMsg(
      r.ok && d.success
        ? ('🗑️ Caché limpiado. ' + (d.deleted||0) + ' certificados eliminados.')
        : ('❌ ' + (d.message || 'Error')),
      r.ok ? 'ok' : 'err'
    );
  } catch(e) { vkEditorMsg('❌ Error de conexión', 'err'); }
}

// ─── SANITIZAR FONDOS (eliminar cert renders guardados como fondo) ─
async function vkSanitizeCertBg() {
  try {
    var r = await fetch(API + '/cert-sanitize-bg', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({vk_token: TOK})
    });
    var d = await r.json();
    vkEditorMsg(
      r.ok && d.success
        ? ('🧹 ' + d.message)
        : ('❌ ' + (d.message || 'Error')),
      r.ok ? 'ok' : 'err'
    );
    // Si se limpiaron configs, recargar el editor para reflejar cambios
    if (r.ok && d.success && d.cleaned > 0) {
      setTimeout(function() { vkLoadCertConfig(); }, 800);
    }
  } catch(e) { vkEditorMsg('❌ Error de conexión', 'err'); }
}

// ─── ESTABLECER FONDO PREDETERMINADO ─────────────────────────────
async function vkSetDefaultBg() {
  vkEditorMsg('⏳ Estableciendo fondo predeterminado...', 'ok');
  try {
    var r = await fetch(API + '/cert-set-default-bg', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({vk_token: TOK})
    });
    var d = await r.json();
    vkEditorMsg(
      r.ok && d.success
        ? ('🖼️ ' + d.message)
        : ('❌ ' + (d.message || 'Error al establecer fondo predeterminado')),
      r.ok ? 'ok' : 'err'
    );
    if (r.ok && d.success) {
      setTimeout(function() { vkLoadCertConfig(); }, 800);
    }
  } catch(e) { vkEditorMsg('❌ Error de conexión', 'err'); }
}

// ─── RESET ────────────────────────────────────────────────────────
function vkResetCertConfig() {
  if (!confirm('¿Restaurar la configuración predeterminada?')) return;
  VK_CERT.cfg   = vkCertDefaults();
  VK_CERT.bgImg = null;
  vkCertFormSync();
  vkCertPreviewRedraw();
}

// ─── DESCARGAR PREVIEW ────────────────────────────────────────────
function vkDownloadPreview() {
  var c = document.getElementById('vk-cert-canvas');
  if (!c) return;
  var a = document.createElement('a');
  a.download = 'certificado-preview.png';
  a.href = c.toDataURL('image/png');
  a.click();
}

// ─── MENSAJES ─────────────────────────────────────────────────────
function vkEditorMsg(msg, type) {
  var el = document.getElementById('vk-cert-msg');
  if (!el) return;
  el.textContent = msg;
  // Support both cert-editor.php (.ed-msg) and administrar.php (.vk-cert-msg) class names
  var base = el.classList.contains('ed-msg') ? 'ed-msg' : 'vk-cert-msg';
  el.className = base + ' ' + (type || 'ok');
  clearTimeout(el._t);
  el._t = setTimeout(function(){ el.textContent = ''; el.className = base; }, 7000);
}

// ─── TABS DEL EDITOR ─────────────────────────────────────────────
function vkEditorTab(id) {
  var tabs = ['design','templates','fonts','assets'];
  tabs.forEach(function(t) {
    var tab  = document.getElementById('vk-etab-' + t);
    var cont = document.getElementById('vk-tc-'   + t);
    if (tab)  tab.classList.toggle('active', t === id);
    // cert-editor.php uses class 'on', administrar.php uses display style
    if (cont) {
      if (cont.classList.contains('ed-tab-content')) {
        cont.classList.toggle('on', t === id);
      } else {
        cont.style.display = (t === id) ? '' : 'none';
      }
    }
  });
  // Lazy-load file gallery when switching to templates or assets tabs
  if (id === 'templates' || id === 'assets') {
    vkLoadFileGallery(false);
  }
}

// ─── FUENTES ─────────────────────────────────────────────────────
function vkSetFont(type, font) {
  if (type === 'title') {
    VK_CERT.cfg.font_title = font;
    document.querySelectorAll('.vk-font-card[data-font]').forEach(function(c) {
      c.classList.toggle('active', c.dataset.font === font);
    });
  } else {
    VK_CERT.cfg.font_body = font;
    document.querySelectorAll('.vk-font-card[data-font-body]').forEach(function(c) {
      c.classList.toggle('active', c.dataset.fontBody === font);
    });
  }
  VK_CERT.dirty = true;
  vkCertPreviewRedraw();
  vkEditorMsg('Tipografía: ' + font + ' aplicada.', 'ok');
}

// ─── SUBIR LOGO ───────────────────────────────────────────────────
async function vkUploadLogoImage(input) {
  if (!input.files || !input.files[0]) return;
  var zone = document.getElementById('vk-logo-zone-inner');
  if (zone) zone.innerHTML = '<span class="vk-spin"></span> Cargando...';
  try {
    var dataUrl = await new Promise(function(resolve, reject) {
      var reader = new FileReader();
      reader.onload  = function(e) { resolve(e.target.result); };
      reader.onerror = function()  { reject(new Error('Error leyendo logo')); };
      reader.readAsDataURL(input.files[0]);
    });
    VK_CERT.cfg.logo_data    = dataUrl;  // base64 data URL — stored in logo_data, not logo_url
    VK_CERT.cfg.logo_url     = '';       // clear so app.js doesn't corrupt it by prepending siteUrl
    VK_CERT.cfg.logo_enabled = true;
    var img = new Image();
    img.onload = function() { VK_CERT.logoImg = img; vkCertPreviewRedraw(); };
    img.src = dataUrl;
    var thumb = document.getElementById('vk-logo-img-thumb');
    if (thumb) {
      thumb.innerHTML = '';
      var ti = document.createElement('img');
      ti.src = dataUrl; ti.style.cssText = 'max-height:60px;margin-top:.3rem;border-radius:4px';
      thumb.appendChild(ti);
    }
    if (zone) zone.innerHTML = '✅ Logo cargado';
    vkEditorMsg('Logo cargado.', 'ok');
  } catch(e) {
    if (zone) zone.innerHTML = '🏷 Subir logo';
    vkEditorMsg('Error: ' + e.message, 'err');
  }
  input.value = '';
}

// ─── GALERÍA de archivos del servidor (solo lectura) ─────────────
var _fileGalleryLoaded = false;
var _fileGalleryData   = null;

// Renders gallery HTML from cached data into a container element
function vkRenderFileGalleryInto(container, files) {
  if (!files || !files.length) {
    container.innerHTML = '<div style="padding:1rem;color:var(--mu);font-size:.85rem">No hay imágenes en cert-templates/.</div>';
    return;
  }
  container.innerHTML = '';
  files.forEach(function(f) {
    var card = document.createElement('div'); card.className = 'vk-file-card file-card';
    card.title = f.name;
    card.addEventListener('click', function(){ vkUseServerFileBg(f.url, f.name); });
    var img = document.createElement('img');
    img.src = f.url; img.loading = 'lazy';
    img.style.cssText = 'width:100%;height:80px;object-fit:cover;display:block';
    var nm = document.createElement('div'); nm.className = 'vk-file-card-name file-card-name'; nm.textContent = f.name;
    card.appendChild(img); card.appendChild(nm);
    container.appendChild(card);
  });
}

async function vkLoadFileGallery(force) {
  if (_fileGalleryLoaded && !force) {
    // Re-render from cache into both containers
    var c1 = document.getElementById('vk-file-gallery');
    var c2 = document.getElementById('vk-file-gallery-assets');
    if (c1 && _fileGalleryData) vkRenderFileGalleryInto(c1, _fileGalleryData);
    if (c2 && _fileGalleryData) vkRenderFileGalleryInto(c2, _fileGalleryData);
    return;
  }
  var c1 = document.getElementById('vk-file-gallery');
  var c2 = document.getElementById('vk-file-gallery-assets');
  var loadMsg = '<div style="padding:1rem;color:var(--mu);font-size:.85rem;display:flex;align-items:center;gap:.5rem"><span class="vk-spin vk-spin-dark"></span> Cargando...</div>';
  if (c1) c1.innerHTML = loadMsg;
  if (c2) c2.innerHTML = loadMsg;
  try {
    var r = await fetch(API + '/cert-templates?vk_token=' + encodeURIComponent(TOK) + '&_t=' + Date.now());
    var d = await r.json();
    if (r.ok && d.files && d.files.length) {
      _fileGalleryLoaded = true;
      _fileGalleryData   = d.files;
      if (c1) vkRenderFileGalleryInto(c1, d.files);
      if (c2) vkRenderFileGalleryInto(c2, d.files);
    } else {
      var msg = '<div style="padding:1rem;color:var(--mu);font-size:.85rem">No hay imágenes guardadas en cert-templates/.</div>';
      if (c1) c1.innerHTML = msg;
      if (c2) c2.innerHTML = msg;
    }
  } catch(e) {
    var err = '<div style="padding:1rem;color:#c00;font-size:.85rem">Error: ' + e.message + '</div>';
    if (c1) c1.innerHTML = err;
    if (c2) c2.innerHTML = err;
  }
}

// ─── USAR imagen del servidor como fondo ─────────────────────────
function vkUseServerFileBg(url, name) {
  VK_CERT.cfg.bg_type       = 'image';
  VK_CERT.cfg.bg_image_url  = url;
  VK_CERT.cfg.bg_image_data = '';   // limpiar base64 previo
  VK_CERT.bgImg             = null;
  vkLoadBgImg(url);
  document.querySelectorAll('[name="vk-bg_type"]').forEach(function(r){ r.checked = (r.value === 'image'); });
  vkBgTypeUpdate();
  vkBgThumbUpdate();
  vkEditorMsg('✅ "' + name + '" aplicada como fondo. Guarda para confirmar.', 'ok');
  vkEditorTab('design');
}

// ─── AUTO-INIT ────────────────────────────────────────────────────
// cert-editor.php calls vkCertEditorInit() from onLogin() after auth.
// administrar.php mounts the editor directly so we init on load there.
// The _VK_EDITOR_INITED guard prevents any double-init in both cases.
window.addEventListener('load', function() {
  // Only auto-init if the canvas already exists at load time (administrar.php).
  // In cert-editor.php the canvas only exists after login so we wait for onLogin().
  if (document.getElementById('vk-cert-canvas')) {
    vkCertEditorInit();
  }
});
