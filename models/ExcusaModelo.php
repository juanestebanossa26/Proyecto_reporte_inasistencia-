<?php
// Acceso a datos de las excusas médicas y sus revisiones.

class ExcusaModelo
{
    // Excusas enviadas por un aprendiz (para su propia vista).
    public function listarPorAprendiz(int $idUsuario): array
    {
        $sql = "SELECT asistencias.fecha,
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

    // Guarda la excusa y la reparte a todos los instructores de la ficha del aprendiz
    public function guardar(int $idAsistencia, string $rutaRelativaArchivo, string $motivo): void
    {
        $conexion = Conexion::obtener();
        
        try {
            // Iniciamos transacción para que, si algo falla, no se guarde a medias
            $conexion->beginTransaction();

            // 1. Obtener el id_aprendiz y su id_ficha a partir de la asistencia
            $stmtInfo = $conexion->prepare("
                SELECT ap.id_aprendiz, ap.id_ficha 
                FROM asistencias a
                JOIN aprendices ap ON a.id_aprendiz = ap.id_aprendiz
                WHERE a.id_asistencia = :id_asistencia
            ");
            $stmtInfo->bindParam(':id_asistencia', $idAsistencia, PDO::PARAM_INT);
            $stmtInfo->execute();
            $infoAprendiz = $stmtInfo->fetch(PDO::FETCH_ASSOC);

            if (!$infoAprendiz) {
                throw new Exception("No se encontró el aprendiz para esta asistencia.");
            }

            $idAprendiz = $infoAprendiz['id_aprendiz'];
            $idFicha    = $infoAprendiz['id_ficha'];

            // 2. Insertar en excusas_medicas
            $stmtExcusa = $conexion->prepare("
                INSERT INTO excusas_medicas (id_asistencia, aprendices_id_aprendiz, archivo_adjunto, motivo, fecha_creacion)
                VALUES (:id_asistencia, :id_aprendiz, :ruta, :motivo, NOW())
            ");
            $stmtExcusa->bindParam(':id_asistencia', $idAsistencia, PDO::PARAM_INT);
            $stmtExcusa->bindParam(':id_aprendiz', $idAprendiz, PDO::PARAM_INT);
            $stmtExcusa->bindParam(':ruta', $rutaRelativaArchivo);
            $stmtExcusa->bindParam(':motivo', $motivo);
            $stmtExcusa->execute();

            // Capturamos el ID de la excusa recién creada
            $idNuevaExcusa = $conexion->lastInsertId();

            // 3. Buscar TODOS los instructores asignados a la ficha de este aprendiz
            $stmtInstructores = $conexion->prepare("
                SELECT id_instructor 
                FROM fichas_has_usuarios 
                WHERE fichas_id_ficha = :id_ficha
            ");
            $stmtInstructores->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
            $stmtInstructores->execute();
            $instructores = $stmtInstructores->fetchAll(PDO::FETCH_ASSOC);

            // 4. Insertar en revision_excusas por cada instructor encontrado
            if (!empty($instructores)) {
                $stmtRevision = $conexion->prepare("
                    INSERT INTO revision_excusas (id_excusa, id_instructor, estado)
                    VALUES (:id_excusa, :id_instructor, 'Pendiente')
                ");
                
                foreach ($instructores as $inst) {
                    $stmtRevision->bindValue(':id_excusa', $idNuevaExcusa, PDO::PARAM_INT);
                    $stmtRevision->bindValue(':id_instructor', $inst['id_instructor'], PDO::PARAM_INT);
                    $stmtRevision->execute();
                }
            }

            // Confirmamos la transacción
            $conexion->commit();

        } catch (Exception $e) {
            $conexion->rollBack();
            error_log('Error al guardar excusa y notificar instructores: ' . $e->getMessage());
            throw $e;
        }
    }

    // Excusas asignadas a un instructor específico a través de la tabla revision_excusas
    public function listarPorInstructor(int $idInstructor): array
    {
        $sql = "SELECT
                    usuarios.nombre_completo,
                    usuarios.apellido,
                    fichas.nombre_programa,
                    fichas.numero_ficha,
                    excusas_medicas.motivo,
                    asistencias.fecha,
                    excusas_medicas.archivo_adjunto,
                    revision_excusas.estado AS estado_excusa,
                    revision_excusas.id_revision,
                    excusas_medicas.id_excusa
                FROM revision_excusas
                JOIN excusas_medicas ON revision_excusas.id_excusa = excusas_medicas.id_excusa
                JOIN asistencias ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                JOIN aprendices ON excusas_medicas.aprendices_id_aprendiz = aprendices.id_aprendiz
                JOIN usuarios ON aprendices.id_usuario = usuarios.id_usuario
                JOIN fichas ON aprendices.id_ficha = fichas.id_ficha
                WHERE revision_excusas.id_instructor = :id_instructor
                ORDER BY excusas_medicas.fecha_creacion DESC";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_instructor', $idInstructor, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Cuenta solo las revisiones pendientes asignadas a este instructor
    public function contarPendientesPorInstructor(int $idInstructor): int
    {
        $sql = "SELECT COUNT(id_revision)
                FROM revision_excusas
                WHERE id_instructor = :id_instructor
                  AND LOWER(estado) = 'pendiente'";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_instructor', $idInstructor, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    // Procesa la decisión del instructor (Aprobar, Rechazar, Devolver) en un solo método
    public function procesarRevision(int $idRevision, string $estado, string $observacion): void
    {
        $conexion = Conexion::obtener();
        
        // 1. Actualizar el estado y guardar la observación de este instructor específico
        $sql = "UPDATE revision_excusas 
                SET estado = :estado, 
                    observaciones = :obs, 
                    fecha_respuesta = NOW() 
                WHERE id_revision = :id_rev";
        
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':obs', $observacion);
        $stmt->bindParam(':id_rev', $idRevision, PDO::PARAM_INT);
        $stmt->execute();

        // 2. Si se Aprueba, cambiar la inasistencia a asistencia en la base de datos principal
        if (strtolower($estado) === 'aprobada') {
            // Buscamos el id_asistencia original a través del id_revision
            $sqlAsistencia = "SELECT em.id_asistencia 
                              FROM revision_excusas re
                              JOIN excusas_medicas em ON re.id_excusa = em.id_excusa
                              WHERE re.id_revision = :id_rev";
            $stmtAsis = $conexion->prepare($sqlAsistencia);
            $stmtAsis->bindParam(':id_rev', $idRevision, PDO::PARAM_INT);
            $stmtAsis->execute();
            $idAsistencia = $stmtAsis->fetchColumn();

            if ($idAsistencia) {
                $stmtUpdate = $conexion->prepare("UPDATE asistencias SET estado = 'asistencia' WHERE id_asistencia = :id_as");
                $stmtUpdate->bindParam(':id_as', $idAsistencia, PDO::PARAM_INT);
                $stmtUpdate->execute();
            }
        }
    }
}