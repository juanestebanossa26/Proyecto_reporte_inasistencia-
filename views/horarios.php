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
    
    <!-- Estilos específicos del Calendario integrados con el SENA (con versión anti-caché) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/calendario.css?v=<?= file_exists(BASE_PATH . '/assets/css/calendario.css') ? filemtime(BASE_PATH . '/assets/css/calendario.css') : time() ?>">

    <!-- Reglas críticas de estructura para asegurar visualización inmediata -->
    <style>
        .cal-body-wrapper { display: flex !important; flex-direction: row !important; min-height: 750px !important; }
        .cal-fichas-sidebar { width: 270px !important; min-width: 270px !important; max-width: 270px !important; flex-shrink: 0 !important; }
        .cal-main-container { flex: 1 1 0% !important; min-width: 0 !important; overflow: hidden !important; }
        #calendar { width: 100% !important; min-height: 720px !important; }
        .cal-ficha-item { display: flex !important; align-items: center !important; }
        .cal-ficha-item input[type="checkbox"] { display: none !important; }
        .cal-summary-box { overflow: hidden !important; min-width: 0 !important; }
        .cal-summary-content { flex: 1 1 0% !important; min-width: 0 !important; overflow: hidden !important; }
        .cal-summary-val, .cal-summary-sub { white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }
    </style>
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
                    <div class="col-12 col-sm-6 col-lg-3 overflow-hidden" style="min-width: 0;">
                        <div class="cal-summary-box cal-summary-box-clickable h-100" 
                             onclick="abrirDetalleProximaClase()" 
                             role="button" 
                             tabindex="0"
                             title="Clic para ver detalles de la ficha, horario y aprendices matriculados">
                            <div class="cal-summary-icon bg-success-subtle text-success">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div class="cal-summary-content overflow-hidden flex-grow-1" style="min-width: 0;">
                                <div class="d-flex align-items-center justify-content-between mb-1" style="min-width: 0;">
                                    <div class="cal-summary-label mb-0 text-truncate">Próxima clase</div>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-1 flex-shrink-0" style="font-size: 0.62rem;">
                                        <i class="bi bi-eye-fill me-1"></i>Ver
                                    </span>
                                </div>
                                <div class="cal-summary-val text-truncate" id="sumProximaHora">
                                    <?php if ($resumenJornada['proxima_clase']): ?>
                                        <?= substr($resumenJornada['proxima_clase']['hora_entrada_programada'], 0, 5) ?> – <?= substr($resumenJornada['proxima_clase']['hora_salida_programada'], 0, 5) ?>
                                    <?php else: ?>
                                        <span class="text-muted fw-normal fs-6">Sin pendientes</span>
                                    <?php endif; ?>
                                </div>
                                <div class="cal-summary-sub text-truncate" id="sumProximaFicha" title="<?= $resumenJornada['proxima_clase'] ? htmlspecialchars($resumenJornada['proxima_clase']['numero_ficha'] . ' · ' . $resumenJornada['proxima_clase']['nombre_programa']) : '' ?>">
                                    <?php if ($resumenJornada['proxima_clase']): ?>
                                        Ficha <?= htmlspecialchars($resumenJornada['proxima_clase']['numero_ficha']) ?> · <?= htmlspecialchars($resumenJornada['proxima_clase']['nombre_programa']) ?>
                                    <?php else: ?>
                                        No hay sesiones próximas registradas
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta 2: Clases de hoy -->
                    <div class="col-12 col-sm-6 col-lg-3 overflow-hidden" style="min-width: 0;">
                        <div class="cal-summary-box h-100">
                            <div class="cal-summary-icon bg-primary-subtle text-primary">
                                <i class="bi bi-calendar2-week"></i>
                            </div>
                            <div class="cal-summary-content overflow-hidden flex-grow-1" style="min-width: 0;">
                                <div class="cal-summary-label text-truncate">Clases de hoy</div>
                                <div class="cal-summary-val text-truncate" id="sumTotalClases">
                                    <?= $resumenJornada['total_clases_hoy'] ?> <span class="fs-6 fw-normal text-muted">sesiones</span>
                                </div>
                                <div class="cal-summary-sub text-truncate" id="sumTotalClasesSub">
                                    <?= $resumenJornada['total_clases_hoy'] > 0 ? 'En la jornada actual' : 'Jornada libre de clases hoy' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta 3: Horas programadas -->
                    <div class="col-12 col-sm-6 col-lg-3 overflow-hidden" style="min-width: 0;">
                        <div class="cal-summary-box h-100">
                            <div class="cal-summary-icon bg-warning-subtle text-warning-emphasis">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div class="cal-summary-content overflow-hidden flex-grow-1" style="min-width: 0;">
                                <div class="cal-summary-label text-truncate">Horas programadas</div>
                                <div class="cal-summary-val text-truncate" id="sumTotalHoras">
                                    <?= $resumenJornada['total_horas_hoy'] ?> <span class="fs-6 fw-normal text-muted">horas</span>
                                </div>
                                <div class="cal-summary-sub text-truncate">
                                    <?= $resumenJornada['total_horas_hoy'] > 0 ? 'Dedicación acumulada de hoy' : 'Sin horas asignadas hoy' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta 4: Fichas activas -->
                    <div class="col-12 col-sm-6 col-lg-3 overflow-hidden" style="min-width: 0;">
                        <div class="cal-summary-box h-100">
                            <div class="cal-summary-icon bg-info-subtle text-info-emphasis">
                                <i class="bi bi-journal-bookmark"></i>
                            </div>
                            <div class="cal-summary-content overflow-hidden flex-grow-1" style="min-width: 0;">
                                <div class="cal-summary-label text-truncate">Fichas activas</div>
                                <div class="cal-summary-val text-truncate" id="sumTotalFichas">
                                    <?= $resumenJornada['total_fichas'] ?> <span class="fs-6 fw-normal text-muted">fichas</span>
                                </div>
                                <div class="cal-summary-sub text-truncate">
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
                            <button type="button" class="btn btn-sm active" id="btnViewWeek" onclick="cambiarVista('timeGridWeek')">Semana</button>
                            <button type="button" class="btn btn-sm" id="btnViewMonth" onclick="cambiarVista('dayGridMonth')">Mes</button>
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
                                    $nombrePrograma = htmlspecialchars($f['nombre_programa']);
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

