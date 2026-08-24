<?php
require_once '../models/mysql.php';
session_start();

$mysql = new MySQL();
$mysql->conectar();

if (
isset($_POST['documento'], $_POST['password']) &&
!empty($_POST['documento']) && !empty($_POST['password'])
) {

$documento = $_POST['documento'];
$password = $_POST['password'];

try {
// Consulta preparada para evitar inyección
$sql = "SELECT
 usuarios.id_usuario,
    usuarios.nombre_completo,
    usuarios.num_documento,
    usuarios.contrasena,
    usuarios.correo_electronico,
    usuarios.apellido,
    roles.nombre_role 
    FROM usuarios 
    JOIN roles ON roles.id_role = usuarios.id_role
    WHERE num_documento = :doc 
    AND estado = 'Activo'";
$resultado = $mysql->prepare($sql);
$resultado->bindParam(':doc', $documento, PDO::PARAM_STR);
$resultado->execute();

$usuario_data = $resultado->fetch(PDO::FETCH_ASSOC);



if ($usuario_data) {
// Verificar contraseña usando password_verify
if (password_verify($password, $usuario_data['contrasena'])) {
// Guardar datos en sesión
    $_SESSION["id"] = $usuario_data['id_usuario'];
    $_SESSION["nombre"] = $usuario_data['nombre_completo'];
    $_SESSION["apellidos"] = $usuario_data['apellido'];
    $_SESSION["Documento"] = $usuario_data['num_documento'];
    $_SESSION["Correo"] = $usuario_data['correo_electronico'];
    $_SESSION["rol"]    = $usuario_data['nombre_role'];
   // $_SESSION['login_time'] = time();

// Redirigir a dashboard
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
// Contraseña incorrecta
header("Location: ../index.php?error=2");
exit();
}
} else {
// Usuario no encontrado
header("Location: ../index.php?error=1");
exit();
}
} catch (PDOException $e) {
error_log("Error en login: " . $e->getMessage());
header("Location: ../index.php?error=500");
exit();
}
} else {
// Datos incompletos



header("Location: ../index.php?error=11");
exit();
}

$mysql->desconectar();
?>