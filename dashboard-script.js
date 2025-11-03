// dashboard-script.js (v1.2 - Arregla stats 'undefined')
// Este es el "cerebro" de la SPA del dashboard.

// Esperar a que el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {

    // --- 1. DEFINICIÓN DE VARIABLES GLOBALES ---
    const body = document.body;
    const moduleContentContainer = document.getElementById('module-content');
    const pageTitleMobile = document.getElementById('page-title-mobile'); 
    const toastContainer = document.getElementById('toast-container');
    const themeToggleButton = document.getElementById('theme-toggle-btn');
    const themeToggleIcon = themeToggleButton ? themeToggleButton.querySelector('i') : null;
    const userAvatarButton = document.getElementById('user-avatar-btn');
    const userProfileOffcanvasEl = document.getElementById('userProfileOffcanvas');
    let userProfileOffcanvas;
    
    // Modales (los inicializamos si existen)
    let inactivityModal, subidErrorModal, uploadProofModal;
    if (document.getElementById('inactivityModal')) {
        inactivityModal = new bootstrap.Modal(document.getElementById('inactivityModal'));
    }
    if (document.getElementById('subidErrorModal')) {
        subidErrorModal = new bootstrap.Modal(document.getElementById('subidErrorModal'));
    }
    if (document.getElementById('uploadProofModal')) {
        uploadProofModal = new bootstrap.Modal(document.getElementById('uploadProofModal'));
    }

    // Estado de la App
    let currentModule = 'home'; // Módulo por defecto
    let inactivityTimer, countdownTimer;
    const inactivityLimit = 5 * 60 * 1000; // 5 minutos


    // --- 2. FUNCIONES PRINCIPALES DE LA APP ---

    /**
     * Carga el contenido de un módulo de forma asíncrona (el corazón de la SPA)
     * @param {string} moduleName - El nombre del módulo (ej: 'home', 'ranking')
     * @param {boolean} pushState - ¿Debería esta carga añadir una entrada al historial del navegador?
     */
    async function loadModuleContent(moduleName, pushState = true) {
        if (!moduleContentContainer) return;
        
        currentModule = moduleName;
        
        // 1. Mostrar Skeleton (pantalla de carga)
        const skeletonTemplateId = `skeleton-${moduleName}`;
        const skeletonTemplate = document.getElementById(skeletonTemplateId);
        
        if (skeletonTemplate) {
            moduleContentContainer.innerHTML = skeletonTemplate.innerHTML;
        } else {
            // Skeleton genérico si no se encuentra uno específico
            moduleContentContainer.innerHTML = `
                <div class="d-flex justify-content-center align-items-center" style="height: 70vh;">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>`;
        }
        
        // 2. Actualizar el estado activo del Sidebar
        document.querySelectorAll('.app-sidebar .nav-link.active').forEach(link => link.classList.remove('active'));
        const newActiveLink = document.querySelector(`.app-sidebar .nav-link[href*="module=${moduleName}"]`);
        if (newActiveLink) {
            newActiveLink.classList.add('active');
        }

        try {
            // 3. Hacer la llamada fetch al backend
            const response = await fetch(`dashboard.php?module=${moduleName}&fetch=fragment`);
            
            // 4. Manejar errores de la API
            if (!response.ok) {
                if (response.status === 401 || response.status === 403) {
                    // 401 (Sesión Inválida) o 403 (Membresía Vencida)
                    showToast('Tu sesión ha expirado o no tienes permisos. Redirigiendo...', 'danger');
                    setTimeout(() => window.location.href = 'login.php', 2000);
                }
                throw new Error(`Error ${response.status}: No se pudo cargar el módulo.`);
            }

            // 5. Inyectar el contenido HTML
            const html = await response.text();
            moduleContentContainer.innerHTML = html;
            
            // 6. Actualizar el historial del navegador
            const targetUrl = `dashboard.php?module=${moduleName}`;
            if (pushState && window.location.search !== `?module=${moduleName}`) {
                history.pushState({ module: moduleName }, '', targetUrl);
            }
            
            // 7. Ejecutar scripts específicos del módulo
            initializeModuleScripts(moduleName);
            
        } catch (error) {
            console.error('Error al cargar el módulo:', error);
            moduleContentContainer.innerHTML = `<div class='bento-box'><div class='box-header text-danger'>Error</div><p>${error.message}</p></div>`;
        }
    }

    /**
     * Ejecuta el JS necesario después de que se carga un módulo
     * @param {string} moduleName - El nombre del módulo que se acaba de cargar
     */
    function initializeModuleScripts(moduleName) {
        // Habilitar Tooltips de Bootstrap (si hay)
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Scripts específicos
        switch (moduleName) {
            case 'home':
                fetchHomeStats(); // Cargar estadísticas del dashboard
                fetchRankingData(); // ¡NUEVO! Cargar ranking para el "home"
                
                // Configurar el formulario de Generador Rápido (Opensurvey)
                const opensurveyForm = document.getElementById('opensurvey-form');
                if (opensurveyForm) {
                    opensurveyForm.addEventListener('submit', (e) => handleGeneratorSubmit(e, 'api_generate_opensurvey.php'));
                }
                break;
            case 'ranking':
                fetchRankingData(); // Cargar el ranking (página completa)
                break;
            case 'membership':
                handlePaypalRender(); // Renderizar botones de PayPal
                handleProofUpload(); // Configurar modal de subida de comprobante
                break;
            case 'modules':
                // (No necesita JS por ahora)
                break;
            // Cargar los módulos de generadores
            case 'meinungsplatz':
            case 'opensurvey':
            case 'opinionexchange':
            case 'samplicio': 
            case 'cint':      
                const generatorForm = document.getElementById('generator-form');
                if(generatorForm) {
                    const apiEndpoint = generatorForm.dataset.api;
                    generatorForm.addEventListener('submit', (e) => handleGeneratorSubmit(e, apiEndpoint));
                }
                // Configurar el modal de "Añadir SubID" si existe (solo para Meinungsplatz)
                const subidModalForm = document.getElementById('modal-add-subid-form');
                if (subidModalForm) {
                    subidModalForm.addEventListener('submit', handleSubidModalSubmit);
                }
                break;
        }
    }

    
    // --- 3. MANEJADORES DE EVENTOS GLOBALES ---

    // 3.1. Navegación de la SPA (Sidebar)
    document.querySelector('.app-sidebar').addEventListener('click', (e) => {
        const link = e.target.closest('a.nav-link');
        // Ignorar enlaces de admin, logout o externos
        if (!link || !link.href.includes('dashboard.php') || link.href.includes('logout.php') || link.target === '_blank') {
            return;
        }
        
        e.preventDefault(); // Prevenir recarga de página
        const urlParams = new URLSearchParams(link.search);
        const targetModule = urlParams.get('module') || 'home';
        
        if (targetModule !== currentModule) {
            loadModuleContent(targetModule);
        }
    });

    // 3.2. Botón "Atrás" del Navegador
    window.addEventListener('popstate', (e) => {
        const targetModule = e.state ? e.state.module : 'home';
        loadModuleContent(targetModule, false);
    });

    // 3.3. Cambio de Tema (Claro/Oscuro)
    function setTheme(theme) {
        if (theme === 'light') {
            body.setAttribute('data-theme', 'light');
            if(themeToggleIcon) {
                themeToggleIcon.classList.remove('bi-sun-fill');
                themeToggleIcon.classList.add('bi-moon-fill');
            }
            localStorage.setItem('theme', 'light');
        } else {
            body.removeAttribute('data-theme');
            if(themeToggleIcon) {
                themeToggleIcon.classList.remove('bi-moon-fill');
                themeToggleIcon.classList.add('bi-sun-fill');
            }
            localStorage.setItem('theme', 'dark');
        }
    }
    if (themeToggleButton) {
        themeToggleButton.addEventListener('click', () => {
            const newTheme = body.hasAttribute('data-theme') ? 'dark' : 'light';
            setTheme(newTheme);
        });
    }

    // 3.4. Offcanvas de Perfil de Usuario
    if (userAvatarButton && userProfileOffcanvasEl) {
        userProfileOffcanvas = new bootstrap.Offcanvas(userProfileOffcanvasEl);
        userAvatarButton.addEventListener('click', () => {
            userProfileOffcanvas.show();
            // Cargar estadísticas del perfil
            fetchHomeStats();
        });
    }

    // 3.5. Temporizador de Inactividad
    function resetInactivityTimer() {
        clearTimeout(inactivityTimer);
        clearTimeout(countdownTimer);
        if (inactivityModal && document.getElementById('inactivityModal').classList.contains('show')) {
            inactivityModal.hide();
        }
        inactivityTimer = setTimeout(showInactivityModal, inactivityLimit);
    }
    function showInactivityModal() {
        if (!inactivityModal) return;
        const countdownEl = document.getElementById('inactivityCountdown');
        if (countdownEl) countdownEl.textContent = '60';
        inactivityModal.show();
        
        let countdown = 60;
        countdownTimer = setInterval(() => {
            countdown--;
            const currentCountdownEl = document.getElementById('inactivityCountdown');
            if(currentCountdownEl) {
                currentCountdownEl.textContent = countdown;
            }
            if (countdown <= 0) {
                clearInterval(countdownTimer);
                window.location.href = 'logout.php';
            }
        }, 1000);
    }
    // ¡ARREGLO DEL BUG DE INACTIVIDAD!
    function stayLoggedIn() {
        clearInterval(countdownTimer);
        if(inactivityModal) inactivityModal.hide();
        // Avisar al servidor que seguimos aquí
        fetch('api_ping.php') 
            .then(response => {
                if(response.ok) {
                    resetInactivityTimer();
                    showToast('Tu sesión ha sido extendida.', 'success');
                } else {
                    window.location.href = 'logout.php';
                }
            })
            .catch(() => window.location.href = 'logout.php');
    }
    // Eventos que reinician el contador
    ['mousemove', 'keypress', 'click', 'scroll', 'touchstart'].forEach(evt => document.addEventListener(evt, resetInactivityTimer, false));
    // Asegurarse que los botones existen antes de añadir listeners
    const logoutBtn = document.getElementById('logoutBtn');
    if(logoutBtn) logoutBtn.addEventListener('click', () => window.location.href = 'logout.php');
    
    const stayLoggedInBtn = document.getElementById('stayLoggedInBtn');
    if(stayLoggedInBtn) stayLoggedInBtn.addEventListener('click', stayLoggedIn);
    

    // --- 4. MANEJADORES DE FORMULARIOS (SPA) ---
    
    /**
     * Manejador genérico para todos los formularios de generadores
     * @param {Event} e - El evento de submit
     * @param {string} apiEndpoint - La API a la que se debe llamar (ej: 'api_generate.php')
     */
    async function handleGeneratorSubmit(e, apiEndpoint) {
        e.preventDefault();
        const form = e.target;
        const submitButton = form.querySelector('button[type="submit"]');
        const btnText = submitButton.querySelector('.btn-text');
        const btnSpinner = submitButton.querySelector('.spinner-border');
        
        // ¡Importante! El contenedor de resultado ahora es estándar
        const resultContainer = document.getElementById('generator-result-container'); 

        if(!resultContainer) {
            console.error("Contenedor 'generator-result-container' no encontrado.");
            return;
        }

        resultContainer.innerHTML = '';
        resultContainer.style.display = 'none'; // Ocultar al empezar
        submitButton.disabled = true;
        if(btnText) btnText.style.display = 'none';
        if(btnSpinner) btnSpinner.style.display = 'inline-block';

        try {
            const formData = new FormData(form);
            const response = await fetch(apiEndpoint, {
                method: 'POST',
                body: formData
            });

            if (response.status === 401 || response.status === 403) {
                 const data = await response.json();
                 showToast(data.message || 'Tu sesión o membresía ha expirado.', 'danger');
                 setTimeout(() => loadModuleContent('membership'), 2000);
                 return;
            }

            const data = await response.json();
            resultContainer.style.display = 'block'; // Mostrar el contenedor de resultados

            if (data.success) {
                // --- ¡ÉXITO! ---
                const template = document.getElementById('template-jumper-success');
                const clone = template.content.cloneNode(true);
                
                // Rellenar la plantilla
                clone.querySelector('.jsc-subid-info .subid-display').textContent = data.subid;
                // ¡NUEVO! Mostrar quién añadió el SubID
                const authorEl = clone.querySelector('.jsc-subid-info .subid-author');
                if (authorEl) authorEl.textContent = data.added_by || 'Sistema';
                
                clone.querySelectorAll('.jumper-link-href').forEach(a => {
                    a.href = data.jumper;
                    a.textContent = data.jumper;
                });
                clone.querySelector('.btn-copy-jumper').addEventListener('click', (e) => copyJumper(data.jumper, e.currentTarget));
                
                // Configurar sección de rating
                const ratingSection = clone.querySelector('.rating-section');
                if (ratingSection) { 
                    ratingSection.dataset.subid = data.subid; // Guardar SubID para la lógica de rating
                    getRatings(data.subid, ratingSection); // Cargar ratings actuales
                    
                    ratingSection.querySelector('.btn-close-rating').addEventListener('click', () => ratingSection.style.display = 'none');
                    ratingSection.querySelectorAll('.rating-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => handleRatingClick(e, data.subid, ratingSection));
                    });
                }
                
                resultContainer.innerHTML = '';
                resultContainer.appendChild(clone);
                
                // Actualizar contadores en vivo
                fetchHomeStats();

            } else if (data.error_type === 'subid_not_found') {
                // --- ERROR: SUBID NO ENCONTRADO ---
                if (subidErrorModal) {
                    document.getElementById('modal-error-message').innerHTML = data.message;
                    document.getElementById('modal-add-projektnummer').value = data.projektnummer;
                    document.getElementById('modal-add-new-subid').value = '';
                    subidErrorModal.show();
                } else {
                     throw new Error(data.message || 'Error: SubID no encontrado pero el modal no existe.');
                }
            } else {
                // --- OTROS ERRORES ---
                throw new Error(data.message || 'Error desconocido');
            }

        } catch (error) {
            console.error(`Error en ${apiEndpoint}:`, error);
            resultContainer.innerHTML = `<div class="alert alert-danger-custom">${error.message}</div>`;
        } finally {
            // Restaurar botón
            submitButton.disabled = false;
            if(btnText) btnText.style.display = 'inline-block';
            if(btnSpinner) btnSpinner.style.display = 'none';
        }
    }

    /**
     * Maneja el envío del modal "Añadir SubID"
     */
    async function handleSubidModalSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const submitButton = form.querySelector('button[type="submit"]');
        const btnText = submitButton.querySelector('.btn-text');
        const btnSpinner = submitButton.querySelector('.spinner-border');

        submitButton.disabled = true;
        if(btnText) btnText.style.display = 'none';
        if(btnSpinner) btnSpinner.style.display = 'inline-block';

        try {
            const formData = new FormData(form);
            const response = await fetch('api_add_subid.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.status === 401 || response.status === 403) {
                 const data = await response.json();
                 showToast(data.message || 'Tu sesión o membresía ha expirado.', 'danger');
                 if(subidErrorModal) subidErrorModal.hide();
                 setTimeout(() => loadModuleContent('membership'), 2000);
                 return;
            }

            const data = await response.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                if(subidErrorModal) subidErrorModal.hide();
                
                // Re-enviar el formulario principal (sea cual sea)
                // Usamos 'currentModule' para encontrar el formulario correcto
                const mainForm = document.getElementById('generator-form');
                if (mainForm) {
                    // Esperar a que el modal se cierre antes de re-enviar
                    setTimeout(() => mainForm.requestSubmit(), 300);
                }
            } else {
                throw new Error(data.message || 'Error al añadir SubID');
            }
            
        } catch (error) {
            console.error('Error al añadir SubID:', error);
            showToast(error.message, 'danger');
        } finally {
            submitButton.disabled = false;
            if(btnText) btnText.style.display = 'inline-block';
            if(btnSpinner) btnSpinner.style.display = 'none';
        }
    }

    /**
     * Maneja el clic en los botones de rating (pulgar arriba/abajo)
     */
    async function handleRatingClick(e, subid, ratingSection) {
        const button = e.currentTarget;
        const rating = button.dataset.rating;
        
        ratingSection.querySelectorAll('.rating-btn').forEach(btn => btn.disabled = true);

        try {
            const formData = new FormData();
            formData.append('subid', subid);
            formData.append('rating', rating);
            
            const response = await fetch('api_rate.php', {
                method: 'POST',
                body: formData
            });

            if (response.status === 401) {
                 showToast('Sesión expirada. Por favor, recarga.', 'warning');
                 return;
            }
            
            const data = await response.json();
            if (data.success) {
                showToast(data.message, 'success');
                if (data.ratings) {
                    const posEl = ratingSection.querySelector('.positive-count');
                    const negEl = ratingSection.querySelector('.negative-count');
                    if(posEl) posEl.textContent = data.ratings.positive;
                    if(negEl) negEl.textContent = data.ratings.negative;
                }
            } else {
                throw new Error(data.message || 'Error al calificar');
            }
        } catch (error) {
            console.error('Error al calificar:', error);
            showToast(error.message, 'danger');
            ratingSection.querySelectorAll('.rating-btn').forEach(btn => btn.disabled = false);
        }
    }

    /**
     * Renderiza los botones de PayPal en el módulo de membresía
     */
    function handlePaypalRender() {
        const container = document.getElementById('paypal-button-container');
        if (!container) return;
        
        try {
            if (typeof paypal !== 'undefined' && paypal.Buttons) {
                paypal.Buttons({
                    createOrder: (data, actions) => {
                        return actions.order.create({
                            purchase_units: [{
                                amount: { value: '5.00', currency_code: 'USD' } // ¡Configura tu precio!
                            }]
                        });
                    },
                    onApprove: (data, actions) => {
                        return actions.order.capture().then(details => {
                            showToast('¡Pago completado! Gracias, ' + details.payer.name.given_name, 'success');
                            // EN PRODUCCIÓN: Aquí harías un fetch a tu servidor
                            // para verificar el 'orderID' (data.orderID) y activar la membresía.
                            // ej: fetch('api_paypal_success.php', { method: 'POST', ... })
                            loadModuleContent('membership'); // Recargar el módulo
                        });
                    },
                    onError: (err) => {
                        console.error("Error de PayPal:", err);
                        showToast("Ocurrió un error con el pago de PayPal.", 'danger');
                    }
                }).render('#paypal-button-container');
            } else {
                throw new Error('El SDK de PayPal no se cargó correctamente.');
            }
        } catch (e) {
            console.error("Error al cargar botones de PayPal:", e);
            container.innerHTML = `<p class="text-danger">${e.message}</p>`;
        }
    }

    /**
     * Maneja la subida del formulario de comprobante de pago
     */
    function handleProofUpload() {
        const form = document.getElementById('payment-proof-form');
        if (!form) return;
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitButton = form.querySelector('#submit-proof-btn');
            const btnText = submitButton.querySelector('.btn-text');
            const btnSpinner = submitButton.querySelector('.spinner-border');
            const errorMsg = form.querySelector('#modal-upload-error');

            submitButton.disabled = true;
            if(btnText) btnText.style.display = 'none';
            if(btnSpinner) btnSpinner.style.display = 'inline-block';
            if(errorMsg) errorMsg.style.display = 'none';
            
            try {
                const formData = new FormData(form);
                const response = await fetch('api_submit_proof.php', { // API dedicada
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showToast(data.message, 'success');
                    if (uploadProofModal) uploadProofModal.hide();
                    loadModuleContent('membership');
                } else {
                    throw new Error(data.message || 'Error desconocido al subir.');
                }
                
            } catch (error) {
                console.error("Error al subir comprobante:", error);
                if(errorMsg) {
                    errorMsg.textContent = error.message;
                    errorMsg.style.display = 'block';
                }
            } finally {
                submitButton.disabled = false;
                if(btnText) btnText.style.display = 'inline-block';
                if(btnSpinner) btnSpinner.style.display = 'none';
            }
        });
    }

    // --- 5. FUNCIONES DE API (FETCH) ---

    /**
     * ¡FUNCIÓN CORREGIDA! (v1.2)
     * Carga las estadísticas del dashboard y el perfil
     */
    async function fetchHomeStats() {
        try {
            const response = await fetch('api_home_stats.php');
            if (!response.ok) {
                 // Si falla el fetch, no rellenar nada y mostrar error en consola
                console.error('Error fetching home stats, response not OK');
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            
            if (data.success && data.stats) {
                // Dashboard (Módulo Home)
                const elTotal = document.getElementById('stat-total-jumpers-all-time');
                const elMonth = document.getElementById('stat-jumpers-month');
                const elRankName = document.getElementById('stat-rank-name');
                const elRankLevel = document.getElementById('stat-rank-level');
                const elSubids = document.getElementById('stat-total-subids');
                const elSubidsRank = document.getElementById('stat-subids-rank');
                
                // Rellenar datos (con comprobación de 'undefined')
                if (elTotal) elTotal.textContent = data.stats.total_jumpers_all_time ?? '0';
                if (elMonth) elMonth.textContent = `Este mes: ${data.stats.total_jumpers_month ?? '0'}`;
                if (elRankName) elRankName.textContent = data.stats.rank_name ?? 'N/A';
                if (elRankLevel) elRankLevel.textContent = `Nivel ${data.stats.rank_level ?? '0'}`;
                if (elSubids) elSubids.textContent = data.stats.total_subids ?? '0';
                if (elSubidsRank) elSubidsRank.textContent = `#${data.stats.subid_rank ?? 'N/A'} en el ranking`;
                
                // Perfil Offcanvas
                const elProfileJumpers = document.getElementById('profile-stat-jumpers');
                const elProfileSubids = document.getElementById('profile-stat-subids');
                if (elProfileJumpers) elProfileJumpers.textContent = data.stats.total_jumpers_month ?? '0';
                if (elProfileSubids) elProfileSubids.textContent = data.stats.total_subids ?? '0';
            } else {
                // Si la API devuelve success: false
                console.error('Error fetching home stats:', data.message);
                throw new Error(data.message || 'API returned success: false');
            }
        } catch (error) {
            console.error('Error en fetchHomeStats:', error);
            // Poner 'Error' si falla
            document.querySelectorAll('.stat-value, .stat-label').forEach(el => {
                if(el.id && el.id.startsWith('stat-')) el.textContent = 'Error';
            });
        }
    }

    /**
     * ¡FUNCIÓN CORREGIDA! (v1.2)
     * Carga los datos del ranking
     */
    async function fetchRankingData() {
        // Determinar dónde renderizar (en el Home o en la página de Ranking)
        let container, listContainerId, isFullPage;
        
        if (currentModule === 'home') {
            container = document.getElementById('ranking-home-container');
            listContainerId = 'ranking-list-home';
            isFullPage = false;
        } else if (currentModule === 'ranking') {
            container = document.getElementById('ranking-podium-container');
            listContainerId = 'ranking-list-details';
            isFullPage = true;
        } else {
            return; // No estamos en una página que necesite el ranking
        }

        if (!container) {
             // console.warn(`Contenedor de ranking no encontrado (buscando ${currentModule === 'home' ? '#ranking-home-container' : '#ranking-podium-container'})`);
             return;
        }

        try {
            const response = await fetch('api_ranking.php');
            if (!response.ok) throw new Error('No se pudo cargar el ranking.');
            const data = await response.json();
            
            if (data.success && data.ranking) {
                container.innerHTML = ''; // Limpiar skeleton
                const ranking = data.ranking;
                
                if (ranking.length === 0) {
                    container.innerHTML = '<p class="text-muted p-3">Aún no hay datos de ranking.</p>';
                    return;
                }
                
                // --- Lógica para la PÁGINA COMPLETA de Ranking ---
                if (isFullPage) {
                    const podium = ranking.slice(0, 3);
                    let podiumHTML = '<div class="podium">';
                    
                    const rank2 = podium.find(r => r.rank === 2);
                    const rank1 = podium.find(r => r.rank === 1);
                    const rank3 = podium.find(r => r.rank === 3);

                    // Construir podio en orden 2-1-3
                    if (rank2) podiumHTML += buildPodiumCard(rank2, 'rank-2'); else podiumHTML += '<div class="podium-card rank-2 skeleton-item"></div>';
                    if (rank1) podiumHTML += buildPodiumCard(rank1, 'rank-1'); else podiumHTML += '<div class="podium-card rank-1 skeleton-item"></div>';
                    if (rank3) podiumHTML += buildPodiumCard(rank3, 'rank-3'); else podiumHTML += '<div class="podium-card rank-3 skeleton-item"></div>';
                    podiumHTML += '</div>';
                    
                    const list = ranking.slice(3); // El resto
                    let listHTML = `<div class="bento-box mt-4"><div class="box-header">Ranking General (4-${ranking.length})</div><ul class="ranking-list" id="${listContainerId}">`;
                    if (list.length > 0) {
                        list.forEach(user => listHTML += buildRankingRow(user));
                    } else {
                        listHTML += '<li class="text-muted p-3">No hay más usuarios en el ranking.</li>';
                    }
                    listHTML += '</ul></div>';
                    container.innerHTML = podiumHTML + listHTML;
                
                // --- Lógica para el WIDGET del Home ---
                } else {
                    const top3 = ranking.slice(0, 3); // Solo Top 3 para el home
                    let listHTML = `<ul class="ranking-list" id="${listContainerId}">`;
                    if (top3.length > 0) {
                        top3.forEach(user => listHTML += buildRankingRow(user));
                    } else {
                        listHTML += '<li class="text-muted p-3">Aún no hay ranking.</li>';
                    }
                    listHTML += '</ul>';
                    container.innerHTML = listHTML;
                }

            } else {
                throw new Error(data.message || 'Error al cargar ranking.');
            }
        } catch (error) {
            console.error('Error fetching ranking data:', error);
            container.innerHTML = `<p class="text-danger">${error.message}</p>`;
        }
    }
    
    function buildPodiumCard(user, rankClass) {
        return `
            <div class="podium-card ${rankClass}">
                <div class="podium-rank">${user.rank}</div>
                <img class="podium-avatar" src="${user.avatar_url}" alt="avatar">
                <div class="podium-name">${escapeHTML(user.username)}</div>
                <div class="podium-score">${user.count} <span class="podium-label">aportes</span></div>
            </div>`;
    }
    
    function buildRankingRow(user) {
        return `
            <li class="ranking-item">
                <span class="rank-pos">${user.rank}.</span>
                <img class="rank-avatar" src="${user.avatar_url}" alt="avatar">
                <span class="rank-name">${escapeHTML(user.username)}</span>
                <span class="rank-score">${user.count}</span>
            </li>`;
    }
    
    async function getRatings(subid, ratingSection) {
        try {
            const response = await fetch(`api_rate.php?subid=${encodeURIComponent(subid)}`);
            if (!response.ok) return;
            const data = await response.json();
            
            if (data.success && data.ratings) {
                const posEl = ratingSection.querySelector('.positive-count');
                const negEl = ratingSection.querySelector('.negative-count');
                if(posEl) posEl.textContent = data.ratings.positive;
                if(negEl) negEl.textContent = data.ratings.negative;
            }
        } catch (error) {
            console.error('Error fetching ratings:', error);
        }
    }

    
    // --- 6. FUNCIONES UTILITARIAS ---
    
    function showToast(message, type = 'info', duration = 4000) {
        if (!toastContainer) { console.warn("Toast container not found!"); return; }
        
        const toastId = 'toast-' + Date.now();
        let iconClass = 'bi-info-circle-fill';
        let bgClass = 'bg-primary';
        
        if (type === 'success') {
            iconClass = 'bi-check-circle-fill';
            bgClass = 'bg-success';
        } else if (type === 'danger') {
            iconClass = 'bi-exclamation-triangle-fill';
            bgClass = 'bg-danger';
        } else if (type === 'warning') {
            iconClass = 'bi-exclamation-triangle-fill';
            bgClass = 'bg-warning text-dark';
        }
        
        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${duration}">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi ${iconClass} me-2"></i>
                        ${escapeHTML(message)}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>`;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = document.getElementById(toastId);
        if (!toastElement) return;
        
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
    }

    function copyJumper(text, buttonElement) {
        if (!navigator.clipboard) {
            showToast("Tu navegador no soporta la copia al portapapeles.", 'warning');
            return;
        }
        navigator.clipboard.writeText(text).then(() => {
            const originalHtml = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="bi bi-check-lg"></i> Copiado';
            buttonElement.disabled = true;
            showToast('¡Enlace copiado!', 'success');
            
            setTimeout(() => { 
                if (document.body.contains(buttonElement)) { 
                    buttonElement.innerHTML = originalHtml; 
                    buttonElement.disabled = false; 
                } 
            }, 2000);
        }).catch(err => {
            console.error('Error al copiar:', err);
            showToast('Error al copiar el enlace.', 'danger');
        });
    }

    function escapeHTML(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, function(m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[m];
        });
    }
    

    // --- 7. INICIALIZACIÓN ---
    
    // Cargar tema guardado
    const savedTheme = localStorage.getItem('theme') || 'dark';
    setTheme(savedTheme);
    
    // Cargar módulo inicial (basado en la URL)
    const initialParams = new URLSearchParams(window.location.search);
    const initialModule = initialParams.get('module') || 'home';
    loadModuleContent(initialModule, false);
    
    // Iniciar temporizador de inactividad
    resetInactivityTimer();
});