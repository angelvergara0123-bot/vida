<?php
/**
 * vk-directory.php — Módulo de Directorio Profesional · VidaKushala
 *
 * Fuente de verdad: tabla {prefix}vk_professionals (una fila por usuario).
 * Sincronización: los datos se reflejan en wp_posts/at_biz_dir para Directorist.
 *
 * Rutas REST (base /wp-json/vk/v1/dir/):
 *   GET  status        — tiene perfil el usuario?
 *   GET  profile       — perfil completo
 *   POST profile       — crear/actualizar
 *   POST upload-image  — subir imagen
 *   GET  categories    — categorías Directorist
 *   GET  debug         — diagnóstico
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════════════════════════
   CONSTANTES
══════════════════════════════════════════════════════════════════════════════ */

if ( ! defined( 'VKD_TABLE' ) )   define( 'VKD_TABLE',   'vk_professionals' );
if ( ! defined( 'VKD_VERSION' ) ) define( 'VKD_VERSION', '2.5' );
if ( ! defined( 'VKD_CPT' ) )     define( 'VKD_CPT',     'at_biz_dir' );

/* ══════════════════════════════════════════════════════════════════════════════
   PERMISO — función nombrada que usa vk_uid() del plugin principal
══════════════════════════════════════════════════════════════════════════════ */

function vkd_auth_check( $req ) {
    // Admins de WP logueados en el navegador también pueden acceder (debug desde browser)
    if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) return true;
    return function_exists( 'vk_uid' ) && ( (bool) vk_uid( $req ) );
}

/* ══════════════════════════════════════════════════════════════════════════════
   INSTALACIÓN DE TABLA
══════════════════════════════════════════════════════════════════════════════ */

function vkd_maybe_create_table() {
    global $wpdb;
    $table   = $wpdb->prefix . VKD_TABLE;
    $charset = $wpdb->get_charset_collate();

    if ( get_option( 'vkd_db_version' ) === VKD_VERSION
        && $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
        return;
    }

    $sql = "CREATE TABLE {$table} (
  id                bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id           bigint(20) unsigned NOT NULL,
  post_id           bigint(20) unsigned NOT NULL DEFAULT 0,
  name              varchar(255) NOT NULL DEFAULT '',
  tagline           varchar(500) NOT NULL DEFAULT '',
  bio               text NOT NULL,
  email             varchar(255) NOT NULL DEFAULT '',
  phone             varchar(100) NOT NULL DEFAULT '',
  whatsapp          varchar(100) NOT NULL DEFAULT '',
  website           varchar(500) NOT NULL DEFAULT '',
  address           varchar(500) NOT NULL DEFAULT '',
  city              varchar(255) NOT NULL DEFAULT '',
  postal_code       varchar(100) NOT NULL DEFAULT '',
  state             varchar(255) NOT NULL DEFAULT '',
  country           varchar(100) NOT NULL DEFAULT '',
  profession        varchar(255) NOT NULL DEFAULT '',
  specialty         varchar(255) NOT NULL DEFAULT '',
  experience        varchar(100) NOT NULL DEFAULT '',
  price_range       varchar(255) NOT NULL DEFAULT '',
  services          text NOT NULL,
  facebook          varchar(500) NOT NULL DEFAULT '',
  twitter           varchar(500) NOT NULL DEFAULT '',
  instagram         varchar(500) NOT NULL DEFAULT '',
  linkedin          varchar(500) NOT NULL DEFAULT '',
  youtube           varchar(500) NOT NULL DEFAULT '',
  tiktok            varchar(500) NOT NULL DEFAULT '',
  featured_image_id bigint(20) unsigned NOT NULL DEFAULT 0,
  logo_id           bigint(20) unsigned NOT NULL DEFAULT 0,
  gallery_ids       text NOT NULL,
  category_ids      text NOT NULL,
  lat               varchar(30) NOT NULL DEFAULT '',
  lng               varchar(30) NOT NULL DEFAULT '',
  gender            varchar(50) NOT NULL DEFAULT '',
  birth_year        smallint(4) unsigned NOT NULL DEFAULT 0,
  availability      varchar(50) NOT NULL DEFAULT 'accepting',
  languages         varchar(500) NOT NULL DEFAULT '',
  technologies      text NOT NULL,
  schedule_json     text NOT NULL,
  rating_avg        decimal(3,1) NOT NULL DEFAULT 0.0,
  rating_count      int unsigned NOT NULL DEFAULT 0,
  approval_status   varchar(20) NOT NULL DEFAULT 'pending',
  approved_at       datetime DEFAULT NULL,
  approved_by       bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at        datetime NOT NULL,
  updated_at        datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY idx_user (user_id),
  KEY idx_post (post_id)
) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Tabla de categorías propias
    $cats_t   = $wpdb->prefix . 'vkd_categories';
    $sql_cats = "CREATE TABLE {$cats_t} (
  id      int unsigned NOT NULL AUTO_INCREMENT,
  name    varchar(200) NOT NULL DEFAULT '',
  slug    varchar(200) NOT NULL DEFAULT '',
  icon    varchar(100) NOT NULL DEFAULT '',
  `order` smallint NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) {$charset};";
    dbDelta( $sql_cats );

    update_option( 'vkd_db_version', VKD_VERSION );
    delete_option( 'vkd_cats_seeded' );
    error_log( '[vkd] tablas listas (v' . VKD_VERSION . ')' );


}
add_action( 'init', 'vkd_maybe_create_table' );
add_action( 'init', 'vkd_maybe_seed_categories', 15 );

function vkd_maybe_seed_categories() {
    if ( get_option( 'vkd_cats_seeded' ) ) return;
    global $wpdb;
    $cats_t = $wpdb->prefix . 'vkd_categories';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cats_t ) ) !== $cats_t ) return;
    $wpdb->query( "DELETE FROM `{$cats_t}`" );
    $defaults = array(
        array( 'Fisioterapia',      '💪', 0  ),
        array( 'Biomagnetismo',     '🧲', 1  ),
        array( 'Terapia holística', '🌿', 2  ),
        array( 'Psicología',        '🧠', 3  ),
    );
    foreach ( $defaults as $d ) {
        $wpdb->insert( $cats_t, array(
            'name'  => $d[0],
            'slug'  => sanitize_title( $d[0] ),
            'icon'  => $d[1],
            'order' => $d[2],
        ) );
    }
    update_option( 'vkd_cats_seeded', '1' );
}

// Flush de reglas de reescritura cuando se crea un nuevo listing
add_action( 'init', 'vkd_maybe_flush_rewrites', 99 );
function vkd_maybe_flush_rewrites() {
    if ( get_option( 'vkd_needs_flush' ) ) {
        flush_rewrite_rules( false );
        delete_option( 'vkd_needs_flush' );
    }
}

// Reparación automática de slugs vacíos — corre una vez al cargar el admin
add_action( 'admin_init', 'vkd_auto_repair_slugs', 5 );
function vkd_auto_repair_slugs() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    // Solo corre si la tabla existe y no se ha reparado antes en esta versión
    if ( get_option( 'vkd_slugs_ok_v2' ) ) return;

    global $wpdb;
    $table = $wpdb->prefix . VKD_TABLE;
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) return;

    // Buscar posts at_biz_dir con post_name vacío o NULL
    $rows = $wpdb->get_results(
        "SELECT p.user_id, p.post_id, p.name
         FROM `{$table}` p
         INNER JOIN {$wpdb->posts} wp ON p.post_id = wp.ID
         WHERE (wp.post_name = '' OR wp.post_name IS NULL) AND p.post_id > 0"
    );

    foreach ( $rows as $r ) {
        // Respetar el approval_status: solo publicar si ya fue aprobado
        $approval = $wpdb->get_var( $wpdb->prepare(
            "SELECT approval_status FROM `{$table}` WHERE post_id = %d LIMIT 1",
            (int) $r->post_id
        ) );
        $target_status = ( $approval === 'approved' ) ? 'publish' : 'pending';
        $slug = wp_unique_post_slug(
            sanitize_title( ( $r->name ?: 'terapeuta' ) . '-' . $r->user_id ),
            (int) $r->post_id, $target_status, VKD_CPT, 0
        );
        $wpdb->update(
            $wpdb->posts,
            array( 'post_name' => $slug, 'post_status' => $target_status ),
            array( 'ID' => (int) $r->post_id )
        );
        clean_post_cache( (int) $r->post_id );
        error_log( "[vkd] auto-repair slug post_id={$r->post_id} → {$slug} status={$target_status}" );
    }

    flush_rewrite_rules( false );
    update_option( 'vkd_slugs_ok_v2', '1' );
}

// Acción de reparación desde el admin (botón en el panel)
add_action( 'admin_init', 'vkd_handle_repair' );
function vkd_handle_repair() {
    if ( ! isset( $_GET['vkd_repair'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'vkd_repair' ) ) return;

    global $wpdb;
    $table = $wpdb->prefix . VKD_TABLE;
    $fixed = 0;

    // Reparar posts sin slug
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.id, p.user_id, p.post_id, p.name FROM `{$table}` p
         INNER JOIN {$wpdb->posts} wp ON p.post_id = wp.ID
         WHERE wp.post_name = '' AND p.post_id > %d", 0
    ) );
    foreach ( $rows as $r ) {
        // Respetar el approval_status al reparar slugs
        $approval = $wpdb->get_var( $wpdb->prepare(
            "SELECT approval_status FROM `{$table}` WHERE post_id = %d LIMIT 1",
            (int) $r->post_id
        ) );
        $target_status = ( $approval === 'approved' ) ? 'publish' : 'pending';
        $slug = wp_unique_post_slug(
            sanitize_title( $r->name . '-' . $r->user_id ),
            (int) $r->post_id, $target_status, VKD_CPT, 0
        );
        $wpdb->update( $wpdb->posts,
            array( 'post_name' => $slug, 'post_status' => $target_status ),
            array( 'ID' => (int) $r->post_id )
        );
        clean_post_cache( (int) $r->post_id );
        $fixed++;
    }

    // Re-sincronizar todos los perfiles sin post
    $orphans = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE post_id = %d", 0
    ) );
    foreach ( $orphans as $row ) {
        vkd_sync_to_wp( $row, (int) $row->user_id );
        $fixed++;
    }

    flush_rewrite_rules( false );

    wp_safe_redirect( admin_url( 'admin.php?page=vkd-directory&vkd_msg=repaired&fixed=' . $fixed ) );
    exit;
}

/* ══════════════════════════════════════════════════════════════════════════════
   REGISTRO DE RUTAS REST
══════════════════════════════════════════════════════════════════════════════ */

add_action( 'rest_api_init', 'vkd_register_routes' );

function vkd_register_routes() {
    $auth = array( 'permission_callback' => 'vkd_auth_check' );
    $pub  = array( 'permission_callback' => '__return_true' );
    $base = 'vk/v1';

    register_rest_route( $base, '/dir/status',                   array_merge( $auth, array( 'methods' => 'GET',  'callback' => 'vkd_api_status' ) ) );
    register_rest_route( $base, '/dir/profile',                  array_merge( $auth, array( 'methods' => 'GET',  'callback' => 'vkd_api_get_profile' ) ) );
    register_rest_route( $base, '/dir/profile',                  array_merge( $auth, array( 'methods' => 'POST',   'callback' => 'vkd_api_save_profile'   ) ) );
    register_rest_route( $base, '/dir/profile',                  array_merge( $auth, array( 'methods' => 'DELETE', 'callback' => 'vkd_api_delete_profile' ) ) );
    register_rest_route( $base, '/dir/upload-image',             array_merge( $auth, array( 'methods' => 'POST',   'callback' => 'vkd_api_upload_image'   ) ) );
    register_rest_route( $base, '/dir/categories',                    array_merge( $pub,  array( 'methods' => 'GET',    'callback' => 'vkd_api_categories'  ) ) );
    register_rest_route( $base, '/dir/categories',                    array_merge( $auth, array( 'methods' => 'POST',   'callback' => 'vkd_api_cat_create'   ) ) );
    register_rest_route( $base, '/dir/categories/(?P<id>[0-9]+)',     array_merge( $auth, array( 'methods' => 'PUT',    'callback' => 'vkd_api_cat_update'   ) ) );
    register_rest_route( $base, '/dir/categories/(?P<id>[0-9]+)',     array_merge( $auth, array( 'methods' => 'DELETE', 'callback' => 'vkd_api_cat_delete'   ) ) );
    register_rest_route( $base, '/dir/list',                     array_merge( $pub,  array( 'methods' => 'GET',  'callback' => 'vkd_api_list' ) ) );
    register_rest_route( $base, '/dir/map-points',               array_merge( $pub,  array( 'methods' => 'GET',  'callback' => 'vkd_api_map_points' ) ) );
    register_rest_route( $base, '/dir/view/(?P<uid>[0-9]+)',     array_merge( $pub,  array( 'methods' => 'GET',  'callback' => 'vkd_api_view' ) ) );
    register_rest_route( $base, '/dir/cities',                   array_merge( $pub,  array( 'methods' => 'GET',  'callback' => 'vkd_api_cities' ) ) );
    register_rest_route( $base, '/config/public',                array_merge( $pub,  array( 'methods' => 'GET',  'callback' => 'vkd_api_config_public' ) ) );
    register_rest_route( $base, '/dir/debug',                    array_merge( $auth, array( 'methods' => 'GET',  'callback' => 'vkd_api_debug' ) ) );
    register_rest_route( $base, '/dir/approve/(?P<uid>[0-9]+)', array_merge( $auth, array( 'methods' => 'POST',  'callback' => 'vkd_api_approve' ) ) );
    register_rest_route( $base, '/dir/reject/(?P<uid>[0-9]+)',  array_merge( $auth, array( 'methods' => 'POST',  'callback' => 'vkd_api_reject'  ) ) );
    register_rest_route( $base, '/dir/pending',                 array_merge( $auth, array( 'methods' => 'GET',   'callback' => 'vkd_api_pending_list' ) ) );
}

/* ══════════════════════════════════════════════════════════════════════════════
   CRUD — FUENTE DE VERDAD (wp_vk_professionals)
══════════════════════════════════════════════════════════════════════════════ */

function vkd_get_record( $user_id ) {
    global $wpdb;
    $table = $wpdb->prefix . VKD_TABLE;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", (int) $user_id ) );
}

function vkd_get_record_by_post( $post_id ) {
    global $wpdb;
    $table = $wpdb->prefix . VKD_TABLE;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d LIMIT 1", (int) $post_id ) );
}

/**
 * Inserta o actualiza el registro del profesional.
 * Solo escribe los campos que vienen en $data (merge con los existentes).
 * @return int|WP_Error  ID de fila si OK.
 */
