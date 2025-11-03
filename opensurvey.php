<?php
// modules/opensurvey.php
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

<h1 class="module-title">Generador Opensurvey</h1>
<p class="module-subtitle">Pega la URL de Opensurvey/Reppublika para generar tu jumper.</p>

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
            <div class="box-header">Generador Opensurvey</div>
            
            <!-- 
                Este formulario es manejado por dashboard-script.js
                data-api="api_generate_opensurvey.php" le dice al script qué API llamar.
            -->
            <form id="generator-form" class="generator-form" data-api="api_generate_opensurvey.php">
                <div class="mb-3">
                    <label for="gen-url" class="form-label">URL de Opensurvey</label>
                    <input type="url" class="form-control-dark" id="gen-url" name="input_url" placeholder="https://opensurvey.reppublika.com/survey/..." required>
                    <div class="form-text">Pega aquí la URL completa. Debe contener 'account', 'project' y 'uuid'.</div>
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
            <!-- Nota: El rating no se mostrará ya que esta API no devuelve un SubID -->
        </div>
    </div>
<?php endif; ?>