<?php
// api_add_subid.php (v8.0 - Integración con Membresías)
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once 'config.php';
require_once 'functions.php';
require_once 'maintenance_check.php'; // Comprobar Modo Mantenimiento

// --- ¡ACTUALIZADO! Auth y Permisos ---
// Solo 'ADMINISTRADOR' y 'PRO' pueden añadir SubIDs.
if (!isset($_SESSION['user']) || !($user = $_SESSION['user']) || !in_array($user['membership_type'], ['ADMINISTRADOR', 'PRO'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para añadir SubIDs. Se requiere membresía PRO o superior.']);
    exit;
}
$userId = $user['id']; // Obtener el ID del usuario
// --- FIN AUTH ---

// --- Validación Sesión Única ---
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
// --- Fin Validación ---

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Validar Inputs
$projektnummer = trim($_POST['projektnummer'] ?? '');
$newSubid = trim($_POST['new_subid'] ?? '');

$isProjektnummerValid = ctype_digit($projektnummer) && (strlen($projektnummer) == 5 || strlen($projektnummer) == 6);
$isSubidValid = !empty($newSubid) && strlen($newSubid) <= 50;

if (empty($projektnummer) || empty($newSubid) || !$isProjektnummerValid || !$isSubidValid) {
    http_response_code(400);
    $errorMsg = 'Datos inválidos. Projektnummer debe ser 5 o 6 dígitos y SubID no puede estar vacío (max 50).';
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    logActivity($pdo, $user['id'], $user['username'], 'Añadir SubID Fallido', 'Formato inválido: ' . $errorMsg);
    exit;
}

try {
    // La función ya fue actualizada en functions.php para aceptar $userId
    if (addProjektnummerSubidMap($pdo, $projektnummer, $newSubid, $userId)) { 
        logActivity($pdo, $user['id'], $user['username'], 'Añadir SubID Exitoso', "P:{$projektnummer}, S:{$newSubid}");
        echo json_encode(['success' => true, 'message' => '¡SubID añadido con éxito!', 'subid' => $newSubid]);
    } else {
        // Verificar si falló por duplicado
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM projektnummer_subid_map WHERE projektnummer = ? AND subid = ?");
        $stmt_check->execute([$projektnummer, $newSubid]);
        if ($stmt_check->fetchColumn() > 0) {
            http_response_code(409);
             echo json_encode(['success' => false, 'message' => 'Este SubID ya está registrado para este Projektnummer.']);
             logActivity($pdo, $user['id'], $user['username'], 'Añadir SubID Fallido', "Duplicado: P:{$projektnummer}, S:{$newSubid}");
        } else {
             http_response_code(500);
             echo json_encode(['success' => false, 'message' => 'No se pudo añadir el SubID (Error de DB).']);
              logActivity($pdo, $user['id'], $user['username'], 'Añadir SubID Fallido', "Error DB: P:{$projektnummer}, S:{$newSubid}");
        }
    }
} catch (Exception $e) {
    error_log("Unexpected error in api_add_subid: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado.']);
     logActivity($pdo, $user['id'], $user['username'], 'Añadir SubID Fallido', "Excepción: {$e->getMessage()}");
}
?>