<?php
// controllers/FichaControlador.php — Controlador dedicado a la gestión y ciclo de vida de Fichas
// Encargado de listar por estado, crear, editar, finalizar atómicamente,
// reabrir auditadamente y eliminar fichas respetando las relaciones e integridad institucional.

declare(strict_types=1);

require_once __DIR__ . '/../models/FichaModelo.php';
require_once __DIR__ . '/../models/AuditoriaModelo.php';

class FichaControlador extends ControladorBase
{
    private FichaModelo $modelo;
    private AuditoriaModelo $auditoria;

    public function __construct()
    {
        $this->auditoria = new AuditoriaModelo();
        $this->modelo    = new FichaModelo($this->auditoria);
    }

    /**
     * accion=fichas -> Vista dedicada al listado y gestión de fichas organizada por estados
     */
    public function mostrarFichas(): void
    {
        $this->exigirSesion(['Administrador', 'Instructor']);

        $rolSesion          = $_SESSION['rol'] ?? 'Administrador';
        $esInstructor       = (strtolower($rolSesion) === 'instructor');
        $idInstructorSesion = $esInstructor ? (int) ($_SESSION['id'] ?? 0) : null;

        // Si el usuario es Instructor, presentar su panel especializado "Mis Fichas"
        if ($esInstructor) {
            try {
                $kpisInstructor = $this->modelo->obtenerKpisInstructor($idInstructorSesion);
                $fichas         = $this->modelo->listarFichasInstructor($idInstructorSesion);
            } catch (PDOException $e) {
                error_log('Error al listar fichas para instructor: ' . $e->getMessage());
                $fichas         = [];
                $kpisInstructor = ['total_fichas' => 0, 'clases_hoy' => 0, 'proximas_clases' => 0];
                $_SESSION['mensaje_error'] = 'No se pudieron cargar sus fichas de formación.';
            }

            require BASE_PATH . '/views/fichas_instructor.php';
            return;
        }

        // Flujo Administrativo: Consulta global por estados de ciclo de vida
        $estadoFiltro = trim((string)($_GET['estado'] ?? 'Activa'));
        if (!in_array($estadoFiltro, ['Activa', 'Finalizada', 'todos'], true)) {
            $estadoFiltro = 'Activa';
        }

        try {
            $conteos      = $this->modelo->contarPorEstado($idInstructorSesion);
            $fichas       = $this->modelo->listarFichasDetalladas($idInstructorSesion, $estadoFiltro);
            $instructores = $this->modelo->listarInstructores();
        } catch (PDOException $e) {
            error_log('Error al listar fichas: ' . $e->getMessage());
            $fichas       = [];
            $instructores = [];
            $conteos      = ['total' => 0, 'activas' => 0, 'finalizadas' => 0];
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

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigirAccion('fichas');
            return;
        }

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
                'El número de ficha debe contener entre 3 y 20 caracteres alfanuméricos sin espacios ni símbolos especiales.',
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
                ? "Ya existe otra ficha registrada con el número '{$numeroFicha}'."
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
                $this->modelo->actualizarFicha($idFicha, $numeroFicha, $programa);
                if ($idInstructor > 0) {
                    $this->modelo->asociarInstructor($idFicha, $idInstructor);
                }
                $titulo = '¡Ficha actualizada!';
                $texto  = 'La información de la ficha fue guardada exitosamente.';
            } else {
                $idNueva = $this->modelo->crearFicha($numeroFicha, $programa);
                if ($idInstructor > 0) {
                    $this->modelo->asociarInstructor($idNueva, $idInstructor);
                }
                $titulo = '¡Ficha creada!';
                $texto  = 'La ficha fue registrada exitosamente con estado Activa.';
            }

