<?php

class HorarioControlador extends ControladorBase
{

    private HorarioModelo $modelo;

    public function __construct()
    {
        $this->modelo = new HorarioModelo();
    }

    // accion=horarios -> Vista dedicada exclusivamente al calendario y programación de horarios
    public function mostrarHorarios(): void
    {
        $this->exigirSesion(['Administrador', 'Instructor']);

        try {
            $horarios     = $this->modelo->listarHorarios();
            $instructores = $this->modelo->listarInstructores();
            $fichas       = $this->modelo->listarFichasDetalladas();

            // Transformación a formato de eventos FullCalendar
            $eventosCalendario = [];
            foreach ($horarios as $h) {
                $eventosCalendario[] = [
                    'id'            => (string) $h['id_horario'],
                    'title'         => $h['numero_ficha'] . ' · ' . $h['nombre_programa'],
                    'start'         => $h['fecha'] . 'T' . substr($h['hora_entrada_programada'], 0, 5) . ':00',
                    'end'           => $h['fecha'] . 'T' . substr($h['hora_salida_programada'], 0, 5) . ':00',
                    'extendedProps' => [
                        'id_ficha'      => (int) $h['id_ficha'],
                        'ficha'         => $h['numero_ficha'],
                        'programa'      => $h['nombre_programa'],
                        'instructor'    => trim($h['instructor_nombre'] ?? '') !== '' ? $h['instructor_nombre'] : 'Sin asignar',
                        'id_instructor' => (int) ($h['id_instructor'] ?? 0),
                        'entrada'       => substr($h['hora_entrada_programada'], 0, 5),
                        'salida'        => substr($h['hora_salida_programada'], 0, 5),
                        'aprendices'    => (int) ($h['total_aprendices'] ?? 0),
                        'fecha'         => $h['fecha']
                    ]
                ];
            }
        } catch (PDOException $e) {
            error_log('Error al listar horarios: ' . $e->getMessage());
            $eventosCalendario = [];
            $instructores      = [];
            $fichas            = [];
            $_SESSION['mensaje_error'] = 'No se pudieron cargar los horarios. Intenta de nuevo.';
        }

        require BASE_PATH . '/views/horarios.php';
    }

    // accion=fichas -> Vista dedicada exclusivamente al listado y gestión de fichas
    public function mostrarFichas(): void
    {
        $this->exigirSesion(['Administrador', 'Instructor']);

        try {
            $fichas       = $this->modelo->listarFichasDetalladas();
            $instructores = $this->modelo->listarInstructores();
        } catch (PDOException $e) {
            error_log('Error al listar fichas: ' . $e->getMessage());
            $fichas       = [];
            $instructores = [];
            $_SESSION['mensaje_error'] = 'No se pudieron cargar las fichas.';
        }

        require BASE_PATH . '/views/fichas.php';
    }

    // Alias para compatibilidad
    public function mostrar(): void
    {
        $this->mostrarHorarios();
    }

    public function mostrarCalendario(): void
    {
        $this->mostrarHorarios();
    }

    // accion=guardarFicha (POST)
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
        $numeroFicha  = Seguridad::sanitizar($_POST['numero_ficha'] ?? '');
        $programa     = Seguridad::sanitizar($_POST['nombre_programa'] ?? '');
        $idInstructor = (int) ($_POST['id_instructor'] ?? 0);

        if ($numeroFicha === '' || $programa === '') {
            Seguridad::alertaYRedirigir(
                'warning',
                'Campos requeridos',
                'Por favor ingresa el número de ficha y el nombre del programa.',
                $this->urlAccion('fichas')
            );
            return;
        }

        try {
            if ($idFicha > 0) {
                // Actualizar ficha existente
                $this->modelo->actualizarFicha($idFicha, $numeroFicha, $programa);
                if ($idInstructor > 0) {
                    $this->modelo->asociarInstructor($idFicha, $idInstructor);
                }
                $titulo = '¡Ficha actualizada!';
                $texto  = 'La información de la ficha fue guardada exitosamente.';
            } else {
                // Crear nueva ficha
                $idNueva = $this->modelo->obtenerOCrearFicha($numeroFicha, $programa);
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
            Seguridad::alertaYRedirigir(
                'error',
                'Error de base de datos',
                'Ocurrió un error al guardar la ficha. Intenta de nuevo.',
                $this->urlAccion('fichas')
            );
        }
    }