<script>
// Estado de sesión y datos provistos por PHP
const esAdmin = <?= json_encode($rolActivo === 'Administrador') ?>;
const todosLosEventos = <?= json_encode($eventosCalendario) ?>;
const fichasDisponibles = <?= json_encode($fichas) ?>;
const coloresFicha = <?= json_encode($coloresFicha) ?>;
const proximaClaseInfo = <?= json_encode($resumenJornada['proxima_clase'] ?? null) ?>;

// Modales Bootstrap e Instancias
let modalFichaBs, modalDetalleBs, modalEliminarBs, modalProxBs;
let calendar;
let eventoActual = null;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar modales Bootstrap de forma segura
    const modalFichaEl = document.getElementById('modalFicha');
    if (modalFichaEl && window.bootstrap) modalFichaBs = new bootstrap.Modal(modalFichaEl);
    
    const modalDetalleEl = document.getElementById('modalDetalleHorario');
    if (modalDetalleEl && window.bootstrap) modalDetalleBs = new bootstrap.Modal(modalDetalleEl);
    
    const modalEliminarEl = document.getElementById('modalEliminarHorario');
    if (modalEliminarEl && window.bootstrap) modalEliminarBs = new bootstrap.Modal(modalEliminarEl);

    const modalProxEl = document.getElementById('modalDetalleProximaClase');
    if (modalProxEl && window.bootstrap) modalProxBs = new bootstrap.Modal(modalProxEl);

    const calendarEl = document.getElementById('calendar');

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        locale: 'es',
        firstDay: 1, // Lunes (semana académica SENA)
        height: 750, // Altura explícita para renderizar la cuadrícula y permitir scroll interno
        expandRows: true,
        headerToolbar: false, // Barra propia y limpia arriba
        slotMinTime: '06:00:00',
        slotMaxTime: '22:00:00',
        scrollTime: '06:00:00',
        slotDuration: '00:30:00',
        slotLabelInterval: '01:00:00',
        allDaySlot: false,
        nowIndicator: true, // Línea roja con punto para hora actual
        events: todosLosEventos,
        eventDisplay: 'block',
        dayMaxEvents: false,
        editable: false,
        selectable: esAdmin,

        // ======================================================================
        // SOLUCIÓN CLAVE A LA SUPERPOSICIÓN:
        // slotEventOverlap: false -> distribuye eventos simultáneos en columnas
        // paralelas adyacentes, evitando que un evento tape u oculte a otro.
        // ======================================================================
        slotEventOverlap: false,
        eventMinHeight: 28,

        // Formato limpio de horas: 7 AM, 8 AM... 12 PM, 1 PM...
        slotLabelContent: function(arg) {
            const date = arg.date;
            let hour = date.getHours();
            const meridiem = hour >= 12 ? 'PM' : 'AM';
            hour = hour % 12;
            if (hour === 0) hour = 12;
            return `${hour} ${meridiem}`;
        },

        // Encabezados de días con nombre y número circular
        dayHeaderContent: function(arg) {
            const fecha = arg.date;
            const hoy = new Date();
            const esHoy = fecha.getFullYear() === hoy.getFullYear() &&
                          fecha.getMonth() === hoy.getMonth() &&
                          fecha.getDate() === hoy.getDate();
            const diasSemana = ['DOM', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB'];
            const diaNombre = diasSemana[fecha.getDay()];
            const diaNumero = fecha.getDate();

            return {
                html: `
                    <div class="cal-header-day ${esHoy ? 'is-today' : ''}">
                        <div class="cal-header-dayname">${diaNombre}</div>
                        <div class="cal-header-daynum">${diaNumero}</div>
                    </div>
                `
            };
        },

        // Renderizado inteligente y adaptativo del contenido del evento
        eventContent: function(arg) {
            const props = arg.event.extendedProps || {};
            const ficha = props.ficha || arg.event.title;
            const programa = props.programa || '';
            const entrada = props.entrada || '';
            const salida = props.salida || '';
            const instructor = props.instructor || '';

            // Duración en minutos
            const duracionMin = (arg.event.end - arg.event.start) / (1000 * 60);

            const div = document.createElement('div');
            div.className = 'fc-event-main-frame';

            let html = '';
            if (duracionMin <= 45) {
                // Evento corto: 1 línea compacta
                html = `
                    <div class="fc-event-single-line">
                        <span class="fc-event-num">${ficha}</span>
                        <span class="fc-event-time-short ms-1">${entrada}–${salida}</span>
                    </div>
                `;
            } else if (duracionMin <= 90) {
                // Evento mediano: 2 líneas
                html = `
                    <div class="fc-event-title-line">
                        <span class="fc-event-num">${ficha}</span>
                        ${programa ? `<span class="fc-event-prog-short ms-1">· ${programa}</span>` : ''}
                    </div>
                    <div class="fc-event-time-line">${entrada} – ${salida}</div>
                `;
            } else {
                // Evento completo: ficha, horas e instructor
                html = `
                    <div class="fc-event-title-line">
                        <span class="fc-event-num">${ficha}</span>
                        ${programa ? `<span class="fc-event-prog-short ms-1">· ${programa}</span>` : ''}
                    </div>
                    <div class="fc-event-time-line">${entrada} – ${salida}</div>
                    ${instructor && instructor !== 'Sin asignar' ? `
                        <div class="fc-event-inst-line text-truncate">
                            <i class="bi bi-person-fill"></i> ${instructor}
                        </div>
                    ` : ''}
                `;
            }

            div.innerHTML = html;
            return { domNodes: [div] };
        },

        // Tooltip nativo al posar el cursor sobre cualquier bloque
        eventDidMount: function(info) {
            const p = info.event.extendedProps || {};
            const horario = `${p.entrada || ''} - ${p.salida || ''}`;
            const inst = p.instructor || 'Sin asignar';
            info.el.setAttribute('title', `Ficha: ${p.ficha}\nPrograma: ${p.programa}\nHorario: ${horario}\nInstructor: ${inst}\nAprendices: ${p.aprendices || 0}`);
        },

        // Clic en evento: abre modal con detalles completos
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            abrirDetalleHorario(info.event);
        },

        // Clic en celda vacía: abre modal para crear nuevo horario
        select: function(info) {
            if (esAdmin) {
                const fechaStr = info.startStr.split('T')[0];
                const horaInicio = info.startStr.split('T')[1]?.substring(0, 5) || '07:00';
                const horaFin = info.endStr?.split('T')[1]?.substring(0, 5) || '13:00';
                abrirModalCrear(fechaStr, horaInicio, horaFin);
            }
        },

        // Actualizar título dinámico de periodo
        datesSet: function(info) {
            actualizarTituloPeriodo();
        }
    });

    calendar.render();

    // Controles de navegación superior
    document.getElementById('btnToday').addEventListener('click', irAHoy);
    document.getElementById('btnPrev').addEventListener('click', () => calendar.prev());
    document.getElementById('btnNext').addEventListener('click', () => calendar.next());

    // Toggle de visualización de panel de filtros
    document.getElementById('btnToggleFiltros').addEventListener('click', toggleOcultarFiltros);

    // Búsqueda en vivo de fichas / programas / instructores
    const inputBuscar = document.getElementById('inputBuscarFicha');
    const btnClearSearch = document.getElementById('btnClearSearch');

    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            if (btnClearSearch) {
                btnClearSearch.classList.toggle('d-none', query === '');
            }

            // Filtrar checkboxes del panel lateral
            document.querySelectorAll('.item-ficha-filtro').forEach(item => {
                const text = item.getAttribute('data-ficha-text') || '';
                item.style.display = text.includes(query) ? 'flex' : 'none';
            });

            // Filtrar eventos directamente en la cuadrícula de FullCalendar
            if (query !== '') {
                calendar.removeAllEvents();
                const filtrados = todosLosEventos.filter(ev => {
                    const p = ev.extendedProps || {};
                    const cadena = `${p.ficha} ${p.programa} ${p.instructor} ${p.area || ''}`.toLowerCase();
                    return cadena.includes(query);
                });
                calendar.addEventSource(filtrados);
            } else {
                filtrarFichas();
            }
        });
    }

    if (btnClearSearch) {
        btnClearSearch.addEventListener('click', function() {
            if (inputBuscar) {
                inputBuscar.value = '';
                btnClearSearch.classList.add('d-none');
                inputBuscar.dispatchEvent(new Event('input'));
            }
        });
    }

    // Botones de acción en el modal de detalles
    const btnEditar = document.getElementById('btnEditarDesdeDetalle');
    if (btnEditar) {
        btnEditar.addEventListener('click', function() {
            modalDetalleBs.hide();
            if (eventoActual) abrirModalEditar(eventoActual);
        });
    }

    const btnEliminar = document.getElementById('btnEliminarDesdeDetalle');
    if (btnEliminar) {
        btnEliminar.addEventListener('click', function() {
            modalDetalleBs.hide();
            if (eventoActual) {
                document.getElementById('eliminar_horario_id').value = eventoActual.id;
                modalEliminarBs.show();
            }
        });
    }

    // Spinner al importar Excel
    const formImportar = document.getElementById('formImportarExcel');
    if (formImportar) {
        formImportar.addEventListener('submit', function() {
            const btn = document.getElementById('btnSubirExcel');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Importando datos...';
        });
    }
});

