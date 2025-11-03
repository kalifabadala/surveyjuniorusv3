<?php
// register.php (v2.0 - Integración con "Plan D" y Membresías)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config.php';
require 'functions.php';
// No se requiere 'maintenance_check.php' en la página de registro.

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if (empty($username) || empty($password) || empty($password_confirm)) {
        $error = 'Todos los campos son obligatorios.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
         $error = 'El nombre de usuario solo puede contener letras, números y guiones bajos (_).';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'El nombre de usuario ya está en uso. Por favor, elige otro.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                
                // --- ¡NUEVA LÓGICA DE REGISTRO DE MEMBRESÍA! ---
                // Inserta en las nuevas columnas de la DB (ver migration.sql)
                $stmt = $pdo->prepare(
                    "INSERT INTO usuarios (username, password, membership_type, jumper_count, jumper_limit, active, banned) 
                     VALUES (?, ?, 'PRUEBA GRATIS', 0, 5, 1, 0)"
                );
                
                if ($stmt->execute([$username, $hash])) {
                    $success = '¡Cuenta creada con éxito! Ahora puedes iniciar sesión.';
                    if (function_exists('logActivity')) {
                        // Obtener el ID del nuevo usuario
                        $newUserId = $pdo->lastInsertId();
                        logActivity($pdo, $newUserId, $username, 'Registro Exitoso');
                    }
                    // Limpiar el post para que el formulario no se rellene
                    $_POST = array();
                } else {
                    $error = 'Error al crear la cuenta. Inténtalo de nuevo.';
                }
            }
        } catch (PDOException $e) {
            error_log("Register Error: " . $e->getMessage());
            $error = 'Error en la base de datos durante el registro.';
            if (function_exists('logActivity')) logActivity($pdo, null, $username, 'Registro Error DB', $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Sección SEO Optimizada -->
    <title>Crear Cuenta - SurveyJunior</title>
    <meta name="description" content="Regístrate gratis en SurveyJunior y obtén 5 usos de prueba. Únete a la plataforma líder para la gestión de jumpers de encuestas.">
    <meta name="keywords" content="surveyjunior, registro, crear cuenta, prueba gratis, jumpers, encuestas">
    <link rel="canonical" href="https://surveyjunior.us/register.php">
    
    <!-- Robots: Permitir indexación -->
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph (para redes sociales) -->
    <meta property="og:title" content="Crear Cuenta - SurveyJunior">
    <meta property="og:description" content="Regístrate gratis y obtén 5 usos de prueba para la mejor herramienta de gestión de jumpers.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://surveyjunior.us/register.php">
    <meta property="og:image" content="https://surveyjunior.us/og-image.png"> <!-- Debes crear esta imagen -->
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Crear Cuenta - SurveyJunior">
    <meta name="twitter:description" content="Regístrate gratis y obtén 5 usos de prueba.">
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
        /* 3. Estilos CSS Embebidos (Tema "Slate" de Plan D) */
        
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
            background-color: var(--brand-green);
            color: var(--bg-slate);
            border: none;
            width: 100%;
            padding: 0.85rem;
            font-weight: 700;
            font-size: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            box-shadow: 0 6px 25px rgba(48, 232, 191, 0.4);
            transform: scale(1.02);
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
        .alert-success {
            background-color: rgba(48, 232, 191, 0.1);
            color: var(--brand-green);
            border: 1px solid rgba(48, 232, 191, 0.2);
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
                <h1>Crear Cuenta</h1>
                <p>Obtén 5 usos de prueba gratis.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="register.php" novalidate class="mt-4">
                <div class="mb-3">
                    <input type="text" name="username" class="form-control form-control-dark" placeholder="Usuario" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : '' ?>">
                </div>
                <div class="mb-3">
                    <input type="password" name="password" class="form-control form-control-dark" placeholder="Contraseña (mín. 6 caracteres)" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="password_confirm" class="form-control form-control-dark" placeholder="Confirmar Contraseña" required>
                </div>
                
                <button type="submit" class="btn-submit mt-3">Registrarme</button>
            </form>
            
            <div class="form-footer-link">
                ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
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
                // Usamos los estilos CSS que ya definimos en el <head>
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
                }, Math.random() * (10000 - 6000) + 6000); // Aleatorio entre 6 y 10 segundos
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