<?php
// modules/ranking.php
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

<h1 class="module-title">Ranking de Colaboradores</h1>
<p class="module-subtitle">El podio de los usuarios que más SubIDs han aportado a la base de datos.</p>

<!-- 
    Este contenedor será rellenado por JS.
    dashboard-script.js detectará que el módulo "ranking" se cargó,
    llamará a fetchRankingData() y rellenará este div.
-->
<div id="ranking-podium-container">
    
    <!-- Skeleton Loading (Vista previa de carga) -->
    <div class="podium">
        <div class="podium-card rank-2 skeleton-item">
            <div class="skeleton-box" style="width: 80px; height: 80px; border-radius: 50%; margin: 1rem auto 0.5rem auto;"></div>
            <div class="skeleton-box" style="width: 80%; height: 24px; margin-bottom: 0.5rem; margin-left: 10%;"></div>
            <div class="skeleton-box" style="width: 50%; height: 30px; margin-left: 25%;"></div>
        </div>
        <div class="podium-card rank-1 skeleton-item">
            <div class="skeleton-box" style="width: 100px; height: 100px; border-radius: 50%; margin: 1rem auto 0.5rem auto;"></div>
            <div class="skeleton-box" style="width: 80%; height: 24px; margin-bottom: 0.5rem; margin-left: 10%;"></div>
            <div class="skeleton-box" style="width: 50%; height: 30px; margin-left: 25%;"></div>
        </div>
        <div class="podium-card rank-3 skeleton-item">
            <div class="skeleton-box" style="width: 80px; height: 80px; border-radius: 50%; margin: 1rem auto 0.5rem auto;"></div>
            <div class="skeleton-box" style="width: 80%; height: 24px; margin-bottom: 0.5rem; margin-left: 10%;"></div>
            <div class="skeleton-box" style="width: 50%; height: 30px; margin-left: 25%;"></div>
        </div>
    </div>
    
    <div class="bento-box mt-4 skeleton-item">
        <div class="box-header"><span class="skeleton-box" style="width: 200px; height: 20px;"></span></div>
        <ul class="ranking-list">
            <li class="ranking-item skeleton-item"><span class="skeleton-box" style="width: 100%; height: 40px;"></span></li>
            <li class="ranking-item skeleton-item"><span class="skeleton-box" style="width: 100%; height: 40px;"></span></li>
            <li class="ranking-item skeleton-item"><span class="skeleton-box" style="width: 100%; height: 40px;"></span></li>
        </ul>
    </div>
    
</div>