/* ════════════════════════════════════════════════════════════
   pull-to-refresh.js — VidaKushala App v2.0
   Pull-to-refresh táctil (móvil) + scroll wheel (desktop).
   Integrado con Service Worker: si hay nueva versión disponible,
   la activa y recarga la página completa con assets frescos.
════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var THRESHOLD  = 72;   // px de arrastre para disparar refresh
  var RESISTANCE = 0.4;  // resistencia visual del indicador

  var _startY    = 0;
  var _pulling   = false;
  var _fired     = false;
  var _indicator = null;

  /* ── Indicador visual ─────────────────────────────────── */
  function getIndicator() {
    if (_indicator) return _indicator;
    var el = document.createElement('div');
    el.id = 'vk-ptr';
    el.setAttribute('aria-hidden', 'true');
    el.style.cssText = [
      'position:fixed;top:0;left:50%;z-index:9100',
      'transform:translateX(-50%) translateY(-72px)',
      'background:var(--vk-plum,#6b2447)',
      'color:#fff;border-radius:0 0 20px 20px',
      'padding:.45rem 1.2rem .55rem',
      'font-family:"DM Sans",sans-serif;font-size:.8rem;font-weight:600',
      'display:flex;align-items:center;gap:.45rem',
      'box-shadow:0 4px 18px rgba(107,36,71,.4)',
      'transition:transform .28s cubic-bezier(.34,1.4,.64,1)',
      'pointer-events:none;white-space:nowrap;will-change:transform'
    ].join(';');
    el.innerHTML = '<span id="vk-ptr-icon" style="display:inline-block;line-height:1;transition:transform .25s">↓</span>'
                 + '<span id="vk-ptr-label">Desliza para actualizar</span>';
    document.body.appendChild(el);
    _indicator = el;
    return el;
  }

  function setIndicator(progress) {
    var el = getIndicator();
    var p  = Math.min(progress, 1);
    var ty = -72 + p * 78;
    el.style.transform = 'translateX(-50%) translateY(' + ty + 'px)';
    var icon  = document.getElementById('vk-ptr-icon');
    var label = document.getElementById('vk-ptr-label');
    if (icon)  icon.style.transform = 'rotate(' + (p * 180) + 'deg)';
    if (label) label.textContent    = p >= 1 ? '¡Suelta para actualizar!' : 'Desliza para actualizar';
  }

  function showSpinner(msg) {
    var el = getIndicator();
    el.style.transform = 'translateX(-50%) translateY(6px)';
    el.innerHTML = '<span style="display:inline-block;animation:vk-ptr-spin .6s linear infinite;line-height:1">↻</span>'
                 + '&nbsp;' + (msg || 'Actualizando…');
    if (!document.getElementById('vk-ptr-css')) {
      var s = document.createElement('style');
      s.id = 'vk-ptr-css';
      s.textContent = '@keyframes vk-ptr-spin{to{transform:rotate(360deg)}}';
      document.head.appendChild(s);
    }
  }

  function hideIndicator() {
    var el = _indicator;
    if (!el) return;
    el.style.transform = 'translateX(-50%) translateY(-72px)';
  }

  /* ── Función de refresh por pantalla ─────────────────── */
  function getRefreshFn() {
    var screen = document.querySelector('.screen.active');
    if (!screen) return null;
    var id = screen.id.replace('screen-', '');
    var map = {
      'home':          function() { if (typeof loadHome          === 'function') loadHome(); },
      'courses':       function() { if (typeof loadCourses       === 'function') loadCourses(); },
      'products':      function() { if (typeof loadProducts      === 'function') loadProducts(); },
      'polls':         function() { if (typeof loadPolls         === 'function') loadPolls(); },
      'notifications': function() { if (typeof loadNotifications === 'function') loadNotifications(); },
      'certificates':  function() { if (typeof loadCertificates  === 'function') loadCertificates(); },
      'profile':       function() { if (typeof loadProfile       === 'function') loadProfile(); },
      'search':        function() { if (typeof loadSearch        === 'function') loadSearch(); },
      'bundles':       function() { if (typeof loadBundles       === 'function') loadBundles(); },
      'qa':            function() { if (typeof vkQA !== 'undefined') vkQA.loadFeed(); },
    };
    return map[id] || null;
  }

  /* ── Refresh de datos (sin cambio de SW) ─────────────── */
  function _doDataRefresh() {
    var fn = getRefreshFn();
    if (fn) { try { fn(); } catch(e) { console.warn('[PTR]', e); } }
    setTimeout(function() {
      hideIndicator();
      setTimeout(function() { _fired = false; }, 400);
    }, 1500);
  }

  /* ── Activar SW en espera y recargar la página ────────── */
  function _activateSwAndReload(waitingSW) {
    showSpinner('Nueva versión — recargando…');
    // Decirle al SW que active skipWaiting y recargar directamente.
    // No dependemos de controllerchange (poco fiable con OneSignal en el mismo SW).
    waitingSW.postMessage({ type: 'SKIP_WAITING' });
    setTimeout(function() { window.location.reload(); }, 800);
  }

  /* ── Lógica principal de refresh ─────────────────────── */
  function doRefresh() {
    if (_fired) return;
    _fired = true;
    showSpinner();

    var reg = window._vkSwReg;

    // Caso 1: ya hay un SW nuevo esperando (detectado en background)
    if (reg && reg.waiting) {
      _activateSwAndReload(reg.waiting);
      return;
    }

    // Caso 2: refrescar datos (el SW ya se actualiza solo con skipWaiting)
    if (reg && typeof reg.update === 'function') {
      reg.update().catch(function() {}); // verificar en background, sin bloquear
      _doDataRefresh();
    } else {
      // SW no disponible (o aún no registrado): refrescar datos
      _doDataRefresh();
    }
  }

  /* ── Posición de scroll del área activa ──────────────── */
  function getScrollTop() {
    var area = document.querySelector('.screen.active .scroll-area');
    return area ? area.scrollTop : (window.scrollY || document.documentElement.scrollTop || 0);
  }

  /* ══ TOUCH EVENTS (móvil) ═══════════════════════════════ */
  document.addEventListener('touchstart', function(e) {
    _startY  = e.touches[0].clientY;
    _pulling = false;
  }, { passive: true });

  document.addEventListener('touchmove', function(e) {
    if (_fired) return;
    var dy = (e.touches[0].clientY - _startY) * RESISTANCE;
    if (dy < 8 || getScrollTop() > 4) {
      if (_pulling) hideIndicator();
      _pulling = false;
      return;
    }
    _pulling = true;
    setIndicator(dy / THRESHOLD);
  }, { passive: true });

  document.addEventListener('touchend', function() {
    if (!_pulling) return;
    _pulling = false;
    var el = getIndicator();
    var ty = parseFloat((el.style.transform.match(/translateY\(([^p]+)px\)/) || [0, '-72'])[1]);
    if (ty >= 6) {
      doRefresh();
    } else {
      hideIndicator();
    }
  });

  document.addEventListener('touchcancel', function() {
    _pulling = false;
    hideIndicator();
  });

  /* ══ MOUSE WHEEL (desktop) ══════════════════════════════
     Scroll hacia arriba desde el tope: umbral 250 unidades.
  ══════════════════════════════════════════════════════════ */
  var _wheelAccum = 0;
  var _wheelTimer = null;

  document.addEventListener('wheel', function(e) {
    if (_fired) return;
    if (getScrollTop() > 6) { _wheelAccum = 0; return; }
    if (e.deltaY >= 0)       { _wheelAccum = 0; return; }

    _wheelAccum += Math.abs(e.deltaY);
    setIndicator(_wheelAccum / 250);

    clearTimeout(_wheelTimer);
    _wheelTimer = setTimeout(function() {
      if (_wheelAccum >= 220) {
        doRefresh();
      } else {
        hideIndicator();
      }
      _wheelAccum = 0;
    }, 320);
  }, { passive: true });

})();
