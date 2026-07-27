<?php
/**
 * DEBUG TEMPORAL — subir a /wp-content/plugins/vk-cors/
 * Abrir: https://vidakushala.com/wp-content/plugins/vk-cors/vk-aichat-debug.php?vk_token=TU_TOKEN
 * BORRAR después de usar.
 */
$wp_load = dirname(__FILE__) . '/../wp-load.php';
if (!file_exists($wp_load)) $wp_load = dirname(__FILE__) . '/../../wp-load.php';
require_once($wp_load);

header('Content-Type: application/json; charset=utf-8');

$tok = sanitize_text_field($_GET['vk_token'] ?? '');
$uid = ($tok && function_exists('vk_read_token')) ? (int)vk_read_token($tok) : 0;

if (!$uid) { echo json_encode(['error'=>'uid=0, token inválido']); exit; }

wp_set_current_user($uid);

// 1. Listar TODAS las rutas MWAI disponibles
$all_routes = rest_get_server()->get_routes();
$mwai_routes = [];
foreach ($all_routes as $route => $handlers) {
    if (stripos($route, 'mwai') !== false || stripos($route, 'ai-engine') !== false) {
        $methods = [];
        foreach ($handlers as $h) {
            $methods[] = is_array($h['methods']) ? implode(',', array_keys($h['methods'])) : $h['methods'];
        }
        $mwai_routes[$route] = array_unique($methods);
    }
}

// 2. Probar llamada directa a AI Engine Pro
$payload = [
    'newMessage' => 'hola, responde solo: OK',
    'messages'   => [],
    'stream'     => false,
    'chatId'     => 'default',
];

$req = new WP_REST_Request('POST', '/mwai/v1/chat/text');
$req->set_header('Content-Type', 'application/json');
$req->set_body(wp_json_encode($payload));
$res  = rest_do_request($req);
$data = rest_get_server()->response_to_data($res, false);

// 3. Probar también sin chatId
$payload2 = ['newMessage' => 'hola', 'messages' => [], 'stream' => false];
$req2 = new WP_REST_Request('POST', '/mwai/v1/chat/text');
$req2->set_header('Content-Type', 'application/json');
$req2->set_body(wp_json_encode($payload2));
$res2  = rest_do_request($req2);
$data2 = rest_get_server()->response_to_data($res2, false);

// 4. Info del chatbot guardado
$bots = get_option('mwai_chatbots', []);
$prod = get_option('vkx_aichat_product', []);

echo json_encode([
    'uid'           => $uid,
    'mwai_routes'   => $mwai_routes,
    'test_with_id'  => ['status' => $res->get_status(),  'data' => $data],
    'test_no_id'    => ['status' => $res2->get_status(), 'data' => $data2],
    'bots'          => array_map(fn($b) => ['botId'=>$b['botId']??'', 'id'=>$b['id']??'', 'name'=>$b['name']??''], array_values($bots)),
    'agent_shortcode' => $prod['agent_shortcode'] ?? 'no guardado',
    'classes'       => [
        'Meow_MWAI_Core'    => class_exists('Meow_MWAI_Core'),
        'MWAI_ChatbotQuery' => class_exists('MWAI_ChatbotQuery'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
