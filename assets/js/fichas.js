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

    // 3. Manejador para Reabrir Ficha
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
});
