<?php
session_start();
if (!isset($_SESSION["Documento"])) {
    header("Location: ../index.php");
    exit();
}
require_once("../models/mysql.php");
$db = new mysql();
$db->conectar();
$sql = "SELECT asistencias.fecha,
        excusas_medicas.estado_excusa,
        excusas_medicas.motivo
        FROM asistencias 
        JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz 
        JOIN excusas_medicas on excusas_medicas.id_asistencia=asistencias.id_asistencia
        WHERE aprendices.id_usuario = :id_usuario";

$resultado= $db->prepare($sql);
$resultado->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
$resultado->execute();

$rolActivo     = 'aprendiz';
$nombreUsuario = 'Laura Martínez';
$paginaActiva  = 'excusas';
$tituloPagina  = 'Excusas médicas';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include '../includes/head.php'; ?>
    
    <!-- CSS de DataTables inyectado directamente para que funcione de inmediato -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php include '../includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include '../includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header">
                <div class="eyebrow">Mi asistencia</div>
                <h1 class="h4 fw-bold mb-0">Excusas médicas</h1>
                <p class="text-suave mb-0">Anexa el soporte de tu inasistencia para que tu instructor la valide.</p>
            </div>

            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="card p-3">
                        <h2 class="h6 fw-bold mb-3">Anexar nueva excusa</h2>
                        <!-- TODO (backend): action="guardar_excusa.php" method="POST" enctype="multipart/form-data" -->
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Fecha de la inasistencia</label>
                                <input type="date" class="form-control" name="fecha" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Motivo</label>
                                <textarea class="form-control" name="motivo" rows="3" placeholder="Ej: Cita médica EPS" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Soporte (PDF o imagen)</label>
                                <div class="dropzone-excusa">
                                    <i class="bi bi-cloud-arrow-up text-sena" style="font-size:1.6rem;"></i>
                                    <p class="mb-1 mt-2 fw-semibold" style="font-size:.9rem;">Arrastra tu archivo aquí</p>
                                    <p class="text-suave mb-2" style="font-size:.8rem;">o haz clic para seleccionarlo</p>
                                    <input type="file" name="archivo" class="form-control">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sena w-100">Enviar excusa</button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card p-3">
                        <h2 class="h6 fw-bold mb-3">Mis excusas enviadas</h2>
                        <div class="table-responsive">
                            <table id="tablaExcusas" class="table table-app align-middle mb-0" style="width:100%">
                                <thead>
                                    <tr><th>Fecha</th><th>Motivo</th><th>Estado</th></tr>
                                </thead>
                                <tbody>
                                <?php 
                                while ($fila=$resultado->fetch()) {
                                    // 1. Limpiamos el texto pasándolo a minúsculas para evitar errores al comparar
                                    $estado = strtolower($fila['estado_excusa']);
                                    $claseColor = '';

                                    // 2. Asignamos la clase de color de Bootstrap según el estado
                                    switch ($estado) {
                                        case 'aprobada':
                                            $claseColor = 'bg-success text-white'; // Verde
                                            break;
                                        case 'rechazada':
                                            $claseColor = 'bg-danger text-white';  // Rojo
                                            break;
                                        case 'pendiente':
                                            $claseColor = 'bg-warning text-dark';  // Amarillo
                                            break;
                                        default:
                                            $claseColor = 'bg-secondary text-white'; // Gris por defecto si hay otro estado
                                            break;
                                    }
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($fila['fecha']) ?></td>
                                        <td><?= htmlspecialchars($fila['motivo']) ?></td>
                                        <!-- 3. Imprimimos el span con la clase dinámica -->
                                        <td>
                                            <span class="badge <?= $claseColor ?>">
                                                <?= ucfirst($estado) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/scripts.php'; ?>

<!-- Dependencias JS de DataTables y jQuery inyectadas directamente -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Inicialización -->
<script>
    $(document).ready(function() {
        $('#tablaExcusas').DataTable({
            language: {
                "decimal": "",
                "emptyTable": "No hay datos disponibles en la tabla",
                "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                "infoEmpty": "Mostrando 0 a 0 de 0 registros",
                "infoFiltered": "(filtrado de _MAX_ registros totales)",
                "infoPostFix": "",
                "thousands": ",",
                "lengthMenu": "Mostrar _MENU_ registros",
                "loadingRecords": "Cargando...",
                "processing": "Procesando...",
                "search": "Buscar:",
                "zeroRecords": "No se encontraron resultados",
                "paginate": {
                    "first": "Primero",
                    "last": "Último",
                    "next": "Siguiente",
                    "previous": "Anterior"
                },
                "aria": {
                    "sortAscending": ": activar para ordenar la columna de manera ascendente",
                    "sortDescending": ": activar para ordenar la columna de manera descendente"
                }
            },
            pageLength: 5, 
            lengthMenu: [5, 10, 25, 50],
            ordering: true 
        });
    });
</script>
</body>
</html>