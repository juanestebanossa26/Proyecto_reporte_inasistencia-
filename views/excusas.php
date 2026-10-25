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
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h2 class="h6 fw-bold mb-0">Anexar nueva excusa</h2>
                            <span class="badge bg-light text-muted border" style="font-size: .75rem;">Paso a paso</span>
                        </div>

                        <form action="<?= BASE_URL ?>/index.php?accion=guardarExcusa" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">

                            <!-- Selección de Fechas -->
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-semibold text-muted mb-0">Fechas a excusar</label>
                                    <?php if (!empty($fechas_faltas)): ?>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btnSeleccionarTodas" style="font-size:.78rem;">Marcar todas</button>
                                    <?php endif; ?>
                                </div>

                                <?php if (empty($fechas_faltas)): ?>
                                    <div class="p-3 text-center bg-light rounded-3 border">
                                        <i class="bi bi-check2-circle text-success fs-3 mb-1 d-block"></i>
                                        <p class="text-suave mb-0 small">No tienes inasistencias pendientes por excusar.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="border rounded-3 p-2" style="max-height:170px; overflow-y:auto; background: #fafbfa;">
                                        <?php foreach ($fechas_faltas as $fila): ?>
                                            <div class="fecha-item-card p-2 mb-2">
                                                <div class="form-check m-0">
                                                    <input class="form-check-input fecha-excusa-check" type="checkbox"
                                                           name="fecha_excusa[]"
                                                           value="<?= htmlspecialchars($fila['ids_asistencia_str'] ?? $fila['id_asistencia'] ?? '') ?>"
                                                           id="fecha_<?= htmlspecialchars($fila['fecha'] ?? '') ?>">
                                                    <label class="form-check-label w-100" for="fecha_<?= htmlspecialchars($fila['fecha'] ?? '') ?>" style="cursor:pointer;">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="fw-bold text-dark" style="font-size:.85rem;">
                                                                <i class="bi bi-calendar-check me-1 text-sena"></i><?= htmlspecialchars($fila['fecha_formateada'] ?? $fila['fecha'] ?? '') ?>
                                                            </span>
                                                            <?php if (!empty($fila['total_bloques']) && $fila['total_bloques'] > 1): ?>
                                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: .68rem;">
                                                                    <i class="bi bi-layers me-1"></i><?= $fila['total_bloques'] ?> Bloques
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($fila['instructores_texto'])): ?>
                                                        <div class="text-truncate text-secondary mt-1" style="font-size: .75rem;" title="<?= htmlspecialchars($fila['instructores_texto']) ?>">
                                                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($fila['instructores_texto']) ?>
                                                        </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($fila['bloques_texto'])): ?>
                                                        <div class="text-muted mt-0" style="font-size: .72rem;">
                                                            <i class="bi bi-clock me-1"></i><?= htmlspecialchars($fila['bloques_texto']) ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted d-block mt-1" style="font-size: .73rem;">
                                        <i class="bi bi-info-circle me-1"></i>Selecciona una o más fechas.
                                    </small>
                                <?php endif; ?>
                            </div>

                            <!-- Motivo compacto -->
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-muted mb-1">Motivo</label>
                                <textarea class="form-control" name="motivo" rows="2" placeholder="Ej: Cita médica EPS, calamidad doméstica..." required style="resize:none; font-size:.85rem;"></textarea>
                            </div>

                            <!-- Dropzone minimalista -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted mb-1">Soporte (PDF)</label>
                                <div class="dropzone-minimal" id="dropzoneBox">
                                    <input type="file" name="archivo" id="archivoInput" class="dropzone-input" accept="application/pdf" required>

                                    <!-- Prompt inicial en una sola fila minimalista -->
                                    <div id="dropzonePrompt" class="d-flex align-items-center justify-content-center gap-2 py-2 px-3 text-center" style="min-height:50px;">
                                        <i class="bi bi-cloud-arrow-up text-sena fs-4"></i>
                                        <div class="text-start">
                                            <div class="fw-semibold text-dark" style="font-size:.82rem; line-height:1.2;">Adjuntar documento PDF</div>
                                            <div class="text-muted" style="font-size:.73rem;">Arrastra aquí o haz clic para examinar</div>
                                        </div>
                                    </div>

                                    <!-- Vista previa al seleccionar archivo -->
                                    <div id="dropzonePreview" class="d-none align-items-center justify-content-between w-100 py-2 px-3 bg-light rounded-3" style="min-height:50px;">
                                        <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-5"></i>
                                            <span id="fileNamePreview" class="fw-semibold text-dark text-truncate" style="font-size:.82rem;">archivo.pdf</span>
                                        </div>
                                        <button type="button" id="btnQuitarArchivo" class="btn btn-sm btn-link text-danger p-0 border-0" title="Quitar archivo" style="z-index: 3;">
                                            <i class="bi bi-x-circle-fill fs-6"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-sena w-100 py-2 fw-semibold" style="font-size:.88rem;">
                                <i class="bi bi-send me-1"></i> Enviar excusa
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card p-3">
                        <h2 class="h6 fw-bold mb-3">Mis excusas enviadas</h2>
                        <table id="tablaExcusas" class="table table-app align-middle mb-0" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="min-width: 100px;">Fecha</th>
                                    <th style="min-width: 140px;">Bloque / Clase</th>
                                    <th style="min-width: 120px;">Motivo</th>
                                    <th style="min-width: 100px;">Estado</th>
                                    <th class="text-end" style="min-width: 110px;" data-orderable="false">Acción</th>
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
                                $tieneObservacion = in_array($estado, ['rechazada', 'devuelta'], true) && !empty($fila['observaciones_instructor']);
                            ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <span class="fw-semibold"><?= htmlspecialchars($fila['fecha']) ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-clock me-1 text-primary"></i><?= htmlspecialchars($fila['bloque_horario']) ?>
                                            </span>
                                        </div>
                                        <small class="text-muted d-block mt-1 text-truncate" style="max-width: 160px;" title="<?= htmlspecialchars($fila['instructor_nombre']) ?>">
                                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($fila['instructor_nombre']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($fila['motivo']) ?>">
                                            <?= htmlspecialchars($fila['motivo']) ?>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <div>
                                            <span class="badge <?= $claseColor ?>">
                                                <?= ucfirst($estado) ?>
                                            </span>
                                        </div>
                                        <?php if ($tieneObservacion): ?>
                                            <div class="mt-2 p-2 rounded small bg-light border border-<?= $estado === 'rechazada' ? 'danger' : 'info' ?>" style="font-size: 0.78rem;">
                                                <strong class="d-block text-<?= $estado === 'rechazada' ? 'danger' : 'primary' ?> mb-1">
                                                    <i class="bi bi-chat-left-dots me-1"></i>Motivo del instructor:
                                                </strong>
                                                <span><?= nl2br(htmlspecialchars($fila['observaciones_instructor'])) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?php if ($sePuedeReenviar): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary border-0 btn-reenviar-excusa"
                                                    title="Editar y reenviar"
                                                    data-id="<?= $fila['id_excusa'] ?>"
                                                    data-motivo="<?= htmlspecialchars($fila['motivo'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-observacion="<?= htmlspecialchars($fila['observaciones_instructor'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-bloque="<?= htmlspecialchars($fila['bloque_horario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-instructor="<?= htmlspecialchars($fila['instructor_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-arrow-repeat"></i> Reenviar
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

            <div id="reenvio_obs_container" class="alert alert-warning py-2 px-3 small mb-3 d-none">
                <div class="fw-bold mb-1">
                    <i class="bi bi-exclamation-triangle me-1"></i>Observación del instructor:
                </div>
                <div id="reenvio_obs_texto" class="fst-italic text-dark"></div>
            </div>

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
<script src="<?= BASE_URL ?>/assets/js/datatables-es.js"></script>

<!-- Inicialización -->
<script src="<?= BASE_URL ?>/assets/js/subirexcusa.js?v=<?= file_exists(BASE_PATH . '/assets/js/subirexcusa.js') ? filemtime(BASE_PATH . '/assets/js/subirexcusa.js') : '1.0' ?>"></script>
</body>
</html>
