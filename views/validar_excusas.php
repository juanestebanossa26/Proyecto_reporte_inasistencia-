<?php
session_start();
if (!isset($_SESSION["Documento"])) {
    header("Location: ../index.php");
    exit();
}
require_once("../models/mysql.php");
$db=new mysql();
$db->conectar();

$sql="SELECT 
usuarios.nombre_completo,
usuarios.apellido,
fichas.nombre_programa,
excusas_medicas.motivo,
asistencias.fecha,
excusas_medicas.estado_excusa
from usuarios 
join aprendices on aprendices.id_usuario=usuarios.id_usuario
join fichas on aprendices.id_ficha=fichas.id_ficha
join asistencias on asistencias.id_aprendiz=aprendices.id_aprendiz
join excusas_medicas on excusas_medicas.id_asistencia=asistencias.id_asistencia
where fichas.id_instructor=:id_usuario 
";
$resultado= $db->prepare($sql);
$resultado->bindParam(':id_usuario', $_SESSION['id'], PDO::PARAM_INT);
$resultado->execute();

$rolActivo     = $_SESSION['rol']?? 'instructor';
$nombreUsuario ;
$paginaActiva  = 'validar_excusas';
$tituloPagina  = 'Validar excusas médicas';


?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include '../includes/head.php'; ?>
<!-- CSS de DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
<div class="app-shell">
    <?php include '../includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include '../includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header">
                <div class="eyebrow">Control</div>
                <h1 class="h4 fw-bold mb-0">Excusas médicas</h1>
                <p class="text-suave mb-0">Aprueba o rechaza los soportes enviados por los aprendices.</p>
            </div>

            <ul class="nav nav-pills mb-3">
                <li class="nav-item"><a class="nav-link active" href="#" style="background:var(--sena-verde);">Pendientes <span class="badge bg-white text-sena ms-1">2</span></a></li>
                <li class="nav-item"><a class="nav-link text-suave" href="#">Aprobadas</a></li>
                <li class="nav-item"><a class="nav-link text-suave" href="#">Rechazadas</a></li>
            </ul>

            <div class="card p-3">
                <div class="table-responsive">
                    <!-- Se agregó el ID "tablaExcusas" a la tabla -->
                    <table id="tablaExcusas" class="table table-app align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Aprendiz</th>
                                <th>Ficha</th>
                                <th>Fecha</th>
                                <th>Motivo</th>
                                <th>Soporte</th>
                                <th>Estado</th>
                                <!-- Se agrega una clase para alinear correctamente -->
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($fila=$resultado->fetch()) {?>
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <span class="avatar-circ"><?= strtoupper(substr($fila['nombre_completo'],0,1)) ?></span>
                                    <?= htmlspecialchars($fila["nombre_completo"].' '.$fila["apellido"]) ?>
                                </td>
                                <td><?= htmlspecialchars($fila['nombre_programa']) ?></td>
                                <td><?= htmlspecialchars($fila['fecha']) ?></td>
                                <td><?= htmlspecialchars($fila['motivo']) ?></td>
                                <td><a href="#" class="text-sena"><i class="bi bi-file-earmark-pdf me-1"></i>Ver PDF</a></td>
                                <td><span class="badge-estado badge-<?= $fila['estado_excusa'] ?>"><?= ucfirst($fila['estado_excusa']) ?></span></td>
                                <td class="text-end">
                                    <?php if ($fila['estado_excusa'] === 'pendiente'): ?>
                                        <button class="btn btn-sm btn-sena">Aprobar</button>
                                        <button class="btn btn-sm btn-outline-danger">Rechazar</button>
                                    <?php else: ?>
                                        <span class="text-suave" style="font-size:.8rem;">Sin acciones</span>
                                    <?php endif; ?>
                                </td>
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
<!-- JS de DataTables (Asegúrate de que no tengan comentarios HTML a los lados) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaExcusas').DataTable({
        // Configuración de idioma español
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        // Evitar que DataTables ordene automáticamente la primera columna al inicio
        order: [],
        pageLength: 10,
        responsive: true,
        // Configuración para desactivar el filtrado/ordenamiento en la última columna de "Acciones" (columna índice 6)
        columnDefs: [
            { targets: 6, orderable: false, searchable: false }
        ]
    });
});
</script>
</body>
</html>