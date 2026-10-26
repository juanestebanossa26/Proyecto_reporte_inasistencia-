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
            $asistenciaModelo = new AsistenciaModelo();
            $misExcusas       = (new ExcusaModelo())->listarPorAprendiz((int) $_SESSION['id']);
            $fechas_faltas    = $asistenciaModelo->listarFaltasAgrupadasPorFecha((int) $_SESSION['id']);
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
        $this->exigirSesion(['Instructor']);

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

    // accion=guardarExcusa -> radica una excusa nueva. Puede cubrir varias
    // fechas o bloques de inasistencia a la vez (checkboxes en la vista), todas con
    // el mismo archivo y el mismo motivo.
    public function guardar(): void
    {
        $this->exigirSesion(['Aprendiz']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $this->exigirCsrfValido('excusas');

        $motivo        = trim($_POST['motivo'] ?? '');
        $idsRaw        = $_POST['fecha_excusa'] ?? [];
        $archivoSubido = $_FILES['archivo'] ?? null;

        // Normalizar arreglo y descomponer valores que vengan agrupados por coma ("101,102")
        if (!is_array($idsRaw)) {
            $idsRaw = $idsRaw !== '' ? [$idsRaw] : [];
        }

        $idsAsistencia = [];
        foreach ($idsRaw as $item) {
            foreach (explode(',', (string) $item) as $id) {
                $id = (int) trim($id);
                if ($id > 0) {
                    $idsAsistencia[] = $id;
                }
            }
        }
        $idsAsistencia = array_values(array_unique($idsAsistencia));

        if ($motivo === '' || empty($idsAsistencia) || !$archivoSubido || $archivoSubido['error'] === UPLOAD_ERR_NO_FILE) {
            Seguridad::imprimirEncabezadoAnimacion();
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos incompletos',
                    text: 'Selecciona al menos una fecha, escribe el motivo y adjunta el soporte.',
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
        if (!is_dir($directorioDestino)) {
            @mkdir($directorioDestino, 0755, true);
        }
        $nombreUnico        = Seguridad::generarNombreArchivoUnico($archivoSubido['name']);
        $rutaFisicaCompleta = $directorioDestino . $nombreUnico;
        $rutaRelativaBD     = 'assets/excusas/' . $nombreUnico;

        if (!move_uploaded_file($archivoSubido['tmp_name'], $rutaFisicaCompleta)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al mover el archivo al servidor.']);
            return;
        }

        try {
            (new ExcusaModelo())->guardarVarios($idsAsistencia, $rutaRelativaBD, $motivo);

            $totalDias  = count($idsAsistencia);
            $textoExito = $totalDias > 1
                ? "El soporte fue guardado para las $totalDias fechas seleccionadas y notificado a tus instructores."
                : 'El soporte ha sido guardado correctamente y notificado a tus instructores.';

            $urlExcusas   = json_encode($this->urlAccion('excusas'));
            $textoExitoJs = json_encode($textoExito);
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: '¡Excusa radicada con éxito!',
                    text: {$textoExitoJs},
                    showClass: { popup: 'animate__animated animate__zoomInDown' },
                    hideClass: { popup: 'animate__animated animate__fadeOutUp' },
                    confirmButtonColor: '#39a900',
                    confirmButtonText: 'Continuar'
                }).then(() => {
                    window.location.href = {$urlExcusas};
                });
            </script>";
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al insertar: ' . $e->getMessage()]);
        }
    }

    // accion=reenviarExcusa -> el aprendiz edita el archivo/motivo de UNA
    // excusa que le devolvieron o rechazaron, y la reenvía. Es un UPDATE
    // sobre la misma fila (mismo id_excusa), nunca crea un envío nuevo.
    public function reenviar(): void
    {
        $this->exigirSesion(['Aprendiz']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $this->exigirCsrfValido('excusas');

        $idExcusa      = (int) ($_POST['id_excusa'] ?? 0);
        $motivo        = trim($_POST['motivo'] ?? '');
        $archivoSubido = $_FILES['archivo'] ?? null;

        $modelo = new ExcusaModelo();
        // Verificación de propiedad: la excusa debe existir y ser de quien
        // la está reenviando, para que nadie pueda editar una ajena
        // adivinando el id_excusa en el formulario.
        $excusa = $idExcusa > 0 ? $modelo->buscarPorIdYAprendiz($idExcusa, (int) $_SESSION['id']) : false;

        if (!$excusa) {
            $this->redirigirAccion('excusas', ['error' => '13']);
        }

        if ($motivo === '' || !$archivoSubido || $archivoSubido['error'] === UPLOAD_ERR_NO_FILE) {
            Seguridad::imprimirEncabezadoAnimacion();
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos incompletos',
                    text: 'Escribe el motivo y adjunta el nuevo soporte en PDF.',
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
        if (!is_dir($directorioDestino)) {
            @mkdir($directorioDestino, 0755, true);
        }
        $nombreUnico        = Seguridad::generarNombreArchivoUnico($archivoSubido['name']);
        $rutaFisicaCompleta = $directorioDestino . $nombreUnico;
        $rutaRelativaBD     = 'assets/excusas/' . $nombreUnico;

        if (!move_uploaded_file($archivoSubido['tmp_name'], $rutaFisicaCompleta)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al mover el archivo al servidor.']);
            return;
        }

        try {
            $modelo->reenviar($idExcusa, $rutaRelativaBD, $motivo);

            $urlExcusas = json_encode($this->urlAccion('excusas'));
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: '¡Excusa reenviada!',
                    text: 'Tu instructor la revisará de nuevo.',
                    showClass: { popup: 'animate__animated animate__zoomInDown' },
                    hideClass: { popup: 'animate__animated animate__fadeOutUp' },
                    confirmButtonColor: '#39a900',
                    confirmButtonText: 'Continuar'
                }).then(() => {
                    window.location.href = {$urlExcusas};
                });
            </script>";
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al reenviar: ' . $e->getMessage()]);
        }
    }

    public function procesar(): void
    {
        $this->exigirSesion(['Instructor']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_excusa'])) {
            $this->redirigirAccion('validarExcusas', ['error' => '11']);
        }

        $this->exigirCsrfValido('validarExcusas');

        $accion      = $_POST['accion'] ?? '';
        $idRevision  = (int) $_POST['id_excusa'];
        $observacion = trim($_POST['observacion'] ?? '');
        $modelo      = new ExcusaModelo();

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
            $detalleAprendiz = $modelo->obtenerDetalleRevision($idRevision);
            $modelo->procesarRevision($idRevision, $estadoBD, $observacion);

            // Notificar al aprendiz por correo electrónico el resultado de la revisión sin alterar ni borrar registros
            if ($detalleAprendiz && !empty($detalleAprendiz['correo_electronico'])) {
                $fichaTxt = !empty($detalleAprendiz['numero_ficha'])
                    ? ($detalleAprendiz['numero_ficha'] . ' · ' . ($detalleAprendiz['nombre_programa'] ?? ''))
                    : '';

                EmailHelper::notificarResultadoExcusa(
                    $detalleAprendiz['correo_electronico'],
                    $detalleAprendiz['nombre_completo'] ?? 'Aprendiz',
                    $detalleAprendiz['fecha'] ?? date('Y-m-d'),
                    $estadoBD,
                    $observacion,
                    trim($_SESSION['nombre'] ?? 'Instructor'),
                    $fichaTxt
                );
            }

            $this->redirigirAccion('validarExcusas', ['success' => '1']);
        } catch (Exception $e) {
            error_log('Error al procesar excusa: ' . $e->getMessage());
            $this->redirigirAccion('validarExcusas', ['error' => '12']);
        }
    }
}