            Seguridad::alertaYRedirigir(
                'success',
                $titulo,
                $texto,
                $this->urlAccion('fichas')
            );
        } catch (DomainException $e) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Operación no permitida',
                $e->getMessage(),
                $this->urlAccion('fichas')
            );
        } catch (\Throwable $e) {
            error_log('Error al guardar ficha: ' . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error al guardar',
                'Ocurrió un error al procesar la ficha. Intenta de nuevo.',
                $this->urlAccion('fichas')
            );
        }
    }

    /**
     * accion=finalizarFicha (POST) -> Finaliza formalmente el ciclo lectivo de una ficha
     */
    public function finalizarFicha(): void
    {
        $this->exigirSesion(['Administrador', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigirAccion('fichas');
            return;
        }

        if (!Seguridad::validarCsrf($_POST['csrf_token'] ?? '')) {
            Seguridad::alertaYRedirigir(
                'error',
                'Sesión inválida',
                'El token de seguridad ha caducado o es inválido. Por favor intenta de nuevo.',
                $this->urlAccion('fichas')
            );
            return;
        }

        $idFicha       = (int) ($_POST['id_ficha'] ?? 0);
        $motivo        = trim((string)($_POST['motivo_finalizacion'] ?? ''));
        $fechaEfectiva = !empty($_POST['fecha_efectiva']) ? trim((string)$_POST['fecha_efectiva']) : null;
        $idAdmin       = (int) ($_SESSION['id'] ?? 0);

        if ($idFicha <= 0) {
            Seguridad::alertaYRedirigir('warning', 'Ficha no especificada', 'Identificador de ficha no válido.', $this->urlAccion('fichas'));
            return;
        }

        try {
            $res = $this->modelo->finalizarFicha($idFicha, $idAdmin, $fechaEfectiva, $motivo);

            $mensajeHtml = "La ficha <strong>{$res['numero_ficha']}</strong> ha concluido formalmente su formación.<br><br>" .
                           "• Horarios futuros cancelados: <strong>{$res['horarios_cancelados']}</strong><br>" .
                           "• Aprendices evaluados: <strong>{$res['aprendices_evaluados']}</strong><br>" .
                           "• Cuentas con acceso conservado (en otras fichas/roles): <strong>{$res['cuentas_conservadas']}</strong><br>" .
                           "• Cuentas de aprendiz inactivadas: <strong>{$res['cuentas_inactivadas']}</strong><br><br>" .
                           "<small class='text-muted'>Historiales de asistencia, excusas y horarios pasados preservados intactos.</small>";

            Seguridad::alertaHtmlYRedirigir(
                'success',
                '¡Ficha Finalizada!',
                $mensajeHtml,
                $this->urlAccion('fichas', ['estado' => 'Finalizada'])
            );
        } catch (DomainException $e) {
            Seguridad::alertaYRedirigir('warning', 'No se puede finalizar', $e->getMessage(), $this->urlAccion('fichas'));
        } catch (\Throwable $e) {
            error_log("Error al finalizar ficha {$idFicha}: " . $e->getMessage());
            Seguridad::alertaYRedirigir('error', 'Error al procesar finalización', 'Ocurrió un error inesperado al finalizar la ficha. Intenta de nuevo.', $this->urlAccion('fichas'));
        }
    }

    /**
     * accion=reabrirFicha (POST) -> Reapertura administrativa excepcional y auditada
     */
    public function reabrirFicha(): void
    {
        $this->exigirSesion(['Administrador', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigirAccion('fichas');
            return;
        }

        if (!Seguridad::validarCsrf($_POST['csrf_token'] ?? '')) {
            Seguridad::alertaYRedirigir('error', 'Sesión inválida', 'Token caducado.', $this->urlAccion('fichas'));
            return;
        }

        $idFicha = (int) ($_POST['id_ficha'] ?? 0);
        $motivo  = trim((string)($_POST['motivo_reapertura'] ?? ''));
        $idAdmin = (int) ($_SESSION['id'] ?? 0);

        try {
            $res = $this->modelo->reabrirFicha($idFicha, $idAdmin, $motivo);

            Seguridad::alertaYRedirigir(
                'success',
                '¡Ficha Reabierta!',
                "La ficha {$res['numero_ficha']} fue reactivada exitosamente. Quedó registrada la trazabilidad de la reapertura en la bitácora.",
                $this->urlAccion('fichas', ['estado' => 'Activa'])
            );
        } catch (DomainException|InvalidArgumentException $e) {
            Seguridad::alertaYRedirigir('warning', 'Reapertura no autorizada', $e->getMessage(), $this->urlAccion('fichas'));
        } catch (\Throwable $e) {
            error_log("Error al reabrir ficha {$idFicha}: " . $e->getMessage());
            Seguridad::alertaYRedirigir('error', 'Error al reabrir', 'Ocurrió un error al procesar la reapertura.', $this->urlAccion('fichas'));
        }
    }

    /**
     * accion=eliminarFicha (POST) -> Elimina únicamente fichas vacías sin historial académico
     */
    public function eliminarFicha(): void
    {
        $this->exigirSesion(['Administrador', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigirAccion('fichas');
            return;
        }

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
        $motivo  = trim((string)($_POST['motivo_eliminacion'] ?? ''));
        $idAdmin = (int) ($_SESSION['id'] ?? 0);

        if ($idFicha <= 0) {
            Seguridad::alertaYRedirigir('warning', 'Ficha no encontrada', 'Identificador de ficha no válido.', $this->urlAccion('fichas'));
            return;
        }

        try {
            $this->modelo->eliminarFichaSegura($idFicha, $idAdmin, $motivo);

            Seguridad::alertaYRedirigir(
                'success',
                'Ficha eliminada',
                'La ficha vacía seleccionada fue eliminada correctamente sin afectar registros relacionados.',
                $this->urlAccion('fichas')
            );
        } catch (DomainException $e) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Eliminación bloqueada',
                $e->getMessage(),
                $this->urlAccion('fichas'),
                'Entendido'
            );
        } catch (\Throwable $e) {
            error_log("Error al eliminar ficha {$idFicha}: " . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error al procesar la solicitud',
                'No fue posible eliminar la ficha. Inténtalo nuevamente o contacta al administrador del sistema.',
                $this->urlAccion('fichas')
            );
        }
    }
}
