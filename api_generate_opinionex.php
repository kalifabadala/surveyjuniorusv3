<?php
// api_generate_opinionex.php (v4.0 - Integración con Membresías y Contador)
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once 'config.php';
require_once 'functions.php';
require_once 'maintenance_check.php'; // Comprobar Modo Mantenimiento

// --- 1. Auth ---
if (!isset($_SESSION['user']) || !($user = $_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

// --- 2. VALIDACIÓN DE MEMBRESÍA (¡NUEVO!) ---
$membership_type = $user['membership_type'] ?? 'VENCIDO';
$jumper_count = (int)($user['jumper_count'] ?? 0);
$jumper_limit = (int)($user['jumper_limit'] ?? 0);
$membership_expires = $user['membership_expires'] ? new DateTime($user['membership_expires']) : null;
$now = new DateTime();

$can_generate = false;
$error_message = 'Tu membresía ha vencido. Por favor, renueva tu plan.';

switch ($membership_type) {
    case 'ADMINISTRADOR':
        $can_generate = true;
        break;
        
    case 'PRO':
        if ($membership_expires && $membership_expires > $now) {
            $can_generate = true;
        } else {
            // El plan PRO venció
            $stmt = $pdo->prepare("UPDATE usuarios SET membership_type = 'VENCIDO' WHERE id = ?");
            $stmt->execute([$user['id']]);
            $_SESSION['user']['membership_type'] = 'VENCIDO'; // Actualiza la sesión
        }
        break;
        
    case 'PRUEBA GRATIS':
        if ($jumper_count < $jumper_limit) {
            $can_generate = true;
        } else {
            // Se acabaron los usos de prueba
            $stmt = $pdo->prepare("UPDATE usuarios SET membership_type = 'VENCIDO' WHERE id = ?");
            $stmt->execute([$user['id']]);
            $_SESSION['user']['membership_type'] = 'VENCIDO'; // Actualiza la sesión
            $error_message = 'Has agotado tus ' . $jumper_limit . ' usos de prueba gratis. ¡Renueva para continuar!';
        }
        break;
        
    case 'VENCIDO':
    default:
        $can_generate = false;
        break;
}

if (!$can_generate) {
    http_response_code(403); // 403 Forbidden
    echo json_encode(['success' => false, 'message' => $error_message, 'error_type' => 'membership_expired']);
    exit;
}
// --- FIN VALIDACIÓN DE MEMBRESÍA ---


// --- 3. Validación Sesión Única ---
if (isset($user['id']) && isset($_SESSION['session_token'])) {
    try {
        $stmt_check = $pdo->prepare("SELECT current_session_token FROM usuarios WHERE id = ?");
        $stmt_check->execute([$user['id']]);
        $db_token = $stmt_check->fetchColumn();
        if ($db_token !== $_SESSION['session_token'] && $user['membership_type'] !== 'ADMINISTRADOR') { // Admin puede tener múltiples sesiones
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
// --- FIN VALIDACIÓN SESIÓN ÚNICA ---

// 4. Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// 5. Obtener y validar input
// ¡IMPORTANTE! Usamos 'input_url_opinion' como se define en el módulo HTML
$input_url = trim($_POST['input_url_opinion'] ?? '');
if (empty($input_url) || !filter_var($input_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'URL no válida proporcionada.']);
    logActivity($pdo, $user['id'], $user['username'], 'Generar OpinionEx API Fallido', 'URL inválida');
    exit;
}

$parts = parse_url($input_url);
if (!isset($parts['query'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La URL no contiene parámetros (query string).']);
    logActivity($pdo, $user['id'], $user['username'], 'Generar OpinionEx API Fallido', 'URL sin query');
    exit;
}

parse_str($parts['query'], $params);
$userUD = $params['UserID'] ?? null;

if ($userUD) {
     if (preg_match('/^[a-zA-Z0-9_-]+$/', $userUD)) {
        $url_final = "https://opex.panelmembers.io/p/exit?s=c&session=" . urlencode($userUD);
        
        // --- ¡ARREGLO DE BUG DE CONTADOR! ---
        // Incrementar el contador SIEMPRE (excepto para admin)
         {
            $new_count = $jumper_count + 1;
            $stmt_inc = $pdo->prepare("UPDATE usuarios SET jumper_count = ? WHERE id = ?");
            $stmt_inc->execute([$new_count, $user['id']]);
            $_SESSION['user']['jumper_count'] = $new_count; // Actualiza la sesión
        }
        // --- FIN ARREGLO ---
        
        logActivity($pdo, $user['id'], $user['username'], 'Generar OpinionEx API Exitoso');
        
        // Devolvemos una respuesta compatible con la de Meinungsplatz
        echo json_encode([
            'success' => true, 
            'jumper' => $url_final,
            'subid' => $userUD, // Usamos el UserID como SubID para la UI
            'added_by' => 'N/A' // No aplica para este módulo
        ]);
        
     } else {
         http_response_code(400);
         echo json_encode(['success' => false, 'message' => 'El UserID encontrado contiene caracteres inválidos.']);
         logActivity($pdo, $user['id'], $user['username'], 'Generar OpinionEx API Fallido', 'UserID inválido');
     }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La URL proporcionada no contiene el parámetro UserID.']);
    logActivity($pdo, $user['id'], $user['username'], 'Generar OpinionEx API Fallido', 'UserID faltante');
}
exit;
?>