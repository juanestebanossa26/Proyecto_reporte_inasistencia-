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

        $documento = trim($_POST['documento'] ?? '');
        $password  = (string) ($_POST['password'] ?? '');

        if ($documento === '' || $password === '') {
            $this->redirigirAccion('login', ['error' => '11']);
        }

        try {
            $usuario = (new UsuarioModelo())->buscarParaLogin($documento);
        } catch (PDOException $e) {
            error_log('Error en login: ' . $e->getMessage());
            $this->redirigirAccion('login', ['error' => '500']);
        }

        if (!$usuario) {
            $this->redirigirAccion('login', ['error' => '1']);
        }

        if (!password_verify($password, $usuario['contrasena'])) {
            $this->redirigirAccion('login', ['error' => '2']);
        }

        $_SESSION['id']        = $usuario['id_usuario'];
        $_SESSION['nombre']    = $usuario['nombre_completo'];
        $_SESSION['apellidos'] = $usuario['apellido'];
        $_SESSION['Documento'] = $usuario['num_documento'];
        $_SESSION['Correo']    = $usuario['correo_electronico'];
        $_SESSION['rol']       = $usuario['nombre_role'];

        $rol = strtolower(trim($_SESSION['rol']));

        if (in_array($rol, ['administrador', 'instructor'], true)) {
            $this->redirigirAccion('dashboard');
        } elseif ($rol === 'aprendiz') {
            $this->redirigirAccion('historial');
        }

        // Rol desconocido: no debería pasar, pero por seguridad no se deja
        // una sesión "a medias" sin destino claro.
        $this->redirigirAccion('login', ['error' => '1']);
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_unset();
        session_destroy();

        $this->redirigirAccion('login');
    }
}