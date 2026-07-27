/**
 * vkRenderCertCanvas — Motor de Renderizado Unificado de Certificados v3.0
 * VidaKushala PWA
 *
 * ÚNICO motor. Produce output idéntico en:
 *   • administrar.php  — editor admin (preview)
 *   • app.js         — certificado real del usuario
 *
 * @param {HTMLCanvasElement} canvas
 * @param {Object}            cfg     vk_app_cert_config
 * @param {Object}            data    { student_name, course_title, cert_date, cert_id, validation_url, bg_img }
 * @returns {Promise<HTMLCanvasElement>}
 */
// Generation counter per canvas — prevents editor and app.js renders from cancelling each other
var _vkRenderGen = 0; // kept for legacy callers that may read it

async function vkRenderCertCanvas(canvas, cfg, data) {
  // Use a per-canvas counter so concurrent renders on *different* canvases never
  // cancel each other. Concurrent renders on the *same* canvas (editor debounce)
  // still cancel as expected.
  canvas._vkRenderGen = (canvas._vkRenderGen || 0) + 1;
  var myGen = canvas._vkRenderGen;
  function stale() { return canvas._vkRenderGen !== myGen; }

  var W = 1122, H = 794;
  canvas.width  = W;
  canvas.height = H;
  var ctx = canvas.getContext('2d');
  cfg  = cfg  || {};
  data = data || {};

  // ─── 1. FONDO ────────────────────────────────────────────────────
  var bgDrawn = false;
  if (cfg.bg_type === 'image') {
    var bgImg = data.bg_img || null;
    // Fallback 1: base64 data stored in config
    if (!bgImg && cfg.bg_image_data) {
      bgImg = await vkLoadImg(cfg.bg_image_data);
      if (stale()) return;
    }
    // Fallback 2: server URL
    if (!bgImg && cfg.bg_image_url) {
      bgImg = await vkLoadImg(cfg.bg_image_url);
      if (stale()) return;
    }
    if (bgImg) {
      // Cover: escala proporcional llenando el canvas
      var iW = bgImg.naturalWidth  || bgImg.width;
      var iH = bgImg.naturalHeight || bgImg.height;
      var scale = Math.max(W / iW, H / iH);
      var bw = iW * scale, bh = iH * scale;
      ctx.drawImage(bgImg, (W - bw) / 2, (H - bh) / 2, bw, bh);
      bgDrawn = true;
    }
  }
  if (!bgDrawn) {
    // Degradado suave o color sólido
    if (cfg.bg_gradient && cfg.bg_gradient_from && cfg.bg_gradient_to) {
      var grad = ctx.createLinearGradient(0, 0, W, H);
      grad.addColorStop(0, cfg.bg_gradient_from);
      grad.addColorStop(1, cfg.bg_gradient_to);
      ctx.fillStyle = grad;
    } else {
      ctx.fillStyle = cfg.bg_color || '#ffffff';
    }
    ctx.fillRect(0, 0, W, H);
  }

  // ─── 2. OVERLAY (oscurecer fondo si hay imagen) ──────────────────
  if (cfg.bg_type === 'image' && bgDrawn && cfg.bg_overlay_opacity > 0) {
    ctx.fillStyle = 'rgba(0,0,0,' + (cfg.bg_overlay_opacity / 100) + ')';
    ctx.fillRect(0, 0, W, H);
  }

  // ─── 3. MARCO DOBLE ──────────────────────────────────────────────
  if (cfg.border_enabled) {
    var bw = Math.max(1, cfg.border_width || 18);
    // Marco exterior
    ctx.strokeStyle = cfg.border_color || '#6f102a';
    ctx.lineWidth   = bw;
    ctx.strokeRect(bw / 2, bw / 2, W - bw, H - bw);
    // Marco interior fino
    var mg2 = bw + 8;
    ctx.strokeStyle = vkHexA(cfg.border_color || '#6f102a', 0.4);
    ctx.lineWidth   = 1;
    ctx.strokeRect(mg2, mg2, W - mg2 * 2, H - mg2 * 2);
  }

  // ─── 4. LOGO ─────────────────────────────────────────────────────
  if (cfg.logo_enabled && (data.logo_img || cfg.logo_url || cfg.logo_data)) {
    var logoImg = data.logo_img || null;
    if (!logoImg && cfg.logo_data) { logoImg = await vkLoadImg(cfg.logo_data); if (stale()) return; }
    if (!logoImg && cfg.logo_url)  { logoImg = await vkLoadImg(cfg.logo_url);  if (stale()) return; }
    if (logoImg) {
      var lw = cfg.logo_w || 100;
      var lh = lw * ((logoImg.naturalHeight || logoImg.height) / (logoImg.naturalWidth || logoImg.width));
      var lx = cfg.logo_x || 60;
      var ly = cfg.logo_y || 50;
      ctx.drawImage(logoImg, lx, ly, lw, lh);
    }
  }

  // ─── Fuentes ─────────────────────────────────────────────────────
  var fontTitle  = (cfg.font_title  || 'Georgia') + ', "Times New Roman", serif';
  var fontBody   = (cfg.font_body   || 'Arial')   + ', sans-serif';
  var fontMono   = '"Courier New", monospace';
  var textColor  = function(c){ ctx.fillStyle = c || '#333333'; };

  // ─── 5. TÍTULO (DIPLOMA) ─────────────────────────────────────────
  var headerText = cfg.header_text || '';
  if (headerText) {
    var hSz  = cfg.header_font_size || 38;
    var hY   = cfg.header_y || 110;
    var hCol = cfg.header_color || '#6f102a';
    var hBold = cfg.header_bold !== false;
    ctx.font      = (hBold ? 'bold ' : '') + hSz + 'px ' + fontTitle;
    textColor(hCol);
    ctx.textAlign = 'center';
    ctx.fillText(headerText, W / 2, hY);
    // Decoración: línea + puntos
    if (cfg.header_line !== false) {
      var lineW = Math.min(ctx.measureText(headerText).width * 1.25, W * 0.65);
      var lineY = hY + hSz * 0.3;
      ctx.strokeStyle = hCol;
      ctx.lineWidth = 1.5;
      _hLine(ctx, W / 2, lineY, lineW);
    }
  }

  // ─── 6. SUBTÍTULO ────────────────────────────────────────────────
  var subText = cfg.subheader_text || '';
  if (subText) {
    var shSz  = cfg.subheader_font_size || 13;
    var shY   = cfg.subheader_y || 158;
    var shCol = cfg.subheader_color || '#1a2e5a';
    ctx.font      = shSz + 'px ' + fontBody;
    textColor(shCol);
    ctx.textAlign = 'center';
    ctx.letterSpacing = '3px';
    ctx.fillText(subText.toUpperCase(), W / 2, shY);
    ctx.letterSpacing = '0px';
  }

  // ─── 7. NOMBRE DEL ESTUDIANTE ────────────────────────────────────
  var studentName = data.student_name || 'Nombre del Estudiante';
  var nSz    = cfg.name_font_size || 46;
  var nY     = cfg.name_y || 340;
  var nCol   = cfg.name_color || '#6f102a';
  var nAlign = cfg.name_align || 'center';
  var nItalic = cfg.name_italic !== false;
  ctx.font      = (nItalic ? 'italic ' : '') + 'bold ' + nSz + 'px ' + fontTitle;
  textColor(nCol);
  ctx.textAlign = nAlign === 'left' ? 'left' : 'center';
  var nX    = nAlign === 'left' ? (cfg.name_x || 80) : W / 2;
  var nLines = vkWrap(ctx, studentName, W * 0.78);
  var nCurY  = nY;
  nLines.forEach(function(l){ ctx.fillText(l, nX, nCurY); nCurY += nSz * 1.35; });
  // Subrayado del nombre
  if (cfg.name_underline !== false) {
    ctx.strokeStyle = nCol;
    ctx.lineWidth   = 1.5;
    _hLine(ctx, W / 2, nCurY - nSz * 0.15, W * 0.7);
  }

  // ─── 8. TEXTO "HA COMPLETADO" ────────────────────────────────────
  if (cfg.has_completed_text && cfg.completed_text) {
    var cSz  = cfg.completed_font_size || 13;
    var cY   = cfg.completed_y || 415;
    var cCol = cfg.completed_color || '#444444';
    ctx.font      = cSz + 'px ' + fontBody;
    textColor(cCol);
    ctx.textAlign = 'center';
    ctx.fillText(cfg.completed_text, W / 2, cY);
  }

  // ─── 9. NOMBRE DEL CURSO ─────────────────────────────────────────
  var courseTitle = data.course_title || '';
  if (courseTitle) {
    var tSz    = cfg.title_font_size || 22;
    var tY     = cfg.title_y || 460;
    var tCol   = cfg.title_color || '#1a2e5a';
    var tAlign = cfg.title_align || 'center';
    ctx.font      = 'bold ' + tSz + 'px ' + fontBody;
    textColor(tCol);
    ctx.textAlign = tAlign === 'left' ? 'left' : 'center';
    var tX = tAlign === 'left' ? W * 0.08 : W / 2;
    var tLines = vkWrap(ctx, '"' + courseTitle + '"', W * 0.72);
    var tCurY  = tY;
    tLines.forEach(function(l){ ctx.fillText(l, tX, tCurY); tCurY += tSz * 1.4; });
  }

  // ─── 10. LÍNEA DIVISORA ──────────────────────────────────────────
  if (cfg.divider_enabled === true) {
    var dvY = cfg.divider_y || 530;
    ctx.strokeStyle = vkHexA(cfg.border_color || '#6f102a', 0.25);
    ctx.lineWidth   = 0.8;
    ctx.setLineDash([4, 4]);
    ctx.beginPath();
    ctx.moveTo(W * 0.08, dvY); ctx.lineTo(W * 0.92, dvY);
    ctx.stroke();
    ctx.setLineDash([]);
  }

  // ─── 11. FECHA ───────────────────────────────────────────────────
  var certDate = data.cert_date || '';
  if (certDate) {
    var dSz  = cfg.date_font_size || 12;
    var dX   = cfg.date_x || 80;
    var dY   = cfg.date_y || 560;
    var dCol = cfg.date_color || '#555555';
    ctx.font      = dSz + 'px ' + fontBody;
    textColor(dCol);
    ctx.textAlign = 'left';
    ctx.fillText((cfg.date_label || 'Fecha:') + ' ' + certDate, dX, dY);
  }

  // ─── 12. ID DEL CERTIFICADO ──────────────────────────────────────
  var certId = data.cert_id || '';
  if (certId) {
    var idSz  = cfg.cert_id_font_size || 9;
    var idX   = cfg.cert_id_x || 80;
    var idY   = cfg.cert_id_y || 578;
    var idCol = cfg.cert_id_color || '#888888';
    ctx.font      = idSz + 'px ' + fontMono;
    textColor(idCol);
    ctx.textAlign = 'left';
    ctx.fillText('ID: ' + certId, idX, idY);
  }

  // ─── 13. FIRMA ───────────────────────────────────────────────────
  var sigLabel = cfg.signature_label || '';
  var sigRole  = cfg.signature_role  || '';
  if (sigLabel || sigRole) {
    var sigX    = cfg.signature_x     || 760;
    var sigY    = cfg.signature_y     || 640;
    var sigLW   = cfg.signature_line_w || 200;
    var sigLineY = sigY - 22;
    // Imagen de firma si existe
    if (data.sig_img || cfg.signature_img_url || cfg.signature_img_data) {
      var sImg = data.sig_img || null;
      if (!sImg && cfg.signature_img_data) { sImg = await vkLoadImg(cfg.signature_img_data); if (stale()) return; }
      if (!sImg && cfg.signature_img_url)  { sImg = await vkLoadImg(cfg.signature_img_url);  if (stale()) return; }
      if (sImg) {
        var siW = sigLW, siH = siW * ((sImg.naturalHeight||sImg.height)/(sImg.naturalWidth||sImg.width));
        ctx.drawImage(sImg, sigX - siW / 2, sigLineY - siH - 5, siW, siH);
      }
    }
    // Línea
    ctx.strokeStyle = '#666666'; ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(sigX - sigLW / 2, sigLineY);
    ctx.lineTo(sigX + sigLW / 2, sigLineY);
    ctx.stroke();
    // Nombre firmante
    if (sigLabel) {
      ctx.font = '12px ' + fontBody; textColor('#333333');
      ctx.textAlign = 'center';
      ctx.fillText(sigLabel, sigX, sigY);
    }
    if (sigRole) {
      ctx.font = '11px ' + fontBody; textColor('#888888');
      ctx.textAlign = 'center';
      ctx.fillText(sigRole, sigX, sigY + 16);
    }
  }

  // ─── 14. QR ──────────────────────────────────────────────────────
  if (cfg.qr_enabled !== false) {
    var qrSz   = cfg.qr_size    || 78;
    var qrXfr  = cfg.qr_x_from_right  || 50;
    var qrYfb  = cfg.qr_y_from_bottom || 85;
    var qrX    = W - qrXfr - qrSz;
    var qrY    = H - qrYfb - qrSz;
    vkDrawQR(ctx, qrX, qrY, qrSz, data.validation_url || '');
    ctx.font = '8px ' + fontBody; textColor('#666666');
    ctx.textAlign = 'center';
    ctx.fillText('Verificar', qrX + qrSz / 2, qrY + qrSz + 12);
  }

  // ─── 15. SELLO / WATERMARK ───────────────────────────────────────
  if (cfg.watermark_text) {
    ctx.save();
    ctx.globalAlpha = 0.055;
    ctx.font = 'bold 78px ' + fontBody;
    ctx.fillStyle = '#000000';
    ctx.textAlign = 'center';
    ctx.translate(W / 2, H / 2);
    ctx.rotate(-0.3);
    ctx.fillText(cfg.watermark_text, 0, 0);
    ctx.restore();
  }

  ctx.textAlign = 'left';
  return canvas;
}

