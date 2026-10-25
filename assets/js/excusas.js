$(document).ready(function () {
  // 1. Inicializar DataTables
  $("#tablaExcusas").DataTable({
    language: typeof DATATABLES_ESPANOL !== "undefined" ? DATATABLES_ESPANOL : {},
    order: [], // Evita que ordene automáticamente
    pageLength: 10,
    autoWidth: false,
    columnDefs: [
      { targets: 6, orderable: false, searchable: false }, // Desactiva orden en Acciones
    ],
  });

  // 2. Acción: APROBAR
  $("#tablaExcusas").on("click", ".btn-aprobar", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "este aprendiz";

    Swal.fire({
      title: "¿Aprobar excusa?",
      text:
        "La inasistencia de " + nombre + " quedará justificada en el sistema.",
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#39a900", // Verde SENA
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Sí, aprobar",
      cancelButtonText: "Cancelar",
      reverseButtons: true,
    }).then((result) => {
      if (result.isConfirmed) {
        $("#accion_id_excusa").val(id);
        $("#accion_tipo").val("aprobar");
        $("#accion_observacion").val(""); // Sin observación necesaria
        $("#formProcesarExcusa").submit();
      }
    });
  });

  // 3. Acción: DEVOLVER
  $("#tablaExcusas").on("click", ".btn-devolver", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "el aprendiz";

    Swal.fire({
      title: "¿Devolver excusa?",
      text:
        "Se habilitará la opción para que " +
        nombre +
        " suba un nuevo soporte.",
      icon: "warning",
      input: "textarea",
      inputLabel: "Motivo de la devolución (Obligatorio)",
      inputPlaceholder:
        "Ej: El documento está borroso, por favor adjunta una foto clara...",
      showCancelButton: true,
      confirmButtonColor: "#ffc107", // Amarillo
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Sí, devolver",
      cancelButtonText: "Cancelar",
      reverseButtons: true,
      preConfirm: (observacion) => {
        if (!observacion) {
          Swal.showValidationMessage(
            "Debes ingresar un motivo para devolver la excusa",
          );
        }
        return observacion;
      },
    }).then((result) => {
      if (result.isConfirmed) {
        $("#accion_id_excusa").val(id);
        $("#accion_tipo").val("devolver");
        $("#accion_observacion").val(result.value);
        $("#formProcesarExcusa").submit();
      }
    });
  });

  // 4. Acción: RECHAZAR
  $("#tablaExcusas").on("click", ".btn-rechazar", function () {
    var id = $(this).data("id");
    var nombre = $(this).data("nombre") || "el aprendiz";

    Swal.fire({
      title: "¿Rechazar excusa?",
      text:
        "La inasistencia de " +
        nombre +
        " no será justificada. Esta acción es definitiva.",
      icon: "error",
      input: "textarea",
      inputLabel: "Motivo del rechazo (Obligatorio)",
      inputPlaceholder:
        "Ej: El documento no corresponde a una entidad médica...",
      showCancelButton: true,
      confirmButtonColor: "#dc3545", // Rojo
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Sí, rechazar",
      cancelButtonText: "Cancelar",
      reverseButtons: true,
      preConfirm: (observacion) => {
        if (!observacion) {
          Swal.showValidationMessage(
            "Debes ingresar un motivo para rechazar la excusa",
          );
        }
        return observacion;
      },
    }).then((result) => {
      if (result.isConfirmed) {
        $("#accion_id_excusa").val(id);
        $("#accion_tipo").val("rechazar");
        $("#accion_observacion").val(result.value);
        $("#formProcesarExcusa").submit();
      }
    });
  });
});
