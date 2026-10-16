// ==========================================================================
// Módulo de Horarios y Programación Académica — Sistema SENA
// Archivo: assets/js/horarios.js
// ==========================================================================

// Estado de sesión y datos provistos por PHP
let esAdmin = false;
let todosLosEventos = [];
let fichasDisponibles = [];
let coloresFicha = {};
let proximaClaseInfo = null;

// Cargar datos del servidor desde el bloque JSON incrustado o desde window
(function cargarDatosServidor() {
    const dataEl = document.getElementById('horarios-data');
    if (dataEl && dataEl.textContent.trim() !== '') {
        try {
            const config = JSON.parse(dataEl.textContent);
            esAdmin = Boolean(config.esAdmin);
            todosLosEventos = Array.isArray(config.todosLosEventos) ? config.todosLosEventos : [];
            fichasDisponibles = Array.isArray(config.fichasDisponibles) ? config.fichasDisponibles : [];
            coloresFicha = (typeof config.coloresFicha === 'object' && config.coloresFicha !== null) ? config.coloresFicha : {};
            proximaClaseInfo = config.proximaClaseInfo || null;
        } catch (e) {
            console.error('Error al parsear los datos de horarios:', e);
        }
    } else if (window.horariosData) {
        esAdmin = Boolean(window.horariosData.esAdmin);
        todosLosEventos = window.horariosData.todosLosEventos || [];
        fichasDisponibles = window.horariosData.fichasDisponibles || [];
        coloresFicha = window.horariosData.coloresFicha || {};
        proximaClaseInfo = window.horariosData.proximaClaseInfo || null;
    }
})();

// Modales Bootstrap e Instancias
let modalFichaBs, modalDetalleBs, modalEliminarBs, modalProxBs;
let calendar;
let eventoActual = null;

function inicializarHorarios() {
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
    if (!calendarEl || !window.FullCalendar) return;

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
    const btnToday = document.getElementById('btnToday');
    if (btnToday) btnToday.addEventListener('click', irAHoy);

    const btnPrev = document.getElementById('btnPrev');
    if (btnPrev) btnPrev.addEventListener('click', () => { if (calendar) calendar.prev(); });

    const btnNext = document.getElementById('btnNext');
    if (btnNext) btnNext.addEventListener('click', () => { if (calendar) calendar.next(); });

    // Toggle de visualización de panel de filtros
    const btnToggleFiltros = document.getElementById('btnToggleFiltros');
    if (btnToggleFiltros) btnToggleFiltros.addEventListener('click', toggleOcultarFiltros);

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
            if (modalDetalleBs) modalDetalleBs.hide();
            if (eventoActual) abrirModalEditar(eventoActual);
        });
    }

    const btnEliminar = document.getElementById('btnEliminarDesdeDetalle');
    if (btnEliminar) {
        btnEliminar.addEventListener('click', function() {
            if (modalDetalleBs) modalDetalleBs.hide();
            if (eventoActual) {
                const idInput = document.getElementById('eliminar_horario_id');
                if (idInput) idInput.value = eventoActual.id;
                if (modalEliminarBs) modalEliminarBs.show();
            }
        });
    }

    // Autocompletar nombre de programa al ingresar número de ficha
    const inputNumFicha = document.getElementById('form_numero_ficha');
    const inputProgFicha = document.getElementById('form_programa');
    if (inputNumFicha && inputProgFicha) {
        const actualizarProgramaSegunFicha = function() {
            const numVal = inputNumFicha.value.trim().toLowerCase();
            if (!numVal) return;
            const encontrada = fichasDisponibles.find(f => 
                String(f.numero_ficha).trim().toLowerCase() === numVal
            );
            if (encontrada && encontrada.nombre_programa) {
                inputProgFicha.value = encontrada.nombre_programa;
            }
        };
        inputNumFicha.addEventListener('input', actualizarProgramaSegunFicha);
        inputNumFicha.addEventListener('change', actualizarProgramaSegunFicha);
    }

    // Validación del formulario de Guardar/Editar Horario
    const formFichaModal = document.getElementById('formFicha');
    if (formFichaModal) {
        formFichaModal.addEventListener('submit', function(e) {
            const numFicha = (document.getElementById('form_numero_ficha')?.value || '').trim();
            const fecha = (document.getElementById('form_fecha')?.value || '').trim();
            const hEntrada = (document.getElementById('form_hora_entrada')?.value || '').trim();
            const hSalida = (document.getElementById('form_hora_salida')?.value || '').trim();

            if (!numFicha || !fecha || !hEntrada || !hSalida) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Campos requeridos',
                        text: 'Por favor completa todos los campos obligatorios.',
                        confirmButtonColor: '#39a900'
                    });
                }
                return;
            }

            // Comprobar que la hora de entrada sea estrictamente menor que la de salida
            if (hEntrada >= hSalida) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Horas incongruentes',
                        text: `La hora de entrada (${hEntrada}) debe ser anterior a la hora de salida (${hSalida}).`,
                        confirmButtonColor: '#39a900'
                    });
                } else {
                    alert(`La hora de entrada (${hEntrada}) debe ser anterior a la hora de salida (${hSalida}).`);
                }
                return;
            }

            // Validar existencia de la ficha en la lista disponible
            if (fichasDisponibles.length > 0) {
                const existeFicha = fichasDisponibles.some(f => 
                    String(f.numero_ficha).trim().toLowerCase() === numFicha.toLowerCase()
                );
                if (!existeFicha) {
                    e.preventDefault();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Ficha no registrada',
                            text: `El número de ficha '${numFicha}' no está registrado en el sistema. Debes crearla previamente en el módulo de Fichas.`,
                            confirmButtonColor: '#39a900'
                        });
                    } else {
                        alert(`El número de ficha '${numFicha}' no está registrado en el sistema.`);
                    }
                    return;
                }
            }
        });
    }

    // Validación y spinner al importar Excel
    const formImportar = document.getElementById('formImportarExcel');
    const inputExcel = document.getElementById('inputArchivoExcel');

    if (inputExcel) {
        inputExcel.addEventListener('change', function() {
            const archivo = this.files[0];
            if (!archivo) return;

            const nombre = archivo.name.toLowerCase();
            const esValido = nombre.endsWith('.xlsx') || nombre.endsWith('.xls');

            if (!esValido) {
                this.value = '';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Formato no permitido',
                        text: 'Formato no válido. Solo se permiten archivos Excel (.xlsx, .xls).',
                        confirmButtonColor: '#39a900'
                    });
                } else {
                    alert('Formato no válido. Solo se permiten archivos Excel (.xlsx, .xls).');
                }
                return;
            }

            if (archivo.size === 0) {
                this.value = '';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Archivo vacío',
                        text: 'El archivo seleccionado está vacío (0 bytes). Por favor selecciona un archivo con datos.',
                        confirmButtonColor: '#39a900'
                    });
                }
                return;
            }

            if (archivo.size > 15 * 1024 * 1024) {
                this.value = '';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Archivo muy grande',
                        text: 'El archivo supera el tamaño máximo permitido de 15MB.',
                        confirmButtonColor: '#39a900'
                    });
                }
                return;
            }
        });
    }

    if (formImportar) {
        formImportar.addEventListener('submit', function(e) {
            const archivo = inputExcel ? inputExcel.files[0] : null;
            if (!archivo) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Archivo requerido',
                        text: 'Por favor selecciona un archivo de Excel antes de importar.',
                        confirmButtonColor: '#39a900'
                    });
                }
                return;
            }

            const btn = document.getElementById('btnSubirExcel');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Importando datos...';
            }
        });
    }
}

