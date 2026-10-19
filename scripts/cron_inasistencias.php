<?php
/**
 * Script de Automatización (CRON): Sincronización Automática de Inasistencias
 * Sistema de Control de Ingreso de Aprendices · SENA
 *
 * Propósito:
 * Recorre todos los horarios y bloques académicos de hoy y días anteriores.
 * Si un bloque de clase ya terminó (hora_salida_programada <= hora actual)
 * y un aprendiz activo no registra asistencia ni retardo en ese bloque,
 * se le registra automáticamente la falta ('inasistencia').
 *
 * Modos de ejecución:
 * 1. CLI (Consola / Cron / Programador de Tareas de Windows):
 *    php cron_inasistencias.php
 *
 * 2. HTTP (Webhook / Tarea web remota con token de seguridad):
 *    http://localhost/proyecto_asistencias/scripts/cron_inasistencias.php?token=SENA_CRON_2026
 */

declare(strict_types=1);

// Permitir ejecución desde CLI o mediante llamada web autorizada con token
$esCli = (PHP_SAPI === 'cli');
$tokenValido = 'SENA_CRON_2026';

if (!$esCli) {
    header('Content-Type: application/json; charset=utf-8');
    $tokenRecibido = $_GET['token'] ?? '';
    if ($tokenRecibido !== $tokenValido) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado: Token inválido.']);
        exit;
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/AsistenciaModelo.php';

try {
    $horaInicio = microtime(true);
    $fechaActual = date('Y-m-d H:i:s');

    $asistenciaModelo = new AsistenciaModelo();
    $totalRegistradas = $asistenciaModelo->sincronizarInasistenciasGlobal();

    $tiempoEjecucion = round((microtime(true) - $horaInicio) * 1000, 2);

    $respuesta = [
        'success'           => true,
        'fecha_ejecucion'   => $fechaActual,
        'nuevas_faltas'     => $totalRegistradas,
        'tiempo_ms'         => $tiempoEjecucion,
        'mensaje'           => "Proceso completado con éxito. Se marcaron {$totalRegistradas} inasistencias en bloques finalizados.",
    ];

    if ($esCli) {
        echo "[{$fechaActual}] {$respuesta['mensaje']} (Tiempo: {$tiempoEjecucion} ms)\n";
    } else {
        echo json_encode($respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    $errorMsg = "[ERROR " . date('Y-m-d H:i:s') . "] Falló la sincronización de inasistencias: " . $e->getMessage();
    error_log($errorMsg);

    if ($esCli) {
        fwrite(STDERR, $errorMsg . "\n");
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
