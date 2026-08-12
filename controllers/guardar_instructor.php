<?php
session_start();
require_once("../models/mysql.php");

 $db=new mysql();
$db->conectar();


if($_SERVER['REQUEST_METHOD']==='POST')
{
if(isset($_POST['nombre']) && !empty(trim($_POST['nombre']))&& isset($_POST['documento']) && !empty(trim($_POST['documento'])) && isset($_POST['correo']) && !empty(trim($_POST['correo'])) && isset($_POST['password']) && !empty(trim($_POST['password'])) )
    {

if (filter_var($_POST['correo'], FILTER_VALIDATE_EMAIL) && preg_match('/@gmail\.com$/i',$_POST['correo'])) {
$nombre = filter_var($_POST['nombre'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$documento = filter_var($_POST['documento'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$correo = filter_var($_POST['correo'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$password = filter_var($_POST['password'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

} else {
     echo "<script>
            alert('ingrese una direccion de correo valida');
            window.location='../views/instructores.php';
          </script>";
}

}else
    {

 echo "<script>
            alert('Llene todos los campos ');
            window.location='../views/instructores.php';
          </script>";


    }

}else
{

echo"metodo de envio de datos es diferente ";

}

$sql = "SELECT id_usuario FROM usuarios WHERE num_documento = :documento";

// Accedes a ->conexion que creaste en el método conectar()
$stmt = $db->prepare($sql);
$stmt->execute([':documento' => $documento]);

if ($stmt->fetchColumn()) {
    echo "<script>
            alert('El usuario ya existe.');
            window.location='../views/instructores.php';
          </script>";

    $db->desconectar();
    exit();
}


$password=password_hash($password,PASSWORD_BCRYPT);

$sql="INSERT INTO usuarios (nombre_completo,num_documento,correo_electronico,contrasena,id_role) VALUES('$nombre','$documento','$correo','$password',2)";

if($db->efectuarconsulta($sql))
    {
    echo "<script>
            alert('Aprendiz registrado exitosamente');
     
          </script>";
          header("Location: ../views/instructores.php");
           exit(); 
    }
    else {

    echo "Error al registrar el usuario.";

    } 

$db->desconectar();

?>





?>