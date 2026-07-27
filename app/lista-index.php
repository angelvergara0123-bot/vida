<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

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

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<meta name="theme-color" content="#6b2447">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="vidakushala">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
<link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152.png">
<link rel="apple-touch-icon" sizes="144x144" href="/icons/icon-144.png">
<link rel="apple-touch-icon" sizes="128x128" href="/icons/icon-128.png">
<link rel="icon" type="image/png" sizes="96x96" href="/icons/icon-96.png">
<link rel="icon" type="image/png" sizes="72x72" href="/icons/icon-72.png">
<title>vidakushala</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://accounts.google.com/gsi/client" async defer></script>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<style>
.screen{opacity:0;pointer-events:none;transform:translateX(24px)}
.screen.active{opacity:1!important;pointer-events:all!important;transform:translateX(0)!important}

/* ═══ NOTIFICATIONS SYSTEM ═══════════════════════════════ */
.notif-scroll{padding:0!important}
.notif-header{
  display:flex;align-items:flex-start;justify-content:space-between;
  padding:1.1rem 1rem .75rem;gap:.75rem;flex-wrap:wrap
}
.notif-header-actions{display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;padding-top:.2rem}
.notif-action-btn{
  display:inline-flex;align-items:center;gap:.35rem;
  padding:.35rem .8rem;border-radius:20px;font-size:.78rem;font-weight:600;
  border:none;cursor:pointer;transition:all .18s;font-family:inherit
}
.notif-read-all-btn{background:rgba(196,77,138,.1);color:var(--vk-plum)}
.notif-read-all-btn:hover{background:rgba(196,77,138,.2)}

/* Filter row */
.notif-filter-row{
  display:flex;gap:.4rem;padding:.1rem 1rem .75rem;overflow-x:auto;
  scrollbar-width:none;-webkit-overflow-scrolling:touch
}
.notif-filter-row::-webkit-scrollbar{display:none}
.nfilter-btn{
  flex-shrink:0;padding:.32rem .75rem;border-radius:20px;
  border:1.5px solid rgba(196,77,138,.18);background:#fff;
  font-size:.78rem;font-weight:600;color:var(--ts);
  cursor:pointer;transition:all .15s;font-family:inherit;white-space:nowrap
}
.nfilter-btn.nfilter-active{
  background:var(--grad-accent);color:#fff;border-color:transparent;
  box-shadow:0 3px 10px rgba(196,77,138,.25)
}

/* Push banner */
.notif-push-banner{margin:.1rem 1rem .75rem;background:var(--vk-petal);border-radius:14px;border:1px solid rgba(196,77,138,.15)}
.notif-push-inner{display:flex;align-items:center;gap:.65rem;padding:.75rem .9rem;flex-wrap:nowrap}
.notif-push-icon{font-size:1.5rem;flex-shrink:0}
.notif-push-text{flex:1;min-width:0}
.notif-push-text strong{font-size:.83rem;display:block;color:var(--vk-plum)}
.notif-push-text p{font-size:.75rem;color:var(--ts);margin-top:.1rem}
.btn-push-enable{
  padding:.35rem .8rem;background:var(--grad-accent);color:#fff;
  border:none;border-radius:10px;font-size:.78rem;font-weight:700;
  cursor:pointer;flex-shrink:0;font-family:inherit
}
.btn-push-dismiss{
  background:none;border:none;cursor:pointer;font-size:.9rem;
  color:var(--tu);padding:.2rem;flex-shrink:0
}

/* Section headers */
.notif-section-hd{
  display:flex;align-items:center;gap:.45rem;
  padding:.35rem 1rem .3rem;font-size:.74rem;font-weight:700;
  color:var(--tu);text-transform:uppercase;letter-spacing:.04em
}
.notif-section-hd--read{margin-top:.75rem}
.notif-collapse-btn{
  margin-left:auto;background:none;border:none;cursor:pointer;
  font-size:.75rem;color:var(--vk-rose);font-weight:600;
  padding:.15rem .4rem;border-radius:8px;font-family:inherit
}
.notif-badge-pill{
  background:var(--grad-accent);color:#fff;border-radius:20px;
  padding:.08rem .5rem;font-size:.72rem;font-weight:700
}

/* Cards list */
.notif-list{padding:.1rem .75rem .75rem;display:flex;flex-direction:column;gap:.5rem}

/* ── NCARD ────────────────────────────────────────────── */
.ncard{
  display:flex;align-items:flex-start;gap:.75rem;
  background:#fff;border-radius:16px;padding:.8rem .9rem;
  border:1.5px solid rgba(196,77,138,.07);
  box-shadow:0 1px 6px rgba(58,15,40,.05);
  cursor:pointer;transition:all .18s ease;position:relative;overflow:hidden;
  -webkit-tap-highlight-color:transparent
}
.ncard:hover{box-shadow:0 4px 16px rgba(58,15,40,.11);border-color:rgba(196,77,138,.16);transform:translateY(-1px)}
.ncard:active{transform:translateY(0);box-shadow:0 1px 6px rgba(58,15,40,.07)}
.ncard-unread{border-color:rgba(196,77,138,.22)}
.ncard-unread::before{
  content:'';position:absolute;left:0;top:0;bottom:0;width:3.5px;
  background:var(--grad-accent);border-radius:16px 0 0 16px
}

.ncard-icon{
  width:42px;height:42px;border-radius:13px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-size:1.22rem;color:#fff;
  box-shadow:0 3px 8px rgba(0,0,0,.12)
}

