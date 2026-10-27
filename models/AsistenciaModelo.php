<?php
// Acceso a datos de la tabla asistencias, desde el punto de vista del
// aprendiz (su propio historial).

class AsistenciaModelo
{
    // Historial completo del aprendiz, con el estado de la excusa si la tiene
    // (LEFT JOIN: también trae inasistencias sin excusa asociada).
    // Historial del aprendiz: se muestran todas las inasistencias, retardos y novedades,
    // pero para asistencias cumplidas ('asistencia', 'presente', 'a tiempo') únicamente se visualiza la del día actual.
    public function listarPorAprendiz(int $idUsuario, ?string $fechaHoy = null): array
    {
        $fechaHoy = $fechaHoy ?: date('Y-m-d');
        $sql = "SELECT asistencias.*
                FROM asistencias
                JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz
                LEFT JOIN excusas_medicas ON excusas_medicas.id_asistencia = asistencias.id_asistencia
                WHERE aprendices.id_usuario = :id_usuario
                  AND (
                      LOWER(asistencias.estado) NOT IN ('asistencia', 'presente', 'a tiempo')
                      OR asistencias.fecha = :fecha_hoy
                  )
                ORDER BY asistencias.fecha DESC, asistencias.hora_entrada_real DESC";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindParam(':fecha_hoy', $fechaHoy, PDO::PARAM_STR);
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
    public function listarHistorialCompleto(int $idUsuario, ?string $fechaHoy = null): array
    {
        $fechaHoy = $fechaHoy ?: date('Y-m-d');
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
                  AND (
                      LOWER(a.estado) NOT IN ('asistencia', 'presente', 'a tiempo')
                      OR a.fecha = :fecha_hoy
                  )
                ORDER BY a.fecha DESC, a.hora_entrada_real DESC";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindParam(':fecha_hoy', $fechaHoy, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Asegura que todas las clases pasadas programadas en horarios sin marcaje
    // queden registradas como 'inasistencia' en la tabla asistencias vinculadas a su horario e instructor.
    public function sincronizarInasistencias(int $idUsuario): void
    {
        $db = Conexion::obtener();

        // 1. Obtener aprendiz activo y su ficha (solo si la ficha está en estado Activa)
        $stmtAp = $db->prepare("
            SELECT ap.id_aprendiz, ap.id_ficha 
            FROM aprendices ap 
            JOIN usuarios u ON u.id_usuario = ap.id_usuario
            JOIN fichas f ON f.id_ficha = ap.id_ficha
            WHERE ap.id_usuario = :id 
              AND LOWER(u.estado) = 'activo'
              AND f.estado = 'Activa'
            LIMIT 1
        ");
        $stmtAp->bindParam(':id', $idUsuario, PDO::PARAM_INT);
        $stmtAp->execute();
        $aprendiz = $stmtAp->fetch(PDO::FETCH_ASSOC);

        if (!$aprendiz) {
            return;
        }

        $idAprendiz = (int) $aprendiz['id_aprendiz'];
        $idFicha    = (int) $aprendiz['id_ficha'];

        // 2. Buscar horarios pasados de la ficha donde el aprendiz no tenga registro en asistencias
        // Excluye rigurosamente horarios cancelados, fechas festivas y asistencias previas
        $sqlFaltantes = "
            SELECT h.id_horario, h.dia_semana, h.id_instructor
            FROM horarios h
            WHERE h.id_ficha = :id_ficha
              AND h.estado = 'Programado'
              AND (
                  h.dia_semana < CURDATE()
                  OR (h.dia_semana = CURDATE() AND h.hora_salida_programada <= CURTIME())
              )
              AND NOT EXISTS (
                  SELECT 1 FROM dias_festivos df
                  WHERE df.fecha = h.dia_semana
                    AND (
                        df.id_ficha IS NULL 
                        OR df.id_ficha = 0 
                        OR df.id_ficha = h.id_ficha
                        OR UPPER(TRIM(COALESCE(df.descripcion, ''))) IN ('FESTIVO', 'FESTIVOS', 'FERIADO', 'VACACIONES', 'RECESO')
                    )
                    AND (
                        df.es_dia_completo = 1
                        OR df.hora_inicio IS NULL 
                        OR df.hora_fin IS NULL
                        OR UPPER(TRIM(COALESCE(df.descripcion, ''))) IN ('FESTIVO', 'FESTIVOS', 'FERIADO')
                        OR (h.hora_entrada_programada < df.hora_fin AND h.hora_salida_programada > df.hora_inicio)
                    )
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
                INSERT IGNORE INTO asistencias (id_aprendiz, id_horario, fecha, hora_entrada_real, hora_salida_real, tiempo_retraso, estado)
                VALUES (:id_aprendiz, :id_horario, :fecha, NULL, NULL, 0, 'inasistencia')
            ");

            foreach ($faltantes as $falta) {
                $stmtInsert->bindValue(':id_aprendiz', $idAprendiz, PDO::PARAM_INT);
                $stmtInsert->bindValue(':id_horario', (int) $falta['id_horario'], PDO::PARAM_INT);
                $stmtInsert->bindValue(':fecha', $falta['dia_semana']);
                $stmtInsert->execute();
            }
        }
    }

    /**
     * Sincroniza las inasistencias de TODOS los aprendices y fichas
     * cuyos bloques de clase ya hayan finalizado (hora_salida_programada <= CURTIME()).
     * 
     * Garantías de integridad y rendimiento:
     * 1. Exclusión mutua (Mutex): Usa GET_LOCK() para impedir que dos procesos sincronicen al mismo tiempo.
     * 2. Exclusión de festivos: No genera faltas en fechas u horarios registrados en dias_festivos (institucionales o de ficha).
     * 3. Idempotencia total: Inserción atómica INSERT IGNORE con clave única uq_asistencia_aprendiz_horario.
     * 4. Exclusión de fichas no activas: Fichas en estado 'Finalizada' o 'Archivada' NO generan inasistencias.
     * 5. Exclusión de horarios cancelados: Solo horarios con estado = 'Programado'.
     * 6. Verificación de clase programada y hora cumplida: Solo sesiones terminadas de aprendices activos.
     * 7. Métricas de diagnóstico: Retorna métricas exhaustivas para auditoría técnica.
     *
     * @param bool $forzarEjecucion Si es true, ignora el bloqueo de concurrencia
     * @return array Resumen de la sincronización con métricas completas
     */
    public function sincronizarInasistenciasGlobal(bool $forzarEjecucion = false): array
    {
        $db = Conexion::obtener();
        $horaInicio = microtime(true);
        $nombreBloqueo = 'sena_sync_inasistencias_lock';
        $bloqueoAdquirido = false;

        try {
            // Intentar adquirir bloqueo de exclusión mutua inmediato (timeout 0)
            $stmtLock = $db->query("SELECT GET_LOCK('{$nombreBloqueo}', 0) AS lock_acquired");
            $bloqueoAdquirido = (int) ($stmtLock->fetchColumn() ?? 0) === 1;

            if (!$bloqueoAdquirido && !$forzarEjecucion) {
                return [
                    'exito'                     => false,
                    'bloqueado'                 => true,
                    'sesiones_evaluadas'        => 0,
                    'nuevas_faltas'             => 0,
                    'omitidos_festivos'         => 0,
                    'omitidos_asistencia_previa'=> 0,
                    'duplicados_evitados'       => 0,
                    'duracion_ms'               => round((microtime(true) - $horaInicio) * 1000, 2),
                    'mensaje'                   => 'Sincronización omitida: Ya existe otro proceso de sincronización activo.'
                ];
            }

            // 1. Total sesiones evaluadas (aprendices activos con horarios en clases finalizadas de fichas activas y horarios no cancelados)
            $sqlTotalCandidatos = "
                SELECT COUNT(*)
                FROM horarios h
                JOIN fichas f ON f.id_ficha = h.id_ficha
                JOIN aprendices ap ON ap.id_ficha = h.id_ficha
                JOIN usuarios u ON u.id_usuario = ap.id_usuario
                WHERE f.estado = 'Activa'
                  AND h.estado = 'Programado'
                  AND LOWER(u.estado) = 'activo'
                  AND (
                      h.dia_semana < CURDATE()
                      OR (h.dia_semana = CURDATE() AND h.hora_salida_programada <= CURTIME())
                  )
            ";
            $totalEvaluados = (int) $db->query($sqlTotalCandidatos)->fetchColumn();

            // 2. Omitidos por coincidir con días festivos (institucionales o de ficha)
            $sqlOmitidosFestivos = "
                SELECT COUNT(*)
                FROM horarios h
                JOIN fichas f ON f.id_ficha = h.id_ficha
                JOIN aprendices ap ON ap.id_ficha = h.id_ficha
                JOIN usuarios u ON u.id_usuario = ap.id_usuario
                WHERE f.estado = 'Activa'
                  AND h.estado = 'Programado'
                  AND LOWER(u.estado) = 'activo'
                  AND (
                      h.dia_semana < CURDATE()
                      OR (h.dia_semana = CURDATE() AND h.hora_salida_programada <= CURTIME())
                  )
                  AND EXISTS (
                      SELECT 1 FROM dias_festivos df
                      WHERE df.fecha = h.dia_semana
                        AND (
                            df.id_ficha IS NULL 
                            OR df.id_ficha = 0 
                            OR df.id_ficha = h.id_ficha
                            OR UPPER(TRIM(COALESCE(df.descripcion, ''))) IN ('FESTIVO', 'FESTIVOS', 'FERIADO', 'VACACIONES', 'RECESO')
                        )
                        AND (
                            df.es_dia_completo = 1
                            OR df.hora_inicio IS NULL 
                            OR df.hora_fin IS NULL
                            OR UPPER(TRIM(COALESCE(df.descripcion, ''))) IN ('FESTIVO', 'FESTIVOS', 'FERIADO')
                            OR (h.hora_entrada_programada < df.hora_fin AND h.hora_salida_programada > df.hora_inicio)
                        )
                  )
            ";
            $omitidosFestivos = (int) $db->query($sqlOmitidosFestivos)->fetchColumn();

            // 3. Omitidos por asistencia previa válida o retardo
            $sqlOmitidosAsistencia = "
                SELECT COUNT(*)
                FROM horarios h
                JOIN fichas f ON f.id_ficha = h.id_ficha
                JOIN aprendices ap ON ap.id_ficha = h.id_ficha
                JOIN usuarios u ON u.id_usuario = ap.id_usuario
                WHERE f.estado = 'Activa'
                  AND h.estado = 'Programado'
                  AND LOWER(u.estado) = 'activo'
                  AND (
                      h.dia_semana < CURDATE()
                      OR (h.dia_semana = CURDATE() AND h.hora_salida_programada <= CURTIME())
                  )
                  AND EXISTS (
                      SELECT 1 FROM asistencias a
                      WHERE a.id_aprendiz = ap.id_aprendiz
                        AND (a.id_horario = h.id_horario OR (a.id_horario IS NULL AND a.fecha = h.dia_semana))
                        AND LOWER(a.estado) != 'inasistencia'
                  )
            ";
            $omitidosAsistencia = (int) $db->query($sqlOmitidosAsistencia)->fetchColumn();

            // 4. Inasistencias previas ya registradas (duplicados evitados)
            $sqlDuplicadosEvitados = "
                SELECT COUNT(*)
                FROM horarios h
                JOIN fichas f ON f.id_ficha = h.id_ficha
                JOIN aprendices ap ON ap.id_ficha = h.id_ficha
                JOIN usuarios u ON u.id_usuario = ap.id_usuario
                WHERE f.estado = 'Activa'
                  AND h.estado = 'Programado'
                  AND LOWER(u.estado) = 'activo'
                  AND (
                      h.dia_semana < CURDATE()
                      OR (h.dia_semana = CURDATE() AND h.hora_salida_programada <= CURTIME())
                  )
                  AND EXISTS (
                      SELECT 1 FROM asistencias a
                      WHERE a.id_aprendiz = ap.id_aprendiz
                        AND (a.id_horario = h.id_horario OR (a.id_horario IS NULL AND a.fecha = h.dia_semana))
                        AND LOWER(a.estado) = 'inasistencia'
                  )
            ";
            $duplicadosEvitados = (int) $db->query($sqlDuplicadosEvitados)->fetchColumn();

            // 5. Inserción idempotente excluyendo festivos, asistencias previas, fichas finalizadas/archivadas y horarios cancelados
            $sql = "
                INSERT IGNORE INTO asistencias (id_aprendiz, id_horario, fecha, hora_entrada_real, hora_salida_real, tiempo_retraso, estado)
                SELECT 
                    ap.id_aprendiz,
                    h.id_horario,
                    h.dia_semana,
                    NULL,
                    NULL,
                    0,
                    'inasistencia'
                FROM horarios h
                JOIN fichas f ON f.id_ficha = h.id_ficha
                JOIN aprendices ap ON ap.id_ficha = h.id_ficha
                JOIN usuarios u ON u.id_usuario = ap.id_usuario
                WHERE f.estado = 'Activa'
                  AND h.estado = 'Programado'
                  AND LOWER(u.estado) = 'activo'
                  AND (
                      h.dia_semana < CURDATE()
                      OR (h.dia_semana = CURDATE() AND h.hora_salida_programada <= CURTIME())
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM dias_festivos df
                      WHERE df.fecha = h.dia_semana
                        AND (
                            df.id_ficha IS NULL 
                            OR df.id_ficha = 0 
                            OR df.id_ficha = h.id_ficha
                            OR UPPER(TRIM(COALESCE(df.descripcion, ''))) IN ('FESTIVO', 'FESTIVOS', 'FERIADO', 'VACACIONES', 'RECESO')
                        )
                        AND (
                            df.es_dia_completo = 1
                            OR df.hora_inicio IS NULL 
                            OR df.hora_fin IS NULL
                            OR UPPER(TRIM(COALESCE(df.descripcion, ''))) IN ('FESTIVO', 'FESTIVOS', 'FERIADO')
                            OR (h.hora_entrada_programada < df.hora_fin AND h.hora_salida_programada > df.hora_inicio)
                        )
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM asistencias a
                      WHERE a.id_aprendiz = ap.id_aprendiz
                        AND (a.id_horario = h.id_horario OR (a.id_horario IS NULL AND a.fecha = h.dia_semana))
                  )
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $totalRegistradas = $stmt->rowCount();
            $duracionMs = round((microtime(true) - $horaInicio) * 1000, 2);

            return [
                'exito'                     => true,
                'bloqueado'                 => false,
                'sesiones_evaluadas'        => $totalEvaluados,
                'nuevas_faltas'             => $totalRegistradas,
                'omitidos_festivos'         => $omitidosFestivos,
                'omitidos_asistencia_previa'=> $omitidosAsistencia,
                'duplicados_evitados'       => $duplicadosEvitados,
                'duracion_ms'               => $duracionMs,
                'mensaje'                   => "Sincronización completada exitosamente. Se evaluaron {$totalEvaluados} sesiones ({$omitidosFestivos} omitidas por festivos, {$omitidosAsistencia} con asistencia previa, {$duplicadosEvitados} duplicados evitados). Se registraron {$totalRegistradas} nuevas inasistencias."
            ];
        } catch (PDOException $e) {
            error_log('Error en sincronizarInasistenciasGlobal: ' . $e->getMessage());
            return [
                'exito'                     => false,
                'bloqueado'                 => false,
                'sesiones_evaluadas'        => 0,
                'nuevas_faltas'             => 0,
                'omitidos_festivos'         => 0,
                'omitidos_asistencia_previa'=> 0,
                'duplicados_evitados'       => 0,
                'duracion_ms'               => round((microtime(true) - $horaInicio) * 1000, 2),
                'error'                     => $e->getMessage(),
                'mensaje'                   => 'Ocurrió un error al sincronizar inasistencias: ' . $e->getMessage()
            ];
        } finally {
            if ($bloqueoAdquirido) {
                try {
                    $db->query("SELECT RELEASE_LOCK('{$nombreBloqueo}')");
                } catch (\Throwable $ignored) {
                }
            }
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
                    COALESCE(
                        h.id_instructor,
                        (SELECT fhu.id_instructor FROM fichas_has_usuarios fhu WHERE fhu.fichas_id_ficha = ap.id_ficha LIMIT 1)
                    ) AS id_instructor,
                    COALESCE(
                        u_inst.nombre_completo,
                        (SELECT u2.nombre_completo FROM fichas_has_usuarios fhu2 JOIN usuarios u2 ON u2.id_usuario = fhu2.id_instructor WHERE fhu2.fichas_id_ficha = ap.id_ficha LIMIT 1),
                        'Instructor'
                    ) AS instructor_nombre
                FROM asistencias a
                JOIN aprendices ap ON ap.id_aprendiz = a.id_aprendiz
                LEFT JOIN horarios h ON h.id_horario = a.id_horario
                LEFT JOIN usuarios u_inst ON u_inst.id_usuario = h.id_instructor
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
            $idAsistencia = (int) $fila['id_asistencia'];

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

            // Evitar duplicar el mismo id_asistencia si se procesa más de una vez
            if (in_array($idAsistencia, $agrupadas[$fecha]['ids_asistencia'], true)) {
                continue;
            }

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

    /**
     * Realiza una auditoría exhaustiva y de SOLO LECTURA sobre todas las inasistencias
     * históricas registradas en la base de datos, identificando posibles inconsistencias.
     *
     * Clasificaciones:
     * - error_confirmado: Evidencia categórica de falta indebida (festivo, marcaje previo, usuario inactivo).
     * - caso_sospechoso: Indicios técnicos que requieren validación administrativa previa (ej. id_horario NULL).
     * - correcto: Sesión válida programada, finalizada, aprendiz activo y sin marcaje.
     *
     * @return array Resumen estructurado y lista de casos auditados
     */
    public function auditarInasistenciasHistoricas(): array
    {
        $db = Conexion::obtener();

        $sql = "
            SELECT 
                a.id_asistencia,
                a.id_aprendiz,
                a.id_horario,
                a.fecha,
                a.hora_entrada_real,
                a.hora_salida_real,
                a.tiempo_retraso,
                a.estado,
                ap.id_ficha AS id_ficha_aprendiz,
                f_ap.numero_ficha AS num_ficha_aprendiz,
                f_ap.nombre_programa,
                u.id_usuario,
                u.nombre_completo AS nombre_aprendiz,
                u.usuario AS doc_aprendiz,
                u.estado AS estado_usuario,
                h.id_horario AS h_existe,
                h.id_ficha AS h_id_ficha,
                h.dia_semana AS h_dia_semana,
                h.hora_entrada_programada,
                h.hora_salida_programada,
                h.id_instructor,
                u_inst.nombre_completo AS nombre_instructor,
                df.id_festivo,
                df.descripcion AS festivo_descripcion,
                df.es_dia_completo AS festivo_dia_completo,
                df.hora_inicio AS festivo_h_ini,
                df.hora_fin AS festivo_h_fin,
                df.id_ficha AS festivo_id_ficha,
                em.id_excusa,
                em.motivo AS excusa_motivo,
                re.estado AS excusa_estado_revision,
                f_ap.estado AS estado_ficha,
                f_ap.fecha_finalizacion AS fecha_fin_ficha,
                h.estado AS estado_horario,
                (
                    SELECT COUNT(*) 
                    FROM asistencias a_dup 
                    WHERE a_dup.id_aprendiz = a.id_aprendiz 
                      AND a_dup.fecha = a.fecha 
                      AND a_dup.id_asistencia != a.id_asistencia
                ) AS conteo_otros_registros_dia,
                (
                    SELECT GROUP_CONCAT(CONCAT('ID:', a2.id_asistencia, ' [', a2.estado, ']') SEPARATOR ', ')
                    FROM asistencias a2
                    WHERE a2.id_aprendiz = a.id_aprendiz
                      AND a2.fecha = a.fecha
                      AND a2.id_asistencia != a.id_asistencia
                ) AS detalle_otros_registros
            FROM asistencias a
            LEFT JOIN aprendices ap ON ap.id_aprendiz = a.id_aprendiz
            LEFT JOIN usuarios u ON u.id_usuario = ap.id_usuario
            LEFT JOIN fichas f_ap ON f_ap.id_ficha = ap.id_ficha
            LEFT JOIN horarios h ON h.id_horario = a.id_horario
            LEFT JOIN usuarios u_inst ON u_inst.id_usuario = h.id_instructor
            LEFT JOIN dias_festivos df ON df.fecha = a.fecha AND (
                df.id_ficha IS NULL 
                OR df.id_ficha = 0 
                OR df.id_ficha = ap.id_ficha 
                OR UPPER(TRIM(COALESCE(df.descripcion, ''))) IN ('FESTIVO', 'FESTIVOS', 'FERIADO')
            )
            LEFT JOIN excusas_medicas em ON em.id_asistencia = a.id_asistencia
            LEFT JOIN revision_excusas re ON re.id_excusa = em.id_excusa
            WHERE LOWER(a.estado) = 'inasistencia'
            ORDER BY a.fecha ASC, a.id_asistencia ASC
        ";

        $filas = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $reporte = [
            'total_analizadas'   => count($filas),
            'errores_confirmados'=> 0,
            'casos_sospechosos'  => 0,
            'correctas'          => 0,
            'detalles'           => []
        ];

        foreach ($filas as $f) {
            $idAsist       = (int) $f['id_asistencia'];
            $idHorario     = $f['id_horario'] ? (int) $f['id_horario'] : null;
            $fecha         = $f['fecha'];
            $clasificacion = 'correcto';
            $motivo        = 'Inasistencia legítima: clase programada finalizada sin marcaje de asistencia.';
            $evidencia     = "Horario ID {$idHorario} finalizado el {$fecha}. No registra marcaje RFID.";
            $certeza       = 'Alta';
            $accion        = 'Conservar registro.';

            // Evaluar Criterio 1: Coincidencia con Día Festivo
            if (!empty($f['id_festivo'])) {
                $esCompleto = (int)($f['festivo_dia_completo'] ?? 1) === 1 || in_array(strtoupper(trim((string)$f['festivo_descripcion'])), ['FESTIVO', 'FERIADO'], true);
                $solapa = true;
                if (!$esCompleto && !empty($f['festivo_h_ini']) && !empty($f['festivo_h_fin']) && !empty($f['hora_entrada_programada'])) {
                    $solapa = ($f['hora_entrada_programada'] < $f['festivo_h_fin'] && $f['hora_salida_programada'] > $f['festivo_h_ini']);
                }

                if ($solapa) {
                    $clasificacion = 'error_confirmado';
                    $motivo = "Generada en día festivo oficial registrado ({$f['festivo_descripcion']}).";
                    $evidencia = "Fecha {$fecha} registrada en dias_festivos (ID: {$f['id_festivo']}, Tipo: {$f['festivo_descripcion']}).";
                    $certeza = 'Absoluta';
                    $accion = 'Anular o retirar falta en procedimiento controlado por ser día no lectivo.';
                }
            }

            // Evaluar Criterio 2: Horario Cancelado
            if ($clasificacion !== 'error_confirmado' && !empty($f['estado_horario']) && $f['estado_horario'] === 'Cancelado') {
                $clasificacion = 'error_confirmado';
                $motivo = "Generada para un horario que fue cancelado.";
                $evidencia = "Horario ID {$idHorario} marcado con estado 'Cancelado' en tabla horarios.";
                $certeza = 'Alta';
                $accion = 'Anular falta por corresponder a clase cancelada.';
            }

            // Evaluar Criterio 3: Ficha Finalizada o Archivada antes de la sesión
            if ($clasificacion !== 'error_confirmado' && !empty($f['fecha_fin_ficha']) && $f['fecha'] > substr((string)$f['fecha_fin_ficha'], 0, 10)) {
                $clasificacion = 'error_confirmado';
                $motivo = "Sesión programada con fecha posterior a la finalización de la ficha ({$f['fecha_fin_ficha']}).";
                $evidencia = "Ficha {$f['num_ficha_aprendiz']} finalizada el {$f['fecha_fin_ficha']}; falta registrada el {$fecha}.";
                $certeza = 'Alta';
                $accion = 'Anular falta por corresponder a fecha posterior al cierre de la formación.';
            }

            // Evaluar Criterio 4: Aprendiz no activo o desvinculado
            if ($clasificacion !== 'error_confirmado' && strtolower((string)$f['estado_usuario']) !== 'activo') {
                $clasificacion = 'error_confirmado';
                $motivo = "El aprendiz no se encontraba en estado activo (Estado actual: '{$f['estado_usuario']}').";
                $evidencia = "Usuario ID {$f['id_usuario']} marcado como '{$f['estado_usuario']}' en tabla usuarios.";
                $certeza = 'Alta';
                $accion = 'Anular falta por corresponder a usuario no activo.';
            }

            // Evaluar Criterio 5: Inasistencia sin id_horario (registro huérfano)
            if ($clasificacion === 'correcto' && $idHorario === null) {
                // Si además tiene otro registro en el mismo día
                if ((int)$f['conteo_otros_registros_dia'] > 0) {
                    $clasificacion = 'caso_sospechoso';
                    $motivo = "Inasistencia sin horario asociado (id_horario NULL) con registros simultáneos en la misma fecha ({$f['detalle_otros_registros']}).";
                    $evidencia = "Registro creado sin id_horario el {$fecha}. Coexiste con {$f['detalle_otros_registros']}.";
                    $certeza = 'Media';
                    $accion = 'Revisar con coordinación académica si corresponde a carga manual legacy o inconsistencia histórica.';
                } else {
                    $clasificacion = 'caso_sospechoso';
                    $motivo = 'Inasistencia sin horario programado vinculado (id_horario NULL).';
                    $evidencia = "No existe vínculo a horarios en la fila {$idAsist}.";
                    $certeza = 'Media';
                    $accion = 'Validar si la clase fue eliminada del calendario o fue una falta manual previa.';
                }
            }

            // Evaluar Criterio 6: Horario inexistente (eliminado físicamente en cargas anteriores)
            if ($clasificacion === 'correcto' && $idHorario !== null && empty($f['h_existe'])) {
                $clasificacion = 'caso_sospechoso';
                $motivo = "El horario vinculado (ID {$idHorario}) ya no existe en la tabla horarios (fue eliminado previamente).";
                $evidencia = "Horario ID {$idHorario} referenciado en asistencia no existe físicamente en horarios.";
                $certeza = 'Media';
                $accion = 'Preservar registro como histórico o archivar según política institucional.';
            }

            // Contabilizar
            if ($clasificacion === 'error_confirmado') {
                $reporte['errores_confirmados']++;
            } elseif ($clasificacion === 'caso_sospechoso') {
                $reporte['casos_sospechosos']++;
            } else {
                $reporte['correctas']++;
            }

            $reporte['detalles'][] = [
                'id_asistencia'      => $idAsist,
                'id_aprendiz'        => (int) $f['id_aprendiz'],
                'nombre_aprendiz'    => $f['nombre_aprendiz'] ?? 'Desconocido',
                'documento'          => $f['doc_aprendiz'] ?? '',
                'id_ficha'           => (int) ($f['id_ficha_aprendiz'] ?? 0),
                'numero_ficha'       => $f['num_ficha_aprendiz'] ?? 'N/A',
                'programa'           => $f['nombre_programa'] ?? '',
                'fecha'              => $fecha,
                'id_horario'         => $idHorario,
                'horario_texto'      => (!empty($f['hora_entrada_programada'])) ? "{$f['hora_entrada_programada']} - {$f['hora_salida_programada']}" : 'N/A',
                'instructor'         => $f['nombre_instructor'] ?? 'N/A',
                'clasificacion'      => $clasificacion,
                'motivo'             => $motivo,
                'evidencia'          => $evidencia,
                'certeza'            => $certeza,
                'accion_recomendada' => $accion,
                'tiene_excusa'       => !empty($f['id_excusa']),
                'id_excusa'          => $f['id_excusa'] ? (int) $f['id_excusa'] : null,
                'excusa_motivo'      => $f['excusa_motivo'] ?? null
            ];
        }

        return $reporte;
    }

    /**
     * Genera una propuesta formal y segura de corrección para los registros auditados.
     * No realiza modificaciones en la base de datos (Operación 100% de Solo Lectura).
     */
    public function obtenerPropuestaCorreccion(): array
    {
        $auditoria = $this->auditarInasistenciasHistoricas();
        $propuesta = [
            'fecha_generacion'      => date('Y-m-d H:i:s'),
            'total_registros'       => $auditoria['total_analizadas'],
            'errores_confirmados'   => $auditoria['errores_confirmados'],
            'casos_sospechosos'     => $auditoria['casos_sospechosos'],
            'correctas'             => $auditoria['correctas'],
            'acciones_propuestas'   => [],
            'procedimiento_seguro'  => [
                'Paso 1: Realizar copia de seguridad de la tabla asistencias (CREATE TABLE asistencias_backup_... AS SELECT * FROM asistencias).',
                'Paso 2: Iniciar transacción MySQL ACID (BEGIN TRANSACTION).',
                'Paso 3: Solo corregir los registros explícitamente confirmados sin alterar los marcajes RFID originales.',
                'Paso 4: Actualizar estado de registros erróneos o anularlos con trazabilidad de auditoría.',
                'Paso 5: Registrar la operación técnica en storage/logs/auditoria_asistencias.log.',
                'Paso 6: Confirmar transacción (COMMIT) o deshacer en caso de anomalía (ROLLBACK).'
            ]
        ];

        foreach ($auditoria['detalles'] as $item) {
            if ($item['clasificacion'] === 'error_confirmado') {
                $propuesta['acciones_propuestas'][] = [
                    'id_asistencia'     => $item['id_asistencia'],
                    'aprendiz'          => "{$item['nombre_aprendiz']} ({$item['documento']})",
                    'fecha'             => $item['fecha'],
                    'tipo'              => 'ERROR_CONFIRMADO',
                    'accion_propuesta'  => 'ANULAR_INASISTENCIA',
                    'justificacion'     => $item['motivo'],
                    'tiene_excusa'      => $item['tiene_excusa']
                ];
            } elseif ($item['clasificacion'] === 'caso_sospechoso') {
                $propuesta['acciones_propuestas'][] = [
                    'id_asistencia'     => $item['id_asistencia'],
                    'aprendiz'          => "{$item['nombre_aprendiz']} ({$item['documento']})",
                    'fecha'             => $item['fecha'],
                    'tipo'              => 'CASO_SOSPECHOSO',
                    'accion_propuesta'  => 'REQUIERE_CONFIRMACION_MANUAL',
                    'justificacion'     => $item['motivo'],
                    'tiene_excusa'      => $item['tiene_excusa']
                ];
            }
        }

        return $propuesta;
    }

    /**
     * Aplica la corrección controlada de registros confirmados mediante transacción segura
     * y respaldo previo sin destruir evidencia ni registros de marcaje físico RFID.
     *
     * @param array $idsAsistencia IDs de asistencias a corregir
     * @param string $motivoJustificacion Justificación institucional
     * @return array Resultado de la operación controlada
     */
    public function ejecutarCorreccionSegura(array $idsAsistencia, string $motivoJustificacion): array
    {
        if (empty($idsAsistencia)) {
            return [
                'exito'     => false,
                'mensaje'   => 'No se proporcionaron identificadores de asistencia para corregir.'
            ];
        }

        $db = Conexion::obtener();
        $iniciadaAqui = false;

        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $iniciadaAqui = true;
        }

        try {
            // 1. Crear tabla de respaldo con timestamp
            $timestamp = date('Ymd_His');
            $tablaBackup = "asistencias_backup_{$timestamp}";
            $db->exec("CREATE TABLE IF NOT EXISTS `{$tablaBackup}` LIKE asistencias");
            $db->exec("INSERT INTO `{$tablaBackup}` SELECT * FROM asistencias");

            // 2. Filtrar únicamente inasistencias sin marcaje físico real
            $placeholders = implode(',', array_fill(0, count($idsAsistencia), '?'));
            $sqlVerificar = "
                SELECT id_asistencia, hora_entrada_real, estado 
                FROM asistencias 
                WHERE id_asistencia IN ({$placeholders})
            ";
            $stmtVerif = $db->prepare($sqlVerificar);
            $stmtVerif->execute($idsAsistencia);
            $registros = $stmtVerif->fetchAll(PDO::FETCH_ASSOC);

            $idsValidosParaCorregir = [];
            foreach ($registros as $r) {
                // Regla estricta: Jamás anular o alterar si existe un marcaje físico RFID registrado
                if (!empty($r['hora_entrada_real'])) {
                    continue;
                }
                $idsValidosParaCorregir[] = (int) $r['id_asistencia'];
            }

            if (empty($idsValidosParaCorregir)) {
                if ($iniciadaAqui && $db->inTransaction()) {
                    $db->rollBack();
                }
                return [
                    'exito'     => false,
                    'mensaje'   => 'Ninguno de los registros seleccionados califica para corrección automática (poseen marcajes RFID o no existen).'
                ];
            }

            // 3. Anular las inasistencias erróneas de forma controlada
            $placeholdersCorregir = implode(',', array_fill(0, count($idsValidosParaCorregir), '?'));
            $sqlAnular = "
                UPDATE asistencias 
                SET estado = 'anulada_por_auditoria' 
                WHERE id_asistencia IN ({$placeholdersCorregir})
            ";
            $stmtAnular = $db->prepare($sqlAnular);
            $stmtAnular->execute($idsValidosParaCorregir);
            $totalCorregidos = $stmtAnular->rowCount();

            if ($iniciadaAqui) {
                $db->commit();
            }

            // Registrar log de auditoría
            $dirLogs = __DIR__ . '/../storage/logs';
            if (!is_dir($dirLogs)) {
                @mkdir($dirLogs, 0755, true);
            }
            $logFile = $dirLogs . '/auditoria_asistencias.log';
            $fecha = date('Y-m-d H:i:s');
            $lineaLog = "[{$fecha}] [CORRECCION_HISTORICA] Respaldo: {$tablaBackup} | Registros corregidos: {$totalCorregidos} | IDs: " . implode(',', $idsValidosParaCorregir) . " | Justificación: {$motivoJustificacion}\n";
            @file_put_contents($logFile, $lineaLog, FILE_APPEND | LOCK_EX);

            return [
                'exito'              => true,
                'total_corregidos'   => $totalCorregidos,
                'tabla_respaldo'     => $tablaBackup,
                'ids_corregidos'     => $idsValidosParaCorregir,
                'mensaje'            => "Corrección completada exitosamente. Se respaldaron los datos en '{$tablaBackup}' y se anularon {$totalCorregidos} inasistencias indebidas."
            ];
        } catch (\Throwable $e) {
            if ($iniciadaAqui && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en ejecutarCorreccionSegura: ' . $e->getMessage());
            return [
                'exito'     => false,
                'error'     => $e->getMessage(),
                'mensaje'   => 'Error al ejecutar la corrección segura: ' . $e->getMessage()
            ];
        }
    }
}
