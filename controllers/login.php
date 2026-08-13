<?php
session_start();
require_once("../models/mysql.php");

$db = new Mysql();
$db->conectar();

$documento = $_POST['documento'];
$password  = $_POST['password'];

$sql = "SELECT
    usuarios.nombre_completo,
    usuarios.num_documento,
    usuarios.contrasena,
    roles.nombre_role 
    FROM usuarios 
    JOIN roles ON roles.id_role = usuarios.id_role
    WHERE num_documento = :doc 
    AND estado = 'Activo'";

$resultado = $db->prepare($sql);
//este el el parametro que usamos para hacer la consulta
$resultado->execute([':doc' => $documento]);

//Obtenemos los datos del usuario de la base de datos
$fila = $resultado->fetch(PDO::FETCH_ASSOC);

//Validamos DIRECTAMENTE si la fila existe y la contraseña coincide
if ($fila && password_verify($password, $fila["contrasena"])) {

   
    $_SESSION["nombre"] = $fila['nombre_completo'];
    $_SESSION["rol"]    = $fila['nombre_role'];
    
   if($_SESSION["rol"]=="Administrador" ||$_SESSION["rol"]=="Instructor" )
    {
header("Location: ../views/dashboard.php");
    exit();


    }elseif($_SESSION["rol"]=="Aprendiz")
    {
header("Location: ../views/historial.php");
    exit();


    }
    

} else {

    
    header("Location: ../index.php");
    exit();

}
?>







$db->desconectar();


?>