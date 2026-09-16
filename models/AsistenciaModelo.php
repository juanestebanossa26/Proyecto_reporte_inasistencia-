<?php
// Acceso a datos de la tabla asistencias, desde el punto de vista del
// aprendiz (su propio historial).

class AsistenciaModelo
{
    // Historial completo del aprendiz, con el estado de la excusa si la tiene
    // (LEFT JOIN: también trae inasistencias sin excusa asociada).
    public function listarPorAprendiz(int $idUsuario): array
    {
        $sql = "SELECT asistencias.*,
                       excusas_medicas.estado_excusa
                FROM asistencias
                JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz
                LEFT JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                WHERE aprendices.id_usuario = :id_usuario";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function contarPorEstado(int $idUsuario, string $estado): int
    {
        $sql = "SELECT COUNT(asistencias.id_asistencia)
                FROM asistencias
                JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz
                WHERE aprendices.id_usuario = :id_usuario AND estado = :estado";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindParam(':estado', $estado);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function contarExcusasPendientes(int $idUsuario): int
    {
        $sql = "SELECT COUNT(excusas_medicas.id_excusa)
                FROM asistencias
                JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz
                JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                WHERE aprendices.id_usuario = :id_usuario AND estado_excusa = 'Pendiente'";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    // Fechas de inasistencia que todavía no tienen ninguna excusa anexada;
    // se usan para poblar el selector del formulario "Anexar excusa".
    public function listarFaltasSinExcusa(int $idUsuario): array
    {
        $sql = "SELECT asistencias.fecha, asistencias.id_asistencia
                FROM asistencias
                JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz
                LEFT JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                WHERE aprendices.id_usuario = :id_usuario
                  AND asistencias.estado = 'inasistencia'
                  AND excusas_medicas.id_asistencia IS NULL";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
