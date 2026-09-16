<?php
// Acceso a datos de la tabla excusas_medicas.

class ExcusaModelo
{
    // Excusas enviadas por un aprendiz (para su propia vista de "Excusas médicas").
    public function listarPorAprendiz(int $idUsuario): array
    {
        $sql = "SELECT asistencias.fecha,
                       excusas_medicas.estado_excusa,
                       excusas_medicas.motivo
                FROM asistencias
                JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz
                JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                WHERE aprendices.id_usuario = :id_usuario";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Guarda la excusa (el archivo físico ya debe haberse movido antes de llamar esto).
    public function guardar(int $idAsistencia, string $rutaRelativaArchivo, string $motivo): void
    {
        $sql = "INSERT INTO excusas_medicas (id_asistencia, archivo_adjunto, motivo)
                VALUES (:id, :ruta, :motivo)";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id', $idAsistencia);
        $stmt->bindParam(':ruta', $rutaRelativaArchivo);
        $stmt->bindParam(':motivo', $motivo);
        $stmt->execute();
    }

    // Excusas de los aprendices de las fichas a cargo de un instructor.
    public function listarPorInstructor(int $idInstructor): array
    {
        $sql = "SELECT
                    usuarios.nombre_completo,
                    usuarios.apellido,
                    fichas.nombre_programa,
                    fichas.numero_ficha,
                    excusas_medicas.motivo,
                    asistencias.fecha,
                    asistencias.id_asistencia,
                    excusas_medicas.estado_excusa,
                    excusas_medicas.archivo_adjunto
                FROM usuarios
                JOIN aprendices ON aprendices.id_usuario = usuarios.id_usuario
                JOIN fichas ON aprendices.id_ficha = fichas.id_ficha
                JOIN asistencias ON asistencias.id_aprendiz = aprendices.id_aprendiz
                JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                WHERE fichas.id_instructor = :id_usuario";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idInstructor, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function contarPendientesPorInstructor(int $idInstructor): int
    {
        $sql = "SELECT COUNT(excusas_medicas.id_excusa)
                FROM usuarios
                JOIN aprendices ON aprendices.id_usuario = usuarios.id_usuario
                JOIN fichas ON aprendices.id_ficha = fichas.id_ficha
                JOIN asistencias ON asistencias.id_aprendiz = aprendices.id_aprendiz
                JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                WHERE fichas.id_instructor = :id_usuario
                  AND excusas_medicas.estado_excusa = 'pendiente'";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idInstructor, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    // Al aprobar, además de marcar la excusa como aprobada, la asistencia
    // asociada pasa a contar como 'asistencia' en vez de 'inasistencia'.
    public function aprobar(int $idAsistencia): void
    {
        $conexion = Conexion::obtener();

        $stmt = $conexion->prepare("UPDATE asistencias SET estado = 'asistencia' WHERE id_asistencia = :id");
        $stmt->bindParam(':id', $idAsistencia, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $conexion->prepare("UPDATE excusas_medicas SET estado_excusa = 'aprobada' WHERE id_asistencia = :id");
        $stmt->bindParam(':id', $idAsistencia, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function rechazar(int $idAsistencia): void
    {
        $stmt = Conexion::obtener()->prepare("UPDATE excusas_medicas SET estado_excusa = 'rechazada' WHERE id_asistencia = :id");
        $stmt->bindParam(':id', $idAsistencia, PDO::PARAM_INT);
        $stmt->execute();
    }
}
