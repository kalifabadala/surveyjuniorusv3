<?php
// api_ping.php
// Un simple "heartbeat" para mantener viva la sesión del usuario
// cuando hace clic en "Continuar Sesión" en el modal de inactividad.

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'functions.php';

// Auth: Solo necesitamos saber que el usuario *existe*
if (!isset($_SESSION['user']) || !($user = $_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

// La acción principal:
// Llama a la función que actualiza el "last_activity" en la base de datos.
if (function_exists('updateUserActivity')) {
    updateUserActivity($pdo, $user['id']);
    
    // Devolver una respuesta exitosa
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Session extended.']);
    exit;
}

// Fallback por si acaso
http_response_code(500);
echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
?>