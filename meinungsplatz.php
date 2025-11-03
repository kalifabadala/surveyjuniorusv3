<?php
// modules/meinungsplatz.php
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

<h1 class="module-title">Generador Meinungsplatz</h1>
<p class="module-subtitle">Pega las URLs de la encuesta y el Projektnummer para generar tu jumper.</p>

<?php if (!$can_use_generators): ?>
    <!-- Alerta de Membresía Vencida -->
    <div class="alert alert-danger-custom" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div class="alert-content">
            <h5 class="alert-title">Membresía Vencida o Expirada</h5>
            <p>Tu plan de "Prueba Gratis" ha terminado o tu membresía "PRO" ha vencido.
            <a href="dashboard.php?module=membership" class="alert-link nav-link">Por favor, renueva tu plan</a> para continuar generando jumpers.</p>
        </div>
    </div>

<?php else: ?>
    <!-- Formulario del Generador -->
    <div class="bento-grid">
        <div class="bento-box box-generator" style="grid-column: span 12;">
            <div class="box-header">Generador Meinungsplatz</div>
            
            <!-- 
                Este formulario es manejado por dashboard-script.js
                data-api="api_generate.php" le dice al script qué API llamar.
            -->
            <form id="generator-form" class="generator-form" data-api="api_generate.php">
                <div class="mb-3">
                    <label for="gen-urls" class="form-label">URLs de Encuesta</label>
                    <textarea class="form-control-dark" id="gen-urls" name="urls" rows="5" placeholder="Pega una o más URLs de encuesta aquí..." required></textarea>
                    <div class="form-text">El sistema buscará automáticamente un ID de usuario de 15 dígitos.</div>
                </div>
                
                <div class="mb-3">
                    <label for="gen-projektnummer" class="form-label">Projektnummer (5 o 6 dígitos)</label>
                    <input type="text" class="form-control-dark" id="gen-projektnummer" name="projektnummer" placeholder="Ej: 123456" required pattern="\d{5,6}" title="Debe ser un número de 5 o 6 dígitos">
                </div>
                
                <button type="submit" class="btn-generate">
                    <span class="btn-text"><i class="bi bi-magic"></i> Generar Jumper</span>
                    <span class="spinner-border spinner-border-sm" style="display: none;" role="status" aria-hidden="true"></span>
                </button>
            </form>
        </div>

        <!-- Contenedor de Resultado y Rating -->
        <div id="generator-result-container" style="grid-column: span 12;">
            <!-- dashboard-script.js inyectará la plantilla "template-jumper-success" aquí -->
        </div>
    </div>
<?php endif; ?>