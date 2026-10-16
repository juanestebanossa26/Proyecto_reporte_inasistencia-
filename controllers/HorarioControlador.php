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
        $numeroFicha  = trim(Seguridad::sanitizar($_POST['numero_ficha'] ?? ''));
        $programa     = trim(Seguridad::sanitizar($_POST['nombre_programa'] ?? ''));
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

    // accion=guardarHorario (POST)
    public function guardar(): void
    {
        // Solo el Administrador puede registrar horarios
        $this->exigirSesion(['Administrador', 'admin']);

        // Recoger y limpiar campos del formulario
        $idHorario    = (int) ($_POST['horario_id'] ?? 0);
        $numeroFicha  = trim(Seguridad::sanitizar($_POST['numero_ficha'] ?? ''));
        $programa     = trim(Seguridad::sanitizar($_POST['programa'] ?? ''));
        $fecha        = trim(Seguridad::sanitizar($_POST['fecha'] ?? ''));
        $horaEntrada  = trim(Seguridad::sanitizar($_POST['hora_entrada'] ?? ''));
        $horaSalida   = trim(Seguridad::sanitizar($_POST['hora_salida'] ?? ''));
        $idInstructor = (int) ($_POST['instructor'] ?? 0);

        // 1. Validaciones de campos requeridos
        if ($numeroFicha === '' || $fecha === '' || $horaEntrada === '' || $horaSalida === '') {
            Seguridad::alertaYRedirigir(
                'warning',
                'Campos incompletos',
                'Por favor completa todos los campos obligatorios (ficha, fecha, hora de entrada y salida).',
                $this->urlAccion('horarios')
            );
            return;
        }

        // 2. Validación de formato de fecha (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Fecha no válida',
                'La fecha seleccionada no tiene un formato válido.',
                $this->urlAccion('horarios')
            );
            return;
        }

        // 3. Validación de horas: entrada debe ser anterior a salida
        if (strtotime($horaEntrada) >= strtotime($horaSalida)) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Horario incongruente',
                "La hora de entrada ({$horaEntrada}) debe ser anterior a la hora de salida ({$horaSalida}).",
                $this->urlAccion('horarios')
            );
            return;
        }

        // 4. Comprobar que el número de ficha asociado exista y sea válido
        $fichaExistente = $this->modelo->buscarFichaPorNumero($numeroFicha);
        if (!$fichaExistente) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Ficha no encontrada',
                "El número de ficha '{$numeroFicha}' no existe en la base de datos. Debes registrar previamente la ficha en el módulo de Fichas antes de crearle un horario.",
                $this->urlAccion('horarios')
            );
            return;
        }
        $idFicha = (int) $fichaExistente['id_ficha'];

        // 5. Evitar horarios duplicados o con solapamiento para la misma ficha
        $conflicto = $this->modelo->verificarConflictoHorario(
            $idFicha,
            $fecha,
            $horaEntrada,
            $horaSalida,
            $idHorario > 0 ? $idHorario : null
        );

        if ($conflicto !== null) {
            $hConfIni = substr($conflicto['hora_entrada_programada'], 0, 5);
            $hConfFin = substr($conflicto['hora_salida_programada'], 0, 5);

            if ($conflicto['tipo_conflicto'] === 'identico') {
                $mensajeConflicto = "Ya existe un horario idéntico registrado para la ficha {$numeroFicha} el día {$fecha} de {$hConfIni} a {$hConfFin}. No es posible registrar horarios duplicados.";
                $tituloConflicto  = 'Horario duplicado';
            } else {
                $mensajeConflicto = "La ficha {$numeroFicha} ya tiene una clase programada el día {$fecha} de {$hConfIni} a {$hConfFin}, la cual se solapa con el horario ingresado ({$horaEntrada} - {$horaSalida}). Por favor selecciona otro rango de horas o fecha.";
                $tituloConflicto  = 'Conflicto de horario';
            }

            Seguridad::alertaYRedirigir(
                'warning',
                $tituloConflicto,
                $mensajeConflicto,
                $this->urlAccion('horarios')
            );
            return;
        }

        try {
            // Asociar instructor si se seleccionó uno
            if ($idInstructor > 0) {
                $this->modelo->asociarInstructor($idFicha, $idInstructor);
            }

            if ($idHorario > 0) {
                // Modo Edición: Actualizar horario existente
                $this->modelo->actualizarHorario($idHorario, $idFicha, $horaEntrada, $horaSalida, $fecha, $idInstructor);
                $mensajeTitulo = '¡Horario actualizado!';
                $mensajeTexto  = 'Los cambios en el horario fueron guardados exitosamente.';
            } else {
                // Modo Creación: Registrar nuevo horario
                $this->modelo->guardarHorario($idFicha, $horaEntrada, $horaSalida, $fecha, $idInstructor);
                $mensajeTitulo = '¡Horario registrado!';
                $mensajeTexto  = 'El horario fue guardado exitosamente en el calendario.';
            }

            Seguridad::alertaYRedirigir(
                'success',
                $mensajeTitulo,
                $mensajeTexto,
                $this->urlAccion('horarios')
            );
        } catch (PDOException $e) {
            error_log('Error al guardar horario: ' . $e->getMessage());
            $errorMsg = ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry'))
                ? "Ya existe un horario programado para esta ficha el día {$fecha} en este mismo horario."
                : 'Ocurrió un error al guardar el horario. Intenta de nuevo.';

            Seguridad::alertaYRedirigir(
                'error',
                'Error al guardar',
                $errorMsg,
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


