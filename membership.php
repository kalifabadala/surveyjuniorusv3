<?php
// modules/membership.php
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

// --- Lógica de la Página de Membresía ---

// 1. Obtener el historial de pagos de este usuario
$payments = [];
try {
    $stmt = $pdo->prepare(
        "SELECT created_at, amount_bs, reference_number, method, status 
         FROM payment_proofs 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 10"
    );
    $stmt->execute([$user['id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar historial de pagos: " . $e->getMessage());
    // No mostrar error al usuario, solo dejar la tabla vacía
}

// 2. Definir variables de estado (para el HTML)
$status_icon = 'bi-exclamation-triangle-fill';
$status_class = 'expired';
$status_text = 'Desconocido';
$status_date = 'Contacta a soporte.';

switch ($user['membership_type']) {
    case 'ADMINISTRADOR':
        $status_icon = 'bi-shield-shaded';
        $status_class = 'admin';
        $status_text = 'Membresía Administrador';
        $status_date = 'Acceso total e ilimitado.';
        break;
    case 'PRO':
        $expires = $user['membership_expires'] ? (new DateTime($user['membership_expires']))->format('d/m/Y') : 'N/A';
        $status_icon = 'bi-patch-check-fill';
        $status_class = 'active';
        $status_text = 'Membresía PRO Activa';
        $status_date = 'Vence el: ' . $expires;
        break;
    case 'PRUEBA GRATIS':
        $status_icon = 'bi-hourglass-split';
        $status_class = 'trial';
        $status_text = 'Prueba Gratis';
        $status_date = "Usos restantes: " . ($user['jumper_limit'] - $user['jumper_count']);
        break;
    case 'VENCIDO':
        $status_icon = 'bi-x-octagon-fill';
        $status_class = 'expired';
        $status_text = 'Membresía Vencida';
        $status_date = 'Por favor, renueva tu plan para continuar.';
        break;
}

?>

<h1 class="module-title">Membresía</h1>
<p class="module-subtitle">Gestiona tu suscripción, métodos de pago e historial.</p>

<!-- Grid de Membresía -->
<div class="bento-grid">

    <!-- Caja 1: Estado de la Suscripción -->
    <div class="bento-box box-status">
        <div class="box-header">Estado Actual</div>
        
        <div class="status-display">
            <div class="status-icon <?= $status_class ?>"><i class="bi <?= $status_icon ?>"></i></div>
            <div class="status-text <?= $status_class ?>"><?= htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="status-date"><?= htmlspecialchars($status_date, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
    
    <!-- Caja 2: Pagomóvil -->
    <div class="bento-box box-pagomovil">
        <div class="box-header">Renovar con Pagomóvil (Bolívares)</div>
        <ul class="payment-details">
            <!-- ¡Actualiza estos datos! -->
            <li><span>Banco:</span> <span>Banesco (0134)</span></li>
            <li><span>Teléfono:</span> <span>0414-000-0000</span></li>
            <li><span>C.I./Rif:</span> <span>V-12.345.678</span></li>
            <li><span>Monto (1 Mes):</span> <span>Bs. 150,00</span></li>
        </ul>
        <button class="btn-upload" data-bs-toggle="modal" data-bs-target="#uploadProofModal">
            <i class="bi bi-upload"></i> Reportar Pago
        </button>
    </div>
    
    <!-- Caja 3: PayPal -->
    <div class="bento-box box-paypal">
        <div class="box-header">Renovar con PayPal (USD)</div>
        <ul class="payment-details">
            <li><span>Monto (1 Mes):</span> <span>$5.00 USD</span></li>
            <li><span>Correo:</span> <span>tu-paypal@email.com</span></li>
        </ul>
        <!-- 
            dashboard-script.js detectará este módulo y
            llamará a handlePaypalRender() para llenar este div.
        -->
        <div id="paypal-button-container">
            <div class="skeleton-box" style="height: 45px; border-radius: 50px;"></div>
        </div>
    </div>
    
    <!-- Caja 4: Historial de Pagos -->
    <div class="bento-box box-history">
        <div class="box-header">Mi Historial de Pagos</div>
        <div class="table-wrapper">
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Monto</th>
                        <th>Referencia/Método</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted);">No tienes pagos registrados.</td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= (new DateTime($payment['created_at']))->format('d/m/Y') ?></td>
                            <td>
                                <?= htmlspecialchars($payment['method'] === 'Pagomóvil' ? ('Bs. ' . $payment['amount_bs']) : '$5.00', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($payment['method'] === 'Pagomóvil' ? $payment['reference_number'] : 'PayPal', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?php
                                $status = htmlspecialchars($payment['status'], ENT_QUOTES, 'UTF-8');
                                $class = 'status-pendiente';
                                if ($status == 'COMPLETADO') $class = 'status-completado';
                                elseif ($status == 'RECHAZADO') $class = 'status-rechazado';
                                echo "<span class='status-badge {$class}'>{$status}</span>";
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
</div>