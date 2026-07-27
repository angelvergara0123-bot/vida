<?php
/**
 * vk-admin-notifications-endpoints.php
 * 
 * Endpoints faltantes para el panel de notificaciones VidaKushala.
 * 
 * INSTALACIÓN: Pega este contenido al FINAL de vk-cors.php
 * (justo antes del último ?> si existe, o al final del archivo)
 * 
 * Endpoints que agrega:
 *   GET  /vk/v1/admin-notifications        — listar todas las notificaciones (admin)
 *   POST /vk/v1/admin-notif-delete         — borrar una / todas las notificaciones (admin)
 *   POST /vk/v1/notifications/delete       — borrar notificaciones propias (usuario)
 *   POST /vk/v1/push-history-delete        — alias de push-history/delete (arregla URL)
 */

if ( ! defined( 'ABSPATH' ) ) exit;
// Solo cargar si vk-cors.php ya fue iniciado (evita error fatal al detectarse como plugin independiente)
if ( ! function_exists( 'vk_uid' ) ) return;

/* ===============================================================
   REGISTRO DE RUTAS
=============================================================== */
add_action( 'rest_api_init', 'vk_register_missing_notif_routes', 25 );
function vk_register_missing_notif_routes() {
    $pub = array( 'permission_callback' => '__return_true' );

    // Admin: listar todas las notificaciones de la BD
    register_rest_route( 'vk/v1', '/admin-notifications', array_merge( $pub, array(
        'methods'  => 'GET',
        'callback' => 'vk_admin_notifications_list',
    ) ) );

    // Admin: borrar una notificación o todas (con filtro por tipo)
    register_rest_route( 'vk/v1', '/admin-notif-delete', array_merge( $pub, array(
        'methods'  => 'POST',
        'callback' => 'vk_admin_notif_delete',
    ) ) );

    // Usuario: borrar notificaciones propias (individual / todas leídas)
    register_rest_route( 'vk/v1', '/notifications/delete', array_merge( $pub, array(
        'methods'  => 'POST',
        'callback' => 'vk_user_notif_delete',
    ) ) );

    // Admin: alias para push-history-delete (el JS llama /push-history-delete,
    // pero el plugin original registró /push-history/delete con barra)
    register_rest_route( 'vk/v1', '/push-history-delete', array_merge( $pub, array(
        'methods'  => 'POST',
        'callback' => 'vk_push_history_delete_alias',
    ) ) );
}

/* ===============================================================
   GET /vk/v1/admin-notifications
   Lista TODAS las notificaciones de la tabla vk_notifications.
   Solo accesible por administradores.
   Params GET: limit, offset, type, search
=============================================================== */
function vk_admin_notifications_list( $req ) {
    if ( ! vk_is_admin_token( $req ) ) {
        return new WP_Error( 'forbidden', 'Solo administradores', array( 'status' => 403 ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';

    $limit  = min( (int) ( $req->get_param( 'limit' )  ?? 20 ), 100 );
    $offset = max( (int) ( $req->get_param( 'offset' ) ?? 0  ),  0  );
    $type   = sanitize_text_field( $req->get_param( 'type' )   ?? '' );
    $search = sanitize_text_field( $req->get_param( 'search' ) ?? '' );

    // Construir WHERE dinámico
    $where_parts  = array( '1=1' );
    $where_values = array();

    if ( $type ) {
        $where_parts[]  = 'type = %s';
        $where_values[] = $type;
    }
    if ( $search ) {
        $where_parts[]  = '(title LIKE %s OR message LIKE %s)';
        $where_values[] = '%' . $wpdb->esc_like( $search ) . '%';
        $where_values[] = '%' . $wpdb->esc_like( $search ) . '%';
    }

    $where_sql = implode( ' AND ', $where_parts );

    // Total (para paginación)
    if ( $where_values ) {
        $total = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $where_values )
        );
    } else {
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    // Filas paginadas
    $query_values   = array_merge( $where_values, array( $limit, $offset ) );
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT n.*, u.display_name
             FROM {$table} n
             LEFT JOIN {$wpdb->users} u ON u.ID = n.user_id AND n.user_id > 0
             WHERE {$where_sql}
             ORDER BY n.created_at DESC
             LIMIT %d OFFSET %d",
            $query_values
        )
    );

    $list = array();
    foreach ( $rows as $r ) {
        $list[] = array(
            'id'           => (int) $r->id,
            'user_id'      => (int) $r->user_id,
            'display_name' => $r->display_name ?? '',
            'title'        => function_exists( 'vkx_fix_utf8' ) ? vkx_fix_utf8( $r->title )   : $r->title,
            'message'      => function_exists( 'vkx_fix_utf8' ) ? vkx_fix_utf8( $r->message ) : $r->message,
            'type'         => $r->type,
            'action_url'   => $r->action_url,
            'is_read'      => (bool) $r->is_read,
            'is_global'    => ( (int) $r->user_id === 0 ),
            'created_at'   => $r->created_at,
        );
    }

    return rest_ensure_response( array(
        'success' => true,
        'data'    => $list,
        'total'   => $total,
    ) );
}

