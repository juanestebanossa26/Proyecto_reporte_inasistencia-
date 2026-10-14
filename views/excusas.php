<?php
// Esta vista ya no consulta la base de datos por su cuenta.
// ExcusaControlador::listar() ya dejó listas las variables $misExcusas
// y $fechas_faltas antes de hacer require de este archivo (ver index.php).

$rolActivo     = 'aprendiz';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'excusas';
$tituloPagina  = 'Excusas médicas';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>

    <!-- CSS de DataTables inyectado directamente para que funcione de inmediato -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
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

                        <form action="<?= BASE_URL ?>/index.php?accion=guardarExcusa" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">

                            <div class="mb-3">
                                <label class="form-label d-flex justify-content-between align-items-center">
                                    <span>Fechas que cubre esta excusa</span>
                                    <?php if (!empty($fechas_faltas)): ?>
                                    <button type="button" class="btn btn-link btn-sm p-0" id="btnSeleccionarTodas">Marcar todas</button>
                                    <?php endif; ?>
                                </label>

                                <?php if (empty($fechas_faltas)): ?>
                                    <p class="text-suave mb-0 small">No tienes inasistencias pendientes por excusar.</p>
                                <?php else: ?>
                                    <div class="border rounded-3 p-2" style="max-height:220px; overflow-y:auto; background: #fafbfa;">
                                        <?php foreach ($fechas_faltas as $fila): ?>
                                            <div class="p-2 mb-2 bg-white rounded-3 border">
                                                <div class="form-check">
                                                    <input class="form-check-input fecha-excusa-check" type="checkbox"
                                                           name="fecha_excusa[]"
                                                           value="<?= htmlspecialchars($fila['ids_asistencia_str'] ?? $fila['id_asistencia'] ?? '') ?>"
                                                           id="fecha_<?= htmlspecialchars($fila['fecha'] ?? '') ?>">
                                                    <label class="form-check-label w-100" for="fecha_<?= htmlspecialchars($fila['fecha'] ?? '') ?>" style="cursor:pointer;">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="fw-bold text-dark"><i class="bi bi-calendar-event me-1 text-sena"></i><?= htmlspecialchars($fila['fecha_formateada'] ?? $fila['fecha'] ?? '') ?></span>
                                                            <?php if (!empty($fila['total_bloques']) && $fila['total_bloques'] > 1): ?>
                                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: .7rem;">
                                                                    <i class="bi bi-layers me-1"></i><?= $fila['total_bloques'] ?> Bloques
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($fila['instructores_texto'])): ?>
                                                        <div class="small text-muted mt-1" style="font-size: .78rem;">
                                                            <i class="bi bi-person-badge text-secondary me-1"></i>
                                                            <span class="fw-semibold text-dark">Instructor(es):</span> <?= htmlspecialchars($fila['instructores_texto']) ?>
                                                        </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($fila['bloques_texto'])): ?>
                                                        <div class="text-suave mt-1" style="font-size: .72rem;">
                                                            <i class="bi bi-clock me-1"></i><?= htmlspecialchars($fila['bloques_texto']) ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-suave">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Elige la fecha de la falta. Si ese día hubo dos o más bloques, quedará vinculada automáticamente a los instructores correspondientes.
                                    </small>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Motivo</label>
                                <textarea class="form-control" name="motivo" rows="3" placeholder="Ej: Cita médica EPS" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Soporte (PDF)</label>
                                <div class="dropzone-excusa">
                                    <i class="bi bi-cloud-arrow-up text-sena" style="font-size:1.6rem;"></i>
                                    <p class="mb-1 mt-2 fw-semibold" style="font-size:.9rem;">Arrastra tu archivo aquí</p>
                                    <p class="text-suave mb-2" style="font-size:.8rem;">o haz clic para seleccionarlo</p>
                                    <input type="file" name="archivo" class="form-control" accept="application/pdf">
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
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Motivo</th>
                                        <th>Estado</th>
                                        <th class="text-end" data-orderable="false">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                foreach ($misExcusas as $fila) {
                                    $estado = strtolower($fila['estado_excusa']);
                                    $claseColor = '';

                                    switch ($estado) {
                                        case 'aprobada':
                                            $claseColor = 'bg-success text-white';
                                            break;
                                        case 'rechazada':
                                            $claseColor = 'bg-danger text-white';
                                            break;
                                        case 'devuelta':
                                            $claseColor = 'bg-info text-dark';
                                            break;
                                        case 'pendiente':
                                            $claseColor = 'bg-warning text-dark';
                                            break;
                                        default:
                                            $claseColor = 'bg-secondary text-white';
                                            break;
                                    }

                                    // Solo se puede editar/reenviar si algún instructor la
                                    // rechazó o la devolvió para corrección.
                                    $sePuedeReenviar = in_array($estado, ['rechazada', 'devuelta'], true);
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($fila['fecha']) ?></td>
                                        <td><?= htmlspecialchars($fila['motivo']) ?></td>
                                        <td>
                                            <span class="badge <?= $claseColor ?>">
                                                <?= ucfirst($estado) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($sePuedeReenviar): ?>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-secondary border-0 btn-reenviar-excusa"
                                                        title="Editar y reenviar"
                                                        data-id="<?= $fila['id_excusa'] ?>"
                                                        data-motivo="<?= htmlspecialchars($fila['motivo'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="bi bi-arrow-repeat"></i> Editar y reenviar
                                                </button>
                                            <?php endif; ?>
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

<!-- Modal para editar y reenviar una excusa devuelta/rechazada.
     Reenvía a la MISMA excusa (id_excusa oculto), nunca crea una nueva. -->
<div class="modal fade" id="modalReenviarExcusa" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="<?= BASE_URL ?>/index.php?accion=reenviarExcusa" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">
        <input type="hidden" name="id_excusa" id="reenvio_id_excusa">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Editar y reenviar excusa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p class="text-suave small">
                Corrige el motivo y/o adjunta un nuevo soporte. Se reenviará como la misma
                excusa, no se crea un envío aparte.
            </p>
            <div class="mb-3">
                <label class="form-label">Motivo</label>
                <textarea class="form-control" name="motivo" id="reenvio_motivo" rows="3" required></textarea>
            </div>
            <div class="mb-0">
                <label class="form-label">Nuevo soporte (PDF)</label>
                <input type="file" name="archivo" class="form-control" accept="application/pdf" required>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sena">Reenviar excusa</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/scripts.php'; ?>

<!-- Dependencias JS de DataTables y jQuery inyectadas directamente -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Inicialización -->
<script src="<?= BASE_URL ?>/assets/js/subirexcusa.js"></script>
</body>
</html>
