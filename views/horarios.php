<?php
/**
 * horarios.php — Módulo de Horarios y Programación Académica
 * Sistema de Control de Ingreso de Aprendices · SENA
 */
$rolActivo     = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'horarios';
$tituloPagina  = 'Horarios y Calendario';

// Datos suministrados desde la base de datos por HorarioControlador
$eventosCalendario = $eventosCalendario ?? [];
$instructores      = $instructores ?? [];
$fichas            = $fichas ?? [];
$coloresFicha      = $coloresFicha ?? [];

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
    
    <!-- Estilos específicos del Calendario integrados con el SENA -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/calendario.css">
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
                    <div class="eyebrow text-uppercase text-sena fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08em;">Gestión Académica</div>
                    <h1 class="h4 fw-bold mb-0 text-dark">Horarios y Calendario</h1>
                    <p class="text-suave mb-0 small">Consulta, programa y gestiona los horarios académicos de las fichas e instructores.</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($rolActivo === 'Administrador'): ?>
                    <button type="button" class="btn btn-outline-success d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalImportarExcel">
                        <i class="bi bi-file-earmark-excel"></i>
                        <span>Importar Excel</span>
                    </button>
                    <button type="button" class="btn btn-sena d-flex align-items-center gap-2" onclick="abrirModalCrear()">
                        <i class="bi bi-plus-lg"></i>
                        <span>Nuevo horario</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tarjeta Contenedora Principal del Calendario -->
            <div class="cal-card overflow-hidden bg-white mb-4">
                <!-- Barra de Herramientas Compacta del Calendario -->
                <div class="cal-toolbar p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <!-- Navegación por fechas y Título -->
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-medium" id="btnToday">Hoy</button>
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

                    <!-- Búsqueda rápida de fichas o instructores -->
                    <div class="position-relative" style="min-width: 240px;">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.85rem;"></i>
                        <input type="text" class="form-control form-control-sm cal-search-input w-100" id="inputBuscarFicha" placeholder="Buscar ficha o instructor..." autocomplete="off">
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
                            <span class="fw-bold small text-uppercase text-secondary" style="font-size: 0.72rem; letter-spacing: 0.05em;">Fichas de Formación</span>
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
                                <span class="cal-ficha-label" title="<?= $numeroFicha ?> - <?= $nombrePrograma ?>">
                                    <span class="cal-ficha-num"><?= $numeroFicha ?></span>
                                    <span class="cal-ficha-prog d-block"><?= $nombrePrograma ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
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
        <h6 class="fw-bold text-dark mb-3" id="modalDetallePrograma">Programa de formación</h6>

        <div class="list-group list-group-flush mb-0 small">
          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-clock me-2 text-sena"></i>Horario programado</span>
            <span class="fw-semibold text-dark" id="modalDetalleHorarioTexto">07:00 – 13:00</span>
          </div>
          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-calendar-event me-2 text-primary"></i>Fecha</span>
            <span class="fw-semibold text-dark" id="modalDetalleFechaTexto">Fecha</span>
          </div>
          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-person-badge me-2 text-info"></i>Instructor encargado</span>
            <span class="fw-semibold text-dark" id="modalDetalleInstructor">Sin asignar</span>
          </div>
          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-people me-2 text-warning"></i>Aprendices matriculados</span>
            <span class="fw-semibold text-dark" id="modalDetalleAprendices">0</span>
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
        <div></div>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
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

// Modales Bootstrap e Instancias
let modalFichaBs, modalDetalleBs, modalEliminarBs;
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

    const calendarEl = document.getElementById('calendar');

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        locale: 'es',
        firstDay: 0, // Domingo
        headerToolbar: false, // Barra propia y limpia arriba
        slotMinTime: '06:00:00',
        slotMaxTime: '22:00:00',
        scrollTime: '07:00:00',
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
    document.getElementById('btnToday').addEventListener('click', () => calendar.today());
    document.getElementById('btnPrev').addEventListener('click', () => calendar.prev());
    document.getElementById('btnNext').addEventListener('click', () => calendar.next());

    // Toggle de visualización de panel de filtros
    document.getElementById('btnToggleFiltros').addEventListener('click', toggleOcultarFiltros);

    // Búsqueda en vivo de fichas / instructores
    const inputBuscar = document.getElementById('inputBuscarFicha');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            // Filtrar checkboxes de la barra
            document.querySelectorAll('.item-ficha-filtro').forEach(item => {
                const text = item.getAttribute('data-ficha-text') || '';
                item.style.display = text.includes(query) ? 'flex' : 'none';
            });

            // Si hay búsqueda activa, también filtrar eventos en el calendario
            if (query !== '') {
                calendar.removeAllEvents();
                const filtrados = todosLosEventos.filter(ev => {
                    const p = ev.extendedProps || {};
                    const cadena = `${p.ficha} ${p.programa} ${p.instructor}`.toLowerCase();
                    return cadena.includes(query);
                });
                calendar.addEventSource(filtrados);
            } else {
                filtrarFichas();
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
function actualizarTituloPeriodo() {
    if (!calendar) return;
    const fecha = calendar.getDate();
    const meses = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];
    const mes = meses[fecha.getMonth()];
    const anio = fecha.getFullYear();
    document.getElementById('calendarTitle').innerText = `${mes} de ${anio}`;
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
    
    // Formatear fecha legible
    const fObj = new Date(p.fecha + 'T12:00:00');
    const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const fechaTxt = fObj.toLocaleDateString('es-ES', opciones);
    document.getElementById('modalDetalleFechaTexto').innerText = fechaTxt.charAt(0).toUpperCase() + fechaTxt.slice(1);

    document.getElementById('modalDetalleInstructor').innerText = p.instructor || 'Sin asignar';
    document.getElementById('modalDetalleAprendices').innerText = `${p.aprendices || 0} aprendices matriculados`;

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
</script>
</body>
</html>
