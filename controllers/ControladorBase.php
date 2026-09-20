<?php
// Controlador base: agrupa comportamiento común a todos los controladores,
// como la protección de rutas por sesión y las redirecciones.
//
// Ya no se redirige a archivos físicos (vistas/dashboard.php); ahora
// todo pasa por el enrutador único (index.php?accion=...), definido por
// la constante BASE_URL calculada en index.php.

abstract class ControladorBase
{
    protected function redirigir(string $ruta): void
    {
        header('Location: ' . $ruta);
        exit;
    }

    // Arma la URL completa de una acción, ej: urlAccion('dashboard')
    // -> "http://localhost/proyecto_asistencias/index.php?accion=dashboard"
    protected function urlAccion(string $accion, array $parametros = []): string
    {
        $query = array_merge(['accion' => $accion], $parametros);
        return BASE_URL . '/index.php?' . http_build_query($query);
    }

    protected function redirigirAccion(string $accion, array $parametros = []): void
    {
        $this->redirigir($this->urlAccion($accion, $parametros));
    }

    // Toda página que requiera un usuario autenticado llama este método
    // como primera línea. $rolesPermitidos vacío = cualquier usuario logueado.
    protected function exigirSesion(array $rolesPermitidos = []): void
    {
        if (!isset($_SESSION['Documento'])) {
            $this->redirigirAccion('login');
        }

        if ($rolesPermitidos && !in_array($_SESSION['rol'] ?? '', $rolesPermitidos, true)) {
            $this->redirigirAccion('login');
        }

        // Le decimos al navegador que NUNCA guarde una copia de esta página.
    // Sin esto, después de "Cerrar sesión" el botón "atrás" puede mostrar
    // la última versión guardada en caché, sin volver a preguntarle nada
    // al servidor.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    }

    // Valida el token CSRF de una petición POST. Si no es válido, se
    // interrumpe la acción antes de tocar cualquier Modelo.
    protected function exigirCsrfValido(string $accionSiFalla, array $parametros = []): void
    {
        if (!Seguridad::validarCsrf($_POST['csrf_token'] ?? null)) {
            $this->redirigirAccion($accionSiFalla, array_merge($parametros, ['error' => 'token']));
        }
    }
}

