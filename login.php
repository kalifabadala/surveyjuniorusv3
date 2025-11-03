<?php
// login.php (v5.0 - Integración con "Plan D" y Membresías)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config.php';
require 'functions.php';
// No se requiere 'maintenance_check.php' en la página de login.

$error = '';
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8');
}

$ip_address = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
if (strpos($ip_address, ',') !== false) { $ip_address = trim(explode(',', $ip_address)[0]); }
$ip_address = filter_var($ip_address, FILTER_VALIDATE_IP) ? $ip_address : 'Invalid IP';

// --- Anti-Fuerza Bruta Check ---
$is_blocked = false;
if (function_exists('isLoginBlocked') && isLoginBlocked($pdo, $ip_address)) {
    $error = 'Demasiados intentos fallidos. Por favor, inténtalo de nuevo en 15 minutos.';
    $is_blocked = true;
    if (function_exists('logActivity')) logActivity($pdo, null, ($_POST['username'] ?? 'N/A'), 'Login Bloqueado (IP)', $error);
}
// --- Fin Check ---

// Procesar el formulario solo si no está bloqueado
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);

    if (empty($username) || empty($password)) {
        $error = 'Usuario y contraseña son requeridos.';
    } else {
        try {
            // ¡IMPORTANTE! Seleccionamos * (todo) para obtener las nuevas columnas de membresía
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // --- Contraseña Correcta ---
                
                if ($user['active'] == 0 || $user['banned'] == 1) {
                    $error = 'Tu cuenta está inactiva o baneada. Contacta a un administrador.';
                    if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Login Fallido (Cuenta Inactiva)', $error);
                    goto login_form;
                }

                // --- Comprobación de Sesión Única (Sin cambios, sigue siendo válida) ---
                if ($user['current_session_token'] !== null) {
                    $five_minutes_ago = time() - 300; // 300 segundos = 5 minutos
                    if ($user['last_activity'] > $five_minutes_ago) {
                        $error = 'Cliente posee una sesion activa. Espera 5 minutos de inactividad o cierra la otra sesión.';
                        if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Login Fallido (Sesión Activa)', $error);
                        goto login_form;
                    }
                }
                // --- Fin Comprobación ---

                // --- Login Exitoso ---
                if (function_exists('clearLoginAttempts')) clearLoginAttempts($pdo, $ip_address);

                session_regenerate_id(true);

                $session_token = bin2hex(random_bytes(32));
                try {
                    $stmt_token = $pdo->prepare("UPDATE usuarios SET current_session_token = ? WHERE id = ?");
                    $stmt_token->execute([$session_token, $user['id']]);
                } catch (PDOException $e) {
                     error_log("Error al actualizar session_token en login: " . $e->getMessage());
                     $error = "Error de la base de datos al iniciar sesión.";
                     goto login_form;
                }
                
                $_SESSION['session_token'] = $session_token;
                // ¡IMPORTANTE! Guardamos el $user COMPLETO (con datos de membresía) en la sesión
                $_SESSION['user'] = $user; 

                if ($remember_me && function_exists('rememberUser')) {
                    rememberUser($pdo, $user['id']);
                } else {
                    if (function_exists('clearRememberMeCookie')) clearRememberMeCookie();
                    $stmt_del_token = $pdo->prepare("DELETE FROM persistent_logins WHERE user_id = ?");
                    $stmt_del_token->execute([$user['id']]);
                }

                if (function_exists('logActivity')) logActivity($pdo, $user['id'], $user['username'], 'Login Exitoso');
                if (function_exists('updateUserActivity')) updateUserActivity($pdo, $user['id']);
                if (function_exists('updateUserLocation')) updateUserLocation($pdo, $user['id']);

                // --- ¡CAMBIO CRÍTICO! Redirigir al nuevo dashboard ---
                header('Location: dashboard.php');
                exit;

            } else {
                // --- Login Fallido (Usuario/Contraseña incorrectos) ---
                $error = 'Usuario o contraseña incorrectos.';
                if (function_exists('recordFailedLogin')) recordFailedLogin($pdo, $ip_address);
                if (function_exists('logActivity')) logActivity($pdo, null, $username, 'Login Fallido', $error);
                if (function_exists('isLoginBlocked') && isLoginBlocked($pdo, $ip_address)) {
                     $error .= ' Has alcanzado el límite de intentos.';
                     $is_blocked = true;
                     if (function_exists('logActivity')) logActivity($pdo, null, $username, 'Login Bloqueado (IP)', 'Límite alcanzado');
                }
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $error = 'Error en la base de datos durante el inicio de sesión.';
            if (function_exists('logActivity')) logActivity($pdo, null, $username, 'Login Error DB', $e->getMessage());
        }
    }
}