.ncard-body{flex:1;min-width:0}
.ncard-row1{display:flex;align-items:center;gap:.35rem;margin-bottom:.25rem;flex-wrap:wrap}
.ncard-type{
  font-size:.68rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.04em;padding:.08rem .42rem;border-radius:8px;
  border:1.5px solid currentColor;opacity:.75
}
.ncard-global{
  font-size:.68rem;background:rgba(100,100,220,.1);color:#5555cc;
  padding:.08rem .42rem;border-radius:8px;font-weight:600
}
.ncard-dot{
  width:7px;height:7px;border-radius:50%;flex-shrink:0;
  background:var(--grad-accent);margin-left:auto
}
.ncard-title{
  font-size:.87rem;font-weight:700;color:#2c1020;line-height:1.3;
  margin-bottom:.2rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis
}
.ncard-msg{
  font-size:.8rem;color:var(--ts);line-height:1.45;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden
}
.ncard-meta{
  display:flex;align-items:center;justify-content:space-between;
  margin-top:.4rem;gap:.5rem
}
.ncard-time{font-size:.72rem;color:var(--tu)}
.ncard-view{
  font-size:.76rem;font-weight:700;background:none;border:none;
  cursor:pointer;padding:.18rem .6rem;border-radius:8px;
  border:1.5px solid currentColor;font-family:inherit;
  transition:all .15s;white-space:nowrap
}
.ncard-view:hover{opacity:.75}
.ncard-del{
  margin-left:auto;background:none;border:none;cursor:pointer;
  font-size:.85rem;color:#c0a0b0;padding:.1rem .35rem;
  border-radius:6px;line-height:1;transition:all .15s;
  flex-shrink:0;
}
.ncard-del:hover{color:#c62828;background:rgba(198,40,40,.08);}

/* Empty state */
.notif-empty{
  display:flex;flex-direction:column;align-items:center;
  padding:3.5rem 2rem 2rem;text-align:center
}
.notif-empty-icon{font-size:3rem;margin-bottom:.75rem;opacity:.5}
.notif-empty h3{font-family:'Cormorant Garamond',serif;font-size:1.3rem;color:var(--vk-plum);margin-bottom:.4rem}
.notif-empty p{font-size:.84rem;color:var(--tu);line-height:1.5}

/* Loading */
.notif-loading{display:flex;justify-content:center;padding:2.5rem 1rem}
/* ── Chat screen: anular padding del scroll-area y fijar input arriba del nav ── */
#screen-chat .scroll-area{
  padding-bottom:0!important;
  overflow:hidden!important;
  display:flex!important;
  flex-direction:column!important;
}
#screen-chat .scroll-area > div[style*="relative"]{
  flex:1;
  min-height:0;
}
#vkc-chat{
  padding-bottom:0!important;
}
#vk-ai-wall{
  padding-bottom:0!important;
}
/* Banner de imagen del wall — responsive */
#vk-ai-wall-img{
  position:relative;
  overflow:hidden;
  flex-shrink:0;
}
#vk-ai-wall-img img{
  width:100%;
  display:block;
  object-fit:cover;
  object-position:center top;
  aspect-ratio:16/7;
}
@media(min-width:1023px){
  /* Desktop: imagen ocupa mitad izquierda, info mitad derecha */
  #vk-ai-wall{
    flex-direction:row!important;
    align-items:stretch!important;
    overflow:hidden!important;
  }
  #vk-ai-wall > div {
    flex-direction:row!important;
    max-width:100%!important;
    width:100%!important;
  }
  #vk-ai-wall-img{
    width:55%!important;
    flex-shrink:0!important;
  }
  #vk-ai-wall-img img{
    width:100%!important;
    height:100%!important;
    aspect-ratio:unset!important;
    object-fit:cover;
    object-position:center;
  }
  #vk-ai-wall > div > div:last-child{
    width:45%;
    justify-content:center!important;
    padding:2.5rem 2.5rem!important;
    border-left:1px solid rgba(196,77,138,.15);
  }
  #vk-ai-wall-name{ font-size:1.9rem!important; }
  #vk-ai-wall-desc{ font-size:.95rem!important; max-width:100%!important; }
}
/* Ocultar bottom-nav solo cuando el chat real está activo (no el wall) */
#screen-chat.active:not(.has-wall) ~ #bottom-nav,
body:has(#screen-chat.active:not(.has-wall)) #bottom-nav{
  display:none!important;
}