// Iniciar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarHorarios);
} else {
    inicializarHorarios();
}

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

    const titleEl = document.getElementById('calendarTitle');
    if (!titleEl) return;

    if (view.type === 'timeGridDay') {
        const d = calendar.getDate();
        const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const diaNombre = diasSemana[d.getDay()];
        const mesNombre = meses[d.getMonth()];
        titleEl.innerText = `${diaNombre}, ${d.getDate()} de ${mesNombre} de ${d.getFullYear()}`;
    } else if (view.type === 'timeGridWeek') {
        const start = new Date(view.currentStart);
        // currentEnd en FullCalendar es exclusivo (un día después)
        const end = new Date(view.currentEnd);
        end.setDate(end.getDate() - 1);

        if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
            titleEl.innerText = `${start.getDate()} al ${end.getDate()} de ${meses[start.getMonth()]} de ${start.getFullYear()}`;
        } else if (start.getFullYear() === end.getFullYear()) {
            titleEl.innerText = `${start.getDate()} de ${meses[start.getMonth()]} al ${end.getDate()} de ${meses[end.getMonth()]} de ${start.getFullYear()}`;
        } else {
            titleEl.innerText = `${start.getDate()} de ${meses[start.getMonth()]} ${start.getFullYear()} al ${end.getDate()} de ${meses[end.getMonth()]} ${end.getFullYear()}`;
        }
    } else {
        const fecha = calendar.getDate();
        const mes = meses[fecha.getMonth()];
        const anio = fecha.getFullYear();
        titleEl.innerText = `${mes} de ${anio}`;
    }
}

function cambiarVista(vistaNombre) {
    if (!calendar) return;
    calendar.changeView(vistaNombre);
    
    // Actualizar botones de vista
    document.querySelectorAll('.cal-view-group .btn').forEach(b => b.classList.remove('active'));
    const btnDay = document.getElementById('btnViewDay');
    const btnWeek = document.getElementById('btnViewWeek');
    const btnMonth = document.getElementById('btnViewMonth');

    if (vistaNombre === 'timeGridDay' && btnDay) btnDay.classList.add('active');
    if (vistaNombre === 'timeGridWeek' && btnWeek) btnWeek.classList.add('active');
    if (vistaNombre === 'dayGridMonth' && btnMonth) btnMonth.classList.add('active');
}

