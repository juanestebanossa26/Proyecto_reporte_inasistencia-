<?php
/**
 * fichas.php — Fichas y horarios en vista Calendario (HU 9, 10)
 */
$rolActivo     = $_GET['rol'] ?? 'Administrador';
$nombreUsuario =$_SESSION['nombre'] ?? '';
$paginaActiva  = 'fichas';
$tituloPagina  = 'Calendario de fichas y horarios';

// Simulación de datos con fechas para el calendario
// TODO (backend): Cargar con SELECT uniendo tablas fichas + horarios
$fichas = [
    [
        'id'          => '1',
        'ficha'       => '2589451',
        'programa'    => 'ADSO',
        'instructor'  => 'Juan Camilo Vanegas',
        'fecha'       => date('Y-m-d'),
        'entrada'     => '07:00',
        'salida'      => '12:30',
        'aprendices'  => 32
    ],
    [
        'id'          => '2',
        'ficha'       => '2678903',
        'programa'    => 'Multimedia',
        'instructor'  => 'Marcela Ortiz',
        'fecha'       => date('Y-m-d', strtotime('+1 day')),
        'entrada'     => '13:00',
        'salida'      => '18:00',
        'aprendices'  => 28
    ],
    [
        'id'          => '3',
        'ficha'       => '2701123',
        'programa'    => 'Redes',
        'instructor'  => 'Héctor Ramírez',
        'fecha'       => date('Y-m-d', strtotime('+2 days')),
        'entrada'     => '06:30',
        'salida'      => '11:30',
        'aprendices'  => 24
    ],
];

// Transformación a formato de eventos FullCalendar
$eventosCalendario = [];
foreach ($fichas as $f) {
    $eventosCalendario[] = [
        'id'          => $f['id'],
        'title'       => $f['ficha'] . ' - ' . $f['programa'],
        'start'       => $f['fecha'] . 'T' . $f['entrada'] . ':00',
        'end'         => $f['fecha'] . 'T' . $f['salida'] . ':00',
        'extendedProps' => [
            'ficha'      => $f['ficha'],
            'programa'   => $f['programa'],
            'instructor' => $f['instructor'],
            'entrada'    => $f['entrada'],
            'salida'     => $f['salida'],
            'aprendices' => $f['aprendices'],
            'fecha'      => $f['fecha']
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>
    <!-- FullCalendar CDN -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <style>
        .fc-event { cursor: pointer; }
        .fc .fc-button-primary {
            background-color: var(--bs-success, #39a900);
            border-color: var(--bs-success, #39a900);
        }
        .fc .fc-button-primary:hover, .fc .fc-button-primary:focus {
            background-color: #2e8b00;
            border-color: #2e8b00;
        }
    </style>
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
      <form id="formFicha" action="guardar_ficha.php" method="POST">
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
                    <option value="Juan Camilo Vanegas">Juan Camilo Vanegas</option>
                    <option value="Marcela Ortiz">Marcela Ortiz</option>
                    <option value="Héctor Ramírez">Héctor Ramírez</option>
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
        events: eventos,
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
            document.getElementById('form_instructor').value = props.instructor;
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