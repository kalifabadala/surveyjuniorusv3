<?php
// modules/home.php
// Este archivo es llamado por dashboard.php
// No necesita session_start() ni require 'config.php' porque ya están cargados.

// $view_data es un array pasado desde dashboard.php
$user = $view_data['user'] ?? null;
$pdo = $view_data['pdo'] ?? null;

if (!$user || !$pdo) {
    echo "<div class='bento-box'><p class='text-danger'>Error al cargar el módulo.</p></div>";
    return;
}
?>

<h1 class="module-title">Bienvenido de nuevo, <?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></h1>
<p class="module-subtitle">Aquí tienes un resumen de tu actividad y herramientas.</p>

<!-- Grid del Dashboard -->
<div class="bento-grid">

    <!-- Caja 1: Estadísticas Jumbo (¡CORREGIDA CON IDs!) -->
    <div class="bento-box stat-box-jumbo">
        <i class="bi bi-rocket-launch-fill stat-icon"></i>
        <div class="box-header">Jumpers Generados (Total)</div>
        <!-- ID CORREGIDO -->
        <div class="stat-value" id="stat-total-jumpers-all-time">...</div> 
        <!-- ID NUEVO -->
        <div class="stat-label" id="stat-jumpers-month">Este mes: ...</div>
    </div>
    
    <!-- Caja 2: Rango (¡CORREGIDA CON IDs!) -->
    <div class="bento-box stat-box-jumbo">
        <i class="bi bi-award-fill stat-icon" style="color: var(--brand-yellow);"></i>
        <div class="box-header">Rango Actual</div>
         <!-- ID CORREGIDO -->
        <div class="stat-value" id="stat-rank-name" style="color: var(--brand-yellow);">...</div>
         <!-- ID NUEVO -->
        <div class="stat-label" id="stat-rank-level">Nivel ...</div>
    </div>
    
    <!-- Caja 3: SubIDs Aportados (¡CORREGIDA CON IDs!) -->
    <div class="bento-box stat-box-jumbo">
        <i class="bi bi-database-fill-add stat-icon" style="color: var(--brand-blue);"></i>
        <div class="box-header">SubIDs Aportados</div>
         <!-- ID CORREGIDO -->
        <div class="stat-value" id="stat-total-subids" style="color: var(--brand-blue);">...</div>
         <!-- ID NUEVO -->
        <div class="stat-label" id="stat-subids-rank">#... en el ranking</div>
    </div>

    <!-- Caja 4: Generador Rápido (Opensurvey) -->
    <div class="bento-box box-generator">
        <div class="box-header">⚡ Generador Rápido (Opensurvey)</div>
        <!-- Este formulario ahora tiene un ID único -->
        <form class="generator-form" id="opensurvey-form" data-api="api_generate_opensurvey.php">
            <div class.form-group">
                <label for="opensurvey-url" class="form-label">URL de Opensurvey</label>
                <input type="url" class="form-control-dark" id="opensurvey-url" name="input_url" placeholder="https://opensurvey.reppublika.com/..." required>
            </div>
            <button type="submit" class="btn-generate">
                <span class="btn-text"><i class="bi bi-magic me-2"></i>Generar Jumper</span>
                <span class="spinner-border spinner-border-sm" style="display: none;" role="status" aria-hidden="true"></span>
            </button>
        </form>
    </div>
    
    <!-- Caja 5: Ranking Top 3 -->
    <div class="bento-box box-ranking">
        <div class="box-header">🏆 Ranking Top 3 (Aportes)</div>
        <!-- Este ID es el objetivo para el JS del ranking -->
        <div id="ranking-home-container">
            <!-- Skeleton de carga -->
            <ul class="ranking-list">
                <li class="ranking-item skeleton-item"><span class="skeleton-box" style="width: 100%; height: 40px;"></span></li>
                <li class="ranking-item skeleton-item"><span class="skeleton-box" style="width: 100%; height: 40px;"></span></li>
                <li class="ranking-item skeleton-item"><span class="skeleton-box" style="width: 100%; height: 40px;"></span></li>
            </ul>
        </div>
    </div>
    
    <!-- Contenedor para el resultado del generador -->
    <div id="generator-result-container" class="bento-box" style="grid-column: span 12; display: none;">
        <!-- El resultado del Jumper (de la plantilla) se insertará aquí -->
    </div>

</div>