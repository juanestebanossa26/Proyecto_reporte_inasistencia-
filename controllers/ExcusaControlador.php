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

        if ($archivoSubido['error'] !== UPLOAD_ERR_OK) {
            Seguridad::alertaYRedirigir('error', 'Error al subir archivo', 'No se pudo cargar el archivo adjunto. Verifica su tamaño e inténtalo de nuevo.', $this->urlAccion('excusas'));
            return;
        }

        if (($archivoSubido['size'] ?? 0) > 10 * 1024 * 1024) {
            Seguridad::alertaYRedirigir('warning', 'Archivo muy grande', 'El archivo PDF no puede superar los 10 MB.', $this->urlAccion('excusas'));
            return;
        }

        if (!Seguridad::esPdfValido($archivoSubido['tmp_name'])) {
            Seguridad::alertaYRedirigir('error', 'Formato inválido', 'Solo se permiten archivos en formato PDF válido.', $this->urlAccion('excusas'));
            return;
        }

        $idUsuario = (int) ($_SESSION['id'] ?? 0);
        if ($idUsuario <= 0) {
            $this->redirigirAccion('login');
        }

        $directorioDestino  = BASE_PATH . '/assets/excusas/';
        if (!is_dir($directorioDestino)) {
            @mkdir($directorioDestino, 0755, true);
        }
        $nombreUnico        = Seguridad::generarNombreArchivoUnico('soporte.pdf');
        $rutaFisicaCompleta = $directorioDestino . $nombreUnico;
        $rutaRelativaBD     = 'assets/excusas/' . $nombreUnico;

        if (!move_uploaded_file($archivoSubido['tmp_name'], $rutaFisicaCompleta)) {
            Seguridad::alertaYRedirigir('error', 'Error de almacenamiento', 'No fue posible guardar el archivo en el servidor.', $this->urlAccion('excusas'));
            return;
        }

        try {
            (new ExcusaModelo())->guardarVarios($idsAsistencia, $rutaRelativaBD, $motivo, $idUsuario);

            $totalDias  = count($idsAsistencia);
            $textoExito = $totalDias > 1
                ? "El soporte fue guardado para las $totalDias fechas seleccionadas y notificado a tus instructores."
                : 'El soporte ha sido guardado correctamente y notificado a tus instructores.';

            Seguridad::alertaYRedirigir('success', '¡Excusa radicada con éxito!', $textoExito, $this->urlAccion('excusas'));
        } catch (DomainException $e) {
            if (is_file($rutaFisicaCompleta)) {
                @unlink($rutaFisicaCompleta);
            }
            Seguridad::alertaYRedirigir('error', 'Operación no permitida', $e->getMessage(), $this->urlAccion('excusas'));
        } catch (Throwable $e) {
            if (is_file($rutaFisicaCompleta)) {
                @unlink($rutaFisicaCompleta);
            }
            error_log('Error al guardar excusa: ' . $e->getMessage());
            Seguridad::alertaYRedirigir('error', 'Error al radicar', 'No se pudo guardar la excusa médica. Intenta de nuevo.', $this->urlAccion('excusas'));
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

        $idUsuario     = (int) ($_SESSION['id'] ?? 0);
        $idExcusa      = (int) ($_POST['id_excusa'] ?? 0);
        $motivo        = trim($_POST['motivo'] ?? '');
        $archivoSubido = $_FILES['archivo'] ?? null;

        $modelo = new ExcusaModelo();
        // Verificación de propiedad y estado reenviable: la excusa debe pertenecer al aprendiz
        // y haber sido devuelta o rechazada previamente.
        $excusa = ($idExcusa > 0 && $idUsuario > 0) ? $modelo->buscarParaReenvioPorAprendiz($idExcusa, $idUsuario) : false;

        if (!$excusa) {
            Seguridad::alertaYRedirigir('error', 'Operación no permitida', 'No tienes permiso para modificar esta excusa o su estado actual no admite reenvío.', $this->urlAccion('excusas'));
            return;
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

        if ($archivoSubido['error'] !== UPLOAD_ERR_OK) {
            Seguridad::alertaYRedirigir('error', 'Error al subir archivo', 'No se pudo cargar el archivo adjunto. Verifica su tamaño e inténtalo de nuevo.', $this->urlAccion('excusas'));
            return;
        }

        if (($archivoSubido['size'] ?? 0) > 10 * 1024 * 1024) {
            Seguridad::alertaYRedirigir('warning', 'Archivo muy grande', 'El archivo PDF no puede superar los 10 MB.', $this->urlAccion('excusas'));
            return;
        }

        if (!Seguridad::esPdfValido($archivoSubido['tmp_name'])) {
            Seguridad::alertaYRedirigir('error', 'Formato inválido', 'Solo se permiten archivos en formato PDF válido.', $this->urlAccion('excusas'));
            return;
        }

        $directorioDestino  = BASE_PATH . '/assets/excusas/';
        if (!is_dir($directorioDestino)) {
            @mkdir($directorioDestino, 0755, true);
        }
        $nombreUnico        = Seguridad::generarNombreArchivoUnico('soporte.pdf');
        $rutaFisicaCompleta = $directorioDestino . $nombreUnico;
        $rutaRelativaBD     = 'assets/excusas/' . $nombreUnico;

        if (!move_uploaded_file($archivoSubido['tmp_name'], $rutaFisicaCompleta)) {
            Seguridad::alertaYRedirigir('error', 'Error de almacenamiento', 'No fue posible guardar el archivo en el servidor.', $this->urlAccion('excusas'));
            return;
        }

        try {
            $modelo->reenviar($idExcusa, $rutaRelativaBD, $motivo, $idUsuario);
            Seguridad::alertaYRedirigir('success', '¡Excusa reenviada!', 'Tu instructor la revisará de nuevo.', $this->urlAccion('excusas'));
        } catch (DomainException $e) {
            if (is_file($rutaFisicaCompleta)) {
                @unlink($rutaFisicaCompleta);
            }
            Seguridad::alertaYRedirigir('error', 'Operación no permitida', $e->getMessage(), $this->urlAccion('excusas'));
        } catch (Throwable $e) {
            if (is_file($rutaFisicaCompleta)) {
                @unlink($rutaFisicaCompleta);
            }
            error_log('Error al reenviar excusa: ' . $e->getMessage());
            Seguridad::alertaYRedirigir('error', 'Error al reenviar', 'No se pudo reenviar la excusa médica. Intenta de nuevo.', $this->urlAccion('excusas'));
        }
    }

    public function procesar(): void
    {
        $this->exigirSesion(['Instructor']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_excusa'])) {
            $this->redirigirAccion('validarExcusas', ['error' => '11']);
        }

        $this->exigirCsrfValido('validarExcusas');

        $idInstructor = (int) ($_SESSION['id'] ?? 0);
        $accion       = $_POST['accion'] ?? '';
        $idRevision   = (int) $_POST['id_excusa'];
        $observacion  = trim($_POST['observacion'] ?? '');
        $modelo       = new ExcusaModelo();

        if ($idInstructor <= 0 || $idRevision <= 0) {
            $this->redirigirAccion('validarExcusas', ['error' => '11']);
        }

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
            $detalleAprendiz = $modelo->obtenerDetalleRevision($idRevision, $idInstructor);
            if (!$detalleAprendiz) {
                $this->redirigirAccion('validarExcusas', ['error' => '13']);
            }

            $modelo->procesarRevision($idRevision, $idInstructor, $estadoBD, $observacion);

            // Notificar al aprendiz por correo electrónico el resultado de la revisión sin alterar ni borrar registros
            if (!empty($detalleAprendiz['correo_electronico'])) {
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
        } catch (DomainException $e) {
            error_log('Intento no autorizado al procesar excusa ID ' . $idRevision . ': ' . $e->getMessage());
            $this->redirigirAccion('validarExcusas', ['error' => '13']);
        } catch (Throwable $e) {
            error_log('Error al procesar excusa: ' . $e->getMessage());
            $this->redirigirAccion('validarExcusas', ['error' => '12']);
        }
    }

    /**
     * accion=verSoporteExcusa -> Visualización segura del archivo PDF adjunto
     * Valida sesión y autorización por rol antes de transmitir el archivo.
     */
    public function verSoporte(): void
    {
        $this->exigirSesion(['Administrador', 'Instructor', 'Aprendiz']);

        $idExcusa = (int) ($_GET['id'] ?? 0);
        if ($idExcusa <= 0) {
            http_response_code(400);
            die('Identificador de excusa no válido.');
        }

        $modelo    = new ExcusaModelo();
        $idUsuario = (int) ($_SESSION['id'] ?? 0);
        $rol       = (string) ($_SESSION['rol'] ?? '');

        $excusa = $modelo->buscarPorIdParaVer($idExcusa, $idUsuario, $rol);

        if (!$excusa || empty($excusa['archivo_adjunto'])) {
            http_response_code(403);
            die('Acceso denegado: No tienes permisos para consultar este documento o no existe.');
        }

        $dirExcusasBase = realpath(BASE_PATH . '/assets/excusas');
        $rutaArchivo    = realpath(BASE_PATH . '/' . ltrim(str_replace('\\', '/', $excusa['archivo_adjunto']), '/'));

        if ($dirExcusasBase === false || $rutaArchivo === false || !str_starts_with($rutaArchivo, $dirExcusasBase . DIRECTORY_SEPARATOR) || !is_file($rutaArchivo)) {
            http_response_code(404);
            die('El archivo de soporte solicitado no fue localizado en el servidor.');
        }

        // Cabeceras estrictas de transmisión de documento PDF seguro
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($rutaArchivo) . '"');
        header('Content-Length: ' . filesize($rutaArchivo));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($rutaArchivo);
        exit;
    }
}
