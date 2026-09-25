<?php
$rolActivo     = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario =$_SESSION['nombre'] ?? '';
$tituloPagina = 'Instructores';
$instructores = $instructores ?? [];
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
                <button type="button" class="btn btn-sena btn-nuevo-instructor" data-bs-toggle="modal" data-bs-target="#modalInstructor">
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
                                <th>Área</th>
                                <th>Correo</th>
                                <th>Estado</th>
                                <th class="text-end" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($instructores as $fila) { 
                            $estado = strtolower(trim($fila['estado']));
                        ?>
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <span class="avatar-circ"><?= strtoupper(substr($fila['nombre_completo'], 0, 1)) ?></span>
                                    <?= htmlspecialchars($fila['nombre_completo'] . ' ' . $fila['apellido'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td><?= htmlspecialchars($fila['num_documento'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($fila['area'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($fila['correo_electronico'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php
                                    $badgeClass = match($estado) {
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
                                        <!-- Botón Editar -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-secondary border-0 btn-editar-instructor" 
                                                title="Editar"
                                                data-id="<?= $fila['id_usuario'] ?>"
                                                data-nombre="<?= htmlspecialchars($fila['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-apellido="<?= htmlspecialchars($fila['apellido'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-documento="<?= htmlspecialchars($fila['num_documento'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-area="<?= htmlspecialchars($fila['area'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-correo="<?= htmlspecialchars($fila['correo_electronico'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <!-- Botones de Estado delegados al JS -->
                                        <?php if ($estado === 'activo'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger border-0 btn-estado-instructor" 
                                                    title="Inactivar instructor"
                                                    data-id="<?= $fila['id_usuario'] ?>"
                                                    data-nombre="<?= htmlspecialchars($fila['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-accion-tipo="desactivar">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-success border-0 btn-estado-instructor" 
                                                    title="Activar instructor"
                                                    data-id="<?= $fila['id_usuario'] ?>"
                                                    data-nombre="<?= htmlspecialchars($fila['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-accion-tipo="activar">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Modal Único Dinámico (Crear / Actualizar) -->
<div class="modal fade" id="modalInstructor" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formInstructor" method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">
        <input type="hidden" name="id_usuario" id="inst_id_usuario" value="0">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="modalInstructorTitulo">Nuevo instructor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Nombres</label>
                <input type="text" class="form-control" name="nombre" id="inst_nombre" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Apellidos</label>
                <input type="text" class="form-control" name="apellido" id="inst_apellido" required>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Número de documento</label>
                    <input type="text" class="form-control" name="documento" id="inst_documento" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Área</label>
                    <select class="form-select" name="area" id="inst_area" required>
                        <option value="" selected disabled>Seleccionar área...</option>
                        <option value="TICS">TICS</option>
                        <option value="Comercio">Comercio</option>
                    </select>
                </div>
            </div>
            <div class="mb-3 mt-3">
                <label class="form-label">Correo electrónico</label>
                <input type="email" class="form-control" name="correo" id="inst_correo" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Contraseña de acceso</label>
                <input type="password" class="form-control" name="password" id="inst_password" placeholder="Se usará para iniciar sesión">
                <small id="ayudaPassword" class="text-muted d-none">Dejar en blanco para mantener la actual.</small>
            </div>
        </div>

        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sena" id="btnSubmitInstructor">Guardar instructor</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Formulario oculto para cambiar el estado -->
<form id="formCambiarEstadoInstructor" action="<?= BASE_URL ?>/index.php?accion=cambiarEstadoInstructor" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">
    <input type="hidden" name="id_usuario" id="estado_inst_id_usuario" value="0">
    <input type="hidden" name="estado_actual" id="estado_inst_actual" value="">
</form>

<?php include 'includes/scripts.php'; ?>

<!-- DataTables Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Definición de BASE_URL -->
<script>
    const BASE_URL = '<?= BASE_URL ?>';
</script>

<!-- Script para instructores -->
<script src="<?= BASE_URL ?>/assets/js/instructor.js"></script>

</body>
</html>