function toggleOcultarFiltros() {
    const sidebar = document.getElementById('panelFiltrosFichas');
    if (!sidebar) return;
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
    if (!calendar) return;
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

    const header = document.getElementById('modalDetalleHeader');
    if (header) header.style.backgroundColor = color;

    const elFicha = document.getElementById('modalDetalleFicha');
    if (elFicha) elFicha.innerText = `Ficha ${p.ficha}`;

    const elProg = document.getElementById('modalDetallePrograma');
    if (elProg) elProg.innerText = p.programa || 'Programa sin especificar';

    const elHorario = document.getElementById('modalDetalleHorarioTexto');
    if (elHorario) elHorario.innerText = `${p.entrada} – ${p.salida}`;
    
    // Duración
    const duracionTxt = p.duracion_horas ? `${p.duracion_horas} horas` : (p.duracion_min ? `${p.duracion_min} min` : 'No calculada');
    const elDur = document.getElementById('modalDetalleDuracion');
    if (elDur) elDur.innerText = duracionTxt;

    // Formatear fecha legible
    if (p.fecha) {
        const fObj = new Date(p.fecha + 'T12:00:00');
        const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const fechaTxt = fObj.toLocaleDateString('es-ES', opciones);
        const elFecha = document.getElementById('modalDetalleFechaTexto');
        if (elFecha) elFecha.innerText = fechaTxt.charAt(0).toUpperCase() + fechaTxt.slice(1);
    }

    const elInst = document.getElementById('modalDetalleInstructor');
    if (elInst) elInst.innerText = p.instructor || 'Sin asignar';
    
    // Área / Especialidad si existe
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

    const elAprendices = document.getElementById('modalDetalleAprendices');
    if (elAprendices) elAprendices.innerText = `${p.aprendices || 0} aprendices matriculados`;

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

    if (modalDetalleBs) modalDetalleBs.show();
}

function abrirModalCrear(fecha = '', horaEntrada = '07:00', horaSalida = '13:00') {
    const form = document.getElementById('formFicha');
    if (form) form.reset();

    const hidInput = document.getElementById('form_horario_id');
    if (hidInput) hidInput.value = '';

    const selInst = document.getElementById('form_instructor');
    if (selInst) selInst.value = '0';

    const titModal = document.getElementById('modalTitulo');
    if (titModal) titModal.innerText = 'Programar horario';
    
    const fechaInput = document.getElementById('form_fecha');
    if (fechaInput) {
        if (fecha) {
            fechaInput.value = fecha;
        } else {
            const hoy = new Date().toISOString().split('T')[0];
            fechaInput.value = hoy;
        }
    }

    const entradaInput = document.getElementById('form_hora_entrada');
    if (entradaInput) entradaInput.value = horaEntrada;

    const salidaInput = document.getElementById('form_hora_salida');
    if (salidaInput) salidaInput.value = horaSalida;

    habilitarCampos(true);
    if (modalFichaBs) modalFichaBs.show();
}

function abrirModalEditar(evento) {
    const p = evento.extendedProps || {};
    const titModal = document.getElementById('modalTitulo');
    if (titModal) titModal.innerText = 'Editar horario';

    const hidInput = document.getElementById('form_horario_id');
    if (hidInput) hidInput.value = evento.id;

    const fichaInput = document.getElementById('form_numero_ficha');
    if (fichaInput) fichaInput.value = p.ficha || '';

    const progInput = document.getElementById('form_programa');
    if (progInput) progInput.value = p.programa || '';

    const fechaInput = document.getElementById('form_fecha');
    if (fechaInput) fechaInput.value = p.fecha || '';

    const aprInput = document.getElementById('form_aprendices');
    if (aprInput) aprInput.value = p.aprendices || 0;

    const selInst = document.getElementById('form_instructor');
    if (selInst) {
        if (p.id_instructor && p.id_instructor > 0) {
            selInst.value = p.id_instructor;
        } else {
            selInst.value = '0';
        }
    }

    const entradaInput = document.getElementById('form_hora_entrada');
    if (entradaInput) entradaInput.value = p.entrada || '';

    const salidaInput = document.getElementById('form_hora_salida');
    if (salidaInput) salidaInput.value = p.salida || '';

    habilitarCampos(esAdmin);
    if (modalFichaBs) modalFichaBs.show();
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

// Exponer funciones en el objeto global window para invocación desde HTML (onclick, onchange)
window.irAHoy = irAHoy;
window.actualizarTituloPeriodo = actualizarTituloPeriodo;
window.cambiarVista = cambiarVista;
window.toggleOcultarFiltros = toggleOcultarFiltros;
window.toggleTodasFichas = toggleTodasFichas;
window.limpiarFiltros = limpiarFiltros;
window.filtrarFichas = filtrarFichas;
window.abrirDetalleHorario = abrirDetalleHorario;
window.abrirModalCrear = abrirModalCrear;
window.abrirModalEditar = abrirModalEditar;
window.habilitarCampos = habilitarCampos;
window.abrirDetalleProximaClase = abrirDetalleProximaClase;
window.ubicarProximaClaseEnCalendario = ubicarProximaClaseEnCalendario;
