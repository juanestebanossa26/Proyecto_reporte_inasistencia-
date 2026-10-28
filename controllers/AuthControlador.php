<?php
// Controlador de autenticación.
// Valida credenciales, guarda la sesión y cierra sesión. No contiene SQL
// (vive en UsuarioModelo) ni HTML propio (vive en vistas/login.php).

class AuthControlador extends ControladorBase
{
    // accion=login (GET) -> solo muestra el formulario.
    public function mostrarLogin(): void
    {
        require BASE_PATH . '/views/login.php';
    }

    // accion=autenticar (POST) -> procesa el formulario de vistas/login.php.
    public function login(): void
    {
        $this->exigirCsrfValido('login');

        $usuarioInput = trim($_POST['usuario']);
        $password     = (string) ($_POST['password'] ?? '');

        if ($usuarioInput === '' || $password === '') {
            $this->redirigirAccion('login', ['error' => '11']);
        }

        try {
            $usuario = (new UsuarioModelo())->buscarParaLogin($usuarioInput);
        } catch (PDOException $e) {
            error_log('Error en login: ' . $e->getMessage());
            $this->redirigirAccion('login', ['error' => '500']);
        }

        if (!$usuario) {
            $this->redirigirAccion('login', ['error' => '1']);
        }

        if (!empty($usuario['estado_inactivo'])) {
            $this->redirigirAccion('login', ['error' => 'inactivo']);
        }

        // Verificación de contraseña compatible con bcrypt, texto plano y hashes legacy
        $hashAlmacenado = (string) ($usuario['contrasena'] ?? '');
        $passwordValida = false;

        if (password_verify($password, $hashAlmacenado)) {
            $passwordValida = true;
        } elseif ($hashAlmacenado === $password || trim($hashAlmacenado) === $password) {
            // Contraseña en texto plano en la base de datos
            $passwordValida = true;
            // Migrarla automáticamente a bcrypt
            try {
                (new UsuarioModelo())->cambiarContrasena((int) $usuario['id_usuario'], password_hash($password, PASSWORD_BCRYPT));
            } catch (Throwable $t) {
                error_log('No se pudo migrar contraseña a hash: ' . $t->getMessage());
            }
        } elseif (md5($password) === $hashAlmacenado || sha1($password) === $hashAlmacenado) {
            // Contraseña con hash legacy MD5 o SHA1
            $passwordValida = true;
            try {
                (new UsuarioModelo())->cambiarContrasena((int) $usuario['id_usuario'], password_hash($password, PASSWORD_BCRYPT));
            } catch (Throwable $t) {
                error_log('No se pudo migrar contraseña a hash: ' . $t->getMessage());
            }
        }

        if (!$passwordValida) {
            $this->redirigirAccion('login', ['error' => '2']);
        }

        // Prevenir fijación de sesión (Session Fixation) regenerando el ID
        session_regenerate_id(true);

        // Establecer variables de sesión del usuario
        $_SESSION['id']        = $usuario['id_usuario'];
        $_SESSION['nombre']    = $usuario['nombre_completo'];
        $_SESSION['usuario']   = $usuario['usuario'];
        $_SESSION['Documento'] = $usuario['usuario'];
        $_SESSION['Correo']    = $usuario['correo_electronico'];
        $_SESSION['rol']       = $usuario['nombre_role'];

        $idRole = (int) ($usuario['id_role'] ?? 0);
        $rol    = strtolower(trim((string) ($_SESSION['rol'] ?? '')));

        // Redirigir según el rol o el id_role detectado
        if (in_array($rol, ['administrador', 'admin'], true) || $idRole === 1) {
            $_SESSION['rol'] = 'Administrador';
            $this->redirigirAccion('dashboard');
        } elseif (in_array($rol, ['instructor', 'docente', 'profesor'], true) || $idRole === 2) {
            $_SESSION['rol'] = 'Instructor';
            $this->redirigirAccion('dashboard');
        } elseif (in_array($rol, ['aprendiz', 'estudiante', 'alumno'], true) || $idRole === 3) {
            $_SESSION['rol'] = 'Aprendiz';
            $this->redirigirAccion('historial');
        } else {
            // Por defecto, si el rol no coincide con las palabras anteriores pero está autenticado
            $_SESSION['rol'] = !empty($usuario['nombre_role']) ? $usuario['nombre_role'] : 'Administrador';
            $this->redirigirAccion('dashboard');
        }
    }