// ==========================================================================
// Funciones de Control de Periodo y Vistas
// ==========================================================================
function irAHoy() {
    if (!calendar) return;
    calendar.today();
}

function actualizarTituloPeriodo() {
    if (!calendar) return;
    const view = calendar.view;
    const meses = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    if (view.type === 'timeGridDay') {
        const d = calendar.getDate();
        const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const diaNombre = diasSemana[d.getDay()];
        const mesNombre = meses[d.getMonth()];
        document.getElementById('calendarTitle').innerText = `${diaNombre}, ${d.getDate()} de ${mesNombre} de ${d.getFullYear()}`;
    } else if (view.type === 'timeGridWeek') {
        const start = new Date(view.currentStart);
        // currentEnd en FullCalendar es exclusivo (un día después)
        const end = new Date(view.currentEnd);
        end.setDate(end.getDate() - 1);

        if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
            document.getElementById('calendarTitle').innerText = `${start.getDate()} al ${end.getDate()} de ${meses[start.getMonth()]} de ${start.getFullYear()}`;
        } else if (start.getFullYear() === end.getFullYear()) {
            document.getElementById('calendarTitle').innerText = `${start.getDate()} de ${meses[start.getMonth()]} al ${end.getDate()} de ${meses[end.getMonth()]} de ${start.getFullYear()}`;
        } else {
            document.getElementById('calendarTitle').innerText = `${start.getDate()} de ${meses[start.getMonth()]} ${start.getFullYear()} al ${end.getDate()} de ${meses[end.getMonth()]} ${end.getFullYear()}`;
        }
    } else {
        const fecha = calendar.getDate();
        const mes = meses[fecha.getMonth()];
        const anio = fecha.getFullYear();
        document.getElementById('calendarTitle').innerText = `${mes} de ${anio}`;
    }
}

