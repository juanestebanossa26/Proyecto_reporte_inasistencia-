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