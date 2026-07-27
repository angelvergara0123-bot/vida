<?php
/**
 * vk-directory-admin.php — Panel de administración del Directorio Profesional
 *
 * Menú: Directorio → Todos los perfiles | Agregar perfil | Shortcodes | Documentación
 * Shortcodes: [directorio]  [directorio_categoria id=""]  [directorio_usuario id=""]  [directorio_destacados]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════════════════════════
   MENÚ DE ADMINISTRACIÓN
══════════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_menu', 'vkd_admin_menu' );

function vkd_admin_menu() {
    add_menu_page(
        'Directorio Profesional', 'Directorio', 'manage_options',
        'vkd-directory', 'vkd_page_list', 'dashicons-id-alt', 30
    );
    add_submenu_page( 'vkd-directory', 'Todos los perfiles', 'Todos los perfiles', 'manage_options', 'vkd-directory',  'vkd_page_list' );
    add_submenu_page( 'vkd-directory', 'Aprobación',         '🕐 Aprobación',      'manage_options', 'vkd-approval',   'vkd_page_approval' );
    add_submenu_page( 'vkd-directory', 'Agregar perfil',     'Agregar perfil',     'manage_options', 'vkd-new',        'vkd_page_edit' );
    add_submenu_page( 'vkd-directory', 'Shortcodes',         'Shortcodes',         'manage_options', 'vkd-shortcodes', 'vkd_page_shortcodes' );
    add_submenu_page( 'vkd-directory', 'Documentación',      'Documentación',      'manage_options', 'vkd-docs',       'vkd_page_docs' );
    add_submenu_page( 'vkd-directory', 'Configuración Pagos','⚙ Pagos',           'manage_options', 'vkd-payments',   'vkd_page_payments' );
    add_submenu_page( 'vkd-directory', 'Google Maps',        '🗺 Google Maps',     'manage_options', 'vkd-gmaps',      'vkd_page_gmaps' );
    add_submenu_page( 'vkd-directory', 'Categorías',        '📂 Categorías',      'manage_options', 'vkd-categories', 'vkd_page_categories' );
    // Subpágina oculta (edición)
    add_submenu_page( null, 'Editar perfil', 'Editar perfil', 'manage_options', 'vkd-edit', 'vkd_page_edit' );
}

/* ══════════════════════════════════════════════════════════════════════════════
   ASSETS ADMIN
══════════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_enqueue_scripts', 'vkd_admin_assets' );

function vkd_admin_assets( $hook ) {
    $allowed = array(
        'toplevel_page_vkd-directory',
        'directorio_page_vkd-new',
        'directorio_page_vkd-edit',
        'directorio_page_vkd-shortcodes',
        'directorio_page_vkd-docs',
        'directorio_page_vkd-payments',
    );
    if ( ! in_array( $hook, $allowed, true ) ) return;
    wp_add_inline_style( 'wp-admin', '
.vkd-wrap{max-width:1100px}
.vkd-card-row{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem}
.vkd-stat-box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:1rem 1.5rem;min-width:140px;text-align:center}
.vkd-stat-box strong{display:block;font-size:2rem;color:#2271b1}
.vkd-stat-box span{font-size:.85rem;color:#666}
.vkd-table-wrap{background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden;margin-top:.5rem}
.vkd-table{width:100%;border-collapse:collapse}
.vkd-table th{background:#f6f7f7;padding:.7rem 1rem;text-align:left;font-size:.82rem;color:#555;border-bottom:1px solid #ddd;white-space:nowrap}
.vkd-table td{padding:.65rem 1rem;border-bottom:1px solid #f0f0f0;vertical-align:middle;font-size:.87rem}
.vkd-table tr:last-child td{border-bottom:none}
.vkd-table tr:hover td{background:#fafafa}
.vkd-avatar{width:36px;height:36px;border-radius:50%;object-fit:cover}
.vkd-avatar-ph{width:36px;height:36px;border-radius:50%;background:#dde3ea;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;color:#888}
.vkd-badge{display:inline-block;padding:.15rem .55rem;border-radius:4px;font-size:.75rem;font-weight:600}
.vkd-badge.publish{background:#d4edda;color:#155724}
.vkd-badge.draft,.vkd-badge.none{background:#fff3cd;color:#856404}
.vkd-badge.trash{background:#f8d7da;color:#721c24}
.vkd-search-bar{display:flex;gap:.5rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap}
.vkd-search-bar input,.vkd-search-bar select{padding:.4rem .7rem;border:1px solid #ddd;border-radius:4px;font-size:.87rem}
.vkd-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
.vkd-form-section{background:#fff;border:1px solid #ddd;border-radius:8px;padding:1.25rem 1.5rem;margin-bottom:1.25rem}
.vkd-form-section h3{margin:0 0 1rem;font-size:.95rem;color:#2271b1;border-bottom:1px solid #f0f0f0;padding-bottom:.75rem}
.vkd-field label{display:block;font-size:.82rem;font-weight:600;color:#555;margin-bottom:.3rem}
.vkd-field input,.vkd-field textarea,.vkd-field select{width:100%;padding:.45rem .65rem;border:1px solid #ddd;border-radius:4px;font-size:.87rem;box-sizing:border-box}
.vkd-field textarea{min-height:90px;resize:vertical}
.vkd-field small{display:block;margin-top:.25rem;color:#888;font-size:.78rem}
.vkd-img-preview img{width:60px;height:60px;border-radius:8px;object-fit:cover;border:1px solid #ddd;display:block;margin-bottom:.35rem}
.vkd-pagination{display:flex;gap:.25rem;align-items:center;margin-top:1rem}
.vkd-pagination a,.vkd-pagination span{padding:.3rem .65rem;border:1px solid #ddd;border-radius:4px;font-size:.85rem;text-decoration:none;color:#2271b1;background:#fff}
.vkd-pagination span.current{background:#2271b1;color:#fff;border-color:#2271b1}
.vkd-empty{text-align:center;padding:3rem;color:#888}
.vkd-generator{background:#fff;border:1px solid #ddd;border-radius:8px;padding:1.5rem;margin-bottom:2rem}
.vkd-gen-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1rem}
.vkd-output{background:#1e1e2e;color:#cdd6f4;padding:1rem 1.25rem;border-radius:6px;font-family:monospace;font-size:.92rem;white-space:nowrap;overflow-x:auto;position:relative}
.vkd-copy-btn{position:absolute;right:.75rem;top:.75rem;background:#2271b1;color:#fff;border:none;border-radius:4px;padding:.3rem .7rem;font-size:.8rem;cursor:pointer}
.vkd-copy-btn:hover{background:#135e96}
.vkd-sc-box{background:#f6f7f7;border:1px solid #ddd;border-radius:6px;padding:.65rem 1rem;font-family:monospace;font-size:.9rem;position:relative;margin-bottom:.4rem}
.vkd-sc-copy{position:absolute;right:.5rem;top:50%;transform:translateY(-50%);padding:.2rem .55rem;font-size:.78rem;cursor:pointer}
.vkd-docs-section{background:#fff;border:1px solid #ddd;border-radius:8px;padding:1.5rem;margin-bottom:1.25rem}
.vkd-docs-section h3{color:#2271b1;margin:0 0 .75rem}
.vkd-param-table{width:100%;border-collapse:collapse;font-size:.85rem;margin-top:.75rem}
.vkd-param-table th{background:#f6f7f7;padding:.45rem .75rem;text-align:left;border:1px solid #ddd}
.vkd-param-table td{padding:.4rem .75rem;border:1px solid #eee}
.vkd-param-table td:first-child{font-family:monospace;color:#d63384;font-weight:600}
' );
}

/* ══════════════════════════════════════════════════════════════════════════════
   HELPER — tabla existe?
══════════════════════════════════════════════════════════════════════════════ */

function vkd_table_exists() {
    global $wpdb;
    $t = $wpdb->prefix . VKD_TABLE;
    return ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t );
}

/* ══════════════════════════════════════════════════════════════════════════════
   PROCESAR ACCIONES  (admin_init — antes de cualquier output)
══════════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_init', 'vkd_admin_handle_actions' );

function vkd_admin_handle_actions() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // ── DELETE ────────────────────────────────────────────────────────────────
    if (
        isset( $_GET['vkd_action'], $_GET['id'], $_GET['_wpnonce'] )
        && $_GET['vkd_action'] === 'delete'
    ) {
        $uid = (int) $_GET['id'];
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'vkd_delete_' . $uid ) ) {
            wp_die( 'Nonce inválido', 'Error de seguridad', array( 'back_link' => true ) );
        }
        global $wpdb;
        if ( vkd_table_exists() ) {
            $row = vkd_get_record( $uid );
            if ( $row ) {
                if ( $row->post_id ) wp_trash_post( (int) $row->post_id );
                $wpdb->delete( $wpdb->prefix . VKD_TABLE, array( 'user_id' => $uid ), array( '%d' ) );
                delete_user_meta( $uid, '_vk_dir_listing_id' );
            }
        }
        wp_safe_redirect( admin_url( 'admin.php?page=vkd-directory&vkd_msg=deleted' ) );
        exit;
    }

    // ── SAVE (POST) ───────────────────────────────────────────────────────────
    if ( isset( $_POST['vkd_nonce'] )
        && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vkd_nonce'] ) ), 'vkd_admin_save' )
    ) {
        // user_id_raw (manual) tiene prioridad sobre el desplegable (user_id_sel) y el campo oculto (user_id)
        $raw_val = isset( $_POST['user_id_raw'] ) ? (int) $_POST['user_id_raw'] : 0;
        $sel_val = isset( $_POST['user_id_sel'] ) ? (int) $_POST['user_id_sel'] : 0;
        $hid_val = isset( $_POST['user_id']     ) ? (int) $_POST['user_id']     : 0;
        $uid = $raw_val ?: ( $sel_val ?: $hid_val );

        if ( ! $uid ) {
            wp_die( 'Debes seleccionar un usuario de WordPress.',
                    'Usuario requerido', array( 'back_link' => true ) );
        }
        if ( ! get_userdata( $uid ) ) {
            wp_die( 'El usuario ID ' . $uid . ' no existe en WordPress.',
                    'Usuario no encontrado', array( 'back_link' => true ) );
        }

        $pv = function( $key, $default = '' ) {
            return isset( $_POST[ $key ] ) ? $_POST[ $key ] : $default;
        };

        $data = array(
            'name'              => sanitize_text_field( $pv('name') ),
            'tagline'           => sanitize_text_field( $pv('tagline') ),
            'bio'               => sanitize_textarea_field( $pv('bio') ),
            'email'             => sanitize_email( $pv('email') ),
            'phone'             => sanitize_text_field( $pv('phone') ),
            'whatsapp'          => sanitize_text_field( $pv('whatsapp') ),
            'website'           => esc_url_raw( $pv('website') ),
            'address'           => sanitize_text_field( $pv('address') ),
            'city'              => sanitize_text_field( $pv('city') ),
            'state'             => sanitize_text_field( $pv('state') ),
            'country'           => sanitize_text_field( $pv('country') ),
            'profession'        => sanitize_text_field( $pv('profession') ),
            'specialty'         => sanitize_text_field( $pv('specialty') ),
            'experience'        => sanitize_text_field( $pv('experience') ),
            'price_range'       => sanitize_text_field( $pv('price_range') ),
            'services'          => sanitize_textarea_field( $pv('services') ),
            'facebook'          => esc_url_raw( $pv('facebook') ),
            'twitter'           => esc_url_raw( $pv('twitter') ),
            'instagram'         => esc_url_raw( $pv('instagram') ),
            'linkedin'          => esc_url_raw( $pv('linkedin') ),
            'youtube'           => esc_url_raw( $pv('youtube') ),
            'tiktok'            => esc_url_raw( $pv('tiktok') ),
            'lat'               => sanitize_text_field( $pv('lat') ),
            'lng'               => sanitize_text_field( $pv('lng') ),
            'featured_image_id' => max( 0, (int) $pv('featured_image_id', 0) ),
            'logo_id'           => max( 0, (int) $pv('logo_id', 0) ),
            'category_ids'      => ( isset( $_POST['category_ids'] ) && is_array( $_POST['category_ids'] ) )
                                    ? array_filter( array_map( 'intval', $_POST['category_ids'] ) )
                                    : array(),
        );

        $row_id = vkd_upsert( $uid, $data );
        if ( is_wp_error( $row_id ) ) {
            wp_die( esc_html( $row_id->get_error_message() ), '', array( 'back_link' => true ) );
        }

        // Sincronizar con WordPress (best-effort — nunca debe impedir el redirect)
        try {
            $row_after = vkd_get_record( $uid );
            if ( $row_after && function_exists( 'vkd_sync_to_wp' ) ) {
                vkd_sync_to_wp( $row_after, $uid );
            }
        } catch ( Throwable $e ) {
            error_log( '[vkd admin] sync error uid=' . $uid . ': ' . $e->getMessage() );
        }

        // Volver al formulario de edición — timestamp evita que la caché de SiteGround sirva datos viejos
        wp_safe_redirect( admin_url( 'admin.php?page=vkd-edit&id=' . $uid . '&vkd_msg=saved&t=' . time() ) );
        exit;
    }
}

/* ══════════════════════════════════════════════════════════════════════════════
   PÁGINA 1: LISTA DE PERFILES
══════════════════════════════════════════════════════════════════════════════ */

