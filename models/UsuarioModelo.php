<?php
// Acceso a datos de la tabla usuarios (y su rol en la tabla roles).
// Este modelo no conoce SweetAlert, ni HTML, ni $_SESSION: solo habla con
// la base de datos.
//
// Estructura de la tabla usuarios según el diagrama:
// id_usuario, nombre_completo, usuario, correo_electronico, area, estado, contrasena, id_role

class UsuarioModelo
{
    // Usado por AuthControlador::login(). Trae el usuario junto con el
    // nombre de su rol, comprobando que se encuentre activo.
    public function buscarParaLogin(string $identificador): array|false
    {
        $db = Conexion::obtener();
        $identificador = trim($identificador);

        // 1. Buscar por usuario o correo electrónico usando parámetros nombrados únicos
        try {
            $sql = "SELECT
                        usuarios.id_usuario,
                        usuarios.nombre_completo,
                        usuarios.usuario,
                        usuarios.contrasena,
                        usuarios.correo_electronico,
                        usuarios.area,
                        usuarios.estado,
                        usuarios.id_role,
                        COALESCE(roles.nombre_role, 'Usuario') AS nombre_role
                    FROM usuarios
                    LEFT JOIN roles ON roles.id_role = usuarios.id_role
                    WHERE (LOWER(TRIM(usuarios.usuario)) = LOWER(:user) OR LOWER(TRIM(usuarios.correo_electronico)) = LOWER(:email))
                      AND (usuarios.estado IS NULL OR usuarios.estado = '' OR LOWER(TRIM(usuarios.estado)) IN ('activo', '1', 'habilitado'))
                    LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user', $identificador, PDO::PARAM_STR);
            $stmt->bindValue(':email', $identificador, PDO::PARAM_STR);
            $stmt->execute();
            $usuario = $stmt->fetch();
            if ($usuario) {
                return $usuario;
            }
        } catch (PDOException $e) {
            error_log('Error en buscarParaLogin: ' . $e->getMessage());
        }

        // 2. Verificar si el usuario existe pero está inactivo para informar adecuadamente
        try {
            $sqlCheck = "SELECT id_usuario, estado FROM usuarios 
                         WHERE (LOWER(TRIM(usuario)) = LOWER(:chk_user) OR LOWER(TRIM(correo_electronico)) = LOWER(:chk_email)) 
                         LIMIT 1";
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->bindValue(':chk_user', $identificador, PDO::PARAM_STR);
            $stmtCheck->bindValue(':chk_email', $identificador, PDO::PARAM_STR);
            $stmtCheck->execute();
            $check = $stmtCheck->fetch();
            if ($check && in_array(strtolower(trim((string)$check['estado'])), ['inactivo', 'desactivado', '0'], true)) {
                return ['estado_inactivo' => true];
            }
        } catch (PDOException $e) {
            // Ignorar
        }

        return false;
    }

    // id_role = 2 corresponde a Instructor en este proyecto.
    public function listarInstructores(): array
    {
        $sql = "SELECT id_usuario, nombre_completo, estado, area, usuario, correo_electronico
                FROM usuarios
                WHERE id_role = 2
                ORDER BY nombre_completo ASC";

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    public function buscarInstructorPorId(int $id): array|false
    {
        $sql = "SELECT id_usuario, nombre_completo, usuario, area, correo_electronico
                FROM usuarios
                WHERE id_usuario = :id AND id_role = 2";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    // Comprueba si el usuario ya existe en `usuarios`.
    public function existeUsuario(string $usuario, ?int $excluirId = null): bool
    {
        $sql = "SELECT id_usuario FROM usuarios WHERE usuario = :user";
        $params = [':user' => $usuario];

        if ($excluirId !== null) {
            $sql .= " AND id_usuario != :id";
            $params[':id'] = $excluirId;
        }

        $stmt = Conexion::obtener()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    // Alias para compatibilidad hacia atrás
    public function existeDocumento(string $documento, ?int $excluirId = null): bool
    {
        return $this->existeUsuario($documento, $excluirId);
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

    // Crea un instructor nuevo (id_role = 2, fijo).
    public function crearInstructor(string $nombre, string $usuario, string $area, string $correo, string $passwordHash): void
    {
        $sql = "INSERT INTO usuarios (nombre_completo, usuario, area, correo_electronico, estado, contrasena, id_role)
                VALUES (:nombre, :usuario, :area, :correo, 'Activo', :pass, 2)";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':usuario', $usuario);
        $stmt->bindParam(':area', $area);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':pass', $passwordHash);
        $stmt->execute();
    }

    // Si $passwordHash es null, la contraseña actual se conserva.
    public function actualizarInstructor(int $id, string $nombre, string $usuario, string $area, string $correo, ?string $passwordHash): void
    {
        $sql = "UPDATE usuarios
                SET nombre_completo = :nombre,
                    usuario = :usuario,
                    area = :area,
                    correo_electronico = :correo" . 
                    ($passwordHash !== null ? ", contrasena = :pass" : "") . "
                WHERE id_usuario = :id";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':usuario', $usuario);
        $stmt->bindParam(':area', $area);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($passwordHash !== null) {
            $stmt->bindParam(':pass', $passwordHash);
        }

        $stmt->execute();
    }

    public function cambiarEstadoInstructor(int $id, string $nuevoEstado): bool
    {
        $sql = "UPDATE usuarios 
                SET estado = :estado 
                WHERE id_usuario = :id AND id_role = 2";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':estado', $nuevoEstado);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Busca un usuario por su ID primario, incluyendo su rol
    public function buscarPorId(int $idUsuario): array|false
    {
        try {
            $sql = "SELECT
                        usuarios.id_usuario,
                        usuarios.nombre_completo,
                        usuarios.usuario,
                        usuarios.correo_electronico,
                        usuarios.area,
                        usuarios.estado,
                        usuarios.contrasena,
                        usuarios.id_role,
                        roles.nombre_role
                    FROM usuarios
                    LEFT JOIN roles ON roles.id_role = usuarios.id_role
                    WHERE usuarios.id_usuario = :id
                    LIMIT 1";

            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch();
        } catch (PDOException $e) {
            $sql = "SELECT
                        usuarios.id_usuario,
                        usuarios.nombre_completo,
                        usuarios.num_documento AS usuario,
                        usuarios.correo_electronico,
                        usuarios.area,
                        usuarios.estado,
                        usuarios.contrasena,
                        usuarios.id_role,
                        roles.nombre_role
                    FROM usuarios
                    LEFT JOIN roles ON roles.id_role = usuarios.id_role
                    WHERE usuarios.id_usuario = :id
                    LIMIT 1";

            $stmt = Conexion::obtener()->prepare($sql);
            $stmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch();
        }
    }

    // Verifica si la contraseña actual ingresada coincide con el hash o texto plano en la base de datos
    public function verificarContrasena(int $idUsuario, string $contrasenaActual): bool
    {
        $sql = "SELECT contrasena FROM usuarios WHERE id_usuario = :id LIMIT 1";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        $hash = (string) $stmt->fetchColumn();
        if ($hash === '') {
            return false;
        }

        return password_verify($contrasenaActual, $hash);
    }

    // Actualiza la contraseña de un usuario
    public function cambiarContrasena(int $idUsuario, string $nuevaContrasenaHash): bool
    {
        $sql = "UPDATE usuarios SET contrasena = :pass WHERE id_usuario = :id";
        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':pass', $nuevaContrasenaHash, PDO::PARAM_STR);
        $stmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);

        return $stmt->execute();
    }
}