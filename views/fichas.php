<?php
/**
 * fichas.php — Fichas y horarios en vista Calendario (HU 9, 10)
 */
$rolActivo     = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario =$_SESSION['nombre'] ?? '';
$paginaActiva  = 'fichas';
$tituloPagina  = 'Calendario de fichas y horarios';

// Los eventos e instructores son suministrados desde la base de datos por HorarioControlador
$eventosCalendario = $eventosCalendario ?? [];
$instructores      = $instructores ?? [];


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>
    <!-- FullCalendar CDN -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    
    <!-- Estilos específicos del Calendario de Fichas -->
     <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/calendario.css">

</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content p-4">
            <div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
                <div>
                    <div class="eyebrow">Gestión</div>
                    <h1 class="h4 fw-bold mb-0">Horarios y Fichas</h1>
                    <p class="text-suave mb-0">Haz clic en un día para programar o en una ficha para editar sus detalles.</p>
                </div>
                <?php if ($rolActivo === 'Administrador'): ?>
                <button class="btn btn-sena" onclick="abrirModalCrear()">
                    <i class="bi bi-plus-lg me-1"></i> Nueva ficha
                </button>
                <?php endif; ?>
            </div>

            <!-- Contenedor del Calendario -->
            <div class="card p-3 shadow-sm border-0">
                <div id="calendar"></div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Unificado: Crear / Editar Ficha y Horario -->
<div class="modal fade" id="modalFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <form id="formFicha" action="<?= BASE_URL ?>/index.php?accion=guardarHorario" method="POST">
        <input type="hidden" name="horario_id" id="form_horario_id">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="modalTitulo">Ficha y horario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Número de ficha</label>
                    <input type="text" class="form-control" name="numero_ficha" id="form_numero_ficha" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Programa</label>
                    <input type="text" class="form-control" name="programa" id="form_programa" required>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label">Fecha</label>
                    <input type="date" class="form-control" name="fecha" id="form_fecha" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">N° Aprendices</label>
                    <input type="number" class="form-control" name="aprendices" id="form_aprendices" min="1">
                </div>
            </div>

            <div class="mb-0 mt-3">
                <label class="form-label">Instructor encargado</label>
                <select class="form-select" name="instructor" id="form_instructor">
                    <option value="0">Seleccionar instructor...</option>
                    <?php foreach ($instructores as $inst): ?>
                        <option value="<?= $inst['id_usuario'] ?>"><?= htmlspecialchars($inst['nombre_completo'] . ' ' . $inst['apellido']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-md-6">
                    <label class="form-label">Hora de entrada</label>
                    <input type="time" class="form-control" name="hora_entrada" id="form_hora_entrada" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hora de salida</label>
                    <input type="time" class="form-control" name="hora_salida" id="form_hora_salida" required>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <?php if ($rolActivo === 'Administrador'): ?>
          <button type="submit" class="btn btn-sena" id="btnGuardar">Guardar ficha</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/scripts.php'; ?>

<!-- Script de FullCalendar -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
const esAdmin = <?= json_encode($rolActivo === 'Administrador') ?>;
const modalEl = document.getElementById('modalFicha');
const modalBs = new bootstrap.Modal(modalEl);

function abrirModalCrear(fecha = '') {
    document.getElementById('formFicha').reset();
    document.getElementById('form_horario_id').value = '';
    document.getElementById('form_instructor').value = '0';
    document.getElementById('modalTitulo').innerText = 'Nueva ficha y horario';
    if (fecha) {
        document.getElementById('form_fecha').value = fecha;
    }
    habilitarCampos(true);
    modalBs.show();
}

function habilitarCampos(editable) {
    const inputs = document.querySelectorAll('#formFicha input, #formFicha select');
    inputs.forEach(el => el.disabled = !editable);
}

document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const eventos = <?= json_encode($eventosCalendario) ?>;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        contentHeight: 520,   // Altura compacta para evitar celdas excesivamente grandes
        dayMaxEvents: 2,      // Limita a 2 fichas por celda para mantener el tamaño compacto (+ más)
        navLinks: false,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día'
        },
        slotMinTime: '06:00:00',
        slotMaxTime: '21:00:00',
        allDaySlot: false,
        events: eventos,
        // Renderizado visual mejorado y compacto para las fichas
        eventContent: function(arg) {
            const props = arg.event.extendedProps || {};
            const ficha = props.ficha || arg.event.title;
            const container = document.createElement('div');
            container.className = 'fc-custom-event d-flex align-items-center gap-1 w-100 overflow-hidden';
            container.innerHTML = `
                <span class="fc-ficha-badge">${ficha}</span>
                <span class="fc-ficha-desc text-truncate">${props.programa || ''}</span>
            `;
            return { domNodes: [container] };
        },
        // Tooltip nativo al pasar el mouse sobre la ficha
        eventDidMount: function(info) {
            const props = info.event.extendedProps || {};
            const horario = (props.entrada && props.salida) ? `${props.entrada} - ${props.salida}` : '';
            const instructor = props.instructor || 'Sin asignar';
            info.el.setAttribute('title', `Ficha: ${props.ficha}\nPrograma: ${props.programa}\nHorario: ${horario}\nInstructor: ${instructor}`);
        },
        // Clic en un día vacío
        dateClick: function(info) {
            if (esAdmin) {
                abrirModalCrear(info.dateStr);
            }
        },
        // Clic en un evento existente
        eventClick: function(info) {
            const props = info.event.extendedProps;
            document.getElementById('modalTitulo').innerText = esAdmin ? 'Editar ficha y horario' : 'Detalles de horario';
            document.getElementById('form_horario_id').value = info.event.id;
            document.getElementById('form_numero_ficha').value = props.ficha;
            document.getElementById('form_programa').value = props.programa;
            document.getElementById('form_fecha').value = props.fecha;
            document.getElementById('form_aprendices').value = props.aprendices;

            if (props.id_instructor && props.id_instructor > 0) {
                document.getElementById('form_instructor').value = props.id_instructor;
            } else {
                document.getElementById('form_instructor').value = '0';
            }


            document.getElementById('form_hora_entrada').value = props.entrada;
            document.getElementById('form_hora_salida').value = props.salida;

            habilitarCampos(esAdmin);
            modalBs.show();
        }
    });

    calendar.render();
});
</script>
</body>
</html>