function vkd_page_list() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    global $wpdb;
    $table = $wpdb->prefix . VKD_TABLE;

    // ── Aviso de acción ───────────────────────────────────────────────────────
    $msg   = isset( $_GET['vkd_msg'] ) ? sanitize_key( $_GET['vkd_msg'] ) : '';
    $fixed = isset( $_GET['fixed'] )   ? (int) $_GET['fixed']             : 0;
    if ( $msg === 'saved'    ) echo '<div class="notice notice-success is-dismissible"><p>✅ Perfil guardado y sincronizado.</p></div>';
    if ( $msg === 'deleted'  ) echo '<div class="notice notice-warning is-dismissible"><p>🗑 Perfil eliminado del directorio.</p></div>';
    if ( $msg === 'repaired' ) echo '<div class="notice notice-success is-dismissible"><p>🔧 Reparación completada: '
        . esc_html( $fixed ) . ' perfil(es) procesado(s). Los permalinks han sido actualizados.</p></div>';

    // ── Tabla ausente ─────────────────────────────────────────────────────────
    if ( ! vkd_table_exists() ) {
        echo '<div class="wrap"><div class="notice notice-error"><p>'
            . '<strong>La tabla del directorio aún no existe.</strong> '
            . 'Desactiva y reactiva el plugin para crearla automáticamente.'
            . '</p></div></div>';
        return;
    }

    // ── Parámetros ────────────────────────────────────────────────────────────
    $search   = sanitize_text_field( isset( $_GET['s'] )     ? wp_unslash( $_GET['s'] )     : '' );
    $paged    = max( 1, (int) ( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) );
    $per_page = 20;
    $offset   = ( $paged - 1 ) * $per_page;

    // ── Query total + filas ───────────────────────────────────────────────────
    if ( $search ) {
        $like   = '%' . $wpdb->esc_like( $search ) . '%';
        $where  = 'WHERE (p.name LIKE %s OR p.email LIKE %s OR p.profession LIKE %s OR p.city LIKE %s)';
        $total  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` p {$where}", $like, $like, $like, $like
        ) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, u.user_email, u.display_name
             FROM `{$table}` p
             LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
             {$where}
             ORDER BY p.updated_at DESC LIMIT %d OFFSET %d",
            $like, $like, $like, $like, $per_page, $offset
        ) );
    } else {
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` p WHERE 1=%d", 1
        ) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, u.user_email, u.display_name
             FROM `{$table}` p
             LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
             ORDER BY p.updated_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );
    }

    $total_pages = max( 1, (int) ceil( $total / $per_page ) );

    // ── Stats rápidas ─────────────────────────────────────────────────────────
    $cnt_pub  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE post_id > %d", 0 ) );
    $cnt_pend = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE post_id = %d", 0 ) );

    // ── Categorías para filtro ────────────────────────────────────────────────
    $categories = array();
    foreach ( array( 'at_biz_dir-category', 'atbdp_listing_category' ) as $tax ) {
        if ( ! taxonomy_exists( $tax ) ) continue;
        $terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'number' => 100 ) );
        if ( ! is_wp_error( $terms ) ) { $categories = $terms; break; }
    }

    $new_url  = admin_url( 'admin.php?page=vkd-new' );
    $list_url = admin_url( 'admin.php?page=vkd-directory' );
    ?>
    <div class="wrap vkd-wrap">
        <h1 class="wp-heading-inline"><span class="dashicons dashicons-id-alt"></span> Directorio Profesional</h1>
        <a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action">+ Agregar perfil</a>
        <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=vkd-directory&vkd_repair=1'), 'vkd_repair' ) ); ?>"
           class="page-title-action"
           onclick="return confirm('¿Reparar slugs y permalinks de todos los perfiles?')"
           style="background:#f0ad4e;color:#fff;border-color:#eea236">🔧 Reparar permalinks</a>
        <hr class="wp-header-end">

        <div class="vkd-card-row">
            <div class="vkd-stat-box"><strong><?php echo esc_html( $total ); ?></strong><span>Total perfiles</span></div>
            <div class="vkd-stat-box"><strong><?php echo esc_html( $cnt_pub ); ?></strong><span>Publicados en WP</span></div>
            <div class="vkd-stat-box"><strong><?php echo esc_html( $cnt_pend ); ?></strong><span>Sin sincronizar</span></div>
            <div class="vkd-stat-box"><strong><?php echo esc_html( count( $categories ) ); ?></strong><span>Categorías</span></div>
        </div>

        <form method="get" class="vkd-search-bar">
            <input type="hidden" name="page" value="vkd-directory">
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Buscar nombre, email, profesión, ciudad…">
            <button type="submit" class="button">Buscar</button>
            <?php if ( $search ) : ?>
                <a href="<?php echo esc_url( $list_url ); ?>" class="button button-secondary">✕ Limpiar</a>
            <?php endif; ?>
        </form>

        <div class="vkd-table-wrap">
            <?php if ( $rows ) : ?>
            <table class="vkd-table">
                <thead><tr>
                    <th style="width:40px"></th>
                    <th>Nombre</th>
                    <th>Email / WP ID</th>
                    <th>Profesión</th>
                    <th>Ciudad</th>
                    <th>Estado WP</th>
                    <th>Actualizado</th>
                    <th>Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $rows as $r ) :
                    $uid      = (int) $r->user_id;
                    $post_id  = (int) $r->post_id;
                    $post_st  = $post_id ? get_post_status( $post_id ) : 'none';
                    $lbl_map  = array( 'publish' => 'Publicado', 'draft' => 'Borrador', 'trash' => 'Papelera' );
                    $badge    = isset( $lbl_map[ $post_st ] ) ? $post_st : 'none';
                    $lbl      = isset( $lbl_map[ $post_st ] ) ? $lbl_map[ $post_st ] : 'Sin post';
                    $edit_url = admin_url( 'admin.php?page=vkd-edit&id=' . $uid );
                    $del_url  = wp_nonce_url(
                        admin_url( 'admin.php?page=vkd-directory&vkd_action=delete&id=' . $uid ),
                        'vkd_delete_' . $uid
                    );
                    $view_url = function_exists('vkd_profile_url') ? vkd_profile_url( $uid ) : '';
                    $img_url  = $r->featured_image_id
                                ? wp_get_attachment_image_url( (int) $r->featured_image_id, 'thumbnail' )
                                : '';
                    $initials = mb_strtoupper( mb_substr( $r->name ?: $r->display_name ?: '?', 0, 1 ) );
                ?>
                <tr>
                    <td><?php if ( $img_url ) : ?>
                        <img src="<?php echo esc_url( $img_url ); ?>" class="vkd-avatar" alt="">
                    <?php else : ?>
                        <div class="vkd-avatar-ph"><?php echo esc_html( $initials ); ?></div>
                    <?php endif; ?></td>
                    <td>
                        <strong><?php echo esc_html( $r->name ?: $r->display_name ?: '(sin nombre)' ); ?></strong>
                        <?php if ( $r->tagline ) : ?><br><small style="color:#888"><?php echo esc_html( $r->tagline ); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $r->email ?: $r->user_email ); ?><br>
                        <small style="color:#aaa">ID: <?php echo esc_html( $uid ); ?></small></td>
                    <td><?php echo esc_html( $r->profession ); ?></td>
                    <td><?php echo esc_html( $r->city ); ?></td>
                    <td><span class="vkd-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $lbl ); ?></span></td>
                    <td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $r->updated_at ) ) ); ?></td>
                    <td style="display:flex;gap:.3rem;flex-wrap:wrap">
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">✏ Editar</a>
                        <?php if ( $view_url ) : ?>
                        <a href="<?php echo esc_url( $view_url ); ?>" target="_blank" class="button button-small">👁 Ver</a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url( $del_url ); ?>" class="button button-small"
                           style="color:#b32d2e"
                           onclick="return confirm('¿Eliminar el perfil de <?php echo esc_js( $r->name ?: 'este usuario' ); ?>?')">🗑</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <div class="vkd-empty">
                <span class="dashicons dashicons-groups" style="font-size:2.5rem;width:auto;height:auto;display:block;margin:0 auto .75rem"></span>
                <p><?php echo $search ? 'No se encontraron perfiles con esa búsqueda.' : 'Aún no hay perfiles en el directorio.'; ?></p>
                <?php if ( ! $search ) : ?>
                <a href="<?php echo esc_url( $new_url ); ?>" class="button button-primary">Agregar el primer perfil</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ( $total_pages > 1 ) : ?>
        <div class="vkd-pagination">
            <?php for ( $i = 1; $i <= $total_pages; $i++ ) :
                $purl = add_query_arg( array( 'page' => 'vkd-directory', 'paged' => $i, 's' => $search ?: '' ), admin_url( 'admin.php' ) );
                if ( $i === $paged ) : ?>
                    <span class="current"><?php echo esc_html( $i ); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url( $purl ); ?>"><?php echo esc_html( $i ); ?></a>
                <?php endif;
            endfor; ?>
            <span style="color:#888;font-size:.82rem;margin-left:.5rem"><?php echo esc_html( $total ); ?> registros</span>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/* ══════════════════════════════════════════════════════════════════════════════
   PÁGINA 2: EDITAR / CREAR PERFIL
══════════════════════════════════════════════════════════════════════════════ */

