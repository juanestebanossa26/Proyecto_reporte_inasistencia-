<?php
// Controlador del historial de asistencia (vista del aprendiz).
// El Modelo nunca falla "en silencio": si la consulta truena, se registra
// el error real en el log del servidor y la vista recibe datos vacíos
// más un mensaje genérico, en vez de que la página se rompa.

class HistorialControlador extends ControladorBase
{
    public function mostrar(): void
    {
        $this->exigirSesion(['Aprendiz']);

        try {
            $asistenciaModelo   = new AsistenciaModelo();
            $historial          = $asistenciaModelo->listarPorAprendiz((int) $_SESSION['id']);
            $total_retardo      = $asistenciaModelo->contarPorEstado((int) $_SESSION['id'], 'retardo');
            $total_inasistencia = $asistenciaModelo->contarPorEstado((int) $_SESSION['id'], 'inasistencia');
            $total_pendientes   = $asistenciaModelo->contarExcusasPendientes((int) $_SESSION['id']);
        } catch (PDOException $e) {
            error_log('Error al listar historial: ' . $e->getMessage());
            $historial          = [];
            $total_retardo      = 0;
            $total_inasistencia = 0;
            $total_pendientes   = 0;
            $_SESSION['mensaje_error'] = 'No se pudo cargar tu historial. Intenta de nuevo.';
        }

        require BASE_PATH . '/views/historial.php';
    }
}
