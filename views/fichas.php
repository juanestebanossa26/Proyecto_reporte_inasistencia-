<?php
/**
 * views/fichas.php — Gestión Integral del Ciclo de Vida de Fichas de Formación
 * Sistema de Control de Ingreso y Asistencia RFID · SENA
 */
$rolActivo     = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'fichas';
$tituloPagina  = 'Fichas de Formación';

$fichas       = $fichas ?? [];
$instructores = $instructores ?? [];
$conteos      = $conteos ?? ['total' => 0, 'activas' => 0, 'finalizadas' => 0, 'archivadas' => 0];
$estadoFiltro = $estadoFiltro ?? 'Activa';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/fichas.css?v=<?= file_exists(BASE_PATH . "/assets/css/fichas.css") ? filemtime(BASE_PATH . "/assets/css/fichas.css") : "1.0" ?>">
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content p-4">
            <!-- Encabezado -->
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
                <div>
                    <div class="eyebrow text-uppercase fw-semibold" style="letter-spacing: 0.05em; font-size: 0.8rem; color: #1b5e20;">Ciclo de Vida Académico</div>
                    <h1 class="h4 fw-bold mb-1">Fichas de Formación</h1>
                    <p class="text-suave mb-0">Gestiona los estados operativos, finalización lectiva, archivo histórico y asignaciones académicas del SENA.</p>
                </div>
                <?php if ($rolActivo === 'Administrador'): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sena" onclick="abrirModalCrearFicha()">
                        <i class="bi bi-plus-lg me-1"></i> Nueva Ficha
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Resumen de Métricas de Ciclo de Vida -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Fichas Activas</small>
                                <h3 class="fw-bold mb-0 text-success"><?= $conteos['activas'] ?></h3>
                                <small class="text-muted">En formación lectiva</small>
                            </div>
                            <div class="rounded-circle bg-success-subtle p-3 text-success">
                                <i class="bi bi-play-circle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Fichas Finalizadas</small>
                                <h3 class="fw-bold mb-0 text-warning"><?= $conteos['finalizadas'] ?></h3>
                                <small class="text-muted">Ciclo concluido</small>
                            </div>
                            <div class="rounded-circle bg-warning-subtle p-3 text-warning">
                                <i class="bi bi-check2-circle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Fichas Archivadas</small>
                                <h3 class="fw-bold mb-0 text-secondary"><?= $conteos['archivadas'] ?></h3>
                                <small class="text-muted">Memoria histórica</small>
                            </div>
                            <div class="rounded-circle bg-secondary-subtle p-3 text-secondary">
                                <i class="bi bi-archive fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Total Fichas</small>
                                <h3 class="fw-bold mb-0 text-primary"><?= $conteos['total'] ?></h3>
                                <small class="text-muted">Registradas en sistema</small>
                            </div>
                            <div class="rounded-circle bg-primary-subtle p-3 text-primary">
                                <i class="bi bi-folder2 fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navegación de Estados (Tabs) -->
            <ul class="nav nav-tabs nav-tabs-sena mb-3 border-bottom">
                <li class="nav-item">
                    <a class="nav-link <?= $estadoFiltro === 'Activa' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?accion=fichas&estado=Activa">
                        <i class="bi bi-play-circle-fill text-success me-1"></i> Activas 
                        <span class="badge bg-success-subtle text-success rounded-pill ms-1"><?= $conteos['activas'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $estadoFiltro === 'Finalizada' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?accion=fichas&estado=Finalizada">
                        <i class="bi bi-check-circle-fill text-warning me-1"></i> Finalizadas 
                        <span class="badge bg-warning-subtle text-warning rounded-pill ms-1"><?= $conteos['finalizadas'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $estadoFiltro === 'Archivada' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?accion=fichas&estado=Archivada">
                        <i class="bi bi-archive-fill text-secondary me-1"></i> Archivadas 
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1"><?= $conteos['archivadas'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $estadoFiltro === 'todos' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?accion=fichas&estado=todos">
                        <i class="bi bi-list-ul me-1"></i> Todas las Fichas
                    </a>
                </li>
              <!-- Barra de Búsqueda y Herramientas -->
            <div class="ficha-toolbar-card d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="ficha-search-box">
                    <i class="bi bi-search ficha-search-icon"></i>
                    <input type="text" id="inputBuscarFicha" class="form-control ficha-search-input" placeholder="Buscar por número de ficha, programa o instructor..." autocomplete="off">
                    <button type="button" id="btnLimpiarBusqueda" class="ficha-search-clear" title="Limpiar búsqueda">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2 text-muted small">
                    <span>Mostrando <strong id="contadorFichasMostradas" class="text-dark"><?= count($fichas) ?></strong> de <strong class="text-dark"><?= count($fichas) ?></strong> fichas</span>
                </div>
            </div>

            <!-- Cuadrícula de Tarjetas de Fichas (Grid) -->
            <div class="fichas-grid-container mb-4">
                <div class="fichas-grid" id="contenedorFichasGrid">
                    <?php if (empty($fichas)): ?>
                        <div class="ficha-empty-state text-center">
                            <div class="empty-icon-circle mx-auto mb-3">
                                <i class="bi bi-folder-x fs-1 text-muted"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-1">No hay fichas registradas</h5>
                            <p class="text-muted small mb-3">No se encontraron fichas en el estado <strong><?= htmlspecialchars($estadoFiltro) ?></strong>.</p>
                            <?php if ($rolActivo === 'Administrador' && $estadoFiltro === 'Activa'): ?>
                            <button type="button" class="btn btn-sm btn-sena" onclick="abrirModalCrearFicha()">
                                <i class="bi bi-plus-lg me-1"></i> Registrar Primera Ficha
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($fichas as $f): 
                            $badgeClase = match($f['estado']) {
                                'Activa'     => 'badge-estado-activa',
                                'Finalizada' => 'badge-estado-finalizada',
                                'Archivada'  => 'badge-estado-archivada',
                                default      => 'badge-estado-activa'
                            };
                            $badgeIcono = match($f['estado']) {
                                'Activa'     => 'bi-play-circle',
                                'Finalizada' => 'bi-check2-circle',
                                'Archivada'  => 'bi-archive',
                                default      => 'bi-play-circle'
                            };
                            $instTexto = trim($f['instructores'] ?? '');
                        ?>
                            <div class="ficha-card card-estado-<?= strtolower($f['estado']) ?>"
                                 data-numero="<?= strtolower(htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8')) ?>"
                                 data-programa="<?= strtolower(htmlspecialchars($f['nombre_programa'], ENT_QUOTES, 'UTF-8')) ?>"
                                 data-instructor="<?= strtolower(htmlspecialchars($instTexto, ENT_QUOTES, 'UTF-8')) ?>"
                                 data-estado="<?= strtolower(htmlspecialchars($f['estado'], ENT_QUOTES, 'UTF-8')) ?>">
                                
                                <!-- Encabezado de la Tarjeta -->
                                <div class="ficha-card-header">
                                    <div class="ficha-numero-badge" title="Número de ficha SENA">
                                        <i class="bi bi-folder2-open text-success"></i>
                                        <span><?= htmlspecialchars($f['numero_ficha']) ?></span>
                                    </div>
                                    <span class="badge <?= $badgeClase ?> px-2 py-1">
                                        <i class="bi <?= $badgeIcono ?> me-1"></i><?= htmlspecialchars($f['estado']) ?>
                                    </span>
                                </div>

                                <!-- Cuerpo / Información Principal -->
                                <div class="ficha-card-body">
                                    <h3 class="ficha-programa-titulo" title="<?= htmlspecialchars($f['nombre_programa']) ?>">
                                        <?= htmlspecialchars($f['nombre_programa']) ?>
                                    </h3>

                                    <!-- Instructor Asignado -->
                                    <div class="ficha-instructor-info">
                                        <?php if ($instTexto !== ''): ?>
                                            <i class="bi bi-person-badge text-primary flex-shrink-0"></i>
                                            <span class="text-truncate" title="Instructor(es): <?= htmlspecialchars($instTexto) ?>">
                                                <?= htmlspecialchars($instTexto) ?>
                                            </span>
                                        <?php else: ?>
                                            <i class="bi bi-person-x text-muted flex-shrink-0"></i>
                                            <span class="text-muted fst-italic">Sin instructor asignado</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Metadatos de Finalización / Archivado -->
                                    <?php if (!empty($f['fecha_finalizacion']) && ($f['estado'] === 'Finalizada' || $f['estado'] === 'Archivada' || $estadoFiltro === 'todos')): ?>
                                    <div class="ficha-meta-cierre">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="text-muted"><i class="bi bi-calendar-check text-warning me-1"></i>Finalizada:</span>
                                            <strong class="text-dark"><?= htmlspecialchars(substr((string)$f['fecha_finalizacion'], 0, 10)) ?></strong>
                                        </div>
                                        <?php if (!empty($f['responsable_finalizacion'])): ?>
                                        <div class="small text-muted text-truncate" title="Por: <?= htmlspecialchars($f['responsable_finalizacion']) ?>">
                                            Por: <?= htmlspecialchars($f['responsable_finalizacion']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($f['fecha_archivado']) && ($f['estado'] === 'Archivada' || $estadoFiltro === 'todos')): ?>
                                    <div class="ficha-meta-cierre border-secondary">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="text-muted"><i class="bi bi-archive text-secondary me-1"></i>Archivada:</span>
                                            <strong class="text-secondary"><?= htmlspecialchars(substr((string)$f['fecha_archivado'], 0, 10)) ?></strong>
                                        </div>
                                        <?php if (!empty($f['responsable_archivado'])): ?>
                                        <div class="small text-muted text-truncate" title="Por: <?= htmlspecialchars($f['responsable_archivado']) ?>">
                                            Por: <?= htmlspecialchars($f['responsable_archivado']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Métricas e Indicadores -->
                                    <div class="ficha-metricas-row">
                                        <div class="ficha-metrica-chip" title="Total de aprendices vinculados">
                                            <i class="bi bi-people-fill text-success"></i>
                                            <span><?= (int)($f['total_aprendices'] ?? 0) ?> aprendices</span>
                                        </div>
                                        <div class="ficha-metrica-chip" title="Horarios: <?= (int)($f['horarios_programados'] ?? 0) ?> programados | <?= (int)($f['horarios_cancelados'] ?? 0) ?> cancelados">
                                            <i class="bi bi-calendar-week text-primary"></i>
                                            <span><?= (int)($f['total_horarios'] ?? 0) ?> horarios</span>
                                            <?php if ((int)($f['horarios_cancelados'] ?? 0) > 0): ?>
                                                <span class="badge bg-danger rounded-pill" style="font-size:0.65rem;" title="Cancelados">
                                                    <?= (int)$f['horarios_cancelados'] ?> canc.
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Acciones y Botones Operativos -->
                                <div class="ficha-card-actions">
                                    <div class="d-flex align-items-center gap-1">
                                        <!-- Botón Ver Historial / Bitácora de Auditoría -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-info ficha-action-btn btn-ver-auditoria" 
                                                title="Consultar bitácora de auditoría"
                                                data-id="<?= $f['id_ficha'] ?>"
                                                data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi bi-journal-text me-1"></i><span class="d-none d-sm-inline">Historial</span>
                                        </button>

                                        <!-- Ver horarios en calendario (solo si activa) -->
                                        <?php if ($f['estado'] === 'Activa'): ?>
                                        <a href="<?= BASE_URL ?>/index.php?accion=horarios" 
                                           class="btn btn-sm btn-outline-primary ficha-action-btn" 
                                           title="Ver horarios en calendario">
                                            <i class="bi bi-calendar3 me-1"></i><span class="d-none d-sm-inline">Horarios</span>
                                        </a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex align-items-center gap-1 ms-auto">
                                        <!-- Acciones para ficha ACTIVA -->
                                        <?php if ($f['estado'] === 'Activa' && $rolActivo === 'Administrador'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-secondary ficha-action-btn btn-editar-ficha" 
                                                    title="Editar Ficha"
                                                    data-id="<?= $f['id_ficha'] ?>"
                                                    data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-programa="<?= htmlspecialchars($f['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-instructor="<?= (int)($f['id_instructor'] ?? 0) ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-warning ficha-action-btn btn-finalizar-ficha" 
                                                    title="Finalizar etapa lectiva de formación"
                                                    data-id="<?= $f['id_ficha'] ?>"
                                                    data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-programa="<?= htmlspecialchars($f['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-aprendices="<?= (int)($f['total_aprendices'] ?? 0) ?>"
                                                    data-horarios="<?= (int)($f['total_horarios'] ?? 0) ?>">
                                                <i class="bi bi-check2-circle text-warning"></i>
                                            </button>

                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger ficha-action-btn btn-eliminar-ficha" 
                                                    title="Eliminar Ficha (Solo si fue creada por error y sin registros)"
                                                    data-id="<?= $f['id_ficha'] ?>"
                                                    data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-aprendices="<?= (int)($f['total_aprendices'] ?? 0) ?>"
                                                    data-horarios="<?= (int)($f['total_horarios'] ?? 0) ?>">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Acciones para ficha FINALIZADA -->
                                        <?php if ($f['estado'] === 'Finalizada' && $rolActivo === 'Administrador'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-secondary ficha-action-btn btn-archivar-ficha" 
                                                    title="Archivar ficha para custodia histórica"
                                                    data-id="<?= $f['id_ficha'] ?>"
                                                    data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-programa="<?= htmlspecialchars($f['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-archive text-secondary me-1"></i>Archivar
                                            </button>

                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-success ficha-action-btn btn-reabrir-ficha" 
                                                    title="Reapertura administrativa excepcional"
                                                    data-id="<?= $f['id_ficha'] ?>"
                                                    data-numero="<?= htmlspecialchars($f['numero_ficha'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-arrow-counterclockwise text-success"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Ficha ARCHIVADA -->
                                        <?php if ($f['estado'] === 'Archivada'): ?>
                                            <span class="badge bg-secondary-subtle text-secondary small py-1 px-2 border" title="Ficha en custodia histórica">
                                                <i class="bi bi-lock me-1"></i>Custodiada
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Estado vacío para búsqueda sin coincidencias -->
                    <div id="fichaSearchEmptyState" class="ficha-empty-state text-center d-none">
                        <div class="empty-icon-circle mx-auto mb-3">
                            <i class="bi bi-search fs-2 text-muted"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Sin resultados de búsqueda</h5>
                        <p class="text-muted small mb-0">No se encontraron fichas que coincidan con el término ingresado. Intenta con otro número o nombre.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALES DEL CICLO DE VIDA DE FICHAS -->
<!-- ========================================================================= -->

<!-- 1. Modal: Crear / Editar Ficha -->
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
                <label class="form-label fw-semibold">Número de Ficha <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="numero_ficha" id="form_numero_ficha" required maxlength="20" minlength="3" pattern="[a-zA-Z0-9\-_]{3,20}" placeholder="Ej. 3064749" autocomplete="off">
                <div class="form-text">Código único de la ficha académica (3 a 20 caracteres, sin espacios ni caracteres especiales).</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Programa de Formación <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="nombre_programa" id="form_nombre_programa" required minlength="3" maxlength="150" placeholder="Ej. ANÁLISIS Y DESARROLLO DE SOFTWARE">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Instructor encargado (Opcional)</label>
                <select class="form-select" name="id_instructor" id="form_id_instructor">
                    <option value="0">Seleccionar instructor...</option>
                    <?php foreach ($instructores as $inst): ?>
                        <option value="<?= $inst['id_usuario'] ?>"><?= htmlspecialchars($inst['nombre_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Asocia un instructor líder de referencia a esta ficha.</div>
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

<!-- 2. Modal: Finalizar Ficha -->
<div class="modal fade" id="modalFinalizarFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form id="formFinalizarFicha" action="<?= BASE_URL ?>/index.php?accion=finalizarFicha" method="POST">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <input type="hidden" name="id_ficha" id="finalizar_id_ficha" value="0">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-warning">
            <i class="bi bi-check2-circle text-warning me-2"></i>Finalizar Formación Lectiva
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="alert alert-warning border-0 small mb-3">
                <i class="bi bi-info-circle-fill me-1"></i>
                Esta operación finalizará el ciclo formativo de la ficha <strong id="finalizar_numero_ficha"></strong>.
                <ul class="mb-0 mt-2 ps-3">
                    <li>Cancela automáticamente los horarios futuros a partir de la fecha de corte.</li>
                    <li>Excluye la ficha de la generación de futuras inasistencias.</li>
                    <li>Cierra las vinculaciones de los aprendices y recalcula su acceso sin destruir historiales.</li>
                    <li><strong>Conserva intactas</strong> todas las asistencias, excusas médicas y horarios pasados.</li>
                </ul>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Fecha efectiva de finalización</label>
                <input type="date" class="form-control" name="fecha_efectiva" id="finalizar_fecha_efectiva" value="<?= date('Y-m-d') ?>">
                <div class="form-text">Las sesiones posteriores a esta fecha quedarán canceladas.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Motivo de finalización <span class="text-danger">*</span></label>
                <textarea class="form-control" name="motivo_finalizacion" id="finalizar_motivo" rows="2" required placeholder="Ej. Culminación exitosa de la etapa lectiva institucional"></textarea>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning text-dark fw-semibold" id="btnConfirmarFinalizar">Confirmar Finalización</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 3. Modal: Archivar Ficha -->
<div class="modal fade" id="modalArchivarFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form id="formArchivarFicha" action="<?= BASE_URL ?>/index.php?accion=archivarFicha" method="POST">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <input type="hidden" name="id_ficha" id="archivar_id_ficha" value="0">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-secondary">
            <i class="bi bi-archive-fill text-secondary me-2"></i>Archivar Ficha Histórica
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p class="text-muted small">
                La ficha <strong id="archivar_numero_ficha"></strong> pasará a custodia en el archivo histórico general.
                Se excluirá de los listados y selectores operativos cotidianos, pero sus registros de asistencia y reportes
                permanecerán consultables permanentemente para auditorías.
            </p>
            <div class="mb-3">
                <label class="form-label fw-semibold">Motivo del archivado (Opcional)</label>
                <input type="text" class="form-control" name="motivo_archivado" id="archivar_motivo" placeholder="Ej. Cierre de expediente institucional anual">
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-secondary" id="btnConfirmarArchivar">Archivar Ficha</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 4. Modal: Reapertura Excepcional -->
<div class="modal fade" id="modalReabrirFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form id="formReabrirFicha" action="<?= BASE_URL ?>/index.php?accion=reabrirFicha" method="POST">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <input type="hidden" name="id_ficha" id="reabrir_id_ficha" value="0">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-success">
            <i class="bi bi-arrow-counterclockwise text-success me-2"></i>Reapertura Excepcional de Ficha
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="alert alert-info border-0 small mb-3">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Esta operación reactivará la ficha <strong id="reabrir_numero_ficha"></strong> para permitir nuevas actividades.
                Requiere un motivo explícito que quedará registrado en la bitácora de auditoría.
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Motivo justificado de reapertura <span class="text-danger">*</span></label>
                <textarea class="form-control" name="motivo_reapertura" id="reabrir_motivo" rows="2" required minlength="8" placeholder="Ej. Corrección por ampliación del calendario lectivo autorizada"></textarea>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success" id="btnConfirmarReabrir">Reabrir Ficha</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 5. Modal: Bitácora de Auditoría -->
<div class="modal fade" id="modalAuditoriaFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-journal-text text-info me-2"></i>Bitácora de Auditoría · Ficha <span id="audit_num_ficha"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3" style="max-height: 480px; overflow-y: auto;">
        <div id="audit_loading" class="text-center py-4">
            <div class="spinner-border text-success" role="status"></div>
            <p class="text-muted small mt-2">Cargando bitácora de auditoría...</p>
        </div>
        <div id="audit_contenido" style="display: none;">
            <!-- Contenedor dinámico de eventos de auditoría -->
        </div>
        <div id="audit_vacio" class="text-center py-4 text-muted small" style="display: none;">
            <i class="bi bi-info-circle fs-3 d-block mb-1"></i>
            No se registran eventos de auditoría para esta ficha.
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Formulario Oculto para Eliminar Ficha con CSRF -->
<form id="formEliminarFichaOculto" action="<?= BASE_URL ?>/index.php?accion=eliminarFicha" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
    <input type="hidden" name="id_ficha" id="inputEliminarFichaId" value="0">
    <input type="hidden" name="motivo_eliminacion" id="inputEliminarFichaMotivo" value="">
</form>

<?php include 'includes/scripts.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>


<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/datatables-es.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="<?= BASE_URL ?>/assets/js/fichas.js?v=<?= file_exists(BASE_PATH . "/assets/js/fichas.js") ? filemtime(BASE_PATH . "/assets/js/fichas.js") : "1.0" ?>"></script>
</body>
</html>