<?php
// admin.php (v18.0 - Integración Completa de Membresías y Pagos)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config.php';
require 'functions.php'; // Carga el nuevo functions.php (v7.0)

// --- ¡ACTUALIZADO! Auth con Membresía ---
// 1. Comprobar si hay sesión
if (!isset($_SESSION['user'])) {
    // Si no hay sesión, intentar "Recordarme"
    if (function_exists('validateRememberMe')) { 
        $userFromCookie = validateRememberMe($pdo); 
        if (!$userFromCookie) {
            header('Location: login.php'); exit; // Falla "Recordarme", ir a login
        }
    } else {
        error_log("Error: validateRememberMe function not found");
        header('Location: login.php'); exit;
    }
}

// 2. Comprobar si el usuario en sesión es ADMINISTRADOR
if (!isset($_SESSION['user']) || $_SESSION['user']['membership_type'] !== 'ADMINISTRADOR') {
     if (isset($_COOKIE[REMEMBER_ME_COOKIE_NAME]) && function_exists('clearRememberMeCookie')) { 
         clearRememberMeCookie(); // Limpiar cookie si no es admin
     }
     // Si no es admin, destruir sesión y redirigir
     session_unset(); session_destroy();
     header('Location: login.php?error=Acceso+denegado.'); exit;
}

// Si llegamos aquí, el usuario es un Admin validado
$user = $_SESSION['user'];
// --- Fin Auth ---

$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
if (function_exists('updateUserActivity')) { updateUserActivity($pdo, $user['id']); }

$message = ''; $message_type = 'info';
$section = $_GET['section'] ?? 'dashboard';
$action_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
if (strpos($action_ip, ',') !== false) { $action_ip = trim(explode(',', $action_ip)[0]); }

// --- LÓGICA DE ACCIONES ADMIN (CON LOGGING Y MEMBRESÍAS) ---

// --- ACCIONES DE USUARIO ---

// ¡ACTUALIZADO! Añadir Usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user']) && $section == 'users') {
    $new_username = trim($_POST['new_username']);
    $new_password = $_POST['new_password'];
    $new_membership = $_POST['membership_type'];
    
    if (empty($new_username) || empty($new_password) || empty($new_membership)) { 
        $message = "Usuario, contraseña y tipo de membresía son obligatorios."; $message_type = 'danger'; 
    } elseif (strlen($new_password) < 6) { 
        $message = "La contraseña debe tener al menos 6 caracteres."; $message_type = 'danger'; 
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?"); $stmt->execute([$new_username]);
            if ($stmt->fetch()) { 
                $message = "El nombre de usuario '{$new_username}' ya está en uso."; $message_type = 'warning'; 
            } else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Lógica de membresía
                $jumper_limit = 5; // Por defecto para PRUEBA GRATIS
                $membership_expires = null;
                
                if ($new_membership === 'ADMINISTRADOR') {
                    $jumper_limit = 999999;
                } elseif ($new_membership === 'PRO') {
                    $jumper_limit = 999999;
                    // Por defecto, 30 días si se crea como PRO
                    $membership_expires = date('Y-m-d H:i:s', strtotime('+30 days')); 
                } elseif ($new_membership === 'VENCIDO') {
                    $jumper_limit = 0;
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO usuarios (username, password, active, banned, membership_type, membership_expires, jumper_count, jumper_limit) 
                     VALUES (?, ?, 1, 0, ?, ?, 0, ?)"
                );
                
                if ($stmt->execute([$new_username, $hash, $new_membership, $membership_expires, $jumper_limit])) {
                    $message = "Usuario '{$new_username}' creado exitosamente."; $message_type = 'success';
                    if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Crear Usuario', "Usuario: {$new_username}, Membresía: {$new_membership}");
                } else { 
                    $message = "Error al crear el usuario."; $message_type = 'danger'; 
                }
            }
        } catch (PDOException $e) { 
            error_log("Admin Add User Error: " . $e->getMessage()); $message = "Error de base de datos: " . $e->getMessage(); $message_type = 'danger'; 
        }
    }
}

// ¡NUEVO! Editar Usuario (Reemplaza toggle_active y toggle_generate)
if (isset($_POST['edit_user']) && $section == 'users') {
    $targetUserId = intval($_POST['user_id']);
    $new_active = isset($_POST['active']) ? 1 : 0;
    $new_membership = $_POST['membership_type'];
    $new_expires = empty($_POST['membership_expires']) ? null : $_POST['membership_expires'];
    $new_jumper_count = intval($_POST['jumper_count']);
    $new_jumper_limit = intval($_POST['jumper_limit']);

    try {
        // No se puede editar al usuario 'admin' (asumiendo que ID=1 es admin, o usa username)
        $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE id = ? AND username != 'admin'");
        $stmt->execute([$targetUserId]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            // Ajustar valores basados en membresía
            if ($new_membership === 'ADMINISTRADOR') {
                $new_jumper_limit = 999999; $new_expires = null;
            } elseif ($new_membership === 'PRO') {
                $new_jumper_limit = 999999; // Límite "infinito"
                if (empty($new_expires)) { // Si es PRO y no se pone fecha, darle 30 días
                    $new_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                }
            } elseif ($new_membership === 'PRUEBA GRATIS') {
                $new_jumper_limit = 5; $new_expires = null;
            } elseif ($new_membership === 'VENCIDO') {
                $new_jumper_limit = 0; // Límite 0
            }

            $stmt_update = $pdo->prepare(
                "UPDATE usuarios SET 
                 active = ?, 
                 membership_type = ?, 
                 membership_expires = ?, 
                 jumper_count = ?, 
                 jumper_limit = ? 
                 WHERE id = ?"
            );
            
            if ($stmt_update->execute([$new_active, $new_membership, $new_expires, $new_jumper_count, $new_jumper_limit, $targetUserId])) {
                $message = "Usuario '".htmlspecialchars($targetUser['username'])."' actualizado."; $message_type = 'success';
                if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Editar Usuario', "ID: {$targetUserId}, Nueva Membresía: {$new_membership}, Activo: {$new_active}");
            } else {
                $message = "Error al actualizar usuario."; $message_type = 'danger';
            }
        } else {
            $message = "Usuario no encontrado o no permitido (no se puede editar al admin)."; $message_type = 'warning';
        }
    } catch (PDOException $e) {
        error_log("Admin Edit User Error: " . $e->getMessage()); $message = "Error DB: " . $e->getMessage(); $message_type = 'danger';
    }
}

// Eliminar Usuario (Sin cambios en la lógica, solo se borró 'toggle_generate' y 'toggle_active')
if (isset($_GET['action']) && $_GET['action'] == 'delete_user' && isset($_GET['user_id']) && $section == 'users') {
    $targetUserId = intval($_GET['user_id']);
    try {
        $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE id = ? AND username != 'admin'");
        $stmt->execute([$targetUserId]); $targetUsername = $stmt->fetchColumn();
        if ($targetUsername) {
             $pdo->beginTransaction();
             $stmt_del_tokens = $pdo->prepare("DELETE FROM persistent_logins WHERE user_id = ?"); $stmt_del_tokens->execute([$targetUserId]);
             // ¡NUEVO! Borrar también sus comprobantes pendientes
             $stmt_del_proofs = $pdo->prepare("DELETE FROM payment_proofs WHERE user_id = ?"); $stmt_del_proofs->execute([$targetUserId]);
             
             $stmt_del = $pdo->prepare("DELETE FROM usuarios WHERE id = ?"); $deleteSuccess = $stmt_del->execute([$targetUserId]);
             $pdo->commit();
            if ($deleteSuccess) {
                $message = "Usuario ".htmlspecialchars($targetUsername)." eliminado."; $message_type = 'success';
                if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Eliminar Usuario', "Usuario ID: {$targetUserId} ({$targetUsername})");
            } else { $message = "Error al eliminar usuario."; $message_type = 'danger'; $pdo->rollBack(); }
        } else { $message = "Usuario no encontrado o no permitido."; $message_type = 'warning'; }
    } catch (PDOException $e) { $pdo->rollBack(); error_log("Admin Delete User Error: ".$e->getMessage()); $message="Error DB al eliminar: " . $e->getMessage(); $message_type = 'danger'; }
}
// Cambiar Contraseña (Sin cambios)
if (isset($_POST['change_password']) && !empty($_POST['user_id']) && !empty($_POST['new_password']) && $section == 'users') {
    $targetUserId = intval($_POST['user_id']); $newPassword = $_POST['new_password'];
    if(strlen($newPassword)>=6){
        try {
            $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE id = ? AND username != 'admin'");
            $stmt->execute([$targetUserId]); $targetUsername = $stmt->fetchColumn();
            if ($targetUsername) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $pdo->beginTransaction();
                $stmt_upd = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?"); $updateSuccess = $stmt_upd->execute([$hashedPassword, $targetUserId]);
                $stmt_del_tokens = $pdo->prepare("DELETE FROM persistent_logins WHERE user_id = ?"); $stmt_del_tokens->execute([$targetUserId]);
                $pdo->commit();
                if ($updateSuccess) {
                    $message = "Contraseña de ".htmlspecialchars($targetUsername)." actualizada."; $message_type = 'success';
                     if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Cambiar Contraseña', "Usuario ID: {$targetUserId} ({$targetUsername})");
                } else { $message = "Error al actualizar contraseña."; $message_type = 'danger'; $pdo->rollBack(); }
            } else { $message = "Usuario no encontrado o no permitido."; $message_type = 'warning'; }
        } catch (PDOException $e){ $pdo->rollBack(); error_log("Admin Change Pass Error: ".$e->getMessage()); $message="Error DB: " . $e->getMessage(); $message_type = 'danger'; }
    } else {$message="La nueva contraseña debe tener al menos 6 caracteres."; $message_type = 'danger';}
}


