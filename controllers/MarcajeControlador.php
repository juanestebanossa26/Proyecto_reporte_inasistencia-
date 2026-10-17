<?php
// Controlador de marcaje RFID.

class MarcajeControlador extends ControladorBase
{
    private const TOLERANCIA_RETARDO_MIN   = 10; // minutos de gracia antes de contar como retardo
    private const ANTICIPACION_PERMITIDA_MIN = 30; // minutos antes del inicio en que ya se puede marcar

    // accion=marcaje -> muestra el lector + los últimos marcajes de hoy.
    public function mostrar(): void
    {
        $this->exigirSesion(['Instructor', 'Administrador']);

        $hoy = date('Y-m-d');

        // Un instructor solo ve los marcajes de sus propias fichas; el
        // administrador los ve todos.
        $idFichaFiltro = null;
        if ($_SESSION['rol'] === 'Instructor') {
            // El filtro real por instructor se resuelve dentro del modelo
            // vía join; aquí solo se usa cuando aplica una sola ficha.
            $idFichaFiltro = null;
        }

        try {
            $marcajeModelo  = new MarcajeModelo();
            $ultimosMarcajes = $marcajeModelo->listarMarcajesDeHoy($hoy, $idFichaFiltro, 15);
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
        $this->exigirSesion(['Instructor']);
        header('Content-Type: application/json; charset=utf-8');

        if (!Seguridad::validarCsrf($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['success' => false, 'message' => 'Tu sesión expiró, recarga la página.']);
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

            $resultado = $this->decidirYRegistrar($modelo, $aprendiz, $bloques, $asistenciasHoy, $ahora, $fechaHoy);

            echo json_encode($resultado);
        } catch (PDOException $e) {
            error_log('Error al registrar marcaje: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al registrar el marcaje. Intenta de nuevo.']);
        }
    }

    // Recorre los bloques del día en orden y decide qué hacer con la
    // lectura actual: completar una salida pendiente, o abrir la entrada
    // del bloque que corresponda según la hora actual.
    private function decidirYRegistrar(
        MarcajeModelo $modelo,
        array $aprendiz,
        array $bloques,
        array $asistenciasHoy,
        DateTime $ahora,
        string $fechaHoy
    ): array {
        $idAprendiz = (int) $aprendiz['id_aprendiz'];
        $nombreCompleto = trim($aprendiz['nombre_completo'] ?? '');
        $fichaLabel = $aprendiz['numero_ficha'] . ' · ' . $aprendiz['nombre_programa'];

        // 1) ¿Hay algún bloque con entrada marcada y salida todavía vacía?
        //    Esta lectura se interpreta como la SALIDA de ese bloque.
        foreach ($bloques as $bloque) {
            $idHorario = (int) $bloque['id_horario'];
            $asistencia = $asistenciasHoy[$idHorario] ?? null;

            if ($asistencia && $asistencia['hora_entrada_real'] && !$asistencia['hora_salida_real']) {
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

        // 2) No hay salida pendiente: buscar el bloque al que le toca
        //    ENTRADA (el primero, en orden, que todavía no tiene fila Y
        //    cuya ventana de tiempo (con tolerancia) incluye "ahora").
        foreach ($bloques as $bloque) {
            $idHorario = (int) $bloque['id_horario'];

            // Este bloque ya se completó (tiene entrada Y salida) -> pasar al siguiente.
            if (isset($asistenciasHoy[$idHorario])) {
                continue;
            }

            $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $bloque['hora_entrada_programada']);
            $fin    = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $bloque['hora_salida_programada']);

            $ventanaInicio = (clone $inicio)->modify('-' . self::ANTICIPACION_PERMITIDA_MIN . ' minutes');

            if ($ahora < $ventanaInicio) {
                // Todavía falta mucho para este bloque; seguir buscando
                // (por si hay otro bloque más próximo, aunque lo normal
                // es que estén en orden cronológico).
                continue;
            }

            if ($ahora > $fin) {
                // Este bloque ya cerró sin que el aprendiz marcara
                // entrada -> se considera que lo perdió, pasar al
                // siguiente bloque del día (si lo hay).
                continue;
            }

            // Este es el bloque correcto para marcar entrada ahora.
            $minutosRetraso = $ahora > $inicio
                ? (int) (($ahora->getTimestamp() - $inicio->getTimestamp()) / 60)
                : 0;

            $estado = $minutosRetraso > self::TOLERANCIA_RETARDO_MIN ? 'retardo' : 'asistencia';
            $horaActual = $ahora->format('H:i:s');

            $modelo->registrarEntrada($idAprendiz, $idHorario, $fechaHoy, $horaActual, $estado, $minutosRetraso);

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

        // 3) No hay ningún bloque activo ahora mismo (todos completados,
        //    o fuera de horario para todos).
        return [
            'success' => false,
            'message' => 'No hay ningún bloque de clase activo para marcar en este momento.',
        ];
    }
}