/* ═══════════════════════════════════════════════════════
   DESKTOP TOPBAR — barra superior derecha (solo ≥1025px)
═══════════════════════════════════════════════════════ */
#desktop-topbar {
  display: none; /* oculto en móvil */
}
/* Dropdown desktop: oculto en móvil, siempre */
#dtb-dropdown {
  display: none;
}
@media (min-width: 1025px) {
  #desktop-topbar {
    display: flex;
    align-items: center;
    gap: .5rem;
    position: fixed;
    top: 1rem;
    right: 1.5rem;
    z-index: 200;
    background: #fff;
    border-radius: 50px;
    padding: .45rem .75rem;
    box-shadow: 0 2px 16px rgba(58,15,40,.10);
    border: 1px solid rgba(196,77,138,.12);
  }

  /* Espacio para que el topbar no tape el contenido en escritorio */
  /* Solo las pantallas que empiezan con contenido en la parte superior */
  #screen-notifications .notif-header,
  #screen-notifications .notif-filter-row {
    margin-top: .5rem;
  }
  /* Espacio para el topbar desktop — SOLO en escritorio */
  @media (min-width: 1025px) {
    .screen .scroll-area {
      padding-top: 4rem;
    }
    .screen .scroll-area .home-hero {
      padding-top: 0;
    }
  }

  /* Botones icono */
  .dtb-btn {
    width: 36px; height: 36px;
    border: none; background: none;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    color: #3a0f28;
    font-size: 1.25rem;
    transition: background .15s;
    position: relative;
  }
  .dtb-btn:hover { background: rgba(196,77,138,.1); }

  /* Badge notificaciones */
  .dtb-notif { position: relative; }
  #dtb-notif-badge {
    position: absolute;
    top: -2px; right: -2px;
    background: #c44d8a;
    color: #fff;
    font-size: .6rem;
    font-weight: 800;
    min-width: 17px; height: 17px;
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid #fff;
    font-family: 'DM Sans', sans-serif;
    pointer-events: none;
  }

  /* Avatar + menú */
  .dtb-user {
    display: flex; align-items: center; gap: .4rem;
    cursor: pointer;
    padding: .2rem .35rem;
    border-radius: 50px;
    transition: background .15s;
    position: relative;
    margin-left: .15rem;
  }
  .dtb-user:hover { background: rgba(196,77,138,.08); }

  .dtb-avatar {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, #c44d8a, #6b2447);
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: .85rem;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid rgba(196,77,138,.25);
  }
  .dtb-avatar img { width: 100%; height: 100%; object-fit: cover; }

  .dtb-chevron {
    font-size: .65rem;
    color: #888;
    transition: transform .2s;
  }
  .dtb-user.open .dtb-chevron { transform: rotate(180deg); }

  /* Dropdown */
  .dtb-dropdown {
    display: none;
    position: fixed;
    top: 4rem;
    right: 1.5rem;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 40px rgba(58,15,40,.2);
    border: 1px solid rgba(196,77,138,.15);
    min-width: 240px;
    overflow: hidden;
    animation: dtbDropIn .18s ease;
    z-index: 9999;
  }
  @keyframes dtbDropIn {
    from { opacity:0; transform:translateY(-8px); }
    to   { opacity:1; transform:translateY(0); }
  }
  #dtb-dropdown.dtb-open { display: block !important; }

  /* Ocultar topbar desktop cuando no hay sesión */
  body.is-logged-out #desktop-topbar { display: none !important; }

  .dtb-dd-header {
    padding: 1rem 1.1rem .75rem;
    border-bottom: 1px solid rgba(196,77,138,.1);
    background: linear-gradient(135deg, #fce8f1 0%, #fff 100%);
  }
  .dtb-dd-name  { font-weight: 700; color: #6b2447; font-size: .88rem; }
  .dtb-dd-email { font-size: .75rem; color: #888; margin-top: .15rem; }

  .dtb-dd-items { padding: .4rem 0; }
  .dtb-dd-items button {
    width: 100%; text-align: left;
    border: none; background: none;
    padding: .65rem 1.1rem;
    font-family: 'DM Sans', sans-serif;
    font-size: .84rem; color: #3a0f28;
    cursor: pointer;
    display: flex; align-items: center; gap: .6rem;
    transition: background .12s;
  }
  .dtb-dd-items button:hover { background: rgba(196,77,138,.07); }
  .dtb-dd-items button i { width: 16px; color: #c44d8a; font-size: .85rem; }
  .dtb-dd-logout { color: #c62828 !important; margin-top: .2rem; border-top: 1px solid #f5e0e8 !important; }
  .dtb-dd-logout i { color: #c62828 !important; }
}

/* ═══════════════════════════════════════════════════════
   HOME GRID — 3 columnas en escritorio
   (style.css usa 4 col / 1120px — aquí lo sobreescribimos)
═══════════════════════════════════════════════════════ */
@media (min-width: 1023px) {
  /* 3 columnas en lugar de 4, mismo max-width que style.css */
  .home-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  }

  /* Notificaciones del home — mismo ancho/margen que home-grid */
  #home-notifs-section {
    max-width: 1120px;
    margin: 0 auto;
    padding: 0 1rem 1.25rem;
  }
}

/* ═══════════════════════════════════════════════════════
   NOTIFICACIONES — Vista escritorio mejorada (≥1025px)
═══════════════════════════════════════════════════════ */
@media (min-width: 1025px) {

  /* Contenedor principal de la pantalla de notificaciones */
  #screen-notifications .scroll-area {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem 1.5rem 2rem;
  }

  /* Header */
  #screen-notifications .notif-header {
    padding:40px 100px 10px 0px;
    border-bottom: 1.5px solid rgba(196,77,138,.1);
    margin-bottom: .75rem;
  }

  /* Filtros en una fila sin scroll */
  #screen-notifications .notif-filter-row {
    padding: .1rem 0 .75rem;
    flex-wrap: wrap;
    overflow: visible;
  }

  /* Push banner */
  #screen-notifications .notif-push-banner {
    margin: 0 0 .75rem;
    border-radius: 14px;
  }

  /* Layout 2 columnas para las cards */
  #screen-notifications .notif-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .65rem;
    padding: 0;
  }

  /* Sección headers (Sin leer / Leídas) — ocupan las 2 columnas */
  #screen-notifications .notif-section-hd {
    grid-column: 1 / -1;
    padding: .25rem 0 .2rem;
  }

  /* Cards más compactas en escritorio */
  #screen-notifications .ncard {
    border-radius: 14px;
    padding: .75rem .85rem;
  }

  #screen-notifications .ncard-title {
    font-size: .84rem;
  }

  #screen-notifications .ncard-msg {
    font-size: .77rem;
    -webkit-line-clamp: 2;
  }

  /* Sin notificaciones — centrado */
  #screen-notifications #notif-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem 1rem;
  }

  /* Unread section — ancho completo */
  #screen-notifications #notif-unread-section,
  #screen-notifications #notif-read-section {
    display: contents; /* permite que los hijos participen en el grid */
  }
}

/* Escritorio grande (≥1280px) — 3 columnas */
@media (min-width: 1280px) {
  #screen-notifications .notif-list {
    grid-template-columns: 1fr 1fr 1fr;
  }
}

/* ═══════════════════════════════════════════════════════
   CABECERA MÓVIL UNIFICADA
   Logo (o botón atrás) izquierda | Título centro | Acciones derecha
   Solo activo en móvil (<1023px). style.css ya oculta .top-bar en ≥1023px
═══════════════════════════════════════════════════════ */

/* Estructura flex del top-bar — SOLO en móvil */
@media (max-width: 1022px) {

#home-notifs-section{margin: 0 auto;
    padding: 0 1rem 1.25rem;}

  .mob-hdr {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 0 .85rem !important;
    height: 58px !important;
    gap: .5rem;
    flex-wrap: nowrap;
  }
}

/* Logo del home */
.mob-hdr .mhdr-logo {
  flex-shrink: 0;
  display: flex;
  align-items: center;
}
.mob-hdr .mhdr-logo img {
  height: 42px;
  width: auto;
  display: block;
}

/* Botón atrás en otras pantallas */
.mob-back {
  flex-shrink: 0;
  min-width: 36px !important;
  padding: .3rem !important;
  font-size: .9rem !important;
}

/* Título central */
.mob-title {
  flex: 1;
  text-align: center;
  font-family: 'Cormorant Garamond', serif;
  font-size: 1rem;
  color: var(--vk-plum);
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  padding: 0 .4rem;
}

/* Acciones derecha: campana + avatar */
.mhdr-actions {
  display: flex;
  align-items: center;
  gap: .2rem;
  flex-shrink: 0;
}

/* Botón campana */
.mhdr-btn {
  width: 36px; height: 36px;
  border: none; background: none;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: #3a0f28; font-size: .95rem;
  position: relative;
  -webkit-tap-highlight-color: transparent;
}
.mhdr-btn:active { background: rgba(196,77,138,.12); }

