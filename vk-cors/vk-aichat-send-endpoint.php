<?php
/**
 * vk-aichat-send-endpoint.php
 * Agente: default (Método VK)
 */
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    $pub = array('permission_callback' => '__return_true');
    register_rest_route('vk/v1', '/aichat-ping', array_merge($pub, array(
        'methods' => 'GET', 'callback' => 'vkx_aichat_ping',
    )));
    register_rest_route('vk/v1', '/aichat-send', array_merge($pub, array(
        'methods' => 'POST', 'callback' => 'vkx_aichat_send',
    )));
});

/* ── Ping/Diagnóstico: muestra estado completo del sistema ── */
function vkx_aichat_ping($req) {
    $uid  = vk_uid($req);
    $prod = (array)(get_option('vkx_aichat_product') ?: array());

    // Rutas REST de AI Engine
    $all_routes = rest_get_server()->get_routes();
    $found = array();
    foreach (array_keys($all_routes) as $r) {
        if (preg_match('/(mwai|ai.engine|meow|chatbot|ai)/i', $r))
            $found[] = $r;
    }

    // Clases disponibles
    $classes = array_filter(array(
        'Meow_MWAI_Core','Meow_MWAI_Modules_Chatbot','MWAI_Modules_Chatbot',
        'Meow_MWAI_Query_Text','MWAI_ChatbotQuery',
    ), 'class_exists');

    // Funciones disponibles
    $funcs = array_filter(array(
        'mwai_ask_chatbot','mwai_chat','mwai_complete','mwai_run_query',
    ), 'function_exists');

    // Chatbots configurados
    $bots = get_option('mwai_chatbots', array());

    // Ruta detectada
    $route = vkx_find_mwai_route();

    // Test real si hay uid
    $test = null;
    if ($uid) {
        wp_set_current_user($uid);
        $test_msg   = 'Hola, responde solo "OK" para confirmar que funciones.';
        $bot_id     = vkx_bot_id($prod);
        $test_reply = vkx_call_ai($test_msg, $bot_id, 'ping-test', array(), true);
        if (is_wp_error($test_reply)) {
            $test = array('ok'=>false,'error'=>$test_reply->get_error_message(),'data'=>$test_reply->get_error_data());
        } else {
            $test = array('ok'=>true,'reply'=>$test_reply);
        }
    }

    return rest_ensure_response(array(
        'uid'        => $uid,
        'is_admin'   => $uid ? user_can($uid,'manage_options') : false,
        'has_access' => $uid ? (!empty(get_user_meta($uid,'vkx_aichat_access',true)) || user_can($uid,'manage_options')) : false,
        'bot_id'     => vkx_bot_id($prod),
        'route'      => $route,
        'routes'     => array_values($found),
        'classes'    => array_values($classes),
        'funcs'      => array_values($funcs),
        'bots'       => array_map(function($b){
            return array('botId'=>$b['botId']??'','name'=>$b['name']??'');
        }, array_values((array)$bots)),
        'test'       => $test,
        'php_version'=> PHP_VERSION,
        'wp_version' => get_bloginfo('version'),
    ));
}

/* ── Send ── */
function vkx_aichat_send($req) {
    // Dar tiempo suficiente para respuestas largas de IA (igual que la página WP)
    @set_time_limit(120);

    // 1. Auth
    $uid = vk_uid($req);
    if (!$uid) {
        $b   = $req->get_json_params() ?: array();
        $tok = sanitize_text_field($b['vk_token'] ?? '');
        $uid = $tok ? (int)vk_read_token($tok) : 0;
    }
    if (!$uid) return new WP_Error('unauthorized','Sesión expirada.',array('status'=>401));
    wp_set_current_user($uid);

    // 2. Acceso
    $prod    = (array)(get_option('vkx_aichat_product') ?: array());
    $granted = user_can($uid,'manage_options');
    if (!$granted) {
        if (($prod['status'] ?? 'active') === 'inactive')
            return new WP_Error('no_access','AI Chat no disponible.',array('status'=>403,'data'=>array('product'=>$prod)));
        $granted = !empty(get_user_meta($uid,'vkx_aichat_access',true));
        if (!$granted && !empty($prod['woo_product_id']) && function_exists('wc_get_orders')) {
            foreach (wc_get_orders(array('customer_id'=>$uid,'status'=>'completed','limit'=>5)) as $o) {
                foreach ($o->get_items() as $i) {
                    if ((int)$i->get_product_id() === (int)$prod['woo_product_id']) { $granted = true; break 2; }
                }
            }
        }
    }
    if (!$granted)
        return new WP_Error('no_access','Sin acceso.',array('status'=>403,'data'=>array('product'=>$prod)));

    // 3. Datos
    $body    = $req->get_json_params() ?: array();
    $msg     = sanitize_textarea_field($body['newMessage'] ?? '');
    if (!$msg) return new WP_Error('empty','Mensaje vacío.',array('status'=>400));

    $bot_id  = vkx_bot_id($prod);
    $session = sanitize_text_field($body['sessionId'] ?? '');
    $history = is_array($body['messages'] ?? null) ? $body['messages'] : array();
    $debug   = !empty($body['debug']) && user_can($uid,'manage_options');

    // 4. Llamar AI Engine
    $reply = vkx_call_ai($msg, $bot_id, $session, $history, $debug);

    if (is_wp_error($reply)) {
        $status  = (int)($reply->get_error_data()['status'] ?? 502);
        $err_msg = $reply->get_error_message();
        return new WP_Error('ai_error', $err_msg, array('status'=>$status,'data'=>$reply->get_error_data()));
    }

    return rest_ensure_response(array('reply' => $reply));
}

