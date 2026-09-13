<?php
session_start();
require_once __DIR__ . "/../config/mysql.php";
$db = new mysql();
$db->conectar();

function imprimirEncabezadoAnimacion() {
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

function imprimirCierreAnimacion() {
    echo '</body></html>';
}

if($_SERVER['REQUEST_METHOD']==='POST')
{
if(isset($_POST['id_usuario']) && !empty(trim($_POST['id_usuario']))&&  isset($_POST['nombre']) && !empty(trim($_POST['nombre']))&& isset($_POST['apellido']) && !empty(trim($_POST['apellido'])) && isset($_POST['documento']) && !empty(trim($_POST['documento'])) && isset($_POST['correo']) && !empty(trim($_POST['correo']))  )
    {

if (filter_var($_POST['correo'], FILTER_VALIDATE_EMAIL) && preg_match('/@gmail\.com$/i',$_POST['correo'])) {
$nombres   = filter_var(trim($_POST['nombre']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $id_usuario = filter_var($_POST['id_usuario'], FILTER_SANITIZE_NUMBER_INT);
            $apellidos = filter_var(trim($_POST['apellido']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $documento = filter_var(trim($_POST['documento']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $correo    = filter_var(trim($_POST['correo']), FILTER_SANITIZE_EMAIL);
            $password  = trim($_POST['password'] ?? '');

            // 1. Validar que el documento no pertenezca a OTRO usuario
            $sql = "SELECT id_usuario FROM usuarios WHERE num_documento = :doc AND id_usuario != :id LIMIT 1";
            $resultado = $db->prepare($sql);
            $resultado->execute([':doc' => $documento, ':id' => $id_usuario]);

            if ($resultado->fetchColumn()) {
                $db->desconectar();
                imprimirEncabezadoAnimacion();
                echo "<script>
                    Swal.fire({
                        icon: 'warning',
                        title: 'Documento en uso',
                        text: 'El número de documento ya está asignado a otro usuario.',
                        confirmButtonColor: '#39a900'
                    }).then(() => {
                        window.location.href = '../views/instructores.php';
                    });
                </script>";
                imprimirCierreAnimacion();
                exit();
            }

            // 2. Validar que el correo no pertenezca a OTRO usuario
            $sql = "SELECT id_usuario FROM usuarios WHERE correo_electronico = :correo AND id_usuario != :id LIMIT 1";
            $resultado = $db->prepare($sql);
            $resultado->execute([':correo' => $correo, ':id' => $id_usuario]);

            if ($resultado->fetchColumn()) {
                $db->desconectar();
                imprimirEncabezadoAnimacion();
                echo "<script>
                    Swal.fire({
                        icon: 'warning',
                        title: 'Correo en uso',
                        text: 'El correo electrónico ya está asignado a otro usuario.',
                        confirmButtonColor: '#39a900'
                    }).then(() => {
                        window.location.href = '../views/instructores.php';
                    });
                </script>";
                imprimirCierreAnimacion();
                exit();
            }

            try {
                if (!empty($password)) {
                    // Si el usuario ingresó una nueva contraseña
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $sql = "UPDATE usuarios 
                            SET nombre_completo = :primer_nombre, 
                                apellido        = :apellido, 
                                num_documento   = :doc, 
                                correo_electronico = :correo, 
                                contrasena      = :pass 
                            WHERE id_usuario    = :id";

                    $resultado = $db->prepare($sql);
                    $resultado->bindParam(':primer_nombre', $nombres);
                    $resultado->bindParam(':apellido', $apellidos);
                    $resultado->bindParam(':doc', $documento);
                    $resultado->bindParam(':correo', $correo);
                    $resultado->bindParam(':pass', $passwordHash);
                    $resultado->bindParam(':id', $id_usuario, PDO::PARAM_INT);
                } else {
                    // Si dejó el campo de contraseña vacío se mantiene la actual
                    $sql = "UPDATE usuarios 
                            SET nombre_completo = :primer_nombre, 
                                apellido        = :apellido, 
                                num_documento   = :doc, 
                                correo_electronico = :correo 
                            WHERE id_usuario    = :id";

                    $resultado = $db->prepare($sql);
                    $resultado->bindParam(':primer_nombre', $nombres);
                    $resultado->bindParam(':apellido', $apellidos);
                    $resultado->bindParam(':doc', $documento);
                    $resultado->bindParam(':correo', $correo);
                    $resultado->bindParam(':id', $id_usuario, PDO::PARAM_INT);
                }

                $resultado->execute();
                $db->desconectar();

                imprimirEncabezadoAnimacion();
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: '¡Actualizado Con Éxito!',
                        text: 'Los datos del instructor han sido actualizados.',
                        showClass: {
                            popup: 'animate__animated animate__zoomInDown'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__fadeOutUp'
                        },
                        confirmButtonColor: '#39a900',
                        confirmButtonText: 'Continuar'
                    }).then(() => {
                        window.location.href = '../views/instructores.php';
                    });
                </script>";
                imprimirCierreAnimacion();
                exit();

            } catch (PDOException $e) {
                $db->desconectar();
                imprimirEncabezadoAnimacion();
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: '¡Error al actualizar!',
                        text: 'No se pudo guardar la información.',
                        confirmButtonColor: '#d33',
                        confirmButtonText: 'Regresar'
                    }).then(() => {
                        window.location.href = '../views/instructores.php';
                    });
                </script>";
                imprimirCierreAnimacion();
                exit();
            }

        } else {
            $db->desconectar();
            imprimirEncabezadoAnimacion();
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Correo inválido',
                    text: 'Ingrese una dirección de correo Gmail válida.',
                    confirmButtonColor: '#d33'
                }).then(() => {
                    window.location.href = '../views/instructores.php';
                });
            </script>";
            imprimirCierreAnimacion();
            exit();
        }

    } else {
        $db->desconectar();
        imprimirEncabezadoAnimacion();
        echo "<script>
            Swal.fire({
                icon: 'warning',
                title: 'Campos incompletos',
                text: 'Por favor complete todos los campos obligatorios.',
                confirmButtonColor: '#39a900'
            }).then(() => {
                window.location.href = '../views/instructores.php';
            });
        </script>";
        imprimirCierreAnimacion();
        exit();
    }

} else {
    $db->desconectar();
    echo "Método de envío no permitido.";
}
?>