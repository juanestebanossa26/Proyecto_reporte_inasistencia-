<?php
// Controlador de excusas médicas.
// Sube el archivo al disco, delega el SQL en ExcusaModelo y muestra el
// resultado con SweetAlert2 antes de redirigir.

class ExcusaControlador extends ControladorBase
{
    // accion=excusas -> vista del aprendiz (sus propias excusas + fechas
    // de inasistencia todavía sin excusar).
    public function listar(): void
    {
        $this->exigirSesion(['Aprendiz']);

        try {
            $misExcusas    = (new ExcusaModelo())->listarPorAprendiz((int) $_SESSION['id']);
            $fechas_faltas = (new AsistenciaModelo())->listarFaltasSinExcusa((int) $_SESSION['id']);
        } catch (PDOException $e) {
            error_log('Error al listar excusas: ' . $e->getMessage());
            $misExcusas    = [];
            $fechas_faltas = [];
            $_SESSION['mensaje_error'] = 'No se pudieron cargar tus excusas. Intenta de nuevo.';
        }

        require BASE_PATH . '/views/excusas.php';
    }

    // accion=validarExcusas -> vista del instructor (excusas de sus
    // aprendices, pendientes de aprobar o rechazar).
    public function listarPendientes(): void
    {
        $this->exigirSesion(['Instructor', 'Administrador']);
    

        try {
            $excusaModelo      = new ExcusaModelo();
            $excusasInstructor = $excusaModelo->listarPorInstructor((int) $_SESSION['id']);
            $total_pendientes  = $excusaModelo->contarPendientesPorInstructor((int) $_SESSION['id']);
        } catch (PDOException $e) {
            error_log('Error al listar excusas pendientes: ' . $e->getMessage());
            $excusasInstructor = [];
            $total_pendientes  = 0;
            $_SESSION['mensaje_error'] = 'No se pudieron cargar las excusas. Intenta de nuevo.';
        }

      
        require BASE_PATH . '/views/validar_excusas.php';
    }

    public function guardar(): void
    {
        $this->exigirSesion(['Aprendiz']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $this->exigirCsrfValido('excusas');

        $motivo          = $_POST['motivo'] ?? '';
        $idAsistencia    = $_POST['fecha_excusa'] ?? '';
        $archivoSubido   = $_FILES['archivo'] ?? null;

        if ($motivo === '' || $idAsistencia === '' || !$archivoSubido || $archivoSubido['error'] === UPLOAD_ERR_NO_FILE) {
            Seguridad::imprimirEncabezadoAnimacion();
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos incompletos',
                    text: 'Por favor completa todos los campos requeridos.',
                    confirmButtonColor: '#ffc107'
                }).then(() => { window.history.back(); });
            </script>";
            Seguridad::imprimirCierreAnimacion();
            return;
        }

        Seguridad::imprimirEncabezadoAnimacion();

        if ($archivoSubido['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Error al subir el archivo.']);
            return;
        }

        if (!Seguridad::esPdfValido($archivoSubido['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos PDF.']);
            return;
        }

        $directorioDestino  = BASE_PATH . '/assets/excusas/';
        $nombreUnico        = Seguridad::generarNombreArchivoUnico($archivoSubido['name']);
        $rutaFisicaCompleta = $directorioDestino . $nombreUnico;
        $rutaRelativaBD     = 'assets/excusas/' . $nombreUnico;

        if (!move_uploaded_file($archivoSubido['tmp_name'], $rutaFisicaCompleta)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al mover el archivo al servidor.']);
            return;
        }

        try {
            (new ExcusaModelo())->guardar((int) $idAsistencia, $rutaRelativaBD, $motivo);

            $urlExcusas = json_encode($this->urlAccion('excusas'));
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: '¡Excusa radicada con éxito!',
                    text: 'El soporte ha sido guardado correctamente y notificado a tus instructores.',
                    showClass: { popup: 'animate__animated animate__zoomInDown' },
                    hideClass: { popup: 'animate__animated animate__fadeOutUp' },
                    confirmButtonColor: '#39a900',
                    confirmButtonText: 'Continuar'
                }).then(() => {
                    window.location.href = {$urlExcusas};
                });
            </script>";
        } catch (Exception $e) {
            // Se usa Exception en lugar de PDOException para atrapar también las excepciones manuales del modelo
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al insertar: ' . $e->getMessage()]);
        }
    }

    public function procesar(): void
    {
        $this->exigirSesion(['Instructor', 'Administrador']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_excusa'])) {
            $this->redirigirAccion('validarExcusas', ['error' => '11']);
        }

        $this->exigirCsrfValido('validarExcusas');

        // Obtenemos los 3 datos enviados por el JS y el formulario oculto
        $accion      = $_POST['accion'] ?? '';
        $idRevision  = (int) $_POST['id_excusa']; // Contiene el id_revision gracias a la vista
        $observacion = trim($_POST['observacion'] ?? '');
        $modelo      = new ExcusaModelo();

        // Mapeamos la acción del botón al estado de la base de datos
        $estadoBD = '';
        if ($accion === 'aprobar') {
            $estadoBD = 'Aprobada';
        } elseif ($accion === 'rechazar') {
            $estadoBD = 'Rechazada';
        } elseif ($accion === 'devolver') {
            $estadoBD = 'Devuelta';
        } else {
            $this->redirigirAccion('validarExcusas', ['error' => '11']);
        }

        try {
            // Ejecutamos el método unificado en el modelo
            $modelo->procesarRevision($idRevision, $estadoBD, $observacion);
            
            // Redirigimos de vuelta a la vista con un parámetro de éxito opcional
            $this->redirigirAccion('validarExcusas', ['success' => '1']);
        } catch (Exception $e) {
            error_log('Error al procesar excusa: ' . $e->getMessage());
            $this->redirigirAccion('validarExcusas', ['error' => '12']);
        }
    }
}