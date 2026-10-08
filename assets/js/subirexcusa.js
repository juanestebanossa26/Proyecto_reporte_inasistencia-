$(document).ready(function () {
  $("#tablaExcusas").DataTable({
    language: {
      decimal: "",
      emptyTable: "No hay datos disponibles en la tabla",
      info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
      infoEmpty: "Mostrando 0 a 0 de 0 registros",
      infoFiltered: "(filtrado de _MAX_ registros totales)",
      infoPostFix: "",
      thousands: ",",
      lengthMenu: "Mostrar _MENU_ registros",
      loadingRecords: "Cargando...",
      processing: "Procesando...",
      search: "Buscar:",
      zeroRecords: "No se encontraron resultados",
      paginate: {
        first: "Primero",
        last: "Último",
        next: "Siguiente",
        previous: "Anterior",
      },
      aria: {
        sortAscending: ": activar para ordenar la columna de manera ascendente",
        sortDescending:
          ": activar para ordenar la columna de manera descendente",
      },
    },
    pageLength: 5,
    lengthMenu: [5, 10, 25, 50],
    ordering: true,
  });

  // "Marcar todas" las fechas, para no tener que dar clic una por una
  // cuando el mismo soporte cubre varios días.
  $("#btnSeleccionarTodas").on("click", function () {
    var checks = $(".fecha-excusa-check");
    var faltanPorMarcar = checks.filter(":not(:checked)").length > 0;
    checks.prop("checked", faltanPorMarcar);
    $(this).text(faltanPorMarcar ? "Desmarcar todas" : "Marcar todas");
  });

  // Botón "Editar y reenviar": precarga el modal con el motivo actual
  // y el id_excusa correcto, para que el reenvío sea sobre la misma excusa.
  var modalReenviarEl = document.getElementById("modalReenviarExcusa");
  var modalReenviarBs = modalReenviarEl
    ? new bootstrap.Modal(modalReenviarEl)
    : null;

  $("#tablaExcusas").on("click", ".btn-reenviar-excusa", function () {
    var btn = $(this);
    $("#reenvio_id_excusa").val(btn.data("id"));
    $("#reenvio_motivo").val(btn.data("motivo"));
    if (modalReenviarBs) modalReenviarBs.show();
  });
});
