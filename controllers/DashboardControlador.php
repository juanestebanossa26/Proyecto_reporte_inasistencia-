<?php
// Controlador para los paneles de control (Dashboards).
// Administra la obtención de información para las vistas de Administrador
// e Instructor a través de DashboardModelo.

declare(strict_types=1);

class DashboardControlador extends ControladorBase
{
    private DashboardModelo $modelo;

    public function __construct()
    {
        $this->modelo = new DashboardModelo();
    }

    /**
     * Punto de entrada principal para 'dashboard'.
     * Detecta el rol del usuario autenticado y delega a la vista correspondiente.
     */
    public function mostrar(): void
    {
        $this->exigirSesion(['Administrador', 'Instructor', 'Aprendiz']);

        $rol = strtolower(trim((string) ($_SESSION['rol'] ?? '')));

        if (in_array($rol, ['administrador', 'admin'], true)) {
            $this->mostrarAdmin();
            return;
        }

        if (in_array($rol, ['instructor'], true)) {
            $this->mostrarInstructor();
            return;
        }

        // Si es aprendiz, se le redirige a su historial de asistencias
        $this->redirigirAccion('historial');
    }

    /**
     * Muestra el Dashboard exclusivo para el Administrador.
     */
    public function mostrarAdmin(): void
    {
        $this->exigirSesion(['Administrador']);

        $fechaHoy       = date('Y-m-d');
        $paginaActiva   = 'dashboard';
        $tituloPagina   = 'Panel Principal - Administrador';
        $nombreUsuario  = $_SESSION['nombre'] ?? 'Administrador';
        $rolActivo      = $_SESSION['rol'] ?? 'Administrador';

        try {
            (new AsistenciaModelo())->sincronizarInasistenciasGlobal();
            $kpis              = $this->modelo->obtenerKpisAdmin($fechaHoy);
            $ingresosRecientes = $this->modelo->obtenerIngresosRecientesAdmin(10);
            $excusasPendientes = $this->modelo->obtenerExcusasRecientesAdmin(6);
            $resumenFichas     = $this->modelo->obtenerResumenFichasAdmin(6);
        } catch (PDOException $e) {
            error_log('Error al cargar datos del dashboard de administrador: ' . $e->getMessage());
            $kpis = [
                'total_aprendices'   => 0,
                'total_instructores' => 0,
                'total_fichas'       => 0,
                'horarios_hoy'       => 0,
                'asistencias_hoy'    => 0,
                'a_tiempo_hoy'       => 0,
                'retardos_hoy'       => 0,
                'inasistencias_hoy'  => 0,
                'excusas_pendientes' => 0,
            ];
            $ingresosRecientes = [];
            $excusasPendientes = [];
            $resumenFichas     = [];
        }

        require BASE_PATH . '/views/dashboard_admin.php';
    }

    /**
     * Muestra el Dashboard exclusivo para el Instructor.
     */
    public function mostrarInstructor(): void
    {
        $this->exigirSesion(['Instructor']);

        $idInstructor   = (int) ($_SESSION['id'] ?? 0);
        $fechaHoy       = date('Y-m-d');
        $paginaActiva   = 'dashboard';
        $tituloPagina   = 'Panel Principal - Instructor';
        $nombreUsuario  = $_SESSION['nombre'] ?? 'Instructor';
        $rolActivo      = $_SESSION['rol'] ?? 'Instructor';

        try {
            (new AsistenciaModelo())->sincronizarInasistenciasGlobal();
            $kpis              = $this->modelo->obtenerKpisInstructor($idInstructor, $fechaHoy);
            $horariosHoy       = $this->modelo->obtenerHorariosHoyInstructor($idInstructor, $fechaHoy);
            $ingresosRecientes = $this->modelo->obtenerIngresosRecientesInstructor($idInstructor, 10);
            $excusasPendientes = $this->modelo->obtenerExcusasPendientesInstructor($idInstructor, 6);
            $misFichas         = $this->modelo->obtenerMisFichas($idInstructor);
        } catch (PDOException $e) {
            error_log('Error al cargar datos del dashboard de instructor: ' . $e->getMessage());
            $kpis = [
                'total_fichas'       => 0,
                'total_aprendices'   => 0,
                'clases_hoy'         => 0,
                'asistencias_hoy'    => 0,
                'a_tiempo_hoy'       => 0,
                'retardos_hoy'       => 0,
                'inasistencias_hoy'  => 0,
                'excusas_pendientes' => 0,
            ];
            $horariosHoy       = [];
            $ingresosRecientes = [];
            $excusasPendientes = [];
            $misFichas         = [];
        }

        require BASE_PATH . '/views/dashboard_instructor.php';
    }
}
