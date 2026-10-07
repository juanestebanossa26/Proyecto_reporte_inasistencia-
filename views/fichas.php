<?php
/**
 * fichas.php — Vista exclusiva para Gestión y Listado de Fichas
 */
$rolActivo     = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'fichas';
$tituloPagina  = 'Fichas de Formación';

// Las fichas e instructores son suministrados por HorarioControlador::mostrarFichas()
$fichas       = $fichas ?? [];
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
        <main class="app-content p-4">
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
                <div>
                    <div class="eyebrow">Gestión</div>
                    <h1 class="h4 fw-bold mb-0">Fichas de Formación</h1>
                    <p class="text-suave mb-0">Administra las fichas académicas, programas de formación e instructores asignados.</p>
                </div>
                <?php if ($rolActivo === 'Administrador'): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sena" onclick="abrirModalCrearFicha()">
                        <i class="bi bi-plus-lg me-1"></i> Nueva ficha
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tabla de Fichas -->
            <div class="card p-3 shadow-sm border-0">
                <div class="table-responsive">
                    <table id="tablaFichas" class="table table-app align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Número de Ficha</th>
                                <th>Programa de Formación</th>
                                <th>Instructores</th>
                                <th class="text-center">Aprendices</th>
                                <th class="text-center">Horarios</th>
                                <th class="text-end" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($fichas as $f): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border fs-6 px-2 py-1">
                                        <i class="bi bi-folder2-open text-success me-1"></i>
                                        <?= htmlspecialchars($f['numero_ficha']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($f['nombre_programa']) ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $instTexto = trim($f['instructores'] ?? '');
                                    if ($instTexto !== ''): ?>
                                        <span class="text-secondary small d-inline-block text-truncate" style="max-width: 280px;" title="<?= htmlspecialchars($instTexto) ?>">
                                            <i class="bi bi-person-badge text-primary me-1"></i><?= htmlspecialchars($instTexto) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-people me-1"></i><?= (int)($f['total_aprendices'] ?? 0) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                        <i class="bi bi-calendar-event me-1"></i><?= (int)($f['total_horarios'] ?? 0) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- Enlace a ver horarios de esta ficha en el calendario -->
                                        <a href="<?= BASE_URL ?>/index.php?accion=horarios" 
                                           class="btn btn-sm btn-outline-primary border-0" 
                                           title="Ver horarios en calendario">
                                            <i class="bi bi-calendar3"></i>
                                        </a>

                                        <?php if ($rolActivo === 'Administrador'): ?>
                                        <!-- Botón Editar Ficha -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-secondary border-0 btn-editar-ficha" 
                                                title="Editar Ficha"
                                                data-id="<?= $f['id_ficha'] ?>"
                                                data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-programa="<?= htmlspecialchars($f['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-instructor="<?= (int)($f['id_instructor'] ?? 0) ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal: Crear / Editar Ficha -->
<div class="modal fade" id="modalFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form id="formFicha" action="<?= BASE_URL ?>/index.php?accion=guardarFicha" method="POST">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <input type="hidden" name="id_ficha" id="form_id_ficha" value="0">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="modalTituloFicha">
            <i class="bi bi-journal-bookmark text-success me-2"></i>Nueva Ficha
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Número de Ficha</label>
                <input type="text" class="form-control" name="numero_ficha" id="form_numero_ficha" required placeholder="Ej. 3064749">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Programa de Formación</label>
                <input type="text" class="form-control" name="nombre_programa" id="form_nombre_programa" required placeholder="Ej. TECNOLOGÍA EN ANÁLISIS Y DESARROLLO DE SOFTWARE">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Instructor encargado (Opcional)</label>
                <select class="form-select" name="id_instructor" id="form_id_instructor">
                    <option value="0">Seleccionar instructor...</option>
                    <?php foreach ($instructores as $inst): ?>
                        <option value="<?= $inst['id_usuario'] ?>"><?= htmlspecialchars($inst['nombre_completo'] . ' ' . $inst['apellido']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Puedes asociar un instructor de referencia a esta ficha.</div>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sena" id="btnGuardarFicha">Guardar Ficha</button>
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
const modalEl = document.getElementById('modalFicha');
const modalBs = new bootstrap.Modal(modalEl);

function abrirModalCrearFicha() {
    document.getElementById('formFicha').reset();
    document.getElementById('form_id_ficha').value = '0';
    document.getElementById('modalTituloFicha').innerHTML = '<i class="bi bi-journal-bookmark text-success me-2"></i>Nueva Ficha';
    modalBs.show();
}

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar DataTable
    $('#tablaFichas').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        order: [[0, 'asc']],
        pageLength: 10
    });

    // Delegación para editar ficha
    $(document).on('click', '.btn-editar-ficha', function() {
        const id = $(this).data('id');
        const numero = $(this).data('numero');
        const programa = $(this).data('programa');
        const instructor = $(this).data('instructor');

        document.getElementById('form_id_ficha').value = id;
        document.getElementById('form_numero_ficha').value = numero;
        document.getElementById('form_nombre_programa').value = programa;
        document.getElementById('form_id_instructor').value = instructor || '0';

        document.getElementById('modalTituloFicha').innerHTML = '<i class="bi bi-pencil-square text-success me-2"></i>Editar Ficha';
        modalBs.show();
    });
});
</script>
</body>
</html>