function cambiarVista(vistaNombre) {
    if (!calendar) return;
    calendar.changeView(vistaNombre);
    
    // Actualizar botones de vista
    document.querySelectorAll('.cal-view-group .btn').forEach(b => b.classList.remove('active'));
    if (vistaNombre === 'timeGridDay') document.getElementById('btnViewDay').classList.add('active');
    if (vistaNombre === 'timeGridWeek') document.getElementById('btnViewWeek').classList.add('active');
    if (vistaNombre === 'dayGridMonth') document.getElementById('btnViewMonth').classList.add('active');
}

function toggleOcultarFiltros() {
    const sidebar = document.getElementById('panelFiltrosFichas');
    sidebar.classList.toggle('is-collapsed');
    setTimeout(() => {
        if (calendar) calendar.updateSize();
    }, 230);
}

// ==========================================================================
// Filtros de Fichas (Todas, Una, o Múltiples)
// ==========================================================================
function toggleTodasFichas(masterCheckbox) {
    const estado = masterCheckbox.checked;
    document.querySelectorAll('.chk-ficha-individual').forEach(chk => {
        chk.checked = estado;
    });
    filtrarFichas();
}

function limpiarFiltros() {
    const inputBuscar = document.getElementById('inputBuscarFicha');
    if (inputBuscar) {
        inputBuscar.value = '';
        const btnClear = document.getElementById('btnClearSearch');
        if (btnClear) btnClear.classList.add('d-none');
    }

    document.querySelectorAll('.item-ficha-filtro').forEach(item => {
        item.style.display = 'flex';
    });

    const chkTodas = document.getElementById('chkTodasFichas');
    if (chkTodas) chkTodas.checked = true;

    document.querySelectorAll('.chk-ficha-individual').forEach(chk => {
        chk.checked = true;
    });

    filtrarFichas();
}

