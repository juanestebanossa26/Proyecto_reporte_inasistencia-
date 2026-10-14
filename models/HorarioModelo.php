<?php
// Acceso a datos de las tablas horarios y fichas.
// Permite consultar y registrar horarios asociados a fichas para el calendario.

class HorarioModelo
{
    // Lista todos los horarios registrados con datos de la ficha, instructor y aprendices.
    public function listarHorarios(): array
    {
        $sql = "SELECT
                    horarios.id_horario,
                    horarios.id_ficha,
                    horarios.hora_entrada_programada,
                    horarios.hora_salida_programada,
                    horarios.dia_semana AS fecha,
                    fichas.numero_ficha,
                    fichas.nombre_programa,
                    COALESCE(horarios.id_instructor, fichas_has_usuarios.id_instructor) AS id_instructor,
                    COALESCE(u_directo.nombre_completo, u_ficha.nombre_completo, '') AS instructor_nombre,
                    (SELECT COUNT(*) FROM aprendices WHERE aprendices.id_ficha = fichas.id_ficha) AS total_aprendices
                FROM horarios
                JOIN fichas ON fichas.id_ficha = horarios.id_ficha
                LEFT JOIN usuarios u_directo ON u_directo.id_usuario = horarios.id_instructor
                LEFT JOIN fichas_has_usuarios ON fichas_has_usuarios.fichas_id_ficha = fichas.id_ficha
                LEFT JOIN usuarios u_ficha ON u_ficha.id_usuario = fichas_has_usuarios.id_instructor
                GROUP BY horarios.id_horario
                ORDER BY horarios.dia_semana ASC, horarios.hora_entrada_programada ASC";

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    // Busca el id de una ficha por su número o retorna null si no existe
    public function buscarIdFichaPorNumero(string $numeroFicha): ?int
    {
        $sql = "SELECT id_ficha FROM fichas WHERE numero_ficha = :num LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':num', $numeroFicha);
        $stmt->execute();
        $id = $stmt->fetchColumn();

        return $id !== false ? (int)$id : null;
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

    // Busca una ficha por su número o la crea si no existe
    public function obtenerOCrearFicha(string $numeroFicha, string $nombrePrograma): int
    {
        $db = Conexion::obtener();

        $sqlBuscar = "SELECT id_ficha FROM fichas WHERE numero_ficha = :num LIMIT 1";
        $stmt = $db->prepare($sqlBuscar);
        $stmt->bindParam(':num', $numeroFicha);
        $stmt->execute();
        $ficha = $stmt->fetch();

        if ($ficha) {
            $idFicha = (int) $ficha['id_ficha'];
            // Actualizar el nombre del programa si cambió
            $sqlUpdate = "UPDATE fichas SET nombre_programa = :prog WHERE id_ficha = :id";
            $stmtUpdate = $db->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':prog', $nombrePrograma);
            $stmtUpdate->bindParam(':id', $idFicha, PDO::PARAM_INT);
            $stmtUpdate->execute();

            return $idFicha;
        }

        $sqlInsert = "INSERT INTO fichas (numero_ficha, nombre_programa) VALUES (:num, :prog)";
        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bindParam(':num', $numeroFicha);
        $stmtInsert->bindParam(':prog', $nombrePrograma);
        $stmtInsert->execute();

        return (int) $db->lastInsertId();

    }

    // Asocia un instructor a una ficha en la tabla  fichas_has_usuarios
    public function asociarInstructor(int $idFicha, int $idInstructor): void
    {
        $db = Conexion::obtener();
        $sql = "INSERT IGNORE INTO fichas_has_usuarios (fichas_id_ficha, id_instructor) VALUES (:id_ficha, :id_inst)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
        $stmt->bindParam(':id_inst', $idInstructor, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Comprueba si un horario ya existe para evitar registros duplicados
    public function existeHorario(int $idFicha, string $horaEntrada, string $horaSalida, string $fecha): bool
    {
        $sql = "SELECT id_horario FROM horarios 
                WHERE id_ficha = :id_ficha 
                  AND dia_semana = :fecha 
                  AND hora_entrada_programada = :entrada 
                  AND hora_salida_programada = :salida 
                LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
        $stmt->bindParam(':fecha',    $fecha);
        $stmt->bindParam(':entrada',  $horaEntrada);
        $stmt->bindParam(':salida',   $horaSalida);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
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
        $instructores = $this->listarInstructores();

        // Se busca primero si el instructor existe
        $encontrado = $this->buscarInstructorEnLista($nombreCompletoRaw, $instructores);
        if ($encontrado) {
            return [
                'id_usuario' => (int) $encontrado['id_usuario'],
                'nombre'     => $encontrado['nombre_completo'],
                'es_nuevo'   => false
            ];
        }

        $nombreCompleto = trim($nombreCompletoRaw);
        $usuarioGenerado = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombreCompleto));
        if ($usuarioGenerado === '') {
            $usuarioGenerado = 'inst_' . time();
        }

        $area = trim($areaRaw);
        if ($area === '') {
            $area = 'TICS';
        } elseif (mb_strlen($area) > 45) {
            $area = mb_substr($area, 0, 45);
        }

        $db = Conexion::obtener();
        $passHash = password_hash('123', PASSWORD_BCRYPT);

        // Al importar del Excel se crea el usuario básico
        $sql = "INSERT INTO usuarios (nombre_completo, usuario, area, correo_electronico, estado, contrasena, id_role)
                VALUES (:nombre, :usuario, :area, :correo, 'Activo', :pass, 2)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':nombre',   $nombreCompleto);
        $stmt->bindParam(':usuario',  $usuarioGenerado);
        $stmt->bindParam(':area',     $area);
        $stmt->bindValue(':correo',   null, PDO::PARAM_NULL);
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
        $nombreBuscar = $this->normalizarTexto($lineas[0] ?? $texto);
        if ($nombreBuscar === '') return null;

        // Ordenar por longitud de nombre completo descendente
        usort($instructores, function ($a, $b) {
            $lenA = mb_strlen(trim($a['nombre_completo']));
            $lenB = mb_strlen(trim($b['nombre_completo']));
            return $lenB <=> $lenA;
        });

        // 1. Coincidencia directa del nombre completo
        foreach ($instructores as $inst) {
            $nombreCompleto = $this->normalizarTexto($inst['nombre_completo']);
            if ($nombreBuscar === $nombreCompleto || str_contains($nombreBuscar, $nombreCompleto) || str_contains($nombreCompleto, $nombreBuscar)) {
                return $inst;
            }
        }

        // 2. Similitud léxica alta (> 80%)
        foreach ($instructores as $inst) {
            $nombreCompleto = $this->normalizarTexto($inst['nombre_completo']);
            similar_text($nombreBuscar, $nombreCompleto, $percent);
            if ($percent >= 80) {
                return $inst;
            }
        }

        return null;
    }

