<?php
// dashboard.php (v1.0 - El nuevo index.php)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config.php';
require 'functions.php'; // Carga el nuevo functions.php (v7.0)

// --- 1. VALIDACIÓN DE SESIÓN Y AUTH ---
if (!isset($_SESSION['user'])) {
    if (function_exists('validateRememberMe')) { 
        $userFromCookie = validateRememberMe($pdo); 
        if (!$userFromCookie) {
            header('Location: login.php'); exit; 
        }
    } else {
        error_log("Error: validateRememberMe function not found");
        header('Location: login.php'); exit;
    }
}
$user = $_SESSION['user']; // Cargar usuario desde la sesión

// --- 2. VALIDACIÓN DE SESIÓN ÚNICA ---
// (Admin puede tener múltiples sesiones, el resto no)
if (isset($user['id']) && isset($_SESSION['session_token']) && $user['membership_type'] !== 'ADMINISTRADOR') {
    try {
        $stmt_check = $pdo->prepare("SELECT current_session_token FROM usuarios WHERE id = ?");
        $stmt_check->execute([$user['id']]);
        $db_token = $stmt_check->fetchColumn();
        if ($db_token !== $_SESSION['session_token']) {
            session_unset(); session_destroy();
            if (function_exists('clearRememberMeCookie')) { clearRememberMeCookie(); }
            header("Location: login.php?error=" . urlencode("Tu sesión fue cerrada porque se inició sesión en otro dispositivo."));
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error validando token de sesión: " . $e->getMessage());
        session_unset(); session_destroy();
        if (function_exists('clearRememberMeCookie')) { clearRememberMeCookie(); }
        header("Location: login.php?error=" . urlencode("Error al verificar la sesión."));
        exit;
    }
} elseif (!isset($_SESSION['session_token'])) {
    session_unset(); session_destroy();
    if (function_exists('clearRememberMeCookie')) { clearRememberMeCookie(); }
    header("Location: login.php?error=" . urlencode("Sesión inválida. Por favor, inicia sesión de nuevo."));
    exit;
}

// --- 3. LÓGICA DE MEMBRESÍA Y NOTIFICACIONES ---
$membership_type = $user['membership_type'] ?? 'VENCIDO';
$jumper_count = (int)($user['jumper_count'] ?? 0);
$jumper_limit = (int)($user['jumper_limit'] ?? 0);
$membership_expires = $user['membership_expires'] ? new DateTime($user['membership_expires']) : null;
$now = new DateTime();
$notification_message = '';
$notification_type = 'warning'; // 'warning' o 'danger'

// Comprobar si la membresía PRO ha vencido
if ($membership_type === 'PRO' && $membership_expires && $membership_expires < $now) {
    $stmt = $pdo->prepare("UPDATE usuarios SET membership_type = 'VENCIDO' WHERE id = ?");
    $stmt->execute([$user['id']]);
    $_SESSION['user']['membership_type'] = 'VENCIDO'; // Actualiza la sesión
    $membership_type = 'VENCIDO';
}

// Comprobar si la PRUEBA GRATIS ha vencido (por usos)
if ($membership_type === 'PRUEBA GRATIS' && $jumper_count >= $jumper_limit) {
    $stmt = $pdo->prepare("UPDATE usuarios SET membership_type = 'VENCIDO' WHERE id = ?");
    $stmt->execute([$user['id']]);
    $_SESSION['user']['membership_type'] = 'VENCIDO'; // Actualiza la sesión
    $membership_type = 'VENCIDO';
}

// Generar mensajes de notificación
if ($membership_type === 'VENCIDO') {
    $notification_message = 'Tu membresía ha vencido. Renueva tu plan para seguir usando los generadores.';
    $notification_type = 'danger';
} elseif ($membership_type === 'PRUEBA GRATIS') {
    $usos_restantes = $jumper_limit - $jumper_count;
    $notification_message = "Estás en tu prueba gratis. Te quedan <strong>{$usos_restantes}</strong> usos.";
    $notification_type = 'info';
} elseif ($membership_type === 'PRO' && $membership_expires) {
    $interval = $now->diff($membership_expires);
    $days_left = (int)$interval->format('%r%a'); // %r%a incluye el signo
    if ($days_left >= 0 && $days_left <= 3) { // 3, 2, 1, 0 días
        $notification_message = "¡Tu membresía PRO expira en <strong>{$days_left} día(s)</strong>! Renueva pronto para no perder el acceso.";
        $notification_type = 'warning';
    }
}

// Variable global para saber si se pueden usar generadores (se usa en los módulos)
$can_use_generators = ($membership_type === 'ADMINISTRADOR' || $membership_type === 'PRO' || $membership_type === 'PRUEBA GRATIS');

// --- 4. ACTUALIZAR ACTIVIDAD ---
// (¡CORREGIDO! Solo si no es una petición fetch, para no sobrecargar)
$isFragmentRequest = isset($_GET['fetch']) && $_GET['fetch'] === 'fragment';
if (function_exists('updateUserActivity') && !$isFragmentRequest) { 
    updateUserActivity($pdo, $user['id']); 
}

// --- 5. CARGADOR DE MÓDULOS (para peticiones fetch) ---
if ($isFragmentRequest) {
    $module = $_GET['module'] ?? 'home';
    $module_path = "modules/{$module}.php";
    
    // Validar que el módulo exista para evitar LFI
    $allowed_modules = ['home', 'modules', 'ranking', 'membership', 'meinungsplatz', 'opensurvey', 'opinionexchange', 'shortener'];
    
    // ¡NUEVO! Pasar datos a los módulos
    $view_data = [
        'user' => $user,
        'pdo' => $pdo,
        'can_use_generators' => $can_use_generators
    ];

    if (!in_array($module, $allowed_modules) || !file_exists($module_path)) {
        http_response_code(404);
        echo "<div class='bento-box'><div class='box-header text-danger'>Error 404</div><p>El módulo '{$module}' no fue encontrado.</p></div>";
        exit;
    }
    
    // Cargar el módulo
    // Las variables $pdo, $user, $can_use_generators, etc. están disponibles en el módulo.
    include($module_path);
    exit; // Terminar la ejecución
}

// Si no es una petición fetch, se carga la página HTML completa.
$module = $_GET['module'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Dashboard - SurveyJunior</title>
    
    <!-- ¡NUEVO! ETIQUETA NO-INDEX PARA SEO -->
    <!-- Le dice a Google que no indexe esta página privada -->
    <meta name="robots" content="noindex, nofollow">

    <!-- Favicon SVG -->
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='3' y='3' width='18' height='18' rx='4' fill='%230A0E1A' opacity='0.1'/%3E%3Cpath d='M16 8C16 6.89543 15.1046 6 14 6H10C8.89543 6 8 6.89543 8 8C8 9.10457 8.89543 10 10 10H14' stroke='%2330E8BF' stroke-width='2.5' stroke-linecap='round'/%3E%3Cpath d='M8 16C8 17.1046 8.89543 18 10 18H14C15.1046 18 16 17.1046 16 16C16 14.8954 15.1046 14 14 14H10' stroke='%2330E8BF' stroke-width='2.5' stroke-linecap='round'/%3E%3C/svg%3E">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Tipografía Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
    
    <!-- Chart.js (para futuros gráficos) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Archivos CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="new-style.css"> <!-- ¡El CSS unificado! -->
    
    <!-- SDK de PayPal (para el módulo de membresía) -->
    <!-- Reemplaza "YOUR_CLIENT_ID" con tu Client ID real de PayPal Developer -->
    <script src="https://www.paypal.com/sdk/js?client-id=YOUR_CLIENT_ID&currency=USD&disable-funding=card,sepa,zimpler,sofort" defer></script>
</head>
<body data-theme="dark"> <!-- Por defecto en oscuro, JS lo cambiará -->

    <!-- =================================================================== -->
    <!-- ESTRUCTURA PRINCIPAL DE LA APP                                      -->
    <!-- =================================================================== -->
    <div class="app-shell" id="app-shell">
        
        <!-- BARRA LATERAL (Sidebar) -->
        <nav class="app-sidebar">
            <a href="dashboard.php?module=home" class="nav-link" style="margin-bottom: 2rem;" title="SurveyJunior">
                <svg class="app-logo" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="18" height="18" rx="4" opacity="0.1"/>
                    <path d="M16 8C16 6.89543 15.1046 6 14 6H10C8.89543 6 8 6.89543 8 8C8 9.10457 8.89543 10 10 10H14"/>
                    <path d="M8 16C8 17.1046 8.89543 18 10 18H14C15.1046 18 16 17.1046 16 16C16 14.8954 15.1046 14 14 14H10"/>
                </svg>
            </a>
            
            <ul class="nav-list">
                <!-- Módulo "Home" (Dashboard) -->
                <li>
                    <a href="dashboard.php?module=home" class="nav-link <?= $module === 'home' ? 'active' : '' ?>" title="Dashboard">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span class="nav-link-text">Dashboard</span>
                    </a>
                </li>
                <!-- Módulo "Módulos" (Selección de Generadores) -->
                <li>
                    <a href="dashboard.php?module=modules" class="nav-link <?= $module === 'modules' || in_array($module, ['meinungsplatz', 'opensurvey', 'opinionexchange']) ? 'active' : '' ?>" title="Generadores">
                        <i class="bi bi-box-fill"></i>
                        <span class="nav-link-text">Generadores</span>
                    </a>
                </li>
                <!-- Módulo "Ranking" -->
                <li>
                    <a href="dashboard.php?module=ranking" class="nav-link <?= $module === 'ranking' ? 'active' : '' ?>" title="Ranking">
                        <i class="bi bi-trophy-fill"></i>
                        <span class="nav-link-text">Ranking</span>
                    </a>
                </li>
                <!-- Módulo "Membresía" -->
                <li>
                    <a href="dashboard.php?module=membership" class="nav-link <?= $module === 'membership' ? 'active' : '' ?>" title="Membresía">
                        <i class="bi bi-gem"></i>
                        <span class="nav-link-text">Membresía</span>
                    </a>
                </li>
                <!-- Módulo "Acortador" -->
                <li>
                    <a href="dashboard.php?module=shortener" class="nav-link <?= $module === 'shortener' ? 'active' : '' ?>" title="Acortador">
                        <i class="bi bi-scissors"></i>
                        <span class="nav-link-text">Acortador</span>
                    </a>
                </li>
            </ul>
            
            <ul class="nav-list sidebar-footer">
                <!-- Enlace a Admin (Solo si es Admin) -->
                <?php if ($user['membership_type'] === 'ADMINISTRADOR'): ?>
                <li>
                    <a href="admin.php" class="nav-link" title="Admin Panel" style="color: var(--brand-yellow);" target="_blank">
                        <i class="bi bi-shield-lock-fill"></i>
                        <span class="nav-link-text">Admin</span>
                    </a>
                </li>
                <?php endif; ?>
                <!-- Enlace de Salir -->
                <li>
                    <a href="logout.php" class="nav-link" title="Cerrar Sesión">
                        <i class="bi bi-box-arrow-left"></i>
                        <span class="nav-link-text">Salir</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- =================================================================== -->
        <!-- CABECERA (Header)                                                   -->
        <!-- =================================================================== -->
        <header class="app-header">
            <!-- Botón de Contacto (Fuego) -->
            <a href="https://t.me/surveyjuniorus" target="_blank" class="btn btn-fire">
                <i class="bi bi-fire"></i>
                <span class="btn-fire-text">Contactar al Admin</span>
            </a>
            
            <!-- Grupo de botones a la derecha -->
            <div class="header-right-group">
                
                <!-- Estado de Membresía -->
                <a href="dashboard.php?module=membership" class="subscription-status 
                    <?php 
                    if ($membership_type === 'PRO' || $membership_type === 'ADMINISTRADOR') echo 'status-active';
                    elseif ($membership_type === 'PRUEBA GRATIS') echo 'status-trial';
                    else echo 'status-expired'; 
                    ?>" 
                   title="Gestionar Membresía">
                    
                    <?php 
                    if ($membership_type === 'PRO' || $membership_type === 'ADMINISTRADOR') echo '<i class="bi bi-patch-check-fill"></i>';
                    elseif ($membership_type === 'PRUEBA GRATIS') echo '<i class="bi bi-clock-history"></i>';
                    else echo '<i class="bi bi-exclamation-triangle-fill"></i>';
                    ?>
                    
                    <span class="sub-status-text">
                        <?php 
                        if ($membership_type === 'ADMINISTRADOR') echo 'Admin';
                        elseif ($membership_type === 'PRO') echo 'Membresía Pro';
                        elseif ($membership_type === 'PRUEBA GRATIS') echo 'Prueba Gratis';
                        else echo 'Vencido';
                        ?>
                    </span>
                </a>
                
                <!-- Botón de Tema (Claro/Oscuro) -->
                <button class="theme-toggle" id="theme-toggle-btn" title="Cambiar tema">
                    <i class="bi bi-sun-fill"></i> <!-- Icono por defecto (oscuro), JS lo cambiará -->
                </button>
                
                <!-- Avatar de Usuario -->
                <img class="user-avatar" id="user-avatar-btn" src="https://api.dicebear.com/8.x/adventurer/svg?seed=<?= urlencode($user['username']) ?>" alt="Avatar de Usuario">
            </div>
        </header>

        <!-- =================================================================== -->
        <!-- ÁREA DE CONTENIDO (Aquí es donde la SPA carga los módulos)       -->
        <!-- =================================================================== -->
        <main class="module-content-area" id="module-content">
            <!-- El "Spinner Infinito" (estado de carga inicial) -->
            <!-- dashboard-script.js reemplazará esto con el módulo "home" -->
            <div class="d-flex justify-content-center align-items-center" style="height: 70vh;">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
        </main>
    </div> <!-- Fin de .app-shell -->

    
    <!-- =================================================================== -->
    <!-- PLANTILLAS HTML (para clonar con JS)                                -->
    <!-- =================================================================== -->
    <div id="app-templates" style="display: none;">
    
        <!-- Plantilla: Éxito al Generar Jumper -->
        <template id="template-jumper-success">
            <div class="jumper-success-card">
                <div class="jsc-icon-wrapper">
                    <i class="bi bi-rocket-launch-fill"></i>
                </div>
                <h4 class="jsc-title">¡JUMPER Generado!</h4>
                <div class="jsc-subid-info">
                    SubID: <strong class="subid-display">...</strong> 
                    (Aportado por: <span class="subid-author">...</span>)
                </div>
                <div class="jsc-link-box">
                    <a href="#" target="_blank" class="jumper-link-href">...</a>
                </div>
                <div class="jsc-actions">
                    <button class="btn btn-success btn-copy-jumper">
                        <i class="bi bi-clipboard-check-fill me-2"></i>Copiar Enlace
                    </button>
                    <a href="#" target="_blank" class="btn btn-secondary jumper-link-href">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Probar
                    </a>
                </div>
            </div>
            
            <!-- ¡ARREGLO DE BUG! Sección de Rating que faltaba -->
            <div class="rating-section bento-box" style="margin-top: 1.5rem;">
                <div class="bento-box-header small" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>¿Este SubID funcionó? ¡Califícalo!</span>
                    <button type="button" class="btn-close btn-close-white btn-close-rating" aria-label="Cerrar"></button>
                </div>
                <div style="display: flex; justify-content: space-around; align-items: center; text-align: center;">
                    <button class="btn btn-rating rating-btn" data-rating="1">
                        <i class="bi bi-hand-thumbs-up-fill"></i>
                        <span class="positive-count">0</span>
                    </button>
                    <button class="btn btn-rating rating-btn" data-rating="-1">
                        <i class="bi bi-hand-thumbs-down-fill"></i>
                        <span class="negative-count">0</span>
                    </button>
                </div>
            </div>
        </template>
        
        <!-- Plantilla: Skeleton (Carga) para Home -->
        <template id="skeleton-home">
            <div class="bento-grid">
                <div class="bento-box stat-box-jumbo skeleton-item">
                    <div class="skeleton-box" style="width: 60%; height: 3rem; margin-bottom: 0.5rem;"></div>
                    <div class="skeleton-box" style="width: 40%; height: 1rem;"></div>
                </div>
                <div class="bento-box stat-box-jumbo skeleton-item">
                    <div class="skeleton-box" style="width: 60%; height: 3rem; margin-bottom: 0.5rem;"></div>
                    <div class="skeleton-box" style="width: 40%; height: 1rem;"></div>
                </div>
                <div class="bento-box stat-box-jumbo skeleton-item">
                    <div class="skeleton-box" style="width: 60%; height: 3rem; margin-bottom: 0.5rem;"></div>
                    <div class="skeleton-box" style="width: 40%; height: 1rem;"></div>
                </div>
                <div class="bento-box box-generator skeleton-item" style="grid-column: span 12; min-height: 300px;">
                    <div class="skeleton-box" style="width: 40%; height: 1rem; margin-bottom: 2rem;"></div>
                    <div class="skeleton-box" style="width: 100%; height: 80px; margin-bottom: 1rem;"></div>
                    <div class="skeleton-box" style="width: 100%; height: 45px; margin-bottom: 1rem;"></div>
                    <div class="skeleton-box" style="width: 100%; height: 45px;"></div>
                </div>
            </div>
        </template>
        
        <!-- Plantilla: Skeleton (Carga) para Ranking -->
        <template id="skeleton-ranking">
            <div id="ranking-podium-container">
                <div class="podium">
                    <div class="podium-card rank-2 skeleton-item"><div class="skeleton-box" style="width: 100%; height: 180px;"></div></div>
                    <div class="podium-card rank-1 skeleton-item"><div class="skeleton-box" style="width: 100%; height: 220px;"></div></div>
                    <div class="podium-card rank-3 skeleton-item"><div class="skeleton-box" style="width: 100%; height: 180px;"></div></div>
                </div>
                <div class="bento-box mt-4 skeleton-item">
                    <div class="skeleton-box" style="width: 100%; height: 40px; margin-bottom: 1rem;"></div>
                    <div class="skeleton-box" style="width: 100%; height: 40px;"></div>
                </div>
            </div>
        </template>
        
    </div> <!-- Fin de #app-templates -->


    <!-- =================================================================== -->
    <!-- MODALES (Ocultos por defecto)                                       -->
    <!-- =================================================================== -->
    
    <!-- Modal: Inactividad -->
    <div class="modal fade" id="inactivityModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="inactivityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="inactivityModalLabel"><i class="bi bi-clock-history me-2"></i>Sesión a punto de expirar</h5>
                </div>
                <div class="modal-body">
                    <p>Has estado inactivo. Tu sesión se cerrará automáticamente en <strong id="inactivityCountdown" style="color: var(--brand-yellow);">60</strong> segundos.</p>
                    <p>¿Deseas continuar tu sesión?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="logoutBtn">Cerrar Sesión</button>
                    <button type="button" class="btn btn-primary" id="stayLoggedInBtn">Continuar Sesión</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal: Error SubID No Encontrado (para Meinungsplatz) -->
    <div class="modal fade" id="subidErrorModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="subidErrorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subidErrorModalLabel"><i class="bi bi-robot me-2 text-warning"></i> SubID No Encontrado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p id="modal-error-message" class="mb-3">No tenemos SubID para Projektnummer <strong>...</strong>.</p>
                    <p class="mb-3">¿Deseas añadirlo manualmente?</p>
                    <form id="modal-add-subid-form">
                        <input type="hidden" id="modal-add-projektnummer" name="projektnummer" value="">
                        <div class="mb-3">
                            <label for="modal-add-new-subid" class="form-label">Nuevo SubID</label>
                            <input type="text" class="form-control-dark" id="modal-add-new-subid" name="new_subid" placeholder="SubID (ej: f8113cee)" maxlength="50" required>
                        </div>
                        <button class="btn btn-success" type="submit" id="modal-add-subid-btn" style="width: 100%;">
                            <span class="btn-text"><i class="bi bi-plus-circle me-1"></i>Añadir y Regenerar</span>
                            <span class="spinner-border spinner-border-sm" style="display: none;" role="status" aria-hidden="true"></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal: Subir Comprobante de Pagomóvil (para Membresía) -->
    <div class="modal fade" id="uploadProofModal" tabindex="-1" aria-labelledby="uploadProofModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadProofModalLabel">Reportar Pago de Pagomóvil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modal-upload-error" class="alert alert-danger" style="display: none;"></div>
                    <form id="payment-proof-form">
                        <div class="mb-3">
                            <label for="payment-ref" class="form-label">Número de Referencia</label>
                            <input type="text" class="form-control form-control-dark" id="payment-ref" name="reference" placeholder="Ej: 00012345" required>
                        </div>
                        <div class="mb-3">
                            <label for="payment-amount" class="form-label">Monto (Bs.)</label>
                            <input type="text" class="form-control form-control-dark" id="payment-amount" name="amount_bs" placeholder="Ej: 150.00" required>
                        </div>
                        <div class="mb-3">
                            <label for="payment-proof" class="form-label">Adjuntar Comprobante (Capture)</label>
                            <input class="form-control form-control-dark" type="file" id="payment-proof" name="proof" accept="image/png, image/jpeg" required>
                        </div>
                        <div class="mb-3">
                            <label for="payment-notes" class="form-label">Nota Adicional (Opcional)</label>
                            <textarea class="form-control form-control-dark" id="payment-notes" name="notes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button typet="button" class="btn btn-primary" id="submit-proof-btn">
                        <span class="btn-text">Enviar Comprobante</span>
                        <span class="spinner-border spinner-border-sm" style="display: none;" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    
    <!-- =================================================================== -->
    <!-- PERFIL OFFCANVAS (Oculto por defecto)                               -->
    <!-- =================================================================== -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="userProfileOffcanvas" aria-labelledby="userProfileOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="userProfileOffcanvasLabel">Mi Perfil</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            
            <div class="d-flex flex-column align-items-center text-center">
                <img class="user-avatar-lg" src="https://api.dicebear.com/8.x/adventurer/svg?seed=<?= urlencode($user['username']) ?>" alt="Avatar de Usuario">
                <h4 class="mt-3 mb-0"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></h4>
                <span class="status-badge <?= $membership_type === 'PRO' ? 'status-pro' : ($membership_type === 'ADMINISTRADOR' ? 'status-admin' : 'status-prueba') ?>">
                    <?= htmlspecialchars($membership_type, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            
            <hr class="my-4" style="border-color: var(--border-color);">
            
            <h5>Estadísticas (Este Mes)</h5>
            <div class="bento-grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="bento-box text-center">
                    <div class="stat-value small" id="profile-stat-jumpers">...</div>
                    <div class="bento-box-header small">Jumpers Creados</div>
                </div>
                <div class="bento-box text-center">
                    <div class="stat-value small" id="profile-stat-subids">...</div>
                    <div class="bento-box-header small">SubIDs Aportados</div>
                </div>
            </div>
            
            <div class="d-grid gap-2 mt-4">
                <a href="dashboard.php?module=membership" class="btn btn-primary"><i class="bi bi-gem me-2"></i>Gestionar Membresía</a>
                <a href="logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a>
            </div>
            
        </div>
    </div>
    
    <!-- Contenedor para Toasts (Alertas pequeñas) -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container" style="z-index: 1100;"></div>

    <!-- =================================================================== -->
    <!-- SCRIPTS (Cargados al final)                                         -->
    <!-- =================================================================== -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- ¡EL CEREBRO! Este script hace funcionar la SPA -->
    <script src="dashboard-script.js"></script>
    
</body>
</html>