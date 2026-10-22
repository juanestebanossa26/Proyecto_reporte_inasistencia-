<?php
/**
 * horarios.php — Módulo de Horarios y Programación Académica
 * Sistema de Control de Ingreso de Aprendices · SENA
 */
$rolActivo     = $_SESSION['rol'] ?? 'Administrador';
$esInstructor  = (strtolower($rolActivo) === 'instructor');
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'horarios';
$tituloPagina  = $esInstructor ? 'Mi Calendario de Horarios' : 'Horarios y Calendario';

// Datos suministrados desde la base de datos por HorarioControlador
$eventosCalendario = $eventosCalendario ?? [];
$instructores      = $instructores ?? [];
$fichas            = $fichas ?? [];
$coloresFicha      = $coloresFicha ?? [];
$resumenJornada    = $resumenJornada ?? [
    'total_clases_hoy' => 0,
    'total_horas_hoy'  => 0,
    'total_fichas'     => count($fichas),
    'proxima_clase'    => null,
    'fecha_hoy'        => date('Y-m-d')
];

// Iniciales para el avatar del topbar si no estuvieran definidas
if (!isset($iniciales)) {
    $iniciales = '';
    foreach (explode(' ', $nombreUsuario) as $parte) {
        if ($parte !== '') $iniciales .= strtoupper($parte[0]);
        if (strlen($iniciales) >= 2) break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>
    
    <!-- FullCalendar 6 Bundle CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    
    <!-- Estilos específicos de Horarios integrados con el SENA -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/horarios.css?v=<?= time() ?>">
</head>
<body>
<div class="app-shell">
    <!-- Menú Lateral Institucional Verde Oscuro SENA -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Contenido Principal -->
    <div class="app-main">
        <!-- Encabezado Superior Blanco del Sistema -->
        <?php include 'includes/topbar.php'; ?>

        <main class="app-content p-4">
            <!-- Encabezado de la Sección -->
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
                <div>
                    <div class="eyebrow text-uppercase text-sena fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08em;">
                        <?= $esInstructor ? 'Portal del Instructor' : 'Gestión Académica' ?>
                    </div>
                    <h1 class="h4 fw-bold mb-0 text-dark">
                        <?= $esInstructor ? 'Mi Calendario de Horarios' : 'Horarios y Calendario' ?>
                    </h1>
                    <p class="text-suave mb-0 small">
                        <?= $esInstructor 
                            ? 'Consulta tu agenda académica, fichas a cargo y programación de clases en tiempo real.' 
                            : 'Consulta, programa y gestiona los horarios académicos de las fichas e instructores.' ?>
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($rolActivo === 'Administrador'): ?>
                        <button type="button" class="btn btn-outline-success d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalImportarExcel">
                            <i class="bi bi-file-earmark-excel"></i>
                            <span>Importar Excel</span>
                        </button>
                        <button type="button" class="btn btn-sena d-flex align-items-center gap-2" onclick="abrirModalCrear()">
                            <i class="bi bi-plus-lg"></i>
                            <span>Nuevo horario</span>
                        </button>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/index.php?accion=validarExcusas" class="btn btn-outline-success d-flex align-items-center gap-2" title="Revisar excusas médicas de aprendices">
                            <i class="bi bi-file-earmark-medical"></i>
                            <span>Validar excusas</span>
                        </a>
                        <button type="button" class="btn btn-sena d-flex align-items-center gap-2" onclick="irAHoy()" title="Ir al día actual">
                            <i class="bi bi-calendar2-check"></i>
                            <span>Ir a Hoy</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resumen de la Jornada: Exclusivo para el Instructor -->
            <?php if ($esInstructor): ?>
            <div class="cal-summary-bar p-3 bg-white mb-3" id="resumenJornadaBar">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 pb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-white fw-bold px-2 py-1" style="background-color: var(--cal-verde-sena); font-size: 0.75rem;">
                            <i class="bi bi-speedometer2 me-1"></i>Resumen de la Jornada
                        </span>
                        <span class="small text-muted fw-medium" id="resumenFechaLabel">
                            Hoy · <?= date('d/m/Y') ?>
                        </span>
                    </div>
                    <div>
                        <?php if ($resumenJornada['total_clases_hoy'] > 0): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small" id="resumenEstadoBadge">
                                <i class="bi bi-check-circle-fill me-1"></i><?= $resumenJornada['total_clases_hoy'] ?> clase(s) programada(s) hoy
                            </span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted border px-2 py-1 small" id="resumenEstadoBadge">
                                <i class="bi bi-sun me-1 text-warning"></i>Sin clases programadas para hoy
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-2 align-items-stretch">
                    <!-- Tarjeta 1: Próxima Clase (Interactiva / Clickeable para ver detalles y aprendices) -->
                    <div class="col-12 col-sm-6 col-lg-3 min-w-0" style="min-width: 0;">
                        <div class="cal-summary-box cal-summary-box-clickable h-100" 
                             onclick="abrirDetalleProximaClase()" 
                             role="button" 
                             tabindex="0"
                             style="min-width: 0; max-width: 100%; overflow: hidden;"
                             title="Clic para ver detalles de la ficha, horario y aprendices matriculados">
                            <div class="cal-summary-icon bg-success-subtle text-success flex-shrink-0">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div class="cal-summary-content min-w-0" style="min-width: 0; max-width: 100%; overflow: hidden; flex: 1 1 0%;">
                                <div class="d-flex align-items-center justify-content-between gap-1 mb-1 min-w-0">
                                    <div class="cal-summary-label mb-0 text-truncate" style="min-width: 0;">Próxima clase</div>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-1 flex-shrink-0" style="font-size: 0.62rem;">
                                        <i class="bi bi-eye-fill me-1"></i>Ver
                                    </span>
                                </div>
                                <div class="cal-summary-val text-truncate" id="sumProximaHora" style="max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php if ($resumenJornada['proxima_clase']): ?>
                                        <?= substr($resumenJornada['proxima_clase']['hora_entrada_programada'], 0, 5) ?> – <?= substr($resumenJornada['proxima_clase']['hora_salida_programada'], 0, 5) ?>
                                    <?php else: ?>
                                        <span class="text-muted fw-normal fs-6">Sin pendientes</span>
                                    <?php endif; ?>
                                </div>
                                <?php 
                                    $proxProg = $resumenJornada['proxima_clase'] ? htmlspecialchars(html_entity_decode($resumenJornada['proxima_clase']['nombre_programa'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
                                    $proxFicha = $resumenJornada['proxima_clase'] ? htmlspecialchars($resumenJornada['proxima_clase']['numero_ficha'] ?? '') : '';
                                ?>
                                <div class="cal-summary-sub text-truncate" id="sumProximaFicha" style="max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= $resumenJornada['proxima_clase'] ? ($proxFicha . ' · ' . $proxProg) : '' ?>">
                                    <?php if ($resumenJornada['proxima_clase']): ?>
                                        Ficha <?= $proxFicha ?> · <?= $proxProg ?>
                                    <?php else: ?>
                                        No hay sesiones próximas registradas
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta 2: Clases de hoy -->
                    <div class="col-12 col-sm-6 col-lg-3 min-w-0" style="min-width: 0;">
                        <div class="cal-summary-box h-100" style="min-width: 0; max-width: 100%; overflow: hidden;">
                            <div class="cal-summary-icon bg-primary-subtle text-primary flex-shrink-0">
                                <i class="bi bi-calendar2-week"></i>
                            </div>
                            <div class="cal-summary-content min-w-0" style="min-width: 0; max-width: 100%; overflow: hidden; flex: 1 1 0%;">
                                <div class="cal-summary-label text-truncate">Clases de hoy</div>
                                <div class="cal-summary-val text-truncate" id="sumTotalClases" style="max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= $resumenJornada['total_clases_hoy'] ?> <span class="fs-6 fw-normal text-muted">sesiones</span>
                                </div>
                                <div class="cal-summary-sub text-truncate" id="sumTotalClasesSub" style="max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= $resumenJornada['total_clases_hoy'] > 0 ? 'En la jornada actual' : 'Jornada libre de clases hoy' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta 3: Horas programadas -->
                    <div class="col-12 col-sm-6 col-lg-3 min-w-0" style="min-width: 0;">
                        <div class="cal-summary-box h-100" style="min-width: 0; max-width: 100%; overflow: hidden;">
                            <div class="cal-summary-icon bg-warning-subtle text-warning-emphasis flex-shrink-0">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div class="cal-summary-content min-w-0" style="min-width: 0; max-width: 100%; overflow: hidden; flex: 1 1 0%;">
                                <div class="cal-summary-label text-truncate">Horas programadas</div>
                                <div class="cal-summary-val text-truncate" id="sumTotalHoras" style="max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= $resumenJornada['total_horas_hoy'] ?> <span class="fs-6 fw-normal text-muted">horas</span>
                                </div>
                                <div class="cal-summary-sub text-truncate" style="max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= $resumenJornada['total_horas_hoy'] > 0 ? 'Dedicación acumulada de hoy' : 'Sin horas asignadas hoy' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta 4: Fichas activas -->
                    <div class="col-12 col-sm-6 col-lg-3 min-w-0" style="min-width: 0;">
                        <div class="cal-summary-box h-100" style="min-width: 0; max-width: 100%; overflow: hidden;">
                            <div class="cal-summary-icon bg-info-subtle text-info-emphasis flex-shrink-0">
                                <i class="bi bi-journal-bookmark"></i>
                            </div>
                            <div class="cal-summary-content min-w-0" style="min-width: 0; max-width: 100%; overflow: hidden; flex: 1 1 0%;">
                                <div class="cal-summary-label text-truncate">Fichas activas</div>
                                <div class="cal-summary-val text-truncate" id="sumTotalFichas" style="max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= $resumenJornada['total_fichas'] ?> <span class="fs-6 fw-normal text-muted">fichas</span>
                                </div>
                                <div class="cal-summary-sub text-truncate" style="max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    Con programación activa
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tarjeta Contenedora Principal del Calendario -->
            <div class="cal-card overflow-hidden bg-white mb-4">
                <!-- Barra de Herramientas Compacta del Calendario -->
                <div class="cal-toolbar p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <!-- Navegación por fechas y Título -->
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-medium" id="btnToday" title="Volver al día de hoy">Hoy</button>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-light border rounded-circle p-0 cal-nav-btn me-1" id="btnPrev" title="Periodo anterior">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light border rounded-circle p-0 cal-nav-btn" id="btnNext" title="Periodo siguiente">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <h2 class="cal-period-title mb-0 fw-bold ms-2" id="calendarTitle">Cargando...</h2>
                    </div>

                    <!-- Búsqueda rápida de fichas o programas -->
                    <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 380px;">
                        <div class="position-relative w-100">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.85rem;"></i>
                            <input type="text" class="form-control form-control-sm cal-search-input w-100" id="inputBuscarFicha" placeholder="<?= $esInstructor ? 'Buscar ficha o programa...' : 'Buscar ficha, programa o instructor...' ?>" autocomplete="off">
                            <button type="button" class="btn btn-sm p-0 position-absolute top-50 end-0 translate-middle-y me-2 text-muted border-0 bg-transparent d-none" id="btnClearSearch" title="Borrar búsqueda">
                                <i class="bi bi-x-circle-fill"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-light border text-nowrap btn-limpiar-filtro" id="btnLimpiarFiltros" onclick="limpiarFiltros()" title="Mostrar todas las clases de nuevo">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar
                        </button>
                    </div>

                    <!-- Botón de Filtros y Selector de Vistas -->
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 d-flex align-items-center gap-2" id="btnToggleFiltros" title="Mostrar u ocultar lista de fichas">
                            <i class="bi bi-sliders"></i>
                            <span>Fichas</span>
                            <span class="badge bg-success rounded-pill" id="badgeFichasActivas"><?= count($fichas) ?></span>
                        </button>

                        <div class="btn-group cal-view-group" role="group">
                            <button type="button" class="btn btn-sm" id="btnViewDay" onclick="cambiarVista('timeGridDay')">Día</button>
                            <button type="button" class="btn btn-sm" id="btnViewWeek" onclick="cambiarVista('timeGridWeek')">Semana</button>
                            <button type="button" class="btn btn-sm active" id="btnViewMonth" onclick="cambiarVista('dayGridMonth')">Mes</button>
                        </div>
                    </div>
                </div>

                <!-- Cuerpo del Calendario: Panel de Fichas + Cuadrícula FullCalendar -->
                <div class="cal-body-wrapper d-flex">
                    <!-- Panel Lateral de Filtro de Fichas -->
                    <aside class="cal-fichas-sidebar p-3" id="panelFiltrosFichas">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold small text-uppercase text-secondary" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                                <?= $esInstructor ? 'Mis Fichas Asignadas' : 'Fichas de Formación' ?>
                            </span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-muted" onclick="toggleOcultarFiltros()" title="Ocultar panel de fichas">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                        </div>

                        <!-- Checkbox maestro -->
                        <label class="cal-ficha-item border-bottom pb-2 mb-2 w-100">
                            <input type="checkbox" id="chkTodasFichas" checked onchange="toggleTodasFichas(this)">
                            <span class="cal-custom-box" style="--chk-color: var(--cal-verde-sena);">
                                <i class="bi bi-check-lg"></i>
                            </span>
                            <span class="cal-ficha-label fw-bold text-dark">Mostrar todas</span>
                        </label>

                        <!-- Lista de Fichas dinámicas -->
                        <div class="cal-fichas-list" id="listaFichasCheckboxes">
                            <?php if (empty($fichas)): ?>
                                <div class="text-muted small text-center p-3">
                                    <i class="bi bi-info-circle d-block fs-4 mb-1"></i>
                                    No tienes fichas asignadas en la programación actual.
                                </div>
                            <?php else: ?>
                                <?php foreach ($fichas as $f): 
                                    $idF = (int) $f['id_ficha'];
                                    $colorFicha = $coloresFicha[$idF] ?? '#1976d2';
                                    $numeroFicha = htmlspecialchars($f['numero_ficha']);
                                    $nombrePrograma = htmlspecialchars(html_entity_decode($f['nombre_programa'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                                ?>
                                <label class="cal-ficha-item item-ficha-filtro" data-ficha-id="<?= $idF ?>" data-ficha-text="<?= strtolower($numeroFicha . ' ' . $nombrePrograma) ?>">
                                    <input type="checkbox" class="chk-ficha-individual" value="<?= $idF ?>" checked onchange="filtrarFichas()">
                                    <span class="cal-custom-box" style="--chk-color: <?= $colorFicha ?>;">
                                        <i class="bi bi-check-lg"></i>
                                    </span>
                                    <span class="cal-ficha-label" title="<?= $numeroFicha ?> · <?= $nombrePrograma ?>">
                                        <span class="cal-ficha-num"><?= $numeroFicha ?></span>
                                        <span class="cal-ficha-prog d-block"><?= $nombrePrograma ?></span>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </aside>

                    <!-- Área de la Cuadrícula del Calendario -->
                    <div class="cal-main-container p-2">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- ==========================================================================
     Modal: Detalles del Horario (Visualización rápida)
     ========================================================================== -->
<div class="modal fade modal-horario-detalle" id="modalDetalleHorario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-4 py-3 text-white" id="modalDetalleHeader" style="background-color: var(--cal-verde-sena);">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar3"></i>
            <h5 class="modal-title fw-bold mb-0" id="modalDetalleFicha">Ficha</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="fw-bold text-dark mb-0" id="modalDetallePrograma">Programa de formación</h6>
            <span id="modalDetalleEstadoBadge" class="badge-estado-sesion bg-light text-secondary border">Programada</span>
        </div>

        <div class="list-group list-group-flush mb-0 small mt-3">
          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-clock me-2 text-sena"></i>Horario programado</span>
            <span class="fw-semibold text-dark" id="modalDetalleHorarioTexto">07:00 – 13:00</span>
          </div>
          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-hourglass-split me-2 text-warning"></i>Duración estimada</span>
            <span class="fw-semibold text-dark" id="modalDetalleDuracion">0 horas</span>
          </div>
          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-calendar-event me-2 text-primary"></i>Fecha</span>
            <span class="fw-semibold text-dark" id="modalDetalleFechaTexto">Fecha</span>
          </div>
          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-person-badge me-2 text-info"></i>Instructor asignado</span>
            <span class="fw-semibold text-dark" id="modalDetalleInstructor">Sin asignar</span>
          </div>
          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between" id="rowDetalleArea">
            <span class="text-muted"><i class="bi bi-mortarboard me-2 text-secondary"></i>Área / Especialidad</span>
            <span class="fw-semibold text-dark" id="modalDetalleArea">General</span>
          </div>
          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-people me-2 text-success"></i>Aprendices matriculados</span>
            <span class="fw-semibold text-dark" id="modalDetalleAprendices">0 aprendices</span>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light px-4 py-2 border-0 d-flex justify-content-between">
        <?php if ($rolActivo === 'Administrador'): ?>
        <div>
            <button type="button" class="btn btn-sm btn-outline-danger" id="btnEliminarDesdeDetalle">
                <i class="bi bi-trash3 me-1"></i> Eliminar
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary ms-1" id="btnEditarDesdeDetalle">
                <i class="bi bi-pencil-square me-1"></i> Editar
            </button>
        </div>
        <?php else: ?>
        <div>
            <a href="<?= BASE_URL ?>/index.php?accion=validarExcusas" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-medical me-1"></i> Validar excusas
            </a>
        </div>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================================================
     Modal: Próxima Clase (Detalle de Ficha y Aprendices)
     ========================================================================== -->
<div class="modal fade" id="modalDetalleProximaClase" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      
      <!-- Encabezado con gradiente verde SENA -->
      <div class="modal-header border-0 text-white p-3" style="background: linear-gradient(135deg, #007A33 0%, #005624 100%);">
        <div class="d-flex align-items-center gap-2">
          <div class="rounded-circle bg-white text-success d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px;">
            <i class="bi bi-clock-history fs-5" style="color: #007A33;"></i>
          </div>
          <div>
            <span class="badge bg-white text-success border-0 px-2 py-0 fw-bold" style="font-size: 0.65rem; color: #007A33 !important;">PRÓXIMA CLASE PROGRAMADA</span>
            <h6 class="modal-title fw-bold mb-0 text-white" id="modalProxTitulo">Información de la Clase</h6>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body p-4">
        <!-- Ficha y Programa -->
        <div class="mb-3">
          <div class="d-flex align-items-baseline gap-2 mb-1">
            <span class="fs-4 fw-bold text-dark" id="modalProxFichaNum">Ficha --</span>
          </div>
          <p class="text-muted fw-semibold mb-0" id="modalProxPrograma">Programa de formación</p>
        </div>

        <!-- Tarjeta destacada de Aprendices Matriculados (Requerimiento Principal) -->
        <div class="p-3 mb-3 rounded-3 border" style="background: linear-gradient(180deg, #F0F9F2 0%, #E8F5E9 100%); border-color: #A3D9B5 !important;">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm" style="width: 46px; height: 46px; background-color: #007A33;">
                <i class="bi bi-people-fill fs-4"></i>
              </div>
              <div>
                <div class="text-uppercase text-muted fw-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Aprendices en esta ficha</div>
                <div class="fs-4 fw-bold mb-0" style="color: #007A33;" id="modalProxTotalAprendices">0 aprendices</div>
              </div>
            </div>
            <a href="<?= BASE_URL ?>/index.php?accion=fichas" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-medium" style="font-size: 0.78rem;">
              <i class="bi bi-eye me-1"></i>Ver ficha
            </a>
          </div>
        </div>

        <!-- Grid de Información de Horario -->
        <div class="row g-2 mb-3">
          <div class="col-6">
            <div class="p-2 border rounded-3 bg-light h-100">
              <div class="text-muted small fw-semibold"><i class="bi bi-clock me-1 text-success"></i>Horario programado</div>
              <div class="fw-bold text-dark fs-6" id="modalProxHorario">--:-- – --:--</div>
              <div class="text-muted small" id="modalProxDuracion">--</div>
            </div>
          </div>
          <div class="col-6">
            <div class="p-2 border rounded-3 bg-light h-100">
              <div class="text-muted small fw-semibold"><i class="bi bi-calendar3 me-1 text-success"></i>Fecha programada</div>
              <div class="fw-bold text-dark" style="font-size: 0.88rem;" id="modalProxFecha">--</div>
              <div class="text-muted small" id="modalProxDiaSemana">--</div>
            </div>
          </div>
        </div>

        <!-- Información de Instructor y Área -->
        <div class="p-3 border rounded-3 bg-light">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-person-badge text-muted"></i>
            <span class="small fw-semibold text-muted">Instructor:</span>
            <span class="small fw-bold text-dark" id="modalProxInstructor">--</span>
          </div>
          <div class="d-flex align-items-center gap-2" id="modalProxRowArea">
            <i class="bi bi-journal-bookmark text-muted"></i>
            <span class="small fw-semibold text-muted">Área / Especialidad:</span>
            <span class="small text-dark" id="modalProxArea">--</span>
          </div>
        </div>
      </div>

      <div class="modal-footer bg-light border-top-0 d-flex justify-content-between p-3">
        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-medium" onclick="ubicarProximaClaseEnCalendario()">
          <i class="bi bi-calendar2-event me-1"></i>Ubicar en calendario
        </button>
        <div class="d-flex gap-2">
          <a href="<?= BASE_URL ?>/index.php?accion=validarExcusas" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-file-earmark-medical me-1"></i>Excusas
          </a>
          <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ==========================================================================
     Modal: Programar / Editar Horario
     ========================================================================== -->
<div class="modal fade" id="modalFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
        <form id="formFicha" action="<?= BASE_URL ?>/index.php?accion=guardarHorario" method="POST">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <input type="hidden" name="horario_id" id="form_horario_id">
        <div class="modal-header border-0 pb-0 px-4 pt-4">
          <h5 class="modal-title fw-bold" id="modalTitulo">Programar horario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body px-4 py-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Número de ficha</label>
                    <input type="text" class="form-control rounded-3" name="numero_ficha" id="form_numero_ficha" list="listaFichasSugeridas" required placeholder="Ej. 3064749">
                    <datalist id="listaFichasSugeridas">
                        <?php foreach ($fichas as $f): ?>
                            <option value="<?= htmlspecialchars($f['numero_ficha']) ?>"><?= htmlspecialchars($f['nombre_programa']) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Programa de formación</label>
                    <input type="text" class="form-control rounded-3" name="programa" id="form_programa" required placeholder="Ej. ADSO">
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Fecha</label>
                    <input type="date" class="form-control rounded-3" name="fecha" id="form_fecha" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">N° Aprendices</label>
                    <input type="number" class="form-control rounded-3" name="aprendices" id="form_aprendices" min="0" placeholder="0">
                </div>
            </div>

            <div class="mb-0 mt-3">
                <label class="form-label small fw-semibold">Instructor encargado</label>
                <select class="form-select rounded-3" name="instructor" id="form_instructor">
                    <option value="0">Seleccionar instructor...</option>
                    <?php foreach ($instructores as $inst): ?>
                        <option value="<?= $inst['id_usuario'] ?>"><?= htmlspecialchars($inst['nombre_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Hora de entrada</label>
                    <input type="time" class="form-control rounded-3" name="hora_entrada" id="form_hora_entrada" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Hora de salida</label>
                    <input type="time" class="form-control rounded-3" name="hora_salida" id="form_hora_salida" required>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-0">
          <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
          <?php if ($rolActivo === 'Administrador'): ?>
          <button type="submit" class="btn btn-sena rounded-pill px-4" id="btnGuardar">Guardar horario</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ==========================================================================
     Modal: Importar Horarios desde Excel
     ========================================================================== -->
<div class="modal fade" id="modalImportarExcel" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="formImportarExcel" action="<?= BASE_URL ?>/index.php?accion=importarHorariosExcel" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <div class="modal-header border-0 pb-0 px-4 pt-4">
          <h5 class="modal-title fw-bold">
            <i class="bi bi-file-earmark-excel text-success me-2"></i>Importar Horarios desde Excel
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body px-4 py-3">
          <p class="text-muted small mb-3">
            Selecciona el archivo de Excel oficial (.xlsx o .xls) con la programación de horarios de fichas e instructores.
          </p>

          <div class="mb-3">
            <label for="inputArchivoExcel" class="form-label fw-semibold small">Archivo de Excel</label>
            <input class="form-control rounded-3" type="file" id="inputArchivoExcel" name="archivo_excel" accept=".xlsx, .xls" required>
            <div class="form-text small">Formatos permitidos: .xlsx, .xls (Máximo 15MB)</div>
          </div>

          <div class="card bg-light border-0 p-3 small text-secondary rounded-3">
            <div class="d-flex align-items-center mb-1 text-dark fw-semibold">
              <i class="bi bi-info-circle text-success me-2"></i> Sincronización automática:
            </div>
            <ul class="mb-0 ps-3">
              <li>Crea fichas e instructores nuevos automáticamente.</li>
              <li>Calcula y registra los bloques horarios exactos.</li>
              <li>Aparecen reflejados inmediatamente en este calendario.</li>
            </ul>
          </div>

          <div class="mt-3 text-end">
            <a href="<?= BASE_URL ?>/index.php?accion=descargarPlantillaHorarios" class="text-decoration-none small text-success fw-semibold">
              <i class="bi bi-file-earmark-arrow-down me-1"></i>Descargar plantilla Excel oficial de ejemplo
            </a>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-0">
          <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sena rounded-pill px-4" id="btnSubirExcel">
            <i class="bi bi-cloud-arrow-up me-1"></i> Subir e Importar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ==========================================================================
     Modal: Confirmar Eliminación de Horario
     ========================================================================== -->
<div class="modal fade" id="modalEliminarHorario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg text-center p-3" style="border-radius: 14px;">
      <form action="<?= BASE_URL ?>/index.php?accion=eliminarHorario" method="POST">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <input type="hidden" name="horario_id" id="eliminar_horario_id">
        <div class="my-3 text-danger">
          <i class="bi bi-trash3" style="font-size: 2.8rem;"></i>
        </div>
        <h5 class="fw-bold mb-2">¿Eliminar este horario?</h5>
        <p class="text-muted small mb-4">Esta acción removerá el bloque de horario de la programación.</p>
        <div class="d-flex justify-content-center gap-2">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger rounded-pill px-3">Sí, eliminar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Scripts Generales del Sistema -->
<?php include 'includes/scripts.php'; ?>

<!-- FullCalendar 6 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<!-- Datos del servidor inyectados para el módulo de horarios -->
<script id="horarios-data" type="application/json">
<?= json_encode([
    'esAdmin'           => ($rolActivo === 'Administrador'),
    'todosLosEventos'   => $eventosCalendario,
    'fichasDisponibles' => $fichas,
    'coloresFicha'      => $coloresFicha,
    'proximaClaseInfo'  => $resumenJornada['proxima_clase'] ?? null,
], JSON_UNESCAPED_UNICODE) ?>
</script>

<!-- Script externo con la lógica del Módulo de Horarios -->
<script src="<?= BASE_URL ?>/assets/js/horarios.js?v=<?= time() ?>"></script>
</body>
</html>
