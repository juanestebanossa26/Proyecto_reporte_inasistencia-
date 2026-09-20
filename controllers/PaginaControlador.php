<?php
// Controlador genérico para las páginas de solo lectura (dashboard,
// aprendices, fichas, historial, instructores, marcaje, perfil, reportes,
// excusas, validar_excusas).
//
// No tienen un Controlador propio porque no reciben ninguna acción de
// escritura por sí mismas (los formularios que sí escriben apuntan a
// AuthControlador / InstructorControlador / ExcusaControlador /
// ReporteControlador). Este Controlador solo exige sesión activa y
// entrega el control a la vista pedida.



class PaginaControlador extends ControladorBase
{
    public function mostrar(string $vista, array $rolesPermitidos = []): void
    {
        $this->exigirSesion($rolesPermitidos);

        require BASE_PATH . '/views/' . $vista . '.php';
    }
}
