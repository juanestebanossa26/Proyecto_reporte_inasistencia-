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
        $('#modal_usuario').val(btn.data('usuario') || btn.data('documento'));
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


    // Boton de activar y desativar aprendiz
    $('#tablaAprendices').on('click', '.btn-estado', function() {
        var btn = $(this);
        var id = btn.data('id');
        var nombre = btn.data('nombre') || 'este aprendiz';
        var accionTipo = btn.data('accion-tipo'); // 'desactivar' o 'activar'

        var esDesactivar = (accionTipo === 'desactivar');
        var titulo = esDesactivar ? '¿Desactivar aprendiz?' : '¿Activar aprendiz?';
        var texto = esDesactivar 
            ? 'El aprendiz <b>' + $('<div>').text(nombre).html() + '</b> pasará a estado <b>Inactivo</b>.'
            : 'El aprendiz <b>' + $('<div>').text(nombre).html() + '</b> pasará a estado <b>Activo</b>.';
        var botonColor = esDesactivar ? '#dc3545' : '#39a900';
        var botonTexto = esDesactivar ? 'Sí, desactivar' : 'Sí, activar';
        var nuevoEstado = esDesactivar ? 'Inactivo' : 'Activo';

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: titulo,
                html: texto,
                icon: esDesactivar ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonColor: botonColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: botonTexto,
                cancelButtonText: 'Cancelar'
            }).then(function(result) {
                if (result.isConfirmed) {
                    $('#estado_id_usuario').val(id);
                    $('#estado_nuevo').val(nuevoEstado);
                    $('#formCambiarEstado').submit();
                }
            });
        } else {
            if (confirm(esDesactivar ? '¿Estás seguro de desactivar a ' + nombre + '?' : '¿Estás seguro de activar a ' + nombre + '?')) {
                $('#estado_id_usuario').val(id);
                $('#estado_nuevo').val(nuevoEstado);
                $('#formCambiarEstado').submit();
            }
        }
    });
});
