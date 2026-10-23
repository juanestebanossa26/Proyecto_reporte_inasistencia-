<?php
// models/AuditoriaModelo.php — Servicio centralizado de auditoría y bitácora
declare(strict_types=1);

require_once __DIR__ . '/../config/conexion.php';

class AuditoriaModelo
{
    private static ?string $archivoLog = null;

    private static function obtenerRutaLog(): string
    {
        if (self::$archivoLog === null) {
            $dir = defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : __DIR__ . '/../storage/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            self::$archivoLog = $dir . '/auditoria_fichas.log';
        }
        return self::$archivoLog;
    }

    /**
     * Registra un evento de auditoría tanto en la tabla `auditoria_fichas` como en el archivo de log técnico.
     *
     * @param string $accion Código de acción (ej. 'FINALIZAR', 'ARCHIVAR', 'REABRIR', 'INTENTO_ELIMINACION', 'ELIMINACION', 'CANCELACION_HORARIOS')
     * @param int|null $idFicha ID de la ficha (si existe)
     * @param string|null $numeroFicha Número de la ficha
     * @param int|null $idUsuario ID del usuario que ejecuta la acción
     * @param string|null $estadoAnterior
     * @param string|null $estadoNuevo
     * @param string|null $motivo Motivo justificado del evento
     * @param string $resultado 'EXITOSO', 'RECHAZADO', 'ERROR'
     * @param array|null $detalles Arreglo asociativo con métricas o metadatos técnicos (sin datos médicos ni passwords)
     * @param string|null $correlacionId ID único de correlación para transacciones complejas
     * @return int ID del registro de auditoría insertado
     */
    public function registrar(
        string $accion,
        ?int $idFicha = null,
        ?string $numeroFicha = null,
        ?int $idUsuario = null,
        ?string $estadoAnterior = null,
        ?string $estadoNuevo = null,
        ?string $motivo = null,
        string $resultado = 'EXITOSO',
        ?array $detalles = null,
        ?string $correlacionId = null
    ): int {
        $db = Conexion::obtener();

        if ($correlacionId === null || $correlacionId === '') {
            $correlacionId = 'corr_' . bin2hex(random_bytes(8));
        }

        $ipOrigen = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $detallesJson = !empty($detalles) ? json_encode($detalles, JSON_UNESCAPED_UNICODE) : null;

        $idAuditoria = 0;
        try {
            $sql = "INSERT INTO auditoria_fichas 
                        (id_ficha, numero_ficha, id_usuario, accion, estado_anterior, estado_nuevo, motivo, resultado, detalles, correlacion_id, ip_origen, created_at)
                    VALUES 
                        (:id_ficha, :num_ficha, :id_usr, :accion, :est_ant, :est_nvo, :motivo, :res, :detalles, :corr, :ip, NOW())";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_ficha',   $idFicha > 0 ? $idFicha : null, PDO::PARAM_INT);
            $stmt->bindValue(':num_ficha',  $numeroFicha);
            $stmt->bindValue(':id_usr',     $idUsuario > 0 ? $idUsuario : null, PDO::PARAM_INT);
            $stmt->bindValue(':accion',     strtoupper($accion));
            $stmt->bindValue(':est_ant',    $estadoAnterior);
            $stmt->bindValue(':est_nvo',    $estadoNuevo);
            $stmt->bindValue(':motivo',     $motivo);
            $stmt->bindValue(':res',        in_array($resultado, ['EXITOSO', 'RECHAZADO', 'ERROR'], true) ? $resultado : 'EXITOSO');
            $stmt->bindValue(':detalles',   $detallesJson);
            $stmt->bindValue(':corr',       $correlacionId);
            $stmt->bindValue(':ip',         $ipOrigen);
            $stmt->execute();

            $idAuditoria = (int)$db->lastInsertId();
        } catch (\Throwable $e) {
            error_log("Error al insertar en auditoria_fichas: " . $e->getMessage());
        }

        // Registro estructurado en log file
        try {
            $rutaLog = self::obtenerRutaLog();
            $fecha = date('Y-m-d H:i:s');
            $linea = sprintf(
                "[%s] [%s] [%s] Ficha: %s (ID: %s) | Usr: %s | Ant: %s -> Nvo: %s | Corr: %s | Motivo: %s | Datos: %s\n",
                $fecha,
                $resultado,
                strtoupper($accion),
                $numeroFicha ?? 'N/A',
                $idFicha ?? 'N/A',
                $idUsuario ?? 'SISTEMA',
                $estadoAnterior ?? 'N/A',
                $estadoNuevo ?? 'N/A',
                $correlacionId,
                $motivo ?? 'Sin motivo registrado',
                $detallesJson ?? '{}'
            );
            @file_put_contents($rutaLog, $linea, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            error_log("Error al escribir log de auditoría: " . $e->getMessage());
        }

        return $idAuditoria;
    }

    /**
     * Consulta el historial de auditoría de una ficha específica.
     */
    public function listarPorFicha(int $idFicha): array
    {
        $sql = "SELECT af.*, u.nombre_completo AS usuario_nombre, u.usuario AS usuario_login
                FROM auditoria_fichas af
                LEFT JOIN usuarios u ON u.id_usuario = af.id_usuario
                WHERE af.id_ficha = :id
                ORDER BY af.created_at DESC, af.id_auditoria DESC";
        
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute([':id' => $idFicha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
