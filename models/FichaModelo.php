<?php
// models/FichaModelo.php — Acceso a datos y lógica del Módulo de Fichas
// Gestiona el ciclo de vida de las fichas (Activa, Finalizada),
// finalización atómica, cancelación de horarios futuros, recálculo de acceso
// y eliminación segura sin borrados en cascada destructivos.

declare(strict_types=1);

require_once __DIR__ . '/AuditoriaModelo.php';

class FichaModelo
{
    private AuditoriaModelo $auditoria;

    public function __construct(?AuditoriaModelo $auditoria = null)
    {
        $this->auditoria = $auditoria ?? new AuditoriaModelo();
    }

    /**
     * Lista fichas con métricas de aprendices, horarios y metadatos del ciclo de vida.
     * Soporta filtrado por instructor y por estado de negocio ('Activa', 'Finalizada', o null para todas).
     *
     * @param int|null $idInstructor
     * @param string|null $filtroEstado
     * @return array
     */
    public function listarFichasDetalladas(?int $idInstructor = null, ?string $filtroEstado = null): array
    {
        $params = [];
        $whereFiltros = "";

        if ($filtroEstado !== null && $filtroEstado !== '' && strtolower($filtroEstado) !== 'todos') {
            $whereFiltros .= " AND f.estado = :filtro_estado";
            $params[':filtro_estado'] = $filtroEstado;
        }

        if ($idInstructor !== null && $idInstructor > 0) {
            $sql = "SELECT 
                        f.id_ficha,
                        f.numero_ficha,
                        f.nombre_programa,
                        f.estado,
                        f.fecha_finalizacion,
                        f.id_usuario_finalizacion,
                        f.motivo_finalizacion,
                        u_fin.nombre_completo AS responsable_finalizacion,
                        COUNT(DISTINCT a.id_aprendiz) AS total_aprendices,
                        COUNT(DISTINCT h.id_horario) AS total_horarios,
                        COUNT(DISTINCT CASE WHEN h.estado = 'Cancelado' THEN h.id_horario END) AS horarios_cancelados,
                        COUNT(DISTINCT CASE WHEN h.estado = 'Programado' THEN h.id_horario END) AS horarios_programados,
                        fhu.id_instructor,
                        GROUP_CONCAT(DISTINCT COALESCE(u.nombre_completo, '') SEPARATOR ', ') AS instructores
                    FROM fichas f
                    LEFT JOIN aprendices a ON a.id_ficha = f.id_ficha
                    LEFT JOIN horarios h ON h.id_ficha = f.id_ficha AND h.id_instructor = :id_inst1
                    LEFT JOIN fichas_has_usuarios fhu ON fhu.fichas_id_ficha = f.id_ficha
                    LEFT JOIN usuarios u ON u.id_usuario = fhu.id_instructor
                    LEFT JOIN usuarios u_fin ON u_fin.id_usuario = f.id_usuario_finalizacion
                    WHERE f.id_ficha IN (
                        SELECT id_ficha FROM horarios WHERE id_instructor = :id_inst2
                        UNION
                        SELECT fichas_id_ficha FROM fichas_has_usuarios WHERE id_instructor = :id_inst3
                    )
                    {$whereFiltros}
                    GROUP BY f.id_ficha
                    ORDER BY f.numero_ficha ASC";

            $params[':id_inst1'] = $idInstructor;
            $params[':id_inst2'] = $idInstructor;
            $params[':id_inst3'] = $idInstructor;

            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT 
                        f.id_ficha,
                        f.numero_ficha,
                        f.nombre_programa,
                        f.estado,
                        f.fecha_finalizacion,
                        f.id_usuario_finalizacion,
                        f.motivo_finalizacion,
                        u_fin.nombre_completo AS responsable_finalizacion,
                        COUNT(DISTINCT a.id_aprendiz) AS total_aprendices,
                        COUNT(DISTINCT h.id_horario) AS total_horarios,
                        COUNT(DISTINCT CASE WHEN h.estado = 'Cancelado' THEN h.id_horario END) AS horarios_cancelados,
                        COUNT(DISTINCT CASE WHEN h.estado = 'Programado' THEN h.id_horario END) AS horarios_programados,
                        fhu.id_instructor,
                        GROUP_CONCAT(DISTINCT COALESCE(u.nombre_completo, '') SEPARATOR ', ') AS instructores
                    FROM fichas f
                    LEFT JOIN aprendices a ON a.id_ficha = f.id_ficha
                    LEFT JOIN horarios h ON h.id_ficha = f.id_ficha
                    LEFT JOIN fichas_has_usuarios fhu ON fhu.fichas_id_ficha = f.id_ficha
                    LEFT JOIN usuarios u ON u.id_usuario = fhu.id_instructor
                    LEFT JOIN usuarios u_fin ON u_fin.id_usuario = f.id_usuario_finalizacion
                    WHERE 1=1 {$whereFiltros}
                    GROUP BY f.id_ficha
                    ORDER BY f.numero_ficha ASC";

            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Decodificar entidades HTML para garantizar UTF-8 limpio
        foreach ($filas as &$f) {
            $f['nombre_programa']          = html_entity_decode((string)($f['nombre_programa'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['instructores']             = html_entity_decode((string)($f['instructores'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['responsable_finalizacion'] = html_entity_decode((string)($f['responsable_finalizacion'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        unset($f);

        return $filas;
    }

    /**
     * Retorna el conteo de fichas por cada estado de negocio para las pestañas de la interfaz.
     */
    public function contarPorEstado(?int $idInstructor = null): array
    {
        $db = Conexion::obtener();
        $params = [];
        $whereInst = "";

        if ($idInstructor !== null && $idInstructor > 0) {
            $whereInst = " AND f.id_ficha IN (
                SELECT id_ficha FROM horarios WHERE id_instructor = :id_inst1
                UNION
                SELECT fichas_id_ficha FROM fichas_has_usuarios WHERE id_instructor = :id_inst2
            )";
            $params[':id_inst1'] = $idInstructor;
            $params[':id_inst2'] = $idInstructor;
        }

        $sql = "SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN f.estado = 'Activa' THEN 1 ELSE 0 END) AS activas,
                    SUM(CASE WHEN f.estado = 'Finalizada' THEN 1 ELSE 0 END) AS finalizadas
                FROM fichas f
                WHERE 1=1 {$whereInst}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'       => (int)($res['total'] ?? 0),
            'activas'     => (int)($res['activas'] ?? 0),
            'finalizadas' => (int)($res['finalizadas'] ?? 0),
        ];
    }

    /**
     * Retorna los indicadores académicos clave para el panel del instructor:
     * - total_fichas: fichas activas actualmente asignadas
     * - clases_hoy: clases programadas para el día de hoy
     * - proximas_clases: clases programadas para fechas futuras
     */
    public function obtenerKpisInstructor(int $idInstructor): array
    {
        $db = Conexion::obtener();

        // 1. Total fichas activas asignadas
        $sqlFichas = "SELECT COUNT(DISTINCT f.id_ficha)
                      FROM fichas f
                      WHERE f.estado = 'Activa'
                        AND f.id_ficha IN (
                            SELECT id_ficha FROM horarios WHERE id_instructor = :id1
                            UNION
                            SELECT fichas_id_ficha FROM fichas_has_usuarios WHERE id_instructor = :id2
                        )";
        $stmtFichas = $db->prepare($sqlFichas);
        $stmtFichas->execute([':id1' => $idInstructor, ':id2' => $idInstructor]);
        $totalFichas = (int)$stmtFichas->fetchColumn();

        // 2. Clases programadas hoy
        $sqlHoy = "SELECT COUNT(*)
                   FROM horarios
                   WHERE id_instructor = :id
                     AND dia_semana = CURDATE()
                     AND estado = 'Programado'";
        $stmtHoy = $db->prepare($sqlHoy);
        $stmtHoy->execute([':id' => $idInstructor]);
        $clasesHoy = (int)$stmtHoy->fetchColumn();

        // 3. Próximas clases programadas a partir de mañana
        $sqlProx = "SELECT COUNT(*)
                    FROM horarios
                    WHERE id_instructor = :id
                      AND dia_semana > CURDATE()
                      AND estado = 'Programado'";
        $stmtProx = $db->prepare($sqlProx);
        $stmtProx->execute([':id' => $idInstructor]);
        $proximasClases = (int)$stmtProx->fetchColumn();

        return [
            'total_fichas'    => $totalFichas,
            'clases_hoy'      => $clasesHoy,
            'proximas_clases' => $proximasClases,
        ];
    }

    /**
     * Obtiene exclusivamente las fichas activas asignadas al instructor,
     * incorporando la información de su próxima clase programada.
     */
    public function listarFichasInstructor(int $idInstructor): array
    {
        // Se reutiliza listarFichasDetalladas forzando únicamente estado 'Activa' y filtrado por instructor
        $fichas = $this->listarFichasDetalladas($idInstructor, 'Activa');
        if (empty($fichas)) {
            return [];
        }

        $db = Conexion::obtener();
        $sqlProximo = "SELECT dia_semana, hora_entrada_programada, hora_salida_programada
                       FROM horarios
                       WHERE id_ficha = :id_ficha
                         AND id_instructor = :id_inst
                         AND estado = 'Programado'
                         AND (dia_semana > CURDATE() OR (dia_semana = CURDATE() AND hora_salida_programada >= CURTIME()))
                       ORDER BY dia_semana ASC, hora_entrada_programada ASC
                       LIMIT 1";
        $stmtProximo = $db->prepare($sqlProximo);

        foreach ($fichas as &$f) {
            $stmtProximo->execute([
                ':id_ficha' => $f['id_ficha'],
                ':id_inst'  => $idInstructor,
            ]);
            $proximo = $stmtProximo->fetch(PDO::FETCH_ASSOC);
            $f['proximo_horario'] = $proximo ?: null;
        }
        unset($f);

        return $fichas;
    }

    /**
     * Busca los datos completos de una ficha por su ID incluyendo estado y metadatos.
     */
    public function buscarFichaPorId(int $idFicha): ?array
    {
        $sql = "SELECT f.*,
                       u_fin.nombre_completo AS responsable_finalizacion
                FROM fichas f
                LEFT JOIN usuarios u_fin ON u_fin.id_usuario = f.id_usuario_finalizacion
                WHERE f.id_ficha = :id 
                LIMIT 1";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute([':id' => $idFicha]);
        $ficha = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ficha) {
            $ficha['nombre_programa']          = html_entity_decode((string)$ficha['nombre_programa'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $ficha['responsable_finalizacion'] = html_entity_decode((string)($ficha['responsable_finalizacion'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $ficha;
        }

        return null;
    }

    /**
     * Busca los datos completos de una ficha por su número.
     */
    public function buscarFichaPorNumero(string $numeroFicha): ?array
    {
        $sql = "SELECT f.*,
                       u_fin.nombre_completo AS responsable_finalizacion
                FROM fichas f
                LEFT JOIN usuarios u_fin ON u_fin.id_usuario = f.id_usuario_finalizacion
                WHERE f.numero_ficha = :num 
                LIMIT 1";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute([':num' => trim($numeroFicha)]);
        $ficha = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ficha) {
            $ficha['nombre_programa']          = html_entity_decode((string)$ficha['nombre_programa'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $ficha['responsable_finalizacion'] = html_entity_decode((string)($ficha['responsable_finalizacion'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $ficha;
        }

        return null;
    }

    /**
     * Busca el ID numérico de una ficha por su número.
     */
    public function buscarIdFichaPorNumero(string $numeroFicha): ?int
    {
        $sql = "SELECT id_ficha FROM fichas WHERE numero_ficha = :num LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute([':num' => trim($numeroFicha)]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int)$id : null;
    }

    /**
     * Comprueba si un número de ficha ya existe en la base de datos (con opción de excluir un ID para edición).
     */
    public function existeNumeroFicha(string $numeroFicha, ?int $excluirIdFicha = null): bool
    {
        $numeroFicha = trim($numeroFicha);
        $sql = "SELECT id_ficha FROM fichas WHERE numero_ficha = :num";
        $params = [':num' => $numeroFicha];

        if ($excluirIdFicha !== null && $excluirIdFicha > 0) {
            $sql .= " AND id_ficha != :id";
            $params[':id'] = $excluirIdFicha;
        }

        $stmt = Conexion::obtener()->prepare($sql . " LIMIT 1");
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Inserta una nueva ficha académica con estado inicial 'Activa'.
     */
    public function crearFicha(string $numeroFicha, string $nombrePrograma): int
    {
        $db = Conexion::obtener();
        $nombrePrograma = html_entity_decode(trim($nombrePrograma), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sql = "INSERT INTO fichas (numero_ficha, nombre_programa, estado) VALUES (:num, :prog, 'Activa')";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':num'  => trim($numeroFicha),
            ':prog' => $nombrePrograma
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Actualiza los datos (número y programa) de una ficha existente.
     * Solo permite editar fichas en estado 'Activa'. Fichas finalizadas o archivadas son de solo lectura.
     */
    public function actualizarFicha(int $idFicha, string $numeroFicha, string $nombrePrograma): void
    {
        $ficha = $this->buscarFichaPorId($idFicha);
        if (!$ficha) {
            throw new InvalidArgumentException("La ficha especificada no existe.");
        }

        if ($ficha['estado'] !== 'Activa') {
            throw new DomainException("No se permite modificar los datos de una ficha en estado '{$ficha['estado']}'.");
        }

        $nombrePrograma = html_entity_decode(trim($nombrePrograma), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sql = "UPDATE fichas 
                SET numero_ficha = :num, 
                    nombre_programa = :prog 
                WHERE id_ficha = :id";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':num',  $numeroFicha);
        $stmt->bindParam(':prog', $nombrePrograma);
        $stmt->bindParam(':id',   $idFicha, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Busca una ficha por su número o la crea si aún no existe.
     * Si la ficha existe pero no está activa, no permite reactivación arbitraria.
     */
    public function obtenerOCrearFicha(string $numeroFicha, string $nombrePrograma): int
    {
        $db = Conexion::obtener();
        $numeroFicha    = trim($numeroFicha);
        $nombrePrograma = html_entity_decode(trim($nombrePrograma), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $sqlBuscar = "SELECT id_ficha, nombre_programa, estado FROM fichas WHERE numero_ficha = :num LIMIT 1";
        $stmt = $db->prepare($sqlBuscar);
        $stmt->bindParam(':num', $numeroFicha);
        $stmt->execute();
        $ficha = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ficha) {
            $idFicha = (int) $ficha['id_ficha'];
            if ($ficha['estado'] !== 'Activa') {
                throw new DomainException("La ficha {$numeroFicha} está en estado '{$ficha['estado']}'. No se pueden asociar operaciones a fichas finalizadas o archivadas.");
            }

            // Actualizar si el nuevo nombre es válido y diferente al existente
            if ($nombrePrograma !== '' && $nombrePrograma !== 'Formación SENA' && $nombrePrograma !== $ficha['nombre_programa']) {
                $sqlUpdate = "UPDATE fichas SET nombre_programa = :prog WHERE id_ficha = :id";
                $stmtUpdate = $db->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':prog', $nombrePrograma);
                $stmtUpdate->bindParam(':id', $idFicha, PDO::PARAM_INT);
                $stmtUpdate->execute();
            }

            return $idFicha;
        }

        $sqlInsert = "INSERT INTO fichas (numero_ficha, nombre_programa, estado) VALUES (:num, :prog, 'Activa')";
        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bindParam(':num', $numeroFicha);
        $stmtInsert->bindParam(':prog', $nombrePrograma);
        $stmtInsert->execute();

        return (int) $db->lastInsertId();
    }

    /**
     * Asocia un instructor a una ficha en la tabla fichas_has_usuarios.
     * Valida que la ficha esté activa.
     */
    public function asociarInstructor(int $idFicha, int $idInstructor): void
    {
        $ficha = $this->buscarFichaPorId($idFicha);
        if (!$ficha || $ficha['estado'] !== 'Activa') {
            throw new DomainException("No se pueden asignar instructores a fichas que no estén activas.");
        }

        $db = Conexion::obtener();
        $sql = "INSERT IGNORE INTO fichas_has_usuarios (fichas_id_ficha, id_instructor) VALUES (:id_ficha, :id_inst)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
        $stmt->bindParam(':id_inst', $idInstructor, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Lista instructores activos para el selector del formulario de fichas.
     */
    public function listarInstructores(): array
    {
        $sql = "SELECT id_usuario, nombre_completo, usuario
                FROM usuarios
                WHERE id_role = 2 AND estado = 'Activo'
                ORDER BY nombre_completo ASC";

        return Conexion::obtener()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // SECCIÓN TRANSACCIONAL: CICLO DE VIDA DE FICHAS
    // =========================================================================

    /**
     * FINALIZACIÓN DE UNA FICHA: Operación atómica y transaccional centralizada.
     *
     * Reglas aplicadas:
     * 1. Valida que la ficha exista y su estado actual sea 'Activa'.
     * 2. Idempotencia: si ya está finalizada, lo detecta limpiamente.
     * 3. Cancela de forma controlada los horarios futuros a partir de la fecha efectiva (sin borrar pasados ni alterar asistencias).
     * 4. Cierra las vinculaciones de los aprendices en `aprendiz_ficha`.
     * 5. Recalcula el estado de acceso de los aprendices sin desactivar cuentas que continúen en otras fichas activas ni tocar otros roles.
     * 6. Actualiza metadatos de finalización: fecha_finalizacion, id_usuario_finalizacion, motivo_finalizacion, estado = 'Finalizada'.
     * 7. Registra evento exhaustivo en auditoría.
     *
     * @param int $idFicha
     * @param int $idUsuarioAdmin Administrador responsable
     * @param string|null $fechaEfectiva Fecha/hora efectiva (formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS). Si es null, usa NOW().
     * @param string|null $motivo Motivo justificado del cierre
     * @return array Resumen detallado de la operación
     * @throws DomainException|InvalidArgumentException|Throwable
     */
    public function finalizarFicha(int $idFicha, int $idUsuarioAdmin, ?string $fechaEfectiva = null, ?string $motivo = null): array
    {
        $db = Conexion::obtener();
        $correlacionId = 'fin_' . bin2hex(random_bytes(8));

        // 1. Obtener la ficha
        $ficha = $this->buscarFichaPorId($idFicha);
        if (!$ficha) {
            $this->auditoria->registrar('FINALIZAR', $idFicha, null, $idUsuarioAdmin, null, null, $motivo, 'RECHAZADO', ['error' => 'Ficha no encontrada'], $correlacionId);
            throw new InvalidArgumentException("La ficha especificada no existe.");
        }

        // 2. Comprobar máquina de estados
        if ($ficha['estado'] === 'Finalizada') {
            $this->auditoria->registrar('FINALIZAR', $idFicha, $ficha['numero_ficha'], $idUsuarioAdmin, 'Finalizada', 'Finalizada', $motivo, 'RECHAZADO', ['error' => 'La ficha ya se encuentra finalizada previamente.'], $correlacionId);
            throw new DomainException("La ficha {$ficha['numero_ficha']} ya se encuentra finalizada previamente.");
        }

        if ($ficha['estado'] !== 'Activa') {
            $this->auditoria->registrar('FINALIZAR', $idFicha, $ficha['numero_ficha'], $idUsuarioAdmin, $ficha['estado'], null, $motivo, 'RECHAZADO', ['error' => 'Estado inválido'], $correlacionId);
            throw new DomainException("La ficha no se encuentra en estado Activa.");
        }

        // Determinar fecha/hora efectiva respetando zona horaria institucional
        $tsAhora = time();
        $fechaHoy = date('Y-m-d', $tsAhora);
        $horaAhora = date('H:i:s', $tsAhora);

        if ($fechaEfectiva === null || trim($fechaEfectiva) === '') {
            $fechaEfectivaSql = date('Y-m-d H:i:s', $tsAhora);
            $fechaCorte = $fechaHoy;
            $horaCorte  = $horaAhora;
        } else {
            $tsEfectivo = strtotime($fechaEfectiva);
            if ($tsEfectivo === false) {
                throw new InvalidArgumentException("La fecha efectiva proporcionada no es válida.");
            }
            $fechaEfectivaSql = date('Y-m-d H:i:s', $tsEfectivo);
            $fechaCorte = date('Y-m-d', $tsEfectivo);
            $horaCorte  = (strlen($fechaEfectiva) > 10) ? date('H:i:s', $tsEfectivo) : '23:59:59';
        }

        $motivoLimpio = trim((string)($motivo ?? 'Finalización formal de etapa lectiva de formación'));

        $db->beginTransaction();
        try {
            // A. Cancelar horarios futuros de esta ficha que no deban ejecutarse
            // Se cancelan: días posteriores a fechaCorte, o el mismo día si hora_salida > horaCorte
            $sqlCancelHor = "
                UPDATE horarios 
                SET estado = 'Cancelado',
                    fecha_cancelacion = NOW(),
                    motivo_cancelacion = :motivo
                WHERE id_ficha = :id_ficha
                  AND estado = 'Programado'
                  AND (
                      dia_semana > :fecha_corte
                      OR (dia_semana = :fecha_corte2 AND hora_salida_programada > :hora_corte)
                  )
            ";
            $stmtCancel = $db->prepare($sqlCancelHor);
            $stmtCancel->execute([
                ':motivo'       => "Ficha finalizada: {$motivoLimpio}",
                ':id_ficha'     => $idFicha,
                ':fecha_corte'  => $fechaCorte,
                ':fecha_corte2' => $fechaCorte,
                ':hora_corte'   => $horaCorte,
            ]);
            $horariosCancelados = $stmtCancel->rowCount();

            // B. Conteo de horarios pasados preservados
            $stmtHorPres = $db->prepare("
                SELECT COUNT(*) FROM horarios 
                WHERE id_ficha = :id AND (estado = 'Cancelado' OR dia_semana <= :corte)
            ");
            $stmtHorPres->execute([':id' => $idFicha, ':corte' => $fechaCorte]);
            $horariosPreservados = (int)$stmtHorPres->fetchColumn();

            // C. Obtener aprendices de la ficha para actualizar aprendiz_ficha y evaluar acceso
            $stmtApr = $db->prepare("
                SELECT DISTINCT a.id_aprendiz, a.id_usuario 
                FROM aprendices a 
                LEFT JOIN aprendiz_ficha af ON af.id_aprendiz = a.id_aprendiz
                WHERE a.id_ficha = :id1 OR af.id_ficha = :id2
            ");
            $stmtApr->execute([':id1' => $idFicha, ':id2' => $idFicha]);
            $aprendicesFicha = $stmtApr->fetchAll(PDO::FETCH_ASSOC);

            $cuentasInactivadas = 0;
            $cuentasConservadas = 0;
            $detallesAprendices = [];

            foreach ($aprendicesFicha as $apr) {
                $idApr = (int)$apr['id_aprendiz'];
                $idUsr = (int)$apr['id_usuario'];

                // Registrar cierre de vinculación en aprendiz_ficha
                $stmtAf = $db->prepare("
                    UPDATE aprendiz_ficha 
                    SET estado = 'Finalizado', fecha_finalizacion = NOW()
                    WHERE id_aprendiz = :id_apr AND id_ficha = :id_ficha AND estado = 'Activo'
                ");
                $stmtAf->execute([':id_apr' => $idApr, ':id_ficha' => $idFicha]);

                // Evaluar si el usuario continúa en OTRA ficha activa
                $tieneOtraFichaActiva = $this->usuarioTieneOtraFichaActiva($idUsr, $idFicha);
                $tieneOtrosRoles      = $this->usuarioTieneOtrosRoles($idUsr);

                if ($tieneOtraFichaActiva || $tieneOtrosRoles) {
                    $cuentasConservadas++;
                    $detallesAprendices[] = [
                        'id_aprendiz'   => $idApr,
                        'id_usuario'    => $idUsr,
                        'acceso'        => 'CONSERVADO',
                        'motivo_acceso' => $tieneOtraFichaActiva ? 'Pertenece a otra ficha activa' : 'Posee roles o permisos adicionales'
                    ];
                } else {
                    // Si ya no tiene ninguna vinculación activa con ninguna ficha activa y es rol Aprendiz:
                    // Inhabilitar acceso de aprendiz de forma segura sin borrar datos
                    $stmtInact = $db->prepare("
                        UPDATE usuarios 
                        SET estado = 'Inactivo' 
                        WHERE id_usuario = :id_usr AND id_role = 3
                    ");
                    $stmtInact->execute([':id_usr' => $idUsr]);
                    $cuentasInactivadas++;
                    $detallesAprendices[] = [
                        'id_aprendiz'   => $idApr,
                        'id_usuario'    => $idUsr,
                        'acceso'        => 'INACTIVADO',
                        'motivo_acceso' => 'Finalizó su única ficha formativa activa'
                    ];
                }
            }

            // D. Actualizar el estado de la ficha a 'Finalizada'
            $sqlUpdateFicha = "
                UPDATE fichas 
                SET estado = 'Finalizada',
                    fecha_finalizacion = :fecha_fin,
                    id_usuario_finalizacion = :id_usr_fin,
                    motivo_finalizacion = :motivo
                WHERE id_ficha = :id_ficha
            ";
            $stmtUpdF = $db->prepare($sqlUpdateFicha);
            $stmtUpdF->execute([
                ':fecha_fin'   => $fechaEfectivaSql,
                ':id_usr_fin'  => $idUsuarioAdmin,
                ':motivo'      => $motivoLimpio,
                ':id_ficha'    => $idFicha,
            ]);

            // E. Registrar evento en auditoría
            $metricas = [
                'horarios_cancelados' => $horariosCancelados,
                'horarios_preservados'=> $horariosPreservados,
                'total_aprendices'    => count($aprendicesFicha),
                'cuentas_inactivadas' => $cuentasInactivadas,
                'cuentas_conservadas' => $cuentasConservadas,
                'fecha_efectiva'      => $fechaEfectivaSql,
                'detalles_aprendices' => $detallesAprendices,
            ];

            $this->auditoria->registrar(
                'FINALIZAR',
                $idFicha,
                $ficha['numero_ficha'],
                $idUsuarioAdmin,
                'Activa',
                'Finalizada',
                $motivoLimpio,
                'EXITOSO',
                $metricas,
                $correlacionId
            );

            $db->commit();

            return [
                'exito'                 => true,
                'id_ficha'              => $idFicha,
                'numero_ficha'          => $ficha['numero_ficha'],
                'nombre_programa'       => $ficha['nombre_programa'],
                'estado_nuevo'          => 'Finalizada',
                'fecha_finalizacion'    => $fechaEfectivaSql,
                'horarios_cancelados'   => $horariosCancelados,
                'aprendices_evaluados'  => count($aprendicesFicha),
                'cuentas_inactivadas'   => $cuentasInactivadas,
                'cuentas_conservadas'   => $cuentasConservadas,
                'correlacion_id'        => $correlacionId,
            ];

        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->auditoria->registrar(
                'FINALIZAR',
                $idFicha,
                $ficha['numero_ficha'],
                $idUsuarioAdmin,
                $ficha['estado'],
                'ERROR',
                $motivoLimpio,
                'ERROR',
                ['error' => $e->getMessage()],
                $correlacionId
            );
            error_log("Error al finalizar ficha {$idFicha}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * REAPERTURA EXCEPCIONAL DE UNA FICHA FINALIZADA.
     * Permite reactivar fichas finalizadas para subsanar cierres erróneos o ampliación lectiva.
     */
    public function reabrirFicha(int $idFicha, int $idUsuarioAdmin, string $motivo): array
    {
        $db = Conexion::obtener();
        $correlacionId = 'rea_' . bin2hex(random_bytes(8));
        $motivoLimpio = trim($motivo);

        if ($motivoLimpio === '' || mb_strlen($motivoLimpio) < 8) {
            throw new InvalidArgumentException("La reapertura de una ficha requiere un motivo justificado de al menos 8 caracteres.");
        }

        $ficha = $this->buscarFichaPorId($idFicha);
        if (!$ficha) {
            throw new InvalidArgumentException("La ficha especificada no existe.");
        }

        if ($ficha['estado'] === 'Activa') {
            throw new DomainException("La ficha ya se encuentra activa.");
        }

        if ($ficha['estado'] !== 'Finalizada') {
            throw new DomainException("Solo se pueden reabrir fichas en estado 'Finalizada'.");
        }

        $db->beginTransaction();
        try {
            // Reabrir ficha: restaurar estado a 'Activa' y limpiar metadatos de cierre
            $sql = "
                UPDATE fichas 
                SET estado = 'Activa',
                    fecha_finalizacion = NULL,
                    id_usuario_finalizacion = NULL,
                    motivo_finalizacion = NULL
                WHERE id_ficha = :id
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $idFicha]);

            // Reactivar vinculación de aprendices
            $stmtAf = $db->prepare("
                UPDATE aprendiz_ficha 
                SET estado = 'Activo', fecha_finalizacion = NULL 
                WHERE id_ficha = :id
            ");
            $stmtAf->execute([':id' => $idFicha]);

            // Reactivar cuentas de usuario si estaban inactivas
            $stmtActUsr = $db->prepare("
                UPDATE usuarios u
                JOIN aprendices a ON a.id_usuario = u.id_usuario
                SET u.estado = 'Activo'
                WHERE a.id_ficha = :id AND u.id_role = 3
            ");
            $stmtActUsr->execute([':id' => $idFicha]);

            $this->auditoria->registrar(
                'REABRIR',
                $idFicha,
                $ficha['numero_ficha'],
                $idUsuarioAdmin,
                'Finalizada',
                'Activa',
                $motivoLimpio,
                'EXITOSO',
                ['nota' => 'Reapertura administrativa controlada. Horarios históricos no reactivados.'],
                $correlacionId
            );

            $db->commit();

            return [
                'exito'           => true,
                'id_ficha'        => $idFicha,
                'numero_ficha'    => $ficha['numero_ficha'],
                'estado_nuevo'    => 'Activa',
                'correlacion_id'  => $correlacionId,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Comprueba si un usuario tiene otra ficha activa en el sistema.
     */
    public function usuarioTieneOtraFichaActiva(int $idUsuario, int $idFichaExcluir): bool
    {
        $db = Conexion::obtener();
        $sql = "
            SELECT COUNT(*) 
            FROM aprendices a
            JOIN fichas f ON f.id_ficha = a.id_ficha
            WHERE a.id_usuario = :id_usr 
              AND a.id_ficha != :id_excluir 
              AND f.estado = 'Activa'
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id_usr' => $idUsuario, ':id_excluir' => $idFichaExcluir]);
        if ((int)$stmt->fetchColumn() > 0) {
            return true;
        }

        // Comprobar también en la tabla de vinculaciones históricas aprendiz_ficha
        $sqlAf = "
            SELECT COUNT(*) 
            FROM aprendiz_ficha af
            JOIN aprendices ap ON ap.id_aprendiz = af.id_aprendiz
            JOIN fichas f ON f.id_ficha = af.id_ficha
            WHERE ap.id_usuario = :id_usr 
              AND af.id_ficha != :id_excluir 
              AND af.estado = 'Activo'
              AND f.estado = 'Activa'
        ";
        $stmtAf = $db->prepare($sqlAf);
        $stmtAf->execute([':id_usr' => $idUsuario, ':id_excluir' => $idFichaExcluir]);
        return (int)$stmtAf->fetchColumn() > 0;
    }

    /**
     * Comprueba si el usuario tiene otros roles diferentes a Aprendiz (id_role != 3).
     */
    public function usuarioTieneOtrosRoles(int $idUsuario): bool
    {
        $sql = "SELECT id_role FROM usuarios WHERE id_usuario = :id LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute([':id' => $idUsuario]);
        $role = $stmt->fetchColumn();
        return ($role !== false && (int)$role !== 3);
    }

    /**
     * Obtiene estadísticas exhaustivas de dependencias de una ficha antes de evaluar su eliminación.
     */
    public function obtenerEstadisticasUso(int $idFicha): array
    {
        $db = Conexion::obtener();

        $stmtApr = $db->prepare("SELECT COUNT(*) FROM aprendices WHERE id_ficha = :id");
        $stmtApr->execute([':id' => $idFicha]);
        $totalAprendices = (int) $stmtApr->fetchColumn();

        $stmtHor = $db->prepare("SELECT COUNT(*) FROM horarios WHERE id_ficha = :id");
        $stmtHor->execute([':id' => $idFicha]);
        $totalHorarios = (int) $stmtHor->fetchColumn();

        // Conteo de asistencias vinculadas a aprendices de esta ficha o a horarios de esta ficha
        $stmtAsist = $db->prepare("
            SELECT COUNT(*) FROM asistencias a
            WHERE a.id_aprendiz IN (SELECT id_aprendiz FROM aprendices WHERE id_ficha = :id1)
               OR a.id_horario IN (SELECT id_horario FROM horarios WHERE id_ficha = :id2)
        ");
        $stmtAsist->execute([':id1' => $idFicha, ':id2' => $idFicha]);
        $totalAsistencias = (int) $stmtAsist->fetchColumn();

        // Conteo de excusas vinculadas
        $stmtExc = $db->prepare("
            SELECT COUNT(*) FROM excusas_medicas em
            WHERE em.aprendices_id_aprendiz IN (SELECT id_aprendiz FROM aprendices WHERE id_ficha = :id)
        ");
        $stmtExc->execute([':id' => $idFicha]);
        $totalExcusas = (int) $stmtExc->fetchColumn();

        // Conteo de festivos específicos
        $stmtFest = $db->prepare("SELECT COUNT(*) FROM dias_festivos WHERE id_ficha = :id");
        $stmtFest->execute([':id' => $idFicha]);
        $totalFestivos = (int) $stmtFest->fetchColumn();

        $tieneDependencias = ($totalAprendices > 0 || $totalHorarios > 0 || $totalAsistencias > 0 || $totalExcusas > 0 || $totalFestivos > 0);

        return [
            'total_aprendices'   => $totalAprendices,
            'total_horarios'     => $totalHorarios,
            'total_asistencias'  => $totalAsistencias,
            'total_excusas'      => $totalExcusas,
            'total_festivos'     => $totalFestivos,
            'tiene_registros'    => $tieneDependencias,
            'tiene_dependencias' => $tieneDependencias,
        ];
    }

    /**
     * ELIMINACIÓN SEGURA DE UNA FICHA:
     * Operación estrictamente restringida a fichas creadas por error que no contengan
     * ningún historial académico, asistencias, excusas, horarios ni aprendices.
     *
     * Si la ficha contiene registros, la eliminación es RECHAZADA en backend y se audita el intento.
     */
    public function eliminarFichaSegura(int $idFicha, int $idUsuarioAdmin = 0, ?string $motivo = null): bool
    {
        $db = Conexion::obtener();
        $correlacionId = 'del_' . bin2hex(random_bytes(8));

        $ficha = $this->buscarFichaPorId($idFicha);
        if (!$ficha) {
            $this->auditoria->registrar('INTENTO_ELIMINACION', $idFicha, null, $idUsuarioAdmin, null, null, $motivo, 'RECHAZADO', ['error' => 'Ficha no encontrada'], $correlacionId);
            throw new InvalidArgumentException("La ficha especificada no existe.");
        }

        $stats = $this->obtenerEstadisticasUso($idFicha);
        if ($stats['tiene_dependencias']) {
            $detalles = [];
            if ($stats['total_aprendices'] > 0) $detalles[] = "{$stats['total_aprendices']} aprendices";
            if ($stats['total_horarios'] > 0) $detalles[] = "{$stats['total_horarios']} horarios";
            if ($stats['total_asistencias'] > 0) $detalles[] = "{$stats['total_asistencias']} asistencias registradas";
            if ($stats['total_excusas'] > 0) $detalles[] = "{$stats['total_excusas']} excusas médicas";
            if ($stats['total_festivos'] > 0) $detalles[] = "{$stats['total_festivos']} festivos asignados";
            $textoDep = implode(', ', $detalles);

            $this->auditoria->registrar(
                'INTENTO_ELIMINACION',
                $idFicha,
                $ficha['numero_ficha'],
                $idUsuarioAdmin,
                $ficha['estado'],
                null,
                $motivo,
                'RECHAZADO',
                ['error' => "Eliminación bloqueada por dependencias: {$textoDep}"],
                $correlacionId
            );

            throw new DomainException("No es posible eliminar la ficha {$ficha['numero_ficha']} porque contiene información académica asociada ({$textoDep}). Para dar de baja una ficha con registros, debes utilizar la opción 'Finalizar'.");
        }

        $db->beginTransaction();
        try {
            // Eliminar asignaciones de instructor en fichas_has_usuarios
            $stmtFhu = $db->prepare("DELETE FROM fichas_has_usuarios WHERE fichas_id_ficha = :id");
            $stmtFhu->execute([':id' => $idFicha]);

            // Eliminar la ficha vacía
            $stmtFicha = $db->prepare("DELETE FROM fichas WHERE id_ficha = :id");
            $stmtFicha->execute([':id' => $idFicha]);

            $this->auditoria->registrar(
                'ELIMINACION',
                $idFicha,
                $ficha['numero_ficha'],
                $idUsuarioAdmin,
                $ficha['estado'],
                'ELIMINADA',
                $motivo ?? 'Eliminación segura de ficha sin dependencias',
                'EXITOSO',
                ['mensaje' => 'Ficha vacía eliminada limpiamente'],
                $correlacionId
            );

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al eliminar ficha segura {$idFicha}: " . $e->getMessage());
            throw $e;
        }
    }
}