// ─── Helpers globales ────────────────────────────────────────────

function vkWrap(ctx, text, maxWidth) {
  if (!text) return [''];
  var words = text.split(' '), lines = [], cur = '';
  words.forEach(function(w) {
    var t = cur ? cur + ' ' + w : w;
    if (ctx.measureText(t).width <= maxWidth) { cur = t; }
    else { if (cur) lines.push(cur); cur = w; }
  });
  if (cur) lines.push(cur);
  return lines.length ? lines : [''];
}

function vkLoadImg(url) {
  return new Promise(function(res) {
    if (!url) return res(null);

    // Resolver rutas relativas del plugin
    if (url.startsWith('cert-templates/') && typeof VK_PLUGIN_URL !== 'undefined' && VK_PLUGIN_URL) {
      url = VK_PLUGIN_URL + url;
    }

    var img = new Image();
    var isDataUrl = url.indexOf('data:') === 0;

    // Detectar cross-origin
    var isCrossOrigin = false;
    if (!isDataUrl) {
      try {
        var urlObj = new URL(url, window.location.href);
        isCrossOrigin = urlObj.origin !== window.location.origin;
      } catch(e) {}
    }

    img.onload  = function(){ res(img); };
    img.onerror = function() {
      // Cross-origin y no es data: — NO reintentar, ya fallará por CORS
      // El servidor debería haber enviado base64; loguear y continuar sin imagen
      if (isCrossOrigin) {
        console.warn('[vkLoadImg] Imagen cross-origin bloqueada por CORS. El servidor debe enviarla como base64:', url.substring(0,80));
        res(null); // continuar sin imagen en vez de loop de reintentos
      } else {
        res(null);
      }
    };

    if (isCrossOrigin) {
      img.crossOrigin = 'anonymous';
    }
    img.src = isDataUrl ? url : (url + (url.indexOf('?') < 0 ? '?' : '&') + '_t=' + Date.now());
  });
}

