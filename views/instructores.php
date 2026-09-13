<?php
session_start();
if (!isset($_SESSION["Documento"])) {
    header("Location: ../index.php");
    exit();
}
require_once __DIR__ . "/../config/mysql.php";

$db = new mysql();
$db->conectar();

//  consulta para traer los datos del instructor elegido para editar
$instructorEditar = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_editar']) && !empty($_POST['id_editar'])) {
    $idEdit = filter_var($_POST['id_editar'], FILTER_SANITIZE_NUMBER_INT);

    $stmt = $db->prepare("SELECT id_usuario, nombre_completo, apellido, num_documento, correo_electronico FROM usuarios WHERE id_usuario = :id AND id_role = 2");
    $stmt->bindParam(':id', $idEdit, PDO::PARAM_INT);
    $stmt->execute();
    $instructorEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}

//  Consulta de todos los instructores para la tabla
$sql = "SELECT id_usuario, nombre_completo, apellido,estado, num_documento, correo_electronico FROM usuarios WHERE id_role = 2";
$resultado = $db->efectuarconsulta($sql);




$rolActivo    = 'admin';        
$paginaActiva = 'instructores'; 
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
                                <th>Estado</th>
                                <th class="text-end" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($fila = $resultado->fetch(PDO::FETCH_ASSOC)) { ?>
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <span class="avatar-circ"><?= strtoupper(substr($fila['nombre_completo'], 0, 1)) ?></span>
                                    <?= htmlspecialchars($fila['nombre_completo'] . ' ' . $fila['apellido'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td><?= htmlspecialchars($fila['num_documento'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($fila['correo_electronico'], ENT_QUOTES, 'UTF-8') ?></td>
                                
                                <td>
    <?php
    $badgeClass = match(strtolower(trim($fila['estado']))) {
        'activo'    => 'bg-success-subtle text-success border border-success-subtle',
        'inactivo'  => 'bg-danger-subtle text-danger border border-danger-subtle',
        'pendiente' => 'bg-warning-subtle text-warning border border-warning-subtle',
        default     => 'bg-secondary-subtle text-secondary border'
    };
    ?>
    <span class="badge rounded-pill <?= $badgeClass ?>">
        <?= htmlspecialchars($fila['estado'], ENT_QUOTES, 'UTF-8') ?>
    </span>
</td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- Formulario POST exclusivo para solicitar edición de este usuario -->
                                        <form method="POST" action="instructores.php" class="d-inline m-0 p-0">
                                            <input type="hidden" name="id_editar" value="<?= $fila['id_usuario'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary border-0" title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                        </form>

                                        <button class="btn btn-sm btn-outline-danger border-0" title="Desactivar">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    </div>
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

<?php include '../includes/scripts.php'; ?>

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