function vkd_page_edit() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    nocache_headers();
    global $wpdb;

    $page_param = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
    // Aceptar tanto ?uid= (desde panel aprobación) como ?id= (redirect interno)
    $uid        = (int) ( $_GET['uid'] ?? $_GET['id'] ?? 0 );
    $is_new     = ( $uid === 0 || $page_param === 'vkd-new' );
    $row        = ( ! $is_new && $uid ) ? vkd_get_record( $uid ) : null;
    $msg        = isset( $_GET['vkd_msg'] ) ? sanitize_key( $_GET['vkd_msg'] ) : '';
    $table      = $wpdb->prefix . VKD_TABLE;

    // ── Procesar acción de aprobación desde esta misma página ───────────────
    $approval_notice = '';
    if ( isset( $_POST['vkd_approval_action'], $_POST['_wpnonce_approval'] ) &&
         wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_approval'] ) ), 'vkd_approval_edit_' . $uid ) ) {

        $action  = sanitize_text_field( $_POST['vkd_approval_action'] );
        $comment = sanitize_textarea_field( wp_unslash( $_POST['approval_comment'] ?? '' ) );

        if ( $row ) {
            if ( $action === 'approve' ) {
                $wpdb->update( $table, array(
                    'approval_status' => 'approved',
                    'approved_at'     => current_time( 'mysql' ),
                    'approved_by'     => get_current_user_id(),
                ), array( 'user_id' => $uid ) );
                if ( (int) $row->post_id > 0 ) {
                    $wpdb->update( $wpdb->posts, array(
                        'post_status'       => 'publish',
                        'post_modified'     => current_time( 'mysql' ),
                        'post_modified_gmt' => current_time( 'mysql', 1 ),
                    ), array( 'ID' => (int) $row->post_id ) );
                    clean_post_cache( (int) $row->post_id );
                }
                if ( $comment ) update_user_meta( $uid, '_vkd_admin_comment', $comment );
                if ( function_exists( 'vkd_notify_dir_approved' ) ) vkd_notify_dir_approved( $uid, $row );
                $approval_notice = '<div class="notice notice-success is-dismissible"><p>✅ <strong>Perfil aprobado y publicado.</strong> Se envió notificación al usuario.' . ( $comment ? ' Comentario guardado.' : '' ) . '</p></div>';
            } elseif ( $action === 'reject' ) {
                $wpdb->update( $table, array( 'approval_status' => 'rejected' ), array( 'user_id' => $uid ) );
                if ( (int) $row->post_id > 0 ) {
                    $wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => (int) $row->post_id ) );
                    clean_post_cache( (int) $row->post_id );
                }
                if ( $comment ) update_user_meta( $uid, '_vkd_admin_comment', $comment );
                $approval_notice = '<div class="notice notice-warning is-dismissible"><p>⛔ <strong>Perfil rechazado.</strong>' . ( $comment ? ' Comentario guardado.' : '' ) . '</p></div>';
            } elseif ( $action === 'request_changes' ) {
                $wpdb->update( $table, array( 'approval_status' => 'pending' ), array( 'user_id' => $uid ) );
                if ( (int) $row->post_id > 0 ) {
                    $wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => (int) $row->post_id ) );
                    clean_post_cache( (int) $row->post_id );
                }
                if ( $comment ) update_user_meta( $uid, '_vkd_admin_comment', $comment );
                $approval_notice = '<div class="notice notice-info is-dismissible"><p>🔄 <strong>Cambios solicitados.</strong>' . ( $comment ? ' Comentario guardado para el usuario.' : '' ) . '</p></div>';
            }
            // Releer el registro actualizado
            $row = vkd_get_record( $uid );
        }
    }

    if ( ! $is_new && ! $row ) {
        echo '<div class="wrap"><div class="notice notice-error"><p>Perfil no encontrado (uid: '
            . esc_html( $uid ) . '). Verifica que el parámetro <code>uid</code> sea correcto.</p></div></div>';
        return;
    }

    // ── Datos del registro ─────────────────────────────────────────────────────
    $get = function( $field, $default = '' ) use ( $row ) {
        if ( ! $row ) return $default;
        return property_exists( $row, $field ) ? $row->$field : $default;
    };

    $wp_users   = $is_new ? get_users( array( 'number' => 500, 'orderby' => 'display_name', 'order' => 'ASC' ) ) : array();
    $categories = array();
    $sel_cats   = $row ? array_filter( array_map( 'intval', explode( ',', (string) $get('category_ids') ) ) ) : array();
    foreach ( array( 'at_biz_dir-category', 'atbdp_listing_category' ) as $tax ) {
        if ( ! taxonomy_exists( $tax ) ) continue;
        $terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
        if ( ! is_wp_error( $terms ) ) { $categories = $terms; break; }
    }

    $post_id        = (int) $get('post_id', 0);
    $post_st        = $post_id ? get_post_status( $post_id ) : '';
    $permalink      = ( ! $is_new && $uid && function_exists('vkd_profile_url') ) ? vkd_profile_url( $uid ) : '';
    $approval       = (string) $get( 'approval_status', 'pending' );
    $approved_at    = (string) $get( 'approved_at', '' );
    $admin_comment  = (string) ( get_user_meta( $uid, '_vkd_admin_comment', true ) ?: '' );
    $feat_url       = $get('featured_image_id') ? wp_get_attachment_image_url( (int) $get('featured_image_id'), 'medium' ) : '';
    $logo_url       = $get('logo_id') ? wp_get_attachment_image_url( (int) $get('logo_id'), 'thumbnail' ) : '';
    $back_url       = admin_url( 'admin.php?page=vkd-approval' );
    $wp_user        = $uid ? get_userdata( $uid ) : null;
    $page_title     = $is_new ? 'Nuevo perfil' : ( $get('name') ?: 'Perfil uid ' . $uid );

    $badge = array(
        'pending'  => '<span style="background:#fff3cd;color:#856404;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700">🕐 Pendiente</span>',
        'approved' => '<span style="background:#d1e7dd;color:#0f5132;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700">✅ Aprobado</span>',
        'rejected' => '<span style="background:#f8d7da;color:#842029;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700">⛔ Rechazado</span>',
    );
    $status_badge = $badge[ $approval ] ?? $badge['pending'];

    ?>
    <style>
    .vkd-edit-layout{display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start;margin-top:1rem}
    @media(max-width:1100px){.vkd-edit-layout{grid-template-columns:1fr}}
    .vkd-sidebar-box{background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden;position:sticky;top:32px}
    .vkd-sidebar-box h3{margin:0;padding:.75rem 1rem;font-size:.9rem;font-weight:700;border-bottom:1px solid #eee;display:flex;align-items:center;gap:.4rem}
    .vkd-sidebar-body{padding:1rem}
    .vkd-approval-btn{display:block;width:100%;padding:.6rem 1rem;border:none;border-radius:6px;font-size:.88rem;font-weight:600;cursor:pointer;text-align:center;margin-bottom:.5rem;transition:opacity .15s}
    .vkd-approval-btn:hover{opacity:.85}
    .vkd-form-section{background:#fff;border:1px solid #ddd;border-radius:8px;padding:1.25rem 1.5rem;margin-bottom:1.25rem}
    .vkd-form-section h3{margin:0 0 1rem;font-size:.95rem;font-weight:700;color:#1d2327;padding-bottom:.6rem;border-bottom:1px solid #f0f0f0}
    .vkd-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
    @media(max-width:700px){.vkd-form-grid{grid-template-columns:1fr}}
    .vkd-field{display:flex;flex-direction:column;gap:.3rem}
    .vkd-field label{font-size:.82rem;font-weight:600;color:#3c434a}
    .vkd-field input,.vkd-field textarea,.vkd-field select{padding:.45rem .65rem;border:1px solid #8c8f94;border-radius:4px;font-size:.88rem;font-family:inherit;width:100%;box-sizing:border-box}
    .vkd-field textarea{min-height:90px;resize:vertical}
    .vkd-field small{font-size:.78rem;color:#646970}
    .vkd-img-preview img{max-width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #ddd;margin-bottom:.3rem}
    .vkd-preview-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:100000;align-items:center;justify-content:center}
    .vkd-preview-modal.open{display:flex}
    .vkd-preview-inner{background:#fff;border-radius:10px;width:90vw;max-width:900px;height:85vh;display:flex;flex-direction:column;overflow:hidden}
    .vkd-preview-header{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.25rem;border-bottom:1px solid #ddd;flex-shrink:0}
    .vkd-preview-header h3{margin:0;font-size:1rem}
    .vkd-preview-iframe{flex:1;border:none;width:100%}
    .vkd-meta-row{display:flex;justify-content:space-between;font-size:.8rem;padding:.35rem 0;border-bottom:1px solid #f5f5f5;color:#3c434a}
    .vkd-meta-row:last-child{border-bottom:none}
    .vkd-meta-row strong{color:#1d2327}
    </style>

    <div class="wrap vkd-wrap">
        <h1 style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
            <span class="dashicons dashicons-id-alt"></span>
            <?php echo esc_html( $page_title ); ?>
            <?php if ( ! $is_new ) echo $status_badge; ?>
        </h1>
        <a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">← Panel de aprobación</a>
        <a href="<?php echo esc_url( admin_url('admin.php?page=vkd-directory') ); ?>" class="page-title-action">📋 Todos los perfiles</a>
        <hr class="wp-header-end">

        <?php echo wp_kses_post( $approval_notice ); ?>

        <?php if ( $msg === 'saved' ) : ?>
        <div class="notice notice-success is-dismissible" style="margin:.75rem 0">
            <p>✅ <strong>Cambios guardados correctamente.</strong>
            <?php if ( $permalink && $approval === 'approved' ) : ?>
            &nbsp;<a href="<?php echo esc_url( $permalink ); ?>" target="_blank">Ver perfil público →</a>
            <?php endif; ?></p>
        </div>
        <?php endif; ?>

        <!-- Modal vista previa -->
        <?php if ( $permalink ) : ?>
        <div class="vkd-preview-modal" id="vkd-preview-modal">
            <div class="vkd-preview-inner">
                <div class="vkd-preview-header">
                    <h3>👁 Vista previa — <?php echo esc_html( $get('name') ); ?></h3>
                    <div style="display:flex;gap:.5rem">
                        <a href="<?php echo esc_url( $permalink ); ?>" target="_blank" class="button button-small">Abrir en nueva pestaña ↗</a>
                        <button class="button button-small" onclick="document.getElementById('vkd-preview-modal').classList.remove('open')">✕ Cerrar</button>
                    </div>
                </div>
                <iframe class="vkd-preview-iframe" id="vkd-preview-iframe" src="about:blank"></iframe>
            </div>
        </div>
        <script>
        function vkdOpenPreview(){
            var m=document.getElementById('vkd-preview-modal');
            var f=document.getElementById('vkd-preview-iframe');
            f.src='<?php echo esc_js( $permalink ); ?>';
            m.classList.add('open');
        }
        document.addEventListener('keydown',function(e){if(e.key==='Escape')document.getElementById('vkd-preview-modal').classList.remove('open');});
        </script>
        <?php endif; ?>

        <div class="vkd-edit-layout">

            <!-- ── COLUMNA PRINCIPAL: formulario ── -->
            <div>
            <form method="post" id="vkd-edit-form">
                <?php wp_nonce_field( 'vkd_admin_save', 'vkd_nonce' ); ?>
                <?php if ( $is_new ) : ?>
                <!-- usuario -->
                <?php else : ?>
                <input type="hidden" name="user_id" value="<?php echo esc_attr( $uid ); ?>">
                <?php endif; ?>

                <?php if ( $is_new ) : ?>
                <div class="vkd-form-section">
                    <h3>👤 Usuario de WordPress</h3>
                    <div class="vkd-field">
                        <label>Seleccionar usuario <strong style="color:#d63638">*</strong></label>
                        <select name="user_id_sel" style="max-width:520px">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ( $wp_users as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>">
                                <?php echo esc_html( $u->display_name . ' (' . $u->user_email . ') — ID ' . $u->ID ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small>O escribe el ID directamente: <input type="number" name="user_id_raw" min="1" placeholder="ej: 42" style="width:80px;padding:.3rem .5rem;border:1px solid #ddd;border-radius:4px"></small>
                    </div>
                </div>
                <?php endif; ?>

                <!-- IMÁGENES -->
                <div class="vkd-form-section">
                    <h3>🖼 Imágenes del perfil</h3>
                    <div class="vkd-form-grid">
                        <div class="vkd-field">
                            <label>Foto de perfil (ID adjunto WordPress)</label>
                            <?php if ( $feat_url ) : ?><div class="vkd-img-preview"><img src="<?php echo esc_url($feat_url); ?>" alt="Foto"></div><?php endif; ?>
                            <input type="number" name="featured_image_id" value="<?php echo esc_attr( $get('featured_image_id', 0) ); ?>" min="0" placeholder="ID del adjunto">
                            <small><a href="<?php echo esc_url(admin_url('upload.php')); ?>" target="_blank">Buscar en Medios →</a></small>
                        </div>
                        <div class="vkd-field">
                            <label>Logo (ID adjunto WordPress)</label>
                            <?php if ( $logo_url ) : ?><div class="vkd-img-preview"><img src="<?php echo esc_url($logo_url); ?>" alt="Logo"></div><?php endif; ?>
                            <input type="number" name="logo_id" value="<?php echo esc_attr( $get('logo_id', 0) ); ?>" min="0" placeholder="ID del adjunto">
                        </div>
                    </div>
                </div>

                <!-- INFORMACIÓN BÁSICA -->
                <div class="vkd-form-section">
                    <h3>📋 Información básica</h3>
                    <div class="vkd-form-grid">
                        <div class="vkd-field">
                            <label>Nombre completo / Nombre comercial *</label>
                            <input type="text" name="name" value="<?php echo esc_attr( $get('name') ); ?>" required>
                        </div>
                        <div class="vkd-field">
                            <label>Tagline (descripción corta)</label>
                            <input type="text" name="tagline" value="<?php echo esc_attr( $get('tagline') ); ?>">
                        </div>
                    </div>
                    <div class="vkd-field" style="margin-top:.75rem">
                        <label>Biografía / Sobre el profesional</label>
                        <textarea name="bio"><?php echo esc_textarea( $get('bio') ); ?></textarea>
                    </div>
                </div>

                <!-- INFORMACIÓN PROFESIONAL -->
                <div class="vkd-form-section">
                    <h3>💼 Información profesional</h3>
                    <div class="vkd-form-grid">
                        <div class="vkd-field"><label>Profesión / Título</label>
                            <input type="text" name="profession" value="<?php echo esc_attr( $get('profession') ); ?>"></div>
                        <div class="vkd-field"><label>Especialidad</label>
                            <input type="text" name="specialty" value="<?php echo esc_attr( $get('specialty') ); ?>"></div>
                        <div class="vkd-field"><label>Años de experiencia</label>
                            <input type="text" name="experience" value="<?php echo esc_attr( $get('experience') ); ?>"></div>
                        <div class="vkd-field"><label>Rango de precios</label>
                            <input type="text" name="price_range" value="<?php echo esc_attr( $get('price_range') ); ?>"></div>
                    </div>
                    <div class="vkd-field" style="margin-top:.75rem">
                        <label>Servicios ofrecidos</label>
                        <textarea name="services"><?php echo esc_textarea( $get('services') ); ?></textarea>
                    </div>
                    <?php if ( $categories ) : ?>
                    <div class="vkd-field" style="margin-top:.75rem">
                        <label>Categorías</label>
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.3rem">
                            <?php foreach ( $categories as $cat ) : ?>
                            <label style="display:inline-flex;align-items:center;gap:.3rem;font-weight:normal;font-size:.85rem;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;padding:.25rem .6rem;cursor:pointer">
                                <input type="checkbox" name="category_ids[]" value="<?php echo esc_attr( $cat->term_id ); ?>"
                                       <?php checked( in_array( (int)$cat->term_id, $sel_cats, true ) ); ?>>
                                <?php echo esc_html( $cat->name ); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- CONTACTO -->
                <div class="vkd-form-section">
                    <h3>📞 Contacto</h3>
                    <div class="vkd-form-grid">
                        <div class="vkd-field"><label>Email</label>
                            <input type="email" name="email" value="<?php echo esc_attr( $get('email') ); ?>"></div>
                        <div class="vkd-field"><label>Teléfono</label>
                            <input type="tel" name="phone" value="<?php echo esc_attr( $get('phone') ); ?>"></div>
                        <div class="vkd-field"><label>WhatsApp <small>(con código de país)</small></label>
                            <input type="tel" name="whatsapp" value="<?php echo esc_attr( $get('whatsapp') ); ?>" placeholder="+52 55 1234 5678"></div>
                        <div class="vkd-field"><label>Sitio web</label>
                            <input type="url" name="website" value="<?php echo esc_attr( $get('website') ); ?>"></div>
                    </div>
                </div>

                <!-- UBICACIÓN -->
                <div class="vkd-form-section">
                    <h3>📍 Ubicación</h3>
                    <div class="vkd-form-grid">
                        <div class="vkd-field" style="grid-column:1/-1"><label>Dirección</label>
                            <input type="text" name="address" value="<?php echo esc_attr( $get('address') ); ?>"></div>
                        <div class="vkd-field"><label>Ciudad</label>
                            <input type="text" name="city" value="<?php echo esc_attr( $get('city') ); ?>"></div>
                        <div class="vkd-field"><label>Estado / Provincia</label>
                            <input type="text" name="state" value="<?php echo esc_attr( $get('state') ); ?>"></div>
                        <div class="vkd-field"><label>País</label>
                            <input type="text" name="country" value="<?php echo esc_attr( $get('country') ); ?>"></div>
                        <div class="vkd-field"><label>Latitud</label>
                            <input type="text" name="lat" value="<?php echo esc_attr( $get('lat') ); ?>" placeholder="19.4326"></div>
                        <div class="vkd-field"><label>Longitud</label>
                            <input type="text" name="lng" value="<?php echo esc_attr( $get('lng') ); ?>" placeholder="-99.1332"></div>
                    </div>
                </div>

                <!-- REDES SOCIALES -->
                <div class="vkd-form-section">
                    <h3>📱 Redes sociales</h3>
                    <div class="vkd-form-grid">
                        <div class="vkd-field"><label>Facebook</label>
                            <input type="url" name="facebook" value="<?php echo esc_attr( $get('facebook') ); ?>"></div>
                        <div class="vkd-field"><label>Instagram</label>
                            <input type="url" name="instagram" value="<?php echo esc_attr( $get('instagram') ); ?>"></div>
                        <div class="vkd-field"><label>TikTok</label>
                            <input type="url" name="tiktok" value="<?php echo esc_attr( $get('tiktok') ); ?>"></div>
                        <div class="vkd-field"><label>LinkedIn</label>
                            <input type="url" name="linkedin" value="<?php echo esc_attr( $get('linkedin') ); ?>"></div>
                        <div class="vkd-field"><label>YouTube</label>
                            <input type="url" name="youtube" value="<?php echo esc_attr( $get('youtube') ); ?>"></div>
                        <div class="vkd-field"><label>X (Twitter)</label>
                            <input type="url" name="twitter" value="<?php echo esc_attr( $get('twitter') ); ?>"></div>
                    </div>
                </div>

                <!-- BOTONES GUARDAR -->
                <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;padding:.25rem 0">
                    <?php submit_button(
                        $is_new ? '➕ Crear perfil' : '💾 Guardar cambios',
                        'primary large', 'submit', false
                    ); ?>
                    <a href="<?php echo esc_url( $back_url ); ?>" class="button button-large">Cancelar</a>
                    <?php if ( ! $is_new ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url(
                        admin_url( 'admin.php?page=vkd-directory&vkd_action=delete&id=' . $uid ),
                        'vkd_delete_' . $uid
                    ) ); ?>" class="button button-large" style="color:#b32d2e;margin-left:auto"
                       onclick="return confirm('¿Eliminar definitivamente este perfil?')">🗑 Eliminar perfil</a>
                    <?php endif; ?>
                </div>
            </form>
            </div>

            <!-- ── SIDEBAR DERECHO ── -->
            <?php if ( ! $is_new ) : ?>
            <div>

                <!-- Info del usuario -->
                <div class="vkd-sidebar-box" style="margin-bottom:1rem">
                    <h3>👤 Usuario</h3>
                    <div class="vkd-sidebar-body">
                        <?php if ( $feat_url ) : ?>
                        <img src="<?php echo esc_url($feat_url); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:50%;border:2px solid #ddd;display:block;margin:0 auto .75rem">
                        <?php endif; ?>
                        <div class="vkd-meta-row"><strong>Nombre</strong><?php echo esc_html( $get('name') ?: '—' ); ?></div>
                        <div class="vkd-meta-row"><strong>WP User</strong><?php echo $wp_user ? esc_html( $wp_user->user_login ) : '—'; ?></div>
                        <div class="vkd-meta-row"><strong>Email</strong><?php echo $wp_user ? esc_html( $wp_user->user_email ) : esc_html( $get('email') ?: '—' ); ?></div>
                        <div class="vkd-meta-row"><strong>uid</strong><?php echo esc_html( $uid ); ?></div>
                        <div class="vkd-meta-row"><strong>Enviado</strong><?php echo esc_html( substr( (string)$get('created_at'), 0, 10 ) ?: '—' ); ?></div>
                        <div class="vkd-meta-row"><strong>WP Post</strong>
                            <?php if ( $post_id ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ); ?>" target="_blank">#<?php echo $post_id; ?> (<?php echo esc_html( $post_st ); ?>)</a>
                            <?php else : echo '—'; endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Estado de aprobación -->
                <div class="vkd-sidebar-box" style="margin-bottom:1rem">
                    <h3>📋 Estado de aprobación</h3>
                    <div class="vkd-sidebar-body">
                        <div style="text-align:center;margin-bottom:1rem">
                            <?php echo $status_badge; ?>
                            <?php if ( $approved_at && $approval === 'approved' ) : ?>
                            <div style="font-size:.75rem;color:#646970;margin-top:.35rem">Aprobado el <?php echo esc_html( substr($approved_at,0,10) ); ?></div>
                            <?php endif; ?>
                        </div>

                        <?php if ( $permalink ) : ?>
                        <button type="button" onclick="vkdOpenPreview()"
                            class="button button-secondary" style="width:100%;margin-bottom:.75rem">
                            👁 Vista previa del perfil
                        </button>
                        <?php endif; ?>

                        <!-- Formulario de aprobación -->
                        <form method="post">
                            <?php wp_nonce_field( 'vkd_approval_edit_' . $uid, '_wpnonce_approval' ); ?>
                            <div class="vkd-field" style="margin-bottom:.6rem">
                                <label style="font-size:.8rem">Comentario para el usuario <small>(opcional)</small></label>
                                <textarea name="approval_comment" rows="3" placeholder="Ej: Falta completar el campo de teléfono..."
                                    style="font-size:.82rem"><?php echo esc_textarea( $admin_comment ); ?></textarea>
                            </div>

                            <?php if ( $approval !== 'approved' ) : ?>
                            <button type="submit" name="vkd_approval_action" value="approve"
                                class="vkd-approval-btn" style="background:#0f5132;color:#fff"
                                onclick="return confirm('¿Aprobar y publicar este perfil en el directorio?')">
                                ✅ Aprobar y publicar
                            </button>
                            <?php endif; ?>

                            <?php if ( $approval !== 'rejected' ) : ?>
                            <button type="submit" name="vkd_approval_action" value="reject"
                                class="vkd-approval-btn" style="background:#842029;color:#fff"
                                onclick="return confirm('¿Rechazar este perfil?')">
                                ⛔ Rechazar
                            </button>
                            <?php endif; ?>

                            <button type="submit" name="vkd_approval_action" value="request_changes"
                                class="vkd-approval-btn" style="background:#664d03;color:#fff">
                                🔄 Solicitar cambios
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Accesos rápidos -->
                <div class="vkd-sidebar-box">
                    <h3>🔗 Accesos rápidos</h3>
                    <div class="vkd-sidebar-body" style="display:flex;flex-direction:column;gap:.4rem">
                        <?php if ( $permalink && $approval === 'approved' ) : ?>
                        <a href="<?php echo esc_url( $permalink ); ?>" target="_blank" class="button button-secondary" style="text-align:center">🌐 Ver perfil público</a>
                        <?php endif; ?>
                        <?php if ( $post_id ) : ?>
                        <a href="<?php echo esc_url( admin_url('post.php?post='.$post_id.'&action=edit') ); ?>" target="_blank" class="button" style="text-align:center">📝 Editar en WP</a>
                        <?php endif; ?>
                        <?php if ( $wp_user ) : ?>
                        <a href="<?php echo esc_url( admin_url('user-edit.php?user_id='.$uid) ); ?>" target="_blank" class="button" style="text-align:center">👤 Ver usuario en WP</a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url( $back_url ); ?>" class="button" style="text-align:center">← Panel de aprobación</a>
                    </div>
                </div>

            </div>
            <?php endif; ?>

        </div><!-- .vkd-edit-layout -->
    </div><!-- .wrap -->
    <?php
}

/* ══════════════════════════════════════════════════════════════════════════════
   PÁGINA 3: SHORTCODES + GENERADOR
══════════════════════════════════════════════════════════════════════════════ */

function vkd_page_shortcodes() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $categories = array();
    foreach ( array( 'at_biz_dir-category', 'atbdp_listing_category' ) as $tax ) {
        if ( ! taxonomy_exists( $tax ) ) continue;
        $terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
        if ( ! is_wp_error( $terms ) ) { $categories = $terms; break; }
    }
    ?>
    <div class="wrap vkd-wrap">
        <h1><span class="dashicons dashicons-shortcode"></span> Shortcodes del Directorio</h1>
        <hr class="wp-header-end">

        <div class="vkd-generator">
            <h3 style="margin:0 0 .75rem">⚙ Generador de shortcodes</h3>
            <div class="vkd-gen-grid">
                <div class="vkd-field">
                    <label>Tipo</label>
                    <select id="gen-type" onchange="vkdGenerate()">
                        <option value="directorio">Todos los profesionales</option>
                        <option value="directorio_categoria">Por categoría</option>
                        <option value="directorio_usuario">Perfil individual</option>
                        <option value="directorio_destacados">Destacados</option>
                    </select>
                </div>
                <div class="vkd-field" id="gen-cat-wrap" style="display:none">
                    <label>Categoría (ID)</label>
                    <select id="gen-cat" onchange="vkdGenerate()">
                        <option value="">— Elegir —</option>
                        <?php foreach ( $categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name . ' (#' . $cat->term_id . ')' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="vkd-field" id="gen-user-wrap" style="display:none">
                    <label>ID de usuario WP</label>
                    <input type="number" id="gen-user" min="1" placeholder="42" oninput="vkdGenerate()">
                </div>
                <div class="vkd-field" id="gen-limit-wrap">
                    <label>Cantidad</label>
                    <select id="gen-limit" onchange="vkdGenerate()">
                        <option value="6">6</option>
                        <option value="9">9</option>
                        <option value="12" selected>12</option>
                        <option value="24">24</option>
                        <option value="-1">Todos</option>
                    </select>
                </div>
                <div class="vkd-field" id="gen-cols-wrap">
                    <label>Columnas</label>
                    <select id="gen-cols" onchange="vkdGenerate()">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3" selected>3</option>
                        <option value="4">4</option>
                    </select>
                </div>
                <div class="vkd-field" id="gen-layout-wrap">
                    <label>Diseño</label>
                    <select id="gen-layout" onchange="vkdGenerate()">
                        <option value="grid" selected>Cuadrícula</option>
                        <option value="list">Lista</option>
                    </select>
                </div>
                <div class="vkd-field" id="gen-order-wrap">
                    <label>Ordenar por</label>
                    <select id="gen-order" onchange="vkdGenerate()">
                        <option value="recent" selected>Más recientes</option>
                        <option value="name">Nombre A-Z</option>
                        <option value="profession">Profesión</option>
                    </select>
                </div>
                <div class="vkd-field" id="gen-style-wrap" style="display:none">
                    <label>Estilo de tarjeta</label>
                    <select id="gen-style" onchange="vkdGenerate()">
                        <option value="card">Tarjeta</option>
                        <option value="full">Perfil completo</option>
                    </select>
                </div>
            </div>
            <label style="font-size:.82rem;font-weight:600;color:#555;display:block;margin-bottom:.4rem">Shortcode generado:</label>
            <div class="vkd-output">
                <code id="gen-output">[directorio limit="12" cols="3" layout="grid" order="recent"]</code>
                <button class="vkd-copy-btn" onclick="vkdCopy('gen-output')">Copiar</button>
            </div>
        </div>

        <h2 style="font-size:1.1rem;margin-bottom:.75rem">📋 Shortcodes disponibles</h2>

        <?php
        $scs = array(
            array(
                'tag'  => '[directorio]',
                'desc' => 'Lista todos los profesionales del directorio.',
                'params' => array(
                    array('limit',    '"12"',   'Cantidad a mostrar. -1 = todos.'),
                    array('cols',     '"3"',    'Columnas (1-4).'),
                    array('layout',   '"grid"', 'grid o list.'),
                    array('order',    '"recent"','recent, name, profession.'),
                    array('category', '""',     'Filtrar por ID de categoría.'),
                ),
                'ex' => array('[directorio]','[directorio limit="6" cols="2"]','[directorio order="name" cols="4"]'),
            ),
            array(
                'tag'  => '[directorio_categoria id=""]',
                'desc' => 'Lista los profesionales de una categoría específica.',
                'params' => array(
                    array('id',     '',     'ID de la categoría (obligatorio).'),
                    array('limit',  '"12"', 'Cantidad.'),
                    array('cols',   '"3"',  'Columnas.'),
                    array('layout', '"grid"','Diseño.'),
                ),
                'ex' => array('[directorio_categoria id="3"]','[directorio_categoria id="3" cols="2" limit="6"]'),
            ),
            array(
                'tag'  => '[directorio_usuario id=""]',
                'desc' => 'Muestra la tarjeta o el perfil completo de un profesional.',
                'params' => array(
                    array('id',    '',      'ID del usuario WP (obligatorio).'),
                    array('style', '"card"','card = tarjeta, full = perfil expandido.'),
                ),
                'ex' => array('[directorio_usuario id="42"]','[directorio_usuario id="42" style="full"]'),
            ),
            array(
                'tag'  => '[directorio_destacados]',
                'desc' => 'Muestra los profesionales marcados como destacados (o los más recientes).',
                'params' => array(
                    array('limit',  '"6"',   'Cantidad.'),
                    array('cols',   '"3"',   'Columnas.'),
                    array('layout', '"grid"','Diseño.'),
                ),
                'ex' => array('[directorio_destacados]','[directorio_destacados limit="4" cols="4"]'),
            ),
        );
        foreach ( $scs as $sc ) : ?>
        <div class="vkd-docs-section" style="margin-bottom:1rem">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
                <h3 style="margin:0;font-family:monospace"><?php echo esc_html( $sc['tag'] ); ?></h3>
                <button class="button button-small vkd-copy-direct" data-text="<?php echo esc_attr( $sc['ex'][0] ); ?>">Copiar ejemplo básico</button>
            </div>
            <p style="margin:.5rem 0 .75rem;color:#555"><?php echo esc_html( $sc['desc'] ); ?></p>
            <table class="vkd-param-table">
                <thead><tr><th>Parámetro</th><th>Default</th><th>Descripción</th></tr></thead>
                <tbody>
                <?php foreach ( $sc['params'] as $p ) : ?>
                <tr><td><?php echo esc_html($p[0]); ?></td><td><?php echo esc_html($p[1]); ?></td><td><?php echo esc_html($p[2]); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top:.75rem">
                <?php foreach ( $sc['ex'] as $ex ) : ?>
                <div class="vkd-sc-box">
                    <?php echo esc_html( $ex ); ?>
                    <button class="button button-small vkd-sc-copy" onclick="vkdCopyText('<?php echo esc_js($ex); ?>')">Copiar</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="vkd-docs-section" style="background:#f0f7ff;border-color:#c3d9f0">
            <h3>🧱 Dónde insertar el shortcode</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
                <div><strong>Gutenberg</strong><ol style="font-size:.88rem;margin:.5rem 0 0;padding-left:1.25rem"><li>Bloque → <em>Shortcode</em></li><li>Pega el shortcode</li><li>Publica la página</li></ol></div>
                <div><strong>Elementor</strong><ol style="font-size:.88rem;margin:.5rem 0 0;padding-left:1.25rem"><li>Widget → <em>Shortcode</em></li><li>Pega el shortcode</li><li>Guarda</li></ol></div>
                <div><strong>PHP / Templates</strong><ol style="font-size:.88rem;margin:.5rem 0 0;padding-left:1.25rem"><li><code>do_shortcode('[directorio]')</code></li><li>Compatible con cualquier tema</li></ol></div>
            </div>
        </div>
    </div>

    <script>
    function vkdGenerate(){
        var t=document.getElementById('gen-type').value;
        var lim=document.getElementById('gen-limit').value;
        var cols=document.getElementById('gen-cols').value;
        var lay=document.getElementById('gen-layout').value;
        var ord=document.getElementById('gen-order').value;
        var cat=document.getElementById('gen-cat').value;
        var uid=document.getElementById('gen-user').value;
        var sty=document.getElementById('gen-style').value;
        var isCat=(t==='directorio_categoria');
        var isUsr=(t==='directorio_usuario');
        document.getElementById('gen-cat-wrap').style.display=isCat?'':'none';
        document.getElementById('gen-user-wrap').style.display=isUsr?'':'none';
        document.getElementById('gen-limit-wrap').style.display=isUsr?'none':'';
        document.getElementById('gen-cols-wrap').style.display=isUsr?'none':'';
        document.getElementById('gen-layout-wrap').style.display=isUsr?'none':'';
        document.getElementById('gen-order-wrap').style.display=isUsr?'none':'';
        document.getElementById('gen-style-wrap').style.display=isUsr?'':'none';
        var sc='['+t;
        if(isCat&&cat) sc+=' id="'+cat+'"';
        if(isUsr){if(uid) sc+=' id="'+uid+'"'; sc+=' style="'+sty+'"';}
        else{sc+=' limit="'+lim+'" cols="'+cols+'" layout="'+lay+'" order="'+ord+'"';
             if(t==='directorio'&&cat) sc+=' category="'+cat+'"';}
        sc+=']';
        document.getElementById('gen-output').textContent=sc;
    }
    function vkdCopy(id){vkdCopyText(document.getElementById(id).textContent);}
    function vkdCopyText(txt){
        navigator.clipboard.writeText(txt).then(function(){
            var s=document.createElement('span');
            s.textContent=' ✅ Copiado';s.style.cssText='color:green;font-size:.8rem;margin-left:.5rem';
            document.body.appendChild(s);setTimeout(function(){document.body.removeChild(s);},1800);
        }).catch(function(){var ta=document.createElement('textarea');ta.value=txt;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);});
    }
    document.querySelectorAll('.vkd-copy-direct').forEach(function(b){b.addEventListener('click',function(){vkdCopyText(this.dataset.text);});});
    vkdGenerate();
    </script>
    <?php
}

/* ══════════════════════════════════════════════════════════════════════════════
   PÁGINA 4: DOCUMENTACIÓN
══════════════════════════════════════════════════════════════════════════════ */

function vkd_page_docs() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="wrap vkd-wrap">
        <h1><span class="dashicons dashicons-book-alt"></span> Documentación del Directorio</h1>
        <hr class="wp-header-end">

        <div class="vkd-docs-section">
            <h3>¿Cómo funciona el directorio?</h3>
            <p>Los perfiles se guardan en la tabla <code>wp_vk_professionals</code> (fuente de verdad) y se sincronizan automáticamente con el CPT <strong>at_biz_dir</strong> de Directorist. Los profesionales llenan su perfil desde la app y los administradores pueden gestionarlo desde este panel.</p>
            <ol style="font-size:.9rem;line-height:2">
                <li>Profesional guarda perfil desde la app → datos en <code>wp_vk_professionals</code></li>
                <li>Plugin sincroniza con un post <code>at_biz_dir</code> + metadatos Directorist</li>
                <li>Post se publica con <code>post_status = publish</code></li>
                <li>Los shortcodes leen de <code>wp_vk_professionals</code> (siempre consistentes)</li>
            </ol>
        </div>

        <div class="vkd-docs-section">
            <h3>Endpoints REST API (app móvil)</h3>
            <p style="color:#666">Base: <code><?php echo esc_html( rest_url('vk/v1/dir/') ); ?></code> · Auth: <code>vk_token</code> en query string o header <code>X-VK-Token</code></p>
            <table class="vkd-param-table" style="width:100%">
                <thead><tr><th>Método</th><th>Ruta</th><th>Descripción</th></tr></thead>
                <tbody>
                    <tr><td>GET</td><td>/vk/v1/dir/status</td><td>¿El usuario ya tiene perfil?</td></tr>
                    <tr><td>GET</td><td>/vk/v1/dir/profile</td><td>Carga el perfil completo</td></tr>
                    <tr><td>POST</td><td>/vk/v1/dir/profile</td><td>Crea o actualiza el perfil (JSON body)</td></tr>
                    <tr><td>POST</td><td>/vk/v1/dir/upload-image</td><td>Sube imagen (multipart, campo <code>file</code>, param <code>type=featured|logo</code>)</td></tr>
                    <tr><td>GET</td><td>/vk/v1/dir/categories</td><td>Categorías disponibles (público)</td></tr>
                    <tr><td>GET</td><td>/vk/v1/dir/debug</td><td>Diagnóstico completo del perfil</td></tr>
                </tbody>
            </table>
        </div>

        <div class="vkd-docs-section" style="background:#fff8e1;border-color:#ffe082">
            <h3>⚠ Diagnóstico rápido</h3>
            <p>Para ver el debug de un usuario desde el navegador (debes estar logueado como admin):</p>
            <code style="display:block;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;padding:.5rem .85rem">
                <?php echo esc_html( rest_url('vk/v1/dir/debug') ); ?>?vk_token=TOKEN_DEL_USUARIO
            </code>
            <p style="margin-top:.75rem;color:#666">O accede directamente como administrador de WordPress sin token:</p>
            <a href="<?php echo esc_url( rest_url('vk/v1/dir/debug') ); ?>" target="_blank" class="button">
                Abrir diagnóstico como admin →
            </a>
        </div>
    </div>
    <?php
}

/* ══════════════════════════════════════════════════════════════════════════════
   SHORTCODES FRONTEND
══════════════════════════════════════════════════════════════════════════════ */

add_shortcode( 'directorio',            'vkd_sc_all' );
add_shortcode( 'directorio_categoria',  'vkd_sc_category' );
add_shortcode( 'directorio_usuario',    'vkd_sc_user' );
add_shortcode( 'directorio_destacados', 'vkd_sc_featured' );

function vkd_sc_css() {
    static $done = false;
    if ( $done ) return '';
    $done = true;
    return '<style>
.vkd-grid{display:grid;gap:1.25rem;margin:1.5rem 0}
.vkd-grid.cols-1{grid-template-columns:1fr}
.vkd-grid.cols-2{grid-template-columns:repeat(2,1fr)}
.vkd-grid.cols-3{grid-template-columns:repeat(3,1fr)}
.vkd-grid.cols-4{grid-template-columns:repeat(4,1fr)}
@media(max-width:768px){.vkd-grid{grid-template-columns:repeat(2,1fr)!important}}
@media(max-width:480px){.vkd-grid{grid-template-columns:1fr!important}}
.vkd-pc{background:#fff;border:1px solid #e8e8e8;border-radius:12px;overflow:hidden;transition:box-shadow .2s}
.vkd-pc:hover{box-shadow:0 4px 20px rgba(0,0,0,.1)}
.vkd-pc-img{width:100%;height:180px;object-fit:cover;display:block;background:#f0f0f0}
.vkd-pc-img-ph{width:100%;height:180px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:2.5rem}
.vkd-pc-body{padding:1rem}
.vkd-pc-name{margin:0 0 .3rem;font-size:1rem;font-weight:700}
.vkd-pc-tag{margin:0 0 .6rem;font-size:.85rem;color:#666}
.vkd-pc-meta{display:flex;gap:.5rem;flex-wrap:wrap;font-size:.78rem;color:#888;margin-bottom:.85rem}
.vkd-pc-btn{display:inline-block;background:#2271b1;color:#fff!important;text-decoration:none;padding:.45rem 1rem;border-radius:6px;font-size:.85rem;font-weight:600}
.vkd-pc-btn:hover{background:#135e96}
.vkd-pl{display:flex;flex-direction:column;gap:.75rem}
.vkd-pl-item{display:flex;gap:1rem;background:#fff;border:1px solid #e8e8e8;border-radius:10px;padding:.85rem;align-items:center}
.vkd-pl-avatar{width:64px;height:64px;border-radius:50%;object-fit:cover;flex-shrink:0}
.vkd-pl-avatar-ph{width:64px;height:64px;border-radius:50%;background:#e0e7ef;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0}
.vkd-pl-body{flex:1}
.vkd-pl-name{font-size:1rem;font-weight:700;margin:0 0 .2rem}
.vkd-pl-tag{font-size:.85rem;color:#555;margin:0 0 .35rem}
.vkd-pl-meta{font-size:.78rem;color:#888;margin:0}
.vkd-full{max-width:720px;margin:0 auto;background:#fff;border:1px solid #e8e8e8;border-radius:14px;overflow:hidden}
.vkd-full-img{width:100%;max-height:280px;object-fit:cover;display:block}
.vkd-full-body{padding:1.5rem}
.vkd-full-name{font-size:1.4rem;font-weight:700;margin:0 0 .3rem}
.vkd-full-tag{font-size:1rem;color:#555;margin:0 0 1rem}
.vkd-full-bio{line-height:1.7;margin-bottom:1rem}
.vkd-full-row{display:flex;flex-wrap:wrap;gap:.5rem 1.5rem;font-size:.9rem;color:#555;margin-bottom:1rem}
.vkd-sc-empty{text-align:center;padding:2rem;color:#888;font-style:italic}
</style>';
}

function vkd_query( $limit, $order, $cat_id ) {
    global $wpdb;
    $t     = $wpdb->prefix . VKD_TABLE;
    $limit = (int) $limit;
    $ord   = in_array( $order, array('name','profession'), true ) ? $order : 'updated_at';
    $dir   = ( $ord === 'updated_at' ) ? 'DESC' : 'ASC';
    $cat   = (int) $cat_id;

    if ( $cat ) {
        $sql = $wpdb->prepare(
            "SELECT * FROM `{$t}` WHERE FIND_IN_SET(%s, category_ids) ORDER BY {$ord} {$dir}",
            (string) $cat
        );
        if ( $limit > 0 ) $sql .= $wpdb->prepare( ' LIMIT %d', $limit );
    } else {
        $sql = "SELECT * FROM `{$t}` ORDER BY {$ord} {$dir}";
        if ( $limit > 0 ) $sql .= $wpdb->prepare( ' LIMIT %d', $limit );
    }
    $rows = $wpdb->get_results( $sql );
    return $rows ? $rows : array();
}

function vkd_card( $row ) {
    $img  = $row->featured_image_id ? wp_get_attachment_image_url( (int)$row->featured_image_id, 'medium' ) : '';
    $link = function_exists( 'vkd_profile_url' ) ? vkd_profile_url( (int)$row->user_id ) : '';
    $name = esc_html( $row->name ?: '' );
    $h  = '<div class="vkd-pc">';
    $h .= $img
        ? '<img src="'.esc_url($img).'" class="vkd-pc-img" alt="'.$name.'" loading="lazy">'
        : '<div class="vkd-pc-img-ph">👤</div>';
    $h .= '<div class="vkd-pc-body">';
    $h .= '<p class="vkd-pc-name">'.$name.'</p>';
    if ( $row->tagline ) $h .= '<p class="vkd-pc-tag">'.esc_html($row->tagline).'</p>';
    $h .= '<div class="vkd-pc-meta">';
    if ( $row->profession ) $h .= '<span>💼 '.esc_html($row->profession).'</span>';
    if ( $row->city )       $h .= '<span>📍 '.esc_html($row->city).'</span>';
    $h .= '</div>';
    if ( $link ) $h .= '<a href="'.esc_url($link).'" class="vkd-pc-btn">Ver perfil →</a>';
    $h .= '</div></div>';
    return $h;
}

function vkd_list_item( $row ) {
    $img  = $row->featured_image_id ? wp_get_attachment_image_url( (int)$row->featured_image_id, 'thumbnail' ) : '';
    $link = function_exists( 'vkd_profile_url' ) ? vkd_profile_url( (int)$row->user_id ) : '';
    $name = esc_html( $row->name ?: '' );
    $h  = '<div class="vkd-pl-item">';
    $h .= $img
        ? '<img src="'.esc_url($img).'" class="vkd-pl-avatar" alt="'.$name.'" loading="lazy">'
        : '<div class="vkd-pl-avatar-ph">👤</div>';
    $h .= '<div class="vkd-pl-body">';
    $h .= '<p class="vkd-pl-name">'.$name.'</p>';
    if ( $row->tagline ) $h .= '<p class="vkd-pl-tag">'.esc_html($row->tagline).'</p>';
    $meta = array();
    if ( $row->profession ) $meta[] = '💼 '.esc_html($row->profession);
    if ( $row->city )       $meta[] = '📍 '.esc_html($row->city);
    if ( $meta ) $h .= '<p class="vkd-pl-meta">'.implode(' · ',$meta).'</p>';
    $h .= '</div>';
    if ( $link ) $h .= '<a href="'.esc_url($link).'" class="vkd-pc-btn" style="flex-shrink:0;align-self:center">Ver →</a>';
    $h .= '</div>';
    return $h;
}

function vkd_render( $rows, $cols, $layout ) {
    if ( empty( $rows ) ) return '<p class="vkd-sc-empty">No hay perfiles disponibles.</p>';
    $cols = max( 1, min( 4, (int)$cols ) );
    if ( $layout === 'list' ) {
        $h = '<div class="vkd-pl">';
        foreach ( $rows as $r ) $h .= vkd_list_item($r);
        return $h . '</div>';
    }
    $h = '<div class="vkd-grid cols-'.$cols.'">';
    foreach ( $rows as $r ) $h .= vkd_card($r);
    return $h . '</div>';
}

/* [directorio] — alias del shortcode completo vkd_directorio (búsqueda + mapa + paginación) */
function vkd_sc_all( $atts ) {
    return vkd_shortcode_directorio( $atts );
}

/* [directorio_categoria id="1"] */
function vkd_sc_category( $atts ) {
    $a = shortcode_atts( array(
        'id'=>'','limit'=>'12','cols'=>'3','layout'=>'grid','order'=>'recent',
    ), $atts, 'directorio_categoria' );
    return vkd_sc_css() . vkd_render( vkd_query($a['limit'],$a['order'],$a['id']), $a['cols'], $a['layout'] );
}

/* [directorio_usuario id="42"] */
function vkd_sc_user( $atts ) {
    $a = shortcode_atts( array( 'id'=>'','style'=>'card' ), $atts, 'directorio_usuario' );
    $uid = (int) $a['id'];
    if ( ! $uid ) return '<p class="vkd-sc-empty">Especifica el ID del usuario: id="42".</p>';
    $row = vkd_get_record( $uid );
    if ( ! $row ) return '<p class="vkd-sc-empty">Perfil no encontrado.</p>';
    $css = vkd_sc_css();

    if ( $a['style'] === 'full' ) {
        $img  = $row->featured_image_id ? wp_get_attachment_image_url( (int)$row->featured_image_id, 'large' ) : '';
        $link = function_exists( 'vkd_profile_url' ) ? vkd_profile_url( (int)$row->user_id ) : '';
        $h  = '<div class="vkd-full">';
        if ( $img ) $h .= '<img src="'.esc_url($img).'" class="vkd-full-img" alt="'.esc_attr($row->name).'">';
        $h .= '<div class="vkd-full-body">';
        $h .= '<p class="vkd-full-name">'.esc_html($row->name).'</p>';
        if ( $row->tagline ) $h .= '<p class="vkd-full-tag">'.esc_html($row->tagline).'</p>';
        if ( $row->bio )     $h .= '<div class="vkd-full-bio">'.nl2br(esc_html($row->bio)).'</div>';
        $meta = array();
        if ( $row->profession ) $meta[] = '💼 '.esc_html($row->profession);
        if ( $row->specialty )  $meta[] = '🎓 '.esc_html($row->specialty);
        if ( $row->city )       $meta[] = '📍 '.esc_html($row->city);
        if ( $row->email )      $meta[] = '✉ '.esc_html($row->email);
        if ( $row->phone )      $meta[] = '📞 '.esc_html($row->phone);
        if ( $meta ) { $h .= '<div class="vkd-full-row">'; foreach($meta as $m) $h.='<span>'.$m.'</span>'; $h.='</div>'; }
        if ( $row->services ) $h .= '<div style="margin-bottom:1rem"><strong>Servicios:</strong><br>'.nl2br(esc_html($row->services)).'</div>';
        if ( $row->whatsapp ) {
            $wa = 'https://wa.me/' . preg_replace('/[^0-9]/','',$row->whatsapp);
            $h .= '<a href="'.esc_url($wa).'" target="_blank" rel="noopener" '
                . 'style="display:inline-flex;align-items:center;gap:8px;background:#25D366;color:#fff;'
                . 'text-decoration:none;padding:9px 18px;border-radius:7px;font-weight:600;font-size:.9em;margin-right:.5rem">'
                . '📲 WhatsApp</a>';
        }
        if ( $link ) $h .= '<a href="'.esc_url($link).'" class="vkd-pc-btn">Ver perfil completo →</a>';
        $h .= '</div></div>';
        return $css . $h;
    }

    return $css . '<div class="vkd-grid cols-1">' . vkd_card($row) . '</div>';
}

/* [directorio_destacados] */
function vkd_sc_featured( $atts ) {
    $a = shortcode_atts( array('limit'=>'6','cols'=>'3','layout'=>'grid'), $atts, 'directorio_destacados' );
    global $wpdb;
    $t   = $wpdb->prefix . VKD_TABLE;
    $lim = max(1,(int)$a['limit']);

    // Destacados marcados en Directorist (si el post existe)
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.* FROM `{$t}` p
         INNER JOIN {$wpdb->postmeta} pm ON p.post_id = pm.post_id
         WHERE pm.meta_key='_listing_featured' AND pm.meta_value='1'
         ORDER BY p.updated_at DESC LIMIT %d", $lim
    ) );
    // Fallback: más recientes con imagen
    if ( empty($rows) ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$t}` WHERE featured_image_id > 0 ORDER BY updated_at DESC LIMIT %d", $lim
        ) );
    }
    // Fallback final: cualquier perfil
    if ( empty($rows) ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$t}` ORDER BY updated_at DESC LIMIT %d", $lim
        ) );
    }
    return vkd_sc_css() . vkd_render( $rows ?: array(), $a['cols'], $a['layout'] );
}


/* ══════════════════════════════════════════════════════════════════════════════
   PÁGINA: CONFIGURACIÓN DE PAGOS
══════════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_post_vkd_save_payments', 'vkd_save_payments' );
function vkd_save_payments() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado' );
    check_admin_referer( 'vkd_payments_nonce' );

    $fields = array(
        'vk_mp_access_token'   => sanitize_text_field( $_POST['vk_mp_access_token']   ?? '' ),
        'vk_mp_public_key'     => sanitize_text_field( $_POST['vk_mp_public_key']     ?? '' ),
        'vk_pp_client_id'      => sanitize_text_field( $_POST['vk_pp_client_id']      ?? '' ),
        'vk_pp_client_secret'  => sanitize_text_field( $_POST['vk_pp_client_secret']  ?? '' ),
        'vk_pp_sandbox'        => ! empty( $_POST['vk_pp_sandbox'] ) ? '1' : '0',
    );
    foreach ( $fields as $k => $v ) update_option( $k, $v );

    wp_safe_redirect( admin_url( 'admin.php?page=vkd-payments&saved=1' ) );
    exit;
}

function vkd_page_payments() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $mp_token  = get_option( 'vk_mp_access_token', '' );
    $mp_pubkey = get_option( 'vk_mp_public_key',   '' );
    $pp_cid    = get_option( 'vk_pp_client_id',     '' );
    $pp_sec    = get_option( 'vk_pp_client_secret', '' );
    $pp_sand   = get_option( 'vk_pp_sandbox',       '0' );

    $saved = ! empty( $_GET['saved'] );
    ?>
    <div class="wrap vkd-wrap">
    <h1 style="display:flex;align-items:center;gap:.5rem">⚙ Configuración de Métodos de Pago</h1>
    <p style="color:#666;margin-bottom:1.5rem">Estas credenciales permiten procesar pagos con Mercado Pago y PayPal directamente en la app, sin salir de <strong>app.vidakushala.com</strong>.</p>

    <?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible"><p>✅ Credenciales guardadas correctamente.</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'vkd_payments_nonce' ); ?>
    <input type="hidden" name="action" value="vkd_save_payments">

    <!-- MERCADO PAGO -->
    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:1.5rem;margin-bottom:1.5rem">
        <h2 style="margin:0 0 1rem;color:#009ee3;display:flex;align-items:center;gap:.5rem">
            <img src="https://http2.mlstatic.com/frontend-assets/mp-web-navigation/ui-navigation/6.0.0/mercadopago/logo__large.png" style="height:22px"> Mercado Pago
        </h2>
        <p style="color:#666;font-size:.85rem;margin:0 0 1rem">
            Obtén las credenciales en: <a href="https://www.mercadopago.com.mx/developers/panel/app" target="_blank">mercadopago.com/developers → Tu aplicación → Credenciales</a><br>
            <strong>Para pruebas</strong> usa las que empiezan con <code>TEST-</code> · <strong>Para producción</strong> usa las que empiezan con <code>APP_USR-</code>
        </p>
        <table class="form-table" style="margin:0">
            <tr>
                <th style="width:220px"><label for="vk_mp_access_token">Access Token <span style="color:red">*</span></label></th>
                <td>
                    <div style="display:flex;align-items:center;gap:.5rem;max-width:560px">
                        <input type="text" id="vk_mp_access_token" name="vk_mp_access_token"
                            value="<?php echo esc_attr( $mp_token ); ?>"
                            autocomplete="off" spellcheck="false"
                            class="regular-text" style="flex:1;font-family:monospace;font-size:.8rem"
                            placeholder="TEST-xxxx... o APP_USR-xxxx...">
                        <button type="button" onclick="vkdToggleToken()" id="vkd-tok-toggle"
                            style="padding:.3rem .6rem;font-size:.8rem;cursor:pointer">👁</button>
                    </div>
                    <p class="description">Se usa para crear preferencias y procesar pagos en el servidor.</p>
                </td>
            </tr>
            <tr>
                <th><label for="vk_mp_public_key">Public Key <span style="color:red">*</span></label></th>
                <td>
                    <input type="text" id="vk_mp_public_key" name="vk_mp_public_key"
                        value="<?php echo esc_attr( $mp_pubkey ); ?>"
                        autocomplete="off" spellcheck="false"
                        class="regular-text" style="width:100%;max-width:520px;font-family:monospace;font-size:.8rem"
                        placeholder="TEST-xxxx... o APP_USR-xxxx...">
                    <p class="description">Clave pública. Se usa en el formulario de tarjeta en la app (MercadoPago Bricks).</p>
                </td>
            </tr>
        </table>

        <!-- Estado y botón de verificación -->
        <div style="margin-top:1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            <?php if ( $mp_token && $mp_pubkey ) : ?>
            <span style="color:#155724;background:#d4edda;padding:.4rem .9rem;border-radius:6px;font-size:.85rem">✅ Credenciales guardadas</span>
            <?php else : ?>
            <span style="color:#856404;background:#fff3cd;padding:.4rem .9rem;border-radius:6px;font-size:.85rem">⚠ Sin credenciales — MP no funcionará</span>
            <?php endif; ?>
            <button type="button" id="vkd-mp-test-btn" onclick="vkdTestMp()"
                style="padding:.4rem 1rem;background:#009ee3;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:600">
                🔍 Verificar conexión con MP
            </button>
        </div>
        <div id="vkd-mp-test-result" style="margin-top:.75rem;display:none;padding:.75rem 1rem;border-radius:6px;font-size:.85rem;font-family:monospace;white-space:pre-wrap;word-break:break-all"></div>
    </div>

    <script>
    function vkdToggleToken(){
        var f = document.getElementById('vk_mp_access_token');
        f.type = (f.type === 'password') ? 'text' : 'password';
    }
    // Empezar enmascarado visualmente pero con type=text para evitar autocompletado
    (function(){
        var f = document.getElementById('vk_mp_access_token');
        if(f && f.value){ f.type = 'password'; }
    })();

    function vkdTestMp(){
        var btn = document.getElementById('vkd-mp-test-btn');
        var res = document.getElementById('vkd-mp-test-result');
        btn.disabled = true;
        btn.textContent = '⏳ Verificando...';
        res.style.display = 'block';
        res.style.background = '#f8f9fa';
        res.style.border = '1px solid #dee2e6';
        res.style.color = '#333';
        res.textContent = 'Conectando con la API de Mercado Pago...';

        fetch('<?php echo esc_js( rest_url('vk/v1/payment/mp-test') ); ?>', {
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce('wp_rest') ); ?>' }
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            var ok  = d.token_ok && d.preference_ok;
            var msg = '';
            msg += '🔑 Token guardado : ' + (d.token_saved  ? '✅ SÍ (' + d.token_prefix  + ')' : '❌ NO') + '\n';
            msg += '🔑 PubKey guardada: ' + (d.pubkey_saved ? '✅ SÍ (' + d.pubkey_prefix + ')' : '❌ NO') + '\n';
            msg += '🌐 Token válido   : ' + (d.token_ok     ? '✅ SÍ' : '❌ NO') + '\n';
            if(d.mp_user){
                msg += '👤 Cuenta MP      : ' + d.mp_user.email + ' (ID: ' + d.mp_user.id + ', País: ' + d.mp_user.country + ')\n';
            }
            msg += '📋 Preferencia    : ' + (d.preference_ok ? '✅ Creada correctamente → ' + (d.preference_id || '') : '❌ Falló') + '\n';
            msg += '💱 Moneda WC      : ' + (d.currency || '?') + '\n';
            if(d.error){ msg += '\n⚠ Error: ' + d.error; }

            res.textContent = msg;
            res.style.background = ok ? '#d4edda' : '#f8d7da';
            res.style.border     = '1px solid ' + (ok ? '#c3e6cb' : '#f5c6cb');
            res.style.color      = ok ? '#155724' : '#721c24';
        })
        .catch(function(e){
            res.textContent = '❌ Error de red: ' + e.message;
            res.style.background = '#f8d7da';
            res.style.border = '1px solid #f5c6cb';
            res.style.color = '#721c24';
        })
        .finally(function(){
            btn.disabled = false;
            btn.textContent = '🔍 Verificar conexión con MP';
        });
    }
    </script>

    <!-- PAYPAL -->
    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:1.5rem;margin-bottom:1.5rem">
        <h2 style="margin:0 0 1rem;color:#003087;display:flex;align-items:center;gap:.5rem">
            💳 PayPal (PPCP)
        </h2>
        <p style="color:#666;font-size:.85rem;margin:0 0 1rem">Obtén el Client ID en: <a href="https://developer.paypal.com/dashboard/applications/live" target="_blank">developer.paypal.com → Apps → Tu app → Live → Client ID</a></p>
        <table class="form-table" style="margin:0">
            <tr>
                <th style="width:220px"><label for="vk_pp_client_id">Client ID <span style="color:red">*</span></label></th>
                <td>
                    <input type="text" id="vk_pp_client_id" name="vk_pp_client_id"
                        value="<?php echo esc_attr( $pp_cid ); ?>"
                        class="regular-text" style="width:100%;max-width:520px;font-family:monospace"
                        placeholder="AXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    <p class="description">Necesario para renderizar los botones de PayPal en la app.</p>
                </td>
            </tr>
            <tr>
                <th><label for="vk_pp_client_secret">Secret Key <span style="color:red">*</span></label></th>
                <td>
                    <input type="password" id="vk_pp_client_secret" name="vk_pp_client_secret"
                        value="<?php echo esc_attr( $pp_sec ); ?>"
                        class="regular-text" style="width:100%;max-width:520px;font-family:monospace"
                        placeholder="ExxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxN">
                    <p class="description">Secret Key (no se comparte con el navegador). Se usa para confirmar pagos en el servidor.</p>
                </td>
            </tr>
            <tr>
                <th><label for="vk_pp_sandbox">Modo Sandbox</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="vk_pp_sandbox" name="vk_pp_sandbox" value="1" <?php checked( $pp_sand, '1' ); ?>>
                        Usar PayPal sandbox (solo para pruebas)
                    </label>
                </td>
            </tr>
        </table>
        <?php if ( $pp_cid && $pp_sec ) : ?>
        <p style="margin:1rem 0 0;color:#155724;background:#d4edda;padding:.5rem 1rem;border-radius:6px;display:inline-block">✅ PayPal configurado (Client ID + Secret)</p>
        <?php elseif ( $pp_cid ) : ?>
        <p style="margin:1rem 0 0;color:#856404;background:#fff3cd;padding:.5rem 1rem;border-radius:6px;display:inline-block">⚠ Falta Secret Key — los pagos no se podrán confirmar</p>
        <?php else : ?>
        <p style="margin:1rem 0 0;color:#856404;background:#fff3cd;padding:.5rem 1rem;border-radius:6px;display:inline-block">⚠ Pendiente — PayPal mostrará error en la app sin credenciales</p>
        <?php endif; ?>
    </div>

    <p class="submit">
        <button type="submit" class="button button-primary" style="font-size:1rem;padding:.5rem 2rem">💾 Guardar credenciales</button>
    </p>
    </form>
    </div>
    <?php
}

/* ══════════════════════════════════════════════════════════════════════════════
   PÁGINA: CONFIGURACIÓN GOOGLE MAPS
══════════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_post_vkd_save_gmaps', 'vkd_save_gmaps' );
function vkd_save_gmaps() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado' );
    check_admin_referer( 'vkd_gmaps_nonce' );
    update_option( 'vk_gmaps_api_key', sanitize_text_field( $_POST['vk_gmaps_api_key'] ?? '' ) );
    wp_safe_redirect( admin_url( 'admin.php?page=vkd-gmaps&saved=1' ) );
    exit;
}

function vkd_page_gmaps() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $api_key = get_option( 'vk_gmaps_api_key', '' );
    $saved   = ! empty( $_GET['saved'] );
    $masked  = $api_key ? substr( $api_key, 0, 8 ) . str_repeat( '•', max( 0, strlen( $api_key ) - 12 ) ) . substr( $api_key, -4 ) : '';
    ?>
    <div class="wrap vkd-wrap">
    <h1 style="display:flex;align-items:center;gap:.5rem">🗺 Configuración de Google Maps</h1>
    <p style="color:#666;margin-bottom:1.5rem">La API Key de Google Maps habilita el autocompletado de direcciones y el mapa interactivo en el formulario de perfil de la app.</p>

    <?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible"><p>✅ Configuración guardada correctamente.</p></div>
    <?php endif; ?>

    <!-- Guía de obtención -->
    <div style="background:#f0f7ff;border:1px solid #b3d4ff;border-radius:8px;padding:1.25rem 1.5rem;margin-bottom:1.5rem">
        <h3 style="margin:0 0 .75rem;color:#1565c0">📋 Cómo obtener tu API Key de Google Maps</h3>
        <ol style="margin:0;padding-left:1.25rem;line-height:1.9;color:#333">
            <li>Ve a <strong>Google Cloud Console → APIs &amp; Services → Library</strong></li>
            <li>Busca y activa estas <strong>3 APIs</strong>:
                <ul style="margin:.25rem 0">
                    <li>✅ <strong>Maps JavaScript API</strong></li>
                    <li>✅ <strong>Places API</strong></li>
                    <li>✅ <strong>Geocoding API</strong></li>
                </ul>
            </li>
            <li>Ve a <strong>Credentials → + CREATE CREDENTIALS → API key</strong></li>
            <li>Se genera una clave que empieza con <code>AIzaSy...</code></li>
            <li>En <strong>Edit API key</strong> agrega restricciones:
                <ul style="margin:.25rem 0">
                    <li><strong>HTTP referrers:</strong> <code>https://app.vidakushala.com/*</code></li>
                    <li><strong>API restrictions:</strong> Selecciona las 3 APIs de arriba</li>
                </ul>
            </li>
        </ol>
        <div style="margin-top:.75rem;padding:.6rem 1rem;background:#fff3cd;border-radius:6px;font-size:.88rem;color:#856404">
            ⚠ <strong>El secreto OAuth (<code>GOCSPX-...</code>) es diferente a esta API Key.</strong> No los confundas — son dos cosas distintas en Google Cloud.
        </div>
    </div>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'vkd_gmaps_nonce' ); ?>
    <input type="hidden" name="action" value="vkd_save_gmaps">

    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:1.5rem;margin-bottom:1.5rem">
        <h2 style="margin:0 0 .25rem">🔑 API Key de Google Maps</h2>
        <p style="color:#666;font-size:.85rem;margin:0 0 1.25rem">Habilita Maps + Places + Geocoding en la app.</p>
        <table class="form-table" style="margin:0">
            <tr>
                <th style="width:200px"><label for="vk_gmaps_api_key">API Key <span style="color:red">*</span></label></th>
                <td>
                    <div style="display:flex;gap:.5rem;align-items:center;max-width:580px">
                        <input type="password" id="vk_gmaps_api_key" name="vk_gmaps_api_key"
                            value="<?php echo esc_attr( $api_key ); ?>"
                            class="regular-text" style="flex:1;font-family:monospace;font-size:.9rem"
                            placeholder="AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
                        <button type="button" onclick="var i=document.getElementById('vk_gmaps_api_key');i.type=i.type==='password'?'text':'password'" class="button">👁</button>
                    </div>
                    <p class="description">Empieza con <code>AIzaSy</code>. Diferente al secreto OAuth (<code>GOCSPX-</code>).</p>
                </td>
            </tr>
        </table>
        <?php if ( $api_key ) : ?>
        <div style="margin-top:1rem;padding:.75rem 1rem;background:#d4edda;border-radius:6px;display:inline-flex;align-items:center;gap:.75rem">
            <span style="color:#155724;font-weight:700">✅ API Key configurada</span>
            <code style="font-size:.82rem;color:#444"><?php echo esc_html( $masked ); ?></code>
        </div>
        <?php else : ?>
        <p style="margin:1rem 0 0;color:#856404;background:#fff3cd;padding:.5rem 1rem;border-radius:6px;display:inline-block">⚠ Sin API Key — el mapa y autocompletado no funcionarán</p>
        <?php endif; ?>
    </div>

    <p class="submit">
        <button type="submit" class="button button-primary" style="font-size:1rem;padding:.5rem 2rem">💾 Guardar API Key</button>
    </p>
    </form>
    </div>
    <?php
}

/* ══════════════════════════════════════════════════════════════════════════════
   PÁGINA — CATEGORÍAS
══════════════════════════════════════════════════════════════════════════════ */

add_action( 'admin_post_vkd_save_category', 'vkd_handle_save_category' );
add_action( 'admin_post_vkd_delete_category', 'vkd_handle_delete_category' );

function vkd_handle_save_category() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acceso denegado' );
    check_admin_referer( 'vkd_cat_nonce' );
    global $wpdb;
    $table = $wpdb->prefix . 'vkd_categories';
    $name  = sanitize_text_field( $_POST['cat_name']  ?? '' );
    $icon  = sanitize_text_field( $_POST['cat_icon']  ?? '' );
    $order = (int) ( $_POST['cat_order'] ?? 0 );
    $id    = (int) ( $_POST['cat_id']    ?? 0 );
    if ( ! $name ) {
        wp_safe_redirect( admin_url( 'admin.php?page=vkd-categories&error=empty' ) );
        exit;
    }
    $slug = sanitize_title( $name );
    if ( $id ) {
        $wpdb->update( $table, array( 'name' => $name, 'slug' => $slug, 'icon' => $icon, 'order' => $order ), array( 'id' => $id ) );
    } else {
        $wpdb->insert( $table, array( 'name' => $name, 'slug' => $slug, 'icon' => $icon, 'order' => $order ) );
    }
    wp_safe_redirect( admin_url( 'admin.php?page=vkd-categories&saved=1' ) );
    exit;
}

function vkd_handle_delete_category() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acceso denegado' );
    check_admin_referer( 'vkd_del_cat_' . (int) ( $_GET['id'] ?? 0 ) );
    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'vkd_categories', array( 'id' => (int) $_GET['id'] ) );
    wp_safe_redirect( admin_url( 'admin.php?page=vkd-categories&deleted=1' ) );
    exit;
}

function vkd_page_categories() {
    global $wpdb;
    $table = $wpdb->prefix . 'vkd_categories';
    $cats  = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY `order` ASC, name ASC" );
    $edit  = isset( $_GET['edit'] ) ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", (int) $_GET['edit'] ) ) : null;
    ?>
    <div class="wrap vkd-wrap">
    <h1 class="wp-heading-inline">📂 Categorías del Directorio</h1>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>✅ Categoría guardada.</p></div><?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-info is-dismissible"><p>🗑 Categoría eliminada.</p></div><?php endif; ?>
    <?php if ( isset( $_GET['error'] ) ) : ?><div class="notice notice-error is-dismissible"><p>⚠ El nombre es obligatorio.</p></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:2rem;margin-top:1rem;align-items:start">

    <!-- Lista -->
    <div class="vkd-table-wrap">
    <?php if ( $cats ) : ?>
    <table class="vkd-table">
      <thead><tr>
        <th>#</th><th>Icono</th><th>Nombre</th><th>Slug</th><th>Orden</th><th>Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach ( $cats as $c ) : ?>
      <tr>
        <td><?php echo (int) $c->id; ?></td>
        <td style="font-size:1.4rem"><?php echo esc_html( $c->icon ); ?></td>
        <td><strong><?php echo esc_html( $c->name ); ?></strong></td>
        <td><code><?php echo esc_html( $c->slug ); ?></code></td>
        <td><?php echo (int) $c->order; ?></td>
        <td>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=vkd-categories&edit=' . $c->id ) ); ?>" class="button button-small">✏ Editar</a>
          &nbsp;
          <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vkd_delete_category&id=' . $c->id ), 'vkd_del_cat_' . $c->id ) ); ?>"
             class="button button-small" style="color:#c00;border-color:#c00"
             onclick="return confirm('¿Eliminar esta categoría?')">🗑 Eliminar</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else : ?>
    <p style="padding:1.5rem;color:#777;text-align:center">No hay categorías aún. Crea la primera usando el formulario.</p>
    <?php endif; ?>
    </div>

    <!-- Formulario -->
    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:1.5rem">
    <h3 style="margin-top:0"><?php echo $edit ? '✏ Editar categoría' : '➕ Nueva categoría'; ?></h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'vkd_cat_nonce' ); ?>
      <input type="hidden" name="action"  value="vkd_save_category">
      <input type="hidden" name="cat_id"  value="<?php echo $edit ? (int) $edit->id : 0; ?>">

      <p>
        <label><strong>Nombre *</strong><br>
        <input type="text" name="cat_name" value="<?php echo $edit ? esc_attr( $edit->name ) : ''; ?>" required style="width:100%"></label>
      </p>
      <p>
        <label><strong>Icono</strong> <span style="color:#888;font-size:.82rem">(emoji opcional)</span><br>
        <input type="text" name="cat_icon" value="<?php echo $edit ? esc_attr( $edit->icon ) : ''; ?>" style="width:80px;font-size:1.4rem;text-align:center" placeholder="🧘"></label>
      </p>
      <p>
        <label><strong>Orden</strong><br>
        <input type="number" name="cat_order" value="<?php echo $edit ? (int) $edit->order : 0; ?>" style="width:80px"></label>
      </p>
      <p>
        <button type="submit" class="button button-primary" style="width:100%;padding:.55rem">
          <?php echo $edit ? '💾 Actualizar' : '➕ Crear categoría'; ?>
        </button>
      </p>
      <?php if ( $edit ) : ?>
      <p style="text-align:center"><a href="<?php echo esc_url( admin_url( 'admin.php?page=vkd-categories' ) ); ?>">Cancelar edición</a></p>
      <?php endif; ?>
    </form>
    </div>

    </div><!-- grid -->

    <div style="margin-top:2rem;padding:1rem;background:#f8f9fb;border:1px solid #e8ecf0;border-radius:8px">
      <strong>📋 Shortcode para el directorio:</strong>
      <code style="background:#fff;padding:.3rem .7rem;border-radius:4px;border:1px solid #ddd;margin-left:.5rem">[vkd_directorio]</code>
      &nbsp;&nbsp;
      <strong>📋 Shortcode perfil individual:</strong>
      <code style="background:#fff;padding:.3rem .7rem;border-radius:4px;border:1px solid #ddd;margin-left:.5rem">[vkd_perfil uid="123"]</code>
    </div>
    </div>
    <?php
}

/* ══════════════════════════════════════════════════════════════════════════════
   PÁGINA: APROBACIÓN DE PERFILES
══════════════════════════════════════════════════════════════════════════════ */

function vkd_page_approval() {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No permitido' );

    $table = $wpdb->prefix . VKD_TABLE;

    // ── Procesar acción (aprobar / rechazar) ────────────────────────────────
    $notice = '';
    if ( isset( $_POST['vkd_action'], $_POST['vkd_uid'], $_POST['_wpnonce'] ) &&
         wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'vkd_approval' ) ) {

        $action = sanitize_text_field( $_POST['vkd_action'] );
        $uid    = (int) $_POST['vkd_uid'];
        $row    = vkd_get_record( $uid );

        if ( $row ) {
            if ( $action === 'approve' ) {
                $wpdb->update( $table, array(
                    'approval_status' => 'approved',
                    'approved_at'     => current_time( 'mysql' ),
                    'approved_by'     => get_current_user_id(),
                ), array( 'user_id' => $uid ) );
                if ( (int) $row->post_id > 0 ) {
                    $wpdb->update( $wpdb->posts, array(
                        'post_status'       => 'publish',
                        'post_modified'     => current_time( 'mysql' ),
                        'post_modified_gmt' => current_time( 'mysql', 1 ),
                    ), array( 'ID' => (int) $row->post_id ) );
                    clean_post_cache( (int) $row->post_id );
                }
                // Notificación push al usuario
                vkd_notify_dir_approved( $uid, $row );
                $notice = '<div class="notice notice-success is-dismissible"><p>✅ Perfil de <strong>' . esc_html( $row->name ) . '</strong> aprobado y publicado. Notificación enviada al usuario.</p></div>';
            } elseif ( $action === 'reject' ) {
                $wpdb->update( $table, array( 'approval_status' => 'rejected' ), array( 'user_id' => $uid ) );
                if ( (int) $row->post_id > 0 ) {
                    $wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => (int) $row->post_id ) );
                    clean_post_cache( (int) $row->post_id );
                }
                $notice = '<div class="notice notice-warning is-dismissible"><p>⛔ Perfil de <strong>' . esc_html( $row->name ) . '</strong> rechazado.</p></div>';
            } elseif ( $action === 'reset' ) {
                $wpdb->update( $table, array( 'approval_status' => 'pending' ), array( 'user_id' => $uid ) );
                if ( (int) $row->post_id > 0 ) {
                    $wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => (int) $row->post_id ) );
                    clean_post_cache( (int) $row->post_id );
                }
                $notice = '<div class="notice notice-info is-dismissible"><p>🔄 Perfil de <strong>' . esc_html( $row->name ) . '</strong> vuelto a pendiente.</p></div>';
            }
        }
    }

    // ── Filtro de estado activo ─────────────────────────────────────────────
    $filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'pending';
    if ( ! in_array( $filter, array( 'pending', 'approved', 'rejected', 'all' ), true ) ) $filter = 'pending';

    // ── Diagnóstico: verificar que la columna approval_status existe ──────────
    $col_exists = $wpdb->get_var( "SHOW COLUMNS FROM `{$table}` LIKE 'approval_status'" );
    if ( ! $col_exists ) {
        // La columna no existe todavía: añadirla ahora sin esperar a dbDelta
        $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `approval_status` varchar(20) NOT NULL DEFAULT 'pending'" );
        $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `approved_at` datetime DEFAULT NULL" );
        $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `approved_by` bigint(20) unsigned NOT NULL DEFAULT 0" );
        // Marcar perfiles ya publicados como aprobados
        $wpdb->query(
            "UPDATE `{$table}` p
             INNER JOIN {$wpdb->posts} wp ON wp.ID = p.post_id
             SET p.approval_status = 'approved', p.approved_at = NOW()
             WHERE wp.post_status = 'publish'"
        );
        delete_option( 'vkd_approval_migrated' );
        delete_option( 'vkd_db_version' );
        echo '<div class="notice notice-warning is-dismissible"><p>⚠️ Se añadió la columna <code>approval_status</code> a la tabla. Los perfiles ya publicados fueron marcados como aprobados. Recarga la página.</p></div>';
    }

    // ── Releer contadores y filas tras posible migración ───────────────────
    $counts = $wpdb->get_results( "SELECT approval_status, COUNT(*) as n FROM `{$table}` GROUP BY approval_status" );
    $cnt = array( 'pending' => 0, 'approved' => 0, 'rejected' => 0 );
    foreach ( $counts as $c ) $cnt[ $c->approval_status ] = (int) $c->n;
    $where = $filter === 'all' ? "WHERE p.name != ''" : $wpdb->prepare( "WHERE p.approval_status = %s", $filter );
    $rows  = $wpdb->get_results(
        "SELECT p.id, p.user_id, p.post_id, p.name, p.tagline, p.profession,
                p.email, p.city, p.approval_status, p.approved_at, p.created_at
         FROM `{$table}` p {$where} ORDER BY p.created_at DESC"
    );

    $base_url = admin_url( 'admin.php?page=vkd-approval' );
    ?>
    <div class="wrap">
      <h1 style="display:flex;align-items:center;gap:.5rem">🕐 Aprobación de perfiles
        <span style="font-size:.85rem;font-weight:400;color:#666;margin-left:.5rem">(<?php echo (int)($cnt['pending']); ?> pendiente<?php echo $cnt['pending'] !== 1 ? 's' : ''; ?>)</span>
      </h1>

      <?php echo wp_kses_post( $notice ); ?>

      <!-- Tabs de estado -->
      <ul class="subsubsub" style="margin-bottom:1rem">
        <?php
        $tabs = array(
            'pending'  => '🕐 Pendientes <span class="count">(' . $cnt['pending']  . ')</span>',
            'approved' => '✅ Aprobados <span class="count">(' . $cnt['approved'] . ')</span>',
            'rejected' => '⛔ Rechazados <span class="count">(' . $cnt['rejected'] . ')</span>',
            'all'      => 'Todos',
        );
        $tab_list = array();
        foreach ( $tabs as $k => $label ) {
            $class = ( $filter === $k ) ? 'current' : '';
            $tab_list[] = '<li><a href="' . esc_url( add_query_arg( 'status', $k, $base_url ) ) . '" class="' . $class . '">' . $label . '</a></li>';
        }
        echo implode( ' | ', $tab_list );
        ?>
      </ul>

      <?php if ( empty( $rows ) ) : ?>
        <p style="color:#666;margin-top:2rem">No hay perfiles con estado «<?php echo esc_html( $filter ); ?>».</p>
      <?php else : ?>

      <table class="wp-list-table widefat fixed striped" style="margin-top:.5rem">
        <thead>
          <tr>
            <th style="width:200px">Nombre</th>
            <th>Profesión / Ciudad</th>
            <th>Usuario / Email</th>
            <th style="width:110px">Enviado</th>
            <th style="width:120px">Estado</th>
            <th style="width:200px">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ( $rows as $r ) :
            $u       = get_userdata( (int) $r->user_id );
            $status  = (string) $r->approval_status;
            $badge   = array(
                'pending'  => '<span style="background:#fff3cd;color:#856404;padding:2px 8px;border-radius:4px;font-size:.8rem;font-weight:600">🕐 Pendiente</span>',
                'approved' => '<span style="background:#d1e7dd;color:#0f5132;padding:2px 8px;border-radius:4px;font-size:.8rem;font-weight:600">✅ Aprobado</span>',
                'rejected' => '<span style="background:#f8d7da;color:#842029;padding:2px 8px;border-radius:4px;font-size:.8rem;font-weight:600">⛔ Rechazado</span>',
            );
            $edit_url = admin_url( 'admin.php?page=vkd-edit&uid=' . (int) $r->user_id );
        ?>
          <tr>
            <td>
              <strong><?php echo esc_html( $r->name ?: '(sin nombre)' ); ?></strong>
              <?php if ( $r->tagline ) echo '<br><small style="color:#777">' . esc_html( $r->tagline ) . '</small>'; ?>
            </td>
            <td>
              <?php echo esc_html( $r->profession ?: '—' ); ?>
              <?php if ( $r->city ) echo '<br><small style="color:#777">📍 ' . esc_html( $r->city ) . '</small>'; ?>
            </td>
            <td>
              <?php echo $u ? esc_html( $u->user_login ) : '<em>uid ' . (int)$r->user_id . '</em>'; ?>
              <?php if ( $r->email ) echo '<br><small style="color:#777">' . esc_html( $r->email ) . '</small>'; ?>
            </td>
            <td style="font-size:.82rem;color:#555">
              <?php echo esc_html( substr( (string)$r->created_at, 0, 10 ) ); ?>
            </td>
            <td><?php echo isset( $badge[$status] ) ? $badge[$status] : esc_html( $status ); ?></td>
            <td>
              <div style="display:flex;gap:.3rem;flex-wrap:wrap">
                <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">Ver / Editar</a>

                <?php if ( $status !== 'approved' ) : ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('¿Aprobar este perfil y publicarlo en el directorio?')">
                    <?php wp_nonce_field( 'vkd_approval' ); ?>
                    <input type="hidden" name="vkd_action" value="approve">
                    <input type="hidden" name="vkd_uid" value="<?php echo (int)$r->user_id; ?>">
                    <button type="submit" class="button button-small" style="background:#0f5132;color:#fff;border-color:#0f5132">✅ Aprobar</button>
                  </form>
                <?php endif; ?>

                <?php if ( $status !== 'rejected' ) : ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('¿Rechazar este perfil?')">
                    <?php wp_nonce_field( 'vkd_approval' ); ?>
                    <input type="hidden" name="vkd_action" value="reject">
                    <input type="hidden" name="vkd_uid" value="<?php echo (int)$r->user_id; ?>">
                    <button type="submit" class="button button-small" style="background:#842029;color:#fff;border-color:#842029">⛔ Rechazar</button>
                  </form>
                <?php endif; ?>

                <?php if ( $status !== 'pending' ) : ?>
                  <form method="post" style="display:inline">
                    <?php wp_nonce_field( 'vkd_approval' ); ?>
                    <input type="hidden" name="vkd_action" value="reset">
                    <input type="hidden" name="vkd_uid" value="<?php echo (int)$r->user_id; ?>">
                    <button type="submit" class="button button-small">🔄 Pendiente</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php
}