/* Badge campana */
.mhdr-notif-badge {
  position: absolute; top: 1px; right: 1px;
  background: #c44d8a; color: #fff;
  font-size: .55rem; font-weight: 800;
  min-width: 15px; height: 15px;
  border-radius: 20px;
  display: flex; align-items: center; justify-content: center;
  border: 1.5px solid #fff;
  pointer-events: none;
}

/* Avatar */
.mhdr-user {
  display: flex; align-items: center; gap: .2rem;
  cursor: pointer; padding: .2rem .25rem;
  border-radius: 30px;
  -webkit-tap-highlight-color: transparent;
}
.mhdr-user:active { background: rgba(196,77,138,.1); }

.mhdr-avatar {
  width: 22px; height: 22px; border-radius: 50%;
  background: linear-gradient(135deg, #c44d8a, #6b2447);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: .8rem; overflow: hidden;
  border: 2px solid rgba(196,77,138,.3); flex-shrink: 0;
}
.mhdr-avatar img { width:100%; height:100%; object-fit:cover; display:block; }

.mhdr-chevron { font-size: .58rem; color: #888; transition: transform .2s; }
.mhdr-user.open .mhdr-chevron { transform: rotate(180deg); }

/* ── Dropdown móvil ── */
.mhdr-dropdown {
  display: none;
  pointer-events: none;
  position: fixed;
  top: 58px; left: .65rem; right: .65rem;
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 10px 40px rgba(58,15,40,.18);
  border: 1px solid rgba(196,77,138,.12);
  z-index: 10000;  /* Por encima de todo — ya no está dentro de .screen */
  overflow: hidden;
  animation: mhdrIn .17s ease;
  max-height: calc(100vh - 70px);
  overflow-y: auto;
}
@media (max-width: 1022px) {
  .mhdr-dropdown.mhdr-open {
    display: block !important;
    pointer-events: auto;
  }
}
/* En escritorio: ocultar siempre (usa dtb-dropdown) */
@media (min-width: 1023px) {
  .mhdr-dropdown { display: none !important; }
}
@keyframes mhdrIn {
  from { opacity:0; transform:translateY(-8px); }
  to   { opacity:1; transform:translateY(0); }
}

.mhdr-dd-header {
  display: flex; align-items: center; gap: .75rem;
  padding: .9rem 1rem .8rem;
  background: linear-gradient(135deg,#fce8f1,#fff);
  border-bottom: 1px solid rgba(196,77,138,.1);
}
.mhdr-dd-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg,#c44d8a,#6b2447);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 1rem; overflow: hidden; flex-shrink: 0;
}
.mhdr-dd-avatar img { width:100%; height:100%; object-fit:cover; }
.mhdr-dd-name  { font-weight: 700; color: #6b2447; font-size: .88rem; }
.mhdr-dd-email { font-size: .73rem; color: #888; margin-top: .08rem; }

.mhdr-dd-items { padding: .35rem 0; }
.mhdr-dd-items button {
  width: 100%; text-align: left; border: none; background: none;
  padding: .7rem 1rem; font-family: 'DM Sans', sans-serif;
  font-size: .87rem; color: #3a0f28; cursor: pointer;
  display: flex; align-items: center; gap: .6rem;
}
.mhdr-dd-items button:active { background: rgba(196,77,138,.07); }
.mhdr-dd-items button i { width:16px; color:#c44d8a; font-size:.85rem; text-align:center; }
.mhdr-dd-logout { color:#c62828 !important; border-top:1px solid rgba(198,40,40,.1) !important; }
.mhdr-dd-logout i { color:#c62828 !important; }

/* Ocultar dropdown en escritorio */
@media (min-width: 1023px) {
  #mhdr-dropdown {
    display: none !important;
  }
}




/* ═══════════════════════════════════════════════════════════════════
   LAYOUT ESCRITORIO — Reglas seguras y no invasivas
   Solo afectan resoluciones >= 1023px, sin tocar el layout móvil.
   No sobreescriben nada de style.css con !important innecesario.
═══════════════════════════════════════════════════════════════════ */
@media (min-width: 1023px) {

  /* Variable de ancho máximo */
  :root {
    --vk-w: 1200px;
    --vk-px: 2rem;
  }

  /* ── Cabeceras de sección (solo en escritorio, heredan el max-width del header) ── */
  .desktop-page-header {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding: 2rem var(--vk-px) 1.25rem;
    box-sizing: border-box;
  }

  /* ── Home: secciones bajo el hero ── */
  #home-notifs-section {
    padding-left: var(--vk-px);
    padding-right: var(--vk-px);
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    box-sizing: border-box;
  }

  #last-course-strip {
    max-width: calc(var(--vk-w) - var(--vk-px) * 2);
    margin-left: auto;
    margin-right: auto;
    border-radius: 16px;
    box-sizing: border-box;
  }

  #home-courses-preview {
    padding-left: var(--vk-px);
    padding-right: var(--vk-px);
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    box-sizing: border-box;
  }

  /* ── Cursos: grid de tarjetas ── */
  #courses-list {
    padding-left: var(--vk-px);
    padding-right: var(--vk-px);
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    box-sizing: border-box;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(275px, 1fr));
    gap: 1.25rem;
    align-items: start;
  }

  /* Título sobre el grid de cursos */
  #courses-list ~ p,
  #courses-list + p,
  .courses-header-row {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--vk-px);
    padding-right: var(--vk-px);
    box-sizing: border-box;
  }

  /* ── Detalle de curso ── */
  #course-detail-body {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding: 1.5rem var(--vk-px);
    box-sizing: border-box;
  }

  /* ── Notificaciones ── */
  .notif-header,
  .notif-filter-row,
  #notif-unread-list,
  #notif-read-list,
  .notif-read-header {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--vk-px);
    padding-right: var(--vk-px);
    box-sizing: border-box;
  }

  /* ── Búsqueda: resultados alineados ── */
  #search-filters-row,
  #search-results-list,
  #search-results-public {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--vk-px);
    padding-right: var(--vk-px);
    box-sizing: border-box;
  }

  /* ── Certificados: grid ── */
  #certs-list,
  #certs-grid {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--vk-px);
    padding-right: var(--vk-px);
    box-sizing: border-box;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
    align-items: start;
  }

  /* ── Productos: grid ── */
  #products-list,
  #products-grid {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--vk-px);
    padding-right: var(--vk-px);
    box-sizing: border-box;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.25rem;
    align-items: start;
  }

  /* ── Perfil / Editar perfil ── */
  #profile-content,
  #edit-profile-form {
    max-width: 680px;
    margin-left: auto;
    margin-right: auto;
    padding-left: var(--vk-px);
    padding-right: var(--vk-px);
    box-sizing: border-box;
  }

  /* ── Lesson y Quiz body ── */
  #lesson-content,
  #quiz-content {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding: 1.5rem var(--vk-px);
    box-sizing: border-box;
  }

  /* ── Bienvenida ── */
  #welcome-card {
    max-width: 520px;
    margin: 2rem auto;
  }

  /* ── Cards: altura uniforme en grids ── */
  #courses-list .course-card,
  #home-courses-preview .course-card {
    display: flex;
    flex-direction: column;
  }
  #courses-list .course-body,
  #home-courses-preview .course-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  /* ── Encabezados de pantalla que no tienen desktop-page-header ── */
  #screen-home .home-hero {
    border-radius: 20PX 20PX 0PX 0PX;
    margin-bottom: 20px;
    padding-left: var(--vk-px);
    padding-right: var(--vk-px);
  }
  #screen-home .home-grid {
    padding-left: var(--vk-px);
    padding-right: var(--vk-px);
    max-width: var(--vk-w);
    margin: 0 auto;
    box-sizing: border-box;
  }

  /* ── Prevenir que el desktop-topbar cubra contenido ── */
  .screen.active .scroll-area {
    padding-top: 4rem;
  }
  #screen-home .scroll-area,
  #screen-login .scroll-area,
  #screen-register .scroll-area,
  #screen-welcome .scroll-area {
    padding-top: 0;
  }
}


