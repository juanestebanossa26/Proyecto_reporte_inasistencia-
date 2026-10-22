<?php

require_once __DIR__ . '/FichaModelo.php';

class HorarioModelo
{
    private FichaModelo $fichaModelo;

    public function __construct(?FichaModelo $fichaModelo = null)
    {
        $this->fichaModelo = $fichaModelo ?? new FichaModelo();
    }

    public function getFichaModelo(): FichaModelo
    {
        return $this->fichaModelo;
    }

    // Lista horarios registrados con datos de la ficha, instructor, área y aprendices.
    // Si se especifica $idInstructor, filtra exclusivamente los horarios de ese instructor.
    public function listarHorarios(?int $idInstructor = null): array
    {
        if ($idInstructor !== null && $idInstructor > 0) {
            $sql = "SELECT
                        h.id_horario,
                        h.id_ficha,
                        h.hora_entrada_programada,
                        h.hora_salida_programada,
                        h.dia_semana AS fecha,
                        f.numero_ficha,
                        f.nombre_programa,
                        COALESCE(h.id_instructor, fhu.id_instructor) AS id_instructor,
                        COALESCE(u_directo.nombre_completo, u_ficha.nombre_completo, '') AS instructor_nombre,
                        COALESCE(u_directo.area, u_ficha.area, '') AS instructor_area,
                        (SELECT COUNT(*) FROM aprendices a WHERE a.id_ficha = f.id_ficha) AS total_aprendices
                    FROM horarios h
                    JOIN fichas f ON f.id_ficha = h.id_ficha
                    LEFT JOIN usuarios u_directo ON u_directo.id_usuario = h.id_instructor
                    LEFT JOIN (
                        SELECT fichas_id_ficha, MIN(id_instructor) AS id_instructor
                        FROM fichas_has_usuarios
                        GROUP BY fichas_id_ficha
                    ) fhu ON fhu.fichas_id_ficha = f.id_ficha
                    LEFT JOIN usuarios u_ficha ON u_ficha.id_usuario = fhu.id_instructor
                    WHERE h.id_instructor = :id_inst1
                       OR (h.id_instructor IS NULL AND fhu.id_instructor = :id_inst2)
                    ORDER BY h.dia_semana ASC, h.hora_entrada_programada ASC";

            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->execute([
                ':id_inst1' => $idInstructor,
                ':id_inst2' => $idInstructor,
            ]);
            $filas = $stmt->fetchAll();
        } else {
            $sql = "SELECT
                        h.id_horario,
                        h.id_ficha,
                        h.hora_entrada_programada,
                        h.hora_salida_programada,
                        h.dia_semana AS fecha,
                        f.numero_ficha,
                        f.nombre_programa,
                        COALESCE(h.id_instructor, fhu.id_instructor) AS id_instructor,
                        COALESCE(u_directo.nombre_completo, u_ficha.nombre_completo, '') AS instructor_nombre,
                        COALESCE(u_directo.area, u_ficha.area, '') AS instructor_area,
                        (SELECT COUNT(*) FROM aprendices a WHERE a.id_ficha = f.id_ficha) AS total_aprendices
                    FROM horarios h
                    JOIN fichas f ON f.id_ficha = h.id_ficha
                    LEFT JOIN usuarios u_directo ON u_directo.id_usuario = h.id_instructor
                    LEFT JOIN (
                        SELECT fichas_id_ficha, MIN(id_instructor) AS id_instructor
                        FROM fichas_has_usuarios
                        GROUP BY fichas_id_ficha
                    ) fhu ON fhu.fichas_id_ficha = f.id_ficha
                    LEFT JOIN usuarios u_ficha ON u_ficha.id_usuario = fhu.id_instructor
                    ORDER BY h.dia_semana ASC, h.hora_entrada_programada ASC";

            $filas = Conexion::obtener()->query($sql)->fetchAll();
        }

        // Saneamiento de entidades HTML para garantizar salida limpia UTF-8
        foreach ($filas as &$f) {
            $f['nombre_programa']  = html_entity_decode((string)($f['nombre_programa'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['instructor_nombre'] = html_entity_decode((string)($f['instructor_nombre'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['instructor_area']   = html_entity_decode((string)($f['instructor_area'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        unset($f);

        return $filas;
    }


    // =========================================================================
    // Delegación al Módulo de Fichas (FichaModelo)
    // Permite al módulo de horarios consultar las fichas sin administrarlas
    // =========================================================================

    public function buscarIdFichaPorNumero(string $numeroFicha): ?int
    {
        return $this->fichaModelo->buscarIdFichaPorNumero($numeroFicha);
    }

    public function buscarFichaPorNumero(string $numeroFicha): ?array
    {
        return $this->fichaModelo->buscarFichaPorNumero($numeroFicha);
    }

    public function buscarFichaPorId(int $idFicha): ?array
    {
        return $this->fichaModelo->buscarFichaPorId($idFicha);
    }

    public function existeNumeroFicha(string $numeroFicha, ?int $excluirIdFicha = null): bool
    {
        return $this->fichaModelo->existeNumeroFicha($numeroFicha, $excluirIdFicha);
    }

    public function crearFicha(string $numeroFicha, string $nombrePrograma): int
    {
        return $this->fichaModelo->crearFicha($numeroFicha, $nombrePrograma);
    }

    public function obtenerOCrearFicha(string $numeroFicha, string $nombrePrograma): int
    {
        return $this->fichaModelo->obtenerOCrearFicha($numeroFicha, $nombrePrograma);
    }

    public function asociarInstructor(int $idFicha, int $idInstructor): void
    {
        $this->fichaModelo->asociarInstructor($idFicha, $idInstructor);
    }

    public function eliminarFichaCompleta(int $idFicha): array
    {
        return $this->fichaModelo->eliminarFichaCompleta($idFicha);
    }

    // Manejo de transacciones para la base de datos
    public function iniciarTransaccion(): void
    {
        $db = Conexion::obtener();
        if (!$db->inTransaction()) {
            $db->beginTransaction();
        }
    }

    public function confirmarTransaccion(): void
    {
        $db = Conexion::obtener();
        if ($db->inTransaction()) {
            $db->commit();
        }
    }

    public function revertirTransaccion(): void
    {
        $db = Conexion::obtener();
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }


    // Comprueba si un horario ya existe para evitar registros duplicados
    public function existeHorario(int $idFicha, string $horaEntrada, string $horaSalida, string $fecha): bool
    {
        $hEntrada = (strlen($horaEntrada) === 5) ? $horaEntrada . ':00' : $horaEntrada;
        $hSalida  = (strlen($horaSalida) === 5) ? $horaSalida . ':00' : $horaSalida;

        $sql = "SELECT id_horario FROM horarios 
                WHERE id_ficha = :id_ficha 
                  AND dia_semana = :fecha 
                  AND hora_entrada_programada = :entrada 
                  AND hora_salida_programada = :salida 
                LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
        $stmt->bindParam(':fecha',    $fecha);
        $stmt->bindParam(':entrada',  $hEntrada);
        $stmt->bindParam(':salida',   $hSalida);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    // Verifica si existe un conflicto de horario (duplicado idéntico o solapamiento de horas)
    // para la misma ficha o para el mismo instructor en la misma fecha.
    public function verificarConflictoHorario(int $idFicha, string $fecha, string $horaEntrada, string $horaSalida, ?int $excluirIdHorario = null, ?int $idInstructor = null): ?array
    {
        $hEntrada = (strlen($horaEntrada) === 5) ? $horaEntrada . ':00' : $horaEntrada;
        $hSalida  = (strlen($horaSalida) === 5) ? $horaSalida . ':00' : $horaSalida;

        // 1. Chequeo de duplicado idéntico exacto para la ficha
        $sqlExacto = "SELECT h.id_horario, h.id_ficha, h.hora_entrada_programada, h.hora_salida_programada, h.dia_semana,
                             f.numero_ficha, f.nombre_programa,
                             COALESCE(u.nombre_completo, 'Sin asignar') AS instructor_nombre
                      FROM horarios h
                      JOIN fichas f ON f.id_ficha = h.id_ficha
                      LEFT JOIN usuarios u ON u.id_usuario = h.id_instructor
                      WHERE h.id_ficha = :id_ficha
                        AND h.dia_semana = :fecha
                        AND h.hora_entrada_programada = :entrada
                        AND h.hora_salida_programada = :salida";
        $paramsExacto = [
            ':id_ficha' => $idFicha,
            ':fecha'    => $fecha,
            ':entrada'  => $hEntrada,
            ':salida'   => $hSalida,
        ];
        if ($excluirIdHorario !== null && $excluirIdHorario > 0) {
            $sqlExacto .= " AND h.id_horario != :excluir";
            $paramsExacto[':excluir'] = $excluirIdHorario;
        }
        $sqlExacto .= " LIMIT 1";

        $stmt = Conexion::obtener()->prepare($sqlExacto);
        $stmt->execute($paramsExacto);
        $dup = $stmt->fetch();
        if ($dup) {
            $dup['tipo_conflicto'] = 'identico';
            return $dup;
        }

        // 2. Chequeo de solapamiento de horas para la misma ficha
        // Condición matemática de solapamiento: (inicio1 < fin2) Y (fin1 > inicio2)
        $sqlSolapado = "SELECT h.id_horario, h.id_ficha, h.hora_entrada_programada, h.hora_salida_programada, h.dia_semana,
                               f.numero_ficha, f.nombre_programa,
                               COALESCE(u.nombre_completo, 'Sin asignar') AS instructor_nombre
                        FROM horarios h
                        JOIN fichas f ON f.id_ficha = h.id_ficha
                        LEFT JOIN usuarios u ON u.id_usuario = h.id_instructor
                        WHERE h.id_ficha = :id_ficha
                          AND h.dia_semana = :fecha
                          AND (:nueva_entrada < h.hora_salida_programada AND :nueva_salida > h.hora_entrada_programada)";
        $paramsSolapado = [
            ':id_ficha'      => $idFicha,
            ':fecha'         => $fecha,
            ':nueva_entrada' => $hEntrada,
            ':nueva_salida'  => $hSalida,
        ];
        if ($excluirIdHorario !== null && $excluirIdHorario > 0) {
            $sqlSolapado .= " AND h.id_horario != :excluir";
            $paramsSolapado[':excluir'] = $excluirIdHorario;
        }
        $sqlSolapado .= " LIMIT 1";

        $stmt = Conexion::obtener()->prepare($sqlSolapado);
        $stmt->execute($paramsSolapado);
        $solap = $stmt->fetch();
        if ($solap) {
            $solap['tipo_conflicto'] = 'solapado';
            return $solap;
        }

        // 3. Chequeo de conflicto de horario para el instructor (si se especificó instructor)
        if ($idInstructor !== null && $idInstructor > 0) {
            $sqlInst = "SELECT h.id_horario, h.id_ficha, h.hora_entrada_programada, h.hora_salida_programada, h.dia_semana, f.numero_ficha, f.nombre_programa
                        FROM horarios h
                        JOIN fichas f ON f.id_ficha = h.id_ficha
                        WHERE h.id_instructor = :id_inst
                          AND h.dia_semana = :fecha
                          AND (:nueva_entrada < h.hora_salida_programada AND :nueva_salida > h.hora_entrada_programada)";
            $paramsInst = [
                ':id_inst'       => $idInstructor,
                ':fecha'         => $fecha,
                ':nueva_entrada' => $hEntrada,
                ':nueva_salida'  => $hSalida,
            ];
            if ($excluirIdHorario !== null && $excluirIdHorario > 0) {
                $sqlInst .= " AND h.id_horario != :excluir";
                $paramsInst[':excluir'] = $excluirIdHorario;
            }
            $sqlInst .= " LIMIT 1";

            $stmtInst = Conexion::obtener()->prepare($sqlInst);
            $stmtInst->execute($paramsInst);
            $confInst = $stmtInst->fetch();
            if ($confInst) {
                $confInst['tipo_conflicto'] = 'instructor_ocupado';
                return $confInst;
            }
        }

        return null;
    }

    // Registra un nuevo horario asociado a una ficha y opcionalmente a un instructor
    public function guardarHorario(int $idFicha, string $horaEntrada, string $horaSalida, string $fecha, ?int $idInstructor = null): void
    {
        try {
            $sql = "INSERT INTO horarios (id_ficha, id_instructor, hora_entrada_programada, hora_salida_programada, dia_semana)
                    VALUES (:id_ficha, :id_inst, :entrada, :salida, :fecha)";

            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
            $stmt->bindValue(':id_inst',  $idInstructor > 0 ? $idInstructor : null, PDO::PARAM_INT);
            $stmt->bindParam(':entrada',  $horaEntrada);
            $stmt->bindParam(':salida',   $horaSalida);
            $stmt->bindParam(':fecha',    $fecha);
            $stmt->execute();
        } catch (PDOException $e) {
            // Fallback si la columna id_instructor no existiera
            $sql = "INSERT INTO horarios (id_ficha, hora_entrada_programada, hora_salida_programada, dia_semana)
                    VALUES (:id_ficha, :entrada, :salida, :fecha)";

            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
            $stmt->bindParam(':entrada',  $horaEntrada);
            $stmt->bindParam(':salida',   $horaSalida);
            $stmt->bindParam(':fecha',    $fecha);
            $stmt->execute();
        }
    }

    // Actualiza un horario existente incluyendo el instructor asignado
    public function actualizarHorario(int $idHorario, int $idFicha, string $horaEntrada, string $horaSalida, string $fecha, ?int $idInstructor = null): void
    {
        // Doble verificación estricta en el modelo justo antes de ejecutar la actualización en la BD
        $existente = $this->obtenerHorarioPorId($idHorario);
        if ($existente) {
            $eval = $this->evaluarEstadoTemporal($existente);
            if ($eval['estado'] === 'pasado') {
                throw new DomainException('Esta clase ya finalizó y forma parte del historial de programación. Por seguridad, no se permite modificar horarios pasados.');
            }
            if ($eval['estado'] === 'en_curso') {
                throw new DomainException('Esta clase se está desarrollando actualmente. No es posible modificarla mientras esté en curso.');
            }
            if (!$eval['eliminable']) {
                throw new DomainException($eval['motivo']);
            }
        }

        try {
            $sql = "UPDATE horarios
                    SET id_ficha = :id_ficha,
                        id_instructor = :id_inst,
                        hora_entrada_programada = :entrada,
                        hora_salida_programada = :salida,
                        dia_semana = :fecha
                    WHERE id_horario = :id_horario";

            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->bindParam(':id_ficha',   $idFicha, PDO::PARAM_INT);
            $stmt->bindValue(':id_inst',    $idInstructor > 0 ? $idInstructor : null, PDO::PARAM_INT);
            $stmt->bindParam(':entrada',    $horaEntrada);
            $stmt->bindParam(':salida',     $horaSalida);
            $stmt->bindParam(':fecha',      $fecha);
            $stmt->bindParam(':id_horario', $idHorario, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            $sql = "UPDATE horarios
                    SET id_ficha = :id_ficha,
                        hora_entrada_programada = :entrada,
                        hora_salida_programada = :salida,
                        dia_semana = :fecha
                    WHERE id_horario = :id_horario";

            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->bindParam(':id_ficha',   $idFicha, PDO::PARAM_INT);
            $stmt->bindParam(':entrada',    $horaEntrada);
            $stmt->bindParam(':salida',     $horaSalida);
            $stmt->bindParam(':fecha',      $fecha);
            $stmt->bindParam(':id_horario', $idHorario, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // Obtiene la información detallada de un horario específico por su ID
    public function obtenerHorarioPorId(int $idHorario): ?array
    {
        $sql = "SELECT h.id_horario, h.id_ficha, h.id_instructor,
                       h.dia_semana AS fecha,
                       h.hora_entrada_programada, h.hora_salida_programada,
                       f.numero_ficha, f.nombre_programa,
                       COALESCE(u.nombre_completo, 'Sin asignar') AS instructor_nombre
                FROM horarios h
                JOIN fichas f ON f.id_ficha = h.id_ficha
                LEFT JOIN usuarios u ON u.id_usuario = h.id_instructor
                WHERE h.id_horario = :id
                LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute([':id' => $idHorario]);
        $fila = $stmt->fetch();

        if ($fila) {
            $fila['nombre_programa']   = html_entity_decode((string)($fila['nombre_programa'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $fila['instructor_nombre'] = html_entity_decode((string)($fila['instructor_nombre'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $fila;
        }

        return null;
    }

    // Cuenta cuántas asistencias están asociadas a un horario específico
    public function contarAsistenciasHorario(int $idHorario): int
    {
        $stmt = Conexion::obtener()->prepare("SELECT COUNT(*) FROM asistencias WHERE id_horario = :id");
        $stmt->execute([':id' => $idHorario]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Evalúa el estado temporal de un horario programado ('pasado', 'en_curso', 'futuro', 'indeterminado').
     * Garantiza el cumplimiento de la zona horaria del proyecto (America/Bogota).
     *
     * @param array|null $horario Datos del horario con 'fecha' (o 'dia_semana'), 'hora_entrada_programada', 'hora_salida_programada'
     * @return array{estado: string, eliminable: bool, motivo: string, ts_inicio: int|null, ts_fin: int|null}
     */
    public function evaluarEstadoTemporal(?array $horario): array
    {
        if (empty($horario)) {
            return [
                'estado'     => 'indeterminado',
                'eliminable' => false,
                'motivo'     => 'El horario no existe o los datos de la consulta están vacíos.',
                'ts_inicio'  => null,
                'ts_fin'     => null,
            ];
        }

        $fecha       = trim((string)($horario['fecha'] ?? $horario['dia_semana'] ?? ''));
        $horaEntrada = trim((string)($horario['hora_entrada_programada'] ?? ''));
        $horaSalida  = trim((string)($horario['hora_salida_programada'] ?? ''));

        // 1. Validación de integridad de los datos
        if ($fecha === '' || $horaEntrada === '' || $horaSalida === '') {
            return [
                'estado'     => 'indeterminado',
                'eliminable' => false,
                'motivo'     => 'No fue posible verificar la programación temporal debido a datos incompletos en el registro.',
                'ts_inicio'  => null,
                'ts_fin'     => null,
            ];
        }

        // 2. Si la fecha no tiene formato YYYY-MM-DD (ej. día recurrente como "Lunes")
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $idHorario = (int)($horario['id_horario'] ?? 0);
            $asistencias = ($idHorario > 0) ? $this->contarAsistenciasHorario($idHorario) : 0;
            if ($asistencias > 0) {
                return [
                    'estado'     => 'pasado',
                    'eliminable' => false,
                    'motivo'     => 'Este horario recurrente ya cuenta con asistencias registradas y forma parte del historial activo.',
                    'ts_inicio'  => null,
                    'ts_fin'     => null,
                ];
            }
            return [
                'estado'     => 'indeterminado',
                'eliminable' => false,
                'motivo'     => 'El horario no cuenta con una fecha calendario concreta verificable para determinar su estado temporal.',
                'ts_inicio'  => null,
                'ts_fin'     => null,
            ];
        }

        // 3. Conversión de fecha y hora a marcas de tiempo Unix respetando la zona horaria configurada
        $strInicio = $fecha . ' ' . $horaEntrada;
        $strFin    = $fecha . ' ' . $horaSalida;

        $tsInicio = strtotime($strInicio);
        $tsFin    = strtotime($strFin);
        $tsAhora  = time();

        if ($tsInicio === false || $tsFin === false) {
            return [
                'estado'     => 'indeterminado',
                'eliminable' => false,
                'motivo'     => 'Formato de fecha u hora no válido para la verificación temporal.',
                'ts_inicio'  => null,
                'ts_fin'     => null,
            ];
        }

        // 4. Si la hora de salida es anterior o igual a la de entrada (ej. cruce de medianoche o error en horas)
        if ($tsFin <= $tsInicio) {
            $tsFin += 86400;
        }

        // 5. Evaluación del estado según la hora actual
        if ($tsAhora >= $tsFin) {
            return [
                'estado'     => 'pasado',
                'eliminable' => false,
                'motivo'     => 'Este horario ya finalizó y forma parte del historial de programación. Por seguridad, no se permite eliminar horarios pasados.',
                'ts_inicio'  => $tsInicio,
                'ts_fin'     => $tsFin,
            ];
        }

        if ($tsAhora >= $tsInicio && $tsAhora < $tsFin) {
            return [
                'estado'     => 'en_curso',
                'eliminable' => false,
                'motivo'     => 'Esta clase se encuentra actualmente en curso. Por seguridad y consistencia operativa, no se permite eliminar horarios activos.',
                'ts_inicio'  => $tsInicio,
                'ts_fin'     => $tsFin,
            ];
        }

        // 6. Horario futuro: comprobar si existen asistencias previas
        $idHorario = (int)($horario['id_horario'] ?? 0);
        if ($idHorario > 0 && $this->contarAsistenciasHorario($idHorario) > 0) {
            return [
                'estado'     => 'pasado',
                'eliminable' => false,
                'motivo'     => 'Este horario ya cuenta con registros de asistencia vinculados y forma parte del historial académico.',
                'ts_inicio'  => $tsInicio,
                'ts_fin'     => $tsFin,
            ];
        }

        return [
            'estado'     => 'futuro',
            'eliminable' => true,
            'motivo'     => 'Horario programado para una fecha y hora futura.',
            'ts_inicio'  => $tsInicio,
            'ts_fin'     => $tsFin,
        ];
    }

    // Elimina un horario por su ID desvinculando de forma segura cualquier asistencia
    public function eliminarHorario(int $idHorario): bool
    {
        $db = Conexion::obtener();
        $iniciadaAqui = false;

        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $iniciadaAqui = true;
        }

        try {
            // 1. Desvincular de forma segura las asistencias asociadas preservando su registro histórico
            $stmtAsist = $db->prepare("UPDATE asistencias SET id_horario = NULL WHERE id_horario = :id");
            $stmtAsist->execute([':id' => $idHorario]);

            // 2. Eliminar el horario programado
            $stmt = $db->prepare("DELETE FROM horarios WHERE id_horario = :id");
            $res = $stmt->execute([':id' => $idHorario]);

            if ($iniciadaAqui) {
                $db->commit();
            }

            return $res;
        } catch (\Throwable $e) {
            if ($iniciadaAqui && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }


    // Lista instructores activos para el selector del formulario
    public function listarInstructores(): array
    {
        $sql = "SELECT id_usuario, nombre_completo, usuario
                FROM usuarios
                WHERE id_role = 2 AND estado = 'Activo'
                ORDER BY nombre_completo ASC";

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    // Busca un instructor existente por coincidencia de nombre o lo crea automáticamente.
    public function obtenerOCrearInstructor(string $nombreCompletoRaw, string $areaRaw = ''): array
    {
        $nombreCompleto = trim(html_entity_decode($nombreCompletoRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $area = trim(html_entity_decode($areaRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // Ignorar días festivos o recesos que puedan venir en la celda del instructor
        $terminosIgnorar = ['festivo', 'festivos', 'vacaciones', 'receso', 'semana santa', 'feriado', 'sin instructor', 'por definir'];
        if (in_array(mb_strtolower($nombreCompleto, 'UTF-8'), $terminosIgnorar, true) || $nombreCompleto === '') {
            return [
                'id_usuario' => 0,
                'nombre'     => $nombreCompleto,
                'es_nuevo'   => false,
                'ignorar'    => true
            ];
        }

        $instructores = $this->listarInstructores();

        // Se busca primero si el instructor ya existe en la base de datos
        $encontrado = $this->buscarInstructorEnLista($nombreCompleto, $instructores);
        if ($encontrado) {
            return [
                'id_usuario' => (int) $encontrado['id_usuario'],
                'nombre'     => $encontrado['nombre_completo'],
                'es_nuevo'   => false
            ];
        }

        // Generar nombre de usuario normalizado a partir de letras sin acentos
        $usuarioBase = strtolower($this->normalizarTexto($nombreCompleto));
        $usuarioBase = preg_replace('/[^a-z0-9]/', '', $usuarioBase);
        if ($usuarioBase === '') {
            $usuarioBase = 'inst_' . time();
        }

        if ($area === '') {
            $area = 'TICS';
        } elseif (mb_strlen($area) > 45) {
            $area = mb_substr($area, 0, 45);
        }

        $db = Conexion::obtener();
        $passHash = password_hash('123', PASSWORD_BCRYPT);

        // Garantizar unicidad de usuario y correo para cumplir con restricciones NOT NULL y UNIQUE
        $usuarioGenerado = substr($usuarioBase, 0, 40);
        $correoGenerado  = substr($usuarioBase, 0, 30) . '@sena.edu.co';
        $i = 1;
        while (true) {
            $chk = $db->prepare("SELECT id_usuario FROM usuarios WHERE usuario = :u OR correo_electronico = :c");
            $chk->execute([':u' => $usuarioGenerado, ':c' => $correoGenerado]);
            if (!$chk->fetch()) {
                break;
            }
            $usuarioGenerado = substr($usuarioBase, 0, 35) . '_' . $i;
            $correoGenerado  = substr($usuarioBase, 0, 25) . '_' . $i . '@sena.edu.co';
            $i++;
        }

        // Al importar del Excel se crea el usuario básico
        $sql = "INSERT INTO usuarios (nombre_completo, usuario, area, correo_electronico, estado, contrasena, id_role)
                VALUES (:nombre, :usuario, :area, :correo, 'Activo', :pass, 2)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':nombre',   $nombreCompleto);
        $stmt->bindParam(':usuario',  $usuarioGenerado);
        $stmt->bindParam(':area',     $area);
        $stmt->bindParam(':correo',   $correoGenerado);
        $stmt->bindParam(':pass',     $passHash);
        $stmt->execute();

        $idNuevo = (int) $db->lastInsertId();

        return [
            'id_usuario' => $idNuevo,
            'nombre'     => $nombreCompleto,
            'es_nuevo'   => true
        ];
    }

    public function buscarInstructorEnLista(string $texto, array $instructores): ?array
    {
        $lineas = preg_split('/[\r\n]+/', trim($texto));
        $nombreBuscar = $this->normalizarTexto(html_entity_decode($lineas[0] ?? $texto, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($nombreBuscar === '') return null;

        // 1. Coincidencia directa del nombre normalizado completo
        foreach ($instructores as $inst) {
            $nomInst = $this->normalizarTexto($inst['nombre_completo']);
            if ($nombreBuscar === $nomInst) {
                return $inst;
            }
        }

        // 2. Coincidencia por palabras completas (evitando falsos positivos de subcadenas como 'ana' en 'juliana')
        $palabrasBuscar = array_filter(explode(' ', $nombreBuscar), fn($p) => mb_strlen($p) >= 3);
        if (count($palabrasBuscar) >= 2) {
            foreach ($instructores as $inst) {
                $nomInst = $this->normalizarTexto($inst['nombre_completo']);
                $todasCoinciden = true;
                foreach ($palabrasBuscar as $palabra) {
                    if (!preg_match('/\b' . preg_quote($palabra, '/') . '\b/u', $nomInst)) {
                        $todasCoinciden = false;
                        break;
                    }
                }
                if ($todasCoinciden) {
                    return $inst;
                }
            }
        }

        // 3. Similitud léxica alta (> 85%) con longitud mínima
        if (mb_strlen($nombreBuscar) >= 5) {
            foreach ($instructores as $inst) {
                $nomInst = $this->normalizarTexto($inst['nombre_completo']);
                similar_text($nombreBuscar, $nomInst, $percent);
                if ($percent >= 85) {
                    return $inst;
                }
            }
        }

        return null;
    }

    public function normalizarTexto(string $str): string
    {
        $str = html_entity_decode(trim($str), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $str = mb_strtolower($str, 'UTF-8');
        $str = str_replace(
            ['á','é','í','ó','ú','ñ','ü','_','-','.',',','(',')',':',';','/'],
            ['a','e','i','o','u','n','u',' ',' ',' ',' ',' ',' ',' ',' ',' '],
            $str
        );
        return preg_replace('/\s+/', ' ', $str);
    }

    // Lista fichas delegando en FichaModelo
    public function listarFichasDetalladas(?int $idInstructor = null): array
    {
        return $this->fichaModelo->listarFichasDetalladas($idInstructor);
    }

    // Actualiza los datos de una ficha existente delegando en FichaModelo
    public function actualizarFicha(int $idFicha, string $numeroFicha, string $nombrePrograma): void
    {
        $this->fichaModelo->actualizarFicha($idFicha, $numeroFicha, $nombrePrograma);
    }

    // Consulta horarios con filtros opcionales para la exportación a Excel
    public function listarHorariosParaExportar(?int $idFicha = null, ?int $idInstructor = null, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $sql = "SELECT
                    h.id_horario,
                    h.id_ficha,
                    h.dia_semana AS fecha,
                    h.hora_entrada_programada,
                    h.hora_salida_programada,
                    f.numero_ficha,
                    f.nombre_programa,
                    COALESCE(u_directo.nombre_completo, u_ficha.nombre_completo, 'Sin asignar') AS instructor_nombre,
                    COALESCE(u_directo.area, u_ficha.area, 'General') AS instructor_area,
                    (SELECT COUNT(*) FROM aprendices a WHERE a.id_ficha = f.id_ficha) AS total_aprendices
                FROM horarios h
                JOIN fichas f ON f.id_ficha = h.id_ficha
                LEFT JOIN usuarios u_directo ON u_directo.id_usuario = h.id_instructor
                LEFT JOIN (
                    SELECT fichas_id_ficha, MIN(id_instructor) AS id_instructor
                    FROM fichas_has_usuarios
                    GROUP BY fichas_id_ficha
                ) fhu ON fhu.fichas_id_ficha = f.id_ficha
                LEFT JOIN usuarios u_ficha ON u_ficha.id_usuario = fhu.id_instructor
                WHERE 1=1";

        $params = [];

        if ($idFicha !== null && $idFicha > 0) {
            $sql .= " AND h.id_ficha = :id_ficha";
            $params[':id_ficha'] = $idFicha;
        }

        if ($idInstructor !== null && $idInstructor > 0) {
            $sql .= " AND (h.id_instructor = :id_inst OR (h.id_instructor IS NULL AND fhu.id_instructor = :id_inst))";
            $params[':id_inst'] = $idInstructor;
        }

        if ($fechaDesde !== null && $fechaDesde !== '') {
            $sql .= " AND h.dia_semana >= :desde";
            $params[':desde'] = $fechaDesde;
        }

        if ($fechaHasta !== null && $fechaHasta !== '') {
            $sql .= " AND h.dia_semana <= :hasta";
            $params[':hasta'] = $fechaHasta;
        }

        $sql .= " ORDER BY h.dia_semana ASC, h.hora_entrada_programada ASC, f.numero_ficha ASC";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        foreach ($filas as &$f) {
            $f['nombre_programa']  = html_entity_decode((string)($f['nombre_programa'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['instructor_nombre'] = html_entity_decode((string)($f['instructor_nombre'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['instructor_area']   = html_entity_decode((string)($f['instructor_area'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        unset($f);

        return $filas;
    }

    // Registra un día festivo en la base de datos de forma segura
    public function guardarFestivo(?int $idFicha, string $fecha, string $horaInicio = '06:00:00', string $horaFin = '22:00:00', int $esDiaCompleto = 1, string $descripcion = 'FESTIVO'): void
    {
        $sql = "INSERT INTO dias_festivos (id_ficha, fecha, hora_inicio, hora_fin, es_dia_completo, descripcion)
                VALUES (:id_ficha, :fecha, :hora_inicio, :hora_fin, :es_dia_completo, :descripcion)";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindValue(':id_ficha', $idFicha > 0 ? $idFicha : null, PDO::PARAM_INT);
        $stmt->bindValue(':fecha', $fecha);
        $stmt->bindValue(':hora_inicio', $horaInicio);
        $stmt->bindValue(':hora_fin', $horaFin);
        $stmt->bindValue(':es_dia_completo', $esDiaCompleto, PDO::PARAM_INT);
        $stmt->bindValue(':descripcion', $descripcion);
        $stmt->execute();
    }

    // Consulta festivos registrados con filtros opcionales
    public function listarFestivos(?int $idFicha = null, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $sql = "SELECT df.*, f.numero_ficha, f.nombre_programa
                FROM dias_festivos df
                LEFT JOIN fichas f ON f.id_ficha = df.id_ficha
                WHERE 1=1";
        $params = [];

        if ($idFicha !== null && $idFicha > 0) {
            $sql .= " AND (df.id_ficha = :id_ficha OR df.id_ficha IS NULL)";
            $params[':id_ficha'] = $idFicha;
        }

        if ($fechaDesde !== null && $fechaDesde !== '') {
            $sql .= " AND df.fecha >= :desde";
            $params[':desde'] = $fechaDesde;
        }

        if ($fechaHasta !== null && $fechaHasta !== '') {
            $sql .= " AND df.fecha <= :hasta";
            $params[':hasta'] = $fechaHasta;
        }

        $sql .= " ORDER BY df.fecha ASC, df.hora_inicio ASC";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Comprueba si un festivo ya fue registrado para una fecha y ficha
    public function existeFestivo(?int $idFicha, string $fecha, string $horaInicio = '06:00:00', string $horaFin = '22:00:00'): bool
    {
        $sql = "SELECT id_festivo FROM dias_festivos
                WHERE fecha = :fecha
                  AND hora_inicio = :h_ini
                  AND hora_fin = :h_fin
                  AND ((:id_ficha IS NULL AND id_ficha IS NULL) OR id_ficha = :id_ficha2)
                LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindValue(':fecha', $fecha);
        $stmt->bindValue(':h_ini', $horaInicio);
        $stmt->bindValue(':h_fin', $horaFin);
        $stmt->bindValue(':id_ficha', $idFicha > 0 ? $idFicha : null, PDO::PARAM_INT);
        $stmt->bindValue(':id_ficha2', $idFicha > 0 ? $idFicha : null, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }
}