function vkd_upsert( $user_id, $data ) {
    global $wpdb;
    $user_id  = (int) $user_id;
    $table    = $wpdb->prefix . VKD_TABLE;
    $existing = vkd_get_record( $user_id );
    $now      = current_time( 'mysql' );

    $str_fields = array(
        'name','tagline','bio','email','phone','whatsapp','website',
        'address','city','state','country','profession','specialty',
        'experience','price_range','services',
        'facebook','twitter','instagram','linkedin','youtube','tiktok',
        'lat','lng','postal_code','gender','availability','languages','technologies','schedule_json',
    );

    if ( $existing ) {
        $row = array( 'updated_at' => $now );
        foreach ( $str_fields as $f ) {
            if ( array_key_exists( $f, $data ) ) {
                $row[ $f ] = (string) $data[ $f ];
            }
        }
        if ( array_key_exists( 'featured_image_id', $data ) ) $row['featured_image_id'] = max( 0, (int) $data['featured_image_id'] );
        if ( array_key_exists( 'logo_id',           $data ) ) $row['logo_id']           = max( 0, (int) $data['logo_id'] );
        if ( array_key_exists( 'gallery_ids',  $data ) ) $row['gallery_ids']  = implode( ',', array_filter( array_map( 'intval', (array) $data['gallery_ids'] ) ) );
        if ( array_key_exists( 'category_ids', $data ) ) $row['category_ids'] = implode( ',', array_filter( array_map( 'intval', (array) $data['category_ids'] ) ) );
        if ( array_key_exists( 'post_id',      $data ) ) $row['post_id']      = max( 0, (int) $data['post_id'] );
        if ( array_key_exists( 'birth_year',   $data ) ) $row['birth_year']   = max( 0, (int) $data['birth_year'] );
        if ( array_key_exists( 'rating_avg',   $data ) ) $row['rating_avg']   = round( (float) $data['rating_avg'], 1 );
        if ( array_key_exists( 'rating_count', $data ) ) $row['rating_count'] = max( 0, (int) $data['rating_count'] );

        $ok = $wpdb->update( $table, $row, array( 'user_id' => $user_id ) );
        if ( false === $ok ) {
            error_log( "[vkd] upsert UPDATE error uid={$user_id}: " . $wpdb->last_error );
            return new WP_Error( 'db_error', 'Error al guardar perfil: ' . $wpdb->last_error );
        }
        return (int) $existing->id;

    } else {
        $row = array(
            'user_id'           => $user_id,
            'post_id'           => 0,
            'featured_image_id' => 0,
            'logo_id'           => 0,
            'gallery_ids'       => '',
            'category_ids'      => '',
            'created_at'        => $now,
            'updated_at'        => $now,
        );
        foreach ( $str_fields as $f ) {
            $row[ $f ] = array_key_exists( $f, $data ) ? (string) $data[ $f ] : '';
        }
        if ( array_key_exists( 'featured_image_id', $data ) ) $row['featured_image_id'] = max( 0, (int) $data['featured_image_id'] );
        if ( array_key_exists( 'logo_id',           $data ) ) $row['logo_id']           = max( 0, (int) $data['logo_id'] );
        if ( array_key_exists( 'gallery_ids',  $data ) ) $row['gallery_ids']  = implode( ',', array_filter( array_map( 'intval', (array) $data['gallery_ids'] ) ) );
        if ( array_key_exists( 'category_ids', $data ) ) $row['category_ids'] = implode( ',', array_filter( array_map( 'intval', (array) $data['category_ids'] ) ) );

        $ok = $wpdb->insert( $table, $row );
        if ( false === $ok ) {
            error_log( "[vkd] upsert INSERT error uid={$user_id}: " . $wpdb->last_error );
            return new WP_Error( 'db_error', 'Error al crear perfil: ' . $wpdb->last_error );
        }
        return (int) $wpdb->insert_id;
    }
}

/** Convierte una fila de la tabla al array que devuelve la API. */
function vkd_row_to_api( $row ) {
    if ( ! $row ) return array();
    $post_id = (int) $row->post_id;
    $post_st = $post_id ? (string) get_post_status( $post_id ) : '';

    // URL canónica propia (/directorio/{uid}/), no depende de Directorist ni de post_name
    $permalink = vkd_profile_url( (int) $row->user_id );

    $gids = $row->gallery_ids  ? array_values( array_filter( array_map( 'intval', explode( ',', $row->gallery_ids ) ) ) )  : array();
    $cids = $row->category_ids ? array_values( array_filter( array_map( 'intval', explode( ',', $row->category_ids ) ) ) ) : array();

    return array(
        'id'               => (int) $row->id,
        'user_id'          => (int) $row->user_id,
        'post_id'          => $post_id,
        'name'             => (string) $row->name,
        'tagline'          => (string) $row->tagline,
        'bio'              => (string) $row->bio,
        'email'            => (string) $row->email,
        'phone'            => (string) $row->phone,
        'whatsapp'         => (string) $row->whatsapp,
        'website'          => (string) $row->website,
        'address'          => (string) $row->address,
        'city'             => (string) $row->city,
        'postal_code'      => (string) ( $row->postal_code ?? '' ),
        'state'            => (string) $row->state,
        'country'          => (string) $row->country,
        'profession'       => (string) $row->profession,
        'specialty'        => (string) $row->specialty,
        'experience'       => (string) $row->experience,
        'price_range'      => (string) $row->price_range,
        'services'         => (string) $row->services,
        'facebook'         => (string) $row->facebook,
        'twitter'          => (string) $row->twitter,
        'instagram'        => (string) $row->instagram,
        'linkedin'         => (string) $row->linkedin,
        'youtube'          => (string) $row->youtube,
        'tiktok'           => (string) $row->tiktok,
        'featured_image_id'=> (int) $row->featured_image_id,
        'featured_image'   => $row->featured_image_id ? (string) wp_get_attachment_url( (int) $row->featured_image_id ) : '',
        'logo_id'          => (int) $row->logo_id,
        'logo'             => $row->logo_id ? (string) wp_get_attachment_url( (int) $row->logo_id ) : '',
        'gallery_ids'      => $gids,
        'category_ids'     => $cids,
        'categories'       => vkd_resolve_cats( $row->category_ids ),
        'lat'              => (string) $row->lat,
        'lng'              => (string) $row->lng,
        'gender'           => (string) ($row->gender      ?? ''),
        'birth_year'       => (int)    ($row->birth_year   ?? 0),
        'availability'     => (string) ($row->availability ?? 'accepting'),
        'languages'        => (string) ($row->languages    ?? ''),
        'technologies'     => (string) ($row->technologies ?? ''),
        'schedule_json'    => (string) ($row->schedule_json ?? ''),
        'rating_avg'       => (float)  ($row->rating_avg   ?? 0),
        'rating_count'     => (int)    ($row->rating_count ?? 0),
        'post_status'      => $post_st,
        'approval_status'  => (string) ( $row->approval_status ?? 'pending' ),
        'approved_at'      => (string) ( $row->approved_at ?? '' ),
        'permalink'        => $permalink,
        'updated_at'       => (string) $row->updated_at,
        'created_at'       => (string) $row->created_at,
    );
}

/* ══════════════════════════════════════════════════════════════════════════════
   SANITIZACIÓN DE ENTRADA
══════════════════════════════════════════════════════════════════════════════ */

function vkd_sanitize_body( $raw ) {
    $raw  = (array) $raw;
    $text = array(
        'name','tagline','email','phone','whatsapp','website',
        'address','city','state','country','profession','specialty',
        'experience','price_range','facebook','twitter','instagram',
        'linkedin','youtube','tiktok','lat','lng',
        'gender','availability','languages',
    );
    $out = array();
    foreach ( $text as $f ) {
        if ( array_key_exists( $f, $raw ) ) {
            $out[ $f ] = sanitize_text_field( (string) $raw[ $f ] );
        }
    }
    if ( array_key_exists( 'bio',           $raw ) ) $out['bio']           = sanitize_textarea_field( (string) $raw['bio'] );
    if ( array_key_exists( 'services',     $raw ) ) $out['services']     = sanitize_textarea_field( (string) $raw['services'] );
    if ( array_key_exists( 'technologies', $raw ) ) $out['technologies'] = sanitize_textarea_field( (string) $raw['technologies'] );
    if ( array_key_exists( 'schedule_json',$raw ) ) {
        $decoded = json_decode( (string) $raw['schedule_json'], true );
        $out['schedule_json'] = $decoded ? wp_json_encode( $decoded ) : '';
    }
    if ( array_key_exists( 'birth_year',   $raw ) ) $out['birth_year']   = max( 0, (int) $raw['birth_year'] );
    if ( array_key_exists( 'rating_avg',   $raw ) ) $out['rating_avg']   = round( (float) $raw['rating_avg'], 1 );
    if ( array_key_exists( 'rating_count', $raw ) ) $out['rating_count'] = max( 0, (int) $raw['rating_count'] );
    if ( array_key_exists( 'featured_image_id', $raw ) ) $out['featured_image_id'] = max( 0, (int) $raw['featured_image_id'] );
    if ( array_key_exists( 'logo_id',           $raw ) ) $out['logo_id']           = max( 0, (int) $raw['logo_id'] );
    if ( isset( $raw['gallery_ids'] )  && is_array( $raw['gallery_ids'] ) )  $out['gallery_ids']  = array_values( array_filter( array_map( 'intval', $raw['gallery_ids'] ) ) );
    if ( isset( $raw['category_ids'] ) && is_array( $raw['category_ids'] ) ) $out['category_ids'] = array_values( array_filter( array_map( 'intval', $raw['category_ids'] ) ) );
    return $out;
}

/* ══════════════════════════════════════════════════════════════════════════════
   SINCRONIZACIÓN CON DIRECTORIST (display en WordPress)
══════════════════════════════════════════════════════════════════════════════ */

/**
 * Crea o actualiza el post at_biz_dir sin disparar hooks de Directorist.
 * Nuevos perfiles: post_status='pending' hasta aprobación del admin.
 * Perfiles aprobados: se mantiene 'publish'.
 * @return int  post_id (0 si error)
 */
function vkd_sync_to_wp( $row, $user_id ) {
    global $wpdb;
    $user_id = (int) $user_id;
    $post_id = (int) $row->post_id;
    $title   = $row->name ?: '';
    if ( ! $title ) {
        $u = get_userdata( $user_id );
        $title = $u ? $u->display_name : 'Profesional';
    }
    $content = $row->bio     ? (string) $row->bio     : '';
    $excerpt = $row->tagline ? (string) $row->tagline : '';

    if ( $post_id ) {
        $still_ok = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s AND post_status != 'trash' LIMIT 1",
            $post_id, VKD_CPT
        ) );
        if ( ! $still_ok ) $post_id = 0;
    }

    // Slug único para la URL del perfil
    $base_slug = sanitize_title( $title . '-' . $user_id );

    // Determinar el approval_status actual del registro
    $approval = 'pending';
    if ( isset( $row->approval_status ) ) {
        $approval = (string) $row->approval_status;
    }
    $target_post_status = ( $approval === 'approved' ) ? 'publish' : 'pending';

    if ( $post_id ) {
        // ── UPDATE directo (sin hooks) ───────────────────────────────────────
        $current_slug = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_name FROM {$wpdb->posts} WHERE ID = %d LIMIT 1", $post_id
        ) );
        $update_data = array(
            'post_title'        => $title,
            'post_content'      => $content,
            'post_excerpt'      => $excerpt,
            'post_status'       => $target_post_status,
            'post_author'       => $user_id,
            'post_modified'     => current_time( 'mysql' ),
            'post_modified_gmt' => current_time( 'mysql', 1 ),
        );
        if ( empty( $current_slug ) ) {
            $update_data['post_name'] = wp_unique_post_slug( $base_slug, $post_id, $target_post_status, VKD_CPT, 0 );
        }
        $wpdb->update( $wpdb->posts, $update_data, array( 'ID' => $post_id ) );
        clean_post_cache( $post_id );

    } else {
        // ── INSERT — nuevo perfil queda en 'pending' hasta aprobación admin
        $inserted = wp_insert_post( array(
            'post_type'    => VKD_CPT,
            'post_title'   => $title,
            'post_name'    => $base_slug,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => 'draft',
            'post_author'  => $user_id,
        ), false );

        if ( ! $inserted || is_wp_error( $inserted ) ) {
            error_log( '[vkd] wp_insert_post falló uid=' . $user_id );
            return 0;
        }
        $post_id    = (int) $inserted;
        $final_slug = wp_unique_post_slug( $base_slug, $post_id, 'pending', VKD_CPT, 0 );
        $wpdb->update( $wpdb->posts, array(
            'post_status'       => 'pending',
            'post_name'         => $final_slug,
            'post_modified'     => current_time( 'mysql' ),
            'post_modified_gmt' => current_time( 'mysql', 1 ),
        ), array( 'ID' => $post_id ) );
        clean_post_cache( $post_id );

        // Guardar post_id en nuestra tabla y user_meta
        $wpdb->update( $wpdb->prefix . VKD_TABLE, array( 'post_id' => $post_id ), array( 'user_id' => $user_id ) );
        update_user_meta( $user_id, '_vk_dir_listing_id', $post_id );
        error_log( "[vkd] nuevo listing PENDIENTE post_id={$post_id} uid={$user_id}" );

        update_option( 'vkd_needs_flush', '1' );
    }

    // Sincronizar postmeta
    vkd_sync_meta( $post_id, $row );

    return $post_id;
}