function filtrarFichas() {
    const checksActivos = Array.from(document.querySelectorAll('.chk-ficha-individual:checked')).map(chk => parseInt(chk.value));
    const totalIndividuales = document.querySelectorAll('.chk-ficha-individual').length;
    const chkTodas = document.getElementById('chkTodasFichas');

    if (chkTodas) {
        chkTodas.checked = (checksActivos.length === totalIndividuales);
    }

    // Actualizar badge
    const badge = document.getElementById('badgeFichasActivas');
    if (badge) badge.innerText = checksActivos.length;

    // Filtrar eventos en el calendario
    calendar.removeAllEvents();
    const eventosFiltrados = todosLosEventos.filter(ev => {
        const idFicha = ev.extendedProps?.id_ficha;
        return checksActivos.includes(idFicha);
    });
    calendar.addEventSource(eventosFiltrados);
}

// ==========================================================================
// Detalles de Evento y Modales
// ==========================================================================
function abrirDetalleHorario(evento) {
    eventoActual = evento;
    const p = evento.extendedProps || {};
    const color = p.color || evento.backgroundColor || 'var(--cal-verde-sena)';

    document.getElementById('modalDetalleHeader').style.backgroundColor = color;
    document.getElementById('modalDetalleFicha').innerText = `Ficha ${p.ficha}`;
    document.getElementById('modalDetallePrograma').innerText = p.programa || 'Programa sin especificar';
    document.getElementById('modalDetalleHorarioTexto').innerText = `${p.entrada} – ${p.salida}`;
    
    // Duración
    const duracionTxt = p.duracion_horas ? `${p.duracion_horas} horas` : (p.duracion_min ? `${p.duracion_min} min` : 'No calculada');
    document.getElementById('modalDetalleDuracion').innerText = duracionTxt;

    // Formatear fecha legible
    const fObj = new Date(p.fecha + 'T12:00:00');
    const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const fechaTxt = fObj.toLocaleDateString('es-ES', opciones);
    document.getElementById('modalDetalleFechaTexto').innerText = fechaTxt.charAt(0).toUpperCase() + fechaTxt.slice(1);

    document.getElementById('modalDetalleInstructor').innerText = p.instructor || 'Sin asignar';
    
    // Área / Especialidad si existe
    const rowArea = document.getElementById('rowDetalleArea');
    if (rowArea) {
        if (p.area && p.area.trim() !== '') {
            rowArea.style.display = 'flex';
            document.getElementById('modalDetalleArea').innerText = p.area;
        } else {
            rowArea.style.display = 'none';
        }
    }

    document.getElementById('modalDetalleAprendices').innerText = `${p.aprendices || 0} aprendices matriculados`;

    // Estado de la sesión (Finalizada, En curso, Programada)
    const badgeEstado = document.getElementById('modalDetalleEstadoBadge');
    if (badgeEstado && evento.start && evento.end) {
        const ahora = new Date();
        badgeEstado.className = 'badge-estado-sesion border';
        if (ahora > evento.end) {
            badgeEstado.innerText = 'Finalizada';
            badgeEstado.classList.add('bg-secondary', 'text-white');
        } else if (ahora >= evento.start && ahora <= evento.end) {
            badgeEstado.innerText = 'En curso';
            badgeEstado.classList.add('bg-success', 'text-white');
        } else {
            badgeEstado.innerText = 'Programada';
            badgeEstado.classList.add('bg-primary-subtle', 'text-primary');
        }
    }

    modalDetalleBs.show();
}

