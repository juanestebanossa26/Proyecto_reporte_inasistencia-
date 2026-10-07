<?php
// Funciones de apoyo reutilizadas por varios Controladores.
// Se agrupan aparte porque no pertenecen a un Modelo (no tocan la base
// de datos) ni a un Controlador específico.

class Seguridad
{
    // Genera el token CSRF de la sesión actual, o reutiliza el existente.
    // Debe incluirse como campo oculto en todo formulario que modifique datos
    // (echo Seguridad::tokenCsrf() dentro de un input hidden llamado csrf_token).
    public static function tokenCsrf(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    // Compara el token recibido en un formulario contra el de la sesión.
    // hash_equals evita filtrar información por diferencias de tiempo.
    public static function validarCsrf(?string $tokenRecibido): bool
    {
        if (empty($_SESSION['csrf_token']) || empty($tokenRecibido)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $tokenRecibido);
    }

    // Limpia un valor de texto proveniente de un formulario.
    public static function sanitizar(?string $valor): string
    {
        return filter_var(trim($valor ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    // El proyecto exige que los correos de instructores sean @gmail.com.
    public static function esCorreoGmailValido(?string $correo): bool
    {
        return $correo
            && filter_var($correo, FILTER_VALIDATE_EMAIL)
            && preg_match('/@gmail\.com$/i', $correo) === 1;
    }

    // Genera un nombre de archivo único para los soportes de excusas,
    // conservando la extensión original.
    public static function generarNombreArchivoUnico(string $nombreOriginal): string
    {
        $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
        return uniqid('pdf_', true) . '.' . $extension;
    }

    // Valida que un archivo subido sea realmente un PDF (por tipo MIME
    // real, no solo por la extensión que trae el nombre).
    public static function esPdfValido(string $rutaTemporal): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $rutaTemporal);
        finfo_close($finfo);

        return $mimeType === 'application/pdf';
    }

    // Las respuestas de los controladores de escritura (guardar/actualizar)
    // usan SweetAlert2 dentro de una página HTML mínima antes de redirigir.
    // Estas dos funciones evitan repetir el mismo bloque en cada archivo.
    public static function imprimirEncabezadoAnimacion(): void
    {
        echo '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
            <style>
                body { font-family: sans-serif; background-color: #f8f9fa; }
            </style>
        </head>
        <body>';
    }

    public static function imprimirCierreAnimacion(): void
    {
        echo '</body></html>';
    }

    // Muestra una alerta de SweetAlert2 y redirige al terminar. Reutilizado
    // por los controladores de instructores y excusas.
    public static function alertaYRedirigir(string $icono, string $titulo, string $texto, string $ruta): void
    {
        self::imprimirEncabezadoAnimacion();
        $iconoJs   = json_encode($icono);
        $tituloJs  = json_encode($titulo);
        $textoJs   = json_encode($texto);
        $rutaJs    = json_encode($ruta);
        echo "<script>
            Swal.fire({
                icon: {$iconoJs},
                title: {$tituloJs},
                text: {$textoJs},
                showClass: { popup: 'animate__animated animate__zoomInDown' },
                hideClass: { popup: 'animate__animated animate__fadeOutUp' },
                confirmButtonColor: '#39a900',
                confirmButtonText: 'Continuar'
            }).then(() => {
                window.location.href = {$rutaJs};
            });
        </script>";
        self::imprimirCierreAnimacion();
    }

    // Muestra una alerta de SweetAlert2 con contenido HTML y redirige al terminar.
    public static function alertaHtmlYRedirigir(string $icono, string $titulo, string $html, string $ruta): void
    {
        self::imprimirEncabezadoAnimacion();
        $iconoJs   = json_encode($icono);
        $tituloJs  = json_encode($titulo);
        $htmlJs    = json_encode($html);
        $rutaJs    = json_encode($ruta);
        echo "<script>
            Swal.fire({
                icon: {$iconoJs},
                title: {$tituloJs},
                html: {$htmlJs},
                showClass: { popup: 'animate__animated animate__zoomInDown' },
                hideClass: { popup: 'animate__animated animate__fadeOutUp' },
                confirmButtonColor: '#39a900',
                confirmButtonText: 'Continuar'
            }).then(() => {
                window.location.href = {$rutaJs};
            });
        </script>";
        self::imprimirCierreAnimacion();
    }
}
