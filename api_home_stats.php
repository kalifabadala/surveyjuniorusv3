<?php
// api_home_stats.php (v3.2 - Arregla bug de SQL 'Error')
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once 'config.php';
require_once 'functions.php';
require_once 'maintenance_check.php'; // Comprobar Modo Mantenimiento

// --- Auth y Permisos (Cualquier usuario logueado) ---
if (!isset($_SESSION['user']) || !($user_session = $_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}
$userId = $user_session['id'];

// --- Validación Sesión Única ---
if (isset($user_session['id']) && isset($_SESSION['session_token'])) {
    try {
        $stmt_check = $pdo->prepare("SELECT current_session_token FROM usuarios WHERE id = ?");
        $stmt_check->execute([$userId]);
        $db_token = $stmt_check->fetchColumn();
        if ($db_token !== $_SESSION['session_token'] && $user_session['membership_type'] !== 'ADMINISTRADOR') {
            http_response_code(401); 
            echo json_encode(['success' => false, 'message' => 'Sesión inválida (iniciada en otro dispositivo).']);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error validando token de sesión en API: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de DB al verificar sesión.']);
        exit;
    }
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no encontrada.']);
    exit;
}
// --- Fin Validación ---

// Actualizar actividad
if (function_exists('updateUserActivity')) {
    updateUserActivity($pdo, $userId);
}

$response = [
    'success' => true,
    'stats' => []
];

try {
    // --- OBTENER DATOS FRESCOS ---
    $stmt_fresh_user = $pdo->prepare("SELECT jumper_count FROM usuarios WHERE id = ?");
    $stmt_fresh_user->execute([$userId]);
    $fresh_jumper_count = (int)$stmt_fresh_user->fetchColumn();
    
    // Actualizar la sesión
    $_SESSION['user']['jumper_count'] = $fresh_jumper_count;

    // 1. Obtener el contador de jumpers (¡LA FUENTE DE VERDAD!)
    $total_jumpers_all_time = $fresh_jumper_count; // Usamos el valor fresco

    // 2. Contar Jumpers (Este Mes)
    $stmt_jumpers_month = $pdo->prepare("
        SELECT COUNT(*) FROM activity_log 
        WHERE user_id = ? 
        AND (action LIKE 'Generar % API Exitoso')
        AND MONTH(timestamp) = MONTH(CURRENT_DATE())
        AND YEAR(timestamp) = YEAR(CURRENT_DATE())
    ");
    $stmt_jumpers_month->execute([$userId]);
    $total_jumpers_month = (int) $stmt_jumpers_month->fetchColumn();

    // 3. Contar SubIDs Aportados (Total)
    $stmt_subids = $pdo->prepare("
        SELECT COUNT(*) FROM projektnummer_subid_map
        WHERE added_by_user_id = ?
    ");
    $stmt_subids->execute([$userId]);
    $total_subids = (int) $stmt_subids->fetchColumn();
    
    // 4. Obtener Rango del Ranking de SubIDs
    $subid_rank = 0;
    
    // --- ¡CORRECCIÓN DEL BUG DE SQL! ---
    // La columna se llama 'added_by_user_id'
    $stmt_rank_query = $pdo->prepare("
        SELECT added_by_user_id, COUNT(*) as count
        FROM projektnummer_subid_map
        WHERE added_by_user_id IS NOT NULL
        GROUP BY added_by_user_id
        ORDER BY count DESC
    ");
    // --- FIN DE LA CORRECCIÓN ---
    
    $stmt_rank_query->execute();
    $rank_data = $stmt_rank_query->fetchAll(PDO::FETCH_ASSOC);
    
    $current_rank = 1;
    foreach ($rank_data as $row) {
        if ($row['added_by_user_id'] == $userId) {
            $subid_rank = $current_rank;
            break;
        }
        $current_rank++;
    }

    // 5. Determinar Rango de Gamificación (basado en total de jumpers)
    $rank_name = "Novato";
    $rank_level = floor($total_jumpers_all_time / 10); // 1 Nivel cada 10 jumpers
    if ($total_jumpers_all_time >= 1000) $rank_name = "Leyenda";
    elseif ($total_jumpers_all_time >= 500) $rank_name = "Maestro";
    elseif ($total_jumpers_all_time >= 100) $rank_name = "Pro";
    elseif ($total_jumpers_all_time >= 25) $rank_name = "Avanzado";
    

    $response['stats'] = [
        'total_jumpers_all_time' => $total_jumpers_all_time,
        'total_jumpers_month' => $total_jumpers_month,
        'total_subids' => $total_subids,
        'subid_rank' => $subid_rank > 0 ? $subid_rank : 'N/A',
        'rank_name' => $rank_name,
        'rank_level' => $rank_level
    ];

} catch (PDOException $e) {
    error_log("Error en api_home_stats: " . $e->getMessage());
    http_response_code(500);
    // ¡Aquí es donde se genera el "Error"!
    echo json_encode(['success' => false, 'message' => 'Error al consultar estadísticas de la base de datos: ' . $e->getMessage()]);
    exit;
}

echo json_encode($response);
exit;
?>