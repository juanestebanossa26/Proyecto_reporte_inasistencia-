<?php

class AprendizControlador extends ControladorBase
{
    private AprendizModelo $modelo;

    public function __construct()
    {
        $this->modelo = new AprendizModelo();
    }

    // accion=aprendices
    // Carga la lista de aprendices y fichas desde el modelo 
    public function mostrar(): void
    {
        $this->exigirSesion(['Administrador', 'Instructor']);
        try {
            $aprendices = $this->modelo->listar();
            $fichas     = $this->modelo->listarFichas();
        } catch (PDOException $e) {
            error_log('Error al listar aprendices: ' . $e->getMessage());
            $aprendices = [];
            $fichas     = [];
            $_SESSION['mensaje_error'] = 'No se pudieron cargar los datos de aprendices.';
        }
        require BASE_PATH . '/views/aprendices.php';
    }


    // accion=guardar_aprendiz (POST)
    public function guardar(): void
    {
        // 1. Solo el Administrador puede gestionar aprendices
        $this->exigirSesion(['Administrador', 'admin']);


        //  Recoger y limpiar campos del formulario
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);  // 0 = crear, >0 = actualizar
        $nombre    = Seguridad::sanitizar($_POST['nombre']    ?? '');
        $apellido  = Seguridad::sanitizar($_POST['apellido']  ?? '');
        $documento = Seguridad::sanitizar($_POST['documento'] ?? '');
        $correo    = trim($_POST['correo']    ?? '');
        $password  = $_POST['password']  ?? '';
        $idFicha   = (int) ($_POST['ficha_id']    ?? 0);
        $tagRfid   = Seguridad::sanitizar($_POST['codigo_rfid'] ?? '');

        //  Validaciones de campos requeridos
        if ($nombre === '' || $apellido === '' || $documento === '' || $idFicha === 0 || $tagRfid === '') {
            Seguridad::alertaYRedirigir(
                'warning',
                'Campos incompletos',
                'Por favor completa todos los campos obligatorios.',
                $this->urlAccion('aprendices')
            );
        }

        // El correo no es requerido solo si el aprendiz edita (puede dejarse igual)
        // pero si se envía debe tener formato válido
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Correo inválido',
                'El correo electrónico no tiene un formato válido.',
                $this->urlAccion('aprendices')
            );
        }

        // Al crear, la contraseña es obligatoria
        $esNuevo = ($idUsuario === 0);
        if ($esNuevo && $password === '') {
            Seguridad::alertaYRedirigir(
                'warning',
                'Contraseña requerida',
                'Debes asignar una contraseña al crear un aprendiz.',
                $this->urlAccion('aprendices')
            );
        }

        //  Validar duplicados
        if ($this->modelo->existeDocumento($documento, $esNuevo ? null : $idUsuario)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Documento duplicado',
                'Ya existe un usuario registrado con ese número de documento.',
                $this->urlAccion('aprendices')
            );
        }

        if ($correo !== '' && $this->modelo->existeCorreo($correo, $esNuevo ? null : $idUsuario)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Correo duplicado',
                'Ya existe un usuario registrado con ese correo electrónico.',
                $this->urlAccion('aprendices')
            );
        }

        if ($this->modelo->existeRfid($tagRfid, $esNuevo ? null : $idUsuario)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'RFID duplicado',
                'Ese código RFID ya está asignado a otro aprendiz.',
                $this->urlAccion('aprendices')
            );
        }

        //  Hash de la contraseña (solo si se proporcionó una nueva)
        $passwordHash = ($password !== '') ? password_hash($password, PASSWORD_BCRYPT) : null;

        //  Ejecutar la operación correcta según si es nuevo o edición
        try {
            if ($esNuevo) {
                // CREAR: inserta en usuarios + aprendices (transacción)
                $this->modelo->crear(
                    $nombre,
                    $apellido,
                    $documento,
                    $correo,
                    $passwordHash,
                    $idFicha,
                    $tagRfid
                );

                Seguridad::alertaYRedirigir(
                    'success',
                    '¡Aprendiz registrado!',
                    'El aprendiz fue creado exitosamente.',
                    $this->urlAccion('aprendices')
                );
            } else {
                // ACTUALIZAR: verifica que el id_usuario pertenezca a un aprendiz real
                $aprendizExistente = $this->modelo->buscarPorIdUsuario($idUsuario);
                if (!$aprendizExistente) {
                    Seguridad::alertaYRedirigir(
                        'error',
                        'Aprendiz no encontrado',
                        'No se encontró el aprendiz que intentas editar.',
                        $this->urlAccion('aprendices')
                    );
                }

                $this->modelo->actualizar(
                    $idUsuario,
                    $nombre,
                    $apellido,
                    $documento,
                    $correo,
                    $passwordHash,
                    $idFicha,
                    $tagRfid
                );

                Seguridad::alertaYRedirigir(
                    'success',
                    '¡Aprendiz actualizado!',
                    'Los datos del aprendiz fueron actualizados correctamente.',
                    $this->urlAccion('aprendices')
                );
            }
        } catch (PDOException $e) {
            error_log('Error al guardar aprendiz: ' . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error de base de datos',
                'Ocurrió un error al guardar. Intenta de nuevo.',
                $this->urlAccion('aprendices')
            );
        }
    }


    // accion=cambiarEstadoAprendiz
    // Permite activar o desactivar un aprendiz cambiando su estado en la base de datos
    public function cambiarEstado(): void
    {
        // Solo Administrador e Instructor pueden gestionar aprendices
        $this->exigirSesion(['Administrador', 'Instructor']);

        $idUsuario = (int) ($_POST['id_usuario'] ?? $_GET['id'] ?? 0);
        $nuevoEstado = Seguridad::sanitizar($_POST['estado'] ?? $_GET['estado'] ?? '');

        if ($idUsuario <= 0) {
            Seguridad::alertaYRedirigir(
                'error',
                'ID no válido',
                'No se proporcionó un aprendiz válido.',
                $this->urlAccion('aprendices')
            );
        }

        $aprendizExistente = $this->modelo->buscarPorIdUsuario($idUsuario);
        if (!$aprendizExistente) {
            Seguridad::alertaYRedirigir(
                'error',
                'Aprendiz no encontrado',
                'No se encontró el aprendiz especificado.',
                $this->urlAccion('aprendices')
            );
        }

        // Revisamos que el estado recibido sea válido
        if (!in_array(strtolower($nuevoEstado), ['activo', 'inactivo'], true)) {
            $estadoActual = strtolower($aprendizExistente['estado'] ?? 'activo');
            $nuevoEstado = ($estadoActual === 'activo') ? 'Inactivo' : 'Activo';
        } else {
            $nuevoEstado = ucfirst(strtolower($nuevoEstado));
        }

        try {
            // Cambiamos el estado del aprendiz en la base de datos
            $this->modelo->cambiarEstado($idUsuario, $nuevoEstado);

            $mensaje = ($nuevoEstado === 'Inactivo')
                ? 'El aprendiz ha sido desactivado correctamente.'
                : 'El aprendiz ha sido activado correctamente.';

            Seguridad::alertaYRedirigir(
                'success',
                '¡Estado actualizado!',
                $mensaje,
                $this->urlAccion('aprendices')
            );
        } catch (PDOException $e) {
            error_log('Error al cambiar estado de aprendiz: ' . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error de base de datos',
                'Ocurrió un error al cambiar el estado del aprendiz. Intenta de nuevo.',
                $this->urlAccion('aprendices')
            );
        }
    }
}