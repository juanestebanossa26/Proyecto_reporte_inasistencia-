<?php
session_start();

require_once("../models/mysql.php");
$db= new mysql();
$db->conectar();

//consulta para ver el historial de insistencias 
$sql = "SELECT asistencias.* 
        FROM asistencias 
        JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz 
        WHERE aprendices.id_usuario = :id_usuario";

$resultado= $db->prepare($sql);
$resultado->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
$resultado->execute();
// esta consulta la hice para hacer un contador de de inasistencias
$sql = "SELECT COUNT(asistencias.id_asistencia) 
        FROM asistencias 
        JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz 
        WHERE aprendices.id_usuario = :id_usuario and estado='inasistencia'";

$conta_inasistencia= $db->prepare($sql);
$conta_inasistencia->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
$conta_inasistencia->execute();

//esta consulta es para hacer un contador  retardos 

$sql = "SELECT COUNT(asistencias.id_asistencia) 
        FROM asistencias 
        JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz 
        WHERE aprendices.id_usuario = :id_usuario and estado='retardo'";

$conta_retardo= $db->prepare($sql);
$conta_retardo->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
$conta_retardo->execute();


$rolActivo     = 'aprendiz';
$nombreUsuario;
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
<!-- Asegúrate de tener los estilos de DataTables en head.php, ej: -->
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
        <div class="card kpi-card p-3 text-center">
            <div class="text-suave" style="font-size:.76rem;">Excusas aprobadas</div>
        
            <div class="h4 fw-bold mb-0" style="color:#2F6FED;">1</div>
        </div>
    </div>

</div>

            <div class="card p-3">
                <!-- DataTables ya trae su propio buscador, así que puedes decidir si dejas este filtro por fechas o lo adaptas después -->
           
                
                <div class="table-responsive">
                    <!-- Se agregó el ID "tablaHistorial" a la tabla -->
                    <table id="tablaHistorial" class="table table-app align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Estado</th>
                          
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($fila=$resultado->fetch()){  ?>
                            <tr>
                                <td><?= htmlspecialchars($fila['fecha']) ?></td>
                                <td><?= htmlspecialchars($fila['hora_entrada_real']) ?></td>
                                <td><?= htmlspecialchars($fila['hora_salida_real']) ?></td>
                                <td><span class="badge-estado badge-<?= $fila['estado'] ?>"><?= $etiquetas[$fila['estado']] ?></span></td>
                               
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
<!-- Asegúrate de tener jQuery y los scripts de DataTables en scripts.php, ej: -->
 <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script> 
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script> 
 <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script> 

<script>
$(document).ready(function() {
    $('#tablaHistorial').DataTable({
        // Cambiar el idioma a español
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        // Evita que ordene automáticamente la primera columna al cargar la página
        order: [],
        // Puedes personalizar el diseño (dom) para que encaje mejor con Bootstrap si lo deseas
        pageLength: 10,
        responsive: true
    });
});
</script>
</body>
</html>
