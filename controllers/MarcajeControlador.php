<?php
// Controlador de marcaje RFID.

class MarcajeControlador extends ControladorBase
{
    private const TOLERANCIA_RETARDO_MIN   = 0; // minutos de gracia antes de contar como retardo
    private const ANTICIPACION_PERMITIDA_MIN = 30; // minutos antes del inicio en que ya se puede marcar

    // accion=marcaje -> muestra el lector + los últimos marcajes de hoy.
    public function mostrar(): void
    {
        $this->exigirSesion(['Instructor', 'Administrador']);

        $hoy = date('Y-m-d');

        // Un instructor solo ve los marcajes de sus propios bloques/fichas; el
        // administrador los ve todos.
        $idInstructorFiltro = null;
        if (($_SESSION['rol'] ?? '') === 'Instructor') {
            $idInstructorFiltro = (int) ($_SESSION['id'] ?? 0);
        }

        try {
            $marcajeModelo   = new MarcajeModelo();
            $ultimosMarcajes = $marcajeModelo->listarMarcajesDeHoy($hoy, null, 15, $idInstructorFiltro);
        } catch (PDOException $e) {
            error_log('Error al listar marcajes: ' . $e->getMessage());
            $ultimosMarcajes = [];
            $_SESSION['mensaje_error'] = 'No se pudieron cargar los marcajes de hoy.';
        }

        require BASE_PATH . '/views/marcaje.php';
    }

