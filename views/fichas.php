<!-- bobadilla -->

<?php
/**
 * fichas.php — Fichas y horarios (HU 9, 10)
 *
 * TODO (backend): reemplazar $fichas por SELECT desde tablas `fichas` + `horarios`;
 * el modal debe enviar a guardar_ficha.php (crea/edita horario, incluyendo instructor).
 */
$rolActivo     = 'admin';
$nombreUsuario = 'Juan Camilo Vanegas';
$paginaActiva  = 'fichas';
$tituloPagina  = 'Fichas y horarios';

$fichas = [
    ['ficha'=>'2589451','programa'=>'ADSO','instructor'=>'Juan Camilo Vanegas','entrada'=>'07:00 a.m.','salida'=>'12:30 p.m.','aprendices'=>32],
    ['ficha'=>'2678903','programa'=>'Multimedia','instructor'=>'Marcela Ortiz','entrada'=>'01:00 p.m.','salida'=>'06:00 p.m.','aprendices'=>28],
    ['ficha'=>'2701123','programa'=>'Redes','instructor'=>'Héctor Ramírez','entrada'=>'06:30 a.m.','salida'=>'11:30 a.m.','aprendices'=>24],
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
                    <h1 class="h4 fw-bold mb-0">Fichas y horarios</h1>
                    <p class="text-suave mb-0">Configura entrada, salida e instructor encargado de cada ficha.</p>
                </div>
                <?php if ($rolActivo === 'admin'): ?>
                <button class="btn btn-sena" data-bs-toggle="modal" data-bs-target="#modalFicha">
                    <i class="bi bi-plus-lg me-1"></i> Nueva ficha
                </button>
                <?php endif; ?>
            </div>

            <div class="row g-3">
                <?php foreach ($fichas as $f): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="text-suave" style="font-size:.76rem;">FICHA</div>
                                <div class="h5 fw-bold mb-0"><?= htmlspecialchars($f['ficha']) ?></div>
                                <div class="text-sena fw-semibold" style="font-size:.85rem;"><?= htmlspecialchars($f['programa']) ?></div>
                            </div>
                            <?php if ($rolActivo === 'admin'): ?>
                            <button class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#modalFicha">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between mb-1" style="font-size:.86rem;">
                            <span class="text-suave"><i class="bi bi-person-badge me-1"></i>Instructor</span>
                            <span class="fw-semibold"><?= htmlspecialchars($f['instructor']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1" style="font-size:.86rem;">
                            <span class="text-suave"><i class="bi bi-box-arrow-in-right me-1"></i>Entrada</span>
                            <span class="fw-semibold"><?= htmlspecialchars($f['entrada']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1" style="font-size:.86rem;">
                            <span class="text-suave"><i class="bi bi-box-arrow-right me-1"></i>Salida</span>
                            <span class="fw-semibold"><?= htmlspecialchars($f['salida']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between" style="font-size:.86rem;">
                            <span class="text-suave"><i class="bi bi-people me-1"></i>Aprendices</span>
                            <span class="fw-semibold"><?= (int)$f['aprendices'] ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="modalFicha" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <!-- TODO (backend): action="guardar_ficha.php" method="POST" -->
      <form>
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Ficha y horario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Número de ficha</label>
                    <input type="text" class="form-control" name="numero_ficha" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Programa</label>
                    <input type="text" class="form-control" name="programa" required>
                </div>
            </div>
            <div class="mb-0 mt-3">
                <label class="form-label">Instructor encargado</label>
                <select class="form-select" name="instructor_id">
                    <option>Juan Camilo Vanegas</option>
                    <option>Marcela Ortiz</option>
                    <option>Héctor Ramírez</option>
                </select>
            </div>
            <div class="row g-3 mt-0">
                <div class="col-md-6">
                    <label class="form-label">Hora de entrada</label>
                    <input type="time" class="form-control" name="hora_entrada" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hora de salida</label>
                    <input type="time" class="form-control" name="hora_salida" required>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sena">Guardar ficha</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/scripts.php'; ?>
</body>
</html>
