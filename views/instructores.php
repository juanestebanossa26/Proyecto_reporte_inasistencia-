<?php

require_once("../models/mysql.php");

$db =new mysql();
$db->conectar();

$sql="SELECT nombre_completo,num_documento,correo_electronico from usuarios where id_role=2";

$resultado=$db->efectuarconsulta($sql);

$rolActivo    = 'admin';        // O según corresponda ($_SESSION['rol'])
$paginaActiva = 'instructores'; // 👈 ESTA LÍNEA ES LA QUE ACTIVA EL VERDE
$tituloPagina = 'Instructores';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include '../includes/head.php'; ?>
<!-- DataTables Bootstrap 5 CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
<div class="app-shell">
    <?php include '../includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include '../includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
                <div>
                    <div class="eyebrow">Gestión</div>
                    <h1 class="h4 fw-bold mb-0">Instructores</h1>
                    <p class="text-suave mb-0">Administra los instructores y su vínculo con las fichas.</p>
                </div>
                <button class="btn btn-sena" data-bs-toggle="modal" data-bs-target="#modalInstructor">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo instructor
                </button>
            </div>

            <div class="card p-3">
                <div class="table-responsive">
                    <table id="tablaInstructores" class="table table-app align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Instructor</th>
                                <th>Documento</th>
                                <th>Correo</th>
                             
                                <th class="text-end" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>

                    <?php 
                
                    while ($fila = $resultado->fetch()) { ?>
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <span class="avatar-circ"><?= strtoupper(substr($fila['nombre_completo'],0,1)) ?></span>
                                    <?=$fila['nombre_completo'] ?>
                                </td>
                                <td><?= $fila['num_documento']?></td>
                                <td><?=$fila['correo_electronico'] ?></td>
                             
                                
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#modalInstructor" title="Editar">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger border-0" title="Desactivar">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="modalInstructor" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="../controllers/guardar_instructor.php" method="POST">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Nuevo instructor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Nombre completo</label>
                <input type="text" class="form-control" name="nombre" required>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Número de documento</label>
                    <input type="text" class="form-control" name="documento" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control" name="correo">
                </div>
            </div>
            <div class="row g-3 mt-0">
                <div class="col-md-6">
                    <label class="form-label">Contraseña de acceso</label>
                    <input type="password" class="form-control" name="password">
                </div>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sena">Guardar instructor</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/scripts.php'; ?>

<!-- jQuery y DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaInstructores').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        pageLength: 10,
        responsive: true,
        columnDefs: [
            { orderable: false, targets: 3 } // Desactiva la ordenación en la columna "Acciones"
        ]
    });
});
</script>
</body>
</html>
