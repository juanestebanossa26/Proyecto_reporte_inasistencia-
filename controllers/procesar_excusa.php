<?php
session_start();
require_once('../models/mysql.php');
$bd=new mysql();
$bd->conectar();




if($_SERVER['REQUEST_METHOD']==='POST' )
    {
        if( isset($_POST['id_excusa']) && !empty($_POST['id_excusa']) )
            {


        $accion=$_POST['accion'];
        $id_excusa=$_POST['id_excusa'];
          
        
        if($accion=='aprobar')
            {
                // con estas dos consultas al aprobar lo que hago es que aprte de aprobar la excusa tambien cambio el estado de aquella inasistencia cuya excusa es aprobada 
               $sql="UPDATE asistencias  set estado='asistencia' where id_asistencia=:id";
               $resultado=$bd->prepare($sql);
               $resultado->bindParam(':id', $id_excusa, PDO::PARAM_INT);
               $resultado->execute();

                  $sql="UPDATE excusas_medicas  set estado_excusa='aprobada' where id_asistencia=:id";
               $resultado=$bd->prepare($sql);
               $resultado->bindParam(':id', $id_excusa, PDO::PARAM_INT);
               $resultado->execute();

                      header("Location: ../views/validar_excusas.php");
                      exit(); 

            }elseif($accion=='rechazar')
            {
               

                  $sql="UPDATE excusas_medicas  set estado_excusa='rechazada' where id_asistencia=:id";
               $resultado=$bd->prepare($sql);
               $resultado->bindParam(':id', $id_excusa, PDO::PARAM_INT);
               $resultado->execute();

                      header("Location: ../views/validar_excusas.php");
                      exit(); 
            }



            }else
            {

  header("Location: ../views/validar_excusas.php?error=11");
      exit();



            }
        



    }else
    {
     header("Location: ../views/validar_excusas.php?error=11");
      exit();

    }




$bd->desconectar();
?>