<?php
require_once __DIR__ . '/inc/init.php';

// Páginas de cursos son públicas e indexables
header('Cache-Control: public, max-age=3600', true);
header('Pragma: public', true);

// ── Parámetros de entrada ──────────────────────────────────────────────────────
$slug_param = trim(strip_tags($_GET['slug'] ?? ''));
$id_param   = (int)($_GET['id'] ?? 0);

// ── Fetch server-side para SEO (meta tags generados en PHP) ──────────────────
$seo = null;
if ($slug_param || $id_param) {
    $api_body = $slug_param
        ? ['resource' => 'courses', 'action' => 'get_by_slug', 'slug' => $slug_param]
        : ['resource' => 'courses', 'action' => 'get', 'courseid' => $id_param];

    $ch = curl_init(API_URL . 'handle');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($api_body),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if ($raw) {
        $resp = json_decode($raw, true);
        if (!empty($resp['ok']) && !empty($resp['data'])) {
            $seo = $resp['data'];
        }
    }
}

$page_title  = $seo ? $seo['title'] : 'Detalle del Curso';
$active_menu = 'cursos';
$seo_public  = true;

$c_slug    = $seo['slug']    ?? ($slug_param ?: '');
$c_title   = $seo['title']   ?? '';
$c_excerpt = mb_substr(strip_tags($seo['excerpt'] ?? ''), 0, 160);
$c_image   = $seo['thumbnail'] ?? '';
$c_canon   = APP_URL . '/curso/' . $c_slug;

// ── Datos inyectados en JS para evitar doble-fetch ───────────────────────────
$js_init = $seo
    ? json_encode(['id' => (int)$seo['id'], 'slug' => $c_slug], JSON_HEX_TAG | JSON_HEX_AMP)
    : 'null';

// ── Extra head: meta SEO + OG + Twitter + JSON-LD ────────────────────────────
$extra_head = '';

// Ocultar UI de sesión y sidebar para visitantes invitados (síncrono, sin flash)
$extra_head .= <<<'HTML'
<style>
.awx-guest .header-right-content{display:none!important;}
html.awx-guest .side-menu-area{display:none!important;}
html.awx-guest .main-content-wrap{padding-left:0!important;}
</style>
<script>(function(){if(!localStorage.getItem('awx_token'))document.documentElement.classList.add('awx-guest');}());</script>
HTML;

if ($seo) {
    $e_title   = htmlspecialchars($c_title,   ENT_QUOTES, 'UTF-8');
    $e_excerpt = htmlspecialchars($c_excerpt, ENT_QUOTES, 'UTF-8');
    $e_image   = htmlspecialchars($c_image,   ENT_QUOTES, 'UTF-8');
    $e_url     = htmlspecialchars($c_canon,   ENT_QUOTES, 'UTF-8');

    $extra_head .= "<meta name=\"description\" content=\"{$e_excerpt}\">\n";
    $extra_head .= "<link rel=\"canonical\" href=\"{$e_url}\">\n";
    $extra_head .= "<meta property=\"og:type\"        content=\"website\">\n";
    $extra_head .= "<meta property=\"og:title\"       content=\"{$e_title} — AulaWix\">\n";
    $extra_head .= "<meta property=\"og:description\" content=\"{$e_excerpt}\">\n";
    if ($e_image) $extra_head .= "<meta property=\"og:image\" content=\"{$e_image}\">\n";
    $extra_head .= "<meta property=\"og:url\"         content=\"{$e_url}\">\n";
    $extra_head .= "<meta name=\"twitter:card\"        content=\"summary_large_image\">\n";
    $extra_head .= "<meta name=\"twitter:title\"       content=\"{$e_title}\">\n";
    $extra_head .= "<meta name=\"twitter:description\" content=\"{$e_excerpt}\">\n";
    if ($e_image) $extra_head .= "<meta name=\"twitter:image\" content=\"{$e_image}\">\n";

    $ld = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Course',
        'name'        => $c_title,
        'description' => $c_excerpt,
        'url'         => $c_canon,
        'provider'    => ['@type' => 'Organization', 'name' => 'AulaWix', 'sameAs' => APP_URL],
    ];
    if ($c_image) $ld['image'] = $c_image;
    if (!empty($seo['author_name'])) {
        $ld['author'] = ['@type' => 'Person', 'name' => $seo['author_name']];
    }
    $ld['hasCourseInstance'] = [['@type' => 'CourseInstance', 'courseMode' => 'https://schema.org/OnlineEventAttendanceMode']];
    $extra_head .= '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
}

require_once __DIR__ . '/inc/header.php';
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     VISTA ÚNICA DEL CURSO — Estilo Tutor LMS
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="tcd-page">

    <!-- Loading -->
    <div id="tcdLoading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status" style="width:2.5rem;height:2.5rem;"></div>
        <p class="mt-3 text-muted">Cargando curso…</p>
    </div>

    <!-- Error -->
    <div id="tcdError" class="alert alert-danger mx-4 d-none"></div>

    <!-- Contenido principal (oculto hasta cargar) -->
    <div id="tcdContent" class="d-none">

        <!-- ── MAIN GRID: CONTENIDO + SIDEBAR ───────────────────────── -->
        <div class="tcd-main">

            <!-- ── Sidebar de inscripción (sticky) ──────────────────── -->
            <aside class="tcd-sidebar">
                <div class="tcd-enrollment-card">

                    <div class="tcd-card-body">

                        <!-- Badge tipo de curso -->
                        <div id="tcdCourseBadge" class="tcd-course-badge d-none">
                            <i class="ri-graduation-cap-line"></i> Curso gratuito
                        </div>

                        <!-- Precio -->
                        <div id="tcdPrice" class="mb-1"></div>
                        <p id="tcdPriceSub" class="tcd-price-sub d-none">Acceso completo de por vida</p>

                        <!-- === Usuario NO inscrito === -->
                        <div id="sideNotEnrolled" class="mt-3">
                            <!-- Autenticado pero no inscrito: curso de pago -->
                            <button id="btnEnroll" type="button"
                               class="tcd-btn-primary d-none" onclick="enrollPaid()">
                                <i class="ri-shopping-cart-line me-2"></i>Inscribirme ahora
                            </button>
                            <!-- Invitado (sin sesión): redirige a registro/login -->
                            <a id="btnGuest" href="registro.php"
                               class="tcd-btn-primary d-none">
                                <i class="ri-graduation-cap-line me-2"></i>Inscribirme gratis
                            </a>
                            <a id="btnGuestLogin" href="login.php"
                               class="tcd-btn-outline d-none" style="margin-top:0;">
                                <i class="ri-login-circle-line me-1"></i>Ya tengo cuenta — Iniciar sesión
                            </a>
                            <!-- Curso gratuito para autenticado -->
                            <div id="freeEnroll" class="d-none">
                                <button id="btnFreeEnroll" type="button" class="tcd-btn-primary" onclick="enrollAndStart()">
                                    <i class="ri-graduation-cap-line me-2"></i>Inscribirme gratis
                                </button>
                            </div>
                        </div>

                        <!-- === Usuario ya inscrito === -->
                        <div id="sideEnrolled" class="d-none mt-3">
                            <div class="tcd-card-progress mb-3">
                                <div class="tcd-card-progress-label">
                                    <span>Tu progreso</span>
                                    <span id="cardPct">0%</span>
                                </div>
                                <div class="tcd-card-progress-bar">
                                    <div class="tcd-card-progress-fill" id="cardBar" style="width:0%;"></div>
                                </div>
                            </div>
                            <button id="btnContinue" type="button" class="tcd-btn-primary" onclick="goToLesson()">
                                <i class="ri-play-circle-line me-2"></i>Continuar curso
                            </button>
                        </div>

                        <!-- Botón favoritos -->
                        <button id="btnFavorite" type="button" class="tcd-btn-outline">
                            <i class="ri-heart-line me-2"></i>Añadir a favoritos
                        </button>

                        <!-- Garantía de satisfacción -->
                        <div class="tcd-guarantee">
                            <i class="ri-shield-check-line"></i>
                            <div>
                                <div class="tcd-guarantee-title">Garantía de satisfacción</div>
                                <div class="tcd-guarantee-sub">Si el curso no es para ti, puedes dejarlo en cualquier momento.</div>
                            </div>
                        </div>

                        <!-- Incluye -->
                        <div class="tcd-includes">
                            <h5 class="tcd-includes-title">Este curso incluye:</h5>
                            <ul id="tcdIncludes" class="tcd-includes-list"></ul>
                        </div>

                        <!-- Compartir -->
                        <div class="tcd-share">
                            <span class="tcd-share-label">Compartir:</span>
                            <div class="tcd-share-btns">
                                <a id="shareWhatsApp" href="#" target="_blank" class="tcd-share-btn tcd-share-wa" aria-label="WhatsApp">
                                    <i class="ri-whatsapp-line"></i>
                                </a>
                                <a id="shareFB" href="#" target="_blank" class="tcd-share-btn tcd-share-fb" aria-label="Facebook">
                                    <i class="ri-facebook-fill"></i>
                                </a>
                                <a id="shareX" href="#" target="_blank" class="tcd-share-btn tcd-share-x" aria-label="X / Twitter">
                                    <i class="ri-twitter-x-line"></i>
                                </a>
                                <button onclick="copyLink()" class="tcd-share-btn tcd-share-copy" aria-label="Copiar enlace">
                                    <i class="ri-link" id="copyIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- ── Columna de contenido ──────────────────────────────── -->
            <div class="tcd-content-col">

                <!-- ── ENCABEZADO DEL CURSO ──────────────────────────── -->
                <div class="tcd-course-header">

                    <!-- Texto del encabezado (izquierda) -->
                    <div class="tcd-header-text">

                        <!-- Volver al panel (solo visible para instructores, mostrado vía JS) -->
                        <a href="dashboard-instructor.php" id="instPanelBack"
                           style="display:none;align-items:center;gap:5px;color:#4B7BF5;font-size:12px;text-decoration:none;margin-bottom:10px;font-weight:600;">
                            <i class="ri-arrow-left-line"></i>Panel Instructor
                        </a>

                        <!-- Breadcrumb -->
                        <nav class="tcd-breadcrumb">
                            <a href="index.php">Inicio</a>
                            <span class="sep"><i class="ri-arrow-right-s-line"></i></span>
                            <a href="aprender.php">Cursos</a>
                            <span class="sep"><i class="ri-arrow-right-s-line"></i></span>
                            <span id="tcdBreadTitle" style="color:#94a3b8;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:200px;display:inline-block;vertical-align:middle;"></span>
                        </nav>

                        <!-- Categoría -->
                        <div id="tcdCatBadge" class="tcd-cat-badge" style="display:none;"></div>

                        <!-- Título -->
                        <h1 id="tcdTitle" class="tcd-hero-title"></h1>

                        <!-- Extracto -->
                        <p id="tcdExcerpt" class="tcd-hero-excerpt"></p>

                        <!-- Rating + Meta -->
                        <div id="tcdMeta" class="tcd-rating-row"></div>

                        <!-- Instructor -->
                        <div id="tcdInstLine" class="tcd-hero-inst" style="display:none;">
                            <span style="color:#94a3b8;font-size:13px;">Creado por</span>
                            <img id="tcdInstAvatar" src="assets/images/avatar.png" alt="">
                            <a id="tcdInstName" href="#">–</a>
                        </div>

                        <!-- Última actualización -->
                        <div id="tcdLastUpdate" class="mt-2" style="font-size:12px;color:#94a3b8;display:none;">
                            <i class="ri-refresh-line me-1"></i>
                            <span id="tcdUpdateText"></span>
                        </div>

                        <!-- Stats Bar integrada en el header -->
                        <div class="tcd-stats-bar">
                            <div class="tcd-stat-item">
                                <i class="ri-booklet-line"></i>
                                <span><strong id="sbarTopics">–</strong> temas</span>
                            </div>
                            <div class="tcd-stat-item">
                                <i class="ri-file-list-3-line"></i>
                                <span><strong id="sbarLessons">–</strong> lecciones</span>
                            </div>
                            <div class="tcd-stat-item">
                                <i class="ri-group-line"></i>
                                <span><strong id="sbarStudents">–</strong> estudiantes</span>
                            </div>
                            <div class="tcd-stat-item" id="sbarLevelWrap" style="display:none;">
                                <i class="ri-bar-chart-2-line"></i>
                                <span id="sbarLevel">–</span>
                            </div>
                            <div class="tcd-stat-item" id="sbarDurWrap" style="display:none;">
                                <i class="ri-time-line"></i>
                                <span id="sbarDur">–</span>
                            </div>
                            <div class="tcd-stat-item" id="sbarLangWrap" style="display:none;">
                                <i class="ri-global-line"></i>
                                <span id="sbarLang">–</span>
                            </div>
                        </div>

                        <!-- CTA en hero — oculto vía CSS, solo para compatibilidad JS -->
                        <div id="heroCta" style="display:none;">
                            <button id="heroFreeBtn" type="button" onclick="enrollAndStart()" style="display:none;"></button>
                            <button id="heroContinueBtn" type="button" onclick="goToLesson()" style="display:none;"></button>
                            <a id="heroGuestBtn" href="registro.php" style="display:none;"></a>
                            <button id="heroPaidBtn" type="button" onclick="enrollPaid()" style="display:none;"></button>
                        </div>

                    </div>
                    <!-- End header-text -->

                    <!-- Imagen/miniatura del curso (derecha) -->
                    <div class="tcd-header-img">
                        <img id="tcdPreviewImg" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;">
                        <div id="tcdPreviewIcon" class="tcd-preview-icon">
                            <i class="ri-book-open-line"></i>
                        </div>
                    </div>

                </div>
                <!-- End course-header -->

                <!-- Lo que aprenderás -->
                <section id="secWWL" class="tcd-section d-none">
                    <h2 class="tcd-section-title">
                        <i class="ri-checkbox-circle-line"></i> Lo que aprenderás
                    </h2>
                    <div id="wwlGrid" class="tcd-wwl-grid"></div>
                </section>

                <!-- Requisitos -->
                <section id="secReqs" class="tcd-section d-none">
                    <h2 class="tcd-section-title">
                        <i class="ri-list-check-2"></i> Requisitos
                    </h2>
                    <ul id="reqsList" class="tcd-req-list"></ul>
                </section>

                <!-- Audiencia -->
                <section id="secAudience" class="tcd-section d-none">
                    <h2 class="tcd-section-title">
                        <i class="ri-focus-3-line"></i> ¿Para quién es este curso?
                    </h2>
                    <ul id="audienceList" class="tcd-req-list"></ul>
                </section>

                <!-- Curriculum -->
                <section class="tcd-section" id="curriculum">
                    <div class="tcd-section-head">
                        <h2 class="tcd-section-title mb-0">
                            <i class="ri-book-2-line"></i> Contenido del curso
                        </h2>
                        <span id="currStats" class="tcd-curr-stats"></span>
                    </div>
                    <div id="currLoading" class="text-center py-3">
                        <div class="spinner-border text-primary" style="width:1.2rem;height:1.2rem;" role="status"></div>
                    </div>
                    <div id="currError" class="alert alert-warning small d-none"></div>
                    <div id="currAccordion" class="tcd-accordion mt-3"></div>
                </section>

                <!-- Instructor -->
                <section id="secInstructor" class="tcd-section d-none">
                    <h2 class="tcd-section-title">
                        <i class="ri-user-star-line"></i> Instructor
                    </h2>
                    <div id="instructorCard"></div>
                </section>

                <!-- Descripción completa -->
                <section id="secDesc" class="tcd-section d-none">
                    <h2 class="tcd-section-title">
                        <i class="ri-file-text-line"></i> Descripción del curso
                    </h2>
                    <div class="tcd-read-more-wrap" id="descWrap">
                        <div id="tcdDesc" class="tcd-desc-content tcd-read-more-content"></div>
                    </div>
                </section>

            </div>
            <!-- End content-col -->

        </div>
        <!-- End tcd-main -->

        <!-- Barra flotante inferior (móvil) — solo visible en pantallas pequeñas -->
        <div id="mobileCtaBar" style="display:none;position:fixed;bottom:0;left:0;right:0;
             background:#fff;padding:12px 16px;box-shadow:0 -4px 20px rgba(0,0,0,.15);
             z-index:300;border-top:1px solid #e2e8f0;">
            <button id="mobileCtaBtn" type="button" onclick="mobileCtaAction()"
                style="display:block;width:100%;padding:14px;border-radius:10px;border:none;
                       background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;
                       font-size:15px;font-weight:700;cursor:pointer;">
                <i class="ri-graduation-cap-line me-2"></i>Inscribirme gratis
            </button>
        </div>

    </div>
    <!-- End tcdContent -->