/** Escribe todos los postmeta que Directorist necesita para mostrar el listing. */
function vkd_sync_meta( $post_id, $row ) {
    $post_id = (int) $post_id;

    $meta = array(
        '_tagline'          => (string) $row->tagline,
        '_email'            => (string) $row->email,
        '_phone'            => (string) $row->phone,
        '_whatsapp'         => (string) $row->whatsapp,
        '_website'          => (string) $row->website,
        '_address'          => (string) $row->address,
        '_city'             => (string) $row->city,
        '_state'            => (string) $row->state,
        '_country'          => (string) $row->country,
        '_zip'              => '',
        '_facebook'         => (string) $row->facebook,
        '_twitter'          => (string) $row->twitter,
        '_instagram'        => (string) $row->instagram,
        '_linkedin'         => (string) $row->linkedin,
        '_youtube'          => (string) $row->youtube,
        '_lat'              => (string) $row->lat,
        '_lng'              => (string) $row->lng,
        '_manual_lat'       => (string) $row->lat,
        '_manual_lng'       => (string) $row->lng,
        '_never_expire'     => '1',
        '_expiry_date'      => '',
        '_listing_status'   => 'post_status',
        '_listing_featured' => '0',
        '_address_type'     => 'local',
        '_pricing_type'     => 'fixed',
        '_price'            => '',
        '_price_range'      => (string) $row->price_range,
        '_vk_profession'    => (string) $row->profession,
        '_vk_specialty'     => (string) $row->specialty,
        '_vk_experience'    => (string) $row->experience,
        '_vk_services'      => (string) $row->services,
        '_vk_user_id'       => (string) $row->user_id,
    );
    foreach ( $meta as $k => $v ) {
        update_post_meta( $post_id, $k, $v );
    }

    // Mapa
    if ( ! empty( $row->lat ) && ! empty( $row->lng ) ) {
        update_post_meta( $post_id, '_manually_set', '1' );
        update_post_meta( $post_id, '_hide_map',     '0' );
    } else {
        update_post_meta( $post_id, '_manually_set', '0' );
        update_post_meta( $post_id, '_hide_map',     '1' );
    }

    // _social_info serializado (Directorist v7+)
    $social = array();
    foreach ( array( 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube' ) as $s ) {
        if ( ! empty( $row->$s ) ) $social[ $s ] = (string) $row->$s;
    }
    if ( $social ) update_post_meta( $post_id, '_social_info', $social );

    // Imagen destacada
    if ( (int) $row->featured_image_id ) set_post_thumbnail( $post_id, (int) $row->featured_image_id );

    // Logo
    if ( (int) $row->logo_id ) update_post_meta( $post_id, '_logo', (int) $row->logo_id );

    // Galería
    if ( $row->gallery_ids ) update_post_meta( $post_id, '_listing_img', (string) $row->gallery_ids );

    // Categorías
    if ( $row->category_ids ) {
        $cat_ids = array_values( array_filter( array_map( 'intval', explode( ',', (string) $row->category_ids ) ) ) );
        if ( $cat_ids ) {
            foreach ( array( 'at_biz_dir-category', 'atbdp_listing_category' ) as $tax ) {
                if ( taxonomy_exists( $tax ) ) {
                    wp_set_post_terms( $post_id, $cat_ids, $tax, false );
                    break;
                }
            }
        }
    }

    clean_post_cache( $post_id );
}

/* ══════════════════════════════════════════════════════════════════════════════
   CALLBACKS REST
══════════════════════════════════════════════════════════════════════════════ */

function vkd_api_status( $req ) {
    $uid = (int) vk_uid( $req );
    $row = vkd_get_record( $uid );
    if ( ! $row ) {
        return rest_ensure_response( array( 'ok' => true, 'has_profile' => false ) );
    }
    return rest_ensure_response( array(
        'ok'              => true,
        'has_profile'     => true,
        'post_id'         => (int) $row->post_id,
        'permalink'       => $row->post_id ? (string) get_permalink( (int) $row->post_id ) : '',
        'approval_status' => (string) ( $row->approval_status ?? 'pending' ),
        'updated_at'      => $row->updated_at,
    ) );
}

function vkd_api_get_profile( $req ) {
    $uid = (int) vk_uid( $req );
    $row = vkd_get_record( $uid );
    return rest_ensure_response( array(
        'ok'      => true,
        'profile' => $row ? vkd_row_to_api( $row ) : null,
    ) );
}

function vkd_api_save_profile( $req ) {
    $uid  = (int) vk_uid( $req );
    $body = vkd_sanitize_body( $req->get_json_params() );

    if ( empty( $body ) ) {
        return new WP_Error( 'empty_body', 'No se recibieron datos', array( 'status' => 400 ) );
    }

    // 1. Guardar en la fuente de verdad
    $row_id = vkd_upsert( $uid, $body );
    if ( is_wp_error( $row_id ) ) return $row_id;

    // 2. Leer el registro completo actualizado
    $row = vkd_get_record( $uid );
    if ( ! $row ) {
        return new WP_Error( 'internal', 'Error leyendo el perfil guardado', array( 'status' => 500 ) );
    }

    $was_new = ( (int) $row->post_id === 0 );

    // 3. Sincronizar con Directorist (best-effort)
    $post_id = 0;
    try {
        $post_id = vkd_sync_to_wp( $row, $uid );
    } catch ( Exception $e ) {
        error_log( '[vkd] vkd_sync_to_wp exception: ' . $e->getMessage() );
    }

    $is_new    = $was_new && $post_id > 0;
    $permalink = vkd_profile_url( $uid );

    // Releer para obtener post_id en la respuesta
    $row = vkd_get_record( $uid );

    $approval = $row ? (string) ( $row->approval_status ?? 'pending' ) : 'pending';
    error_log( "[vkd] SAVE OK uid={$uid} row_id={$row_id} post_id={$post_id} is_new=" . ( $is_new ? '1' : '0' ) . " approval={$approval}" );

    if ( $is_new ) {
        $msg = 'pending';
    } elseif ( $approval === 'approved' ) {
        $msg = 'updated';
    } else {
        $msg = 'pending';
    }

    // Notificar a administradores sobre perfil pendiente de revisión
    vkd_notify_admin_dir_pending( $uid, $row, $is_new );

    return rest_ensure_response( array(
        'ok'              => true,
        'is_new'          => $is_new,
        'post_id'         => $post_id,
        'permalink'       => $permalink,
        'approval_status' => $approval,
        'save_result'     => $msg,
        'message'         => ( $msg === 'pending' )
            ? '¡Tu perfil ha sido enviado correctamente! Está pendiente de aprobación.'
            : 'Perfil actualizado correctamente.',
        'profile'         => $row ? vkd_row_to_api( $row ) : null,
    ) );
}

function vkd_api_delete_profile( $req ) {
    global $wpdb;
    $uid   = (int) vk_uid( $req );
    $table = $wpdb->prefix . VKD_TABLE;
    $row   = vkd_get_record( $uid );

    if ( ! $row ) {
        return new WP_Error( 'not_found', 'No existe un perfil para este usuario', array( 'status' => 404 ) );
    }

    // Eliminar el post de WordPress si existe
    $post_id = (int) $row->post_id;
    if ( $post_id > 0 ) {
        wp_delete_post( $post_id, true ); // true = borrado permanente (no papelera)
    }

    // Eliminar el registro de la tabla personalizada
    $deleted = $wpdb->delete( $table, array( 'user_id' => $uid ), array( '%d' ) );
    if ( $deleted === false ) {
        return new WP_Error( 'db_error', 'Error al eliminar el perfil de la base de datos', array( 'status' => 500 ) );
    }

    error_log( "[vkd] DELETE profile uid={$uid} post_id={$post_id}" );

    return rest_ensure_response( array(
        'ok'      => true,
        'message' => 'Perfil eliminado correctamente.',
    ) );
}

function vkd_api_upload_image( $req ) {
    $uid   = (int) vk_uid( $req );
    $files = $req->get_file_params();
    $body  = $req->get_body_params();
    $type  = sanitize_key( isset( $body['type'] ) ? $body['type'] : 'featured' );

    if ( empty( $files['file']['tmp_name'] ) ) {
        return new WP_Error( 'no_file', 'No se recibió ningún archivo', array( 'status' => 400 ) );
    }

    $finfo   = new finfo( FILEINFO_MIME_TYPE );
    $mime    = $finfo->file( $files['file']['tmp_name'] );
    $allowed = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
    if ( ! in_array( $mime, $allowed, true ) ) {
        return new WP_Error( 'bad_mime', 'Tipo no permitido: ' . $mime, array( 'status' => 415 ) );
    }

    wp_set_current_user( $uid );
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $att_id = media_handle_upload( 'file', 0 );
    if ( is_wp_error( $att_id ) ) {
        error_log( '[vkd] upload error uid=' . $uid . ': ' . $att_id->get_error_message() );
        return new WP_Error( 'upload_error', $att_id->get_error_message(), array( 'status' => 500 ) );
    }

    $url   = (string) wp_get_attachment_url( $att_id );
    $field = ( $type === 'logo' ) ? 'logo_id' : 'featured_image_id';

    $res = vkd_upsert( $uid, array( $field => $att_id ) );
    if ( ! is_wp_error( $res ) ) {
        $row = vkd_get_record( $uid );
        if ( $row && $row->post_id ) {
            vkd_sync_meta( (int) $row->post_id, $row );
        }
    }

    error_log( "[vkd] upload OK uid={$uid} att_id={$att_id} type={$type}" );
    return rest_ensure_response( array( 'ok' => true, 'id' => $att_id, 'url' => $url, 'type' => $type ) );
}

function vkd_api_categories( $req ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vkd_categories';
    $rows  = $wpdb->get_results( "SELECT id, name, slug, icon, `order` FROM `{$table}` ORDER BY `order` ASC, name ASC" );
    $cats  = array();
    foreach ( (array) $rows as $r ) {
        $cats[] = array(
            'id'   => (int)    $r->id,
            'name' => (string) $r->name,
            'slug' => (string) $r->slug,
            'icon' => (string) $r->icon,
        );
    }
    return rest_ensure_response( array( 'ok' => true, 'categories' => $cats ) );
}

function vkd_api_cat_create( $req ) {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'forbidden', 'No permitido', array( 'status' => 403 ) );
    }
    $name  = sanitize_text_field( (string) ( $req->get_param('name')  ?: '' ) );
    $icon  = sanitize_text_field( (string) ( $req->get_param('icon')  ?: '' ) );
    $order = (int) ( $req->get_param('order') ?: 0 );
    if ( ! $name ) return new WP_Error( 'missing', 'Nombre requerido', array( 'status' => 400 ) );
    $slug  = sanitize_title( $name );
    $table = $wpdb->prefix . 'vkd_categories';
    $wpdb->insert( $table, array( 'name' => $name, 'slug' => $slug, 'icon' => $icon, 'order' => $order ) );
    return rest_ensure_response( array( 'ok' => true, 'id' => (int) $wpdb->insert_id ) );
}

function vkd_api_cat_update( $req ) {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'forbidden', 'No permitido', array( 'status' => 403 ) );
    }
    $id    = (int) $req->get_param('id');
    $table = $wpdb->prefix . 'vkd_categories';
    $data  = array();
    if ( $req->get_param('name')  !== null ) { $data['name']  = sanitize_text_field( (string) $req->get_param('name') ); $data['slug'] = sanitize_title( $data['name'] ); }
    if ( $req->get_param('icon')  !== null ) $data['icon']  = sanitize_text_field( (string) $req->get_param('icon') );
    if ( $req->get_param('order') !== null ) $data['order'] = (int) $req->get_param('order');
    if ( ! $data ) return new WP_Error( 'empty', 'Sin datos', array( 'status' => 400 ) );
    $wpdb->update( $table, $data, array( 'id' => $id ) );
    return rest_ensure_response( array( 'ok' => true ) );
}

function vkd_api_cat_delete( $req ) {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'forbidden', 'No permitido', array( 'status' => 403 ) );
    }
    $id    = (int) $req->get_param('id');
    $table = $wpdb->prefix . 'vkd_categories';
    $wpdb->delete( $table, array( 'id' => $id ) );
    return rest_ensure_response( array( 'ok' => true ) );
}

/**
 * GET /vk/v1/dir/list
 * Listado público con filtros: q, category_id, city, order (asc|desc), page, per_page.
 */

/* ── Map points endpoint — all profiles with coords (no pagination) ──────── */
function vkd_api_map_points( $req ) {
    global $wpdb;
    $table = $wpdb->prefix . VKD_TABLE;
    $rows  = $wpdb->get_results(
        "SELECT user_id, name, specialty, profession, city, country, lat, lng, featured_image_id
         FROM `{$table}`
         WHERE post_id > 0
           AND approval_status = 'approved'
           AND lat  IS NOT NULL AND lat  != '' AND lat  != '0'
           AND lng  IS NOT NULL AND lng  != '' AND lng  != '0'
         ORDER BY name ASC
         LIMIT 2000"
    );
    $points = array();
    foreach ( (array) $rows as $r ) {
        $lat = (float) $r->lat;
        $lng = (float) $r->lng;
        if ( ! $lat || ! $lng ) continue;
        $points[] = array(
            'user_id' => (int)    $r->user_id,
            'name'    => (string) $r->name,
            'spec'    => (string) ( $r->specialty ?: $r->profession ),
            'loc'     => implode( ', ', array_filter( array( (string) $r->city, (string) $r->country ) ) ),
            'img'     => $r->featured_image_id ? (string) wp_get_attachment_url( (int) $r->featured_image_id ) : '',
            'lat'     => $lat,
            'lng'     => $lng,
            'url'     => vkd_profile_url( (int) $r->user_id ),
        );
    }
    return rest_ensure_response( array( 'ok' => true, 'points' => $points ) );
}

function vkd_api_list( $req ) {
    global $wpdb;
    $table = $wpdb->prefix . VKD_TABLE;

    $q           = sanitize_text_field( $req->get_param('q')           ?: '' );
    $category_id = (int) ( $req->get_param('category_id')             ?: 0 );
    $city        = sanitize_text_field( $req->get_param('city')        ?: '' );
    $order       = strtoupper( $req->get_param('order') ?: 'ASC' );
    $order       = in_array( $order, array('ASC','DESC'), true ) ? $order : 'ASC';
    $page        = max( 1, (int) ( $req->get_param('page')    ?: 1 ) );
    $per_page    = max( 1, min( 50, (int) ( $req->get_param('per_page') ?: 12 ) ) );
    $offset      = ( $page - 1 ) * $per_page;

    // Solo mostrar perfiles aprobados por el admin
    $where   = "WHERE p.name != '' AND p.approval_status = 'approved'";
    $params  = array();

    if ( $q !== '' ) {
        $like = '%' . $wpdb->esc_like( $q ) . '%';
        $where .= ' AND (p.name LIKE %s OR p.specialty LIKE %s OR p.profession LIKE %s OR p.city LIKE %s OR p.services LIKE %s)';
        $params = array_merge( $params, array( $like, $like, $like, $like, $like ) );
    }

    if ( $city !== '' ) {
        $where .= ' AND p.city = %s';
        $params[] = $city;
    }

    // Filtro por categoría — buscamos en category_ids
    if ( $category_id > 0 ) {
        $where .= ' AND FIND_IN_SET(%d, p.category_ids)';
        $params[] = $category_id;
    }

    // Total
    $count_sql = "SELECT COUNT(*) FROM `{$table}` p {$where}";
    $total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

    // Resultados
    $sql = "SELECT p.id, p.user_id, p.post_id, p.name, p.tagline, p.profession, p.specialty,
                   p.city, p.state, p.country, p.phone, p.whatsapp,
                   p.featured_image_id, p.category_ids,
                   p.gender, p.birth_year, p.availability,
                   p.rating_avg, p.rating_count, p.updated_at
            FROM `{$table}` p
            {$where}
            ORDER BY p.name {$order}
            LIMIT %d OFFSET %d";
    $full_params = array_merge( $params, array( $per_page, $offset ) );
    $rows        = $wpdb->get_results( $wpdb->prepare( $sql, $full_params ) );

    $items = array();
    foreach ( $rows as $r ) {
        $img_url = $r->featured_image_id ? (string) wp_get_attachment_image_url( (int) $r->featured_image_id, 'medium' ) : '';
        $age     = $r->birth_year > 0 ? ( (int) gmdate('Y') - (int) $r->birth_year ) : 0;
        $items[] = array(
            'user_id'      => (int)    $r->user_id,
            'post_id'      => (int)    $r->post_id,
            'name'         => (string) $r->name,
            'tagline'      => (string) $r->tagline,
            'profession'   => (string) $r->profession,
            'specialty'    => (string) $r->specialty,
            'city'         => (string) $r->city,
            'state'        => (string) $r->state,
            'country'      => (string) $r->country,
            'phone'        => (string) $r->phone,
            'whatsapp'     => (string) $r->whatsapp,
            'featured_image'=> $img_url,
            'gender'       => (string) $r->gender,
            'age'          => $age,
            'availability' => (string) $r->availability ?: 'accepting',
            'rating_avg'   => (float)  $r->rating_avg,
            'rating_count' => (int)    $r->rating_count,
            'categories'   => vkd_resolve_cats( $r->category_ids ),
            'permalink'    => vkd_profile_url( (int) $r->user_id ),
        );
    }

    return rest_ensure_response( array(
        'ok'       => true,
        'items'    => $items,
        'total'    => $total,
        'page'     => $page,
        'pages'    => ceil( $total / $per_page ),
        'per_page' => $per_page,
    ) );
}

/**
 * GET /vk/v1/dir/view/{uid}
 * Perfil público completo de un profesional.
 */
