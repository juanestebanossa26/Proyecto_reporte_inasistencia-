<?php


$rolActivo    = $rolActivo = $_SESSION['rol'] ?? 'Administrador';    
$paginaActiva =  $_SESSION['nombre'] ?? '';
$tituloPagina = 'Instructores';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'includes/head.php'; ?>
<!-- DataTables Bootstrap 5 CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
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
                                <th>Estado</th>
                                <th class="text-end" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
           
   
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal: Crear Nuevo Instructor -->
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
                <label class="form-label">Nombres</label>
                <input type="text" class="form-control" name="nombre" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Apellidos</label>
                <input type="text" class="form-control" name="apellido" required>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Número de documento</label>
                    <input type="text" class="form-control" name="documento" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control" name="correo" required>
                </div>
            </div>
            <div class="row g-3 mt-0">
                <div class="col-md-12">
                    <label class="form-label">Contraseña de acceso</label>
                    <input type="password" class="form-control" name="password" required>
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

<!-- Modal: Actualizar Instructor (poblado con PHP) -->
<div class="modal fade" id="modalactualizarInstructor" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="../controllers/actualizar_instructor.php" method="POST">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Actualizar instructor</h5>
          <a href="instructores.php" class="btn-close"></a>
        </div>
        <div class="modal-body">
            <!-- ID que viaja al controlador para saber a quién actualizar -->
            <input type="hidden" name="id_usuario" value="<?= $instructorEditar ? $instructorEditar['id_usuario'] : '' ?>">

            <div class="mb-3">
                <label class="form-label">Nombres</label>
                <input type="text" class="form-control" name="nombre" value="<?= $instructorEditar ? htmlspecialchars($instructorEditar['nombre_completo']) : '' ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Apellidos</label>
                <input type="text" class="form-control" name="apellido" value="<?= $instructorEditar ? htmlspecialchars($instructorEditar['apellido']) : '' ?>" required>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Número de documento</label>
                    <input type="text" class="form-control" name="documento" value="<?= $instructorEditar ? htmlspecialchars($instructorEditar['num_documento']) : '' ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control" name="correo" value="<?= $instructorEditar ? htmlspecialchars($instructorEditar['correo_electronico']) : '' ?>" required>
                </div>
            </div>
            <div class="row g-3 mt-0">
                <div class="col-md-12">
                    <label class="form-label">Nueva contraseña (opcional)</label>
                    <input type="password" class="form-control" name="password" placeholder="Dejar en blanco para mantener la actual">
                </div>
            </div>
        </div>
        <div class="modal-footer border-0">
          <a href="instructores.php" class="btn btn-light">Cancelar</a>
          <button type="submit" class="btn btn-sena">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/scripts.php'; ?>

<!-- DataTables Scripts -->
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
            { orderable: false, targets: 4 }
        ]
    });
});
</script>

<!-- Si PHP detectó la petición POST con datos de usuario, abre el modal -->
<?php if ($instructorEditar): ?>
<script>
    let modalEditar = new bootstrap.Modal(document.getElementById('modalactualizarInstructor'));
    modalEditar.show();
</script>
<?php endif; ?>

</body>
</html>