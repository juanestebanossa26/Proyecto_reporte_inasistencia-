<?php
// Acceso a datos de la tabla usuarios (y su rol en la tabla roles).
// Este modelo no conoce SweetAlert, ni HTML, ni $_SESSION: solo habla con
// la base de datos.

class UsuarioModelo
{
    // Usado por AuthControlador::login(). Trae el usuario junto con el
    // nombre de su rol, solo si está Activo.
    public function buscarParaLogin(string $documento): array|false
    {
        $sql = "SELECT
                    usuarios.id_usuario,
                    usuarios.nombre_completo,
                    usuarios.num_documento,
                    usuarios.contrasena,
                    usuarios.correo_electronico,
                    usuarios.apellido,
                    roles.nombre_role
                FROM usuarios
                JOIN roles ON roles.id_role = usuarios.id_role
                WHERE num_documento = :doc
                  AND estado = 'Activo'";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':doc', $documento, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch();
    }

    // id_role = 2 corresponde a Instructor en este proyecto.
    public function listarInstructores(): array
    {
        $sql = "SELECT id_usuario, nombre_completo, apellido, estado , area, num_documento, correo_electronico
                FROM usuarios
                WHERE id_role = 2";

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    public function buscarInstructorPorId(int $id): array|false
    {
        $sql = "SELECT id_usuario, nombre_completo, apellido, num_documento,area, correo_electronico
                FROM usuarios
                WHERE id_usuario = :id AND id_role = 2";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function existeDocumento(string $documento, ?int $excluirId = null): bool
    {
        $sql = "SELECT id_usuario FROM usuarios WHERE num_documento = :doc";
        $params = [':doc' => $documento];

        if ($excluirId !== null) {
            $sql .= " AND id_usuario != :id";
            $params[':id'] = $excluirId;
        }

        $stmt = Conexion::obtener()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function existeCorreo(string $correo, ?int $excluirId = null): bool
    {
        $sql = "SELECT id_usuario FROM usuarios WHERE correo_electronico = :correo";
        $params = [':correo' => $correo];

        if ($excluirId !== null) {
            $sql .= " AND id_usuario != :id";
            $params[':id'] = $excluirId;
        }

        $stmt = Conexion::obtener()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    // Crea un instructor nuevo (id_role = 2, fijo, igual que en el
    // proyecto original).
    public function crearInstructor(string $nombre, string $apellido, string $documento,string $area, string $correo, string $passwordHash): void
    {
        $sql = "INSERT INTO usuarios (nombre_completo, apellido, num_documento, correo_electronico, estado, contrasena, id_role)
                VALUES (:nombre, :apellido, :doc, :area , :correo, 'Activo', :pass, 2)";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':apellido', $apellido);
        $stmt->bindParam(':doc', $documento);
        $stmt->bindParam(':area', $area);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':pass', $passwordHash);
        $stmt->execute();
    }

    // Si $passwordHash es null, la contraseña actual se conserva.
    public function actualizarInstructor(int $id, string $nombre, string $apellido, string $documento,string $area, string $correo, ?string $passwordHash): void
    {
        if ($passwordHash !== null) {
            $sql = "UPDATE usuarios
                    SET nombre_completo = :nombre, apellido = :apellido, num_documento = :doc,
                          area = :area, correo_electronico = :correo, contrasena = :pass
                    WHERE id_usuario = :id";
        } else {
            $sql = "UPDATE usuarios
                    SET nombre_completo = :nombre, apellido = :apellido, num_documento = :doc,
                          area = :area, correo_electronico = :correo
                    WHERE id_usuario = :id";
        }

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':apellido', $apellido);
        $stmt->bindParam(':doc', $documento);
         $stmt->bindParam(':area', $area);
        $stmt->bindParam(':correo', $correo);
        if ($passwordHash !== null) {
            $stmt->bindParam(':pass', $passwordHash);
        }
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
