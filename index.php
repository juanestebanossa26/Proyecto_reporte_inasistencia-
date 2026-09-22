<?php
// Punto de entrada único de la aplicación.
// Configura la sesión de forma segura, carga las dependencias y enruta
// la petición hacia el Controlador correspondiente, según ?accion=...

declare(strict_types=1);

define('BASE_PATH', __DIR__);

// URL base real del proyecto, calculada a partir del propio script.
// Si el proyecto vive en http://localhost/proyecto_asistencias/, esto
// vale "/proyecto_asistencias". Si vive en la raíz del servidor, vale "".
// Se usa para que los enlaces, formularios y assets funcionen sin importar
// en qué subcarpeta esté instalado el proyecto.
define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'));

// Cuando el proyecto se ejecuta con el servidor interno de PHP
// (php -S localhost:8000), TODAS las peticiones pasan por este archivo,
// incluidas las de CSS, JS o imágenes. Sin este bloque, el navegador nunca
// recibiría esos archivos: siempre recibiría el HTML de la acción por
// defecto. Con Apache/XAMPP este bloque nunca se ejecuta y no cambia nada.
if (PHP_SAPI === 'cli-server') {
    $rutaSolicitada = BASE_PATH . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Solo se delega al servidor embebido si el archivo existe Y no es un
    // .php — un .php siempre debe pasar por aquí (el enrutador), nunca
    // ejecutarse aparte. Sin este segundo chequeo, pedir /index.php
    // directamente lo ejecutaría dos veces en la misma petición.
    if (is_file($rutaSolicitada) && pathinfo($rutaSolicitada, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}

require_once BASE_PATH . '/config/config.php';

// La cookie de sesión se configura ANTES de session_start(), o estas
// opciones no tendrían efecto.
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    // secure solo se activa si la conexión ya es HTTPS, para no bloquear
    // el uso en un entorno de práctica local sin certificado.
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

require_once BASE_PATH . '/config/conexion.php';
require_once BASE_PATH . '/helpers/Seguridad.php';

// Modelos (acceso a datos, sin HTML ni encabezados HTTP).
require_once BASE_PATH . '/models/UsuarioModelo.php';
require_once BASE_PATH . '/models/AsistenciaModelo.php';
require_once BASE_PATH . '/models/ExcusaModelo.php';
require_once BASE_PATH . '/models/ReporteModelo.php';
require_once BASE_PATH . '/models/AprendicesModelo.php';
//controladores
// Controladores
require_once BASE_PATH . '/controllers/ControladorBase.php'; 
require_once BASE_PATH . '/controllers/AuthControlador.php';
require_once BASE_PATH . '/controllers/PaginaControlador.php';

require_once BASE_PATH . '/controllers/HistorialControlador.php'; 

require_once BASE_PATH . '/controllers/aprendizControlador.php';

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

$accion = $_GET['accion'] ?? 'login';

$auth        = new AuthControlador();
$pagina      = new PaginaControlador();

$historial   = new HistorialControlador();

$aprendiz    = new AprendizControlador();



match ($accion) {
    // --- Autenticación ---
    'login'      => $auth->mostrarLogin(),
    'autenticar' => $auth->login(),
    'logout'     => $auth->logout(),
    'guardarAprendiz' => $aprendiz-> guardar(),

    // --- Páginas de solo lectura CON datos: el Controlador arma las
    //     variables y se las pasa listas a la vista (ya no consultan la
    //     base de datos por su cuenta) ---
  'historial'      => $historial->mostrar(),




    // --- Páginas de solo lectura (protegidas por sesión Y rol) ---
    'dashboard'       => $pagina->mostrar('dashboard', ['Aprendiz', 'Instructor', 'Administrador']),
    'aprendices'      => $pagina->mostrar('aprendices', ['Instructor', 'Administrador']),
    'fichas'          => $pagina->mostrar('fichas', ['Instructor', 'Administrador']),
    'historial'       => $pagina->mostrar('historial', ['Aprendiz']),
    'instructores'    => $pagina->mostrar('instructores', ['Administrador']),
    'marcaje'         => $pagina->mostrar('marcaje', ['Instructor', 'Administrador']),
    'perfil'          => $pagina->mostrar('perfil', ['Aprendiz', 'Instructor', 'Administrador']),
    'reportes'        => $pagina->mostrar('reportes', ['Instructor', 'Administrador']),
    'excusas'         => $pagina->mostrar('excusas', ['Aprendiz']),
    'validarExcusas'  => $pagina->mostrar('validar_excusas', ['Instructor', 'Administrador']),

    default => $auth->mostrarLogin(),
};