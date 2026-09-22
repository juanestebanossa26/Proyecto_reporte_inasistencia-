$(document).ready(function() {
    $('#tablaAprendices').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        },
        order: [],
        pageLength: 10,
        responsive: true,
        columnDefs: [
            { targets: 5, orderable: false, searchable: false }
        ]
    });

    var modalAprendizEl = document.getElementById('modalAprendiz');
    var bsModalAprendiz = modalAprendizEl ? new bootstrap.Modal(modalAprendizEl) : null;

    // Botón NUEVO aprendiz
    $('button[data-bs-target="#modalAprendiz"]').on('click', function() {
        $('#formAprendiz')[0].reset();
        $('#modal_id_usuario').val('0');
        $('#modalAprendizTitulo').text('Nuevo aprendiz');
        $('#modalAprendizBoton').text('Guardar aprendiz');
        $('#modal_password').prop('required', true).attr('placeholder', 'Se usará para iniciar sesión');
        $('#modal_password_help').addClass('d-none');
    });

    // Botón EDITAR aprendiz
    $('#tablaAprendices').on('click', '.btn-editar', function() {
        var btn = $(this);

        $('#modal_id_usuario').val(btn.data('id'));
        $('#modal_nombre').val(btn.data('nombre'));
        $('#modal_apellido').val(btn.data('apellido'));
        $('#modal_documento').val(btn.data('documento'));
        $('#modal_correo').val(btn.data('correo'));
        $('#modal_codigo_rfid').val(btn.data('rfid'));
        $('#modal_ficha_id').val(btn.data('ficha'));

        $('#modalAprendizTitulo').text('Editar aprendiz');
        $('#modalAprendizBoton').text('Actualizar aprendiz');
        $('#modal_password').val('').prop('required', false).attr('placeholder', 'Dejar en blanco para mantener la actual');
        $('#modal_password_help').removeClass('d-none');

        if (bsModalAprendiz) {
            bsModalAprendiz.show();
        }
    });
});