    public function logout(): void
    {
        $_SESSION = [];

        // Destruir la cookie de sesión en el navegador
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        // Cabeceras estrictas anti-caché
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
        header('Pragma: no-cache');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

        $this->redirigirAccion('login');
    }

    // accion=perfil -> muestra la vista de perfil con los datos completos del usuario
    public function mostrarPerfil(): void
    {
        $this->exigirSesion();

        $usuarioModelo = new UsuarioModelo();
        $idUsuario = (int) ($_SESSION['id'] ?? 0);
        $usuarioActual = $usuarioModelo->buscarPorId($idUsuario);

        if (!$usuarioActual) {
            $usuarioActual = [
                'nombre_completo' => $_SESSION['nombre'] ?? 'Usuario',
                'usuario' => $_SESSION['usuario'] ?? $_SESSION['Documento'] ?? '',
                'correo_electronico' => $_SESSION['Correo'] ?? '',
                'nombre_role' => $_SESSION['rol'] ?? 'Aprendiz',
                'area' => 'No especificada',
                'estado' => 'Activo'
            ];
        }

        // Estadísticas según el rol para el apartado de resumen interactivo
        $rol = strtolower(trim($_SESSION['rol'] ?? ''));
        $stats = [];

        try {
            if ($rol === 'aprendiz') {
                $asistenciaModelo = new AsistenciaModelo();
                $stats['retardos'] = $asistenciaModelo->contarPorEstado($idUsuario, 'retardo');
                $stats['inasistencias'] = $asistenciaModelo->contarPorEstado($idUsuario, 'inasistencia');
                $stats['excusas_pendientes'] = $asistenciaModelo->contarExcusasPendientes($idUsuario);
            } elseif (in_array($rol, ['instructor', 'administrador'], true)) {
                $excusaModelo = new ExcusaModelo();
                $stats['excusas_pendientes'] = $excusaModelo->contarPendientesPorInstructor($idUsuario);
            }
        } catch (PDOException $e) {
            error_log('Error al cargar stats de perfil: ' . $e->getMessage());
        }

        require BASE_PATH . '/views/perfil.php';
    }

    // accion=cambiarPassword (POST) -> valida y actualiza la contraseña del usuario logueado
    public function cambiarPassword(): void
    {
        $this->exigirSesion();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigirAccion('perfil');
            return;
        }

        $this->exigirCsrfValido('perfil');

        $passwordActual  = (string) ($_POST['password_actual'] ?? '');
        $passwordNueva   = (string) ($_POST['password_nueva'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
        $idUsuario       = (int) ($_SESSION['id'] ?? 0);

        if ($passwordActual === '' || $passwordNueva === '' || $passwordConfirm === '') {
            Seguridad::alertaYRedirigir(
                'warning',
                'Campos requeridos',
                'Por favor completa todos los campos del formulario de cambio de contraseña.',
                $this->urlAccion('perfil')
            );
            return;
        }

        if ($passwordNueva !== $passwordConfirm) {
            Seguridad::alertaYRedirigir(
                'error',
                'Contraseñas no coinciden',
                'La nueva contraseña y su confirmación no son iguales. Verifica e inténtalo de nuevo.',
                $this->urlAccion('perfil')
            );
            return;
        }

        if (mb_strlen($passwordNueva) < 6) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Contraseña muy corta',
                'La nueva contraseña debe tener al menos 6 caracteres.',
                $this->urlAccion('perfil')
            );
            return;
        }

        $usuarioModelo = new UsuarioModelo();

        if (!$usuarioModelo->verificarContrasena($idUsuario, $passwordActual)) {
            Seguridad::alertaYRedirigir(
                'error',
                'Contraseña actual incorrecta',
                'La contraseña actual ingresada no coincide con nuestros registros.',
                $this->urlAccion('perfil')
            );
            return;
        }

        try {
            $nuevoHash = password_hash($passwordNueva, PASSWORD_BCRYPT);
            $usuarioModelo->cambiarContrasena($idUsuario, $nuevoHash);

            Seguridad::alertaYRedirigir(
                'success',
                '¡Contraseña actualizada!',
                'Tu contraseña ha sido modificada exitosamente. Úsala en tu próximo inicio de sesión.',
                $this->urlAccion('perfil')
            );
        } catch (PDOException $e) {
            error_log('Error al cambiar contraseña: ' . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error del servidor',
                'No fue posible actualizar tu contraseña en este momento. Intenta de nuevo más tarde.',
                $this->urlAccion('perfil')
            );
        }
    }
}