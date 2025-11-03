<?php
// modules/modules.php
// Este archivo es llamado por dashboard.php
// No necesita session_start() ni require 'config.php' porque ya están cargados.

// $view_data es un array pasado desde dashboard.php
$user = $view_data['user'] ?? null;
$pdo = $view_data['pdo'] ?? null;
$can_use_generators = $view_data['can_use_generators'] ?? false;

if (!$user || !$pdo) {
    echo "<div class='bento-box'><p class='text-danger'>Error al cargar el módulo.</p></div>";
    return;
}
?>

<h1 class="module-title">Generadores de Jumpers</h1>
<p class="module-subtitle">Selecciona una herramienta para comenzar.</p>

<!-- ¡Importante! Comprobación de Membresía -->
<?php if (!$can_use_generators): ?>
<div class="alert alert-danger-custom" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span>Tu membresía (<?= htmlspecialchars($user['membership_type'], ENT_QUOTES, 'UTF-8') ?>) no tiene acceso a los generadores.</span>
    <!-- El .nav-link hace que la SPA lo cargue sin recargar -->
    <a href="dashboard.php?module=membership" class="alert-link nav-link">Actualizar a PRO</a>
</div>
<?php endif; ?>

<!-- Cuadrícula de Módulos -->
<div class="module-grid">

    <!-- Meinungsplatz -->
    <a href="dashboard.php?module=meinungsplatz" class="module-card nav-link <?= $can_use_generators ? '' : 'disabled' ?>">
        <img src="https://placehold.co/60x60/13192B/30E8BF?text=MP" alt="Meinungsplatz" class="module-icon">
        <div class="module-info">
            <h5 class="module-name">Meinungsplatz</h5>
            <p class="module-description">Genera jumpers y gestiona SubIDs de Projektnummer.</p>
        </div>
        <i class="bi bi-arrow-right-circle-fill module-arrow"></i>
    </a>
    
    <!-- Opensurvey -->
    <a href="dashboard.php?module=opensurvey" class="module-card nav-link <?= $can_use_generators ? '' : 'disabled' ?>">
        <img src="https://placehold.co/60x60/13192B/3B82F6?text=OS" alt="Opensurvey" class="module-icon">
        <div class="module-info">
            <h5 class="module-name">Opensurvey</h5>
            <p class="module-description">Genera jumpers para encuestas de Opensurvey/Reppublika.</p>
        </div>
        <i class="bi bi-arrow-right-circle-fill module-arrow"></i>
    </a>
    
    <!-- OpinionExchange -->
    <a href="dashboard.php?module=opinionexchange" class="module-card nav-link <?= $can_use_generators ? '' : 'disabled' ?>">
        <img src="https://placehold.co/60x60/13192B/FACC15?text=OE" alt="OpinionExchange" class="module-icon">
        <div class="module-info">
            <h5 class="module-name">OpinionExchange</h5>
            <p class="module-description">Genera jumpers para encuestas con UserID de OpinionEx.</p>
        </div>
        <i class="bi bi-arrow-right-circle-fill module-arrow"></i>
    </a>
    
    <!-- Samplicio (Próximamente) -->
    <a href="#" class="module-card nav-link disabled" onclick="return false;">
        <img src="https://placehold.co/60x60/13192B/8392AD?text=S" alt="Samplicio" class="module-icon">
        <div class="module-info">
            <h5 class="module-name">Samplicio (Próximamente)</h5>
            <p class="module-description">Generador para enlaces de Samplicio / Dynata.</p>
        </div>
        <i class="bi bi-lock-fill module-arrow"></i>
    </a>
    
    <!-- Cint (Próximamente) -->
    <a href="#" class="module-card nav-link disabled" onclick="return false;">
        <img src="https://placehold.co/60x60/13192B/8392AD?text=C" alt="Cint" class="module-icon">
        <div class="module-info">
            <h5 class="module-name">Cint (Próximamente)</h5>
            <p class="module-description">Generador para encuestas de la plataforma Cint.</p>
        </div>
        <i class="bi bi-lock-fill module-arrow"></i>
    </a>

</div>