<?php
// api_generate.php (v9.1 - Arreglo de Contador de Admin)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Pragma: no-cache');

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

// --- 2. VALIDACIÓN DE MEMBRESÍA (Gatekeeper) ---
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
            $_SESSION['user']['membership_type'] = 'VENCIDO';
        }
        break;
        
    case 'PRUEBA GRATIS':
        if ($jumper_count < $jumper_limit) {
            $can_generate = true;
        } else {
            // Se acabaron los usos de prueba
            $stmt = $pdo->prepare("UPDATE usuarios SET membership_type = 'VENCIDO' WHERE id = ?");
            $stmt->execute([$user['id']]);
            $_SESSION['user']['membership_type'] = 'VENCIDO';
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
            http_response_code(401); // 401 Unauthorized
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

// 5. Obtener y Validar Inputs
$urls = $_POST['urls'] ?? '';
$projektnummer = trim($_POST['projektnummer'] ?? '');
$isProjektnummerValid = ctype_digit($projektnummer) && (strlen($projektnummer) == 5 || strlen($projektnummer) == 6);

if (empty($urls) || empty($projektnummer) || !$isProjektnummerValid) {
    http_response_code(400);
    $errorMsg = 'Datos inválidos. Asegúrate de pegar las URLs y un Projektnummer de 5 o 6 dígitos.';
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    logActivity($pdo, $user['id'], $user['username'], 'Generar Meinungsplatz API Fallido', 'Datos inválidos: ' . $errorMsg);
    exit;
}

// 6. Encontrar el user_id de 15 dígitos
$user_id = null;
try {
    $lines = explode("\n", str_replace("\r", "", $urls));
    foreach ($lines as $line) {
        $trimmed_line = trim($line);
        if (empty($trimmed_line)) continue;
        $query_string = parse_url($trimmed_line, PHP_URL_QUERY);
        if ($query_string) {
            parse_str($query_string, $params);
            if (is_array($params)){
                foreach ($params as $key => $value) {
                    if (is_string($value) && ctype_digit($value) && strlen($value) === 15) {
                        $user_id = $value; break 2;
                    }
                }
            }
        }
        if (!$user_id && preg_match('/[?&](?:m|UserID|uid|id)=([0-9]{15})(?:&|$)/i', $trimmed_line, $matches)) {
             $user_id = $matches[1]; break;
        }
    }
} catch (Exception $e) {
     error_log("Error parsing URLs in api_generate: " . $e->getMessage());
     http_response_code(400);
     echo json_encode(['success' => false, 'message' => 'Error al procesar las URLs ingresadas.']);
     exit;
}

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se encontró un ID de usuario (15 dígitos) en las URLs proporcionadas.']);
    logActivity($pdo, $user['id'], $user['username'], 'Generar Meinungsplatz API Fallido', 'ID Usuario 15d no encontrado');
    exit;
}

// 7. Buscar el SubID en la base de datos (¡ACTUALIZADO!)
$subid_data = null;
try {
    // findSubidForProjektnummer ahora devuelve un array ['subid' => ..., 'added_by' => ...]
    $subid_data = findSubidForProjektnummer($pdo, $projektnummer); 
} catch (Throwable $e) {
     error_log("Throwable calling findSubidForProjektnummer: " . $e->getMessage());
     http_response_code(500);
     echo json_encode(['success' => false, 'message' => 'Error fatal al buscar SubID: ' . $e->getMessage()]);
     exit;
}

// --- Continuar con la lógica normal ---
if ($subid_data !== null) {
    // 8. Éxito: Generar el JUMPER
    try {
        $subid = $subid_data['subid'];
        $added_by = $subid_data['added_by'];
        
        $jumper = "https://survey.maximiles.com/complete?p=" . urlencode($projektnummer . '_' . $subid) . "&m=" . urlencode($user_id);
        
        // --- ¡ARREGLO DE BUG DE CONTADOR (v2)! ---
        // Incrementar el contador SIEMPRE (Admin y usuarios).
        // La lógica de "can_generate" ya protegió a los usuarios VENCIDOS.
        $new_count = $jumper_count + 1;
        $stmt_inc = $pdo->prepare("UPDATE usuarios SET jumper_count = ? WHERE id = ?");
        $stmt_inc->execute([$new_count, $user['id']]);
        $_SESSION['user']['jumper_count'] = $new_count; // Actualiza la sesión
        // --- FIN ARREGLO ---
        
        logActivity($pdo, $user['id'], $user['username'], 'Generar Meinungsplatz API Exitoso', "P:{$projektnummer}, S:{$subid}");

        $json_output = json_encode([
            'success' => true,
            'message' => "¡JUMPER Generado con éxito!",
            'jumper' => $jumper,
            'subid' => $subid,
            'projektnummer' => $projektnummer,
            'added_by' => $added_by // ¡NUEVO!
        ]);
        
        if (json_last_error() !== JSON_ERROR_NONE) { throw new Exception("JSON Encoding Error: " . json_last_error_msg()); }
        echo $json_output;

    } catch (Exception $e) {
         error_log("Error generating success response in api_generate: " . $e->getMessage());
         http_response_code(500);
         echo json_encode(['success' => false, 'message' => 'Error interno al generar respuesta.']);
    }

} else {
    // 9. Error: SubID no encontrado
    logActivity($pdo, $user['id'], $user['username'], 'Generar Meinungsplatz API Fallido', "SubID no encontrado P:{$projektnummer}");
    echo json_encode([
        'success' => false,
        'error_type' => 'subid_not_found',
        'message' => "No tenemos SubID para Projektnummer <strong>".htmlspecialchars($projektnummer, ENT_QUOTES, 'UTF-8')."</strong>.",
        'projektnummer' => $projektnummer
    ]);
}
exit;
?>
```eof