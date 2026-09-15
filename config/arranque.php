<?php
// Arranque común del proyecto.
// Cada script real que recibe peticiones (index.php, los archivos dentro
// de rutas/, y las vistas que consultan la base de datos) empieza
// incluyendo este archivo antes de hacer cualquier otra cosa. Aquí se
// define la sesión y se cargan los Modelos, Controladores y Helpers.



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Conexion.php';   

// Helpers (funciones de apoyo que no pertenecen a un Modelo ni a un
// Controlador concreto).
require_once __DIR__ . '/../helpers/Seguridad.php';
// Modelos (acceso a datos, sin HTML ni encabezados HTTP).
require_once __DIR__ . '/../modelos/UsuarioModelo.php';
require_once __DIR__ . '/../modelos/AsistenciaModelo.php';
require_once __DIR__ . '/../modelos/ExcusaModelo.php';
require_once __DIR__ . '/../modelos/ReporteModelo.php';

// Controladores (orquestan Modelo + Vista, sin SQL ni HTML propio).
require_once __DIR__ . '/../controladores/ControladorBase.php';
require_once __DIR__ . '/../controladores/AuthControlador.php';
require_once __DIR__ . '/../controladores/InstructorControlador.php';
require_once __DIR__ . '/../controladores/ExcusaControlador.php';
require_once __DIR__ . '/../controladores/ReporteControlador.php';

// Cualquier excepción que ningún Controlador haya capturado llega hasta
// aquí. El detalle técnico se registra en el servidor y el usuario final
// solo ve un mensaje genérico, salvo en modo de depuración.
set_exception_handler(function (Throwable $e): void {
    error_log('Excepción no controlada: ' . $e->getMessage());

    http_response_code(500);

    if (MODO_DEBUG) {
        echo 'Error no controlado: ' . htmlspecialchars($e->getMessage());
        return;
    }

    echo 'Ocurrió un error inesperado. Intenta de nuevo más tarde.';
});