/**
 * Llama al motor de IA usando 4 métodos en cascada.
 * $verbose = true devuelve WP_Error con detalles para diagnóstico.
 */
function vkx_call_ai($msg, $bot_id, $session, $history, $verbose = false) {
    $log = array();

    // ── A: mwai_ask_chatbot() ────────────────────────────────────────
    if (function_exists('mwai_ask_chatbot')) {
        try {
            $params = array(
                'message'    => $msg,
                'botId'      => $bot_id,
                'chatId'     => $bot_id,
                'newMessage' => $msg,
            );
            if ($session) $params['sessionId'] = $session;

            $r = mwai_ask_chatbot($params);

            // Extraer texto de cualquier formato de respuesta
            $text = '';
            if (is_string($r))                          $text = $r;
            elseif (is_array($r))                       $text = $r['text'] ?? $r['reply'] ?? $r['result'] ?? $r['answer'] ?? '';
            elseif (is_object($r) && isset($r->text))   $text = $r->text;
            elseif (is_object($r) && isset($r->reply))  $text = $r->reply;
            elseif (is_object($r))                      $text = (string)($r->text ?? $r->reply ?? $r->result ?? '');

            if ($text !== '') return $text;
            $log[] = 'A:mwai_ask_chatbot returned empty — r_type='.gettype($r);
        } catch (Throwable $e) {
            $log[] = 'A:mwai_ask_chatbot exception: '.$e->getMessage();
        }
    } else {
        $log[] = 'A:mwai_ask_chatbot not found';
    }

    // ── B: $mwai_core->chatbot->chat_submit() ────────────────────────
    global $mwai_core;
    if ($mwai_core && method_exists($mwai_core, 'chatbot') && is_object($mwai_core->chatbot)
        && method_exists($mwai_core->chatbot, 'chat_submit')) {
        try {
            $params = array(
                'botId'     => $bot_id,
                'chatId'    => $session ?: $bot_id,
                'sessionId' => $session,
                'messages'  => $history,
                'stream'    => false,
                'scope'     => 'chatbot',
            );
            $data = $mwai_core->chatbot->chat_submit($bot_id, $msg, null, $params, false);
            if (!empty($data['reply'])) return $data['reply'];
            $log[] = 'B:chat_submit returned empty — '.json_encode($data);
        } catch (Throwable $e) {
            $log[] = 'B:chat_submit exception: '.$e->getMessage();
        }
    } else {
        $log[] = 'B:mwai_core->chatbot->chat_submit not available';
    }

    // ── C: Llamada directa PHP a MWAI (sin HTTP, sin deadlock) ─────────
    $mwai_reply = vkx_call_mwai_direct($msg, $bot_id, $session, $history, $log);
    if ($mwai_reply !== null) return $mwai_reply;

    // ── D: REST interno via rest_do_request (sin HTTP) ─────────────────
    $route = vkx_find_mwai_route();
    if ($route) {
        try {
            $messages = array();
            foreach ($history as $i => $h) {
                $messages[] = array(
                    'id'      => 'h'.$i,
                    'role'    => in_array($h['role'] ?? '', array('user','assistant')) ? $h['role'] : 'user',
                    'content' => sanitize_textarea_field($h['content'] ?? ''),
                );
            }
            $payload = array(
                'newMessage' => $msg, 'messages' => $messages,
                'stream' => false, 'botId' => $bot_id,
            );
            if ($session) $payload['sessionId'] = $session;
            $rest_req = new WP_REST_Request('POST', $route);
            $rest_req->set_header('Content-Type', 'application/json');
            $rest_req->set_json_params($payload);
            $rest_res  = rest_do_request($rest_req);
            $rest_data = rest_get_server()->response_to_data($rest_res, false);
            if ($rest_res->get_status() === 200) {
                $reply = $rest_data['result'] ?? $rest_data['reply'] ?? $rest_data['text'] ?? '';
                if ($reply) return $reply;
                $log[] = 'D:200_vacio keys='.json_encode(array_keys((array)$rest_data));
            } else {
                $log[] = 'D:status='.$rest_res->get_status().' '.substr(json_encode($rest_data),0,300);
            }
        } catch (Throwable $e) {
            $log[] = 'D:ex='.$e->getMessage();
        }
    } else {
        $log[] = 'D:ruta_no_encontrada';
    }

    // ── Todos fallaron ────────────────────────────────────────────────
    return new WP_Error(
        'ai_failed',
        'No se pudo obtener respuesta de AI Engine Pro.',
        array(
            'status'  => 502,
            'bot_id'  => $bot_id,
            'route'   => $route,
            'log'     => $log,
            'funcs'   => array_filter(array('mwai_ask_chatbot'), 'function_exists'),
        )
    );
}