    // accion=marcarAsistencia (POST, AJAX) -> procesa UNA lectura del
    // lector/input RFID y responde en JSON para que la vista lo muestre
    // sin recargar la página.
    public function marcar(): void
    {
        $this->exigirSesion(['Instructor', 'Administrador']);
        header('Content-Type: application/json; charset=utf-8');

        if (!Seguridad::validarCsrf($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['success' => false, 'message' => 'Tu sesión expiró, recarga la página.']);
            return;
        }

        $rolSesion = $_SESSION['rol'] ?? '';
        $idInstructorSesion = (int) ($_SESSION['id'] ?? 0);
        $nombreInstructorSesion = trim($_SESSION['nombre'] ?? 'Instructor');

        // Validación fundamental: Solo el perfil del instructor puede registrar el marcaje
        if ($rolSesion !== 'Instructor') {
            echo json_encode([
                'success' => false,
                'message' => 'El marcaje RFID solo puede ser realizado desde el perfil del instructor a cargo de la clase.',
            ]);
            return;
        }

        $tagRfid = trim($_POST['tag_rfid'] ?? '');
        if ($tagRfid === '') {
            echo json_encode(['success' => false, 'message' => 'Ingresa o acerca un carné.']);
            return;
        }

        $modelo = new MarcajeModelo();

        try {
            $aprendiz = $modelo->buscarAprendizPorTag($tagRfid);

            if (!$aprendiz) {
                echo json_encode(['success' => false, 'message' => 'Carné no reconocido.']);
                return;
            }

            if (strtolower($aprendiz['estado']) !== 'activo') {
                echo json_encode(['success' => false, 'message' => 'Este aprendiz no está activo.']);
                return;
            }

            $ahora = new DateTime();
            $fechaHoy = $ahora->format('Y-m-d');

            $bloques = $modelo->obtenerBloquesDelDia((int) $aprendiz['id_ficha'], $fechaHoy);

            if (empty($bloques)) {
                echo json_encode(['success' => false, 'message' => 'Su ficha no tiene clase programada hoy.']);
                return;
            }

            $asistenciasHoy = $modelo->obtenerAsistenciasDelDia((int) $aprendiz['id_aprendiz'], $fechaHoy);

            $resultado = $this->decidirYRegistrar(
                $modelo,
                $aprendiz,
                $bloques,
                $asistenciasHoy,
                $ahora,
                $fechaHoy,
                $idInstructorSesion,
                $nombreInstructorSesion
            );

            echo json_encode($resultado);
        } catch (PDOException $e) {
            error_log('Error al registrar marcaje: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al registrar el marcaje. Intenta de nuevo.']);
        }
    }

    // Recorre los bloques del día y valida que el marcaje corresponda
    // estrictamente al instructor que tiene la sesión abierta.
    private function decidirYRegistrar(
        MarcajeModelo $modelo,
        array $aprendiz,
        array $bloques,
        array $asistenciasHoy,
        DateTime $ahora,
        string $fechaHoy,
        int $idInstructorSesion,
        string $nombreInstructorSesion
    ): array {
        $idAprendiz     = (int) $aprendiz['id_aprendiz'];
        $nombreCompleto = trim($aprendiz['nombre_completo'] ?? '');
        $fichaLabel     = $aprendiz['numero_ficha'] . ' · ' . $aprendiz['nombre_programa'];

        // Filtrar los bloques programados hoy que pertenecen al instructor actualmente logueado
        $bloquesMios = array_values(array_filter($bloques, function ($b) use ($idInstructorSesion) {
            return (int) ($b['id_instructor'] ?? 0) === $idInstructorSesion;
        }));

        // Caso A: El aprendiz no tiene ningún bloque programado hoy con este instructor
        if (empty($bloquesMios)) {
            // Identificar si en este momento está en ventana de clase con OTRO instructor
            foreach ($bloques as $b) {
                $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $b['hora_entrada_programada']);
                $fin    = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $b['hora_salida_programada']);
                $ventanaInicio = (clone $inicio)->modify('-' . self::ANTICIPACION_PERMITIDA_MIN . ' minutes');

                if ($ahora >= $ventanaInicio && $ahora <= $fin) {
                    $instOtro = trim($b['instructor_nombre'] ?? 'otro instructor');
                    $horarioTxt = substr($b['hora_entrada_programada'], 0, 5) . ' - ' . substr($b['hora_salida_programada'], 0, 5);
                    return [
                        'success' => false,
                        'message' => "Marcaje no permitido: El bloque actual ({$horarioTxt}) corresponde al instructor {$instOtro}. Solo ese instructor puede registrar la asistencia.",
                    ];
                }
            }

            return [
                'success' => false,
                'message' => "El aprendiz {$nombreCompleto} no tiene clases programadas hoy con usted ({$nombreInstructorSesion}).",
            ];
        }

        // Caso B: El aprendiz SÍ tiene bloques con este instructor hoy.
        // 1) ¿Tiene alguna salida pendiente en alguno de mis bloques?
        foreach ($bloquesMios as $bloque) {
            $idHorario  = (int) $bloque['id_horario'];
            $asistencia = $asistenciasHoy[$idHorario] ?? null;

            if ($asistencia && !empty($asistencia['hora_entrada_real']) && empty($asistencia['hora_salida_real'])) {
                $horaActual = $ahora->format('H:i:s');
                $modelo->registrarSalida((int) $asistencia['id_asistencia'], $horaActual);

                return [
                    'success' => true,
                    'tipo'    => 'salida',
                    'nombre'  => $nombreCompleto,
                    'ficha'   => $fichaLabel,
                    'hora'    => $ahora->format('g:i a'),
                    'message' => "Salida registrada para {$nombreCompleto}.",
                ];
            }
        }

        // 2) Buscar si hay un bloque con este instructor listo para registrar ENTRADA
        foreach ($bloquesMios as $bloque) {
            $idHorario        = (int) $bloque['id_horario'];
            $asistenciaPrevia = $asistenciasHoy[$idHorario] ?? null;

            // Si ya completó entrada y salida en este bloque, continuar
            if ($asistenciaPrevia && !empty($asistenciaPrevia['hora_entrada_real']) && !empty($asistenciaPrevia['hora_salida_real'])) {
                continue;
            }

            $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $bloque['hora_entrada_programada']);
            $fin    = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $bloque['hora_salida_programada']);

            $ventanaInicio = (clone $inicio)->modify('-' . self::ANTICIPACION_PERMITIDA_MIN . ' minutes');

            if ($ahora < $ventanaInicio) {
                // Aún no llega la hora de anticipación para este bloque con este instructor
                continue;
            }

            if ($ahora > $fin) {
                // Este bloque con este instructor ya terminó su jornada
                continue;
            }

            // Ventana válida para marcar entrada en este bloque
            $minutosRetraso = $ahora > $inicio
                ? (int) (($ahora->getTimestamp() - $inicio->getTimestamp()) / 60)
                : 0;

            $estado = $minutosRetraso > self::TOLERANCIA_RETARDO_MIN ? 'retardo' : 'asistencia';
            $horaActual = $ahora->format('H:i:s');

            if ($asistenciaPrevia && empty($asistenciaPrevia['hora_entrada_real'])) {
                $modelo->actualizarInasistenciaARetardo((int) $asistenciaPrevia['id_asistencia'], $horaActual, $estado, $minutosRetraso);
            } else {
                $modelo->registrarEntrada($idAprendiz, $idHorario, $fechaHoy, $horaActual, $estado, $minutosRetraso);
            }

            $etiquetaEstado = $estado === 'retardo' ? "Retardo de {$minutosRetraso} min" : 'A tiempo';

            return [
                'success' => true,
                'tipo'    => 'entrada',
                'nombre'  => $nombreCompleto,
                'ficha'   => $fichaLabel,
                'hora'    => $ahora->format('g:i a'),
                'estado'  => $estado,
                'message' => "Entrada registrada para {$nombreCompleto} ({$etiquetaEstado}).",
            ];
        }

        // 3) Si no se pudo marcar en mis bloques:
        // ¿Está en horario de clase con OTRO instructor ahora mismo?
        foreach ($bloques as $b) {
            if ((int) ($b['id_instructor'] ?? 0) === $idInstructorSesion) {
                continue;
            }
            $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $b['hora_entrada_programada']);
            $fin    = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $b['hora_salida_programada']);
            $ventanaInicio = (clone $inicio)->modify('-' . self::ANTICIPACION_PERMITIDA_MIN . ' minutes');

            if ($ahora >= $ventanaInicio && $ahora <= $fin) {
                $instOtro = trim($b['instructor_nombre'] ?? 'otro instructor');
                $horarioTxt = substr($b['hora_entrada_programada'], 0, 5) . ' - ' . substr($b['hora_salida_programada'], 0, 5);
                return [
                    'success' => false,
                    'message' => "Marcaje no permitido: El bloque actual ({$horarioTxt}) corresponde al instructor {$instOtro}. Solo ese instructor puede registrar la asistencia.",
                ];
            }
        }

        // ¿El bloque con este instructor es más tarde hoy?
        foreach ($bloquesMios as $b) {
            $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $b['hora_entrada_programada']);
            $ventanaInicio = (clone $inicio)->modify('-' . self::ANTICIPACION_PERMITIDA_MIN . ' minutes');
            if ($ahora < $ventanaInicio) {
                $horaInicioStr = substr($b['hora_entrada_programada'], 0, 5);
                $horaVentana   = $ventanaInicio->format('H:i');
                return [
                    'success' => false,
                    'message' => "Su clase con usted inicia a las {$horaInicioStr}. El marcaje se habilitará a las {$horaVentana}.",
                ];
            }
        }

        // ¿Los bloques con este instructor ya completaron entrada y salida?
        $todosCompletados = true;
        foreach ($bloquesMios as $b) {
            $idH = (int) $b['id_horario'];
            $asist = $asistenciasHoy[$idH] ?? null;
            if (!$asist || empty($asist['hora_entrada_real']) || empty($asist['hora_salida_real'])) {
                $todosCompletados = false;
                break;
            }
        }
        if ($todosCompletados) {
            return [
                'success' => false,
                'message' => "El aprendiz {$nombreCompleto} ya completó su asistencia (entrada y salida) para sus clases de hoy.",
            ];
        }

        return [
            'success' => false,
            'message' => "El bloque de clase con usted ya finalizó.",
        ];
    }
}