/* ── Productos: 3 columnas en escritorio, tarjetas verticales ── */
@media (min-width: 1023px) {

  /* Contenedor: max-width 1200px centrado */
  #products-list {
    max-width: var(--vk-w) !important;
    margin-left: auto !important;
    margin-right: auto !important;
    padding: .5rem 2rem !important;
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 1.25rem !important;
    box-sizing: border-box;
  }

  /* Card: cambia de horizontal a vertical en escritorio */
  #products-list .product-card {
    flex-direction: column !important;
    align-items: stretch !important;
    padding: 0 !important;
    gap: 0 !important;
    margin-bottom: 0 !important;
    border-radius: 18px !important;
    overflow: hidden !important;
    transition: transform .15s, box-shadow .15s !important;
  }
  #products-list .product-card:hover {
    transform: translateY(-4px) !important;
    box-shadow: 0 12px 32px rgba(107,36,71,.15) !important;
  }

  /* Imagen: ocupa todo el ancho, altura fija */
  #products-list .product-thumb {
    width: 100% !important;
    height: 160px !important;
    border-radius: 0 !important;
    flex-shrink: 0;
  }
  #products-list .product-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  /* Cuerpo de la tarjeta */
  #products-list .product-card > div[style] {
    padding: .9rem 1rem 1rem !important;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  /* Precio: al fondo de la tarjeta */
  #products-list .product-price {
    margin-top: .5rem;
    display: block;
    font-size: 1.1rem !important;
  }

  /* Flecha: ocultar en modo tarjeta */
  #products-list .product-card > span[style*="color:var(--tu)"] {
    display: none !important;
  }

  /* Header del desktop con mismo ancho */
  #screen-products .desktop-page-header {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding: 2rem 2rem 1rem;
    box-sizing: border-box;
  }

  /* Filtros y buscador alineados */
  #products-list ~ div,
  #products-filter-row,
  #product-cats-row,
  .product-header-row {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding-left: 2rem;
    padding-right: 2rem;
    box-sizing: border-box;
  }
}

/* Tablet: 2 columnas */
@media (min-width: 768px) and (max-width: 1022px) {
  #products-list {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 1rem !important;
    padding: .5rem 1rem !important;
  }
  #products-list .product-card {
    flex-direction: column !important;
    align-items: stretch !important;
    padding: 0 !important;
    gap: 0 !important;
    margin-bottom: 0 !important;
  }
  #products-list .product-thumb {
    width: 100% !important;
    height: 130px !important;
    border-radius: 0 !important;
  }
}


/* ── Cursos: 3 columnas en escritorio, tarjetas verticales ── */
@media (min-width: 1023px) {

  /* Contenedor: 3 columnas, centrado a 1200px */
  .cards-stack,
  #courses-list {
    max-width: var(--vk-w) !important;
    margin-left: auto !important;
    margin-right: auto !important;
    padding: .5rem 2rem !important;
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 1.25rem !important;
    box-sizing: border-box;
  }

  /* Card: vertical (imagen arriba, texto abajo) */
  .cards-stack .course-card,
  #courses-list .course-card {
    flex-direction: column !important;
    align-items: stretch !important;
    min-height: unset !important;
    height: 100%;
  }

  /* Imagen: ancho completo, altura fija */
  .cards-stack .course-thumb,
  #courses-list .course-thumb {
    width: 100% !important;
    height: 150px !important;
    flex-shrink: 0;
  }

  /* Cuerpo: ocupa el espacio restante */
  .cards-stack .course-body,
  #courses-list .course-body {
    padding: .85rem .9rem .9rem !important;
    flex: 1;
  }

  /* Título: 2 líneas máximo */
  .cards-stack .course-card h3,
  #courses-list .course-card h3 {
    -webkit-line-clamp: 2;
    font-size: .9rem !important;
  }
}

/* Tablet: 2 columnas */
@media (min-width: 768px) and (max-width: 1022px) {
  .cards-stack {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 1rem !important;
    padding: .5rem 1rem !important;
  }
  .cards-stack .course-card {
    flex-direction: column !important;
    min-height: unset !important;
  }
  .cards-stack .course-thumb {
    width: 100% !important;
    height: 130px !important;
  }
}


