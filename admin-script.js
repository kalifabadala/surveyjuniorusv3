document.addEventListener('DOMContentLoaded', () => {

    // --- LÓGICA DEL GRÁFICO (SOLO PARA DASHBOARD) ---
    // Busca 'admin-chart' para saber si estamos en el dashboard
    const ctx = document.getElementById('admin-chart');
    if (ctx) {
        let adminChart;

        async function fetchChartData() {
            try {
                const response = await fetch('api_admin_stats.php');
                if (!response.ok) {
                    if (response.status === 401) {
                        alert('Sesión expirada. Redirigiendo a login.');
                        window.location.href = 'login.php';
                        return;
                    }
                    throw new Error('No se pudo obtener los datos del admin (error ' + response.status + ')');
                }
                const data = await response.json();
                
                if (data.success) {
                    // Actualizar las tarjetas de estadísticas
                    document.getElementById('stat-total-users').textContent = data.stats.totalUsers;
                    document.getElementById('stat-online-users').textContent = data.stats.onlineUsers;
                    
                    // Sumar los jumpers y logins de los últimos 7 días
                    const totalJumpers7d = data.chart_data.jumpers.reduce((a, b) => a + b, 0);
                    const totalLogins7d = data.chart_data.logins.reduce((a, b) => a + b, 0);
                    document.getElementById('stat-total-jumpers').textContent = totalJumpers7d;
                    document.getElementById('stat-total-logins').textContent = totalLogins7d;

                    // Crear el gráfico
                    const chartData = {
                        labels: data.chart_data.labels,
                        datasets: [
                            {
                                label: 'Jumpers Generados',
                                data: data.chart_data.jumpers,
                                borderColor: 'var(--brand-green)',
                                backgroundColor: 'rgba(48, 232, 191, 0.1)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 2
                            },
                            {
                                label: 'Inicios de Sesión',
                                data: data.chart_data.logins,
                                borderColor: 'var(--brand-blue)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 2
                            }
                        ]
                    };

                    const chartConfig = {
                        type: 'line',
                        data: chartData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    labels: { color: 'var(--text-muted)' }
                                }
                            },
                            scales: {
                                x: {
                                    ticks: { color: 'var(--text-muted)' },
                                    grid: { color: 'var(--border-color)' }
                                },
                                y: {
                                    ticks: { color: 'var(--text-muted)' },
                                    grid: { color: 'var(--border-color)' },
                                    beginAtZero: true
                                }
                            }
                        }
                    };
                    
                    if(ctx) {
                        adminChart = new Chart(ctx, chartConfig);
                    }
                }
            } catch (error) {
                console.error('Error al cargar datos del dashboard:', error);
                if(ctx) {
                    ctx.parentElement.innerHTML = '<div class="alert alert-danger">Error al cargar el gráfico.</div>';
                }
            }
        }
        
        fetchChartData();
        
        // --- LÓGICA DE ACCIONES (API) DEL DASHBOARD ---
        // (Formularios de Mantenimiento, Purgar, etc.)
        const maintenanceForm = document.getElementById('maintenance-form');
        const purgeCacheForm = document.getElementById('purge-cache-form');
        const clearLogsForm = document.getElementById('clear-logs-form');
        const forceLogoutForm = document.getElementById('force-logout-form');

        if(maintenanceForm) maintenanceForm.addEventListener('submit', handleAdminAction);
        if(purgeCacheForm) purgeCacheForm.addEventListener('submit', handleAdminAction);
        if(clearLogsForm) clearLogsForm.addEventListener('submit', handleAdminAction);
        if(forceLogoutForm) forceLogoutForm.addEventListener('submit', handleAdminAction);
        
        async function handleAdminAction(e) {
            e.preventDefault();
            const form = e.target;
            const action = form.querySelector('input[name="action"]').value;
            let value = null;
            if (form.querySelector('input[name="value"]')) {
                value = form.querySelector('input[name="value"]').value;
            }
            
            const button = form.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
            button.disabled = true;

            try {
                const response = await fetch('api_admin_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ action: action, value: value === 'on' ? true : (value === 'off' ? false : null) })
                });
                
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Error desconocido');
                }
                
                // Éxito, recargar la página para ver los cambios
                alert('Acción exitosa: ' + data.message);
                window.location.reload();
                
            } catch (error) {
                console.error('Error en handleAdminAction:', error);
                alert('Error: ' + error.message);
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }
    } // Fin del script de dashboard
    
    // --- LÓGICA DEL TEMA (Claro/Oscuro) ---
    // Esto se ejecuta en todas las secciones
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    const currentTheme = localStorage.getItem('theme');
    const body = document.body;

    function setTheme(theme) {
        if (theme === 'light') {
            body.setAttribute('data-theme', 'light');
            if(themeToggleBtn) themeToggleBtn.innerHTML = '<i class="bi bi-moon-fill"></i>';
        } else {
            body.removeAttribute('data-theme');
            if(themeToggleBtn) themeToggleBtn.innerHTML = '<i class="bi bi-sun-fill"></i>';
        }
    }

    // Aplicar tema al cargar
    if (currentTheme) {
        setTheme(currentTheme);
    } else {
        setTheme('dark'); // Por defecto
    }

    // Evento de clic
    if(themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            let theme = 'dark';
            // Si no tiene 'data-theme', es oscuro, entonces queremos cambiar a claro
            if (!body.hasAttribute('data-theme')) {
                theme = 'light';
            }
            
            if (theme === 'light') {
                localStorage.setItem('theme', 'light');
                setTheme('light');
            } else {
                localStorage.setItem('theme', 'dark');
                setTheme('dark');
            }
        });
    }

});