// --- ¡NUEVAS ACCIONES DE PAGO! ---

if (isset($_GET['action']) && $_GET['action'] == 'approve_payment' && isset($_GET['proof_id']) && $section == 'payments') {
    $proofId = intval($_GET['proof_id']);
    
    try {
        $pdo->beginTransaction();
        
        // 1. Obtener los datos del comprobante y del usuario
        $stmt_proof = $pdo->prepare("SELECT user_id, amount_bs, reference_number FROM payment_proofs WHERE id = ? AND status = 'PENDIENTE'");
        $stmt_proof->execute([$proofId]);
        $proof = $stmt_proof->fetch();
        
        if ($proof) {
            $targetUserId = $proof['user_id'];
            
            // 2. Marcar el comprobante como COMPLETADO
            $stmt_update_proof = $pdo->prepare("UPDATE payment_proofs SET status = 'COMPLETADO', processed_by_admin_id = ? WHERE id = ?");
            $stmt_update_proof->execute([$user['id'], $proofId]);
            
            // 3. Actualizar la membresía del usuario
            // Le daremos 30 días de PRO (o extenderemos 30 días si ya es PRO)
            $stmt_update_user = $pdo->prepare(
                "UPDATE usuarios SET 
                 membership_type = 'PRO', 
                 jumper_limit = 999999,
                 membership_expires = CASE 
                    WHEN membership_type = 'PRO' AND membership_expires > NOW() 
                    THEN DATE_ADD(membership_expires, INTERVAL 30 DAY) 
                    ELSE DATE_ADD(NOW(), INTERVAL 30 DAY) 
                 END
                 WHERE id = ?"
            );
            $stmt_update_user->execute([$targetUserId]);
            
            $pdo->commit();
            $message = "Pago #{$proofId} aprobado. El usuario ID {$targetUserId} ahora tiene 30 días de membresía PRO.";
            $message_type = 'success';
            if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Aprobar Pago', "Proof ID: {$proofId}, User ID: {$targetUserId}");

        } else {
            $pdo->rollBack();
            $message = "Error: El comprobante de pago no se encontró o ya fue procesado.";
            $message_type = 'danger';
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Admin Approve Payment Error: " . $e->getMessage()); $message = "Error DB: " . $e->getMessage(); $message_type = 'danger';
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'reject_payment' && isset($_GET['proof_id']) && $section == 'payments') {
    $proofId = intval($_GET['proof_id']);
    
    try {
        // Simplemente marcar como RECHAZADO. No hacemos rollback de membresía.
        $stmt_update_proof = $pdo->prepare("UPDATE payment_proofs SET status = 'RECHAZADO', processed_by_admin_id = ? WHERE id = ? AND status = 'PENDIENTE'");
        
        if ($stmt_update_proof->execute([$user['id'], $proofId])) {
            if ($stmt_update_proof->rowCount() > 0) {
                $message = "Pago #{$proofId} rechazado exitosamente.";
                $message_type = 'success';
                if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Rechazar Pago', "Proof ID: {$proofId}");
            } else {
                $message = "El pago no se encontró o ya fue procesado.";
                $message_type = 'warning';
            }
        } else {
            $message = "Error al actualizar el pago."; $message_type = 'danger';
        }
        
    } catch (PDOException $e) {
        error_log("Admin Reject Payment Error: " . $e->getMessage()); $message = "Error DB: " . $e->getMessage(); $message_type = 'danger';
    }
}


// --- OTRAS ACCIONES (SubID, Rating, Shortener) ---

// Mapeo SubID
if (isset($_POST['add_map']) && $section == 'subid_maps') {
    $projektnummer = trim($_POST['projektnummer']); $newSubid = trim($_POST['new_subid']); 
    $userId = $user['id']; // ID del admin logueado
    $isProjektnummerValid = ctype_digit($projektnummer) && (strlen($projektnummer) == 5 || strlen($projektnummer) == 6);
    $isSubidValid = !empty($newSubid) && strlen($newSubid) <= 50;
    if (!$isProjektnummerValid || !$isSubidValid) { $message = "Datos inválidos. Projektnummer debe ser 5 o 6 dígitos y SubID no puede estar vacío (max 50)."; $message_type = 'danger'; }
    else {
        // La función 'addProjektnummerSubidMap' ya fue actualizada para recibir el $userId
        if (addProjektnummerSubidMap($pdo, $projektnummer, $newSubid, $userId)) {
            $message = "Mapeo añadido con éxito."; $message_type = 'success';
            if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Añadir Mapeo Manual', "P:{$projektnummer}, S:{$newSubid}");
        } else {
            try {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM projektnummer_subid_map WHERE projektnummer = ? AND subid = ?");
                $stmt_check->execute([$projektnummer, $newSubid]);
                if ($stmt_check->fetchColumn() > 0) {
                    $message = "Error: Este mapeo (Projektnummer + SubID) ya existe."; $message_type = 'warning';
                } else { $message = "Error: No se pudo añadir el mapeo (Error de DB)."; $message_type = 'danger'; }
            } catch (PDOException $e) { $message = "Error de DB al verificar duplicado: " . $e->getMessage(); $message_type = 'danger'; }
        }
    }
}
if (isset($_GET['action']) && $_GET['action'] == 'delete_map' && isset($_GET['map_id']) && $section == 'subid_maps') {
    $mapId = intval($_GET['map_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM projektnummer_subid_map WHERE id = ?");
        if ($stmt->execute([$mapId])) {
            $message = "Mapeo SubID eliminado."; $message_type = 'success';
            if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Eliminar Mapeo SubID', "Map ID: {$mapId}");
        } else { $message = "Error al eliminar mapeo."; $message_type = 'danger'; }
    } catch (PDOException $e) { error_log("Admin Delete Map Error: ".$e->getMessage()); $message="Error DB: " . $e->getMessage(); $message_type = 'danger'; }
}
if (isset($_POST['edit_map']) && $section == 'subid_maps') {
    $mapId = intval($_POST['map_id']);
    $newSubid = trim($_POST['new_subid']);
    $isSubidValid = !empty($newSubid) && strlen($newSubid) <= 50;
    if (!$isSubidValid) { $message = "El nuevo SubID no es válido (debe tener entre 1 y 50 caracteres)."; $message_type = 'danger'; }
    else {
        try {
            $stmt = $pdo->prepare("UPDATE projektnummer_subid_map SET subid = ? WHERE id = ?");
            if ($stmt->execute([$newSubid, $mapId])) {
                $message = "Mapeo SubID actualizado con éxito."; $message_type = 'success';
                if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Editar Mapeo SubID', "Map ID: {$mapId}, New SubID: {$newSubid}");
            } else { $message = "Error al actualizar el mapeo."; $message_type = 'danger'; }
        } catch (PDOException $e) { error_log("Admin Edit Map Error: ".$e->getMessage()); $message="Error DB al actualizar: " . $e->getMessage(); $message_type = 'danger'; }
    }
}
// Ratings
if (isset($_GET['action']) && $_GET['action'] == 'delete_rating' && isset($_GET['rating_id']) && $section == 'ratings') {
    $ratingId = intval($_GET['rating_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM subid_ratings WHERE id = ?");
        if ($stmt->execute([$ratingId])) {
            $message = "Calificación eliminada."; $message_type = 'success';
            if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Eliminar Calificación', "Rating DB ID: {$ratingId}");
        } else { $message = "Error al eliminar calificación."; $message_type = 'danger'; }
    } catch (PDOException $e) { error_log("Admin Delete Rating Error: ".$e->getMessage()); $message="Error DB: " . $e->getMessage(); $message_type = 'danger'; }
}
// Acortador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_link']) && $section == 'shortener') {
    $slug = trim($_POST['slug']);
    $target_url = trim($_POST['target_url']);
    if (empty($slug) || empty($target_url)) { $message = "El 'Slug' y la 'URL de Destino' son obligatorios."; $message_type = 'danger';
    } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) { $message = "El 'Slug' solo puede contener letras, números, guiones (-) y guiones bajos (_)."; $message_type = 'danger';
    } elseif (!filter_var($target_url, FILTER_VALIDATE_URL)) { $message = "La 'URL de Destino' no es una URL válida."; $message_type = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO short_links (slug, target_url) VALUES (?, ?)");
            $stmt->execute([$slug, $target_url]);
            $message = "Enlace acortado creado con éxito: /go/{$slug}"; $message_type = 'success';
            if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Crear Enlace Corto', "Slug: {$slug}");
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { $message = "Error: El 'Slug' (atajo) '{$slug}' ya está en uso."; $message_type = 'warning';
            } else { $message = "Error de DB al crear el enlace: " . $e->getMessage(); $message_type = 'danger'; }
            error_log("Admin Add Link Error: " . $e->getMessage());
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_link']) && $section == 'shortener') {
    $linkId = intval($_POST['link_id']);
    $slug = trim($_POST['slug']);
    $target_url = trim($_POST['target_url']);
    if (empty($slug) || empty($target_url) || empty($linkId)) { $message = "ID, 'Slug' y 'URL de Destino' son obligatorios."; $message_type = 'danger';
    } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) { $message = "El 'Slug' solo puede contener letras, números, guiones (-) y guiones bajos (_)."; $message_type = 'danger';
    } elseif (!filter_var($target_url, FILTER_VALIDATE_URL)) { $message = "La 'URL de Destino' no es una URL válida."; $message_type = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE short_links SET slug = ?, target_url = ? WHERE id = ?");
            $stmt->execute([$slug, $target_url, $linkId]);
            $message = "Enlace actualizado con éxito."; $message_type = 'success';
            if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Editar Enlace Corto', "ID: {$linkId}, Slug: {$slug}");
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { $message = "Error: El 'Slug' (atajo) '{$slug}' ya está en uso."; $message_type = 'warning';
            } else { $message = "Error de DB al actualizar el enlace: " . $e->getMessage(); $message_type = 'danger'; }
            error_log("Admin Edit Link Error: " . $e->getMessage());
        }
    }
}
if (isset($_GET['action']) && $_GET['action'] == 'delete_link' && isset($_GET['link_id']) && $section == 'shortener') {
    $linkId = intval($_GET['link_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM short_links WHERE id = ?");
        if ($stmt->execute([$linkId])) {
            $message = "Enlace acortado eliminado."; $message_type = 'success';
            if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Admin: Eliminar Enlace Corto', "ID: {$linkId}");
        } else { $message = "Error al eliminar el enlace."; $message_type = 'danger'; }
    } catch (PDOException $e) { error_log("Admin Delete Link Error: ".$e->getMessage()); $message="Error DB: " . $e->getMessage(); $message_type = 'danger'; }
}


// --- LÓGICA DE VISUALIZACIÓN POR SECCIÓN ---
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$dashboardData = []; $tableData = []; $totalItems = 0; $totalPages = 0;
$debugOutput = '';

try {
    if ($section == 'dashboard') {
        $dashboardData['maintenance_mode'] = file_exists('MAINTENANCE');
        // El resto se carga por JS (api_admin_stats.php)
        
    } elseif ($section == 'users') {
        $params = [];
        // ¡ACTUALIZADO! Selecciona todas las nuevas columnas de membresía
        $count_sql = "SELECT COUNT(*) FROM usuarios"; 
        $list_sql = "SELECT id, username, active, online, last_login, last_ip, 
                            membership_type, membership_expires, jumper_count, jumper_limit 
                     FROM usuarios";
        
        if ($search) { $count_sql .= " WHERE username LIKE ?"; $list_sql .= " WHERE username LIKE ?"; $params[] = "%$search%"; }
        $list_sql .= " ORDER BY username LIMIT ? OFFSET ?";
        $params[] = $perPage; $params[] = $offset;
        
        $stmt_count_params = $params; array_pop($stmt_count_params); array_pop($stmt_count_params);
        $stmt_count = $pdo->prepare($count_sql); $stmt_count->execute($stmt_count_params); $totalItems = $stmt_count->fetchColumn();
        
        $stmt_list = $pdo->prepare($list_sql);
        $i = 1; foreach ($params as $param) { $stmt_list->bindValue($i, $param, (is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR)); $i++; }
        $stmt_list->execute(); $tableData = $stmt_list->fetchAll(PDO::FETCH_ASSOC); $totalPages = ceil($totalItems / $perPage);

    } 
    
    // --- ¡NUEVA SECCIÓN DE PAGOS! ---
    elseif ($section == 'payments') {
        $params = [];
        $filter_status = $_GET['status'] ?? 'PENDIENTE'; // Por defecto, solo PENDIENTES
        
        // Unir con 'usuarios' para obtener el nombre de usuario
        $count_sql = "SELECT COUNT(p.id) FROM payment_proofs p JOIN usuarios u ON p.user_id = u.id";
        $list_sql = "SELECT p.*, u.username 
                     FROM payment_proofs p 
                     JOIN usuarios u ON p.user_id = u.id";
        
        $where_conditions = [];
        if ($filter_status !== 'TODOS') {
            $where_conditions[] = "p.status = ?";
            $params[] = $filter_status;
        }
        if ($search) { 
            $where_conditions[] = "(u.username LIKE ? OR p.reference_number LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (count($where_conditions) > 0) {
            $count_sql .= " WHERE " . implode(' AND ', $where_conditions);
            $list_sql .= " WHERE " . implode(' AND ', $where_conditions);
        }

        $list_sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage; $params[] = $offset;
        
        $stmt_count_params = $params; array_pop($stmt_count_params); array_pop($stmt_count_params);
        $stmt_count = $pdo->prepare($count_sql); $stmt_count->execute($stmt_count_params); $totalItems = $stmt_count->fetchColumn();
        
        $stmt_list = $pdo->prepare($list_sql);
        $i = 1; foreach ($params as $param) { $stmt_list->bindValue($i, $param, (is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR)); $i++; }
        $stmt_list->execute(); $tableData = $stmt_list->fetchAll(PDO::FETCH_ASSOC); $totalPages = ceil($totalItems / $perPage);

    }
    // --- FIN SECCIÓN PAGOS ---
    
    elseif ($section == 'logs') {
        $params = [];
        $count_sql = "SELECT COUNT(*) FROM activity_log"; $list_sql = "SELECT * FROM activity_log";
        if ($search) { $search_param = "%$search%"; $count_sql .= " WHERE username LIKE ? OR action LIKE ? OR ip_address LIKE ?"; $list_sql .= " WHERE username LIKE ? OR action LIKE ? OR ip_address LIKE ?"; $params[] = $search_param; $params[] = $search_param; $params[] = $search_param; }
        $list_sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        $params[] = $perPage; $params[] = $offset;
        $stmt_count_params = $params; array_pop($stmt_count_params); array_pop($stmt_count_params);
        $stmt_count = $pdo->prepare($count_sql); $stmt_count->execute($stmt_count_params); $totalItems = $stmt_count->fetchColumn();
        $stmt_list = $pdo->prepare($list_sql);
        $i = 1; foreach ($params as $param) { $stmt_list->bindValue($i, $param, (is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR)); $i++; }
        $stmt_list->execute(); $tableData = $stmt_list->fetchAll(PDO::FETCH_ASSOC); $totalPages = ceil($totalItems / $perPage);

    } elseif ($section == 'subid_maps') {
        $params = [];
        // ¡ACTUALIZADO! Unir con 'usuarios' para obtener el nombre
        $count_sql = "SELECT COUNT(m.id) FROM projektnummer_subid_map m LEFT JOIN usuarios u ON m.added_by_user_id = u.id";
        $list_sql = "SELECT m.*, u.username as added_by_username 
                     FROM projektnummer_subid_map m 
                     LEFT JOIN usuarios u ON m.added_by_user_id = u.id";
        if ($search) { 
            $search_param = "%$search%"; 
            $count_sql .= " WHERE m.projektnummer LIKE ? OR m.subid LIKE ? OR u.username LIKE ?"; 
            $list_sql .= " WHERE m.projektnummer LIKE ? OR m.subid LIKE ? OR u.username LIKE ?"; 
            $params[] = $search_param; $params[] = $search_param; $params[] = $search_param; 
        }
        $list_sql .= " ORDER BY m.id DESC LIMIT ? OFFSET ?";
        $params[] = $perPage; $params[] = $offset;
        $stmt_count_params = $params; array_pop($stmt_count_params); array_pop($stmt_count_params);
        $stmt_count = $pdo->prepare($count_sql); $stmt_count->execute($stmt_count_params); $totalItems = $stmt_count->fetchColumn();
        $stmt_list = $pdo->prepare($list_sql);
        $i = 1; foreach ($params as $param) { $stmt_list->bindValue($i, $param, (is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR)); $i++; }
        $stmt_list->execute(); $tableData = $stmt_list->fetchAll(PDO::FETCH_ASSOC); $totalPages = ceil($totalItems / $perPage);

    } elseif ($section == 'ratings') {
        $params = [];
        $count_sql = "SELECT COUNT(r.id) FROM subid_ratings r LEFT JOIN usuarios u ON r.user_id = u.id";
        $list_sql = "SELECT r.*, u.username FROM subid_ratings r LEFT JOIN usuarios u ON r.user_id = u.id";
         if ($search) { $search_param = "%$search%"; $count_sql .= " WHERE r.subid LIKE ? OR r.comment LIKE ? OR u.username LIKE ?"; $list_sql .= " WHERE r.subid LIKE ? OR r.comment LIKE ? OR u.username LIKE ?"; $params[] = $search_param; $params[] = $search_param; $params[] = $search_param; }
        $list_sql .= " ORDER BY r.id DESC LIMIT ? OFFSET ?";
        $params[] = $perPage; $params[] = $offset;
        $stmt_count_params = $params; array_pop($stmt_count_params); array_pop($stmt_count_params);
        $stmt_count = $pdo->prepare($count_sql); $stmt_count->execute($stmt_count_params); $totalItems = $stmt_count->fetchColumn();
        $stmt_list = $pdo->prepare($list_sql);
         $i = 1; foreach ($params as $param) { $stmt_list->bindValue($i, $param, (is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR)); $i++; }
        $stmt_list->execute(); $tableData = $stmt_list->fetchAll(PDO::FETCH_ASSOC); $totalPages = ceil($totalItems / $perPage);
    
    } elseif ($section == 'shortener') {
        $params = [];
        $count_sql = "SELECT COUNT(*) FROM short_links";
        $list_sql = "SELECT * FROM short_links";
        if ($search) { $search_param = "%$search%"; $count_sql .= " WHERE slug LIKE ? OR target_url LIKE ?"; $list_sql .= " WHERE slug LIKE ? OR target_url LIKE ?"; $params[] = $search_param; $params[] = $search_param; }
        $list_sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        $params[] = $perPage; $params[] = $offset;
        $stmt_count_params = $params; array_pop($stmt_count_params); array_pop($stmt_count_params);
        $stmt_count = $pdo->prepare($count_sql); $stmt_count->execute($stmt_count_params); $totalItems = $stmt_count->fetchColumn();
        $stmt_list = $pdo->prepare($list_sql);
         $i = 1; foreach ($params as $param) { $stmt_list->bindValue($i, $param, (is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR)); $i++; }
        $stmt_list->execute(); $tableData = $stmt_list->fetchAll(PDO::FETCH_ASSOC); $totalPages = ceil($totalItems / $perPage);
    }
} catch (PDOException $e) {
    $errorMessage = $e->getMessage(); error_log("Admin Section ({$section}) Query Error: " . $errorMessage);
    $message .= " Error al cargar datos para la sección '" . htmlspecialchars($section, ENT_QUOTES, 'UTF-8') . "': " . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
    $message_type = 'danger';
    $dashboardData = []; $tableData = []; $totalItems = 0; $totalPages = 0;
}

// Helper function paginationLinks
function paginationLinks(int $currentPage, int $totalPages, string $baseUrl): string {
    if ($totalPages <= 1) return '';
    $links = '<ul class="pagination justify-content-center">';
    $maxPagesToShow = 5; $startPage = max(1, $currentPage - floor($maxPagesToShow / 2)); $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
    if ($endPage - $startPage + 1 < $maxPagesToShow) { $startPage = max(1, $endPage - $maxPagesToShow + 1); }
    
    // URL amigable para Paginación
    $searchQuery = empty($_GET['search']) ? '' : '&search=' . urlencode($_GET['search']);
    $statusQuery = empty($_GET['status']) ? '' : '&status=' . urlencode($_GET['status']);
    $pageBaseUrl = "admin.php?section={$_GET['section']}{$searchQuery}{$statusQuery}";

    $disabled = ($currentPage == 1) ? ' disabled' : '';
    $links .= "<li class='page-item{$disabled}'><a class='page-link' href='{$pageBaseUrl}&page=1'>&laquo;</a></li>";
    $prevPage = $currentPage - 1;
    $links .= "<li class='page-item{$disabled}'><a class='page-link' href='{$pageBaseUrl}&page={$prevPage}'>&lsaquo;</a></li>";
    for ($i = $startPage; $i <= $endPage; $i++) { $active = ($i == $currentPage) ? ' active' : ''; $links .= "<li class='page-item{$active}'><a class='page-link' href='{$pageBaseUrl}&page={$i}'>{$i}</a></li>"; }
    $disabled = ($currentPage == $totalPages) ? ' disabled' : ''; $nextPage = $currentPage + 1;
    $links .= "<li class='page-item{$disabled}'><a class='page-link' href='{$pageBaseUrl}&page={$nextPage}'>&rsaquo;</a></li>";
    $links .= "<li class='page-item{$disabled}'><a class='page-link' href='{$pageBaseUrl}&page={$totalPages}'>&raquo;</a></li>";
    $links .= '</ul>'; return $links;
}
$paginationBaseUrl = "admin.php?section={$section}" . ($search ? '&search=' . urlencode($search) : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Admin - SurveyJunior</title>
    
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='3' y='3' width='18' height='18' rx='4' fill='%230A0E1A' opacity='0.1'/%3E%3Cpath d='M16 8C16 6.89543 15.1046 6 14 6H10C8.89543 6 8 6.89543 8 8C8 9.10457 8.89543 10 10 10H14' stroke='%2330E8BF' stroke-width='2.5' stroke-linecap='round'/%3E%3Cpath d='M8 16C8 17.1046 8.89543 18 10 18H14C15.1046 18 16 17.1046 16 16C16 14.8954 15.1046 14 14 14H10' stroke='%2330E8BF' stroke-width='2.5' stroke-linecap='round'/%3E%3C/svg%3E">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="new-style.css">
</head>
<body data-theme="dark"> <div class="app-shell" id="app-shell">
        
        <nav class="app-sidebar">
            <a href="admin.php?section=dashboard" class="nav-link" style="margin-bottom: 2rem;" title="SurveyJunior">
                <svg class="app-logo" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="18" height="18" rx="4" opacity="0.1"/>
                    <path d="M16 8C16 6.89543 15.1046 6 14 6H10C8.89543 6 8 6.89543 8 8C8 9.10457 8.89543 10 10 10H14"/>
                    <path d="M8 16C8 17.1046 8.89543 18 10 18H14C15.1046 18 16 17.1046 16 16C16 14.8954 15.1046 14 14 14H10"/>
                </svg>
            </a>
            
            <ul class="nav-list">
                <li>
                    <a href="admin.php?section=dashboard" class="nav-link <?= $section === 'dashboard' ? 'active' : '' ?>" title="Dashboard">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span class="nav-link-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="admin.php?section=payments" class="nav-link <?= $section === 'payments' ? 'active' : '' ?>" title="Pagos">
                        <i class="bi bi-wallet-fill"></i>
                        <span class="nav-link-text">Pagos</span>
                        <?php
                            // (Opcional) Contar pendientes
                            try {
                                $stmt_pending = $pdo->query("SELECT COUNT(*) FROM payment_proofs WHERE status = 'PENDIENTE'");
                                $pending_count = $stmt_pending->fetchColumn();
                                if ($pending_count > 0) {
                                    echo ' <span class="badge bg-danger ms-auto" style="border-radius: 50px; margin-right: 1.5rem; min-width: 20px;">'.$pending_count.'</span>';
                                }
                            } catch (PDOException $e) {}
                        ?>
                    </a>
                </li>
                <li>
                    <a href="admin.php?section=users" class="nav-link <?= $section === 'users' ? 'active' : '' ?>" title="Usuarios">
                        <i class="bi bi-people-fill"></i>
                        <span class="nav-link-text">Usuarios</span>
                    </a>
                </li>
                <li>
                    <a href="admin.php?section=subid_maps" class="nav-link <?= $section === 'subid_maps' ? 'active' : '' ?>" title="Mapeos SubID">
                        <i class="bi bi-link-45deg"></i>
                        <span class="nav-link-text">Mapeos</span>
                    </a>
                </li>
                 <li>
                    <a href="admin.php?section=shortener" class="nav-link <?= $section === 'shortener' ? 'active' : '' ?>" title="Acortador">
                        <i class="bi bi-scissors"></i>
                        <span class="nav-link-text">Acortador</span>
                    </a>
                </li>
                <li>
                    <a href="admin.php?section=ratings" class="nav-link <?= $section === 'ratings' ? 'active' : '' ?>" title="Ratings">
                        <i class="bi bi-star-fill"></i>
                        <span class="nav-link-text">Ratings</span>
                    </a>
                </li>
                <li>
                    <a href="admin.php?section=logs" class="nav-link <?= $section === 'logs' ? 'active' : '' ?>" title="Logs">
                        <i class="bi bi-clipboard-data-fill"></i>
                        <span class="nav-link-text">Logs</span>
                    </a>
                </li>
            </ul>
            
            <ul class="nav-list sidebar-footer">
    <li>
        <!-- CAMBIOS:
             1. href="dashboard.php" (en vez de index.php)
             2. title="Volver a Modulos"
             3. Se eliminó target="_blank"
             4. Icono cambiado a "bi-arrow-left-circle-fill"
             5. Texto cambiado a "Volver a Modulos"
        -->
        <a href="dashboard.php" class="nav-link" title="Volver a Modulos">
            <i class="bi bi-arrow-left-circle-fill"></i>
            <span class="nav-link-text">Volver a Modulos</span>
        </a>
    </li>
    <li>
                    <a href="logout.php" class="nav-link" title="Cerrar Sesión">
                        <i class="bi bi-box-arrow-left"></i>
                        <span class="nav-link-text">Salir</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <header class="app-header">
            <div class="header-title" id="page-title-mobile">
                <?php
                // Título dinámico
                switch($section) {
                    case 'users': echo 'Usuarios'; break;
                    case 'payments': echo 'Gestión de Pagos'; break;
                    case 'subid_maps': echo 'Mapeos SubID'; break;
                    case 'shortener': echo 'Acortador de Enlaces'; break;
                    case 'ratings': echo 'Calificaciones'; break;
                    case 'logs': echo 'Registro de Actividad'; break;
                    default: echo 'Dashboard';
                }
                ?>
            </div>
            
            <button class="theme-toggle" id="theme-toggle-btn" title="Cambiar tema">
                <i class="bi bi-sun-fill"></i> </button>
            <img class="user-avatar" src="https://api.dicebear.com/8.x/adventurer/svg?seed=<?= urlencode($user['username']) ?>" alt="Avatar de Usuario">
        </header>

                <!-- ÁREA DE CONTENIDO (Módulo "Home") -->
        <main class="module-content-area">
            
            <!-- Mensaje de Alerta (si existe) -->
            <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type === 'success' ? 'alert-success' : ($message_type === 'warning' ? 'alert-warning' : 'alert-danger') ?>" role="alert">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- ============================================= -->
            <!-- SECCIÓN: DASHBOARD (default)                  -->
            <!-- ============================================= -->
            <?php if ($section == 'dashboard'): ?>
                <div class="bento-grid">
                    <!-- Estadísticas -->
                    <div class="bento-box" style="grid-column: span 12; grid-row: span 1; display: flex; justify-content: space-around; align-items: center; text-align: center;">
                        <div>
                            <div class="bento-box-header small">Usuarios Totales</div>
                            <div class="stat-value" id="stat-total-users">...</div>
                        </div>
                        <div>
                            <div class="bento-box-header small">Usuarios en Línea</div>
                            <div class="stat-value" id="stat-online-users" style="color: var(--brand-blue);">...</div>
                        </div>
                        <div>
                            <div class="bento-box-header small">Jumpers (Últ. 7 días)</div>
                            <div class="stat-value" id="stat-total-jumpers" style="color: var(--brand-green);">...</div>
                        </div>
                        <div>
                            <div class="bento-box-header small">Logins (Últ. 7 días)</div>
                            <div class="stat-value" id="stat-total-logins" style="color: var(--brand-yellow);">...</div>
                        </div>
                    </div>
                    
                    <!-- Gráfico -->
                    <div class="bento-box" style="grid-column: span 12; grid-row: span 2;">
                        <canvas id="admin-chart"></canvas>
                    </div>

                    <!-- Acciones Rápidas -->
                    <div class="bento-box" style="grid-column: span 12; grid-row: span 1;">
                         <div class="bento-box-header small">Acciones del Sitio</div>
                         <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            
                            <!-- Mantenimiento -->
                            <form id="maintenance-form" method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="toggle_maintenance">
                                <input type="hidden" id="maintenance_value" name="value" value="<?= $dashboardData['maintenance_mode'] ? 'off' : 'on' ?>">
                                <?php if ($dashboardData['maintenance_mode']): ?>
                                    <button type="submit" class="btn btn-success"><i class="bi bi-play-fill"></i> Desactivar Mantenimiento</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-warning"><i class="bi bi-pause-fill"></i> Activar Mantenimiento</button>
                                <?php endif; ?>
                            </form>

                            <!-- Purgar Caché CF -->
                            <form id="purge-cache-form" method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="purge_cache">
                                <button type="submit" class="btn btn-secondary" onclick="return confirm('¿Seguro que quieres purgar el caché de Cloudflare?')"><i class="bi bi-cloud-slash-fill"></i> Purgar Caché CF</button>
                            </form>

                            <!-- Limpiar Logs -->
                            <form id="clear-logs-form" method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="clear_logs">
                                <button type="submit" class="btn btn-secondary" onclick="return confirm('¿Seguro que quieres eliminar logs de más de 30 días?')"><i class="bi bi-trash3-fill"></i> Limpiar Logs Antiguos</button>
                            </form>
                            
                            <!-- Forzar Logout -->
                            <form id="force-logout-form" method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="force_logout">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('¡PELIGRO! ¿Estás seguro que quieres cerrar la sesión de TODOS los usuarios?')"><i class="bi bi-power"></i> Forzar Cierre de Sesión</button>
                            </form>
                         </div>
                    </div>
                </div>
            <?php endif; ?>
            <!-- ============================================= -->
            <!-- FIN SECCIÓN: DASHBOARD                        -->
            <!-- ============================================= -->


            
            <!-- ============================================= -->
            <!-- SECCIÓN: USUARIOS                             -->
            <!-- ============================================= -->
            <?php if ($section == 'users'): ?>
                <div class="bento-box">
                    <div class="bento-box-header small" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Mostrando <?= $totalItems ?> usuarios</span>
                        <!-- Formulario de Búsqueda -->
                        <form method="GET" style="display: flex; gap: 0.5rem;">
                            <input type="hidden" name="section" value="users">
                            <input type="text" name="search" class="form-control-dark" placeholder="Buscar usuario..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                        </form>
                    </div>

                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Membresía</th>
                                    <th>Estado</th>
                                    <th>Jumpers (Usados/Límite)</th>
                                    <th>Expira</th>
                                    <th>Último Login</th>
                                    <th>Última IP</th>
                                    <th style="width: 150px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData as $row): ?>
                                    <tr>
                                        <td>
                                            <strong style="color: var(--text-light);"><?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            (ID: <?= $row['id'] ?>)
                                        </td>
                                        <td>
                                            <?php
                                            $membership = htmlspecialchars($row['membership_type'], ENT_QUOTES, 'UTF-8');
                                            $class = '';
                                            if ($membership == 'ADMINISTRADOR') $class = 'status-admin';
                                            elseif ($membership == 'PRO') $class = 'status-pro';
                                            elseif ($membership == 'PRUEBA GRATIS') $class = 'status-prueba';
                                            elseif ($membership == 'VENCIDO') $class = 'status-vencido';
                                            echo "<span class='status-badge {$class}'>{$membership}</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($row['active']): ?>
                                                <span class="status-activo">Activo</span> <?= $row['online'] ? '(En Línea)' : '' ?>
                                            <?php else: ?>
                                                <span class="status-inactivo">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $row['jumper_count'] ?> / 
                                            <?= ($row['jumper_limit'] >= 999999) ? '∞' : $row['jumper_limit'] ?>
                                        </td>
                                        <td><?= $row['membership_expires'] ? (new DateTime($row['membership_expires']))->format('Y-m-d') : 'N/A' ?></td>
                                        <td><?= $row['last_login'] ? (new DateTime($row['last_login']))->format('Y-m-d H:i') : 'Nunca' ?></td>
                                        <td><?= htmlspecialchars($row['last_ip'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editUserModal-<?= $row['id'] ?>">Editar</button>
                                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#changePasswordModal-<?= $row['id'] ?>">Clave</button>
                                            <a href="admin.php?section=users&action=delete_user&user_id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Seguro que quieres eliminar a este usuario? Esta acción es irreversible.')">
                                                <i class="bi bi-trash3-fill"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tableData)): ?>
                                    <tr><td colspan="8" style="text-align: center;">No se encontraron usuarios.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?= paginationLinks($page, $totalPages, $paginationBaseUrl) ?>

                    <!-- Botón para añadir usuario -->
                    <button class="btn btn-success" style="margin-top: 1rem;" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus-fill"></i> Añadir Nuevo Usuario</button>
                </div>
            <?php endif; ?>
            <!-- ============================================= -->
            <!-- FIN SECCIÓN: USUARIOS                         -->
            <!-- ============================================= -->



            <!-- ============================================= -->
            <!-- SECCIÓN: PAGOS                                -->
            <!-- ============================================= -->
            <?php if ($section == 'payments'): ?>
                <div class="bento-box">
                    <div class="bento-box-header small" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Mostrando <?= $totalItems ?> comprobantes</span>
                        
                        <!-- Filtros -->
                        <div style="display: flex; gap: 1rem;">
                            <form method="GET" style="display: flex; gap: 0.5rem;">
                                <input type="hidden" name="section" value="payments">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                                <select name="status" class="form-control-dark" onchange="this.form.submit()">
                                    <option value="PENDIENTE" <?= ($_GET['status'] ?? 'PENDIENTE') == 'PENDIENTE' ? 'selected' : '' ?>>Pendientes</option>
                                    <option value="COMPLETADO" <?= ($_GET['status'] ?? '') == 'COMPLETADO' ? 'selected' : '' ?>>Completados</option>
                                    <option value="RECHAZADO" <?= ($_GET['status'] ?? '') == 'RECHAZADO' ? 'selected' : '' ?>>Rechazados</option>
                                    <option value="TODOS" <?= ($_GET['status'] ?? '') == 'TODOS' ? 'selected' : '' ?>>Todos</option>
                                </select>
                            </form>
                            <!-- Formulario de Búsqueda -->
                            <form method="GET" style="display: flex; gap: 0.5rem;">
                                <input type="hidden" name="section" value="payments">
                                <input type="hidden" name="status" value="<?= htmlspecialchars($_GET['status'] ?? 'PENDIENTE', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="text" name="search" class="form-control-dark" placeholder="Buscar usuario o ref..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                            </form>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Fecha</th>
                                    <th>Monto (Bs.)</th>
                                    <th>Referencia</th>
                                    <th>Comprobante</th>
                                    <th>Estado</th>
                                    <th style="width: 200px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData as $row): ?>
                                    <tr>
                                        <td><?= $row['id'] ?></td>
                                        <td>
                                            <strong style="color: var(--text-light);"><?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            (ID: <?= $row['user_id'] ?>)
                                        </td>
                                        <td><?= (new DateTime($row['created_at']))->format('Y-m-d H:i') ?></td>
                                        <td><?= htmlspecialchars($row['amount_bs'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['reference_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <!-- Corregido: file_path en lugar de proof_image_path -->
                                            <?php if ($row['file_path']): ?>
                                                <a href="<?= htmlspecialchars($row['file_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-sm btn-secondary">Ver Imagen</a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8');
                                            $class = '';
                                            if ($status == 'PENDIENTE') $class = 'status-pendiente';
                                            elseif ($status == 'COMPLETADO') $class = 'status-completado';
                                            elseif ($status == 'RECHAZADO') $class = 'status-rechazado';
                                            echo "<span class='status-badge {$class}'>{$status}</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] == 'PENDIENTE'): ?>
                                                <a href="admin.php?section=payments&action=approve_payment&proof_id=<?= $row['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('¿Seguro que quieres APROBAR este pago? Se añadirán 30 días de membresía al usuario.')">
                                                    <i class="bi bi-check-lg"></i> Aprobar
                                                </a>
                                                <a href="admin.php?section=payments&action=reject_payment&proof_id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Seguro que quieres RECHAZAR este pago?')">
                                                    <i class="bi bi-x-lg"></i> Rechazar
                                                </a>
                                            <?php else: ?>
                                                <span>Procesado</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tableData)): ?>
                                    <tr><td colspan="8" style="text-align: center;">No se encontraron comprobantes.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?= paginationLinks($page, $totalPages, $paginationBaseUrl) ?>
                </div>
            <?php endif; ?>
            <!-- ============================================= -->
            <!-- FIN SECCIÓN: PAGOS                            -->
            <!-- ============================================= -->


            
            <!-- ============================================= -->
            <!-- SECCIÓN: MAPEOS SUBID                         -->
            <!-- ============================================= -->
            <?php if ($section == 'subid_maps'): ?>
                 <div class="bento-box">
                    <div class="bento-box-header small" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Mostrando <?= $totalItems ?> mapeos</span>
                        <!-- Formulario de Búsqueda -->
                        <form method="GET" style="display: flex; gap: 0.5rem;">
                            <input type="hidden" name="section" value="subid_maps">
                            <input type="text" name="search" class="form-control-dark" placeholder="Buscar Projekt, SubID o Usuario..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                        </form>
                    </div>

                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Projektnummer</th>
                                    <th>SubID</th>
                                    <th>Añadido por</th>
                                    <th>Fecha</th>
                                    <th style="width: 150px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData as $row): ?>
                                    <tr>
                                        <td><?= $row['id'] ?></td>
                                        <td><strong style="color: var(--text-light);"><?= htmlspecialchars($row['projektnummer'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td><?= htmlspecialchars($row['subid'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['added_by_username'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> (ID: <?= $row['added_by_user_id'] ?? '?' ?>)</td>
                                        <td><?= (new DateTime($row['created_at']))->format('Y-m-d H:i') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editMapModal-<?= $row['id'] ?>">Editar</button>
                                            <a href="admin.php?section=subid_maps&action=delete_map&map_id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Seguro que quieres eliminar este mapeo?')">
                                                <i class="bi bi-trash3-fill"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tableData)): ?>
                                    <tr><td colspan="6" style="text-align: center;">No se encontraron mapeos.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?= paginationLinks($page, $totalPages, $paginationBaseUrl) ?>

                    <!-- Botón para añadir mapeo -->
                    <button class="btn btn-success" style="margin-top: 1rem;" data-bs-toggle="modal" data-bs-target="#addMapModal"><i class="bi bi-plus-lg"></i> Añadir Mapeo Manual</button>
                </div>
            <?php endif; ?>
            <!-- ============================================= -->
            <!-- FIN SECCIÓN: MAPEOS SUBID                     -->
            <!-- ============================================= -->


            
            <!-- ============================================= -->
            <!-- SECCIÓN: RATINGS                            -->
            <!-- ============================================= -->
            <?php if ($section == 'ratings'): ?>
                 <div class="bento-box">
                    <div class="bento-box-header small" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Mostrando <?= $totalItems ?> calificaciones</span>
                        <!-- Formulario de Búsqueda -->
                        <form method="GET" style="display: flex; gap: 0.5rem;">
                            <input type="hidden" name="section" value="ratings">
                            <input type="text" name="search" class="form-control-dark" placeholder="Buscar SubID, Comentario o Usuario..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                        </form>
                    </div>

                    <div class="table-wrapper">
                        <table class="table" style="min-width: 1000px;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>SubID</th>
                                    <th>Usuario</th>
                                    <th>Rating</th>
                                    <th style="min-width: 300px;">Comentario</th>
                                    <th>Fecha</th>
                                    <th style="width: 100px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData as $row): ?>
                                    <tr>
                                        <td><?= $row['id'] ?></td>
                                        <td><strong style="color: var(--text-light);"><?= htmlspecialchars($row['subid'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td><?= htmlspecialchars($row['username'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> (ID: <?= $row['user_id'] ?>)</td>
                                        <td>
                                            <?php if ($row['rating'] == 1): ?>
                                                <span style="color: var(--brand-green); font-weight: 700;"><i class="bi bi-hand-thumbs-up-fill"></i> Positivo</span>
                                            <?php elseif ($row['rating'] == -1): ?>
                                                <span style="color: var(--brand-red); font-weight: 700;"><i class="bi bi-hand-thumbs-down-fill"></i> Negativo</span>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space: normal;"><?= htmlspecialchars($row['comment'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (new DateTime($row['created_at']))->format('Y-m-d H:i') ?></td>
                                        <td>
                                            <a href="admin.php?section=ratings&action=delete_rating&rating_id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Seguro que quieres eliminar esta calificación?')">
                                                <i class="bi bi-trash3-fill"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tableData)): ?>
                                    <tr><td colspan="7" style="text-align: center;">No se encontraron calificaciones.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= paginationLinks($page, $totalPages, $paginationBaseUrl) ?>
                </div>
            <?php endif; ?>
            <!-- ============================================= -->
            <!-- FIN SECCIÓN: RATINGS                          -->
            <!-- ============================================= -->


            
            <!-- ============================================= -->
            <!-- SECCIÓN: SHORTENER                          -->
            <!-- ============================================= -->
            <?php if ($section == 'shortener'): ?>
                 <div class="bento-box">
                    <div class="bento-box-header small" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Mostrando <?= $totalItems ?> enlaces</span>
                        <!-- Formulario de Búsqueda -->
                        <form method="GET" style="display: flex; gap: 0.5rem;">
                            <input type="hidden" name="section" value="shortener">
                            <input type="text" name="search" class="form-control-dark" placeholder="Buscar slug o URL..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                        </form>
                    </div>

                    <div class="table-wrapper">
                        <table class="table" style="min-width: 1000px;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Slug (Atajo)</th>
                                    <th style="min-width: 400px;">URL de Destino</th>
                                    <th>Fecha</th>
                                    <th style="width: 150px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData as $row): ?>
                                    <tr>
                                        <td><?= $row['id'] ?></td>
                                        <td><strong style="color: var(--text-light);">/go/<?= htmlspecialchars($row['slug'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td style="white-space: normal;"><?= htmlspecialchars($row['target_url'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (new DateTime($row['created_at']))->format('Y-m-d H:i') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editLinkModal-<?= $row['id'] ?>">Editar</button>
                                            <a href="admin.php?section=shortener&action=delete_link&link_id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Seguro que quieres eliminar este enlace?')">
                                                <i class="bi bi-trash3-fill"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tableData)): ?>
                                    <tr><td colspan="5" style="text-align: center;">No se encontraron enlaces.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?= paginationLinks($page, $totalPages, $paginationBaseUrl) ?>

                    <!-- Botón para añadir enlace -->
                    <button class="btn btn-success" style="margin-top: 1rem;" data-bs-toggle="modal" data-bs-target="#addLinkModal"><i class="bi bi-plus-lg"></i> Crear Nuevo Enlace</button>
                </div>
            <?php endif; ?>
            <!-- ============================================= -->
            <!-- FIN SECCIÓN: SHORTENER                        -->
            <!-- ============================================= -->


            
            <!-- ============================================= -->
            <!-- SECCIÓN: LOGS                                 -->
            <!-- ============================================= -->
            <?php if ($section == 'logs'): ?>
                 <div class="bento-box">
                    <div class="bento-box-header small" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Mostrando <?= $totalItems ?> logs</span>
                        <!-- Formulario de Búsqueda -->
                        <form method="GET" style="display: flex; gap: 0.5rem;">
                            <input type="hidden" name="section" value="logs">
                            <input type="text" name="search" class="form-control-dark" placeholder="Buscar usuario, acción o IP..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                        </form>
                    </div>

                    <div class="table-wrapper">
                        <table class="table" style="min-width: 1200px;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th style="min-width: 250px;">Detalles</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData as $row): ?>
                                    <tr>
                                        <td><?= $row['id'] ?></td>
                                        <td><?= (new DateTime($row['timestamp']))->format('Y-m-d H:i:s') ?></td>
                                        <td><?= htmlspecialchars($row['username'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> (ID: <?= $row['user_id'] ?? '?' ?>)</td>
                                        <td><strong style="color: var(--text-light);"><?= htmlspecialchars($row['action'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td style="white-space: normal;"><?= htmlspecialchars($row['details'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['ip_address'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tableData)): ?>
                                    <tr><td colspan="6" style="text-align: center;">No se encontraron logs.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?= paginationLinks($page, $totalPages, $paginationBaseUrl) ?>
                </div>
            <?php endif; ?>
            <!-- ============================================= -->
            <!-- FIN SECCIÓN: LOGS                             -->
            <!-- ============================================= -->

        </main>
    </div>
    
    <!-- =================================================================== -->
    <!-- MODALES (Formularios emergentes)                                    -->
    <!-- =================================================================== -->
    
    <!-- Modal: Añadir Usuario -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Añadir Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="admin.php?section=users">
                        <input type="hidden" name="add_user" value="1">
                        <div class="mb-3">
                            <label for="new_username" class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control-dark" id="new_username" name="new_username" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Contraseña (mín. 6 caracteres)</label>
                            <input type="password" class="form-control-dark" id="new_password" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_membership_type" class="form-label">Tipo de Membresía</label>
                            <select class="form-control-dark" id="add_membership_type" name="membership_type">
                                <option value="PRUEBA GRATIS">PRUEBA GRATIS (5 usos)</option>
                                <option value="PRO">PRO (Ilimitado)</option>
                                <option value="ADMINISTRADOR">ADMINISTRADOR (Total)</option>
                                <option value="VENCIDO">VENCIDO (Sin acceso)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success" style="width: 100%;">Crear Usuario</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modales de Edición de Usuario (generados dinámicamente) -->
    <?php if ($section == 'users' && !empty($tableData)): foreach ($tableData as $row): ?>
        <!-- Modal: Editar Usuario -->
        <div class="modal fade" id="editUserModal-<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editUserModalLabel-<?= $row['id'] ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel-<?= $row['id'] ?>">Editar Usuario: <?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="admin.php?section=users&page=<?= $page ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                            <input type="hidden" name="edit_user" value="1">
                            <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                            
                            <div class="mb-3">
                                <label for="edit_membership_type_<?= $row['id'] ?>" class="form-label">Tipo de Membresía</label>
                                <select class="form-control-dark" id="edit_membership_type_<?= $row['id'] ?>" name="membership_type">
                                    <option value="PRUEBA GRATIS" <?= $row['membership_type'] == 'PRUEBA GRATIS' ? 'selected' : '' ?>>PRUEBA GRATIS</option>
                                    <option value="PRO" <?= $row['membership_type'] == 'PRO' ? 'selected' : '' ?>>PRO</option>
                                    <option value="ADMINISTRADOR" <?= $row['membership_type'] == 'ADMINISTRADOR' ? 'selected' : '' ?>>ADMINISTRADOR</option>
                                    <option value="VENCIDO" <?= $row['membership_type'] == 'VENCIDO' ? 'selected' : '' ?>>VENCIDO</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="edit_membership_expires_<?= $row['id'] ?>" class="form-label">Fecha de Expiración (YYYY-MM-DD HH:MM:SS)</label>
                                <input type="text" class="form-control-dark" id="edit_membership_expires_<?= $row['id'] ?>" name="membership_expires" value="<?= htmlspecialchars($row['membership_expires'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Dejar vacío para N/A o auto-asignar (PRO)">
                                <div class="form-text">Si es PRO y se deja vacío, se auto-asignan 30 días.</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label for="edit_jumper_count_<?= $row['id'] ?>" class="form-label">Jumpers Usados</label>
                                        <input type="number" class="form-control-dark" id="edit_jumper_count_<?= $row['id'] ?>" name="jumper_count" value="<?= $row['jumper_count'] ?>">
                                    </div>
                                </div>
                                <div class="col-6">
                                     <div class="mb-3">
                                        <label for="edit_jumper_limit_<?= $row['id'] ?>" class="form-label">Límite Jumpers</label>
                                        <input type="number" class="form-control-dark" id="edit_jumper_limit_<?= $row['id'] ?>" name="jumper_limit" value="<?= $row['jumper_limit'] ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="active" value="1" id="edit_active_<?= $row['id'] ?>" <?= $row['active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="edit_active_<?= $row['id'] ?>">
                                    Usuario Activo (puede iniciar sesión)
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Guardar Cambios</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Cambiar Contraseña -->
        <div class="modal fade" id="changePasswordModal-<?= $row['id'] ?>" tabindex="-1" aria-labelledby="changePasswordModalLabel-<?= $row['id'] ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="changePasswordModalLabel-<?= $row['id'] ?>">Cambiar Contraseña: <?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="admin.php?section=users&page=<?= $page ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                            <input type="hidden" name="change_password" value="1">
                            <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                            <div class="mb-3">
                                <label for="new_password_<?= $row['id'] ?>" class="form-label">Nueva Contraseña (mín. 6 caracteres)</label>
                                <input type="password" class="form-control-dark" id="new_password_<?= $row['id'] ?>" name="new_password" required>
                            </div>
                            <button type="submit" class="btn btn-warning" style="width: 100%;">Establecer Nueva Contraseña</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
    
    <!-- Modales de Mapeo SubID (generados dinámicamente) -->
    <?php if ($section == 'subid_maps'): ?>
        <!-- Modal: Añadir Mapeo -->
        <div class="modal fade" id="addMapModal" tabindex="-1" aria-labelledby="addMapModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addMapModalLabel">Añadir Mapeo Manual</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="admin.php?section=subid_maps">
                            <input type="hidden" name="add_map" value="1">
                            <div class="mb-3">
                                <label for="add_projektnummer" class="form-label">Projektnummer (5 o 6 dígitos)</label>
                                <input type="text" class="form-control-dark" id="add_projektnummer" name="projektnummer" required pattern="\d{5,6}">
                            </div>
                            <div class="mb-3">
                                <label for="add_new_subid" class="form-label">SubID (max 50 caracteres)</label>
                                <input type="text" class="form-control-dark" id="add_new_subid" name="new_subid" required maxlength="50">
                            </div>
                            <button type="submit" class="btn btn-success" style="width: 100%;">Añadir Mapeo</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    
        <?php if (!empty($tableData)): foreach ($tableData as $row): ?>
        <!-- Modal: Editar Mapeo -->
        <div class="modal fade" id="editMapModal-<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editMapModalLabel-<?= $row['id'] ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editMapModalLabel-<?= $row['id'] ?>">Editar Mapeo: <?= htmlspecialchars($row['projektnummer'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="admin.php?section=subid_maps&page=<?= $page ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                            <input type="hidden" name="edit_map" value="1">
                            <input type="hidden" name="map_id" value="<?= $row['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label">Projektnummer</label>
                                <input type="text" class="form-control-dark" value="<?= htmlspecialchars($row['projektnummer'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label for="edit_subid_<?= $row['id'] ?>" class="form-label">SubID (max 50 caracteres)</label>
                                <input type="text" class="form-control-dark" id="edit_subid_<?= $row['id'] ?>" name="new_subid" value="<?= htmlspecialchars($row['subid'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="50">
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Guardar Cambios</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    <?php endif; ?>

    <!-- Modales de Acortador (generados dinámicamente) -->
    <?php if ($section == 'shortener'): ?>
        <!-- Modal: Añadir Enlace -->
        <div class="modal fade" id="addLinkModal" tabindex="-1" aria-labelledby="addLinkModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addLinkModalLabel">Crear Nuevo Enlace</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="admin.php?section=shortener">
                            <input type="hidden" name="add_link" value="1">
                            <div class="mb-3">
                                <label for="add_slug" class="form-label">Slug (Atajo)</label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background-color: var(--bg-slate); border-color: var(--border-color); color: var(--text-muted);">/go/</span>
                                    <input type="text" class="form-control-dark" id="add_slug" name="slug" required pattern="[a-zA-Z0-9_-]+">
                                </div>
                                <div class="form-text">Solo letras, números, guiones y guiones bajos.</div>
                            </div>
                            <div class="mb-3">
                                <label for="add_target_url" class="form-label">URL de Destino</label>
                                <input type="url" class="form-control-dark" id="add_target_url" name="target_url" required placeholder="https://...">
                            </div>
                            <button type="submit" class="btn btn-success" style="width: 100%;">Crear Enlace</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($tableData)): foreach ($tableData as $row): ?>
        <!-- Modal: Editar Enlace -->
        <div class="modal fade" id="editLinkModal-<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editLinkModalLabel-<?= $row['id'] ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editLinkModalLabel-<?= $row['id'] ?>">Editar Enlace: /go/<?= htmlspecialchars($row['slug'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="admin.php?section=shortener&page=<?= $page ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                            <input type="hidden" name="edit_link" value="1">
                            <input type="hidden" name="link_id" value="<?= $row['id'] ?>">
                            <div class="mb-3">
                                <label for="edit_slug_<?= $row['id'] ?>" class="form-label">Slug (Atajo)</label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background-color: var(--bg-slate); border-color: var(--border-color); color: var(--text-muted);">/go/</span>
                                    <input type="text" class="form-control-dark" id="edit_slug_<?= $row['id'] ?>" name="slug" value="<?= htmlspecialchars($row['slug'], ENT_QUOTES, 'UTF-8') ?>" required pattern="[a-zA-Z0-9_-]+">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_target_url_<?= $row['id'] ?>" class="form-label">URL de Destino</label>
                                <input type="url" class="form-control-dark" id="edit_target_url_<?= $row['id'] ?>" name="target_url" value="<?= htmlspecialchars($row['target_url'], ENT_QUOTES, 'UTF-8') ?>" required placeholder="https://...">
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Guardar Cambios</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    <?php endif; ?>    
    <!-- Bootstrap JS (para que funcionen los Modales) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- ¡NUEVO! JavaScript del Panel de Admin en archivo externo -->
    <script src="admin-script.js"></script>
    
</body>
</html>