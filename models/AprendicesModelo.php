<?php
// Acceso a datos de aprendices.
// Un aprendiz vive en DOS tablas:
//   - usuarios  → datos personales (nombre, documento, correo, contraseña, id_role=3)
//   - aprendices → datos de SENA (tag_rfid, id_ficha, vínculo con usuarios)
// Toda operación de creación / actualización debe tocar AMBAS tablas


class AprendizModelo
{
     // Lista todos los aprendices combinando usuarios + aprendices + fichas.
     // Devuelve un array con las columnas que necesita la vista.
     
    public function listar(): array
    {
        $sql = "SELECT
                    usuarios.id_usuario,
                    usuarios.nombre_completo,
                    usuarios.apellido,
                    usuarios.num_documento,
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
                    usuarios.apellido,
                    usuarios.num_documento,
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

    //Lista todas las fichas disponibles para el <select> del formulario.
     
    public function listarFichas(): array
    {
        $sql = "SELECT id_ficha, numero_ficha, nombre_programa
                FROM fichas
                ORDER BY numero_ficha ASC";

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    // Comprueba si el número de documento ya existe en `usuarios`.
     // $excluirIdUsuario se usa al editar para no comparar el registro consigo mismo.
     
    public function existeDocumento(string $documento, ?int $excluirIdUsuario = null): bool
    {
        $sql    = "SELECT id_usuario FROM usuarios WHERE num_documento = :doc";
        $params = [':doc' => $documento];

        if ($excluirIdUsuario !== null) {
            $sql .= " AND id_usuario != :id";
            $params[':id'] = $excluirIdUsuario;
        }

        $stmt = Conexion::obtener()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    // Comprueba si el correo ya existe en `usuarios`.
    
    public function existeCorreo(string $correo, ?int $excluirIdUsuario = null): bool
    {
        $sql    = "SELECT id_usuario FROM usuarios WHERE correo_electronico = :correo";
        $params = [':correo' => $correo];

        if ($excluirIdUsuario !== null) {
            $sql .= " AND id_usuario != :id";
            $params[':id'] = $excluirIdUsuario;
        }

        $stmt = Conexion::obtener()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    
      //  Comprueba si el tag RFID ya existe en `aprendices`.
      // $excluirIdUsuario evita el falso positivo al editar el mismo aprendiz.
     
    public function existeRfid(string $rfid, ?int $excluirIdUsuario = null): bool
    {
        $sql    = "SELECT aprendices.id_aprendiz FROM aprendices WHERE aprendices.tag_rfid = :rfid";
        $params = [':rfid' => $rfid];

        if ($excluirIdUsuario !== null) {
            $sql .= " AND aprendices.id_usuario != :id";
            $params[':id'] = $excluirIdUsuario;
        }

        $stmt = Conexion::obtener()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }


    public function crear(string $nombre,string $apellido,string $documento,string $correo,string $passwordHash,int    $idFicha,string $tagRfid): void {
        $db = Conexion::obtener();
        $db->beginTransaction();

        try {
            //  Insertar en usuarios con rol Aprendiz (id_role = 3)
            $sqlUsuario = "INSERT INTO usuarios (nombre_completo, apellido, num_documento, correo_electronico, estado, contrasena, id_role)
                           VALUES (:nombre, :apellido, :doc, :correo, 'Activo', :pass, 3)";

            $stmtU = $db->prepare($sqlUsuario);
            $stmtU->bindParam(':nombre',   $nombre);
            $stmtU->bindParam(':apellido', $apellido);
            $stmtU->bindParam(':doc',      $documento);
            $stmtU->bindParam(':correo',   $correo);
            $stmtU->bindParam(':pass',     $passwordHash);
            $stmtU->execute();

            $idUsuarioNuevo = (int) $db->lastInsertId();

            //  Insertar en aprendices vinculando con el nuevo usuario
            $sqlAprendiz = "INSERT INTO aprendices (id_usuario, id_ficha, tag_rfid)
                            VALUES (:id_usuario, :id_ficha, :rfid)";

            $stmtA = $db->prepare($sqlAprendiz);
            $stmtA->bindParam(':id_usuario', $idUsuarioNuevo, PDO::PARAM_INT);
            $stmtA->bindParam(':id_ficha',   $idFicha,        PDO::PARAM_INT);
            $stmtA->bindParam(':rfid',       $tagRfid);
            $stmtA->execute();

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizar( int $idUsuario, string $nombre, string $apellido, string $documento, string $correo,?string $passwordHash, int    $idFicha, string $tagRfid): void {
        $db = Conexion::obtener();
        $db->beginTransaction();

        try {
            //  Actualizar tabla usuarios
            if ($passwordHash !== null) {
                $sqlU = "UPDATE usuarios
                         SET nombre_completo    = :nombre,
                             apellido           = :apellido,
                             num_documento      = :doc,
                             correo_electronico = :correo,
                             contrasena         = :pass
                         WHERE id_usuario = :id AND id_role = 3";
            } else {$sqlU = "UPDATE usuarios 
                         SET nombre_completo    = :nombre,
                             apellido           = :apellido,
                             num_documento      = :doc,
                             correo_electronico = :correo
                         WHERE id_usuario = :id AND id_role = 3";
            }

            $stmtU = $db->prepare($sqlU);
            $stmtU->bindParam(':nombre',   $nombre);
            $stmtU->bindParam(':apellido', $apellido);
            $stmtU->bindParam(':doc',      $documento);
            $stmtU->bindParam(':correo',   $correo);
            if ($passwordHash !== null) {
                $stmtU->bindParam(':pass', $passwordHash);
            }
            $stmtU->bindParam(':id', $idUsuario, PDO::PARAM_INT);
            $stmtU->execute();

            //  Actualizar tabla aprendices (solo actualiza ficha y rfid
            //    del aprendiz cuyo id_usuario coincide — nunca toca otro registro)
            $sqlA = "UPDATE aprendices
                     SET id_ficha = :id_ficha,
                         tag_rfid = :rfid
                     WHERE id_usuario = :id_usuario";

            $stmtA = $db->prepare($sqlA);
            $stmtA->bindParam(':id_ficha',   $idFicha,   PDO::PARAM_INT);
            $stmtA->bindParam(':rfid',       $tagRfid);
            $stmtA->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmtA->execute();

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            throw $e;
        }
    }

   
}
