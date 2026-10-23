/* ============================================================
   Lógica interactiva del módulo de Fichas de Caracterización
   ============================================================ */

function getModal(id) {
    const el = document.getElementById(id);
    if (!el || typeof bootstrap === 'undefined') return null;
    return bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
}

function abrirModalCrearFicha() {
    const form = document.getElementById('formFicha');
    if (form) form.reset();
    const idEl = document.getElementById('form_id_ficha');
    if (idEl) idEl.value = '0';
    const titulo = document.getElementById('modalTituloFicha');
    if (titulo) titulo.innerHTML = '<i class="bi bi-journal-bookmark text-success me-2"></i>Nueva Ficha';
    const btn = document.getElementById('btnGuardarFicha');
    if (btn) btn.textContent = 'Guardar Ficha';
    const modal = getModal('modalFicha');
    if (modal) modal.show();
}

$(document).ready(function() {
    // Inicializar DataTable si existe tabla de fichas
    if ($('#tablaFichas').length > 0) {
        $('#tablaFichas').DataTable({
            language: typeof DATATABLES_ESPANOL !== 'undefined' ? DATATABLES_ESPANOL : {},
            order: [[0, 'asc']],
            pageLength: 10
        });
    }

    // Filtrado interactivo en tiempo real de tarjetas de fichas
    const inputBuscar = document.getElementById('inputBuscarFicha');
    const btnLimpiar  = document.getElementById('btnLimpiarBusqueda');
    const contador    = document.getElementById('contadorFichasMostradas');
    const grid        = document.getElementById('contenedorFichasGrid');
    const emptySearch = document.getElementById('fichaSearchEmptyState');

    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            const cards = grid ? grid.querySelectorAll('.ficha-card') : [];
            let visibles = 0;

            cards.forEach(card => {
                const num  = card.getAttribute('data-numero') || '';
                const prog = card.getAttribute('data-programa') || '';
                const inst = card.getAttribute('data-instructor') || '';

                if (query === '' || num.includes(query) || prog.includes(query) || inst.includes(query)) {
                    card.style.display = '';
                    visibles++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (contador) {
                contador.textContent = visibles;
            }

            if (emptySearch) {
                if (visibles === 0 && cards.length > 0) {
                    emptySearch.classList.remove('d-none');
                } else {
                    emptySearch.classList.add('d-none');
                }
            }

            if (btnLimpiar) {
                btnLimpiar.style.display = query.length > 0 ? 'inline-block' : 'none';
            }
        });

        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', function() {
                inputBuscar.value = '';
                inputBuscar.dispatchEvent(new Event('input'));
                inputBuscar.focus();
            });
        }
    }

    // 1. Manejador para Editar Ficha
    $(document).on('click', '.btn-editar-ficha', function() {
        const id   = $(this).data('id');
        const num  = $(this).data('numero');
        const prog = $(this).data('programa');
        const inst = $(this).data('instructor');

        const idEl = document.getElementById('form_id_ficha');
        if (idEl) idEl.value = id;
        const numEl = document.getElementById('form_numero_ficha');
        if (numEl) numEl.value = num;
        const progEl = document.getElementById('form_nombre_programa');
        if (progEl) progEl.value = prog;
        const instEl = document.getElementById('form_id_instructor');
        if (instEl) instEl.value = inst || 0;

        const titulo = document.getElementById('modalTituloFicha');
        if (titulo) titulo.innerHTML = '<i class="bi bi-pencil-square text-success me-2"></i>Editar Ficha';
        const btn = document.getElementById('btnGuardarFicha');
        if (btn) btn.textContent = 'Guardar Cambios';

        const modal = getModal('modalFicha');
        if (modal) modal.show();
    });

    // 2. Manejador para Finalizar Ficha
    $(document).on('click', '.btn-finalizar-ficha', function() {
        const id   = $(this).data('id');
        const num  = $(this).data('numero');

        const idEl = document.getElementById('finalizar_id_ficha');
        if (idEl) idEl.value = id;
        const numEl = document.getElementById('finalizar_numero_ficha');
        if (numEl) numEl.textContent = num;
        const motivoEl = document.getElementById('finalizar_motivo');
        if (motivoEl) motivoEl.value = 'Culminación de etapa lectiva de formación';

        const modal = getModal('modalFinalizarFicha');
        if (modal) modal.show();
    });

    // 3. Manejador para Archivar Ficha
    $(document).on('click', '.btn-archivar-ficha', function() {
        const id  = $(this).data('id');
        const num = $(this).data('numero');

        const idEl = document.getElementById('archivar_id_ficha');
        if (idEl) idEl.value = id;
        const numEl = document.getElementById('archivar_numero_ficha');
        if (numEl) numEl.textContent = num;
        const motivoEl = document.getElementById('archivar_motivo');
        if (motivoEl) motivoEl.value = 'Archivado definitivo para custodia histórica';

        const modal = getModal('modalArchivarFicha');
        if (modal) modal.show();
    });

    // 4. Manejador para Reabrir Ficha
    $(document).on('click', '.btn-reabrir-ficha', function() {
        const id  = $(this).data('id');
        const num = $(this).data('numero');

        const idEl = document.getElementById('reabrir_id_ficha');
        if (idEl) idEl.value = id;
        const numEl = document.getElementById('reabrir_numero_ficha');
        if (numEl) numEl.textContent = num;
        const motivoEl = document.getElementById('reabrir_motivo');
        if (motivoEl) motivoEl.value = '';

        const modal = getModal('modalReabrirFicha');
        if (modal) modal.show();
    });

    // 5. Manejador para Ver Auditoría / Bitácora
    $(document).on('click', '.btn-ver-auditoria', function() {
        const id  = $(this).data('id');
        const num = $(this).data('numero');

        const numEl = document.getElementById('audit_num_ficha');
        if (numEl) numEl.textContent = num;
        $('#audit_loading').show();
        $('#audit_contenido').hide().empty();
        $('#audit_vacio').hide();

        const modal = getModal('modalAuditoriaFicha');
        if (modal) modal.show();

        const baseUrl = (typeof window.BASE_URL !== 'undefined') ? window.BASE_URL : (typeof BASE_URL !== 'undefined' ? BASE_URL : '');
        fetch(`${baseUrl}/index.php?accion=obtenerAuditoriaFicha&id_ficha=${id}`)
            .then(res => res.json())
            .then(data => {
                $('#audit_loading').hide();
                if (data.exito && data.eventos && data.eventos.length > 0) {
                    let html = '<div class="timeline-audit">';
                    data.eventos.forEach(ev => {
                        const badgeRes = ev.resultado === 'EXITOSO' 
                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle">EXITOSO</span>'
                            : (ev.resultado === 'RECHAZADO' 
                                ? '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">RECHAZADO</span>'
                                : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">ERROR</span>');
                        
                        let detallesTexto = '';
                        if (ev.detalles) {
                            try {
                                const det = typeof ev.detalles === 'string' ? JSON.parse(ev.detalles) : ev.detalles;
                                const items = [];
                                if (det.horarios_cancelados !== undefined) items.push(`Horarios cancelados: ${det.horarios_cancelados}`);
                                if (det.cuentas_conservadas !== undefined) items.push(`Cuentas conservadas: ${det.cuentas_conservadas}`);
                                if (det.cuentas_inactivadas !== undefined) items.push(`Cuentas inactivadas: ${det.cuentas_inactivadas}`);
                                if (det.error) items.push(`Detalle: ${det.error}`);
                                if (items.length > 0) {
                                    detallesTexto = `<div class="bg-light p-2 rounded small mt-1 text-muted">${items.join(' | ')}</div>`;
                                }
                            } catch(e) {}
                        }

                        html += `
                            <div class="timeline-audit-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-dark">${ev.accion} ${badgeRes}</strong>
                                    <small class="text-muted">${ev.created_at}</small>
                                </div>
                                <div class="small text-muted mb-1">
                                    Responsable: <strong>${ev.usuario_nombre || 'Sistema'}</strong> 
                                    ${ev.estado_anterior ? `(${ev.estado_anterior} &rarr; ${ev.estado_nuevo})` : ''}
                                </div>
                                ${ev.motivo ? `<div class="small fst-italic text-secondary">"${ev.motivo}"</div>` : ''}
                                ${detallesTexto}
                            </div>
                        `;
                    });
                    html += '</div>';
                    $('#audit_contenido').html(html).show();
                } else {
                    $('#audit_vacio').show();
                }
            })
            .catch(() => {
                $('#audit_loading').hide();
                $('#audit_contenido').html('<div class="alert alert-danger small">No se pudo cargar la bitácora.</div>').show();
            });
    });

    // 6. Manejador para Eliminar Ficha
    $(document).on('click', '.btn-eliminar-ficha', function() {
        const id         = $(this).data('id');
        const num        = $(this).data('numero');
        const aprendices = parseInt($(this).data('aprendices'), 10) || 0;
        const horarios   = parseInt($(this).data('horarios'), 10) || 0;

        if (aprendices > 0 || horarios > 0) {
            Swal.fire({
                title: 'Eliminación no permitida',
                html: `La ficha <strong>${num}</strong> posee <strong>${aprendices} aprendices</strong> y <strong>${horarios} horarios</strong>.<br><br>Por políticas de trazabilidad y memoria académica institucional, no es posible eliminar fichas con registros asociados.<br><br>Utiliza la opción <strong>Finalizar</strong> o <strong>Archivar</strong> para gestionar su ciclo de vida.`,
                icon: 'warning',
                confirmButtonColor: '#39A900',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        Swal.fire({
            title: `¿Eliminar ficha ${num}?`,
            text: 'Esta acción es irreversible y solo está autorizada para fichas vacías sin actividad académica creada por error.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#546e7a',
            confirmButtonText: 'Sí, eliminar ficha',
            cancelButtonText: 'Cancelar',
            input: 'text',
            inputPlaceholder: 'Ingresa el motivo de eliminación...',
            inputValidator: (value) => {
                if (!value || value.trim().length < 5) {
                    return 'Debes ingresar un motivo de al menos 5 caracteres.';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const idEl = document.getElementById('inputEliminarFichaId');
                if (idEl) idEl.value = id;
                const motivoEl = document.getElementById('inputEliminarFichaMotivo');
                if (motivoEl) motivoEl.value = result.value.trim();
                const form = document.getElementById('formEliminarFichaOculto');
                if (form) form.submit();
            }
        });
    });
});
