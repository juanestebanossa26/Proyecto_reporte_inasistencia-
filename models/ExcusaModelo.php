<?php
// Acceso a datos de las excusas médicas y sus revisiones.

class ExcusaModelo
{
    // Excusas enviadas por un aprendiz (para su propia vista). Como una
    // misma excusa puede tener varias revisiones (una por instructor de
    // la ficha), aquí se calcula un solo "estado_excusa" resumen:
    // - Si algún instructor la rechazó      -> Rechazada
    // - si no, si algún instructor la devolvió -> Devuelta
    // - si no, si todos la aprobaron         -> Aprobada
    // - si no                                -> Pendiente
    public function listarPorAprendiz(int $idUsuario): array
    {
        $sql = "SELECT
                    excusas_medicas.id_excusa,
                    excusas_medicas.archivo_adjunto,
                    asistencias.fecha,
                    excusas_medicas.motivo,
                    asistencias.id_horario,
                    horarios.hora_entrada_programada,
                    horarios.hora_salida_programada,
                    COALESCE(
                        u_inst.nombre_completo,
                        (SELECT u2.nombre_completo FROM fichas_has_usuarios fhu JOIN usuarios u2 ON u2.id_usuario = fhu.id_instructor WHERE fhu.fichas_id_ficha = aprendices.id_ficha LIMIT 1),
                        'Instructor asignado'
                    ) AS instructor_nombre,
                    COUNT(revision_excusas.id_revision) AS total_revisiones,
                    SUM(revision_excusas.estado = 'Rechazada') AS rechazadas,
                    SUM(revision_excusas.estado = 'Devuelta') AS devueltas,
                    SUM(revision_excusas.estado = 'Aprobada') AS aprobadas,
                    (
                        SELECT GROUP_CONCAT(
                            CONCAT(
                                CASE WHEN u_rev.nombre_completo IS NOT NULL THEN CONCAT(u_rev.nombre_completo, ': ') ELSE '' END,
                                TRIM(re.observaciones)
                            )
                            SEPARATOR ' · '
                        )
                        FROM revision_excusas re
                        LEFT JOIN usuarios u_rev ON u_rev.id_usuario = re.id_instructor
                        WHERE re.id_excusa = excusas_medicas.id_excusa
                          AND re.observaciones IS NOT NULL 
                          AND TRIM(re.observaciones) != ''
                    ) AS observaciones_instructor
                FROM asistencias
                JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz
                JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                LEFT JOIN horarios ON horarios.id_horario = asistencias.id_horario
                LEFT JOIN usuarios u_inst ON u_inst.id_usuario = horarios.id_instructor
                LEFT JOIN revision_excusas ON revision_excusas.id_excusa = excusas_medicas.id_excusa
                WHERE aprendices.id_usuario = :id_usuario
                GROUP BY excusas_medicas.id_excusa, excusas_medicas.archivo_adjunto,
                         asistencias.fecha, excusas_medicas.motivo, asistencias.id_horario,
                         horarios.hora_entrada_programada, horarios.hora_salida_programada,
                         instructor_nombre
                ORDER BY excusas_medicas.fecha_creacion DESC";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll();

        foreach ($filas as &$fila) {
            if ((int) $fila['rechazadas'] > 0) {
                $fila['estado_excusa'] = 'Rechazada';
            } elseif ((int) $fila['devueltas'] > 0) {
                $fila['estado_excusa'] = 'Devuelta';
            } elseif ((int) $fila['total_revisiones'] > 0 && (int) $fila['aprobadas'] === (int) $fila['total_revisiones']) {
                $fila['estado_excusa'] = 'Aprobada';
            } else {
                $fila['estado_excusa'] = 'Pendiente';
            }

            $horaIni = !empty($fila['hora_entrada_programada']) ? substr($fila['hora_entrada_programada'], 0, 5) : '';
            $horaFin = !empty($fila['hora_salida_programada']) ? substr($fila['hora_salida_programada'], 0, 5) : '';
            $fila['bloque_horario'] = ($horaIni && $horaFin) ? "{$horaIni} - {$horaFin}" : 'Jornada asignada';
        }
        unset($fila);

        return $filas;
    }

