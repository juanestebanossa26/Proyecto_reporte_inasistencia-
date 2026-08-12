<?php
/**
 * aprendices.php — Gestión de aprendices (HU 1, 14)
 *
 * TODO (backend):
 *   - Reemplazar $aprendices (arreglo de ejemplo) por SELECT * FROM aprendices
 *   - El formulario del modal #modalAprendiz debe apuntar a guardar_aprendiz.php (POST)
 *   - El botón "Desactivar" debe llamar a desactivar_aprendiz.php?id=X (HU 14: no borra,
 *     solo cambia estado a inactivo)
 */
$rolActivo     = 'admin';
$nombreUsuario = 'Juan Camilo Vanegas';
$paginaActiva  = 'aprendices';
$tituloPagina  = 'Aprendices';

$aprendices = [
    ['id'=>1,'nombre'=>'Laura Martínez','doc'=>'1093456781','ficha'=>'2589451 · ADSO','rfid'=>'RF-88231','estado'=>'activo'],
    ['id'=>2,'nombre'=>'Julián Rojas','doc'=>'1094223451','ficha'=>'2589451 · ADSO','rfid'=>'RF-88232','estado'=>'activo'],
    ['id'=>3,'nombre'=>'Camila Suárez','doc'=>'1098765432','ficha'=>'2678903 · Multimedia','rfid'=>'RF-90112','estado'=>'activo'],
    ['id'=>4,'nombre'=>'Diego Peña','doc'=>'1096541230','ficha'=>'2589451 · ADSO','rfid'=>'RF-88233','estado'=>'inactivo'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include '../includes/head.php'; ?>
</head>
<body>
<div class="app-shell">
    <?php include '../includes/sidebar.php'; ?>

    <div class="app-main">
        <?php include '../includes/topbar.php'; ?>

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
                <div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
                    <div class="input-group" style="max-width:320px;">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control border-start-0" placeholder="Buscar por nombre o documento...">
                    </div>
                    <select class="form-select" style="max-width:200px;">
                        <option>Todas las fichas</option>
                        <option>2589451 · ADSO</option>
                        <option>2678903 · Multimedia</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="table table-app align-middle mb-0">
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
                        <?php foreach ($aprendices as $a): ?>
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <span class="avatar-circ"><?= strtoupper(substr($a['nombre'],0,1)) ?></span>
                                    <?= htmlspecialchars($a['nombre']) ?>
                                </td>
                                <td><?= htmlspecialchars($a['doc']) ?></td>
                                <td><?= htmlspecialchars($a['ficha']) ?></td>
                                <td><code><?= htmlspecialchars($a['rfid']) ?></code></td>
                                <td>
                                    <?php if ($a['estado'] === 'activo'): ?>
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
  
      <form   action="guardar_aprendiz.php" method="POST"     >
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Nuevo aprendiz</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Nombre completo</label>
                <input type="text" class="form-control" name="nombre" placeholder="Ej: Laura Martínez" required>
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
                    <select class="form-select" name="ficha_id">
                        <option>2589451 · ADSO</option>
                        <option>2678903 · Multimedia</option>
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

<?php include '../includes/scripts.php'; ?>
</body>
</html>