function vkd_api_view( $req ) {
    $uid = (int) $req->get_param('uid');
    if ( ! $uid ) return new WP_Error( 'invalid', 'UID requerido', array( 'status' => 400 ) );
    $row = vkd_get_record( $uid );
    if ( ! $row ) {
        return new WP_Error( 'not_found', 'Profesional no encontrado', array( 'status' => 404 ) );
    }
    // Solo perfiles aprobados son visibles públicamente
    $approval = (string) ( $row->approval_status ?? 'pending' );
    if ( $approval !== 'approved' && ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'not_found', 'Profesional no encontrado', array( 'status' => 404 ) );
    }
    $data        = vkd_row_to_api( $row );
    $data['age'] = $row->birth_year > 0 ? ( (int) gmdate('Y') - (int) $row->birth_year ) : 0;
    return rest_ensure_response( array( 'ok' => true, 'profile' => $data ) );
}

/**
 * GET /vk/v1/dir/cities
 * Lista de ciudades únicas del directorio (para filtro de ciudad).
 */
function vkd_api_cities( $req ) {
    global $wpdb;
    $table  = $wpdb->prefix . VKD_TABLE;
    $cities = $wpdb->get_col( "SELECT DISTINCT city FROM `{$table}` WHERE city != '' AND name != '' AND approval_status = 'approved' ORDER BY city ASC LIMIT 200" );
    return rest_ensure_response( array( 'ok' => true, 'cities' => $cities ?: array() ) );
}

/** Convierte array de IDs de categoría a array de objetos {id, name}. */
function vkd_resolve_cats( $ids_csv ) {
    if ( ! $ids_csv ) return array();
    global $wpdb;
    $ids   = array_values( array_filter( array_map( 'intval', explode( ',', $ids_csv ) ) ) );
    if ( ! $ids ) return array();
    $table = $wpdb->prefix . 'vkd_categories';
    $in    = implode( ',', $ids );
    $rows  = $wpdb->get_results( "SELECT id, name, icon FROM `{$table}` WHERE id IN ({$in})" );
    $out   = array();
    foreach ( (array) $rows as $r ) {
        $out[] = array( 'id' => (int) $r->id, 'name' => (string) $r->name, 'icon' => (string) $r->icon );
    }
    return $out;
}

function vkd_api_config_public( $req ) {
    return rest_ensure_response( array(
        'ok'          => true,
        'gmaps_key'   => get_option( 'vk_gmaps_api_key', '' ),
    ) );
}

function vkd_api_debug( $req ) {
    global $wpdb;
    $uid   = (int) vk_uid( $req );
    $user  = get_userdata( $uid );
    $table = $wpdb->prefix . VKD_TABLE;
    $row   = vkd_get_record( $uid );

    $out = array(
        'uid'          => $uid,
        'user_email'   => $user ? $user->user_email : '?',
        'table'        => $table,
        'table_exists' => ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ),
        'db_version'   => get_option( 'vkd_db_version', 'no instalado' ),
        'row'          => $row ? (array) $row : null,
    );

    if ( $row && $row->post_id ) {
        $post = get_post( (int) $row->post_id );
        if ( $post ) {
            $dir_keys = array(
                '_tagline','_email','_phone','_listing_status','_never_expire',
                '_address_type','_logo','_listing_img','_hide_map','_manually_set',
                '_lat','_lng','_facebook','_instagram','_social_info',
            );
            $meta_check = array();
            foreach ( $dir_keys as $k ) {
                $v = get_post_meta( $post->ID, $k, true );
                $meta_check[ $k ] = ( $v !== '' && $v !== false && $v !== null ) ? $v : '(vacío)';
            }
            $out['wp_post'] = array(
                'ID'          => $post->ID,
                'post_type'   => $post->post_type,
                'post_status' => $post->post_status,
                'post_author' => (int) $post->post_author,
                'post_title'  => $post->post_title,
                'post_name'   => $post->post_name,
                'slug_ok'     => ! empty( $post->post_name ),
                'permalink'   => (string) get_permalink( $post->ID ),
            );
            $out['directorist_meta'] = $meta_check;
        }
    }

    return rest_ensure_response( $out );
}

/* ══════════════════════════════════════════════════════════════════════════════
   ROUTING PROPIO — /directorio/{uid}/
   Independiente de Directorist, post_name y configuración de permalinks.
   URL: https://vidakushala.com/directorio/42/
══════════════════════════════════════════════════════════════════════════════ */

define( 'VKD_ROUTE_BASE', 'directorio' );

add_action( 'init', 'vkd_register_rewrite', 20 );
function vkd_register_rewrite() {
    // Formato: /directorio/nombre-del-terapeuta-42/  (nombre-slug + uid al final)
    add_rewrite_rule(
        '^' . VKD_ROUTE_BASE . '/(.+)-([0-9]+)/?$',
        'index.php?vkd_profile_uid=$2',
        'top'
    );
    $rules = get_option( 'rewrite_rules', array() );
    if ( ! isset( $rules[ '^' . VKD_ROUTE_BASE . '/(.+)-([0-9]+)/?$' ] ) ) {
        update_option( 'vkd_needs_flush', '1' );
    }
}

add_filter( 'query_vars', 'vkd_add_query_vars' );
function vkd_add_query_vars( $vars ) {
    $vars[] = 'vkd_profile_uid';
    return $vars;
}

/** URL canónica: /directorio/nombre-del-terapeuta-{uid}/ */
function vkd_profile_url( $uid ) {
    $uid = (int) $uid;
    $row = vkd_get_record( $uid );
    $name_slug = $row && $row->name
        ? sanitize_title( $row->name )
        : 'terapeuta';
    if ( get_option( 'permalink_structure' ) ) {
        return trailingslashit( home_url( '/' . VKD_ROUTE_BASE . '/' . $name_slug . '-' . $uid ) );
    }
    return add_query_arg( 'vkd_profile_uid', $uid, home_url( '/' ) );
}

/** HTML del perfil como string — usado tanto en el frontend como en filtros. */
function vkd_build_profile_html( $row ) {

    /* ── Datos básicos ─────────────────────────────────────────────────── */
    $name        = esc_html( $row->name        ?: '' );
    $tagline     = esc_html( $row->tagline     ?: '' );
    $bio         = nl2br( esc_html( $row->bio  ?: '' ) );
    $profession  = esc_html( $row->profession  ?: '' );
    $specialty   = esc_html( $row->specialty   ?: '' );
    $experience  = esc_html( $row->experience  ?: '' );
    $city        = esc_html( $row->city        ?: '' );
    $state       = esc_html( $row->state       ?: '' );
    $country     = esc_html( $row->country     ?: '' );
    $address     = esc_html( $row->address     ?: '' );
    $email       = sanitize_email( $row->email ?: '' );
    $phone       = esc_html( $row->phone       ?: '' );
    $website     = esc_url(  $row->website     ?: '' );
    $services    = esc_html( $row->services    ?: '' );
    $technologies= esc_html( $row->technologies?: '' );
    $languages   = esc_html( $row->languages   ?: '' );
    $price       = esc_html( $row->price_range ?: '' );
    $gender_raw  = (string) ( $row->gender     ?? '' );
    $birth_year  = (int)    ( $row->birth_year ?? 0  );

    // Imágenes
    $img_url  = $row->featured_image_id ? (string) wp_get_attachment_image_url( (int) $row->featured_image_id, 'large' ) : '';
    $logo_url = $row->logo_id           ? (string) wp_get_attachment_image_url( (int) $row->logo_id,           'thumbnail' ) : '';

    // WhatsApp y redes
    $wa_url = $row->whatsapp ? 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $row->whatsapp ) : '';
    $fb     = esc_url( $row->facebook  ?: '' );
    $ig     = esc_url( $row->instagram ?: '' );
    $li     = esc_url( $row->linkedin  ?: '' );
    $yt     = esc_url( $row->youtube   ?: '' );
    $tw     = esc_url( $row->twitter   ?: '' );
    $tt     = esc_url( $row->tiktok    ?: '' );

    // Categorías
    $cats = vkd_resolve_cats( $row->category_ids ?? '' );
    $cat_names = array_map( function($c){ return esc_html($c['name']); }, $cats );
    $main_cat  = $cats ? esc_html( $cats[0]['name'] ) : '';

    // Disponibilidad
    $avail_map = array(
        'accepting'     => array( 'Aceptando nuevos pacientes', '#1b5e20', 'rgba(200,230,201,.9)' ),
        'existing_only' => array( 'Solo pacientes existentes',  '#e65100', 'rgba(255,224,178,.9)' ),
        'not_available' => array( 'No disponible',              '#b71c1c', 'rgba(255,205,210,.9)' ),
    );
    $avail_key  = (string) ( $row->availability ?? 'accepting' );
    $avail      = $avail_map[ $avail_key ] ?? $avail_map['accepting'];

    // Horarios (schedule_json)
    $schedule = array();
    $sched_raw = (string) ( $row->schedule_json ?? '' );
    if ( $sched_raw ) {
        $decoded = json_decode( $sched_raw, true );
        if ( is_array( $decoded ) ) $schedule = $decoded;
    }

    // Especialidades y servicios como listas
    $spec_list = array_filter( array_map( 'trim', preg_split( '/[\n,;]+/', $specialty ) ) );
    $serv_list = array_filter( array_map( 'trim', preg_split( '/[\n,;]+/', $services ) ) );
    $tech_list = array_filter( array_map( 'trim', preg_split( '/[\n,;]+/', $technologies ) ) );
    $lang_list = array_filter( array_map( 'trim', preg_split( '/[\n,;,]+/', $languages ) ) );

    // Coordenadas
    $lat     = (string) ( $row->lat ?? '' );
    $lng     = (string) ( $row->lng ?? '' );
    $has_map = $lat && $lng && is_numeric($lat) && is_numeric($lng);
    $map_id  = 'vkd-map-' . (int) $row->user_id;

    // Dirección formateada
    $full_addr = trim( implode( ', ', array_filter( array( $address, $city, $state, $country ) ) ) );

    // Permalink del directorio (atrás)
    $dir_page = get_option('vkd_directory_page_url', '/directorio/');

    // Género
    $gender_label = array( 'male' => 'Hombre', 'female' => 'Mujer', 'other' => 'Otro' );
    $gender = isset( $gender_label[$gender_raw] ) ? $gender_label[$gender_raw] : '';

    // Edad
    $age = $birth_year ? ( (int) date('Y') - $birth_year ) . ' años' : '';

    /* ── Helper: checklist item ────────────────────────────────────────── */
    $li_html = function( $text, $color='#0088cc' ) {
        return '<li style="display:flex;align-items:flex-start;gap:.55rem;padding:.4rem 0;font-size:.88rem;color:#333;border-bottom:1px solid #f5f5f5">'
             . '<span style="flex-shrink:0;width:18px;height:18px;border-radius:50%;border:1.5px solid '.$color.';display:flex;align-items:center;justify-content:center;margin-top:.05rem">'
             . '<svg width="10" height="8" viewBox="0 0 10 8" fill="none"><path d="M1 4l2.5 2.5L9 1" stroke="'.$color.'" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
             . '</span>'
             . '<span style="line-height:1.5">'.esc_html($text).'</span></li>';
    };

    /* ═══════════════════════════════════════════════════════════════════
       CSS
    ═══════════════════════════════════════════════════════════════════ */
    $h = '<style id="vkdp-css">