// --- Check "Recordarme" al cargar la página (si no hay sesión activa) ---
if (!isset($_SESSION['user']) && !$is_blocked) {
    if (function_exists('validateRememberMe')) {
        $userFromCookie = validateRememberMe($pdo);
        if ($userFromCookie) {
            // ¡CAMBIO CRÍTICO! Redirigir al nuevo dashboard
            header('Location: dashboard.php');
            exit;
        }
    }
} elseif (isset($_SESSION['user'])) {
    // ¡CAMBIO CRÍTICO! Redirigir al nuevo dashboard
    header('Location: dashboard.php');
    exit;
}

login_form:
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Sección SEO Optimizada -->
    <title>Iniciar Sesión - SurveyJunior</title>
    <meta name="description" content="Inicia sesión en tu cuenta de SurveyJunior para acceder al dashboard, generar jumpers y gestionar tu membresía.">
    <meta name="keywords" content="surveyjunior, login, iniciar sesión, dashboard, jumpers">
    <link rel="canonical" href="https://surveyjunior.us/login.php">
    
    <!-- Robots: Permitir indexación -->
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph (para redes sociales) -->
    <meta property="og:title" content="Iniciar Sesión - SurveyJunior">
    <meta property="og:description" content="Accede a tu cuenta de SurveyJunior para gestionar tus jumpers de encuestas.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://surveyjunior.us/login.php">
    <meta property="og:image" content="https://surveyjunior.us/og-image.png">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Iniciar Sesión - SurveyJunior">
    <meta name="twitter:description" content="Accede a tu cuenta de SurveyJunior.">
    <meta name="twitter:image" content="https://surveyjunior.us/og-image.png">
    
    <!-- Favicon (Usando el SVG del Plan D) -->
    <link rel="icon" href='data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%2330E8BF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4" opacity="0.2" stroke="rgba(67, 83, 125, 0.2)"/><path d="M16 8C16 6.89543 15.1046 6 14 6H10C8.89543 6 8 6.89543 8 8C8 9.10457 8.89543 10 10 10H14"/><path d="M8 16C8 17.1046 8.89543 18 10 18H14C15.1046 18 16 17.1046 16 16C16 14.8954 15.1046 14 14 14H10"/></svg>'>

    <!-- 1. Importar la tipografía "Inter" -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
    
    <!-- 2. Importar Bootstrap (Necesario para Alertas y Toasts) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* 3. Estilos CSS Embebidos (Idénticos a register.php para consistencia) */
        
        :root {
            --bg-slate: #0A0E1A; /* Pizarra/Noche */
            --bg-card: #13192B; /* Un poco más claro */
            --border-color: rgba(67, 83, 125, 0.2);
            --glow-border: rgba(94, 234, 212, 0.5); /* Verde Neón para el brillo */
            --text-light: #E0E4F0;
            --text-muted: #8392AD;
            --brand-green: #30E8BF; /* Verde Eléctrico */
            --brand-blue: #3B82F6; /* Azul Vibrante */
            --brand-red: #F47174;
            --brand-yellow: #FACC15;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-slate);
            color: var(--text-light);
            min-height: 100vh;
            /* Fondo de Grid */
            background-image: 
                radial-gradient(var(--border-color) 1px, transparent 1px);
            background-size: 30px 30px;
        }
        
        /* Contenedor del Formulario */
        .form-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .form-box {
            width: 100%;
            max-width: 450px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3);
        }
        
        /* Logo y Título */
        .form-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }
        .logo-svg {
            width: 50px;
            height: 50px;
            fill: none;
            stroke: var(--brand-green);
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
            margin-bottom: 1rem;
        }
        .form-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-light);
        }
        .form-header p {
            color: var(--text-muted);
            margin-top: 0.5rem;
        }
        
        /* Estilos del Formulario */
        .form-control-dark {
            background: var(--bg-slate);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.85rem 1rem;
            font-size: 1rem;
            color: var(--text-light);
            font-family: 'Inter', sans-serif;
            width: 100%;
        }
        .form-control-dark:focus {
            background: var(--bg-slate);
            color: var(--text-light);
            outline: none;
            border-color: var(--brand-blue);
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.3);
        }
        
        .btn-submit {
            background-color: var(--brand-blue); /* Botón de Login es Azul */
            color: white;
            border: none;
            width: 100%;
            padding: 0.85rem;
            font-weight: 700;
            font-size: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-submit:hover:not(:disabled) {
            background-color: #4B9BFF;
            box-shadow: 0 6px 25px rgba(59, 130, 246, 0.4);
            transform: scale(1.02);
        }
        .btn-submit:disabled {
            background: var(--text-muted);
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .form-check-label {
            color: var(--text-muted);
        }
        .form-check-input:checked {
            background-color: var(--brand-blue);
            border-color: var(--brand-blue);
        }
        .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
        }
        
        .form-footer-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        .form-footer-link a {
            color: var(--brand-blue);
            text-decoration: none;
            font-weight: 600;
        }
        .form-footer-link a:hover {
            text-decoration: underline;
        }
        
        /* Alertas de Bootstrap (para errores/éxito) */
        .alert-danger {
            background-color: rgba(244, 113, 116, 0.1);
            color: var(--brand-red);
            border: 1px solid rgba(244, 113, 116, 0.2);
        }
        
        /* Estilos para Toasts de Actividad (de public-toast.js) */
        .live-toast {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 0.5rem;
            margin-top: 0.5rem;
            transition: all 0.4s ease;
            animation: liveToastSlideIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes liveToastSlideIn {
            from { opacity: 0; transform: translateY(100px) scale(0.8); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .live-toast .toast-body {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.25rem 0.5rem;
            color: var(--text-muted);
        }
        .live-toast-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-blue), var(--brand-green));
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 1.2rem;
            box-shadow: 0 4px 10px rgba(48, 232, 191, 0.2);
        }
        .live-toast-text {
            font-size: 0.9rem;
            line-height: 1.3;
            color: var(--text-muted);
        }
        .live-toast-text strong, .live-toast-text b {
            color: var(--text-light);
            font-weight: 600;
        }
        .live-toast-time {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-left: auto;
            white-space: nowrap;
            padding-right: 0.5rem;
        }

    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-box">
            
            <div class="form-header">
                <svg class="logo-svg" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="18" height="18" rx="4" opacity="0.2" stroke="rgba(67, 83, 125, 0.2)"/>
                    <path d="M16 8C16 6.89543 15.1046 6 14 6H10C8.89543 6 8 6.89543 8 8C8 9.10457 8.89543 10 10 10H14"/>
                    <path d="M8 16C8 17.1046 8.89543 18 10 18H14C15.1046 18 16 17.1046 16 16C16 14.8954 15.1046 14 14 14H10"/>
                </svg>
                <h1>Iniciar Sesión</h1>
                <p>Bienvenido de nuevo.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate class="mt-4">
                <div class="mb-3">
                    <input type="text" name="username" class="form-control form-control-dark" placeholder="Usuario" required autofocus value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : '' ?>" <?= $is_blocked ? 'disabled' : '' ?>/>
                </div>
                <div class="mb-3">
                    <input type="password" name="password" class="form-control form-control-dark" placeholder="Contraseña" required <?= $is_blocked ? 'disabled' : '' ?>/>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me" value="1" <?= $is_blocked ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="remember_me">Recordarme</label>
                    </div>
                    <!-- (Opcional) <a href="#" style="font-size: 0.9rem; color: var(--brand-blue); text-decoration: none;">¿Olvidaste tu clave?</a> -->
                </div>
                
                <button type="submit" class="btn-submit" <?= $is_blocked ? 'disabled' : '' ?>>
                    Entrar
                </button>
            </form>
            
            <div class="form-footer-link">
                ¿No tienes cuenta? <a href="register.php">Regístrate aquí</a>
            </div>

        </div>
    </div>

    <!-- Contenedor para Public Toasts (Central) -->
    <div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3" id="public-toast-container" style="z-index: 1100">
        <!-- Los toasts de actividad se insertarán aquí -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script de Public Toast (Integrado) -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let activityList = [];
            let toastInterval;
            
            function showActivityToast(user, message) {
                const toastContainer = document.getElementById('public-toast-container');
                if (!toastContainer) { return; }

                const toastId = 'toast-' + Date.now();
                const toastHTML = `
                    <div id="${toastId}" class="toast live-toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                        <div class="toast-body">
                            <div class="live-toast-icon">
                                <i class="bi bi-graph-up-arrow" style="font-size: 1.2rem;"></i>
                            </div>
                            <div class="live-toast-text">
                                ${message} <!-- El HTML (con <b>) viene de la API -->
                            </div>
                            <small class="live-toast-time">justo ahora</small>
                        </div>
                    </div>
                `;
                
                toastContainer.insertAdjacentHTML('beforeend', toastHTML);
                const toastElement = document.getElementById(toastId);
                const toast = new bootstrap.Toast(toastElement);
                toast.show();
                
                toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
            }

            function startToastLoop() {
                if (toastInterval) clearInterval(toastInterval);
                
                toastInterval = setInterval(() => {
                    if (activityList.length === 0) return;
                    const randomIndex = Math.floor(Math.random() * activityList.length);
                    const activity = activityList[randomIndex];
                    showActivityToast(activity.user, activity.message);
                }, Math.random() * (10000 - 6000) + 6000); 
            }

            async function fetchActivityData() {
                try {
                    // LLamada a la API que ya tenías
                    const response = await fetch('api_public_activity.php');
                    if (!response.ok) return;
                    
                    const data = await response.json();
                    if (data.success && data.activities.length > 0) {
                        activityList = data.activities;
                        
                        // Mostrar uno de inmediato (después de unos segundos)
                        const randomIndex = Math.floor(Math.random() * activityList.length);
                        const activity = activityList[randomIndex];
                        setTimeout(() => {
                           showActivityToast(activity.user, activity.message);
                        }, 3000); // Esperar 3 segundos
                        
                        startToastLoop();
                    }
                } catch (error) {
                    console.error('Error fetching public activity:', error);
                }
            }
            fetchActivityData();
        });
    </script>
</body>
</html>