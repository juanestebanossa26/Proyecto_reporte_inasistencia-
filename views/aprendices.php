<?php
/**
 * aprendices.php — Gestión de aprendices
 *
 * TODO (backend):
 *   - Reemplazar $aprendices (arreglo de ejemplo) por SELECT * FROM aprendices
 *   - El formulario del modal #modalAprendiz debe apuntar a guardar_aprendiz.php (POST)
 *   - El botón "Desactivar" debe llamar a desactivar_aprendiz.php?  no borra
 *     solo cambia estado a inactivo
 */

$rolActivo     = $rolActivo = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario =$_SESSION['nombre'] ?? '';
$paginaActiva  = 'aprendices';
$tituloPagina  = 'Aprendices';

$modeloAprendiz = new AprendizModelo();
$aprendices     = $modeloAprendiz->listar();
$fichas         = $modeloAprendiz->listarFichas();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'includes/head.php'; ?>
<!-- CSS de DataTables -->
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
                    <div class="eyebrow">Gestión</div>
                    <h1 class="h4 fw-bold mb-0">Aprendices</h1>
                    <p class="text-suave mb-0">Crea, edita y administra el estado de los aprendices.</p>
                </div>
                <button class="btn btn-sena" data-bs-toggle="modal" data-bs-target="#modalAprendiz">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo aprendiz
                </button>
            </div>

            <div class="card p-3">
                <!-- Buscador manual y filtro original -->
           
                  

                <div class="table-responsive">
                    <!-- Se agregó el ID "tablaAprendices" a la tabla -->
                    <table id="tablaAprendices" class="table table-app align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Aprendiz</th>
                                <th>Documento</th>
                                <th>Ficha</th>
                                <th>Código RFID</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($aprendices as $a): 
                            $nombreCompleto = trim(($a['nombre_completo'] ?? '') . ' ' . ($a['apellido'] ?? '')) ?: ($a['nombre'] ?? '');
                            $documento      = $a['num_documento'] ?? $a['doc'] ?? '';
                            $fichaLabel     = $a['ficha_label'] ?? $a['ficha'] ?? '';
                            $rfid           = $a['tag_rfid'] ?? $a['rfid'] ?? '';
                            $estado         = strtolower($a['estado'] ?? 'activo');
                        ?>
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <span class="avatar-circ"><?= strtoupper(substr($nombreCompleto, 0, 1)) ?></span>
                                    <?= htmlspecialchars($nombreCompleto) ?>
                                </td>
                                <td><?= htmlspecialchars($documento) ?></td>
                                <td><?= htmlspecialchars($fichaLabel) ?></td>
                                <td><code><?= htmlspecialchars($rfid) ?></code></td>
                                <td>
                                    <?php if ($estado === 'activo'): ?>
                                        <span class="badge-estado badge-a-tiempo">Activo</span>
                                    <?php else: ?>
                                        <span class="badge-estado badge-inasistencia">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#modalAprendiz" title="Editar">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger border-0" title="Desactivar">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
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


<div class="modal fade" id="modalAprendiz" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
  
      <form action="<?= BASE_URL?>/index.php?accion=guardarAprendiz" method="POST">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Nuevo aprendiz</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Nombres </label>
                <input type="text" class="form-control" name="nombre" placeholder="Ej: Laura" required>
            </div>
             <div class="mb-3">
                <label class="form-label">Apellidos</label>
                <input type="text" class="form-control" name="apellido" placeholder="Ej: Martínez" required>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Número de documento</label>
                    <input type="text" class="form-control" name="documento" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Código RFID</label>
                    <input type="text" class="form-control" name="codigo_rfid" placeholder="RF-00000" required>
                </div>
            </div>
            <div class="row g-3 mt-0">
                <div class="col-md-6">
                    <label class="form-label">Ficha</label>
                    <select class="form-select" name="ficha_id" required>
                        <option value="">Seleccione una ficha...</option>
                        <?php foreach ($fichas as $f): ?>
                            <option value="<?= $f['id_ficha'] ?>">
                                <?= htmlspecialchars($f['numero_ficha'] . ' · ' . $f['nombre_programa']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control" name="correo">
                </div>
            </div>
            <div class="mb-0 mt-3">
                <label class="form-label">Contraseña de acceso</label>
                <input type="password" class="form-control" name="password" placeholder="Se usará para iniciar sesión">
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sena">Guardar aprendiz</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/scripts.php'; ?>

<!-- Scripts de DataTables -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaAprendices').DataTable({
        // Configuración de idioma a español
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        order: [], // Evita que se ordene automáticamente al cargar
        pageLength: 10,
        responsive: true,
        columnDefs: [
            // Desactiva el ordenamiento y buscador para la columna de "Acciones" (índice 5)
            { targets: 5, orderable: false, searchable: false }
        ]
    });
});
</script>
</body>
</html>
