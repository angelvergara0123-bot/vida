<?php
/**
 * ai-chat.php — Chat IA embebido en iframe de la PWA VidaKushala
 *
 * FIX IFRAME: WordPress envía X-Frame-Options: SAMEORIGIN por defecto,
 * bloqueando el iframe en la PWA. Se eliminan esos headers antes y
 * después de cargar WP para garantizar que el iframe funcione.
 */

// ── 1. Cargar WordPress ───────────────────────────────────────────────
$wp_load = dirname(__FILE__) . '/../wp-load.php';
if (!file_exists($wp_load)) $wp_load = dirname(__FILE__) . '/../../wp-load.php';
if (!file_exists($wp_load)) $wp_load = dirname(__FILE__) . '/../../vidakushala.com/public_html/wp-load.php';
require_once($wp_load);

// ── 2. Eliminar TODOS los hooks que envían X-Frame-Options ───────────
// WordPress core lo agrega en send_headers con la función wp_framework_header
remove_action('send_headers', 'wp_framework_header');
// Algunos plugins/themes también lo agregan:
header_remove('X-Frame-Options');
// Hook que se dispara justo antes de enviar headers — máxima prioridad
add_action('send_headers', function() {
    header_remove('X-Frame-Options');
    header_remove('Content-Security-Policy');
}, PHP_INT_MAX);
// También removerlo si ya fue enviado antes de este punto
if (!headers_sent()) {
    header_remove('X-Frame-Options');
}

// ── 3. Autenticar usuario via token ──────────────────────────────────
$vk_token = isset($_GET['vk_token']) ? sanitize_text_field($_GET['vk_token']) : '';
$uid = 0;
if ($vk_token && function_exists('vk_read_token')) {
    $uid = vk_read_token($vk_token);
}
if ($uid) {
    wp_set_current_user($uid);
}

// ── 4. Resolver shortcode del agente ─────────────────────────────────
$shortcode      = '[mwai_chatbot]';
$aichat_product = get_option('vkx_aichat_product', []);
if (!empty($aichat_product['agent_shortcode'])) {
    $shortcode = $aichat_product['agent_shortcode'];
} elseif ($uid) {
    $agent_id = get_user_meta($uid, 'vk_ai_agent_id', true);
    $agents   = get_option('vk_ai_agents', []);
    if ($agent_id && !empty($agents)) {
        foreach ($agents as $a) {
            if ($a['id'] === $agent_id) { $shortcode = $a['shortcode'] ?? $shortcode; break; }
        }
    } elseif (!empty($agents)) {
        $shortcode = $agents[0]['shortcode'] ?? $shortcode;
    }
}

// ── 5. Renderizar el chat MWAI ────────────────────────────────────────
ob_start();
echo do_shortcode($shortcode);
$chat_html = ob_get_clean();

// ── 6. Header final — justo antes del HTML ────────────────────────────
header_remove('X-Frame-Options');
header('X-Frame-Options: ALLOWALL');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Chat IA</title>
  <?php wp_head(); ?>
  <style>
    body, html {
      background: transparent !important;
      margin: 0;
      padding: 0;
      height: 100%;
      overflow: hidden;
      font-family: 'DM Sans', sans-serif;
    }

    .mwai-chat-container {
      max-width: 100% !important;
      width: 100% !important;
      height: 100vh !important;
      border: none !important;
      box-shadow: none !important;
      background: transparent !important;
      margin: 0 !important;
      display: flex !important;
      flex-direction: column !important;
    }

    .mwai-chat {
      flex: 1 !important;
      display: flex !important;
      flex-direction: column !important;
      height: 100% !important;
      background: transparent !important;
    }

    .mwai-chat .mwai-content {
      background: rgba(255, 255, 255, 0.9) !important;
      border-radius: 18px !important;
      box-shadow: 0 4px 15px rgba(58, 15, 40, 0.04) !important;
      border: 1px solid rgba(196, 77, 138, 0.08) !important;
      color: #3d1a2d !important;
    }

    .mwai-chat .mwai-user .mwai-content {
      background: linear-gradient(135deg, #c44d8a, #8b2458) !important;
      color: #ffffff !important;
      border-radius: 18px 18px 0 18px !important;
      box-shadow: 0 4px 15px rgba(196, 77, 138, 0.2) !important;
      border: none !important;
    }

    .mwai-chat .mwai-ai .mwai-content {
      background: #ffffff !important;
      border-radius: 18px 18px 18px 0 !important;
    }

    .mwai-chat .mwai-input {
      background: #ffffff !important;
      border: 1.5px solid rgba(196, 77, 138, 0.15) !important;
      border-radius: 20px !important;
      padding: 0.6rem 1rem !important;
      box-shadow: 0 6px 20px rgba(58, 15, 40, 0.05) !important;
      transition: all 0.2s ease !important;
    }

    .mwai-chat .mwai-input:focus-within {
      border-color: #c44d8a !important;
      box-shadow: 0 6px 20px rgba(196, 77, 138, 0.12) !important;
    }

    .mwai-chat .mwai-input textarea {
      font-family: inherit !important;
      color: #3d1a2d !important;
    }

    .mwai-chat .mwai-input button {
      background: #c44d8a !important;
      color: #ffffff !important;
      border-radius: 12px !important;
      transition: background 0.2s ease !important;
    }

    .mwai-chat .mwai-input button:hover {
      background: #a63972 !important;
    }

    .mwai-chat .mwai-header {
      display: none !important;
    }
  </style>
</head>
<body>
  <?php echo $chat_html; ?>
  <?php wp_footer(); ?>
</body>
</html>