/* ── Paquetes: 3 columnas en escritorio, alineado con otros contenidos ── */
@media (min-width: 1023px) {

  #screen-bundles .scroll-area {
    padding-top: 0;
  }

  #screen-bundles .desktop-page-header {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding: 2rem 2rem 1.25rem;
    box-sizing: border-box;
  }

  #bundles-list {
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding: .5rem 2rem 2rem;
    box-sizing: border-box;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
  }

  /* Card: vertical (imagen arriba) — sobreescribe el flex inline del JS */
  #bundles-list .course-card {
    flex-direction: column !important;
    align-items: stretch !important;
    padding: 0 !important;
    gap: 0 !important;
    cursor: pointer;
  }

  /* Imagen del paquete: ancho completo */
  #bundles-list .course-card > img {
    width: 100% !important;
    height: 160px !important;
    object-fit: cover !important;
    border-radius: 18px 18px 0 0 !important;
    flex-shrink: 0;
  }

  /* Cuerpo del paquete */
  #bundles-list .course-card > div[style] {
    padding: 1rem !important;
    flex: 1;
    display: flex;
    flex-direction: column;
  }

  /* Título */
  #bundles-list .course-card h3 {
    font-size: .95rem !important;
  }

  /* Botón al fondo */
  #bundles-list .course-card .btn-small {
    margin-top: auto !important;
    padding-top: .6rem;
    width: 100%;
    text-align: center;
  }
}

/* Tablet: 2 columnas */
@media (min-width: 768px) and (max-width: 1022px) {
  #bundles-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    padding: .5rem 1rem;
  }
  #bundles-list .course-card {
    flex-direction: column !important;
    padding: 0 !important;
  }
  #bundles-list .course-card > img {
    width: 100% !important;
    height: 130px !important;
    border-radius: 14px 14px 0 0 !important;
  }
  #bundles-list .course-card > div[style] {
    padding: .85rem !important;
  }
}


/* ═══════════════════════════════════════════════════════════════════
   ENCUESTAS — Diseño lista (fila horizontal con icono + texto + botón)
═══════════════════════════════════════════════════════════════════ */

/* Contenedor lista */
.polls-list {
  max-width: var(--vk-w);
  margin: 0 auto;
  padding: .5rem 2rem 3rem;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: .85rem;
}

/* Fila de encuesta */
.poll-row {
  background: #fff;
  border: 1px solid rgba(196,77,138,.10);
  border-radius: 18px;
  padding: 1.1rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 1.1rem;
  cursor: pointer;
  transition: box-shadow .15s, transform .12s;
  box-shadow: 0 2px 10px rgba(107,36,71,.06);
}
.poll-row:hover {
  box-shadow: 0 6px 24px rgba(107,36,71,.12);
  transform: translateY(-1px);
}

