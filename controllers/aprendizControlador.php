<?php

class AprendizControlador extends ControladorBase
{
    private AprendizModelo $modelo;

    public function __construct()
    {
        $this->modelo = new AprendizModelo();
    }

    // accion=aprendices -> arma los datos y entrega el control a la vista.
    // El formulario de "editar" apunta aqui
    public function mostrar(): void
    {
        $this->exigirSesion(['Administrador', 'admin']);
        $aprendizModelo  = $this->modelo;
        $aprendizEditar  = null;
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id_editar'])) {
                $idEdit = (int) filter_var($_POST['id_editar'], FILTER_SANITIZE_NUMBER_INT);
                $aprendizEditar = $aprendizModelo->buscarPorIdUsuario($idEdit);
            }

            $aprendices = $aprendizModelo->listar();
            $fichas     = $aprendizModelo->listarFichas();
            
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
        $this->exigirCsrfValido('aprendices');

        // Recoger y limpiar campos del formulario
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);  // 0 = crear, >0 = actualizar
        $nombre    = trim(Seguridad::sanitizar($_POST['nombre'] ?? ''));
        $usuario   = trim(Seguridad::sanitizar($_POST['usuario'] ?? $_POST['documento'] ?? ''));
        $correo    = trim($_POST['correo'] ?? '');
        $password  = $_POST['password'] ?? '';
        $idFicha   = (int) ($_POST['ficha_id'] ?? 0);
        $tagRfid   = trim(Seguridad::sanitizar($_POST['codigo_rfid'] ?? ''));

        // 1. Validaciones de campos obligatorios
        if ($nombre === '' || $usuario === '' || $idFicha <= 0) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Campos incompletos',
                'Por favor completa los campos obligatorios: Nombre completo, Usuario/Documento y Ficha.',
                $this->urlAccion('aprendices')
            );
            return;
        }

        // 2. Validación de formato de documento / usuario (4 a 25 caracteres)
        if (!preg_match('/^[a-zA-Z0-9\-_]{4,25}$/', $usuario)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Documento no válido',
                'El número de documento o usuario debe contener entre 4 y 25 caracteres alfanuméricos sin espacios.',
                $this->urlAccion('aprendices')
            );
            return;
        }

        // 3. Validación de formato de TAG RFID si fue proporcionado
        if ($tagRfid !== '' && !preg_match('/^[a-zA-Z0-9\-_]{3,50}$/', $tagRfid)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'TAG RFID no válido',
                'El código de TAG RFID debe contener entre 3 y 50 caracteres alfanuméricos sin espacios.',
                $this->urlAccion('aprendices')
            );
            return;
        }

        // 4. Validación de formato de correo
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Correo inválido',
                'El correo electrónico no tiene un formato válido (ejemplo: usuario@correo.com).',
                $this->urlAccion('aprendices')
            );
            return;
        }

        // 5. Al crear, la contraseña es obligatoria
        $esNuevo = ($idUsuario === 0);
        if ($esNuevo && $password === '') {
            Seguridad::alertaYRedirigir(
                'warning',
                'Contraseña requerida',
                'Debes asignar una contraseña de acceso al registrar un nuevo aprendiz.',
                $this->urlAccion('aprendices')
            );
            return;
        }

        // 6. Validar duplicados de usuario / documento
        if ($this->modelo->existeUsuario($usuario, $esNuevo ? null : $idUsuario)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Documento duplicado',
                "Ya existe un aprendiz registrado con el documento o identificador '{$usuario}'.",
                $this->urlAccion('aprendices')
            );
            return;
        }

        // 7. Validar duplicados de correo electrónico
        if ($correo !== '' && $this->modelo->existeCorreo($correo, $esNuevo ? null : $idUsuario)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Correo duplicado',
                "El correo electrónico '{$correo}' ya está registrado para otro usuario.",
                $this->urlAccion('aprendices')
            );
            return;
        }

        // 8. Validar duplicados de TAG RFID (permite conservar el propio en edición)
        if ($tagRfid !== '' && $this->modelo->existeRfid($tagRfid, $esNuevo ? null : $idUsuario)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'TAG RFID duplicado',
                "Este TAG RFID ya está asignado a otro aprendiz. Cada aprendiz debe contar con un código único.",
                $this->urlAccion('aprendices')
            );
            return;
        }

        // Hash de la contraseña (solo si se proporcionó una nueva)
        $passwordHash = ($password !== '') ? password_hash($password, PASSWORD_BCRYPT) : null;

        // Ejecutar la operación correcta según si es nuevo o edición
        try {
            if ($esNuevo) {
                // CREAR: inserta en usuarios + aprendices (transacción)
                $this->modelo->crear(
                    $nombre,
                    $usuario,
                    $correo,
                    $passwordHash,
                    $idFicha,
                    $tagRfid !== '' ? $tagRfid : null
                );

                Seguridad::alertaYRedirigir(
                    'success',
                    '¡Aprendiz registrado!',
                    'El aprendiz fue registrado exitosamente.',
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
                    return;
                }

                $this->modelo->actualizar(
                    $idUsuario,
                    $nombre,
                    $usuario,
                    $correo,
                    $passwordHash,
                    $idFicha,
                    $tagRfid !== '' ? $tagRfid : null
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
            $errorMsg = ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry'))
                ? 'No fue posible guardar: ya existe un registro con ese documento, correo o TAG RFID.'
                : 'Ocurrió un error al guardar los datos del aprendiz. Intenta de nuevo.';

            Seguridad::alertaYRedirigir(
                'error',
                'Error al guardar',
                $errorMsg,
                $this->urlAccion('aprendices')
            );
        }
    }


    // accion=cambiarEstadoAprendiz
    // Permite activar o desactivar un aprendiz cambiando su estado en la base de datos
    public function cambiarEstado(): void
    {
        // Solo el Administrador puede cambiar el estado de los aprendices
        $this->exigirSesion(['Administrador', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Seguridad::alertaYRedirigir(
                'error',
                'Método no permitido',
                'La acción solicitada requiere un método POST válido.',
                $this->urlAccion('aprendices')
            );
            return;
        }

        $this->exigirCsrfValido('aprendices');

        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $nuevoEstado = Seguridad::sanitizar($_POST['estado'] ?? '');

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