<?php
// models/FichaModelo.php — Acceso a datos y lógica del Módulo de Fichas
// Gestiona la creación, consulta, edición, validación y eliminación en cascada de fichas.

declare(strict_types=1);

class FichaModelo
{
    /**
     * Lista fichas detalladas con métricas de aprendices y horarios.
     * Si se especifica $idInstructor, solo lista las fichas asociadas a ese instructor.
     *
     * @param int|null $idInstructor
     * @return array
     */
    public function listarFichasDetalladas(?int $idInstructor = null): array
    {
        if ($idInstructor !== null && $idInstructor > 0) {
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
                    LEFT JOIN horarios h ON h.id_ficha = f.id_ficha AND h.id_instructor = :id_inst1
                    LEFT JOIN fichas_has_usuarios fhu ON fhu.fichas_id_ficha = f.id_ficha
                    LEFT JOIN usuarios u ON u.id_usuario = fhu.id_instructor
                    WHERE f.id_ficha IN (
                        SELECT id_ficha FROM horarios WHERE id_instructor = :id_inst2
                        UNION
                        SELECT fichas_id_ficha FROM fichas_has_usuarios WHERE id_instructor = :id_inst3
                    )
                    GROUP BY f.id_ficha
                    ORDER BY f.numero_ficha ASC";

            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->execute([
                ':id_inst1' => $idInstructor,
                ':id_inst2' => $idInstructor,
                ':id_inst3' => $idInstructor,
            ]);
            $filas = $stmt->fetchAll();
        } else {
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

            $filas = Conexion::obtener()->query($sql)->fetchAll();
        }

        // Decodificar entidades HTML para garantizar UTF-8 limpio
        foreach ($filas as &$f) {
            $f['nombre_programa'] = html_entity_decode((string)($f['nombre_programa'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $f['instructores']    = html_entity_decode((string)($f['instructores'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        unset($f);

        return $filas;
    }

    /**
     * Busca los datos completos de una ficha por su ID.
     */
    public function buscarFichaPorId(int $idFicha): ?array
    {
        $sql = "SELECT id_ficha, numero_ficha, nombre_programa FROM fichas WHERE id_ficha = :id LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute([':id' => $idFicha]);
        $ficha = $stmt->fetch();

        if ($ficha) {
            $ficha['nombre_programa'] = html_entity_decode((string)$ficha['nombre_programa'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $ficha;
        }

        return null;
    }

    /**
     * Busca los datos completos de una ficha por su número.
     */
    public function buscarFichaPorNumero(string $numeroFicha): ?array
    {
        $sql = "SELECT id_ficha, numero_ficha, nombre_programa FROM fichas WHERE numero_ficha = :num LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute([':num' => trim($numeroFicha)]);
        $ficha = $stmt->fetch();

        if ($ficha) {
            $ficha['nombre_programa'] = html_entity_decode((string)$ficha['nombre_programa'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
     * Inserta una nueva ficha académica.
     */
    public function crearFicha(string $numeroFicha, string $nombrePrograma): int
    {
        $db = Conexion::obtener();
        $nombrePrograma = html_entity_decode(trim($nombrePrograma), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sql = "INSERT INTO fichas (numero_ficha, nombre_programa) VALUES (:num, :prog)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':num'  => trim($numeroFicha),
            ':prog' => $nombrePrograma
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Actualiza los datos (número y programa) de una ficha existente.
     */
    public function actualizarFicha(int $idFicha, string $numeroFicha, string $nombrePrograma): void
    {
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
     */
    public function obtenerOCrearFicha(string $numeroFicha, string $nombrePrograma): int
    {
        $db = Conexion::obtener();
        $numeroFicha    = trim($numeroFicha);
        $nombrePrograma = html_entity_decode(trim($nombrePrograma), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $sqlBuscar = "SELECT id_ficha, nombre_programa FROM fichas WHERE numero_ficha = :num LIMIT 1";
        $stmt = $db->prepare($sqlBuscar);
        $stmt->bindParam(':num', $numeroFicha);
        $stmt->execute();
        $ficha = $stmt->fetch();

        if ($ficha) {
            $idFicha = (int) $ficha['id_ficha'];
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

        $sqlInsert = "INSERT INTO fichas (numero_ficha, nombre_programa) VALUES (:num, :prog)";
        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bindParam(':num', $numeroFicha);
        $stmtInsert->bindParam(':prog', $nombrePrograma);
        $stmtInsert->execute();

        return (int) $db->lastInsertId();
    }

    /**
     * Asocia un instructor a una ficha en la tabla fichas_has_usuarios.
     */
    public function asociarInstructor(int $idFicha, int $idInstructor): void
    {
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

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    /**
     * Elimina completamente una ficha y todos sus registros asociados de forma atómica y segura.
     * Desvincula y elimina:
     * 1. Asignaciones de instructor en fichas_has_usuarios.
     * 2. Excusas médicas y revisiones asociadas a los aprendices de la ficha.
     * 3. Asistencias asociadas a los aprendices de la ficha.
     * 4. Aprendices pertenecientes a la ficha.
     * 5. Asistencias vinculadas a los horarios de la ficha (desvinculación con NULL).
     * 6. Horarios programados de la ficha.
     * 7. La ficha propiamente dicha.
     *
     * Preserva intactos: usuarios/instructores, aprendices de otras fichas, horarios de otras fichas y asistencias de otras fichas.
     *
     * @param int $idFicha
     * @return array Resumen de registros eliminados
     * @throws Throwable Si ocurre un fallo en cualquier paso para provocar rollback total
     */
    public function eliminarFichaCompleta(int $idFicha): array
    {
        $db = Conexion::obtener();
        $iniciadaAqui = false;

        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $iniciadaAqui = true;
        }

        try {
            // 0. Verificar existencia de la ficha
            $ficha = $this->buscarFichaPorId($idFicha);
            if (!$ficha) {
                throw new InvalidArgumentException("La ficha especificada no existe.");
            }

            // 1. Obtener los aprendices vinculados a la ficha (id_aprendiz e id_usuario)
            $stmtApr = $db->prepare("SELECT id_aprendiz, id_usuario FROM aprendices WHERE id_ficha = :id");
            $stmtApr->execute([':id' => $idFicha]);
            $filasApr = $stmtApr->fetchAll(PDO::FETCH_ASSOC);

            $idsAprendices = array_column($filasApr, 'id_aprendiz');
            $idsUsuariosAprendices = array_filter(array_column($filasApr, 'id_usuario'));
            $totalAprendices = count($idsAprendices);

            if ($totalAprendices > 0) {
                // Crear lista de placeholders ?,?,? para consultas IN seguras
                $placeholdersApr = implode(',', array_fill(0, $totalAprendices, '?'));

                // a. Eliminar revisiones de excusas de estos aprendices
                $sqlRev = "DELETE FROM revision_excusas WHERE id_excusa IN (
                               SELECT id_excusa FROM excusas_medicas WHERE aprendices_id_aprendiz IN ($placeholdersApr)
                           )";
                $stmtRev = $db->prepare($sqlRev);
                $stmtRev->execute($idsAprendices);

                // b. Eliminar excusas médicas de estos aprendices
                $sqlExc = "DELETE FROM excusas_medicas WHERE aprendices_id_aprendiz IN ($placeholdersApr)";
                $stmtExc = $db->prepare($sqlExc);
                $stmtExc->execute($idsAprendices);

                // c. Eliminar asistencias de estos aprendices
                $sqlAsistApr = "DELETE FROM asistencias WHERE id_aprendiz IN ($placeholdersApr)";
                $stmtAsistApr = $db->prepare($sqlAsistApr);
                $stmtAsistApr->execute($idsAprendices);

                // d. Eliminar los aprendices de la ficha
                $stmtDelApr = $db->prepare("DELETE FROM aprendices WHERE id_ficha = :id");
                $stmtDelApr->execute([':id' => $idFicha]);

                // e. Eliminar las cuentas de usuario de los aprendices (estrictamente id_role = 3, preservando instructores y administradores)
                if (!empty($idsUsuariosAprendices)) {
                    $placeholdersUsr = implode(',', array_fill(0, count($idsUsuariosAprendices), '?'));
                    $sqlDelUsr = "DELETE FROM usuarios WHERE id_usuario IN ($placeholdersUsr) AND id_role = 3";
                    $stmtDelUsr = $db->prepare($sqlDelUsr);
                    $stmtDelUsr->execute(array_values($idsUsuariosAprendices));
                }
            }

            // 2. Desvincular cualquier asistencia restante asociada a los horarios de esta ficha
            $stmtDesvHor = $db->prepare("
                UPDATE asistencias 
                SET id_horario = NULL 
                WHERE id_horario IN (SELECT id_horario FROM horarios WHERE id_ficha = :id)
            ");
            $stmtDesvHor->execute([':id' => $idFicha]);

            // 3. Contar y eliminar los horarios de esta ficha
            $stmtCountHor = $db->prepare("SELECT COUNT(*) FROM horarios WHERE id_ficha = :id");
            $stmtCountHor->execute([':id' => $idFicha]);
            $totalHorarios = (int)$stmtCountHor->fetchColumn();

            $stmtDelHor = $db->prepare("DELETE FROM horarios WHERE id_ficha = :id");
            $stmtDelHor->execute([':id' => $idFicha]);

            // 4. Eliminar asignaciones en fichas_has_usuarios
            $stmtDelFhu = $db->prepare("DELETE FROM fichas_has_usuarios WHERE fichas_id_ficha = :id");
            $stmtDelFhu->execute([':id' => $idFicha]);

            // 5. Eliminar la ficha
            $stmtDelFicha = $db->prepare("DELETE FROM fichas WHERE id_ficha = :id");
            $stmtDelFicha->execute([':id' => $idFicha]);

            if ($iniciadaAqui) {
                $db->commit();
            }

            return [
                'exito'                 => true,
                'id_ficha'              => $idFicha,
                'numero_ficha'          => $ficha['numero_ficha'],
                'nombre_programa'       => $ficha['nombre_programa'],
                'aprendices_eliminados' => $totalAprendices,
                'horarios_eliminados'   => $totalHorarios,
            ];
        } catch (\Throwable $e) {
            if ($iniciadaAqui && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al eliminar ficha {$idFicha} en cascada atómica: " . $e->getMessage());
            throw $e;
        }
    }
}