function abrirModalCrear(fecha = '', horaEntrada = '07:00', horaSalida = '13:00') {
    document.getElementById('formFicha').reset();
    document.getElementById('form_horario_id').value = '';
    document.getElementById('form_instructor').value = '0';
    document.getElementById('modalTitulo').innerText = 'Programar horario';
    
    if (fecha) {
        document.getElementById('form_fecha').value = fecha;
    } else {
        const hoy = new Date().toISOString().split('T')[0];
        document.getElementById('form_fecha').value = hoy;
    }

    document.getElementById('form_hora_entrada').value = horaEntrada;
    document.getElementById('form_hora_salida').value = horaSalida;

    habilitarCampos(true);
    modalFichaBs.show();
}

function abrirModalEditar(evento) {
    const p = evento.extendedProps || {};
    document.getElementById('modalTitulo').innerText = 'Editar horario';
    document.getElementById('form_horario_id').value = evento.id;
    document.getElementById('form_numero_ficha').value = p.ficha || '';
    document.getElementById('form_programa').value = p.programa || '';
    document.getElementById('form_fecha').value = p.fecha || '';
    document.getElementById('form_aprendices').value = p.aprendices || 0;

    if (p.id_instructor && p.id_instructor > 0) {
        document.getElementById('form_instructor').value = p.id_instructor;
    } else {
        document.getElementById('form_instructor').value = '0';
    }

    document.getElementById('form_hora_entrada').value = p.entrada || '';
    document.getElementById('form_hora_salida').value = p.salida || '';

    habilitarCampos(esAdmin);
    modalFichaBs.show();
}

