<?php
// Acceso a datos de aprendices.
// Un aprendiz vive en DOS tablas:
//   - usuarios  → datos personales (nombre_completo, usuario, correo, contraseña, id_role=3)
//   - aprendices → datos de SENA (tag_rfid, id_ficha, vínculo con usuarios)
// Toda operación de creación / actualización debe tocar AMBAS tablas

class AprendizModelo
{
    // Lista todos los aprendices combinando usuarios + aprendices + fichas.
    public function listar(): array
    {
        $sql = "SELECT
                    usuarios.id_usuario,
                    usuarios.nombre_completo,
                    usuarios.usuario,
                    usuarios.correo_electronico,
                    usuarios.estado, 
                    aprendices.id_aprendiz,
                    aprendices.tag_rfid,
                    aprendices.id_ficha,
                    CONCAT(fichas.numero_ficha, ' · ', fichas.nombre_programa) AS ficha_label
                FROM aprendices
                JOIN usuarios  ON usuarios.id_usuario  = aprendices.id_usuario
                JOIN fichas    ON fichas.id_ficha      = aprendices.id_ficha
                ORDER BY usuarios.nombre_completo ASC";

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    // Devuelve los datos de UN aprendiz para cargarlos en el formulario de edición.
    public function buscarPorIdUsuario(int $idUsuario): array|false
    {
        $sql = "SELECT
                    usuarios.id_usuario,
                    usuarios.nombre_completo,
                    usuarios.usuario,
                    usuarios.correo_electronico,
                    usuarios.estado,
                    aprendices.id_aprendiz,
                    aprendices.tag_rfid,
                    aprendices.id_ficha
                FROM aprendices
                JOIN usuarios ON usuarios.id_usuario = aprendices.id_usuario
                WHERE usuarios.id_usuario = :id
                LIMIT 1";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    // Lista todas las fichas disponibles para el <select> del formulario.
    public function listarFichas(): array
    {
        $sql = "SELECT id_ficha, numero_ficha, nombre_programa
                FROM fichas
                ORDER BY numero_ficha ASC";

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    // Comprueba si el usuario (documento o nombre de usuario) ya existe en `usuarios`.
    public function existeUsuario(string $usuario, ?int $excluirIdUsuario = null): bool
    {
        $usuario = trim($usuario);
        if ($usuario === '') return false;

        $sql    = "SELECT id_usuario FROM usuarios WHERE usuario = :user";
        $params = [':user' => $usuario];

        if ($excluirIdUsuario !== null && $excluirIdUsuario > 0) {
            $sql .= " AND id_usuario != :id";
            $params[':id'] = $excluirIdUsuario;
        }

        $stmt = Conexion::obtener()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    // Alias para compatibilidad hacia atrás
    public function existeDocumento(string $documento, ?int $excluirIdUsuario = null): bool
    {
        return $this->existeUsuario($documento, $excluirIdUsuario);
    }

    // Comprueba si el correo ya existe en `usuarios`.
    public function existeCorreo(string $correo, ?int $excluirIdUsuario = null): bool
    {
        $correo = trim($correo);
        if ($correo === '') return false;

        $sql    = "SELECT id_usuario FROM usuarios WHERE correo_electronico = :correo";
        $params = [':correo' => $correo];

        if ($excluirIdUsuario !== null && $excluirIdUsuario > 0) {
            $sql .= " AND id_usuario != :id";
            $params[':id'] = $excluirIdUsuario;
        }

        $stmt = Conexion::obtener()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    // Comprueba si el tag RFID ya existe en `aprendices` para otro aprendiz diferente.
    public function existeRfid(string $rfid, ?int $excluirIdUsuario = null): bool
    {
        $rfid = trim($rfid);
        if ($rfid === '' || $rfid === '0' || strtolower($rfid) === 'sin tag' || strtolower($rfid) === 'null') {
            return false;
        }

        $sql    = "SELECT aprendices.id_aprendiz FROM aprendices WHERE aprendices.tag_rfid = :rfid";
        $params = [':rfid' => $rfid];

        if ($excluirIdUsuario !== null && $excluirIdUsuario > 0) {
            $sql .= " AND aprendices.id_usuario != :id";
            $params[':id'] = $excluirIdUsuario;
        }

        $stmt = Conexion::obtener()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function crear(string $nombre, string $usuario, ?string $correo, string $passwordHash, int $idFicha, ?string $tagRfid = null): void
    {
        $db = Conexion::obtener();
        $db->beginTransaction();

        try {
            $correoVal = (trim($correo ?? '') !== '') ? trim($correo) : null;
            $tagVal    = (trim($tagRfid ?? '') !== '' && trim($tagRfid) !== '0') ? trim($tagRfid) : null;

            // Insertar en usuarios con rol Aprendiz (id_role = 3)
            $sqlUsuario = "INSERT INTO usuarios (nombre_completo, usuario, correo_electronico, estado, contrasena, id_role)
                           VALUES (:nombre, :usuario, :correo, 'Activo', :pass, 3)";

            $stmtU = $db->prepare($sqlUsuario);
            $stmtU->bindParam(':nombre',  $nombre);
            $stmtU->bindParam(':usuario', $usuario);
            if ($correoVal !== null) {
                $stmtU->bindParam(':correo', $correoVal);
            } else {
                $stmtU->bindValue(':correo', null, PDO::PARAM_NULL);
            }
            $stmtU->bindParam(':pass', $passwordHash);
            $stmtU->execute();

            $idUsuarioNuevo = (int) $db->lastInsertId();

            // Insertar en aprendices vinculando con el nuevo usuario
            $sqlAprendiz = "INSERT INTO aprendices (id_usuario, id_ficha, tag_rfid)
                            VALUES (:id_usuario, :id_ficha, :rfid)";

            $stmtA = $db->prepare($sqlAprendiz);
            $stmtA->bindParam(':id_usuario', $idUsuarioNuevo, PDO::PARAM_INT);
            $stmtA->bindParam(':id_ficha',   $idFicha,        PDO::PARAM_INT);
            if ($tagVal !== null) {
                $stmtA->bindParam(':rfid', $tagVal);
            } else {
                $stmtA->bindValue(':rfid', null, PDO::PARAM_NULL);
            }
            $stmtA->execute();

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $idUsuario, string $nombre, string $usuario, ?string $correo, ?string $passwordHash, int $idFicha, ?string $tagRfid = null): void
    {
        $db = Conexion::obtener();
        $db->beginTransaction();

        try {
            $correoVal = (trim($correo ?? '') !== '') ? trim($correo) : null;
            $tagVal    = (trim($tagRfid ?? '') !== '' && trim($tagRfid) !== '0') ? trim($tagRfid) : null;

            // Actualizar tabla usuarios
            if ($passwordHash !== null) {
                $sqlU = "UPDATE usuarios
                         SET nombre_completo    = :nombre,
                             usuario            = :usuario,
                             correo_electronico = :correo,
                             contrasena         = :pass
                         WHERE id_usuario = :id AND id_role = 3";
            } else {
                $sqlU = "UPDATE usuarios 
                         SET nombre_completo    = :nombre,
                             usuario            = :usuario,
                             correo_electronico = :correo
                         WHERE id_usuario = :id AND id_role = 3";
            }

            $stmtU = $db->prepare($sqlU);
            $stmtU->bindParam(':nombre',  $nombre);
            $stmtU->bindParam(':usuario', $usuario);
            if ($correoVal !== null) {
                $stmtU->bindParam(':correo', $correoVal);
            } else {
                $stmtU->bindValue(':correo', null, PDO::PARAM_NULL);
            }
            if ($passwordHash !== null) {
                $stmtU->bindParam(':pass', $passwordHash);
            }
            $stmtU->bindParam(':id', $idUsuario, PDO::PARAM_INT);
            $stmtU->execute();

            // Actualizar tabla aprendices
            $sqlA = "UPDATE aprendices
                     SET id_ficha = :id_ficha,
                         tag_rfid = :rfid
                     WHERE id_usuario = :id_usuario";

            $stmtA = $db->prepare($sqlA);
            $stmtA->bindParam(':id_ficha',   $idFicha,   PDO::PARAM_INT);
            if ($tagVal !== null) {
                $stmtA->bindParam(':rfid', $tagVal);
            } else {
                $stmtA->bindValue(':rfid', null, PDO::PARAM_NULL);
            }
            $stmtA->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmtA->execute();

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // Cambia el estado del aprendiz en la tabla usuarios
    public function cambiarEstado(int $idUsuario, string $nuevoEstado): bool
    {
        $sql = "UPDATE usuarios 
                SET estado = :estado 
                WHERE id_usuario = :id AND id_role = 3";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':estado', $nuevoEstado);
        $stmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