/* Icono izquierdo */
.poll-row-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: linear-gradient(135deg, #fce8f1, #f6d0e5);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 1.25rem;
  color: var(--vk-rose, #c44d8a);
}

/* Cuerpo central */
.poll-row-body {
  flex: 1;
  min-width: 0;
}
.poll-row-title {
  font-size: .98rem;
  font-weight: 700;
  color: var(--td, #3a0f28);
  line-height: 1.35;
  margin: 0 0 .2rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.poll-row-desc {
  font-size: .82rem;
  color: var(--ts, #888);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Meta (X preguntas) */
.poll-row-meta {
  flex-shrink: 0;
  font-size: .82rem;
  color: var(--tu, #aaa);
  white-space: nowrap;
  min-width: 90px;
  text-align: right;
}

/* Botón Responder */
.poll-row-btn {
  flex-shrink: 0;
  border: 2px solid var(--vk-rose, #c44d8a);
  background: transparent;
  color: var(--vk-rose, #c44d8a);
  padding: .55rem 1.25rem;
  border-radius: 50px;
  font-family: 'DM Sans', sans-serif;
  font-size: .88rem;
  font-weight: 700;
  cursor: pointer;
  transition: background .15s, color .15s;
  white-space: nowrap;
}
.poll-row-btn:hover {
  background: var(--vk-rose, #c44d8a);
  color: #fff;
}
.poll-row-btn--done {
  border-color: #2e7d32;
  color: #2e7d32;
  background: #f0faf0;
}
.poll-row-btn--done:hover {
  background: #2e7d32;
  color: #fff;
}

/* Móvil: adaptar para pantallas pequeñas */
@media (max-width: 600px) {
  .polls-list {
    padding: .5rem .85rem 5rem;
    gap: .65rem;
  }
  .poll-row {
    flex-wrap: wrap;
    gap: .75rem;
    padding: .9rem 1rem;
  }
  .poll-row-meta {
    display: none; /* ocultar en móvil muy pequeño */
  }
  .poll-row-btn {
    width: 100%;
    text-align: center;
  }
  .poll-row-title {
    white-space: normal;
  }
}

/* Tablet */
@media (min-width: 601px) and (max-width: 1022px) {
  .polls-list {
    padding: .5rem 1.25rem 4rem;
  }
  .poll-row-icon {
    width: 46px;
    height: 46px;
  }
}


/* ── Home Grid: layout horizontal en escritorio (icono + texto + chevron) ── */
@media (min-width: 1023px) {

  /* Contenedor: 3 columnas, centrado */
  .home-grid {
    grid-template-columns: repeat(3, 1fr) !important;
    max-width: var(--vk-w);
    margin-left: auto;
    margin-right: auto;
    padding: 1.25rem 2rem !important;
    gap: 1rem !important;
    margin-top: 0 !important;
    position: static !important;
  }

  /* Card: horizontal con icono a la izquierda */
  .menu-card {
    flex-direction: row !important;
    align-items: center !important;
    text-align: left !important;
    padding: 1.1rem 1.25rem !important;
    gap: 1rem !important;
    border-radius: 18px !important;
  }

  /* Icono: tamaño fijo a la izquierda */
  .menu-icon {
    width: 52px !important;
    height: 52px !important;
    flex-shrink: 0;
    margin-bottom: 0 !important;
    border-radius: 14px !important;
    font-size: 1.35rem !important;
  }

  /* Texto: título + subtítulo, ocupa el espacio restante */
  .menu-text {
    flex: 1;
    min-width: 0;
  }
  .menu-card .menu-text h3 {
    font-size: 1rem !important;
    font-weight: 700 !important;
    color: var(--td) !important;
    margin: 0 0 .15rem !important;
  }
  .menu-card .menu-text p {
    font-size: .82rem !important;
    color: var(--ts) !important;
    margin: 0 !important;
  }

  /* Chevron a la derecha */
  .menu-chevron {
    font-size: .85rem;
    color: var(--ts, #aaa);
    flex-shrink: 0;
    opacity: .5;
    transition: opacity .15s, transform .15s;
  }
  .menu-card:hover .menu-chevron {
    opacity: 1;
    transform: translateX(3px);
  }

  /* Colores de iconos */
  .icon-polls {
    background: #7c3357;
    color: #fff;
  }
  .icon-chat {
    background: #7c3357;
    color: #fff;
  }
}

/* En móvil: ocultar chevron y menu-text actúa como bloque normal */
@media (max-width: 1022px) {
  .menu-chevron { display: none; }
  .menu-text { text-align: center; }
  .menu-text h3, .menu-card h3 { font-size: .88rem; }
  .menu-text p, .menu-card p  { font-size: .73rem; }
}

</style>
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
    <button class="snav-item" id="snav-bundles" onclick="goto('bundles')"><span class="snav-icon"><i class="fas fa-cube"></i></span>Paquetes</button>
    <button class="snav-item" id="snav-products" onclick="goto('products')"><span class="snav-icon"><i class="fas fa-shopping-cart"></i></span>Productos</button>
    <button class="snav-item" id="snav-polls" onclick="goto('polls')"><span class="snav-icon"><i class="fas fa-pen-to-square"></i></span>Encuestas</button>
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
      <div style="display:flex;justify-content:center;margin-bottom:.65rem;width:100%">
        <div class="g_id_signin" data-type="standard" data-shape="rectangular"
          data-theme="outline" data-text="signin_with" data-size="large"
          data-locale="es" data-width="340"></div>
      </div>


      <div class="divider"><span>o con correo</span></div>
      <div class="field">
        <label>Correo electrónico</label>
        <input type="email" id="login-user" placeholder="correo@ejemplo.com" autocomplete="email">
      </div>
      <div class="field">
        <label>Contraseña</label>
        <input type="password" id="login-pass" placeholder="Tu contraseña" autocomplete="current-password" onkeydown="if(event.key==='Enter')loginEmail()">
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
        <input type="password" id="reg-pass" placeholder="Mínimo 8 caracteres" autocomplete="new-password">
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
      <div class="menu-card" onclick="goto('bundles')"><div class="menu-icon icon-box icon-bundles"><i class="fas fa-cube"></i></div><div class="menu-text"><h3>Paquetes</h3><p>Combos de cursos</p></div><i class="fas fa-chevron-right menu-chevron"></i></div>
      <div class="menu-card" onclick="goto('products')"><div class="menu-icon icon-box icon-products"><i class="fas fa-shopping-cart"></i></div><div class="menu-text"><h3>Productos</h3><p>Catálogo</p></div><i class="fas fa-chevron-right menu-chevron"></i></div>
      <div class="menu-card" onclick="goto('polls')"><div class="menu-icon icon-box icon-polls"><i class="fas fa-clipboard-list"></i></div><div class="menu-text"><h3>Encuestas</h3><p>Tu opinión</p></div><i class="fas fa-chevron-right menu-chevron"></i></div>
      <div class="menu-card" onclick="goto('chat')"><div class="menu-icon icon-box icon-chat"><i class="fas fa-robot"></i></div><div class="menu-text"><h3>Chat IA</h3><p>Asistente virtual</p></div><i class="fas fa-chevron-right menu-chevron"></i></div>
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
      <span style="font-size:1.7rem;flex-shrink:0"><i class="fa-solid fa-play"></i></span>
      <div style="flex:1"><strong style="display:block;font-size:.9rem;color:var(--vk-plum);font-weight:700">Continuar donde lo dejaste</strong><span id="last-course-name" style="font-size:.8rem;color:var(--ts);margin-top:.1rem;display:block"></span></div>
      <button style="background:var(--vk-rose);color:white;border:none;padding:.5rem 1rem;border-radius:10px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;white-space:nowrap;flex-shrink:0">Ver →</button>
    </div>
    <div id="home-courses-preview"></div>
  </div>
</div>

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
    <button class="back-btn" style="color:var(--vk-pink)" onclick="stopActiveVideo();goto('course-detail')">← Clase</button>
    <span id="lesson-course-label" style="font-size:.85rem;color:#9a8090;font-family:'DM Sans',sans-serif;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
    
  </div>
  <div class="lesson-desktop-body">
    <div id="video-container" style="background:#000;width:100%;flex-shrink:0"></div>
    <div class="video-info">
      <div class="video-info-text">
        <h3 id="lesson-title">Cargando...</h3>
        <p id="lesson-desc"></p>
      </div>
      <button class="btn-done" id="btn-lesson-done" onclick="markLessonDone()">✓ Marcar como vista</button>
    </div>
  </div>
</div>

<!-- QUIZ -->
<div class="screen" id="screen-quiz">
  <div class="top-bar mobile-only mob-hdr">
    <button class="back-btn mob-back" onclick="goto('course-detail')"><i class="fas fa-arrow-left"></i></button>
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
    <div class="product-hero" id="prod-hero"><span style="font-size:5rem">🎓</span></div>
    <div class="product-detail-body" id="prod-body"><div class="spinner-wrap"><div class="spinner"></div></div></div>
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
      <div class="profile-row" onclick="goto('certificates')"><span class="profile-row-icon"><i class="fa-solid fa-award"></i></span><div class="profile-row-info"><strong>Mis certificados</strong><span>Cursos completados</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="goto('search')"><span class="profile-row-icon"><i class="fas fa-search"></i></span><div class="profile-row-info"><strong>Explorar cursos</strong><span>Descubrir nuevos</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="goto('products')"><span class="profile-row-icon"><i class="fas fa-shopping-cart"></i></span><div class="profile-row-info"><strong>Productos</strong><span>Catálogo</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="goto('settings')"><span class="profile-row-icon"><i class="fa-solid fa-gear"></i></span><div class="profile-row-info"><strong>Editar perfil</strong><span>Nombre, teléfono, bio</span></div><span style="color:var(--tu)">›</span></div>
      <div class="profile-row" onclick="goto('notifications')"><span class="profile-row-icon"><i class="fas fa-bell"></i></span><div class="profile-row-info"><strong>Notificaciones</strong><span>Preferencias de email</span></div><span style="color:var(--tu)">›</span></div>
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
  <div class="scroll-area" style="padding:1rem 1rem 5rem">
    <div class="desktop-page-header">
      <div><h2 class="desktop-page-title">Mis Certificados</h2><p class="desktop-page-sub">Diplomas de cursos completados</p></div>
      <button class="btn btn-outline btn-sm" onclick="loadCertificates()" style="align-self:flex-start;font-size:.78rem">🔄 Actualizar</button>
    </div>
    <div id="cert-list"><div class="spinner-wrap"><div class="spinner"></div>Cargando...</div></div>
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
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.5rem 1.75rem 2rem;text-align:center;">
            <h2 id="vk-ai-wall-name" style="font-size:1.55rem;color:#f0d8e8;margin:0 0 .5rem;font-weight:700;line-height:1.25;font-family:'Cormorant Garamond',serif"></h2>
            <p id="vk-ai-wall-desc" style="color:rgba(240,216,232,.6);font-size:.88rem;max-width:300px;line-height:1.65;margin:0 auto 1.25rem"></p>
            <div id="vk-ai-wall-price" style="margin-bottom:.75rem"></div>
            <div id="vk-ai-wall-cta" style="width:100%;max-width:300px"></div>
          </div>
        </div>
      </div>

      <!-- PANEL B: chat activo — estructura original intacta -->
      <div id="vkc-chat" style="position:absolute;inset:0;display:flex;flex-direction:column;background:linear-gradient(160deg,#12061a 0%,#1a0828 60%,#0f1220 100%);">
        <div id="vkc-msgs" style="flex:1;overflow-y:auto;padding:1rem .95rem .6rem;display:flex;flex-direction:column;gap:.65rem;scroll-behavior:smooth;font-family:'DM Sans',sans-serif;"></div>
        <div style="flex-shrink:0;padding:.6rem .9rem .75rem;background:rgba(10,4,18,.85);border-top:1px solid rgba(196,77,138,.18);backdrop-filter:blur(12px);">
          <div id="vkc-inputwrap" style="display:flex;gap:.5rem;align-items:flex-end;background:rgba(255,255,255,.06);border:1.5px solid rgba(196,77,138,.25);border-radius:22px;padding:.48rem .5rem .48rem .95rem;transition:border-color .2s,box-shadow .2s;">
            <textarea id="vkc-ta" rows="1" placeholder="Escribe un mensaje…"
              style="flex:1;resize:none;border:none;outline:none;font-family:'DM Sans',sans-serif;font-size:.93rem;color:#f0d8e8;background:transparent;line-height:1.45;max-height:110px;overflow-y:auto;padding:0;caret-color:#e05fa0;"
              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();vkcSend();}"
              oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,110)+'px';"
              onfocus="var w=document.getElementById('vkc-inputwrap');w.style.borderColor='#e05fa0';w.style.boxShadow='0 0 0 3px rgba(196,77,138,.18),0 8px 24px rgba(0,0,0,.4)';"
              onblur="var w=document.getElementById('vkc-inputwrap');w.style.borderColor='rgba(196,77,138,.25)';w.style.boxShadow='none';"></textarea>
            <button id="vkc-sendbtn" onclick="vkcSend()"
              style="background:linear-gradient(135deg,#e05fa0,#9b2d62);color:#fff;border:none;border-radius:14px;padding:.52rem .9rem;font-size:1.05rem;cursor:pointer;flex-shrink:0;line-height:1;transition:all .2s;box-shadow:0 4px 16px rgba(196,77,138,.45);"
              onmouseover="this.style.transform='scale(1.08)';this.style.boxShadow='0 6px 22px rgba(196,77,138,.6)'"
              onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 4px 16px rgba(196,77,138,.45)'">➤</button>
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
  <button class="nav-item" id="nav-polls"    onclick="goto('polls')">   <span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span class="nav-label">Encuestas</span></button>
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
<script src="app.js"></script>
<script src="/pull-to-refresh.js?v=1.0"></script>
<script>
/* ═══════════════════════════════════════════════════════════
   PWA — Service Worker + Banner de instalación
═══════════════════════════════════════════════════════════ */
(function () {
  /* 1. Service Worker combinado (OneSignal push + PWA cache en uno solo) */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      // sw.js contiene: importScripts(OneSignalSDK.sw.js) + cache PWA
      // Un solo SW para evitar conflictos de scope
      navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then(function(reg){
          console.log('[VK SW] Registrado OK, scope:', reg.scope);
          setInterval(function(){ reg.update(); }, 3600000);
        })
        .catch(function(err){
          console.warn('[VK SW] Error:', err.message);
        });
    });
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
    if (document.getElementById('vk-pwa-banner')) return;
    var b = document.createElement('div');
    b.id = 'vk-pwa-banner';
    b.style.cssText = [
      'max-width:500px;position:fixed;bottom:0;;right:1px;z-index:8500',
      'background:linear-gradient(135deg,#3a0f28,#6b2447)',
      'color:#fff;padding:1rem 1.1rem',
      'display:flex;align-items:center;gap:.75rem',
      'box-shadow:0 -4px 24px rgba(58,15,40,.5)',
      'font-family:"DM Sans",sans-serif',
      'animation:vkPwaSlide .35s ease'
    ].join(';');
    if (!document.getElementById('vk-pwa-css')) {
      var s = document.createElement('style');
      s.id = 'vk-pwa-css';
      s.textContent = '@keyframes vkPwaSlide{from{transform:translateY(100%)}to{transform:translateY(0)}}';
      document.head.appendChild(s);
    }
    b.innerHTML = '<img src="/icons/icon-72.png" alt="" style="width:42px;height:42px;border-radius:12px;flex-shrink:0">'
      + '<div style="flex:1;min-width:0">'
      + '<p style="font-weight:700;font-size:.88rem;margin:0 0 .1rem">Instala VidaKushala</p>'
      + '<p style="font-size:.75rem;opacity:.75;margin:0">Acceso rápido desde tu pantalla de inicio</p>'
      + '</div>'
      + '<button id="vk-pwa-install-btn" style="background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.3);color:#fff;border-radius:10px;padding:.45rem .9rem;font-size:.82rem;font-weight:600;cursor:pointer;flex-shrink:0;font-family:inherit">Instalar</button>'
      + '<button onclick="vkDismissPwa()" style="background:none;border:none;color:rgba(255,255,255,.55);font-size:1.3rem;cursor:pointer;padding:.1rem .2rem;line-height:1;flex-shrink:0">×</button>';
    document.body.appendChild(b);
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
    if (b) b.remove();
    sessionStorage.setItem('vk_pwa_dismissed', '1');
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