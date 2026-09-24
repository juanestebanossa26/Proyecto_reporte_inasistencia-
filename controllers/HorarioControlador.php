<?php

class HorarioControlador extends ControladorBase
{
    private HorarioModelo $modelo;

    public function __construct()
    {
        $this->modelo = new HorarioModelo();
    }

    // accion=fichas -> arma los datos y entrega el control a la vista de calendario
    public function mostrar(): void
    {
        $this->exigirSesion(['Administrador', 'Instructor']);

        try {
            $horarios = $this->modelo->listarHorarios();
            $instructores = $this->modelo->listarInstructores();

            // Transformación a formato de eventos FullCalendar
            $eventosCalendario = [];
            foreach ($horarios as $h) {
                $eventosCalendario[] = [
                    'id'          => (string) $h['id_horario'],
                    'title'       => $h['numero_ficha'] . ' - ' . $h['nombre_programa'],
                    'start'       => $h['fecha'] . 'T' . substr($h['hora_entrada_programada'], 0, 5) . ':00',
                    'end'         => $h['fecha'] . 'T' . substr($h['hora_salida_programada'], 0, 5) . ':00',
                    'extendedProps' => [
                        'ficha'         => $h['numero_ficha'],
                        'programa'      => $h['nombre_programa'],
                        'instructor'    => trim($h['instructor_nombre']) !== '' ? $h['instructor_nombre'] : 'Sin asignar',
                        'id_instructor' => (int) ($h['id_instructor'] ?? 0),
                        'entrada'       => substr($h['hora_entrada_programada'], 0, 5),
                        'salida'        => substr($h['hora_salida_programada'], 0, 5),
                        'aprendices'    => (int) $h['total_aprendices'],
                        'fecha'         => $h['fecha']
                    ]
                ];
            }
        } catch (PDOException $e) {
            error_log('Error al listar horarios: ' . $e->getMessage());
            $eventosCalendario = [];
            $instructores = [];
            $_SESSION['mensaje_error'] = 'No se pudieron cargar los horarios. Intenta de nuevo.';
        }

        require BASE_PATH . '/views/fichas.php';
    }

    // accion=guardarHorario (POST)
    public function guardar(): void
    {
        // 1. Solo el Administrador puede registrar horarios
        $this->exigirSesion(['Administrador', 'admin']);

        // Recoger y limpiar campos del formulario
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
                $this->urlAccion('fichas')
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

            // Guardar el horario en la base de datos
            $this->modelo->guardarHorario($idFicha, $horaEntrada, $horaSalida, $fecha);

            Seguridad::alertaYRedirigir(
                'success',
                '¡Horario registrado!',
                'El horario fue guardado exitosamente en la base de datos.',
                $this->urlAccion('fichas')
            );
        } catch (PDOException $e) {
            error_log('Error al guardar horario: ' . $e->getMessage());
            Seguridad::alertaYRedirigir(
                'error',
                'Error de base de datos',
                'Ocurrió un error al guardar el horario. Intenta de nuevo.',
                $this->urlAccion('fichas')
            );
        }
    }
}