*,*::before,*::after{box-sizing:border-box}
.vkdp{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1a202c;--blue:#0088cc;--green:#1b5e20;--border:#edf0f4;--bg:#f5f7fa}

/* Barra superior */
.vkdp-topbar{display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.5rem;background:#fff;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
.vkdp-back{display:inline-flex;align-items:center;gap:.45rem;color:#555;text-decoration:none!important;font-size:.87rem;font-weight:600;transition:color .15s}
.vkdp-back:hover{color:var(--blue)}
.vkdp-topbar-actions{display:flex;align-items:center;gap:.65rem}
.vkdp-tba{display:inline-flex;align-items:center;gap:.38rem;padding:.48rem 1rem;border:1.5px solid var(--border);border-radius:50px;font-size:.82rem;font-weight:700;color:#555;text-decoration:none!important;background:#fff;cursor:pointer;transition:border-color .15s,color .15s}
.vkdp-tba:hover{border-color:var(--blue);color:var(--blue)}

/* Hero */
.vkdp-hero{display:grid;grid-template-columns:1fr 1fr;gap:0;background:#fff;border-bottom:1px solid var(--border)}
.vkdp-hero-left{padding:2rem 2.5rem 2rem 2.5rem;overflow-y:auto}
.vkdp-hero-right{overflow:hidden;min-height:480px;max-height:640px}
.vkdp-hero-img{width:100%;height:100%;object-fit:cover;object-position:center top;display:block}
.vkdp-hero-img-ph{width:100%;height:100%;min-height:480px;background:linear-gradient(135deg,#1a1a2e,#16213e,#0f3460);display:flex;align-items:center;justify-content:center;font-size:8rem;color:rgba(255,255,255,.15)}

/* Logo + nombre */
.vkdp-id{display:flex;align-items:center;gap:1.1rem;margin-bottom:1.2rem}
.vkdp-logo{width:86px;height:86px;border-radius:50%;object-fit:cover;border:3px solid var(--border);flex-shrink:0}
.vkdp-logo-ph{width:86px;height:86px;border-radius:50%;background:linear-gradient(135deg,#1a3a5c,#0088cc);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;flex-shrink:0}
.vkdp-name{font-size:1.85rem;font-weight:800;margin:0 0 .15rem;line-height:1.15;color:#0d1b2a}
.vkdp-spec-label{font-size:1rem;font-weight:600;color:var(--blue);margin:0 0 .55rem}
.vkdp-avail{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .75rem;border-radius:20px;font-size:.72rem;font-weight:700}

/* Dirección y teléfono */
.vkdp-contact-row{display:flex;flex-wrap:wrap;align-items:flex-start;gap:1.25rem 2.5rem;margin:.85rem 0 1rem}
.vkdp-cr-item{display:flex;align-items:flex-start;gap:.55rem;font-size:.87rem;color:#444}
.vkdp-cr-item svg{flex-shrink:0;margin-top:.1rem}

/* Botones de acción */
.vkdp-btns{display:flex;flex-wrap:wrap;gap:.6rem;margin:1rem 0}
.vkdp-btn{display:inline-flex;align-items:center;gap:.45rem;padding:.65rem 1.3rem;border-radius:10px;font-weight:700;font-size:.87rem;text-decoration:none!important;border:1.5px solid transparent;transition:opacity .15s,background .15s;cursor:pointer;line-height:1}
.vkdp-btn:hover{opacity:.85}
.vkdp-btn-wa{background:#25D366;color:#fff!important;border-color:#25D366}
.vkdp-btn-call{background:#fff;color:#333!important;border-color:#ddd}
.vkdp-btn-call:hover{border-color:var(--blue);color:var(--blue)!important;opacity:1}

/* Contacto texto */
.vkdp-contact-text{display:flex;flex-direction:column;gap:.45rem;margin:.25rem 0 1rem}
.vkdp-ct{display:flex;align-items:center;gap:.6rem;font-size:.87rem;color:#444;text-decoration:none}
.vkdp-ct:hover{color:var(--blue)}

/* Redes sociales */
.vkdp-soc-title{font-size:.82rem;font-weight:700;color:#666;margin:.9rem 0 .55rem}
.vkdp-soc{display:flex;gap:.55rem;flex-wrap:wrap}
.vkdp-soc-btn{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff!important;text-decoration:none!important;font-size:1rem;transition:transform .15s,opacity .15s;flex-shrink:0}
.vkdp-soc-btn:hover{transform:scale(1.08);opacity:.9}
.vkdp-soc-fb{background:#1877f2}
.vkdp-soc-ig{background:radial-gradient(circle at 30% 107%,#fdf497 0,#fd5949 45%,#d6249f 60%,#285aeb 90%)}
.vkdp-soc-li{background:#0a66c2}
.vkdp-soc-yt{background:#ff0000}
.vkdp-soc-tw{background:#1da1f2}
.vkdp-soc-tt{background:#010101}

/* Bio */
.vkdp-bio{font-size:.9rem;line-height:1.8;color:#4a5568;margin:1rem 0 0}

/* ── Secciones de contenido ────────────────────────── */
.vkdp-body{max-width:1200px;margin:0 auto;padding:1.75rem 0rem}
.vkdp-row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.25rem;margin-bottom:1.25rem}
.vkdp-row2{display:grid;grid-template-columns:1.6fr 1fr;gap:1.25rem;margin-bottom:1.25rem}
.vkdp-sec{background:#fff;border-radius:14px;border:1px solid var(--border);padding:1.5rem 1.75rem}
.vkdp-sec-full{background:#fff;border-radius:14px;border:1px solid var(--border);padding:1.5rem 1.75rem;margin-bottom:1.25rem}
.vkdp-sh{display:flex;align-items:center;gap:.65rem;margin-bottom:1.1rem;padding-bottom:.75rem;border-bottom:1px solid var(--border)}
.vkdp-sh-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.vkdp-sh-title{font-size:.95rem;font-weight:800;color:#0d1b2a;margin:0}
.vkdp-checklist{list-style:none;margin:0;padding:0}
.vkdp-checklist li:last-child{border-bottom:none!important}

/* Info adicional */
.vkdp-info-item{display:flex;align-items:flex-start;gap:.6rem;padding:.65rem 0;border-bottom:1px solid #f5f5f5}
.vkdp-info-item:last-child{border-bottom:none}
.vkdp-ii-icon{width:32px;height:32px;border-radius:8px;background:#f0f7ff;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.vkdp-ii-label{font-size:.72rem;color:#9ca3af;font-weight:600;margin:0 0 .12rem}
.vkdp-ii-val{font-size:.9rem;color:#111;font-weight:700;margin:0}

/* Horarios */
.vkdp-sched-row{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid #f5f5f5;font-size:.87rem}
.vkdp-sched-row:last-child{border-bottom:none}
.vkdp-sched-day{font-weight:600;color:#374151;min-width:85px}
.vkdp-sched-time{color:#4b5563}
.vkdp-sched-closed{padding:.18rem .7rem;background:#fee2e2;color:#dc2626;border-radius:20px;font-size:.73rem;font-weight:700}
.vkdp-sched-note{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:.85rem 1rem;margin-top:1rem;font-size:.82rem;color:#1e40af;display:flex;gap:.6rem;align-items:flex-start}

/* Servicios 2 columnas */
.vkdp-serv-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 1.5rem}

/* Mapa */
.vkdp-map-sec{background:#fff;border-radius:14px;border:1px solid var(--border);padding:1.5rem 1.75rem;margin-bottom:1.25rem}
.vkdp-map-container{border-radius:10px;overflow:hidden;border:1px solid var(--border);margin:1rem 0 .75rem;height:300px}
.vkdp-map-footer{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem}
.vkdp-map-addr{display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#555}
.vkdp-gmaps{display:inline-flex;align-items:center;gap:.38rem;color:var(--blue);font-size:.83rem;font-weight:700;text-decoration:none!important}
.vkdp-gmaps:hover{text-decoration:underline!important}

/* Responsive */
@media(max-width:900px){
  .vkdp-hero{grid-template-columns:1fr;grid-template-rows:auto 340px}
  .vkdp-hero-right{min-height:340px;max-height:340px}
  .vkdp-hero-left{padding:1.5rem}
  .vkdp-row3{grid-template-columns:1fr 1fr}
  .vkdp-row2{grid-template-columns:1fr}
}
@media(max-width:600px){
  .vkdp-hero{grid-template-rows:auto 280px}
  .vkdp-hero-right{min-height:280px;max-height:280px}
  .vkdp-hero-left{padding:1.25rem}
  .vkdp-body{padding:1rem}
  .vkdp-row3,.vkdp-row2{grid-template-columns:1fr}
  .vkdp-serv-grid{grid-template-columns:1fr}
  .vkdp-name{font-size:1.45rem}
  .vkdp-topbar{padding:.7rem 1rem}
  .vkdp-sec,.vkdp-sec-full,.vkdp-map-sec{padding:1.1rem 1.2rem}
}
</style>';

    /* ═══════════════════════════════════════════════════════════════════
       BARRA SUPERIOR
    ═══════════════════════════════════════════════════════════════════ */
    $h .= '<div class="vkdp">';
    $h .= '<nav class="vkdp-topbar">';
    $h .= '<a href="' . esc_url($dir_page) . '" class="vkdp-back">'
        . '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        . ' Volver a la lista</a>';
    $h .= '<div class="vkdp-topbar-actions">';
    $h .= '<button class="vkdp-tba" onclick="navigator.share&&navigator.share({title:\''.esc_js($name).'\',url:window.location.href})">'
        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>'
        . ' Compartir</button>';
    $h .= '</div></nav>';

    /* ═══════════════════════════════════════════════════════════════════
       HERO: Izquierda info + Derecha imagen
    ═══════════════════════════════════════════════════════════════════ */
    $h .= '<div class="vkdp-hero">';

    // Columna izquierda
    $h .= '<div class="vkdp-hero-left">';

    // Logo + nombre + especialidad
    $h .= '<div class="vkdp-id">';
    if ( $logo_url ) {
        $h .= '<img src="' . esc_url($logo_url) . '" alt="' . $name . '" class="vkdp-logo">';
    } else {
        $initial = mb_strtoupper( mb_substr( $row->name ?: '?', 0, 1 ) );
        $h .= '<div class="vkdp-logo-ph">' . $initial . '</div>';
    }
    $h .= '<div>';
    $h .= '<h1 class="vkdp-name">' . $name . '</h1>';
    $spec_display = $specialty ?: $profession ?: $main_cat;
    if ( $spec_display ) $h .= '<p class="vkdp-spec-label">' . $spec_display . '</p>';
    $h .= '<span class="vkdp-avail" style="color:' . esc_attr($avail[1]) . ';background:' . esc_attr($avail[2]) . '">&#10003; ' . esc_html($avail[0]) . '</span>';
    $h .= '</div></div>';

    // Dirección y teléfono
    if ( $full_addr || $phone ) {
        $h .= '<div class="vkdp-contact-row">';
        if ( $full_addr ) {
            $h .= '<div class="vkdp-cr-item">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0088cc" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>'
                . '<span>' . $full_addr . '</span></div>';
        }
        if ( $phone ) {
            $h .= '<div class="vkdp-cr-item">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0088cc" stroke-width="2.2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.1 19.79 19.79 0 0 1 1.62 4.55a2 2 0 0 1 1.99-2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.06 6.06l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'
                . '<span>' . $phone . '</span></div>';
        }
        $h .= '</div>';
    }

    // Botones WhatsApp y Llamar
    if ( $wa_url || $phone ) {
        $h .= '<div class="vkdp-btns">';
        if ( $wa_url ) {
            $h .= '<a href="' . esc_url($wa_url) . '" target="_blank" rel="noopener" class="vkdp-btn vkdp-btn-wa">'
                . '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>'
                . ' WhatsApp</a>';
        }
        if ( $phone ) {
            $ph_clean = preg_replace('/[^0-9+]/', '', $phone);
            $h .= '<a href="tel:' . esc_attr($ph_clean) . '" class="vkdp-btn vkdp-btn-call">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.1 19.79 19.79 0 0 1 1.62 4.55a2 2 0 0 1 1.99-2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.06 6.06l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'
                . ' Llamar</a>';
        }
        $h .= '</div>';
    }

    // Email y Sitio web
    if ( $email || $website ) {
        $h .= '<div class="vkdp-contact-text">';
        if ( $email ) {
            $h .= '<a href="mailto:' . esc_attr($email) . '" class="vkdp-ct">'
                . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>'
                . esc_html($email) . '</a>';
        }
        if ( $website ) {
            $display_web = preg_replace('#^https?://#', '', $website);
            $h .= '<a href="' . esc_url($website) . '" target="_blank" rel="noopener" class="vkdp-ct">'
                . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>'
                . esc_html($display_web) . '</a>';
        }
        $h .= '</div>';
    }

    // Redes sociales
    $soc_links = array(
        array( $fb, 'vkdp-soc-fb', '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>' ),
        array( $ig, 'vkdp-soc-ig', '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>' ),
        array( $li, 'vkdp-soc-li', '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>' ),
        array( $yt, 'vkdp-soc-yt', '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75,15.02 15.5,12 9.75,8.98 9.75,15.02" fill="white"/></svg>' ),
        array( $tw, 'vkdp-soc-tw', '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>' ),
        array( $tt, 'vkdp-soc-tt', '<svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V9a8.28 8.28 0 0 0 4.84 1.54V7.1a4.85 4.85 0 0 1-1.07-.41z"/></svg>' ),
    );
    $has_soc = false;
    foreach ( $soc_links as $s ) { if ( $s[0] ) { $has_soc = true; break; } }
    if ( $has_soc ) {
        $h .= '<p class="vkdp-soc-title">Sígueme en redes sociales</p><div class="vkdp-soc">';
        foreach ( $soc_links as $s ) {
            if ( $s[0] ) $h .= '<a href="' . $s[0] . '" target="_blank" rel="noopener" class="vkdp-soc-btn ' . $s[1] . '">' . $s[2] . '</a>';
        }
        $h .= '</div>';
    }

    // Bio
    if ( $bio ) $h .= '<p class="vkdp-bio">' . $bio . '</p>';

    $h .= '</div>'; // .vkdp-hero-left

    // Columna derecha — imagen
    $h .= '<div class="vkdp-hero-right">';
    if ( $img_url ) {
        $h .= '<img src="' . esc_url($img_url) . '" alt="' . $name . '" class="vkdp-hero-img">';
    } else {
        $h .= '<div class="vkdp-hero-img-ph">&#128100;</div>';
    }
    $h .= '</div>';

    $h .= '</div>'; // .vkdp-hero

    /* ═══════════════════════════════════════════════════════════════════
       CUERPO: Especialidades + Tecnología + Info adicional
    ═══════════════════════════════════════════════════════════════════ */
    $h .= '<div class="vkdp-body">';

    // Fila 3 columnas — solo si hay contenido
    $has_specs = !empty($cat_names) || !empty($spec_list);
    $has_tech  = !empty($tech_list);
    $has_info3 = $experience || $gender || $age || !empty($lang_list) || $price;

    if ( $has_specs || $has_tech || $has_info3 ) {
        $h .= '<div class="vkdp-row3">';

        // Especialidades
        if ( $has_specs ) {
            $h .= '<div class="vkdp-sec">';
            $h .= '<div class="vkdp-sh"><div class="vkdp-sh-icon" style="background:#e8f5f9">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#00838f" stroke-width="2.2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>'
                . '</div><h3 class="vkdp-sh-title">Especialidades</h3></div>';
            $h .= '<ul class="vkdp-checklist">';
            foreach ( $cat_names as $item ) $h .= call_user_func( $li_html, $item, '#00838f' );
            foreach ( $spec_list as $item ) { if ( ! in_array( $item, $cat_names ) ) $h .= call_user_func( $li_html, $item, '#00838f' ); }
            if ( empty($cat_names) && empty($spec_list) ) $h .= '<li style="font-size:.85rem;color:#9ca3af;padding:.5rem 0">No especificado</li>';
            $h .= '</ul></div>';
        }

        // Tecnología / Categorías
        if ( $has_tech ) {
            $h .= '<div class="vkdp-sec">';
            $h .= '<div class="vkdp-sh"><div class="vkdp-sh-icon" style="background:#eef2ff">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2.2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M16.24 7.76a6 6 0 0 1 0 8.49M4.93 19.07a10 10 0 0 1 0-14.14M7.76 16.24a6 6 0 0 1 0-8.49"/></svg>'
                . '</div><h3 class="vkdp-sh-title">Tecnología</h3></div>';
            $h .= '<ul class="vkdp-checklist">';
            foreach ( $tech_list as $item ) $h .= call_user_func( $li_html, $item, '#4f46e5' );
            $h .= '</ul></div>';
        }

        // Información adicional
        if ( $has_info3 ) {
            $h .= '<div class="vkdp-sec">';
            $h .= '<div class="vkdp-sh"><div class="vkdp-sh-icon" style="background:#f0fdf4">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                . '</div><h3 class="vkdp-sh-title">Información adicional</h3></div>';
            $info_items = array();
            if ( $experience ) $info_items[] = array( '&#x23F3;',  'Años de experiencia', $experience );
            if ( $gender )     $info_items[] = array( '&#x1F464;', 'Género',              $gender );
            if ( $age )        $info_items[] = array( '&#x1F382;', 'Edad',                $age );
            if ( !empty($lang_list) ) $info_items[] = array( '&#x1F30D;', 'Idiomas', implode(', ', $lang_list) );
            if ( $price )      $info_items[] = array( '&#x1F4B0;', 'Precio',              $price );
            foreach ( $info_items as $it ) {
                $h .= '<div class="vkdp-info-item">'
                    . '<div class="vkdp-ii-icon"><span style="font-size:1rem">' . $it[0] . '</span></div>'
                    . '<div><p class="vkdp-ii-label">' . esc_html($it[1]) . '</p><p class="vkdp-ii-val">' . esc_html($it[2]) . '</p></div>'
                    . '</div>';
            }
            $h .= '</div>';
        }

        $h .= '</div>'; // .vkdp-row3
    }

    /* ── Fila 2 col: Tratamientos + Horarios ──────────────────────────── */
    $has_serv  = !empty($serv_list);
    $has_sched = !empty($schedule);

    if ( $has_serv || $has_sched ) {
        $h .= '<div class="vkdp-row2">';

        // Tratamientos (izquierda)
        if ( $has_serv ) {
            $h .= '<div class="vkdp-sec">';
            $h .= '<div class="vkdp-sh"><div class="vkdp-sh-icon" style="background:#fff0f3">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e91e63" stroke-width="2.2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>'
                . '</div><h3 class="vkdp-sh-title">Tratamientos</h3></div>';
            // Dividir en 2 columnas
            $half = (int) ceil( count($serv_list) / 2 );
            $col1 = array_slice( $serv_list, 0, $half );
            $col2 = array_slice( $serv_list, $half );
            $h .= '<div class="vkdp-serv-grid">';
            $h .= '<ul class="vkdp-checklist">';
            foreach ( $col1 as $item ) $h .= call_user_func( $li_html, $item, '#e91e63' );
            $h .= '</ul>';
            if ( !empty($col2) ) {
                $h .= '<ul class="vkdp-checklist">';
                foreach ( $col2 as $item ) $h .= call_user_func( $li_html, $item, '#e91e63' );
                $h .= '</ul>';
            }
            $h .= '</div></div>';
        } else {
            $h .= '<div></div>'; // placeholder
        }

        // Horarios (derecha)
        if ( $has_sched ) {
            $day_names = array(
                'lunes'     => 'Lunes',
                'martes'    => 'Martes',
                'miercoles' => 'Miércoles',
                'jueves'    => 'Jueves',
                'viernes'   => 'Viernes',
                'sabado'    => 'Sábado',
                'domingo'   => 'Domingo',
            );
            $h .= '<div class="vkdp-sec">';
            $h .= '<div class="vkdp-sh"><div class="vkdp-sh-icon" style="background:#fef9ec">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>'
                . '</div><h3 class="vkdp-sh-title">Horarios de atención</h3></div>';
            foreach ( $day_names as $key => $label ) {
                if ( ! isset($schedule[$key]) ) continue;
                $day = $schedule[$key];
                $closed = !empty($day['closed']);
                $h .= '<div class="vkdp-sched-row">'
                    . '<span class="vkdp-sched-day">' . esc_html($label) . '</span>';
                if ( $closed ) {
                    $h .= '<span class="vkdp-sched-closed">Cerrado</span>';
                } else {
                    $open  = esc_html( $day['open']  ?? '08:00' );
                    $close = esc_html( $day['close'] ?? '18:00' );
                    $h .= '<span class="vkdp-sched-time">' . $open . ' am – ' . $close . ' pm</span>';
                }
                $h .= '</div>';
            }
            $h .= '<div class="vkdp-sched-note">'
                . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1e40af" stroke-width="2" style="flex-shrink:0;margin-top:.1rem"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'
                . '<span>Atención con previa cita.</span>'
                . '</div>';
            $h .= '</div>';
        }

        $h .= '</div>'; // .vkdp-row2
    }

    /* ── Mapa ─────────────────────────────────────────────────────────── */
    if ( $has_map ) {
        $lat_js  = esc_js( $lat );
        $lng_js  = esc_js( $lng );
        $addr_js = esc_js( $full_addr ?: $name );
        $gmaps   = 'https://maps.google.com/?q=' . urlencode( $full_addr ?: "$lat,$lng" );

        $h .= '<div class="vkdp-map-sec">';
        $h .= '<div class="vkdp-sh" style="margin-bottom:.75rem;padding-bottom:.75rem">'
            . '<div class="vkdp-sh-icon" style="background:#eef6ff">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0088cc" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>'
            . '</div><h3 class="vkdp-sh-title">Ubicación</h3></div>';

        $h .= '<div class="vkdp-map-container"><div id="' . esc_attr($map_id) . '" style="height:100%"></div></div>';

        $h .= '<div class="vkdp-map-footer">';
        if ( $full_addr ) {
            $h .= '<div class="vkdp-map-addr">'
                . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>'
                . esc_html($full_addr) . '</div>';
        }
        $h .= '<a href="' . esc_url($gmaps) . '" target="_blank" rel="noopener" class="vkdp-gmaps">'
            . 'Ver en Google Maps '
            . '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15,3 21,3 21,9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>'
            . '</a>';
        $h .= '</div></div>';

        // Script del mapa
        $h .= '<script>'
            . '(function(){'
            . 'function initMap(){'
            . '  if(typeof L==="undefined"){setTimeout(initMap,400);return;}'
            . '  var m=L.map("' . esc_js($map_id) . '",{scrollWheelZoom:false,zoomControl:true}).setView([' . $lat_js . ',' . $lng_js . '],15);'
            . '  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"&copy; OpenStreetMap",maxZoom:19}).addTo(m);'
            . '  var icon=L.divIcon({html:\'<svg width="32" height="42" viewBox="0 0 32 42" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16 0C7.163 0 0 7.163 0 16c0 10.627 14.4 24.96 15.027 25.547a1.333 1.333 0 0 0 1.946 0C17.6 40.96 32 26.627 32 16 32 7.163 24.837 0 16 0z" fill="#e91e63"/><circle cx="16" cy="16" r="7" fill="white"/></svg>\',className:"",iconSize:[32,42],iconAnchor:[16,42]});'
            . '  L.marker([' . $lat_js . ',' . $lng_js . '],{icon:icon}).addTo(m).bindPopup("<strong>' . esc_js($name) . '</strong><br><small>' . esc_js($full_addr) . '</small>",{maxWidth:220}).openPopup();'
            . '  setTimeout(function(){m.invalidateSize();},200);'
            . '}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",initMap);}else{initMap();}'
            . '})();'
            . '</script>';
    }

    $h .= '</div>'; // .vkdp-body
    $h .= '</div>'; // .vkdp

    return $h;
}

add_action( 'template_redirect', 'vkd_profile_template_redirect' );
function vkd_profile_template_redirect() {
    $uid = (int) get_query_var( 'vkd_profile_uid' );

    // Fallback: si los rewrite rules no están flusheados, parsear la URI directamente
    if ( ! $uid ) {
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
        $base = preg_quote( VKD_ROUTE_BASE, '#' );
        if ( preg_match( '#^/' . $base . '/.+-(\d+)/?$#', $uri, $m ) ) {
            $uid = (int) $m[1];
        }
    }

    if ( ! $uid ) return;

    $row = vkd_get_record( $uid );

    // Perfil no encontrado o no aprobado → 404 para público (admin puede ver)
    $approval = $row ? (string) ( $row->approval_status ?? 'pending' ) : '';
    if ( ! $row || ( $approval !== 'approved' && ! current_user_can( 'manage_options' ) ) ) {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
        include get_404_template();
        exit;
    }

    // Limpiar estado 404 para que Astra use el layout de página normal
    global $wp_query;
    $wp_query->is_404        = false;
    $wp_query->is_singular   = true;
    $wp_query->is_page       = true;
    $wp_query->is_archive    = false;
    $wp_query->is_home       = false;
    $wp_query->is_front_page = false;

    $name      = $row->name ?: 'Perfil profesional';
    $site_name = get_bloginfo( 'name' );

    // SEO title
    add_filter( 'pre_get_document_title', function() use ( $name, $site_name ) {
        return esc_html( $name ) . ' | ' . esc_html( $site_name );
    } );

    // Sin sidebar para que el perfil ocupe todo el ancho
    add_filter( 'astra_page_layout',         function() { return 'no-sidebar'; } );
    add_filter( 'astra_get_sidebar',         '__return_false' );
    add_filter( 'astra_content_layout_flag', function() { return 'no-sidebar'; } );

    // Leaflet para el mapa de ubicación
    add_action( 'wp_head', function() {
        echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">' . "\n";
        echo '<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>' . "\n";
    }, 5 );

    // CSS inyectado en wp_head (que se ejecuta dentro de get_header())
    add_action( 'wp_head', function() {
        ?>
<style id="vkd-profile-css">
.site-content{background:#f2f0fe}
/* Layout wrapper para la vista de perfil */
.vkd-outer{width:100%;margin:0;padding:0}
.vkd-outer .entry-content,.vkd-outer .ast-container{max-width:none!important;padding:0!important;margin:0!important}
/* Forzar ancho completo en Astra */
.vkd-outer .entry-content{width:100%}
</style>
        <?php
    }, 99 );

    status_header( 200 );

    // Header del tema Astra — incluye DOCTYPE, <html>, <head>, wp_head(), menú de navegación
    get_header();

    // .vkd-outer envuelve todo el contenido del perfil (ancho completo)
    echo '<div class="vkd-outer">';
    echo vkd_build_profile_html( $row );
    echo '</div>';

    // Footer del tema Astra — incluye wp_footer(), </body>, </html>
    get_footer();
    exit;
}

/* ══════════════════════════════════════════════════════════════════════════════
   DISPLAY EN WORDPRESS
   Inyecta datos adicionales (servicios, WhatsApp, experiencia) en la vista
   individual del listing at_biz_dir, complementando lo que Directorist muestra.
══════════════════════════════════════════════════════════════════════════════ */

add_filter( 'the_content', 'vkd_inject_extra_content', 20 );

function vkd_inject_extra_content( $content ) {
    if ( is_admin() || ! is_singular( VKD_CPT ) ) return $content;
    $post_id = (int) get_the_ID();
    if ( ! $post_id ) return $content;

    $row = vkd_get_record_by_post( $post_id );
    if ( ! $row ) return $content;

    $items  = array();
    $parts  = array_filter( array( (string) $row->profession, (string) $row->specialty ) );
    if ( $parts ) $items[] = array( '🎓', 'Especialidad', implode( ' · ', $parts ) );
    if ( $row->experience ) $items[] = array( '⏳', 'Experiencia',      (string) $row->experience );
    if ( $row->price_range ) $items[] = array( '💰', 'Rango de precios', (string) $row->price_range );

    if ( ! $items && ! $row->services && ! $row->whatsapp ) return $content;

    $html = '<div style="margin:24px 0;padding:20px;border:1px solid #e0e0e0;border-radius:10px;background:#f9f9f9">';
    foreach ( $items as $it ) {
        $html .= '<div style="margin-bottom:8px;font-size:.93em">'
               . $it[0] . ' <strong>' . esc_html( $it[1] ) . ':</strong> '
               . esc_html( $it[2] ) . '</div>';
    }
    if ( $row->services ) {
        $html .= '<div style="margin-top:10px;font-size:.9em">'
               . '🛠 <strong>Servicios:</strong><br><span style="white-space:pre-line">'
               . esc_html( (string) $row->services ) . '</span></div>';
    }
    if ( $row->whatsapp ) {
        $wa    = 'https://wa.me/' . preg_replace( '/[^0-9]/', '', (string) $row->whatsapp );
        $html .= '<div style="margin-top:14px">'
               . '<a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener" '
               . 'style="display:inline-flex;align-items:center;gap:8px;background:#25D366;'
               . 'color:#fff;text-decoration:none;padding:9px 18px;border-radius:7px;font-weight:600;font-size:.9em">'
               . '📲 Contactar por WhatsApp</a></div>';
    }
    $html .= '</div>';

    return $content . $html;
}

/* ══════════════════════════════════════════════════════════════════════════════
   SHORTCODES — Directorio en WordPress
   [vkd_directorio]  — Listado completo con filtros
   [vkd_perfil uid="123"]  — Ficha individual
══════════════════════════════════════════════════════════════════════════════ */

add_shortcode( 'vkd_directorio', 'vkd_shortcode_directorio' );
add_shortcode( 'vkd_perfil',     'vkd_shortcode_perfil' );

function vkd_shortcode_directorio( $atts ) {
    $api = rest_url( 'vk/v1' );

    // Registrar el JS en wp_footer para evitar que filtros de contenido lo corrompan
    static $vkd_js_added = false;
    if ( ! $vkd_js_added ) {
        $vkd_js_added = true;
        $api_js = esc_js( $api );
        add_action( 'wp_footer', function() use ( $api_js ) {
            ?>
<script id="vkd-dir-js">
(function(){
var API='<?php echo $api_js; ?>';
var _page=1,_pages=1,_total=0,_ftimer=null,_mapInst=null;
function XS(s){return(s+'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function avail(k){var m={accepting:{l:'Aceptando nuevos pacientes',c:'#1b5e20',bg:'rgba(200,230,201,.88)',icon:'&#10003;'},existing_only:{l:'Solo pacientes existentes',c:'#e65100',bg:'rgba(255,224,178,.88)',icon:'&#8635;'},not_available:{l:'No disponible',c:'#b71c1c',bg:'rgba(255,205,210,.88)',icon:'&#10005;'}};return m[k]||m.accepting;}
function starsHtml(avg,n){if(!n)return '<span class="vkd-stars-count">Sin rese&ntilde;as</span>';avg=Math.round(avg*2)/2;var h='<span class="vkd-stars-fill">';for(var i=1;i<=5;i++)h+=avg>=i?'&#9733;':(avg>=i-.5?'&#11944;':'&#9734;');return h+'</span><span class="vkd-stars-count">'+n+' rese&ntilde;a'+(n!==1?'s':'')+'</span>';}
function ageStr(y){return y?(new Date().getFullYear()-y)+' a&ntilde;os':'';}
function genderStr(g){return{male:'Hombre',female:'Mujer',other:'Otro'}[g]||'';}
function renderCard(p){var av=avail(p.availability);var img=p.featured_image?'<img class="vkd-card-img" src="'+XS(p.featured_image)+'" loading="lazy" alt="'+XS(p.name)+'">':'<div class="vkd-card-img-ph">&#128100;</div>';var spec=p.specialty||p.profession||'';var parts=[spec,genderStr(p.gender),ageStr(p.birth_year)].filter(Boolean);var loc=[p.city,p.country].filter(Boolean).join(', ');return '<div class="vkd-card" onclick="location.href=\''+XS(p.permalink)+'\'">'+'<div class="vkd-card-img-wrap">'+img+'<div class="vkd-card-overlay"></div>'+'<div class="vkd-card-fire">&#128293;</div>'+'<button class="vkd-card-fav" onclick="event.stopPropagation()" title="Guardar">&#9825;</button>'+'<div class="vkd-avail-badge" style="color:'+av.c+';background:'+av.bg+'">'+av.icon+' '+XS(av.l)+'</div>'+'</div>'+'<div class="vkd-card-body">'+'<p class="vkd-card-name">'+XS(p.name)+'</p>'+(parts.length?'<p class="vkd-card-spec">'+parts.map(function(s,i){return(i?'<span class="vkd-sep">&bull;</span>':'')+XS(s);}).join('')+'</p>':'')+(loc?'<p class="vkd-card-loc"><i class="fas fa-location-dot"></i>'+XS(loc)+'</p>':'')+(p.phone?'<p class="vkd-card-phone"><i class="fas fa-phone"></i>'+XS(p.phone)+'</p>':'')+'<div class="vkd-card-footer">'+'<div class="vkd-stars-wrap">'+starsHtml(p.rating_avg,p.rating_count)+'</div>'+'<a href="'+XS(p.permalink)+'" class="vkd-cta" onclick="event.stopPropagation()">Agendar cita</a>'+'</div></div></div>';}
function load(pg){pg=pg||1;var q=document.getElementById('vkd-q').value.trim();var cat=document.getElementById('vkd-cat').value;var city=document.getElementById('vkd-city').value;var order=document.getElementById('vkd-order').value;var wrap=document.getElementById('vkd-results');if(pg===1)wrap.innerHTML='<p style="text-align:center;padding:3rem;color:#999"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:#0088cc"></i></p>';var url=API+'/dir/list?page='+pg+'&per_page=12&order='+encodeURIComponent(order);if(q)url+='&q='+encodeURIComponent(q);if(cat)url+='&category_id='+encodeURIComponent(cat);if(city)url+='&city='+encodeURIComponent(city);fetch(url).then(function(r){return r.json();}).then(function(res){_page=res.page||1;_pages=res.pages||1;_total=res.total||0;var meta=document.getElementById('vkd-meta');if(meta)meta.textContent=_total+' profesional'+(_total!==1?'es':'')+' encontrado'+(_total!==1?'s':'');if(!res.items||!res.items.length){wrap.innerHTML='<div class="vkd-empty"><i class="fas fa-magnifying-glass"></i><p>No se encontraron profesionales.</p></div>';return;}var html='<div class="vkd-grid">';res.items.forEach(function(p){html+=renderCard(p);});html+='</div>';if(_pages>1){html+='<div class="vkd-pager">';if(_page>1)html+='<button onclick="vkdLoad('+(_page-1)+')">&#8249; Anterior</button>';html+='<span>P&aacute;gina '+_page+' / '+_pages+'</span>';if(_page<_pages)html+='<button onclick="vkdLoad('+(_page+1)+')">Siguiente &#8250;</button>';html+='</div>';}wrap.innerHTML=html;}).catch(function(err){console.error('[vkd] Error:',err);wrap.innerHTML='<div class="vkd-empty"><i class="fas fa-triangle-exclamation"></i><p>Error al cargar el directorio.</p></div>';});}
window.vkdLoad=load;
function buildMap(mapDiv){if(_mapInst){try{_mapInst.remove();}catch(e){}_mapInst=null;}mapDiv.innerHTML='';var sp=document.createElement('div');sp.id='vkd-map-spin';sp.style.cssText='position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:.75rem;background:rgba(255,255,255,.85);z-index:9999';sp.innerHTML='<i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#0088cc"></i><p style="margin:0;font-size:.85rem;color:#718096">Cargando mapa...</p>';mapDiv.style.position='relative';mapDiv.appendChild(sp);_mapInst=L.map(mapDiv,{scrollWheelZoom:false,zoomControl:true}).setView([20,-99],4);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',maxZoom:19}).addTo(_mapInst);fetch(API+'/dir/map-points').then(function(r){return r.json();}).then(function(res){var spin=document.getElementById('vkd-map-spin');if(spin)spin.remove();var pts=res.points||[];if(!pts.length)return;var bounds=[];pts.forEach(function(p){bounds.push([p.lat,p.lng]);var init=(p.name||'?').split(' ').slice(0,2).map(function(w){return w[0]||'';}).join('').toUpperCase();var inner=p.img?'<img src="'+XS(p.img)+'" style="width:100%;height:100%;object-fit:cover;display:block">':'<span style="font-weight:800;font-size:.85rem;color:#fff;line-height:1">'+XS(init)+'</span>';var icon=L.divIcon({html:'<div style="width:46px;height:46px;border-radius:50%;border:3px solid #0088cc;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#0088cc;box-shadow:0 3px 14px rgba(0,0,0,.28)">'+inner+'</div><div style="width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;border-top:8px solid #0088cc;margin:-1px auto 0;display:block;width:12px"></div>',className:'',iconSize:[46,54],iconAnchor:[23,54]});var popup='<div style="font-family:-apple-system,BlinkMacSystemFont,sans-serif">'+(p.img?'<img src="'+XS(p.img)+'" style="width:100%;height:110px;object-fit:cover;display:block">':'<div style="height:70px;background:linear-gradient(135deg,#1a1a2e,#0f3460);display:flex;align-items:center;justify-content:center;font-size:2.2rem">&#128100;</div>')+'<div style="padding:.75rem .9rem .85rem">'+'<p style="font-size:.9rem;font-weight:800;color:#1a202c;margin:0 0 .2rem">'+XS(p.name)+'</p>'+(p.spec?'<p style="font-size:.75rem;color:#0088cc;font-weight:600;margin:0 0 .25rem">'+XS(p.spec)+'</p>':'')+(p.loc?'<p style="font-size:.73rem;color:#718096;margin:0 0 .6rem">&#128205; '+XS(p.loc)+'</p>':'')+'<a href="'+XS(p.url)+'" style="display:block;text-align:center;padding:.42rem .75rem;background:#0088cc;color:#fff;border-radius:9px;font-size:.76rem;font-weight:700;text-decoration:none">Ver perfil completo &rarr;</a>'+'</div></div>';L.marker([p.lat,p.lng],{icon:icon}).addTo(_mapInst).bindPopup(popup,{maxWidth:240,className:'vkd-map-popup'});});if(bounds.length===1)_mapInst.setView(bounds[0],13);else if(bounds.length>1)_mapInst.fitBounds(bounds,{padding:[60,60],maxZoom:12});setTimeout(function(){if(_mapInst)_mapInst.invalidateSize(true);},200);setTimeout(function(){if(_mapInst)_mapInst.invalidateSize(true);},600);}).catch(function(){});}
function _doLoadMap(){var mapDiv=document.getElementById('vkd-map');if(!mapDiv)return;if(window.L){buildMap(mapDiv);return;}mapDiv.innerHTML='<i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#0088cc"></i>';var link=document.createElement('link');link.rel='stylesheet';link.href='https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css';document.head.appendChild(link);var sc=document.createElement('script');sc.src='https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js';sc.onload=function(){buildMap(document.getElementById('vkd-map'));};document.head.appendChild(sc);}
function loadMap(){setTimeout(_doLoadMap,80);}
window.vkdSetView=function(v){document.getElementById('vkd-btn-grid').classList.toggle('active',v==='grid');document.getElementById('vkd-btn-map').classList.toggle('active',v==='map');document.getElementById('vkd-results').style.display=v==='grid'?'':'none';var outer=document.getElementById('vkd-map-outer');if(outer)outer.style.display=v==='map'?'block':'none';if(v==='map')loadMap();};
function deb(){clearTimeout(_ftimer);_ftimer=setTimeout(function(){load(1);},420);}
document.getElementById('vkd-q').addEventListener('input',deb);
document.getElementById('vkd-cat').addEventListener('change',function(){load(1);});
document.getElementById('vkd-city').addEventListener('change',function(){load(1);});
document.getElementById('vkd-order').addEventListener('change',function(){load(1);});
fetch(API+'/dir/categories').then(function(r){return r.json();}).then(function(res){if(!res.categories)return;var sel=document.getElementById('vkd-cat');res.categories.forEach(function(c){var o=document.createElement('option');o.value=c.id;o.textContent=(c.icon?c.icon+' ':'')+c.name;sel.appendChild(o);});}).catch(function(){});
fetch(API+'/dir/cities').then(function(r){return r.json();}).then(function(res){if(!res.cities)return;var sel=document.getElementById('vkd-city');res.cities.forEach(function(c){var o=document.createElement('option');o.value=c;o.textContent=c;sel.appendChild(o);});}).catch(function(){});
load(1);
})();
</script>
            <?php
        } );
    }

    ob_start();
    ?>
<style id="vkd-dir-css">
.site-content{background:#f2f0fe}
#vkd-dir{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;--vkd-primary:#0088cc;--vkd-border:#e8ecf1;--vkd-text:#1a202c;--vkd-muted:#718096}
.vkd-filters-wrap{background:#fff;border:1px solid var(--vkd-border);border-radius:16px;padding:1.3rem 1.5rem;margin-bottom:1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.05)}
.vkd-filters-grid{display:grid;grid-template-columns:2fr 1.2fr 1.2fr 1fr;gap:.8rem}
.vkd-fg{display:flex;flex-direction:column;gap:.3rem}
.vkd-fg label{font-size:.71rem;font-weight:700;color:var(--vkd-muted);text-transform:uppercase;letter-spacing:.06em}
.vkd-fg-input{position:relative;display:flex;align-items:center}
.vkd-fg-input i{position:absolute;left:.85rem;color:var(--vkd-muted);font-size:.82rem;pointer-events:none;z-index:1}
.vkd-fg-input input,.vkd-fg-input select{width:100%;padding:.68rem .9rem .68rem 2.3rem;border:1.5px solid var(--vkd-border);border-radius:10px;font-size:.87rem;color:var(--vkd-text);background:#fff;outline:none;transition:border-color .2s;-webkit-appearance:none;appearance:none;box-sizing:border-box}
.vkd-fg-input select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 10 7'%3E%3Cpath fill='%23718096' d='M0 0l5 7 5-7z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .8rem center;padding-right:2rem}
.vkd-fg-input input:focus,.vkd-fg-input select:focus{border-color:var(--vkd-primary)}
.vkd-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem;flex-wrap:wrap;gap:.6rem}
.vkd-meta-txt{font-size:.82rem;color:var(--vkd-muted)}
.vkd-view-toggle{display:flex;border:1.5px solid var(--vkd-border);border-radius:10px;overflow:hidden}
.vkd-vt-btn{display:flex;align-items:center;gap:.4rem;padding:.48rem 1.1rem;font-size:.82rem;font-weight:600;background:#fff;color:var(--vkd-muted);border:none;cursor:pointer;transition:background .15s,color .15s}
.vkd-vt-btn.active{background:var(--vkd-primary);color:#fff}
.vkd-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(295px,1fr));gap:1.4rem}
.vkd-card{background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 2px 14px rgba(0,0,0,.07);cursor:pointer;transition:box-shadow .22s,transform .22s;border:1px solid rgba(0,0,0,.04)}
.vkd-card:hover{box-shadow:0 10px 36px rgba(0,136,204,.16);transform:translateY(-4px)}
.vkd-card-img-wrap{position:relative;height:220px;overflow:hidden;background:linear-gradient(135deg,#1a1a2e,#16213e,#0f3460)}
.vkd-card-img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .38s}
.vkd-card:hover .vkd-card-img{transform:scale(1.05)}
.vkd-card-img-ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:4.5rem;color:rgba(255,255,255,.12)}
.vkd-card-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.58) 0%,transparent 55%)}
.vkd-card-fire{position:absolute;top:10px;left:10px;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.92);display:flex;align-items:center;justify-content:center;font-size:.95rem;box-shadow:0 2px 8px rgba(0,0,0,.18)}
.vkd-card-fav{position:absolute;top:10px;right:10px;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.92);display:flex;align-items:center;justify-content:center;font-size:.88rem;color:#e91e63;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.18);border:none;transition:background .15s,transform .15s}
.vkd-card-fav:hover{background:#fff;transform:scale(1.12)}
.vkd-avail-badge{position:absolute;bottom:10px;left:10px;display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .78rem;border-radius:20px;font-size:.69rem;font-weight:700;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
.vkd-card-body{padding:1.1rem 1.2rem 1.2rem}
.vkd-card-name{font-size:1.05rem;font-weight:800;color:var(--vkd-text);margin:0 0 .22rem;line-height:1.3}
.vkd-card-spec{font-size:.79rem;color:var(--vkd-muted);margin:0 0 .65rem;display:flex;align-items:center;gap:.3rem;flex-wrap:wrap}
.vkd-sep{color:#d0d5dd}
.vkd-card-loc,.vkd-card-phone{font-size:.8rem;color:var(--vkd-muted);margin:0 0 .28rem;display:flex;align-items:center;gap:.5rem}
.vkd-card-loc i,.vkd-card-phone i{color:var(--vkd-primary);font-size:.77rem;width:14px;text-align:center;flex-shrink:0}
.vkd-card-footer{display:flex;align-items:center;justify-content:space-between;margin-top:.8rem;gap:.5rem;border-top:1px solid #f0f2f5;padding-top:.8rem}
.vkd-stars-wrap{display:flex;align-items:center;gap:.3rem}
.vkd-stars-fill{color:#f5a623;font-size:.83rem}
.vkd-stars-count{color:var(--vkd-muted);font-size:.73rem}
.vkd-cta{padding:.42rem 1rem;border:1.8px solid var(--vkd-primary);color:var(--vkd-primary);border-radius:50px;font-size:.78rem;font-weight:700;background:none;cursor:pointer;text-decoration:none!important;transition:background .15s,color .15s;white-space:nowrap}
.vkd-cta:hover{background:var(--vkd-primary);color:#fff!important}
#vkd-map-outer{border-radius:16px;overflow:hidden;border:1px solid var(--vkd-border);box-shadow:0 2px 12px rgba(0,0,0,.07);display:none}#vkd-map{height:540px}
.vkd-map-popup .leaflet-popup-content-wrapper{border-radius:14px;box-shadow:0 6px 24px rgba(0,0,0,.16);padding:0;overflow:hidden}
.vkd-map-popup .leaflet-popup-content{margin:0;width:220px!important}
.vkd-pop-img{width:100%;height:110px;object-fit:cover;display:block}
.vkd-pop-body{padding:.75rem .9rem .85rem}
.vkd-pop-name{font-size:.9rem;font-weight:800;color:#1a202c;margin:0 0 .18rem}
.vkd-pop-spec{font-size:.75rem;color:#0088cc;font-weight:600;margin:0 0 .3rem}
.vkd-pop-loc{font-size:.73rem;color:#718096;margin:0 0 .6rem}
.vkd-pop-btn{display:block;text-align:center;padding:.42rem .75rem;background:#0088cc;color:#fff!important;border-radius:9px;font-size:.76rem;font-weight:700;text-decoration:none!important}
.vkd-pager{display:flex;justify-content:center;align-items:center;gap:.7rem;margin-top:1.75rem;flex-wrap:wrap}
.vkd-pager button{padding:.52rem 1.2rem;border:1.5px solid var(--vkd-border);border-radius:50px;background:#fff;color:var(--vkd-text);cursor:pointer;font-size:.83rem;font-weight:600;transition:all .15s}
.vkd-pager button:hover{border-color:var(--vkd-primary);color:var(--vkd-primary)}
.vkd-pager span{font-size:.82rem;color:var(--vkd-muted)}
.vkd-empty{text-align:center;padding:4rem 1rem;color:var(--vkd-muted)}
.vkd-empty i{font-size:2.8rem;display:block;margin-bottom:1rem;opacity:.3}
@media(max-width:780px){.vkd-filters-grid{grid-template-columns:1fr 1fr}}
@media(max-width:540px){.vkd-filters-grid{grid-template-columns:1fr}.vkd-grid{grid-template-columns:1fr}}
</style>

<div id="vkd-dir">
  <div class="vkd-filters-wrap">
    <div class="vkd-filters-grid">
      <div class="vkd-fg">
        <label>Buscar por palabra clave</label>
        <div class="vkd-fg-input">
          <i class="fas fa-magnifying-glass"></i>
          <input type="text" id="vkd-q" placeholder="Ej. nombre del doctor, especialidad...">
        </div>
      </div>
      <div class="vkd-fg">
        <label>Categor&iacute;a</label>
        <div class="vkd-fg-input">
          <i class="fas fa-tag"></i>
          <select id="vkd-cat"><option value="">Todas las categor&iacute;as</option></select>
        </div>
      </div>
      <div class="vkd-fg">
        <label>Ciudad</label>
        <div class="vkd-fg-input">
          <i class="fas fa-location-dot"></i>
          <select id="vkd-city"><option value="">Todas las ciudades</option></select>
        </div>
      </div>
      <div class="vkd-fg">
        <label>Orden alfab&eacute;tico</label>
        <div class="vkd-fg-input">
          <i class="fas fa-arrow-down-a-z"></i>
          <select id="vkd-order">
            <option value="ASC">A - Z</option>
            <option value="DESC">Z - A</option>
          </select>
        </div>
      </div>
    </div>
  </div>
  <div class="vkd-bar">
    <div id="vkd-meta" class="vkd-meta-txt">Cargando...</div>
    <div class="vkd-view-toggle">
      <button class="vkd-vt-btn active" id="vkd-btn-grid" onclick="vkdSetView('grid')">
        <i class="fas fa-grip"></i> Cuadr&iacute;cula
      </button>
      <button class="vkd-vt-btn" id="vkd-btn-map" onclick="vkdSetView('map')">
        <i class="fas fa-map-location-dot"></i> Mapa
      </button>
    </div>
  </div>
  <div id="vkd-results">
    <p style="text-align:center;padding:3rem;color:#999"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:#0088cc"></i></p>
  </div>
  <div id="vkd-map-outer"><div id="vkd-map"></div></div>
</div>
    <?php
    return ob_get_clean();
}

function vkd_shortcode_perfil( $atts ) {
    $atts = shortcode_atts( array( 'uid' => 0 ), $atts );
    $uid  = (int) $atts['uid'];
    if ( ! $uid ) $uid = (int) get_query_var( 'vkd_profile_uid' );
    if ( ! $uid && isset( $_GET['uid'] ) ) $uid = (int) $_GET['uid'];
    if ( ! $uid ) return '<p style="color:#c00">UID del profesional no especificado.</p>';

    $row      = vkd_get_record( $uid );
    $approval = $row ? (string) ( $row->approval_status ?? 'pending' ) : '';
    if ( ! $row || ( $approval !== 'approved' && ! current_user_can( 'manage_options' ) ) ) {
        return '<p style="color:#c00">Profesional no encontrado.</p>';
    }
    return vkd_build_profile_html( $row );
}

/* ══════════════════════════════════════════════════════════════════════════════
   APROBACIÓN DE PERFILES (admin)
══════════════════════════════════════════════════════════════════════════════ */

/** Listado de perfiles pendientes — solo admin */
function vkd_api_pending_list( $req ) {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'forbidden', 'No permitido', array( 'status' => 403 ) );
    }
    $table  = $wpdb->prefix . VKD_TABLE;
    $status = sanitize_text_field( $req->get_param('status') ?: 'pending' );
    $valid  = array( 'pending', 'approved', 'rejected', 'all' );
    if ( ! in_array( $status, $valid, true ) ) $status = 'pending';

    $where = $status === 'all' ? '' : $wpdb->prepare( "WHERE p.approval_status = %s", $status );
    $rows  = $wpdb->get_results(
        "SELECT p.id, p.user_id, p.post_id, p.name, p.tagline, p.profession,
                p.city, p.email, p.phone, p.approval_status, p.approved_at,
                p.created_at, p.updated_at
         FROM `{$table}` p
         {$where}
         ORDER BY p.created_at DESC"
    );

    $items = array();
    foreach ( $rows as $r ) {
        $u = get_userdata( (int) $r->user_id );
        $items[] = array(
            'id'              => (int) $r->id,
            'user_id'         => (int) $r->user_id,
            'user_email'      => $u ? $u->user_email : '',
            'user_login'      => $u ? $u->user_login : '',
            'post_id'         => (int) $r->post_id,
            'name'            => (string) $r->name,
            'tagline'         => (string) $r->tagline,
            'profession'      => (string) $r->profession,
            'city'            => (string) $r->city,
            'email'           => (string) $r->email,
            'phone'           => (string) $r->phone,
            'approval_status' => (string) $r->approval_status,
            'approved_at'     => (string) ( $r->approved_at ?? '' ),
            'created_at'      => (string) $r->created_at,
            'updated_at'      => (string) $r->updated_at,
            'edit_url'        => admin_url( 'admin.php?page=vk-directory&action=edit&uid=' . $r->user_id ),
        );
    }
    return rest_ensure_response( array( 'ok' => true, 'items' => $items, 'total' => count( $items ) ) );
}

/** Aprobar un perfil — solo admin */
function vkd_api_approve( $req ) {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'forbidden', 'No permitido', array( 'status' => 403 ) );
    }
    $uid   = (int) $req->get_param('uid');
    $table = $wpdb->prefix . VKD_TABLE;

    $row = vkd_get_record( $uid );
    if ( ! $row ) return new WP_Error( 'not_found', 'Perfil no encontrado', array( 'status' => 404 ) );

    $wpdb->update( $table, array(
        'approval_status' => 'approved',
        'approved_at'     => current_time( 'mysql' ),
        'approved_by'     => get_current_user_id(),
    ), array( 'user_id' => $uid ) );

    // Publicar el post de Directorist
    if ( (int) $row->post_id > 0 ) {
        $wpdb->update( $wpdb->posts, array(
            'post_status'       => 'publish',
            'post_modified'     => current_time( 'mysql' ),
            'post_modified_gmt' => current_time( 'mysql', 1 ),
        ), array( 'ID' => (int) $row->post_id ) );
        clean_post_cache( (int) $row->post_id );
    }

    error_log( "[vkd] APROBADO uid={$uid} por admin=" . get_current_user_id() );

    // Notificación push al usuario
    vkd_notify_dir_approved( $uid, $row );

    return rest_ensure_response( array( 'ok' => true, 'message' => 'Perfil aprobado y publicado en el directorio.' ) );
}

/** Rechazar un perfil — solo admin */
function vkd_api_reject( $req ) {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'forbidden', 'No permitido', array( 'status' => 403 ) );
    }
    $uid    = (int) $req->get_param('uid');
    $reason = sanitize_textarea_field( $req->get_json_params()['reason'] ?? '' );
    $table  = $wpdb->prefix . VKD_TABLE;

    $row = vkd_get_record( $uid );
    if ( ! $row ) return new WP_Error( 'not_found', 'Perfil no encontrado', array( 'status' => 404 ) );

    $wpdb->update( $table, array(
        'approval_status' => 'rejected',
    ), array( 'user_id' => $uid ) );

    // Poner el post en 'draft' para que no sea visible
    if ( (int) $row->post_id > 0 ) {
        $wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => (int) $row->post_id ) );
        clean_post_cache( (int) $row->post_id );
    }

    error_log( "[vkd] RECHAZADO uid={$uid} por admin=" . get_current_user_id() . ( $reason ? " razón: {$reason}" : '' ) );
    return rest_ensure_response( array( 'ok' => true, 'message' => 'Perfil rechazado.' ) );
}

/** Migración: marcar como aprobados los perfiles publish existentes */
function vkd_migrate_approval_status() {
    global $wpdb;
    $table = $wpdb->prefix . VKD_TABLE;
    // Solo ejecutar una vez
    if ( get_option( 'vkd_approval_migrated' ) ) return;
    // Aprobar automáticamente los que ya están publicados en WP
    $wpdb->query(
        "UPDATE `{$table}` p
         INNER JOIN {$wpdb->posts} wp ON wp.ID = p.post_id
         SET p.approval_status = 'approved', p.approved_at = NOW()
         WHERE wp.post_status = 'publish' AND p.approval_status = 'pending'"
    );
    update_option( 'vkd_approval_migrated', '1' );
    error_log( '[vkd] migración approval_status completada' );
}
add_action( 'init', 'vkd_migrate_approval_status', 20 );

/* ══════════════════════════════════════════════════════════════════════════════
   NOTIFICACIÓN PUSH AL APROBAR UN PERFIL
══════════════════════════════════════════════════════════════════════════════ */

/**
 * Envía notificación push + BD al usuario cuando su perfil es aprobado.
 * Respeta la plantilla configurable en el panel de Notificaciones Automáticas.
 *
 * @param int    $uid  user_id del profesional aprobado
 * @param object $row  fila del registro (puede ser null si no se pasó)
 */
function vkd_notify_dir_approved( $uid, $row = null ) {
    $uid = (int) $uid;
    if ( ! $uid ) return;

    // Leer plantilla desde la configuración del panel de Notificaciones Automáticas
    $config   = get_option( 'vk_push_auto_config', array() );
    $ev       = isset( $config['dir_approved'] ) ? $config['dir_approved'] : array();
    $enabled  = ! empty( $ev['enabled'] );
    $default_tpl = '¡Tu perfil "{NAME}" ha sido aprobado y ya está visible en el directorio de Vida Kushala! Puedes compartirlo con tus clientes.';
    $template = ! empty( $ev['template'] ) ? $ev['template'] : $default_tpl;

    if ( ! $enabled ) {
        error_log( "[vkd] dir_approved: evento desactivado, sin notificación a uid={$uid}" );
        return;
    }

    // Sustituir {NAME} con el nombre del perfil
    $name    = $row && ! empty( $row->name ) ? (string) $row->name : '';
    $message = $name ? str_replace( '{NAME}', $name, $template ) : str_replace( ' "{NAME}"', '', $template );

    $title = '¡Perfil aprobado en el Directorio!';
    // URL al perfil público en WordPress (se abre en el navegador externo)
    $permalink = function_exists( 'vkd_profile_url' ) ? vkd_profile_url( $uid ) : '';
    $url = $permalink ?: 'https://app.vidakushala.com/?screen=directory-profile';

    // Delegar en la función central de vk-cors.php (disponible en el mismo proceso WP)
    if ( function_exists( 'vk_notify_user' ) ) {
        vk_notify_user( $uid, 'directory', $title, $message, $url );
        error_log( "[vkd] dir_approved: notificación enviada a uid={$uid}" );
    } else {
        error_log( "[vkd] dir_approved: vk_notify_user no disponible para uid={$uid}" );
    }
}

/* ══════════════════════════════════════════════════════════════════════════════
   NOTIFICACIÓN PUSH A ADMINS — PERFIL PENDIENTE DE REVISIÓN
══════════════════════════════════════════════════════════════════════════════ */

/**
 * Notifica a todos los administradores WordPress cuando un usuario
 * envía un nuevo perfil o actualiza uno existente en el directorio.
 *
 * Estrategia de entrega:
 *  1. Guarda en BD (vk_notifications) → aparece en el panel de notificaciones
 *  2. Push por include_external_user_ids (WP user ID vinculado en OneSignal)
 *  3. Fallback por include_subscription_ids (player_ids en user meta)
 *
 * @param int    $uid    user_id del propietario del perfil
 * @param object $row    fila del registro (puede ser null)
 * @param bool   $is_new true si es un perfil nuevo, false si es actualización
 */
function vkd_notify_admin_dir_pending( $uid, $row = null, $is_new = true ) {
    $uid = (int) $uid;
    if ( ! $uid ) return;
    error_log( "[vkd] dir_pending: iniciando notificación para uid={$uid} is_new=" . ( $is_new ? '1' : '0' ) );

    // Respetar configuración del panel de Notificaciones Automáticas
    $config   = get_option( 'vk_push_auto_config', array() );
    $ev       = isset( $config['dir_pending'] ) ? $config['dir_pending'] : array();
    $enabled  = isset( $ev['enabled'] ) ? (bool) $ev['enabled'] : true;
    $template = ! empty( $ev['template'] )
        ? $ev['template']
        : '📋 {NAME} ha enviado su perfil al directorio y está pendiente de aprobación.';

    if ( ! $enabled ) {
        error_log( "[vkd] dir_pending: evento desactivado" );
        return;
    }

    // Nombre del perfil
    $name = $row && ! empty( $row->name ) ? (string) $row->name : '';
    if ( ! $name ) {
        $wp_user = get_userdata( $uid );
        $name    = $wp_user ? $wp_user->display_name : 'Usuario #' . $uid;
    }

    $now     = wp_date( 'd/m/Y H:i', time() );
    $action  = $is_new ? 'Nuevo perfil en Directorio' : 'Perfil actualizado en Directorio';
    $message = str_replace( '{NAME}', $name, $template );
    $title   = '🔔 ' . $action;
    // URL directa al panel de edición/aprobación en WP admin
    $url     = admin_url( 'admin.php?page=vkd-edit&uid=' . $uid );

    // ── 1. Obtener admins ───────────────────────────────────────────────────
    $admins = get_users( array(
        'role'    => 'administrator',
        'number'  => 50,
        'fields'  => array( 'ID' ),
    ) );

    if ( empty( $admins ) ) {
        error_log( "[vkd] dir_pending: no se encontraron administradores" );
        return;
    }

    $admin_ids = array();
    foreach ( $admins as $a ) {
        $aid = (int) $a->ID;
        if ( $aid !== $uid ) $admin_ids[] = $aid; // no notificar al propio usuario
    }
    if ( empty( $admin_ids ) ) return;

    // ── 2. Guardar en BD para cada admin (aparece en su campana de notifs) ──
    global $wpdb;
    $notif_table  = $wpdb->prefix . 'vk_notifications';
    $full_message = $message . ' Estado: Pendiente. Fecha: ' . $now;

    // Forzar utf8mb4 para preservar emojis y tildes
    $wpdb->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
    foreach ( $admin_ids as $aid ) {
        $wpdb->insert( $notif_table, array(
            'user_id'    => $aid,
            'title'      => wp_strip_all_tags( $title ),
            'message'    => wp_strip_all_tags( $full_message ),
            'type'       => 'directory_admin',
            'action_url' => esc_url_raw( $url ),
            'is_read'    => 0,
            'created_at' => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' ) );
    }

    // ── 3. Push OneSignal ───────────────────────────────────────────────────
    $os_settings  = get_option( 'onesignal_settings', array() );
    $rest_api_key = isset( $os_settings['app_rest_api_key'] ) ? trim( $os_settings['app_rest_api_key'] ) : '';
    $app_id       = defined( 'VK_ONESIGNAL_APP_ID' ) ? VK_ONESIGNAL_APP_ID : '';

    if ( empty( $rest_api_key ) || empty( $app_id ) ) {
        error_log( "[vkd] dir_pending: OneSignal no configurado — solo BD" );
        return;
    }

    // Targeting por external_user_id (WP user ID vinculado al login OneSignal)
    // Es el método más confiable: funciona aunque el player_id haya expirado
    $external_ids = array_map( 'strval', $admin_ids );

    // Fallback: recolectar player_ids en user meta de los admins
    $player_ids = array();
    foreach ( $admin_ids as $aid ) {
        $pids = get_user_meta( $aid, 'onesignal_player_ids', true ) ?: array();
        if ( is_array( $pids ) ) {
            foreach ( $pids as $pid ) { if ( ! empty( $pid ) ) $player_ids[] = $pid; }
        }
        $single = get_user_meta( $aid, 'onesignal_player_id', true );
        if ( $single ) $player_ids[] = $single;
    }
    $player_ids = array_values( array_unique( $player_ids ) );

    $icon = 'https://vidakushala.com/wp-content/uploads/dm-icon.png';

    $payload = array(
        'app_id'                         => $app_id,
        'headings'                       => array( 'en' => $title, 'es' => $title ),
        'contents'                       => array( 'en' => $full_message, 'es' => $full_message ),
        'url'                            => $url,
        'include_external_user_ids'      => $external_ids,
        'channel_for_external_user_ids'  => 'push',
        'chrome_web_icon'                => $icon,
        'firefox_icon'                   => $icon,
        'chrome_web_badge'               => $icon,
        'data'                           => array( 'type' => 'directory_admin', 'uid' => $uid, 'url' => $url ),
        'web_push_topic'                 => 'directory_admin',
        'ttl'                            => 86400,
        'priority'                       => 10,
    );

    // Si hay player_ids conocidos, incluirlos también para mayor cobertura
    if ( ! empty( $player_ids ) ) {
        $payload['include_subscription_ids'] = $player_ids;
    }

    $response = wp_remote_post( 'https://onesignal.com/api/v1/notifications', array(
        'headers' => array(
            'Content-Type'  => 'application/json; charset=utf-8',
            'Authorization' => 'Key ' . $rest_api_key,
        ),
        'body'    => json_encode( $payload, JSON_UNESCAPED_UNICODE ),
        'timeout' => 15,
    ) );

    if ( is_wp_error( $response ) ) {
        error_log( "[vkd] dir_pending push error: " . $response->get_error_message() );
        return;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $code = wp_remote_retrieve_response_code( $response );
    error_log( "[vkd] dir_pending push HTTP={$code} recipients=" . ( $body['recipients'] ?? 0 ) . " errors=" . json_encode( $body['errors'] ?? [] ) );
}