    // Trae una excusa puntual, verificando que sea del aprendiz que la pide
    // (para que nadie pueda reenviar/editar una excusa ajena adivinando el id).
    public function buscarPorIdYAprendiz(int $idExcusa, int $idUsuario): array|false
    {
        $sql = "SELECT excusas_medicas.id_excusa, excusas_medicas.motivo, excusas_medicas.archivo_adjunto
                FROM excusas_medicas
                JOIN asistencias ON asistencias.id_asistencia = excusas_medicas.id_asistencia
                JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz
                WHERE excusas_medicas.id_excusa = :id_excusa
                  AND aprendices.id_usuario = :id_usuario";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_excusa', $idExcusa, PDO::PARAM_INT);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    // Guarda una excusa para UNA fecha (se mantiene por compatibilidad;
    // internamente usa el mismo camino que guardarVarios()).
    public function guardar(int $idAsistencia, string $rutaRelativaArchivo, string $motivo): void
    {
        $this->guardarVarios([$idAsistencia], $rutaRelativaArchivo, $motivo);
    }

    // Guarda LA MISMA excusa (mismo archivo, mismo motivo) para varias
    // fechas de inasistencia a la vez: una fila en excusas_medicas por
    // cada fecha marcada, todas dentro de una sola transacción.
    public function guardarVarios(array $idsAsistencia, string $rutaRelativaArchivo, string $motivo): void
    {
        $conexion = Conexion::obtener();

        try {
            $conexion->beginTransaction();

            foreach ($idsAsistencia as $idAsistencia) {
                $this->insertarExcusaYNotificar($conexion, (int) $idAsistencia, $rutaRelativaArchivo, $motivo);
            }

            $conexion->commit();
        } catch (Exception $e) {
            $conexion->rollBack();
            error_log('Error al guardar excusa(s) y notificar instructores: ' . $e->getMessage());
            throw $e;
        }
    }

    // Inserta UNA fila de excusas_medicas para una fecha puntual, y crea
    // su revisión pendiente por cada instructor de la ficha. No maneja su
    // Inserta UNA fila de excusas_medicas para una fecha/bloque puntual, y crea
    // su revisión pendiente para el instructor que tenía clase en ese bloque
    // (o para los instructores de la ficha si no había uno asignado en horarios).
    private function insertarExcusaYNotificar(PDO $conexion, int $idAsistencia, string $rutaRelativaArchivo, string $motivo): void
    {
        $stmtInfo = $conexion->prepare("
            SELECT 
                a.id_asistencia,
                a.id_horario,
                ap.id_aprendiz, 
                ap.id_ficha,
                h.id_instructor AS horario_instructor
            FROM asistencias a
            JOIN aprendices ap ON a.id_aprendiz = ap.id_aprendiz
            LEFT JOIN horarios h ON a.id_horario = h.id_horario
            WHERE a.id_asistencia = :id_asistencia
            LIMIT 1
        ");
        $stmtInfo->bindParam(':id_asistencia', $idAsistencia, PDO::PARAM_INT);
        $stmtInfo->execute();
        $infoAprendiz = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        if (!$infoAprendiz) {
            throw new Exception("No se encontró el aprendiz para esta asistencia.");
        }

        $idAprendiz        = (int) $infoAprendiz['id_aprendiz'];
        $idFicha           = (int) $infoAprendiz['id_ficha'];
        $idInstructorClase = !empty($infoAprendiz['horario_instructor']) ? (int) $infoAprendiz['horario_instructor'] : 0;

        $stmtExcusa = $conexion->prepare("
            INSERT INTO excusas_medicas (id_asistencia, aprendices_id_aprendiz, archivo_adjunto, motivo, fecha_creacion)
            VALUES (:id_asistencia, :id_aprendiz, :ruta, :motivo, NOW())
        ");
        $stmtExcusa->bindParam(':id_asistencia', $idAsistencia, PDO::PARAM_INT);
        $stmtExcusa->bindParam(':id_aprendiz', $idAprendiz, PDO::PARAM_INT);
        $stmtExcusa->bindParam(':ruta', $rutaRelativaArchivo);
        $stmtExcusa->bindParam(':motivo', $motivo);
        $stmtExcusa->execute();

        $idNuevaExcusa = (int) $conexion->lastInsertId();

        // Determinar instructor a relacionar: el que tenía la clase en ese horario
        $instructores = [];
        if ($idInstructorClase > 0) {
            $instructores = [['id_instructor' => $idInstructorClase]];
        } else {
            // Si el horario no tenía instructor específico, se asocian los de la ficha
            $stmtInstructores = $conexion->prepare("
                SELECT id_instructor
                FROM fichas_has_usuarios
                WHERE fichas_id_ficha = :id_ficha
            ");
            $stmtInstructores->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
            $stmtInstructores->execute();
            $instructores = $stmtInstructores->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!empty($instructores)) {
            $stmtRevision = $conexion->prepare("
                INSERT INTO revision_excusas (id_excusa, id_instructor, estado)
                VALUES (:id_excusa, :id_instructor, 'Pendiente')
            ");

            foreach ($instructores as $inst) {
                $idInst = (int) ($inst['id_instructor'] ?? 0);
                if ($idInst > 0) {
                    $stmtRevision->bindValue(':id_excusa', $idNuevaExcusa, PDO::PARAM_INT);
                    $stmtRevision->bindValue(':id_instructor', $idInst, PDO::PARAM_INT);
                    $stmtRevision->execute();
                }
            }
        }
    }

    // Reenvía una excusa YA EXISTENTE (misma fila, mismo id_excusa): se
    // reemplaza el archivo y el motivo, y se reabren a "Pendiente" solo
    // las revisiones que no estaban ya aprobadas -- al instructor que ya
    // la había aprobado no le vuelve a aparecer para revisar de nuevo.
    public function reenviar(int $idExcusa, string $rutaRelativaArchivo, string $motivo): void
    {
        $conexion = Conexion::obtener();

        try {
            $conexion->beginTransaction();

            $stmt = $conexion->prepare("
                UPDATE excusas_medicas
                SET archivo_adjunto = :ruta, motivo = :motivo
                WHERE id_excusa = :id_excusa
            ");
            $stmt->bindParam(':ruta', $rutaRelativaArchivo);
            $stmt->bindParam(':motivo', $motivo);
            $stmt->bindParam(':id_excusa', $idExcusa, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $conexion->prepare("
                UPDATE revision_excusas
                SET estado = 'Pendiente', observaciones = NULL, fecha_respuesta = NULL
                WHERE id_excusa = :id_excusa AND estado != 'Aprobada'
            ");
            $stmt->bindParam(':id_excusa', $idExcusa, PDO::PARAM_INT);
            $stmt->execute();

            $conexion->commit();
        } catch (Exception $e) {
            $conexion->rollBack();
            error_log('Error al reenviar excusa: ' . $e->getMessage());
            throw $e;
        }
    }

    // Excusas asignadas a un instructor específico a través de la tabla revision_excusas
    public function listarPorInstructor(int $idInstructor): array
    {
        $sql = "SELECT
                    usuarios.nombre_completo,
                    fichas.nombre_programa,
                    fichas.numero_ficha,
                    excusas_medicas.motivo,
                    asistencias.fecha,
                    horarios.hora_entrada_programada,
                    horarios.hora_salida_programada,
                    excusas_medicas.archivo_adjunto,
                    revision_excusas.estado AS estado_excusa,
                    revision_excusas.observaciones,
                    revision_excusas.id_revision,
                    excusas_medicas.id_excusa
                FROM revision_excusas
                JOIN excusas_medicas ON revision_excusas.id_excusa = excusas_medicas.id_excusa
                JOIN asistencias ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                LEFT JOIN horarios ON asistencias.id_horario = horarios.id_horario
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

        if (strtolower($estado) === 'aprobada') {
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
