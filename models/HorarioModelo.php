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
                    fichas_has_usuarios.id_instructor,
                    CONCAT(COALESCE(usuarios.nombre_completo, ''), ' ', COALESCE(usuarios.apellido, '')) AS instructor_nombre,
                    (SELECT COUNT(*) FROM aprendices WHERE aprendices.id_ficha = fichas.id_ficha) AS total_aprendices
                FROM horarios
                JOIN fichas ON fichas.id_ficha = horarios.id_ficha
                LEFT JOIN fichas_has_usuarios ON fichas_has_usuarios.fichas_id_ficha = fichas.id_ficha
                LEFT JOIN usuarios ON usuarios.id_usuario = fichas_has_usuarios.id_instructor
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

    // Registra un nuevo horario asociado a una ficha
    public function guardarHorario(int $idFicha, string $horaEntrada, string $horaSalida, string $fecha): void
    {
        $sql = "INSERT INTO horarios (id_ficha, hora_entrada_programada, hora_salida_programada, dia_semana)
                VALUES (:id_ficha, :entrada, :salida, :fecha)";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
        $stmt->bindParam(':entrada',  $horaEntrada);
        $stmt->bindParam(':salida',   $horaSalida);
        $stmt->bindParam(':fecha',    $fecha);
        $stmt->execute();
    }

    //actualizar horario
    public function actualizarHorario(int $idHorario, int $idFicha, string $horaEntrada, string $horaSalida, string $fecha): void
    {
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


    // Lista instructores activos para el selector del formulario
    public function listarInstructores(): array
    {
        $sql = "SELECT id_usuario, nombre_completo, apellido
                FROM usuarios
                WHERE id_role = 2 AND estado = 'Activo'
                ORDER BY nombre_completo ASC";

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    // Busca un instructor existente por coincidencia de nombre o lo crea automáticamente.
     
    public function obtenerOCrearInstructor(string $nombreCompletoRaw, string $areaRaw = ''): array
    {
        $instructores = $this->listarInstructores();

        //Se busca primero si el instructor existe
        $encontrado = $this->buscarInstructorEnLista($nombreCompletoRaw, $instructores);
        if ($encontrado) {
            return [
                'id_usuario' => (int) $encontrado['id_usuario'],
                'nombre'     => $encontrado['nombre_completo'] . ' ' . $encontrado['apellido'],
                'es_nuevo'   => false
            ];
        }

        // Si no existe, crearlo automáticamente
        $partes = preg_split('/\s+/', trim($nombreCompletoRaw));
        $totalPartes = count($partes);

        if ($totalPartes === 1) {
            $nombre = $partes[0];
            $apellido = '.';
        } elseif ($totalPartes === 2) {
            $nombre = $partes[0];
            $apellido = $partes[1];
        } elseif ($totalPartes === 3) {
            $nombre = $partes[0];
            $apellido = $partes[1] . ' ' . $partes[2];
        } else {
            $mitad = (int) ceil($totalPartes / 2);
            $nombre = implode(' ', array_slice($partes, 0, $mitad));
            $apellido = implode(' ', array_slice($partes, $mitad));
        }

        $area = trim($areaRaw);
        if ($area === '') {
            $area = 'TICS';
        } elseif (mb_strlen($area) > 45) {
            $area = mb_substr($area, 0, 45);
        }

        $db = Conexion::obtener();
        $passHash = password_hash('123', PASSWORD_BCRYPT);

        // Al importar del Excel se dejan el documento y el correo vacíos (null)
        $sql = "INSERT INTO usuarios (nombre_completo, apellido, num_documento, area, correo_electronico, estado, contrasena, id_role)
                VALUES (:nombre, :apellido, :doc, :area, :correo, 'Activo', :pass, 2)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':nombre',   $nombre);
        $stmt->bindParam(':apellido', $apellido);
        $stmt->bindValue(':doc',      null, PDO::PARAM_NULL);
        $stmt->bindParam(':area',     $area);
        $stmt->bindValue(':correo',   null, PDO::PARAM_NULL);
        $stmt->bindParam(':pass',     $passHash);
        $stmt->execute();

        $idNuevo = (int) $db->lastInsertId();

        return [
            'id_usuario' => $idNuevo,
            'nombre'     => "$nombre $apellido",
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
            $lenA = mb_strlen(trim($a['nombre_completo'] . ' ' . $a['apellido']));
            $lenB = mb_strlen(trim($b['nombre_completo'] . ' ' . $b['apellido']));
            return $lenB <=> $lenA;
        });

        // 1. Coincidencia directa del nombre completo
        foreach ($instructores as $inst) {
            $nombreCompleto = $this->normalizarTexto($inst['nombre_completo'] . ' ' . $inst['apellido']);
            if ($nombreBuscar === $nombreCompleto || str_contains($nombreBuscar, $nombreCompleto) || str_contains($nombreCompleto, $nombreBuscar)) {
                return $inst;
            }
        }

        // 2. Coincidencia por partes (nombre y apellido simultáneamente)
        foreach ($instructores as $inst) {
            $soloNombre   = $this->normalizarTexto($inst['nombre_completo']);
            $soloApellido = $this->normalizarTexto($inst['apellido']);
            if ($soloNombre !== '' && $soloApellido !== '') {
                if (str_contains($nombreBuscar, $soloNombre) && str_contains($nombreBuscar, $soloApellido)) {
                    return $inst;
                }
            }
        }

        // 3. Similitud léxica alta (> 80%)
        foreach ($instructores as $inst) {
            $nombreCompleto = $this->normalizarTexto($inst['nombre_completo'] . ' ' . $inst['apellido']);
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
                    GROUP_CONCAT(DISTINCT CONCAT(COALESCE(u.nombre_completo, ''), ' ', COALESCE(u.apellido, '')) SEPARATOR ', ') AS instructores
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
