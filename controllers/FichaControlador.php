<?php
// controllers/FichaControlador.php — Controlador dedicado exclusivamente a la gestión de Fichas
// Encargado de listar, crear, editar y eliminar fichas respetando las relaciones del sistema.

declare(strict_types=1);

require_once __DIR__ . '/../models/FichaModelo.php';

class FichaControlador extends ControladorBase
{
    private FichaModelo $modelo;

    public function __construct()
    {
        $this->modelo = new FichaModelo();
    }

    /**
     * accion=fichas -> Vista dedicada al listado y gestión de fichas
     */
    public function mostrarFichas(): void
    {
        $this->exigirSesion(['Administrador', 'Instructor']);

        $rolSesion          = $_SESSION['rol'] ?? 'Administrador';
        $esInstructor       = (strtolower($rolSesion) === 'instructor');
        $idInstructorSesion = $esInstructor ? (int) ($_SESSION['id'] ?? 0) : null;

        try {
            $fichas       = $this->modelo->listarFichasDetalladas($idInstructorSesion);
            $instructores = $this->modelo->listarInstructores();
        } catch (PDOException $e) {
            error_log('Error al listar fichas: ' . $e->getMessage());
            $fichas       = [];
            $instructores = [];
            $_SESSION['mensaje_error'] = 'No se pudieron cargar las fichas.';
        }

        require BASE_PATH . '/views/fichas.php';
    }

    /**
     * Alias para compatibilidad
     */
    public function mostrar(): void
    {
        $this->mostrarFichas();
    }

    /**
     * accion=guardarFicha (POST) -> Crea o actualiza una ficha académica
     */
    public function guardarFicha(): void
    {
        $this->exigirSesion(['Administrador', 'admin']);

        // Validación de CSRF
        if (!Seguridad::validarCsrf($_POST['csrf_token'] ?? '')) {
            Seguridad::alertaYRedirigir(
                'error',
                'Sesión inválida',
                'El token de seguridad ha caducado. Intenta de nuevo.',
                $this->urlAccion('fichas')
            );
            return;
        }

        $idFicha      = (int) ($_POST['id_ficha'] ?? 0);
        $numeroFicha  = trim(strip_tags((string)($_POST['numero_ficha'] ?? '')));
        $programa     = trim(html_entity_decode(strip_tags((string)($_POST['nombre_programa'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $idInstructor = (int) ($_POST['id_instructor'] ?? 0);

        // 1. Validación de campos obligatorios
        if ($numeroFicha === '' || $programa === '') {
            Seguridad::alertaYRedirigir(
                'warning',
                'Campos obligatorios',
                'Por favor ingresa tanto el número de ficha como el nombre del programa.',
                $this->urlAccion('fichas')
            );
            return;
        }

        // 2. Validación de formato de número de ficha (3 a 20 caracteres alfanuméricos)
        if (!preg_match('/^[a-zA-Z0-9\-_]{3,20}$/', $numeroFicha)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Formato no válido',
                'El número de ficha debe contener entre 3 y 20 caracteres alfanuméricos (letras, números o guiones) sin espacios ni símbolos especiales.',
                $this->urlAccion('fichas')
            );
            return;
        }

        if (mb_strlen($programa) < 3) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Programa no válido',
                'El nombre del programa de formación debe tener al menos 3 caracteres.',
                $this->urlAccion('fichas')
            );
            return;
        }

        // 3. Validación de duplicados en la base de datos
        $esEdicion = ($idFicha > 0);
        if ($this->modelo->existeNumeroFicha($numeroFicha, $esEdicion ? $idFicha : null)) {
            $msgDuplicado = $esEdicion
                ? "Ya existe otra ficha registrada con el número '{$numeroFicha}'. Por favor verifica el número ingresado."
                : "Ya existe una ficha registrada con este número ({$numeroFicha}). No se permiten números de ficha duplicados.";

            Seguridad::alertaYRedirigir(
                'warning',
                'Ficha duplicada',
                $msgDuplicado,
                $this->urlAccion('fichas')
            );
            return;
        }

        try {
            if ($esEdicion) {
                // Actualizar ficha existente conservando su identidad si corresponde
                $this->modelo->actualizarFicha($idFicha, $numeroFicha, $programa);
                if ($idInstructor > 0) {
                    $this->modelo->asociarInstructor($idFicha, $idInstructor);
                }
                $titulo = '¡Ficha actualizada!';
                $texto  = 'La información de la ficha fue guardada exitosamente.';
            } else {
                // Crear nueva ficha verificada
                $idNueva = $this->modelo->crearFicha($numeroFicha, $programa);
                if ($idInstructor > 0) {
                    $this->modelo->asociarInstructor($idNueva, $idInstructor);
                }
                $titulo = '¡Ficha creada!';
                $texto  = 'La ficha fue registrada exitosamente.';
            }

            Seguridad::alertaYRedirigir(
                'success',
                $titulo,
                $texto,
                $this->urlAccion('fichas')
            );
        } catch (PDOException $e) {
            error_log('Error al guardar ficha: ' . $e->getMessage());
            $errorMsg = ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry'))
                ? "Ya existe una ficha registrada con el número '{$numeroFicha}'."
                : 'Ocurrió un error al guardar la ficha. Intenta de nuevo.';

            Seguridad::alertaYRedirigir(
                'error',
                'Error al guardar',
                $errorMsg,
                $this->urlAccion('fichas')
            );
        }
    }

    /**
     * accion=eliminarFicha (POST) -> Elimina una ficha y sus registros asociados con cascada segura
     */
    public function eliminarFicha(): void
    {
        // 1. Exigir sesión activa y permisos de Administrador exclusivamente
        $this->exigirSesion(['Administrador', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigirAccion('fichas');
            return;
        }

        // Validación estricta de token CSRF
        if (!Seguridad::validarCsrf($_POST['csrf_token'] ?? '')) {
            Seguridad::alertaYRedirigir(
                'error',
                'Sesión inválida',
                'El token de seguridad ha caducado o es inválido. Por favor intenta de nuevo.',
                $this->urlAccion('fichas')
            );
            return;
        }

        $idFicha = (int) ($_POST['id_ficha'] ?? 0);

        if ($idFicha <= 0) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Ficha no encontrada',
                'No se encontró la ficha que intentas eliminar. Actualiza la lista e inténtalo nuevamente.',
                $this->urlAccion('fichas'),
                'Entendido'
            );
            return;
        }

        // 2. Comprobar que la ficha exista en la base de datos
        $fichaExistente = $this->modelo->buscarFichaPorId($idFicha);
        if (!$fichaExistente) {
            Seguridad::alertaYRedirigir(
                'error',
                'Ficha no encontrada',
                'No se encontró la ficha que intentas eliminar. Actualiza la lista e inténtalo nuevamente.',
                $this->urlAccion('fichas'),
                'Entendido'
            );
            return;
        }

        // 3. Ejecutar la operación de eliminación atómica y en cascada controlada
        try {
            $this->modelo->eliminarFichaCompleta($idFicha);

            Seguridad::alertaYRedirigir(
                'success',
                'Ficha eliminada correctamente.',
                'Se eliminaron la ficha seleccionada y los registros asociados que correspondía eliminar.',
                $this->urlAccion('fichas'),
                'Continuar'
            );
        } catch (\Throwable $e) {
            error_log("Error al eliminar ficha {$idFicha}: " . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error al procesar la solicitud',
                'No fue posible eliminar la ficha. No se confirmaron los cambios. Inténtalo nuevamente o contacta al administrador del sistema.',
                $this->urlAccion('fichas'),
                'Entendido'
            );
        }
    }
}