    public function normalizarTexto(string $str): string
    {
        $str = mb_strtolower(trim($str), 'UTF-8');
        $str = str_replace(
            ['á','é','í','ó','ú','ñ','ü','_','-','.',',','(',')',':',';','/'],
            ['a','e','i','o','u','n','u',' ',' ',' ',' ',' ',' ',' ',' ',' '],
            $str
        );
        return preg_replace('/\s+/', ' ', $str);
    }

    public function listarFichasDetalladas(): array
    {
        $sql = "SELECT 
                    f.id_ficha,
                    f.numero_ficha,
                    f.nombre_programa,
                    COUNT(DISTINCT a.id_aprendiz) AS total_aprendices,
                    COUNT(DISTINCT h.id_horario) AS total_horarios,
                    fhu.id_instructor,
                    GROUP_CONCAT(DISTINCT COALESCE(u.nombre_completo, '') SEPARATOR ', ') AS instructores
                FROM fichas f
                LEFT JOIN aprendices a ON a.id_ficha = f.id_ficha
                LEFT JOIN horarios h ON h.id_ficha = f.id_ficha
                LEFT JOIN fichas_has_usuarios fhu ON fhu.fichas_id_ficha = f.id_ficha
                LEFT JOIN usuarios u ON u.id_usuario = fhu.id_instructor
                GROUP BY f.id_ficha
                ORDER BY f.numero_ficha ASC";

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    // Actualiza los datos de una ficha existente
    public function actualizarFicha(int $idFicha, string $numeroFicha, string $nombrePrograma): void
    {
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
}
