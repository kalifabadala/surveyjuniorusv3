<?php
// api_login.php
// Este script maneja el inicio de sesión asíncrono desde el modal de la landing page.
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config.php';
require 'functions.php';

header('Content-Type: application/json');

// 1. Validar Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// 2. Obtener datos del JSON
$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$remember_me = !empty($input['remember_me']);
$ip_address = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
if (strpos($ip_address, ',') !== false) { $ip_address = trim(explode(',', $ip_address)[0]); }

// 3. Anti-Fuerza Bruta
if (function_exists('isLoginBlocked') && isLoginBlocked($pdo, $ip_address)) {
    if (function_exists('logActivity')) logActivity($pdo, null, $username, 'Login API Fallido (Bloqueado)', $ip_address);
    echo json_encode(['success' => false, 'message' => 'Demasiados intentos fallidos. Inténtalo en 15 minutos.']);
    exit;
}

// 4. Validar Credenciales (Lógica copiada de login.php)
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // --- Contraseña Correcta ---
        
        // Comprobar si está activo/baneado
        if ($user['active'] == 0 || $user['banned'] == 1) {
            if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Login API Fallido (Inactivo)', $ip_address);
            echo json_encode(['success' => false, 'message' => 'Tu cuenta está inactiva o baneada.']);
            exit;
        }

        // --- Login Exitoso ---
        if (function_exists('clearLoginAttempts')) clearLoginAttempts($pdo, $ip_address);
        session_regenerate_id(true); // Prevenir fijación de sesión

        // Generar y guardar el token de sesión única
        $session_token = bin2hex(random_bytes(32));
        $stmt_token = $pdo->prepare("UPDATE usuarios SET current_session_token = ? WHERE id = ?");
        $stmt_token->execute([$session_token, $user['id']]);

        $_SESSION['session_token'] = $session_token;
        $user['current_session_token'] = $session_token; // Añadir a la sesión
        $_SESSION['user'] = $user; // ¡Guardar usuario en la sesión!

        // Manejar "Recordarme"
        if ($remember_me && function_exists('rememberUser')) {
            rememberUser($pdo, $user['id']);
        }

        if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Login API Exitoso', $ip_address);
        if (function_exists('updateUserActivity')) updateUserActivity($pdo, $user['id']);
        
        // ¡Éxito! Enviar la URL de redirección
        echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
        exit;

    } else {
        // --- Login Fallido ---
        if (function_exists('recordFailedLogin')) recordFailedLogin($pdo, $ip_address);
        if (function_exists('logActivity')) logActivity($pdo, null, $username, 'Login API Fallido (Credenciales)', $ip_address);
        echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
        exit;
    }

} catch (PDOException $e) {
    error_log("API Login Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de la base de datos.']);
    exit;
}
?>