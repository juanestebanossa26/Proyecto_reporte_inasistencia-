<?php

date_default_timezone_set('America/Bogota');

// Cargar variables desde el archivo .env si existe de forma robusta
$env = [];
$rutaEnv = __DIR__ . '/../.env';
if (file_exists($rutaEnv)) {
    $envParsed = @parse_ini_file($rutaEnv);
    if ($envParsed !== false) {
        $env = $envParsed;
    } else {
        // Fallback manual línea por línea si .env contiene caracteres especiales (~, #, etc.) sin comillas
        $lineas = file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#') || str_starts_with($linea, ';')) {
                continue;
            }
            if (str_contains($linea, '=')) {
                [$clave, $valor] = explode('=', $linea, 2);
                $clave = trim($clave);
                $valor = trim($valor);
                if ((str_starts_with($valor, '"') && str_ends_with($valor, '"')) ||
                    (str_starts_with($valor, "'") && str_ends_with($valor, "'"))) {
                    $valor = substr($valor, 1, -1);
                }
                $env[$clave] = $valor;
            }
        }
    }
}

// Configuración de la base de datos
define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
define('DB_PORT', (int) ($env['DB_PORT'] ?? 3306));
define('DB_NAME', $env['DB_NAME'] ?? 'control_asistencia');
define('DB_USER', $env['DB_USER'] ?? 'root');
define('DB_PASS', $env['DB_PASS'] ?? '');
define('DB_CHARSET', $env['DB_CHARSET'] ?? 'utf8mb4');

// Modo depuración (true para ver errores en desarrollo, false en producción)
define('MODO_DEBUG', filter_var($env['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN));

// Configuración de Servidor de Correo (SMTP)
define('SMTP_HOST', $env['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', (int) ($env['SMTP_PORT'] ?? 587));
define('SMTP_USER', $env['SMTP_USER'] ?? '');
define('SMTP_PASS', $env['SMTP_PASS'] ?? '');
define('SMTP_SECURE', $env['SMTP_SECURE'] ?? 'tls');
define('MAIL_FROM_ADDRESS', !empty($env['MAIL_FROM_ADDRESS']) ? $env['MAIL_FROM_ADDRESS'] : ($env['SMTP_USER'] ?? 'no-reply@sena.edu.co'));
define('MAIL_FROM_NAME', !empty($env['MAIL_FROM_NAME']) ? $env['MAIL_FROM_NAME'] : 'Control de Asistencias SENA');

// Token de seguridad para la ejecución remota de tareas programadas (CRON)
define('CRON_SECRET_TOKEN', $env['CRON_SECRET_TOKEN'] ?? 'SENA_CRON_SECURE_2026');


// Límite de tamaño máximo para importación de horarios Excel
define('LIMITE_EXCEL_HORARIOS_MB', 5);
define('LIMITE_EXCEL_HORARIOS_BYTES', LIMITE_EXCEL_HORARIOS_MB * 1024 * 1024);
