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
    // Soporta filtrado por instructor, intervalo de fechas y separación de horarios operativos vs históricos/cancelados.
    public function listarHorarios(?int $idInstructor = null, ?string $fechaDesde = null, ?string $fechaHasta = null, bool $incluirCancelados = false): array
    {
        $params = [];
        $whereFiltros = "";

        if ($fechaDesde !== null && $fechaDesde !== '') {
            $whereFiltros .= " AND h.dia_semana >= :desde";
            $params[':desde'] = $fechaDesde;
        }

        if ($fechaHasta !== null && $fechaHasta !== '') {
            $whereFiltros .= " AND h.dia_semana <= :hasta";
            $params[':hasta'] = $fechaHasta;
        }

        // En vistas operativas ordinarias, solo se muestran horarios programados de fichas activas
        if (!$incluirCancelados) {
            $whereFiltros .= " AND h.estado = 'Programado' AND f.estado = 'Activa'";
        }

        if ($idInstructor !== null && $idInstructor > 0) {
            $sql = "SELECT
                        h.id_horario,
                        h.id_ficha,
                        h.hora_entrada_programada,
                        h.hora_salida_programada,
                        h.dia_semana AS fecha,
                        h.estado AS estado_horario,
                        h.fecha_cancelacion,
                        h.motivo_cancelacion,
                        f.numero_ficha,
                        f.nombre_programa,
                        f.estado AS estado_ficha,
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
                    WHERE (h.id_instructor = :id_inst1 OR (h.id_instructor IS NULL AND fhu.id_instructor = :id_inst2))
                          {$whereFiltros}
                    ORDER BY h.dia_semana ASC, h.hora_entrada_programada ASC";

            $params[':id_inst1'] = $idInstructor;
            $params[':id_inst2'] = $idInstructor;
            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT
                        h.id_horario,
                        h.id_ficha,
                        h.hora_entrada_programada,
                        h.hora_salida_programada,
                        h.dia_semana AS fecha,
                        h.estado AS estado_horario,
                        h.fecha_cancelacion,
                        h.motivo_cancelacion,
                        f.numero_ficha,
                        f.nombre_programa,
                        f.estado AS estado_ficha,
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
                    WHERE 1=1 {$whereFiltros}
                    ORDER BY h.dia_semana ASC, h.hora_entrada_programada ASC";

            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Saneamiento de entidades HTML para garantizar salida limpia UTF-8
        foreach ($filas as &$f) {
            $f['nombre_programa']   = html_entity_decode((string)($f['nombre_programa'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['instructor_nombre'] = html_entity_decode((string)($f['instructor_nombre'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['instructor_area']   = html_entity_decode((string)($f['instructor_area'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        unset($f);

        return $filas;
    }

    /**
     * Obtiene los datos del aprendiz y su ficha asociada a partir de su ID de usuario en sesión.
     */
    public function obtenerInfoAprendizPorUsuario(int $idUsuario): ?array
    {
        $db = Conexion::obtener();
        $sql = "SELECT 
                    a.id_aprendiz,
                    a.id_usuario,
                    a.id_ficha,
                    u.nombre_completo,
                    u.usuario AS documento,
                    f.numero_ficha,
                    f.nombre_programa,
                    f.estado AS estado_ficha
                FROM aprendices a
                JOIN usuarios u ON u.id_usuario = a.id_usuario
                LEFT JOIN fichas f ON f.id_ficha = a.id_ficha
                WHERE a.id_usuario = :id_usuario
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id_usuario' => $idUsuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila && !empty($_SESSION['usuario'])) {
            $sqlFallback = "SELECT 
                                a.id_aprendiz,
                                a.id_usuario,
                                a.id_ficha,
                                u.nombre_completo,
                                u.usuario AS documento,
                                f.numero_ficha,
                                f.nombre_programa,
                                f.estado AS estado_ficha
                            FROM usuarios u
                            LEFT JOIN aprendices a ON a.id_usuario = u.id_usuario
                            LEFT JOIN fichas f ON f.id_ficha = a.id_ficha
                            WHERE u.usuario = :usuario
                            LIMIT 1";
            $stmtFallback = $db->prepare($sqlFallback);
            $stmtFallback->execute([':usuario' => (string)$_SESSION['usuario']]);
            $fila = $stmtFallback->fetch(PDO::FETCH_ASSOC);
        }

        if ($fila) {
            $fila['nombre_programa'] = html_entity_decode((string)($fila['nombre_programa'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $fila['nombre_completo'] = html_entity_decode((string)($fila['nombre_completo'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $fila;
        }

        return null;
    }

    /**
     * Lista los horarios programados correspondientes a una ficha específica.
     * En consultas ordinarias de aprendices solo incluye horarios 'Programado' de fichas con estado 'Activa'
     * y omite el conteo de aprendices salvo cuando se solicita explícitamente.
     */
    public function listarHorariosPorFicha(
        int $idFicha,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $incluirCancelados = false,
        bool $incluirTotalAprendices = false
    ): array {
        $params = [
            ':id_ficha'     => $idFicha,
            ':id_ficha_fhu' => $idFicha,
        ];
        $whereFiltros = "";

        if ($fechaDesde !== null && $fechaDesde !== '') {
            $whereFiltros .= " AND h.dia_semana >= :desde";
            $params[':desde'] = $fechaDesde;
        }

        if ($fechaHasta !== null && $fechaHasta !== '') {
            $whereFiltros .= " AND h.dia_semana <= :hasta";
            $params[':hasta'] = $fechaHasta;
        }

        if (!$incluirCancelados) {
            $whereFiltros .= " AND h.estado = 'Programado' AND f.estado = 'Activa'";
        }

        $selectTotalAprendices = $incluirTotalAprendices
            ? "(SELECT COUNT(*) FROM aprendices a WHERE a.id_ficha = f.id_ficha) AS total_aprendices"
            : "0 AS total_aprendices";

        $sql = "SELECT
                    h.id_horario,
                    h.id_ficha,
                    h.hora_entrada_programada,
                    h.hora_salida_programada,
                    h.dia_semana AS fecha,
                    h.estado AS estado_horario,
                    h.fecha_cancelacion,
                    h.motivo_cancelacion,
                    f.numero_ficha,
                    f.nombre_programa,
                    f.estado AS estado_ficha,
                    COALESCE(h.id_instructor, fhu.id_instructor) AS id_instructor,
                    COALESCE(u_directo.nombre_completo, u_ficha.nombre_completo, '') AS instructor_nombre,
                    COALESCE(u_directo.area, u_ficha.area, '') AS instructor_area,
                    {$selectTotalAprendices}
                FROM horarios h
                JOIN fichas f ON f.id_ficha = h.id_ficha
                LEFT JOIN usuarios u_directo ON u_directo.id_usuario = h.id_instructor
                LEFT JOIN (
                    SELECT fichas_id_ficha, MIN(id_instructor) AS id_instructor
                    FROM fichas_has_usuarios
                    WHERE fichas_id_ficha = :id_ficha_fhu
                    GROUP BY fichas_id_ficha
                ) fhu ON fhu.fichas_id_ficha = f.id_ficha
                LEFT JOIN usuarios u_ficha ON u_ficha.id_usuario = fhu.id_instructor
                WHERE h.id_ficha = :id_ficha
                      {$whereFiltros}
                ORDER BY h.dia_semana ASC, h.hora_entrada_programada ASC";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$f) {
            $f['nombre_programa']    = html_entity_decode((string)($f['nombre_programa'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['instructor_nombre']  = html_entity_decode((string)($f['instructor_nombre'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['instructor_area']    = html_entity_decode((string)($f['instructor_area'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['motivo_cancelacion'] = html_entity_decode((string)($f['motivo_cancelacion'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
    // Valida en backend que la ficha esté en estado Activa
    public function guardarHorario(int $idFicha, string $horaEntrada, string $horaSalida, string $fecha, ?int $idInstructor = null): void
    {
        $ficha = $this->fichaModelo->buscarFichaPorId($idFicha);
        if (!$ficha) {
            throw new InvalidArgumentException("La ficha especificada no existe.");
        }

        if ($ficha['estado'] !== 'Activa') {
            throw new DomainException("No es posible programar horarios para la ficha {$ficha['numero_ficha']} porque se encuentra en estado '{$ficha['estado']}'. Solo se admiten horarios en fichas activas.");
        }

        $sql = "INSERT INTO horarios (id_ficha, id_instructor, hora_entrada_programada, hora_salida_programada, dia_semana, estado)
                VALUES (:id_ficha, :id_inst, :entrada, :salida, :fecha, 'Programado')";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
        $stmt->bindValue(':id_inst',  $idInstructor > 0 ? $idInstructor : null, PDO::PARAM_INT);
        $stmt->bindParam(':entrada',  $horaEntrada);
        $stmt->bindParam(':salida',   $horaSalida);
        $stmt->bindParam(':fecha',    $fecha);
        $stmt->execute();
    }

    // Actualiza un horario existente incluyendo el instructor asignado
    // Valida que la ficha esté Activa y que el horario no esté Cancelado ni finalizado
    public function actualizarHorario(int $idHorario, int $idFicha, string $horaEntrada, string $horaSalida, string $fecha, ?int $idInstructor = null): void
    {
        $ficha = $this->fichaModelo->buscarFichaPorId($idFicha);
        if (!$ficha) {
            throw new InvalidArgumentException("La ficha especificada no existe.");
        }

        if ($ficha['estado'] !== 'Activa') {
            throw new DomainException("No es posible modificar horarios de la ficha {$ficha['numero_ficha']} porque se encuentra en estado '{$ficha['estado']}'.");
        }

        $existente = $this->obtenerHorarioPorId($idHorario);
        if ($existente) {
            if (($existente['estado_horario'] ?? '') === 'Cancelado') {
                throw new DomainException('Este horario se encuentra cancelado. No es posible modificar horarios cancelados.');
            }

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

        $sql = "UPDATE horarios
                SET id_ficha = :id_ficha,
                    id_instructor = :id_inst,
                    hora_entrada_programada = :entrada,
                    hora_salida_programada = :salida,
                    dia_semana = :fecha
                WHERE id_horario = :id_horario AND estado = 'Programado'";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_ficha',   $idFicha, PDO::PARAM_INT);
        $stmt->bindValue(':id_inst',    $idInstructor > 0 ? $idInstructor : null, PDO::PARAM_INT);
        $stmt->bindParam(':entrada',    $horaEntrada);
        $stmt->bindParam(':salida',     $horaSalida);
        $stmt->bindParam(':fecha',      $fecha);
        $stmt->bindParam(':id_horario', $idHorario, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Cancela un horario programado preservando su registro y relación con asistencias históricas
    public function cancelarHorario(int $idHorario, string $motivo, int $idUsuario = 0): bool
    {
        $horario = $this->obtenerHorarioPorId($idHorario);
        if (!$horario) {
            throw new InvalidArgumentException("El horario a cancelar no existe.");
        }

        if (($horario['estado_horario'] ?? '') === 'Cancelado') {
            return true; // Idempotente
        }

        $sql = "UPDATE horarios 
                SET estado = 'Cancelado', 
                    fecha_cancelacion = NOW(), 
                    motivo_cancelacion = :motivo 
                WHERE id_horario = :id";
        $stmt = Conexion::obtener()->prepare($sql);
        return $stmt->execute([
            ':motivo' => trim($motivo) !== '' ? trim($motivo) : 'Cancelación de sesión de clase',
            ':id'     => $idHorario
        ]);
    }

    // Obtiene la información detallada de un horario específico por su ID incluyendo estado y metadatos
    public function obtenerHorarioPorId(int $idHorario): ?array
    {
        $sql = "SELECT h.id_horario, h.id_ficha, h.id_instructor,
                       h.dia_semana AS fecha,
                       h.hora_entrada_programada, h.hora_salida_programada,
                       h.estado AS estado_horario,
                       h.fecha_cancelacion,
                       h.motivo_cancelacion,
                       f.numero_ficha, f.nombre_programa,
                       f.estado AS estado_ficha,
                       COALESCE(u.nombre_completo, 'Sin asignar') AS instructor_nombre
                FROM horarios h
                JOIN fichas f ON f.id_ficha = h.id_ficha
                LEFT JOIN usuarios u ON u.id_usuario = h.id_instructor
                WHERE h.id_horario = :id
                LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute([':id' => $idHorario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

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
     * Evalúa el estado temporal y operativo de un horario programado.
     * Posibles estados: 'cancelado', 'pasado', 'en_curso', 'futuro', 'indeterminado'.
     *
     * @param array|null $horario Datos del horario
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

        // Si el horario está explícitamente cancelado
        $estadoHor = $horario['estado_horario'] ?? $horario['estado'] ?? 'Programado';
        if ($estadoHor === 'Cancelado') {
            return [
                'estado'     => 'cancelado',
                'eliminable' => false,
                'motivo'     => 'Este horario ha sido cancelado y no admite modificaciones operativas.',
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
        if ($area !== '' && (strpos($area, '-') !== false || strpos($area, '–') !== false || strpos($area, '—') !== false)) {
            $partesArea = preg_split('/[-–—]/u', $area);
            $area = trim($partesArea[0] ?? '');
        }

        // Ignorar días festivos, recesos o tokens puramente numéricos/de relleno que puedan venir en la celda del instructor
        $terminosIgnorar = ['festivo', 'festivos', 'vacaciones', 'receso', 'semana santa', 'feriado', 'sin instructor', 'por definir', '123'];
        if (in_array(mb_strtolower($nombreCompleto, 'UTF-8'), $terminosIgnorar, true)
            || $nombreCompleto === ''
            || preg_match('/^\d+$/', $nombreCompleto)) {
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
            $usuarioBase = 'inst_' . bin2hex(random_bytes(4));
        }

        if ($area === '') {
            $area = 'TICS';
        } elseif (mb_strlen($area) > 45) {
            $area = mb_substr($area, 0, 45);
        }

        $db = Conexion::obtener();
        // Credencial temporal aleatoria de alta entropía; nunca se asignan contraseñas débiles predecibles
        $contrasenaAleatoria = bin2hex(random_bytes(16));
        $passHash = password_hash($contrasenaAleatoria, PASSWORD_DEFAULT);

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

    /**
     * Normaliza franja horaria y descripción de un día festivo para alinear inserciones
     * con las columnas virtuales normalizadas de `dias_festivos`.
     */
    private function normalizarFranjaFestivo(?string $horaInicio, ?string $horaFin, int $esDiaCompleto): array
    {
        $hIni = trim((string) $horaInicio);
        $hFin = trim((string) $horaFin);

        if ($hIni === '') {
            $hIni = '06:00:00';
        } elseif (strlen($hIni) === 5) {
            $hIni .= ':00';
        }

        if ($hFin === '') {
            $hFin = '22:00:00';
        } elseif (strlen($hFin) === 5) {
            $hFin .= ':00';
        }

        return [$hIni, $hFin, $esDiaCompleto ? 1 : 0];
    }

    /**
     * Registra o sincroniza un día festivo de forma idempotente usando la restricción única
     * `uq_festivo_fecha_ficha_franja` (sin duplicar filas ante reimportaciones).
     *
     * @return string 'insertado' | 'actualizado' | 'sin_cambios'
     */
    public function guardarFestivo(
        ?int $idFicha,
        string $fecha,
        string $horaInicio = '06:00:00',
        string $horaFin = '22:00:00',
        int $esDiaCompleto = 1,
        string $descripcion = 'FESTIVO'
    ): string {
        $idFichaNorm = ($idFicha !== null && $idFicha > 0) ? $idFicha : null;
        [$hIni, $hFin, $esDia] = $this->normalizarFranjaFestivo($horaInicio, $horaFin, $esDiaCompleto);
        $descLimpia = trim($descripcion);
        if ($descLimpia === '') {
            $descLimpia = 'FESTIVO';
        } elseif (mb_strlen($descLimpia) > 150) {
            $descLimpia = mb_substr($descLimpia, 0, 150);
        }

        $sql = "INSERT INTO dias_festivos (id_ficha, fecha, hora_inicio, hora_fin, es_dia_completo, descripcion)
                VALUES (:id_ficha, :fecha, :hora_inicio, :hora_fin, :es_dia_completo, :descripcion)
                ON DUPLICATE KEY UPDATE
                    es_dia_completo = VALUES(es_dia_completo),
                    descripcion = VALUES(descripcion)";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindValue(':id_ficha', $idFichaNorm, $idFichaNorm === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':fecha', $fecha);
        $stmt->bindValue(':hora_inicio', $hIni);
        $stmt->bindValue(':hora_fin', $hFin);
        $stmt->bindValue(':es_dia_completo', $esDia, PDO::PARAM_INT);
        $stmt->bindValue(':descripcion', $descLimpia);
        $stmt->execute();

        $afectadas = $stmt->rowCount();
        if ($afectadas === 1) {
            return 'insertado';
        }
        if ($afectadas >= 2) {
            return 'actualizado';
        }
        return 'sin_cambios';
    }

    /**
     * Sincroniza un lote de días festivos en BD dentro de una transacción evitando consultas N+1.
     * Pre-carga las fechas del rango y ejecuta un único statement preparado ON DUPLICATE KEY UPDATE.
     */
    public function sincronizarFestivosEnLote(array $festivos): array
    {
        $resumen = [
            'nuevos'      => 0,
            'sin_cambios' => 0,
            'actualizados'=> 0,
            'invalidos'   => 0,
            'detalles'    => [],
        ];

        if (empty($festivos)) {
            return $resumen;
        }

        // Deduplicar y validar en memoria primero
        $unicos = [];
        $fechasValidas = [];
        foreach ($festivos as $fest) {
            $fecha = trim((string) ($fest['fecha'] ?? ''));
            $dt = DateTime::createFromFormat('Y-m-d', $fecha);
            if (!$dt || $dt->format('Y-m-d') !== $fecha) {
                $resumen['invalidos']++;
                continue;
            }

            $idFichaRaw = $fest['id_ficha'] ?? null;
            $idFicha = ($idFichaRaw !== null && (int) $idFichaRaw > 0) ? (int) $idFichaRaw : null;
            $esDia = isset($fest['es_dia_completo']) ? (int) $fest['es_dia_completo'] : 1;
            [$hIni, $hFin, $esDiaNorm] = $this->normalizarFranjaFestivo(
                $fest['hora_inicio'] ?? '06:00:00',
                $fest['hora_fin'] ?? '22:00:00',
                $esDia
            );

            if ($hIni >= $hFin) {
                $resumen['invalidos']++;
                continue;
            }

            $desc = trim((string) ($fest['descripcion'] ?? 'FESTIVO'));
            if ($desc === '') {
                $desc = 'FESTIVO';
            } elseif (mb_strlen($desc) > 150) {
                $desc = mb_substr($desc, 0, 150);
            }

            $clave = $fecha . '|' . ($idFicha ?? 0) . '|' . $hIni . '|' . $hFin;
            $unicos[$clave] = [
                'id_ficha'        => $idFicha,
                'fecha'           => $fecha,
                'hora_inicio'     => $hIni,
                'hora_fin'        => $hFin,
                'es_dia_completo' => $esDiaNorm,
                'descripcion'     => $desc,
            ];
            $fechasValidas[$fecha] = true;
        }

        if (empty($unicos)) {
            return $resumen;
        }

        $db = Conexion::obtener();
        $transaccionPropia = !$db->inTransaction();
        if ($transaccionPropia) {
            $db->beginTransaction();
        }

        try {
            // Precargar festivos existentes del rango en 1 sola consulta
            $listaFechas = array_keys($fechasValidas);
            sort($listaFechas);
            $fechaMin = $listaFechas[0];
            $fechaMax = $listaFechas[count($listaFechas) - 1];

            $stmtPre = $db->prepare("
                SELECT id_festivo,
                       fecha,
                       COALESCE(id_ficha, 0) AS id_ficha_norm,
                       COALESCE(hora_inicio, '00:00:00') AS hi_norm,
                       COALESCE(hora_fin, '23:59:59') AS hf_norm,
                       es_dia_completo,
                       descripcion
                FROM dias_festivos
                WHERE fecha BETWEEN :desde AND :hasta
            ");
            $stmtPre->execute([':desde' => $fechaMin, ':hasta' => $fechaMax]);
            $existentes = [];
            foreach ($stmtPre->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $k = $row['fecha'] . '|' . (int) $row['id_ficha_norm'] . '|' . $row['hi_norm'] . '|' . $row['hf_norm'];
                $existentes[$k] = $row;
            }

            $stmtUpsert = $db->prepare("
                INSERT INTO dias_festivos (id_ficha, fecha, hora_inicio, hora_fin, es_dia_completo, descripcion)
                VALUES (:id_ficha, :fecha, :hora_inicio, :hora_fin, :es_dia_completo, :descripcion)
                ON DUPLICATE KEY UPDATE
                    es_dia_completo = VALUES(es_dia_completo),
                    descripcion = VALUES(descripcion)
            ");

            foreach ($unicos as $clave => $item) {
                $prev = $existentes[$clave] ?? null;
                if ($prev !== null
                    && (int) $prev['es_dia_completo'] === $item['es_dia_completo']
                    && trim((string) $prev['descripcion']) === $item['descripcion']) {
                    $resumen['sin_cambios']++;
                    continue;
                }

                $stmtUpsert->bindValue(':id_ficha', $item['id_ficha'], $item['id_ficha'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmtUpsert->bindValue(':fecha', $item['fecha']);
                $stmtUpsert->bindValue(':hora_inicio', $item['hora_inicio']);
                $stmtUpsert->bindValue(':hora_fin', $item['hora_fin']);
                $stmtUpsert->bindValue(':es_dia_completo', $item['es_dia_completo'], PDO::PARAM_INT);
                $stmtUpsert->bindValue(':descripcion', $item['descripcion']);
                $stmtUpsert->execute();

                if ($prev === null) {
                    $resumen['nuevos']++;
                    $resumen['detalles'][] = "Festivo oficial institucional registrado para el día {$item['fecha']} ({$item['hora_inicio']} - {$item['hora_fin']}).";
                } else {
                    $resumen['actualizados']++;
                    $resumen['detalles'][] = "Festivo oficial actualizado para el día {$item['fecha']} ({$item['hora_inicio']} - {$item['hora_fin']}).";
                }
            }

            if ($transaccionPropia) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($transaccionPropia && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $resumen;
    }

    // Consulta festivos registrados con filtros opcionales (columnas explícitas)
    public function listarFestivos(?int $idFicha = null, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $sql = "SELECT df.id_festivo,
                       df.id_ficha,
                       df.fecha,
                       df.hora_inicio,
                       df.hora_fin,
                       df.es_dia_completo,
                       df.descripcion,
                       df.created_at,
                       f.numero_ficha,
                       f.nombre_programa
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

        $sql .= " ORDER BY df.fecha ASC, df.hora_inicio ASC, df.id_festivo ASC";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Comprueba si ya existe un festivo registrado para una fecha determinada (a nivel general o institucional)
    public function existeFestivoParaFecha(string $fecha): bool
    {
        $sql = "SELECT id_festivo FROM dias_festivos WHERE fecha = :fecha LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute([':fecha' => $fecha]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Comprueba si existe un festivo vigente para la fecha y ficha indicadas (o institucional global).
     * Evita fugas cruzadas entre fichas distintas.
     */
    public function existeFestivo(?int $idFicha, string $fecha, ?string $horaInicio = null, ?string $horaFin = null): bool
    {
        $idFichaNorm = ($idFicha !== null && $idFicha > 0) ? $idFicha : null;

        if ($idFichaNorm !== null) {
            $sql = "SELECT id_festivo FROM dias_festivos
                    WHERE fecha = :fecha
                      AND (id_ficha IS NULL OR id_ficha = 0 OR id_ficha = :id_ficha)";
        } else {
            $sql = "SELECT id_festivo FROM dias_festivos
                    WHERE fecha = :fecha
                      AND (id_ficha IS NULL OR id_ficha = 0)";
        }

        if ($horaInicio !== null && $horaFin !== null) {
            $sql .= " AND (
                es_dia_completo = 1
                OR hora_inicio IS NULL
                OR hora_fin IS NULL
                OR (:h_ini < hora_fin AND :h_fin > hora_inicio)
            )";
        }

        $sql .= " LIMIT 1";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindValue(':fecha', $fecha);
        if ($idFichaNorm !== null) {
            $stmt->bindValue(':id_ficha', $idFichaNorm, PDO::PARAM_INT);
        }

        if ($horaInicio !== null && $horaFin !== null) {
            $stmt->bindValue(':h_ini', $horaInicio);
            $stmt->bindValue(':h_fin', $horaFin);
        }

        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }
}

