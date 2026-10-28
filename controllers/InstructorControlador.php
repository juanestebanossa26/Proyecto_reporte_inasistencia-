<?php
// Controlador de gestión de instructores (alta y edición).
// Las validaciones y el SQL viven en UsuarioModelo; aquí solo se orquesta
// la petición y se decide qué alerta mostrar.

class InstructorControlador extends ControladorBase
{
    private const ACCION_LISTADO = 'instructores';

    // accion=instructores -> arma los datos y entrega el control a la vista.
    // El formulario de "editar" también apunta aquí (POST con id_editar),
    // para poder precargar el instructor elegido en el modal.
    public function listar(): void
    {
        $this->exigirSesion(['Administrador']);

        $usuarioModelo     = new UsuarioModelo();
        $instructorEditar  = null;

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id_editar'])) {
                $idEdit = (int) filter_var($_POST['id_editar'], FILTER_SANITIZE_NUMBER_INT);
                $instructorEditar = $usuarioModelo->buscarInstructorPorId($idEdit);
            }

            $instructores = $usuarioModelo->listarInstructores();
        } catch (PDOException $e) {
            error_log('Error al listar instructores: ' . $e->getMessage());
            $instructores = [];
            $_SESSION['mensaje_error'] = 'No se pudieron cargar los instructores. Intenta de nuevo.';
        }

        require BASE_PATH . '/views/instructores.php';
    }

    public function guardar(): void
    {
        $this->exigirSesion(['Administrador']);
        $this->exigirCsrfValido(self::ACCION_LISTADO);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo 'Método de envío de datos es diferente.';
            return;
        }

        $nombre    = trim($_POST['nombre'] ?? '');
        $usuario   = trim($_POST['usuario'] ?? $_POST['documento'] ?? '');
        $area      = trim($_POST['area'] ?? '');
        if ($area !== '' && (strpos($area, '-') !== false || strpos($area, '–') !== false || strpos($area, '—') !== false)) {
            $partesArea = preg_split('/[-–—]/u', $area);
            $area = trim($partesArea[0] ?? '');
        }
        $correo    = trim($_POST['correo'] ?? '');
        $password  = trim($_POST['password'] ?? '');

        if ($nombre === '' || $usuario === '' || $area === '' || $correo === '' || $password === '') {
            Seguridad::alertaYRedirigir('warning', 'Campos incompletos', 'Llene todos los campos.', $this->urlAccion(self::ACCION_LISTADO));
            return;
        }

        if (!Seguridad::esCorreoGmailValido($correo)) {
            Seguridad::alertaYRedirigir('error', 'Correo inválido', 'Ingrese una dirección de correo válida (@gmail.com, @sena.edu.co o @misena.edu.co).', $this->urlAccion(self::ACCION_LISTADO));
            return;
        }

        $modelo  = new UsuarioModelo();
        $nombre  = Seguridad::sanitizar($nombre);
        $usuario = Seguridad::sanitizar($usuario);
        $area    = Seguridad::sanitizar($area);
        $correo  = filter_var($correo, FILTER_SANITIZE_EMAIL);

        if ($modelo->existeUsuario($usuario)) {
            Seguridad::alertaYRedirigir('warning', 'Usuario en uso', 'El usuario ya existe en el sistema.', $this->urlAccion(self::ACCION_LISTADO));
            return;
        }

        if ($modelo->existeCorreo($correo)) {
            Seguridad::alertaYRedirigir('warning', 'Correo en uso', 'El correo ya existe.', $this->urlAccion(self::ACCION_LISTADO));
            return;
        }

        try {
            $modelo->crearInstructor($nombre, $usuario, $area, $correo, password_hash($password, PASSWORD_BCRYPT));
            Seguridad::alertaYRedirigir('success', '¡Registrado con éxito!', 'El instructor ha sido registrado correctamente.', $this->urlAccion(self::ACCION_LISTADO));
        } catch (PDOException $e) {
            error_log('Error al crear instructor: ' . $e->getMessage());
            Seguridad::alertaYRedirigir('error', '¡No se pudo realizar el registro!', 'Hubo un error al procesar la solicitud.', $this->urlAccion(self::ACCION_LISTADO));
        }
    }

    public function actualizar(): void
    {
        $this->exigirSesion(['Administrador']);
        $this->exigirCsrfValido(self::ACCION_LISTADO);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo 'Método de envío no permitido.';
            return;
        }

        $id        = filter_var($_POST['id_usuario'] ?? '', FILTER_SANITIZE_NUMBER_INT);
        $nombre    = trim($_POST['nombre'] ?? '');
        $usuario   = trim($_POST['usuario'] ?? $_POST['documento'] ?? '');
        $area      = trim($_POST['area'] ?? '');
        if ($area !== '' && (strpos($area, '-') !== false || strpos($area, '–') !== false || strpos($area, '—') !== false)) {
            $partesArea = preg_split('/[-–—]/u', $area);
            $area = trim($partesArea[0] ?? '');
        }
        $correo    = trim($_POST['correo'] ?? '');
        $password  = trim($_POST['password'] ?? '');

        if ($id === '' || $nombre === '' || $usuario === '' || $area === '' || $correo === '') {
            Seguridad::alertaYRedirigir('warning', 'Campos incompletos', 'Por favor complete todos los campos obligatorios.', $this->urlAccion(self::ACCION_LISTADO));
            return;
        }

        if (!Seguridad::esCorreoGmailValido($correo)) {
            Seguridad::alertaYRedirigir('error', 'Correo inválido', 'Ingrese una dirección de correo válida (@gmail.com, @sena.edu.co o @misena.edu.co).', $this->urlAccion(self::ACCION_LISTADO));
            return;
        }

        $id      = (int) $id;
        $nombre  = Seguridad::sanitizar($nombre);
        $usuario = Seguridad::sanitizar($usuario);
        $area    = Seguridad::sanitizar($area);
        $correo  = filter_var($correo, FILTER_SANITIZE_EMAIL);

        $modelo = new UsuarioModelo();

        if ($modelo->existeUsuario($usuario, $id)) {
            Seguridad::alertaYRedirigir('warning', 'Usuario en uso', 'El usuario ya está asignado a otra persona.', $this->urlAccion(self::ACCION_LISTADO));
            return;
        }

        if ($modelo->existeCorreo($correo, $id)) {
            Seguridad::alertaYRedirigir('warning', 'Correo en uso', 'El correo electrónico ya está asignado a otro usuario.', $this->urlAccion(self::ACCION_LISTADO));
            return;
        }

        try {
            $passwordHash = $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : null;
            $modelo->actualizarInstructor($id, $nombre, $usuario, $area, $correo, $passwordHash);
            Seguridad::alertaYRedirigir('success', '¡Actualizado con éxito!', 'Los datos del instructor han sido actualizados.', $this->urlAccion(self::ACCION_LISTADO));
        } catch (PDOException $e) {
            error_log('Error al actualizar instructor: ' . $e->getMessage());
            Seguridad::alertaYRedirigir('error', '¡Error al actualizar!', 'No se pudo guardar la información.', $this->urlAccion(self::ACCION_LISTADO));
        }
    }

    public function cambiarEstado(): void
    {
        $this->exigirSesion(['Administrador']);
        $this->exigirCsrfValido(self::ACCION_LISTADO);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Seguridad::alertaYRedirigir('error', 'Error', 'Método no permitido.', $this->urlAccion(self::ACCION_LISTADO));
            return;
        }

        $id = filter_var($_POST['id_usuario'] ?? '', FILTER_SANITIZE_NUMBER_INT);
        $estadoActual = trim($_POST['estado_actual'] ?? '');

        if (empty($id)) {
            Seguridad::alertaYRedirigir('warning', 'Atención', 'Identificador de usuario inválido.', $this->urlAccion(self::ACCION_LISTADO));
            return;
        }

        // Alternar estado: si está Activo pasa a Inactivo, de lo contrario pasa a Activo
        $nuevoEstado = (strtolower($estadoActual) === 'activo') ? 'Inactivo' : 'Activo';

        try {
            $modelo = new UsuarioModelo();
            $modelo->cambiarEstadoInstructor((int) $id, $nuevoEstado);

            $mensaje = $nuevoEstado === 'Inactivo' ? 'El instructor ha sido inactivado.' : 'El instructor ha sido activado.';
            Seguridad::alertaYRedirigir('success', '¡Estado actualizado!', $mensaje, $this->urlAccion(self::ACCION_LISTADO));
        } catch (PDOException $e) {
            error_log('Error al cambiar estado del instructor: ' . $e->getMessage());
            Seguridad::alertaYRedirigir('error', 'Error', 'No se pudo cambiar el estado.', $this->urlAccion(self::ACCION_LISTADO));
        }
    }
}