function vkHexA(hex, alpha) {
  hex = (hex || '#000').replace('#', '');
  if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
  return 'rgba('+parseInt(hex.slice(0,2),16)+','+parseInt(hex.slice(2,4),16)+','+parseInt(hex.slice(4,6),16)+','+alpha+')';
}

function _hLine(ctx, cx, y, w) {
  ctx.beginPath();
  ctx.moveTo(cx - w / 2, y);
  ctx.lineTo(cx + w / 2, y);
  ctx.stroke();
}

// QR simplificado (visual)
function vkDrawQR(ctx, x, y, size, url) {
  var mod = Math.floor(size / 21);
  var act = mod * 21;
  var off = (size - act) / 2;
  x += off; y += off;
  // Fondo blanco con borde
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(x - 3, y - 3, act + 6, act + 6);
  ctx.strokeStyle = '#cccccc'; ctx.lineWidth = 0.5;
  ctx.strokeRect(x - 3, y - 3, act + 6, act + 6);
  // Datos (hash visual)
  var h = 0;
  for (var i = 0; i < (url||'').length; i++) h = ((h << 5) - h) + url.charCodeAt(i);
  ctx.fillStyle = '#1a1a1a';
  for (var r = 0; r < 21; r++) {
    for (var c = 0; c < 21; c++) {
      // Skip corner areas
      if ((r < 8 && c < 8) || (r < 8 && c > 12) || (r > 12 && c < 8)) continue;
      if (((h >> ((r * 21 + c) % 30)) & 1)) ctx.fillRect(x + c * mod, y + r * mod, mod - 0.5, mod - 0.5);
    }
  }
  // 3 esquinas de posición
  [0, 14].forEach(function(cr){ [0, 14].forEach(function(cc){ if (cr===14&&cc===14) return; _qrCorner(ctx, x+cc*mod, y+cr*mod, mod); }); });
}

function _qrCorner(ctx, x, y, m) {
  ctx.fillStyle = '#1a1a1a';
  for (var r=0; r<7; r++) for (var c=0; c<7; c++) {
    var outer = r===0||r===6||c===0||c===6;
    var inner = r>=2&&r<=4&&c>=2&&c<=4;
    if (outer||inner) ctx.fillRect(x+c*m, y+r*m, m-0.5, m-0.5);
    else { ctx.fillStyle='#fff'; ctx.fillRect(x+c*m,y+r*m,m,m); ctx.fillStyle='#1a1a1a'; }
  }
}