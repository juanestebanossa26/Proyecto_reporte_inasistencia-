<?php
/**
 * Script de Automatización (CRON): Sincronización Programada de Inasistencias
 * Sistema de Control de Ingreso y Asistencia RFID · SENA
 *
 * Características:
 * - Exclusión mutua (Mutex) para impedir ejecuciones simultáneas.
 * - Idempotencia total: Inserción atómica sin duplicar asistencias.
 * - Exclusión de días y bloques festivos (dias_festivos).
 * - Bitácora técnica en storage/logs/sincronizacion_cron.log sin datos sensibles.
 * - Soporte para ejecución en consola (CLI) o llamada remota protegida.
 *
 * Uso CLI:
 *   php cron_inasistencias.php
 *   php cron_inasistencias.php --force
 *   php cron_inasistencias.php --dry-run
 */

declare(strict_types=1);

$esCli = (PHP_SAPI === 'cli');

// Validación de acceso seguro en llamadas HTTP
if (!$esCli) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Configuración de token seguro (puede definirse en config o usar por defecto)
    $tokenConfigurado = defined('CRON_SECRET_TOKEN') ? CRON_SECRET_TOKEN : 'SENA_CRON_SECURE_2026';
    $tokenRecibido = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

    // Permitir solo desde localhost o con token válido
    $esLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

    if (!$esLocal && (!hash_equals($tokenConfigurado, $tokenRecibido))) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error'   => 'Acceso denegado: Se requiere autenticación autorizada para ejecutar la tarea.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/AsistenciaModelo.php';

// Parsear banderas CLI
$forzarEjecucion = false;
$modoSimulacion  = false;

if ($esCli) {
    global $argv;
    if (isset($argv) && is_array($argv)) {
        if (in_array('--force', $argv, true)) {
            $forzarEjecucion = true;
        }
        if (in_array('--dry-run', $argv, true)) {
            $modoSimulacion = true;
        }
    }
}

// Ruta del archivo de log
$directorioLogs = __DIR__ . '/../storage/logs';
if (!is_dir($directorioLogs)) {
    @mkdir($directorioLogs, 0755, true);
}
$archivoLog = $directorioLogs . '/sincronizacion_cron.log';

$timestampInicio = date('Y-m-d H:i:s');
$microInicio = microtime(true);

function registrarLogTecnico(string $archivoLog, string $nivel, string $mensaje, array $contexto = []): void
{
    $fecha = date('Y-m-d H:i:s');
    $ctxJson = !empty($contexto) ? ' | Datos: ' . json_encode($contexto, JSON_UNESCAPED_UNICODE) : '';
    $linea = "[{$fecha}] [{$nivel}] {$mensaje}{$ctxJson}\n";
    @file_put_contents($archivoLog, $linea, FILE_APPEND | LOCK_EX);
}

try {
    $asistenciaModelo = new AsistenciaModelo();

    if ($modoSimulacion) {
        $resultado = [
            'exito'         => true,
            'bloqueado'     => false,
            'nuevas_faltas' => 0,
            'duracion_ms'   => round((microtime(true) - $microInicio) * 1000, 2),
            'mensaje'       => 'Modo simulación (--dry-run): No se realizaron modificaciones en la base de datos.'
        ];
        registrarLogTecnico($archivoLog, 'INFO', 'Ejecución en modo simulación (dry-run)');
    } else {
        $resultado = $asistenciaModelo->sincronizarInasistenciasGlobal($forzarEjecucion);
    }

    $duracionTotalMs = round((microtime(true) - $microInicio) * 1000, 2);

    if (!empty($resultado['bloqueado'])) {
        registrarLogTecnico($archivoLog, 'WARNING', 'Ejecución omitida por bloqueo concurrente activo.', [
            'duracion_ms' => $duracionTotalMs,
            'modo'        => $esCli ? 'CLI' : 'HTTP',
        ]);

        if ($esCli) {
            echo "[{$timestampInicio}] [AVISO] Ya existe otra sincronización activa. Proceso finalizado sin cambios. ({$duracionTotalMs} ms)\n";
            exit(0);
        } else {
            http_response_code(429);
            echo json_encode([
                'success'     => false,
                'bloqueado'   => true,
                'mensaje'     => 'Tarea omitida: Ya existe otro proceso de sincronización ejecutándose.',
                'duracion_ms' => $duracionTotalMs
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if (!empty($resultado['exito'])) {
        registrarLogTecnico($archivoLog, 'INFO', 'Sincronización completada con éxito.', [
            'sesiones_evaluadas'         => $resultado['sesiones_evaluadas'] ?? 0,
            'nuevas_faltas'              => $resultado['nuevas_faltas'] ?? 0,
            'omitidos_festivos'          => $resultado['omitidos_festivos'] ?? 0,
            'omitidos_asistencia_previa' => $resultado['omitidos_asistencia_previa'] ?? 0,
            'duplicados_evitados'        => $resultado['duplicados_evitados'] ?? 0,
            'duracion_ms'                => $duracionTotalMs,
            'modo'                       => $esCli ? 'CLI' : 'HTTP',
        ]);

        $salida = [
            'success'                    => true,
            'fecha'                      => $timestampInicio,
            'sesiones_evaluadas'         => $resultado['sesiones_evaluadas'] ?? 0,
            'nuevas_faltas'              => $resultado['nuevas_faltas'] ?? 0,
            'omitidos_festivos'          => $resultado['omitidos_festivos'] ?? 0,
            'omitidos_asistencia_previa' => $resultado['omitidos_asistencia_previa'] ?? 0,
            'duplicados_evitados'        => $resultado['duplicados_evitados'] ?? 0,
            'duracion_ms'                => $duracionTotalMs,
            'mensaje'                    => $resultado['mensaje'] ?? 'Sincronización finalizada exitosamente.',
        ];

        if ($esCli) {
            echo "[{$timestampInicio}] [ÉXITO] {$salida['mensaje']} (Duración: {$duracionTotalMs} ms)\n";
            exit(0);
        } else {
            echo json_encode($salida, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $errorMsg = $resultado['mensaje'] ?? 'Error desconocido durante la sincronización.';
        registrarLogTecnico($archivoLog, 'ERROR', $errorMsg, [
            'duracion_ms' => $duracionTotalMs,
            'error_raw'   => $resultado['error'] ?? null
        ]);

        if ($esCli) {
            fwrite(STDERR, "[{$timestampInicio}] [ERROR] {$errorMsg}\n");
            exit(1);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => $errorMsg
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

} catch (Throwable $e) {
    $duracionErrorMs = round((microtime(true) - $microInicio) * 1000, 2);
    $mensajeError = 'Excepción no controlada durante la sincronización: ' . $e->getMessage();
    
    registrarLogTecnico($archivoLog, 'CRITICAL', $mensajeError, [
        'archivo'     => basename($e->getFile()),
        'linea'       => $e->getLine(),
        'duracion_ms' => $duracionErrorMs
    ]);

    if ($esCli) {
        fwrite(STDERR, "[{$timestampInicio}] [CRITICAL] {$mensajeError}\n");
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'Error interno al ejecutar la tarea de sincronización.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
