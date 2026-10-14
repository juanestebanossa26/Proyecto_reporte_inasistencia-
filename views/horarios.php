<?php
/**
 * horarios.php — Vista exclusiva de Horarios y Calendario
 */
$rolActivo     = $_SESSION['rol'] ?? 'Administrador';
$nombreUsuario = $_SESSION['nombre'] ?? '';
$paginaActiva  = 'horarios';
$tituloPagina  = 'Horarios y Calendario';

// Los eventos, fichas e instructores son suministrados desde la base de datos por HorarioControlador
$eventosCalendario = $eventosCalendario ?? [];
$instructores      = $instructores ?? [];
$fichas            = $fichas ?? [];

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
                    <h1 class="h4 fw-bold mb-0">Horarios y Calendario</h1>
                    <p class="text-suave mb-0">Haz clic en un día para programar un horario o en un bloque para editar sus detalles.</p>
                </div>
                <?php if ($rolActivo === 'Administrador'): ?>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalImportarExcel">
                        <i class="bi bi-file-earmark-excel me-1"></i> Importar Excel
                    </button>
                    <button class="btn btn-sena" onclick="abrirModalCrear()">
                        <i class="bi bi-plus-lg me-1"></i> Nuevo horario
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Contenedor del Calendario -->
            <div class="card p-3 shadow-sm border-0">
                <div id="calendar"></div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Unificado: Crear / Editar Horario -->
<div class="modal fade" id="modalFicha" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <form id="formFicha" action="<?= BASE_URL ?>/index.php?accion=guardarHorario" method="POST">
        <input type="hidden" name="horario_id" id="form_horario_id">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="modalTitulo">Programar horario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Número de ficha</label>
                    <input type="text" class="form-control" name="numero_ficha" id="form_numero_ficha" list="listaFichasSugeridas" required placeholder="Ej. 3064749">
                    <datalist id="listaFichasSugeridas">
                        <?php foreach ($fichas as $f): ?>
                            <option value="<?= htmlspecialchars($f['numero_ficha']) ?>"><?= htmlspecialchars($f['nombre_programa']) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Programa</label>
                    <input type="text" class="form-control" name="programa" id="form_programa" required placeholder="Ej. ADSO">
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label">Fecha</label>
                    <input type="date" class="form-control" name="fecha" id="form_fecha" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">N° Aprendices</label>
                    <input type="number" class="form-control" name="aprendices" id="form_aprendices" min="0" placeholder="0">
                </div>
            </div>

            <div class="mb-0 mt-3">
                <label class="form-label">Instructor encargado</label>
                <select class="form-select" name="instructor" id="form_instructor">
                    <option value="0">Seleccionar instructor...</option>
                    <?php foreach ($instructores as $inst): ?>
                        <option value="<?= $inst['id_usuario'] ?>"><?= htmlspecialchars($inst['nombre_completo']) ?></option>
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
          <button type="submit" class="btn btn-sena" id="btnGuardar">Guardar horario</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Importar Horarios desde Excel -->
<div class="modal fade" id="modalImportarExcel" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form id="formImportarExcel" action="<?= BASE_URL ?>/index.php?accion=importarHorariosExcel" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= Seguridad::tokenCsrf() ?>">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">
            <i class="bi bi-file-earmark-excel text-success me-2"></i>Importar Horarios desde Excel
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">
            Selecciona un archivo de Excel (<strong>.xlsx</strong> o <strong>.xls</strong>) para leer y guardar la programación de fichas y horarios en la base de datos.
          </p>

          <div class="mb-3">
            <label for="inputArchivoExcel" class="form-label fw-semibold">Archivo de Excel</label>
            <input class="form-control" type="file" id="inputArchivoExcel" name="archivo_excel" accept=".xlsx, .xls" required>
            <div class="form-text">Formatos permitidos: .xlsx, .xls (Máximo 15MB)</div>
          </div>

          <div class="card bg-light border-0 p-3 small text-secondary mb-0">
            <div class="d-flex align-items-center mb-1 text-dark fw-semibold">
              <i class="bi bi-info-circle text-success me-2"></i> Procesamiento automático:
            </div>
            <ul class="mb-0 ps-3">
              <li><strong>Fichas:</strong> Si la ficha no existe, se creará automáticamente en el sistema.</li>
              <li><strong>Instructores:</strong> Se comprobarán los instructores y se crearán si aún no están registrados.</li>
              <li><strong>Horarios:</strong> Se validarán y guardarán evitando registros duplicados.</li>
              <li><strong>Calendario:</strong> Los horarios importados aparecerán reflejados en este calendario.</li>
            </ul>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sena" id="btnSubirExcel">
            <i class="bi bi-cloud-arrow-up me-1"></i> Subir e Importar
          </button>
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
    document.getElementById('modalTitulo').innerText = 'Nuevo horario';
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
        contentHeight: 520,
        dayMaxEvents: 2,
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
        eventDidMount: function(info) {
            const props = info.event.extendedProps || {};
            const horario = (props.entrada && props.salida) ? `${props.entrada} - ${props.salida}` : '';
            const instructor = props.instructor || 'Sin asignar';
            info.el.setAttribute('title', `Ficha: ${props.ficha}\nPrograma: ${props.programa}\nHorario: ${horario}\nInstructor: ${instructor}`);
        },
        dateClick: function(info) {
            if (esAdmin) {
                abrirModalCrear(info.dateStr);
            }
        },
        eventClick: function(info) {
            const props = info.event.extendedProps;
            document.getElementById('modalTitulo').innerText = esAdmin ? 'Editar horario' : 'Detalles de horario';
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

    // Control de carga al enviar el formulario de importar Excel
    const formImportar = document.getElementById('formImportarExcel');
    if (formImportar) {
        formImportar.addEventListener('submit', function() {
            const btn = document.getElementById('btnSubirExcel');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Importando datos...';
        });
    }
});
</script>
</body>
</html>
