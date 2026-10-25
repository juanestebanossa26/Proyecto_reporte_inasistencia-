$(document).ready(function() {
    $('#tablaAprendices').DataTable({
        language: typeof DATATABLES_ESPANOL !== 'undefined' ? DATATABLES_ESPANOL : {},
        order: [],
        pageLength: 10,
        autoWidth: false,
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
        $('#modal_password_req').removeClass('d-none');
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
        $('#modal_password_req').addClass('d-none');
        $('#modal_password_help').removeClass('d-none');

        if (bsModalAprendiz) {
            bsModalAprendiz.show();
        }
    });

    // Validación del formulario de aprendiz antes de enviar
    $('#formAprendiz').on('submit', function(e) {
        var nombre = $('#modal_nombre').val().trim();
        var usuario = $('#modal_usuario').val().trim();
        var fichaId = $('#modal_ficha_id').val();
        var rfid = $('#modal_codigo_rfid').val().trim();
        var correo = $('#modal_correo').val().trim();
        var idUsuario = parseInt($('#modal_id_usuario').val()) || 0;
        var pass = $('#modal_password').val();

        if (!nombre || !usuario || !fichaId) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Campos requeridos',
                text: 'Por favor completa los campos obligatorios: Nombre, Usuario/Documento y Ficha.',
                confirmButtonColor: '#39a900'
            });
            return;
        }

        var regexDoc = /^[a-zA-Z0-9\-_]{4,25}$/;
        if (!regexDoc.test(usuario)) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Documento no válido',
                text: 'El documento o usuario debe tener entre 4 y 25 caracteres alfanuméricos sin espacios.',
                confirmButtonColor: '#39a900'
            });
            return;
        }

        if (rfid !== '') {
            var regexRfid = /^[a-zA-Z0-9\-_]{3,50}$/;
            if (!regexRfid.test(rfid)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'TAG RFID no válido',
                    text: 'El código TAG RFID debe tener entre 3 y 50 caracteres alfanuméricos sin espacios.',
                    confirmButtonColor: '#39a900'
                });
                return;
            }
        }

        if (correo !== '') {
            var regexCorreo = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!regexCorreo.test(correo)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Correo no válido',
                    text: 'El formato del correo electrónico ingresado no es válido.',
                    confirmButtonColor: '#39a900'
                });
                return;
            }
        }

        if (idUsuario === 0 && !pass) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Contraseña requerida',
                text: 'Debes asignar una contraseña para el nuevo aprendiz.',
                confirmButtonColor: '#39a900'
            });
            return;
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

// Función para copiar el código RFID al portapapeles con confirmación visual
window.copiarTag = function(event, tag) {
    if (event) event.stopPropagation();
    if (!tag) return;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(tag).then(function() {
            mostrarToastCopiado(tag);
        }).catch(function() {
            fallbackCopiar(tag);
        });
    } else {
        fallbackCopiar(tag);
    }
};

function fallbackCopiar(tag) {
    var textArea = document.createElement("textarea");
    textArea.value = tag;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
        mostrarToastCopiado(tag);
    } catch (err) {
        console.error('Error al copiar:', err);
    }
    document.body.removeChild(textArea);
}

function mostrarToastCopiado(tag) {
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        Toast.fire({
            icon: 'success',
            title: 'TAG ' + tag + ' copiado'
        });
    }
}