/* ===============================================================
   POST /vk/v1/admin-notif-delete
   Elimina notificaciones desde el panel de administración.
   Body: { "id": 123 }            — borrar una
         { "all": true }          — borrar todas
         { "all": true, "type": "course" } — borrar todas de un tipo
   Solo administradores.
=============================================================== */
function vk_admin_notif_delete( $req ) {
    if ( ! vk_is_admin_token( $req ) ) {
        return new WP_Error( 'forbidden', 'Solo administradores', array( 'status' => 403 ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';
    $body  = $req->get_json_params() ?: array();

    $all      = ! empty( $body['all'] );
    $notif_id = (int) ( $body['id'] ?? 0 );
    $type     = sanitize_text_field( $body['type'] ?? '' );

    if ( $all ) {
        if ( $type ) {
            $deleted = $wpdb->delete( $table, array( 'type' => $type ), array( '%s' ) );
        } else {
            $deleted = $wpdb->query( "DELETE FROM {$table}" );
        }
        return rest_ensure_response( array(
            'success' => true,
            'deleted' => (int) $deleted,
            'message' => $type
                ? "Notificaciones de tipo '{$type}' eliminadas."
                : 'Todas las notificaciones eliminadas.',
        ) );
    }

    if ( $notif_id ) {
        $deleted = $wpdb->delete( $table, array( 'id' => $notif_id ), array( '%d' ) );
        return rest_ensure_response( array(
            'success' => (bool) $deleted,
            'deleted' => (int) $deleted,
        ) );
    }

    return new WP_Error( 'invalid', 'Debes enviar "id" o "all":true', array( 'status' => 400 ) );
}

/* ===============================================================
   POST /vk/v1/notifications/delete
   El usuario borra sus propias notificaciones desde la app.
   Body: { "id": 123 }           — borrar una propia
         { "read": true }        — borrar todas las leídas del usuario
   Requiere token de usuario válido.
=============================================================== */
function vk_user_notif_delete( $req ) {
    $uid = vk_uid( $req );
    if ( ! $uid ) {
        return new WP_Error( 'unauthorized', 'Token inválido', array( 'status' => 401 ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'vk_notifications';
    $body  = $req->get_json_params() ?: array();

    $notif_id  = (int) ( $body['id']   ?? 0 );
    $del_read  = ! empty( $body['read'] );

    // Borrar todas las leídas propias del usuario
    if ( $del_read ) {
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE user_id = %d AND is_read = 1",
                $uid
            )
        );
        // Limpiar también el registro de globales leídas en usermeta
        // (las globales no se borran de la tabla, solo se "olvida" que estaban leídas)
        // Se limpia el meta para que no crezca indefinidamente con IDs ya borrados
        $global_ids   = $wpdb->get_col( "SELECT id FROM {$table} WHERE user_id = 0" );
        $read_globals = get_user_meta( $uid, 'vk_read_global_notifs', true ) ?: array();
        // Mantener solo los IDs globales que aún existen
        $clean_globals = array_values( array_intersect(
            array_map( 'intval', (array) $read_globals ),
            array_map( 'intval', $global_ids )
        ) );
        update_user_meta( $uid, 'vk_read_global_notifs', $clean_globals );

        return rest_ensure_response( array(
            'success' => true,
            'deleted' => (int) $deleted,
        ) );
    }

    // Borrar una notificación individual
    if ( $notif_id ) {
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, user_id FROM {$table} WHERE id = %d", $notif_id )
        );

        if ( ! $row ) {
            return rest_ensure_response( array( 'success' => true, 'deleted' => 0 ) );
        }

        if ( (int) $row->user_id === 0 ) {
            // Notificación global: solo marcarla como "leída" para este usuario
            // (no borrarla de la tabla porque es compartida con todos)
            $read = get_user_meta( $uid, 'vk_read_global_notifs', true ) ?: array();
            $read[] = $notif_id;
            update_user_meta( $uid, 'vk_read_global_notifs', array_unique( array_map( 'intval', $read ) ) );
            return rest_ensure_response( array( 'success' => true, 'deleted' => 1, 'note' => 'global_hidden' ) );
        }

        // Notificación propia: borrar solo si pertenece al usuario
        if ( (int) $row->user_id === $uid ) {
            $deleted = $wpdb->delete( $table, array( 'id' => $notif_id, 'user_id' => $uid ), array( '%d', '%d' ) );
            return rest_ensure_response( array( 'success' => (bool) $deleted, 'deleted' => (int) $deleted ) );
        }

        // Intentar borrar por admin
        if ( user_can( $uid, 'manage_options' ) ) {
            $deleted = $wpdb->delete( $table, array( 'id' => $notif_id ), array( '%d' ) );
            return rest_ensure_response( array( 'success' => (bool) $deleted, 'deleted' => (int) $deleted ) );
        }

        return new WP_Error( 'forbidden', 'No puedes borrar esta notificación', array( 'status' => 403 ) );
    }

    return new WP_Error( 'invalid', 'Debes enviar "id" o "read":true', array( 'status' => 400 ) );
}

/* ===============================================================
   POST /vk/v1/push-history-delete
   Alias que el JS llama. El plugin original registró
   /push-history/delete (con barra) pero el JS llama
   /push-history-delete (con guión). Este alias arregla eso.
   Body: { "all": true }          — limpiar todo el log
         { "id": "2024-..." }     — borrar una entrada por id/date
   Solo administradores.
=============================================================== */
function vk_push_history_delete_alias( $req ) {
    if ( ! vk_is_admin_token( $req ) ) {
        return new WP_Error( 'forbidden', 'Solo administradores', array( 'status' => 403 ) );
    }

    $body    = $req->get_json_params() ?: array();
    $history = get_option( 'vk_push_history', array() );
    $deleted = 0;

    if ( ! empty( $body['all'] ) ) {
        update_option( 'vk_push_history', array() );
        $deleted = count( $history );
        return rest_ensure_response( array( 'success' => true, 'deleted' => $deleted ) );
    }

    // Borrar por id (puede ser el campo id, date, o un índice)
    $entry_id = sanitize_text_field( $body['id'] ?? '' );
    if ( $entry_id ) {
        $new_history = array();
        foreach ( $history as $h ) {
            // Comparar contra el campo id o date del registro
            $h_id = $h['id'] ?? $h['date'] ?? '';
            if ( (string) $h_id === $entry_id ) {
                $deleted++;
            } else {
                $new_history[] = $h;
            }
        }
        // Si no encontró por id, intentar borrar por índice numérico
        if ( $deleted === 0 && is_numeric( $entry_id ) ) {
            $idx = (int) $entry_id;
            if ( isset( $history[ $idx ] ) ) {
                array_splice( $history, $idx, 1 );
                $new_history = $history;
                $deleted     = 1;
            }
        }
        update_option( 'vk_push_history', array_values( $new_history ) );
        return rest_ensure_response( array( 'success' => true, 'deleted' => $deleted ) );
    }

    return new WP_Error( 'invalid', 'Envía "all":true o "id":"..."', array( 'status' => 400 ) );
}
