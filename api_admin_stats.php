<?php
// api_admin_stats.php (v2.0 - Integración con Membresías)
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once 'config.php';
require_once 'functions.php'; // Para updateUserActivity y validación de token

// --- ¡ACTUALIZADO! Auth y Permisos (Solo Admin) ---
if (!isset($_SESSION['user']) || !($user = $_SESSION['user']) || $user['membership_type'] !== 'ADMINISTRADOR') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo para administradores.']);
    exit;
}
// --- FIN AUTH ---

// --- Validación Sesión Única ---
if (isset($user['id']) && isset($_SESSION['session_token'])) {
    try {
        $stmt_check = $pdo->prepare("SELECT current_session_token FROM usuarios WHERE id = ?");
        $stmt_check->execute([$user['id']]);
        $db_token = $stmt_check->fetchColumn();
        if ($db_token !== $_SESSION['session_token']) {
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

// Actualizar actividad del admin
if (function_exists('updateUserActivity')) {
    updateUserActivity($pdo, $user['id']);
}

$response = [
    'success' => true,
    'stats' => [],
    'chart_data' => []
];

try {
    // 1. Estadísticas de Tarjetas
    $stmt_total = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $response['stats']['totalUsers'] = $stmt_total->fetchColumn();

    $stmt_online = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE online = 1");
    $response['stats']['onlineUsers'] = $stmt_online->fetchColumn();

    $stmt_admins = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE membership_type = 'ADMINISTRADOR'"); // ¡Actualizado!
    $response['stats']['adminCount'] = $stmt_admins->fetchColumn();
    
    // 2. Datos del Gráfico (Actividad de Jumpers y Logins en los últimos 7 días)
    $labels = [];
    $jumpersData = [];
    $loginsData = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M d', strtotime($date));
        
        // Contar Jumpers (Sin cambios, esto es global)
        $stmt_jumpers = $pdo->prepare("
            SELECT COUNT(*) FROM activity_log 
            WHERE DATE(timestamp) = ? 
            AND (action = 'Generar Opensurvey API Exitoso' 
                 OR action = 'Generar OpinionEx API Exitoso' 
                 OR action = 'Generar Meinungsplatz API Exitoso')
        ");
        $stmt_jumpers->execute([$date]);
        $jumpersData[] = (int) $stmt_jumpers->fetchColumn();
        
        // Contar Logins (Sin cambios, esto es global)
        $stmt_logins = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE DATE(timestamp) = ? AND action LIKE 'Login%'");
        $stmt_logins->execute([$date]);
        $loginsData[] = (int) $stmt_logins->fetchColumn();
    }
    
    $response['chart_data'] = [
        'labels' => $labels,
        'jumpers' => $jumpersData,
        'logins' => $loginsData
    ];

    // 3. Estado del Modo Mantenimiento
    $response['stats']['maintenance_mode'] = file_exists('MAINTENANCE');

} catch (PDOException $e) {
    error_log("Error en api_admin_stats: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al consultar estadísticas de la base de datos.']);
    exit;
}

echo json_encode($response);
exit;
?>
```eof

---

### 2. Arreglo para Acciones del Admin (Purgar, Mantenimiento, etc.)

Este archivo arreglará el "permiso denegado" al hacer clic en "Activar Mantenimiento", "Purgar Caché", etc.

**Sobrescribe** tu `api_admin_actions.php` con este código:

```php:api_admin_actions.php (¡Actualizado!):api_admin_actions.php
<?php
// api_admin_actions.php (v2.0 - Integración con Membresías)
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once 'config.php';
require_once 'functions.php'; 

// --- ¡ACTUALIZADO! Auth y Permisos (Solo Admin) ---
if (!isset($_SESSION['user']) || !($user = $_SESSION['user']) || $user['membership_type'] !== 'ADMINISTRADOR') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo para administradores.']);
    exit;
}
// --- FIN AUTH ---

// --- Validación Sesión Única ---
if (isset($user['id']) && isset($_SESSION['session_token'])) {
    try {
        $stmt_check = $pdo->prepare("SELECT current_session_token FROM usuarios WHERE id = ?");
        $stmt_check->execute([$user['id']]);
        $db_token = $stmt_check->fetchColumn();
        if ($db_token !== $_SESSION['session_token']) {
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

// Leer el cuerpo de la solicitud JSON
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Acción no especificada.']);
    exit;
}

try {
    switch ($action) {
        
        case 'toggle_maintenance':
            $new_status = $input['value'] ?? false;
            $maintenance_file = 'MAINTENANCE';
            if ($new_status === true) {
                // Activar modo mantenimiento
                file_put_contents($maintenance_file, 'Activado el ' . date('c'));
                logActivity($pdo, $user['id'], $user['username'], 'Admin: Mantenimiento ACTIVADO');
                echo json_encode(['success' => true, 'message' => 'Modo Mantenimiento ACTIVADO.']);
            } else {
                // Desactivar modo mantenimiento
                if (file_exists($maintenance_file)) {
                    unlink($maintenance_file);
                }
                logActivity($pdo, $user['id'], $user['username'], 'Admin: Mantenimiento DESACTIVADO');
                echo json_encode(['success' => true, 'message' => 'Modo Mantenimiento DESACTIVADO.']);
            }
            break;

        case 'clear_logs':
            // Limpiar logs de más de 30 días
            $stmt = $pdo->prepare("DELETE FROM activity_log WHERE timestamp < NOW() - INTERVAL 30 DAY");
            $stmt->execute();
            $rowsAffected = $stmt->rowCount();
            logActivity($pdo, $user['id'], $user['username'], 'Admin: Limpiar Logs Antiguos', "Registros eliminados: {$rowsAffected}");
            echo json_encode(['success' => true, 'message' => "Se eliminaron {$rowsAffected} registros de log antiguos."]);
            break;

        case 'force_logout':
            // Forzar cierre de sesión de todos
            $stmt = $pdo->prepare("UPDATE usuarios SET current_session_token = NULL");
            $stmt->execute();
            $rowsAffected = $stmt->rowCount();
            // Desloguear al admin actual también
            $_SESSION['session_token'] = null; // Esto hará que su próxima acción falle y pida login
            logActivity($pdo, $user['id'], $user['username'], 'Admin: Forzar Cierre de Sesiones', "Sesiones cerradas: {$rowsAffected}");
            echo json_encode(['success' => true, 'message' => "Se cerraron {$rowsAffected} sesiones. Serás desconectado."]);
            break;

        case 'purge_cache':
            // Esta función requiere configuración en config.php
            if (defined('CLOUDFLARE_API_KEY') && defined('CLOUDFLARE_ZONE_ID') && defined('CLOUDFLARE_EMAIL') && CLOUDFLARE_API_KEY !== '') {
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones/" . CLOUDFLARE_ZONE_ID . "/purge_cache");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['purge_everything' => true]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'X-Auth-Email: ' . CLOUDFLARE_EMAIL,
                    'X-Auth-Key: ' . CLOUDFLARE_API_KEY,
                    'Content-Type: application/json'
                ]);
                
                $result = curl_exec($ch);
                curl_close($ch);
                
                logActivity($pdo, $user['id'], $user['username'], 'Admin: Purgar Caché Cloudflare');
                echo json_encode(['success' => true, 'message' => 'Se envió la solicitud para purgar el caché de Cloudflare.', 'details' => $result]);

            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Las credenciales de API de Cloudflare no están configuradas en config.php.']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción desconocida.']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de Base de Datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error General: ' . $e->getMessage()]);
}
exit;
?>
```eof

---

### 3. Arreglo para Añadir SubID (Permisos)

Este archivo arreglará el "permiso denegado" al intentar añadir un SubID. Tu lógica era correcta: "ADMINISTRADOR" y "PRO" deben poder añadir, "PRUEBA GRATIS" y "VENCIDO" no.

**Sobrescribe** tu `api_add_subid.php` con este código:

```php:api_add_subid.php (¡Actualizado!):api_add_subid.php
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
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para añadir SubIDs. Se requiere membresía PRO.']);
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
        if ($db_token !== $_SESSION['session_token']) {
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
```eof

---

Después de subir estos tres (3) archivos, tus problemas de permisos de admin y el contador deberían estar 100% resueltos.