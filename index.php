<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- ETIQUETAS SEO ESENCIALES -->
    <title>SurveyJunior - Optimiza tus Jumpers de Encuestas</title>
    <meta name="description" content="La plataforma #1 para generar, colaborar y gestionar enlaces Jumper de Meinungsplatz, OpinionEx y más. Únete a la comunidad y optimiza tu flujo de trabajo.">
    <meta name="keywords" content="survey, jumper, meinungsplatz, opinionex, encuestas, cpanel, surveyjunior, generar jumper">
    <link rel="canonical" href="https://surveyjunior.us/index.php">
    
    <!-- ETIQUETAS OPEN GRAPH (para Redes Sociales) -->
    <meta property="og:title" content="SurveyJunior - Optimiza tus Jumpers">
    <meta property="og:description" content="La plataforma #1 para generar, colaborar y gestionar enlaces Jumper.">
    <meta property="og:image" content="https://surveyjunior.us/og-image.png"> <!-- DEBES CREAR ESTA IMAGEN (ej. 1200x630px) -->
    <meta property="og:url" content="https://surveyjunior.us">
    <meta property="og:type" content="website">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="SurveyJunior - Optimiza tus Jumpers">
    <meta name="twitter:description" content="La plataforma #1 para generar, colaborar y gestionar enlaces Jumper.">
    <meta name="twitter:image" content="https://surveyjunior.us/og-image.png">

    <!-- Favicon (Generado con Plan D) -->
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='3' y='3' width='18' height='18' rx='4' opacity='0.2' fill='%23E0E4F0'/%3E%3Cpath d='M16 8C16 6.89543 15.1046 6 14 6H10C8.89543 6 8 6.89543 8 8C8 9.10457 8.89543 10 10 10H14' stroke='%2330E8BF' stroke-width='2.5' stroke-linecap='round'/%3E%3Cpath d='M8 16C8 17.1046 8.89543 18 10 18H14C15.1046 18 16 17.1046 16 16C16 14.8954 15.1046 14 14 14H10' stroke='%2330E8BF' stroke-width='2.5' stroke-linecap='round'/%3E%3C/svg%3E">

    <!-- 1. Importar la tipografía "Inter" -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
    
    <!-- 2. Importar Bootstrap (Necesario para el Modal) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* 3. Estilos CSS Embebidos (Plan D) */
        
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
        }
        
        .main-container {
            position: relative;
            width: 100%;
            min-height: 100vh;
            padding: 2rem;
            overflow: hidden;
            background-image: 
                radial-gradient(var(--border-color) 1px, transparent 1px);
            background-size: 30px 30px;
            transition: transform 0.3s ease-out;
        }

        /* --- Barra de Navegación --- */
        nav.main-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding-bottom: 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text-light);
        }
        .logo-svg { width: 40px; height: 40px; fill: none; stroke: var(--text-light); stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
        .logo-svg .s-path { stroke: var(--brand-green); stroke-dasharray: 40; stroke-dashoffset: 40; animation: draw 1.5s ease-out 0.5s forwards; }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        .logo-text { font-size: 1.5rem; font-weight: 700; }
        .nav-links { display: flex; align-items: center; gap: 1rem; }
        
        .btn {
            padding: 0.6rem 1.25rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.9rem;
            border: 1px solid transparent;
        }

        .btn-secondary { background-color: var(--bg-card); color: var(--text-light); border-color: var(--border-color); }
        .btn-secondary:hover { border-color: var(--text-muted); }
        .btn-primary { background-color: var(--brand-green); color: var(--bg-slate); box-shadow: 0 4px 20px rgba(48, 232, 191, 0.2); }
        .btn-primary:hover { transform: scale(1.05); box-shadow: 0 6px 25px rgba(48, 232, 191, 0.4); }

        /* --- Bento Grid Layout --- */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            grid-auto-rows: minmax(100px, auto);
            gap: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .bento-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            transform: scale(1);
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .bento-box::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border-radius: 20px;
            padding: 1px;
            background: linear-gradient(135deg, var(--brand-green), var(--brand-blue));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: destination-out;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .bento-box:hover { transform: translateY(-5px) scale(1.01); border-color: transparent; }
        .bento-box:hover::before { opacity: 1; }

        /* --- Asignación de Áreas del Grid --- */
        .box-title { grid-column: span 12; background: none; border: none; padding: 2rem 0; text-align: center; }
        .box-title:hover { transform: none; }
        .box-title::before { display: none; }
        
        .box-title h1 {
            font-size: 3.5rem;
            font-weight: 900;
            line-height: 1.1;
            color: var(--text-light);
            max-width: 800px;
            margin: 0 auto 1.5rem auto;
        }
        .box-title h1 .highlight { color: var(--brand-green); }
        .box-title p {
            font-size: 1.2rem;
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto 2rem auto;
        }

        .box-workflow-in { grid-column: span 12; grid-row: span 2; }
        .box-workflow-process { grid-column: span 12; grid-row: span 2; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 1rem; }
        .box-workflow-out { grid-column: span 12; grid-row: span 2; }
        .box-ranking { grid-column: span 12; grid-row: span 4; }
        .box-activity { grid-column: span 12; grid-row: span 3; }
        
        @media (min-width: 992px) {
            .box-title { grid-column: span 12; grid-row: span 1; }
            .box-workflow-in { grid-column: span 4; grid-row: span 3; }
            .box-workflow-process { grid-column: span 4; grid-row: span 3; }
            .box-workflow-out { grid-column: span 4; grid-row: span 3; }
            .box-ranking { grid-column: span 7; grid-row: span 4; }
            .box-activity { grid-column: span 5; grid-row: span 4; }
        }
        
        /* --- Estilos del Contenido de las Cajas --- */
        .box-header { font-size: 1rem; font-weight: 700; color: var(--text-muted); margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 1px; }
        .code-block { background: var(--bg-slate); border: 1px solid var(--border-color); border-radius: 12px; padding: 1rem; font-family: 'Courier New', Courier, monospace; font-size: 0.9rem; color: var(--text-muted); word-break: break-all; }
        .code-block .line { display: block; opacity: 0.7; }
        .code-block .line .highlight { color: #FFD700; opacity: 1; }
        .code-block .success { color: var(--brand-green); font-weight: 700; }
        .spinner { width: 50px; height: 50px; border-radius: 50%; border: 4px solid var(--border-color); border-top-color: var(--brand-green); animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .ranking-list { list-style: none; }
        .ranking-item { display: flex; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color); }
        .ranking-item:last-child { border-bottom: none; }
        .rank-pos { font-size: 1rem; font-weight: 700; color: var(--text-muted); width: 30px; }
        .rank-avatar { width: 40px; height: 40px; border-radius: 50%; margin: 0 1rem; background-color: var(--bg-slate); }
        .rank-name { font-weight: 600; color: var(--text-light); }
        .rank-score { margin-left: auto; font-weight: 700; font-size: 1.1rem; color: var(--brand-green); }
        
        .activity-feed { height: 250px; overflow-y: auto; position: relative; }
        .activity-item { display: flex; gap: 1rem; padding: 0.75rem 0.25rem; font-size: 0.9rem; color: var(--text-muted); animation: feedScroll 15s linear infinite; }
        @keyframes feedScroll { 0% { transform: translateY(0); } 100% { transform: translateY(-100%); } }
        .activity-icon { width: 30px; height: 30px; border-radius: 50%; background-color: var(--bg-slate); color: var(--brand-green); display: grid; place-items: center; flex-shrink: 0; font-weight: 700; }
        .activity-text strong { color: var(--text-light); }
        
        footer { text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.9rem; }
        
        /* --- ESTILOS PARA EL MODAL DE LOGIN --- */
        .modal-content {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            color: var(--text-light);
        }
        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }
        /* Ajuste para el botón de cerrar en modo oscuro */
        .modal-header .btn-close {
             filter: invert(1) grayscale(100) brightness(200%);
        }
        .modal-footer {
            border-top: 1px solid var(--border-color);
        }
        
        .form-control-dark {
            background: var(--bg-slate);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            color: var(--text-light);
            font-family: 'Inter', sans-serif;
        }
        .form-control-dark:focus {
            background: var(--bg-slate);
            color: var(--text-light);
            outline: none;
            border-color: var(--brand-blue);
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.3);
        }
        
        .btn-modal-login {
            background-color: var(--brand-blue);
            color: white;
            border: none;
            width: 100%;
            padding: 0.75rem;
            font-weight: 600;
        }
        .btn-modal-login:hover {
            background-color: #4B9BFF;
        }
        .btn-modal-login:disabled {
            background: var(--text-muted);
        }
        
        .modal-bottom-links {
            font-size: 0.9rem;
            text-align: center;
            width: 100%;
        }
        .modal-bottom-links a {
            color: var(--brand-blue);
            text-decoration: none;
            font-weight: 600;
        }
        .modal-bottom-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="main-container" id="main-container">

        <!-- BARRA DE NAVEGACIÓN -->
        <nav class="main-nav">
            <a href="index.php" class="logo">
                <svg class="logo-svg" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="18" height="18" rx="4" opacity="0.2"/>
                    <path class="s-path" d="M16 8C16 6.89543 15.1046 6 14 6H10C8.89543 6 8 6.89543 8 8C8 9.10457 8.89543 10 10 10H14"/>
                    <path class="s-path" d="M8 16C8 17.1046 8.89543 18 10 18H14C15.1046 18 16 17.1046 16 16C16 14.8954 15.1046 14 14 14H10"/>
                </svg>
                <span class="logo-text">SurveyJunior</span>
            </a>
            <div class="nav-links">
                <!-- Este botón abre el modal (que añadiremos en la Parte 3) -->
                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#loginModal">
                    Iniciar Sesión
                </button>
                <a href="register.php" class="btn btn-primary">Comenzar Gratis</a>
            </div>
        </nav>

        <!-- BENTO GRID -->
        <main class="bento-grid">

            <!-- Título y CTA Principal -->
            <section class="bento-box box-title">
                <h1>Tu <span class="highlight">Flujo de Trabajo</span> de Encuestas,
                    <br>Automatizado.
                </h1>
                <p>
                    Genera, colabora y califica. SurveyJunior es el centro de mando
                    para tus enlaces Jumper de Meinungsplatz, OpinionEx y más.
                </p>
                <a href="register.php" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                    Empezar a Optimizar
                </a>
            </section>
            
            <!-- --- El Workflow Visual --- -->
            
            <!-- Caja 1: Entrada -->
            <section class="bento-box box-workflow-in" style="animation-delay: 100ms;">
                <div class="box-header">1. Entrada (URL Cruda)</div>
                <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 1rem;">
                    Pega tu URL de encuesta. Nosotros extraemos los datos.
                </p>
                <div class="code-block">
                    <span class="line">https://nk.decipherinc.com/survey/...</span>
                    <span class="line">?p=123456</span>
                    <span class="line">&<span class="highlight">m=123456789012345</span></span>
                </div>
            </section>
            
            <!-- Caja 2: Proceso (Simulado) -->
            <section class="bento-box box-workflow-process" style="animation-delay: 200ms;">
                <div class="spinner"></div>
                <div class="box-header" style="margin-bottom: 0;">2. Procesando</div>
                <p class="text-muted" style="font-size: 1rem;">
                    Buscando SubID en la base de datos...
                </p>
            </section>
            
            <!-- Caja 3: Salida -->
            <section class="bento-box box-workflow-out" style="animation-delay: 300ms;">
                <div class="box-header">3. Salida (Jumper Link)</div>
                <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 1rem;">
                    ¡Listo! Copia tu enlace Jumper generado.
                </p>
                <div class="code-block">
                    <span class="line success">¡Éxito! SubID Encontrado: f8113cee</span>
                    <span class="line" style="margin-top: 1rem; opacity: 1;">
                        ...complete?p=<span class="highlight">123456_f8113cee</span>
                    </span>
                </div>
            </section>
            
            <!-- --- Cajas de Características --- -->
            
            <!-- Caja 4: Ranking (Simulado) -->
            <section class="bento-box box-ranking" style="animation-delay: 400ms;">
                <div class="box-header">🏆 Ranking de Colaboradores</div>
                <ul class="ranking-list">
                    <li class="ranking-item"><span class="rank-pos">1.</span><img class="rank-avatar" src="https://api.dicebear.com/8.x/adventurer/svg?seed=Freddy" alt="avatar"><span class="rank-name">Freddy</span><span class="rank-score">1,204</span></li>
                    <li class="ranking-item"><span class="rank-pos">2.</span><img class="rank-avatar" src="https://api.dicebear.com/8.x/adventurer/svg?seed=Kalifa" alt="avatar"><span class="rank-name">Kalifa</span><span class="rank-score">982</span></li>
                    <li class="ranking-item"><span class="rank-pos">3.</span><img class="rank-avatar" src="https://api.dicebear.com/8.x/adventurer/svg?seed=Badala" alt="avatar"><span class="rank-name">Badala</span><span class="rank-score">765</span></li>
                    <li class="ranking-item"><span class="rank-pos">4.</span><img class="rank-avatar" src="https://api.dicebear.com/8.x/adventurer/svg?seed=Usuario" alt="avatar"><span class="rank-name">UsuarioX</span><span class="rank-score">510</span></li>
                </ul>
            </section>
            
            <!-- Caja 5: Actividad en Vivo (Simulada) -->
            <section class="bento-box box-activity" style="animation-delay: 500ms;">
                <div class="box-header">⚡ Actividad en Vivo</div>
                <div class="activity-feed">
                    <div class="activity-item"><div class="activity-icon">➕</div><div class="activity-text"><strong>Freddy</strong> añadió un SubID para <strong>987654</strong></div></div>
                    <div class="activity-item"><div class="activity-icon">🚀</div><div class="activity-text"><strong>Kalifa</strong> generó un jumper para <strong>112233</strong></div></div>
                    <div class="activity-item"><div class="activity-icon">👍</div><div class="activity-text"><strong>Badala</strong> calificó positivamente un SubID</div></div>
                    <div class="activity-item"><div class="activity-icon">🚀</div><div class="activity-text"><strong>UsuarioX</strong> generó un jumper para <strong>554433</strong></div></div>
                    <div class="activity-item"><div class="activity-icon">➕</div><div class="activity-text"><strong>Freddy</strong> añadió un SubID para <strong>776655</strong></div></div>
                    <!-- Duplicado para el efecto de scroll infinito -->
                    <div class="activity-item"><div class="activity-icon">➕</div><div class="activity-text"><strong>Freddy</strong> añadió un SubID para <strong>987654</strong></div></div>
                    <div class="activity-item"><div class="activity-icon">🚀</div><div class="activity-text"><strong>Kalifa</strong> generó un jumper para <strong>112233</strong></div></div>
                </div>
            </section>
            
        </main>
        
        <footer>
            SurveyJunior © 2024 - Creado para optimizar.
        </footer>
    </div>
    
    <!-- 4. MODAL DE LOGIN (HTML) -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">Iniciar Sesión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="ajax-login-form">
                        <div id="login-error-msg" class="alert alert-danger" style="display: none;" role="alert">
                            <!-- Los errores de JS irán aquí -->
                        </div>
                        <div class="mb-3">
                            <label for="login-username" class="form-label">Usuario</label>
                            <input type="text" class="form-control form-control-dark" id="login-username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="login-password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control form-control-dark" id="login-password" name="password" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="login-remember" name="remember_me">
                            <label class="form-check-label" for="login-remember">Recordarme</label>
                        </div>
                        <button type="submit" id="login-submit-btn" class="btn btn-modal-login">
                            <span id="login-btn-text">Entrar</span>
                            <span id="login-btn-spinner" class="spinner-border spinner-border-sm" style="display: none;" role="status" aria-hidden="true"></span>
                        </button>
                    </form>
                </div>
                <div class="modal-footer">
                    <p class="modal-bottom-links">
                        ¿No tienes cuenta? <a href="register.php">Regístrate aquí</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. JavaScript (Bootstrap + Simulación de Login) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // --- Interacción del Grid (del Plan D) ---
            const container = document.getElementById('main-container');
            if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
                container.addEventListener('mousemove', (e) => {
                    const x = e.clientX / window.innerWidth - 0.5;
                    const y = e.clientY / window.innerHeight - 0.5;
                    const moveX = x * -20;
                    const moveY = y * -20;
                    requestAnimationFrame(() => {
                        container.style.backgroundPosition = `calc(50% + ${moveX}px) calc(50% + ${moveY}px)`;
                    });
                });
            }
            
            // --- ¡NUEVO! Lógica del Modal de Login ---
            const loginForm = document.getElementById('ajax-login-form');
            const submitBtn = document.getElementById('login-submit-btn');
            const btnText = document.getElementById('login-btn-text');
            const btnSpinner = document.getElementById('login-btn-spinner');
            const errorMsg = document.getElementById('login-error-msg');
            
            if (loginForm) {
                loginForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    
                    // 1. Mostrar estado de carga
                    submitBtn.disabled = true;
                    btnText.style.display = 'none';
                    btnSpinner.style.display = 'inline-block';
                    errorMsg.style.display = 'none';
                    
                    // --- SIMULACIÓN DE FETCH ---
                    // En tu app real, aquí harías:
                    // const formData = new FormData(loginForm);
                    // const response = await fetch('api_login.php', { method: 'POST', body: formData });
                    // const data = await response.json();
                    
                    // Simulamos la llamada de red
                    setTimeout(() => {
                        const username = document.getElementById('login-username').value;
                        
                        // SIMULACIÓN DE ÉXITO
                        if (username === "admin" || username === "freddy") {
                            // "data.success == true"
                            errorMsg.style.display = 'none';
                            submitBtn.classList.remove('btn-modal-login');
                            submitBtn.classList.add('btn-success');
                            btnText.innerText = '¡Éxito!';
                            btnText.style.display = 'inline-block';
                            btnSpinner.style.display = 'none';
                            
                            // Redirigir a la app
                            setTimeout(() => {
                                // Esta sería la redirección real
                                // window.location.href = 'dashboard.php'; // (El nuevo nombre de tu app)
                                alert("Simulación: Redirigiendo a dashboard.php...");
                                // Reseteamos el modal para la demo
                                submitBtn.disabled = false;
                                submitBtn.classList.add('btn-modal-login');
                                submitBtn.classList.remove('btn-success');
                                btnText.innerText = 'Entrar';
                                // Opcional: cerrar el modal
                                // const modalInstance = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                                // modalInstance.hide();
                            }, 1000);
                        
                        // SIMULACIÓN DE ERROR
                        } else {
                            // "data.success == false"
                            errorMsg.innerText = "Usuario o contraseña incorrectos."; // (Mensaje de la API)
                            errorMsg.style.display = 'block';
                            submitBtn.disabled = false;
                            btnText.style.display = 'inline-block';
                            btnSpinner.style.display = 'none';
                        }
                    }, 1500); // Simular 1.5s de carga
                });
            }

            // --- Lógica de Toasts (Actividad en Vivo) ---
            // (La lógica de public-toast.js integrada aquí)
            const activityFeed = document.querySelector('.activity-feed');
            if (activityFeed) {
                // Duplicar el contenido para el scroll infinito
                activityFeed.innerHTML += activityFeed.innerHTML;
            }

        });
    </script>

</body>
</html>