<?php

require_once __DIR__ . '/../models/FichaModelo.php';
require_once __DIR__ . '/FichaControlador.php';

class HorarioControlador extends ControladorBase
{
    private HorarioModelo $modelo;
    private FichaModelo $fichaModelo;

    public function __construct()
    {
        $this->modelo = new HorarioModelo();
        $this->fichaModelo = new FichaModelo();
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
            $fichas       = $this->fichaModelo->listarFichasDetalladas($idInstructorSesion, 'Activa');

            // Paleta sobria y armónica para bordes sutiles de acento por ficha
            $paletaGoogle = [
                '#2563eb', // Azul zafiro suave
                '#16a34a', // Verde esmeralda suave
                '#0891b2', // Cian pizarra
                '#475569', // Pizarra grafito
                '#7c3aed', // Violeta sobrio
                '#d97706', // Ámbar cálido
                '#0d9488', // Verde azulado
                '#4338ca', // Índigo moderno
            ];

            // Asignar color único y consistente a cada ficha
            $coloresFicha = [];
            foreach ($fichas as $idx => $f) {
                $idF = (int) $f['id_ficha'];
                $coloresFicha[$idF] = $paletaGoogle[$idx % count($paletaGoogle)];
            }

            // Integración de Días Festivos al Calendario
            $festivos = $this->modelo->listarFestivos();

            // Transformación a formato de eventos FullCalendar
            $eventosCalendario = $this->formatearEventos($horarios, $festivos, $coloresFicha);

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

    // Alias para compatibilidad de rutas
    public function mostrar(): void
    {
        $this->mostrarHorarios();
    }

    public function mostrarCalendario(): void
    {
        $this->mostrarHorarios();
    }

    /**
     * accion=horarioAprendiz -> Vista de consulta académica exclusiva para el rol Aprendiz.
     * Carga únicamente los horarios correspondientes a la ficha asignada en el servidor.
     */
    public function mostrarHorarioAprendiz(): void
    {
        $this->exigirSesion(['Aprendiz', 'aprendiz']);

        $rolActivo          = $_SESSION['rol'] ?? 'Aprendiz';
        $nombreUsuario      = $_SESSION['nombre'] ?? 'Aprendiz';
        $paginaActiva       = 'horario_aprendiz';
        $tituloPagina       = 'Horario de Formación';

        $idUsuario          = (int) ($_SESSION['id'] ?? 0);
        $aprendizInfo       = null;
        $sinFicha           = false;
        $sinHorarios        = false;
        $eventosCalendario  = [];
        $coloresFicha       = [];
        $resumenAprendiz    = [
            'total_clases'  => 0,
            'proxima_clase' => null,
            'ficha'         => null,
            'programa'      => null,
        ];

        try {
            $aprendizInfo = $this->modelo->obtenerInfoAprendizPorUsuario($idUsuario);

            if ($aprendizInfo === null || empty($aprendizInfo['id_ficha'])) {
                $sinFicha = true;
            } else {
                $idFicha = (int) $aprendizInfo['id_ficha'];
                $horarios = $this->modelo->listarHorariosPorFicha($idFicha);
                $festivos = $this->modelo->listarFestivos($idFicha);

                $coloresFicha = [$idFicha => '#39A900']; // Verde SENA institucional
                $eventosCalendario = $this->formatearEventos($horarios, $festivos, $coloresFicha);

                $sinHorarios = empty($horarios);

                // Calcular próxima clase
                $fechaHoy = date('Y-m-d');
                $horaActual = date('H:i:s');
                $proximaClase = null;

                foreach ($horarios as $h) {
                    if ($h['fecha'] > $fechaHoy || ($h['fecha'] === $fechaHoy && $h['hora_salida_programada'] > $horaActual)) {
                        $proximaClase = $h;
                        break;
                    }
                }

                $resumenAprendiz = [
                    'total_clases'  => count($horarios),
                    'proxima_clase' => $proximaClase,
                    'ficha'         => $aprendizInfo['numero_ficha'],
                    'programa'      => $aprendizInfo['nombre_programa'],
                ];
            }
        } catch (PDOException $e) {
            error_log('Error al cargar horario del aprendiz: ' . $e->getMessage());
            $_SESSION['mensaje_error'] = 'No se pudo cargar tu horario. Por favor intenta de nuevo.';
        }

        require BASE_PATH . '/views/horarios_aprendiz.php';
    }

    /**
     * Alias para compatibilidad hacia atrás
     */
    public function mostrarMiHorario(): void
    {
        $this->mostrarHorarioAprendiz();
    }


    // accion=guardarHorario (POST)
    public function guardar(): void
    {
        // Solo el Administrador puede registrar horarios
        $this->exigirSesion(['Administrador', 'admin']);

        // Validación de CSRF
        if (!Seguridad::validarCsrf($_POST['csrf_token'] ?? '')) {
            Seguridad::alertaYRedirigir(
                'error',
                'Sesión inválida',
                'El token de seguridad ha caducado o es inválido. Por favor intenta de nuevo.',
                $this->urlAccion('horarios')
            );
            return;
        }

        // Recoger y limpiar campos del formulario
        $idHorario    = (int) ($_POST['horario_id'] ?? 0);
        $numeroFicha  = trim(strip_tags((string)($_POST['numero_ficha'] ?? '')));
        $programa     = trim(html_entity_decode(strip_tags((string)($_POST['programa'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $fecha        = trim(strip_tags((string)($_POST['fecha'] ?? '')));
        $horaEntrada  = trim(strip_tags((string)($_POST['hora_entrada'] ?? '')));
        $horaSalida   = trim(strip_tags((string)($_POST['hora_salida'] ?? '')));
        $idInstructor = (int) ($_POST['instructor'] ?? 0);

        // 1. Si es modo edición (horario_id > 0), validar que el horario exista y no sea pasado ni en curso
        if ($idHorario > 0) {
            $horarioExistente = $this->modelo->obtenerHorarioPorId($idHorario);
            if (!$horarioExistente) {
                Seguridad::alertaYRedirigir(
                    'error',
                    'Horario no encontrado',
                    'El horario que intentas editar no existe o ya no está disponible en la programación.',
                    $this->urlAccion('horarios'),
                    'Entendido'
                );
                return;
            }

            $evaluacion = $this->modelo->evaluarEstadoTemporal($horarioExistente);
            if ($evaluacion['estado'] === 'pasado') {
                Seguridad::alertaYRedirigir(
                    'warning',
                    'No se puede editar este horario.',
                    'Esta clase ya finalizó y forma parte del historial de programación. Por seguridad, no se permite modificar horarios pasados.',
                    $this->urlAccion('horarios'),
                    'Entendido'
                );
                return;
            }

            if ($evaluacion['estado'] === 'en_curso') {
                Seguridad::alertaYRedirigir(
                    'info',
                    'Horario en curso.',
                    'Esta clase se está desarrollando actualmente. No es posible modificarla mientras esté en curso.',
                    $this->urlAccion('horarios'),
                    'Entendido'
                );
                return;
            }

            if (!$evaluacion['eliminable']) {
                Seguridad::alertaYRedirigir(
                    'warning',
                    'No se puede editar este horario.',
                    $evaluacion['motivo'],
                    $this->urlAccion('horarios'),
                    'Entendido'
                );
                return;
            }
        }

        // 2. Validaciones de campos requeridos
        if ($numeroFicha === '' || $fecha === '' || $horaEntrada === '' || $horaSalida === '') {
            Seguridad::alertaYRedirigir(
                'warning',
                'Campos incompletos',
                'Por favor completa todos los campos obligatorios (ficha, fecha, hora de entrada y salida).',
                $this->urlAccion('horarios')
            );
            return;
        }

        // 3. Validación de formato de fecha (YYYY-MM-DD)
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

        // 4. Comprobar que el número de ficha asociado exista y esté en estado Activa
        $fichaExistente = $this->fichaModelo->buscarFichaPorNumero($numeroFicha);
        if (!$fichaExistente) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Ficha no encontrada',
                "El número de ficha '{$numeroFicha}' no existe en la base de datos. Debes registrar previamente la ficha en el módulo de Fichas antes de crearle un horario.",
                $this->urlAccion('horarios')
            );
            return;
        }

        if (($fichaExistente['estado'] ?? 'Activa') !== 'Activa') {
            Seguridad::alertaYRedirigir(
                'warning',
                'Ficha no operativa',
                "La ficha '{$numeroFicha}' se encuentra en estado '{$fichaExistente['estado']}'. Por políticas institucionales, no es posible registrar ni modificar horarios en fichas finalizadas o archivadas.",
                $this->urlAccion('horarios')
            );
            return;
        }
        $idFicha = (int) $fichaExistente['id_ficha'];

        // 5. Evitar horarios duplicados o con solapamiento para la misma ficha o instructor
        $conflicto = $this->modelo->verificarConflictoHorario(
            $idFicha,
            $fecha,
            $horaEntrada,
            $horaSalida,
            $idHorario > 0 ? $idHorario : null,
            $idInstructor > 0 ? $idInstructor : null
        );

        if ($conflicto !== null) {
            $hConfIni = substr($conflicto['hora_entrada_programada'], 0, 5);
            $hConfFin = substr($conflicto['hora_salida_programada'], 0, 5);
            $docenteExistente = $conflicto['instructor_nombre'] ?? 'Sin asignar';

            if ($conflicto['tipo_conflicto'] === 'identico') {
                $mensajeConflicto = "Ya existe un horario idéntico registrado para la ficha {$numeroFicha} el día {$fecha} de {$hConfIni} a {$hConfFin} (Docente: {$docenteExistente}). No es posible registrar horarios duplicados.";
                $tituloConflicto  = 'Horario duplicado';
            } elseif ($conflicto['tipo_conflicto'] === 'instructor_ocupado') {
                $mensajeConflicto = "El instructor seleccionado ya tiene una clase programada el día {$fecha} de {$hConfIni} a {$hConfFin} con la ficha {$conflicto['numero_ficha']}.";
                $tituloConflicto  = 'Instructor no disponible';
            } else {
                $mensajeConflicto = "La ficha {$numeroFicha} ya tiene una clase programada el día {$fecha} de {$hConfIni} a {$hConfFin} (Docente: {$docenteExistente}), la cual se superpone con el horario ingresado ({$horaEntrada} - {$horaSalida}). Una misma ficha no puede tener dos clases simultáneas.";
                $tituloConflicto  = 'Conflicto de horario superpuesto';
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
        } catch (\DomainException $e) {
            Seguridad::alertaYRedirigir(
                'warning',
                'No se puede editar este horario.',
                $e->getMessage(),
                $this->urlAccion('horarios'),
                'Entendido'
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

    // accion=eliminarHorario (POST)
    public function eliminar(): void
    {
        $this->exigirSesion(['Administrador', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigirAccion('horarios');
            return;
        }

        if (!Seguridad::validarCsrf($_POST['csrf_token'] ?? '')) {
            Seguridad::alertaYRedirigir(
                'error',
                'Sesión inválida',
                'El token de seguridad ha caducado o es inválido.',
                $this->urlAccion('horarios')
            );
            return;
        }

        $idHorario = (int) ($_POST['horario_id'] ?? 0);

        if ($idHorario <= 0) {
            $this->redirigirAccion('horarios');
            return;
        }

        // 1. Obtener la información del horario para validar su existencia y temporalidad
        $horario = $this->modelo->obtenerHorarioPorId($idHorario);
        if (!$horario) {
            Seguridad::alertaYRedirigir(
                'error',
                'Horario no encontrado',
                'El horario que intentas eliminar no existe o ya fue retirado de la programación.',
                $this->urlAccion('horarios'),
                'Entendido'
            );
            return;
        }

        // 2. Validación estricta en el servidor: Proteger el historial contra eliminación de horarios pasados o en curso
        $evaluacion = $this->modelo->evaluarEstadoTemporal($horario);
        if (!$evaluacion['eliminable']) {
            Seguridad::alertaYRedirigir(
                'warning',
                'No se puede eliminar este horario',
                $evaluacion['motivo'],
                $this->urlAccion('horarios'),
                'Entendido'
            );
            return;
        }

        // 3. Proceso seguro de eliminación para horarios futuros válidos
        try {
            $asistenciasVinculadas = $this->modelo->contarAsistenciasHorario($idHorario);
            $this->modelo->eliminarHorario($idHorario);

            $textoExito = 'El horario fue eliminado exitosamente de la programación futura.';
            if ($asistenciasVinculadas > 0) {
                $textoExito .= " Se desvincularon de forma segura {$asistenciasVinculadas} registro(s) de asistencia histórica, preservando sus datos intactos.";
            }

            Seguridad::alertaYRedirigir(
                'success',
                '¡Horario eliminado!',
                $textoExito,
                $this->urlAccion('horarios'),
                'Continuar'
            );
        } catch (\Throwable $e) {
            error_log('Error al eliminar horario ID ' . $idHorario . ': ' . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'No se pudo eliminar el horario',
                'Ocurrió un error al procesar la eliminación del horario. Los registros de asistencia están protegidos.',
                $this->urlAccion('horarios'),
                'Entendido'
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
            $htmlErr = '<div style="text-align: left; font-size: 0.9rem; line-height: 1.5;">';
            $htmlErr .= '<p style="margin-bottom: 8px;">' . htmlspecialchars($resultado['mensaje']) . '</p>';
            if (!empty($resultado['errores'])) {
                $htmlErr .= '<div style="background-color: #ffebee; border-left: 4px solid #f44336; padding: 8px 12px; margin-top: 10px; border-radius: 4px;">';
                $htmlErr .= '<strong style="color: #c62828;">Conflictos de horario detectados (' . count($resultado['errores']) . '):</strong>';
                $htmlErr .= '<ul style="margin: 6px 0 0 16px; padding: 0; font-size: 0.85rem; max-height: 180px; overflow-y: auto;">';
                foreach (array_slice($resultado['errores'], 0, 15) as $err) {
                    $htmlErr .= '<li>' . htmlspecialchars($err) . '</li>';
                }
                if (count($resultado['errores']) > 15) {
                    $htmlErr .= '<li><em>... y ' . (count($resultado['errores']) - 15) . ' conflictos más.</em></li>';
                }
                $htmlErr .= '</ul></div>';
            }
            $htmlErr .= '</div>';

            Seguridad::alertaHtmlYRedirigir(
                'error',
                'Importación rechazada por conflicto',
                $htmlErr,
                $this->urlAccion('horarios')
            );
            return;
        }

        // Construir reporte detallado para el usuario con SweetAlert2
        $icono = ($resultado['guardados'] > 0 || $resultado['festivos_registrados'] > 0) ? 'success' : 'info';
        $titulo = ($resultado['guardados'] > 0 || $resultado['festivos_registrados'] > 0) ? '¡Importación exitosa!' : 'Importación finalizada';

        $html = '<div style="text-align: left; font-size: 0.95rem; line-height: 1.6;">';
        $html .= '<p style="margin-bottom: 10px;">Se procesó el archivo Excel oficial con los siguientes resultados:</p>';
        $html .= '<ul style="list-style: none; padding-left: 0; margin-bottom: 12px;">';
        $html .= '<li style="color: #2e7d32; margin-bottom: 4px;">✅ <strong>Horarios registrados:</strong> ' . $resultado['guardados'] . '</li>';
        if ($resultado['festivos_registrados'] > 0) {
            $html .= '<li style="color: #546e7a; margin-bottom: 4px;">📅 <strong>Días festivos identificados:</strong> ' . $resultado['festivos_registrados'] . ' (sin crear instructores)</li>';
        }
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

    // accion=exportarHorariosExcel (GET o POST) - Exportación en formato oficial SENA
    public function exportarExcel(): void
    {
        $this->exigirSesion(['Administrador', 'admin', 'Instructor']);

        $rolSesion = $_SESSION['rol'] ?? 'Administrador';
        $esInstructor = (strtolower($rolSesion) === 'instructor');
        $idInstructorSesion = $esInstructor ? (int) ($_SESSION['id'] ?? 0) : null;

        $idFichaFiltro = !empty($_GET['id_ficha']) ? (int)$_GET['id_ficha'] : null;

        // Si no se pasó id_ficha específico, seleccionar la primera ficha disponible con programación
        if ($idFichaFiltro === null || $idFichaFiltro <= 0) {
            $fichas = $this->fichaModelo->listarFichasDetalladas($idInstructorSesion);
            if (!empty($fichas)) {
                $idFichaFiltro = (int)$fichas[0]['id_ficha'];
            }
        }

        if ($idFichaFiltro === null || $idFichaFiltro <= 0) {
            Seguridad::alertaYRedirigir(
                'warning',
                'Sin ficha seleccionada',
                'No hay ninguna ficha de formación registrada o disponible para exportar.',
                $this->urlAccion('horarios')
            );
            return;
        }

        try {
            require_once BASE_PATH . '/helpers/ExcelHorarioHelper.php';
            $helper = new ExcelHorarioHelper($this->modelo);
            $helper->exportarOficial($idFichaFiltro, $idInstructorSesion);
        } catch (\Throwable $e) {
            error_log('Error al exportar horarios con formato oficial SENA: ' . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error al exportar',
                'Ocurrió un error al generar el archivo Excel oficial: ' . $e->getMessage(),
                $this->urlAccion('horarios')
            );
        }
    }

    // accion=descargarPlantillaHorarios (GET) - Descarga de la plantilla oficial SENA
    public function descargarPlantilla(): void
    {
        $this->exigirSesion(['Administrador', 'admin']);

        try {
            require_once BASE_PATH . '/helpers/ExcelHorarioHelper.php';
            $helper = new ExcelHorarioHelper($this->modelo);
            $helper->descargarPlantilla();
        } catch (\Throwable $e) {
            error_log('Error al descargar plantilla oficial: ' . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error al generar plantilla',
                $e->getMessage(),
                $this->urlAccion('horarios')
            );
        }
    }

    // accion=obtenerEventosJson (GET) - Endpoint AJAX para recarga segura de eventos
    public function obtenerEventosJson(): void
    {
        $this->exigirSesion(['Administrador', 'Instructor', 'Aprendiz', 'aprendiz']);

        header('Content-Type: application/json; charset=utf-8');

        $rolActivo          = strtolower(trim((string)($_SESSION['rol'] ?? 'Administrador')));
        $esInstructor       = ($rolActivo === 'instructor');
        $esAprendiz         = ($rolActivo === 'aprendiz');
        $idInstructorSesion = $esInstructor ? (int) ($_SESSION['id'] ?? 0) : null;

        $fechaInicio = null;
        $fechaFin    = null;

        if (!empty($_GET['start'])) {
            $valStart = substr(trim((string)$_GET['start']), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valStart)) {
                $fechaInicio = $valStart;
            }
        }

        if (!empty($_GET['end'])) {
            $valEnd = substr(trim((string)$_GET['end']), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valEnd)) {
                $fechaFin = $valEnd;
            }
        }

        try {
            // Si es rol Aprendiz: filtra estricta y exclusivamente por la ficha asignada a su usuario en sesión
            if ($esAprendiz) {
                $idUsuario = (int) ($_SESSION['id'] ?? 0);
                $aprendizInfo = $this->modelo->obtenerInfoAprendizPorUsuario($idUsuario);

                if (!$aprendizInfo || empty($aprendizInfo['id_ficha'])) {
                    echo json_encode(['exito' => true, 'eventos' => []], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $idFicha = (int) $aprendizInfo['id_ficha'];
                $horarios = $this->modelo->listarHorariosPorFicha($idFicha, $fechaInicio, $fechaFin);
                $festivos = $this->modelo->listarFestivos($idFicha, $fechaInicio, $fechaFin);
                $coloresFicha = [$idFicha => '#39A900'];

                $eventos = $this->formatearEventos($horarios, $festivos, $coloresFicha);

                echo json_encode([
                    'exito'   => true,
                    'eventos' => $eventos
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Administrador e Instructor
            $horarios     = $this->modelo->listarHorarios($idInstructorSesion, $fechaInicio, $fechaFin);
            $festivos     = $this->modelo->listarFestivos(null, $fechaInicio, $fechaFin);
            $fichas       = $this->fichaModelo->listarFichasDetalladas($idInstructorSesion);

            $paletaGoogle = [
                '#2563eb', '#16a34a', '#0891b2', '#475569',
                '#7c3aed', '#d97706', '#0d9488', '#4338ca'
            ];

            $coloresFicha = [];
            foreach ($fichas as $idx => $f) {
                $idF = (int) $f['id_ficha'];
                $coloresFicha[$idF] = $paletaGoogle[$idx % count($paletaGoogle)];
            }

            $eventos = $this->formatearEventos($horarios, $festivos, $coloresFicha);

            echo json_encode([
                'exito'   => true,
                'eventos' => $eventos
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'exito'   => false,
                'mensaje' => 'Error al obtener los eventos: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // Formateo uniforme de horarios y festivos a la especificación de FullCalendar
    private function formatearEventos(array $horarios, array $festivos, array $coloresFicha): array
    {
        $eventosCalendario = [];

        foreach ($horarios as $h) {
            $idFicha = (int) $h['id_ficha'];
            $colorEvento = $coloresFicha[$idFicha] ?? '#039be5';

            $horaEntrada = substr($h['hora_entrada_programada'], 0, 5);
            $horaSalida  = substr($h['hora_salida_programada'], 0, 5);

            $tEntrada = strtotime($h['hora_entrada_programada']);
            $tSalida  = strtotime($h['hora_salida_programada']);
            $duracionMinutos = ($tSalida > $tEntrada) ? (int) (($tSalida - $tEntrada) / 60) : 0;
            $duracionHoras   = round($duracionMinutos / 60, 1);

            $progLimpio = html_entity_decode((string)$h['nombre_programa'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $instLimpio = html_entity_decode((string)($h['instructor_nombre'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $areaLimpia = html_entity_decode((string)($h['instructor_area'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($areaLimpia !== '' && (strpos($areaLimpia, '-') !== false || strpos($areaLimpia, '–') !== false || strpos($areaLimpia, '—') !== false)) {
                $partesArea = preg_split('/[-–—]/u', $areaLimpia);
                $areaLimpia = trim($partesArea[0] ?? '');
            }

            $eventosCalendario[] = [
                'id'              => (string) $h['id_horario'],
                'title'           => $h['numero_ficha'] . ' · ' . $progLimpio,
                'start'           => $h['fecha'] . 'T' . $horaEntrada . ':00',
                'end'             => $h['fecha'] . 'T' . $horaSalida . ':00',
                'backgroundColor' => $colorEvento,
                'borderColor'     => $colorEvento,
                'textColor'       => '#ffffff',
                'extendedProps'   => [
                    'id_ficha'       => $idFicha,
                    'ficha'          => $h['numero_ficha'],
                    'programa'       => $progLimpio,
                    'instructor'     => trim($instLimpio) !== '' ? $instLimpio : 'Sin asignar',
                    'id_instructor'  => (int) ($h['id_instructor'] ?? 0),
                    'area'           => trim($areaLimpia),
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

        foreach ($festivos as $fest) {
            $hIni = substr($fest['hora_inicio'] ?? '06:00:00', 0, 5);
            $hFin = substr($fest['hora_fin'] ?? '22:00:00', 0, 5);
            $esDiaCompleto = (int)($fest['es_dia_completo'] ?? 1);
            $colorFestivo = '#607d8b';
            $numFichaFest = !empty($fest['numero_ficha']) ? $fest['numero_ficha'] : 'General';

            $eventosCalendario[] = [
                'id'              => 'festivo_' . $fest['id_festivo'],
                'title'           => 'FESTIVO',
                'start'           => $fest['fecha'] . 'T' . $hIni . ':00',
                'end'             => $fest['fecha'] . 'T' . $hFin . ':00',
                'backgroundColor' => $colorFestivo,
                'borderColor'     => '#455a64',
                'textColor'       => '#ffffff',
                'extendedProps'   => [
                    'es_festivo'     => true,
                    'es_dia_completo'=> $esDiaCompleto,
                    'id_ficha'       => (int)($fest['id_ficha'] ?? 0),
                    'ficha'          => $numFichaFest,
                    'programa'       => 'Día Festivo Institucional',
                    'instructor'     => 'FESTIVO',
                    'id_instructor'  => 0,
                    'area'           => 'Calendario Oficial SENA',
                    'entrada'        => $hIni,
                    'salida'         => $hFin,
                    'duracion_min'   => 0,
                    'duracion_horas' => 0,
                    'aprendices'     => 0,
                    'fecha'          => $fest['fecha'],
                    'color'          => $colorFestivo,
                ]
            ];
        }

        return $eventosCalendario;
    }
}


