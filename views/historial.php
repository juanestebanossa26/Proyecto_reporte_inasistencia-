<?php
session_start();

require_once("../models/mysql.php");
$db= new mysql();
$db->conectar();

// Se agregó LEFT JOIN en excusas_medicas para que traiga asistencias sin excusa
$sql = "SELECT asistencias.*,
       excusas_medicas.estado_excusa      
        FROM asistencias 
        JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz 
        LEFT JOIN excusas_medicas on excusas_medicas.id_asistencia=asistencias.id_asistencia
        WHERE aprendices.id_usuario = :id_usuario";

$resultado= $db->prepare($sql);
$resultado->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
$resultado->execute();

// consulta para hacer un contador de inasistencias
$sql = "SELECT COUNT(asistencias.id_asistencia) 
        FROM asistencias 
        JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz 
        WHERE aprendices.id_usuario = :id_usuario and estado='inasistencia'";

$conta_inasistencia= $db->prepare($sql);
$conta_inasistencia->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
$conta_inasistencia->execute();

// consulta para hacer un contador retardos 
$sql = "SELECT COUNT(asistencias.id_asistencia) 
        FROM asistencias 
        JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz 
        WHERE aprendices.id_usuario = :id_usuario and estado='retardo'";

$conta_retardo= $db->prepare($sql);
$conta_retardo->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
$conta_retardo->execute();
//este contador lo hice para contar las excusas pendientes por aprobar
$sql = "SELECT 
       COUNT(excusas_medicas.id_excusa)      
        FROM asistencias 
        JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz 
        JOIN excusas_medicas on excusas_medicas.id_asistencia=asistencias.id_asistencia
        WHERE aprendices.id_usuario = :id_usuario and estado_excusa='Pendiente'";

$contador_pendiente= $db->prepare($sql);
$contador_pendiente->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
$contador_pendiente->execute();



$rolActivo     = 'aprendiz';
$nombreUsuario = ''; 
$paginaActiva  = 'historial';
$tituloPagina  = 'Mi historial de asistencia';

$etiquetas = [
    'a-tiempo' => 'A tiempo', 'retardo' => 'Retardo',
    'inasistencia' => 'Inasistencia', 'salida-anticipada' => 'Salida anticipada',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include '../includes/head.php'; ?>
 <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"> 
</head>
<body>
<div class="app-shell">
    <?php include '../includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include '../includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2">
                <div>
                    <div class="eyebrow">Mi asistencia</div>
                    <h1 class="h4 fw-bold mb-0">Mi historial</h1>
                    <p class="text-suave mb-0">Consulta tus retardos, inasistencias y el estado de tus excusas.</p>
                </div>
                <button class="btn btn-outline-sena"><i class="bi bi-download me-1"></i> Descargar mi reporte</button>
            </div>

            <div class="row g-3 mb-3 justify-content-center">
                <div class="col-6 col-md-3">
                    <?php $total_retardo = $conta_retardo->fetchColumn(); ?>
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Retardos</div>
                        <div class="h4 fw-bold mb-0" style="color:#8a6300;"><?php echo $total_retardo; ?></div>
                    </div>
                </div>
                
                <div class="col-6 col-md-3">
                    <?php $total_inasistencia = $conta_inasistencia->fetchColumn(); ?>
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Inasistencias</div>
                        <div class="h4 fw-bold mb-0" style="color:#B4212B;"><?php echo $total_inasistencia; ?></div>
                    </div>
                </div>
                
                <div class="col-6 col-md-3">
                    <?php $total_pendientes = $contador_pendiente->fetchColumn(); ?>
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Excusas Pendientes</div>
                        <div class="h4 fw-bold mb-0" style="color:#2F6FED;"><?php echo $total_pendientes; ?></div>
                    </div>
                </div>
            </div>

            <div class="card p-3">
                <div class="table-responsive">
                    <table id="tablaHistorial" class="table table-app align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th class="text-center">Entrada</th>
                                <th class="text-center">Salida</th>
                                <th>Estado</th>
                                <th>Estado Excusa</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($fila = $resultado->fetch()) {  
                            
                            // Lógica para verificar si la excusa está vacía
                            $estadoExcusa = $fila['estado_excusa'];
                            
                            if (empty($estadoExcusa)) {
                                $textoExcusa = "No hay excusa subida";
                                $claseExcusa = "badge bg-secondary text-white"; 
                            } else {
                                $textoExcusa = ucfirst($estadoExcusa); 
                                $claseExcusa = "badge-estado badge-" . strtolower($estadoExcusa);
                            }

                            // NUEVO CAMBIO AQUÍ: Validamos si la hora de entrada y salida están vacías
                            $horaEntrada = !empty($fila['hora_entrada_real']) ? htmlspecialchars($fila['hora_entrada_real']) : '<span class="text-muted fw-bold fs-5">-</span>';
                            $horaSalida  = !empty($fila['hora_salida_real']) ? htmlspecialchars($fila['hora_salida_real']) : '<span class="text-muted fw-bold fs-5">-</span>';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($fila['fecha']) ?></td>
                                <!-- Imprimimos las horas o el guion centrado -->
                                <td class="text-center"><?= $horaEntrada ?></td>
                                <td class="text-center"><?= $horaSalida ?></td>
                                <td><span class="badge-estado badge-<?= $fila['estado'] ?>"><?= $etiquetas[$fila['estado']] ?? $fila['estado'] ?></span></td>
                                <td><span class="<?= $claseExcusa ?>"><?= $textoExcusa ?></span></td>
                            </tr>
                        <?php }; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/scripts.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script> 
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script> 
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script> 

<script>
$(document).ready(function() {
    $('#tablaHistorial').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        order: [],
        pageLength: 10,
        responsive: true
    });
});
</script>
</body>
</html>