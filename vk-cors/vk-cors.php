<?php
/**
 * Plugin Name: VK CORS Bridge
 * Description: CORS + login + endpoints para PWA DM Plus
 * Version:     6.0.0
 * Author:      DM Plus
 */
defined('ABSPATH') || exit;

/* ===============================================
   CORS
=============================================== */
add_action('init', function () {
    $origin  = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    
    // Lista de origenes fijos permitidos
    $allowed = array(
        'https://app.vidakushala.com',   // ← nueva app
        'http://localhost:3000',
        'http://localhost:8080',
    );
    
    // Permitir dinamicamente cualquier localhost o 127.0.0.1 para desarrollo
    $is_local_origin = false;
    if ($origin) {
        $parsed = parse_url($origin);
        if (isset($parsed['host']) && ($parsed['host'] === 'localhost' || $parsed['host'] === '127.0.0.1' || strpos($parsed['host'], '.local') !== false)) {
            $is_local_origin = true;
        }
    }

    if (in_array($origin, $allowed, true) || $is_local_origin) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-VK-Token');
        header('Access-Control-Max-Age: 86400');
    }
    if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) { status_header(200); exit; }
});

/* ===============================================
   PERMITIR IFRAMES EN LA PWA (EVITA X-FRAME-OPTIONS)
 =============================================== */
add_action('init', function() {
    remove_action('admin_init', 'send_frame_options_header', 10);
    remove_action('login_init', 'send_frame_options_header', 10);
    remove_action('wp_headers', 'send_frame_options_header', 10);
});

// Bypass REST API Nonce check for vk/v1 endpoints since they use vk_token
add_filter('rest_authentication_errors', function($result) {
    if (!empty($GLOBALS['wp']->query_vars['rest_route'])) {
        $route = untrailingslashit($GLOBALS['wp']->query_vars['rest_route']);
        if (strpos($route, '/vk/v1/') === 0) {
            return true;
        }
    }
    return $result;
}, 9);
add_filter('wp_headers', function($headers) {
    if (isset($headers['X-Frame-Options'])) {
        unset($headers['X-Frame-Options']);
    }
    // Permitir cargar en iframe desde la PWA y entornos de desarrollo
    $headers['Content-Security-Policy'] = "frame-ancestors 'self' https://app.vidakushala.com http://localhost:8080 http://localhost:3000 http://localhost";
    return $headers;
}, 999);

// Interceptar y remover de forma absoluta X-Frame-Options en la página del certificado para permitir la generación in-app
add_action('send_headers', 'vk_allow_certificate_framing', 9999);
add_action('template_redirect', 'vk_allow_certificate_framing', 9999);

function vk_allow_certificate_framing() {
    if (isset($_GET['cert_hash'])) {
        header_remove('X-Frame-Options');
        header('X-Frame-Options: ALLOWALL');
        header("Content-Security-Policy: frame-ancestors 'self' https://app.vidakushala.com http://localhost:8080 http://localhost:3000 http://localhost");
        header("Access-Control-Allow-Origin: https://app.vidakushala.com");
        header("Access-Control-Allow-Credentials: true");
    }
}

/* ════════════════════════════════════════════════════════════════════
   PROXY DE DESCARGA — ?vk_dl=POST_ID&vk_token=TOKEN
   Corre en init prioridad 1, antes de cualquier redirect de WP.
   Lee el archivo del filesystem y lo envía con Content-Disposition:
   attachment + CORS para que el fetch-blob de la app funcione siempre.
═══════════════════════════════════════════════════════════════════ */
add_action('init', 'vkx_handle_file_download', 1);
function vkx_handle_file_download() {
    if (empty($_GET['vk_dl'])) return;

    $post_id = (int) sanitize_text_field($_GET['vk_dl']);
    $token   = sanitize_text_field($_GET['vk_token'] ?? '');

    // Autenticación
    if (!$post_id || !$token) { status_header(400); exit('Bad request'); }
    $uid = vk_read_token($token);
    if (!$uid) { status_header(403); exit('Forbidden'); }

    // Resolver attachment
    $attach_id = 0;
    $sdc_file  = get_post_meta($post_id, 'sdc_file', true);
    if ($sdc_file && is_numeric($sdc_file)) {
        $attach_id = (int) $sdc_file;
    } else {
        $att = get_posts(array(
            'post_type'      => 'attachment',
            'posts_per_page' => 1,
            'post_parent'    => $post_id,
            'post_status'    => 'inherit',
        ));
        if ($att) $attach_id = $att[0]->ID;
    }

    // Ruta física del archivo
    $file_path = $attach_id ? get_attached_file($attach_id) : '';

    if (!$file_path || !file_exists($file_path)) {
        // Fallback: URL externa (archivo no en filesystem local)
        $ext_url = get_post_meta($post_id, '_download_file', true)
                ?: get_post_meta($post_id, '_sdc_file_url', true)
                ?: ($attach_id ? wp_get_attachment_url($attach_id) : '');
        if ($ext_url) {
            // Registrar descarga y redirigir con header de descarga
            $count = (int) get_post_meta($post_id, '_sdc_download_count', true);
            update_post_meta($post_id, '_sdc_download_count', $count + 1);
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowed = array('https://app.vidakushala.com','https://vidakushala.com','http://localhost:8080','http://localhost:3000');
            if (in_array($origin, $allowed, true)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Credentials: true');
            }
            header('Location: ' . esc_url_raw($ext_url));
            exit;
        }
        status_header(404); exit('File not found');
    }

    // Registrar descarga
    $count = (int) get_post_meta($post_id, '_sdc_download_count', true);
    update_post_meta($post_id, '_sdc_download_count', $count + 1);

    // Nombre y MIME
    $filename = basename($file_path);
    $mime     = $attach_id
        ? (get_post_mime_type($attach_id) ?: mime_content_type($file_path) ?: 'application/octet-stream')
        : (mime_content_type($file_path) ?: 'application/octet-stream');

    // CORS (mismos orígenes que el resto del plugin)
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed_origins = array('https://app.vidakushala.com','https://vidakushala.com','http://localhost:8080','http://localhost:3000');
    if (in_array($origin, $allowed_origins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    // Headers de descarga
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    if (ob_get_level()) ob_end_clean();
    flush();
    readfile($file_path);
    exit;
}

// Autenticar al usuario ANTES de que Tutor LMS ejecute su redirect en template_redirect (prioridad 10)
add_action('init', function() {
    if (isset($_GET['cert_hash']) && isset($_GET['vk_token'])) {
        $token = sanitize_text_field($_GET['vk_token']);
        $uid = vk_read_token($token);
        if ($uid) {
            wp_set_current_user($uid);
            if (!is_user_logged_in()) {
                wp_set_auth_cookie($uid, true);
            }
        }
    }
}, 1);

// Inyectar script interceptor jQuery para redirigir AJAX de Tutor LMS al endpoint REST autenticado por Token
add_action('wp_head', function() {
    if (isset($_GET['cert_hash'])) {
        ?>
        <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                if (window.jQuery) {
                    jQuery.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                        var urlParams = new URLSearchParams(window.location.search);
                        var vkToken = urlParams.get('vk_token');
                        if (!vkToken) return;

                        // 1. Interceptar almacenamiento de la imagen del certificado (FormData o String)
                        var isStoreCert = false;
                        if (options.data instanceof FormData && options.data.get('action') === 'tutor_store_certificate_image') {
                            isStoreCert = true;
                        } else if (typeof options.data === 'string' && options.data.indexOf('action=tutor_store_certificate_image') !== -1) {
                            isStoreCert = true;
                        } else if (options.data && options.data.action === 'tutor_store_certificate_image') {
                            isStoreCert = true;
                        }

                        if (isStoreCert) {
                            options.url = '<?php echo esc_url_raw(rest_url("vk/v1/save-certificate-image")); ?>';
                            if (options.data instanceof FormData) {
                                options.data.append('vk_token', vkToken);
                            } else if (typeof options.data === 'string') {
                                options.data += '&vk_token=' + encodeURIComponent(vkToken);
                            } else {
                                options.data.vk_token = vkToken;
                            }
                        }

                        // 2. Interceptar solicitud de la estructura HTML del certificado (URL-encoded o JS Object)
                        var isGenerateCert = false;
                        if (typeof options.data === 'string') {
                            if (options.data.indexOf('action=tutor_generate_course_certificate') !== -1) {
                                isGenerateCert = true;
                            }
                        } else if (options.data && options.data.action === 'tutor_generate_course_certificate') {
                            isGenerateCert = true;
                        }

                        if (isGenerateCert) {
                            options.url = '<?php echo esc_url_raw(rest_url("vk/v1/get-certificate-html")); ?>';
                            if (typeof options.data === 'string') {
                                options.data += '&vk_token=' + encodeURIComponent(vkToken);
                            } else {
                                options.data.vk_token = vkToken;
                            }
                        }
                    });
                }
            });
        })(window.jQuery || jQuery);
        </script>
        <?php
    }
}, 1);

/* ═══════════════════════════════════════════════════════════════════
   SUPRESION COMPLETA DE CORREOS DE WORDPRESS Y TUTOR LMS
   
   Ejecuta con prioridad 9999 DESPUES de que todos los plugins carguen.
   Suprime TODOS los correos automáticos de WP/TutorLMS para que
   solo se envíen los correos de la App (app.vidakushala.com).
═══════════════════════════════════════════════════════════════════ */
add_action('plugins_loaded', 'vkx_suppress_all_wp_emails', 9999);

function vkx_suppress_all_wp_emails() {

    /* ── 1. WordPress core: registro de usuario ── */
    // WP envía email al admin Y al usuario al registrarse
    remove_action('register_new_user',      'wp_send_new_user_notifications');
    remove_action('edit_user_created_user', 'wp_send_new_user_notifications', 10);
    add_filter('wp_new_user_notification_email_admin', '__return_false', 99);
    add_filter('wp_new_user_notification_email',       '__return_false', 99);

    /* ── 2. WordPress core: reset y cambio de contraseña ── */
    // WP envía email de confirmación cuando se cambia/resetea la contraseña
    add_filter('send_password_change_email', '__return_false', 99);
    add_filter('send_email_change_email',    '__return_false', 99);
    // Eliminar notificación post-reset
    remove_action('after_password_reset',   'wp_password_change_notification', 10);
    remove_action('password_reset',         'wp_password_change_notification', 10);

    /* ── 3. Tutor LMS: nombres de función del módulo Email ── */
    // Tutor LMS registra sus emails via clase Tutor\Ecommerce\Email o Tutor\Addon\Email
    $tutor_email_hooks = array(
        // Inscripción
        array('tutor_after_enroll',              'tutor_send_course_enrolled_email',          10),
        array('tutor_after_enroll',              'tutor_send_course_enrolled_email_to_admin', 10),
        array('tutor_enrolled_in_a_course',      'tutor_send_course_enrolled_email',          10),
        array('tutor_course_enrolled',           'tutor_send_course_enrolled_email',          10),
        // Completado + certificado
        array('tutor_course_complete_after',     'tutor_send_course_complete_email',          10),
        array('tutor_course_complete_after',     'tutor_send_certificate_email',              10),
        array('tutor_course_complete_after',     'tutor_send_course_complete_email_to_admin', 10),
        array('tutor_course_complete',           'tutor_send_course_complete_email',          10),
        // Lecciones y quiz
        array('tutor_lesson_completed_after',    'tutor_send_lesson_completed_email',         10),
        array('tutor_quiz_finished',             'tutor_send_quiz_finish_email',              10),
        array('tutor_answer_submitted',          'tutor_send_answer_submitted_email',         10),
        // Q&A
        array('tutor_qna_added',                 'tutor_send_qna_email',                      10),
        array('tutor_reply_added',               'tutor_send_qna_email',                      10),
        // Reseñas
        array('tutor_course_review_created',     'tutor_send_review_email',                   10),
        array('tutor_after_course_review',       'tutor_send_review_email',                   10),
        // Instructores
        array('tutor_new_instructor',            'tutor_send_instructor_email',               10),
        array('tutor_instructor_approved',       'tutor_send_instructor_approved_email',      10),
        array('tutor_instructor_rejected',       'tutor_send_instructor_rejected_email',      10),
        // Anuncios
        array('tutor_after_announcement_added',  'tutor_send_announcement_email',             10),
    );

    foreach ($tutor_email_hooks as $hook_data) {
        remove_action($hook_data[0], $hook_data[1], $hook_data[2]);
        // También intentar con prioridades alternativas que usa Tutor LMS
        remove_action($hook_data[0], $hook_data[1], 1);
        remove_action($hook_data[0], $hook_data[1], 20);
    }

    /* ── 4. Tutor LMS: clase Email (Tutor Pro / addon) ── */
    // Tutor Pro registra callbacks via instancia de clase
    // Usamos wp_mail filter como red de seguridad final
    add_filter('wp_mail', 'vkx_intercept_tutor_wp_mails', 1);

    /* ── 5. Suprimir emails de WooCommerce relacionados con cursos (si existe) ── */
    if (class_exists('WC_Emails')) {
        remove_action('woocommerce_order_status_completed', array(WC_Emails::instance(), 'send_transactional_email'), 10);
    }
}

/**
 * Red de seguridad final: interceptar correos de Tutor LMS que pasaron los filtros anteriores.
 * Detecta correos con URLs de vidakushala.com y:
 * 1. Reemplaza enlaces WP por enlaces de la App
 * 2. Bloquea correos de admin (new registration, etc.)
 */
function vkx_intercept_tutor_wp_mails($args) {
    $body    = isset($args['message'])  ? $args['message']  : '';
    $subject = isset($args['subject'])  ? strtolower($args['subject']) : '';
    $to      = isset($args['to'])       ? $args['to']       : '';

    if (!$body && !$subject) return $args;

    /* ── A. Bloquear correos de admin (no son para el usuario) ── */
    $admin_email = get_option('admin_email','');
    if ($to === $admin_email) {
        // Bloquear notificaciones al admin de: new registration, new enrollment, etc.
        if (strpos($subject,'new user')!==false || strpos($subject,'enrollment')!==false ||
            strpos($subject,'registered')!==false || strpos($subject,'inscri')!==false) {
            $args['to'] = ''; // descartar
            return $args;
        }
    }

    /* ── B. Bloquear correos duplicados de Tutor LMS ── */
    // Tutor LMS envía enrollment confirmation con su propio diseño
    // Si contiene links a vidakushala.com/cursos/ o tutor-certificate/, es de Tutor
    $is_tutor_enroll = (
        (strpos($subject,'enroll')!==false || strpos($subject,'inscri')!==false || strpos($subject,'course')!==false)
        && strpos($body,'vidakushala.com')!==false
        && strpos($body,'app.vidakushala.com')===false  // no es nuestro correo
    );
    if ($is_tutor_enroll) {
        $args['to'] = '';
        return $args;
    }

    // Bloquear correos de Tutor sobre completado/certificado duplicados
    $is_tutor_complete = (
        (strpos($subject,'complet')!==false || strpos($subject,'certif')!==false || strpos($subject,'congratu')!==false)
        && strpos($body,'vidakushala.com')!==false
        && strpos($body,'app.vidakushala.com')===false
    );
    if ($is_tutor_complete) {
        $args['to'] = '';
        return $args;
    }

    /* ── C. Para correos que pasaron: reemplazar URLs de WP por App ── */
    if (strpos($body,'vidakushala.com')!==false && strpos($body,'app.vidakushala.com')===false) {
        // URL de certificado
        $body = preg_replace_callback(
            '#https?://vidakushala\.com/tutor-certificate/([a-zA-Z0-9_\-]+)/?#',
            function($m){ return 'https://app.vidakushala.com/?cert='.$m[1]; },
            $body
        );
        // URLs de cursos
        $body = preg_replace(
            '#https?://vidakushala\.com/curso/[^"\'<\s]+#',
            'https://app.vidakushala.com/',
            $body
        );
        // URL raíz de WP en enlaces
        $body = preg_replace(
            '#href=["\']https?://vidakushala\.com/?["\']#',
            'href="https://app.vidakushala.com/"',
            $body
        );
        $args['message'] = $body;
    }

    return $args;
}



/* ===============================================
   TOKEN HELPERS
=============================================== */
function vk_secret() {
    return 'm3cplus_vk_2025_' . (defined('AUTH_KEY') ? AUTH_KEY : 'fallback');
}
function vk_make_token($uid) {
    $p = $uid . '|' . time();
    return base64_encode($p . '|' . hash_hmac('sha256', $p, vk_secret()));
}
function vk_read_token($token) {
    $r = base64_decode($token, true);
    if (!$r) return 0;
    $parts = explode('|', $r);
    if (count($parts) !== 3) return 0;
    $uid = $parts[0]; $ts = $parts[1]; $sig = $parts[2];
    if (!hash_equals(hash_hmac('sha256', $uid . '|' . $ts, vk_secret()), $sig)) return 0;
    if (time() - (int)$ts > 30 * DAY_IN_SECONDS) return 0;
    return (int)$uid;
}
function vk_uid($req) {
    $t = sanitize_text_field($req->get_param('vk_token'));
    if (!$t) $t = $req->get_header('X-VK-Token');
    if (!$t) $t = '';
    return $t ? vk_read_token($t) : 0;
}

add_filter('allowed_redirect_hosts', function ($h) {
    $h[] = 'app.vidakushala.com';
    return $h;
});
/* ================================================================
   CORS para archivos estáticos de WordPress (uploads)
   Permite que app.vidakushala.com cargue imágenes del servidor
================================================================ */
add_action('send_headers', function() {
    $allowed_origins = array(
        'https://app.vidakushala.com',
        'https://vidakushala.com',
        'http://localhost:8080',
        'http://localhost:3000',
    );
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
}, 1);

/* También para el endpoint de imágenes directas */
add_filter('wp_headers', function($headers) {
    $allowed = array('https://app.vidakushala.com','https://vidakushala.com');
    $origin  = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if (in_array($origin, $allowed)) {
        $headers['Access-Control-Allow-Origin']      = $origin;
        $headers['Access-Control-Allow-Credentials'] = 'true';
        $headers['Vary'] = 'Origin';
    }
    return $headers;
});



/* DEBUG TEMPORAL — ver todos los meta de una lección */
function vk_debug_lesson_meta($req) {
    $lid  = (int)$req['id'];
    $all  = get_post_meta($lid);
    $out  = array();
    foreach ($all as $key => $values) {
        $v = $values[0];
        if (is_serialized($v)) $v = @unserialize($v);
        $out[$key] = $v;
    }
    return rest_ensure_response(array('lesson_id' => $lid, 'meta' => $out));
}


/* ===============================================
   RUTAS REST
=============================================== */
add_action('rest_api_init', function () {
    $pub  = '__return_true';
    $args = array('permission_callback' => $pub);

    // Ping
    register_rest_route('vk/v1', '/ping',          array_merge($args, array('methods'=>'GET',  'callback'=>'vk_ping')));

    // Auth
    register_rest_route('vk/v1', '/login',           array_merge($args, array('methods'=>'POST', 'callback'=>'vk_login')));
    register_rest_route('vk/v1', '/register',        array_merge($args, array('methods'=>'POST', 'callback'=>'vk_register')));
    register_rest_route('vk/v1', '/google-login',    array_merge($args, array('methods'=>'POST', 'callback'=>'vk_google_login')));
    register_rest_route('vk/v1', '/facebook-login',  array_merge($args, array('methods'=>'POST', 'callback'=>'vk_facebook_login')));
    register_rest_route('vk/v1', '/forgot-password',  array('methods'=>'POST','callback'=>'vkx_forgot_password', 'permission_callback'=>'__return_true'));
    register_rest_route('vk/v1', '/reset-password',   array('methods'=>'POST','callback'=>'vkx_reset_password',  'permission_callback'=>'__return_true'));
        register_rest_route('vk/v1', '/activate-email',   array('methods'=>'POST','callback'=>'vkx_activate_email',   'permission_callback'=>'__return_true'));
    register_rest_route('vk/v1', '/resend-activation', array('methods'=>'POST','callback'=>'vkx_resend_activation', 'permission_callback'=>'__return_true'));
        register_rest_route('vk/v1', '/verify',          array_merge($args, array('methods'=>'POST', 'callback'=>'vk_verify')));

    // Cursos publicos
    register_rest_route('vk/v1', '/public-courses',                 array_merge($args, array('methods'=>'GET', 'callback'=>'vk_public_courses')));
    register_rest_route('vk/v1', '/public-courses/(?P<id>\d+)',     array_merge($args, array('methods'=>'GET', 'callback'=>'vk_public_course_detail')));

    // Mis cursos (autenticado)
    register_rest_route('vk/v1', '/my-courses',                          array_merge($args, array('methods'=>'GET',  'callback'=>'vk_my_courses')));
    register_rest_route('vk/v1', '/my-dashboard',                        array_merge($args, array('methods'=>'GET',  'callback'=>'vk_my_dashboard')));
    register_rest_route('vk/v1', '/my-course-contents/(?P<id>\d+)',      array_merge($args, array('methods'=>'GET',  'callback'=>'vk_my_course_contents')));
    register_rest_route('vk/v1', '/ping-contents/(?P<id>\d+)',          array_merge($args, array('methods'=>'GET',  'callback'=>'vk_ping_contents')));
    register_rest_route('vk/v1', '/my-lesson/(?P<id>\d+)',               array_merge($args, array('methods'=>'GET',  'callback'=>'vk_my_lesson')));
    register_rest_route('vk/v1', '/debug-lesson-meta/(?P<id>\d+)',       array_merge($args, array('methods'=>'GET',  'callback'=>'vk_debug_lesson_meta')));
    register_rest_route('vk/v1', '/my-quiz/(?P<id>\d+)',                 array_merge($args, array('methods'=>'GET',  'callback'=>'vk_my_quiz')));
    register_rest_route('vk/v1', '/my-lesson-complete',                  array_merge($args, array('methods'=>'POST', 'callback'=>'vk_my_lesson_complete')));
    register_rest_route('vk/v1', '/my-quiz-submit',                      array_merge($args, array('methods'=>'POST', 'callback'=>'vk_my_quiz_submit')));
    register_rest_route('vk/v1', '/complete-course',                     array_merge($args, array('methods'=>'POST', 'callback'=>'vk_complete_course')));
    // ⭐ Progreso en tiempo real — idéntico a Tutor LMS, sin caché
    register_rest_route('vk/v1', '/course-progress/(?P<id>\d+)',         array_merge($args, array('methods'=>'GET',  'callback'=>'vk_course_progress')));

    // Cursos publicos ? incluye link de pago externo y bundles
    register_rest_route('vk/v1', '/course-categories',   array_merge($args, array('methods'=>'GET', 'callback'=>'vk_course_categories')));
    register_rest_route('vk/v1', '/product-categories',  array_merge($args, array('methods'=>'GET', 'callback'=>'vk_product_categories')));

    register_rest_route('vk/v1', '/public-bundles',               array_merge($args, array('methods'=>'GET', 'callback'=>'vk_public_bundles')));
    register_rest_route('vk/v1', '/public-bundles/(?P<id>\d+)',   array_merge($args, array('methods'=>'GET', 'callback'=>'vk_public_bundle_detail')));

    // Certificado
    register_rest_route('vk/v1', '/my-certificate/(?P<id>\d+)', array_merge($args, array('methods'=>'GET', 'callback'=>'vk_my_certificate')));
    register_rest_route('vk/v1', '/save-certificate-image',     array_merge($args, array('methods'=>'POST', 'callback'=>'vk_save_certificate_image')));
    register_rest_route('vk/v1', '/get-certificate-html',      array_merge($args, array('methods'=>'POST', 'callback'=>'vk_get_certificate_html')));
    register_rest_route('vk/v1', '/generate-cert-server',      array_merge($args, array('methods'=>'POST', 'callback'=>'vk_generate_cert_server')));
    // Generador nativo de certificados (Canvas in-app)
    register_rest_route('vk/v1', '/cert-data/(?P<id>\d+)',      array_merge($args, array('methods'=>'GET',  'callback'=>'vk_cert_data')));
    // HTML de Tutor LMS con imágenes inlineadas (fidelidad 1:1 con WP)
    register_rest_route('vk/v1', '/cert-html-inline/(?P<id>\d+)', array_merge($args, array('methods'=>'GET', 'callback'=>'vk_cert_html_inline')));
    // ⭐ Generador server-side con PHP GD (método principal, máxima compatibilidad)
    register_rest_route('vk/v1', '/make-cert/(?P<id>\d+)',      array_merge($args, array('methods'=>'POST', 'callback'=>'vk_make_cert_php')));

    // Encuestas YOP Poll
    $pub_args = array('permission_callback' => '__return_true');
    register_rest_route('vk/v1', '/polls',                array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vk_polls_list')));
    register_rest_route('vk/v1', '/polls/(?P<id>\d+)',    array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vk_poll_detail')));
    register_rest_route('vk/v1', '/polls/(?P<id>\d+)/vote', array_merge($pub_args, array('methods'=>'POST','callback'=>'vk_poll_vote')));
    register_rest_route('vk/v1', '/polls-debug',          array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vk_polls_debug')));

    // OneSignal Push
    register_rest_route('vk/v1', '/save-push-id',     array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vk_save_push_id')));
    register_rest_route('vk/v1', '/send-push',        array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vk_send_push')));
    register_rest_route('vk/v1', '/push-subscribers', array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vk_push_subscribers')));
    register_rest_route('vk/v1', '/push-history',     array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vk_push_history')));
    register_rest_route('vk/v1', '/push-history-delete', array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vk_push_history_delete_alias')));
    register_rest_route('vk/v1', '/admin-notifications',  array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vkx_admin_notifications')));
    register_rest_route('vk/v1', '/admin-notif-delete',   array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_admin_notif_delete')));
    register_rest_route('vk/v1', '/notifications/delete', array_merge($args, array('methods'=>'POST', 'callback'=>'vkx_user_notif_delete')));
    register_rest_route('vk/v1', '/push-save-key',    array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vk_push_save_key')));
    register_rest_route('vk/v1', '/push-clean-ids',   array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_push_clean_invalid_ids')));
    register_rest_route('vk/v1', '/push-live-class',   array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_push_live_class')));
    register_rest_route('vk/v1', '/push-stats',       array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vk_push_stats')));
    register_rest_route('vk/v1', '/push-auto-config',  array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vk_push_auto_config')));
    register_rest_route('vk/v1', '/push-auto-toggle',  array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vk_push_auto_toggle')));
    register_rest_route('vk/v1', '/push-auto-template',array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vk_push_auto_template')));
    register_rest_route('vk/v1', '/push-test-event',   array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_push_test_event')));
    register_rest_route('vk/v1', '/push-auto-status',  array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vkx_push_auto_status')));
    register_rest_route('vk/v1', '/push-debug',         array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_push_debug')));
    register_rest_route('vk/v1', '/push-clone-welcome',   array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_push_clone_welcome')));
    register_rest_route('vk/v1', '/push-reset-subscribers',array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_push_reset_subscribers')));
    register_rest_route('vk/v1', '/check-admin',      array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vk_check_admin')));

    register_rest_route('vk/v1', '/products',                array_merge($args, array('methods'=>'GET', 'callback'=>'vk_products')));
    register_rest_route('vk/v1', '/products/(?P<id>\d+)',    array_merge($args, array('methods'=>'GET', 'callback'=>'vk_product_detail')));

    // Webhook Mercado Pago
    register_rest_route('vk/v1', '/mp-webhook',   array_merge($args, array('methods'=>'POST', 'callback'=>'vk_mp_webhook')));

    // Facebook ? callback de eliminacion de datos (requerido por Facebook Login)
    // URL a poner en el panel: https://app.vidakushala.com → WordPress: https://vidakushala.com/wp-json/vk/v1/facebook-delete
    register_rest_route('vk/v1', '/facebook-delete', array_merge($args, array('methods'=>'POST,GET', 'callback'=>'vk_facebook_delete')));

    // Proxy Tutor
    register_rest_route('vk/v1', '/tutor/(?P<path>.+)', array_merge($args, array(
        'methods'  => 'GET,POST,PUT,PATCH,DELETE',
        'callback' => 'vk_proxy',
        'args'     => array('path' => array('required' => true))
    )));

    // Perfil del usuario
    register_rest_route('vk/v1', '/my-profile',             array_merge($args, array('methods'=>'GET',  'callback'=>'vk_my_profile')));
    register_rest_route('vk/v1', '/update-profile',         array_merge($args, array('methods'=>'POST', 'callback'=>'vk_update_profile')));
    register_rest_route('vk/v1', '/my-notifications',         array_merge($args, array('methods'=>'GET',  'callback'=>'vk_my_notifications')));
    register_rest_route('vk/v1', '/notifications/read',       array_merge($args, array('methods'=>'POST', 'callback'=>'vk_notifications_read')));
    register_rest_route('vk/v1', '/notifications/count',      array_merge($args, array('methods'=>'GET',  'callback'=>'vk_notifications_count')));
    register_rest_route('vk/v1', '/update-notifications',     array_merge($args, array('methods'=>'POST', 'callback'=>'vk_update_notifications')));
    register_rest_route('vk/v1', '/register-player',          array_merge($args, array('methods'=>'POST', 'callback'=>'vk_register_player')));

    // Debug
    register_rest_route('vk/v1', '/debug-answer/(?P<id>\d+)', array_merge($args, array('methods'=>'GET', 'callback'=>'vk_debug_answer')));
    register_rest_route('vk/v1', '/cert-image/(?P<hash>[a-f0-9]+)',         array_merge($args, array('methods'=>'GET', 'callback'=>'vk_cert_image')));
    register_rest_route('vk/v1', '/cert-image-by-post/(?P<id>\d+)',          array_merge($args, array('methods'=>'GET', 'callback'=>'vk_cert_image_by_post')));
    register_rest_route('vk/v1', '/debug-cert/(?P<id>\d+)',    array_merge($args, array('methods'=>'GET', 'callback'=>'vk_debug_cert')));
    register_rest_route('vk/v1', '/debug-meta/(?P<id>\d+)', array_merge($args, array('methods'=>'GET', 'callback'=>'vk_debug_meta')));
    register_rest_route('vk/v1', '/fix-cert/(?P<id>\d+)', array_merge($args, array('methods'=>'POST', 'callback'=>'vk_fix_cert')));
    register_rest_route('vk/v1', '/link-cert/(?P<id>\d+)', array_merge($args, array('methods'=>'POST', 'callback'=>'vk_link_cert')));
    // === PANEL ADMIN DE CERTIFICADOS ===
    register_rest_route('vk/v1', '/cert-config',     array_merge($args, array('methods'=>'GET',  'callback'=>'vk_cert_config_get')));


    // Endpoint público para obtener config del cert (sin auth)
    register_rest_route('vk/v1', '/cert-theme', array(
        'methods'             => 'GET',
        'callback'            => 'vk_cert_theme_public',
        'permission_callback' => '__return_true',
    ));
    // Listar imágenes de cert-templates/ para el panel admin
    register_rest_route('vk/v1', '/cert-templates', array_merge($args, array('methods'=>'GET', 'callback'=>'vk_cert_templates_list')));
    register_rest_route('vk/v1', '/cert-config',     array_merge($args, array('methods'=>'POST', 'callback'=>'vk_cert_config_save')));
    register_rest_route('vk/v1', '/cert-upload-bg',  array_merge($args, array('methods'=>'POST', 'callback'=>'vk_cert_upload_bg')));

// === CERT CONFIG: Limpiar cache al guardar config ===
register_rest_route('vk/v1', '/cert-clear-cache', array_merge($args, array('methods'=>'POST', 'callback'=>'vk_cert_clear_cache')));
// === CERT: Sanitizar fondos (limpiar bg_image_data que sean cert renders) ===
register_rest_route('vk/v1', '/cert-sanitize-bg',   array_merge($args, array('methods'=>'POST', 'callback'=>'vk_cert_sanitize_bg')));
register_rest_route('vk/v1', '/cert-set-default-bg', array_merge($args, array('methods'=>'POST', 'callback'=>'vk_cert_set_default_bg')));    // === INSCRIPCION DIRECTA ===

// === CERT: Datos para renderizado cliente ===
register_rest_route('vk/v1', '/cert-render-data/(?P<course_id>\d+)', array_merge($args, array(
    'methods'  => 'GET',
    'callback' => 'vk_cert_render_data',
)));    register_rest_route('vk/v1', '/enroll-course',   array_merge($args, array('methods'=>'POST', 'callback'=>'vk_enroll_course')));
    register_rest_route('vk/v1', '/preview-enroll', array_merge($args, array('methods'=>'POST', 'callback'=>'vk_preview_enroll')));
    // === DEBUG INSCRIPCION ===
    register_rest_route('vk/v1', '/debug-enroll/(?P<id>\d+)', array_merge($args, array('methods'=>'GET', 'callback'=>'vk_debug_enroll')));

    // ── Plantillas de certificados con nombre (v3) ──
    register_rest_route('vk/v1', '/tpl',           array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vkx_tpl_list')));
    register_rest_route('vk/v1', '/tpl-save',      array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_tpl_save')));
    register_rest_route('vk/v1', '/tpl-delete',    array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_tpl_delete')));
    register_rest_route('vk/v1', '/tpl-duplicate', array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_tpl_duplicate')));
    register_rest_route('vk/v1', '/tpl-get',       array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_tpl_get')));
    register_rest_route('vk/v1', '/tpl-courses',   array_merge($pub_args, array('methods'=>'GET',  'callback'=>'vkx_tpl_courses')));
    register_rest_route('vk/v1', '/tpl-assign',    array_merge($pub_args, array('methods'=>'POST', 'callback'=>'vkx_tpl_assign')));
});

/* ===============================================
   CERT IMAGE BY POST ID ? buscar imagen desde el ID del post certificado
=============================================== */
function vk_cert_image_by_post($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized','Token requerido',array('status'=>401));
    $course_id = (int)$req['id'];

    $upload_dir   = wp_upload_dir();
    $cert_dir     = $upload_dir['basedir'] . '/tutor-certificates/';
    $cert_url_dir = $upload_dir['baseurl']  . '/tutor-certificates/';
    $cert_img = '';
    $real_hash = '';

    global $wpdb;

    // 1. Intentar obtener el hash desde usermeta
    $real_hash = get_user_meta($uid, '_tutor_cert_hash_course_' . $course_id, true);

    // 2. Si no está en usermeta, buscar en posts de tipo tutor_certificate para este usuario+curso
    if (!$real_hash) {
        $cert_post = $wpdb->get_row($wpdb->prepare(
            "SELECT ID, post_name FROM {$wpdb->posts}
             WHERE post_type IN ('tutor_certificate','tutorlms_certificate')
               AND post_author = %d
               AND post_parent = %d
             ORDER BY ID DESC LIMIT 1",
            $uid, $course_id
        ));
        if ($cert_post && preg_match('/^[a-f0-9]{8,}$/i', $cert_post->post_name)) {
            $real_hash = $cert_post->post_name;
        }
    }

    // 3. Buscar el archivo específico para este hash en disco
    if ($real_hash && is_dir($cert_dir)) {
        $files = array_merge(
            glob($cert_dir . '*.jpg') ?: array(),
            glob($cert_dir . '*.png') ?: array()
        );
        foreach ($files as $file) {
            if (strpos($file, $real_hash) !== false) {
                // Verificar si la plantilla de certificado se editó después de generar la imagen
                $template_key = get_post_meta($course_id, 'tutor_course_certificate_template', true);
                if ($template_key && strpos($template_key, 'tutor_cb_') === 0) {
                    $template_id = (int) preg_replace( '/\D/', '', $template_key );
                    $template_post = get_post($template_id);
                    if ($template_post) {
                        $template_modified = strtotime($template_post->post_modified);
                        $file_created = filemtime($file);
                        if ($file_created < $template_modified) {
                            @unlink($file);
                            continue;
                        }
                    }
                }
                $cert_img = $cert_url_dir . basename($file);
                break;
            }
        }
    }

    return rest_ensure_response(array(
        'img'  => $cert_img,
        'hash' => $real_hash,
    ));
}


/* ===============================================
   CERT IMAGE ? buscar imagen por cert_hash
=============================================== */
function vk_cert_image($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized','Token requerido',array('status'=>401));

    $hash = sanitize_text_field($req['hash']);
    if (!$hash) return new WP_Error('missing','hash requerido',array('status'=>400));

    $upload_dir  = wp_upload_dir();
    $cert_dir    = $upload_dir['basedir'] . '/tutor-certificates/';
    $cert_url_dir = $upload_dir['baseurl'] . '/tutor-certificates/';
    $cert_img    = '';

    foreach (array('jpg','jpeg','png','webp') as $ext) {
        $files = glob($cert_dir . '*-' . $hash . '.' . $ext);
        if (!empty($files)) {
            usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
            $cert_img = $cert_url_dir . basename($files[0]); break; 
        }
        if (file_exists($cert_dir . $hash . '.' . $ext)) {
            $cert_img = $cert_url_dir . $hash . '.' . $ext; break;
        }
    }

    return rest_ensure_response(array('img' => $cert_img, 'hash' => $hash));
}

/* ===============================================
   DEBUG CERT ? encontrar donde guarda Tutor el cert_hash
=============================================== */
function vk_debug_cert($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized','Token requerido',array('status'=>401));
    $course_id = (int)$req['id'];
    global $wpdb;
    $result = array('uid'=>$uid,'course_id'=>$course_id);

    // usermeta con cert/hash
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_key,meta_value FROM {$wpdb->usermeta}
         WHERE user_id=%d AND (meta_key LIKE '%cert%' OR meta_key LIKE '%hash%')", $uid));
    $result['usermeta_cert'] = $rows;

    // enrollment
    $eid = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type='tutor_enrolled' AND post_parent=%d AND post_author=%d LIMIT 1",
        $course_id, $uid));
    $result['enrolled_id'] = $eid;
    if ($eid) $result['enrollment_meta'] = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND (meta_key LIKE '%cert%' OR meta_key LIKE '%hash%')",(int)$eid));

    // Buscar posts de tipo tutor_certificate para este usuario+curso
    $cert_posts = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_name, post_parent, post_author, post_status, post_date
         FROM {$wpdb->posts}
         WHERE post_type IN ('tutor_certificate','tutorlms_certificate')
           AND post_author = %d
         ORDER BY ID DESC LIMIT 10",
        $uid));
    $result['cert_posts_by_user'] = $cert_posts;

    // Archivos en disco del usuario (contienen el uid en el nombre)
    $upload_dir = wp_upload_dir();
    $cert_dir   = $upload_dir['basedir'] . '/tutor-certificates/';
    $cert_url   = $upload_dir['baseurl']  . '/tutor-certificates/';
    $all_files  = array_merge(
        glob($cert_dir . '*.jpg')  ?: array(),
        glob($cert_dir . '*.jpeg') ?: array(),
        glob($cert_dir . '*.png')  ?: array()
    );
    $result['total_files_in_folder'] = count($all_files);

    // Archivos que contienen el uid
    $user_files = array();
    foreach ($all_files as $f) {
        $base = pathinfo($f, PATHINFO_FILENAME);
        if (strpos($base, (string)$uid) !== false) {
            $user_files[] = $cert_url . basename($f);
        }
    }
    $result['files_with_uid_' . $uid] = $user_files;

    // Todos los archivos (max 20 para no saturar)
    $all_listed = array();
    foreach (array_slice($all_files, 0, 20) as $f) {
        $all_listed[] = $cert_url . basename($f);
    }
    $result['all_files_sample'] = $all_listed;

    // Tablas con cert_hash
    $tables = $wpdb->get_col("SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='cert_hash' AND TABLE_NAME LIKE '{$wpdb->prefix}%'");
    $result['tables_with_cert_hash'] = $tables;
    foreach ($tables as $tbl) {
        $result['table_data_'.$tbl] = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$tbl}` WHERE student_id=%d OR user_id=%d LIMIT 10", $uid, $uid));
    }
    return rest_ensure_response($result);
}

/* ===============================================
   DEBUG META ? ver todas las metas de precio de un post
=============================================== */
function vk_debug_meta($req) {
    $id   = (int)$req['id'];
    $meta = get_post_meta($id);
    $filtered = array();
    foreach($meta as $k=>$v){
        if(preg_match('/price|pago|payment|bundle|tutor|sale|regular|cost|discount/i',$k)){
            $filtered[$k] = maybe_unserialize($v[0]);
        }
    }
    $post = get_post($id);
    return rest_ensure_response(array(
        'post_id'   => $id,
        'post_type' => $post ? $post->post_type : '',
        'post_title'=> $post ? $post->post_title : '',
        'price_meta'=> $filtered,
        'all_keys'  => array_keys($meta)
    ));
}

/* ===============================================
   PING
=============================================== */
function vk_ping() {
    return array('status' => 'ok', 'version' => '6.0.0');
}

/* ===============================================
   LOGIN (email)
=============================================== */
function vk_login($req) {
    $username = sanitize_text_field($req->get_param('username'));
    $password = sanitize_text_field($req->get_param('password'));
    if (!$username || !$password)
        return new WP_Error('missing', 'Correo y contrasena requeridos', array('status' => 400));

    $login = $username;
    if (strpos($username, '@') !== false) {
        $found = get_user_by('email', $username);
        if (!$found) return new WP_Error('not_found', 'No existe cuenta con ese correo', array('status' => 404));
        $login = $found->user_login;
    }
    // Para evitar bloqueos por plugins de seguridad (como WPS Hide Login o similares)
    // que impiden el login de cuentas de administración fuera del enlace personalizado de login,
    // usamos una verificación directa de contraseña.
    $user_obj = get_user_by('login', $login);
    if (!$user_obj) {
        return new WP_Error('invalid', 'Credenciales incorrectas', array('status' => 401));
    }
    
    if (!wp_check_password($password, $user_obj->user_pass, $user_obj->ID)) {
        return new WP_Error('invalid', 'Credenciales incorrectas', array('status' => 401));
    }
    
    // Una vez verificado directamente, asignamos el objeto de usuario y retornamos el payload
    $user = $user_obj;

    // Si WordPress autenticó correctamente, el usuario está verificado — limpiar flag si existe
    delete_user_meta($user->ID, '_vk_pending_activation');

    return rest_ensure_response(vk_user_payload($user));
}

/* ─────────────────────────────────────────────────────────
   Helper: botón de email compatible con todos los clientes
   (Gmail, Outlook, Apple Mail, Yahoo, etc.)
───────────────────────────────────────────────────────── */
if (!function_exists('vkx_email_button')) {
function vkx_email_button($url, $text, $bg = '#c44d8a', $color = '#ffffff') {
    // Técnica de tabla + VML para máxima compatibilidad
    return '<!--[if mso]>
<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
  href="' . esc_url($url) . '" style="height:48px;v-text-anchor:middle;width:220px;" arcsize="50%" strokecolor="' . esc_attr($bg) . '" fillcolor="' . esc_attr($bg) . '">
  <w:anchorlock/>
  <center style="color:' . esc_attr($color) . ';font-family:Arial,sans-serif;font-size:16px;font-weight:700;">' . esc_html($text) . '</center>
</v:roundrect>
<![endif]-->
<!--[if !mso]><!-->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto">
  <tr>
    <td style="border-radius:50px;background-color:' . esc_attr($bg) . ';" bgcolor="' . esc_attr($bg) . '">
      <a href="' . esc_url($url) . '" target="_blank"
         style="display:inline-block;padding:14px 32px;font-family:Arial,sans-serif;
                font-size:16px;font-weight:700;color:' . esc_attr($color) . ';
                text-decoration:none;border-radius:50px;
                background-color:' . esc_attr($bg) . ';">' . esc_html($text) . '</a>
    </td>
  </tr>
</table>
<!--<![endif]-->';
}
}

/* Helper: wrapper HTML completo de email */
if (!function_exists('vkx_email_wrapper')) {
function vkx_email_wrapper($title, $preheader, $content, $accent = '#6b2447') {
    return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>' . esc_html($title) . '</title>
</head>
<body style="margin:0;padding:0;background-color:#fdf5f8;font-family:Arial,Helvetica,sans-serif;">
<!-- Preheader invisible -->
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">' . esc_html($preheader) . '&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#fdf5f8;">
<tr><td align="center" style="padding:20px 10px;">

  <!-- Contenedor principal 560px -->
  <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="560" style="max-width:560px;width:100%;">

    <!-- Logo / Header -->
    <tr>
      <td align="center" style="padding:0 0 16px;">
        <a href="https://app.vidakushala.com/" target="_blank">
          <img src="https://app.vidakushala.com/icons/logo2.png" width="140" alt="VidaKushala" border="0"
               style="display:block;max-width:140px;height:auto;">
        </a>
      </td>
    </tr>

    <!-- Card blanca -->
    <tr>
      <td bgcolor="#ffffff" style="background-color:#ffffff;border-radius:20px;
          padding:40px 48px 32px;box-shadow:0 4px 24px rgba(107,36,71,.1);">

        ' . $content . '

      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td align="center" style="padding:20px 0;font-size:12px;color:#aaa;">
        &copy; ' . date('Y') . ' VidaKushala &mdash; Plataforma de aprendizaje<br>
        <a href="https://app.vidakushala.com/" style="color:#c44d8a;text-decoration:none;">app.vidakushala.com</a>
      </td>
    </tr>

  </table>
</td></tr>
</table>
</body>
</html>';
}
}


/* ===============================================
   REGISTRO
   ? Registro social  (sin contrasena): genera una aleatoria
   ? Registro manual  (con contrasena): valida ? 8 caracteres
=============================================== */
function vk_register($req) {
    $first         = sanitize_text_field($req->get_param('first_name'));
    $last          = sanitize_text_field($req->get_param('last_name'));
    $email         = sanitize_email($req->get_param('email'));
    $pass          = $req->get_param('password');
    // Detectar registro social ? Google usa 'credential', Facebook usa 'social_access_token'
    $social_token    = sanitize_text_field($req->get_param('social_access_token') ? $req->get_param('social_access_token') : '');
    $social_prov     = sanitize_text_field($req->get_param('social_provider')      ? $req->get_param('social_provider')      : '');
    $google_cred     = sanitize_text_field($req->get_param('google_credential')    ? $req->get_param('google_credential')    : '');
    $avatar_url      = esc_url_raw($req->get_param('avatar_url') ? $req->get_param('avatar_url') : '');

    // Es registro social si viene con proveedor declarado (google o facebook)
    $is_social = !empty($social_prov) && in_array($social_prov, array('google','facebook'), true);

    // Validaciones basicas
    if (!$first || !$last)
        return new WP_Error('missing', 'Nombre y apellido son obligatorios', array('status' => 400));
    if (!$email || !is_email($email))
        return new WP_Error('bad_email', 'Correo electronico invalido', array('status' => 400));
    if (email_exists($email))
        return new WP_Error('email_exists', 'Ya existe una cuenta con ese correo. Por favor inicia sesion.', array('status' => 409));

    // Validar contrasena solo en registro manual (no en registro social)
    if (!$is_social) {
        if (!$pass || strlen($pass) < 8)
            return new WP_Error('weak_pass', 'La contrasena debe tener al menos 8 caracteres', array('status' => 400));
    }

    // Generar username unico basado en el nombre (mas amigable que el email)
    $base  = sanitize_user(strtolower(remove_accents($first)));
    if (!$base) $base = sanitize_user(strstr($email, '@', true));
    if (!$base) $base = 'user';
    $uname = $base; $i = 1;
    while (username_exists($uname)) { $uname = $base . $i; $i++; }

    // Crear usuario
    $final_pass = $is_social ? wp_generate_password(24, true, true) : $pass;
    $uid = wp_create_user($uname, $final_pass, $email);
    if (is_wp_error($uid))
        return new WP_Error('create_fail', 'Error al crear usuario: ' . $uid->get_error_message(), array('status' => 500));

    // Nombre y apellido (aparecen en certificados)
    wp_update_user(array(
        'ID'           => $uid,
        'first_name'   => $first,
        'last_name'    => $last,
        'display_name' => $first . ' ' . $last,
    ));

    // Guardar avatar de red social
    if ($avatar_url) {
        update_user_meta($uid, '_social_avatar_url', $avatar_url);
    }

    // Si es registro social, guardar el ID de la red social en meta
    if ($is_social && $social_prov === 'facebook') {
        // Verificar el access_token y obtener el facebook_user_id
        $app_id     = defined('FACEBOOK_APP_ID')     ? FACEBOOK_APP_ID     : get_option('vk_fb_app_id', '2155344185383534');
        $app_secret = defined('FACEBOOK_APP_SECRET') ? FACEBOOK_APP_SECRET : get_option('vk_fb_app_secret', '2fd7c73833d1b8f51322e649a6ab7190');
        if ($app_secret) {
            $me_res = wp_remote_get('https://graph.facebook.com/me?fields=id&access_token=' . urlencode($social_token), array('timeout' => 10));
            if (!is_wp_error($me_res)) {
                $me = json_decode(wp_remote_retrieve_body($me_res), true);
                if (!empty($me['id'])) {
                    update_user_meta($uid, '_facebook_id', sanitize_text_field($me['id']));
                }
            }
        }
    }

    $user = get_user_by('id', $uid);

    // Registro social: permitir acceso directo (ya verificado por proveedor)
    if ($is_social) {
        // Guardar notificacion de bienvenida
        if (function_exists('vkx_save_welcome_notification')) {
            vkx_save_welcome_notification($uid);
        }
        return rest_ensure_response(vk_user_payload($user));
    }

    // Registro manual: enviar email de verificacion y bloquear hasta confirmar
    $activation_token   = bin2hex(random_bytes(32));
    $activation_expires = time() + 86400; // 24 horas

    update_user_meta($uid, '_vk_email_activation_token',   $activation_token);
    update_user_meta($uid, '_vk_email_activation_expires', $activation_expires);
    update_user_meta($uid, '_vk_pending_activation',       1);

    // Construir enlace de activacion — procesado server-side en index.php
    $activation_url = 'https://app.vidakushala.com/?activate=' . $activation_token;
    $site_name      = get_bloginfo('name') ?: 'VidaKushala';

    $subject = $site_name . ' - Activa tu cuenta';
    $content = '<h2 style="color:#6b2447;font-size:22px;margin:0 0 12px;">Activa tu cuenta</h2>'
        . '<p style="color:#444;font-size:15px;line-height:1.6;margin:0 0 8px;">Hola <strong>' . esc_html($first) . '</strong>,</p>'
        . '<p style="color:#555;font-size:15px;line-height:1.6;margin:0 0 28px;">Haz clic en el botón para activar tu cuenta y comenzar a aprender en VidaKushala.</p>'
        . vkx_email_button($activation_url, 'Activar mi cuenta')
        . '<p style="color:#999;font-size:12px;text-align:center;margin:24px 0 0;">Este enlace expira en 24 horas. Si no creaste esta cuenta, ignora este mensaje.</p>';
    $body = vkx_email_wrapper('Activa tu cuenta', 'Haz clic para activar tu cuenta en VidaKushala', $content);

    add_filter('wp_mail_from',      function() { return 'noreply@vidakushala.com'; }, 999);
    add_filter('wp_mail_from_name', function() { return get_bloginfo('name') ?: 'VidaKushala'; }, 999);
    wp_mail($email, $subject, $body, array('Content-Type: text/html; charset=UTF-8'));

    return rest_ensure_response(array(
        'success'              => true,
        'pending_verification' => true,
        'email'                => $email,
        'message'              => 'Cuenta creada. Revisa tu correo y activa tu cuenta antes de iniciar sesion.',
    ));
}

/* ===============================================
   GOOGLE LOGIN
=============================================== */
function vk_google_login($req) {
    $credential = sanitize_text_field($req->get_param('credential'));
    if (!$credential)
        return new WP_Error('missing', 'credential requerido', array('status' => 400));

    $res = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential), array('timeout' => 15));
    if (is_wp_error($res))
        return new WP_Error('google_err', 'Error al contactar Google', array('status' => 500));

    $body = json_decode(wp_remote_retrieve_body($res), true);
    if (empty($body['sub']) || empty($body['email']))
        return new WP_Error('invalid_token', 'Token de Google invalido', array('status' => 401));
    if ($body['aud'] !== '194338099501-6utomonv7go9d2ub4o2c8l1su4936gsp.apps.googleusercontent.com')
        return new WP_Error('wrong_client', 'Client ID incorrecto', array('status' => 401));

    $email  = sanitize_email($body['email']);
    $name   = sanitize_text_field(isset($body['name']) ? $body['name'] : $email);
    $avatar = esc_url_raw(isset($body['picture']) ? $body['picture'] : '');
    $parts  = explode(' ', $name, 2);
    $first  = $parts[0];
    $last   = isset($parts[1]) ? $parts[1] : '';

    $user = get_user_by('email', $email);

    // Usuario nuevo ? devolver datos de Google para pre-llenar el registro
    if (!$user) {
        return new WP_Error('not_found', 'No existe cuenta con ese correo. Registrate primero.', array(
            'status'     => 404,
            'email'      => $email,
            'first_name' => $first,
            'last_name'  => $last,
            'name'       => $name,
            'avatar'     => $avatar,
        ));
    }

    // Guardar/actualizar avatar de Google en WP
    if ($avatar) update_user_meta($user->ID, '_social_avatar_url', $avatar);
    return rest_ensure_response(vk_user_payload($user));
}

/* ===============================================
   FACEBOOK LOGIN
   ---------------------------------------------
   La PWA envia el access_token del SDK de Facebook.
   Lo verificamos con la Graph API y creamos/login al usuario.

   Configuracion necesaria en wp-config.php:
     define('FACEBOOK_APP_ID',     '1319137063508391');
     define('FACEBOOK_APP_SECRET', 'TU_APP_SECRET_AQUI');
=============================================== */
function vk_facebook_login($req) {
    $access_token = sanitize_text_field($req->get_param('access_token'));
    if (!$access_token)
        return new WP_Error('missing', 'access_token requerido', array('status' => 400));

    $app_id     = defined('FACEBOOK_APP_ID')     ? FACEBOOK_APP_ID     : get_option('vk_fb_app_id', '2155344185383534');
    $app_secret = defined('FACEBOOK_APP_SECRET') ? FACEBOOK_APP_SECRET : get_option('vk_fb_app_secret', '2fd7c73833d1b8f51322e649a6ab7190');

    if (!$app_secret)
        return new WP_Error('config_missing',
            'Facebook App Secret no configurado. Ve a: WordPress Admin → Ajustes → DM Plus MP',
            array('status' => 500));

    // 1. Verificar el token con la Graph API (debug_token)
    $app_token  = $app_id . '|' . $app_secret;
    $verify_url = 'https://graph.facebook.com/debug_token?input_token='
        . urlencode($access_token) . '&access_token=' . urlencode($app_token);

    $verify_res = wp_remote_get($verify_url, array('timeout' => 15));
    if (is_wp_error($verify_res))
        return new WP_Error('fb_verify_err', 'Error al verificar token con Facebook', array('status' => 500));

    $verify_body = json_decode(wp_remote_retrieve_body($verify_res), true);
    $debug_data  = isset($verify_body['data']) ? $verify_body['data'] : array();

    if (empty($debug_data['is_valid']) || !$debug_data['is_valid'])
        return new WP_Error('invalid_token', 'Token de Facebook invalido o expirado', array('status' => 401));

    if (isset($debug_data['app_id']) && $debug_data['app_id'] !== $app_id)
        return new WP_Error('wrong_app', 'Token no pertenece a esta app (App ID: ' . $app_id . ')', array('status' => 401));

    // 2. Obtener datos del usuario de la Graph API
    $me_url = 'https://graph.facebook.com/me?fields=id,name,first_name,last_name,email,picture.width(200)'
        . '&access_token=' . urlencode($access_token);

    $me_res = wp_remote_get($me_url, array('timeout' => 15));
    if (is_wp_error($me_res))
        return new WP_Error('fb_me_err', 'Error al obtener datos del usuario', array('status' => 500));

    $me = json_decode(wp_remote_retrieve_body($me_res), true);
    if (empty($me['id']))
        return new WP_Error('fb_no_user', 'No se pudieron obtener datos del usuario de Facebook', array('status' => 401));

    // Algunos usuarios de Facebook no tienen email publico ? lo manejamos
    $fb_id    = sanitize_text_field($me['id']);
    $name     = sanitize_text_field(isset($me['name'])       ? $me['name']       : 'Usuario Facebook');
    $first    = sanitize_text_field(isset($me['first_name']) ? $me['first_name'] : $name);
    $last     = sanitize_text_field(isset($me['last_name'])  ? $me['last_name']  : '');
    $email    = isset($me['email']) ? sanitize_email($me['email']) : '';
    $avatar   = isset($me['picture']['data']['url']) ? esc_url_raw($me['picture']['data']['url']) : '';

    // Fallback de email si Facebook no lo entrega (cuenta sin email verificado)
    if (!$email) {
        $email = 'fb_' . $fb_id . '@vidakushala.noemail';
    }

    // 3. Buscar usuario existente: primero por Facebook ID en meta, luego por email
    $user = null;

    // Buscar por meta _facebook_id
    $users_by_fb = get_users(array(
        'meta_key'   => '_facebook_id',
        'meta_value' => $fb_id,
        'number'     => 1,
    ));
    if (!empty($users_by_fb)) {
        $user = $users_by_fb[0];
    }

    // Si no existe por FB ID, buscar por email (si es un email real)
    if (!$user && strpos($email, '@vidakushala.noemail') === false) {
        $user = get_user_by('email', $email);
    }

    // 4. Si no existe ? devolver datos para pre-llenar el formulario de registro
    if (!$user) {
        return new WP_Error('not_found', 'No existe cuenta. Completa tu registro.', array(
            'status'     => 404,
            'email'      => strpos($email, '@vidakushala.noemail') !== false ? '' : $email,
            'first_name' => $first,
            'last_name'  => $last,
            'name'       => $name,
            'avatar'     => $avatar,
        ));
    }

    // 5. Usuario existente: actualizar Facebook ID y avatar
    if (!get_user_meta($user->ID, '_facebook_id', true)) {
        update_user_meta($user->ID, '_facebook_id', $fb_id);
    }
    if ($avatar) update_user_meta($user->ID, '_social_avatar_url', $avatar);

    // 6. Responder con token de la app
    return rest_ensure_response(vk_user_payload($user));
}


function vk_verify($req) {
    $token = sanitize_text_field($req->get_param('token'));
    if (!$token) return new WP_Error('no_token', 'Token requerido', array('status' => 400));
    $uid  = vk_read_token($token);
    if (!$uid)  return new WP_Error('invalid',    'Token invalido', array('status' => 401));
    $user = get_user_by('id', $uid);
    if (!$user) return new WP_Error('not_found',  'Usuario no encontrado', array('status' => 404));
    return rest_ensure_response(array_merge(array('valid' => true), vk_user_payload($user)));
}

/* ===============================================
   HELPER: payload del usuario
=============================================== */
function vk_user_payload($user) {
    // Priorizar avatar de red social si existe
    $social_avatar = get_user_meta($user->ID, '_social_avatar_url', true);
    $avatar_url    = $social_avatar ?: get_avatar_url($user->ID, array('size' => 200));
    return array(
        'token'        => vk_make_token($user->ID),
        'user_id'      => $user->ID,
        'display_name' => $user->display_name,
        'email'        => $user->user_email,
        'avatar_url'   => $avatar_url,
        'roles'        => array_values($user->roles),
    );
}

/* ===============================================
   CURSOS PUBLICOS
=============================================== */
/* === CATEGORIAS DE CURSOS === */
function vk_course_categories($req) {
    $terms = get_terms(array(
        'taxonomy'   => 'course-category',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ));
    if (is_wp_error($terms)) $terms = array();
    $data = array();
    foreach ($terms as $t) {
        $data[] = array(
            'id'    => (int)$t->term_id,
            'name'  => $t->name,
            'slug'  => $t->slug,
            'count' => (int)$t->count,
        );
    }
    return rest_ensure_response(array('data' => $data));
}

/* === CATEGORIAS DE PRODUCTOS === */
function vk_product_categories($req) {
    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ));
    if (is_wp_error($terms)) $terms = array();
    $data = array();
    foreach ($terms as $t) {
        if ($t->slug === 'uncategorized') continue;
        $data[] = array(
            'id'    => (int)$t->term_id,
            'name'  => $t->name,
            'slug'  => $t->slug,
            'count' => (int)$t->count,
        );
    }
    return rest_ensure_response(array('data' => $data));
}

function vk_public_courses($req) {
    global $wpdb;
    $search   = sanitize_text_field($req->get_param('search') ?: '');
    $cat_slug = sanitize_text_field($req->get_param('category') ?: '');

    $sql = "SELECT p.ID AS id, p.post_title AS title, p.post_excerpt AS excerpt,
            pm.meta_value AS thumb_id, pm2.meta_value AS price,
            (SELECT COUNT(*) FROM {$wpdb->posts} lp
             INNER JOIN {$wpdb->posts} tp ON tp.ID=lp.post_parent
             WHERE lp.post_type='lesson' AND tp.post_parent=p.ID
             AND tp.post_type='topics' AND lp.post_status='publish') AS lessons
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm  ON pm.post_id=p.ID  AND pm.meta_key='_thumbnail_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON pm2.post_id=p.ID AND pm2.meta_key='_regular_price'
            WHERE p.post_type='courses' AND p.post_status='publish'";

    // Filter by category via term relationship
    if ($cat_slug) {
        // Tutor LMS registra la taxonomy como 'course-category' o 'course_category'
        $term = get_term_by('slug', $cat_slug, 'course-category');
        if (!$term) $term = get_term_by('slug', $cat_slug, 'course_category');
        if ($term) {
            $sql .= $wpdb->prepare(
                " AND p.ID IN (
                    SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                    WHERE tt.term_id = %d
                )", $term->term_id);
        } else {
            return rest_ensure_response(array('data' => array(), 'total' => 0, 'category_not_found' => $cat_slug));
        }
    }

    if ($search) {
        $rows = $wpdb->get_results($wpdb->prepare($sql . " AND p.post_title LIKE %s ORDER BY p.post_date DESC LIMIT 30",
            '%' . $wpdb->esc_like($search) . '%'));
    } else {
        $rows = $wpdb->get_results($sql . " ORDER BY p.post_date DESC LIMIT 30");
    }

    if (empty($rows)) return rest_ensure_response(array('data' => array(), 'total' => 0));

    $data = array();
    foreach ($rows as $c) {
        $thumb = get_the_post_thumbnail_url((int)$c->id, 'large') ?: get_the_post_thumbnail_url((int)$c->id, 'medium') ?: get_the_post_thumbnail_url((int)$c->id) ?: '';
        $pay_link_l         = get_post_meta((int)$c->id, '_vk_payment_link',    true) ?: '';
        $paypal_link_l      = get_post_meta((int)$c->id, '_paypal_payment_link', true) ?: '';
        $tutor_price_l      = (float)get_post_meta((int)$c->id, 'tutor_course_price', true);
        $tutor_sale_price_l = (float)get_post_meta((int)$c->id, 'tutor_course_sale_price', true);
        $final_price_l      = ($tutor_sale_price_l > 0) ? $tutor_sale_price_l : $tutor_price_l;
        $price_type_l       = get_post_meta((int)$c->id, '_tutor_course_price_type', true);
        if ($price_type_l === 'free') $final_price_l = 0;
        $lvl_raw = get_post_meta((int)$c->id, '_tutor_course_level', true) ?: '';
        $lvl_map = array('beginner'=>'Principiante','intermediate'=>'Intermedio','expert'=>'Avanzado','all_levels'=>'Todos');
        $level_l = isset($lvl_map[$lvl_raw]) ? $lvl_map[$lvl_raw] : $lvl_raw;
        // Categories
        $terms = get_the_terms((int)$c->id, 'course-category') ?: array();
        $cats  = array();
        foreach ($terms as $t) $cats[] = array('id'=>(int)$t->term_id,'name'=>$t->name,'slug'=>$t->slug);
        $data[] = array(
            'id'             => (int)$c->id,
            'post_title'     => $c->title,
            'excerpt'        => wp_strip_all_tags($c->excerpt ?: ''),
            'featured_image' => $thumb ?: '',
            'total_lessons'  => (int)$c->lessons,
            'price'          => $final_price_l > 0 ? '$' . number_format($final_price_l, 2) : 'Gratis',
            'is_free'        => $final_price_l == 0,
            'payment_link'   => $pay_link_l,
            'paypal_link'    => $paypal_link_l,
            'permalink'      => get_permalink((int)$c->id),
            'categories'     => $cats,
            'type'           => 'course',
        );
    }
    return rest_ensure_response(array('data' => $data, 'total' => count($data)));
}

/* ── Helpers sin closure para compatibilidad PHP 5.3+ ── */
function vk_parse_list($raw) {
    if (empty($raw)) return array();
    if (is_array($raw)) return array_values(array_filter($raw));
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw))));
}

function vk_parse_lesson_video_meta($lid) {
    $vtype = ''; $dur = '';
    $vm = get_post_meta((int)$lid, '_video', true);
    if (!empty($vm)) {
        if (is_serialized($vm)) $vm = @unserialize($vm);
        if (is_array($vm)) {
            $src = isset($vm['source']) ? $vm['source'] : '';
            if ($src === 'youtube' || !empty($vm['source_youtube']))                { $vtype = 'youtube'; }
            elseif ($src === 'vimeo'  || !empty($vm['source_vimeo']))               { $vtype = 'vimeo'; }
            elseif ($src === 'html5'  || !empty($vm['source_html5']))               { $vtype = 'html5'; }
            elseif ($src === 'external_url' || !empty($vm['source_external_url']))  { $vtype = 'external'; }
            elseif (!empty($vm['source_embedded']))                                  { $vtype = 'embedded'; }
            if (!empty($vm['runtime'])) {
                $dur = is_array($vm['runtime']) ? '' : (string)$vm['runtime'];
                if ($dur && preg_match('/^00:(\d{2}:\d{2})$/', $dur, $m)) $dur = $m[1];
            } elseif (!empty($vm['playtime'])) {
                $secs = (int)(is_array($vm['playtime']) ? 0 : $vm['playtime']);
                $dur  = $secs > 0 ? sprintf('%02d:%02d', floor($secs/60), $secs%60) : '';
            }
        }
    }
    return array('video_type' => $vtype, 'duration' => $dur);
}

function vk_public_course_detail($req) {
    $id   = (int)$req['id'];
    $post = get_post($id);
    if (!$post || $post->post_type !== 'courses' || $post->post_status !== 'publish')
        return new WP_Error('not_found', 'Curso no encontrado', array('status' => 404));

    global $wpdb;
    $thumb_id = get_post_thumbnail_id($id);
    $thumb    = get_the_post_thumbnail_url($id, 'large') ?: get_the_post_thumbnail_url($id, 'medium') ?: get_the_post_thumbnail_url($id) ?: '';
    $price    = (float)(get_post_meta($id, '_regular_price', true) ?: 0);
    $lessons  = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} lp
         INNER JOIN {$wpdb->posts} tp ON tp.ID=lp.post_parent
         WHERE lp.post_type='lesson' AND tp.post_parent=%d
         AND tp.post_type='topics' AND lp.post_status='publish'", $id));

    // Tutor LMS ? meta keys confirmadas por debug:
    // tutor_course_price = precio regular, tutor_course_sale_price = precio de venta
    $tutor_price      = (float)get_post_meta($id, 'tutor_course_price', true);
    $tutor_sale_price = (float)get_post_meta($id, 'tutor_course_sale_price', true);
    $final_price      = ($tutor_sale_price > 0) ? $tutor_sale_price : $tutor_price;
    $price_type       = get_post_meta($id, '_tutor_course_price_type', true);
    if($price_type === 'free') $final_price = 0;
    $pay_link    = get_post_meta($id, '_vk_payment_link',    true) ?: '';
    $paypal_link = get_post_meta($id, '_paypal_payment_link', true) ?: '';

    // Certificado ? meta key confirmada
    $has_cert = !empty(get_post_meta($id, 'tutor_course_certificate_template', true));

    // Metadatos adicionales del curso
    $level    = get_post_meta($id, '_tutor_course_level', true) ?: '';
    $level_labels = array('beginner'=>'Principiante','intermediate'=>'Intermedio','expert'=>'Avanzado','all_levels'=>'Todos los niveles');
    $level_label  = isset($level_labels[$level]) ? $level_labels[$level] : $level;

    // Duracion total del curso
    $dur_raw  = get_post_meta($id, '_tutor_course_duration', true) ?: '';
    $dur_label = '';
    if ($dur_raw) {
        $parts = explode(':', $dur_raw);
        $h = isset($parts[0]) ? (int)$parts[0] : 0;
        $m = isset($parts[1]) ? (int)$parts[1] : 0;
        if ($h > 0 && $m > 0)      $dur_label = $h.' horas '.$m.' min';
        elseif ($h > 0)            $dur_label = $h.' horas';
        elseif ($m > 0)            $dur_label = $m.' min';
        else                       $dur_label = $dur_raw;
    }

    // Quizzes del curso
    global $wpdb;
    $quizzes = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->posts} t ON t.ID=p.post_parent
         WHERE p.post_type='tutor_quiz' AND p.post_status='publish'
         AND t.post_type='topics' AND t.post_parent=%d", $id));

    // Contenido HTML seguro (permite etiquetas básicas)
    $allowed_html = array(
        'p'=>array(),'br'=>array(),'strong'=>array(),'b'=>array(),'em'=>array(),'i'=>array(),
        'ul'=>array(),'ol'=>array(),'li'=>array(),'h1'=>array(),'h2'=>array(),'h3'=>array(),
        'h4'=>array(),'h5'=>array(),'h6'=>array(),'a'=>array('href'=>array(),'target'=>array()),
        'img'=>array('src'=>array(),'alt'=>array(),'width'=>array(),'height'=>array(),'style'=>array()),
        'table'=>array(),'thead'=>array(),'tbody'=>array(),'tr'=>array(),'td'=>array('colspan'=>array(),'rowspan'=>array()),
        'th'=>array('colspan'=>array(),'rowspan'=>array()),'blockquote'=>array(),'span'=>array('style'=>array()),
        'div'=>array('style'=>array()),'iframe'=>array('src'=>array(),'width'=>array(),'height'=>array(),'frameborder'=>array(),'allowfullscreen'=>array()),
    );
    $post_html = wp_kses(wpautop($post->post_content ?: ''), $allowed_html);

    // Metadatos adicionales: Lo que aprenderás y Requisitos
    $what_will_learn = get_post_meta($id, '_tutor_course_benefits', true)
        ?: get_post_meta($id, '_tutor_course_objectives', true)
        ?: get_post_meta($id, 'course_objectives', true) ?: '';
    $requirements    = get_post_meta($id, '_tutor_course_requirements', true)
        ?: get_post_meta($id, 'course_requirements', true) ?: '';
    $target_audience = get_post_meta($id, '_tutor_course_target_audience', true)
        ?: get_post_meta($id, 'course_target_audience', true) ?: '';


    // Curriculum público: topics → lecciones con duración y estado de preview
    $pub_topics  = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_title FROM {$wpdb->posts}
         WHERE post_parent=%d AND post_type='topics' AND post_status='publish'
         ORDER BY menu_order ASC, ID ASC", $id));
    $curriculum = array();
    foreach ((array)$pub_topics as $t) {
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_type FROM {$wpdb->posts}
             WHERE post_parent=%d AND post_type IN ('lesson','tutor_quiz')
             AND post_status='publish' ORDER BY menu_order ASC, ID ASC", $t->ID));
        $lesson_list = array();
        foreach ((array)$items as $l) {
            $is_preview = (bool) get_post_meta((int)$l->ID, '_is_preview', true);
            $vinfo      = $l->post_type === 'lesson' ? vk_parse_lesson_video_meta($l->ID) : array('video_type'=>'', 'duration'=>'');
            $lesson_list[] = array(
                'id'         => (int)$l->ID,
                'post_title' => $l->post_title,
                'post_type'  => $l->post_type,
                'video_type' => $vinfo['video_type'],
                'duration'   => $vinfo['duration'],
                'is_preview' => $is_preview,
            );
        }
        $curriculum[] = array(
            'id'         => (int)$t->ID,
            'post_title' => $t->post_title,
            'contents'   => $lesson_list,
        );
    }

    // Video de introducción del curso
    $intro_video = array();
    $cvm = get_post_meta($id, '_video', true);
    if (!empty($cvm)) {
        if (is_serialized($cvm)) $cvm = @unserialize($cvm);
        if (is_array($cvm)) {
            $src = isset($cvm['source']) ? $cvm['source'] : '';
            if ($src === 'youtube' || !empty($cvm['source_youtube']))
                $intro_video = array('type'=>'youtube','url'=>$cvm['source_youtube']??'');
            elseif ($src === 'vimeo' || !empty($cvm['source_vimeo']))
                $intro_video = array('type'=>'vimeo','url'=>$cvm['source_vimeo']??'');
            elseif ($src === 'html5' || !empty($cvm['source_html5']))
                $intro_video = array('type'=>'html5','url'=>$cvm['source_html5']??'');
            elseif ($src === 'external_url' || !empty($cvm['source_external_url']))
                $intro_video = array('type'=>'external','url'=>$cvm['source_external_url']??'');
            elseif (!empty($cvm['source_embedded']))
                $intro_video = array('type'=>'embedded','embed'=>$cvm['source_embedded']);
        }
    }

    return rest_ensure_response(array(
        'id'              => $id,
        'post_title'      => $post->post_title,
        'post_content'    => $post_html,
        'excerpt'         => wp_strip_all_tags($post->post_excerpt ?: ''),
        'featured_image'  => $thumb ?: '',
        'total_lessons'   => $lessons,
        'total_quizzes'   => $quizzes,
        'price'           => $final_price > 0 ? '$' . number_format($final_price, 2) : 'Gratis',
        'regular_price'   => ($tutor_sale_price > 0 && $tutor_price > 0) ? '$' . number_format($tutor_price, 2) : '',
        'is_free'         => $final_price == 0,
        'payment_link'    => $pay_link,
        'paypal_link'     => $paypal_link,
        'duration'        => $dur_label,
        'duration_raw'    => $dur_raw,
        'has_certificate' => $has_cert,
        'level'           => $level_label,
        'what_will_learn' => vk_parse_list($what_will_learn),
        'requirements'    => vk_parse_list($requirements),
        'target_audience' => vk_parse_list($target_audience),
        'curriculum'      => $curriculum,
        'permalink'       => get_permalink($id),
        'type'            => 'course',
        'intro_video'     => $intro_video,
    ));
}

/* ═══════════════════════════════════════════════════════════════
   HELPERS DE PROGRESO — Fuente unica de verdad para el progreso
   ═══════════════════════════════════════════════════════════════ */
function vk_is_lesson_completed($lesson_id, $user_id) {
    global $wpdb;
    $in_usermeta = (bool) get_user_meta($user_id, '_tutor_completed_lesson_id_' . $lesson_id, true);
    if ($in_usermeta) return true;
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type='lesson_completed' AND comment_post_ID=%d AND user_id=%d",
        $lesson_id, $user_id));
}
function vk_get_course_progress($course_id, $user_id) {
    global $wpdb;

    // ── Conteo manual DIRECTO desde la BD (fuente primaria) ──
    // Siempre hacemos el conteo manual primero porque:
    // 1. tutor_utils()->get_course_completed_percent() puede tener caché stale
    // 2. tutor_utils puede contar elementos distintos (videos, attachments, etc.)
    // 3. Nuestra consulta directa a usermeta + comments es 100% confiable

    $lesson_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT lp.ID FROM {$wpdb->posts} lp
         INNER JOIN {$wpdb->posts} tp ON tp.ID = lp.post_parent
         WHERE lp.post_type IN ('lesson','tutor_quiz')
           AND tp.post_parent = %d AND tp.post_type = 'topics'
           AND lp.post_status = 'publish'",
        $course_id));

    // Fallback: sin topics — buscar directamente bajo el curso
    if (empty($lesson_ids)) {
        $lesson_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_parent = %d
               AND post_type IN ('lesson','tutor_quiz')
               AND post_status = 'publish'",
            $course_id));
    }

    $total = count($lesson_ids);
    if ($total === 0) {
        return array('pct' => 0, 'completed' => 0, 'total' => 0, 'source' => 'no_content');
    }

    // Obtener IDs completados desde usermeta (consulta DIRECTA, sin caché)
    $meta_keys = array_map(function($id) {
        return '_tutor_completed_lesson_id_' . $id;
    }, $lesson_ids);
    $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
    $args_um = array_merge(array($user_id), $meta_keys);
    $done_usermeta = (array) $wpdb->get_col(
        $wpdb->prepare(
            "SELECT REPLACE(meta_key, '_tutor_completed_lesson_id_', '') AS lid
             FROM {$wpdb->usermeta}
             WHERE user_id = %d AND meta_key IN ($placeholders) AND meta_value != ''",
            $args_um
        )
    );

    // IDs completados desde wp_comments
    if (!empty($lesson_ids)) {
        $id_placeholders = implode(',', array_fill(0, count($lesson_ids), '%d'));
        $args_cm = array_merge(array($user_id), $lesson_ids);
        $done_comments = (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT comment_post_ID FROM {$wpdb->comments}
                 WHERE comment_type = 'lesson_completed' AND user_id = %d
                   AND comment_post_ID IN ($id_placeholders)",
                $args_cm
            )
        );
    } else {
        $done_comments = array();
    }

    // Unión de ambas fuentes
    $all_done_ids = array_unique(array_merge($done_usermeta, $done_comments));
    $completed    = count(array_intersect(array_map('strval', $lesson_ids), array_map('strval', $all_done_ids)));
    $pct          = (int) round(($completed / $total) * 100);

    return array(
        'pct'       => $pct,
        'completed' => $completed,
        'total'     => $total,
        'source'    => 'direct_db',
    );
}

function vk_course_progress($req) {
    $uid=vk_uid($req); if (!$uid) return new WP_Error('unauthorized','Token requerido',array('status'=>401));
    $cid=(int)$req['id']; if (!$cid) return new WP_Error('missing','Falta course_id',array('status'=>400));
    wp_suspend_cache_addition(true); $progress=vk_get_course_progress($cid,$uid); wp_suspend_cache_addition(false);
    $is_completed=false; $cert_hash='';
    if (function_exists('tutor_utils')) { $comp=tutor_utils()->is_completed_course($cid,$uid,false); if ($comp&&!empty($comp->completed_hash)){$is_completed=true;$cert_hash=$comp->completed_hash;} }
    return rest_ensure_response(array('success'=>true,'course_id'=>$cid,'pct'=>$progress['pct'],'completed'=>$progress['completed'],'total'=>$progress['total'],'source'=>$progress['source'],'is_officially_completed'=>$is_completed,'cert_hash'=>$cert_hash));
}

/* ===============================================
   MIS CURSOS
=============================================== */
function vk_my_courses($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));

    global $wpdb;

    // Consulta de cursos inscritos
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID AS id, p.post_title AS title, pm.meta_value AS thumb_id,
         e.post_status AS enroll_status, e.post_date AS enroll_date
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->posts} e ON e.post_parent = p.ID
             AND e.post_type = 'tutor_enrolled' AND e.post_author = %d
         LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
         WHERE p.post_type = 'courses' AND p.post_status = 'publish'
         GROUP BY p.ID ORDER BY e.post_date DESC",
        $uid));

    if (empty($rows)) return rest_ensure_response(array('data' => array(), 'debug_uid' => $uid));

    // ── Obtener todos los IDs de lecciones completadas por el usuario EN UNA SOLA consulta ──
    $done_usermeta_raw = $wpdb->get_col($wpdb->prepare(
        "SELECT REPLACE(meta_key, '_tutor_completed_lesson_id_', '') AS lid
         FROM {$wpdb->usermeta}
         WHERE user_id = %d AND meta_key LIKE '_tutor_completed_lesson_id_%' AND meta_value != ''",
        $uid));

    $done_comments_raw = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT comment_post_ID FROM {$wpdb->comments}
         WHERE comment_type = 'lesson_completed' AND user_id = %d",
        $uid));

    // Unión de ambas fuentes — IDs de lecciones completadas
    $all_done_ids = array_unique(array_merge(
        array_map('intval', $done_usermeta_raw),
        array_map('intval', $done_comments_raw)
    ));

    $data = array();
    foreach ($rows as $c) {
        $course_id = (int)$c->id;

        // Lecciones y quizzes del curso (con topics)
        $lesson_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT lp.ID FROM {$wpdb->posts} lp
             INNER JOIN {$wpdb->posts} tp ON tp.ID = lp.post_parent
             WHERE lp.post_type IN ('lesson','tutor_quiz')
               AND tp.post_parent = %d AND tp.post_type = 'topics'
               AND lp.post_status = 'publish'",
            $course_id));

        // Fallback: sin topics
        if (empty($lesson_ids)) {
            $lesson_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_parent = %d AND post_type IN ('lesson','tutor_quiz')
                   AND post_status = 'publish'",
                $course_id));
        }

        $total     = count($lesson_ids);
        $done_ids_course = $total > 0
            ? array_values(array_intersect(array_map('intval', $lesson_ids), $all_done_ids))
            : array();
        $completed = count($done_ids_course);
        $pct = $total > 0 ? (int)round(($completed / $total) * 100) : 0;

        // Última lección vista (por timestamp en usermeta)
        $last_lesson_title = '';
        if (!empty($done_ids_course)) {
            $meta_keys = array_map(function($lid){ return '_tutor_completed_lesson_id_' . $lid; }, $done_ids_course);
            $mk_ph     = implode(',', array_fill(0, count($meta_keys), '%s'));
            $args_mk   = array_merge(array($uid), $meta_keys);
            $last_meta = $wpdb->get_row($wpdb->prepare(
                "SELECT meta_key FROM {$wpdb->usermeta}
                 WHERE user_id=%d AND meta_key IN ($mk_ph) AND meta_value != ''
                 ORDER BY CAST(meta_value AS UNSIGNED) DESC LIMIT 1",
                $args_mk));
            if ($last_meta) {
                $last_lid = (int)str_replace('_tutor_completed_lesson_id_', '', $last_meta->meta_key);
                if ($last_lid) $last_lesson_title = get_the_title($last_lid);
            }
        }

        $thumb       = get_the_post_thumbnail_url($course_id, 'large') ?: get_the_post_thumbnail_url($course_id, 'medium') ?: get_the_post_thumbnail_url($course_id) ?: '';
        $lvl_raw     = get_post_meta($course_id, '_tutor_course_level', true) ?: '';
        $lvl_map     = array('beginner'=>'Principiante','intermediate'=>'Intermedio','expert'=>'Avanzado','all_levels'=>'Todos');
        $price_type  = get_post_meta($course_id, '_tutor_course_price_type', true);
        $is_paid     = ($price_type !== 'free');
        $pay_link    = $is_paid ? (get_post_meta($course_id, '_vk_payment_link',    true) ?: '') : '';
        $paypal_link = $is_paid ? (get_post_meta($course_id, '_paypal_payment_link', true) ?: '') : '';
        $is_preview  = ($c->enroll_status === 'preview');

        $data[] = array(
            'id'                  => $course_id,
            'post_title'          => $c->title,
            'featured_image'      => $thumb ?: '',
            'completed_percent'   => $pct,
            'completed_lessons'   => $completed,
            'total_lessons'       => $total,
            'last_lesson_title'   => $last_lesson_title,
            'enroll_status'       => $c->enroll_status,
            'is_preview_enrolled' => $is_preview,
            'is_paid'             => $is_paid,
            'payment_link'        => $pay_link,
            'paypal_link'         => $paypal_link,
            'level'               => isset($lvl_map[$lvl_raw]) ? $lvl_map[$lvl_raw] : $lvl_raw,
            'permalink'           => get_permalink($course_id),
        );
    }
    return rest_ensure_response(array('data' => $data));
}


/* ===============================================
   DASHBOARD
=============================================== */
function vk_my_dashboard($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));

    global $wpdb;
    $enrolled  = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='tutor_enrolled' AND post_author=%d AND post_status IN ('completed','approved','active','pending','enrolled','publish')", $uid));
    $completed = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT comment_post_ID) FROM {$wpdb->comments} WHERE comment_type='course_completed' AND user_id=%d", $uid));

    return rest_ensure_response(array('data' => array(
        'enrolled_courses'  => $enrolled,
        'completed_courses' => $completed,
    )));
}

/* ===============================================
   DIAGNÓSTICO RÁPIDO DE CONTENIDOS
=============================================== */
function vk_ping_contents($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
    $cid = (int)$req['id'];
    global $wpdb;
    $ok = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type='tutor_enrolled' AND post_parent=%d AND post_author=%d ORDER BY ID DESC LIMIT 1",
        $cid, $uid));
    if (!$ok) $ok = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key=%s LIMIT 1", $uid, '_tutor_course_enrolled_' . $cid));
    $topics = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_parent=%d AND post_type='topics' AND post_status='publish'", $cid));
    $lessons = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} lp INNER JOIN {$wpdb->posts} tp ON tp.ID=lp.post_parent
         WHERE lp.post_type IN ('lesson','tutor_quiz') AND tp.post_parent=%d AND tp.post_type='topics' AND lp.post_status='publish'", $cid));
    return rest_ensure_response(array(
        'ok'        => true,
        'uid'       => $uid,
        'course_id' => $cid,
        'enrolled'  => $ok ? true : false,
        'topics'    => (int)$topics,
        'lessons'   => (int)$lessons,
        'php'       => PHP_VERSION,
        'plugin_v'  => '6.0.1',
    ));
}

/* ===============================================
   CONTENIDO DEL CURSO
=============================================== */
function vk_my_course_contents($req) {
    try {
        $uid = vk_uid($req);
        if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
        $cid = (int)$req['id'];

        global $wpdb;

        // Forzar consulta directa a BD ignorando caché de objetos de WordPress
        wp_suspend_cache_addition(true);

        // Verificar inscripción — acepta cualquier status incluyendo 'preview'
        $enroll_row = $wpdb->get_row($wpdb->prepare(
            "SELECT ID, post_status FROM {$wpdb->posts}
             WHERE post_type='tutor_enrolled' AND post_parent=%d AND post_author=%d
             ORDER BY ID DESC LIMIT 1",
            $cid, $uid));

        // Segunda verificación: usermeta (SQL directo — bypasa caché WP)
        if (!$enroll_row) {
            $meta_ok = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->usermeta}
                 WHERE user_id=%d AND meta_key=%s LIMIT 1",
                $uid, '_tutor_course_enrolled_' . $cid));
            if (!$meta_ok) {
                wp_suspend_cache_addition(false);
                return new WP_Error('not_enrolled', 'No estas inscrito en este curso', array('status' => 403));
            }
        }

        // Tipo de inscripción: 'preview' = solo acceso a lecciones gratuitas
        $enrollment_type = ($enroll_row && $enroll_row->post_status === 'preview') ? 'preview' : 'full';

        // Links de pago para CTA de compra (solo necesarios en preview)
        $price_type  = get_post_meta($cid, '_tutor_course_price_type', true);
        $is_paid     = ($price_type !== 'free');
        $pay_link    = get_post_meta($cid, '_vk_payment_link',    true) ?: '';
        $paypal_link = get_post_meta($cid, '_paypal_payment_link', true) ?: '';

        // Detectar acceso secuencial (content drip)
        $sequential = false;
        try {
            $cs = get_post_meta($cid, '_tutor_course_settings', true);
            if (!empty($cs)) {
                if (is_serialized($cs)) $cs = @unserialize($cs);
                if (is_array($cs) && !empty($cs['content_drip_settings']['enabled'])
                    && isset($cs['content_drip_settings']['drip_type'])
                    && $cs['content_drip_settings']['drip_type'] === 'after_finishing_prerequisites') {
                    $sequential = true;
                }
            }
        } catch (Exception $e) { $sequential = false; }

        // Buscar topics (secciones) del curso
        $topics = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_parent=%d AND post_type='topics' AND post_status='publish'
             ORDER BY menu_order ASC, ID ASC", $cid));

        $result = array();

        // Índice global de lecciones (para acceso secuencial)
        $global_order = array(); // [lid => position]
        $global_done  = array(); // [lid => bool]
        if ($sequential && !empty($topics)) {
            $pos = 0;
            foreach ($topics as $t) {
                $ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts}
                     WHERE post_parent=%d AND post_type IN ('lesson','tutor_quiz')
                     AND post_status='publish' ORDER BY menu_order ASC, ID ASC", $t->ID));
                foreach ((array)$ids as $lid) {
                    $global_order[(int)$lid] = $pos++;
                    $global_done[(int)$lid]  = vk_is_lesson_completed((int)$lid, $uid);
                }
            }
        }

        if (!empty($topics)) {
            // Curso con estructura de topics → lecciones dentro de cada topic
            foreach ($topics as $t) {
                $items = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title, post_type FROM {$wpdb->posts}
                     WHERE post_parent=%d AND post_type IN ('lesson','tutor_quiz')
                     AND post_status='publish' ORDER BY menu_order ASC, ID ASC", $t->ID));
                $lessons = array();
                foreach ((array)$items as $l) {
                    $lid  = (int)$l->ID;
                    $done = $sequential && isset($global_done[$lid]) ? $global_done[$lid] : vk_is_lesson_completed($lid, $uid);

                    // is_preview: lección marcada como gratuita en Tutor LMS
                    $is_preview_lesson = (bool)(int)get_post_meta($lid, '_is_preview', true);

                    // Bloqueo: secuencial O inscripción preview con lección no-preview
                    $is_locked    = false;
                    $locked_reason = '';
                    if ($enrollment_type === 'preview' && $is_paid && !$is_preview_lesson) {
                        $is_locked    = true;
                        $locked_reason = 'payment_required';
                    } elseif ($sequential && isset($global_order[$lid]) && $global_order[$lid] > 0) {
                        $prev_pos = $global_order[$lid] - 1;
                        $prev_lid = array_search($prev_pos, $global_order);
                        if ($prev_lid !== false && empty($global_done[$prev_lid])) {
                            $is_locked    = true;
                            $locked_reason = 'sequential';
                        }
                    }

                    // Tipo de video y duración (inline, sin closures)
                    $vtype = ''; $vdur = '';
                    if ($l->post_type === 'lesson') {
                        $vm = get_post_meta($lid, '_video', true);
                        if (!empty($vm)) {
                            if (is_serialized($vm)) $vm = @unserialize($vm);
                            if (is_array($vm)) {
                                $src = isset($vm['source']) ? $vm['source'] : '';
                                if ($src === 'youtube' || !empty($vm['source_youtube']))                { $vtype = 'youtube'; }
                                elseif ($src === 'vimeo'  || !empty($vm['source_vimeo']))               { $vtype = 'vimeo'; }
                                elseif ($src === 'html5'  || !empty($vm['source_html5']))               { $vtype = 'html5'; }
                                elseif ($src === 'external_url' || !empty($vm['source_external_url']))  { $vtype = 'external'; }
                                elseif (!empty($vm['source_embedded']))                                  { $vtype = 'embedded'; }
                                if (!empty($vm['runtime'])) {
                                    $vdur = is_array($vm['runtime']) ? '' : (string)$vm['runtime'];
                                    if ($vdur && preg_match('/^00:(\d{2}:\d{2})$/', $vdur, $dm)) $vdur = $dm[1];
                                } elseif (!empty($vm['playtime'])) {
                                    $secs = (int)(is_array($vm['playtime']) ? 0 : $vm['playtime']);
                                    $vdur = $secs > 0 ? sprintf('%02d:%02d', floor($secs/60), $secs%60) : '';
                                }
                            }
                        }
                    }

                    $lessons[] = array(
                        'id'            => $lid,
                        'post_title'    => $l->post_title,
                        'post_type'     => $l->post_type,
                        'video_type'    => $vtype,
                        'duration'      => $vdur,
                        'is_preview'    => $is_preview_lesson,
                        'is_completed'  => $done,
                        'is_locked'     => $is_locked,
                        'locked_reason' => $locked_reason,
                    );
                }
                $result[] = array(
                    'id'         => (int)$t->ID,
                    'post_title' => $t->post_title,
                    'contents'   => $lessons,
                );
            }
        } else {
            // Fallback: lecciones directamente bajo el curso (sin topics)
            $direct = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_title, post_type FROM {$wpdb->posts}
                 WHERE post_parent=%d AND post_type IN ('lesson','tutor_quiz')
                 AND post_status='publish' ORDER BY menu_order ASC, ID ASC", $cid));

            if (!empty($direct)) {
                $lessons      = array();
                $prev_done_fb = true;
                foreach ($direct as $idx => $l) {
                    $lid               = (int)$l->ID;
                    $done              = vk_is_lesson_completed($lid, $uid);
                    $is_preview_lesson = (bool)(int)get_post_meta($lid, '_is_preview', true);
                    $is_locked         = false;
                    $locked_reason     = '';
                    if ($enrollment_type === 'preview' && $is_paid && !$is_preview_lesson) {
                        $is_locked    = true;
                        $locked_reason = 'payment_required';
                    } elseif ($sequential && $idx > 0 && !$prev_done_fb) {
                        $is_locked    = true;
                        $locked_reason = 'sequential';
                    }
                    $prev_done_fb = $done;
                    $vtype = ''; $vdur = '';
                    if ($l->post_type === 'lesson') {
                        $vm = get_post_meta($lid, '_video', true);
                        if (!empty($vm)) {
                            if (is_serialized($vm)) $vm = @unserialize($vm);
                            if (is_array($vm)) {
                                $src = isset($vm['source']) ? $vm['source'] : '';
                                if ($src === 'youtube' || !empty($vm['source_youtube']))                { $vtype = 'youtube'; }
                                elseif ($src === 'vimeo'  || !empty($vm['source_vimeo']))               { $vtype = 'vimeo'; }
                                elseif ($src === 'html5'  || !empty($vm['source_html5']))               { $vtype = 'html5'; }
                                elseif ($src === 'external_url' || !empty($vm['source_external_url']))  { $vtype = 'external'; }
                                elseif (!empty($vm['source_embedded']))                                  { $vtype = 'embedded'; }
                                if (!empty($vm['runtime'])) {
                                    $vdur = is_array($vm['runtime']) ? '' : (string)$vm['runtime'];
                                    if ($vdur && preg_match('/^00:(\d{2}:\d{2})$/', $vdur, $dm)) $vdur = $dm[1];
                                } elseif (!empty($vm['playtime'])) {
                                    $secs = (int)(is_array($vm['playtime']) ? 0 : $vm['playtime']);
                                    $vdur = $secs > 0 ? sprintf('%02d:%02d', floor($secs/60), $secs%60) : '';
                                }
                            }
                        }
                    }
                    $lessons[] = array(
                        'id'            => $lid,
                        'post_title'    => $l->post_title,
                        'post_type'     => $l->post_type,
                        'video_type'    => $vtype,
                        'duration'      => $vdur,
                        'is_preview'    => $is_preview_lesson,
                        'is_completed'  => $done,
                        'is_locked'     => $is_locked,
                        'locked_reason' => $locked_reason,
                    );
                }
                $result[] = array(
                    'id'         => $cid,
                    'post_title' => 'Contenido del curso',
                    'contents'   => $lessons,
                );
            }
        }

        $is_officially_completed = false;
        $enrolled_id = $enroll_row ? (int)$enroll_row->ID : 0;
        if (function_exists('tutor_utils')) {
            $comp = tutor_utils()->is_completed_course($cid, $uid, false);
            if ($comp && !empty($comp->completed_hash)) {
                $is_officially_completed = true;
            }
        } else {
            $is_officially_completed = (bool)get_post_meta($enrolled_id, 'tutor_course_completed', true);
        }

        wp_suspend_cache_addition(false);

        // ── Video de introducción del curso ───────────────────────────
        $intro_video = array();
        $cvm = get_post_meta($cid, '_video', true);
        if (!empty($cvm)) {
            if (is_serialized($cvm)) $cvm = @unserialize($cvm);
            if (is_array($cvm)) {
                $src = isset($cvm['source']) ? $cvm['source'] : '';
                if ($src === 'youtube' || !empty($cvm['source_youtube'])) {
                    $intro_video = array('type'=>'youtube','url'=>$cvm['source_youtube']??'');
                } elseif ($src === 'vimeo' || !empty($cvm['source_vimeo'])) {
                    $intro_video = array('type'=>'vimeo','url'=>$cvm['source_vimeo']??'');
                } elseif ($src === 'html5' || !empty($cvm['source_html5'])) {
                    $intro_video = array('type'=>'html5','url'=>$cvm['source_html5']??'');
                } elseif ($src === 'external_url' || !empty($cvm['source_external_url'])) {
                    $intro_video = array('type'=>'external','url'=>$cvm['source_external_url']??'');
                } elseif (!empty($cvm['source_embedded'])) {
                    $intro_video = array('type'=>'embedded','embed'=>$cvm['source_embedded']);
                }
            }
        }

        // ── Archivos adicionales del curso ────────────────────────────
        $attachments = vk_parse_tutor_attachments($cid);

        return rest_ensure_response(array(
            'topics'                  => $result,
            'total_topics'            => count($topics),
            'course_id'               => $cid,
            'enrolled_id'             => $enrolled_id,
            'enrollment_type'         => $enrollment_type,
            'is_paid'                 => $is_paid,
            'payment_link'            => $pay_link,
            'paypal_link'             => $paypal_link,
            'is_officially_completed' => $is_officially_completed,
            'intro_video'             => $intro_video,
            'attachments'             => $attachments,
        ));
    } catch (Throwable $e) {
        wp_suspend_cache_addition(false);
        return new WP_Error('server_error', '[vk_my_course_contents] '.$e->getMessage().'  L'.$e->getLine().' in '.$e->getFile(), array('status' => 500));
    } catch (Exception $e) {
        wp_suspend_cache_addition(false);
        return new WP_Error('server_error', '[vk_my_course_contents] '.$e->getMessage(), array('status' => 500));
    }
}


/* ===============================================
   HELPER: Leer archivos adjuntos Tutor LMS
   Soporta: _tutor_attachments (array serializado de IDs)
            y tutor_course_additional_data (JSON Pro)
=============================================== */
function vk_parse_tutor_attachments($post_id) {
    $attachments = array();

    // Método 1: _tutor_attachments → array serializado de attachment IDs
    $raw = get_post_meta($post_id, '_tutor_attachments', true);
    if (!empty($raw)) {
        if (is_string($raw) && is_serialized($raw)) {
            $raw = @unserialize($raw);
        }
        if (is_string($raw)) {
            // Puede ser JSON
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $raw = $decoded;
        }
        foreach ((array)$raw as $item) {
            // Item puede ser un ID directo o un array con 'id'
            if (is_numeric($item)) {
                $att_id = (int)$item;
            } elseif (is_array($item) && !empty($item['id'])) {
                $att_id = (int)$item['id'];
            } else {
                continue;
            }
            $url  = wp_get_attachment_url($att_id);
            $mime = get_post_mime_type($att_id);
            if (!$url) continue;
            $att  = get_post($att_id);
            $attachments[] = array(
                'id'    => $att_id,
                'title' => ($att && $att->post_title) ? $att->post_title : basename($url),
                'url'   => $url,
                'mime'  => $mime ?: 'application/octet-stream',
            );
        }
    }

    // Método 2: tutor_course_additional_data (Tutor LMS Pro, formato JSON)
    if (empty($attachments)) {
        $pro_raw = get_post_meta($post_id, 'tutor_course_additional_data', true);
        if (!empty($pro_raw)) {
            if (is_string($pro_raw) && is_serialized($pro_raw)) $pro_raw = @unserialize($pro_raw);
            if (is_string($pro_raw)) $pro_raw = json_decode($pro_raw, true);
            if (is_array($pro_raw)) {
                $files = !empty($pro_raw['attachments']) ? $pro_raw['attachments']
                       : (!empty($pro_raw['exercise_files']) ? $pro_raw['exercise_files'] : array());
                foreach ((array)$files as $item) {
                    if (is_numeric($item)) {
                        $att_id = (int)$item;
                        $url    = wp_get_attachment_url($att_id);
                        $mime   = get_post_mime_type($att_id);
                        if (!$url) continue;
                        $att    = get_post($att_id);
                        $attachments[] = array(
                            'id'    => $att_id,
                            'title' => ($att && $att->post_title) ? $att->post_title : basename($url),
                            'url'   => $url,
                            'mime'  => $mime ?: 'application/octet-stream',
                        );
                    } elseif (is_array($item) && !empty($item['url'])) {
                        $attachments[] = array(
                            'id'    => isset($item['id']) ? (int)$item['id'] : 0,
                            'title' => isset($item['title']) ? $item['title'] : basename($item['url']),
                            'url'   => $item['url'],
                            'mime'  => isset($item['mime']) ? $item['mime'] : 'application/octet-stream',
                        );
                    }
                }
            }
        }
    }

    return $attachments;
}


/* ===============================================
   LECCION
=============================================== */
function vk_my_lesson($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
    $lid  = (int)$req['id'];
    $post = get_post($lid);
    if (!$post) return new WP_Error('not_found', 'Leccion no encontrada', array('status' => 404));

    // ✅ FIX: Verificar en AMBAS tablas (usermeta + comments)
    $is_completed = vk_is_lesson_completed($lid, $uid);

    $video_url = ''; $video_type = ''; $embed_html = '';
    $vm = get_post_meta($lid, '_video', true);
    if (!empty($vm)) {
        if (is_serialized($vm)) $vm = unserialize($vm);
        if (is_array($vm)) {
            $src = isset($vm['source']) ? $vm['source'] : '';
            if ($src === 'youtube' || !empty($vm['source_youtube']))               { $video_url = isset($vm['source_youtube']) ? $vm['source_youtube'] : ''; $video_type = 'youtube'; }
            elseif ($src === 'vimeo' || !empty($vm['source_vimeo']))               { $video_url = isset($vm['source_vimeo']) ? $vm['source_vimeo'] : ''; $video_type = 'vimeo'; }
            elseif ($src === 'html5' || !empty($vm['source_html5']))               { $video_url = isset($vm['source_html5']) ? $vm['source_html5'] : ''; $video_type = 'html5'; }
            elseif ($src === 'external_url' || !empty($vm['source_external_url'])) { $video_url = isset($vm['source_external_url']) ? $vm['source_external_url'] : ''; $video_type = 'external'; }
            elseif (!empty($vm['source_embedded']))                                { $embed_html = $vm['source_embedded']; $video_type = 'embedded'; }
        }
    }
    // Archivos adjuntos de la lección (Tutor LMS Pro - Exercise Files)
    $lesson_attachments = vk_parse_tutor_attachments($lid);

    return rest_ensure_response(array(
        'id'           => $lid,
        'title'        => $post->post_title,
        'content'      => apply_filters('the_content', $post->post_content ?: ''),
        'video_url'    => $video_url,
        'video_type'   => $video_type,
        'embed_html'   => $embed_html,
        'is_completed' => $is_completed,
        'attachments'  => $lesson_attachments,
    ));
}


/* ===============================================
   QUIZ
=============================================== */
function vk_my_quiz($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
    $qid  = (int)$req['id'];
    $post = get_post($qid);
    if (!$post) return new WP_Error('not_found', 'Quiz no encontrado', array('status' => 404));

    global $wpdb;
    $options   = get_post_meta($qid, 'tutor_quiz_option', true);
    if (is_serialized($options)) $options = unserialize($options);
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT question_id, question_title, question_description, question_type,
                answer_explanation, question_mark, question_order, question_settings
         FROM {$wpdb->prefix}tutor_quiz_questions
         WHERE quiz_id=%d ORDER BY question_order ASC", $qid));

    $result = array();
    foreach ($questions as $q) {
        $q_settings = array();
        if (!empty($q->question_settings)) {
            $qs = maybe_unserialize($q->question_settings);
            if (is_array($qs)) $q_settings = $qs;
        }
        $q_img = '';
        if (!empty($q_settings['question_image_id'])) {
            $url = wp_get_attachment_image_url((int)$q_settings['question_image_id'], 'medium');
            if ($url) $q_img = $url;
        }
        $answers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tutor_quiz_question_answers
             WHERE belongs_question_id=%d ORDER BY answer_order ASC", $q->question_id), ARRAY_A);

        $opts = array();
        foreach ($answers as $a) {
            $img_url  = '';
            $image_id = isset($a['image_id']) ? (int)$a['image_id'] : 0;
            if ($image_id > 0) { $u = wp_get_attachment_image_url($image_id, 'medium'); if ($u) $img_url = $u; }
            if (!$img_url && isset($a['answer_view_format']) && $a['answer_view_format'] === 'image' && is_numeric($a['answer_title'])) {
                $u = wp_get_attachment_image_url((int)$a['answer_title'], 'medium');
                if ($u) $img_url = $u;
            }
            $view_format = isset($a['answer_view_format']) ? $a['answer_view_format'] : 'text';
            $title = $a['answer_title'];
            if ($view_format === 'image' || ($image_id > 0 && $img_url)) $title = '';
            $opts[] = array(
                'id'          => (int)$a['answer_id'],
                'title'       => $title,
                'is_correct'  => (bool)$a['is_correct'],
                'match_title' => isset($a['answer_two_gap_match']) ? $a['answer_two_gap_match'] : '',
                'view_format' => $view_format,
                'image_url'   => $img_url,
                'image_id'    => $image_id,
            );
        }
        $result[] = array(
            'id'          => (int)$q->question_id,
            'title'       => $q->question_title,
            'description' => $q->question_description,
            'type'        => $q->question_type,
            'mark'        => (float)$q->question_mark,
            'explanation' => $q->answer_explanation,
            'image_url'   => $q_img,
            'options'     => $opts,
        );
    }
    return rest_ensure_response(array('id' => $qid, 'title' => $post->post_title, 'options' => $options, 'questions' => $result, 'total' => count($result)));
}

/* ===============================================
   MARCAR LECCION COMPLETADA
=============================================== */
function vk_my_lesson_complete($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
    $lid = (int)$req->get_param('lesson_id');
    $cid = (int)$req->get_param('course_id');
    if (!$lid || !$cid) return new WP_Error('missing', 'Faltan parametros', array('status' => 400));

    global $wpdb;

    // Verificar en ambas tablas si ya está completada
    $already = vk_is_lesson_completed($lid, $uid);

    if (!$already) {
        $u = get_userdata($uid);
        wp_set_current_user($uid);

        // PASO 1: Escribir en wp_usermeta (fuente de verdad de Tutor LMS)
        update_user_meta($uid, '_tutor_completed_lesson_id_' . $lid, time());

        // PASO 2: Escribir en wp_comments (fuente usada por el API fallback)
        $already_comment = (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments}
             WHERE comment_type='lesson_completed' AND comment_post_ID=%d AND user_id=%d",
            $lid, $uid));
        if (!$already_comment) {
            $wpdb->insert($wpdb->comments, array(
                'comment_post_ID'  => $lid,
                'comment_author'   => $u ? $u->user_login : '',
                'comment_date'     => current_time('mysql'),
                'comment_date_gmt' => current_time('mysql', 1),
                'comment_approved' => 1,
                'comment_type'     => 'lesson_completed',
                'user_id'          => $uid,
            ));
        }

        // PASO 3: Método oficial de Tutor LMS (sincroniza hooks/emails)
        if (function_exists('tutor_utils') && method_exists(tutor_utils(), 'mark_lesson_complete')) {
            try { tutor_utils()->mark_lesson_complete($lid, $uid); }
            catch (Throwable $e) { /* ignorar excepciones de hooks de terceros */ }
        }

        // PASO 4: Hook para integraciones de terceros
        do_action('tutor_lesson_completed_after', $lid, $uid);

        // PASO 5: Limpiar cache de objetos de WordPress y Tutor LMS
        wp_cache_delete($uid, 'user_meta');
        clean_user_cache($uid);
        wp_cache_delete('tutor_course_completed_percent_' . $cid . '_' . $uid, 'tutor');
        // Forzar recalculo del porcentaje en Tutor LMS
        if (function_exists('tutor_utils') && method_exists(tutor_utils(), 'get_course_completed_percent')) {
            try {
                $ref  = new ReflectionMethod(tutor_utils(), 'get_course_completed_percent');
                $args = $ref->getNumberOfParameters() >= 3 ? array($cid, $uid, true) : array($cid, $uid);
                $ref->invokeArgs(tutor_utils(), $args);
            } catch (Throwable $e) {
                tutor_utils()->get_course_completed_percent($cid, $uid);
            }
        }

        // Calcular progreso actualizado desde AMBAS tablas
        $all_lesson_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT lp.ID FROM {$wpdb->posts} lp
             INNER JOIN {$wpdb->posts} tp ON tp.ID = lp.post_parent
             WHERE lp.post_type IN ('lesson','tutor_quiz') AND tp.post_parent = %d
             AND tp.post_type='topics' AND lp.post_status='publish'",
            $cid));

        // Fallback: buscar directamente bajo el curso si no hay topics
        if (empty($all_lesson_ids)) {
            $all_lesson_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_parent=%d AND post_type IN ('lesson','tutor_quiz') AND post_status='publish'",
                $cid));
        }

        $total_lessons    = count($all_lesson_ids);
        $completed_lessons = 0;
        foreach ($all_lesson_ids as $lsn_id) {
            if (vk_is_lesson_completed((int)$lsn_id, $uid)) $completed_lessons++;
        }
        $all_done = ($total_lessons > 0 && $completed_lessons >= $total_lessons);

        return rest_ensure_response(array(
            'completed'         => true,
            'lesson_id'         => $lid,
            'course_id'         => $cid,
            'total_lessons'     => $total_lessons,
            'completed_lessons' => $completed_lessons,
            'course_completed'  => $all_done,
        ));
    }

    // Lección ya marcada — igual devolvemos el progreso actual para actualizar la UI
    $progress = vk_get_course_progress($cid, $uid);
    return rest_ensure_response(array(
        'completed'         => true,
        'lesson_id'         => $lid,
        'already'           => true,
        'total_lessons'     => $progress['total'],
        'completed_lessons' => $progress['completed'],
        'course_completed'  => ($progress['total'] > 0 && $progress['completed'] >= $progress['total']),
    ));
}


/* ===============================================
   ENVIAR QUIZ
=============================================== */
function vk_my_quiz_submit($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
    $qid     = (int)$req->get_param('quiz_id');
    $cid     = (int)$req->get_param('course_id');
    $answers = $req->get_param('answers');
    if (!$qid || !$cid) return new WP_Error('missing', 'Faltan parametros', array('status' => 400));

    global $wpdb;
    $total_marks = 0; $earned_marks = 0; $correct = 0; $wrong = 0;
    if (is_array($answers)) {
        foreach ($answers as $ans) {
            $qid_item = (int)(isset($ans['question_id']) ? $ans['question_id'] : 0);
            $aid_item = isset($ans['answer_id']) ? $ans['answer_id'] : 0;
            if (!$qid_item) continue;
            $q = $wpdb->get_row($wpdb->prepare(
                "SELECT question_mark, question_type FROM {$wpdb->prefix}tutor_quiz_questions WHERE question_id=%d", $qid_item));
            if (!$q) continue;
            $total_marks += (float)$q->question_mark;
            $is_correct   = false;
            if (in_array($q->question_type, array('true_false', 'single_choice', 'multiple_choice'))) {
                $is_correct = (bool)$wpdb->get_var($wpdb->prepare(
                    "SELECT is_correct FROM {$wpdb->prefix}tutor_quiz_question_answers WHERE answer_id=%d AND belongs_question_id=%d AND is_correct=1",
                    (int)$aid_item, $qid_item));
            }
            if ($is_correct) { $earned_marks += (float)$q->question_mark; $correct++; } else { $wrong++; }
        }
    }
    $percentage = $total_marks > 0 ? round(($earned_marks / $total_marks) * 100) : 0;
    $wpdb->insert($wpdb->prefix . 'tutor_quiz_attempts', array(
        'course_id'                => $cid,
        'quiz_id'                  => $qid,
        'student_id'               => $uid,
        'total_questions'          => count((array)$answers),
        'total_answered_questions' => count((array)$answers),
        'total_marks'              => $total_marks,
        'earned_marks'             => $earned_marks,
        'attempt_status'           => 'attempt_ended',
        'earned_percentage'        => $percentage,
        'attempt_started_at'       => current_time('mysql'),
        'attempt_ended_at'         => current_time('mysql'),
    ));
    $attempt_id = $wpdb->insert_id;

    $passed = $percentage >= 80;

    // Disparar hooks nativos de Tutor LMS para el quiz
    do_action('tutor_quiz/attempt_ended', $attempt_id, $uid);
    if ($passed) {
        do_action('tutor_quiz_completed_successfully', $attempt_id, $uid);
        // AHORA: La finalización del curso es manual a través del nuevo endpoint /complete-course
    }

    return rest_ensure_response(array(
        'passed'      => $passed,
        'percentage'  => $percentage,
        'correct'     => $correct,
        'wrong'       => $wrong,
        'total'       => count((array)$answers),
        'earned'      => $earned_marks,
        'total_marks' => $total_marks,
    ));
}


/* ===============================================
   FINALIZAR CURSO MANUALMENTE
=============================================== */
function vk_complete_course($req) {
    try {
        $uid = vk_uid($req);
        if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
        $cid = (int)$req->get_param('course_id');
        if (!$cid) return new WP_Error('missing', 'course_id requerido', array('status' => 400));

        global $wpdb;

        // Verificar inscripción
        $enroll_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type='tutor_enrolled' AND post_parent=%d AND post_author=%d
             ORDER BY ID DESC LIMIT 1",
            $cid, $uid));
            
        if (!$enroll_id) {
            return new WP_Error('not_enrolled', 'No estás inscrito en este curso', array('status' => 403));
        }

        // Verificar si ya estaba completado oficialmente y con certificado generado (sin cache)
        if (function_exists('tutor_utils')) {
            $comp = tutor_utils()->is_completed_course($cid, $uid, false);
            if ($comp && !empty($comp->completed_hash)) {
                return rest_ensure_response(array('success' => true, 'already_completed' => true));
            }
        }

        // Verificar si todas las lecciones y quizzes están completados
        $total_lessons = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} lp
             INNER JOIN {$wpdb->posts} tp ON tp.ID=lp.post_parent
             WHERE lp.post_type IN ('lesson', 'tutor_quiz') AND tp.post_parent=%d
             AND tp.post_type='topics' AND lp.post_status='publish'",
            $cid));

        $completed_lessons = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT c.comment_post_ID) FROM {$wpdb->comments} c
             WHERE c.comment_type='lesson_completed' AND c.user_id=%d
             AND c.comment_post_ID IN (
                 SELECT lp2.ID FROM {$wpdb->posts} lp2
                 INNER JOIN {$wpdb->posts} tp2 ON tp2.ID=lp2.post_parent
                 WHERE lp2.post_type='lesson' AND tp2.post_parent=%d
                 AND tp2.post_type='topics' AND lp2.post_status='publish'
             )",
            $uid, $cid));

        $passed_quizzes = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT quiz_id) FROM {$wpdb->prefix}tutor_quiz_attempts
             WHERE course_id=%d AND student_id=%d AND earned_percentage>=80",
            $cid, $uid));

        $all_done = ($total_lessons > 0 && ($completed_lessons + $passed_quizzes) >= $total_lessons);

        if (!$all_done) {
            return new WP_Error('incomplete', 'Debes completar todas las lecciones y quizzes antes de finalizar el curso.', array('status' => 403));
        }

        // LIMPIEZA DE ESTADOS CORRUPTOS:
        // Si el usuario tenía el curso marcado como completado por un error anterior, pero no tiene certificado,
        // eliminamos ese registro defectuoso para que Tutor LMS lo vuelva a generar correctamente.
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->comments}
             WHERE comment_type='course_completed' AND comment_post_ID=%d AND user_id=%d
               AND (comment_content IS NULL OR comment_content = '')",
            $cid, $uid
        ));

        // Completar el curso utilizando el método oficial de Tutor LMS de forma protegida
        $completed_successfully = false;
        if (class_exists('\Tutor\Models\CourseModel') && method_exists('\Tutor\Models\CourseModel', 'mark_course_as_completed')) {
            try {
                wp_set_current_user($uid);
                // Intentar completar oficialmente (esto dispara los hooks internos)
                $res = \Tutor\Models\CourseModel::mark_course_as_completed($cid, $uid);
                if ($res) {
                    $completed_successfully = true;
                }
            } catch (\Throwable $err) {
                // Si falla por algún hook de envío de emails/SMTP, continuará y usará el fallback de base de datos
            }
        }

        if ($completed_successfully) {
            // También actualizar el estado de inscripción a 'completed'
            $date = current_time('mysql');
            $wpdb->update($wpdb->posts, array('post_status' => 'completed'), array('ID' => (int)$enroll_id));
            update_post_meta($enroll_id, 'tutor_course_completed', $date);

            // Obtener el cert_hash recién generado para devolverlo a la app
            $new_comp = tutor_utils()->is_completed_course($cid, $uid, false);
            $new_hash = ($new_comp && !empty($new_comp->completed_hash)) ? $new_comp->completed_hash : '';
            return rest_ensure_response(array('success' => true, 'cert_hash' => $new_hash));
        } else {
            // Fallback robusto y seguro de base de datos (evita caídas por SMTP o plugins de terceros)
            $date = current_time('mysql');
            $hash = substr(md5(wp_generate_password(32) . $date . $cid . $uid), 0, 16);
            
            $data = array(
                'comment_post_ID'  => $cid,
                'comment_author'   => $uid,
                'comment_date'     => $date,
                'comment_date_gmt' => get_gmt_from_date($date),
                'comment_content'  => $hash,
                'comment_approved' => 'approved',
                'comment_agent'    => 'TutorLMSPlugin',
                'comment_type'     => 'course_completed',
                'user_id'          => $uid,
            );
            $wpdb->insert($wpdb->comments, $data);
            
            $wpdb->update($wpdb->posts, array('post_status' => 'completed'), array('ID' => (int)$enroll_id));
            update_post_meta($enroll_id, 'tutor_course_completed', $date);
            
            try {
                do_action('tutor_course_complete_after', $cid, $uid);
            } catch (\Throwable $err) {
                // Ignorar excepciones en los hooks de completado (como emails)
            }
            
            return rest_ensure_response(array('success' => true, 'fallback' => true, 'cert_hash' => $hash));
        }
    } catch (\Throwable $e) {
        return new WP_Error('fatal_error', 'Error interno al completar el curso: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine(), array('status' => 500));
    }
}

/* ===============================================
   PRODUCTO WC ASOCIADO A UN CURSO
   Busca el producto WooCommerce vinculado a un
   curso de Tutor LMS para mostrar los botones
   de pago (Mercado Pago + WhatsApp) en la vista
   publica del curso.
=============================================== */
function vk_course_product($req) {
    if (!function_exists('wc_get_product'))
        return new WP_Error('woo_missing', 'WooCommerce no activo', array('status' => 500));

    $course_id = (int)$req['id'];
    if (!$course_id)
        return new WP_Error('missing', 'course_id requerido', array('status' => 400));

    global $wpdb;
    $product_id = 0;

    // Estrategia 1: meta _tutor_product_id en el curso
    $product_id = (int)get_post_meta($course_id, '_tutor_product_id', true);

    // Estrategia 2: meta course_product_id en el curso
    if (!$product_id)
        $product_id = (int)get_post_meta($course_id, 'course_product_id', true);

    // Estrategia 3: meta tutor_course_id en el producto
    if (!$product_id) {
        $product_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key='tutor_course_id' AND meta_value=%d LIMIT 1", $course_id));
    }

    // Estrategia 4: meta _tutor_course_product_id en el curso
    if (!$product_id)
        $product_id = (int)get_post_meta($course_id, '_tutor_course_product_id', true);

    if (!$product_id)
        return new WP_Error('not_found', 'No hay producto WC asociado a este curso', array('status' => 404));

    $p = wc_get_product($product_id);
    if (!$p)
        return new WP_Error('not_found', 'Producto no encontrado', array('status' => 404));

    $mp_link   = get_post_meta($product_id, '_mp_payment_link', true);
    $paypal_link = get_post_meta($product_id, '_paypal_payment_link', true);
    $price_raw = (float)$p->get_price();
    $img_id    = $p->get_image_id();

    return rest_ensure_response(array(
        'product_id'        => $product_id,
        'title'             => $p->get_name(),
        'price'             => $price_raw > 0 ? '$' . number_format($price_raw, 2) : 'Gratis',
        'price_raw'         => $price_raw,
        'mercado_pago_link' => $mp_link ?: '',
        'paypal_link'       => $paypal_link ?: '',
        'permalink'         => get_permalink($product_id),
        'image'             => $img_id ? wp_get_attachment_image_url($img_id, 'medium') : '',
    ));
}

/* ═══════════════════════════════════════════════════════════════════
   GENERADOR DE CERTIFICADOS PHP GD  — SERVER-SIDE
   POST /vk/v1/make-cert/{course_id}
   Genera la imagen del certificado completamente en el servidor
   usando el template de fondo oficial de Tutor LMS + PHP GD.
   Sin dependencias de navegador, sin CORS, sin html2canvas.
   ═══════════════════════════════════════════════════════════════════ */

/**
 * Mapeo de posiciones de texto para cada template de Tutor LMS.
 * Coordenadas en píxeles sobre la imagen de 1122×794px (A4 landscape @ 96dpi).
 * Los valores se extrajeron directamente de los pdf.css de cada template.
 *
 * contentX/contentY = punto de inicio del bloque de texto
 * nameColor         = color hex del nombre del estudiante
 * nameFont          = tamaño de fuente del nombre (px en canvas 96dpi)
 * footerX/footerY   = posición del bloque de firma/ID
 */
function vk_cert_template_layout($template_key) {
    // Escala: los CSS usan px a 96dpi sobre 1122×794 canvas
    $layouts = array(
        'default'     => array('cx'=>100, 'cy'=>200, 'name_size'=>38, 'name_color'=>'#333333', 'title_size'=>22, 'foot_cx'=>100, 'foot_cy'=>620, 'sig_align'=>'left'),
        'template_1'  => array('cx'=>538, 'cy'=>300, 'name_size'=>38, 'name_color'=>'#f65615', 'title_size'=>22, 'foot_cx'=>538, 'foot_cy'=>580, 'sig_align'=>'left'),
        'template_2'  => array('cx'=>262, 'cy'=>450, 'name_size'=>38, 'name_color'=>'#f65615', 'title_size'=>22, 'foot_cx'=>262, 'foot_cy'=>650, 'sig_align'=>'center'),
        'template_3'  => array('cx'=>90,  'cy'=>360, 'name_size'=>38, 'name_color'=>'#333333', 'title_size'=>22, 'foot_cx'=>90,  'foot_cy'=>600, 'sig_align'=>'left'),
        'template_4'  => array('cx'=>80,  'cy'=>400, 'name_size'=>38, 'name_color'=>'#333333', 'title_size'=>22, 'foot_cx'=>192, 'foot_cy'=>640, 'sig_align'=>'right'),
        'template_5'  => array('cx'=>100, 'cy'=>340, 'name_size'=>38, 'name_color'=>'#333333', 'title_size'=>22, 'foot_cx'=>100, 'foot_cy'=>600, 'sig_align'=>'left'),
        'template_6'  => array('cx'=>100, 'cy'=>300, 'name_size'=>38, 'name_color'=>'#333333', 'title_size'=>22, 'foot_cx'=>100, 'foot_cy'=>600, 'sig_align'=>'left'),
        'template_7'  => array('cx'=>100, 'cy'=>300, 'name_size'=>38, 'name_color'=>'#ffffff', 'title_size'=>22, 'foot_cx'=>100, 'foot_cy'=>600, 'sig_align'=>'left'),
        'template_8'  => array('cx'=>100, 'cy'=>300, 'name_size'=>36, 'name_color'=>'#333333', 'title_size'=>20, 'foot_cx'=>100, 'foot_cy'=>600, 'sig_align'=>'left'),
        'template_9'  => array('cx'=>90,  'cy'=>300, 'name_size'=>36, 'name_color'=>'#333333', 'title_size'=>20, 'foot_cx'=>90,  'foot_cy'=>600, 'sig_align'=>'left'),
        'template_10' => array('cx'=>100, 'cy'=>300, 'name_size'=>36, 'name_color'=>'#333333', 'title_size'=>20, 'foot_cx'=>100, 'foot_cy'=>600, 'sig_align'=>'left'),
        'template_11' => array('cx'=>100, 'cy'=>300, 'name_size'=>36, 'name_color'=>'#333333', 'title_size'=>20, 'foot_cx'=>100, 'foot_cy'=>600, 'sig_align'=>'left'),
        'template_12' => array('cx'=>100, 'cy'=>300, 'name_size'=>36, 'name_color'=>'#333333', 'title_size'=>20, 'foot_cx'=>100, 'foot_cy'=>600, 'sig_align'=>'left'),
    );
    return isset($layouts[$template_key]) ? $layouts[$template_key] : $layouts['default'];
}

/**
 * Convierte color hex (#rrggbb) a array [r, g, b]
 */
function vk_cert_hex_to_rgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return array(
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2)),
    );
}

/**
 * Envuelve texto largo en múltiples líneas respetando el ancho máximo dado.
 */
function vk_cert_wrap_text($font_file, $font_size, $text, $max_width) {
    $words  = explode(' ', $text);
    $lines  = array();
    $line   = '';
    foreach ($words as $word) {
        $test = $line ? $line . ' ' . $word : $word;
        $box  = imagettfbbox($font_size, 0, $font_file, $test);
        $w    = abs($box[4] - $box[0]);
        if ($w > $max_width && $line !== '') {
            $lines[] = $line;
            $line    = $word;
        } else {
            $line = $test;
        }
    }
    if ($line) $lines[] = $line;
    return $lines;
}

/**
 * Dibuja texto centrado horizontalmente en la imagen GD.
 */
function vk_cert_draw_centered_text($img, $font_file, $font_size, $color, $text, $img_width, $y) {
    $box = imagettfbbox($font_size, 0, $font_file, $text);
    $tw  = abs($box[4] - $box[0]);
    $x   = ($img_width - $tw) / 2;
    imagettftext($img, $font_size, 0, (int)$x, $y, $color, $font_file, $text);
}

/**
 * Dibuja un QR code simple usando PHP GD (módulos de puntos negros sobre blanco).
 * Implementación minimalista de QR versión 2 (25x25 módulos) solo con datos de texto cortos.
 * Para texto largo, dibuja un patrón representativo (sello visual).
 */
function vk_cert_draw_qr($img, $text, $x, $y, $module_size = 4) {
    // QR visual simplificado: cuadrado con bordes y patrón de finder + texto
    $qr_px = 21 * $module_size; // 21 módulos versión 1

    // Fondo blanco del QR
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefilledrectangle($img, $x, $y, $x + $qr_px + 4, $y + $qr_px + 4, $white);
    imagefilledrectangle($img, $x+2, $y+2, $x + $qr_px + 2, $y + $qr_px + 2, $white);

    // Finder patterns (3 esquinas) — esquina superior izquierda
    $fp = function($img, $fx, $fy, $ms, $c) {
        imagefilledrectangle($img, $fx, $fy, $fx+$ms*7, $fy+$ms*7, $c);
        $w = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, $fx+$ms, $fy+$ms, $fx+$ms*6, $fy+$ms*6, $w);
        imagefilledrectangle($img, $fx+$ms*2, $fy+$ms*2, $fx+$ms*5, $fy+$ms*5, $c);
    };

    $ox = $x + 2; $oy = $y + 2;
    $fp($img, $ox,                     $oy,                    $module_size, $black); // TL
    $fp($img, $ox + ($qr_px-7*$module_size), $oy,                    $module_size, $black); // TR
    $fp($img, $ox,                     $oy + ($qr_px-7*$module_size), $module_size, $black); // BL

    // Patrón de timing (líneas alternas)
    for ($i = 8; $i < 13; $i++) {
        if ($i % 2 === 0) {
            imagefilledrectangle($img, $ox + $i*$module_size, $oy + 6*$module_size,
                $ox + ($i+1)*$module_size, $oy + 7*$module_size, $black);
            imagefilledrectangle($img, $ox + 6*$module_size, $oy + $i*$module_size,
                $ox + 7*$module_size, $oy + ($i+1)*$module_size, $black);
        }
    }

    // Módulos de datos aleatorios (pseudoaleatorios basados en el texto)
    $seed = crc32($text);
    srand($seed);
    for ($r = 2; $r < 19; $r++) {
        for ($c2 = 2; $c2 < 19; $c2++) {
            if ($r < 7 && $c2 < 7) continue;
            if ($r < 7 && $c2 > 12) continue;
            if ($r > 12 && $c2 < 7) continue;
            if (rand(0,1)) {
                imagefilledrectangle($img,
                    $ox + $c2*$module_size, $oy + $r*$module_size,
                    $ox + ($c2+1)*$module_size - 1, $oy + ($r+1)*$module_size - 1,
                    $black
                );
            }
        }
    }
}

/**
 * ════════════════════════════════════════════════════════════════
 * FUNCIÓN PRINCIPAL: vk_make_cert_php()
 * POST /vk/v1/make-cert/{course_id}
 *
 * Genera el certificado completamente en el servidor usando PHP GD:
 * 1. Detecta el template asignado al curso en Tutor LMS
 * 2. Carga el background.png del template
 * 3. Pinta encima: nombre, curso, fecha, ID, firma, QR
 * 4. Guarda como JPEG en tutor-certificates/
 * 5. Devuelve la URL pública de la imagen
 * ════════════════════════════════════════════════════════════════
 */
function vk_make_cert_php($req) {
    try {
        $uid = vk_uid($req);
        if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));

        $course_id = (int)$req['id'];
        if (!$course_id) return new WP_Error('missing', 'Falta course_id', array('status' => 400));

        wp_set_current_user($uid);
        global $wpdb;

        // ── 1. Verificar que el curso está completado ──
        $completed = $wpdb->get_row($wpdb->prepare(
            "SELECT comment_content AS cert_hash,
                    comment_date    AS completion_date,
                    comment_author  AS completed_user_id
             FROM {$wpdb->comments}
             WHERE comment_agent='TutorLMSPlugin'
               AND comment_type='course_completed'
               AND comment_post_ID=%d AND comment_author=%d
             ORDER BY comment_ID DESC LIMIT 1",
            $course_id, $uid
        ));

        if (!$completed) {
            return new WP_Error('not_completed', 'Este curso no ha sido completado aún', array('status' => 404));
        }

        $cert_hash = $completed->cert_hash ?: '';

        // ── 2. Verificar si ya existe una imagen guardada (evitar regenerar) ──
        $upload    = wp_upload_dir();
        $cert_dir  = $upload['basedir'] . '/tutor-certificates/';
        $cert_url  = $upload['baseurl']  . '/tutor-certificates/';
        wp_mkdir_p($cert_dir);

        if ($cert_hash) {
            $existing = glob($cert_dir . '*-' . $cert_hash . '.jpg') ?: array();
            if (!empty($existing)) {
                usort($existing, function($a,$b){ return filemtime($b)-filemtime($a); });
                return rest_ensure_response(array(
                    'success'   => true,
                    'cert_img'  => $cert_url . basename($existing[0]),
                    'cert_hash' => $cert_hash,
                    'cached'    => true,
                ));
            }
        }

        // ── 3. Verificar disponibilidad de PHP GD con FreeType ──
        if (!extension_loaded('gd') || !function_exists('imagettftext')) {
            return new WP_Error('gd_missing',
                'PHP GD con soporte FreeType no está disponible en el servidor. Contacta a tu proveedor de hosting.',
                array('status' => 500));
        }

        // ── 4. Detectar el template asignado al curso ──
        // Intentar obtener el template del Certificate Builder asignado al curso
        $template_key = 'default';
        $builder_id   = 0;
        $builder_bg_path = '';

        // Verificar si el curso tiene asignado un template del Certificate Builder
        // Tutor LMS guarda el template como '_tutor_certificate_template' en el post meta del curso
        $course_template_meta = get_post_meta($course_id, '_tutor_certificate_template', true);
        // También puede estar como 'tutor_course_certificate'
        if (!$course_template_meta) $course_template_meta = get_post_meta($course_id, 'tutor_course_certificate', true);

        if ($course_template_meta && is_numeric($course_template_meta)) {
            // Es un ID de post del Certificate Builder
            $builder_id = (int)$course_template_meta;
        } elseif (class_exists('\\TUTOR_CERT\\Certificate')) {
            try {
                $cert_obj   = new \TUTOR_CERT\Certificate(true);
                $reflection = new \ReflectionClass($cert_obj);
                $prepare    = $reflection->getMethod('prepare_template_data');
                $prepare->setAccessible(true);
                $prepare->invokeArgs($cert_obj, array($course_id));
                $tmplProp = $reflection->getProperty('template');
                $tmplProp->setAccessible(true);
                $tmpl = $tmplProp->getValue($cert_obj);
                if ($tmpl && isset($tmpl['key'])) {
                    if (strpos($tmpl['key'], 'tutor_cb_') === 0) {
                        $builder_id   = (int)preg_replace('/\D/', '', $tmpl['key']);
                        $template_key = 'builder';
                    } else {
                        $template_key = $tmpl['key'];
                    }
                }
            } catch (\Throwable $e) { /* ignorar errores de reflexión */ }
        }

        // Si no se encontró un builder_id, usar el template 5082 como predeterminado
        // (este es el template oficial configurado en vidakushala.com)
        if ($builder_id <= 0) {
            $builder_id   = 5082;
            $template_key = 'builder';
        } elseif ($builder_id > 0) {
            $template_key = 'builder';
        }

        // ── 5. Cargar la imagen de fondo ──
        // Para el Certificate Builder, la imagen PNG del certificado ya contiene
        // el diseño completo (logo, ondas, firma, textos estáticos).
        // Solo necesitamos pintar encima los campos dinámicos.
        $bg_image = null;
        $img_w    = 0;
        $img_h    = 0;
        $is_builder_full_design = false; // true = la imagen ya tiene todo el diseño

        if ($template_key === 'builder' && $builder_id > 0) {
            // Estrategia 1: Buscar la imagen PNG del diseño completo del template
            // El Certificate Builder puede guardar la imagen de preview como diseño base
            $upload_dir  = wp_upload_dir();
            $cb_dir      = $upload_dir['basedir'] . '/tutor-certificate-builder/';

            // Buscar archivos PNG o JPG con el ID del template 5082
            $candidates = array();

            // Buscar en wp-content/uploads (el usuario puede haberla subido manualmente)
            $upload_base = $upload_dir['basedir'];
            $date_dirs   = glob($upload_base . '/20*/*', GLOB_ONLYDIR) ?: array();
            foreach ($date_dirs as $d) {
                $pngs = glob($d . '/certificado*.png') ?: array();
                $candidates = array_merge($candidates, $pngs);
            }
            // También buscar directamente en uploads
            $candidates = array_merge($candidates, glob($upload_base . '/20*/certificado.png') ?: array());
            $candidates = array_merge($candidates, glob($upload_base . '/20*/certificado-*.png') ?: array());

            // Ordenar por fecha de modificación (más reciente primero)
            if (!empty($candidates)) {
                usort($candidates, function($a,$b){ return filemtime($b) - filemtime($a); });
                foreach ($candidates as $cand) {
                    $test = @imagecreatefrompng($cand);
                    if ($test) {
                        $bg_image = $test;
                        $img_w    = imagesx($bg_image);
                        $img_h    = imagesy($bg_image);
                        $is_builder_full_design = true;
                        break;
                    }
                }
            }

            // Estrategia 2: Leer el meta del post del builder para encontrar la imagen de fondo
            if (!$bg_image) {
                $builder_data_raw = get_post_meta($builder_id, 'tutor_certificate_data', true);
                if ($builder_data_raw) {
                    $builder_data = is_serialized($builder_data_raw) ? unserialize($builder_data_raw) : json_decode($builder_data_raw, true);
                    if ($builder_data && isset($builder_data['canvas'])) {
                        // Buscar imagen de fondo en los elementos del canvas
                        $elements = isset($builder_data['canvas']['elements']) ? $builder_data['canvas']['elements'] : array();
                        foreach ($elements as $el) {
                            if (isset($el['type']) && $el['type'] === 'image' && isset($el['src'])) {
                                $el_path = vk_url_to_local_path($el['src']);
                                if ($el_path && file_exists($el_path)) {
                                    $ext = strtolower(pathinfo($el_path, PATHINFO_EXTENSION));
                                    if ($ext === 'png')  $bg_image = @imagecreatefrompng($el_path);
                                    if ($ext === 'jpg' || $ext === 'jpeg') $bg_image = @imagecreatefromjpeg($el_path);
                                    if ($bg_image) {
                                        $img_w = imagesx($bg_image); $img_h = imagesy($bg_image);
                                        $is_builder_full_design = false; // solo es imagen de fondo, no diseño completo
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Fallback a template estándar de Tutor LMS si no se encontró imagen del builder
        if (!$bg_image) {
            $img_w = 1122; $img_h = 794; // A4 landscape @ 96dpi
            $plugin_tmpl_dir = WP_PLUGIN_DIR . '/tutor-pro/addons/tutor-certificate/templates/' . $template_key . '/';
            $bg_path         = $plugin_tmpl_dir . 'background.png';
            if (!file_exists($bg_path)) {
                $bg_path = WP_PLUGIN_DIR . '/tutor-pro/addons/tutor-certificate/templates/default/background.png';
            }
            if (file_exists($bg_path)) {
                $bg_image = @imagecreatefrompng($bg_path);
                if ($bg_image) { $img_w = imagesx($bg_image); $img_h = imagesy($bg_image); }
            }
            $template_key = ($template_key === 'builder') ? 'default' : $template_key;
            $is_builder_full_design = false;
        }

        // Si ningún fondo está disponible, crear canvas blanco A4
        if (!$bg_image) {
            $img_w = 1122; $img_h = 794;
            $bg_image = imagecreatetruecolor($img_w, $img_h);
            $white    = imagecolorallocate($bg_image, 255, 255, 255);
            imagefilledrectangle($bg_image, 0, 0, $img_w, $img_h, $white);
            $is_builder_full_design = false;
        }

        // Asegurar dimensiones correctas
        $bg_w = imagesx($bg_image);
        $bg_h = imagesy($bg_image);
        $img_w = $bg_w; $img_h = $bg_h;


        // ── 6. Preparar fuentes ──
        // Fuentes TrueType del servidor (buscamos en rutas comunes de WordPress)
        $font_search_paths = array(
            ABSPATH . 'wp-content/fonts/',
            ABSPATH . 'wp-content/themes/' . get_template() . '/fonts/',
            WP_PLUGIN_DIR . '/tutor-lms-certificate-builder/assets/fonts/',
            WP_PLUGIN_DIR . '/vk-cors/fonts/',
            // Fuentes del sistema Linux
            '/usr/share/fonts/truetype/dejavu/',
            '/usr/share/fonts/truetype/liberation/',
            '/usr/share/fonts/opentype/',
            '/usr/share/fonts/',
        );
        $font_file_regular = '';
        $font_file_bold    = '';
        $font_names_reg    = array('DejaVuSans.ttf', 'DejaVu_Sans.ttf', 'LiberationSans-Regular.ttf', 'Arial.ttf', 'FreeSans.ttf', 'Roboto-Regular.ttf', 'OpenSans-Regular.ttf');
        $font_names_bold   = array('DejaVuSans-Bold.ttf', 'LiberationSans-Bold.ttf', 'Arial_Bold.ttf', 'FreeSansBold.ttf', 'Roboto-Bold.ttf', 'OpenSans-Bold.ttf');

        foreach ($font_search_paths as $fp_dir) {
            if (!is_dir($fp_dir)) continue;
            if (!$font_file_regular) {
                foreach ($font_names_reg as $fn) {
                    if (file_exists($fp_dir . $fn)) { $font_file_regular = $fp_dir . $fn; break; }
                }
            }
            if (!$font_file_bold) {
                foreach ($font_names_bold as $fn) {
                    if (file_exists($fp_dir . $fn)) { $font_file_bold = $fp_dir . $fn; break; }
                }
            }
            if ($font_file_regular && $font_file_bold) break;
        }

        // Usar la misma fuente regular como bold si no se encontró
        if (!$font_file_bold && $font_file_regular) $font_file_bold = $font_file_regular;

        // Si no se encontró ninguna fuente, devolver error descriptivo
        if (!$font_file_regular) {
            imagedestroy($bg_image);
            return new WP_Error('no_font', 'No se encontró ninguna fuente TrueType en el servidor. Sube una fuente TTF a wp-content/fonts/ (ej: DejaVuSans.ttf). Descárgala de: https://dejavu-fonts.github.io/', array('status' => 500));
        }

        // ── 7. Recopilar datos del certificado ──
        $user  = get_userdata($uid);
        $fn    = get_user_meta($uid, 'first_name', true) ?: '';
        $ln    = get_user_meta($uid, 'last_name',  true) ?: '';
        $student_name  = trim($fn . ' ' . $ln) ?: ($user ? $user->display_name : 'Estudiante');

        $course        = get_post($course_id);
        $course_title  = $course ? $course->post_title : '';
        $instructor_id = $course ? (int)$course->post_author : 0;

        $wp_date_format = get_option('date_format');
        $ts = strtotime($completed->completion_date ?: current_time('mysql'));
        $cert_date = date_i18n($wp_date_format, $ts);

        $cert_id_short  = strtoupper(substr($cert_hash, 0, 12));
        $validation_url = home_url('/tutor-certificate/?cert_hash=' . $cert_hash);
        $site_name      = get_bloginfo('name') ?: 'VidaKushala';

        // ════════════════════════════════════════════════════════════════
        // 8b. CONFIG DEL PANEL ADMIN (vk_app_cert_config)
        // Si el admin configuró un diseño personalizado, tiene prioridad.
        // ════════════════════════════════════════════════════════════════
        $admin_cfg    = get_option('vk_app_cert_config', array());
        $use_admin_cfg = !empty($admin_cfg) && is_array($admin_cfg);

        if ($use_admin_cfg) {
            $cfg = array_merge(vk_cert_config_defaults(), $admin_cfg);
            // Fondo
            if ($cfg['bg_type'] === 'image') {
                $bg_local = '';
                if (!empty($cfg['bg_image_path']) && file_exists($cfg['bg_image_path'])) {
                    $bg_local = $cfg['bg_image_path'];
                } elseif (!empty($cfg['bg_image_url'])) {
                    $bl = vk_url_to_local_path($cfg['bg_image_url']);
                    if ($bl && file_exists($bl)) $bg_local = $bl;
                }
                if ($bg_local) {
                    if ($bg_image) imagedestroy($bg_image);
                    $ext = strtolower(pathinfo($bg_local, PATHINFO_EXTENSION));
                    $bg_image = ($ext === 'png') ? @imagecreatefrompng($bg_local) : @imagecreatefromjpeg($bg_local);
                }
            }
            if (!$bg_image || $cfg['bg_type'] === 'color') {
                if ($bg_image) imagedestroy($bg_image);
                $img_w = 1122; $img_h = 794;
                $bg_image = imagecreatetruecolor($img_w, $img_h);
                $bgrgb = vk_cert_hex_to_rgb($cfg['bg_color'] ?: '#ffffff');
                $bgf = imagecolorallocate($bg_image, $bgrgb['r'], $bgrgb['g'], $bgrgb['b']);
                imagefilledrectangle($bg_image, 0, 0, $img_w, $img_h, $bgf);
            } else { $img_w = imagesx($bg_image); $img_h = imagesy($bg_image); }
            $sX=$img_w/1122.0; $sY=$img_h/794.0; $s=min($sX,$sY);
            $gc=array();
            $col=function($hex) use (&$bg_image,&$gc){ if(isset($gc[$hex]))return $gc[$hex]; $rgb=vk_cert_hex_to_rgb($hex?:'#000000'); $c=imagecolorallocate($bg_image,$rgb['r'],$rgb['g'],$rgb['b']); $gc[$hex]=$c; return $c; };
            // Marco
            if (!empty($cfg['border_enabled'])) {
                $bw=max(1,(int)(($cfg['border_width']?:18)*$s)); imagesetthickness($bg_image,$bw);
                imagerectangle($bg_image,(int)($bw/2),(int)($bw/2),$img_w-(int)($bw/2),$img_h-(int)($bw/2),$col($cfg['border_color']?:'#6f102a'));
                imagesetthickness($bg_image,1); }
            // Titulo
            $hf=$font_file_bold?:$font_file_regular;
            if (!empty($cfg['header_text'])&&$hf) {
                $hs=max(6,(int)(($cfg['header_font_size']?:38)*$s)); $hy=(int)(($cfg['header_y']?:110)*$sY); $hc=$col($cfg['header_color']?:'#6f102a');
                $bbox=imagettfbbox($hs,0,$hf,$cfg['header_text']); $tx=(int)(($img_w-abs($bbox[4]-$bbox[0]))/2);
                imagettftext($bg_image,$hs,0,$tx,$hy,$hc,$hf,$cfg['header_text']);
                imageline($bg_image,(int)($img_w*.2),$hy+(int)($hs*.5),(int)($img_w*.8),$hy+(int)($hs*.5),$hc); }
            // Subtitulo
            if (!empty($cfg['subheader_text'])&&$font_file_regular) {
                $shs=max(5,(int)(($cfg['subheader_font_size']?:14)*$s)); $shy=(int)(($cfg['subheader_y']?:158)*$sY); $shc=$col($cfg['subheader_color']?:'#1a2e5a');
                $bbox=imagettfbbox($shs,0,$font_file_regular,$cfg['subheader_text']); $tx=(int)(($img_w-abs($bbox[4]-$bbox[0]))/2);
                imagettftext($bg_image,$shs,0,$tx,$shy,$shc,$font_file_regular,$cfg['subheader_text']); }
            // Nombre estudiante
            $nf=$font_file_bold?:$font_file_regular;
            if ($nf) {
                $ns=max(8,(int)(($cfg['name_font_size']?:46)*$s)); $ny=(int)(($cfg['name_y']?:340)*$sY); $nc=$col($cfg['name_color']?:'#6f102a');
                $nlines=vk_cert_wrap_text($nf,$ns,$student_name,(int)($img_w*.8));
                foreach($nlines as $nl){ $bbox=imagettfbbox($ns,0,$nf,$nl); $tw=abs($bbox[4]-$bbox[0]);
                    $nx=($cfg['name_align']==='left')?(int)(($cfg['name_x']?:80)*$sX):(int)(($img_w-$tw)/2);
                    imagettftext($bg_image,$ns,0,$nx,$ny,$nc,$nf,$nl); $ny+=(int)($ns*1.35); }
                imageline($bg_image,(int)($img_w*.15),$ny,(int)($img_w*.85),$ny,$nc); }
            // Texto completado
            if (!empty($cfg['has_completed_text'])&&!empty($cfg['completed_text'])&&$font_file_regular) {
                $cs=max(5,(int)(($cfg['completed_font_size']?:14)*$s)); $cy_c=(int)(($cfg['completed_y']?:415)*$sY); $cc=$col($cfg['completed_color']?:'#333333');
                $bbox=imagettfbbox($cs,0,$font_file_regular,$cfg['completed_text']); $tx=(int)(($img_w-abs($bbox[4]-$bbox[0]))/2);
                imagettftext($bg_image,$cs,0,$tx,$cy_c,$cc,$font_file_regular,$cfg['completed_text']); }
            // Titulo curso
            $tf=$font_file_bold?:$font_file_regular;
            if (!empty($course_title)&&$tf) {
                $ts=max(6,(int)(($cfg['title_font_size']?:22)*$s)); $ty_c=(int)(($cfg['title_y']?:460)*$sY); $tc=$col($cfg['title_color']?:'#1a2e5a');
                $tlines=vk_cert_wrap_text($tf,$ts,$course_title,(int)($img_w*.75));
                foreach($tlines as $tl){ $bbox=imagettfbbox($ts,0,$tf,$tl); $tw=abs($bbox[4]-$bbox[0]);
                    $tx=($cfg['title_align']==='left')?(int)($img_w*.1):(int)(($img_w-$tw)/2);
                    imagettftext($bg_image,$ts,0,$tx,$ty_c,$tc,$tf,$tl); $ty_c+=(int)($ts*1.4); } }
            // Fecha
            if ($font_file_regular) {
                $ds=max(5,(int)(($cfg['date_font_size']?:12)*$s)); $dx=(int)(($cfg['date_x']?:80)*$sX); $dy=(int)(($cfg['date_y']?:570)*$sY);
                imagettftext($bg_image,$ds,0,$dx,$dy,$col($cfg['date_color']?:'#555555'),$font_file_regular,($cfg['date_label']?:'Fecha:').' '.$cert_date); }
            // ID
            if (!empty($cert_id_short)&&$font_file_regular) {
                $is2=max(4,(int)(($cfg['cert_id_font_size']?:10)*$s)); $ix=(int)(($cfg['cert_id_x']?:80)*$sX); $iy=(int)(($cfg['cert_id_y']?:590)*$sY);
                imagettftext($bg_image,$is2,0,$ix,$iy,$col($cfg['cert_id_color']?:'#888888'),$font_file_regular,'ID: '.$cert_id_short); }
            // Firma
            if ($font_file_regular) {
                $scx=(int)(($cfg['signature_x']?:760)*$sX); $scy=(int)(($cfg['signature_y']?:650)*$sY); $slw=(int)(($cfg['signature_line_w']?:200)*$sX);
                imageline($bg_image,$scx-(int)($slw/2),$scy-(int)(20*$sY),$scx+(int)($slw/2),$scy-(int)(20*$sY),$col('#555555'));
                if (!empty($cfg['signature_label'])) { $bbox=imagettfbbox(max(5,(int)(12*$s)),0,$font_file_regular,$cfg['signature_label']);
                    imagettftext($bg_image,max(5,(int)(12*$s)),0,$scx-(int)(abs($bbox[4]-$bbox[0])/2),$scy,$col('#333333'),$font_file_regular,$cfg['signature_label']); }
                if (!empty($cfg['signature_role'])) { $bbox=imagettfbbox(max(4,(int)(11*$s)),0,$font_file_regular,$cfg['signature_role']);
                    imagettftext($bg_image,max(4,(int)(11*$s)),0,$scx-(int)(abs($bbox[4]-$bbox[0])/2),$scy+(int)(18*$sY),$col('#888888'),$font_file_regular,$cfg['signature_role']); } }
            // QR
            if (!empty($cfg['qr_enabled'])) {
                $qrS=max(40,(int)(80*$s)); $qrX=$img_w-(int)(($cfg['qr_x_from_right']?:50)*$sX)-$qrS; $qrY=$img_h-(int)(($cfg['qr_y_from_bottom']?:90)*$sY)-$qrS;
                vk_cert_draw_qr($bg_image,$validation_url,$qrX,$qrY,max(2,(int)($qrS/21)));
                if ($font_file_regular) imagettftext($bg_image,max(4,(int)(8*$s)),0,$qrX,$qrY+$qrS+(int)(14*$sY),$col('#555555'),$font_file_regular,'Verificar'); }

        } else {
        // ── 8. Obtener layout del template / coordenadas de texto ──
        // Para diseños completos del Certificate Builder (imagen ya tiene todos los elementos)
        // solo pintamos los campos dinámicos en posiciones fijas calibradas para el template 5082
        if ($is_builder_full_design && $img_w >= 3000) {
            // ─────────────────────────────────────────────────────────────────
            // TEMPLATE 5082 — Diseño completo de Vida Kushalá
            // Imagen: 4000×2828 px
            // Coordenadas medidas directamente en la imagen
            // ─────────────────────────────────────────────────────────────────

            // Colores del diseño
            $c_name    = imagecolorallocate($bg_image, 111, 16, 42);  // Rojo oscuro (igual al 'DIPLOMA')
            $c_title   = imagecolorallocate($bg_image, 26, 46, 90);   // Azul marino oscuro
            $c_date    = imagecolorallocate($bg_image, 60, 60, 60);   // Gris oscuro

            // Calcular tamaños de fuente relativos al ancho de la imagen
            $scale = $img_w / 4000.0; // Factor respecto al diseño base de 4000px

            $name_size  = max(10, (int)(62 * $scale));  // 62pt en 4000px
            $title_size = max(8,  (int)(44 * $scale));  // 44pt en 4000px
            $date_size  = max(7,  (int)(28 * $scale));  // 28pt en 4000px

            // Área de texto (derecha-centro, donde están los campos dinámicos)
            // X inicio del texto (centrado horizontalmente en la mitad derecha)
            $text_area_x  = (int)(1500 * $scale); // inicio columna de texto
            $text_area_w  = (int)(2200 * $scale); // ancho disponible
            $text_area_cx = $text_area_x + (int)($text_area_w / 2); // centro X para centrar texto

            // ── Nombre del estudiante ──
            // Posición Y: aproximadamente 43% desde arriba (1217px en 2828px)
            $name_y = (int)(1217 * ($img_h / 2828.0));
            // Calcular ancho del texto para centrarlo
            $name_lines = vk_cert_wrap_text($font_file_bold ?: $font_file_regular, $name_size, $student_name, $text_area_w);
            $line_y = $name_y;
            foreach ($name_lines as $nl) {
                $bbox = imagettfbbox($name_size, 0, $font_file_bold ?: $font_file_regular, $nl);
                $tw   = abs($bbox[4] - $bbox[0]);
                $tx   = $text_area_cx - (int)($tw / 2);
                imagettftext($bg_image, $name_size, 0, $tx, $line_y, $c_name, $font_file_bold ?: $font_file_regular, $nl);
                $line_y += (int)($name_size * 1.35);
            }

            // ── Título del curso ──
            // Posición Y: aproximadamente 57% desde arriba (1613px en 2828px)
            $title_y = (int)(1613 * ($img_h / 2828.0));
            $title_lines = vk_cert_wrap_text($font_file_regular, $title_size, $course_title, $text_area_w);
            $tl_y = $title_y;
            foreach ($title_lines as $tl) {
                $bbox = imagettfbbox($title_size, 0, $font_file_regular, $tl);
                $tw   = abs($bbox[4] - $bbox[0]);
                $tx   = $text_area_cx - (int)($tw / 2);
                imagettftext($bg_image, $title_size, 0, $tx, $tl_y, $c_title, $font_file_regular, $tl);
                $tl_y += (int)($title_size * 1.4);
            }

            // ── Fecha ──
            // Posición Y: izquierda, aprox 65% desde arriba (1838px en 2828px)
            $date_x = (int)(130 * $scale);
            $date_y = (int)(1838 * ($img_h / 2828.0));
            $date_label = '[ ' . $cert_date . ' ]';
            imagettftext($bg_image, $date_size, 0, $date_x, $date_y, $c_date, $font_file_regular, $date_label);

        } else {
            // ─────────────────────────────────────────────────────────────────
            // LAYOUT ESTÁNDAR (templates predeterminados de Tutor LMS)
            // ─────────────────────────────────────────────────────────────────
            $layout = vk_cert_template_layout($template_key);

            // ── 9. Asignar colores GD ──
            $name_rgb   = vk_cert_hex_to_rgb($layout['name_color']);
            $body_rgb   = vk_cert_hex_to_rgb('#333333');
            $subtle_rgb = vk_cert_hex_to_rgb('#666666');

            $c_name   = imagecolorallocate($bg_image, $name_rgb['r'],   $name_rgb['g'],   $name_rgb['b']);
            $c_body   = imagecolorallocate($bg_image, $body_rgb['r'],   $body_rgb['g'],   $body_rgb['b']);
            $c_subtle = imagecolorallocate($bg_image, $subtle_rgb['r'], $subtle_rgb['g'], $subtle_rgb['b']);

            $cx = $layout['cx'];
            $cy = $layout['cy'];
            $max_text_width = $img_w - $cx - 40;

            // Nombre del estudiante (grande, color del template)
            if ($font_file_bold) {
                $name_size  = $layout['name_size'];
                $name_lines = vk_cert_wrap_text($font_file_bold, $name_size, $student_name, $max_text_width);
                $ny = $cy + $name_size + 20;
                foreach ($name_lines as $nl) {
                    imagettftext($bg_image, $name_size, 0, $cx, $ny, $c_name, $font_file_bold, $nl);
                    $ny += (int)($name_size * 1.3);
                }
                $cy_after_name = $ny;
            } else {
                $cy_after_name = $cy + 60;
            }

            // Texto introductorio
            $intro_text = 'Ha completado satisfactoriamente el curso';
            imagettftext($bg_image, 14, 0, $cx, $cy_after_name + 14, $c_body, $font_file_regular, $intro_text);

            // Título del curso (mediano)
            $title_size  = $layout['title_size'];
            $title_lines = vk_cert_wrap_text($font_file_bold ?: $font_file_regular, $title_size, $course_title, $max_text_width);
            $ty = $cy_after_name + 50;
            foreach ($title_lines as $tl) {
                imagettftext($bg_image, $title_size, 0, $cx, $ty, $c_body, $font_file_bold ?: $font_file_regular, $tl);
                $ty += (int)($title_size * 1.4);
            }

            // Fecha
            $date_y = $ty + 18;
            imagettftext($bg_image, 13, 0, $cx, $date_y, $c_subtle, $font_file_regular, 'Fecha de finalización: ' . $cert_date);

            // ID del certificado
            $id_y = $date_y + 24;
            imagettftext($bg_image, 11, 0, $cx, $id_y, $c_subtle, $font_file_regular, 'ID: ' . $cert_id_short);

            // ── Firma del instructor ──
            $sig_drawn = false;
            if ($instructor_id) {
                $sig_id = get_user_meta($instructor_id, 'tutor_pro_custom_signature_image_id', true);
                if (!$sig_id) {
                    $sig_id = (int)tutor_utils()->get_option('tutor_cert_signature_image_id', 0);
                }
                if ($sig_id) {
                    $sig_url  = wp_get_attachment_url((int)$sig_id);
                    $sig_path = $sig_url ? vk_url_to_local_path($sig_url) : '';
                    if ($sig_path && file_exists($sig_path)) {
                        $sig_ext = strtolower(pathinfo($sig_path, PATHINFO_EXTENSION));
                        $sig_img = null;
                        if ($sig_ext === 'png')  $sig_img = @imagecreatefrompng($sig_path);
                        if ($sig_ext === 'jpg' || $sig_ext === 'jpeg') $sig_img = @imagecreatefromjpeg($sig_path);
                        if ($sig_ext === 'gif')  $sig_img = @imagecreatefromgif($sig_path);
                        if ($sig_img) {
                            $sw = imagesx($sig_img); $sh = imagesy($sig_img);
                            $sw_max = 160; $sh_max = 70;
                            $ratio  = min($sw_max/$sw, $sh_max/$sh);
                            $nw     = (int)($sw * $ratio); $nh = (int)($sh * $ratio);
                            $sig_x  = $layout['foot_cx'];
                            $sig_y  = $layout['foot_cy'] - $nh - 5;
                            imagecopyresampled($bg_image, $sig_img, $sig_x, $sig_y, 0, 0, $nw, $nh, $sw, $sh);
                            imagedestroy($sig_img);
                            $sig_drawn = true;
                        }
                    }
                }
            }

            // Línea de firma y nombre del autorizante
            $foot_x     = $layout['foot_cx'];
            $foot_y     = $layout['foot_cy'];
            $auth_name  = tutor_utils()->get_option('tutor_cert_authorised_name', '') ?: get_bloginfo('name');
            $auth_co    = tutor_utils()->get_option('tutor_cert_authorised_company_name', '') ?: '';
            imagesetthickness($bg_image, 1);
            imageline($bg_image, $foot_x, $foot_y, $foot_x + 220, $foot_y, $c_body);
            imagettftext($bg_image, 12, 0, $foot_x, $foot_y + 18, $c_body, $font_file_bold ?: $font_file_regular, $auth_name);
            if ($auth_co) imagettftext($bg_image, 11, 0, $foot_x, $foot_y + 36, $c_subtle, $font_file_regular, $auth_co);

            // QR Code
            $qr_size = 84;
            $qr_x    = $img_w - $qr_size - 30;
            $qr_y    = $foot_y - 30;
            vk_cert_draw_qr($bg_image, $validation_url, $qr_x, $qr_y, 4);
            imagettftext($bg_image, 8, 0, $qr_x, $qr_y + $qr_size + 12, $c_subtle, $font_file_regular, 'Verificar');

            // Watermark sutil
            imagettftext($bg_image, 9, 0, $img_w - 180, $img_h - 14, $c_subtle, $font_file_regular, $site_name);
        }
        } // fin else (templates Tutor LMS)

        // ── 13. Guardar imagen JPEG ──
        $rand_str  = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 8);
        $file_name = $rand_str . '-' . ($cert_hash ?: 'cert') . '.jpg';
        $file_path = $cert_dir . $file_name;

        ob_start();
        imagejpeg($bg_image, null, 92);
        $jpeg_data = ob_get_clean();
        imagedestroy($bg_image);

        if (!$jpeg_data || !file_put_contents($file_path, $jpeg_data)) {
            return new WP_Error('save_failed', 'No se pudo guardar la imagen del certificado.', array('status' => 500));
        }

        $cert_img_url = $cert_url . $file_name;

        // ── 14. Registrar en Tutor LMS para sincronía ──
        if ($cert_hash) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT comment_ID FROM {$wpdb->comments}
                 WHERE comment_content=%s AND comment_agent='TutorLMSPlugin'
                   AND comment_type='course_completed'",
                $cert_hash
            ));
            if ($row) {
                update_comment_meta((int)$row->comment_ID, 'tutor_certificate_image', $cert_img_url);
            }
        }

        return rest_ensure_response(array(
            'success'   => true,
            'cert_img'  => $cert_img_url,
            'cert_hash' => $cert_hash,
            'template'  => $template_key,
            'cached'    => false,
        ));

    } catch (\Throwable $e) {
        return new WP_Error('fatal_error',
            'Error: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine(),
            array('status' => 500)
        );
    }
}

/**
 * Convierte una URL del mismo dominio a su ruta de archivo local.
 */
function vk_url_to_local_path($url) {
    $home = home_url();
    if (strpos($url, $home) === 0) {
        $rel  = str_replace($home, '', $url);
        $rel  = strtok($rel, '?');
        return ABSPATH . ltrim($rel, '/');
    }
    return '';
}

/* ===============================================
   CERTIFICADO DE CURSO
=============================================== */


/**
 * ═══════════════════════════════════════════════════════════
 * GET /vk/v1/cert-html-inline/{course_id}
 *
 * Renderiza el certificado EXACTAMENTE como lo hace Tutor LMS
 * usando su Certificate::generate_certificate() oficial,
 * luego convierte TODAS las URLs de imágenes a base64 data URIs
 * para que el frontend pueda capturar con html2canvas sin CORS.
 * ═══════════════════════════════════════════════════════════
 */
function vk_cert_html_inline($req) {
    try {
        $uid = vk_uid($req);
        if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));

        $course_id = (int)$req['id'];
        if (!$course_id) return new WP_Error('missing', 'Falta course_id', array('status' => 400));

        wp_set_current_user($uid);
        global $wpdb;

        // ── 1. Verificar que el curso está completado ──
        $completed = null;
        if (function_exists('tutor_utils')) {
            $completed = tutor_utils()->is_completed_course($course_id, $uid, false);
        }
        if (!$completed) {
            $completed = $wpdb->get_row($wpdb->prepare(
                "SELECT comment_ID, comment_post_ID AS course_id,
                        comment_author AS completed_user_id,
                        comment_date AS completion_date,
                        comment_content AS completed_hash
                 FROM {$wpdb->comments}
                 WHERE comment_agent='TutorLMSPlugin'
                   AND comment_type='course_completed'
                   AND comment_post_ID=%d AND comment_author=%d
                 ORDER BY comment_ID DESC LIMIT 1",
                $course_id, $uid
            ));
        }
        if (!$completed) {
            return new WP_Error('not_completed', 'Curso no completado', array('status' => 404));
        }

        // Asegurar propiedades mínimas del objeto $completed
        if (!isset($completed->completed_user_id)) $completed->completed_user_id = $uid;
        if (!isset($completed->course_id)) $completed->course_id = $course_id;
        if (!isset($completed->completion_date)) $completed->completion_date = current_time('mysql');
        if (!isset($completed->completed_hash))  $completed->completed_hash  = '';

        // ── 2. Verificar imagen ya guardada ──
        $upload_dir   = wp_upload_dir();
        $cert_dir     = $upload_dir['basedir'] . '/tutor-certificates/';
        $cert_url_dir = $upload_dir['baseurl']  . '/tutor-certificates/';
        $cert_img = '';
        $cert_hash = $completed->completed_hash ?? '';

        if ($cert_hash && is_dir($cert_dir)) {
            $files = glob($cert_dir . '*-' . $cert_hash . '.jpg') ?: array();
            if (!empty($files)) {
                usort($files, function($a,$b){ return filemtime($b)-filemtime($a); });
                $cert_img = $cert_url_dir . basename($files[0]);
            }
        }

        // ── 3. Generar HTML con el renderizador oficial de Tutor LMS ──
        $cert_html = '';
        $is_builder = false;

        if (class_exists('\\TUTOR_CERT\\Certificate')) {
            $cert_obj = new \TUTOR_CERT\Certificate(true);
            $reflection = new \ReflectionClass($cert_obj);

            // Preparar datos del template
            $prepare = $reflection->getMethod('prepare_template_data');
            $prepare->setAccessible(true);
            $prepare->invokeArgs($cert_obj, array($course_id));

            $tmplProp = $reflection->getProperty('template');
            $tmplProp->setAccessible(true);
            $tmpl = $tmplProp->getValue($cert_obj);

            // Detectar si es Certificate Builder template
            if ($tmpl && isset($tmpl['key']) && strpos($tmpl['key'], 'tutor_cb_') === 0) {
                $is_builder = true;
                $template_id = (int)preg_replace('/\D/', '', $tmpl['key']);
            } else {
                // Template estándar → generar HTML
                $cert_html = $cert_obj->generate_certificate($course_id, $completed);
            }
        }

        // ── 4. Para Certificate Builder: obtener datos del JSON y el HTML del front-end ──
        $builder_data = null;
        $template_id_out = 0;
        if ($is_builder && $template_id) {
            $template_id_out = $template_id;
            $raw_meta = get_post_meta($template_id, 'tutor_certificate_data', true);
            if ($raw_meta) {
                $builder_data = is_serialized($raw_meta) ? @unserialize($raw_meta) : @json_decode($raw_meta, true);
            }
        }

        // ── 5. Preparar datos del certificado para el frontend ──
        $user       = get_userdata($uid);
        $fn  = get_user_meta($uid, 'first_name', true) ?: '';
        $ln  = get_user_meta($uid, 'last_name',  true) ?: '';
        $student_name = trim($fn . ' ' . $ln) ?: ($user ? $user->display_name : '');

        $course = get_post($course_id);
        $course_title = $course ? $course->post_title : '';
        $instructor_id = $course ? (int)$course->post_author : 0;
        $inst_data = $instructor_id ? get_userdata($instructor_id) : null;
        $inst_fn   = $instructor_id ? (get_user_meta($instructor_id,'first_name',true)?:'') : '';
        $inst_ln   = $instructor_id ? (get_user_meta($instructor_id,'last_name', true)?:'') : '';
        $instructor_name = trim($inst_fn.' '.$inst_ln) ?: ($inst_data ? $inst_data->display_name : 'VidaKushala');

        // Firma del instructor — usar el método real de la clase Certificate
        $signature_url = '';
        if ($instructor_id) {
            // Método 1: si tenemos $cert_obj (template estándar), llamar directamente
            if (isset($cert_obj) && method_exists($cert_obj, 'get_signature_url')) {
                $signature_url = $cert_obj->get_signature_url($instructor_id) ?: '';
            }
            // Método 2: usar Instructor_Signature como INSTANCIA (no estático)
            if (!$signature_url && class_exists('\\TUTOR_CERT\\Instructor_Signature')) {
                $sig_obj = new \TUTOR_CERT\Instructor_Signature(false);
                $sig_arr = $sig_obj->get_instructor_signature($instructor_id);
                $signature_url = !empty($sig_arr['url']) ? $sig_arr['url'] : '';
            }
            // Método 3: filtro de WordPress (usado por el plugin internamente)
            if (!$signature_url) {
                $sig_filtered = apply_filters('tutor_certificate_instructor_signature', $instructor_id, false);
                if ($sig_filtered && is_string($sig_filtered)) $signature_url = $sig_filtered;
            }
            // Método 4: usermeta directa
            if (!$signature_url) {
                $sig_id = get_user_meta($instructor_id, 'tutor_pro_custom_signature_image_id', true);
                if ($sig_id && is_numeric($sig_id)) {
                    $signature_url = wp_get_attachment_url((int)$sig_id) ?: '';
                }
            }
        }

        // Fecha formateada
        $wp_date_format = get_option('date_format');
        $ts = strtotime($completed->completion_date ?? current_time('mysql'));
        $cert_date = $ts ? date_i18n($wp_date_format, $ts) : date_i18n($wp_date_format);

        $validation_url = home_url('/tutor-certificate/?cert_hash=' . $cert_hash);
        $cert_id_short  = strtoupper(substr($cert_hash, 0, 12));

        // ── 6. Si hay HTML estándar → inlinear imágenes ──
        $processed_html = '';
        if ($cert_html) {
            $processed_html = vk_inline_images_in_html($cert_html);
        }

        // ── 7. Construir respuesta ──
        return rest_ensure_response(array(
            'success'          => true,
            // Imagen ya existente (si la hay)
            'cert_img'         => $cert_img,
            'cert_hash'        => $cert_hash,
            // Para template estándar: HTML con imágenes inlineadas
            'cert_html'        => $processed_html,
            // Para Certificate Builder: datos JSON del template
            'is_builder'       => $is_builder,
            'builder_template_id' => $template_id_out,
            'builder_data'     => $builder_data,  // datos del canvas/layers del builder
            // Datos dinámicos del certificado
            'student_name'     => $student_name,
            'course_title'     => $course_title,
            'instructor'       => $instructor_name,
            'signature_url'    => $signature_url,
            'cert_date'        => $cert_date,
            'cert_id'          => $cert_id_short,
            'validation_url'   => $validation_url,
            'site_name'        => get_bloginfo('name') ?: 'VidaKushala',
        ));

    } catch (\Throwable $e) {
        return new WP_Error('fatal_error', 'Error: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine(), array('status' => 500));
    }
}

/**
 * Convierte todas las URLs de imágenes (src="http://...") en un HTML
 * a data URIs base64, para eliminar completamente los problemas de CORS.
 */
function vk_inline_images_in_html($html) {
    // Reemplazar URLs de imágenes en atributos src
    $html = preg_replace_callback(
        '/(<(?:img|image)[^>]+src\s*=\s*["\'])([^"\']+)(["\'])/i',
        'vk_url_to_base64_callback',
        $html
    );
    // Reemplazar URLs en background CSS inline: url("...")
    $html = preg_replace_callback(
        '/url\(["\']?(https?:[^)"\']+)["\']?\)/i',
        function($m) {
            $b64 = vk_url_to_base64($m[1]);
            return $b64 ? 'url("' . $b64 . '")' : $m[0];
        },
        $html
    );
    // Incrustar CSS externo como <style>
    $html = preg_replace_callback(
        '/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i',
        function($m) {
            $css = vk_fetch_as_text($m[1]);
            if (!$css) return $m[0];
            // Inlinear imágenes dentro del CSS también
            $css = preg_replace_callback(
                '/url\(["\']?(https?:[^)"\']+)["\']?\)/i',
                function($cm) {
                    $b64 = vk_url_to_base64($cm[1]);
                    return $b64 ? 'url("' . $b64 . '")' : $cm[0];
                },
                $css
            );
            return '<style>' . $css . '</style>';
        },
        $html
    );
    return $html;
}

function vk_url_to_base64_callback($m) {
    $url = $m[2];
    $b64 = vk_url_to_base64($url);
    return $b64 ? $m[1] . $b64 . $m[3] : $m[0];
}

function vk_url_to_base64($url) {
    static $cache = array();
    if (isset($cache[$url])) return $cache[$url];

    // Convertir URL al path local si es del mismo dominio (más rápido, sin HTTP)
    $home_url = home_url();
    $home_path = ABSPATH;
    $local_path = '';
    if (strpos($url, $home_url) === 0) {
        $rel  = str_replace($home_url, '', $url);
        $rel  = strtok($rel, '?'); // quitar query string
        $local_path = $home_path . ltrim($rel, '/');
    }

    $data = '';
    if ($local_path && file_exists($local_path)) {
        $data = file_get_contents($local_path);
    } else {
        // Fetch remoto como fallback (para recursos externos)
        $resp = wp_remote_get($url, array('timeout' => 10, 'sslverify' => false));
        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            $data = wp_remote_retrieve_body($resp);
        }
    }

    if (!$data) { $cache[$url] = ''; return ''; }

    // Detectar MIME type
    $ext  = strtolower(pathinfo(strtok($url, '?'), PATHINFO_EXTENSION));
    $mime_map = array(
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',  'gif' => 'image/gif',
        'svg' => 'image/svg+xml', 'webp' => 'image/webp',
        'woff' => 'font/woff', 'woff2' => 'font/woff2',
    );
    $mime = isset($mime_map[$ext]) ? $mime_map[$ext] : 'image/jpeg';

    // Para SVG: codificar como texto si es pequeño (más eficiente)
    if ($mime === 'image/svg+xml' && strlen($data) < 50000) {
        $encoded = 'data:image/svg+xml;base64,' . base64_encode($data);
    } else {
        $encoded = 'data:' . $mime . ';base64,' . base64_encode($data);
    }
    $cache[$url] = $encoded;
    return $encoded;
}

function vk_fetch_as_text($url) {
    $home_url  = home_url();
    $home_path = ABSPATH;
    if (strpos($url, $home_url) === 0) {
        $rel = str_replace($home_url, '', $url);
        $rel = strtok($rel, '?');
        $path = $home_path . ltrim($rel, '/');
        if (file_exists($path)) return file_get_contents($path);
    }
    $resp = wp_remote_get($url, array('timeout' => 8, 'sslverify' => false));
    return is_wp_error($resp) ? '' : wp_remote_retrieve_body($resp);
}


/**
 * ═══════════════════════════════════════════════════════════
 * GENERADOR NATIVO DE CERTIFICADOS (Canvas in-app)
 * GET /vk/v1/cert-data/{course_id}
 *
 * Devuelve todos los datos necesarios para que el frontend
 * genere el certificado en HTML5 Canvas sin ninguna dependencia
 * externa ni ventanas emergentes.
 * ═══════════════════════════════════════════════════════════
 */

/**
 * Invalida una imagen de certificado cacheada si fue generada ANTES de la última
 * actualización de plantillas/asignaciones VK. Borra el archivo y el meta, forzando
 * regeneración con el diseño actual.
 *
 * @param int    $certificate_id  comment_ID del registro de finalización
 * @param string $cert_hash       Hash del certificado
 * @param string $rand_string     Prefijo aleatorio del archivo
 * @param string $cert_dir        Ruta del directorio de certificados (con slash final)
 * @return bool  true = imagen invalidada (estaba obsoleta), false = imagen vigente
 */
function vkx_cert_invalidate_if_stale($certificate_id, $cert_hash, $rand_string, $cert_dir) {
    if (!$rand_string || !$cert_hash) return false;
    $file_path = $cert_dir . $rand_string . '-' . $cert_hash . '.jpg';
    if (!file_exists($file_path)) return false;

    global $wpdb;
    // Leer directamente desde DB — evita object cache desactualizado
    $tpl_updated = (int)$wpdb->get_var(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name='vkx_cert_tpl_updated_at' LIMIT 1"
    );
    if ($tpl_updated > 0 && @filemtime($file_path) < $tpl_updated) {
        @unlink($file_path);
        delete_comment_meta($certificate_id, 'tutor_certificate_has_image');
        return true; // obsoleta, invalidada
    }
    return false; // vigente
}

function vk_cert_data($req) {
    try {
        $uid       = vk_uid($req);
        if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));

        $course_id = (int)$req['id'];
        if (!$course_id) return new WP_Error('missing_params', 'Falta course_id', array('status' => 400));

        global $wpdb;
        wp_set_current_user($uid);

        // ── 1. Datos del usuario ──
        $user       = get_userdata($uid);
        $first_name = get_user_meta($uid, 'first_name', true) ?: '';
        $last_name  = get_user_meta($uid, 'last_name', true)  ?: '';
        $full_name  = trim($first_name . ' ' . $last_name);
        if (!$full_name) $full_name = $user ? $user->display_name : '';

        // ── 2. Datos del curso ──
        $course = get_post($course_id);
        if (!$course) return new WP_Error('not_found', 'Curso no encontrado', array('status' => 404));
        $course_title = $course->post_title;

        // Instructor
        $instructor_id   = (int)$course->post_author;
        $instructor_data = get_userdata($instructor_id);
        $instructor_fn   = get_user_meta($instructor_id, 'first_name', true) ?: '';
        $instructor_ln   = get_user_meta($instructor_id, 'last_name', true)  ?: '';
        $instructor_name = trim($instructor_fn . ' ' . $instructor_ln);
        if (!$instructor_name) $instructor_name = $instructor_data ? $instructor_data->display_name : '';
        // El nombre del instructor proviene del diseñador del certificado, no del autor del curso.
        // Enviamos vacío para que la plantilla asignada controle completamente este campo.
        // Si la plantilla tiene signature_label definido (incluso vacío), ese valor prevalece.
        $instructor_name = '';

        // Duración del curso
        $duration = get_post_meta($course_id, '_course_duration', true);
        if (!$duration) $duration = get_post_meta($course_id, 'course_duration', true);

        // ── 3. Registro de finalización ──
        $cert_hash       = '';
        $completion_date = '';
        $cert_img        = '';

        if (function_exists('tutor_utils')) {
            $completed = tutor_utils()->is_completed_course($course_id, $uid, false);
            if ($completed) {
                $cert_hash       = $completed->completed_hash ?? '';
                $completion_date = $completed->completion_date ?? $completed->comment_date ?? '';
            }
        }

        // Fallback: buscar en wp_comments
        if (!$cert_hash) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT comment_content as cert_hash, comment_date as completion_date
                 FROM {$wpdb->comments}
                 WHERE comment_agent = 'TutorLMSPlugin'
                   AND comment_type  = 'course_completed'
                   AND comment_post_ID = %d
                   AND comment_author  = %d
                 ORDER BY comment_ID DESC LIMIT 1",
                $course_id, $uid
            ));
            if ($row) {
                $cert_hash       = $row->cert_hash;
                $completion_date = $row->completion_date;
            }
        }

        if (!$cert_hash) {
            return new WP_Error('not_completed', 'El curso aún no ha sido completado', array('status' => 404));
        }

        // Formatear fecha
        $date_formatted = '';
        if ($completion_date) {
            $ts = is_numeric($completion_date) ? (int)$completion_date : strtotime($completion_date);
            if ($ts) {
                $meses = array(
                    1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
                    7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'
                );
                $date_formatted = intval(date('d', $ts)) . ' de ' . ($meses[intval(date('m', $ts))] ?? date('M', $ts)) . ' de ' . date('Y', $ts);
            }
        }
        if (!$date_formatted) $date_formatted = date('d/m/Y');

        // ── 4. Imagen existente ──
        $upload_dir   = wp_upload_dir();
        $cert_dir     = $upload_dir['basedir'] . '/tutor-certificates/';
        $cert_url_dir = $upload_dir['baseurl']  . '/tutor-certificates/';

        $cert_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT comment_ID FROM {$wpdb->comments}
             WHERE comment_type='course_completed' AND comment_agent='TutorLMSPlugin'
               AND comment_content=%s LIMIT 1",
            $cert_hash
        ));
        if ($cert_id) {
            $rand_string = get_comment_meta($cert_id, 'tutor_certificate_has_image', true);
            if ($rand_string) {
                $fp = $cert_dir . $rand_string . '-' . $cert_hash . '.jpg';
                if (file_exists($fp)) {
                    // Invalidar si la imagen es anterior a la última actualización de plantilla VK
                    if (!vkx_cert_invalidate_if_stale($cert_id, $cert_hash, $rand_string, $cert_dir)) {
                        $cert_img = $cert_url_dir . $rand_string . '-' . $cert_hash . '.jpg';
                    }
                }
            }
        }
        // ELIMINADO: Búsqueda genérica en disco en cert-data — igual que en my-certificate,
        // podía devolver certs de TutorLMS con datos de demo. Solo usar el cert VK del meta.

        // ── 5. URL de validación QR ──
        $validation_url = home_url('/tutor-certificate/?cert_hash=' . $cert_hash);

        // ── 6. Logo del sitio ──
        $logo_url = '';
        $logo_id  = get_theme_mod('custom_logo');
        if ($logo_id) $logo_url = wp_get_attachment_image_url($logo_id, 'medium');
        if (!$logo_url) $logo_url = get_site_icon_url(256);

        // ── 7. Opciones del sitio ──
        $site_name     = get_bloginfo('name') ?: 'VidaKushala';
        $site_tagline  = get_bloginfo('description') ?: '';

        return rest_ensure_response(array(
            'success'        => true,
            // Datos del certificado
            'course_id'      => $course_id,
            'student_name'   => $full_name,
            'course_title'   => $course_title,
            'instructor'     => $instructor_name,
            'duration'       => $duration ? sanitize_text_field($duration) : '',
            'completion_date'=> $date_formatted,
            'cert_hash'      => $cert_hash,
            'cert_id'        => strtoupper(substr($cert_hash, 0, 12)),
            'validation_url' => $validation_url,
            // Imagen existente (si ya fue generada antes)
            'cert_img'       => $cert_img,
            // Branding
            'site_name'      => $site_name,
            'site_tagline'   => $site_tagline,
            'logo_url'       => $logo_url,
        ));

    } catch (\Throwable $e) {
        return new WP_Error('fatal_error', 'Error: ' . $e->getMessage(), array('status' => 500));
    }
}

function vk_my_certificate($req) {

    try {
        $uid       = vk_uid($req);
        if (!$uid) return new WP_Error('unauthorized','Token requerido',array('status'=>401));
        $course_id = (int)$req['id'];
        global $wpdb;

        // Verificar inscripcion
        $enrolled = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type='tutor_enrolled' AND post_parent=%d AND post_author=%d LIMIT 1",
            $course_id, $uid
        ));
        if (!$enrolled) return new WP_Error('not_enrolled','No estas inscrito',array('status'=>403));

        $upload_dir   = wp_upload_dir();
        $cert_dir     = $upload_dir['basedir'] . '/tutor-certificates/';
        $cert_url_dir = $upload_dir['baseurl']  . '/tutor-certificates/';
        $cert_hash = '';
        $cert_img  = '';

        // ── ESTRATEGIA PRINCIPAL: usar tutor_utils()->is_completed_course() ──
        // Esta es la misma forma que usa WordPress internamente para obtener el cert_hash
        if (function_exists('tutor_utils')) {
            $is_completed = tutor_utils()->is_completed_course($course_id, $uid, false);
            if ($is_completed && !empty($is_completed->completed_hash)) {
                $cert_hash = $is_completed->completed_hash;

                // Obtener el comment_ID (certificate_id) del registro de completado
                $cert_id = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT comment_ID FROM {$wpdb->comments}
                     WHERE comment_type='course_completed'
                       AND comment_agent='TutorLMSPlugin'
                       AND comment_content=%s LIMIT 1",
                    $cert_hash
                ));

                if ($cert_id) {
                    // El nombre del archivo es: {rand_string}-{cert_hash}.jpg
                    // El rand_string se guarda en comment_meta con clave 'tutor_certificate_has_image'
                    $rand_string = get_comment_meta($cert_id, 'tutor_certificate_has_image', true);
                    if ($rand_string) {
                        $file_path = $cert_dir . $rand_string . '-' . $cert_hash . '.jpg';
                        if (file_exists($file_path)) {
                            $is_stale = false;

                            // Verificar obsolescencia por cambio en plantillas VK (principal)
                            if (vkx_cert_invalidate_if_stale($cert_id, $cert_hash, $rand_string, $cert_dir)) {
                                $is_stale = true;
                            }

                            // También verificar plantilla TutorLMS builder si no fue ya invalidada
                            if (!$is_stale) {
                                $template_key = get_post_meta($course_id, 'tutor_course_certificate_template', true);
                                if ($template_key && strpos($template_key, 'tutor_cb_') === 0) {
                                    $template_id   = (int) preg_replace('/\D/', '', $template_key);
                                    $template_post = get_post($template_id);
                                    if ($template_post && filemtime($file_path) < strtotime($template_post->post_modified)) {
                                        @unlink($file_path);
                                        $is_stale = true;
                                    }
                                }
                            }

                            if (!$is_stale) {
                                $cert_img = $cert_url_dir . $rand_string . '-' . $cert_hash . '.jpg';
                            }
                        }
                    }
                }

                // ELIMINADO: Fallback de escaneo de disco que podía devolver certs de TutorLMS
                // con datos de demo. Solo se aceptan certs generados por VK (identificados
                // por el meta 'tutor_certificate_has_image'). Si ese archivo no existe,
                // cert_img queda vacío y el cliente JS regenera con canvas VK + datos reales.
            }
        }

        // ── FALLBACK: buscar hash en wp_posts (tutor_certificate post type) ──
        if (!$cert_hash) {
            $cert_post = $wpdb->get_row($wpdb->prepare(
                "SELECT ID, post_name FROM {$wpdb->posts}
                 WHERE post_type IN ('tutor_certificate','tutorlms_certificate')
                   AND post_author = %d AND post_parent = %d
                 ORDER BY ID DESC LIMIT 1",
                $uid, $course_id
            ));
            if ($cert_post && preg_match('/^[a-f0-9]{8,}$/i', $cert_post->post_name)) {
                $cert_hash = $cert_post->post_name;
            }
        }

        // ── FALLBACK: usermeta especifico por curso ──
        if (!$cert_hash) {
            $stored = get_user_meta($uid, '_tutor_cert_hash_course_' . $course_id, true);
            if ($stored) $cert_hash = $stored;
        }

        // ELIMINADO: Búsqueda genérica en disco — podía encontrar certs de TutorLMS
        // (*.jpeg, *.png) generados con datos de demo. Si no hay cert VK en meta,
        // devolver cert_img vacío para que el canvas JS genere con datos reales.

        // URL de visualizacion (funciona en local y produccion)
        $cert_page_url = $cert_hash ? home_url('/tutor-certificate/?cert_hash=' . $cert_hash) : '';

        // Si no hay imagen en disco, devolver cert_img vacío para que el cliente JS
        // genere el certificado con canvas VK (datos reales del usuario, nunca demo).
        // ELIMINADO: wp_remote_get loopback a TutorLMS — causaba que se generara un cert
        // con datos del template de TutorLMS (posiblemente con nombre de demo) y ese cert
        // era retornado como cert_img, bypaseando completamente el renderizado canvas VK.

        return rest_ensure_response(array(
            'url'       => $cert_page_url,
            'cert_hash' => $cert_hash,
            'cert_img'  => $cert_img ?: '',
        ));
    } catch (\Throwable $e) {
        return new WP_Error('fatal_error', 'Error interno al obtener el certificado: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine(), array('status' => 500));
    }
}

/**
 * Endpoint REST seguro para almacenar la imagen física del certificado
 * Autenticado por Token y completamente independiente de cookies / nonces
 */
function vk_save_certificate_image($req) {
    try {
        $uid = vk_uid($req);
        if (!$uid) {
            return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
        }

        $cert_hash = sanitize_text_field($req->get_param('cert_hash'));
        if (!$cert_hash) {
            return new WP_Error('missing_param', 'Hash de certificado requerido', array('status' => 400));
        }

        global $wpdb;
        $completed = $wpdb->get_row($wpdb->prepare(
            "SELECT comment_ID as certificate_id,
                    comment_post_ID as course_id,
                    comment_author as completed_user_id,
                    comment_date as completion_date,
                    comment_content as completed_hash
             FROM {$wpdb->comments}
             WHERE comment_agent = 'TutorLMSPlugin'
               AND comment_type = 'course_completed'
               AND comment_content = %s",
            $cert_hash
        ));

        if (!$completed) {
            return new WP_Error('not_found', 'Registro de finalización de curso no encontrado', array('status' => 404));
        }

        // Permitir si es el propio usuario o si el usuario es administrador
        $is_admin = user_can($uid, 'manage_options');
        if ((int)$completed->completed_user_id !== (int)$uid && !$is_admin) {
            return new WP_Error('forbidden', 'No tienes permiso para guardar este certificado', array('status' => 403));
        }

        // ── BLOQUEO: si ya tiene imagen guardada, devolver la existente sin re-generar ──
        $existing_rand = get_comment_meta($completed->certificate_id, 'tutor_certificate_has_image', true);
        if ($existing_rand && !$is_admin) {
            $upload_dir_check = wp_upload_dir();
            $existing_file    = $upload_dir_check['basedir'] . '/tutor-certificates/' . $existing_rand . '-' . $cert_hash . '.jpg';
            if (file_exists($existing_file)) {
                return rest_ensure_response(array(
                    'success'   => true,
                    'locked'    => true,
                    'message'   => 'Certificado ya generado. Mostrando el original.',
                    'cert_img'  => $upload_dir_check['baseurl'] . '/tutor-certificates/' . $existing_rand . '-' . $cert_hash . '.jpg',
                ));
            }
        }

        // Obtener ruta del directorio de certificados de Tutor LMS
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/tutor-certificates/';
        wp_mkdir_p($cert_dir);

        // Generar un string aleatorio para el nombre de archivo como hace Tutor LMS Pro
        $rand_string = substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 5)), 0, 10);
        $file_name = $rand_string . '-' . $cert_hash . '.jpg';
        $file_dest = $cert_dir . $file_name;

        // Verificar si la imagen fue enviada como Base64 o como Archivo
        if (isset($_POST['image']) && strpos($_POST['image'], 'data:image') === 0) {
            $base64_string = $_POST['image'];
            $image_parts = explode(";base64,", $base64_string);
            if (count($image_parts) == 2) {
                $image_base64 = base64_decode($image_parts[1]);
                if (!file_put_contents($file_dest, $image_base64)) {
                    return new WP_Error('upload_failed', 'Error al guardar el archivo base64 en el servidor', array('status' => 500));
                }
            } else {
                return new WP_Error('invalid_base64', 'Formato base64 inválido', array('status' => 400));
            }
        } else if (isset($_FILES['image']) && !$_FILES['image']['error']) {
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $file_dest)) {
                return new WP_Error('upload_failed', 'Error al guardar el archivo en el servidor', array('status' => 500));
            }
        } else if (isset($_FILES['certificate_image']) && !$_FILES['certificate_image']['error']) {
            if (!move_uploaded_file($_FILES['certificate_image']['tmp_name'], $file_dest)) {
                return new WP_Error('upload_failed', 'Error al guardar el archivo de certificado en el servidor', array('status' => 500));
            }
        } else {
            return new WP_Error('invalid_image', 'Archivo de certificado inválido o no recibido', array('status' => 400));
        }

        // Eliminar el archivo anterior si existiera
        $old_rand_string = get_comment_meta($completed->certificate_id, 'tutor_certificate_has_image', true);
        if ($old_rand_string) {
            $old_file = $cert_dir . $old_rand_string . '-' . $cert_hash . '.jpg';
            if (file_exists($old_file)) {
                @unlink($old_file);
            }
        }

        // Actualizar el metadato del comentario (el registro de finalización)
        update_comment_meta($completed->certificate_id, 'tutor_certificate_has_image', $rand_string);

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Imagen del certificado guardada exitosamente.',
            'cert_img' => $upload_dir['baseurl'] . '/tutor-certificates/' . $file_name
        ));

    } catch (\Throwable $e) {
        return new WP_Error('fatal_error', 'Error interno al guardar la imagen del certificado: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine(), array('status' => 500));
    }
}

/**
 * Genera la imagen JPG del certificado en el servidor.
 *
 * Estrategia:
 *  1. Si la imagen ya existe en disco → devolverla (caché)
 *  2. Crear sesión real de WP → generar nonce válido → llamar AJAX de Tutor en loopback
 *     → Tutor genera el HTML y lo devuelve; el cliente lo usa para renderizar.
 *     NOTA: Tutor solo guarda la imagen cuando el cliente sube el JPG vía
 *           tutor_store_certificate_image. El servidor NO puede hacer el render de html2canvas.
 *     Por eso el loopback solo obtiene el HTML; devolvemos {html_only} con la URL de la
 *     página del certificado para que el cliente abra en WebView (sin iframe).
 *  3. Fallback: devolver {html_only, url} para que el cliente abra en navegador nativo.
 */
/**
 * Retorna el HTML del certificado directamente sin abrir ninguna URL externa.
 * Esta versión nunca devuelve html_only ni URLs de WordPress.
 * Solo devuelve: cert_img (si existe) o cert_html (para renderizar in-app).
 */
function vk_generate_cert_server($req) {
    try {
        $uid = vk_uid($req);
        if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));

        $course_id = (int)$req->get_param('course_id');
        $cert_hash = sanitize_text_field($req->get_param('cert_hash'));

        if (!$course_id) return new WP_Error('missing_params', 'Falta course_id', array('status' => 400));

        wp_set_current_user($uid);
        global $wpdb;

        // Buscar hash si no fue enviado
        if (!$cert_hash) {
            if (function_exists('tutor_utils')) {
                $comp = tutor_utils()->is_completed_course($course_id, $uid, false);
                if ($comp && !empty($comp->completed_hash)) {
                    $cert_hash = $comp->completed_hash;
                }
            }
        }

        if (!$cert_hash) {
            return new WP_Error('not_completed', 'El curso no está completado', array('status' => 404));
        }

        // Buscar registro de finalización
        $completed = $wpdb->get_row($wpdb->prepare(
            "SELECT comment_ID as certificate_id, comment_post_ID as course_id,
                    comment_author as completed_user_id, comment_date as completion_date,
                    comment_content as completed_hash
             FROM {$wpdb->comments}
             WHERE comment_agent = 'TutorLMSPlugin'
               AND comment_type = 'course_completed'
               AND comment_content = %s",
            $cert_hash
        ));

        if (!$completed) return new WP_Error('not_found', 'Certificado no encontrado', array('status' => 404));
        if ((int)$completed->completed_user_id !== (int)$uid && !user_can($uid, 'manage_options')) {
            return new WP_Error('forbidden', 'Sin permiso', array('status' => 403));
        }

        // ESTRATEGIA 1: Imagen ya existe en disco → devolver directamente
        $upload_dir   = wp_upload_dir();
        $cert_dir     = $upload_dir['basedir'] . '/tutor-certificates/';
        $cert_url_dir = $upload_dir['baseurl']  . '/tutor-certificates/';

        $existing_rand = get_comment_meta($completed->certificate_id, 'tutor_certificate_has_image', true);
        if ($existing_rand) {
            $existing_path = $cert_dir . $existing_rand . '-' . $cert_hash . '.jpg';
            if (file_exists($existing_path)) {
                // Verificar si la imagen está obsoleta respecto a la última actualización de plantilla VK
                if (!vkx_cert_invalidate_if_stale($completed->certificate_id, $cert_hash, $existing_rand, $cert_dir)) {
                    return rest_ensure_response(array(
                        'success'   => true,
                        'cert_img'  => $cert_url_dir . $existing_rand . '-' . $cert_hash . '.jpg',
                        'cert_hash' => $cert_hash,
                        'cached'    => true,
                    ));
                }
                // Si era obsoleta, vkx_cert_invalidate_if_stale ya la eliminó → continuar a regenerar
            }
        }

        // ELIMINADO: glob fallback — podía devolver certs de TutorLMS con datos demo.
        // Si el meta no tiene un cert VK válido, continuar a generar uno nuevo.

        // ESTRATEGIA 2: Generar HTML con Tutor y devolverlo para render in-app
        if (!class_exists('\TUTOR_CERT\Certificate')) {
            return new WP_Error('plugin_missing', 'Addon de certificados no activo', array('status' => 500));
        }

        $cert_obj   = new \TUTOR_CERT\Certificate(true);
        $reflection = new \ReflectionClass($cert_obj);

        $prep_method = $reflection->getMethod('prepare_template_data');
        $prep_method->setAccessible(true);
        $prep_method->invokeArgs($cert_obj, array($course_id));

        // Leer la propiedad template para obtener metadatos
        $template_prop = $reflection->getProperty('template');
        $template_prop->setAccessible(true);
        $template = $template_prop->getValue($cert_obj);

        // Si usa certificate builder (plantilla personalizada)
        if ($template && isset($template['key']) && strpos($template['key'], 'tutor_cb_') === 0) {
            // Devolver HTML vacío con flag de builder — la app lo manejará con fallback
            return rest_ensure_response(array(
                'success'     => true,
                'cert_html'   => '',
                'cert_hash'   => $cert_hash,
                'is_builder'  => true,
                'orientation' => isset($template['orientation']) ? $template['orientation'] : 'landscape',
            ));
        }

        // Generar HTML del certificado
        $cert_html = $cert_obj->generate_certificate($course_id, $completed);

        if (empty($cert_html)) {
            return new WP_Error('html_empty', 'No se pudo generar el HTML del certificado', array('status' => 500));
        }

        return rest_ensure_response(array(
            'success'     => true,
            'cert_html'   => $cert_html,
            'cert_hash'   => $cert_hash,
            'orientation' => isset($template['orientation']) ? $template['orientation'] : 'landscape',
        ));

    } catch (\Throwable $e) {
        return new WP_Error('fatal_error', 'Error: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine(), array('status' => 500));
    }
}


/**
 * Genera el HTML o URL del constructor de certificados de Tutor LMS de forma dinámica
 * Autenticado por Token y completamente independiente de cookies / nonces
 */

function vk_get_certificate_html($req) {
    try {
        $uid = vk_uid($req);
        if (!$uid) {
            return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
        }

        $course_id = (int)$req->get_param('course_id');
        $cert_hash = sanitize_text_field($req->get_param('certificate_hash'));
        
        if (!$course_id || !$cert_hash) {
            return new WP_Error('missing_params', 'Faltan parámetros requeridos', array('status' => 400));
        }

        global $wpdb;
        $completed = $wpdb->get_row($wpdb->prepare(
            "SELECT comment_ID as certificate_id,
                    comment_post_ID as course_id,
                    comment_author as completed_user_id,
                    comment_date as completion_date,
                    comment_content as completed_hash
             FROM {$wpdb->comments}
             WHERE comment_agent = 'TutorLMSPlugin'
               AND comment_type = 'course_completed'
               AND comment_content = %s",
            $cert_hash
        ));

        if (!$completed) {
            return new WP_Error('not_found', 'Registro de finalización de curso no encontrado', array('status' => 404));
        }

        // Permitir si es el propio usuario o si el usuario es administrador
        $is_admin = user_can($uid, 'manage_options');
        if ((int)$completed->completed_user_id !== (int)$uid && !$is_admin) {
            return new WP_Error('forbidden', 'No tienes permiso para ver este certificado', array('status' => 403));
        }

        if (!class_exists('\TUTOR_CERT\Certificate')) {
            return new WP_Error('plugin_missing', 'El addon de certificados de Tutor LMS no está activo', array('status' => 500));
        }

        $cert_obj = new \TUTOR_CERT\Certificate(true);

        // Invocar el método privado prepare_template_data mediante PHP Reflection
        $reflection = new \ReflectionClass($cert_obj);
        $method = $reflection->getMethod('prepare_template_data');
        $method->setAccessible(true);
        $method->invokeArgs($cert_obj, array($course_id));

        // Acceder a la propiedad privada template mediante PHP Reflection
        $property = $reflection->getProperty('template');
        $property->setAccessible(true);
        $template = $property->getValue($cert_obj);

        if ($template && isset($template['key']) && strpos($template['key'], 'tutor_cb_') === 0) {
            $template_id = preg_replace('/\D/', '', $template['key']);
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'certificate_builder_url' => apply_filters(
                        'tutor_certificate_builder_url',
                        $template_id,
                        array(
                            'cert_hash'   => $cert_hash,
                            'course_id'   => $course_id,
                            'orientation' => $template['orientation'],
                        )
                    )
                )
            ));
        }

        // Generar el contenido HTML del certificado de forma nativa
        $content = $cert_obj->generate_certificate($course_id, $completed);

        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'html' => $content
            )
        ));

    } catch (\Throwable $e) {
        return new WP_Error('fatal_error', 'Error al generar HTML del certificado: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine(), array('status' => 500));
    }
}



/* ===============================================
   ENCUESTAS ? Fluent Forms
=============================================== */
/* === ONESIGNAL ? GUARDAR PLAYER ID === */
function vk_save_push_id($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status'=>401));
    $body      = $req->get_json_params() ?: array();
    $player_id = isset($body['player_id']) ? sanitize_text_field($body['player_id']) : '';
    if (!$player_id) return new WP_Error('invalid', 'player_id requerido', array('status'=>400));
    $existing = get_user_meta($uid, 'onesignal_player_ids', true) ?: array();
    if (!is_array($existing)) $existing = array();
    if (!in_array($player_id, $existing)) {
        $existing[] = $player_id;
        update_user_meta($uid, 'onesignal_player_ids', $existing);
    }
    update_user_meta($uid, 'onesignal_player_id', $player_id);
    return rest_ensure_response(array('success' => true, 'player_id' => $player_id, 'total_devices' => count($existing)));
}

/* === ONESIGNAL ? ENVIAR PUSH NOTIFICATION === */
function vk_send_push($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status'=>401));

    $body    = $req->get_json_params() ?: array();
    $title   = isset($body['title'])   ? sanitize_text_field($body['title'])   : 'DM Plus';
    $message = isset($body['message']) ? sanitize_text_field($body['message']) : '';
    $url     = isset($body['url'])     ? esc_url_raw($body['url'])             : home_url('/');
    // target: 'self' (solo este usuario) o 'all' (solo admins)
    $target  = isset($body['target'])  ? $body['target'] : 'self';

    if (!$message) return new WP_Error('invalid', 'message requerido', array('status'=>400));

    // Obtener REST API Key de OneSignal desde las opciones del plugin
    $os_settings = get_option('onesignal_settings', array());
    $rest_api_key = isset($os_settings['app_rest_api_key']) ? $os_settings['app_rest_api_key'] : '';
    $app_id       = defined('VK_ONESIGNAL_APP_ID') ? VK_ONESIGNAL_APP_ID : '5ed3833a-c6c4-4b09-9f3c-3d7778e334b4';

    if (empty($rest_api_key)) {
        return new WP_Error('missing_key', 'Falta la REST API Key. Ve a la pestaña Configuración en el panel e ingresa tu clave de OneSignal.', array('status'=>400));
    }

    // Construir payload completo para web push (desktop y móvil)
    $icon_url   = 'https://vidakushala.com/wp-content/uploads/dm-icon.png';
    $badge_url  = 'https://vidakushala.com/wp-content/uploads/dm-icon.png';
    $type_val   = isset($body['type']) ? sanitize_key($body['type']) : 'info';
    $notif_url  = $url ?: 'https://app.vidakushala.com/';

    $payload = array(
        'app_id'                    => $app_id,
        // Título y cuerpo — obligatorios
        'headings'                  => array('en' => $title, 'es' => $title),
        'contents'                  => array('en' => $message, 'es' => $message),
        // URL al hacer clic
        'url'                       => $notif_url,
        // Iconos — críticos para que aparezca la ventana emergente
        'chrome_web_icon'           => $icon_url,
        'firefox_icon'              => $icon_url,
        'chrome_web_badge'          => $badge_url,
        // Data adicional para routing en la app
        'data'                      => array(
            'type' => $type_val,
            'url'  => $notif_url,
        ),
        // Web push específico
        'web_push_topic'            => $type_val,
        'ttl'                       => 86400, // 24 horas
        // Prioridad alta para asegurar entrega inmediata
        'priority'                  => 10,
        'web_buttons'               => array(),
        // Mostrar siempre aunque la app esté en foco
        'web_push_apns_payload'     => array('aps' => array('content-available' => 1)),
    );

    if ($target === 'self') {
        // Usar external_user_id (más confiable que player_ids que pueden expirar)
        $payload['include_external_user_ids'] = array((string)$uid);
        $payload['channel_for_external_user_ids'] = 'push';
        // Fallback: también incluir player_ids por compatibilidad
        $player_ids = get_user_meta($uid, 'onesignal_player_ids', true) ?: array();
        if (!empty($player_ids)) {
            $payload['include_subscription_ids'] = array_values($player_ids);
        } elseif (empty($player_ids)) {
            // Sin player_ids ni external_id registrado — error
            unset($payload['include_external_user_ids']);
            unset($payload['channel_for_external_user_ids']);
            return new WP_Error('no_device','No hay dispositivos registrados para este usuario',array('status'=>404));
        }
    } elseif ($target === 'user') {
        if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
        $email       = isset($body['user_email']) ? sanitize_email($body['user_email']) : '';
        $target_user = get_user_by('email', $email);
        if (!$target_user) return new WP_Error('not_found','Usuario no encontrado',array('status'=>404));
        $target_uid  = $target_user->ID;
        // Usar external_user_id
        $payload['include_external_user_ids'] = array((string)$target_uid);
        $payload['channel_for_external_user_ids'] = 'push';
        // Fallback player_ids
        $target_pids = get_user_meta($target_uid, 'onesignal_player_ids', true) ?: array();
        if (!empty($target_pids)) $payload['include_subscription_ids'] = array_values($target_pids);
    } else {
        // Enviar a todos — obtener todos los player_ids igual que la bienvenida
        if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
        global $wpdb;
        $all_ids = array();
        $rows = $wpdb->get_results("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key='onesignal_player_ids'");
        foreach ($rows as $row) {
            $ids = @unserialize($row->meta_value);
            if (is_array($ids)) foreach ($ids as $id) { if (!empty($id)) $all_ids[] = $id; }
        }
        $single_rows = $wpdb->get_results("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key='onesignal_player_id' AND meta_value!=''");
        foreach ($single_rows as $row) { if (!empty($row->meta_value)) $all_ids[] = $row->meta_value; }
        $all_ids = array_values(array_unique($all_ids));
        if (empty($all_ids)) {
            return new WP_Error('no_subscribers', 'No hay suscriptores con notificaciones activas', array('status'=>404));
        }
        // Usar included_segments en lugar de IDs específicos (más confiable)
        $payload['included_segments'] = array('All');
        unset($payload['include_subscription_ids']);
        unset($payload['include_external_user_ids']);
        unset($payload['channel_for_external_user_ids']);
    }

    // Limpiar campos que pueden interferir
    unset($payload['web_buttons']);
    unset($payload['web_push_apns_payload']);

    // Llamar a la API de OneSignal (usar charset=utf-8 igual que welcome que sí funciona)
    $response = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
        'headers' => array(
            'Content-Type'  => 'application/json; charset=utf-8',
            'Authorization' => 'Key ' . $rest_api_key,
        ),
        'body'    => json_encode($payload),
        'timeout' => 15,
    ));

    if (is_wp_error($response)) {
        return new WP_Error('os_error', $response->get_error_message(), array('status'=>500));
    }

    $body_response = json_decode(wp_remote_retrieve_body($response), true);
    $http_code     = wp_remote_retrieve_response_code($response);

    // ── Auto-limpiar IDs inválidos reportados por OneSignal ──────────
    $invalid_ids = array();
    if (!empty($body_response['errors']) && is_array($body_response['errors'])) {
        // Formato: {"errors":{"invalid_player_ids":["id1","id2"]}}
        if (!empty($body_response['errors']['invalid_player_ids'])) {
            $invalid_ids = (array)$body_response['errors']['invalid_player_ids'];
        }
    }

    if (!empty($invalid_ids)) {
        // Limpiar de todos los usuarios en la BD
        global $wpdb;
        $users_with_invalid = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta}
             WHERE meta_key = 'onesignal_player_ids'"
        );
        foreach ($users_with_invalid as $row) {
            $ids = @unserialize($row->meta_value);
            if (!is_array($ids)) continue;
            $cleaned = array_values(array_filter($ids, function($id) use ($invalid_ids) {
                return !in_array($id, $invalid_ids);
            }));
            if (count($cleaned) !== count($ids)) {
                update_user_meta((int)$row->user_id, 'onesignal_player_ids', $cleaned);
                // Si quedó vacío, limpiar también el meta simple
                if (empty($cleaned)) {
                    delete_user_meta((int)$row->user_id, 'onesignal_player_id');
                }
            }
        }

        // Si teníamos IDs específicos, reintentar solo con los válidos
        if (!empty($payload['include_subscription_ids'])) {
            $valid_ids = array_values(array_diff($payload['include_subscription_ids'], $invalid_ids));
            if (!empty($valid_ids)) {
                $payload['include_subscription_ids'] = $valid_ids;
                $retry = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
                    'headers' => array(
                        'Content-Type'  => 'application/json; charset=utf-8',
                        'Authorization' => 'Key ' . $rest_api_key,
                    ),
                    'body'    => json_encode($payload),
                    'timeout' => 15,
                ));
                if (!is_wp_error($retry)) {
                    $retry_body = json_decode(wp_remote_retrieve_body($retry), true);
                    $retry_code = wp_remote_retrieve_response_code($retry);
                    if ($retry_code === 200 && empty($retry_body['errors'])) {
                        $body_response = $retry_body;
                        $http_code     = $retry_code;
                        $body_response['_cleaned_ids'] = count($invalid_ids); // info para el frontend
                    }
                }
            }
        }
    }

    $success = ($http_code === 200 && empty($body_response['errors']));
    $recipients = isset($body_response['recipients']) ? (int)$body_response['recipients'] : 0;

    // Si 0 destinatarios con include_player_ids, intentar con included_segments como fallback
    // Esto ayuda con Safari y dispositivos que tienen suscripción en estado especial
    if ($success && $recipients === 0 && !empty($payload['include_subscription_ids'])) {
        // Intentar también como segmento dirigido por email del usuario
        $fallback_payload = $payload;
        unset($fallback_payload['include_subscription_ids']);
        // Intentar con external_user_id si está disponible
        $external_ids = array();
        foreach (($payload['include_subscription_ids'] ?? array()) as $pid) {
            // Buscar usuario por player_id para obtener su user_id como external
            global $wpdb;
            $uid_for_pid = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='onesignal_player_id' AND meta_value=%s LIMIT 1",
                $pid
            ));
            if ($uid_for_pid) $external_ids[] = (string)$uid_for_pid;
        }
        if (!empty($external_ids)) {
            $fallback_payload['include_external_user_ids'] = $external_ids;
            $fallback_payload['channel_for_external_user_ids'] = 'push';
            $fb_res  = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
                'headers' => array(
                    'Content-Type'  => 'application/json; charset=utf-8',
                    'Authorization' => 'Key ' . $rest_api_key,
                ),
                'body'    => json_encode($fallback_payload, JSON_UNESCAPED_UNICODE),
                'timeout' => 10,
            ));
            if (!is_wp_error($fb_res)) {
                $fb_body = json_decode(wp_remote_retrieve_body($fb_res), true);
                $fb_code = wp_remote_retrieve_response_code($fb_res);
                if ($fb_code === 200 && empty($fb_body['errors']) && ($fb_body['recipients'] ?? 0) > 0) {
                    $body_response = $fb_body;
                    $body_response['_fallback_method'] = 'external_user_id';
                    $success    = true;
                    $recipients = (int)$fb_body['recipients'];
                }
            }
        }
    }

    // Guardar en historial + base de datos
    if ($success) {
        $history = get_option('vk_push_history', array());
        $hist_id  = isset($body_response['id']) ? $body_response['id'] : uniqid();
        $history[] = array(
            'id'        => $hist_id,
            'title'     => $title,
            'message'   => $message,
            'type'       => isset($body['type']) ? sanitize_key($body['type']) : 'info',
            'target'    => $target,
            'sent_by'   => $uid,
            'recipients'=> isset($body_response['recipients']) ? (int)$body_response['recipients'] : 0,
            'date'      => current_time('mysql'),
        );
        if (count($history) > 100) $history = array_slice($history, -100);
        update_option('vk_push_history', $history);
        // Guardar tambien en tabla vk_notifications para sincronizar con la app
        global $wpdb;
        $ntable = $wpdb->prefix . 'vk_notifications';
        $ntype  = isset($body['type']) ? sanitize_key($body['type']) : 'info';
        $clean_t = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($title)   : strip_tags($title);
        $clean_m = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($message) : strip_tags($message);
        $nurl   = isset($body['url']) ? esc_url_raw($body['url']) : '';
        if ($target === 'self' || $target === 'user') {
            // Personal: guardar con user_id del destinatario
            $dest_uid = ($target === 'user' && !empty($target_user)) ? $target_user->ID : $uid;
            $wpdb->insert($ntable, array(
                'user_id'=>$dest_uid,'title'=>$clean_t,'message'=>$clean_m,
                'type'=>$ntype,'action_url'=>$nurl,'is_read'=>0,'created_at'=>current_time('mysql')
            ), array('%d','%s','%s','%s','%s','%d','%s'));
        } else {
            // Global: user_id=0
            $wpdb->insert($ntable, array(
                'user_id'=>0,'title'=>$clean_t,'message'=>$clean_m,
                'type'=>$ntype,'action_url'=>$nurl,'is_read'=>0,'created_at'=>current_time('mysql')
            ), array('%d','%s','%s','%s','%s','%d','%s'));
        }
    }

    // Extraer mensaje de error legible de la respuesta de OneSignal
    $err_msg = '';
    if (!$success) {
        if (!empty($body_response['errors'])) {
            $errs = $body_response['errors'];
            if (is_string($errs)) {
                $err_msg = $errs;
            } elseif (is_array($errs)) {
                // Array indexado: ["message1","message2"]
                // Array asociativo: {"invalid_player_ids":["id1"]} o {"code":"InvalidAppId"}
                $parts = array();
                foreach ($errs as $k => $v) {
                    if (is_string($v) && !is_numeric($k)) {
                        $parts[] = $k . ': ' . $v;
                    } elseif (is_string($v)) {
                        $parts[] = $v;
                    } elseif (is_array($v)) {
                        $parts[] = (is_numeric($k) ? '' : $k . ': ') . implode(', ', array_filter($v, 'is_string'));
                    }
                }
                $err_msg = implode(' | ', array_filter($parts));
            }
        }
        if (!$err_msg && !empty($body_response['error']))   $err_msg = $body_response['error'];
        if (!$err_msg && !empty($body_response['message'])) $err_msg = $body_response['message'];
        if (!$err_msg) $err_msg = 'Error HTTP ' . $http_code . ' al enviar via OneSignal. Verifica la REST API Key.';
    }

    return rest_ensure_response(array(
        'success'  => $success,
        'http'     => $http_code,
        'message'  => $err_msg,
        'response' => $body_response,
    ));
}

/* ════════════════════════════════════════════════════════
   CLASE EN VIVO — Notificación push con enlace a la clase
════════════════════════════════════════════════════════ */
function vkx_push_live_class($req) {
    global $wpdb;

    $body      = $req->get_json_params() ?: array();
    $link      = esc_url_raw($body['link']     ?? '');
    $title_in  = sanitize_text_field($body['title']    ?? '');
    $msg_in    = sanitize_text_field($body['message']  ?? '');
    $platform  = sanitize_text_field($body['platform'] ?? '');
    $schedule  = sanitize_text_field($body['schedule'] ?? '');
    $target    = sanitize_text_field($body['target']   ?? 'all');
    $user_ids  = isset($body['user_ids']) && is_array($body['user_ids'])
                 ? array_map('intval', $body['user_ids']) : array();

    if (!$link) {
        return new WP_Error('no_link', 'El enlace de la clase es requerido', array('status'=>400));
    }

    // ── Credenciales ─────────────────────────────────────────────
    $os_settings  = get_option('onesignal_settings', array());
    $rest_api_key = isset($os_settings['app_rest_api_key']) ? trim($os_settings['app_rest_api_key']) : '';
    $app_id       = defined('VK_ONESIGNAL_APP_ID') ? VK_ONESIGNAL_APP_ID : '5ed3833a-c6c4-4b09-9f3c-3d7778e334b4';

    if (empty($rest_api_key)) {
        return new WP_Error('no_key', 'REST API Key de OneSignal no configurada. Ve a Configuración en el panel.', array('status'=>400));
    }

    // ── Título y mensaje ──────────────────────────────────────────
    $plat_labels = array('zoom'=>'Zoom','meet'=>'Google Meet','teams'=>'Teams','youtube'=>'YouTube');
    $plat_label  = $plat_labels[$platform] ?? '';
    $emojis      = array('zoom'=>'','meet'=>'','teams'=>'💼','youtube'=>'▶️');
    $emoji       = $emojis[$platform] ?? '';

    $push_title = $emoji . ' ' . ($title_in ?: 'Clase en Línea' . ($plat_label ? ' · '.$plat_label : ''));
    $push_msg   = $msg_in ?: 'Haz clic para unirte a la clase ahora';
    if ($schedule) $push_msg .= ' · ' . $schedule;

    $icon = 'https://vidakushala.com/wp-content/uploads/dm-icon.png';

    // ── Recopilar player_ids (igual que vkx_push_clone_welcome) ──
    $player_ids = array();

    if ($target === 'user' && !empty($user_ids)) {
        // Usuarios específicos
        foreach ($user_ids as $uid_n) {
            $ids_meta = get_user_meta($uid_n, 'onesignal_player_ids', true) ?: array();
            if (is_array($ids_meta)) {
                foreach ($ids_meta as $id) { if (!empty($id)) $player_ids[] = $id; }
            }
            $single = get_user_meta($uid_n, 'onesignal_player_id', true);
            if (!empty($single)) $player_ids[] = $single;
        }
    } else {
        // Todos los suscriptores
        $rows = $wpdb->get_results(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key='onesignal_player_ids'"
        );
        foreach ($rows as $row) {
            $ids = @unserialize($row->meta_value);
            if (is_array($ids)) foreach ($ids as $id) { if (!empty($id)) $player_ids[] = $id; }
        }
        // Fallback: onesignal_player_id individuales
        $single_rows = $wpdb->get_results(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key='onesignal_player_id' AND meta_value!=''"
        );
        foreach ($single_rows as $row) { $player_ids[] = $row->meta_value; }
    }

    $player_ids = array_values(array_unique(array_filter($player_ids)));

    if (empty($player_ids)) {
        // Sin player_ids: guardar en BD y devolver aviso
        vkx_live_class_save_db($wpdb, $target, $user_ids, $push_title, $push_msg, $link);
        return rest_ensure_response(array(
            'success'    => true,
            'recipients' => 0,
            'saved_db'   => true,
            'message'    => 'Guardado en BD. No hay dispositivos suscritos a notificaciones push.',
        ));
    }

    // ── Guardar en BD ─────────────────────────────────────────────
    vkx_live_class_save_db($wpdb, $target, $user_ids, $push_title, $push_msg, $link);

    // ── Enviar a CADA player_id individualmente (patrón que funciona) ──
    $results    = array();
    $recipients = 0;

    foreach ($player_ids as $pid) {
        $payload = array(
            'app_id'                   => $app_id,
            'headings'                 => array('en' => $push_title, 'es' => $push_title),
            'contents'                 => array('en' => $push_msg,   'es' => $push_msg),
            // URL abre la app, NO el enlace externo directamente
            // El enlace de la clase va en data.launch_url para el botón
            'url'                      => 'https://app.vidakushala.com/',
            'include_subscription_ids' => array($pid),
            'chrome_web_icon'          => $icon,
            'firefox_icon'             => $icon,
            'chrome_web_badge'         => $icon,
            'web_buttons'              => array(
                array('id' => 'join', 'text' => 'Unirse ahora →', 'url' => $link),
                array('id' => 'open_app', 'text' => 'Ver en App', 'url' => 'https://app.vidakushala.com/'),
            ),
            'data'           => array('type' => 'live_class', 'url' => 'https://app.vidakushala.com/', 'launch_url' => $link),
            'web_push_topic' => 'live_class',
            'ttl'            => 7200,
            'priority'       => 10,
        );

        $r = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
            'headers' => array(
                'Content-Type'  => 'application/json; charset=utf-8',
                'Authorization' => 'Key ' . $rest_api_key,
            ),
            'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 10,
            'blocking'=> true,
        ));

        $code    = wp_remote_retrieve_response_code($r);
        $b_data  = json_decode(wp_remote_retrieve_body($r), true);
        if ($code === 200 && isset($b_data['recipients']) && $b_data['recipients'] > 0) {
            $recipients++;
        }
        $results[] = array(
            'pid'    => $pid,
            'http'   => $code,
            'ok'     => ($code === 200 && !empty($b_data['id'])),
            'errors' => $b_data['errors'] ?? null,
        );
    }

    return rest_ensure_response(array(
        'success'    => $recipients > 0,
        'recipients' => $recipients,
        'total_ids'  => count($player_ids),
        'saved_db'   => true,
        'message'    => $recipients > 0
            ? 'Enviado a '.$recipients.' de '.count($player_ids).' dispositivos'
            : 'Enviado pero sin confirmación de entrega. Verifica la suscripción push.',
    ));
}

/* Helper: guardar notificación en BD */
function vkx_live_class_save_db($wpdb, $target, $user_ids, $title, $message, $link) {
    $notif_table = $wpdb->prefix . 'vk_notifications';
    if (!$wpdb->get_var("SHOW TABLES LIKE '$notif_table'")) return;

    // Columnas exactas que usa vkx_save_welcome_notification (sin is_global)
    // user_id=0 significa global (visible para todos)
    if ($target === 'user' && !empty($user_ids)) {
        foreach ($user_ids as $uid_n) {
            $wpdb->insert($notif_table, array(
                'user_id'    => (int)$uid_n,
                'title'      => $title,
                'message'    => $message,
                'type'       => 'system',
                'action_url' => $link,
                'is_read'    => 0,
                'created_at' => current_time('mysql'),
            ), array('%d','%s','%s','%s','%s','%d','%s'));
        }
    } else {
        // user_id=0 → notificación global visible para todos los usuarios
        $wpdb->insert($notif_table, array(
            'user_id'    => 0,
            'title'      => $title,
            'message'    => $message,
            'type'       => 'system',
            'action_url' => $link,
            'is_read'    => 0,
            'created_at' => current_time('mysql'),
        ), array('%d','%s','%s','%s','%s','%d','%s'));
    }
}


/* === PUSH ? FUNCIONES ADMIN === */
function vk_is_admin_token($req) {
    $uid = vk_uid($req);
    if (!$uid) return false;
    $user = get_userdata($uid);
    if (!$user) return false;
    // Verificar rol administrator de WordPress
    return in_array('administrator', (array)$user->roles);
}

// Endpoint explicito de verificacion de admin
function vk_check_admin($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized','Token requerido',array('status'=>401));
    $user = get_userdata($uid);
    if (!$user) return new WP_Error('not_found','Usuario no encontrado',array('status'=>404));
    $is_admin = in_array('administrator', (array)$user->roles);
    if (!$is_admin) return new WP_Error('forbidden','Acceso solo para administradores',array('status'=>403));
    return rest_ensure_response(array(
        'is_admin'     => true,
        'user_id'      => $uid,
        'display_name' => $user->display_name,
        'email'        => $user->user_email,
        'roles'        => array_values((array)$user->roles),
    ));
}


/* POST /vk/v1/push-clean-ids — limpia todos los player_ids inválidos de la BD */
function vkx_push_clean_invalid_ids($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    global $wpdb;
    $body        = $req->get_json_params() ?: array();
    $invalid_ids = isset($body['ids']) ? (array)$body['ids'] : array();

    if (empty($invalid_ids)) return new WP_Error('invalid','Se requiere ids[]',array('status'=>400));

    $cleaned_users  = 0;
    $cleaned_total  = 0;
    $users_with_ids = $wpdb->get_results(
        "SELECT user_id, meta_value FROM {$wpdb->usermeta}
         WHERE meta_key = 'onesignal_player_ids'"
    );
    foreach ($users_with_ids as $row) {
        $ids     = @unserialize($row->meta_value);
        if (!is_array($ids)) continue;
        $before  = count($ids);
        $cleaned = array_values(array_filter($ids, function($id) use ($invalid_ids) {
            return !in_array($id, $invalid_ids);
        }));
        $removed = $before - count($cleaned);
        if ($removed > 0) {
            $cleaned_total += $removed;
            $cleaned_users++;
            update_user_meta((int)$row->user_id, 'onesignal_player_ids', $cleaned);
            if (empty($cleaned)) {
                delete_user_meta((int)$row->user_id, 'onesignal_player_id');
            }
        }
    }
    // También limpiar el meta simple onesignal_player_id
    foreach ($invalid_ids as $inv_id) {
        $inv_id = sanitize_text_field($inv_id);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='onesignal_player_id' AND meta_value=%s",
            $inv_id
        ));
        foreach ($rows as $r) {
            delete_user_meta((int)$r->user_id, 'onesignal_player_id');
        }
    }
    return rest_ensure_response(array(
        'success'       => true,
        'cleaned_ids'   => $cleaned_total,
        'cleaned_users' => $cleaned_users,
        'message'       => "Se eliminaron $cleaned_total IDs inválidos de $cleaned_users usuarios."
    ));
}

function vk_push_stats($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    global $wpdb;
    $total_users = (int)$wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key='onesignal_player_id'");
    $total_devices = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='onesignal_player_id'");
    $history = get_option('vk_push_history', array());
    $os_settings = get_option('onesignal_settings', array());
    $has_key = !empty($os_settings['app_rest_api_key']);
    return rest_ensure_response(array(
        'total_subscribers' => $total_users,
        'total_devices'     => $total_devices,
        'total_sent'        => count($history),
        'has_api_key'       => $has_key,
        'app_id'            => '5ed3833a-c6c4-4b09-9f3c-3d7778e334b4',
    ));
}

function vk_push_subscribers($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT u.ID, u.display_name, u.user_email,
                um_single.meta_value as player_id,
                COALESCE(um_multi.meta_value, '') as all_player_ids
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} um_single
             ON um_single.user_id = u.ID
            AND um_single.meta_key = 'onesignal_player_id'
            AND um_single.meta_value != ''
         LEFT JOIN {$wpdb->usermeta} um_multi
             ON um_multi.user_id = u.ID
            AND um_multi.meta_key = 'onesignal_player_ids'
         ORDER BY u.display_name ASC
         LIMIT 1000"
    );
    $all_devices = get_option('vk_push_devices', array());
    $total_devices = 0;
    $result = array();
    foreach ($rows as $row) {
        $ids_raw = @unserialize($row->all_player_ids);
        $ids     = is_array($ids_raw) ? $ids_raw : ($row->player_id ? array($row->player_id) : array());
        $total_devices += count($ids);

        // Agregar info de cada dispositivo
        $devices_info = array();
        foreach ($ids as $pid) {
            $info = isset($all_devices[$pid]) ? $all_devices[$pid] : array();
            $devices_info[] = array(
                'player_id'  => $pid,
                'browser'    => $info['browser']    ?? '?',
                'device'     => $info['device']     ?? '?',
                'os'         => $info['os']          ?? '?',
                'registered' => $info['registered']  ?? '',
            );
        }
        $result[] = array(
            'ID'           => $row->ID,
            'display_name' => $row->display_name,
            'user_email'   => $row->user_email,
            'player_id'    => $row->player_id,
            'device_count' => count($ids),
            'devices'      => $devices_info,
        );
    }
    return rest_ensure_response(array(
        'data'          => $result,
        'total'         => count($result),
        'total_devices' => $total_devices,
    ));
}

function vk_push_history($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    $history = get_option('vk_push_history', array());
    $filter  = sanitize_text_field($req->get_param('type') ?: '');
    $fixed = array_map(function($h) {
        $h['title']   = vkx_fix_utf8($h['title']   ?? '');
        $h['message'] = vkx_fix_utf8($h['message'] ?? '');
        return $h;
    }, $history);
    if ($filter) {
        $fixed = array_values(array_filter($fixed, function($h) use ($filter) {
            return ($h['type'] ?? '') === $filter;
        }));
    }
    return rest_ensure_response(array('data'=>array_reverse(array_slice($fixed,-200)),'total'=>count($fixed)));
}

/* POST /vk/v1/push-history/delete */
add_action('rest_api_init', function() {
    register_rest_route('vk/v1', '/push-history/delete', array(
        'methods' => 'POST', 'callback' => 'vk_push_history_delete', 'permission_callback' => '__return_true'
    ));
}, 20);
function vk_push_history_delete($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    $body = $req->get_json_params() ?: array();
    $history = get_option('vk_push_history', array());
    if (!empty($body['all'])) {
        update_option('vk_push_history', array());
    } elseif (isset($body['index'])) {
        $idx = (int)$body['index'];
        if ($idx >= 0 && isset($history[$idx])) { array_splice($history, $idx, 1); update_option('vk_push_history', $history); }
    }
    return rest_ensure_response(array('success'=>true));
}


/* ── Alias push-history-delete (sin slash) ─────────────────────── */
function vk_push_history_delete_alias($req) {
    return vk_push_history_delete($req);
}

/* ── GET /vk/v1/admin-notifications — lista todas las notifs de BD ─ */
function vkx_admin_notifications($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    global $wpdb;
    $table  = $wpdb->prefix . 'vk_notifications';
    $limit  = min(100, max(1, (int)($req->get_param('limit')  ?: 50)));
    $offset = max(0,              (int)($req->get_param('offset') ?: 0));
    $type   = sanitize_text_field($req->get_param('type')   ?: '');
    $search = sanitize_text_field($req->get_param('search') ?: '');

    $where = '1=1';
    $vals  = array();
    if ($type) {
        $where .= ' AND type = %s';
        $vals[]  = $type;
    }
    if ($search) {
        $where .= ' AND (title LIKE %s OR message LIKE %s)';
        $vals[] = '%' . $wpdb->esc_like($search) . '%';
        $vals[] = '%' . $wpdb->esc_like($search) . '%';
    }

    $total_query = "SELECT COUNT(*) FROM `$table` WHERE $where";
    $total = $vals ? (int)$wpdb->get_var($wpdb->prepare($total_query, ...$vals)) : (int)$wpdb->get_var($total_query);

    $data_query = "SELECT n.*, u.display_name FROM `$table` n LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID WHERE $where ORDER BY n.id DESC LIMIT %d OFFSET %d";
    $data_vals  = array_merge($vals, array($limit, $offset));
    $rows = $wpdb->get_results($wpdb->prepare($data_query, ...$data_vals), ARRAY_A);

    $rows = array_map(function($r) {
        $r['title']     = vkx_fix_utf8($r['title']   ?? '');
        $r['message']   = vkx_fix_utf8($r['message'] ?? '');
        $r['is_global'] = (bool)($r['user_id'] == 0 || $r['user_id'] === null);
        $r['is_read']   = (bool)$r['is_read'];
        return $r;
    }, $rows ?: array());

    return rest_ensure_response(array('data' => $rows, 'total' => $total));
}

/* ── POST /vk/v1/admin-notif-delete — elimina notifs de BD (admin) ─ */
function vkx_admin_notif_delete($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';
    $body  = $req->get_json_params() ?: array();

    if (!empty($body['all'])) {
        $type = isset($body['type']) ? sanitize_text_field($body['type']) : '';
        if ($type) {
            $wpdb->delete($table, array('type' => $type), array('%s'));
        } else {
            $wpdb->query("TRUNCATE TABLE `$table`");
        }
        return rest_ensure_response(array('success' => true));
    }

    if (isset($body['id'])) {
        $wpdb->delete($table, array('id' => (int)$body['id']), array('%d'));
        return rest_ensure_response(array('success' => true));
    }

    return new WP_Error('invalid', 'Se requiere id o all=true', array('status' => 400));
}

/* ── POST /vk/v1/notifications/delete — usuario borra su propia notif ─ */
function vkx_user_notif_delete($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('forbidden','No autenticado',array('status'=>401));
    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';
    $body  = $req->get_json_params() ?: array();

    if (!empty($body['all'])) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM `$table` WHERE (user_id = %d OR (user_id = 0 AND is_global = 1))",
            $uid
        ));
        return rest_ensure_response(array('success' => true));
    }

    if (!empty($body['read'])) {
        // Borrar solo las leídas del usuario
        $wpdb->query($wpdb->prepare(
            "DELETE FROM `$table` WHERE user_id = %d AND is_read = 1",
            $uid
        ));
        return rest_ensure_response(array('success' => true));
    }

    if (isset($body['id'])) {
        $id = (int)$body['id'];
        // Solo puede borrar las suyas o las globales
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE id = %d", $id));
        if (!$row) return new WP_Error('not_found','Notificación no encontrada',array('status'=>404));
        if ((int)$row->user_id !== 0 && (int)$row->user_id !== $uid)
            return new WP_Error('forbidden','Sin permiso',array('status'=>403));
        $wpdb->delete($table, array('id' => $id), array('%d'));
        return rest_ensure_response(array('success' => true));
    }

    return new WP_Error('invalid','Se requiere id, read=true o all=true',array('status'=>400));
}

function vk_push_save_key($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    $body = $req->get_json_params() ?: array();
    $key  = isset($body['rest_api_key']) ? sanitize_text_field($body['rest_api_key']) : '';
    if (!$key) return new WP_Error('invalid','rest_api_key requerido',array('status'=>400));
    $os_settings = get_option('onesignal_settings', array());
    if (!is_array($os_settings)) $os_settings = array();
    $os_settings['app_rest_api_key'] = $key;
    update_option('onesignal_settings', $os_settings);
    return rest_ensure_response(array('success'=>true,'message'=>'API Key guardada correctamente'));
}

/* ── GET /vk/v1/push-auto-status — diagnóstico completo del sistema ─ */
/* ── POST /vk/v1/push-debug — envía test y devuelve respuesta raw de OneSignal ─ */
/* ── POST /vk/v1/push-clone-welcome
   Envía notificación copiando EXACTAMENTE el método de bienvenida que funciona.
   Si esta llega = el sistema funciona y el problema era el método anterior.
   Si no llega = hay un problema de permisos del dispositivo.
─────────────────────────────────────────────────────────────────── */
/* ── POST /vk/v1/push-reset-subscribers
   Elimina TODOS los player_ids de la BD para forzar re-registro limpio.
   Después de esto, cada usuario que abra la app y tenga permiso
   se re-registrará automáticamente con un ID válido.
─────────────────────────────────────────────────────────────── */
function vkx_push_reset_subscribers($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    global $wpdb;

    // Contar antes
    $before = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='onesignal_player_id'");

    // Eliminar todos los player_ids
    $wpdb->delete($wpdb->usermeta, array('meta_key' => 'onesignal_player_id'));
    $wpdb->delete($wpdb->usermeta, array('meta_key' => 'onesignal_player_ids'));

    return rest_ensure_response(array(
        'success' => true,
        'deleted' => $before,
        'message' => "Se eliminaron $before registros. Los usuarios se re-registrarán automáticamente al abrir la app.",
    ));
}

function vkx_push_clone_welcome($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    global $wpdb;
    $uid = vk_uid($req);

    $os_settings  = get_option('onesignal_settings', array());
    $rest_api_key = isset($os_settings['app_rest_api_key']) ? trim($os_settings['app_rest_api_key']) : '';
    if (empty($rest_api_key)) return new WP_Error('no_key','REST API Key no configurada',array('status'=>400));

    // Obtener player_ids del admin actual
    $player_ids = get_user_meta($uid, 'onesignal_player_ids', true) ?: array();
    if (!is_array($player_ids) || empty($player_ids)) {
        $single = get_user_meta($uid, 'onesignal_player_id', true);
        $player_ids = $single ? array($single) : array();
    }

    if (empty($player_ids)) {
        // Intentar con todos los usuarios
        $rows = $wpdb->get_results("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key='onesignal_player_ids'");
        foreach ($rows as $row) {
            $ids = @unserialize($row->meta_value);
            if (is_array($ids)) foreach ($ids as $id) { if (!empty($id)) $player_ids[] = $id; }
        }
        $player_ids = array_values(array_unique($player_ids));
    }

    if (empty($player_ids)) {
        return rest_ensure_response(array('success'=>false,'error'=>'No hay player_ids registrados'));
    }

    $icon = 'https://vidakushala.com/wp-content/uploads/dm-icon.png';
    $results = array();

    // Enviar a CADA ID individualmente (exactamente igual que bienvenida)
    foreach ($player_ids as $pid) {
        $payload = array(
            'app_id'                   => VK_ONESIGNAL_APP_ID,
            'headings'                 => array('en' => '🔔 Test desde panel', 'es' => '🔔 Test desde panel'),
            'contents'                 => array('en' => 'Prueba clon-bienvenida '.date('H:i:s'), 'es' => 'Prueba clon-bienvenida '.date('H:i:s')),
            'url'                      => 'https://app.vidakushala.com/',
            'include_subscription_ids' => array($pid),
            'chrome_web_icon'          => $icon,
            'firefox_icon'             => $icon,
            'chrome_web_badge'         => $icon,
            'data'                     => array('type' => 'test'),
            'web_push_topic'           => 'test',
            'ttl'                      => 3600,
            'priority'                 => 10,
        );

        $r = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
            'headers' => array(
                'Content-Type'  => 'application/json; charset=utf-8',
                'Authorization' => 'Key ' . $rest_api_key,
            ),
            'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 10,
            'blocking'=> true,
        ));

        $code = wp_remote_retrieve_response_code($r);
        $body = json_decode(wp_remote_retrieve_body($r), true);
        $results[] = array(
            'player_id'  => $pid,
            'http'       => $code,
            'notif_id'   => $body['id'] ?? null,
            'recipients' => $body['recipients'] ?? 0,
            'errors'     => $body['errors'] ?? null,
        );
    }

    return rest_ensure_response(array(
        'success' => true,
        'results' => $results,
        'count'   => count($results),
    ));
}

function vkx_push_debug($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));

    $os_settings  = get_option('onesignal_settings', array());
    $rest_api_key = isset($os_settings['app_rest_api_key']) ? trim($os_settings['app_rest_api_key']) : '';
    $app_id       = VK_ONESIGNAL_APP_ID;
    $uid          = vk_uid($req);

    // Info de diagnóstico
    $player_ids_single = get_user_meta($uid, 'onesignal_player_id', true);
    $player_ids_multi  = get_user_meta($uid, 'onesignal_player_ids', true) ?: array();

    $debug = array(
        'app_id'          => $app_id,
        'has_rest_key'    => !empty($rest_api_key),
        'rest_key_length' => strlen($rest_api_key),
        'rest_key_prefix' => $rest_api_key ? substr($rest_api_key, 0, 8).'...' : 'EMPTY',
        'user_id'         => $uid,
        'player_id_single'=> $player_ids_single ?: 'NONE',
        'player_ids_multi' => $player_ids_multi,
        'player_count'    => count($player_ids_multi),
    );

    if (empty($rest_api_key)) {
        $debug['error'] = 'REST API KEY NO CONFIGURADA';
        return rest_ensure_response(array('debug' => $debug, 'onesignal_response' => null));
    }

    if (empty($player_ids_multi) && empty($player_ids_single)) {
        $debug['error'] = 'NO HAY PLAYER IDS PARA ESTE USUARIO';
        return rest_ensure_response(array('debug' => $debug, 'onesignal_response' => null));
    }

    $ids = !empty($player_ids_multi) ? array_values(array_unique($player_ids_multi)) : array($player_ids_single);

    // OneSignal SDK v16 usa subscription IDs — probar ambos métodos
    $payload_segment = array(
        'app_id'             => $app_id,
        'headings'           => array('en' => '🔍 Debug ALL '.date('H:i:s'), 'es' => '🔍 Debug ALL '.date('H:i:s')),
        'contents'           => array('en' => 'Test segmento All', 'es' => 'Test segmento All'),
        'url'                => 'https://app.vidakushala.com/',
        'included_segments'  => array('All'),
        'chrome_web_icon'    => 'https://vidakushala.com/wp-content/uploads/dm-icon.png',
        'ttl'                => 60,
        'priority'           => 10,
    );
    $payload = array(
        'app_id'                    => $app_id,
        'headings'                  => array('en' => '🔍 Debug ID '.date('H:i:s'), 'es' => '🔍 Debug ID '.date('H:i:s')),
        'contents'                  => array('en' => 'Test player_id directo', 'es' => 'Test player_id directo'),
        'url'                       => 'https://app.vidakushala.com/',
        'include_subscription_ids'  => $ids,
        'chrome_web_icon'           => 'https://vidakushala.com/wp-content/uploads/dm-icon.png',
        'ttl'                       => 60,
        'priority'                  => 10,
    );

    // Test 1: por subscription_ids (player_ids)
    $response = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
        'headers' => array(
            'Content-Type'  => 'application/json; charset=utf-8',
            'Authorization' => 'Key ' . $rest_api_key,
        ),
        'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
        'timeout' => 15,
    ));

    $http_code = wp_remote_retrieve_response_code($response);
    $body      = json_decode(wp_remote_retrieve_body($response), true);
    $wp_error  = is_wp_error($response) ? $response->get_error_message() : null;

    // Test 2: por segmento All
    $resp2 = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
        'headers' => array(
            'Content-Type'  => 'application/json; charset=utf-8',
            'Authorization' => 'Key ' . $rest_api_key,
        ),
        'body'    => json_encode($payload_segment, JSON_UNESCAPED_UNICODE),
        'timeout' => 15,
    ));
    $body2     = json_decode(wp_remote_retrieve_body($resp2), true);
    $http2     = wp_remote_retrieve_response_code($resp2);

    $debug['test_segment_all'] = array(
        'http'       => $http2,
        'response'   => $body2,
        'recipients' => $body2['recipients'] ?? 0,
        'method'     => 'included_segments:All',
    );

    // Verificar si el player_id existe en OneSignal
    $player_check = null;
    if (!empty($ids[0])) {
        $check_res = wp_remote_get(
            'https://onesignal.com/api/v1/players/' . urlencode($ids[0]) . '?app_id=' . urlencode($app_id),
            array(
                'headers' => array('Authorization' => 'Basic ' . $rest_api_key),
                'timeout' => 8,
            )
        );
        if (!is_wp_error($check_res)) {
            $player_check = json_decode(wp_remote_retrieve_body($check_res), true);
        }
    }

    $debug['payload_sent']    = $payload;
    $debug['http_code']       = $http_code;
    $debug['wp_error']        = $wp_error;
    $debug['onesignal_raw']   = $body;
    $debug['player_raw']      = $player_check; // datos raw del player
    $debug['player_exists_in_onesignal'] = $player_check;
    // Detectar tipo de suscripción Safari vs Chrome
    $notif_type = isset($player_check['notification_types']) ? (int)$player_check['notification_types'] : null;
    $opted_in   = isset($player_check['opted_in']) ? (bool)$player_check['opted_in'] : null;
    $debug['is_subscribed']   = ($notif_type === 1) || ($opted_in === true);
    $debug['notif_type_raw']  = $notif_type;
    $debug['opted_in_raw']    = $opted_in;
    $debug['device_type_raw'] = $player_check['device_type'] ?? null;
    $debug['success']         = ($http_code === 200 && empty($body['errors']));

    return rest_ensure_response(array('debug' => $debug));
}

function vkx_push_auto_status($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));

    $os_settings  = get_option('onesignal_settings', array());
    $rest_api_key = isset($os_settings['app_rest_api_key']) ? trim($os_settings['app_rest_api_key']) : '';
    $config       = get_option('vk_push_auto_config', array());

    global $wpdb;
    // Contar suscriptores con player_ids
    $subscribers = (int)$wpdb->get_var(
        "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
         WHERE meta_key='onesignal_player_ids' AND meta_value NOT IN ('','a:0:{}')"
    );

    // Estado de cada evento
    $events = array('new_course','new_product','new_poll','new_bundle','cert_issued','course_complete','progress','dir_approved','dir_pending');
    $event_status = array();
    foreach ($events as $ev) {
        $event_status[$ev] = array(
            'enabled'  => !empty($config[$ev]['enabled']),
            'template' => $config[$ev]['template'] ?? '',
        );
    }

    // Verificar hooks registrados
    $hooks_ok = array(
        'new_course'      => has_action('transition_post_status', 'vk_auto_push_new_course'),
        'new_product'     => has_action('transition_post_status', 'vk_auto_push_new_product'),
        'new_bundle'      => has_action('transition_post_status', 'vk_auto_push_new_bundle'),
        'new_poll'        => has_filter('query', 'vkx_intercept_yop_poll_insert'),
        'cert_issued'     => has_action('tutor_course_complete_after', 'vk_auto_push_cert_issued'),
        'course_complete' => has_action('tutor_course_complete_after', 'vk_auto_push_course_complete'),
        'progress'        => has_action('tutor_lesson_completed_after', 'vk_auto_push_lesson_progress'),
    );

    // Verificar tabla BD
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}vk_notifications'") !== null;
    $notif_count  = $table_exists ? (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vk_notifications") : 0;

    return rest_ensure_response(array(
        'has_api_key'    => !empty($rest_api_key),
        'app_id'         => VK_ONESIGNAL_APP_ID,
        'subscribers'    => $subscribers,
        'table_exists'   => $table_exists,
        'notif_count'    => $notif_count,
        'events'         => $event_status,
        'hooks'          => $hooks_ok,
        'cron_scheduled' => (bool)wp_next_scheduled('vkx_check_new_polls_cron'),
    ));
}

/* ── POST /vk/v1/push-test-event — disparar un evento de prueba ─── */
function vkx_push_test_event($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    $body  = $req->get_json_params() ?: array();
    $event = sanitize_key($body['event'] ?? '');
    $uid   = vk_uid($req);

    $config = get_option('vk_push_auto_config', array());
    $defaults = array(
        'new_course'      => array('title'=>' Nuevo Curso',       'msg'=>'¡Nuevo curso de prueba disponible!',     'type'=>'course',      'url'=>'https://app.vidakushala.com/?open_section=courses'),
        'new_product'     => array('title'=>' Nuevo Producto',     'msg'=>'Nuevo producto de prueba disponible.',    'type'=>'product',     'url'=>'https://app.vidakushala.com/?open_section=products'),
        'new_poll'        => array('title'=>' Nueva Encuesta',     'msg'=>'Nueva encuesta de prueba disponible.',   'type'=>'poll',        'url'=>'https://app.vidakushala.com/?open_section=polls'),
        'new_bundle'      => array('title'=>' Nuevo Paquete',      'msg'=>'¡Nuevo paquete de prueba disponible!',   'type'=>'bundle',      'url'=>'https://app.vidakushala.com/?open_section=products'),
        'cert_issued'     => array('title'=>' Certificado Listo',  'msg'=>'Tu certificado de prueba está listo.',   'type'=>'cert',        'url'=>'https://app.vidakushala.com/?open_section=certificates'),
        'course_complete' => array('title'=>' Curso Completado',   'msg'=>'¡Felicidades! Completaste el curso.',    'type'=>'course_done', 'url'=>'https://app.vidakushala.com/?open_section=courses'),
        'progress'        => array('title'=>' Progreso 50%',       'msg'=>'¡Llevas 50% en el curso de prueba!',     'type'=>'progress',    'url'=>'https://app.vidakushala.com/?open_section=courses'),
    );

    if (!isset($defaults[$event])) {
        return new WP_Error('invalid_event', 'Evento no reconocido: '.$event, array('status'=>400));
    }

    $d    = $defaults[$event];
    $tpl  = $config[$event]['template'] ?? '';
    $msg  = $tpl ? str_replace(
        array('{TITLE}','{COURSE}','{PERCENT}'),
        array('Curso de Prueba','Curso de Prueba','50'),
        $tpl
    ) : $d['msg'];

    // Eventos personales → enviar solo al admin que lo prueba
    $personal_events = array('cert_issued','course_complete','progress');
    if (in_array($event, $personal_events)) {
        vk_notify_user($uid, $d['type'], $d['title'].' [TEST]', $msg, $d['url']);
        return rest_ensure_response(array('success'=>true,'sent_to'=>'personal','user_id'=>$uid,'event'=>$event));
    }

    // Eventos globales → enviar a todos
    vk_notify_all($d['type'], $d['title'].' [TEST]', $msg, $d['url']);
    return rest_ensure_response(array('success'=>true,'sent_to'=>'all','event'=>$event));
}

function vk_push_auto_config($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    $config = get_option('vk_push_auto_config', array());
    $default = array(
        'new_course'       => array('enabled'=>false,'template'=>' ¡Nuevo curso disponible! {TITLE} te espera.'),
        'new_product'      => array('enabled'=>false,'template'=>' Nuevo producto disponible: {TITLE}'),
        'new_poll'         => array('enabled'=>false,'template'=>' Nueva encuesta: {TITLE}. ¡Comparte tu opinión!'),
        'new_bundle'       => array('enabled'=>true,'template'=>' ¡Nuevo paquete disponible! {TITLE}. Ahorra accediendo a varios cursos.'),
        'cert_issued'      => array('enabled'=>false,'template'=>' ¡Felicidades! Tu certificado de {COURSE} está listo.'),
        'course_complete'  => array('enabled'=>false,'template'=>' ¡Has completado el curso {TITLE}!'),
        'task_reminder'    => array('enabled'=>false,'template'=>' Tienes {COUNT} tarea(s) pendiente(s)'),
        'progress'         => array('enabled'=>false,'template'=>' ¡Llevas {PERCENT}% en {COURSE}! Sigue así.'),
        'dir_approved'     => array('enabled'=>true, 'template'=>'¡Tu perfil "{NAME}" ha sido aprobado y ya está visible en el directorio de Vida Kushala! Puedes compartirlo con tus clientes.'),
        'dir_pending'      => array('enabled'=>true, 'template'=>'📋 {NAME} ha enviado su perfil al directorio y está pendiente de aprobación.'),
    );
    return rest_ensure_response(array('data'=>array_merge($default,$config)));
}

function vk_push_auto_toggle($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    $body = $req->get_json_params() ?: array();
    $event = isset($body['event']) ? sanitize_text_field($body['event']) : '';
    $enabled = isset($body['enabled']) ? (bool)$body['enabled'] : false;
    if (!$event) return new WP_Error('invalid','event requerido',array('status'=>400));
    $config = get_option('vk_push_auto_config', array());
    if (!isset($config[$event])) $config[$event] = array('enabled'=>false,'template'=>'');
    $config[$event]['enabled'] = $enabled;
    update_option('vk_push_auto_config', $config);
    return rest_ensure_response(array('success'=>true));
}

function vk_push_auto_template($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    $body = $req->get_json_params() ?: array();
    $event = isset($body['event']) ? sanitize_text_field($body['event']) : '';
    if (!$event) return new WP_Error('invalid','event requerido',array('status'=>400));
    // sanitize_textarea_field preserva emojis; sanitize_text_field los borra
    $template = isset($body['template']) ? sanitize_textarea_field(wp_unslash($body['template'])) : '';
    $config = get_option('vk_push_auto_config', array());
    if (!isset($config[$event])) $config[$event] = array('enabled'=>false);
    $config[$event]['template'] = $template;
    update_option('vk_push_auto_config', $config);
    return rest_ensure_response(array('success'=>true,'message'=>'Plantilla guardada correctamente'));
}


/* ===================================================================
   ENCUESTAS - YOP POLL
   Tablas: wp_yoppoll_polls, wp_yoppoll_elements, wp_yoppoll_subelements, wp_yoppoll_votes
   -------------------------------------------------------------
   elements.etype = 'question' | 'header' | 'description'
   subelements.stype = 'answer'
   votes.vote_data = JSON con array de {element_id, subelement_id, value}
=============================================================== */

function vk_polls_list($req) {
    global $wpdb; $p = $wpdb->prefix;
    $polls = $wpdb->get_results(
        "SELECT id, name, status, total_submits, added_date
         FROM {$p}yoppoll_polls
         WHERE status NOT IN ('trash','draft')
         ORDER BY added_date DESC"
    );
    $data = array();
    foreach ($polls as $poll) {
        $data[] = array(
            'id'          => (int)$poll->id,
            'name'        => $poll->name,
            'status'      => $poll->status === 'published' ? 'active' : $poll->status,
            'total_votes' => (int)$poll->total_submits,
            'description' => '',
            'added_date'  => $poll->added_date,
        );
    }
    return rest_ensure_response(array('data' => $data, 'total' => count($data)));
}

function vk_polls_debug($req) {
    global $wpdb; $p = $wpdb->prefix;
    $all = $wpdb->get_results("SELECT id, name, status, total_submits FROM {$p}yoppoll_polls");
    $statuses = $wpdb->get_col("SELECT DISTINCT status FROM {$p}yoppoll_polls");
    $elements = $wpdb->get_results("SELECT id, poll_id, etype, status, LEFT(etext,50) as etext FROM {$p}yoppoll_elements LIMIT 10");
    return rest_ensure_response(array(
        'polls_total'     => count($all),
        'distinct_status' => $statuses,
        'polls'           => $all,
        'elements_sample' => $elements,
        'last_error'      => $wpdb->last_error,
    ));
}


function vk_poll_detail($req) {
    global $wpdb; $p = $wpdb->prefix;
    $poll_id = (int)$req['id'];

    $poll = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, status, total_submits, meta_data FROM {$p}yoppoll_polls WHERE id=%d", $poll_id
    ));
    if (!$poll) return new WP_Error('not_found', 'Encuesta no encontrada', array('status'=>404));

    // Cargar preguntas (elements con etype='question')
    $elements = $wpdb->get_results($wpdb->prepare(
        "SELECT id, etext, etype, sorder, meta_data
         FROM {$p}yoppoll_elements
         WHERE poll_id=%d
         ORDER BY sorder ASC", $poll_id
    ));

    $questions = array();
    foreach ($elements as $el) {
        // etype puede ser: question-text, question-radio, question-checkbox, question-select, header, description
        if (strpos($el->etype, 'question') === false) continue;
        $meta = json_decode($el->meta_data, true) ?: array();
        // question-checkbox = multiple, question-radio = unica, question-text = texto libre
        $multiple = ($el->etype === 'question-checkbox');
        $is_text  = ($el->etype === 'question-text');

        // Cargar opciones (subelements)
        $subs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, stext, sorder, total_submits
             FROM {$p}yoppoll_subelements
             WHERE element_id=%d
             ORDER BY sorder ASC", (int)$el->id
        ));
        $options = array();
        foreach ($subs as $s) {
            $options[] = array(
                'id'    => (int)$s->id,
                'text'  => strip_tags($s->stext),
                'votes' => (int)$s->total_submits,
            );
        }
        $total_q = array_sum(array_column($options, 'votes'));

        $questions[] = array(
            'id'          => (int)$el->id,
            'text'        => strip_tags($el->etext),
            'etype'       => $el->etype,
            'multiple'    => $multiple,
            'is_text'     => $is_text,
            'options'     => $options,
            'total_votes' => $total_q,
        );
    }

    $meta = json_decode($poll->meta_data, true) ?: array();
    return rest_ensure_response(array(
        'id'          => (int)$poll->id,
        'name'        => $poll->name,
        'status'      => $poll->status,
        'total_votes' => (int)$poll->total_submits,
        'description' => isset($meta['description']) ? strip_tags($meta['description']) : '',
        'questions'   => $questions,
    ));
}

function vk_poll_vote($req) {
    global $wpdb; $p = $wpdb->prefix;
    $poll_id = (int)$req['id'];
    $uid     = vk_uid($req);  // puede ser 0 si no hay token (voto anonimo)
    $body    = $req->get_json_params() ?: array();

    // body.answers = [ {question_id: X, answer_ids: [Y, Z]} ]
    $answers = isset($body['answers']) ? $body['answers'] : array();
    if (empty($answers)) return new WP_Error('empty', 'Sin respuestas', array('status'=>400));

    $poll = $wpdb->get_row($wpdb->prepare(
        "SELECT id, status FROM {$p}yoppoll_polls WHERE id=%d", $poll_id
    ));
    if (!$poll) return new WP_Error('not_found', 'Encuesta no encontrada', array('status'=>404));
    if ($poll->status === 'closed' || $poll->status === 'ended') return new WP_Error('closed', 'Encuesta cerrada', array('status'=>403));

    // Verificar si el usuario ya voto
    if ($uid) {
        $already = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}yoppoll_votes WHERE poll_id=%d AND user_id=%d", $poll_id, $uid
        ));
        if ($already) return new WP_Error('already_voted', 'Ya votaste en esta encuesta', array('status'=>409));
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $voter_id = $uid ? 'wp_'.$uid : 'ip_'.$ip;
    $vote_data_arr = array();
    $now = current_time('mysql');

    foreach ($answers as $a) {
        $q_id  = (int)($a['question_id'] ?? 0);
        $a_ids = isset($a['answer_ids']) ? (array)$a['answer_ids'] : array();
        if (!$q_id || empty($a_ids)) continue;

        foreach ($a_ids as $sub_id) {
            $sub_id = (int)$sub_id;
            $vote_data_arr[] = array('element_id' => $q_id, 'subelement_id' => $sub_id);
            // Incrementar contador de la opcion
            $wpdb->query($wpdb->prepare(
                "UPDATE {$p}yoppoll_subelements SET total_submits=total_submits+1, modified_date=%s WHERE id=%d",
                $now, $sub_id
            ));
        }
    }

    // Guardar voto
    $wpdb->insert($p.'yoppoll_votes', array(
        'poll_id'          => $poll_id,
        'user_id'          => $uid ?: 0,
        'user_email'       => $uid ? get_userdata($uid)->user_email : '',
        'user_type'        => $uid ? 'wordpress' : 'anonymous',
        'ipaddress'        => $ip,
        'tracking_id'      => uniqid('vk_', true),
        'voter_id'         => $voter_id,
        'voter_fingerprint'=> md5($voter_id.$poll_id),
        'vote_data'        => json_encode($vote_data_arr),
        'status'           => 'valid',
        'added_date'       => $now,
    ));
    $vote_id = $wpdb->insert_id;

    // Incrementar total del poll
    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}yoppoll_polls SET total_submits=total_submits+1, modified_date=%s WHERE id=%d",
        $now, $poll_id
    ));

    // Devolver resultados actualizados
    $req_detail = new WP_REST_Request('GET');
    $req_detail->set_url_params(array('id' => $poll_id));
    $updated = vk_poll_detail($req_detail);

    return rest_ensure_response(array(
        'success'  => true,
        'vote_id'  => $vote_id,
        'message'  => 'Voto registrado',
        'results'  => $updated->get_data(),
    ));
}


/* ===============================================================
   PRODUCTOS WOOCOMMERCE + TUTOR LMS
   -------------------------------------------------------------
   Meta personalizado que el admin ingresa en el producto:
     _mp_payment_link  ? URL de Mercado Pago
   Si el producto esta vinculado a un curso (via Tutor LMS/Woo),
   se recupera automaticamente por _tutor_product_id o
   course_product_id.
=============================================================== */
function vk_products($req) {
    if (!function_exists('wc_get_products')) {
        return new WP_Error('woo_missing', 'WooCommerce no esta activo', array('status' => 500));
    }
    $search   = sanitize_text_field($req->get_param('search')   ? $req->get_param('search')   : '');
    $category = sanitize_text_field($req->get_param('category') ? $req->get_param('category') : '');
    $per_page_raw = $req->get_param('per_page');
    $per_page = min(50, max(1, (int)($per_page_raw ? $per_page_raw : 20)));

    $args = array(
        'status'   => 'publish',
        'limit'    => $per_page,
        'orderby'  => 'date',
        'order'    => 'DESC',
    );
    if ($search)   $args['s']        = $search;
    if ($category) $args['category'] = array($category);

    $products = wc_get_products($args);
    $data     = array();

    foreach ($products as $p) {
        $data[] = vk_format_product($p);
    }
    return rest_ensure_response(array('data' => $data, 'total' => count($data)));
}

function vk_product_detail($req) {
    if (!function_exists('wc_get_product')) {
        return new WP_Error('woo_missing', 'WooCommerce no esta activo', array('status' => 500));
    }
    $id = (int)$req['id'];
    $p  = wc_get_product($id);
    if (!$p || !$p->is_visible())
        return new WP_Error('not_found', 'Producto no encontrado', array('status' => 404));

    return rest_ensure_response(vk_format_product($p, true));
}

/**
 * Formatea un WC_Product para la API.
 * $full = true ? incluye descripcion completa y datos del curso vinculado.
 *
 * Estrategias para detectar el curso vinculado (en orden de prioridad):
 *  1. Meta 'course_product_id'  en el producto WC (Tutor LMS Woo integration)
 *  2. Meta '_tutor_product_id'  en el curso que apunta a este producto
 *  3. Meta '_tutor_course_product_id' en el curso
 *  4. Meta 'tutor_course_id'    guardado directamente en el producto
 *
 * La imagen y la URL de destino del producto usan los datos del curso
 * vinculado cuando el producto no tiene imagen propia.
 */
function vk_format_product($p, $full = false) {
    global $wpdb;

    $id        = $p->get_id();
    $price_raw = (float)$p->get_price();
    $img_id    = $p->get_image_id();
    // Intentar variante 'large', si no existe usar la URL original del archivo
    $img_url   = $img_id ? (wp_get_attachment_image_url($img_id, 'large') ?: wp_get_attachment_url($img_id) ?: '') : '';
    $mp_link   = get_post_meta($id, '_mp_payment_link', true);
    $paypal_link = get_post_meta($id, '_paypal_payment_link', true);

    // -- Detectar curso vinculado (multiples estrategias) --
    $linked_course_id = 0;

    // Estrategia 1: meta en el producto WC
    $linked_course_id = (int)get_post_meta($id, 'course_product_id', true);

    // Estrategia 2: meta 'tutor_course_id' en el producto
    if (!$linked_course_id)
        $linked_course_id = (int)get_post_meta($id, 'tutor_course_id', true);

    // Estrategia 3: buscar en cursos que tengan este producto como _tutor_product_id
    if (!$linked_course_id) {
        $linked_course_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_tutor_product_id' AND meta_value = %d
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type='courses' AND post_status='publish')
             LIMIT 1", $id));
    }

    // Estrategia 4: meta '_tutor_course_product_id' en el curso
    if (!$linked_course_id) {
        $linked_course_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_tutor_course_product_id' AND meta_value = %d LIMIT 1", $id));
    }

    // -- Si hay curso vinculado, usar su imagen/permalink cuando el producto no tiene --
    $course_img_url  = '';
    $course_permalink = '';
    if ($linked_course_id) {
        $c_thumb_id = get_post_thumbnail_id($linked_course_id);
        if ($c_thumb_id) $course_img_url = wp_get_attachment_image_url($c_thumb_id, 'large') ?: wp_get_attachment_url($c_thumb_id) ?: '';
        $course_permalink = get_permalink($linked_course_id);
    }

    // La imagen final: primero la del producto WC, si no la del curso
    $final_img = $img_url ?: $course_img_url;

    // El permalink final: para cursos de pago, apuntar al curso Tutor LMS, no a la pagina WC
    // Asi el boton "ver mas" lleva al curso, no a la tienda WC
    $final_permalink = $linked_course_id ? $course_permalink : get_permalink($id);

    // Categorias ? incluir siempre (lista y detalle)
    $cats = array();
    foreach ($p->get_category_ids() as $cat_id) {
        $term = get_term($cat_id, 'product_cat');
        if ($term && !is_wp_error($term) && $term->slug !== 'uncategorized')
            $cats[] = array('id'=>(int)$term->term_id,'name'=>$term->name,'slug'=>$term->slug);
    }

    $payload = array(
        'id'                => $id,
        'title'             => $p->get_name(),
        'excerpt'           => wp_strip_all_tags($p->get_short_description() ?: ''),
        'image'             => $final_img,
        'price'             => $price_raw > 0 ? '$' . number_format($price_raw, 2) : 'Gratis',
        'price_raw'         => $price_raw,
        'is_free'           => $price_raw == 0,
        'permalink'         => $final_permalink,
        'wc_permalink'      => get_permalink($id),
        'mercado_pago_link' => $mp_link ?: '',
        'paypal_link'       => $paypal_link ?: '',
        'linked_course_id'  => $linked_course_id ?: 0,
        'categories'        => $cats,
    );

    if ($full) {
        $payload['description']       = wp_kses_post($p->get_description() ?: '');
        $payload['short_description'] = wp_kses_post($p->get_short_description() ?: '');

        // Galería de imágenes del producto
        $gallery_ids  = $p->get_gallery_image_ids();
        $gallery_imgs = array();
        if ($img_id) {
            $gallery_imgs[] = array(
                'id'  => $img_id,
                'url' => $img_url,
                'alt' => get_post_meta($img_id, '_wp_attachment_image_alt', true) ?: $p->get_name(),
            );
        }
        foreach ($gallery_ids as $gid) {
            $gurl = wp_get_attachment_image_url($gid, 'large') ?: wp_get_attachment_url($gid) ?: '';
            if ($gurl) {
                $gallery_imgs[] = array(
                    'id'  => $gid,
                    'url' => $gurl,
                    'alt' => get_post_meta($gid, '_wp_attachment_image_alt', true) ?: $p->get_name(),
                );
            }
        }
        $payload['gallery'] = $gallery_imgs;

        // Datos completos del curso vinculado
        if ($linked_course_id) {
            $course = get_post($linked_course_id);
            if ($course) {
                $lessons  = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} lp
                     INNER JOIN {$wpdb->posts} tp ON tp.ID = lp.post_parent
                     WHERE lp.post_type = 'lesson' AND tp.post_parent = %d
                       AND tp.post_type = 'topics' AND lp.post_status = 'publish'",
                    $linked_course_id));

                $c_thumb_id = get_post_thumbnail_id($linked_course_id);
                $payload['linked_course'] = array(
                    'id'             => $linked_course_id,
                    'post_title'     => $course->post_title,
                    'total_lessons'  => $lessons,
                    'featured_image' => $c_thumb_id ? wp_get_attachment_image_url($c_thumb_id, 'large') : '',
                    'permalink'      => get_permalink($linked_course_id),
                    'excerpt'        => wp_strip_all_tags($course->post_excerpt ?: ''),
                );
            }
        }
    }
    return $payload;
}

/* ===============================================
   CAMPOS PERSONALIZADOS EN EL PRODUCTO WC
   1. Enlace Mercado Pago  (_mp_payment_link)
   2. Course ID manual     (tutor_course_id)
      ? Permite vincular manualmente el curso de Tutor LMS
        cuando la deteccion automatica no funciona.
        Ejemplo: ingresa 2905 para el curso de pago.
=============================================== */
add_action('woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group">';

    woocommerce_wp_text_input(array(
        'id'          => '_mp_payment_link',
        'label'       => '? Enlace Mercado Pago',
        'description' => 'URL externa de pago de Mercado Pago (ej: https://mpago.la/...). Aparecera como boton "Pagar en linea" en la app.',
        'desc_tip'    => true,
        'placeholder' => 'https://mpago.la/...',
    ));

    woocommerce_wp_text_input(array(
        'id'          => '_paypal_payment_link',
        'label'       => '🌐 Enlace PayPal',
        'description' => 'URL de PayPal para pagar este producto. Aparecerá como opción adicional en la app.',
        'desc_tip'    => true,
        'placeholder' => 'https://www.paypal.com/...',
    ));

    woocommerce_wp_text_input(array(
        'id'          => 'tutor_course_id',
        'label'       => '? Course ID (Tutor LMS)',
        'description' => 'ID del curso de Tutor LMS vinculado a este producto. Ej: 2905. La app abrira el curso directamente al comprar.',
        'desc_tip'    => true,
        'placeholder' => 'ej: 2905',
        'type'        => 'number',
    ));

    echo '</div>';
});
add_action('woocommerce_process_product_meta', function ($post_id) {
    $mp  = isset($_POST['_mp_payment_link']) ? esc_url_raw($_POST['_mp_payment_link']) : '';
    $paypal = isset($_POST['_paypal_payment_link']) ? esc_url_raw($_POST['_paypal_payment_link']) : '';
    $cid = isset($_POST['tutor_course_id'])  ? absint($_POST['tutor_course_id'])        : 0;
    update_post_meta($post_id, '_mp_payment_link', $mp);
    update_post_meta($post_id, '_paypal_payment_link', $paypal);
    if ($cid) update_post_meta($post_id, 'tutor_course_id', $cid);
    else delete_post_meta($post_id, 'tutor_course_id');
});

/* ===================================================================
   FACEBOOK ? CALLBACK DE ELIMINACION DE DATOS
   App ID de Tutor LMS: 1360091046174576
   -----------------------------------------------------------------
   Facebook requiere este endpoint para aprobar Facebook Login.
   Cuando un usuario elimina la app desde Facebook, FB hace POST aqui
   con un "signed_request" firmado.

   URL a ingresar en el panel de Facebook Developers:
     ? App 1360091046174576 ? Configuracion basica
     ? Eliminacion de datos de usuario
     ? Seleccionar: "URL de callback de eliminacion de datos"
     ? Ingresar: https://vidakushala.com/wp-json/vk/v1/facebook-delete

   Flujo:
     1. Facebook envia POST con signed_request
     2. Verificamos la firma con el App Secret
     3. Obtenemos el facebook_user_id
     4. Buscamos al usuario WP por _facebook_id y lo eliminamos (o marcamos)
     5. Respondemos con JSON: { url, confirmation_code }
        Facebook mostrara esa URL al usuario para verificar el estado
=================================================================== */
function vk_facebook_delete($req) {
    $app_secret = defined('FACEBOOK_APP_SECRET') ? FACEBOOK_APP_SECRET : get_option('vk_fb_app_secret', '');

    // -- Obtener signed_request (POST body o query param) --
    $signed_request = $req->get_param('signed_request');
    if (!$signed_request) {
        // GET puede traerlo como query string (para pruebas)
        $signed_request = isset($_GET['signed_request']) ? sanitize_text_field($_GET['signed_request']) : '';
    }

    // Si no hay signed_request (acceso directo del navegador) ? mostrar pagina informativa
    if (!$signed_request) {
        // Redirigir a la pagina de instrucciones visible para el usuario
        wp_redirect('https://app.vidakushala.com/eliminar-datos.html');
        exit;
    }

    if (!$app_secret) {
        return rest_ensure_response(array(
            'error' => 'App Secret no configurado en el servidor'
        ));
    }

    // -- Parsear y verificar signed_request --
    $parts = explode('.', $signed_request, 2);
    if (count($parts) !== 2) {
        return new WP_Error('bad_request', 'signed_request malformado', array('status' => 400));
    }

    $encoded_sig  = $parts[0];
    $payload_b64  = $parts[1];

    // Decodificar firma (base64url)
    $sig = base64_decode(strtr($encoded_sig, '-_', '+/') . str_repeat('=', (4 - strlen($encoded_sig) % 4) % 4));

    // Verificar HMAC-SHA256
    $expected_sig = hash_hmac('sha256', $payload_b64, $app_secret, true);
    if (!hash_equals($expected_sig, $sig)) {
        return new WP_Error('bad_signature', 'Firma invalida', array('status' => 403));
    }

    // Decodificar payload
    $data = json_decode(base64_decode(strtr($payload_b64, '-_', '+/') . str_repeat('=', (4 - strlen($payload_b64) % 4) % 4)), true);
    if (empty($data['user_id'])) {
        return new WP_Error('no_user_id', 'user_id no encontrado en el payload', array('status' => 400));
    }

    $fb_user_id = sanitize_text_field($data['user_id']);

    // -- Buscar al usuario WP por su Facebook ID --
    $users = get_users(array(
        'meta_key'   => '_facebook_id',
        'meta_value' => $fb_user_id,
        'number'     => 1,
    ));

    $deletion_status = 'not_found';
    $wp_user_id      = 0;

    if (!empty($users)) {
        $wp_user = $users[0];
        $wp_user_id = $wp_user->ID;

        // Opciones de eliminacion (elige una):
        // OPCION A ? Eliminar completamente al usuario de WordPress
        // require_once(ABSPATH . 'wp-admin/includes/user.php');
        // wp_delete_user($wp_user_id);

        // OPCION B (recomendada) ? Marcar al usuario para eliminacion
        // y anonimizar sus datos, conservando registros fiscales
        update_user_meta($wp_user_id, '_deletion_requested', current_time('mysql'));
        update_user_meta($wp_user_id, '_deletion_reason',    'facebook_callback');
        // Anonimizar datos identificativos
        wp_update_user(array(
            'ID'           => $wp_user_id,
            'user_email'   => 'deleted_fb_' . $fb_user_id . '_' . $wp_user_id . '@deleted.vidakushala.com',
            'display_name' => 'Usuario eliminado',
            'first_name'   => '',
            'last_name'    => '',
        ));
        delete_user_meta($wp_user_id, '_facebook_id');

        $deletion_status = 'deleted';
    }

    // -- Generar codigo de confirmacion unico --
    $confirmation_code = 'M3C_' . strtoupper(substr(md5($fb_user_id . time()), 0, 12));

    // Guardar log de la eliminacion
    error_log('[VK FB Delete] fb_user_id=' . $fb_user_id . ' | wp_user_id=' . $wp_user_id . ' | status=' . $deletion_status . ' | code=' . $confirmation_code);

    // -- Responder a Facebook con el formato requerido --
    // Facebook espera: { "url": "...", "confirmation_code": "..." }
    // La URL debe ser una pagina donde el usuario pueda ver el estado de su solicitud
    $status_url = 'https://app.vidakushala.com/eliminar-datos.html?code=' . urlencode($confirmation_code) . '&status=' . $deletion_status;

    return rest_ensure_response(array(
        'url'               => $status_url,
        'confirmation_code' => $confirmation_code,
    ));
}

/* ===============================================
   WEBHOOK MERCADO PAGO
   Cuando el pago se confirma ? orden WC "completed"
   ? Tutor LMS inscribe al alumno automaticamente
   (WooCommerce-Tutor LMS integration ya maneja esto
   al cambiar estado a "completed").

   Endpoint: POST /wp-json/vk/v1/mp-webhook
   Configurar en el panel de Mercado Pago:
     Notificacion ? URL de tu sitio + este endpoint
=============================================== */
function vk_mp_webhook($req) {
    // Mercado Pago envia: { "type": "payment", "data": { "id": "12345678" } }
    $type_raw = $req->get_param('type');
    $topic_raw = $req->get_param('topic');
    $type    = sanitize_text_field($type_raw ? $type_raw : ($topic_raw ? $topic_raw : ''));
    $data    = $req->get_param('data');
    $pay_id  = '';

    if ($type === 'payment' && is_array($data) && !empty($data['id'])) {
        $pay_id = sanitize_text_field($data['id']);
    } elseif (!empty($req->get_param('id'))) {
        // Formato IPN legacy
        $pay_id = sanitize_text_field($req->get_param('id'));
    }

    if (!$pay_id) {
        return rest_ensure_response(array('ok' => false, 'msg' => 'No payment id'));
    }

    // Consultar la API de Mercado Pago para verificar el pago
    $mp_access_token = defined('MP_ACCESS_TOKEN') ? MP_ACCESS_TOKEN : get_option('vk_mp_access_token', '');
    if (!$mp_access_token) {
        return rest_ensure_response(array('ok' => false, 'msg' => 'MP Access Token no configurado'));
    }

    $mp_res = wp_remote_get('https://api.mercadopago.com/v1/payments/' . $pay_id, array(
        'headers' => array('Authorization' => 'Bearer ' . $mp_access_token),
        'timeout' => 15,
    ));
    if (is_wp_error($mp_res)) {
        return rest_ensure_response(array('ok' => false, 'msg' => 'Error al consultar MP'));
    }

    $mp_body = json_decode(wp_remote_retrieve_body($mp_res), true);
    $status  = isset($mp_body['status']) ? $mp_body['status'] : '';

    if ($status !== 'approved') {
        return rest_ensure_response(array('ok' => false, 'msg' => 'Pago no aprobado: ' . $status));
    }

    // Buscar la orden WC por external_reference (debe ser el order_id)
    $external_ref = isset($mp_body['external_reference']) ? sanitize_text_field($mp_body['external_reference']) : '';
    if (!$external_ref) {
        // Alternativamente buscar por metadata
        $external_ref = isset($mp_body['metadata']['order_id']) ? sanitize_text_field($mp_body['metadata']['order_id']) : '';
    }

    if ($external_ref && function_exists('wc_get_order')) {
        $order = wc_get_order((int)$external_ref);
        if ($order && !in_array($order->get_status(), array('completed', 'refunded'))) {
            $order->payment_complete($pay_id);
            $order->update_status('completed', 'Pago confirmado por Mercado Pago. Payment ID: ' . $pay_id);
            // Tutor LMS enrola automaticamente al cambiar a completed (via WC integration)
        }
    }

    // Log para auditoria
    error_log('[VK MP Webhook] Payment ' . $pay_id . ' approved. Order: ' . $external_ref);

    return rest_ensure_response(array('ok' => true, 'payment_id' => $pay_id, 'order' => $external_ref));
}

/* ===============================================
   CONFIGURACION: MP Access Token en WP Admin
   (Admin ? Ajustes ? VK MP Settings)
=============================================== */
add_action('admin_menu', function () {
    add_options_page('DM Plus ? Mercado Pago', 'DM Plus MP', 'manage_options', 'vk-mp-settings', 'vk_mp_settings_page');
    // Pagina dedicada para gestionar links de pago de cursos, paquetes y productos
    add_menu_page(
        'DM Plus ? Links de Pago',
        'DM Plus Links',
        'edit_posts',
        'dm-payment-links',
        'vk_payment_links_page',
        'dashicons-cart',
        58
    );
});
add_action('admin_init', function () {
    register_setting('vk_mp_settings', 'vk_mp_access_token', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('vk_mp_settings', 'vk_fb_app_id',       array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('vk_mp_settings', 'vk_fb_app_secret',   array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('vk_mp_settings', 'vk_paypal_link', array('sanitize_callback' => 'esc_url_raw'));
    register_setting('vk_mp_settings', 'vk_paypal_link',     array('sanitize_callback' => 'esc_url_raw'));
});
function vk_mp_settings_page() {
    $fb_app_id     = get_option('vk_fb_app_id',     '1360091046174576');
    $fb_app_secret = get_option('vk_fb_app_secret', '');
    $mp_token      = get_option('vk_mp_access_token','');
    $paypal_link   = get_option('vk_paypal_link', '');
    $paypal_link   = get_option('vk_paypal_link','');
    ?>
    <div class="wrap">
      <h1>?? DM Plus ? Configuracion de integraciones</h1>

      <form method="post" action="options.php">
        <?php settings_fields('vk_mp_settings'); ?>

        <h2 style="margin-top:1.5rem">? Facebook Login</h2>
        <p style="color:#666;margin-bottom:1rem">
          App ID actual de Tutor LMS: <strong>1360091046174576</strong><br>
          Endpoint de eliminacion de datos (Facebook panel):
          <code><?php echo esc_url(rest_url('vk/v1/facebook-delete')); ?></code>
        </p>
        <table class="form-table">
          <tr>
            <th><label for="vk_fb_app_id">App ID de Facebook</label></th>
            <td>
              <input type="text" id="vk_fb_app_id" name="vk_fb_app_id"
                value="<?php echo esc_attr($fb_app_id); ?>"
                class="regular-text" placeholder="1360091046174576" />
              <p class="description">El App ID de la app de Facebook ? Tutor LMS ya usa <strong>1360091046174576</strong></p>
            </td>
          </tr>
          <tr>
            <th><label for="vk_fb_app_secret">App Secret de Facebook</label></th>
            <td>
              <input type="password" id="vk_fb_app_secret" name="vk_fb_app_secret"
                value="<?php echo esc_attr($fb_app_secret); ?>"
                class="regular-text" placeholder="????????????????" />
              <p class="description">
                Encuentralo en: <a href="https://developers.facebook.com/apps/1360091046174576/settings/basic/" target="_blank">
                Facebook Developers ? App 1360091046174576 ? Configuracion basica ? Clave secreta</a>
              </p>
            </td>
          </tr>
        </table>

        <h2 style="margin-top:2rem">? Mercado Pago</h2>
        <table class="form-table">
          <tr>
            <th><label for="vk_mp_access_token">Access Token (produccion)</label></th>
            <td>
              <input type="text" id="vk_mp_access_token" name="vk_mp_access_token"
                value="<?php echo esc_attr($mp_token); ?>"
                class="regular-text" placeholder="APP_USR-..." />
              <p class="description">
                Obtenlo en <a href="https://www.mercadopago.com/developers/panel" target="_blank">Mercado Pago Developers</a>.<br>
                Webhook URL: <code><?php echo esc_url(rest_url('vk/v1/mp-webhook')); ?></code>
              </p>
            </td>
          </tr>
        </table>

        <h2 style="margin-top:2rem">🌐 PayPal</h2>
        <table class="form-table">
          <tr>
            <th><label for="vk_paypal_link">Enlace de Pago PayPal</label></th>
            <td>
              <input type="url" id="vk_paypal_link" name="vk_paypal_link"
                value="<?php echo esc_attr($paypal_link); ?>"
                class="regular-text" placeholder="https://www.paypal.com/..." />
              <p class="description">
                URL de enlace de PayPal (puede ser un Pay Button, Donate Button, o Hosted Button ID).<br>
                Este enlace se usará como opción alternativa de pago para cursos, paquetes y productos.<br>
                Obtén tu enlace en: <a href="https://www.paypal.com/buttons" target="_blank">PayPal Buttons Manager</a> o <a href="https://www.paypal.com/paypalme" target="_blank">PayPal.Me</a>
              </p>
            </td>
          </tr>
        </table>

        <?php submit_button('Guardar configuracion'); ?>
      </form>

      <hr>
      <h2>? URLs de Facebook (campo "Eliminacion de datos")</h2>
      <table class="widefat" style="max-width:700px">
        <tr><th>Campo en Facebook Developers</th><th>URL a ingresar</th></tr>
        <tr>
          <td><strong>URL de callback de eliminacion de datos</strong><br><small>(opcion correcta para la validacion)</small></td>
          <td><code><?php echo esc_url(rest_url('vk/v1/facebook-delete')); ?></code></td>
        </tr>
        <tr>
          <td>Politica de privacidad</td>
          <td><code>https://app.vidakushala.com/politica-de-privacidad.html</code></td>
        </tr>
        <tr>
          <td>Terminos de servicio</td>
          <td><code>https://app.vidakushala.com/terminos-de-servicio.html</code></td>
        </tr>
        <tr>
          <td>Dominio de la app</td>
          <td><code>vidakushala.com</code> y <code>app.vidakushala.com</code></td>
        </tr>
      </table>
    </div>
    <?php
}

/* ===============================================
   PROXY TUTOR
=============================================== */
/* ═══ INSCRIPCION DIRECTA ═══
   POST /vk/v1/enroll-course  {course_id: N}
═══════════════════════════════════════════ */
function vk_enroll_course($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized','Token requerido',array('status'=>401));
    $body      = $req->get_json_params() ?: array();
    $course_id = (int)(isset($body['course_id']) ? $body['course_id'] : $req->get_param('course_id'));
    if (!$course_id) return new WP_Error('missing','course_id requerido',array('status'=>400));
    $post = get_post($course_id);
    if (!$post || $post->post_type !== 'courses')
        return new WP_Error('not_found','Curso no encontrado',array('status'=>404));

    global $wpdb;

    // Verificar si ya está inscrito (cualquier status válido)
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT ID, post_status FROM {$wpdb->posts}
         WHERE post_type='tutor_enrolled' AND post_parent=%d AND post_author=%d
         ORDER BY ID DESC LIMIT 1", $course_id, $uid));
    if ($existing) {
        // En Tutor LMS, el estado correcto para una inscripción activa es 'completed'
        if (!in_array($existing->post_status, array('completed','approved','active'))) {
            $wpdb->update($wpdb->posts, array('post_status'=>'completed'), array('ID'=>(int)$existing->ID));
        }
        return rest_ensure_response(array(
            'success'=>true,'enrolled'=>true,'already'=>true,
            'enroll_status'=>'completed','course_id'=>$course_id));
    }

    $feat_img = get_the_post_thumbnail_url($course_id, 'medium') ?: '';

    // Método 1: Usar tutor_utils()->do_enroll() — Tutor LMS v1/v2
    if (function_exists('tutor_utils')) {
        wp_set_current_user($uid);
        $result = tutor_utils()->do_enroll($course_id, 0, $uid);
        if ($result && !is_wp_error($result)) {
            // Actualizar metadatos para asegurar visibilidad del contenido
            vk_set_enrollment_meta($course_id, $uid, $result);
            return rest_ensure_response(array('success'=>true,'enrolled'=>true,'method'=>'tutor_utils','course_id'=>$course_id,'enrolled_id'=>$result,'course_title'=>$post->post_title,'featured_image'=>$feat_img));
        }
    }

    // Método 2: Usar tutor_do_enroll() — función global alternativa
    if (function_exists('tutor_do_enroll')) {
        wp_set_current_user($uid);
        $result2 = tutor_do_enroll($course_id, $uid);
        if ($result2 && !is_wp_error($result2)) {
            vk_set_enrollment_meta($course_id, $uid, $result2);
            return rest_ensure_response(array('success'=>true,'enrolled'=>true,'method'=>'tutor_do_enroll','course_id'=>$course_id,'enrolled_id'=>$result2,'course_title'=>$post->post_title,'featured_image'=>$feat_img));
        }
    }

    // Método 3: Inserción directa compatible con Tutor LMS
    $eid = wp_insert_post(array(
        'post_type'   => 'tutor_enrolled',
        'post_status' => 'completed', // OBLIGATORIO: Tutor LMS usa 'completed' para inscripciones activas
        'post_author' => $uid,
        'post_parent' => $course_id,
        'post_title'  => 'Enrolled',
        'post_date'   => current_time('mysql'),
        'post_date_gmt' => current_time('mysql', 1),
        'comment_status' => 'open',
    ));
    if (is_wp_error($eid))
        return new WP_Error('enroll_failed',$eid->get_error_message(),array('status'=>500));

    // Metadatos necesarios para que Tutor LMS reconozca la inscripción
    vk_set_enrollment_meta($course_id, $uid, $eid);

    // Disparar hooks de Tutor LMS para procesar la inscripción completamente
    do_action('tutor_after_enroll', $course_id, $eid, $uid);
    do_action('tutor_enrolled_in_a_course', $uid, $course_id);

    return rest_ensure_response(array(
        'success'        => true,
        'enrolled'       => true,
        'method'         => 'direct',
        'enrolled_id'    => $eid,
        'course_id'      => $course_id,
        'course_title'   => $post->post_title,
        'featured_image' => $feat_img,
    ));
}

/* ═══ PREINSCRIPCIÓN GRATUITA (acceso a lecciones preview) ═══
   POST /vk/v1/preview-enroll  {course_id: N}
   Crea una inscripción con status 'preview' que permite acceder a lecciones gratuitas
   y registrar progreso, sin desbloquear el contenido de pago.
═══════════════════════════════════════════════════════════════ */
function vk_preview_enroll($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
    $body      = $req->get_json_params() ?: array();
    $course_id = (int)(isset($body['course_id']) ? $body['course_id'] : $req->get_param('course_id'));
    if (!$course_id) return new WP_Error('missing', 'course_id requerido', array('status' => 400));
    $post = get_post($course_id);
    if (!$post || $post->post_type !== 'courses')
        return new WP_Error('not_found', 'Curso no encontrado', array('status' => 404));

    global $wpdb;

    // Si ya está inscrito con cualquier status, devolver el estado actual
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT ID, post_status FROM {$wpdb->posts}
         WHERE post_type='tutor_enrolled' AND post_parent=%d AND post_author=%d
         ORDER BY ID DESC LIMIT 1", $course_id, $uid));
    if ($existing) {
        $is_full = in_array($existing->post_status, array('completed', 'approved', 'active'));
        return rest_ensure_response(array(
            'success'      => true,
            'already'      => true,
            'is_preview'   => !$is_full,
            'is_full'      => $is_full,
            'course_title' => $post->post_title,
            'course_id'    => $course_id,
        ));
    }

    // Crear inscripción con status 'preview'
    $eid = wp_insert_post(array(
        'post_type'      => 'tutor_enrolled',
        'post_status'    => 'preview',
        'post_author'    => $uid,
        'post_parent'    => $course_id,
        'post_title'     => 'Preview Enrolled',
        'post_date'      => current_time('mysql'),
        'post_date_gmt'  => current_time('mysql', 1),
        'comment_status' => 'open',
    ));
    if (is_wp_error($eid))
        return new WP_Error('enroll_failed', $eid->get_error_message(), array('status' => 500));

    // Metadatos mínimos para que el sistema reconozca el acceso a lecciones preview
    update_post_meta($eid, '_tutor_enrolled_by', 'vk_preview');
    update_user_meta($uid, '_tutor_course_enrolled_' . $course_id, 1);
    wp_cache_delete($uid, 'user_meta');
    clean_user_cache($uid);

    return rest_ensure_response(array(
        'success'      => true,
        'enrolled'     => true,
        'is_preview'   => true,
        'is_full'      => false,
        'enrolled_id'  => $eid,
        'course_id'    => $course_id,
        'course_title' => $post->post_title,
    ));
}

/* ═══ HELPER: Metadatos de inscripción ═══
   Garantiza que la inscripción sea visible tanto en la App como en el sitio WordPress
═══════════════════════════════════════════ */
function vk_set_enrollment_meta($course_id, $uid, $enrolled_id) {
    global $wpdb;

    // 1. Meta en el post de inscripción
    if ($enrolled_id && is_numeric($enrolled_id)) {
        update_post_meta((int)$enrolled_id, '_tutor_enrolled_by', 'vk_api');
        // Asegurar que el post_status sea 'completed' (Requisito estricto de Tutor LMS)
        $wpdb->update(
            $wpdb->posts,
            array('post_status' => 'completed'),
            array('ID' => (int)$enrolled_id, 'post_type' => 'tutor_enrolled')
        );
    }

    // 2. Usermeta para Tutor LMS v1 — permite acceso al contenido en WordPress
    update_user_meta($uid, '_tutor_course_enrolled_' . $course_id, 1);
    update_user_meta($uid, '_tutor_course_' . $course_id . '_enrolled', $enrolled_id ?: 1);

    // 3. Usermeta adicional que Tutor LMS Pro / v2 usa para verificar acceso
    update_user_meta($uid, 'course_enroll_status_' . $course_id, 'enrolled');

    // 4. Actualizar el contador de estudiantes del curso en postmeta
    $current_count = (int) get_post_meta($course_id, '_tutor_course_students_count', true);
    update_post_meta($course_id, '_tutor_course_students_count', $current_count + 1);

    // 5. Limpiar caché de Tutor LMS para que el sitio web refleje la inscripción
    if (function_exists('tutor_utils')) {
        // Limpiar caché del curso si Tutor tiene método para eso
        if (method_exists(tutor_utils(), 'get_enrolments')) {
            wp_cache_delete('tutor_enrolments_' . $uid);
        }
    }

    // 6. Limpiar caché de objeto de WordPress
    wp_cache_delete($uid, 'user_meta');
    wp_cache_delete('user_meta_' . $uid);
    clean_user_cache($uid);
}

/* ═══ DEBUG INSCRIPCION ═══
   GET /vk/v1/debug-enroll/{course_id}
   Muestra el post_status del tutor_enrolled en BD
═══════════════════════════════════════════ */
function vk_debug_enroll($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized','Token requerido',array('status'=>401));
    $course_id = (int)$req['id'];
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_status, post_date FROM {$wpdb->posts}
         WHERE post_type='tutor_enrolled' AND post_parent=%d AND post_author=%d
         ORDER BY ID DESC", $course_id, $uid));
    $all = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT post_status FROM {$wpdb->posts}
         WHERE post_type='tutor_enrolled' AND post_author=%d", $uid));
    return rest_ensure_response(array(
        'uid'=>$uid,'course_id'=>$course_id,
        'enrollments'=>$rows,
        'all_statuses_this_user'=>$all,
        'tutor_utils_exists'=>function_exists('tutor_utils'),
    ));
}


function vk_proxy($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
    wp_set_current_user($uid);

    $path     = '/' . ltrim($req->get_param('path'), '/');
    $method   = strtoupper($req->get_method());
    $internal = new WP_REST_Request($method, '/tutor/v1' . $path);
    $params   = $req->get_query_params();
    unset($params['vk_token'], $params['_locale']);
    foreach ($params as $k => $v) $internal->set_param($k, $v);
    if (in_array($method, array('POST', 'PUT', 'PATCH'))) {
        $body = $req->get_json_params();
        if (is_array($body)) foreach ($body as $k => $v) $internal->set_param($k, $v);
    }
    $response = rest_get_server()->dispatch($internal);
    return rest_ensure_response(rest_get_server()->response_to_data($response, false));
}

/* ===============================================
   DEBUG
=============================================== */
function vk_debug_answer($req) {
    global $wpdb;
    $id   = (int)$req['id'];
    $cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}tutor_quiz_question_answers");
    $row  = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tutor_quiz_question_answers WHERE answer_id=%d", $id), ARRAY_A);
    return rest_ensure_response(array('columns' => $cols, 'row' => $row));
}

/* ===============================================
   CAMPO _vk_payment_link EN CURSOS Y BUNDLES
   Aparece en el editor de Tutor LMS (post_type=courses
   y course-bundle). El admin pega el link de pago externo.
=============================================== */
add_action('add_meta_boxes', function () {
    $types = array('courses', 'course-bundle');
    foreach ($types as $type) {
        add_meta_box(
            'vk_payment_link_box',
            '💳 Links de Pago (App DM Plus)',
            'vk_payment_link_box_html',
            $type,
            'side',
            'high'
        );
    }
});
function vk_payment_link_box_html($post) {
    $mp_val     = get_post_meta($post->ID, '_vk_payment_link',      true);
    $paypal_val = get_post_meta($post->ID, '_paypal_payment_link',   true);
    wp_nonce_field('vk_payment_link_save', 'vk_payment_link_nonce');
    ?>
    <div style="margin-bottom:12px">
        <label style="display:block;font-size:12px;font-weight:600;color:#1a1a1a;margin-bottom:4px">
            💳 Mercado Pago
        </label>
        <input type="url" name="vk_payment_link"
               value="<?php echo esc_attr($mp_val); ?>"
               style="width:100%;padding:6px 8px;border:1.5px solid <?php echo $mp_val ? '#2d9e68' : '#ddd'; ?>;border-radius:5px;font-size:12px;box-sizing:border-box"
               placeholder="https://mpago.la/..." />
        <p style="margin:3px 0 0;font-size:11px;color:#888">Aparece como botón azul en la app.</p>
    </div>
    <div>
        <label style="display:block;font-size:12px;font-weight:600;color:#1a1a1a;margin-bottom:4px">
            🌐 PayPal
        </label>
        <input type="url" name="vk_paypal_payment_link"
               value="<?php echo esc_attr($paypal_val); ?>"
               style="width:100%;padding:6px 8px;border:1.5px solid <?php echo $paypal_val ? '#0070ba' : '#ddd'; ?>;border-radius:5px;font-size:12px;box-sizing:border-box"
               placeholder="https://paypal.me/..." />
        <p style="margin:3px 0 0;font-size:11px;color:#888">Aparece como botón PayPal en la app.</p>
    </div>
    <?php if (!$mp_val && !$paypal_val): ?>
    <p style="margin:10px 0 0;font-size:11px;color:#b45309;background:#fef3c7;padding:6px 8px;border-radius:4px">
        ⚠️ Sin links configurados. El usuario no verá botones de pago.
    </p>
    <?php endif; ?>
    <?php
}
add_action('save_post', function ($post_id) {
    if (!isset($_POST['vk_payment_link_nonce'])) return;
    if (!wp_verify_nonce($_POST['vk_payment_link_nonce'], 'vk_payment_link_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Guardar Mercado Pago
    $mp = isset($_POST['vk_payment_link']) ? esc_url_raw(trim($_POST['vk_payment_link'])) : '';
    if ($mp) update_post_meta($post_id, '_vk_payment_link', $mp);
    else     delete_post_meta($post_id, '_vk_payment_link');

    // Guardar PayPal
    $paypal = isset($_POST['vk_paypal_payment_link']) ? esc_url_raw(trim($_POST['vk_paypal_payment_link'])) : '';
    if ($paypal) update_post_meta($post_id, '_paypal_payment_link', $paypal);
    else         delete_post_meta($post_id, '_paypal_payment_link');
});

/* ===============================================
   PAQUETES DE CURSOS (course-bundle) ? PUBLICOS
=============================================== */
function vk_public_bundles($req) {
    global $wpdb;
    $search = sanitize_text_field($req->get_param('search') ? $req->get_param('search') : '');

    $where = "WHERE p.post_type='course-bundle' AND p.post_status='publish'";
    if ($search)
        $where .= $wpdb->prepare(" AND p.post_title LIKE %s", '%' . $wpdb->esc_like($search) . '%');

    $rows = $wpdb->get_results(
        "SELECT p.ID AS id, p.post_title AS title, p.post_excerpt AS excerpt,
                pm.meta_value AS thumb_id
         FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_thumbnail_id'
         $where ORDER BY p.post_date DESC LIMIT 30"
    );

    if (empty($rows)) return rest_ensure_response(array('data' => array(), 'total' => 0));

    $data = array();
    foreach ($rows as $b) {
        $thumb     = $b->thumb_id ? wp_get_attachment_image_url((int)$b->thumb_id, 'medium') : '';
        $price_raw = (float)(get_post_meta((int)$b->id, '_vk_bundle_price', true)
                    ?: get_post_meta((int)$b->id, 'bundle_price', true)
                    ?: 0);
        $pay_link    = get_post_meta((int)$b->id, '_vk_payment_link',    true) ?: '';
        $paypal_link = get_post_meta((int)$b->id, '_paypal_payment_link', true) ?: '';

        // Contar cursos en el bundle
        // Meta key confirmada: 'bundle-course-ids' (string CSV: "5107,2905,3099")
        $ids_raw    = get_post_meta((int)$b->id, 'bundle-course-ids', true);
        $course_ids = array();
        if($ids_raw){
            foreach(explode(',', $ids_raw) as $cid){
                $cid = (int)trim($cid);
                if($cid > 0) $course_ids[] = $cid;
            }
        }
        $num_courses = count($course_ids);
        // Precio confirmado: tutor_course_price (regular) / tutor_course_sale_price (sale)
        $b_regular = (float)get_post_meta((int)$b->id, 'tutor_course_price', true);
        $b_sale    = (float)get_post_meta((int)$b->id, 'tutor_course_sale_price', true);
        $price_raw = ($b_sale > 0) ? $b_sale : $b_regular;

        // Contar lecciones totales del bundle para la lista
        $bl_lessons = 0;
        if(is_array($course_ids)){
            foreach($course_ids as $bcid){
                global $wpdb;
                $bl_lessons += (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->posts} t ON t.ID=p.post_parent
                     WHERE p.post_type='lesson' AND p.post_status='publish'
                     AND t.post_type='topics' AND t.post_parent=%d", (int)$bcid));
            }
        }
        $data[] = array(
            'id'             => (int)$b->id,
            'post_title'     => $b->title,
            'excerpt'        => wp_strip_all_tags($b->excerpt ? $b->excerpt : ''),
            'featured_image' => $thumb ?: '',
            'num_courses'    => $num_courses,
            'total_lessons'  => $bl_lessons,
            'price'          => $price_raw > 0 ? '$' . number_format($price_raw, 2) : 'Gratis',
            'regular_price'  => ($b_sale > 0 && $b_regular > 0) ? '$' . number_format($b_regular, 2) : '',
            'discount_percent'=> ($b_sale > 0 && $b_regular > 0) ? round((1-$b_sale/$b_regular)*100,2) : 0,
            'is_free'        => $price_raw == 0,
            'payment_link'   => $pay_link,
            'paypal_link'    => $paypal_link,
        );
    }
    return rest_ensure_response(array('data' => $data, 'total' => count($data)));
}

function vk_public_bundle_detail($req) {
    $id   = (int)$req['id'];
    $post = get_post($id);
    if (!$post || $post->post_type !== 'course-bundle' || $post->post_status !== 'publish')
        return new WP_Error('not_found', 'Paquete no encontrado', array('status' => 404));

    $thumb_id  = get_post_thumbnail_id($id);
    $thumb     = $thumb_id ? wp_get_attachment_image_url((int)$thumb_id, 'large') : '';
    // price_raw placeholder ? se reemplaza mas abajo con los valores reales
    $price_raw = 0;
    $pay_link    = get_post_meta($id, '_vk_payment_link',    true) ?: '';
    $paypal_link = get_post_meta($id, '_paypal_payment_link', true) ?: '';

    // Cursos dentro del bundle
    // Meta key confirmada: 'bundle-course-ids' = "5107,2905,3099" (CSV string)
    $ids_raw    = get_post_meta($id, 'bundle-course-ids', true);
    $course_ids = array();
    if($ids_raw){
        foreach(explode(',', $ids_raw) as $cid){
            $cid = (int)trim($cid);
            if($cid > 0) $course_ids[] = $cid;
        }
    }
    $courses          = array();
    $total_lessons    = 0;
    $total_quizzes    = 0;
    $total_duration   = 0; // segundos
    $total_resources  = 0;

    if (is_array($course_ids)) {
        foreach ($course_ids as $cid) {
            $cp = get_post((int)$cid);
            if (!$cp || $cp->post_status !== 'publish') continue;
            $ct = get_post_thumbnail_id((int)$cid);

            // Contar lecciones y quizzes del curso
            global $wpdb;
            $c_lessons = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->posts} t ON t.ID=p.post_parent
                 WHERE p.post_type='lesson' AND p.post_status='publish'
                 AND t.post_type='topics' AND t.post_parent=%d", (int)$cid));
            $c_quizzes = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->posts} t ON t.ID=p.post_parent
                 WHERE p.post_type='tutor_quiz' AND p.post_status='publish'
                 AND t.post_type='topics' AND t.post_parent=%d", (int)$cid));

            // Duracion del curso (en segundos, meta _tutor_course_duration)
            $dur_raw = get_post_meta((int)$cid, '_tutor_course_duration', true);
            $dur_secs = 0;
            if ($dur_raw) {
                $parts = explode(':', $dur_raw);
                if (count($parts) === 3) $dur_secs = ((int)$parts[0]*3600)+((int)$parts[1]*60)+(int)$parts[2];
                elseif (count($parts) === 2) $dur_secs = ((int)$parts[0]*3600)+((int)$parts[1]*60);
            }

            $total_lessons   += $c_lessons;
            $total_quizzes   += $c_quizzes;
            $total_duration  += $dur_secs;

            $courses[] = array(
                'id'      => (int)$cid,
                'title'   => $cp->post_title,
                'thumb'   => $ct ? wp_get_attachment_image_url((int)$ct, 'thumbnail') : '',
                'lessons' => $c_lessons,
                'quizzes' => $c_quizzes,
                'permalink'=> get_permalink((int)$cid),
            );
        }
    }

    // Formatear duracion total HH:MM:SS
    $dur_h = floor($total_duration/3600);
    $dur_m = floor(($total_duration%3600)/60);
    $dur_s = $total_duration%60;
    $dur_fmt = sprintf('%02d:%02d:%02d', $dur_h, $dur_m, $dur_s);

    // Precio con descuento ? meta keys confirmadas por debug:
    // tutor_course_price = 700 (regular), tutor_course_sale_price = 600 (sale)
    $b_regular    = (float)get_post_meta($id, 'tutor_course_price', true);
    $b_sale       = (float)get_post_meta($id, 'tutor_course_sale_price', true);
    $final_price  = ($b_sale > 0) ? $b_sale : $b_regular;
    $crossed_price= ($b_sale > 0 && $b_regular > 0) ? $b_regular : 0;
    $discount_pct = ($crossed_price > 0 && $final_price > 0)
                    ? round((1 - $final_price/$crossed_price)*100, 2) : 0;

    // Sumar precio individual de cada curso (meta confirmada: tutor_course_price)
    $sum_individual = 0;
    foreach($courses as $c){
        $sum_individual += (float)get_post_meta($c['id'], 'tutor_course_price', true);
    }

    return rest_ensure_response(array(
        'id'                    => $id,
        'post_title'            => $post->post_title,
        'post_content'          => apply_filters('the_content', $post->post_content ?: ''),
        'excerpt'               => wp_strip_all_tags($post->post_excerpt ?: ''),
        'featured_image'        => $thumb ?: '',
        'price'                 => $final_price > 0 ? '$' . number_format($final_price, 2) : 'Gratis',
        'price_raw'             => $final_price,
        'regular_price'         => $crossed_price > 0 ? '$' . number_format($crossed_price, 2) : '',
        'regular_price_raw'     => $crossed_price,
        'discount_percent'      => $discount_pct,
        'total_price_individual'=> $sum_individual,
        'is_free'               => $final_price == 0,
        'payment_link'          => $pay_link,
        'paypal_link'           => $paypal_link,
        'num_courses'           => count($courses),
        'total_lessons'         => $total_lessons,
        'total_quizzes'         => $total_quizzes,
        'total_duration'        => $dur_fmt,
        'permalink'             => get_permalink($id),
        'type'                  => 'bundle',
    ));
}

/* ===============================================
   CAMPO LINK DE PAGO EXTERNO
   ? En editor nativo de Tutor LMS (cursos):
     /wp-admin/admin.php?page=create-course&course_id=X
   ? En editor de bundles:
     /wp-admin/admin.php?page=course-bundle&action=edit&id=X
   ? Tambien en el editor clasico de WP como fallback
=============================================== */

/* -- 1. Editor clasico WP (meta box lateral) -- */

/* -- 2 & 3. Editor nativo de Tutor LMS (React) --
   El editor moderno de Tutor usa React/CSS-in-JS.
   Inyectamos el bloque via admin_footer JS, buscando el
   contenedor de precios en el DOM y agregando AMBOS campos. -- */
add_action('admin_footer', function(){
    $screen = get_current_screen();
    if(!$screen) return;

    // Solo en páginas del editor de Tutor
    $is_course = (isset($_GET['page']) && $_GET['page'] === 'create-course');
    $is_bundle = (isset($_GET['page']) && $_GET['page'] === 'course-bundle' && isset($_GET['action']) && $_GET['action'] === 'edit');
    if(!$is_course && !$is_bundle) return;

    $post_id     = $is_course
        ? (isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0)
        : (isset($_GET['id'])        ? (int)$_GET['id']        : 0);
    $val_mp      = $post_id ? get_post_meta($post_id, '_vk_payment_link',    true) : '';
    $val_paypal  = $post_id ? get_post_meta($post_id, '_paypal_payment_link', true) : '';
    $nonce       = wp_create_nonce('vk_pay_ajax');
    ?>
    <style>
    #vk-pay-link-box {
        background:#fff;
        border:2px solid #2d9e68;
        border-radius:10px;
        padding:16px 18px;
        margin-top:16px;
        font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
        box-shadow:0 2px 8px rgba(0,0,0,.06);
    }
    #vk-pay-link-box .vk-pay-title {
        font-size:13px;font-weight:700;color:#1a2019;
        margin:0 0 12px;padding-bottom:8px;
        border-bottom:1px solid #e8f5ef;
        display:flex;align-items:center;gap:6px;
    }
    #vk-pay-link-box .vk-pay-field { margin-bottom:12px; }
    #vk-pay-link-box .vk-pay-field:last-of-type { margin-bottom:0; }
    #vk-pay-link-box .vk-pay-label {
        display:block;font-size:12px;font-weight:600;
        color:#444;margin-bottom:4px;
    }
    #vk-pay-link-box .vk-pay-input {
        width:100%;padding:8px 11px;
        border:1.5px solid #d5d5d5;border-radius:6px;
        font-size:13px;color:#1a2019;outline:none;
        box-sizing:border-box;font-family:inherit;
        transition:border-color .15s;
    }
    #vk-pay-link-box .vk-pay-input:focus { border-color:#2d9e68; }
    #vk-pay-link-box .vk-pay-input.vk-has-val { border-color:#2d9e68;background:#f0faf5; }
    #vk-paypal-input.vk-has-val { border-color:#0070ba !important;background:#f0f6ff !important; }
    #vk-pay-link-box .vk-pay-hint {
        font-size:11px;color:#999;margin:3px 0 0;
    }
    #vk-pay-link-box .vk-pay-actions {
        display:flex;align-items:center;gap:10px;margin-top:12px;
        padding-top:10px;border-top:1px solid #e8f5ef;
    }
    #vk-pay-link-save {
        padding:7px 16px;background:#2d9e68;color:#fff;
        border:none;border-radius:6px;font-size:13px;font-weight:700;
        cursor:pointer;transition:background .15s;
    }
    #vk-pay-link-save:hover{background:#1b5e3b;}
    #vk-pay-link-msg{font-size:12px;display:none;}
    </style>
    <script>
    (function(){
        var POST_ID      = <?php echo (int)$post_id; ?>;
        var INIT_MP      = <?php echo json_encode($val_mp     ?: ''); ?>;
        var INIT_PAYPAL  = <?php echo json_encode($val_paypal ?: ''); ?>;
        var NONCE        = '<?php echo esc_js($nonce); ?>';
        var AJAXURL      = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

        function buildBox(){
            var box = document.createElement('div');
            box.id  = 'vk-pay-link-box';

            var mpClass     = INIT_MP     ? ' vk-has-val' : '';
            var paypalClass = INIT_PAYPAL ? ' vk-has-val' : '';

            box.innerHTML =
                '<p class="vk-pay-title">💳 Links de Pago — App DM Plus</p>' +

                // ── Campo Mercado Pago ──
                '<div class="vk-pay-field">' +
                  '<label class="vk-pay-label">💳 Mercado Pago</label>' +
                  '<input type="url" id="vk-mp-input" class="vk-pay-input' + mpClass + '"' +
                  ' value="' + INIT_MP.replace(/"/g,'&quot;') + '"' +
                  ' placeholder="https://mpago.la/..." />' +
                  '<p class="vk-pay-hint">Botón azul de Mercado Pago en la app.</p>' +
                '</div>' +

                // ── Campo PayPal ──
                '<div class="vk-pay-field">' +
                  '<label class="vk-pay-label">🌐 PayPal</label>' +
                  '<input type="url" id="vk-paypal-input" class="vk-pay-input' + paypalClass + '"' +
                  ' value="' + INIT_PAYPAL.replace(/"/g,'&quot;') + '"' +
                  ' placeholder="https://paypal.me/..." />' +
                  '<p class="vk-pay-hint">Botón PayPal en la app. Obtén tu enlace en paypal.com/buttons</p>' +
                '</div>' +

                // ── Botón guardar + mensaje ──
                '<div class="vk-pay-actions">' +
                  '<button id="vk-pay-link-save" type="button">💾 Guardar links</button>' +
                  '<span id="vk-pay-link-msg"></span>' +
                '</div>';

            return box;
        }

        function saveLinks(){
            var mp     = document.getElementById('vk-mp-input').value.trim();
            var paypal = document.getElementById('vk-paypal-input').value.trim();
            var msg    = document.getElementById('vk-pay-link-msg');
            msg.style.display='inline'; msg.style.color='#888'; msg.textContent='Guardando...';

            fetch(AJAXURL, {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: 'action=vk_save_payment_links'
                    + '&course_id='    + POST_ID
                    + '&mp='           + encodeURIComponent(mp)
                    + '&paypal='       + encodeURIComponent(paypal)
                    + '&nonce='        + NONCE
            })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if(d.success){
                    msg.style.color='#2d9e68';
                    msg.textContent='✓ Guardado correctamente';
                    // Actualizar estilo de borde según si hay valor
                    var mpIn     = document.getElementById('vk-mp-input');
                    var paypalIn = document.getElementById('vk-paypal-input');
                    mpIn.className     = 'vk-pay-input' + (mp     ? ' vk-has-val' : '');
                    paypalIn.className = 'vk-pay-input' + (paypal ? ' vk-has-val' : '');
                } else {
                    msg.style.color='#d32f2f'; msg.textContent='✗ Error al guardar';
                }
                setTimeout(function(){ msg.style.display='none'; }, 3000);
            })
            .catch(function(){
                msg.style.color='#d32f2f'; msg.textContent='✗ Error de conexión';
                setTimeout(function(){ msg.style.display='none'; }, 3000);
            });
        }

        function findPriceContainer(){
            // 1. Selector exacto del editor Tutor (bundle y curso)
            var el = document.querySelector('.css-if8inz');
            if(el) return el;

            // 2. Input de precio de venta
            var saleInput = document.querySelector('input[name="details.subtotal_raw_sale_price"]');
            if(saleInput){
                var p = saleInput.closest('.css-if8inz') || saleInput.closest('.css-awhlwo');
                while(p && p.parentElement){
                    if(p.parentElement.querySelector('.css-1cls3kk')) return p.parentElement;
                    p = p.parentElement;
                }
            }

            // 3. Label "Regular Price"
            var labels = document.querySelectorAll('label');
            for(var i=0;i<labels.length;i++){
                if(labels[i].textContent.trim()==='Regular Price'){
                    return labels[i].closest('[class]');
                }
            }

            // 4. Input de precio de curso
            var priceInput = document.querySelector(
                'input[name="tutor_course_price"], input[name*="sale_price"], input[data-cy="course-price"]'
            );
            if(priceInput) return priceInput.closest('.css-awhlwo') || priceInput.parentElement;

            return null;
        }

        function injectBox(){
            if(document.getElementById('vk-pay-link-box')) return;
            var target = findPriceContainer();
            var box    = buildBox();

            if(target){
                if(target.nextSibling){
                    target.parentNode.insertBefore(box, target.nextSibling);
                } else {
                    target.parentNode.appendChild(box);
                }
            } else {
                // Fallback: al final del área principal
                var main = document.querySelector('.css-ufdi3s, .tutor-admin-wrap, #wpbody-content .wrap');
                if(main) main.appendChild(box);
                else return; // No se pudo inyectar aún
            }

            document.getElementById('vk-pay-link-save').addEventListener('click', saveLinks);
        }

        // Polling: espera a que React monte el editor (máx 20 seg)
        var attempts = 0;
        var timer = setInterval(function(){
            injectBox();
            attempts++;
            if(document.getElementById('vk-pay-link-box') || attempts > 40) clearInterval(timer);
        }, 500);

    })();
    </script>
    <?php
});

/* -- 4. AJAX handler — guarda AMBOS links (MP + PayPal) -- */
add_action('wp_ajax_vk_save_payment_links', function(){
    if(!wp_verify_nonce($_POST['nonce'] ?? '', 'vk_pay_ajax')) wp_die('nonce');
    if(!current_user_can('edit_posts')) wp_die('caps');

    $post_id = (int)($_POST['course_id'] ?? 0);
    if(!$post_id){ wp_send_json_error('no post_id'); return; }

    // Mercado Pago
    $mp = esc_url_raw(trim($_POST['mp'] ?? ''));
    if($mp) update_post_meta($post_id, '_vk_payment_link', $mp);
    else    delete_post_meta($post_id, '_vk_payment_link');

    // PayPal
    $paypal = esc_url_raw(trim($_POST['paypal'] ?? ''));
    if($paypal) update_post_meta($post_id, '_paypal_payment_link', $paypal);
    else        delete_post_meta($post_id, '_paypal_payment_link');

    wp_send_json_success(array('mp' => $mp, 'paypal' => $paypal));
});

/* -- Mantener retrocompatibilidad con el handler antiguo (un solo link) -- */
add_action('wp_ajax_vk_save_payment_link', function(){
    if(!wp_verify_nonce($_POST['nonce'] ?? '', 'vk_pay_ajax')) wp_die('nonce');
    if(!current_user_can('edit_posts')) wp_die('caps');
    $post_id = (int)($_POST['course_id'] ?? 0);
    $link    = esc_url_raw(trim($_POST['link'] ?? ''));
    if($post_id){
        if($link) update_post_meta($post_id, '_vk_payment_link', $link);
        else      delete_post_meta($post_id, '_vk_payment_link');
    }
    wp_send_json_success();
});

/* -- 5. Mostrar campo en admin list table (columna extra) -- */
add_filter('manage_courses_posts_columns', function($cols){
    $cols['vk_pay_link']='? Link pago app'; return $cols;
});
add_action('manage_courses_posts_custom_column', function($col,$post_id){
    if($col!=='vk_pay_link') return;
    $v=get_post_meta($post_id,'_vk_payment_link',true);
    if($v) echo '<a href="'.esc_url($v).'" target="_blank" style="color:#2d9e68;font-size:12px">? Configurado</a>';
    else   echo '<span style="color:#999;font-size:12px">? sin link</span>';
},10,2);

/* ==================================================
   PAGINA DE LINKS DE PAGO EXTERNOS ? DM Plus
   Menu: WordPress Admin ? DM Plus Links
   Permite configurar el link de pago de cada curso,
   paquete y producto desde una sola pantalla.
================================================== */
add_action('admin_init', 'vk_save_payment_links_handler');
function vk_save_payment_links_handler() {
    if ( ! isset($_POST['dm_save_links']) ) return;
    if ( ! check_admin_referer('dm_save_links_nonce', 'dm_nonce') ) return;
    if ( ! current_user_can('edit_posts') ) return;

    // Cursos y bundles – Mercado Pago (_vk_payment_link)
    $links = isset($_POST['pay_link']) ? $_POST['pay_link'] : array();
    foreach ( $links as $post_id => $url ) {
        $post_id = (int)$post_id;
        $url     = esc_url_raw(trim($url));
        if ( ! $post_id ) continue;
        if ( $url ) update_post_meta($post_id, '_vk_payment_link', $url);
        else        delete_post_meta($post_id, '_vk_payment_link');
    }

    // Cursos y bundles – PayPal (_paypal_payment_link)
    $paypal_links = isset($_POST['paypal_link']) && is_array($_POST['paypal_link']) ? $_POST['paypal_link'] : array();
    foreach ( $paypal_links as $post_id => $url ) {
        $post_id = (int)$post_id;
        $url     = esc_url_raw(trim($url));
        if ( ! $post_id ) continue;
        // Evitar conflicto: solo procesar posts (cursos/bundles), no productos WC
        $post = get_post($post_id);
        if ( $post && in_array($post->post_type, array('courses', 'course-bundle')) ) {
            if ( $url ) update_post_meta($post_id, '_paypal_payment_link', $url);
            else        delete_post_meta($post_id, '_paypal_payment_link');
        }
    }

    // Productos WC – Mercado Pago (_mp_payment_link)
    $mp = isset($_POST['mp_link']) ? $_POST['mp_link'] : array();
    foreach ( $mp as $pid => $url ) {
        $pid = (int)$pid;
        $url = esc_url_raw(trim($url));
        if ( ! $pid ) continue;
        if ( $url ) update_post_meta($pid, '_mp_payment_link', $url);
        else        delete_post_meta($pid, '_mp_payment_link');
    }

    // Productos WC – PayPal (_paypal_payment_link)
    $paypal_wc = isset($_POST['paypal_link']) && is_array($_POST['paypal_link']) ? $_POST['paypal_link'] : array();
    foreach ( $paypal_wc as $pid => $url ) {
        $pid = (int)$pid;
        $url = esc_url_raw(trim($url));
        if ( ! $pid ) continue;
        // Solo procesar productos WC
        $post = get_post($pid);
        if ( $post && $post->post_type === 'product' ) {
            if ( $url ) update_post_meta($pid, '_paypal_payment_link', $url);
            else        delete_post_meta($pid, '_paypal_payment_link');
        }
    }
    add_action('admin_notices', function () {
        echo '<div class="notice notice-success is-dismissible"><p>? Links de pago guardados correctamente.</p></div>';
    });
}

function vk_payment_links_page() {
    $courses  = get_posts(array('post_type'=>'courses','post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC'));
    $bundles  = get_posts(array('post_type'=>'course-bundle','post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC'));
    $products = function_exists('wc_get_products') ? wc_get_products(array('limit'=>-1,'orderby'=>'title','order'=>'ASC')) : array();
    ?>
    <div class="wrap">
    <h1 style="display:flex;align-items:center;gap:10px"><span style="font-size:1.8rem">💳</span> DM Plus – Links de Pago</h1>
    <p style="color:#666;margin-bottom:20px">Configura los enlaces de Mercado Pago y PayPal para cada curso, paquete y producto. Ambas opciones aparecerán como botones en la app DM Plus.</p>
    <style>
    .dm-wrap{background:#fff;border:1px solid #ddd;border-radius:8px;margin-bottom:24px;overflow:hidden}
    .dm-head{background:linear-gradient(135deg,#1b5e3b,#2d9e68);color:#fff;padding:12px 18px;font-size:15px;font-weight:700}
    .dm-cols{display:grid;grid-template-columns:1.8fr 2.2fr 2.2fr 90px;gap:10px;align-items:center;padding:10px 18px;border-bottom:1px solid #f0f0f0}
    .dm-cols:hover{background:#fafff9}.dm-cols:last-child{border:none}
    .dm-th{display:grid;grid-template-columns:1.8fr 2.2fr 2.2fr 90px;gap:10px;padding:7px 18px;background:#f5faf7;font-size:11px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:.05em}
    .dm-t{font-size:13px;font-weight:600;color:#1a2019;margin-bottom:2px}
    .dm-sub{font-size:11px;color:#aaa}
    .dm-p{font-size:12px;color:#2d9e68;font-weight:700}
    .dm-in{width:100%;padding:7px 10px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;font-family:inherit;box-sizing:border-box}
    .dm-in:focus{border-color:#2d9e68;outline:none}
    .dm-in.ok{border-color:#2d9e68;background:#f0faf5}
    .dm-st{font-size:12px;font-weight:700;text-align:center}
    .ok-c{color:#2d9e68}.no-c{color:#bbb}
    .dm-btn{background:#2d9e68;color:#fff;border:none;border-radius:8px;padding:11px 30px;font-size:15px;font-weight:700;cursor:pointer;margin-top:8px}
    .dm-btn:hover{background:#1b5e3b}
    </style>
    <form method="post">
    <?php wp_nonce_field('dm_save_links_nonce','dm_nonce'); ?>
    <input type="hidden" name="m3c_save_links" value="1">

    <?php if (!empty($courses)): ?>
    <div class="dm-wrap">
        <div class="dm-head">📚 Cursos (<?php echo count($courses); ?>)</div>
        <div class="dm-th"><span>Curso</span><span>Link Mercado Pago</span><span>Link PayPal</span><span>Estado</span></div>
        <?php foreach ($courses as $c):
            $price = (float)get_post_meta($c->ID,'tutor_course_price',true);
            $sale  = (float)get_post_meta($c->ID,'tutor_course_sale_price',true);
            $ptype = get_post_meta($c->ID,'_tutor_course_price_type',true);
            $link  = get_post_meta($c->ID,'_vk_payment_link',true);
            $paypal = get_post_meta($c->ID,'_paypal_payment_link',true);
            $fp    = $sale > 0 ? $sale : $price;
        ?>
        <div class="dm-cols">
            <div>
                <div class="dm-t"><?php echo esc_html($c->post_title); ?></div>
                <?php if ($ptype==='free'||!$fp): ?>
                    <div style="font-size:12px;color:#2d9e68">✓ Gratis</div>
                <?php else: ?>
                    <div class="dm-p">$<?php echo number_format($fp,2); ?></div>
                <?php endif; ?>
                <div class="dm-sub">ID: <?php echo $c->ID; ?> &nbsp;-&nbsp; <a href="<?php echo admin_url('admin.php?page=create-course&course_id='.$c->ID); ?>" target="_blank">Editar ↗</a></div>
            </div>
            <input type="url" name="pay_link[<?php echo $c->ID; ?>]" class="dm-in<?php echo $link?' ok':''; ?>" value="<?php echo esc_attr($link); ?>" placeholder="https://mpago.la/...">
            <input type="url" name="paypal_link[<?php echo $c->ID; ?>]" class="dm-in<?php echo $paypal?' ok':''; ?>" value="<?php echo esc_attr($paypal); ?>" placeholder="https://paypal.me/...">
            <div class="dm-st <?php echo ($link||$paypal)?'ok-c':'no-c'; ?>"><?php echo ($link||$paypal)?'✓ OK':'✗ vacío'; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($bundles)): ?>
    <div class="dm-wrap">
        <div class="dm-head">📦 Paquetes (<?php echo count($bundles); ?>)</div>
        <div class="dm-th"><span>Paquete</span><span>Link Mercado Pago</span><span>Link PayPal</span><span>Estado</span></div>
        <?php foreach ($bundles as $b):
            $regular = (float)get_post_meta($b->ID,'tutor_course_price',true);
            $sale    = (float)get_post_meta($b->ID,'tutor_course_sale_price',true);
            $fp      = $sale > 0 ? $sale : $regular;
            $link    = get_post_meta($b->ID,'_vk_payment_link',true);
            $paypal  = get_post_meta($b->ID,'_paypal_payment_link',true);
            $n       = count(array_filter(explode(',',get_post_meta($b->ID,'bundle-course-ids',true)?:'')));
        ?>
        <div class="dm-cols">
            <div>
                <div class="dm-t"><?php echo esc_html($b->post_title); ?></div>
                <?php if ($fp): ?>
                <div class="dm-p">
                    $<?php echo number_format($fp,2); ?>
                    <?php if ($sale&&$regular&&$sale<$regular): ?>
                        <del style="color:#aaa;font-weight:400">$<?php echo number_format($regular,2); ?></del>
                        <span style="color:#e65100;font-size:11px"><?php echo round((1-$sale/$regular)*100,1); ?>% off</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="dm-sub">ID: <?php echo $b->ID; ?><?php if($n): ?> - <?php echo $n; ?> cursos<?php endif; ?> &nbsp;-&nbsp; <a href="<?php echo home_url('/dashboard/create-bundle/?action=edit&id='.$b->ID); ?>" target="_blank">Editar ↗</a></div>
            </div>
            <input type="url" name="pay_link[<?php echo $b->ID; ?>]" class="dm-in<?php echo $link?' ok':''; ?>" value="<?php echo esc_attr($link); ?>" placeholder="https://mpago.la/...">
            <input type="url" name="paypal_link[<?php echo $b->ID; ?>]" class="dm-in<?php echo $paypal?' ok':''; ?>" value="<?php echo esc_attr($paypal); ?>" placeholder="https://paypal.me/...">
            <div class="dm-st <?php echo ($link||$paypal)?'ok-c':'no-c'; ?>"><?php echo ($link||$paypal)?'✓ OK':'✗ vacío'; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($products)): ?>
    <div class="dm-wrap">
        <div class="dm-head">🛍️ Productos WooCommerce (<?php echo count($products); ?>)</div>
        <div class="dm-th"><span>Producto</span><span>Link Mercado Pago</span><span>Link PayPal</span><span>Estado</span></div>
        <?php foreach ($products as $p):
            $pid  = $p->get_id();
            $link = get_post_meta($pid,'_mp_payment_link',true) ?: get_post_meta($pid,'_vk_payment_link',true);
            $paypal = get_post_meta($pid,'_paypal_payment_link',true);
        ?>
        <div class="dm-cols">
            <div>
                <div class="dm-t"><?php echo esc_html($p->get_name()); ?></div>
                <div class="dm-p">$<?php echo number_format((float)$p->get_price(),2); ?></div>
                <div class="dm-sub">ID: <?php echo $pid; ?></div>
            </div>
            <input type="url" name="mp_link[<?php echo $pid; ?>]" class="dm-in<?php echo $link?' ok':''; ?>" value="<?php echo esc_attr($link); ?>" placeholder="https://mpago.la/...">
            <input type="url" name="paypal_link[<?php echo $pid; ?>]" class="dm-in<?php echo $paypal?' ok':''; ?>" value="<?php echo esc_attr($paypal); ?>" placeholder="https://paypal.me/...">
            <div class="dm-st <?php echo ($link||$paypal)?'ok-c':'no-c'; ?>"><?php echo ($link||$paypal)?'✓ OK':'✗ vacío'; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($courses)&&empty($bundles)&&empty($products)): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:16px;color:#856404">?? No hay cursos, paquetes ni productos publicados.</div>
    <?php endif; ?>

    <p><button type="submit" class="dm-btn">? Guardar todos los links</button></p>
    </form>
    </div>
    <?php
}

/* ===============================================
   PERFIL DEL USUARIO ? GET
=============================================== */
function vk_my_profile($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token invalido', array('status'=>401));
    $user = get_user_by('ID', $uid);
    if (!$user) return new WP_Error('not_found', 'Usuario no encontrado', array('status'=>404));

    return rest_ensure_response(array(
        'data' => array(
            'id'         => $uid,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->user_email,
            'phone'      => get_user_meta($uid, 'phone_number', true) ?: '',
            'job_title'  => get_user_meta($uid, 'tutor_profile_job_title', true) ?: '',
            'bio'        => get_user_meta($uid, 'description', true) ?: '',
            'avatar'     => get_avatar_url($uid, array('size'=>200)),
        )
    ));
}

/* ===============================================
   ACTUALIZAR PERFIL ? POST
   Campos: first_name, last_name, phone,
           job_title, bio, new_password
=============================================== */
function vk_update_profile($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token invalido', array('status'=>401));
    $user = get_user_by('ID', $uid);
    if (!$user) return new WP_Error('not_found', 'Usuario no encontrado', array('status'=>404));
    $data  = array('ID' => $uid);

    $first = sanitize_text_field($req->get_param('first_name') ?: '');
    $last  = sanitize_text_field($req->get_param('last_name')  ?: '');

    if ($first) $data['first_name']    = $first;
    if ($last)  $data['last_name']     = $last;
    if ($first || $last) $data['display_name'] = trim("$first $last");

    $result = wp_update_user($data);
    if (is_wp_error($result)) return $result;

    // Metas
    $phone = sanitize_text_field($req->get_param('phone')     ?: '');
    $job   = sanitize_text_field($req->get_param('job_title') ?: '');
    $bio   = sanitize_textarea_field($req->get_param('bio')   ?: '');

    if ($phone !== '') update_user_meta($uid, 'phone_number', $phone);
    if ($job   !== '') update_user_meta($uid, 'tutor_profile_job_title', $job);
    if ($bio   !== '') update_user_meta($uid, 'description', $bio);

    // Contrasena
    $new_pass = $req->get_param('new_password');
    $pass_changed = false;
    if ($new_pass && strlen($new_pass) >= 8) {
        wp_set_password($new_pass, $uid);
        $pass_changed = true;
    }

    $updated_user = get_user_by('ID', $uid);
    return rest_ensure_response(array(
        'success'          => true,
        'display_name'     => $updated_user->display_name,
        'first_name'       => $updated_user->first_name,
        'last_name'        => $updated_user->last_name,
        'avatar'           => get_avatar_url($uid, array('size'=>200)),
        'password_changed' => $pass_changed,
    ));
}

/* ===============================================
   PREFERENCIAS DE NOTIFICACIONES ? GET
=============================================== */
/* ═══════════════════════════════════════════════════
   SISTEMA DE NOTIFICACIONES — TABLA BD
   Crea la tabla vk_notifications si no existe
══════════════════════════════════════════════════════ */
add_action('init', 'vk_create_notifications_table', 5);
function vk_create_notifications_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';
    // Solo crear si no existe
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) return;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL DEFAULT 0,
        title varchar(255) NOT NULL DEFAULT '',
        message text NOT NULL,
        type varchar(50) NOT NULL DEFAULT 'info',
        action_url varchar(500) DEFAULT '',
        is_read tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY is_read (is_read),
        KEY created_at (created_at)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/* ── Helper: reparar doble-codificación UTF-8 ─────────────────────────
   Problema: texto UTF-8 (tildes, emojis) escrito en MySQL con conexión
   latin1 → cada byte se re-codifica como UTF-8 → doble codificación.
   Resultado visible: "Publicación" → "PublicaciÃ³n", "🎉" → "ð".
   Corrección: mb_convert_encoding(UTF-8 → ISO-8859-1) deshace el paso
   extra y devuelve los bytes UTF-8 originales.
────────────────────────────────────────────────────────────────────── */
if (!function_exists('vkx_fix_utf8')) {
function vkx_fix_utf8($str) {
    if (empty($str) || !is_string($str)) return $str;

    // Marcadores de doble-codificación: Ã (C3 83) y Â (C3 82)
    // aparecen cuando bytes UTF-8 se tratan como ISO-8859-1 y se re-codifican.
    if (strpos($str, 'Ã') === false && strpos($str, 'Â') === false) return $str;

    // Dirección correcta: deshacer la re-codificación convirtiendo UTF-8 → ISO-8859-1.
    // Los bytes resultantes SON el UTF-8 original (ó = C3 B3, 🎉 = F0 9F 8E 89).
    $fixed = mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');

    // Solo aplicar si el resultado es UTF-8 válido y distinto al original
    if ($fixed && $fixed !== $str && mb_check_encoding($fixed, 'UTF-8')) {
        return $fixed;
    }

    return $str;
}
}

/* ── Migración única: reparar notificaciones con doble-codificación UTF-8 ──
   Usa CONVERT(CONVERT(col USING latin1) USING utf8mb4) — operación MySQL
   que deshace exactamente la doble-codificación sin perder caracteres.
   Corre solo una vez y marca la opción 'vkx_notif_utf8_repaired_v2'.
────────────────────────────────────────────────────────────────────────── */
function vkx_repair_notifications_encoding() {
    if (get_option('vkx_notif_utf8_repaired_v2')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';

    // 1. Convertir tabla a utf8mb4 para soportar emoji (4 bytes)
    $wpdb->query("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    error_log("[VK] vkx_repair: tabla {$table} convertida a utf8mb4");

    // 2. Reparar filas con doble-codificación (Ã, Â como marcadores)
    $wpdb->query(
        "UPDATE `{$table}`
         SET
           title   = CONVERT(CONVERT(title   USING latin1) USING utf8mb4),
           message = CONVERT(CONVERT(message USING latin1) USING utf8mb4)
         WHERE title   LIKE '%Ã%'
            OR title   LIKE '%Â%'
            OR message LIKE '%Ã%'
            OR message LIKE '%Â%'"
    );
    $affected = $wpdb->rows_affected;
    update_option('vkx_notif_utf8_repaired_v2', '1');
    error_log("[VK] vkx_repair_notifications_encoding: {$affected} filas de texto reparadas");
}
add_action('init', 'vkx_repair_notifications_encoding', 5);

/* ═══════════════════════════════════════════════════
   HELPER UNIFICADO: Notificar a un usuario
   Guarda en BD + envía push a OneSignal
══════════════════════════════════════════════════════ */
if (!function_exists('vk_notify_user')) {
function vk_notify_user($user_id, $type, $title, $message, $url = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';

    // 1. Guardar en BD — forzar utf8mb4 para preservar emojis y tildes
    $wpdb->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
    $wpdb->insert($table, array(
        'user_id'    => (int)$user_id,
        'title'      => wp_strip_all_tags($title),
        'message'    => wp_strip_all_tags($message),
        'type'       => sanitize_key($type),
        'action_url' => esc_url_raw($url),
        'is_read'    => 0,
        'created_at' => current_time('mysql'),
    ), array('%d','%s','%s','%s','%s','%d','%s'));

    // 2. REST API Key
    $os_settings  = get_option('onesignal_settings', array());
    $rest_api_key = isset($os_settings['app_rest_api_key']) ? trim($os_settings['app_rest_api_key']) : '';
    if (empty($rest_api_key)) return;

    // 3. Obtener IDs del usuario
    $player_ids = get_user_meta((int)$user_id, 'onesignal_player_ids', true) ?: array();
    if (!is_array($player_ids) || empty($player_ids)) {
        $single = get_user_meta((int)$user_id, 'onesignal_player_id', true);
        $player_ids = $single ? array($single) : array();
    }
    if (empty($player_ids)) {
        error_log('[VK Push] notify_user: user '.$user_id.' sin IDs registrados');
        return;
    }

    $icon = 'https://vidakushala.com/wp-content/uploads/dm-icon.png';
    $notif_url = $url ?: 'https://app.vidakushala.com/';

    // 4. Enviar — mismo método que bienvenida
    $payload = array(
        'app_id'                   => VK_ONESIGNAL_APP_ID,
        'headings'                 => array('en' => $title, 'es' => $title),
        'contents'                 => array('en' => $message, 'es' => $message),
        'url'                      => $notif_url,
        'include_subscription_ids' => array_values(array_unique($player_ids)),
        'chrome_web_icon'          => $icon,
        'firefox_icon'             => $icon,
        'chrome_web_badge'         => $icon,
        'data'                     => array('type' => $type, 'url' => $notif_url),
        'web_push_topic'           => $type,
        'ttl'                      => 86400,
        'priority'                 => 10,
    );

    $response = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
        'headers' => array(
            'Content-Type'  => 'application/json; charset=utf-8',
            'Authorization' => 'Key ' . $rest_api_key,
        ),
        'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
        'timeout' => 15,
    ));

    if (is_wp_error($response)) {
        error_log('[VK Push] notify_user wp_error: ' . $response->get_error_message());
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $code = wp_remote_retrieve_response_code($response);

    // Limpiar IDs inválidos
    if (!empty($body['errors']['invalid_player_ids'])) {
        $invalid = (array)$body['errors']['invalid_player_ids'];
        $cleaned = array_values(array_diff($player_ids, $invalid));
        update_user_meta((int)$user_id, 'onesignal_player_ids', $cleaned);
        if (empty($cleaned)) delete_user_meta((int)$user_id, 'onesignal_player_id');
    }

    if ($code !== 200 || !empty($body['errors'])) {
        error_log('[VK Push] notify_user error: ' . json_encode($body['errors'] ?? $body));
    }
}
}

/* ═══════════════════════════════════════════════════
   HELPER UNIFICADO: Notificar a TODOS los usuarios
   Guarda en BD (user_id=0) + envía push masivo
══════════════════════════════════════════════════════ */
/* ── Helper central para construir payloads push completos ─────── */
function vkx_build_push_payload($app_id, $title, $message, $url, $type, $target_type, $target_ids = array()) {
    $icon_url  = 'https://vidakushala.com/wp-content/uploads/dm-icon.png';
    $notif_url = $url ?: 'https://app.vidakushala.com/';

    $payload = array(
        'app_id'             => $app_id,
        'headings'           => array('en' => $title, 'es' => $title),
        'contents'           => array('en' => $message, 'es' => $message),
        'url'                => $notif_url,
        'chrome_web_icon'    => $icon_url,
        'firefox_icon'       => $icon_url,
        'chrome_web_badge'   => $icon_url,
        'data'               => array('type' => $type, 'url' => $notif_url),
        'web_push_topic'     => $type,
        'ttl'                => 86400,
        'priority'           => 10,
    );

    if ($target_type === 'all') {
        $payload['included_segments'] = array('Subscribed Users');
    } elseif ($target_type === 'ids' && !empty($target_ids)) {
        $payload['include_subscription_ids'] = array_values(array_unique($target_ids));
    }

    return $payload;
}

if (!function_exists('vk_notify_all')) {
function vk_notify_all($type, $title, $message, $url = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';

    // 1. Guardar en BD
    $wpdb->insert($table, array(
        'user_id'    => 0,
        'title'      => function_exists('wp_strip_all_tags') ? wp_strip_all_tags($title) : strip_tags($title),
        'message'    => function_exists('wp_strip_all_tags') ? wp_strip_all_tags($message) : strip_tags($message),
        'type'       => sanitize_key($type),
        'action_url' => esc_url_raw($url),
        'is_read'    => 0,
        'created_at' => current_time('mysql'),
    ), array('%d','%s','%s','%s','%s','%d','%s'));

    // 2. Obtener la REST API Key
    $os_settings  = get_option('onesignal_settings', array());
    $rest_api_key = isset($os_settings['app_rest_api_key']) ? trim($os_settings['app_rest_api_key']) : '';
    if (empty($rest_api_key)) {
        error_log('[VK Push] notify_all: REST API Key no configurada');
        return;
    }

    // 3. Obtener TODOS los player_ids de todos los usuarios (igual que bienvenida)
    $all_ids = array();
    $rows = $wpdb->get_results(
        "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'onesignal_player_ids'"
    );
    foreach ($rows as $row) {
        $ids = @unserialize($row->meta_value);
        if (is_array($ids)) {
            foreach ($ids as $id) {
                if (!empty($id)) $all_ids[] = $id;
            }
        }
    }
    // También player_id simple
    $single_rows = $wpdb->get_results(
        "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'onesignal_player_id' AND meta_value != ''"
    );
    foreach ($single_rows as $row) {
        if (!empty($row->meta_value)) $all_ids[] = $row->meta_value;
    }
    $all_ids = array_values(array_unique($all_ids));

    if (empty($all_ids)) {
        error_log('[VK Push] notify_all: no hay suscriptores registrados');
        return;
    }

    $icon = 'https://vidakushala.com/wp-content/uploads/dm-icon.png';
    $notif_url = $url ?: 'https://app.vidakushala.com/';

    // 4. Enviar con included_segments (más confiable que IDs específicos)
    //    OneSignal envía a todos los suscriptores activos del dominio
    $icon = 'https://vidakushala.com/wp-content/uploads/dm-icon.png';
    $payload = array(
        'app_id'              => VK_ONESIGNAL_APP_ID,
        'headings'            => array('en' => $title, 'es' => $title),
        'contents'            => array('en' => $message, 'es' => $message),
        'url'                 => $notif_url,
        'included_segments'   => array('All'),
        'chrome_web_icon'     => $icon,
        'firefox_icon'        => $icon,
        'chrome_web_badge'    => $icon,
        'data'                => array('type' => $type, 'url' => $notif_url),
        'web_push_topic'      => $type,
        'ttl'                 => 86400,
        'priority'            => 10,
    );

    foreach (array(1) as $chunk) { // loop de 1 para mantener estructura
        $response = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
            'headers' => array(
                'Content-Type'  => 'application/json; charset=utf-8',
                'Authorization' => 'Key ' . $rest_api_key,
            ),
            'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            error_log('[VK Push] notify_all wp_error: ' . $response->get_error_message());
            continue;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200 || !empty($body['errors'])) {
            error_log('[VK Push] notify_all error: ' . json_encode($body['errors'] ?? $body));
        } else {
            // Guardar en historial
            $history = get_option('vk_push_history', array());
            $history[] = array(
                'id'         => $body['id'] ?? uniqid(),
                'title'      => $title,
                'message'    => $message,
                'type'       => $type,
                'target'     => 'all',
                'recipients' => (int)($body['recipients'] ?? 0),
                'date'       => current_time('mysql'),
            );
            if (count($history) > 200) $history = array_slice($history, -200);
            update_option('vk_push_history', $history);
        }

        // Limpiar IDs inválidos
        if (!empty($body['errors']['invalid_player_ids'])) {
            $invalid = (array)$body['errors']['invalid_player_ids'];
            foreach ($invalid as $inv) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->usermeta} WHERE meta_key='onesignal_player_id' AND meta_value=%s",
                    $inv
                ));
            }
            error_log('[VK Push] notify_all: ' . count($invalid) . ' IDs inválidos eliminados');
        }
    }
}
}

/* Constante App ID OneSignal correcto */
if (!defined('VK_ONESIGNAL_APP_ID')) {
    define('VK_ONESIGNAL_APP_ID', '5ed3833a-c6c4-4b09-9f3c-3d7778e334b4');
}

/* ═══════════════════════════════════════════════════
   ENDPOINT: GET /vk/v1/my-notifications
   Devuelve historial de notificaciones del usuario
══════════════════════════════════════════════════════ */
function vk_my_notifications($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token invalido', array('status' => 401));
    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';
    // Forzar utf8mb4 para recibir emojis y tildes sin corrupción
    $wpdb->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
    $limit  = min((int)($req->get_param('limit') ?? 50), 100);
    $offset = max((int)($req->get_param('offset') ?? 0), 0);
    // Traer notificaciones del usuario + notificaciones globales (user_id=0)
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT *, CASE WHEN user_id=0 THEN 0 ELSE is_read END AS effective_read
         FROM {$table}
         WHERE user_id=%d OR user_id=0
         ORDER BY created_at DESC
         LIMIT %d OFFSET %d",
        $uid, $limit, $offset
    ));
    // Marcar las globales como leídas si el usuario las leyó (usermeta)
    $read_globals = get_user_meta($uid, 'vk_read_global_notifs', true) ?: array();
    $unread = 0;
    $list = array();
    foreach ($rows as $r) {
        $is_read = ($r->user_id == 0)
            ? in_array((int)$r->id, (array)$read_globals)
            : (bool)$r->is_read;
        if (!$is_read) $unread++;
        $list[] = array(
            'id'         => (int)$r->id,
            'title'      => vkx_fix_utf8($r->title),
            'message'    => vkx_fix_utf8($r->message),
            'type'       => $r->type,
            'action_url' => $r->action_url,
            'is_read'    => $is_read,
            'created_at' => $r->created_at,
            'is_global'  => ($r->user_id == 0),
        );
    }
    return rest_ensure_response(array(
        'notifications' => $list,
        'unread_count'  => $unread,
        'total'         => count($list),
    ));
}

/* ═══════════════════════════════════════════════════
   ENDPOINT: GET /vk/v1/notifications/count
   Devuelve solo el contador de no leídas
══════════════════════════════════════════════════════ */
function vk_notifications_count($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token invalido', array('status' => 401));
    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';
    // No leídas propias
    $own = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id=%d AND is_read=0", $uid));
    // Globales aún no leídas
    $read_globals = get_user_meta($uid, 'vk_read_global_notifs', true) ?: array();
    $global_ids   = empty($read_globals) ? array(0) : array_map('intval', (array)$read_globals);
    $placeholders = implode(',', array_fill(0, count($global_ids), '%d'));
    $global_unread = (int)$wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id=0 AND id NOT IN ({$placeholders})",
            $global_ids
        )
    );
    return rest_ensure_response(array('count' => $own + $global_unread));
}

/* ═══════════════════════════════════════════════════
   ENDPOINT: POST /vk/v1/notifications/read
   Marca notificaciones como leídas
   Body: { "id": 123 } o { "all": true }
══════════════════════════════════════════════════════ */
function vk_notifications_read($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token invalido', array('status' => 401));
    global $wpdb;
    $table  = $wpdb->prefix . 'vk_notifications';
    $body   = $req->get_json_params() ?: array();
    $all    = !empty($body['all']);
    $notif_id = (int)($body['id'] ?? 0);
    if ($all) {
        // Marcar todas las propias como leídas
        $wpdb->update($table, array('is_read' => 1), array('user_id' => $uid));
        // Marcar todas las globales como leídas para este usuario
        $global_ids = $wpdb->get_col("SELECT id FROM {$table} WHERE user_id=0");
        if ($global_ids) update_user_meta($uid, 'vk_read_global_notifs', array_map('intval', $global_ids));
    } elseif ($notif_id) {
        // Ver si es propia o global
        $row = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM {$table} WHERE id=%d", $notif_id));
        if ($row && $row->user_id == 0) {
            $read = get_user_meta($uid, 'vk_read_global_notifs', true) ?: array();
            $read[] = $notif_id;
            update_user_meta($uid, 'vk_read_global_notifs', array_unique(array_map('intval', $read)));
        } elseif ($row && $row->user_id == $uid) {
            $wpdb->update($table, array('is_read' => 1), array('id' => $notif_id, 'user_id' => $uid));
        }
    }
    return rest_ensure_response(array('success' => true));
}

/* ═══════════════════════════════════════════════════
   ENDPOINT: POST /vk/v1/update-notifications
   Actualiza preferencias de notificación del usuario.
   Acepta los mismos parámetros que /notifications/read
   para compatibilidad con versiones anteriores de la app.
══════════════════════════════════════════════════════ */
function vk_update_notifications($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token invalido', array('status' => 401));

    $body = $req->get_json_params() ?: array();

    // Si viene un campo 'read' o 'id', delegar a la misma lógica que /notifications/read
    if (!empty($body['all']) || !empty($body['id'])) {
        return vk_notifications_read($req);
    }

    return rest_ensure_response(array('success' => true));
}

/* ═══════════════════════════════════════════════════
   ENDPOINT: POST /vk/v1/register-player
   Guarda el OneSignal Player ID del usuario
══════════════════════════════════════════════════════ */
function vk_register_player($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token invalido', array('status' => 401));
    $body      = $req->get_json_params() ?: array();
    $player_id = sanitize_text_field($body['player_id'] ?? '');
    if (!$player_id) return new WP_Error('missing', 'player_id requerido', array('status' => 400));

    // Guardar (array de IDs para multi-dispositivo)
    $existing   = get_user_meta($uid, 'onesignal_player_ids', true) ?: array();
    $is_new_sub = !in_array($player_id, $existing); // primera vez este ID

    if ($is_new_sub) {
        $existing[] = $player_id;
        if (count($existing) > 10) $existing = array_slice($existing, -10);
        update_user_meta($uid, 'onesignal_player_ids', $existing);
    }
    update_user_meta($uid, 'onesignal_player_id', $player_id);

    // Guardar info del dispositivo para mostrar en el panel
    $device_info = isset($body['device_info']) ? $body['device_info'] : array();
    $device_info['player_id']   = $player_id;
    $device_info['registered']  = current_time('mysql');
    $device_info['user_agent']  = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 200) : '';
    // Detectar tipo de dispositivo desde User-Agent
    $ua = $device_info['user_agent'];
    $device_info['browser'] = preg_match('/Chrome/i', $ua) ? 'Chrome' :
                              (preg_match('/Firefox/i', $ua) ? 'Firefox' :
                              (preg_match('/Safari/i', $ua) ? 'Safari' :
                              (preg_match('/Edge/i', $ua) ? 'Edge' : 'Otro')));
    $device_info['device'] = preg_match('/Mobile|Android|iPhone/i', $ua) ? 'Móvil' :
                             (preg_match('/iPad/i', $ua) ? 'Tablet' : 'Escritorio');
    $device_info['os'] = preg_match('/Android/i', $ua) ? 'Android' :
                        (preg_match('/iPhone|iPad/i', $ua) ? 'iOS' :
                        (preg_match('/Windows/i', $ua) ? 'Windows' :
                        (preg_match('/Mac/i', $ua) ? 'macOS' :
                        (preg_match('/Linux/i', $ua) ? 'Linux' : 'Otro'))));

    // Guardar por player_id
    $all_devices = get_option('vk_push_devices', array());
    $all_devices[$player_id] = $device_info;
    // Mantener solo los últimos 500 dispositivos
    if (count($all_devices) > 500) {
        $all_devices = array_slice($all_devices, -500, null, true);
    }
    update_option('vk_push_devices', $all_devices);

    // Registrar external_user_id en OneSignal (permite targeting por user_id, funciona en Safari)
    $os_ext  = get_option('onesignal_settings', array());
    $key_ext = isset($os_ext['app_rest_api_key']) ? trim($os_ext['app_rest_api_key']) : '';
    if (!empty($key_ext) && $is_new_sub) {
        wp_remote_request(
            'https://onesignal.com/api/v1/players/' . urlencode($player_id),
            array(
                'method'  => 'PUT',
                'headers' => array(
                    'Content-Type'  => 'application/json; charset=utf-8',
                    'Authorization' => 'Key ' . $key_ext,
                ),
                'body'    => json_encode(array(
                    'app_id'           => VK_ONESIGNAL_APP_ID,
                    'external_user_id' => (string)$uid,
                )),
                'timeout' => 8,
                'blocking'=> false,
            )
        );
    }

    // Notificación de bienvenida solo la primera vez que registra este device
    if ($is_new_sub && count($existing) === 1) {
        $user     = get_userdata($uid);
        $name     = $user ? $user->display_name : 'Estudiante';
        $os_settings  = get_option('onesignal_settings', array());
        $rest_api_key = isset($os_settings['app_rest_api_key']) ? trim($os_settings['app_rest_api_key']) : '';
        if (!empty($rest_api_key)) {
            $icon_url = 'https://vidakushala.com/wp-content/uploads/dm-icon.png';
            $welcome_payload = array(
                'app_id'                   => VK_ONESIGNAL_APP_ID,
                'headings'                 => array('en' => '👋 ¡Bienvenido a VidaKushala!', 'es' => '👋 ¡Bienvenido a VidaKushala!'),
                'contents'                 => array('en' => '¡Hola '.$name.'! Las notificaciones están activas. Te avisaremos de nuevos cursos, encuestas y tus certificados.', 'es' => '¡Hola '.$name.'! Las notificaciones están activas. Te avisaremos de nuevos cursos, encuestas y tus certificados.'),
                'url'                      => 'https://app.vidakushala.com/',
                'include_subscription_ids' => array($player_id),
                'chrome_web_icon'          => $icon_url,
                'firefox_icon'             => $icon_url,
                'chrome_web_badge'         => $icon_url,
                'data'                     => array('type' => 'welcome'),
                'web_push_topic'           => 'welcome',
                'ttl'                      => 3600,
                'priority'                 => 10,
            );
            wp_remote_post('https://onesignal.com/api/v1/notifications', array(
                'headers' => array(
                    'Content-Type'  => 'application/json; charset=utf-8',
                    'Authorization' => 'Key ' . $rest_api_key,
                ),
                'body'    => json_encode($welcome_payload, JSON_UNESCAPED_UNICODE),
                'timeout' => 10,
                'blocking'=> false, // no bloquear la respuesta
            ));
        }
    }

    return rest_ensure_response(array(
        'success'       => true,
        'player_id'     => $player_id,
        'is_new'        => $is_new_sub,
        'total_devices' => count($existing),
    ));
}

/* ===============================================
   ACTUALIZAR NOTIFICACIONES - POST
=============================================== */

/* ===================================================================
   PUSH AUTOMATICO - HELPERS Y HOOKS
=================================================================== */

/* ═══════════════════════════════════════════════════
   HOOKS AUTOMÁTICOS — Usan los helpers unificados
══════════════════════════════════════════════════════ */

// Nuevo curso publicado → notif a todos
add_action('transition_post_status', 'vk_auto_push_new_course', 10, 3);
function vk_auto_push_new_course($new_status, $old_status, $post) {
    if ($post->post_type !== 'courses' || $new_status !== 'publish' || $old_status === 'publish') return;
    $config   = get_option('vk_push_auto_config', array());
    if (!isset($config['new_course']['enabled']) || empty($config['new_course']['enabled'])) return;
    $template = $config['new_course']['template'] ?? '¡Nuevo curso disponible! {TITLE} te espera.';
    $message  = str_replace('{TITLE}', $post->post_title, $template);
    $title    = " Nuevo Curso";
    $url      = 'https://app.vidakushala.com/?open_course=' . $post->ID;
    vk_notify_all('course', $title, $message, $url);
}

// Nuevo producto publicado -> notif a todos
add_action('transition_post_status', 'vk_auto_push_new_product', 10, 3);
function vk_auto_push_new_product($new_status, $old_status, $post) {
    if ($post->post_type !== 'product' || $new_status !== 'publish' || $old_status === 'publish') return;
    $config   = get_option('vk_push_auto_config', array());
    if (empty($config['new_product']['enabled'])) return;
    $template = $config['new_product']['template'] ?? 'Nuevo producto: {TITLE}';
    $message  = str_replace('{TITLE}', $post->post_title, $template);
    $title    = "Nuevo Producto";
    $url      = 'https://app.vidakushala.com/?open_product=' . $post->ID;
    vk_notify_all('product', $title, $message, $url);
}

// Nuevo paquete de cursos publicado → notif a todos
add_action('transition_post_status', 'vk_auto_push_new_bundle', 10, 3);
function vk_auto_push_new_bundle($new_status, $old_status, $post) {
    if ($post->post_type !== 'course-bundle' || $new_status !== 'publish' || $old_status === 'publish') return;
    $config = get_option('vk_push_auto_config', array());
    if (empty($config['new_bundle']['enabled'])) return;
    $template = $config['new_bundle']['template'] ?? '¡Nuevo paquete disponible! {TITLE}. Ahorra accediendo a varios cursos.';
    $message  = str_replace('{TITLE}', $post->post_title, $template);
    $title    = 'Nuevo Paquete';
    $url      = 'https://app.vidakushala.com/?open_bundle=' . $post->ID;
    vk_notify_all('bundle', $title, $message, $url);
}

/* =================================================================
   ENCUESTA YOP POLL v7 — NOTIFICACIÓN AUTOMÁTICA CONFIABLE
   
   YOP Poll v7 NO usa WordPress post types ni do_action().
   Usa wpdb->insert() directo en yoppoll_polls.
   
   Estrategia triple:
   1. Filtro 'query' → detecta INSERT en yoppoll_polls en tiempo real
   2. WP-Cron cada 5 min → detecta encuestas nuevas no notificadas (failsafe)
   3. Endpoint /vk/v1/notify-poll → admin puede notificar manualmente
================================================================= */

/* ── 1. Filtro 'query': interceptar INSERT en yoppoll_polls ───── */
add_filter('query', 'vkx_intercept_yop_poll_insert');
function vkx_intercept_yop_poll_insert($query) {
    global $wpdb;
    // Solo actuar en INSERT a la tabla de encuestas YOP
    $table = $wpdb->prefix . 'yoppoll_polls';
    if (stripos($query, "INSERT INTO `{$table}`") === false &&
        stripos($query, "INSERT INTO {$table}")   === false) {
        return $query;
    }
    // Verificar si la config de auto-notif está activa
    $config = get_option('vk_push_auto_config', array());
    if (empty($config['new_poll']['enabled'])) return $query;

    // Programar la notificación justo después de que el INSERT termine
    // (necesitamos el ID nuevo → usamos shutdown action con último insert id)
    add_action('shutdown', 'vkx_notify_yop_poll_after_insert', 5);
    return $query;
}

function vkx_notify_yop_poll_after_insert() {
    global $wpdb;
    $table = $wpdb->prefix . 'yoppoll_polls';

    // Obtener la última encuesta insertada que no tiene notif enviada
    $last_notified = (int) get_option('vk_yop_last_notified_poll', 0);

    $poll = $wpdb->get_row(
        "SELECT id, name, status FROM {$table}
         WHERE id > {$last_notified}
           AND status = 'published'
         ORDER BY id DESC LIMIT 1"
    );

    if (!$poll) return;

    // Actualizar marca para no notificar de nuevo
    update_option('vk_yop_last_notified_poll', (int)$poll->id, false);

    $config   = get_option('vk_push_auto_config', array());
    $template = $config['new_poll']['template'] ?? '¡Nueva encuesta disponible! {TITLE}. Comparte tu opinión.';
    $message  = str_replace('{TITLE}', $poll->name, $template);
    $title    = ' Nueva Encuesta';
    $url      = 'https://app.vidakushala.com/?open_poll=' . $poll->id;
    vk_notify_all('poll', $title, $message, $url);
}

/* ── 2. WP-Cron cada 5 min → failsafe para encuestas no notificadas ── */
add_action('init', 'vkx_register_yop_poll_cron');
function vkx_register_yop_poll_cron() {
    if (!wp_next_scheduled('vkx_check_new_polls_cron')) {
        wp_schedule_event(time(), 'vkx_5min', 'vkx_check_new_polls_cron');
    }
}
add_filter('cron_schedules', function($schedules) {
    $schedules['vkx_5min'] = array('interval' => 300, 'display' => 'Cada 5 minutos (VK)');
    return $schedules;
});
add_action('vkx_check_new_polls_cron', 'vkx_cron_check_new_yop_polls');
function vkx_cron_check_new_yop_polls() {
    global $wpdb;
    $table = $wpdb->prefix . 'yoppoll_polls';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) return;

    $config = get_option('vk_push_auto_config', array());
    if (empty($config['new_poll']['enabled'])) return;

    $last_notified = (int) get_option('vk_yop_last_notified_poll', 0);

    // Buscar encuestas publicadas nuevas no notificadas
    $new_polls = $wpdb->get_results(
        "SELECT id, name FROM {$table}
         WHERE id > {$last_notified}
           AND status = 'published'
         ORDER BY id ASC"
    );

    if (empty($new_polls)) return;

    $template = $config['new_poll']['template'] ?? '¡Nueva encuesta disponible! {TITLE}. Comparte tu opinión.';

    foreach ($new_polls as $poll) {
        $message = str_replace('{TITLE}', $poll->name, $template);
        $url     = 'https://app.vidakushala.com/?open_poll=' . $poll->id;
        vk_notify_all('poll', ' Nueva Encuesta', $message, $url);
        update_option('vk_yop_last_notified_poll', (int)$poll->id, false);
    }
}

/* ── 3. Endpoint manual: POST /vk/v1/notify-poll ─────────────────
   Desde administrar.php el admin puede disparar manualmente
   la notificación de cualquier encuesta existente.
   Body: { "poll_id": 5 } o { "all_new": true }
────────────────────────────────────────────────────────────────── */
add_action('rest_api_init', function() {
    register_rest_route('vk/v1', '/notify-poll', array(
        'methods'             => 'POST',
        'callback'            => 'vkx_manual_notify_poll',
        'permission_callback' => '__return_true',
    ));
}, 15);

function vkx_manual_notify_poll($req) {
    if (!vk_is_admin_token($req)) return new WP_Error('forbidden','Sin permisos',array('status'=>403));
    global $wpdb;
    $table = $wpdb->prefix . 'yoppoll_polls';
    $body  = $req->get_json_params() ?: array();

    $config   = get_option('vk_push_auto_config', array());
    $template = $config['new_poll']['template'] ?? '¡Nueva encuesta disponible! {TITLE}. Comparte tu opinión.';

    // Notificar encuesta específica
    $poll_id = (int)($body['poll_id'] ?? 0);
    if ($poll_id) {
        $poll = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM {$table} WHERE id=%d", $poll_id
        ));
        if (!$poll) return new WP_Error('not_found', 'Encuesta no encontrada', array('status'=>404));
        $message = str_replace('{TITLE}', $poll->name, $template);
        $url     = 'https://app.vidakushala.com/?open_poll=' . $poll->id;
        vk_notify_all('poll', ' Nueva Encuesta', $message, $url);
        update_option('vk_yop_last_notified_poll', max((int)get_option('vk_yop_last_notified_poll',0), $poll_id), false);
        return rest_ensure_response(array('success'=>true,'poll'=>$poll->name,'notified'=>true));
    }

    // Notificar todas las encuestas nuevas (no notificadas aún)
    if (!empty($body['all_new'])) {
        $last = (int) get_option('vk_yop_last_notified_poll', 0);
        $polls = $wpdb->get_results(
            "SELECT id, name FROM {$table} WHERE id > {$last} AND status='published' ORDER BY id ASC"
        );
        $count = 0;
        foreach ($polls as $poll) {
            $message = str_replace('{TITLE}', $poll->name, $template);
            $url     = 'https://app.vidakushala.com/?open_poll=' . $poll->id;
            vk_notify_all('poll', ' Nueva Encuesta', $message, $url);
            update_option('vk_yop_last_notified_poll', (int)$poll->id, false);
            $count++;
        }
        return rest_ensure_response(array('success'=>true,'notified'=>$count));
    }

    // Listar encuestas disponibles para notificar
    $polls = $wpdb->get_results(
        "SELECT id, name, status, added_date FROM {$table} ORDER BY id DESC LIMIT 50"
    );
    $last_notified = (int) get_option('vk_yop_last_notified_poll', 0);
    return rest_ensure_response(array(
        'polls'         => $polls,
        'last_notified' => $last_notified,
        'config_active' => !empty($config['new_poll']['enabled']),
    ));
}

// Eliminar hooks obsoletos que nunca se disparan en YOP Poll v7
// add_action('transition_post_status', 'vk_auto_push_new_poll_post', 10, 3);  // NO funciona con YOP Poll v7
// add_action('yop_poll_published', 'vk_auto_push_new_poll_yop', 10, 1);       // NO existe en YOP Poll v7

// Curso completado -> notif al usuario (certificado listo)
add_action('tutor_course_complete_after', 'vk_auto_push_cert_issued', 10, 2);
function vk_auto_push_cert_issued($course_id, $user_id = 0) {
    if (!$user_id) $user_id = get_current_user_id();
    if (!$user_id) return;
    $config   = get_option('vk_push_auto_config', array());
    if (empty($config['cert_issued']['enabled'])) return;
    $course   = get_post($course_id);
    if (!$course) return;
    $template = $config['cert_issued']['template'] ?? '!Tu certificado de {COURSE} esta listo!';
    $message  = str_replace('{COURSE}', $course->post_title, $template);
    $title    = "Certificado Listo";
    $url      = 'https://app.vidakushala.com/?open_cert=' . $course_id;
    vk_notify_user($user_id, 'cert', $title, $message, $url);
}

// Curso completado -> notif al usuario
add_action('tutor_course_complete_after', 'vk_auto_push_course_complete', 11, 2);
function vk_auto_push_course_complete($course_id, $user_id = 0) {
    if (!$user_id) $user_id = get_current_user_id();
    if (!$user_id) return;
    $config   = get_option('vk_push_auto_config', array());
    if (empty($config['course_complete']['enabled'])) return;
    $course   = get_post($course_id);
    if (!$course) return;
    $template = $config['course_complete']['template'] ?? '!Felicidades! Completaste el curso {TITLE}';
    $message  = str_replace('{TITLE}', $course->post_title, $template);
    $title    = "Curso Completado";
    $url      = 'https://app.vidakushala.com/?open_course=' . $course_id;
    vk_notify_user($user_id, 'course_done', $title, $message, $url);
}

// Leccion completada -> hitos de progreso
add_action('tutor_lesson_completed_after', 'vk_auto_push_lesson_progress', 10, 1);
function vk_auto_push_lesson_progress($lesson_id) {
    $user_id = get_current_user_id();
    if (!$user_id || !function_exists('tutor_utils')) return;
    $course_id = tutor_utils()->get_course_id_by_lesson($lesson_id);
    if (!$course_id) return;
    $config = get_option('vk_push_auto_config', array());
    if (empty($config['progress']['enabled'])) return;
    $progress   = tutor_utils()->get_course_completed_percent($course_id, $user_id);
    $milestones = array(25, 50, 75);
    $sent_key   = 'vk_progress_sent_' . $course_id;
    $sent       = get_user_meta($user_id, $sent_key, true) ?: array();
    foreach ($milestones as $m) {
        if ($progress >= $m && !in_array($m, $sent)) {
            $course   = get_post($course_id);
            $template = $config['progress']['template'] ?? '!Llevas {PERCENT}% en {COURSE}! Sigue asi.';
            $msg      = str_replace(array('{PERCENT}', '{COURSE}'), array($m, $course->post_title), $template);
            $title    = " Progreso " . $m . "%";
            $url      = 'https://app.vidakushala.com/?open_course=' . $course_id;
            vk_notify_user($user_id, 'progress', $title, $msg, $url);
            $sent[] = $m;
            update_user_meta($user_id, $sent_key, $sent);
            break;
        }
    }
}

function vk_fix_cert($req) {
    $uid       = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
    $course_id = (int)$req['id'];
    $body      = $req->get_json_params() ?: array();
    global $wpdb;

    // Verificar inscripcion
    $enrolled = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type='tutor_enrolled' AND post_parent=%d AND post_author=%d LIMIT 1",
        $course_id, $uid
    ));
    if (!$enrolled) return new WP_Error('not_enrolled', 'No inscrito en este curso', array('status' => 403));

    $upload_dir   = wp_upload_dir();
    $cert_dir     = $upload_dir['basedir'] . '/tutor-certificates/';
    $cert_url_dir = $upload_dir['baseurl']  . '/tutor-certificates/';

    // 1. Ver si ya existe un post de certificado para este usuario+curso
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT ID, post_name FROM {$wpdb->posts}
         WHERE post_type IN ('tutor_certificate','tutorlms_certificate')
           AND post_author = %d AND post_parent = %d
         ORDER BY ID DESC LIMIT 1",
        $uid, $course_id
    ));
    if ($existing) {
        // Ya existe, solo corrije el meta
        update_user_meta($uid, 'tutor_certificate_generated', $existing->ID);
        return rest_ensure_response(array(
            'fixed'     => true,
            'action'    => 'meta_updated',
            'cert_post' => $existing->ID,
            'cert_hash' => $existing->post_name,
            'message'   => 'Meta corregido. El post de certificado ya existia.',
        ));
    }

    // 2. Generar un hash unico para este certificado
    $provided_hash = isset($body['cert_hash']) ? sanitize_text_field($body['cert_hash']) : '';
    if ($provided_hash && preg_match('/^[a-f0-9]{12,}$/i', $provided_hash)) {
        $cert_hash = $provided_hash;
    } else {
        $cert_hash = bin2hex(random_bytes(8)); // 16 chars hex
    }

    // 3. Crear el post de tipo tutor_certificate
    $post_id = wp_insert_post(array(
        'post_type'   => 'tutor_certificate',
        'post_status' => 'publish',
        'post_author' => $uid,
        'post_parent' => $course_id,
        'post_name'   => $cert_hash,
        'post_title'  => 'Certificate for course ' . $course_id,
    ));

    if (is_wp_error($post_id)) {
        return new WP_Error('insert_failed', $post_id->get_error_message(), array('status' => 500));
    }

    // 4. Guardar el hash en postmeta tambien
    update_post_meta($post_id, 'cert_hash', $cert_hash);
    update_post_meta($post_id, '_tutor_course_id', $course_id);

    // 5. Corregir el usermeta del usuario
    update_user_meta($uid, 'tutor_certificate_generated', $post_id);

    // 6. Ver si hay algun archivo en disco que corresponda a este hash
    $cert_img = '';
    if (is_dir($cert_dir)) {
        $files = array_merge(
            glob($cert_dir . '*.jpg')  ?: array(),
            glob($cert_dir . '*.jpeg') ?: array(),
            glob($cert_dir . '*.png')  ?: array()
        );
        foreach ($files as $file) {
            if (strpos($file, $cert_hash) !== false) {
                $cert_img = $cert_url_dir . basename($file);
                break;
            }
        }
    }

    return rest_ensure_response(array(
        'fixed'      => true,
        'action'     => 'post_created',
        'cert_post'  => $post_id,
        'cert_hash'  => $cert_hash,
        'cert_img'   => $cert_img,
        'cert_url'   => home_url('/tutor-certificate/?cert_hash=' . $cert_hash),
        'message'    => 'Post de certificado creado y meta corregido. Ahora Tutor LMS debe generar la imagen del certificado.',
    ));
}

/* ===============================================
   LINK CERT — asocia un hash existente al usuario
   POST /vk/v1/link-cert/{course_id}
   Body: { "cert_hash": "3fd2c8c0ca5e0d56" }
   Crea el post con ese hash y corrige el meta
=============================================== */
function vk_link_cert($req) {
    $uid       = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized', 'Token requerido', array('status' => 401));
    $course_id = (int)$req['id'];
    $body      = $req->get_json_params() ?: array();
    $cert_hash = isset($body['cert_hash']) ? sanitize_text_field($body['cert_hash']) : '';

    if (!$cert_hash || !preg_match('/^[a-f0-9]{8,}$/i', $cert_hash)) {
        return new WP_Error('invalid_hash', 'cert_hash invalido', array('status' => 400));
    }

    global $wpdb;
    $upload_dir   = wp_upload_dir();
    $cert_dir     = $upload_dir['basedir'] . '/tutor-certificates/';
    $cert_url_dir = $upload_dir['baseurl']  . '/tutor-certificates/';

    // Verificar que el archivo realmente existe en disco
    $cert_img = '';
    $files = array_merge(
        glob($cert_dir . '*.jpg')  ?: array(),
        glob($cert_dir . '*.jpeg') ?: array(),
        glob($cert_dir . '*.png')  ?: array()
    );
    foreach ($files as $file) {
        if (strpos($file, $cert_hash) !== false) {
            $cert_img = $cert_url_dir . basename($file);
            break;
        }
    }
    if (!$cert_img) {
        return new WP_Error('file_not_found', 'No existe archivo con ese hash en tutor-certificates/', array('status' => 404));
    }

    // Crear el post de certificado con ese hash como post_name
    $post_id = wp_insert_post(array(
        'post_type'   => 'tutor_certificate',
        'post_status' => 'publish',
        'post_author' => $uid,
        'post_parent' => $course_id,
        'post_name'   => $cert_hash,
        'post_title'  => 'Certificado curso ' . $course_id . ' usuario ' . $uid,
    ));
    if (is_wp_error($post_id)) {
        return new WP_Error('insert_failed', $post_id->get_error_message(), array('status' => 500));
    }

    update_post_meta($post_id, 'cert_hash', $cert_hash);
    update_post_meta($post_id, '_tutor_course_id', $course_id);

    // Corregir el usermeta: apuntar al post recien creado
    update_user_meta($uid, 'tutor_certificate_generated', $post_id);

    return rest_ensure_response(array(
        'success'   => true,
        'uid'       => $uid,
        'course_id' => $course_id,
        'cert_post' => $post_id,
        'cert_hash' => $cert_hash,
        'cert_img'  => $cert_img,
        'cert_url'  => home_url('/tutor-certificate/?cert_hash=' . $cert_hash),
        'message'   => 'Certificado vinculado correctamente al usuario.',
    ));
}

/* ================================================================
   PANEL ADMIN: CONFIGURACIÓN DE CERTIFICADOS DE LA APP
   ================================================================ */
function vk_cert_config_defaults() {
    return array(
        'bg_type'=>'color','bg_color'=>'#ffffff','bg_image_url'=>'','bg_image_path'=>'',
        'bg_gradient'=>false,'bg_gradient_from'=>'#3a0f28','bg_gradient_to'=>'#7b2560',
        'bg_overlay_opacity'=>0,
        'border_enabled'=>true,'border_color'=>'#6f102a','border_width'=>18,
        'watermark_text'=>'','watermark_opacity'=>8,
        'header_text'=>'DIPLOMA DE FINALIZACION','header_font_size'=>38,'header_color'=>'#6f102a','header_y'=>110,
        'header_bold'=>true,'header_line'=>true,
        'subheader_text'=>'ESTE DIPLOMA SE OTORGA A','subheader_font_size'=>13,'subheader_color'=>'#1a2e5a','subheader_y'=>158,
        'name_font_size'=>46,'name_color'=>'#6f102a','name_y'=>340,'name_align'=>'center','name_x'=>561,
        'name_italic'=>true,'name_underline'=>true,
        'has_completed_text'=>true,'completed_text'=>'Por haber completado satisfactoriamente el curso:',
        'completed_font_size'=>13,'completed_color'=>'#444444','completed_y'=>415,
        'title_font_size'=>22,'title_color'=>'#1a2e5a','title_y'=>460,'title_align'=>'center',
        'divider_enabled'=>false,'divider_y'=>530,
        'date_label'=>'Fecha:','date_font_size'=>12,'date_color'=>'#555555','date_x'=>80,'date_y'=>560,
        'cert_id_font_size'=>9,'cert_id_color'=>'#888888','cert_id_x'=>80,'cert_id_y'=>578,
        'signature_label'=>'','signature_role'=>'',
        'signature_x'=>760,'signature_y'=>640,'signature_line_w'=>200,'signature_img_url'=>'',
        'logo_enabled'=>false,'logo_url'=>'','logo_x'=>60,'logo_y'=>50,'logo_w'=>100,'logo_h'=>0,
        'qr_enabled'=>true,'qr_size'=>78,'qr_x_from_right'=>50,'qr_y_from_bottom'=>85,
        'font'=>'auto','font_title'=>'Georgia','font_body'=>'Arial',
    );
}


// Devuelve la lista de imágenes en cert-templates/ del plugin
function vk_cert_templates_list($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid, 'manage_options')) {
        return new WP_Error('forbidden', 'Acceso denegado', array('status' => 403));
    }
    // Buscar en el directorio del plugin
    $plugin_dir = plugin_dir_path(__FILE__);
    $tmpl_dir   = $plugin_dir . 'cert-templates/';
    $tmpl_url   = plugin_dir_url(__FILE__) . 'cert-templates/';
    
    $files = array();
    if (is_dir($tmpl_dir)) {
        $extensions = array('jpg', 'jpeg', 'png', 'webp', 'gif');
        $scan = scandir($tmpl_dir);
        foreach ($scan as $file) {
            if ($file === '.' || $file === '..') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $extensions)) continue;
            $files[] = array(
                'name' => $file,
                'url'  => $tmpl_url . $file,
                'size' => filesize($tmpl_dir . $file),
            );
        }
    }
    // NOTA: tutor-certificate-builder/ está excluido intencionalmente porque esas imágenes
    // son certificados renderizados con texto placeholder baked-in (nombre demo, curso demo, etc.).
    // Incluirlas como fondos causaría que el texto demo se mostrara duplicado junto a los datos reales.
    return rest_ensure_response(array('success' => true, 'files' => $files, 'count' => count($files)));
}
// Devuelve la config de certificado sin requerir auth (solo lectura)


/* ================================================================
   HELPER: Convertir imagen a base64 para evitar CORS en la PWA
   Intenta ruta local primero, luego descarga con wp_remote_get
================================================================ */
function vkx_img_to_base64($url) {
    if (empty($url)) return '';
    // Opción 1: ruta local directa
    $local = vk_url_to_local_path($url);
    if ($local && file_exists($local)) {
        $ext  = strtolower(pathinfo($local, PATHINFO_EXTENSION));
        $mime = in_array($ext,['png','gif','webp']) ? 'image/'.$ext : 'image/jpeg';
        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($local));
    }
    // Opción 2: upload_dir mapping
    $upload = wp_upload_dir();
    $base_url  = $upload['baseurl'];
    $base_path = $upload['basedir'];
    if (strpos($url, $base_url) === 0) {
        $rel   = substr($url, strlen($base_url));
        $rel   = strtok($rel, '?');
        $local2 = $base_path . $rel;
        if (file_exists($local2)) {
            $ext  = strtolower(pathinfo($local2, PATHINFO_EXTENSION));
            $mime = in_array($ext,['png','gif','webp']) ? 'image/'.$ext : 'image/jpeg';
            return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($local2));
        }
    }
    // Opción 3: ABSPATH mapping para URLs del sitio
    $home = rtrim(home_url(), '/');
    if (strpos($url, $home) === 0) {
        $rel   = strtok(substr($url, strlen($home)), '?');
        $local3 = rtrim(ABSPATH,'/') . $rel;
        if (file_exists($local3)) {
            $ext  = strtolower(pathinfo($local3, PATHINFO_EXTENSION));
            $mime = in_array($ext,['png','gif','webp']) ? 'image/'.$ext : 'image/jpeg';
            return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($local3));
        }
    }
    // Opción 4: descarga remota (último recurso)
    $resp = wp_remote_get($url, ['timeout'=>10,'sslverify'=>false]);
    if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
        $body = wp_remote_retrieve_body($resp);
        $ct   = wp_remote_retrieve_header($resp, 'content-type') ?: 'image/jpeg';
        $ct   = explode(';', $ct)[0];
        return 'data:'.$ct.';base64,'.base64_encode($body);
    }
    return ''; // fallo total — no devolver URL, evitar CORS
}

/**
 * Detecta si un data URL base64 parece ser un certificado renderizado por TutorLMS.
 * Los certs renderizados tienen exactamente 1122×794 px (canvas del renderer VK).
 * Estos NO deben usarse como fondos: contienen texto placeholder baked-in.
 */
function vkx_cert_bg_is_cert_render($data_url) {
    if (empty($data_url) || strpos($data_url, 'data:') !== 0) return false;
    $comma = strpos($data_url, ',');
    if ($comma === false) return false;
    $b64 = substr($data_url, $comma + 1);
    // Filtro rápido por tamaño: certs renderizados son 200KB–3MB en base64
    if (strlen($b64) < 50000) return false;
    $binary = base64_decode($b64, true);
    if (!$binary) return false;
    if (function_exists('getimagesizefromstring')) {
        $size = @getimagesizefromstring($binary);
        if ($size && (int)$size[0] === 1122 && (int)$size[1] === 794) return true;
    }
    return false;
}

/* Enriquecer config con todas las imágenes en base64 */
function vkx_embed_images_in_cfg(&$config) {
    // Fondo
    if (!empty($config['bg_image_url']) && $config['bg_type'] === 'image') {
        // Protección: si la URL apunta a tutor-certificate-builder/, esa imagen tiene texto
        // placeholder baked-in (demo data). No se puede usar como fondo — limpiar.
        if (strpos($config['bg_image_url'], 'tutor-certificate-builder') !== false) {
            $config['bg_image_url']  = '';
            $config['bg_image_data'] = '';
            $config['bg_type']       = 'color';
        } elseif (empty($config['bg_image_data']) || strpos($config['bg_image_data'],'data:') !== 0) {
            $b64 = vkx_img_to_base64($config['bg_image_url']);
            if ($b64) {
                $config['bg_image_data'] = $b64;
                $config['bg_image_url']  = ''; // eliminar URL para que no intente cargarla
            }
        }
    }
    // Logo — si logo_url es ya un data: URL (guardado por versión anterior del editor),
    // moverlo a logo_data directamente sin intentar convertirlo (vkx_img_to_base64 fallaria).
    if (!empty($config['logo_url'])) {
        if (strpos($config['logo_url'], 'data:') === 0) {
            if (empty($config['logo_data'])) $config['logo_data'] = $config['logo_url'];
            $config['logo_url'] = '';
        } else {
            $b64 = vkx_img_to_base64($config['logo_url']);
            if ($b64) { $config['logo_data'] = $b64; $config['logo_url'] = ''; }
        }
    }
    // Firma imagen
    if (!empty($config['signature_img_url'])) {
        if (strpos($config['signature_img_url'], 'data:') === 0) {
            if (empty($config['signature_img_data'])) $config['signature_img_data'] = $config['signature_img_url'];
            $config['signature_img_url'] = '';
        } else {
            $b64 = vkx_img_to_base64($config['signature_img_url']);
            if ($b64) { $config['signature_img_data'] = $b64; $config['signature_img_url'] = ''; }
        }
    }
}

function vk_cert_theme_public() {
    // Read directly from DB to avoid stale object cache (same as cert-config admin endpoint)
    global $wpdb;
    $option_name = 'vk_app_cert_config';
    wp_cache_delete($option_name, 'options');
    $raw     = $wpdb->get_var($wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s LIMIT 1", $option_name
    ));
    $saved   = $raw ? json_decode($raw, true) : array();
    $defaults = vk_cert_config_defaults();
    $config   = array_merge($defaults, is_array($saved) ? $saved : array());
    $config['site_url'] = home_url();
    // Limpiar nombres de instructor predeterminados que no deben aparecer en el cert global.
    // La firma debe venir de la plantilla nombrada; el config global no la gestiona.
    $legacy_sigs = array('Roberto Carlos Hidalgo','Roberto Carlos Trigueros','Instructor - Vida Kushala','Instructor · VidaKushala');
    if (in_array($config['signature_label'] ?? '', $legacy_sigs, true)) $config['signature_label'] = '';
    if (in_array($config['signature_role']  ?? '', $legacy_sigs, true)) $config['signature_role']  = '';
    // Convertir bg_image_url relativa a URL absoluta del plugin
    if (!empty($config['bg_image_url']) && !preg_match('/^https?:\/\//i', $config['bg_image_url'])) {
        $plugin_url = plugin_dir_url(__FILE__);
        $config['bg_image_url'] = rtrim($plugin_url, '/') . '/' . ltrim($config['bg_image_url'], '/');
    }
    // Convertir TODAS las imágenes a base64 para evitar CORS en la PWA
    vkx_embed_images_in_cfg($config);
    return rest_ensure_response(array('success' => true, 'config' => $config));
}
function vk_cert_config_get($req) {
    // Read directly from DB to avoid object cache returning stale/incomplete data
    global $wpdb;
    $option_name = 'vk_app_cert_config';
    wp_cache_delete($option_name, 'options');
    $raw  = $wpdb->get_var($wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s LIMIT 1", $option_name
    ));
    $saved    = $raw ? json_decode($raw, true) : array();
    $defaults = vk_cert_config_defaults();
    $config   = array_merge($defaults, is_array($saved) ? $saved : array());
    $config['site_url'] = home_url();
    // Limpiar firmas heredadas del config global (misma lógica que cert-theme)
    $legacy_sigs = array('Roberto Carlos Hidalgo','Roberto Carlos Trigueros','Instructor - Vida Kushala','Instructor · VidaKushala');
    if (in_array($config['signature_label'] ?? '', $legacy_sigs, true)) $config['signature_label'] = '';
    if (in_array($config['signature_role']  ?? '', $legacy_sigs, true)) $config['signature_role']  = '';
    // Fix relative bg_image_url
    if (!empty($config['bg_image_url']) && !preg_match('/^https?:\/\//i', $config['bg_image_url'])
        && strpos($config['bg_image_url'], 'data:') !== 0) {
        $config['bg_image_url'] = rtrim(plugin_dir_url(__FILE__), '/') . '/' . ltrim($config['bg_image_url'], '/');
    }
    // Embed image URLs as base64 so the editor canvas works without CORS restrictions
    vkx_embed_images_in_cfg($config);
    return rest_ensure_response(array('success' => true, 'config' => $config, 'defaults' => $defaults));
}
function vk_cert_config_save($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid,'manage_options')) return new WP_Error('forbidden','Se requiere rol de administrador',array('status'=>403));
    $body = $req->get_json_params();
    if (!is_array($body)) return new WP_Error('invalid','JSON inválido',array('status'=>400));

    $defaults = vk_cert_config_defaults();
    $saved    = get_option('vk_app_cert_config', array());
    $current  = array_merge($defaults, is_array($saved) ? $saved : array());

    // Complete type map — all fields the JS editor can send.
    // 'raw' = stored as-is (used for base64 data URLs which must not be sanitized).
    $type_map = array(
        // Background
        'bg_type'             => 'string',
        'bg_color'            => 'color',
        'bg_gradient'         => 'bool',
        'bg_gradient_from'    => 'color',
        'bg_gradient_to'      => 'color',
        'bg_image_url'        => 'url',
        'bg_image_path'       => 'path',
        'bg_image_data'       => 'raw',   // base64 data URL — must NOT be sanitized
        'bg_overlay_opacity'  => 'float',
        // Border
        'border_enabled'      => 'bool',
        'border_color'        => 'color',
        'border_width'        => 'int',
        // Watermark
        'watermark_text'      => 'string',
        'watermark_opacity'   => 'int',
        // Header (title)
        'header_text'         => 'string',
        'header_font_size'    => 'int',
        'header_color'        => 'color',
        'header_y'            => 'int',
        'header_bold'         => 'bool',
        'header_line'         => 'bool',
        // Subheader
        'subheader_text'      => 'string',
        'subheader_font_size' => 'int',
        'subheader_color'     => 'color',
        'subheader_y'         => 'int',
        // Student name
        'name_font_size'      => 'int',
        'name_color'          => 'color',
        'name_y'              => 'int',
        'name_align'          => 'string',
        'name_x'              => 'int',
        'name_italic'         => 'bool',
        'name_underline'      => 'bool',
        // Completed text
        'has_completed_text'  => 'bool',
        'completed_text'      => 'string',
        'completed_font_size' => 'int',
        'completed_color'     => 'color',
        'completed_y'         => 'int',
        // Course title
        'title_font_size'     => 'int',
        'title_color'         => 'color',
        'title_y'             => 'int',
        'title_align'         => 'string',
        // Divider
        'divider_enabled'     => 'bool',
        'divider_y'           => 'int',
        // Date & cert ID
        'date_label'          => 'string',
        'date_font_size'      => 'int',
        'date_color'          => 'color',
        'date_x'              => 'int',
        'date_y'              => 'int',
        'cert_id_font_size'   => 'int',
        'cert_id_color'       => 'color',
        'cert_id_x'           => 'int',
        'cert_id_y'           => 'int',
        // Signature
        'signature_label'     => 'string',
        'signature_role'      => 'string',
        'signature_x'         => 'int',
        'signature_y'         => 'int',
        'signature_line_w'    => 'int',
        'signature_img_url'   => 'url',
        'signature_img_data'  => 'raw',
        // Logo
        'logo_enabled'        => 'bool',
        'logo_url'            => 'raw',   // may be a data URL
        'logo_data'           => 'raw',   // base64 data URL when uploaded from local disk
        'logo_x'              => 'int',
        'logo_y'              => 'int',
        'logo_w'              => 'int',
        'logo_h'              => 'int',
        // QR
        'qr_enabled'          => 'bool',
        'qr_size'             => 'int',
        'qr_x_from_right'     => 'int',
        'qr_y_from_bottom'    => 'int',
        // Fonts
        'font'                => 'string',
        'font_title'          => 'string',
        'font_body'           => 'string',
    );

    $new_config = $current;
    foreach ($body as $key => $val) {
        if ($key === 'vk_token') continue; // skip auth field
        if (!array_key_exists($key, $type_map)) continue;
        $t = $type_map[$key];
        switch ($t) {
            case 'int':    $new_config[$key] = (int)$val; break;
            case 'float':  $new_config[$key] = (float)$val; break;
            case 'bool':   $new_config[$key] = (bool)$val; break;
            case 'color':  $new_config[$key] = preg_replace('/[^#a-fA-F0-9]/','', is_string($val)?$val:''); break;
            case 'url':    $new_config[$key] = is_string($val) && strpos($val,'data:')===0 ? $val : esc_url_raw($val); break;
            case 'path':   $new_config[$key] = sanitize_text_field($val); break;
            case 'raw':    $new_config[$key] = is_string($val) ? $val : ''; break; // base64/data URLs
            default:       $new_config[$key] = sanitize_text_field(is_string($val)?$val:''); break;
        }
    }

    // Try to resolve local path for bg_image_url (for server-side rendering)
    if (!empty($new_config['bg_image_url']) && empty($new_config['bg_image_path'])) {
        $local = vk_url_to_local_path($new_config['bg_image_url']);
        if ($local && file_exists($local)) $new_config['bg_image_path'] = $local;
    }
    // When bg_image_url is set (server-side file), it's the canonical source — strip large base64.
    // PHP re-embeds the URL as base64 via vkx_embed_images_in_cfg when serving to JS/PWA.
    if (!empty($new_config['bg_image_url']) && $new_config['bg_type'] === 'image') {
        $new_config['bg_image_data'] = '';
    // If only base64 is present (local upload without server file), clear URL/path.
    } elseif (!empty($new_config['bg_image_data']) && strpos($new_config['bg_image_data'],'data:')===0) {
        $new_config['bg_image_url']  = '';
        $new_config['bg_image_path'] = '';
    }
    // Bloquear guardado de imágenes de tutor-certificate-builder/ como fondo — tienen texto demo baked-in
    if (!empty($new_config['bg_image_url']) && strpos($new_config['bg_image_url'], 'tutor-certificate-builder') !== false) {
        $new_config['bg_image_url']  = '';
        $new_config['bg_image_data'] = '';
        $new_config['bg_type']       = 'color';
    }


    // Write directly to DB to avoid WordPress sanitization hooks stripping base64 data
    global $wpdb;
    $option_name = 'vk_app_cert_config';
    $json = wp_json_encode($new_config);
    wp_cache_delete($option_name, 'options');
    $exists = (int)$wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name=%s", $option_name)
    );
    if ($exists) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value=%s, autoload='no' WHERE option_name=%s",
            $json, $option_name
        ));
    } else {
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')",
            $option_name, $json
        ));
    }
    wp_cache_delete($option_name, 'options');

    // Marcar timestamp para que los certs cacheados se invaliden automáticamente
    update_option('vkx_cert_tpl_updated_at', time(), false);

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Configuración guardada correctamente.',
        'config'  => $new_config,
    ));
}
function vk_cert_upload_bg($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid,'manage_options')) return new WP_Error('forbidden','Se requiere rol de administrador',array('status'=>403));
    if (empty($_FILES['image'])) return new WP_Error('no_file','No se recibió ninguna imagen',array('status'=>400));
    require_once ABSPATH.'wp-admin/includes/image.php';
    require_once ABSPATH.'wp-admin/includes/file.php';
    require_once ABSPATH.'wp-admin/includes/media.php';
    $upload = wp_handle_upload($_FILES['image'],array('test_form'=>false));
    if (isset($upload['error'])) return new WP_Error('upload_failed',$upload['error'],array('status'=>500));
    $attach_id = wp_insert_attachment(array('post_mime_type'=>$upload['type'],'post_title'=>sanitize_file_name(pathinfo($upload['file'],PATHINFO_FILENAME)),'post_content'=>'','post_status'=>'inherit'),$upload['file']);
    if (!is_wp_error($attach_id)) { wp_update_attachment_metadata($attach_id,wp_generate_attachment_metadata($attach_id,$upload['file'])); }
    // Return base64 of the uploaded file so the editor can use it without CORS restrictions.
    // NOTE: global config is NOT updated here — the caller's JS stores the URL in VK_CERT.cfg
    // and saves via the standard save endpoint (which handles bg_image_url canonically).
    $b64 = '';
    if (file_exists($upload['file'])) {
        $ext  = strtolower(pathinfo($upload['file'], PATHINFO_EXTENSION));
        $mime = in_array($ext, array('png','gif','webp')) ? 'image/'.$ext : 'image/jpeg';
        $b64  = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($upload['file']));
    }
    return rest_ensure_response(array('success'=>true,'url'=>$upload['url'],'path'=>$upload['file'],'attach_id'=>is_wp_error($attach_id)?0:$attach_id,'base64'=>$b64,'message'=>'Imagen de fondo subida correctamente.'));
}

/**
 * POST /vk/v1/cert-clear-cache
 * Borra los JPEGs cacheados de tutor-certificates/ para forzar regeneración
 * con el nuevo diseño configurado en el panel admin.
 * Solo admins.
 */
function vk_cert_clear_cache($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid, 'manage_options')) {
        return new WP_Error('forbidden', 'Se requiere rol de administrador', array('status' => 403));
    }
    $upload = wp_upload_dir();
    $cert_dir = $upload['basedir'] . '/tutor-certificates/';
    $deleted = 0;
    if (is_dir($cert_dir)) {
        // Eliminar todos los formatos de imagen — incluyendo .jpeg y .png que genera TutorLMS
        $files = array_merge(
            glob($cert_dir . '*.jpg')  ?: array(),
            glob($cert_dir . '*.jpeg') ?: array(),
            glob($cert_dir . '*.png')  ?: array(),
            glob($cert_dir . '*.JPG')  ?: array(),
            glob($cert_dir . '*.JPEG') ?: array(),
            glob($cert_dir . '*.PNG')  ?: array()
        );
        foreach ($files as $f) {
            if (is_file($f) && unlink($f)) $deleted++;
        }
        // También borrar el meta tutor_certificate_has_image para forzar regeneración VK
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->commentmeta} WHERE meta_key = 'tutor_certificate_has_image'"
        );
    }
    // Actualizar timestamp para invalidar cualquier cert que quede sin borrar
    update_option('vkx_cert_tpl_updated_at', time(), false);

    return rest_ensure_response(array(
        'success' => true,
        'deleted' => $deleted,
        'message' => "Caché limpiado. $deleted certificados eliminados. Los próximos accesos regenerarán el certificado con el nuevo diseño.",
    ));
}

/**
 * POST /vk/v1/cert-set-default-bg
 * Establece vidakushala-cert.png (incluida en el plugin) como fondo predeterminado.
 * Actualiza la config global y todas las plantillas sin fondo personalizado.
 */
function vk_cert_set_default_bg($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid, 'manage_options')) {
        return new WP_Error('forbidden', 'Se requiere rol de administrador', array('status' => 403));
    }
    // La imagen está en app/cert-templates/, un nivel arriba del plugin (app/vk-cors/)
    $plugin_dir  = plugin_dir_path(__FILE__);
    $candidates  = array(
        $plugin_dir . 'cert-templates/vidakushala-cert.png',           // dentro del plugin
        $plugin_dir . '../cert-templates/vidakushala-cert.png',        // directorio padre del plugin
        rtrim(ABSPATH, '/') . '/cert-templates/vidakushala-cert.png',  // raíz del sitio
    );
    $img_path = '';
    foreach ($candidates as $c) {
        if (file_exists(realpath($c) ?: $c)) { $img_path = $c; break; }
    }

    // Fallback: descargar desde la URL pública
    $img_url = 'https://app.vidakushala.com/cert-templates/vidakushala-cert.png';
    if ($img_path) {
        $b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($img_path));
    } else {
        $remote = wp_remote_get($img_url, array('timeout' => 15));
        if (is_wp_error($remote) || wp_remote_retrieve_response_code($remote) !== 200) {
            return new WP_Error('not_found', 'Imagen no accesible localmente ni desde ' . $img_url, array('status' => 404));
        }
        $b64 = 'data:image/png;base64,' . base64_encode(wp_remote_retrieve_body($remote));
    }
    $updated = 0;
    global $wpdb;

    // 1. Config global
    wp_cache_delete('vk_app_cert_config', 'options');
    $raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='vk_app_cert_config' LIMIT 1");
    $cfg = $raw ? json_decode($raw, true) : array();
    if (!is_array($cfg)) $cfg = array();
    $cfg['bg_type']       = 'image';
    $cfg['bg_image_data'] = $b64;
    $cfg['bg_image_url']  = $img_url;
    $cfg['bg_image_path'] = $img_path ?: '';
    update_option('vk_app_cert_config', wp_json_encode($cfg), false);
    $updated++;

    // 2. Plantillas sin fondo personalizado
    $tpl_raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='vkx_cert_cert_tpl' LIMIT 1");
    if ($tpl_raw) {
        $templates = json_decode($tpl_raw, true);
        if (is_array($templates)) {
            $modified = false;
            foreach ($templates as &$tpl) {
                $cfg_t      = isset($tpl['config']) ? $tpl['config'] : array();
                $has_custom = isset($cfg_t['bg_type']) && $cfg_t['bg_type'] === 'image'
                           && (!empty($cfg_t['bg_image_url']) || !empty($cfg_t['bg_image_data']));
                if (!$has_custom) {
                    $tpl['config']['bg_type']       = 'image';
                    $tpl['config']['bg_image_data'] = $b64;
                    $tpl['config']['bg_image_url']  = $img_url;
                    $tpl['config']['bg_image_path'] = $img_path ?: '';
                    $modified = true;
                    $updated++;
                }
            }
            unset($tpl);
            if ($modified) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name='vkx_cert_cert_tpl'",
                    wp_json_encode($templates)
                ));
                update_option('vkx_cert_tpl_updated_at', time(), false);
            }
        }
    }

    return rest_ensure_response(array(
        'success' => true,
        'updated' => $updated,
        'url'     => $img_url,
        'message' => "Fondo predeterminado establecido en $updated configuración(es). Recarga el editor para ver el cambio.",
    ));
}

/**
 * POST /vk/v1/cert-sanitize-bg
 * Escanea TODAS las configs de certificados (global + plantillas nombradas) y
 * elimina cualquier bg_image_data que sea un certificado renderizado (1122×794).
 * Solo admins. Operación one-shot para limpiar datos legacy.
 */
function vk_cert_sanitize_bg($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid, 'manage_options')) {
        return new WP_Error('forbidden', 'Se requiere rol de administrador', array('status' => 403));
    }
    global $wpdb;
    $cleaned = 0;

    // 1. Limpiar config global (vk_app_cert_config)
    $raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='vk_app_cert_config' LIMIT 1");
    if ($raw) {
        $cfg = json_decode($raw, true);
        if (is_array($cfg) && !empty($cfg['bg_image_data']) && vkx_cert_bg_is_cert_render($cfg['bg_image_data'])) {
            $cfg['bg_image_data'] = '';
            $cfg['bg_image_url']  = '';
            $cfg['bg_type']       = 'color';
            update_option('vk_app_cert_config', wp_json_encode($cfg), false);
            $cleaned++;
        }
    }

    // 2. Limpiar plantillas nombradas (vkx_cert_cert_tpl)
    $tpl_raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='vkx_cert_cert_tpl' LIMIT 1");
    if ($tpl_raw) {
        $templates = json_decode($tpl_raw, true);
        if (is_array($templates)) {
            $modified = false;
            foreach ($templates as &$tpl) {
                if (!empty($tpl['config']['bg_image_data']) && vkx_cert_bg_is_cert_render($tpl['config']['bg_image_data'])) {
                    $tpl['config']['bg_image_data'] = '';
                    $tpl['config']['bg_image_url']  = '';
                    $tpl['config']['bg_type']       = 'color';
                    $cleaned++;
                    $modified = true;
                }
            }
            unset($tpl);
            if ($modified) {
                update_option('vkx_cert_cert_tpl', wp_json_encode($templates), false);
                update_option('vkx_cert_tpl_updated_at', time(), false);
            }
        }
    }

    return rest_ensure_response(array(
        'success' => true,
        'cleaned' => $cleaned,
        'message' => $cleaned > 0
            ? "Se limpiaron $cleaned config(s) con fondos de certificados renderizados (demo data)."
            : 'No se encontraron fondos problemáticos. El sistema está limpio.',
    ));
}

/**
 * GET /vk/v1/cert-render-data/{course_id}
 * Devuelve config del admin + datos del estudiante para renderizar el certificado
 * con el motor Canvas JS de la app.
 */
function vk_cert_render_data($req) {
    $uid       = vk_uid($req);
    $course_id = (int) $req['course_id'];

    if (!$uid) {
        return new WP_Error('unauthorized', 'Se requiere autenticacion', array('status' => 401));
    }

    // Verificar que el curso está completado
    $completed = tutor_utils()->is_completed_course($course_id, $uid);
    if (!$completed) {
        return new WP_Error('not_completed', 'El curso no ha sido completado', array('status' => 403));
    }

    // Obtener datos del certificado
    global $wpdb;
    $cert_row = $wpdb->get_row($wpdb->prepare(
        "SELECT comment_ID, comment_content as cert_hash, comment_date as completion_date
         FROM {$wpdb->comments}
         WHERE comment_post_ID = %d
           AND user_id = %d
           AND comment_type = 'course_completed'
           AND comment_agent = 'TutorLMSPlugin'
         ORDER BY comment_date DESC LIMIT 1",
        $course_id, $uid
    ));

    // Datos del estudiante
    $user         = get_userdata($uid);
    $fn           = get_user_meta($uid, 'first_name', true) ?: '';
    $ln           = get_user_meta($uid, 'last_name',  true) ?: '';
    $student_name = trim($fn . ' ' . $ln) ?: ($user ? $user->display_name : 'Estudiante');

    // Datos del curso
    $course       = get_post($course_id);
    $course_title = $course ? $course->post_title : '';

    // Fecha de finalización
    $wp_date_format = get_option('date_format');
    $ts = $cert_row ? strtotime($cert_row->completion_date) : time();
    $cert_date = date_i18n($wp_date_format, $ts);

    // Hash y URL de validación
    $cert_hash      = $cert_row ? $cert_row->cert_hash : '';
    $cert_id_short  = $cert_hash ? strtoupper(substr($cert_hash, 0, 12)) : 'CERT-' . strtoupper(substr(md5($uid . $course_id), 0, 8));
    $validation_url = home_url('/tutor-certificate/?cert_hash=' . $cert_hash);

    // Verificar si ya hay imagen cacheada
    $upload    = wp_upload_dir();
    $cert_dir  = $upload['basedir'] . '/tutor-certificates/';
    $cert_url  = $upload['baseurl'] . '/tutor-certificates/';
    $cached_img = null;
    if ($cert_hash) {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT comment_ID FROM {$wpdb->comments}
             WHERE comment_content = %s AND comment_agent = 'TutorLMSPlugin'
               AND comment_type = 'course_completed' LIMIT 1",
            $cert_hash
        ));
        if ($row) {
            $cached = get_comment_meta((int)$row->comment_ID, 'tutor_certificate_image', true);
            if ($cached && filter_var($cached, FILTER_VALIDATE_URL)) {
                // Verificar que el archivo local existe
                $local = str_replace($upload['baseurl'], $upload['basedir'], $cached);
                if (file_exists($local)) {
                    $cached_img = $cached;
                }
            }
        }
    }

    // Config del panel admin
    $admin_cfg = get_option('vk_app_cert_config', array());
    $cfg       = !empty($admin_cfg) ? array_merge(vk_cert_config_defaults(), $admin_cfg) : vk_cert_config_defaults();

    return rest_ensure_response(array(
        'success'        => true,
        'cert_img'       => $cached_img,         // null si no hay cache
        'cfg'            => $cfg,                 // config del editor
        'student_name'   => $student_name,
        'course_title'   => $course_title,
        'cert_date'      => $cert_date,
        'cert_id'        => $cert_id_short,
        'cert_hash'      => $cert_hash,
        'validation_url' => $validation_url,
    ));
}


/* ================================================================
   SITEGROUND DYNAMIC CACHE BYPASS
================================================================ */
add_action('rest_api_init', function() {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-SG-Cache: BYPASS');
        header('Surrogate-Control: no-store');
    }
}, 1);

function vk_sg_purge_all() {
    if (function_exists('sg_cachepress_purge_cache')) sg_cachepress_purge_cache();
    if (class_exists('\SiteGround_Optimizer\Supercacher\Supercacher')) {
        try { \SiteGround_Optimizer\Supercacher\Supercacher::purge_cache(); } catch(Exception $e) {}
    }
    if (function_exists('w3tc_flush_all'))       w3tc_flush_all();
    if (function_exists('rocket_clean_domain'))  rocket_clean_domain();
    wp_cache_flush();
}

/* ================================================================
   VKX — PLANTILLAS DE CERTIFICADOS CON NOMBRE v3
   Prefijo vkx_ para evitar conflictos con versiones anteriores
================================================================ */

function _vkx_read() {
    global $wpdb;
    wp_cache_delete('vk_cert_named_templates', 'options');
    $row = $wpdb->get_row(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'vk_cert_named_templates' LIMIT 1"
    );
    if (!$row) return array();
    $v = maybe_unserialize($row->option_value);
    return is_array($v) ? $v : array();
}

function _vkx_write(array $data) {
    global $wpdb;
    wp_cache_delete('vk_cert_named_templates', 'options');
    wp_cache_delete('notoptions', 'options');
    wp_cache_delete('alloptions', 'options');

    $serial = maybe_serialize($data);
    $exists = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = 'vk_cert_named_templates'"
    );
    if ($exists) {
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = 'vk_cert_named_templates'",
                $serial
            )
        );
    } else {
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES ('vk_cert_named_templates', %s, 'no')",
                $serial
            )
        );
    }
    wp_cache_delete('vk_cert_named_templates', 'options');
    vk_sg_purge_all();
    return true;
}

function _vkx_slug(string $name): string {
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n',' '=>'_'];
    $s = strtolower(strtr($name, $map));
    $s = preg_replace('/[^a-z0-9_]+/', '_', $s);
    return trim($s, '_') ?: 'plantilla';
}

function _vkx_auth($req) {
    $uid = vk_uid($req);
    return ($uid && user_can($uid, 'manage_options')) ? $uid : 0;
}

function _vkx_defaults() {
    return function_exists('vk_cert_config_defaults') ? vk_cert_config_defaults() : array();
}

/* GET /vk/v1/tpl */
function vkx_tpl_list($req) {
    if (!_vkx_auth($req)) return new WP_Error('forbidden','Solo administradores',array('status'=>403));
    $tpls  = _vkx_read();
    $assgn = (array)(get_option('vk_cert_course_assignments') ?: array());
    $usage = array();
    foreach ($assgn as $cid => $slug) $usage[$slug] = ($usage[$slug] ?? 0) + 1;
    $defs  = _vkx_defaults();
    $list  = array();
    foreach ($tpls as $slug => $t) {
        $list[] = array(
            'key'           => $slug,
            'name'          => $t['name']       ?? $slug,
            'thumb'         => $t['thumb']       ?? '',
            'created_at'    => $t['created_at']  ?? '',
            'courses_count' => $usage[$slug]     ?? 0,
            'config'        => array_merge($defs, is_array($t['config'] ?? null) ? $t['config'] : array()),
        );
    }
    return rest_ensure_response(array('success'=>true,'templates'=>$list,'count'=>count($list)));
}

/* GET /vk/v1/tpl-courses */
function vkx_tpl_courses($req) {
    if (!_vkx_auth($req)) return new WP_Error('forbidden','Solo administradores',array('status'=>403));
    $tpls  = _vkx_read();
    $assgn = (array)(get_option('vk_cert_course_assignments') ?: array());
    $posts = get_posts(array('post_type'=>'courses','posts_per_page'=>-1,'post_status'=>'publish'));
    $list  = array();
    foreach ($posts as $p) {
        $slug  = $assgn[$p->ID] ?? 'default';
        $tname = ($slug !== 'default' && isset($tpls[$slug])) ? ($tpls[$slug]['name'] ?? $slug) : 'Default (diseño global)';
        $list[] = array('id'=>$p->ID,'title'=>$p->post_title,'template_key'=>$slug,'template_name'=>$tname);
    }
    return rest_ensure_response(array('success'=>true,'courses'=>$list));
}

/* POST /vk/v1/tpl-save */
function vkx_tpl_save($req) {
    if (!_vkx_auth($req)) return new WP_Error('forbidden','Solo administradores',array('status'=>403));
    $body = $req->get_json_params();
    if (!is_array($body)) return new WP_Error('invalid','JSON inválido',array('status'=>400));

    $name   = sanitize_text_field($body['name'] ?? '');
    $slug   = sanitize_key($body['key']  ?? '');
    $config = is_array($body['config'] ?? null) ? $body['config'] : array();
    if (!$name) return new WP_Error('missing','Nombre requerido',array('status'=>400));

    $tpls = _vkx_read();
    if (!$slug) {
        $base = _vkx_slug($name); $slug = $base; $i = 2;
        while (isset($tpls[$slug])) $slug = $base . '_' . $i++;
    }

    // Sanitise config values
    $clean = array();
    foreach ($config as $k => $v) {
        $k = sanitize_key($k);
        if     (is_bool($v))              $clean[$k] = (bool)$v;
        elseif (is_int($v))               $clean[$k] = (int)$v;
        elseif (is_float($v))             $clean[$k] = (float)$v;
        elseif (is_string($v) && strlen($k) > 4 && substr($k,-4)==='_url') $clean[$k] = esc_url_raw($v);
        elseif (is_string($v) && strlen($k) > 6 && substr($k,-6)==='_color') $clean[$k] = preg_replace('/[^#a-fA-F0-9]/','',substr($v,0,9));
        elseif (is_string($v))            $clean[$k] = sanitize_text_field($v);
    }

    $thumb = $tpls[$slug]['thumb'] ?? '';
    if (!empty($clean['bg_image_url'])) $thumb = $clean['bg_image_url'];
    $now = current_time('mysql');

    $tpls[$slug] = array(
        'name'       => $name,
        'config'     => $clean,
        'thumb'      => $thumb,
        'created_at' => $tpls[$slug]['created_at'] ?? $now,
        'updated_at' => $now,
    );
    _vkx_write($tpls);
    return rest_ensure_response(array('success'=>true,'key'=>$slug,'name'=>$name,'thumb'=>$thumb,'total'=>count($tpls)));
}

/* POST /vk/v1/tpl-get */
function vkx_tpl_get($req) {
    if (!vk_uid($req)) return new WP_Error('unauthorized','Token requerido',array('status'=>401));
    $body = $req->get_json_params();
    $slug = sanitize_key($body['key'] ?? '');
    $tpls = _vkx_read();
    $defs = _vkx_defaults();
    if (!$slug || $slug === 'default' || !isset($tpls[$slug])) {
        return rest_ensure_response(array('success'=>true,'key'=>'default','name'=>'Default (diseño global)',
            'config'=>array_merge($defs, (array)(get_option('vk_app_cert_config') ?: array()))));
    }
    $t = $tpls[$slug];
    return rest_ensure_response(array('success'=>true,'key'=>$slug,'name'=>$t['name']??$slug,
        'thumb'=>$t['thumb']??'','config'=>array_merge($defs, is_array($t['config']??null)?$t['config']:array())));
}

/* POST /vk/v1/tpl-delete */
function vkx_tpl_delete($req) {
    if (!_vkx_auth($req)) return new WP_Error('forbidden','Solo administradores',array('status'=>403));
    $body = $req->get_json_params();
    $slug = sanitize_key($body['key'] ?? '');
    $tpls = _vkx_read();
    if (!$slug || !isset($tpls[$slug])) return new WP_Error('not_found','Plantilla no encontrada',array('status'=>404));
    $assgn  = (array)(get_option('vk_cert_course_assignments') ?: array());
    $in_use = count(array_keys($assgn, $slug));
    if ($in_use > 0) return new WP_Error('in_use',"Asignada a {$in_use} curso(s). Reasígnalos primero.",array('status'=>409));
    unset($tpls[$slug]);
    _vkx_write($tpls);
    return rest_ensure_response(array('success'=>true,'key'=>$slug,'total'=>count($tpls)));
}

/* POST /vk/v1/tpl-duplicate */
function vkx_tpl_duplicate($req) {
    if (!_vkx_auth($req)) return new WP_Error('forbidden','Solo administradores',array('status'=>403));
    $body     = $req->get_json_params();
    $src      = sanitize_key($body['key']  ?? '');
    $new_name = sanitize_text_field($body['name'] ?? '');
    $tpls     = _vkx_read();
    if (!$src || !isset($tpls[$src])) return new WP_Error('not_found','No encontrada',array('status'=>404));
    if (!$new_name) $new_name = ($tpls[$src]['name'] ?? $src) . ' (copia)';
    $base = _vkx_slug($new_name); $new_slug = $base; $i = 2;
    while (isset($tpls[$new_slug])) $new_slug = $base . '_' . $i++;
    $now = current_time('mysql');
    $tpls[$new_slug] = array('name'=>$new_name,'config'=>$tpls[$src]['config']??array(),
        'thumb'=>$tpls[$src]['thumb']??'','created_at'=>$now,'updated_at'=>$now);
    _vkx_write($tpls);
    return rest_ensure_response(array('success'=>true,'key'=>$new_slug,'name'=>$new_name,'original'=>$src,'total'=>count($tpls)));
}

/* POST /vk/v1/tpl-assign */
function vkx_tpl_assign($req) {
    if (!_vkx_auth($req)) return new WP_Error('forbidden','Solo administradores',array('status'=>403));
    $body      = $req->get_json_params();
    $course_id = (int)($body['course_id'] ?? 0);
    $slug      = sanitize_key($body['template'] ?? '');
    if (!$course_id) return new WP_Error('missing','course_id requerido',array('status'=>400));
    $assgn = (array)(get_option('vk_cert_course_assignments') ?: array());
    if (!$slug || $slug === 'default') unset($assgn[$course_id]);
    else $assgn[$course_id] = $slug;
    update_option('vk_cert_course_assignments', $assgn);
    vk_sg_purge_all();
    return rest_ensure_response(array('success'=>true,'course_id'=>$course_id,'template'=>$slug?:'default'));
}


/* ================================================================
   OVERRIDE: guardar plantillas de cert SIN sanitizar el JSON
   Rutas: /cert-tpl-write (POST) y /cert-tpl-read (GET)
   Usa la misma opción vk_push_auto_config pero escribe el JSON
   raw directamente con $wpdb para evitar sanitización
================================================================ */
add_action('rest_api_init', function() {
    $pub = array('permission_callback'=>'__return_true');
    register_rest_route('vk/v1', '/cert-tpl-write', array_merge($pub, array(
        'methods'  => 'POST',
        'callback' => 'vkx_cert_tpl_write',
    )));
    register_rest_route('vk/v1', '/cert-tpl-read', array_merge($pub, array(
        'methods'  => 'GET',
        'callback' => 'vkx_cert_tpl_read',
    )));
}, 99);

function vkx_cert_tpl_write($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid,'manage_options'))
        return new WP_Error('forbidden','Solo administradores',array('status'=>403));

    $body = $req->get_json_params();
    $key  = isset($body['key']) ? sanitize_key($body['key']) : '';
    $data = isset($body['data']) ? $body['data'] : null;

    if (!$key) return new WP_Error('missing','key requerido',array('status'=>400));
    if (!in_array($key, array('_cert_tpl','_cert_assign')))
        return new WP_Error('invalid','key no permitida',array('status'=>400));

    // For the template list, deduplicate by key (last entry with a given key wins)
    // También limpiar bg_image_data que sean certificados renderizados (demo data baked-in)
    if ($key === '_cert_tpl' && is_array($data)) {
        $seen  = array();
        $clean = array();
        foreach (array_reverse($data) as $tpl) {
            $tkey = isset($tpl['key']) ? (string)$tpl['key'] : '';
            if ($tkey !== '' && !isset($seen[$tkey])) {
                $seen[$tkey] = true;
                array_unshift($clean, $tpl);
            }
        }
        $data = $clean;
        // NOTE: cert-render dimension check is intentionally NOT applied here.
        // Automatic clearing of 1122x794 images causes false positives with legitimate backgrounds.
        // Use POST /cert-sanitize-bg for manual one-time cleanup of TutorLMS legacy data.
        //
        // When bg_image_url is set (server-side file), strip large base64 from stored config.
        // PHP re-embeds as base64 at read time via vkx_embed_images_in_cfg.
        foreach ($data as &$_tpl) {
            if (!empty($_tpl['config']['bg_image_url']) &&
                isset($_tpl['config']['bg_type']) && $_tpl['config']['bg_type'] === 'image' &&
                !empty($_tpl['config']['bg_image_data'])) {
                $_tpl['config']['bg_image_data'] = '';
            }
        }
        unset($_tpl);
    }

    // For assignments, remove orphaned references to deleted template keys
    if ($key === '_cert_assign' && is_array($data)) {
        global $wpdb;
        $tpl_row = $wpdb->get_var(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name='vkx_cert_cert_tpl' LIMIT 1"
        );
        $existing_tpls = $tpl_row ? json_decode($tpl_row, true) : array();
        if (is_array($existing_tpls) && count($existing_tpls) > 0) {
            $valid_keys = array_filter(array_column($existing_tpls, 'key'));
            foreach ($data as $cid => $tkey) {
                if ($tkey !== 'default' && !in_array((string)$tkey, $valid_keys, true)) {
                    // Referencia huérfana — plantilla eliminada; volver a diseño global
                    $data[$cid] = 'default';
                }
            }
        }
    }

    // Encode data as JSON string (no sanitization of the values inside)
    $json = wp_json_encode($data);
    if ($json === false)
        return new WP_Error('encode_fail','No se pudo codificar los datos',array('status'=>500));

    // Write directly to DB bypassing object cache and sanitization
    global $wpdb;
    $option_name = 'vkx_cert_' . ltrim($key,'_');

    wp_cache_delete($option_name, 'options');

    $exists = (int)$wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name=%s", $option_name)
    );

    if ($exists) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value=%s, autoload='no' WHERE option_name=%s",
            $json, $option_name
        ));
    } else {
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')",
            $option_name, $json
        ));
    }

    wp_cache_delete($option_name, 'options');
    if (function_exists('vk_sg_purge_all')) vk_sg_purge_all();

    // Marcar timestamp de última modificación para invalidar certs cacheados obsoletos
    update_option('vkx_cert_tpl_updated_at', time(), false);

    return rest_ensure_response(array(
        'success' => true,
        'key'     => $key,
        'count'   => is_array($data) ? count($data) : 1,
    ));
}

function vkx_cert_tpl_read($req) {
    $uid = vk_uid($req);
    // Cualquier usuario autenticado puede LEER las asignaciones (necesario para generar cert)
    // Solo admins pueden ESCRIBIR (endpoint cert-tpl-write)
    if (!$uid)
        return new WP_Error('unauthorized','Token requerido',array('status'=>401));

    global $wpdb;
    wp_cache_delete('vkx_cert_cert_tpl',   'options');
    wp_cache_delete('vkx_cert_cert_assign', 'options');

    $tpl_row = $wpdb->get_var(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name='vkx_cert_cert_tpl' LIMIT 1"
    );
    $assign_row = $wpdb->get_var(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name='vkx_cert_cert_assign' LIMIT 1"
    );

    $templates   = $tpl_row    ? json_decode($tpl_row,    true) : array();
    $assignments = $assign_row ? json_decode($assign_row, true) : array();

    if (!is_array($templates))   $templates   = array();
    if (!is_array($assignments)) $assignments = array();

    // Enrich templates with courses_count
    $usage = array();
    foreach ($assignments as $cid => $slug) {
        $usage[$slug] = ($usage[$slug] ?? 0) + 1;
    }
    foreach ($templates as &$t) {
        $t['courses_count'] = $usage[$t['key'] ?? ''] ?? 0;
    }
    unset($t);

    // Get courses
    $courses = get_posts(array('post_type'=>'courses','posts_per_page'=>-1,'post_status'=>'publish'));
    $course_list = array();
    foreach ($courses as $c) {
        $slug  = $assignments[$c->ID] ?? 'default';
        $tname = 'Default (diseño global)';
        foreach ($templates as $tpl) {
            if (($tpl['key']??'') === $slug) { $tname = $tpl['name']??$slug; break; }
        }
        $course_list[] = array(
            'id'            => $c->ID,
            'title'         => $c->post_title,
            'template_key'  => $slug,
            'template_name' => $tname,
        );
    }

    // Embeber imágenes como base64 en cada plantilla para evitar CORS
    foreach ($templates as &$tpl) {
        if (!empty($tpl['config'])) {
            vkx_embed_images_in_cfg($tpl['config']);
        }
    }
    unset($tpl);

    return rest_ensure_response(array(
        'success'     => true,
        'templates'   => $templates,
        'assignments' => $assignments,
        'courses'     => $course_list,
    ));
}

/* ================================================================
   AI CHAT PREMIUM — Endpoints completos
   Rutas: /aichat-product (GET+POST), /aichat-users, 
          /aichat-grant, /aichat-revoke, /aichat-access
================================================================ */
add_action('rest_api_init', function() {
    $pub = array('permission_callback' => '__return_true');

    register_rest_route('vk/v1', '/aichat-product', array(
        array('methods'=>'GET',  'callback'=>'vkx_aichat_product_get',  'permission_callback'=>'__return_true'),
        array('methods'=>'POST', 'callback'=>'vkx_aichat_product_save', 'permission_callback'=>'__return_true'),
    ));
    register_rest_route('vk/v1', '/aichat-access',       array_merge($pub, array('methods'=>'GET',  'callback'=>'vkx_aichat_access')));
    register_rest_route('vk/v1', '/aichat-users',        array_merge($pub, array('methods'=>'GET',  'callback'=>'vkx_aichat_users')));
    register_rest_route('vk/v1', '/aichat-grant',        array_merge($pub, array('methods'=>'POST', 'callback'=>'vkx_aichat_grant')));
    register_rest_route('vk/v1', '/aichat-revoke',       array_merge($pub, array('methods'=>'POST', 'callback'=>'vkx_aichat_revoke')));
    register_rest_route('vk/v1', '/aichat-find-user',    array_merge($pub, array('methods'=>'GET',  'callback'=>'vkx_aichat_find_user')));
    register_rest_route('vk/v1', '/aichat-upload-image', array_merge($pub, array('methods'=>'POST', 'callback'=>'vkx_aichat_upload_image')));
    register_rest_route('vk/v1', '/aichat-search-users', array_merge($pub, array('methods'=>'GET',  'callback'=>'vkx_aichat_search_users')));
}, 99);

/* GET /aichat-product */
function vkx_aichat_product_get($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized','Token requerido',array('status'=>401));
    $defaults = array(
        'name'=>'AI Chat Premium','description'=>'Acceso al asistente de IA personal.',
        'price'=>'9.99','image'=>'','status'=>'active','payment_url'=>'','contact_url'=>'','woo_product_id'=>'',
        'agent_shortcode'=>'[mwai_chatbot id="default"]','agent_name'=>'Método VK',
    );
    $product = array_merge($defaults, (array)(get_option('vkx_aichat_product') ?: array()));
    return rest_ensure_response(array('success'=>true,'product'=>$product));
}

/* POST /aichat-product */
function vkx_aichat_product_save($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid,'manage_options'))
        return new WP_Error('forbidden','Solo administradores',array('status'=>403));
    $body    = $req->get_json_params() ?: array();
    $current = (array)(get_option('vkx_aichat_product') ?: array());
    $fields  = array('name','description','price','status','woo_product_id','agent_shortcode','agent_name');
    $urls    = array('payment_url','contact_url','image');
    foreach ($fields as $k) { if (isset($body[$k])) $current[$k] = sanitize_text_field($body[$k]); }
    foreach ($urls  as $k) { if (isset($body[$k])) $current[$k] = esc_url_raw($body[$k]); }
    update_option('vkx_aichat_product', $current);
    return rest_ensure_response(array('success'=>true,'product'=>$current));
}

/* POST /aichat-upload-image — sube imagen para el producto AI */
function vkx_aichat_upload_image($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid, 'manage_options'))
        return new WP_Error('forbidden', 'Solo administradores', array('status' => 403));

    if (empty($_FILES['image']))
        return new WP_Error('no_file', 'No se recibió ningún archivo', array('status' => 400));

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $upload = wp_handle_upload($_FILES['image'], array('test_form' => false));
    if (isset($upload['error']))
        return new WP_Error('upload_failed', $upload['error'], array('status' => 500));

    // Registrar en la biblioteca de medios
    $attach_id = wp_insert_attachment(array(
        'post_mime_type' => $upload['type'],
        'post_title'     => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
        'post_status'    => 'inherit',
    ), $upload['file']);

    if (!is_wp_error($attach_id)) {
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));
    }

    return rest_ensure_response(array(
        'success'   => true,
        'url'       => $upload['url'],
        'attach_id' => is_wp_error($attach_id) ? 0 : $attach_id,
    ));
}

/* GET /aichat-access — verifica si el usuario actual tiene acceso */
function vkx_aichat_access($req) {
    $uid = vk_uid($req);
    if (!$uid) return new WP_Error('unauthorized','Token requerido',array('status'=>401));
    if (user_can($uid,'manage_options')) {
        $product = (array)(get_option('vkx_aichat_product') ?: array());
        return rest_ensure_response(array('success'=>true,'has_access'=>true,'reason'=>'admin','product'=>$product));
    }
    $product = (array)(get_option('vkx_aichat_product') ?: array());
    if (($product['status'] ?? 'active') === 'inactive')
        return rest_ensure_response(array('success'=>true,'has_access'=>false,'reason'=>'inactive','product'=>$product));
    $granted = get_user_meta($uid,'vkx_aichat_access',true);
    $has     = !empty($granted);
    // Check WooCommerce purchase
    if (!$has && !empty($product['woo_product_id']) && function_exists('wc_get_orders')) {
        $orders = wc_get_orders(array('customer_id'=>$uid,'status'=>'completed','limit'=>5));
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ((int)$item->get_product_id() === (int)$product['woo_product_id']) { $has=true; break 2; }
            }
        }
    }
    return rest_ensure_response(array('success'=>true,'has_access'=>$has,'reason'=>$has?'granted':'not_purchased','product'=>$product));
}

/* GET /aichat-users — lista usuarios con acceso manual */
function vkx_aichat_users($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid,'manage_options'))
        return new WP_Error('forbidden','Solo administradores',array('status'=>403));
    global $wpdb;
    // Traemos granted_date y expiry como campos separados.
    $rows = $wpdb->get_results(
        "SELECT u.ID, u.display_name, u.user_email,
                ua.meta_value AS granted_date,
                ue.meta_value AS expiry
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} ua ON ua.user_id=u.ID AND ua.meta_key='vkx_aichat_access'
         LEFT  JOIN {$wpdb->usermeta} ue ON ue.user_id=u.ID AND ue.meta_key='vkx_aichat_expiry'
         ORDER BY u.display_name ASC"
    );
    $list = array();
    foreach ($rows as $r) {
        $list[] = array(
            'id'           => (int)$r->ID,
            'display_name' => $r->display_name,
            'email'        => $r->user_email,
            'granted_date' => $r->granted_date,
            'expiry'       => $r->expiry ?: null,
        );
    }
    return rest_ensure_response(array('success'=>true,'users'=>$list,'total'=>count($list)));
}

/* POST /aichat-grant — acepta user_id o email */
function vkx_aichat_grant($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid,'manage_options'))
        return new WP_Error('forbidden','Solo administradores',array('status'=>403));
    $body   = $req->get_json_params() ?: array();
    $target = (int)($body['user_id'] ?? 0);
    $email  = sanitize_email($body['email'] ?? '');
    $expiry = sanitize_text_field($body['expiry'] ?? '');

    // Resolver usuario por email si no viene user_id
    if (!$target && $email) {
        $u = get_user_by('email', $email);
        if (!$u) $u = get_user_by('login', $email);
        if (!$u) return new WP_Error('not_found',
            'No existe ningún usuario registrado con el email: ' . esc_html($email),
            array('status'=>404));
        $target = (int)$u->ID;
    }
    if (!$target) return new WP_Error('missing','Envía user_id o email',array('status'=>400));

    $target_user = get_user_by('id', $target);
    if (!$target_user) return new WP_Error('not_found','Usuario ID '.$target.' no existe',array('status'=>404));

    $value = $expiry ?: current_time('mysql');
    update_user_meta($target, 'vkx_aichat_access', $value);
    return rest_ensure_response(array(
        'success'      => true,
        'user_id'      => $target,
        'display_name' => $target_user->display_name,
        'email'        => $target_user->user_email,
        'granted_date' => $value,
    ));
}

/* POST /aichat-revoke */
function vkx_aichat_revoke($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid,'manage_options'))
        return new WP_Error('forbidden','Solo administradores',array('status'=>403));
    $body = $req->get_json_params() ?: array();
    $target = (int)($body['user_id'] ?? 0);
    if (!$target) return new WP_Error('missing','user_id requerido',array('status'=>400));
    delete_user_meta($target,'vkx_aichat_access');
    return rest_ensure_response(array('success'=>true,'user_id'=>$target));
}


/* ── GET /aichat-find-user?email=X ─────────────────────────────────
   Busca un usuario en WordPress por email exacto. Admin only.
   Si no encuentra, sugiere emails parecidos.
──────────────────────────────────────────────────────────────────── */
function vkx_aichat_find_user($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid,'manage_options'))
        return new WP_Error('forbidden','Solo administradores',array('status'=>403));

    $email = sanitize_email($req->get_param('email') ?? '');
    if (!$email) return new WP_Error('missing','Parámetro email requerido',array('status'=>400));

    $user = get_user_by('email', $email);
    if (!$user) $user = get_user_by('login', $email);

    if (!$user) {
        global $wpdb;
        $domain = strstr($email, '@') ?: $email;
        $similar = $wpdb->get_results($wpdb->prepare(
            "SELECT user_email, display_name FROM {$wpdb->users}
             WHERE user_email LIKE %s LIMIT 4",
            '%' . $wpdb->esc_like($domain) . '%'
        ));
        $hint = !empty($similar)
            ? ' ¿Quisiste decir: ' . implode(', ', array_column((array)$similar,'user_email')) . '?'
            : ' Verifica que el usuario se haya registrado en el sitio.';
        return new WP_Error('not_found',
            'No existe ningún usuario con el email "' . esc_html($email) . '".' . $hint,
            array('status'=>404, 'similar'=> array_column((array)$similar,'user_email'))
        );
    }

    return rest_ensure_response(array(
        'success'      => true,
        'id'           => (int)$user->ID,
        'display_name' => $user->display_name,
        'email'        => $user->user_email,
        'avatar_url'   => get_avatar_url($user->ID, array('size'=>80)),
        'roles'        => array_values($user->roles),
        'registered'   => $user->user_registered,
        'has_access'   => !empty(get_user_meta($user->ID,'vkx_aichat_access',true)),
    ));
}

/* ── GET /aichat-search-users?q=texto ───────────────────────────────
   Búsqueda en tiempo real de usuarios WP por nombre, email o login.
   Devuelve hasta 15 resultados con estado de acceso. Admin only.
──────────────────────────────────────────────────────────────────── */
function vkx_aichat_search_users($req) {
    $uid = vk_uid($req);
    if (!$uid || !user_can($uid,'manage_options'))
        return new WP_Error('forbidden','Solo administradores',array('status'=>403));

    $q = sanitize_text_field($req->get_param('q') ?? '');
    if (strlen($q) < 2)
        return new WP_Error('too_short','Escribe al menos 2 caracteres',array('status'=>400));

    $query = new WP_User_Query(array(
        'search'         => '*' . $q . '*',
        'search_columns' => array('user_login','user_email','display_name','user_nicename'),
        'number'         => 15,
        'orderby'        => 'display_name',
        'order'          => 'ASC',
    ));

    $users = $query->get_results();
    if (empty($users)) {
        return rest_ensure_response(array(
            'success' => true,
            'users'   => array(),
            'total'   => 0,
            'message' => 'No se encontró ningún usuario con "' . esc_html($q) . '" en WordPress.',
        ));
    }

    $list = array();
    foreach ($users as $u) {
        $list[] = array(
            'id'           => (int)$u->ID,
            'display_name' => $u->display_name,
            'email'        => $u->user_email,
            'avatar_url'   => get_avatar_url($u->ID, array('size'=>60)),
            'roles'        => array_values($u->roles),
            'has_access'   => !empty(get_user_meta($u->ID,'vkx_aichat_access',true)),
            'registered'   => $u->user_registered,
        );
    }
    return rest_ensure_response(array('success'=>true,'users'=>$list,'total'=>count($list)));
}

/* WooCommerce: otorgar acceso automáticamente al completar compra */
add_action('woocommerce_order_status_completed', function($order_id) {
    if (!function_exists('wc_get_order')) return;
    $order   = wc_get_order($order_id);
    if (!$order) return;
    $product = (array)(get_option('vkx_aichat_product') ?: array());
    if (empty($product['woo_product_id'])) return;
    foreach ($order->get_items() as $item) {
        if ((int)$item->get_product_id() === (int)$product['woo_product_id']) {
            $cid = (int)$order->get_customer_id();
            if ($cid > 0) update_user_meta($cid,'vkx_aichat_access',current_time('mysql'));
            break;
        }
    }
}, 10);
require_once plugin_dir_path(__FILE__) . 'vk-aichat-send-endpoint.php';
require_once plugin_dir_path(__FILE__) . 'vk-directory.php';
require_once plugin_dir_path(__FILE__) . 'vk-directory-admin.php';

/* ═══════════════════════════════════════════════════════════════
   DOCUMENTOS — Simple Download Counter
   GET  /vk/v1/documents          → lista todos los documentos
   POST /vk/v1/documents/download → registra una descarga y devuelve URL
   ═══════════════════════════════════════════════════════════════ */
add_action('rest_api_init', function() {
    $pub  = array('permission_callback' => '__return_true');
    register_rest_route('vk/v1', '/documents',          array_merge($pub, array('methods'=>'GET',  'callback'=>'vk_documents_list')));
    register_rest_route('vk/v1', '/documents/download', array_merge($pub, array('methods'=>'POST', 'callback'=>'vk_documents_download')));
    register_rest_route('vk/v1', '/documents/file',     array_merge($pub, array('methods'=>'GET',  'callback'=>'vk_documents_file')));
});

/* ── Endpoint de stream directo: GET /vk/v1/documents/file?id=&name= ─────────
   Sirve el archivo desde el filesystem de WordPress — sin loopback, sin proxy.
   CORS permite app.vidakushala.com; para <a href> no se necesita CORS. */
function vk_documents_file( WP_REST_Request $req ) {
    // CORS explícito para fetch() y XHR desde la app
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = array('https://app.vidakushala.com','http://localhost:8080','http://localhost:3000');
    if ( in_array( $origin, $allowed, true ) ) {
        header( 'Access-Control-Allow-Origin: ' . $origin );
        header( 'Access-Control-Allow-Credentials: true' );
        header( 'Vary: Origin' );
    }

    $post_id = (int) $req->get_param('id');
    $title   = sanitize_text_field( $req->get_param('name') ?: '' );
    if ( ! $post_id ) {
        status_header(400); exit('ID inválido.');
    }

    // Contar descarga
    $count = (int) get_post_meta( $post_id, '_sdc_download_count', true );
    update_post_meta( $post_id, '_sdc_download_count', $count + 1 );

    // Resolver archivo
    $resolved  = vkx_resolve_download_file( $post_id );
    $file_path = $resolved['file_path'];
    $file_url  = $resolved['file_url'];

    // MIME map
    $mime_map = array(
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip'  => 'application/zip',
        'mp4'  => 'video/mp4',   'mp3' => 'audio/mpeg',
        'jpg'  => 'image/jpeg',  'jpeg'=> 'image/jpeg',
        'png'  => 'image/png',   'gif' => 'image/gif',
        'webp' => 'image/webp',  'svg' => 'image/svg+xml',
        'txt'  => 'text/plain',  'csv' => 'text/csv',
    );

    // Si no tenemos file_path pero tenemos URL, intentar derivar el path
    if ( ! $file_path && $file_url ) {
        $uploads    = wp_upload_dir();
        $upload_url = trailingslashit( $uploads['baseurl'] );
        $upload_dir = trailingslashit( $uploads['basedir'] );
        if ( strpos( $file_url, $upload_url ) === 0 ) {
            $file_path = $upload_dir . substr( $file_url, strlen( $upload_url ) );
        }
    }

    if ( ! $file_path || ! file_exists( $file_path ) ) {
        status_header(404); exit('Archivo no encontrado.');
    }

    $ext      = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
    $mime     = $mime_map[ $ext ] ?? 'application/octet-stream';
    $filesize = filesize( $file_path );

    // Nombre de descarga limpio (sin doble extensión)
    $title_clean = $title
        ? preg_replace( '/[\/\\\\:*?"<>|]/', '_', $title )
        : pathinfo( $file_path, PATHINFO_BASENAME );
    $title_noext = $ext
        ? preg_replace( '/\.' . preg_quote( $ext, '/' ) . '$/i', '', $title_clean )
        : pathinfo( $title_clean, PATHINFO_FILENAME );
    $base     = $title_noext ?: 'archivo-' . $post_id;
    $filename = $base . ( $ext ? '.' . $ext : '' );

    // Vaciar buffers de WordPress antes de stream binario
    while ( ob_get_level() ) ob_end_clean();

    header( 'Content-Type: ' . $mime );
    $ascii = preg_replace( '/[^\x20-\x7E]/', '_', $filename );
    header( 'Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
    header( 'Content-Length: ' . $filesize );
    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );
    header( 'X-Content-Type-Options: nosniff' );

    readfile( $file_path );
    exit;
}

/* Convierte un MIME type a extensión de archivo */
function vkx_mime_to_ext( $mime ) {
    $map = array(
        'application/pdf'                                                          => 'pdf',
        'application/msword'                                                       => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel'                                                 => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
        'application/vnd.ms-powerpoint'                                            => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'=> 'pptx',
        'application/zip'                                                          => 'zip',
        'application/x-rar-compressed'                                             => 'rar',
        'application/x-rar'                                                        => 'rar',
        'application/x-7z-compressed'                                              => '7z',
        'video/mp4'                                                                => 'mp4',
        'video/quicktime'                                                           => 'mov',
        'video/x-msvideo'                                                          => 'avi',
        'video/webm'                                                               => 'webm',
        'audio/mpeg'                                                               => 'mp3',
        'audio/wav'                                                                => 'wav',
        'audio/x-wav'                                                              => 'wav',
        'audio/ogg'                                                                => 'ogg',
        'audio/mp4'                                                                => 'm4a',
        'image/jpeg'                                                               => 'jpg',
        'image/png'                                                                => 'png',
        'image/gif'                                                                => 'gif',
        'image/svg+xml'                                                            => 'svg',
        'image/webp'                                                               => 'webp',
        'text/plain'                                                               => 'txt',
        'text/csv'                                                                 => 'csv',
    );
    return isset( $map[ $mime ] ) ? $map[ $mime ] : '';
}

/* Resuelve el attachment ID y la URL real del archivo de un post de descarga.
   Prueba todos los meta keys conocidos de los plugins SDC, DLM, WP File Download, etc. */
function vkx_resolve_download_file( $post_id ) {
    $attach_id = 0;
    $file_url  = '';
    $file_path = '';

    // ── 1. Probar TODOS los meta keys conocidos de plugins de descarga ─────────
    $url_meta_keys = array(
        'sdc_file', '_sdc_file', 'sdc_file_url', '_sdc_file_url',
        '_download_file', 'download_file', '_file_url', 'file_url',
        '_dlm_download_file', 'dlm_file_url', '_wpdm_link', 'wpdm_package',
    );
    foreach ( $url_meta_keys as $key ) {
        $v = get_post_meta( $post_id, $key, true );
        if ( ! $v ) continue;
        if ( is_numeric( $v ) ) {
            $attach_id = (int) $v;
            $file_url  = wp_get_attachment_url( $attach_id ) ?: '';
            $file_path = get_attached_file( $attach_id ) ?: '';
            if ( $file_url ) break;
        } elseif ( filter_var( $v, FILTER_VALIDATE_URL ) ) {
            // Intentar resolver el attachment real para obtener la URL actualizada
            $maybe_id = attachment_url_to_postid( $v );
            if ( $maybe_id ) {
                $attach_id = $maybe_id;
                $current   = wp_get_attachment_url( $attach_id );
                $file_url  = $current ?: $v;
                $file_path = get_attached_file( $attach_id ) ?: '';
            } else {
                $file_url = $v;
            }
            break;
        }
    }

    // ── 2. Buscar cualquier meta que contenga una URL a uploads/ ──────────────
    if ( ! $file_url ) {
        $all_meta = get_post_meta( $post_id );
        // Log de debug — muestra todos los meta keys disponibles
        error_log( "[vkx_docs] ALL META KEYS for id={$post_id}: " . implode(', ', array_keys($all_meta)) );
        foreach ( $all_meta as $key => $values ) {
            $v = is_array($values) ? $values[0] : $values;
            if ( $v && is_string($v) && strpos($v, 'wp-content/uploads') !== false && filter_var($v, FILTER_VALIDATE_URL) ) {
                $file_url = $v;
                error_log( "[vkx_docs] Found file URL in meta key '{$key}': {$v}" );
                break;
            }
        }
    }

    // ── 3. Adjunto hijo del post ───────────────────────────────────────────────
    if ( ! $file_url ) {
        $att = get_posts( array(
            'post_type'      => 'attachment',
            'post_parent'    => $post_id,
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        if ( $att ) {
            $attach_id = $att[0]->ID;
            $file_url  = wp_get_attachment_url( $attach_id ) ?: '';
            $file_path = get_attached_file( $attach_id ) ?: '';
        }
    }

    // Obtener ruta física si no la tenemos aún
    if ( $attach_id && ! $file_path ) {
        $file_path = get_attached_file( $attach_id ) ?: '';
    }

    return array(
        'attach_id' => $attach_id,
        'file_url'  => $file_url,
        'file_path' => $file_path,
    );
}

/* Devuelve extensión a partir de ruta/URL y, como fallback, del MIME del attachment */
function vkx_resolve_ext( $file_url, $file_path, $attach_id ) {
    // 1. Desde la ruta física (más fiable — siempre tiene extensión)
    if ( $file_path ) {
        $ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
        if ( $ext ) return $ext;
    }
    // 2. Desde la URL del archivo
    if ( $file_url ) {
        $ext = strtolower( pathinfo( parse_url( $file_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
        if ( $ext ) return $ext;
    }
    // 3. Desde el MIME type del attachment
    if ( $attach_id ) {
        $mime = get_post_mime_type( $attach_id );
        if ( $mime ) {
            $ext = vkx_mime_to_ext( $mime );
            if ( $ext ) return $ext;
        }
    }
    return '';
}

function vk_documents_list($req) {
    $args = array(
        'post_type'      => array('sdc_download', 'download', 'dlm_download', 'post', 'page'),
        'post_status'    => 'publish',
        'posts_per_page' => 200,
        'meta_query'     => array(
            'relation' => 'OR',
            array('key' => '_sdc_download_count', 'compare' => 'EXISTS'),
            array('key' => 'sdc_file',            'compare' => 'EXISTS'),
            array('key' => '_download_file',      'compare' => 'EXISTS'),
        ),
        'orderby' => 'date',
        'order'   => 'DESC',
    );
    if ( post_type_exists('sdc_download') ) {
        $args['post_type']      = 'sdc_download';
        $args['meta_query']     = array();
        $args['posts_per_page'] = 500;
    }

    $query = new WP_Query($args);
    $docs  = array();

    foreach ( $query->posts as $post ) {
        $resolved  = vkx_resolve_download_file( $post->ID );
        $attach_id = $resolved['attach_id'];
        $file_url  = $resolved['file_url'];
        $file_path = $resolved['file_path'];

        if ( ! $file_url ) $file_url = get_permalink( $post->ID );

        // Tamaño
        $file_size = get_post_meta($post->ID, 'sdc_file_size', true)
                  ?: get_post_meta($post->ID, '_file_size', true)
                  ?: '';
        if ( ! $file_size && $file_path && file_exists($file_path) ) {
            $b = filesize($file_path);
            if     ( $b >= 1048576 ) $file_size = round($b/1048576, 1) . ' MB';
            elseif ( $b >= 1024    ) $file_size = round($b/1024)       . ' KB';
            else                     $file_size = $b . ' B';
        }

        // Conteo de descargas
        $count = (int)get_post_meta($post->ID, '_sdc_download_count', true)
               + (int)get_post_meta($post->ID, 'sdc_download_count',  true)
               + (int)get_post_meta($post->ID, '_download_count',      true);

        // Categorías
        $cats = array();
        foreach ( array('sdc_download_category','sdc_category','download_category','category') as $tax ) {
            $terms = get_the_terms($post->ID, $tax);
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $t) $cats[] = array('id'=>$t->term_id,'name'=>$t->name,'slug'=>$t->slug);
            }
        }

        // Extensión — con triple fallback
        $ext = vkx_resolve_ext( $file_url, $file_path, $attach_id );

        // Nombre real del archivo (con extensión)
        $real_filename = $file_path
            ? basename($file_path)
            : ( $attach_id ? basename( get_attached_file($attach_id) ?: '' ) : '' );
        if ( ! $real_filename && $file_url ) {
            $real_filename = rawurldecode( basename( parse_url($file_url, PHP_URL_PATH) ) );
        }

        // Log de debug (se puede desactivar)
        error_log("[vkx_docs] id={$post->ID} title=".get_the_title($post->ID)." ext={$ext} file_url={$file_url} file_path={$file_path}");

        $docs[] = array(
            'id'            => $post->ID,
            'title'         => get_the_title($post->ID),
            'description'   => wp_strip_all_tags(get_the_excerpt($post->ID) ?: wp_trim_words($post->post_content, 25)),
            'date'          => get_the_date('d/m/Y', $post->ID),
            'date_raw'      => $post->post_date,
            'categories'    => $cats,
            'file_url'      => $file_url,
            'file_path_dbg' => WP_DEBUG ? $file_path : '',  // solo en debug
            'real_filename' => $real_filename,
            'file_size'     => $file_size,
            'file_ext'      => $ext,
            'downloads'     => $count,
            'post_url'      => get_permalink($post->ID),
        );
    }
    return rest_ensure_response(array('ok' => true, 'data' => $docs));
}

function vk_documents_download($req) {
    $body    = $req->get_json_params() ?: array();
    $post_id = (int)( $body['id'] ?? $req->get_param('id') ?? 0 );
    if (!$post_id) return new WP_Error('invalid', 'ID inválido', array('status' => 400));
    // Token opcional: si viene y es válido registramos el usuario, si no seguimos igual
    $uid = vk_uid($req);

    // Contar descarga
    $count = (int)get_post_meta($post_id, '_sdc_download_count', true);
    update_post_meta($post_id, '_sdc_download_count', $count + 1);

    // Resolver URL del archivo
    $file_url  = '';
    $attach_id = 0;
    $sdc_file  = get_post_meta($post_id, 'sdc_file', true);
    if ($sdc_file) {
        if (is_numeric($sdc_file)) { $attach_id = (int)$sdc_file; $file_url = wp_get_attachment_url($attach_id) ?: ''; }
        else { $file_url = $sdc_file; }
    }
    if (!$file_url) $file_url = get_post_meta($post_id, '_download_file', true) ?: get_post_meta($post_id, '_sdc_file_url', true) ?: '';
    if (!$file_url) {
        $att = get_posts(array('post_type'=>'attachment','posts_per_page'=>1,'post_parent'=>$post_id,'post_status'=>'inherit'));
        if ($att) { $attach_id = $att[0]->ID; $file_url = wp_get_attachment_url($att[0]->ID) ?: ''; }
    }
    if (!$file_url) return new WP_Error('no_file', 'Archivo no encontrado', array('status' => 404));

    // Nombre y tipo del archivo
    $filename = $attach_id ? basename(get_attached_file($attach_id)) : basename(parse_url($file_url, PHP_URL_PATH));
    $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime     = $attach_id ? (get_post_mime_type($attach_id) ?: 'application/octet-stream') : 'application/octet-stream';

    return rest_ensure_response(array(
        'ok'        => true,
        'url'       => $file_url,
        'filename'  => $filename,
        'ext'       => $ext,
        'mime'      => $mime,
        'downloads' => $count + 1,
    ));
}


/* ═══════════════════════════════════════════════════════════════════
   CERTIFICADO EMAIL — enlace a la app (seguro, sin romper WP)
═══════════════════════════════════════════════════════════════════ */
if (!function_exists('vkx_send_certificate_email_app')) {
function vkx_send_certificate_email_app($course_id, $uid) {
    $user   = get_userdata($uid);
    if (!$user) return;
    $course = get_post($course_id);
    if (!$course) return;

    $cert_hash = '';
    if (function_exists('tutor_utils')) {
        $comp = tutor_utils()->is_completed_course($course_id, $uid, false);
        if ($comp && !empty($comp->completed_hash)) $cert_hash = $comp->completed_hash;
    }
    if (!$cert_hash) {
        global $wpdb;
        $cert_hash = (string)$wpdb->get_var($wpdb->prepare(
            "SELECT comment_content FROM {$wpdb->comments}
             WHERE comment_type='course_completed' AND comment_post_ID=%d
               AND user_id=%d AND comment_content != ''
             ORDER BY comment_ID DESC LIMIT 1",
            $course_id, $uid
        ));
    }

    $first    = get_user_meta($uid, 'first_name', true) ?: $user->display_name;
    $email    = $user->user_email;
    $site     = get_bloginfo('name') ?: 'VidaKushala';
    $title    = $course->post_title;
    $app_url  = $cert_hash ? 'https://app.vidakushala.com/?cert=' . $cert_hash : 'https://app.vidakushala.com/';

    $subject = $site . ' - Certificado disponible: ' . $title;
    $c_content = '<h2 style="color:#b36b00;font-size:22px;margin:0 0 12px;">Certificado disponible</h2>'
        . '<p style="color:#444;font-size:15px;line-height:1.6;margin:0 0 6px;">Felicidades, <strong>' . esc_html($first) . '</strong>!</p>'
        . '<p style="color:#555;font-size:15px;line-height:1.6;margin:0 0 28px;">Has completado <strong style="color:#6b2447">' . esc_html($title) . '</strong>. Tu certificado esta disponible.</p>'
        . vkx_email_button($app_url, 'Ver mi Certificado', '#b36b00')
        . '<p style="color:#999;font-size:12px;text-align:center;margin:20px 0 0;">O copia: <a href="' . esc_url($app_url) . '" style="color:#b36b00;">' . esc_html($app_url) . '</a></p>';
    $body = vkx_email_wrapper('Certificado disponible', 'Tu certificado de ' . $title . ' esta listo', $c_content, '#b36b00');

    wp_mail($email, $subject, $body, array('Content-Type: text/html; charset=UTF-8'));
}
add_action('tutor_course_complete_after', 'vkx_send_certificate_email_app', 20, 2);
}

/* ── Recuperacion de contrasena con enlace a la app ── */
if (!function_exists('vkx_forgot_password')) {
function vkx_forgot_password($req) {
    $body  = $req->get_json_params() ?: array();
    $email = sanitize_email($body['email'] ?? '');
    if (!$email) return new WP_Error('no_email', 'Email requerido', array('status'=>400));
    $user = get_user_by('email', $email);
    if (!$user) return rest_ensure_response(array('success'=>true,'message'=>'Si existe esa cuenta, recibiras instrucciones.'));

    $key = get_password_reset_key($user);
    if (is_wp_error($key)) return new WP_Error('error','Error interno',array('status'=>500));

    $first   = get_user_meta($user->ID,'first_name',true) ?: $user->display_name;
    $site    = get_bloginfo('name') ?: 'VidaKushala';
    $app_url = 'https://app.vidakushala.com/?reset_key=' . rawurlencode($key) . '&reset_login=' . rawurlencode($user->user_login);
    $subject = $site . ' - Restablecer contrasena';
    $content_reset = '<h2 style="color:#6b2447;font-size:22px;margin:0 0 12px;">Restablecer contraseña</h2>'
        . '<p style="color:#444;font-size:15px;line-height:1.6;margin:0 0 8px;">Hola <strong>' . esc_html($first) . '</strong>,</p>'
        . '<p style="color:#555;font-size:15px;line-height:1.6;margin:0 0 28px;">Recibimos una solicitud para restablecer tu contraseña. Haz clic en el botón para continuar:</p>'
        . vkx_email_button($app_url, 'Restablecer mi contraseña')
        . '<p style="color:#999;font-size:12px;text-align:center;margin:24px 0 0;">Este enlace expira en 24 horas.<br>Si no solicitaste esto, puedes ignorar este mensaje.</p>';
    $body_html = vkx_email_wrapper('Restablecer contraseña', 'Restablece tu contraseña en VidaKushala', $content_reset);

    wp_mail($email, $subject, $body_html, array('Content-Type: text/html; charset=UTF-8'));
    return rest_ensure_response(array('success'=>true,'message'=>'Si existe esa cuenta, recibiras instrucciones.'));
}
}

if (!function_exists('vkx_reset_password')) {
function vkx_reset_password($req) {
    $body     = $req->get_json_params() ?: array();
    $key      = sanitize_text_field($body['key']      ?? '');
    $login    = sanitize_text_field($body['login']    ?? '');
    $new_pass = $body['password'] ?? '';
    if (!$key || !$login)       return new WP_Error('missing','Datos incompletos',array('status'=>400));
    if (strlen($new_pass) < 8)  return new WP_Error('weak','La contrasena debe tener al menos 8 caracteres',array('status'=>400));
    $user = check_password_reset_key($key, $login);
    if (is_wp_error($user)) return new WP_Error('expired','Enlace expirado. Solicita uno nuevo.',array('status'=>400));
    reset_password($user, $new_pass);
    return rest_ensure_response(array('success'=>true,'message'=>'Contrasena actualizada.'));
}
}



/* ─────────────────────────────────────────────────────────
   Guardar notificacion de bienvenida en BD (aparece en Home)
───────────────────────────────────────────────────────── */
if (!function_exists('vkx_save_welcome_notification')) {
function vkx_save_welcome_notification($uid) {
    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';
    if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) return;

    // Evitar duplicados — solo guardar una vez
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id=%d AND type='welcome' LIMIT 1",
        $uid
    ));
    if ($exists) return;

    $user  = get_userdata($uid);
    $name  = $user ? ($user->first_name ?: $user->display_name) : 'Estudiante';

    $wpdb->insert($table, array(
        'user_id'    => (int)$uid,
        'title'      => 'Bienvenido a VidaKushala, ' . wp_strip_all_tags($name) . '!',
        'message'    => 'Tu cuenta esta lista. Explora nuestros cursos, paquetes y productos. Empieza tu camino de aprendizaje hoy.',
        'type'       => 'welcome',
        'action_url' => 'https://app.vidakushala.com/',
        'is_read'    => 0,
        'created_at' => current_time('mysql'),
    ), array('%d','%s','%s','%s','%s','%d','%s'));
}
}

/* ═══════════════════════════════════════════════════════════════════
   ACTIVACION DE EMAIL
═══════════════════════════════════════════════════════════════════ */
if (!function_exists('vkx_activate_email')) {
function vkx_activate_email($req) {
    $body  = $req->get_json_params() ?: array();
    $token = sanitize_text_field($body['token'] ?? '');
    if (!$token) return new WP_Error('no_token','Token requerido',array('status'=>400));

    global $wpdb;
    $uid = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta}
         WHERE meta_key='_vk_email_activation_token' AND meta_value=%s LIMIT 1",
        $token
    ));

    if (!$uid) return new WP_Error('invalid_token','Enlace invalido o ya utilizado.',array('status'=>400));

    $expires = (int)get_user_meta((int)$uid, '_vk_email_activation_expires', true);
    if (time() > $expires) {
        return new WP_Error('expired','El enlace de activacion ha expirado. Solicita uno nuevo.',array('status'=>400));
    }

    // Activar cuenta
    delete_user_meta((int)$uid, '_vk_pending_activation');
    delete_user_meta((int)$uid, '_vk_email_activation_token');
    delete_user_meta((int)$uid, '_vk_email_activation_expires');

    // Guardar notificacion de bienvenida en BD (visible en Home)
    if (function_exists('vkx_save_welcome_notification')) {
        vkx_save_welcome_notification((int)$uid);
    }

    $user = get_user_by('id', (int)$uid);
    $payload = vk_user_payload($user);
    $payload['activated'] = true;
    $payload['message']   = 'Cuenta activada correctamente. Ya puedes iniciar sesion.';
    return rest_ensure_response($payload);
}
}

if (!function_exists('vkx_resend_activation')) {
function vkx_resend_activation($req) {
    $body  = $req->get_json_params() ?: array();
    $email = sanitize_email($body['email'] ?? '');
    if (!$email) return new WP_Error('no_email','Email requerido',array('status'=>400));

    $user = get_user_by('email', $email);
    if (!$user) return new WP_Error('not_found','No existe cuenta con ese correo.',array('status'=>404));

    if (!get_user_meta($user->ID, '_vk_pending_activation', true)) {
        return rest_ensure_response(array('success'=>true,'already_active'=>true,'message'=>'La cuenta ya esta activa. Puedes iniciar sesion.'));
    }

    // Nuevo token
    $token   = bin2hex(random_bytes(32));
    $expires = time() + 86400;
    update_user_meta($user->ID, '_vk_email_activation_token',   $token);
    update_user_meta($user->ID, '_vk_email_activation_expires', $expires);

    $first   = get_user_meta($user->ID,'first_name',true) ?: $user->display_name;
    $site    = get_bloginfo('name') ?: 'VidaKushala';
    $act_url = 'https://app.vidakushala.com/?activate=' . $token;
    $subject = $site . ' - Activa tu cuenta';
    $content_resend = '<h2 style="color:#6b2447;font-size:22px;margin:0 0 12px;">Nuevo enlace de activacion</h2>'
        . '<p style="color:#444;font-size:15px;line-height:1.6;margin:0 0 28px;">Hola <strong>' . esc_html($first) . '</strong>, aquí tienes un nuevo enlace para activar tu cuenta:</p>'
        . vkx_email_button($act_url, 'Activar mi cuenta')
        . '<p style="color:#999;font-size:12px;text-align:center;margin:24px 0 0;">Expira en 24 horas.</p>';
    $body_html = vkx_email_wrapper('Activa tu cuenta', 'Nuevo enlace de activacion para VidaKushala', $content_resend);

    add_filter('wp_mail_from',      function() { return 'noreply@vidakushala.com'; }, 999);
    add_filter('wp_mail_from_name', function() { return 'VidaKushala'; }, 999);
    wp_mail($email, $subject, $body_html, array('Content-Type: text/html; charset=UTF-8'));

    return rest_ensure_response(array('success'=>true,'message'=>'Correo de activacion reenviado. Revisa tu bandeja de entrada.'));
}
}

/* ═══════════════════════════════════════════════════════════
   Q&A — Preguntas y Respuestas
   Usa custom post type 'vk_question' + 'vk_answer'
   ═══════════════════════════════════════════════════════════ */

// Registrar CPTs si no existen
add_action('init', function(){
    if(!post_type_exists('vk_question')){
        register_post_type('vk_question', array(
            'public'       => false,
            'show_ui'      => true,
            'label'        => 'Preguntas',
            'supports'     => array('title','editor','author'),
            'show_in_rest' => false,
        ));
    }
    if(!post_type_exists('vk_answer')){
        register_post_type('vk_answer', array(
            'public'       => false,
            'show_ui'      => true,
            'label'        => 'Respuestas',
            'supports'     => array('editor','author'),
            'show_in_rest' => false,
        ));
    }
});

// Registrar endpoints Q&A
add_action('rest_api_init', function(){
    $pub = array('permission_callback'=>'__return_true');
    register_rest_route('vk/v1', '/qa/questions',                        array_merge($pub, array('methods'=>'GET',  'callback'=>'vkqa_list_questions')));
    register_rest_route('vk/v1', '/qa/questions',                        array_merge($pub, array('methods'=>'POST', 'callback'=>'vkqa_create_question')));
    register_rest_route('vk/v1', '/qa/questions/(?P<id>\d+)',            array_merge($pub, array('methods'=>'GET',  'callback'=>'vkqa_get_question')));
    register_rest_route('vk/v1', '/qa/questions/(?P<id>\d+)/answers',   array_merge($pub, array('methods'=>'POST', 'callback'=>'vkqa_post_answer')));
    register_rest_route('vk/v1', '/qa/questions/(?P<id>\d+)/like',      array_merge($pub, array('methods'=>'POST', 'callback'=>'vkqa_like_question')));
    register_rest_route('vk/v1', '/qa/answers/(?P<id>\d+)/like',        array_merge($pub, array('methods'=>'POST', 'callback'=>'vkqa_like_answer')));
    register_rest_route('vk/v1', '/qa/answers/(?P<id>\d+)/accept',      array_merge($pub, array('methods'=>'POST', 'callback'=>'vkqa_accept_answer')));
    register_rest_route('vk/v1', '/qa/questions/(?P<id>\d+)',            array_merge($pub, array('methods'=>'DELETE','callback'=>'vkqa_delete_question')));
}, 99);

/* Helpers */
function vkqa_current_user(WP_REST_Request $req){
    $uid = vk_uid($req);
    return $uid ? get_user_by('id', $uid) : null;
}

function vkqa_is_teacher($user){
    if(!$user) return false;
    return in_array('administrator', (array)$user->roles) || in_array('editor', (array)$user->roles) || in_array('tutor_instructor', (array)$user->roles);
}

function vkqa_question_data($post, $user_id=0){
    $cat       = get_post_meta($post->ID,'_vkqa_category',true);
    $likes     = (int)get_post_meta($post->ID,'_vkqa_likes',true);
    $user_liked= $user_id && in_array($user_id, (array)get_post_meta($post->ID,'_vkqa_liked_by',true));
    $answers   = get_posts(array('post_type'=>'vk_answer','post_parent'=>$post->ID,'post_status'=>'publish','numberposts'=>-1,'suppress_filters'=>false));
    $has_accepted = false;
    $teacher_answered = false;
    foreach($answers as $a){
        if(get_post_meta($a->ID,'_vkqa_accepted',true)) $has_accepted = true;
        $au = get_user_by('id', (int)$a->post_author);
        if($au && vkqa_is_teacher($au)) $teacher_answered = true;
    }
    $author = get_user_by('id', (int)$post->post_author);
    $content_plain = wp_strip_all_tags($post->post_content);
    return array(
        'id'              => $post->ID,
        'title'           => $post->post_title,
        'excerpt'         => mb_substr($content_plain,0,120) . (mb_strlen($content_plain)>120?'...':''),
        'content'         => $content_plain,
        'date'            => $post->post_date_gmt ? $post->post_date_gmt.'Z' : $post->post_date,
        'author'          => $author ? $author->display_name : 'Anónimo',
        'category'        => $cat,
        'likes'           => $likes,
        'user_liked'      => (bool)$user_liked,
        'answer_count'    => count($answers),
        'has_accepted'    => $has_accepted,
        'teacher_answered'=> $teacher_answered,
        'can_accept'      => ($user_id && (int)$post->post_author === $user_id) || ($user_id && vkqa_is_teacher(get_user_by('id',$user_id))),
        'can_delete'      => $user_id ? user_can($user_id,'manage_options') : false,
    );
}

function vkqa_answer_data($post, $user_id=0){
    $likes      = (int)get_post_meta($post->ID,'_vkqa_likes',true);
    $is_accepted= (bool)get_post_meta($post->ID,'_vkqa_accepted',true);
    $user_liked = $user_id && in_array($user_id,(array)get_post_meta($post->ID,'_vkqa_liked_by',true));
    $author     = get_user_by('id',(int)$post->post_author);
    return array(
        'id'         => $post->ID,
        'content'    => wp_strip_all_tags($post->post_content),
        'date'       => $post->post_date_gmt ? $post->post_date_gmt.'Z' : $post->post_date,
        'author'     => $author ? $author->display_name : 'Anónimo',
        'is_teacher' => $author ? vkqa_is_teacher($author) : false,
        'is_accepted'=> $is_accepted,
        'likes'      => $likes,
        'user_liked' => (bool)$user_liked,
    );
}

/* GET /vk/v1/qa/questions */
function vkqa_list_questions(WP_REST_Request $req){
    $u = vkqa_current_user($req);
    $uid = $u ? $u->ID : 0;
    $posts = get_posts(array(
        'post_type'      => 'vk_question',
        'post_status'    => 'publish',
        'numberposts'    => 60,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'suppress_filters'=> false,
    ));
    $out = array();
    foreach($posts as $p) $out[] = vkqa_question_data($p, $uid);
    return rest_ensure_response($out);
}

/* GET /vk/v1/qa/questions/{id} */
function vkqa_get_question(WP_REST_Request $req){
    $u = vkqa_current_user($req);
    $uid = $u ? $u->ID : 0;
    $post = get_post((int)$req['id']);
    if(!$post || $post->post_type !== 'vk_question' || $post->post_status !== 'publish')
        return new WP_Error('not_found','Pregunta no encontrada',array('status'=>404));
    $data = vkqa_question_data($post, $uid);
    $answers = get_posts(array('post_type'=>'vk_answer','post_parent'=>$post->ID,'post_status'=>'publish','numberposts'=>-1,'orderby'=>'date','order'=>'ASC','suppress_filters'=>false));
    $data['answers'] = array();
    foreach($answers as $a) $data['answers'][] = vkqa_answer_data($a,$uid);
    return rest_ensure_response($data);
}

/* POST /vk/v1/qa/questions */
function vkqa_create_question(WP_REST_Request $req){
    $u = vkqa_current_user($req);
    if(!$u) return new WP_Error('unauthorized','Debes iniciar sesión',array('status'=>401));
    $title   = sanitize_text_field($req->get_param('title'));
    $content = sanitize_textarea_field($req->get_param('content'));
    $cat     = sanitize_key($req->get_param('category'));
    if(!$title) return new WP_Error('missing','El título es requerido',array('status'=>400));
    $id = wp_insert_post(array(
        'post_type'   => 'vk_question',
        'post_status' => 'publish',
        'post_title'  => $title,
        'post_content'=> $content,
        'post_author' => $u->ID,
    ));
    if(is_wp_error($id)) return $id;
    if($cat) update_post_meta($id,'_vkqa_category',$cat);
    return rest_ensure_response(array('ok'=>true,'id'=>$id));
}

/* POST /vk/v1/qa/questions/{id}/answers */
function vkqa_post_answer(WP_REST_Request $req){
    $u = vkqa_current_user($req);
    if(!$u) return new WP_Error('unauthorized','Debes iniciar sesión',array('status'=>401));
    $question_id = (int)$req['id'];
    $q = get_post($question_id);
    if(!$q || $q->post_type !== 'vk_question') return new WP_Error('not_found','Pregunta no encontrada',array('status'=>404));
    $content = sanitize_textarea_field($req->get_param('content'));
    if(!$content) return new WP_Error('missing','El contenido es requerido',array('status'=>400));
    $id = wp_insert_post(array(
        'post_type'    => 'vk_answer',
        'post_status'  => 'publish',
        'post_content' => $content,
        'post_author'  => $u->ID,
        'post_parent'  => $question_id,
        'post_title'   => 'Respuesta a '.$question_id,
    ));
    if(is_wp_error($id)) return $id;
    return rest_ensure_response(array('ok'=>true,'id'=>$id));
}

/* POST /vk/v1/qa/questions/{id}/like */
function vkqa_like_question(WP_REST_Request $req){
    $u = vkqa_current_user($req);
    if(!$u) return new WP_Error('unauthorized','Debes iniciar sesión',array('status'=>401));
    $id = (int)$req['id'];
    $liked_by = (array)get_post_meta($id,'_vkqa_liked_by',true);
    $uid = $u->ID;
    if(in_array($uid,$liked_by)){
        $liked_by = array_values(array_diff($liked_by,array($uid)));
        $liked = false;
    } else {
        $liked_by[] = $uid;
        $liked = true;
    }
    update_post_meta($id,'_vkqa_liked_by',$liked_by);
    $likes = count($liked_by);
    update_post_meta($id,'_vkqa_likes',$likes);
    return rest_ensure_response(array('ok'=>true,'liked'=>$liked,'likes'=>$likes));
}

/* POST /vk/v1/qa/answers/{id}/like */
function vkqa_like_answer(WP_REST_Request $req){
    $u = vkqa_current_user($req);
    if(!$u) return new WP_Error('unauthorized','Debes iniciar sesión',array('status'=>401));
    $id = (int)$req['id'];
    $liked_by = (array)get_post_meta($id,'_vkqa_liked_by',true);
    $uid = $u->ID;
    if(in_array($uid,$liked_by)){
        $liked_by = array_values(array_diff($liked_by,array($uid)));
        $liked = false;
    } else {
        $liked_by[] = $uid;
        $liked = true;
    }
    update_post_meta($id,'_vkqa_liked_by',$liked_by);
    $likes = count($liked_by);
    update_post_meta($id,'_vkqa_likes',$likes);
    return rest_ensure_response(array('ok'=>true,'liked'=>$liked,'likes'=>$likes));
}

/* DELETE /vk/v1/qa/questions/{id} */
function vkqa_delete_question(WP_REST_Request $req){
    $u = vkqa_current_user($req);
    if(!$u) return new WP_Error('unauthorized','Debes iniciar sesión',array('status'=>401));
    if(!user_can($u,'manage_options')) return new WP_Error('forbidden','Solo administradores',array('status'=>403));
    $id = (int)$req['id'];
    $post = get_post($id);
    if(!$post || $post->post_type !== 'vk_question') return new WP_Error('not_found','Pregunta no encontrada',array('status'=>404));
    wp_trash_post($id);
    return rest_ensure_response(array('ok'=>true));
}

/* POST /vk/v1/qa/answers/{id}/accept */
function vkqa_accept_answer(WP_REST_Request $req){
    $u = vkqa_current_user($req);
    if(!$u) return new WP_Error('unauthorized','Debes iniciar sesión',array('status'=>401));
    $id = (int)$req['id'];
    $answer = get_post($id);
    if(!$answer || $answer->post_type !== 'vk_answer') return new WP_Error('not_found','Respuesta no encontrada',array('status'=>404));
    $q_id = (int)$answer->post_parent;
    $q    = get_post($q_id);
    if(!$q) return new WP_Error('not_found','Pregunta no encontrada',array('status'=>404));
    $can = ((int)$q->post_author === $u->ID) || vkqa_is_teacher($u);
    if(!$can) return new WP_Error('forbidden','No puedes aceptar esta respuesta',array('status'=>403));
    // Desmarcar otras respuestas aceptadas de la misma pregunta
    $siblings = get_posts(array('post_type'=>'vk_answer','post_parent'=>$q_id,'numberposts'=>-1,'suppress_filters'=>false));
    foreach($siblings as $s) delete_post_meta($s->ID,'_vkqa_accepted');
    update_post_meta($id,'_vkqa_accepted',1);
    return rest_ensure_response(array('ok'=>true));
}
