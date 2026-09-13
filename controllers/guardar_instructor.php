<?php
session_start();
require_once __DIR__."../config/mysql.php";

 $db=new mysql();
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
if(isset($_POST['nombre']) && !empty(trim($_POST['nombre']))&& isset($_POST['apellido']) && !empty(trim($_POST['apellido'])) && isset($_POST['documento']) && !empty(trim($_POST['documento'])) && isset($_POST['correo']) && !empty(trim($_POST['correo'])) && isset($_POST['password']) && !empty(trim($_POST['password'])) )
    {

if (filter_var($_POST['correo'], FILTER_VALIDATE_EMAIL) && preg_match('/@gmail\.com$/i',$_POST['correo'])) {
$nombres = filter_var($_POST['nombre'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$apellidos = filter_var($_POST['apellido'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$documento = filter_var($_POST['documento'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$correo = filter_var($_POST['correo'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$password = filter_var($_POST['password'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);



$sql = "SELECT id_usuario FROM usuarios WHERE num_documento = :doc";

// Accedes a ->conexion que creaste en el método conectar()
$resultado= $db->prepare($sql);
$resultado->execute([':doc' => $documento]);

if ($resultado->fetchColumn()) {
    echo "<script>
            alert('El usuario ya existe.');
            window.location='../views/instructores.php';
          </script>";

    $db->desconectar();
    exit();
}
$sql = "SELECT id_usuario FROM usuarios WHERE correo_electronico = :correo";


$resultado= $db->prepare($sql);
$resultado->execute([':correo' => $correo]);

if ($resultado->fetchColumn()) {
    echo "<script>
            alert('El correo ya existe ');
            window.location='../views/instructores.php';
          </script>";

    $db->desconectar();
    exit();
}

$password=password_hash($password,PASSWORD_BCRYPT);

try{
$sql="INSERT INTO usuarios (nombre_completo,apellido,num_documento,correo_electronico,estado,contrasena,id_role) VALUES(:primer_nombre,:apellido,:doc,:correo,'Activo',:pass,2)";
$resultado=$db->prepare($sql);
$resultado->bindParam(':primer_nombre',$nombres);
$resultado->bindParam(':apellido',$apellidos);
$resultado->bindParam(':doc',$documento);
$resultado->bindParam(':correo',$correo);
$resultado->bindParam(':pass',$password);
$resultado->execute();

$db->desconectar();
imprimirEncabezadoAnimacion();
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: '¡Registrado Con Éxito!',
                        text: 'El Instructor ha sido registrado correctamente.',
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

}catch(PDOException $e)
{
$db->desconectar();
imprimirEncabezadoAnimacion();
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: '¡No se pudo realizar el registro!',
                        text: 'Hubo un error al procesar la solicitud.',
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
     echo "<script>
            alert('ingrese una direccion de correo valida');
            window.location='../views/instructores.php';
          </script>";
}

}else
    {
$db->desconectar();
 echo "<script>
            alert('Llene todos los campos ');
            window.location='../views/instructores.php';
          </script>";


    }

}else
{
$db->desconectar();
echo"metodo de envio de datos es diferente ";

}


$db->desconectar();

?>





