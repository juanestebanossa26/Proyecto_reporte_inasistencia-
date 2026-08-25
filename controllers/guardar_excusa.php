<?php
session_start();
require_once("../models/mysql.php");
$bd = new mysql();
$bd->conectar();

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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        isset($_POST['motivo']) && !empty($_POST['motivo']) &&
        isset($_POST['fecha_excusa']) && !empty($_POST['fecha_excusa']) &&
        isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $archivo = $_FILES['archivo']; 
        $motivo = $_POST['motivo'];
        $id_inasistencia = $_POST['fecha_excusa'];

        imprimirEncabezadoAnimacion();
        // Validar si hubo error en la subida
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Error al subir el archivo.']);
            exit;
        }

        // Validar tipo MIME real para asegurar que sea PDF
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        if ($mimeType !== 'application/pdf') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos PDF.']);
            exit;
        }

        // esto para definir a dodnde voy a guardar  localmente los archivos de las excusas
        $directorioDestino = __DIR__ . '/../assets/excusas/';


        // Generar nombre único
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombreUnico = uniqid('pdf_', true) . '.' . $extension;
        $rutaFisicaCompleta = $directorioDestino . $nombreUnico;
        $rutaRelativaBD = 'assets/excusas/' . $nombreUnico;

        // Mover archivo y registrar en BD
        if (move_uploaded_file($archivo['tmp_name'], $rutaFisicaCompleta)) {
            $sql = "INSERT INTO excusas_medicas (id_asistencia, archivo_adjunto, motivo) VALUES (:id, :ruta, :motivo)";

            try {
                $stmt = $bd->prepare($sql);
                $stmt->bindParam(':ruta', $rutaRelativaBD);
                $stmt->bindParam(':motivo', $motivo);
                $stmt->bindParam(':id', $id_inasistencia);
                
               
                $stmt->execute();

            echo "
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: '¡Excusa Radicada Con Éxito!',
                        text: 'El soporte ha sido guardado correctamente.',
                        showClass: {
                            popup: 'animate__animated animate__zoomInDown'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__fadeOutUp'
                        },
                        confirmButtonColor: '#39a900',
                        confirmButtonText: 'Continuar'
                    }).then(() => {
                        window.location.href = '../views/excusas.php'; 
                    });
                </script>";
                imprimirCierreAnimacion();

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al insertar: ' . $e->getMessage()]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al mover el archivo al servidor.']);
        }
    } else {
     imprimirEncabezadoAnimacion();
        echo "
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Campos incompletos',
                text: 'Por favor completa todos los campos requeridos.',
                confirmButtonColor: '#ffc107'
            }).then(() => { window.history.back(); });
        </script>";
        imprimirCierreAnimacion();
    }
}

$bd->desconectar();