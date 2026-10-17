<?php
// La sesión ya fue verificada por PaginaControlador::mostrar() antes de
// llegar aquí (ver index.php). No hace falta repetir el chequeo.




$rolActivo     = $_SESSION['rol']    ?? 'Aprendiz';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'historial';
$tituloPagina  = 'Mi historial de asistencia';

// Variables por defecto si el controlador no las ha inyectado (evita warnings y diagnósticos)
$historial          = $historial          ?? [];
$total_retardo      = $total_retardo      ?? 0;
$total_inasistencia = $total_inasistencia ?? 0;
$total_pendientes   = $total_pendientes   ?? 0;

$etiquetas = [
    'asistencia' => 'Asistencia', 'retardo' => 'Retardo',
    'inasistencia' => 'Inasistencia', 'salida-anticipada' => 'Salida anticipada',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'includes/head.php'; ?>
 <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"> 
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2">
                <div>
                    <div class="eyebrow">Mi asistencia</div>
                    <h1 class="h4 fw-bold mb-0">Mi historial</h1>
                    <p class="text-suave mb-0">Consulta tus retardos, inasistencias y el estado de tus excusas.</p>
                </div>
                <a href="<?= BASE_URL ?>/index.php?accion=descargarMiReporte" target="_blank" class="btn btn-outline-sena" id="btnDescargarMiReporte">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Descargar mi reporte
                </a>
            </div>

            <div class="row g-3 mb-3 justify-content-center">
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Retardos</div>
                        <div class="h4 fw-bold mb-0" style="color:#8a6300;"><?php echo $total_retardo; ?></div>
                    </div>
                </div>
                
                <div class="col-6 col-md-3">
                    <div class="card kpi-card p-3 text-center">
                        <div class="text-suave" style="font-size:.76rem;">Inasistencias</div>
                        <div class="h4 fw-bold mb-0" style="color:#B4212B;"><?php echo $total_inasistencia; ?></div>
                    </div>
                </div>
                
                <div class="col-6 col-md-3">
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
                     
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($historial as $fila) {  
                            
                          

                            // Validamos si la hora de entrada y salida están vacías
                            $horaEntrada = !empty($fila['hora_entrada_real']) ? htmlspecialchars($fila['hora_entrada_real']) : '<span class="text-muted fw-bold fs-5">-</span>';
                            $horaSalida  = !empty($fila['hora_salida_real']) ? htmlspecialchars($fila['hora_salida_real']) : '<span class="text-muted fw-bold fs-5">-</span>';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($fila['fecha']) ?></td>
                            
                                <td class="text-center"><?= $horaEntrada ?></td>
                                <td class="text-center"><?= $horaSalida ?></td>
                                <td><span class="badge-estado badge-<?= $fila['estado'] ?>"><?= $etiquetas[$fila['estado']] ?? $fila['estado'] ?></span></td>
                        
                            </tr>
                        <?php }; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
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