    // accion=guardarHorario (POST)
    public function guardar(): void
    {
        // Solo el Administrador puede registrar horarios
        $this->exigirSesion(['Administrador', 'admin']);

        // Recoger y limpiar campos del formulario
        $idHorario    = (int) ($_POST['horario_id'] ?? 0);
        $numeroFicha  = Seguridad::sanitizar($_POST['numero_ficha'] ?? '');
        $programa     = Seguridad::sanitizar($_POST['programa'] ?? '');
        $fecha        = Seguridad::sanitizar($_POST['fecha'] ?? '');
        $horaEntrada  = Seguridad::sanitizar($_POST['hora_entrada'] ?? '');
        $horaSalida   = Seguridad::sanitizar($_POST['hora_salida'] ?? '');
        $idInstructor = (int) ($_POST['instructor'] ?? 0);

        // Validaciones de campos requeridos
        if ($numeroFicha === '' || $programa === '' || $fecha === '' || $horaEntrada === '' || $horaSalida === '') {
            Seguridad::alertaYRedirigir(
                'warning',
                'Campos incompletos',
                'Por favor completa todos los campos obligatorios.',
                $this->urlAccion('horarios')
            );
            return;
        }

        try {
            // Obtener el id de la ficha o crearla si es nueva
            $idFicha = $this->modelo->obtenerOCrearFicha($numeroFicha, $programa);

            // Asociar instructor si se seleccionó uno
            if ($idInstructor > 0) {
                $this->modelo->asociarInstructor($idFicha, $idInstructor);
            }

            if ($idHorario > 0) {
                // Modo Edición: Actualizar horario existente
                $this->modelo->actualizarHorario($idHorario, $idFicha, $horaEntrada, $horaSalida, $fecha);
                $mensajeTitulo = '¡Horario actualizado!';
                $mensajeTexto  = 'Los cambios fueron guardados exitosamente.';
            } else {
                // Modo Creación: Registrar nuevo horario
                $this->modelo->guardarHorario($idFicha, $horaEntrada, $horaSalida, $fecha);
                $mensajeTitulo = '¡Horario registrado!';
                $mensajeTexto  = 'El horario fue guardado exitosamente en la base de datos.';
            }

            Seguridad::alertaYRedirigir(
                'success',
                $mensajeTitulo,
                $mensajeTexto,
                $this->urlAccion('horarios')
            );
        } catch (PDOException $e) {
            error_log('Error al guardar horario: ' . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error de base de datos',
                'Ocurrió un error al guardar el horario. Intenta de nuevo.',
                $this->urlAccion('horarios')
            );
        }
    }

    // accion=importarHorariosExcel (POST)
    public function importarExcel(): void
    {
        // Solo el Administrador puede importar archivos Excel de horarios
        $this->exigirSesion(['Administrador', 'admin']);

        // Validación de CSRF
        if (!Seguridad::validarCsrf($_POST['csrf_token'] ?? '')) {
            Seguridad::alertaYRedirigir(
                'error',
                'Sesión inválida',
                'El token de seguridad ha caducado. Por favor, intenta de nuevo.',
                $this->urlAccion('fichas')
            );
            return;
        }

        require_once BASE_PATH . '/helpers/ExcelHorarioHelper.php';

        $helper = new ExcelHorarioHelper($this->modelo);
        $validacion = $helper->validarArchivo($_FILES['archivo_excel'] ?? []);

        if (!$validacion['valido']) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Archivo no válido',
                $validacion['error'],
                $this->urlAccion('horarios')
            );
            return;
        }

        // Ejecutar proceso de lectura de datos e inserción en la base de datos
        $resultado = $helper->importar($_FILES['archivo_excel']['tmp_name']);

        if (!$resultado['exito']) {
            Seguridad::alertaYRedirigir(
                'error',
                'Error en la importación',
                $resultado['mensaje'],
                $this->urlAccion('horarios')
            );
            return;
        }

        // Construir reporte detallado para el usuario con SweetAlert2
        $icono = ($resultado['guardados'] > 0) ? 'success' : 'info';
        $titulo = ($resultado['guardados'] > 0) ? '¡Importación exitosa!' : 'Importación finalizada';

        $html = '<div style="text-align: left; font-size: 0.95rem; line-height: 1.6;">';
        $html .= '<p style="margin-bottom: 10px;">Se procesaron <strong>' . $resultado['total_procesados'] . '</strong> bloques de horario del archivo Excel:</p>';
        $html .= '<ul style="list-style: none; padding-left: 0; margin-bottom: 12px;">';
        $html .= '<li style="color: #2e7d32; margin-bottom: 4px;">✅ <strong>Horarios registrados:</strong> ' . $resultado['guardados'] . '</li>';
        $html .= '<li style="color: #0277bd; margin-bottom: 4px;">ℹ️ <strong>Horarios ya existentes (omitidos):</strong> ' . $resultado['duplicados'] . '</li>';
        $html .= '<li style="color: #555; margin-bottom: 4px;">📋 <strong>Fichas nuevas creadas:</strong> ' . $resultado['fichas_creadas'] . '</li>';
        $html .= '<li style="color: #555; margin-bottom: 4px;">👨‍🏫 <strong>Instructores nuevos creados:</strong> ' . $resultado['instructores_creados'] . '</li>';
        $html .= '</ul>';

        if (!empty($resultado['errores'])) {
            $totalErr = count($resultado['errores']);
            $html .= '<div style="background-color: #fff3e0; border-left: 4px solid #ff9800; padding: 8px 12px; margin-top: 10px; border-radius: 4px;">';
            $html .= '<strong style="color: #e65100;">Filas con observaciones (' . $totalErr . '):</strong>';
            $html .= '<ul style="margin: 6px 0 0 16px; padding: 0; font-size: 0.85rem; max-height: 120px; overflow-y: auto;">';
            foreach (array_slice($resultado['errores'], 0, 10) as $err) {
                $html .= '<li>' . htmlspecialchars($err) . '</li>';
            }
            if ($totalErr > 10) {
                $html .= '<li><em>... y ' . ($totalErr - 10) . ' observaciones más.</em></li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '</div>';

        Seguridad::alertaHtmlYRedirigir(
            $icono,
            $titulo,
            $html,
            $this->urlAccion('horarios')
        );
    }
}


