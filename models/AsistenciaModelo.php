<?php
// Acceso a datos de la tabla asistencias, desde el punto de vista del
// aprendiz (su propio historial).

class AsistenciaModelo
{
    // Historial completo del aprendiz, con el estado de la excusa si la tiene
    // (LEFT JOIN: también trae inasistencias sin excusa asociada).
    public function listarPorAprendiz(int $idUsuario): array
    {
        $sql = "SELECT asistencias.*
                       
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
                WHERE aprendices.id_usuario = :id_usuario AND LOWER(asistencias.estado) = LOWER(:estado)";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function contarExcusasPendientes(int $idUsuario): int
    {
        $sql = "SELECT COUNT(DISTINCT excusas_medicas.id_excusa)
                FROM asistencias
                JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz
                JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                LEFT JOIN revision_excusas ON revision_excusas.id_excusa = excusas_medicas.id_excusa
                WHERE aprendices.id_usuario = :id_usuario
                  AND (LOWER(revision_excusas.estado) = 'pendiente' OR revision_excusas.id_revision IS NULL)";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    // Obtiene la información académica y personal del aprendiz para el reporte individual
    public function obtenerInfoAprendiz(int $idUsuario): array|false
    {
        $sql = "SELECT 
                    u.nombre_completo,
                    u.usuario AS documento,
                    u.correo_electronico,
                    f.numero_ficha,
                    f.nombre_programa
                FROM aprendices ap
                JOIN usuarios u ON u.id_usuario = ap.id_usuario
                JOIN fichas f ON f.id_ficha = ap.id_ficha
                WHERE ap.id_usuario = :id_usuario
                LIMIT 1";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Historial ordenado y con detalle de horario programado para el reporte PDF
    public function listarHistorialCompleto(int $idUsuario): array
    {
        $sql = "SELECT 
                    a.fecha,
                    a.hora_entrada_real,
                    a.hora_salida_real,
                    a.tiempo_retraso,
                    a.estado,
                    h.hora_entrada_programada,
                    h.hora_salida_programada
                FROM asistencias a
                JOIN aprendices ap ON ap.id_aprendiz = a.id_aprendiz
                LEFT JOIN horarios h ON h.id_horario = a.id_horario
                WHERE ap.id_usuario = :id_usuario
                ORDER BY a.fecha DESC, a.hora_entrada_real DESC";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Asegura que todas las clases pasadas programadas en horarios sin marcaje
    // queden registradas como 'inasistencia' en la tabla asistencias vinculadas a su horario e instructor.
    public function sincronizarInasistencias(int $idUsuario): void
    {
        $db = Conexion::obtener();

        // 1. Obtener aprendiz y su ficha
        $stmtAp = $db->prepare("SELECT id_aprendiz, id_ficha FROM aprendices WHERE id_usuario = :id LIMIT 1");
        $stmtAp->bindParam(':id', $idUsuario, PDO::PARAM_INT);
        $stmtAp->execute();
        $aprendiz = $stmtAp->fetch(PDO::FETCH_ASSOC);

        if (!$aprendiz) {
            return;
        }

        $idAprendiz = (int) $aprendiz['id_aprendiz'];
        $idFicha    = (int) $aprendiz['id_ficha'];

        // 2. Buscar horarios pasados de la ficha donde el aprendiz no tenga registro en asistencias
        $sqlFaltantes = "
            SELECT h.id_horario, h.dia_semana, h.id_instructor
            FROM horarios h
            WHERE h.id_ficha = :id_ficha
              AND (
                  h.dia_semana < CURDATE()
                  OR (h.dia_semana = CURDATE() AND h.hora_salida_programada < CURTIME())
              )
              AND NOT EXISTS (
                  SELECT 1 FROM asistencias a
                  WHERE a.id_aprendiz = :id_aprendiz
                    AND (a.id_horario = h.id_horario OR (a.id_horario IS NULL AND a.fecha = h.dia_semana))
              )
        ";

        $stmtFaltantes = $db->prepare($sqlFaltantes);
        $stmtFaltantes->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
        $stmtFaltantes->bindParam(':id_aprendiz', $idAprendiz, PDO::PARAM_INT);
        $stmtFaltantes->execute();
        $faltantes = $stmtFaltantes->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($faltantes)) {
            $stmtInsert = $db->prepare("
                INSERT INTO asistencias (id_aprendiz, id_horario, fecha, hora_entrada_real, hora_salida_real, tiempo_retraso, estado)
                VALUES (:id_aprendiz, :id_horario, :fecha, NULL, NULL, 0, 'inasistencia')
            ");

            foreach ($faltantes as $falta) {
                $stmtInsert->bindValue(':id_aprendiz', $idAprendiz, PDO::PARAM_INT);
                $stmtInsert->bindValue(':id_horario', (int) $falta['id_horario'], PDO::PARAM_INT);
                $stmtInsert->bindValue(':fecha', $falta['dia_semana']);
                $stmtInsert->execute();
            }
        }

        // 3. Si hay asistencias con estado 'inasistencia' y id_horario NULL para este aprendiz, vincularlas al horario del día
        $sqlVincular = "
            UPDATE asistencias a
            JOIN horarios h ON h.id_ficha = :id_ficha AND h.dia_semana = a.fecha
            SET a.id_horario = h.id_horario
            WHERE a.id_aprendiz = :id_aprendiz
              AND a.id_horario IS NULL
              AND LOWER(a.estado) = 'inasistencia'
        ";
        try {
            $stmtVincular = $db->prepare($sqlVincular);
            $stmtVincular->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
            $stmtVincular->bindParam(':id_aprendiz', $idAprendiz, PDO::PARAM_INT);
            $stmtVincular->execute();
        } catch (PDOException $e) {
            // Ignorar si la actualización no aplica
        }
    }

    // Fechas de inasistencia que todavía no tienen ninguna excusa anexada,
    // con información del horario y el instructor correspondiente.
    public function listarFaltasSinExcusa(int $idUsuario): array
    {
        $this->sincronizarInasistencias($idUsuario);

        $sql = "SELECT 
                    a.id_asistencia,
                    a.fecha,
                    a.id_horario,
                    a.estado,
                    h.hora_entrada_programada,
                    h.hora_salida_programada,
                    COALESCE(h.id_instructor, fhu.id_instructor) AS id_instructor,
                    COALESCE(u_inst.nombre_completo, u_fhu.nombre_completo, 'Instructor') AS instructor_nombre
                FROM asistencias a
                JOIN aprendices ap ON ap.id_aprendiz = a.id_aprendiz
                LEFT JOIN horarios h ON h.id_horario = a.id_horario
                LEFT JOIN fichas_has_usuarios fhu ON fhu.fichas_id_ficha = ap.id_ficha
                LEFT JOIN usuarios u_inst ON u_inst.id_usuario = h.id_instructor
                LEFT JOIN usuarios u_fhu ON u_fhu.id_usuario = fhu.id_instructor
                LEFT JOIN excusas_medicas em ON em.id_asistencia = a.id_asistencia
                WHERE ap.id_usuario = :id_usuario
                  AND (LOWER(COALESCE(a.estado, '')) IN ('inasistencia', 'falta') OR a.hora_entrada_real IS NULL)
                  AND em.id_asistencia IS NULL
                ORDER BY a.fecha DESC, h.hora_entrada_programada ASC";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Agrupa las inasistencias por fecha para que en la interfaz visual solo se seleccione
    // UNA VEZ la fecha, aun cuando haya 2 o más bloques ese mismo día, pero manteniendo
    // la conexión con los instructores de cada bloque.
    public function listarFaltasAgrupadasPorFecha(int $idUsuario): array
    {
        $filas = $this->listarFaltasSinExcusa($idUsuario);
        $agrupadas = [];

        foreach ($filas as $fila) {
            $fecha = $fila['fecha'];
            if (!isset($agrupadas[$fecha])) {
                $nombreDia = date('d/m/Y', strtotime($fecha));
                $agrupadas[$fecha] = [
                    'fecha'              => $fecha,
                    'fecha_formateada'   => $nombreDia,
                    'ids_asistencia'     => [],
                    'ids_asistencia_str' => '',
                    'total_bloques'      => 0,
                    'instructores'       => [],
                    'instructores_texto' => '',
                    'bloques'            => [],
                    'bloques_texto'      => '',
                ];
            }

            $idAsistencia = (int) $fila['id_asistencia'];
            $agrupadas[$fecha]['ids_asistencia'][] = $idAsistencia;

            $nomInst = trim($fila['instructor_nombre'] ?? '');
            if ($nomInst === '') {
                $nomInst = 'Instructor asignado';
            }

            if (!in_array($nomInst, $agrupadas[$fecha]['instructores'], true)) {
                $agrupadas[$fecha]['instructores'][] = $nomInst;
            }

            $horaInicio = !empty($fila['hora_entrada_programada']) ? substr($fila['hora_entrada_programada'], 0, 5) : '';
            $horaFin    = !empty($fila['hora_salida_programada']) ? substr($fila['hora_salida_programada'], 0, 5) : '';
            $horarioTxt = ($horaInicio && $horaFin) ? "{$horaInicio} - {$horaFin}" : 'Horario asignado';

            $agrupadas[$fecha]['bloques'][] = [
                'id_asistencia' => $idAsistencia,
                'id_horario'    => (int) ($fila['id_horario'] ?? 0),
                'horario'       => $horarioTxt,
                'instructor'    => $nomInst,
                'id_instructor' => (int) ($fila['id_instructor'] ?? 0),
            ];
        }

        // Finalizar textos descriptivos de cada fecha agrupada
        foreach ($agrupadas as &$item) {
            $item['total_bloques']      = count($item['ids_asistencia']);
            $item['ids_asistencia_str'] = implode(',', $item['ids_asistencia']);
            $item['instructores_texto'] = implode(', ', $item['instructores']);

            $bloquesDesc = [];
            foreach ($item['bloques'] as $b) {
                $bloquesDesc[] = "{$b['horario']} ({$b['instructor']})";
            }
            $item['bloques_texto'] = implode(' · ', $bloquesDesc);
        }
        unset($item);

        return array_values($agrupadas);
    }
}
