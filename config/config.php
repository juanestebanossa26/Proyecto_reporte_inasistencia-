<?php

date_default_timezone_set('America/Bogota');

// Cargar variables desde el archivo .env si existe
$env = file_exists(__DIR__ . '/../.env') ? parse_ini_file(__DIR__ . '/../.env') : [];

// Configuración de la base de datos
define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
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
