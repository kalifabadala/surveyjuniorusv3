<?php
// test_telegram.php (v3 - Corregido para Webhook de Make.com)

// Establecer la visualización de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Prueba de Notificación de Webhook (Make.com)</h2>";

require_once 'config.php';
require_once 'functions.php'; // Cargar para la función sendTelegramNotification

// 1. Comprobar si la función existe
if (function_exists('sendTelegramNotification')) {
    echo "<p>✅ Éxito: La función <code>sendTelegramNotification()</code> existe en functions.php (v6.0).</p>";
} else {
    echo "<p style='color:red;'>❌ ERROR FATAL: La función <code>sendTelegramNotification()</code> no se encontró. Asegúrate de haber subido la versión <strong>v6.0</strong> de <strong>functions.php</strong>.</p>";
    exit;
}

// 2. Comprobar si la constante del Webhook está cargada y NO es un placeholder
if (defined('MAKE_WEBHOOK_URL') && 
    MAKE_WEBHOOK_URL !== 'AQUI_VA_TU_URL_DE_MAKE.COM' && !empty(MAKE_WEBHOOK_URL)) {
    
    echo "<p>✅ Éxito: La constante <code>MAKE_WEBHOOK_URL</code> está cargada desde config.php.</p>";
    echo "<p><b>URL del Webhook:</b> <code>" . htmlspecialchars(substr(MAKE_WEBHOOK_URL, 0, 25)) . "...</code></p>";
    
} else {
    echo "<p style='color:red;'>❌ ERROR FATAL: Tu constante <code>MAKE_WEBHOOK_URL</code> en <strong>config.php</strong> está vacía o sigue siendo el valor de ejemplo ('AQUI_VA_TU_URL_DE_MAKE.COM').</p>";
    echo "<p>Por favor, edita tu <strong>config.php</strong> en el servidor y reemplaza ese valor con tu URL real de hook.make.com.</p>";
    exit;
}

// 3. Si todo está bien, intentar enviar el mensaje
try {
    $message = "<b>¡HOLA! 👋</b>\n\nEste es un mensaje de prueba desde <code>test_telegram.php (v3)</code>.\n\nSi ves esto, ¡el webhook de Make.com funciona!";
    echo "<hr>";
    echo "<p>Enviando este mensaje a tu webhook:</p>";
    echo "<pre>" . $message . "</pre>";
    
    // Llamar a la función real de functions.php
    sendTelegramNotification($message);
    
    echo "<hr>";
    echo "<h3 style='color:green;'>✅ ¡Prueba completada!</h3>";
    echo "<p>El script ha terminado. Por favor, revisa tu grupo de Telegram para ver si recibiste el mensaje '¡HOLA! 👋'.</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ ERROR DE PHP: Se produjo una excepción: " . $e->getMessage() . "</p>";
}
?>