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

        $rolActivo          = $_SESSION['rol'] ?? 'Administrador';
        $esInstructor       = (strtolower($rolActivo) === 'instructor');
        $idInstructorSesion = $esInstructor ? (int) ($_SESSION['id'] ?? 0) : null;

        try {
            $horarios     = $this->modelo->listarHorarios($idInstructorSesion);
            $instructores = $this->modelo->listarInstructores();
            $fichas       = $this->modelo->listarFichasDetalladas($idInstructorSesion);

            // Paleta de colores con contraste óptimo para texto blanco
            $paletaGoogle = [
                '#1976d2', // Azul corporativo
                '#2e7d32', // Verde SENA
                '#7b1fa2', // Púrpura intenso
                '#c2185b', // Frambuesa
                '#d97706', // Ámbar oscuro
                '#e65100', // Naranja intenso
                '#303f9f', // Índigo profundo
                '#00796b', // Turquesa oscuro
                '#d32f2f', // Carmesí / Rojo
                '#455a64', // Pizarra / Grafito
                '#00838f', // Cian oscuro
                '#4e342e', // Café moka
            ];

            // Asignar color único y consistente a cada ficha
            $coloresFicha = [];
            foreach ($fichas as $idx => $f) {
                $idF = (int) $f['id_ficha'];
                $coloresFicha[$idF] = $paletaGoogle[$idx % count($paletaGoogle)];
            }

            // Transformación a formato de eventos FullCalendar
            $eventosCalendario = [];
            foreach ($horarios as $h) {
                $idFicha = (int) $h['id_ficha'];
                $colorEvento = $coloresFicha[$idFicha] ?? '#039be5';

                $horaEntrada = substr($h['hora_entrada_programada'], 0, 5);
                $horaSalida  = substr($h['hora_salida_programada'], 0, 5);

                // Duración en minutos y horas
                $tEntrada = strtotime($h['hora_entrada_programada']);
                $tSalida  = strtotime($h['hora_salida_programada']);
                $duracionMinutos = ($tSalida > $tEntrada) ? (int) (($tSalida - $tEntrada) / 60) : 0;
                $duracionHoras   = round($duracionMinutos / 60, 1);

                $eventosCalendario[] = [
                    'id'              => (string) $h['id_horario'],
                    'title'           => $h['numero_ficha'] . ' · ' . $h['nombre_programa'],
                    'start'           => $h['fecha'] . 'T' . $horaEntrada . ':00',
                    'end'             => $h['fecha'] . 'T' . $horaSalida . ':00',
                    'backgroundColor' => $colorEvento,
                    'borderColor'     => $colorEvento,
                    'textColor'       => '#ffffff',
                    'extendedProps'   => [
                        'id_ficha'       => $idFicha,
                        'ficha'          => $h['numero_ficha'],
                        'programa'       => $h['nombre_programa'],
                        'instructor'     => trim($h['instructor_nombre'] ?? '') !== '' ? $h['instructor_nombre'] : 'Sin asignar',
                        'id_instructor'  => (int) ($h['id_instructor'] ?? 0),
                        'area'           => trim($h['instructor_area'] ?? ''),
                        'entrada'        => $horaEntrada,
                        'salida'         => $horaSalida,
                        'duracion_min'   => $duracionMinutos,
                        'duracion_horas' => $duracionHoras,
                        'aprendices'     => (int) ($h['total_aprendices'] ?? 0),
                        'fecha'          => $h['fecha'],
                        'color'          => $colorEvento,
                    ]
                ];
            }

            // Resumen de la Jornada para el instructor
            $fechaHoy     = date('Y-m-d');
            $horaActual   = date('H:i:s');
            $clasesHoy    = [];
            $proximaClase = null;
            $horasHoy     = 0.0;

            foreach ($horarios as $h) {
                if ($h['fecha'] === $fechaHoy) {
                    $clasesHoy[] = $h;
                    $t1 = strtotime($h['hora_entrada_programada']);
                    $t2 = strtotime($h['hora_salida_programada']);
                    if ($t2 > $t1) {
                        $horasHoy += ($t2 - $t1) / 3600;
                    }

                    if ($h['hora_salida_programada'] > $horaActual && $proximaClase === null) {
                        $proximaClase = $h;
                    }
                }
            }

            // Si hoy no hay pendientes, buscar la primera clase próxima en el calendario
            if ($proximaClase === null) {
                foreach ($horarios as $h) {
                    if ($h['fecha'] > $fechaHoy || ($h['fecha'] === $fechaHoy && $h['hora_salida_programada'] > $horaActual)) {
                        $proximaClase = $h;
                        break;
                    }
                }
            }

            $resumenJornada = [
                'total_clases_hoy' => count($clasesHoy),
                'total_horas_hoy'  => round($horasHoy, 1),
                'total_fichas'     => count($fichas),
                'proxima_clase'    => $proximaClase,
                'fecha_hoy'        => $fechaHoy,
            ];

        } catch (PDOException $e) {
            error_log('Error al listar horarios: ' . $e->getMessage());
            $eventosCalendario = [];
            $instructores      = [];
            $fichas            = [];
            $coloresFicha      = [];
            $resumenJornada    = [
                'total_clases_hoy' => 0,
                'total_horas_hoy'  => 0,
                'total_fichas'     => 0,
                'proxima_clase'    => null,
                'fecha_hoy'        => date('Y-m-d'),
            ];
            $_SESSION['mensaje_error'] = 'No se pudieron cargar los horarios. Intenta de nuevo.';
        }

        require BASE_PATH . '/views/horarios.php';
    }

    // accion=fichas -> Vista dedicada exclusivamente al listado y gestión de fichas
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
                $this->modelo->actualizarHorario($idHorario, $idFicha, $horaEntrada, $horaSalida, $fecha, $idInstructor);
                $mensajeTitulo = '¡Horario actualizado!';
                $mensajeTexto  = 'Los cambios fueron guardados exitosamente.';
            } else {
                // Modo Creación: Registrar nuevo horario
                $this->modelo->guardarHorario($idFicha, $horaEntrada, $horaSalida, $fecha, $idInstructor);
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

    // accion=eliminarHorario
    public function eliminar(): void
    {
        $this->exigirSesion(['Administrador', 'admin']);
        $idHorario = (int) ($_POST['horario_id'] ?? $_GET['id'] ?? 0);

        if ($idHorario > 0) {
            $this->modelo->eliminarHorario($idHorario);
            Seguridad::alertaYRedirigir(
                'success',
                '¡Horario eliminado!',
                'El horario fue eliminado exitosamente.',
                $this->urlAccion('horarios')
            );
        } else {
            $this->redirigirAccion('horarios');
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


