<?php
/**
 * views/horarios_aprendiz.php — Vista de Horarios para el rol Aprendiz
 * Sistema de Control de Ingreso y Asistencia RFID · SENA
 *
 * Permite al aprendiz consultar de forma exclusiva y en modo solo lectura
 * las clases y horarios asignados a su ficha de formación mediante FullCalendar.
 */

declare(strict_types=1);

$rolActivo         = $_SESSION['rol'] ?? 'Aprendiz';
$nombreUsuario     = $_SESSION['nombre'] ?? 'Aprendiz';
$paginaActiva      = 'horario_aprendiz';
$tituloPagina      = 'Horario de Formación';

$sinFicha          = $sinFicha ?? false;
$sinHorarios       = $sinHorarios ?? false;
$aprendizInfo      = $aprendizInfo ?? null;
$eventosCalendario = $eventosCalendario ?? [];
$resumenAprendiz   = $resumenAprendiz ?? [
    'total_clases'  => 0,
    'proxima_clase' => null,
    'ficha'         => null,
    'programa'      => null
];

// Formateador amigable de la próxima clase
$proximaClaseTexto = null;
if (!empty($resumenAprendiz['proxima_clase'])) {
    $prox = $resumenAprendiz['proxima_clase'];
    $dt = DateTime::createFromFormat('Y-m-d', (string)$prox['fecha']);
    if ($dt) {
        $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $diaSem = $dias[(int)$dt->format('w')] ?? '';
        $diaNum = (int)$dt->format('d');
        $mesNom = $meses[(int)$dt->format('n')] ?? '';
        $horaIni = substr((string)$prox['hora_entrada_programada'], 0, 5);
        $horaFin = substr((string)$prox['hora_salida_programada'], 0, 5);
        $proximaClaseTexto = "{$diaSem} {$diaNum} {$mesNom} · {$horaIni} - {$horaFin}";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>

    <!-- FullCalendar 6 Bundle CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">

    <!-- Estilos de Horarios integrados con SENA -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/horarios.css?v=<?= file_exists(BASE_PATH . '/assets/css/horarios.css') ? filemtime(BASE_PATH . '/assets/css/horarios.css') : '1.0' ?>">
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>

    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>

        <main class="app-content p-4">

            <!-- Encabezado de la página -->
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
                <div>
                    <div class="eyebrow text-uppercase text-sena fw-bold" style="font-size: 0.72rem; letter-spacing: 0.08em;">
                        <i class="bi bi-mortarboard me-1"></i> Formación Profesional Integral · SENA
                    </div>
                    <h1 class="h4 fw-bold mb-1 text-dark">Mi Horario de Formación</h1>
                    <p class="text-secondary small mb-0">
                        Consulta la programación académica, sesiones de clase y jornadas programadas para tu ficha.
                    </p>
                </div>
            </div>

            <?php if ($sinFicha): ?>
                <!-- Estado vacío 1: El aprendiz no tiene ficha asignada -->
                <div class="card border-0 shadow-sm p-5 text-center my-4" style="border-radius: 14px; background: #ffffff;">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 72px; height: 72px;">
                        <i class="bi bi-folder-x fs-1 text-muted"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">No tienes una ficha asociada</h5>
                    <p class="text-muted mx-auto mb-4" style="max-width: 500px; font-size: 0.92rem;">
                        Actualmente tu usuario no se encuentra vinculado a ninguna ficha de formación activa.
                        Por favor comunícate con la coordinación académica o con tu instructor para verificar tu estado de matrícula.
                    </p>
                    <div>
                        <a href="<?= BASE_URL ?>/index.php?accion=historial" class="btn btn-outline-sena rounded-pill px-4">
                            <i class="bi bi-clock-history me-1"></i> Ver mi historial de asistencia
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <!-- Tarjeta informativa de la Ficha asignada -->
                <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius: 12px; border-left: 4px solid #39A900 !important; background: #ffffff;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-light text-dark border px-2 py-1 fw-bold font-monospace" style="font-size: 0.85rem;">
                                    <i class="bi bi-hash text-muted"></i><?= htmlspecialchars((string)($resumenAprendiz['ficha'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="badge rounded-pill" style="background-color: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; font-size: 0.75rem;">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> Ficha Activa
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-0">
                                <?= htmlspecialchars((string)($resumenAprendiz['programa'] ?? 'Programa de Formación'), ENT_QUOTES, 'UTF-8') ?>
                            </h6>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <?php if ($proximaClaseTexto !== null): ?>
                                <span class="badge bg-light text-success border px-3 py-2 rounded-pill fw-semibold" title="Próxima sesión programada en calendario">
                                    <i class="bi bi-calendar2-check-fill me-1 text-success"></i> Próxima clase: <?= htmlspecialchars($proximaClaseTexto, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                            <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill fw-medium">
                                <i class="bi bi-journal-check me-1"></i> <?= (int)$resumenAprendiz['total_clases'] ?> <?= (int)$resumenAprendiz['total_clases'] === 1 ? 'clase registrada' : 'clases registradas' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($sinHorarios): ?>
                    <!-- Estado vacío 2: La ficha existe pero no tiene horarios programados -->
                    <div class="card border-0 shadow-sm p-5 text-center my-4" style="border-radius: 14px; background: #ffffff;">
                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 72px; height: 72px;">
                            <i class="bi bi-calendar-x fs-1 text-muted"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Todavía no hay clases programadas</h5>
                        <p class="text-muted mx-auto mb-0" style="max-width: 500px; font-size: 0.92rem;">
                            Tu ficha de formación (<strong><?= htmlspecialchars((string)$resumenAprendiz['ficha'], ENT_QUOTES, 'UTF-8') ?></strong>)
                            no tiene horarios activos registrados en el calendario en este momento.
                            Los bloques de formación aparecerán aquí tan pronto como sean programados.
                        </p>
                    </div>

                <?php else: ?>

                    <!-- Contenedor del Calendario Académico -->
                    <div class="cal-card shadow-sm border-0 mb-4" style="border-radius: 12px; background: #ffffff;">

                        <!-- Barra de Navegación del Calendario -->
                        <div class="cal-toolbar p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                            
                            <!-- Navegación Temporal (Hoy, Prev, Next) -->
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-medium" id="btnToday">
                                    Hoy
                                </button>
                                <div class="btn-group btn-group-sm ms-1">
                                    <button type="button" class="btn btn-outline-secondary" id="btnPrev" title="Periodo anterior">
                                        <i class="bi bi-chevron-left"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="btnNext" title="Periodo siguiente">
                                        <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                                <h5 class="fw-bold mb-0 text-dark ms-2" id="calPeriodoTitulo" style="min-width: 170px;">
                                    Cargando...
                                </h5>
                            </div>

                            <!-- Selector de Vistas: Mes, Semana, Día -->
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary active fw-medium px-3" id="btnViewMonth">
                                    Mes
                                </button>
                                <button type="button" class="btn btn-outline-secondary fw-medium px-3" id="btnViewWeek">
                                    Semana
                                </button>
                                <button type="button" class="btn btn-outline-secondary fw-medium px-3" id="btnViewDay">
                                    Día
                                </button>
                            </div>
                        </div>

                        <!-- Cuadrícula FullCalendar -->
                        <div class="p-3">
                            <div id="calendar"></div>
                        </div>

                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>
</div>

<!-- ==========================================================================
     Modal: Detalle de la Clase (Solo Lectura para el Aprendiz)
     ========================================================================== -->
<div class="modal fade modal-horario-detalle" id="modalDetalleHorario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow" style="border-radius: 14px; overflow: hidden;">
      
      <div class="modal-header px-4 py-3" id="modalDetalleHeader" style="background-color: #ffffff; border-left: 4px solid #39A900;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar-event text-success fs-5"></i>
            <h5 class="modal-title fw-bold mb-0 text-dark" id="modalDetalleFicha">Ficha</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="fw-bold text-dark mb-0" id="modalDetallePrograma">Programa de formación</h6>
            <span id="modalDetalleEstadoBadge" class="badge rounded-pill px-2 py-1 bg-light text-secondary border">Programada</span>
        </div>

        <div class="list-group list-group-flush mb-0 small">
          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-clock me-2 text-muted"></i>Horario de clase</span>
            <span class="fw-semibold text-dark" id="modalDetalleHorarioTexto">00:00 – 00:00</span>
          </div>

          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-hourglass-split me-2 text-muted"></i>Duración estimada</span>
            <span class="fw-semibold text-dark" id="modalDetalleDuracion">0 horas</span>
          </div>

          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-calendar-check me-2 text-muted"></i>Fecha de sesión</span>
            <span class="fw-semibold text-dark" id="modalDetalleFechaTexto">Fecha</span>
          </div>

          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
            <span class="text-muted"><i class="bi bi-person-badge me-2 text-muted"></i>Instructor asignado</span>
            <span class="fw-semibold text-dark" id="modalDetalleInstructor">Sin asignar</span>
          </div>

          <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between" id="rowDetalleArea">
            <span class="text-muted"><i class="bi bi-journal-bookmark me-2 text-muted"></i>Área / Asignatura</span>
            <span class="fw-semibold text-dark" id="modalDetalleArea">General</span>
          </div>
        </div>
      </div>

      <div class="modal-footer px-4 py-2 bg-light border-0 d-flex justify-content-end">
        <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">
            Cerrar
        </button>
      </div>

    </div>
  </div>
</div>

<?php include 'includes/scripts.php'; ?>

<!-- FullCalendar 6 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<!-- Datos iniciales para FullCalendar -->
<script id="horarios-aprendiz-data" type="application/json">
<?= json_encode([
    'eventosIniciales' => $eventosCalendario,
], JSON_UNESCAPED_UNICODE) ?>
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl || !window.FullCalendar) return;

    let modalDetalleBs = null;
    const modalEl = document.getElementById('modalDetalleHorario');
    if (modalEl && window.bootstrap) {
        modalDetalleBs = new bootstrap.Modal(modalEl);
    }

    // Cargar eventos iniciales renderizados por el servidor
    let eventosIniciales = [];
    const dataEl = document.getElementById('horarios-aprendiz-data');
    if (dataEl && dataEl.textContent.trim() !== '') {
        try {
            const parsed = JSON.parse(dataEl.textContent);
            eventosIniciales = Array.isArray(parsed.eventosIniciales) ? parsed.eventosIniciales : [];
        } catch (e) {
            console.error('Error al parsear eventos de horario:', e);
        }
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: false,
        height: 'auto',
        contentHeight: 'auto',
        dayMaxEvents: 4,
        moreLinkClick: 'popover',
        allDaySlot: true,
        slotMinTime: '06:00:00',
        slotMaxTime: '22:00:00',
        expandRows: true,
        handleWindowResize: true,
        nowIndicator: true,

        // Fuente de eventos: recarga segura vía AJAX mediante el endpoint del servidor
        events: function (fetchInfo, successCallback, failureCallback) {
            const startStr = fetchInfo.startStr.substring(0, 10);
            const endStr = fetchInfo.endStr.substring(0, 10);
            const url = `index.php?accion=obtenerEventosJson&start=${encodeURIComponent(startStr)}&end=${encodeURIComponent(endStr)}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data && data.exito && Array.isArray(data.eventos)) {
                        successCallback(data.eventos);
                    } else if (eventosIniciales.length > 0) {
                        successCallback(eventosIniciales);
                    } else {
                        successCallback([]);
                    }
                })
                .catch(err => {
                    console.warn('Recurriendo a eventos iniciales tras error en fetch:', err);
                    successCallback(eventosIniciales);
                });
        },

        // Personalización de encabezados de día adaptativos
        dayHeaderContent: function (arg) {
            const fecha = arg.date;
            const viewType = arg.view ? arg.view.type : 'dayGridMonth';
            const diasCortos = ['DOM', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB'];
            const diasLargos = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            const mesesLargos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

            if (viewType === 'dayGridMonth') {
                return diasCortos[fecha.getDay()];
            }

            if (viewType === 'timeGridWeek') {
                return {
                    html: `<div class="text-center py-1">
                               <div class="fw-bold small text-muted">${diasCortos[fecha.getDay()]}</div>
                               <div class="fw-bold text-dark fs-6">${fecha.getDate()}</div>
                           </div>`
                };
            }

            return {
                html: `<div class="text-center py-1">
                           <div class="fw-bold text-dark">${diasLargos[fecha.getDay()]}, ${fecha.getDate()} de ${mesesLargos[fecha.getMonth()]}</div>
                       </div>`
            };
        },

        // Renderizado del contenido del evento
        eventContent: function (arg) {
            const p = arg.event.extendedProps || {};
            const viewType = arg.view ? arg.view.type : 'dayGridMonth';
            const div = document.createElement('div');

            if (p.es_festivo) {
                div.className = 'text-truncate px-1 py-0';
                div.innerHTML = `<span class="badge bg-secondary" style="font-size:0.65rem;">FESTIVO</span> <span class="small">${p.es_dia_completo ? 'Día Festivo' : p.entrada}</span>`;
                return { domNodes: [div] };
            }

            const entrada = p.entrada || '';
            const ficha = p.ficha || '';
            const instructor = p.instructor || '';

            if (viewType === 'dayGridMonth') {
                div.className = 'text-truncate px-1';
                div.style.lineHeight = '1.3';
                div.innerHTML = `<span class="fw-bold text-success me-1" style="font-size:0.7rem;">${entrada}</span>
                                 <span class="fw-semibold text-dark" style="font-size:0.72rem;">${ficha}</span>`;
                return { domNodes: [div] };
            }

            div.className = 'p-1';
            div.innerHTML = `
                <div class="fw-bold text-dark small">${entrada} - ${p.salida || ''}</div>
                <div class="fw-bold text-success" style="font-size:0.8rem;">Ficha ${ficha}</div>
                ${p.area ? `<div class="text-muted small text-truncate">${p.area}</div>` : ''}
                ${instructor ? `<div class="text-secondary small text-truncate"><i class="bi bi-person me-1"></i>${instructor}</div>` : ''}
            `;
            return { domNodes: [div] };
        },

        // Asignación de estilos y tooltips
        eventDidMount: function (info) {
            const p = info.event.extendedProps || {};
            info.el.style.borderRadius = '6px';
            info.el.style.cursor = 'pointer';

            if (p.es_festivo) {
                info.el.style.backgroundColor = '#f1f5f9';
                info.el.style.borderColor = '#cbd5e1';
                info.el.style.color = '#475569';
                info.el.setAttribute('title', `Día festivo: ${p.fecha}`);
                return;
            }

            info.el.style.backgroundColor = '#f0fdf4';
            info.el.style.borderLeft = '4px solid #39A900';
            info.el.style.borderColor = '#bbf7d0';
            info.el.style.color = '#14532d';

            const tooltip = `Ficha: ${p.ficha}\nHorario: ${p.entrada} - ${p.salida}\nInstructor: ${p.instructor || 'Sin asignar'}`;
            info.el.setAttribute('title', tooltip);
        },

        // Clic en evento: Abre el modal con el detalle completo
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            const p = info.event.extendedProps || {};

            const elFicha = document.getElementById('modalDetalleFicha');
            if (elFicha) elFicha.innerText = p.es_festivo ? 'Día Festivo SENA' : `Ficha ${p.ficha || '--'}`;

            const elProg = document.getElementById('modalDetallePrograma');
            if (elProg) elProg.innerText = p.programa || (p.es_festivo ? 'Calendario Institucional' : '--');

            const elHorario = document.getElementById('modalDetalleHorarioTexto');
            if (elHorario) elHorario.innerText = `${p.entrada || '--'} – ${p.salida || '--'}`;

            const elDur = document.getElementById('modalDetalleDuracion');
            if (elDur) elDur.innerText = p.duracion_horas ? `${p.duracion_horas} horas` : (p.duracion_min ? `${p.duracion_min} min` : 'No calculada');

            const elFecha = document.getElementById('modalDetalleFechaTexto');
            if (elFecha && p.fecha) {
                const fObj = new Date(p.fecha + 'T12:00:00');
                const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                const txt = fObj.toLocaleDateString('es-ES', opciones);
                elFecha.innerText = txt.charAt(0).toUpperCase() + txt.slice(1);
            }

            const elInst = document.getElementById('modalDetalleInstructor');
            if (elInst) elInst.innerText = p.instructor || 'Sin asignar';

            const rowArea = document.getElementById('rowDetalleArea');
            const elArea = document.getElementById('modalDetalleArea');
            if (rowArea && elArea) {
                if (p.area && p.area.trim() !== '') {
                    rowArea.style.display = 'flex';
                    elArea.innerText = p.area;
                } else {
                    rowArea.style.display = 'none';
                }
            }

            const badgeEstado = document.getElementById('modalDetalleEstadoBadge');
            if (badgeEstado) {
                const hoy = new Date().toISOString().split('T')[0];
                if (p.es_festivo) {
                    badgeEstado.textContent = 'Festivo';
                    badgeEstado.className = 'badge rounded-pill px-2 py-1 bg-secondary text-white';
                } else if (p.fecha < hoy) {
                    badgeEstado.textContent = 'Finalizada';
                    badgeEstado.className = 'badge rounded-pill px-2 py-1 bg-light text-secondary border';
                } else if (p.fecha === hoy) {
                    badgeEstado.textContent = 'Hoy';
                    badgeEstado.className = 'badge rounded-pill px-2 py-1 bg-success text-white';
                } else {
                    badgeEstado.textContent = 'Programada';
                    badgeEstado.className = 'badge rounded-pill px-2 py-1 bg-primary-subtle text-primary border';
                }
            }

            if (modalDetalleBs) {
                modalDetalleBs.show();
            }
        },

        // Actualizar título dinámico de periodo
        datesSet: function (info) {
            const tituloEl = document.getElementById('calPeriodoTitulo');
            if (tituloEl && info.view) {
                tituloEl.innerText = info.view.title || '';
            }
        }
    });

    calendar.render();

    // Controles de navegación del calendario
    const btnToday = document.getElementById('btnToday');
    if (btnToday) btnToday.addEventListener('click', () => calendar.today());

    const btnPrev = document.getElementById('btnPrev');
    if (btnPrev) btnPrev.addEventListener('click', () => calendar.prev());

    const btnNext = document.getElementById('btnNext');
    if (btnNext) btnNext.addEventListener('click', () => calendar.next());

    // Selector de vistas
    const btnMonth = document.getElementById('btnViewMonth');
    const btnWeek = document.getElementById('btnViewWeek');
    const btnDay = document.getElementById('btnViewDay');

    function actualizarBotonesVista(vistaActiva) {
        [btnMonth, btnWeek, btnDay].forEach(btn => {
            if (btn) btn.classList.remove('active', 'btn-secondary', 'text-white');
        });
        if (vistaActiva === 'dayGridMonth' && btnMonth) btnMonth.classList.add('active');
        if (vistaActiva === 'timeGridWeek' && btnWeek) btnWeek.classList.add('active');
        if (vistaActiva === 'timeGridDay' && btnDay) btnDay.classList.add('active');
    }

    if (btnMonth) {
        btnMonth.addEventListener('click', () => {
            calendar.changeView('dayGridMonth');
            actualizarBotonesVista('dayGridMonth');
        });
    }

    if (btnWeek) {
        btnWeek.addEventListener('click', () => {
            calendar.changeView('timeGridWeek');
            actualizarBotonesVista('timeGridWeek');
        });
    }

    if (btnDay) {
        btnDay.addEventListener('click', () => {
            calendar.changeView('timeGridDay');
            actualizarBotonesVista('timeGridDay');
        });
    }
});
</script>
</body>
</html>