function habilitarCampos(editable) {
    const inputs = document.querySelectorAll('#formFicha input, #formFicha select');
    inputs.forEach(el => el.disabled = !editable);
}

// ==========================================================================
// Modal Próxima Clase (Instructor)
// ==========================================================================
function abrirDetalleProximaClase() {
    if (!proximaClaseInfo) {
        alert('No hay información de una próxima clase programada disponible.');
        return;
    }

    const p = proximaClaseInfo;
    const fichaNum = p.numero_ficha ? `Ficha ${p.numero_ficha}` : 'Ficha sin asignar';
    const programa = p.nombre_programa || 'Programa de formación sin especificar';
    const entrada = p.hora_entrada_programada ? p.hora_entrada_programada.substring(0, 5) : '';
    const salida = p.hora_salida_programada ? p.hora_salida_programada.substring(0, 5) : '';
    const totalAprendices = parseInt(p.total_aprendices || 0);

    const elFicha = document.getElementById('modalProxFichaNum');
    if (elFicha) elFicha.innerText = fichaNum;

    const elProg = document.getElementById('modalProxPrograma');
    if (elProg) elProg.innerText = programa;

    const elAprendices = document.getElementById('modalProxTotalAprendices');
    if (elAprendices) {
        elAprendices.innerText = `${totalAprendices} ${totalAprendices === 1 ? 'aprendiz matriculado' : 'aprendices matriculados'}`;
    }

    const elHorario = document.getElementById('modalProxHorario');
    if (elHorario) elHorario.innerText = `${entrada} – ${salida}`;

    // Calcular duración en horas y minutos
    let duracionTxt = '';
    if (entrada && salida) {
        const [h1, m1] = entrada.split(':').map(Number);
        const [h2, m2] = salida.split(':').map(Number);
        const mins = (h2 * 60 + m2) - (h1 * 60 + m1);
        if (mins > 0) {
            const horas = (mins / 60).toFixed(1).replace('.0', '');
            duracionTxt = `${horas} hora${horas !== '1' ? 's' : ''} (${mins} min)`;
        }
    }
    const elDur = document.getElementById('modalProxDuracion');
    if (elDur) elDur.innerText = duracionTxt ? `Duración: ${duracionTxt}` : 'Duración estimada';

    // Fecha legible
    if (p.fecha) {
        const partes = p.fecha.split('-');
        if (partes.length === 3) {
            const fObj = new Date(parseInt(partes[0]), parseInt(partes[1]) - 1, parseInt(partes[2]));
            const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const fechaTxt = fObj.toLocaleDateString('es-ES', opciones);
            const elFecha = document.getElementById('modalProxFecha');
            if (elFecha) elFecha.innerText = `${partes[2]}/${partes[1]}/${partes[0]}`;
            const elDia = document.getElementById('modalProxDiaSemana');
            if (elDia) elDia.innerText = fechaTxt.charAt(0).toUpperCase() + fechaTxt.slice(1);
        }
    }

    const elInst = document.getElementById('modalProxInstructor');
    if (elInst) elInst.innerText = p.instructor_nombre || p.instructor || 'Instructor asignado';

    const rowArea = document.getElementById('modalProxRowArea');
    const elArea = document.getElementById('modalProxArea');
    const area = p.instructor_area || p.area || '';
    if (rowArea && elArea) {
        if (area.trim() !== '') {
            rowArea.style.display = 'flex';
            elArea.innerText = area;
        } else {
            rowArea.style.display = 'none';
        }
    }

    if (modalProxBs) {
        modalProxBs.show();
    } else {
        const modalEl = document.getElementById('modalDetalleProximaClase');
        if (modalEl && window.bootstrap) {
            modalProxBs = new bootstrap.Modal(modalEl);
            modalProxBs.show();
        }
    }
}

function ubicarProximaClaseEnCalendario() {
    if (!calendar || !proximaClaseInfo || !proximaClaseInfo.fecha) return;
    if (modalProxBs) modalProxBs.hide();
    calendar.gotoDate(proximaClaseInfo.fecha);
    cambiarVista('timeGridDay');
}
</script>
</body>
</html>