</div>
<!-- End tcd-page -->

<!-- ═══════════════════════════════════════════════════
     MODAL DE CHECKOUT
     ═══════════════════════════════════════════════════ -->
<div id="ckModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div id="ckBox" style="background:#fff;border-radius:18px;width:100%;max-width:540px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.28);display:flex;flex-direction:column;max-height:92vh;">

        <!-- Header -->
        <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 22px;border-bottom:1px solid #e2e8f0;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div id="ckBackBtn" onclick="ckBack()" style="display:none;cursor:pointer;width:32px;height:32px;border-radius:8px;background:#f1f5f9;display:none;align-items:center;justify-content:center;">
                    <i class="ri-arrow-left-line" style="color:#4B7BF5;font-size:16px;"></i>
                </div>
                <span id="ckTitle" style="font-weight:700;font-size:15px;color:#1e293b;">Inscribirse en el curso</span>
            </div>
            <button onclick="closeCheckout()" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:20px;line-height:1;padding:4px;"><i class="ri-close-line"></i></button>
        </div>

        <!-- Body scrollable -->
        <div id="ckBody" style="overflow-y:auto;flex:1;"></div>
    </div>
</div>

<style>
/* ── Checkout modal ───────────────────────────────────────────── */
.ck-section { padding:22px; }
.ck-summary {
    background:linear-gradient(135deg,#f0f4ff,#eef2ff);
    border:1px solid #c7d7ff;border-radius:12px;
    padding:14px 16px;display:flex;align-items:center;gap:14px;margin-bottom:20px;
}
.ck-summary-img { width:56px;height:56px;border-radius:8px;object-fit:cover;flex-shrink:0;background:#e2e8f0; }
.ck-summary-info { flex:1;min-width:0; }
.ck-summary-title { font-weight:700;font-size:14px;color:#1e293b;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical; }
.ck-summary-price { font-size:20px;font-weight:800;color:#4B7BF5;margin-top:3px; }
.ck-summary-orig  { font-size:12px;color:#94a3b8;text-decoration:line-through;margin-left:4px; }
.ck-method-list { display:flex;flex-direction:column;gap:10px; }
.ck-method-opt {
    display:flex;align-items:center;gap:14px;padding:14px 16px;
    border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:border-color .15s,background .15s;
}
.ck-method-opt:hover { border-color:#4B7BF5;background:#f8faff; }
.ck-method-opt.selected { border-color:#4B7BF5;background:#eef2ff; }
.ck-method-icon { width:40px;height:40px;border-radius:10px;background:#f0f4ff;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.ck-method-icon i { font-size:20px;color:#4B7BF5; }
.ck-method-name { font-weight:600;font-size:14px;color:#1e293b; }
.ck-method-desc { font-size:12px;color:#64748b;margin-top:2px; }
.ck-btn-primary {
    display:block;width:100%;padding:14px;border-radius:10px;border:none;cursor:pointer;
    background:linear-gradient(135deg,#4B7BF5,#3a6ae8);color:#fff;font-size:15px;font-weight:700;
    margin-top:20px;transition:opacity .2s;
}
.ck-btn-primary:hover { opacity:.88; }
.ck-btn-primary:disabled { opacity:.5;cursor:not-allowed; }
.ck-label { font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px; }
.ck-input {
    width:100%;padding:11px 13px;border:2px solid #e2e8f0;border-radius:9px;
    font-size:14px;outline:none;transition:border-color .15s;color:#1e293b;background:#fff;
}
.ck-input:focus { border-color:#4B7BF5; }
.ck-bank-row { display:flex;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid #f1f5f9; }
.ck-bank-label { font-size:12px;color:#64748b;width:120px;flex-shrink:0; }
.ck-bank-value { font-size:14px;font-weight:600;color:#1e293b;flex:1; }
.ck-bank-copy  { display:none; }
.ck-success-icon { width:72px;height:72px;border-radius:50%;margin:0 auto 16px;
    background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center; }
.ck-success-icon i { font-size:36px;color:#fff; }
.ck-pending-icon { width:72px;height:72px;border-radius:50%;margin:0 auto 16px;
    background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center; }
.ck-pending-icon i { font-size:36px;color:#fff; }
.darkmode-body #ckBox { background:#1e2a3a; }
.darkmode-body .ck-summary { background:linear-gradient(135deg,#1a273e,#1e2a45);border-color:#2d3f5e; }
.darkmode-body .ck-summary-title,.darkmode-body .ck-method-name,.darkmode-body .ck-bank-value { color:#e2e8f0; }
.darkmode-body .ck-method-opt { border-color:#2d3f5e; }
.darkmode-body .ck-method-opt:hover,.darkmode-body .ck-method-opt.selected { background:#243050;border-color:#4B7BF5; }
.darkmode-body .ck-input { background:#243050;border-color:#2d3f5e;color:#e2e8f0; }
.darkmode-body #ckBox > div:first-child { border-bottom-color:#2d3f5e; }
.darkmode-body #ckTitle { color:#e2e8f0; }
</style>

<script>
// ═══════════════════════════════════════════════════════════
//  CHECKOUT FLOW
// ═══════════════════════════════════════════════════════════
(function(){
'use strict';

let _ckCourseId = 0;
let _ckCourse   = {};
let _ckStep     = 'methods';  // 'methods' | 'payment' | 'result'
let _ckMethod   = null;       // {id, name, ...}
let _ckOrderId  = 0;
let _ckPayData  = {};         // datos devueltos por orders/create

const $ = id => document.getElementById(id);

window.openCheckout = async function(courseId, courseData) {
    _ckCourseId = courseId;
    _ckCourse   = courseData || {};
    _ckStep     = 'methods';
    _ckMethod   = null;
    _ckOrderId  = 0;
    _ckPayData  = {};
    $('ckModal').style.display = 'flex';
    ckRenderLoading('Cargando métodos de pago…');

    const res = await AulaWixApi.orders.paymentMethods(courseId);
    if (!res.ok) { ckRenderError(res.message || 'Error al cargar métodos de pago.'); return; }

    const d = res.data;

    // ── WooCommerce: redirigir al checkout de WC ──────────────────────────────
    if (d.mode === 'wc') {
        ckRenderMethods(d, d.wc_methods || []);
        return;
    }

    // ── Tutor nativo ──────────────────────────────────────────────────────────
    // Precio: preferir el que viene de payment_methods, si no del objeto curso
    if (!d.final_price) {
        d.final_price = parseFloat(_ckCourse.sale_price || _ckCourse.price || 0);
        d.price       = parseFloat(_ckCourse.price || 0);
    }
    _ckPayData.pmConfig = d;

    ckRenderMethods(d, d.methods || []);
};

window.closeCheckout = function() {
    $('ckModal').style.display = 'none';
    $('ckBody').innerHTML = '';
};

window.ckBack = function() {
    if (_ckStep === 'payment') {
        _ckStep = 'methods';
        openCheckout(_ckCourseId, _ckCourse);
    }
};

// ── Render: loading ──────────────────────────────────────────────────────────
function ckRenderLoading(msg) {
    $('ckBody').innerHTML = `<div class="ck-section text-center py-5">
        <div class="spinner-border text-primary mb-3"></div>
        <p class="text-muted">${msg}</p>
    </div>`;
}

// ── Render: error ────────────────────────────────────────────────────────────
function ckRenderError(msg) {
    $('ckBody').innerHTML = `<div class="ck-section">
        <div class="alert alert-danger">${esc(msg)}</div>
    </div>`;
}

// ── Render: selección de métodos ─────────────────────────────────────────────
function ckRenderMethods(pmData, methods) {
    $('ckTitle').textContent = 'Inscribirse en el curso';
    $('ckBackBtn').style.display = 'none';
    _ckStep = 'methods';

    const sym   = pmData.currency_symbol || '$';
    const final = parseFloat(pmData.final_price || 0);
    const orig  = parseFloat(pmData.price || 0);
    const thumb = _ckCourse.thumbnail || '';

    // WC mode → un solo botón de redirect
    if (pmData.mode === 'wc') {
        $('ckBody').innerHTML = `
        <div class="ck-section">
            ${ckSummaryHtml(thumb, _ckCourse.title, sym, final, orig)}
            <p class="text-muted" style="font-size:13px;margin-bottom:16px;">
                Serás redirigido al checkout seguro para completar tu inscripción.
            </p>
            ${pmData.wc_methods && pmData.wc_methods.length ? `
            <div style="font-size:12px;color:#64748b;margin-bottom:12px;">Métodos de pago disponibles:</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
                ${pmData.wc_methods.map(m => `<span style="padding:4px 10px;background:#f1f5f9;border-radius:6px;font-size:12px;">${esc(m.name)}</span>`).join('')}
            </div>` : ''}
            <button class="ck-btn-primary" onclick="window.open('${esc(pmData.checkout_url)}','_blank')">
                <i class="ri-shopping-cart-line me-2"></i>Ir al checkout seguro
            </button>
        </div>`;
        return;
    }

    // Tutor native → selección de método
    $('ckBody').innerHTML = `
    <div class="ck-section">
        ${ckSummaryHtml(thumb, _ckCourse.title, sym, final, orig)}
        <div class="ck-label mb-2">Selecciona tu método de pago</div>
        <div class="ck-method-list" id="ckMethodList">
            ${methods.map(m => `
            <div class="ck-method-opt" data-mid="${esc(m.id)}" onclick="ckSelectMethod(this,'${esc(m.id)}')">
                <div class="ck-method-icon"><i class="${esc(m.icon || 'ri-money-dollar-circle-line')}"></i></div>
                <div>
                    <div class="ck-method-name">${esc(m.name)}</div>
                    <div class="ck-method-desc">${esc(methodDesc(m))}</div>
                </div>
            </div>`).join('')}
        </div>
        <button class="ck-btn-primary" id="ckContinueBtn" onclick="ckProceed()" disabled>
            Continuar al pago <i class="ri-arrow-right-line ms-1"></i>
        </button>
    </div>`;

    // Guardar métodos en _ckPayData para acceso posterior
    _ckPayData.methods = methods;
    _ckPayData.pmConfig = pmData;
}

window.ckSelectMethod = function(el, methodId) {
    document.querySelectorAll('.ck-method-opt').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    _ckMethod = _ckPayData.methods.find(m => m.id === methodId) || { id: methodId };
    const btn = $('ckContinueBtn');
    if (btn) btn.disabled = false;
};

window.ckProceed = async function() {
    if (!_ckMethod) return;
    _ckStep = 'payment';
    $('ckBackBtn').style.display = 'flex';
    ckRenderLoading('Preparando el pago…');

    const res = await AulaWixApi.orders.create(_ckCourseId, _ckMethod.id);
    if (!res.ok) { ckRenderError(res.message || 'Error al crear la orden.'); return; }

    const d = res.data;
    _ckOrderId = d.order_id || 0;

    switch (d.next_action) {
        case 'show_bank_details': ckRenderBankDetails(d); break;
        case 'paypal_js':         ckRenderPayPal(d);      break;
        case 'stripe_elements':   ckRenderStripe(d);      break;
        case 'payphone_box':      ckRenderPayPhone(d);    break;
        default: ckRenderError('Acción desconocida: ' + d.next_action);
    }
};

// ── Render: transferencia bancaria ───────────────────────────────────────────
function ckRenderBankDetails(d) {
    $('ckTitle').textContent = 'Transferencia bancaria';
    const sym = _ckPayData.pmConfig?.currency_symbol || '$';

    const rows = [
        d.bank_name      && ['Banco',     d.bank_name],
        d.account_name   && ['Titular',   d.account_name],
        d.account_number && ['Cuenta',    d.account_number],
        d.routing_number && ['Clave/ABA', d.routing_number],
        d.swift_code     && ['SWIFT',     d.swift_code],
        ['Monto',  sym + parseFloat(d.amount || 0).toFixed(2)],
    ].filter(Boolean);

    $('ckBody').innerHTML = `
    <div class="ck-section">
        <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:13px;color:#92400e;">
            <i class="ri-information-line me-1"></i>${esc(d.instructions || 'Realiza la transferencia con los datos de abajo.')}
        </div>
        <div style="margin-bottom:18px;">
            ${rows.map(([label, value]) => `
            <div class="ck-bank-row">
                <span class="ck-bank-label">${esc(label)}</span>
                <span class="ck-bank-value">${esc(String(value))}</span>
            </div>`).join('')}
        </div>
        <button class="ck-btn-primary" onclick="ckSubmitBankTransfer()">
            <i class="ri-shopping-cart-2-line me-2"></i>Realizar pedido
        </button>
    </div>`;
}

window.ckSubmitBankTransfer = async function() {
    ckRenderLoading('Procesando pedido…');
    const res = await AulaWixApi.orders.confirm(_ckCourseId, 'bank_transfer', { order_id: _ckOrderId });
    if (res.ok) {
        ckRenderBankPending(res.data);
    } else {
        ckRenderError(res.message || 'Error al procesar el pedido.');
    }
};

function ckRenderBankPending(data) {
    $('ckTitle').textContent = '¡Pedido recibido!';
    $('ckBackBtn').style.display = 'none';
    const sym  = _ckPayData.pmConfig?.currency_symbol || '$';
    const rows = [
        data.bank_name      && ['Banco',     data.bank_name],
        data.account_name   && ['Titular',   data.account_name],
        data.account_number && ['Cuenta',    data.account_number],
        data.routing_number && ['Clave/ABA', data.routing_number],
        data.swift_code     && ['SWIFT',     data.swift_code],
        data.amount > 0     && ['Monto',     sym + parseFloat(data.amount||0).toFixed(2)],
    ].filter(Boolean);
    const rowsHtml = rows.map(function(r) {
        return '<div class="ck-bank-row">'
             + '<span class="ck-bank-label">'  + esc(r[0])         + '</span>'
             + '<span class="ck-bank-value">'  + esc(String(r[1])) + '</span>'
             + '</div>';
    }).join('');
    const instrHtml = data.instructions
        ? '<div style="background:#fef3c7;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#92400e;">'
          + '<i class="ri-information-line me-1"></i>' + esc(data.instructions) + '</div>'
        : '';
    $('ckBody').innerHTML =
        '<div class="ck-section">'
        + '<div style="text-align:center;margin-bottom:20px;">'
        + '<div style="width:56px;height:56px;border-radius:50%;background:rgba(46,166,122,.12);display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">'
        + '<i class="ri-checkbox-circle-fill" style="font-size:30px;color:#2ea67a;"></i></div>'
        + '<h5 style="font-weight:700;margin:0 0 4px;">¡Pedido recibido!</h5>'
        + '<p style="font-size:13px;color:#6b7280;margin:0;">Revisa tu correo para ver las instrucciones.</p></div>'
        + instrHtml
        + (rows.length ? '<div style="margin-bottom:16px;">' + rowsHtml + '</div>' : '')
        + '<button onclick="closeCheckout()" class="ck-btn-primary" style="display:block;width:100%;text-align:center;">Entendido</button>'
        + '</div>';
}

// ── Render: PayPal JS SDK ────────────────────────────────────────────────────
function ckRenderPayPal(d) {
    $('ckTitle').textContent = 'Pago con PayPal';
    const sym = _ckPayData.pmConfig?.currency_symbol || '$';

    $('ckBody').innerHTML = `
    <div class="ck-section">
        <p style="font-size:13px;color:#475569;margin-bottom:20px;">
            Monto a pagar: <strong>${sym}${parseFloat(d.amount||0).toFixed(2)} ${esc(d.currency||'')}</strong>
        </p>
        <div id="paypal-button-container"></div>
        <p id="paypalStatus" style="font-size:12px;color:#64748b;text-align:center;margin-top:12px;"></p>
    </div>`;

    // Cargar PayPal JS SDK
    const script = document.createElement('script');
    const base   = d.sandbox ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
    script.src = `${base}/sdk/js?client-id=${encodeURIComponent(d.client_id)}&currency=${encodeURIComponent(d.currency||'USD')}`;
    script.onload = () => {
        if (!window.paypal) { document.getElementById('paypalStatus').textContent = 'Error al cargar PayPal.'; return; }
        window.paypal.Buttons({
            createOrder: (_data, actions) => {
                if (d.paypal_order_id) return Promise.resolve(d.paypal_order_id);
                return actions.order.create({
                    purchase_units: [{ amount: { value: String(parseFloat(d.amount||0).toFixed(2)), currency_code: d.currency||'USD' } }],
                });
            },
            onApprove: async (data) => {
                document.getElementById('paypalStatus').textContent = 'Confirmando pago…';
                const res = await AulaWixApi.orders.confirm(_ckCourseId, 'paypal', {
                    order_id:        _ckOrderId,
                    paypal_order_id: data.orderID,
                });
                if (res.ok && res.data.enrolled) {
                    ckRenderSuccess(res.data.first_lesson);
                } else if (res.ok) {
                    ckRenderPending(res.data.message || 'Pago recibido, procesando inscripción.');
                } else {
                    ckRenderError(res.message || 'Error al confirmar el pago.');
                }
            },
            onError: (err) => {
                console.error('[PayPal]', err);
                document.getElementById('paypalStatus').textContent = 'Error en el pago de PayPal. Intenta de nuevo.';
            },
            onCancel: () => {
                document.getElementById('paypalStatus').textContent = 'Pago cancelado.';
            },
        }).render('#paypal-button-container');
    };
    script.onerror = () => { document.getElementById('paypalStatus').textContent = 'No se pudo cargar PayPal.'; };
    document.head.appendChild(script);
}

// ── Render: Stripe Elements ──────────────────────────────────────────────────
function ckRenderStripe(d) {
    $('ckTitle').textContent = 'Pago con tarjeta';
    const sym = _ckPayData.pmConfig?.currency_symbol || '$';
    const pk  = _ckPayData.pmConfig?.methods?.find(m => m.id === 'stripe')?.publishable_key || '';

    $('ckBody').innerHTML = `
    <div class="ck-section">
        <p style="font-size:13px;color:#475569;margin-bottom:16px;">
            Monto a pagar: <strong>${sym}${parseFloat(d.amount||0).toFixed(2)} ${esc(d.currency||'').toUpperCase()}</strong>
        </p>
        <div class="ck-label">Datos de tu tarjeta</div>
        <div id="stripe-payment-element" style="border:2px solid #e2e8f0;border-radius:9px;padding:14px;margin-bottom:16px;background:#fff;"></div>
        <div id="stripeError" style="color:#ef4444;font-size:13px;margin-bottom:10px;display:none;"></div>
        <button class="ck-btn-primary" id="stripePayBtn" onclick="ckStripeConfirm()">
            <i class="ri-shield-check-line me-2"></i>Pagar ${sym}${parseFloat(d.amount||0).toFixed(2)}
        </button>
        <p style="font-size:11px;color:#94a3b8;text-align:center;margin-top:10px;">
            <i class="ri-lock-line me-1"></i>Pago seguro con cifrado SSL — Stripe
        </p>
    </div>`;

    // Cargar Stripe.js
    if (!window._stripeJs) {
        const s = document.createElement('script');
        s.src = 'https://js.stripe.com/v3/';
        s.onload = () => initStripeElements(pk, d.client_secret);
        document.head.appendChild(s);
        window._stripeJs = true;
    } else {
        initStripeElements(pk, d.client_secret);
    }
}

let _stripe, _stripeElements;
function initStripeElements(pk, clientSecret) {
    if (!pk || !window.Stripe) { document.getElementById('stripe-payment-element').textContent = 'Stripe no disponible.'; return; }
    _stripe  = window.Stripe(pk);
    _stripeElements = _stripe.elements({ clientSecret });
    const el = _stripeElements.create('payment');
    el.mount('#stripe-payment-element');
    window._stripePaymentElement = el;
    window._stripeClientSecret   = clientSecret;
}

window.ckStripeConfirm = async function() {
    const btn = $('stripePayBtn');
    if (!_stripe || !_stripeElements) return;
    const errEl = $('stripeError');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando…'; }
    if (errEl) errEl.style.display = 'none';

    const { error, paymentIntent } = await _stripe.confirmPayment({
        elements: _stripeElements,
        redirect: 'if_required',
    });

    if (error) {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ri-shield-check-line me-2"></i>Pagar de nuevo'; }
        if (errEl) { errEl.textContent = error.message; errEl.style.display = ''; }
        return;
    }

    if (paymentIntent?.status === 'succeeded') {
        ckRenderLoading('Confirmando inscripción…');
        const res = await AulaWixApi.orders.confirm(_ckCourseId, 'stripe', {
            order_id: _ckOrderId,
            pi_id:    paymentIntent.id,
        });
        if (res.ok && res.data.enrolled) {
            ckRenderSuccess(res.data.first_lesson);
        } else if (res.ok) {
            ckRenderPending(res.data.message || 'Pago recibido, procesando inscripción.');
        } else {
            ckRenderError(res.message || 'Error al confirmar el pago.');
        }
    }
};

// ── Render: PayPhone Cajita de Pagos ────────────────────────────────────────
function ckRenderPayPhone(d) {
    $('ckTitle').textContent = 'Pagar con PayPhone';
    const sym = _ckPayData.pmConfig?.currency_symbol || '$';

    $('ckBody').innerHTML = `
    <div class="ck-section">
        <p style="font-size:13px;color:#475569;margin-bottom:16px;">
            Monto a pagar: <strong>${sym}${parseFloat((d.amount_cents||0)/100).toFixed(2)} ${esc(d.currency||'')}</strong>
        </p>
        <div id="pp-button"></div>
        <p id="ppStatus" style="font-size:12px;color:#64748b;text-align:center;margin-top:12px;"></p>
    </div>`;

    function initPP() {
        new window.PPaymentButtonBox({
            token:               d.token,
            storeId:             d.store_id || '',
            amount:              d.amount_cents,
            amountWithoutTax:    d.amount_cents,
            currency:            d.currency || 'USD',
            clientTransactionId: d.client_tx_id,
            reference:           d.reference || ('Curso ' + _ckCourseId),
            lang:                'es',
            defaultMethod:       'card',
        }).render('pp-button');
    }

    // Cargar CSS y JS del SDK si no están ya
    if (!document.getElementById('pp-sdk-css')) {
        const link = document.createElement('link');
        link.id   = 'pp-sdk-css';
        link.rel  = 'stylesheet';
        link.href = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.css';
        document.head.appendChild(link);
    }
    if (window.PPaymentButtonBox) {
        initPP();
    } else {
        const script = document.createElement('script');
        script.src = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js';
        script.onload  = initPP;
        script.onerror = () => { const el = document.getElementById('ppStatus'); if (el) el.textContent = 'No se pudo cargar PayPhone. Intenta de nuevo.'; };
        document.head.appendChild(script);
    }
}

// ── Render: éxito ────────────────────────────────────────────────────────────
function ckRenderSuccess(firstLesson) {
    $('ckTitle').textContent = '¡Inscripción exitosa!';
    $('ckBackBtn').style.display = 'none';
    const lessonUrl = firstLesson?.id ? `leccion.php?id=${firstLesson.id}&course=${_ckCourseId}` : `mis-cursos.php`;
    $('ckBody').innerHTML = `
    <div class="ck-section text-center py-4">
        <div class="ck-success-icon"><i class="ri-graduation-cap-fill"></i></div>
        <h5 style="font-weight:700;margin-bottom:8px;">¡Pago aprobado!</h5>
        <p class="text-muted" style="font-size:14px;margin-bottom:24px;">Ya estás inscrito. ¡Empieza a aprender ahora mismo!</p>
        <a href="${esc(lessonUrl)}" class="ck-btn-primary" style="text-decoration:none;display:inline-block;width:auto;padding:13px 32px;">
            <i class="ri-play-circle-fill me-2"></i>Ir al curso
        </a>
    </div>`;
}

// ── Render: pendiente ────────────────────────────────────────────────────────
function ckRenderPending(msg) {
    $('ckTitle').textContent = 'Pago en revisión';
    $('ckBackBtn').style.display = 'none';
    $('ckBody').innerHTML = `
    <div class="ck-section text-center py-4">
        <div class="ck-pending-icon"><i class="ri-time-fill"></i></div>
        <h5 style="font-weight:700;margin-bottom:8px;">Comprobante recibido</h5>
        <p class="text-muted" style="font-size:14px;margin-bottom:24px;">${esc(msg)}</p>
        <button onclick="closeCheckout()" class="ck-btn-primary" style="display:inline-block;width:auto;padding:13px 32px;">
            Entendido
        </button>
    </div>`;
}

// ── Utilidades ───────────────────────────────────────────────────────────────
function ckSummaryHtml(thumb, title, sym, final, orig) {
    const discHtml = (orig && orig > final)
        ? `<span class="ck-summary-orig">${sym}${parseFloat(orig).toFixed(2)}</span>` : '';
    return `<div class="ck-summary">
        ${thumb ? `<img src="${esc(thumb)}" class="ck-summary-img" alt="">` : `<div class="ck-summary-img" style="display:flex;align-items:center;justify-content:center;"><i class="ri-book-open-line" style="font-size:24px;color:#94a3b8;"></i></div>`}
        <div class="ck-summary-info">
            <div class="ck-summary-title">${esc(title||'Curso')}</div>
            <div class="ck-summary-price">${sym}${parseFloat(final||0).toFixed(2)} ${discHtml}</div>
        </div>
    </div>`;
}

function methodDesc(m) {
    if (m.id === 'paypal')        return 'Paga con tu cuenta PayPal de forma segura';
    if (m.id === 'stripe')        return 'Visa, Mastercard, American Express y más';
    if (m.id === 'bank_transfer') return 'Transfiere a nuestra cuenta — recibirás instrucciones por correo';
    if (m.id === 'payphone')      return 'Visa, Mastercard y saldo PayPhone';
    return '';
}

window.ckCopy = function(text, btn) {
    navigator.clipboard?.writeText(text).then(() => {
        btn.innerHTML = '<i class="ri-check-line" style="color:#22c55e;"></i>';
        setTimeout(() => { btn.innerHTML = '<i class="ri-file-copy-line"></i>'; }, 2000);
    });
};

function esc(s) {
    return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Cerrar con Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCheckout(); });
// Cerrar al hacer clic fuera
$('ckModal').addEventListener('click', e => { if (e.target === $('ckModal')) closeCheckout(); });

})();
</script>

<style>
/* ═══════════════════════════════════════════════════════════════
   COURSE DETAIL PAGE — AulaWix (Light Design)
   ═══════════════════════════════════════════════════════════════ */
.tcd-page{width:100%;}

/* ── Main grid ───────────────────────────────────────────────── */
.tcd-main{
    display:grid;
    grid-template-columns:1fr 350px;
    grid-template-areas:"content sidebar";
    gap:28px;
    padding:20px 0 40px;
    align-items:start;
}
.tcd-content-col{grid-area:content;}
.tcd-sidebar{grid-area:sidebar;}
@media(max-width:1199px){.tcd-main{grid-template-columns:1fr 310px;gap:20px;}}
@media(max-width:991px){
    .tcd-main{grid-template-columns:1fr;grid-template-areas:"sidebar""content";padding-top:16px;gap:14px;}
}

/* ── Course header: flex row text + image ───────────────────── */
.tcd-course-header{
    display:flex;gap:28px;align-items:flex-start;
    padding-bottom:20px;margin-bottom:4px;
}
.tcd-header-text{flex:1;min-width:0;}
.tcd-header-img{
    width:280px;flex-shrink:0;
    border-radius:14px;overflow:hidden;
    aspect-ratio:16/9;position:relative;
    background:linear-gradient(135deg,#1e3a5f,#0f172a);
    box-shadow:0 4px 20px rgba(0,0,0,.15);
    align-self:flex-start;
}
@media(max-width:1199px){.tcd-header-img{width:240px;}}
@media(max-width:991px){
    .tcd-course-header{flex-direction:column-reverse;gap:16px;}
    .tcd-header-img{width:100%;aspect-ratio:16/9;}
}

/* ── Breadcrumb ─────────────────────────────────────────────── */
.tcd-breadcrumb{display:flex;align-items:center;gap:4px;font-size:12px;color:#94a3b8;margin-bottom:14px;flex-wrap:wrap;}
.tcd-breadcrumb a{color:#4B7BF5;text-decoration:none;}
.tcd-breadcrumb a:hover{color:#3a6ae4;text-decoration:underline;}
.tcd-breadcrumb .sep{color:#cbd5e1;line-height:1;}
.darkmode-body .tcd-breadcrumb a{color:#93c5fd;}

/* ── Category badge ─────────────────────────────────────────── */
.tcd-cat-badge{
    display:inline-block;
    background:#e0f2fe;color:#0369a1;
    font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;
    padding:3px 12px;border-radius:50px;margin-bottom:12px;
}
.darkmode-body .tcd-cat-badge{background:#1e3a5f;color:#60a5fa;}

/* ── Course title ────────────────────────────────────────────── */
.tcd-hero-title{font-size:1.85rem;font-weight:800;line-height:1.25;color:#0f172a;margin-bottom:12px;}
@media(max-width:767px){.tcd-hero-title{font-size:1.3rem;}}
.darkmode-body .tcd-hero-title{color:#f1f5f9;}

/* ── Excerpt ─────────────────────────────────────────────────── */
.tcd-hero-excerpt{font-size:.94rem;line-height:1.7;color:#475569;margin-bottom:14px;max-width:720px;}
.darkmode-body .tcd-hero-excerpt{color:#94a3b8;}

/* ── Rating / Meta row ───────────────────────────────────────── */
.tcd-rating-row{display:flex;flex-wrap:wrap;align-items:center;gap:12px;font-size:13px;color:#64748b;margin-bottom:12px;}
.tcd-stars{position:relative;display:inline-block;font-size:16px;line-height:1;}
.tcd-stars-bg{color:rgba(251,191,36,.3);}
.tcd-stars-fill{position:absolute;left:0;top:0;overflow:hidden;white-space:nowrap;color:#fbbf24;}
.tcd-rating-val{font-weight:700;color:#f59e0b;font-size:15px;}
.tcd-dot{color:#cbd5e1;}

/* ── Instructor line ─────────────────────────────────────────── */
.tcd-hero-inst{display:flex;align-items:center;gap:9px;font-size:13px;color:#64748b;margin-top:6px;margin-bottom:6px;}
.tcd-hero-inst img{width:28px;height:28px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;}
.tcd-hero-inst a{color:#4B7BF5;text-decoration:none;font-weight:600;}
.tcd-hero-inst a:hover{text-decoration:underline;}

/* ── Stats Bar (integrated in header) ───────────────────────── */
.tcd-stats-bar{
    display:flex;flex-wrap:wrap;gap:16px;align-items:center;
    padding:14px 0;margin:14px 0 0;
    border-top:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9;
}
.darkmode-body .tcd-stats-bar{border-color:#2d3f5e;}
.tcd-stat-item{display:flex;align-items:center;gap:7px;font-size:13px;color:#64748b;}
.tcd-stat-item i{color:#4B7BF5;font-size:15px;}
.tcd-stat-item strong{color:#374151;font-weight:700;}
.darkmode-body .tcd-stat-item{color:#94a3b8;}
.darkmode-body .tcd-stat-item strong{color:#e2e8f0;}

/* ── Enrollment card ─────────────────────────────────────────── */
.tcd-enrollment-card{
    background:#fff;border-radius:16px;
    box-shadow:0 4px 28px rgba(0,0,0,.1);
    overflow:hidden;position:sticky;top:80px;
    border:1px solid #e9ecef;
}
.darkmode-body .tcd-enrollment-card{background:#1e2a45;box-shadow:0 4px 24px rgba(0,0,0,.4);border-color:#2d3f5e;}

.tcd-preview-icon{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;}
.tcd-preview-icon i{font-size:3.5rem;color:rgba(255,255,255,.55);}
.tcd-card-body{padding:20px;}

/* Course type badge */
.tcd-course-badge{
    display:inline-flex;align-items:center;gap:6px;
    background:#dcfce7;color:#16a34a;
    font-size:12px;font-weight:700;
    padding:5px 14px;border-radius:50px;margin-bottom:10px;
}
.tcd-course-badge i{font-size:13px;}
.darkmode-body .tcd-course-badge{background:#14532d;color:#4ade80;}

/* Price */
.tcd-price-free{font-size:30px;font-weight:800;color:#0f172a;line-height:1.1;}
.darkmode-body .tcd-price-free{color:#f1f5f9;}
.tcd-price-row{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;}
.tcd-price-main{font-size:30px;font-weight:800;color:#0f172a;}
.tcd-price-orig{font-size:16px;color:#94a3b8;text-decoration:line-through;}
.tcd-price-badge{font-size:11px;font-weight:700;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;}
.darkmode-body .tcd-price-main{color:#f1f5f9;}
.tcd-price-sub{font-size:13px;color:#64748b;margin:3px 0 0;line-height:1.4;}
.darkmode-body .tcd-price-sub{color:#94a3b8;}

/* Buttons */
.tcd-btn-primary{
    display:block;width:100%;text-align:center;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#fff;font-size:15px;font-weight:700;
    padding:13px 20px;border-radius:10px;border:none;
    text-decoration:none;cursor:pointer;transition:opacity .2s;
    margin-bottom:10px;letter-spacing:.2px;
}
.tcd-btn-primary:hover{opacity:.88;color:#fff;}
.tcd-btn-outline{
    display:block;width:100%;text-align:center;
    background:transparent;color:#374151;
    font-size:14px;font-weight:600;
    padding:12px 20px;border-radius:10px;
    border:1.5px solid #d1d5db;text-decoration:none;
    cursor:pointer;transition:background .2s,border-color .2s;margin-bottom:10px;
}
.tcd-btn-outline:hover{background:#f8faff;border-color:#4B7BF5;color:#4B7BF5;}
.darkmode-body .tcd-btn-outline{color:#94a3b8;border-color:#2d3f5e;}
.darkmode-body .tcd-btn-outline:hover{background:#1a273e;border-color:#4B7BF5;color:#60a5fa;}

/* Progress in card */
.tcd-card-progress-label{display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:#475569;margin-bottom:7px;}
.tcd-card-progress-bar{height:8px;background:#e2e8f0;border-radius:10px;overflow:hidden;}
.tcd-card-progress-fill{height:100%;background:linear-gradient(90deg,#4B7BF5,#22c55e);border-radius:10px;transition:width .6s ease;}
.darkmode-body .tcd-card-progress-bar{background:#2d3f5e;}

/* Guarantee */
.tcd-guarantee{
    display:flex;align-items:flex-start;gap:12px;
    padding:12px 14px;background:#f8faff;border-radius:10px;
    margin:12px 0 14px;
}
.tcd-guarantee>i{font-size:20px;color:#4B7BF5;flex-shrink:0;margin-top:2px;}
.tcd-guarantee-title{font-size:13px;font-weight:700;color:#1e293b;margin-bottom:3px;}
.tcd-guarantee-sub{font-size:12px;color:#64748b;line-height:1.5;}
.darkmode-body .tcd-guarantee{background:#243050;}
.darkmode-body .tcd-guarantee-title{color:#e2e8f0;}
.darkmode-body .tcd-guarantee-sub{color:#94a3b8;}

/* Includes */
.tcd-includes{padding-top:14px;border-top:1px solid #f1f5f9;}
.darkmode-body .tcd-includes{border-top-color:#2d3f5e;}
.tcd-includes-title{font-size:11px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;}
.darkmode-body .tcd-includes-title{color:#94a3b8;}
.tcd-includes-list{list-style:none;padding:0;margin:0;}
.tcd-includes-list li{display:flex;align-items:center;gap:10px;font-size:13px;color:#374151;padding:6px 0;}
.tcd-includes-list li i{color:#22c55e;font-size:15px;flex-shrink:0;}
.darkmode-body .tcd-includes-list li{color:#94a3b8;}

/* Share */
.tcd-share{display:flex;align-items:center;gap:8px;margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;flex-wrap:wrap;}
.darkmode-body .tcd-share{border-top-color:#2d3f5e;}
.tcd-share-label{font-size:12px;color:#94a3b8;flex-shrink:0;}
.tcd-share-btns{display:flex;gap:6px;}
.tcd-share-btn{
    width:32px;height:32px;border-radius:8px;border:none;cursor:pointer;
    display:flex;align-items:center;justify-content:center;font-size:15px;
    text-decoration:none;transition:opacity .2s;
}
.tcd-share-btn:hover{opacity:.8;}
.tcd-share-wa{background:#25d366;color:#fff;}
.tcd-share-fb{background:#1877f2;color:#fff;}
.tcd-share-x{background:#000;color:#fff;}
.tcd-share-copy{background:#f1f5f9;color:#4B7BF5;}
.darkmode-body .tcd-share-copy{background:#2d3f5e;}

/* ── Content sections ────────────────────────────────────────── */
.tcd-section{
    background:#fff;border-radius:12px;padding:24px;
    margin-bottom:16px;
    box-shadow:0 1px 4px rgba(0,0,0,.06);
    border:1px solid #e9ecef;
}
.darkmode-body .tcd-section{background:#1e2a45;border-color:#2d3f5e;}

.tcd-section-head{
    display:flex;justify-content:space-between;align-items:center;
    flex-wrap:wrap;gap:8px;
    margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f1f5f9;
}
.darkmode-body .tcd-section-head{border-bottom-color:#2d3f5e;}

.tcd-section-title{
    font-size:1rem;font-weight:700;color:#1e293b;
    margin-bottom:18px;padding-bottom:14px;
    border-bottom:2px solid #f1f5f9;
    display:flex;align-items:center;gap:9px;
}
.tcd-section-head .tcd-section-title{margin-bottom:0;padding-bottom:0;border-bottom:none;}
.tcd-section-title i{color:#4B7BF5;font-size:18px;}
.darkmode-body .tcd-section-title{color:#e2e8f0;border-bottom-color:#2d3f5e;}
.tcd-curr-stats{font-size:12px;color:#64748b;white-space:nowrap;}

/* Lo que aprenderás */
.tcd-wwl-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
@media(max-width:575px){.tcd-wwl-grid{grid-template-columns:1fr;}}
.tcd-wwl-item{display:flex;align-items:flex-start;gap:9px;font-size:13px;color:#374151;line-height:1.55;}
.tcd-wwl-item i{color:#22c55e;font-size:15px;flex-shrink:0;margin-top:2px;}
.darkmode-body .tcd-wwl-item{color:#cbd5e1;}

/* Requisitos / Audiencia */
.tcd-req-list{list-style:none;padding:0;margin:0;}
.tcd-req-list li{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#374151;padding:7px 0;border-bottom:1px solid #f8faff;line-height:1.5;}
.tcd-req-list li:last-child{border-bottom:none;}
.tcd-req-list li::before{content:'✓';color:#22c55e;font-weight:700;flex-shrink:0;}
.darkmode-body .tcd-req-list li{color:#cbd5e1;border-bottom-color:#243050;}

/* ── Curriculum ──────────────────────────────────────────────── */
.tcd-topic{border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:8px;}
.darkmode-body .tcd-topic{border-color:#2d3f5e;}

.tcd-topic-head{
    display:flex;align-items:center;gap:12px;
    padding:13px 18px;background:#f8faff;
    cursor:pointer;user-select:none;transition:background .15s;
}
.tcd-topic-head:hover{background:#eef2ff;}
.tcd-topic-head.is-open{background:#eef2ff;}
.darkmode-body .tcd-topic-head{background:#243050;}
.darkmode-body .tcd-topic-head:hover,
.darkmode-body .tcd-topic-head.is-open{background:#1a2744;}

.tcd-topic-num{font-size:12px;font-weight:700;color:#4B7BF5;flex-shrink:0;}
.tcd-topic-title{font-size:13px;font-weight:600;color:#1e293b;flex:1;line-height:1.4;}
.darkmode-body .tcd-topic-title{color:#e2e8f0;}
.tcd-topic-meta{font-size:11px;color:#64748b;white-space:nowrap;flex-shrink:0;}
.tcd-topic-arrow{font-size:20px;color:#4B7BF5;flex-shrink:0;transition:transform .25s ease;}
.tcd-topic-arrow.is-open{transform:rotate(180deg);}

.tcd-topic-body{display:none;border-top:1px solid #f1f5f9;}
.tcd-topic-body.show{display:block;}
.darkmode-body .tcd-topic-body{border-top-color:#2d3f5e;}

.tcd-lesson-item{
    display:flex;align-items:center;gap:12px;
    padding:11px 18px;font-size:13px;color:#374151;
    text-decoration:none;transition:background .12s;
    border-bottom:1px solid #f8faff;
}
.tcd-lesson-item:last-child{border-bottom:none;}
.tcd-lesson-item:hover:not(.locked){background:#f8faff;}
.tcd-lesson-item.locked{cursor:default;opacity:.7;}
.tcd-lesson-item.tcd-lesson-guest:hover{background:#f0f4ff;}
.tcd-lesson-item.tcd-lesson-guest .tcd-lesson-lock{color:#94a3b8;}
.darkmode-body .tcd-lesson-item{color:#cbd5e1;border-bottom-color:#1e2a45;}
.darkmode-body .tcd-lesson-item:hover:not(.locked){background:#1e2a45;}
.darkmode-body .tcd-lesson-item.tcd-lesson-guest:hover{background:#1a273e;}

.tcd-lesson-icon{width:32px;height:32px;border-radius:7px;background:#f0f4ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.tcd-lesson-icon i{color:#4B7BF5;font-size:14px;}
.darkmode-body .tcd-lesson-icon{background:#243050;}

.tcd-lesson-title{flex:1;line-height:1.4;}
.tcd-lesson-dur{font-size:11px;color:#94a3b8;white-space:nowrap;}
.tcd-lesson-done{color:#22c55e;font-size:17px;flex-shrink:0;}
.tcd-lesson-lock{color:#d1d5db;font-size:17px;flex-shrink:0;}

.tcd-lock-notice{
    display:flex;align-items:center;gap:8px;
    font-size:12px;color:#94a3b8;padding:10px 18px;
    background:#fafbff;border-top:1px solid #f1f5f9;
}
.tcd-lock-link{color:#4B7BF5;text-decoration:none;font-weight:600;}
.tcd-lock-link:hover{text-decoration:underline;}
.darkmode-body .tcd-lock-notice{background:#243050;border-top-color:#2d3f5e;}

/* ── Instructor ──────────────────────────────────────────────── */
.tcd-instructor-wrap{display:flex;align-items:flex-start;gap:18px;}
@media(max-width:575px){.tcd-instructor-wrap{flex-direction:column;align-items:center;text-align:center;}}
.tcd-inst-avatar{width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #e2e8f0;flex-shrink:0;}
.darkmode-body .tcd-inst-avatar{border-color:#2d3f5e;}
.tcd-inst-name{font-size:16px;font-weight:700;color:#1e293b;margin-bottom:4px;}
.darkmode-body .tcd-inst-name{color:#e2e8f0;}
.tcd-inst-bio{font-size:13px;color:#64748b;line-height:1.65;margin:0;}

/* ── Read-more ─────────────────────────────────────────────── */
.tcd-read-more-wrap{position:relative;}
.tcd-read-more-wrap.collapsed .tcd-read-more-content{max-height:200px;overflow:hidden;}
.tcd-read-more-wrap.collapsed::after{
    content:'';position:absolute;bottom:0;left:0;right:0;height:80px;
    background:linear-gradient(transparent,#fff);pointer-events:none;
}
.darkmode-body .tcd-read-more-wrap.collapsed::after{background:linear-gradient(transparent,#1e2a45);}
.tcd-read-more-btn{
    display:block;text-align:center;margin-top:12px;
    font-size:13px;font-weight:600;color:#4B7BF5;
    cursor:pointer;background:none;border:none;padding:0;
}
.tcd-read-more-btn:hover{text-decoration:underline;}

.tcd-desc-content{font-size:14px;line-height:1.75;color:#374151;}
.tcd-desc-content h1,.tcd-desc-content h2,.tcd-desc-content h3{font-weight:700;margin-top:1.2em;margin-bottom:.5em;}
.tcd-desc-content ul,.tcd-desc-content ol{padding-left:1.5rem;}
.tcd-desc-content img{max-width:100%;border-radius:8px;margin:8px 0;}
.tcd-desc-content a{color:#4B7BF5;}
.tcd-desc-content p{margin-bottom:.75em;}
.darkmode-body .tcd-desc-content{color:#cbd5e1;}

/* ── Hero CTA (hidden, kept for JS compat) ───────────────────── */
#heroCta{display:none !important;}

/* ── Mobile sticky CTA bar ───────────────────────────────────── */
#mobileCtaBar{display:none;}
#mobileCtaBtn:hover{opacity:.88;}
#mobileCtaBtn:disabled{opacity:.6;cursor:not-allowed;}
.darkmode-body #mobileCtaBar{background:#1e2a3a;border-top-color:#2d3a4e;}
</style>

<?php
$extra_scripts = <<<SCRIPTS
<script>window.__COURSE_INIT__={$js_init};</script>
<script>
(async function () {
    'use strict';

    const \$  = id => document.getElementById(id);
    const show = id => \$(id) && \$(id).classList.remove('d-none');
    const hide = id => \$(id) && \$(id).classList.add('d-none');
    const set  = (id, html) => { if (\$(id)) \$(id).innerHTML = html; };
    const setText = (id, t) => { if (\$(id)) \$(id).textContent = t; };

    function escHtml(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function fmtNum(n) {
        n = parseInt(n) || 0;
        return n >= 1000 ? (n/1000).toFixed(1).replace('.0','') + 'k' : String(n);
    }
    function fmtDate(d) {
        return d ? new Date(d).toLocaleDateString('es',{year:'numeric',month:'short',day:'numeric'}) : '';
    }
    function renderStars(r) {
        const pct = Math.round(r/5*100);
        return '<span class="tcd-stars" title="'+r.toFixed(1)+'/5"><span class="tcd-stars-bg">★★★★★</span><span class="tcd-stars-fill" style="width:'+pct+'%">★★★★★</span></span>';
    }
    function showError(msg) {
        hide('tcdLoading');
        const el = \$('tcdError');
        if (el) { el.textContent = msg; el.classList.remove('d-none'); }
    }

    // ── Determinar curso ─────────────────────────────────────────
    const INIT   = window.__COURSE_INIT__;
    const params = new URLSearchParams(location.search);
    let courseId  = INIT?.id || parseInt(params.get('id')) || 0;
    const slug    = INIT?.slug || params.get('slug') || '';

    if (!courseId && !slug) { showError('No se especificó ningún curso.'); return; }

    // ── Fetch ────────────────────────────────────────────────────
    const res = courseId
        ? await AulaWixApi.courses.get(courseId)
        : await AulaWixApi.courses.getBySlug(slug);

    if (!res?.ok || !res.data) { showError(res?.message || 'Curso no encontrado.'); return; }

    const c  = res.data;
    courseId = courseId || c.id;

    // URL canónica con slug
    if (c.slug && !slug && history.replaceState) {
        history.replaceState(null, '', '/curso/' + encodeURIComponent(c.slug));
    }
    document.title = c.title + ' — AulaWix';

    // ── HERO ─────────────────────────────────────────────────────
    setText('tcdBreadTitle', c.title);
    setText('tcdTitle', c.title);

    if (c.categoryname) {
        const cb = \$('tcdCatBadge');
        cb.textContent = c.categoryname;
        cb.style.display = '';
    }

    const tmp = document.createElement('div');
    tmp.innerHTML = c.excerpt || '';
    const ex = (tmp.textContent || '').trim();
    if (ex) setText('tcdExcerpt', ex); else \$('tcdExcerpt').remove();

    const rHtml = c.rating > 0
        ? renderStars(c.rating) + '<span class="tcd-rating-val ms-1">'+c.rating.toFixed(1)+'</span><span class="tcd-dot">•</span>'
        : '';
    set('tcdMeta', rHtml
        + '<span><i class="ri-group-line me-1"></i>' + fmtNum(c.enrolled_count) + ' estudiantes</span>'
        + '<span class="tcd-dot">•</span>'
        + '<span><i class="ri-calendar-line me-1"></i>Publicado ' + fmtDate(c.date) + '</span>'
    );

    const inst0 = c.instructors?.[0];
    if (inst0) {
        \$('tcdInstAvatar').src = inst0.avatar_url || 'assets/images/avatar.png';
        setText('tcdInstName', inst0.name);
        \$('tcdInstLine').style.display = 'flex';
    }
    if (c.modified) {
        setText('tcdUpdateText', 'Última actualización: ' + fmtDate(c.modified));
        \$('tcdLastUpdate').style.display = '';
    }

    // ── STATS BAR ────────────────────────────────────────────────
    const st = c.stats || {};
    setText('sbarTopics',   st.topic_count   || 0);
    setText('sbarLessons',  st.lesson_count  || c.lesson_count || 0);
    setText('sbarStudents', fmtNum(st.enrolled_count || c.enrolled_count || 0));

    if (c.level) {
        const lm = {beginner:'Principiante',intermediate:'Intermedio',advanced:'Avanzado',all_levels:'Todos los niveles'};
        setText('sbarLevel', lm[c.level] || c.level);
        \$('sbarLevelWrap').style.display = '';
    }
    if (c.duration && typeof c.duration === 'string') { setText('sbarDur', c.duration); \$('sbarDurWrap').style.display = ''; }
    if (c.language) { setText('sbarLang', c.language); \$('sbarLangWrap').style.display = ''; }

    // ── SIDEBAR ──────────────────────────────────────────────────
    if (c.thumbnail) {
        const img = \$('tcdPreviewImg');
        img.src = c.thumbnail; img.alt = c.title;
        img.style.display = 'block';
        \$('tcdPreviewIcon').style.display = 'none';
    }

    renderPrice(c);

    const isAuth     = AulaWixApi.auth.isLoggedIn();
    const isEnrolled = !!c.enrolled;
    const isFree     = (c.price_type === 'free' || !c.price || +c.price === 0);

    // Primera lección disponible (se rellena al cargar curriculum)
    let _firstLesson = null;
    let _firstIncompleteLesson = null;
    // Promesa pre-cargada para usuarios inscritos — resuelve el ID de lección al cargar el curso
    let _firstLessonPromise = null;

    function _notify(icon, title, text) {
        if (window.Swal) {
            Swal.fire({ icon, title, text, confirmButtonColor: '#4B7BF5', confirmButtonText: 'Entendido' });
        } else {
            alert(title + (text ? '\\n' + text : ''));
        }
    }

    // Helper: muestra estado de carga en los botones CTA y devuelve función de restauración
    function _ctaBtnsLoading(ids, loadingHtml) {
        const saved = {};
        ids.forEach(id => {
            const b = \$(id);
            if (!b) return;
            saved[id] = b.innerHTML;
            b.disabled = true;
            b.innerHTML = loadingHtml;
        });
        return function restore() {
            ids.forEach(id => {
                const b = \$(id);
                if (b && saved[id] !== undefined) { b.disabled = false; b.innerHTML = saved[id]; }
            });
        };
    }

    // Resuelve el ID de la primera lección pendiente del curso (primera incompleta o primera total)
    async function _resolveFirstLessonId() {
        // 1. Datos ya en memoria (curriculum ya cargó)
        const local = (_firstIncompleteLesson || _firstLesson)?.id;
        if (local) return local;

        // 2. Llamar a la API de topics
        const tr = await AulaWixApi.courses.topics(courseId);
        if (tr?.ok && Array.isArray(tr.data) && tr.data.length) {
            let firstId = null, incompleteId = null;
            for (const topic of tr.data) {
                for (const l of (topic.lessons || [])) {
                    if (!firstId) firstId = l.id;
                    if (!l.completed && !incompleteId) incompleteId = l.id;
                }
            }
            const id = incompleteId || firstId;
            if (id) return id;
        }

        // 3. Fallback: inscripción idempotente (devuelve first_lesson aunque ya esté inscrito)
        if (courseId) {
            const er = await AulaWixApi.courses.enroll(courseId);
            if (er?.ok && er.data?.first_lesson?.id) return er.data.first_lesson.id;
        }

        return null;
    }

    // Pre-cargar la primera lección tan pronto como sabemos que el usuario está inscrito
    if (isEnrolled && courseId) {
        _firstLessonPromise = _resolveFirstLessonId();
    }

    window.enrollAndStart = async function() {
        const restore = _ctaBtnsLoading(
            ['btnFreeEnroll','heroFreeBtn','mobileCtaBtn'],
            '<span class="spinner-border spinner-border-sm me-2"></span>Inscribiendo…'
        );
        try {
            const r = await AulaWixApi.courses.enroll(courseId);
            if (r?.ok) {
                const lessonId = r.data?.first_lesson?.id || await _resolveFirstLessonId();
                if (lessonId) {
                    location.href = 'leccion.php?id=' + lessonId + '&course=' + courseId;
                } else {
                    restore();
                    _notify('error', 'Sin lecciones', 'Este curso no tiene lecciones disponibles todavía.');
                }
            } else {
                restore();
                _notify('error', 'No se pudo inscribir', r?.message || 'Inténtalo de nuevo.');
            }
        } catch(e) {
            restore();
            _notify('error', 'Error de conexión', 'Verifica tu conexión e inténtalo de nuevo.');
        }
    };

    window.enrollPaid = function() { openCheckout(courseId, c); };

    window.goToLesson = async function() {
        const restore = _ctaBtnsLoading(
            ['btnContinue','heroContinueBtn','mobileCtaBtn'],
            '<span class="spinner-border spinner-border-sm me-1"></span>Cargando…'
        );
        try {
            // Usar la promesa pre-cargada (ya resuelta o en curso) o resolver ahora
            const lessonId = await (_firstLessonPromise || _resolveFirstLessonId());
            if (lessonId) {
                location.href = 'leccion.php?id=' + lessonId + '&course=' + courseId;
                // No restaurar — la página va a redirigir
            } else {
                restore();
                _notify('error', 'Sin lecciones disponibles', 'No se encontraron lecciones en este curso. Intenta recargar la página.');
            }
        } catch(e) {
            restore();
            _notify('error', 'Error de conexión', 'No se pudo cargar el curso. Verifica tu conexión e inténtalo de nuevo.');
        }
    };

    // URL de retorno tras login/registro
    const coursePageUrl = location.href;
    const loginUrl      = '/login.php?redirect='    + encodeURIComponent(coursePageUrl);
    const registerUrl   = '/registro.php?redirect=' + encodeURIComponent(coursePageUrl);
    if (\$('btnGuest'))      \$('btnGuest').href      = registerUrl;
    if (\$('btnGuestLogin')) \$('btnGuestLogin').href = loginUrl;
    if (\$('heroGuestBtn'))  \$('heroGuestBtn').href  = registerUrl;

    // ── Mostrar botones correctos según estado ────────────────────
    function setupCtaButtons() {
        // Sidebar
        if (isEnrolled) {
            show('sideEnrolled'); hide('sideNotEnrolled');
            const pct = c.progress || 0;
            setText('cardPct', pct + '%');
            if (\$('cardBar')) \$('cardBar').style.width = pct + '%';
            // Texto del botón según progreso
            const btnC = \$('btnContinue');
            if (btnC) {
                btnC.innerHTML = pct > 0
                    ? '<i class="ri-play-circle-line me-2"></i>Continuar curso'
                    : '<i class="ri-play-circle-fill me-2"></i>Empezar el aprendizaje';
            }
        } else {
            hide('sideEnrolled'); show('sideNotEnrolled');
            if (!isAuth) {
                show('btnGuest'); show('btnGuestLogin');
                hide('btnEnroll'); hide('freeEnroll');
            } else if (isFree) {
                show('freeEnroll'); hide('btnEnroll'); hide('btnGuest'); hide('btnGuestLogin');
            } else {
                show('btnEnroll'); hide('freeEnroll'); hide('btnGuest'); hide('btnGuestLogin');
            }
        }

        // Hero CTA
        \$('heroCta').style.display = '';
        if (isEnrolled) {
            const pct = c.progress || 0;
            const hcb = \$('heroContinueBtn');
            if (hcb) hcb.innerHTML = pct > 0
                ? '<i class="ri-play-circle-fill me-2"></i>Continuar curso'
                : '<i class="ri-play-circle-fill me-2"></i>Empezar el aprendizaje';
            \$('heroContinueBtn').style.display = '';
        } else if (!isAuth) {
            \$('heroGuestBtn').style.display = '';
        } else if (isFree) {
            \$('heroFreeBtn').style.display = '';
        } else {
            \$('heroPaidBtn').style.display = '';
        }

        // Barra móvil (solo < 992px)
        if (window.innerWidth < 992) {
            const bar = \$('mobileCtaBar');
            const btn = \$('mobileCtaBtn');
            if (bar && btn) {
                if (isEnrolled) {
                    const pct = c.progress || 0;
                    btn.innerHTML = pct > 0
                        ? '<i class="ri-play-circle-fill me-2"></i>Continuar curso'
                        : '<i class="ri-play-circle-fill me-2"></i>Empezar el aprendizaje';
                    btn.style.background = 'linear-gradient(135deg,#4B7BF5,#3a6ae4)';
                } else if (!isAuth) {
                    btn.innerHTML = '<i class="ri-graduation-cap-line me-2"></i>Inscribirme gratis';
                    btn.style.background = 'linear-gradient(135deg,#22c55e,#16a34a)';
                } else if (isFree) {
                    btn.innerHTML = '<i class="ri-graduation-cap-line me-2"></i>Inscribirme gratis — ¡Empieza ahora!';
                    btn.style.background = 'linear-gradient(135deg,#22c55e,#16a34a)';
                } else {
                    btn.innerHTML = '<i class="ri-shopping-cart-line me-2"></i>Inscribirme ahora';
                    btn.style.background = 'linear-gradient(135deg,#4B7BF5,#3a6ae4)';
                }
                bar.style.display = '';
                // Añadir padding inferior al contenido para que la barra no tape nada
                document.body.style.paddingBottom = '76px';
            }
        }
    }
    setupCtaButtons();

    // Acción del botón móvil según estado
    window.mobileCtaAction = function() {
        if (isEnrolled) { goToLesson(); }
        else if (!isAuth) { location.href = registerUrl; }
        else if (isFree) { enrollAndStart(); }
        else { enrollPaid(); }
    };

    renderIncludes(c);

    // Share
    const su = encodeURIComponent(location.href);
    const st2 = encodeURIComponent(c.title);
    \$('shareWhatsApp').href = 'https://wa.me/?text=' + st2 + '%20' + su;
    \$('shareFB').href  = 'https://www.facebook.com/sharer/sharer.php?u=' + su;
    \$('shareX').href   = 'https://twitter.com/intent/tweet?url=' + su + '&text=' + st2;

    // ── Lo que aprenderás ─────────────────────────────────────────
    if (c.what_will_learn && typeof c.what_will_learn === 'string') {
        const wwl = c.what_will_learn.trim();
        if (wwl) {
            show('secWWL');
            // Support both legacy newline format and new HTML format
            const isHtml = /<[a-z]/i.test(wwl);
            if (isHtml) {
                // HTML content — extract text nodes/items and render as checklist items
                const tmp = document.createElement('div');
                tmp.innerHTML = wwl;
                const texts = [];
                tmp.querySelectorAll('li, p').forEach(el => {
                    const t = el.textContent.trim();
                    if (t) texts.push(t);
                });
                if (texts.length) {
                    set('wwlGrid', texts.map(it =>
                        '<div class="tcd-wwl-item"><i class="ri-check-line"></i><span>'+escHtml(it)+'</span></div>'
                    ).join(''));
                } else {
                    // Fallback: render raw HTML if no recognizable list items
                    set('wwlGrid', '<div style="font-size:14px;line-height:1.7;color:#374151;">'+wwl+'</div>');
                }
            } else {
                const items = wwl.split('\\n').map(s => s.trim()).filter(Boolean);
                if (items.length) {
                    set('wwlGrid', items.map(it =>
                        '<div class="tcd-wwl-item"><i class="ri-check-line"></i><span>'+escHtml(it)+'</span></div>'
                    ).join(''));
                }
            }
        }
    }

    // ── Requisitos ────────────────────────────────────────────────
    if (c.requirements && typeof c.requirements === 'string') {
        const reqs = c.requirements.split('\\n').map(s => s.trim()).filter(Boolean);
        if (reqs.length) { show('secReqs'); set('reqsList', reqs.map(r => '<li>'+escHtml(r)+'</li>').join('')); }
    }

    // ── Audiencia ─────────────────────────────────────────────────
    if (c.target_audience && typeof c.target_audience === 'string') {
        const aud = c.target_audience.split('\\n').map(s => s.trim()).filter(Boolean);
        if (aud.length) { show('secAudience'); set('audienceList', aud.map(a => '<li>'+escHtml(a)+'</li>').join('')); }
    }

    // ── Instructor ────────────────────────────────────────────────
    if (inst0) {
        show('secInstructor');
        set('instructorCard', '<div class="tcd-instructor-wrap">'
            + '<img class="tcd-inst-avatar" src="'+escHtml(inst0.avatar_url||'assets/images/avatar.png')+'" onerror="this.src=\'assets/images/avatar.png\'" alt="'+escHtml(inst0.name)+'">'
            + '<div>'
            + '<div class="tcd-inst-name">'+escHtml(inst0.name)+'</div>'
            + (inst0.bio ? '<p class="tcd-inst-bio">'+escHtml(inst0.bio)+'</p>' : '')
            + '</div></div>'
        );
    }

    // ── Descripción ───────────────────────────────────────────────
    if (c.description) {
        show('secDesc');
        \$('tcdDesc').innerHTML = c.description;
        // Read-more si el contenido es largo
        setTimeout(() => {
            const descEl  = \$('tcdDesc');
            const wrapEl  = \$('descWrap');
            if (descEl && wrapEl && descEl.scrollHeight > 280) {
                wrapEl.classList.add('collapsed');
                const btn = document.createElement('button');
                btn.className = 'tcd-read-more-btn';
                btn.textContent = 'Leer más ↓';
                btn.onclick = () => {
                    wrapEl.classList.toggle('collapsed');
                    btn.textContent = wrapEl.classList.contains('collapsed') ? 'Leer más ↓' : 'Leer menos ↑';
                };
                wrapEl.appendChild(btn);
            }
        }, 100);
    }

    // Mostrar link "Panel Instructor" si el usuario es instructor
    const _instRoles = ['administrator','editor','tutor_instructor','instructor'];
    const _me = AulaWixApi.auth.getUser();
    if (_me && (_me.roles||[]).some(r => _instRoles.includes(r))) {
        const ib = document.getElementById('instPanelBack');
        if (ib) ib.style.display = 'inline-flex';
    }

    // Mostrar contenido
    hide('tcdLoading');
    show('tcdContent');

    // ── CURRICULUM ───────────────────────────────────────────────
    const tRes = await AulaWixApi.courses.topics(courseId);
    hide('currLoading');

    if (!tRes?.ok || !tRes.data?.length) {
        const err = \$('currError');
        if (err) { err.textContent = tRes?.message || 'Sin temario disponible.'; show('currError'); }
        return;
    }

    const topics    = tRes.data;
    const totalLess = topics.reduce((a, t) => a + (t.lessons?.length || 0), 0);

    // Registrar primera lección y primera lección incompleta para goToLesson()
    for (const topic of topics) {
        for (const l of (topic.lessons || [])) {
            if (!_firstLesson) _firstLesson = l;
            if (!l.completed && !_firstIncompleteLesson) _firstIncompleteLesson = l;
        }
    }
    // Actualizar la promesa con el resultado real (resuelve cualquier click pendiente)
    if (isEnrolled) {
        _firstLessonPromise = Promise.resolve((_firstIncompleteLesson || _firstLesson)?.id || null);
    }
    setText('currStats', topics.length + ' ' + (topics.length === 1 ? 'sección' : 'secciones') + ' • ' + totalLess + ' lecciones');
    setText('sbarTopics',  topics.length);
    setText('sbarLessons', totalLess);

    set('currAccordion', topics.map((topic, i) => {
        const lessons   = topic.lessons || [];
        const doneCount = lessons.filter(l => l.completed).length;
        const isFirst   = (i === 0);

        const lessonsHtml = lessons.length
            ? lessons.map(l => {
                const icon = l.has_video ? 'ri-play-circle-line' : 'ri-file-text-line';
                const durHtml = l.duration ? '<span class="tcd-lesson-dur">'+escHtml(l.duration)+'</span>' : '';

                if (isEnrolled) {
                    // ── Inscrito: enlace a la lección con estado completado
                    const doneHtml = l.completed
                        ? '<i class="ri-checkbox-circle-fill tcd-lesson-done"></i>'
                        : '<i class="ri-checkbox-blank-circle-line tcd-lesson-lock"></i>';
                    return '<a href="leccion.php?id='+l.id+'&course='+courseId+'" class="tcd-lesson-item">'
                        + '<div class="tcd-lesson-icon"><i class="'+icon+'"></i></div>'
                        + '<span class="tcd-lesson-title">'+escHtml(l.title)+'</span>'
                        + durHtml + doneHtml + '</a>';

                } else if (isAuth) {
                    // ── Autenticado pero NO inscrito: ítem bloqueado (sin clic)
                    return '<div class="tcd-lesson-item locked" title="Inscríbete para acceder a esta lección">'
                        + '<div class="tcd-lesson-icon"><i class="'+icon+'"></i></div>'
                        + '<span class="tcd-lesson-title">'+escHtml(l.title)+'</span>'
                        + durHtml
                        + '<i class="ri-lock-line tcd-lesson-lock"></i></div>';

                } else {
                    // ── Visitante sin sesión: clic redirige a login
                    const dest = loginUrl + '&next=' + encodeURIComponent('leccion.php?id='+l.id+'&course='+courseId);
                    return '<a href="'+dest+'" class="tcd-lesson-item tcd-lesson-guest">'
                        + '<div class="tcd-lesson-icon"><i class="'+icon+'"></i></div>'
                        + '<span class="tcd-lesson-title">'+escHtml(l.title)+'</span>'
                        + durHtml
                        + '<i class="ri-lock-line tcd-lesson-lock"></i></a>';
                }
            }).join('')
            : '<div class="tcd-lock-notice"><i class="ri-information-line"></i>Sin lecciones en este tema.</div>';

        // Aviso al pie de cada tema (distinto mensaje según estado)
        let lockNotice = '';
        if (!isEnrolled && lessons.length) {
            if (!isAuth) {
                lockNotice = '<div class="tcd-lock-notice">'
                    + '<i class="ri-lock-line"></i>'
                    + '<a href="'+loginUrl+'" class="tcd-lock-link">Inicia sesión</a>'
                    + '&nbsp;o&nbsp;'
                    + '<a href="'+registerUrl+'" class="tcd-lock-link">regístrate</a>'
                    + ' para acceder a las lecciones.'
                    + '</div>';
            } else {
                lockNotice = '<div class="tcd-lock-notice"><i class="ri-lock-line"></i>Inscríbete en este curso para acceder al contenido.</div>';
            }
        }

        return '<div class="tcd-topic">'
            + '<div class="tcd-topic-head'+(isFirst?' is-open':'')+'" onclick="tcdToggle(this)">'
            +   '<span class="tcd-topic-num">'+(i<9?'0':'')+(i+1)+'.</span>'
            +   '<span class="tcd-topic-title">'+escHtml(topic.title)+'</span>'
            +   '<span class="tcd-topic-meta">'+doneCount+'/'+lessons.length+'</span>'
            +   '<i class="ri-arrow-down-s-line tcd-topic-arrow'+(isFirst?' is-open':'')+'"></i>'
            + '</div>'
            + '<div class="tcd-topic-body'+(isFirst?' show':'')+'">'+lessonsHtml+lockNotice+'</div>'
            + '</div>';
    }).join(''));

    // ── Helpers ───────────────────────────────────────────────────
    function renderPrice(c) {
        const pt = c.price_type || 'free';
        let html;
        if (pt === 'free' || !c.price || +c.price === 0) {
            html = '<div class="tcd-price-free">Gratis</div>';
            show('tcdCourseBadge');
            show('tcdPriceSub');
        } else if (c.sale_price && +c.sale_price > 0 && +c.sale_price < +c.price) {
            const disc = Math.round((1 - c.sale_price / c.price) * 100);
            html = '<div class="tcd-price-row"><span class="tcd-price-main">\$'+parseFloat(c.sale_price).toFixed(2)+'</span>'
                 + '<span class="tcd-price-orig">\$'+parseFloat(c.price).toFixed(2)+'</span>'
                 + '<span class="tcd-price-badge">-'+disc+'%</span></div>';
        } else {
            html = '<div class="tcd-price-row"><span class="tcd-price-main">\$'+parseFloat(c.price).toFixed(2)+'</span></div>';
        }
        set('tcdPrice', html);
    }

    function renderIncludes(c) {
        const lc = c.stats?.lesson_count || c.lesson_count || 0;
        const items = [];
        if (lc)         items.push(['ri-file-list-3-line', lc + ' lecciones']);
        if (c.duration && typeof c.duration === 'string') items.push(['ri-time-line', c.duration + ' de contenido']);
        if (c.level) {
            const lm = {beginner:'Principiante',intermediate:'Intermedio',advanced:'Avanzado',all_levels:'Todos los niveles'};
            items.push(['ri-bar-chart-2-line', lm[c.level] || c.level]);
        }
        items.push(['ri-global-line', 'Acceso de por vida']);
        if (c.certificate) items.push(['ri-award-line', 'Certificado al completar']);
        set('tcdIncludes', items.map(([,tx]) => '<li><i class="ri-check-line"></i>'+tx+'</li>').join(''));
    }

})();

function tcdToggle(head) {
    const body  = head.nextElementSibling;
    const arrow = head.querySelector('.tcd-topic-arrow');
    const open  = body.classList.contains('show');
    body.classList.toggle('show', !open);
    arrow.classList.toggle('is-open', !open);
    head.classList.toggle('is-open', !open);
}

function copyLink() {
    navigator.clipboard?.writeText(location.href).then(() => {
        const icon = document.getElementById('copyIcon');
        if (icon) { icon.className = 'ri-check-line'; setTimeout(() => { icon.className = 'ri-link'; }, 1800); }
    });
}
</script>
SCRIPTS;

require_once __DIR__ . '/inc/footer.php';