/**
 * Llama al core de MWAI directamente via PHP sin pasar por REST ni HTTP.
 * $log se pasa por referencia para registrar diagnóstico detallado.
 */
function vkx_call_mwai_direct($msg, $bot_id, $session, $history, &$log = array()) {
    try {
        $chatbots = get_option('mwai_chatbots', array());
        if (!is_array($chatbots)) $chatbots = array();

        $bot = null;
        foreach ($chatbots as $b) {
            if (isset($b['botId']) && $b['botId'] === $bot_id) { $bot = $b; break; }
        }
        if (!$bot && !empty($chatbots)) $bot = $chatbots[0];
        if (!$bot) { $log[] = 'C:no_bot botId='.$bot_id.' count='.count($chatbots); return null; }

        $env_id       = $bot['envId']       ?? $bot['env_id']  ?? null;
        $model        = $bot['model']        ?? null;
        $instructions = $bot['instructions'] ?? $bot['context'] ?? '';
        $log[] = 'C:bot_found id='.($bot['botId']??'?').' model='.($model??'null')
               .' env='.($env_id??'null').' keys='.implode(',', array_keys($bot));

        $context = '';
        foreach ((array)$history as $h) {
            $role    = ($h['role'] ?? 'user') === 'assistant' ? 'AI' : 'User';
            $content = sanitize_textarea_field($h['content'] ?? '');
            if ($content) $context .= $role . ': ' . $content . "\n";
        }

        if (!class_exists('Meow_MWAI_Query_Text')) {
            $log[] = 'C:class_missing Meow_MWAI_Query_Text'; return null;
        }
        global $mwai_core;
        if (!$mwai_core || !method_exists($mwai_core, 'run_query')) {
            $log[] = 'C:mwai_core_missing'; return null;
        }

        $query = new Meow_MWAI_Query_Text($msg);
        if ($instructions) $query->set_instructions($instructions);
        if ($context)      $query->set_context($context);
        if ($model)        $query->set_model($model);
        if ($env_id)       $query->set_env_id($env_id);
        $query->set_max_tokens(4096);
        $query->set_scope('chatbot');

        $result = $mwai_core->run_query($query);
        $log[] = 'C:run_query type='.gettype($result)
               .(is_wp_error($result)?' err='.$result->get_error_message():'');

        if ($result && !is_wp_error($result)) {
            $reply = is_string($result) ? $result
                   : ($result->result ?? $result->text ?? $result->reply ?? '');
            if ($reply) return $reply;
            $log[] = 'C:reply_empty';
        }
        return null;
    } catch (Throwable $e) {
        $log[] = 'C:exception='.$e->getMessage();
        return null;
    }
}


/* Busca la ruta de chat de AI Engine Pro dinámicamente */
function vkx_find_mwai_route() {
    $all = rest_get_server()->get_routes();

    // Candidatos exactos primero (más rápido y más seguro)
    $exact = array(
        '/mwai-ui/v1/chats/submit',   // AI Engine Pro >= 2.x (ruta real detectada)
        '/mwai/v1/chats/submit',
        '/mwai/v1/chat',
        '/mwai-ui/v1/chat/submit',
        '/ai-engine/v1/chats/submit',
    );
    foreach ($exact as $route) {
        if (!isset($all[$route])) continue;
        foreach ($all[$route] as $handler) {
            $methods = $handler['methods'] ?? array();
            if (is_array($methods) && isset($methods['POST'])) return $route;
            if (is_string($methods) && strpos($methods, 'POST') !== false) return $route;
        }
    }

    // Búsqueda por patrón como fallback
    $patterns = array(
        '|mwai-ui.*chats.*submit|i',
        '|mwai.*chats.*submit|i',
        '|mwai.*chat|i',
        '|ai-engine.*chat|i',
        '|chat/submit|i',
    );
    foreach ($patterns as $p) {
        foreach (array_keys($all) as $route) {
            if (preg_match($p, $route)) {
                foreach ($all[$route] as $handler) {
                    $methods = $handler['methods'] ?? array();
                    if (is_array($methods) && isset($methods['POST'])) return $route;
                    if (is_string($methods) && strpos($methods, 'POST') !== false) return $route;
                }
            }
        }
    }
    return null;
}

function vkx_bot_id($prod) {
    // Leer ID del shortcode configurado
    $sc = $prod['agent_shortcode'] ?? '';
    $configured_id = '';
    if ($sc && preg_match('/\bid=["\']?([a-zA-Z0-9_\-]+)["\']?/', $sc, $m))
        $configured_id = $m[1];

    // Verificar que exista en AI Engine; si no, usar el primero disponible
    $all_bots  = get_option('mwai_chatbots', array());
    $valid_ids = is_array($all_bots) ? array_column($all_bots, 'botId') : array();

    if ($configured_id && in_array($configured_id, $valid_ids)) return $configured_id;
    if (!empty($valid_ids)) return $valid_ids[0];   // primer bot real disponible
    return $configured_id ?: 'default';
}
