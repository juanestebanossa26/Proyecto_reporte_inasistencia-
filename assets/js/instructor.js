$(document).ready(function () {
  // 1. Inicializar DataTables
  $("#tablaInstructores").DataTable({
    language: {
      url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json",
    },
    pageLength: 10,
    responsive: true,
    columnDefs: [{ orderable: false, targets: 5 }],
  });

  let modalInstructorEl = document.getElementById("modalInstructor");
  let bsModalInstructor = modalInstructorEl
    ? new bootstrap.Modal(modalInstructorEl)
    : null;

  // 2. Botón NUEVO instructor
  $(".btn-nuevo-instructor").on("click", function () {
    $("#formInstructor")[0].reset();
    $("#formInstructor").attr(
      "action",
      BASE_URL + "/index.php?accion=guardarInstructor",
    );

    $("#inst_id_usuario").val("0");
    $("#modalInstructorTitulo").text("Nuevo instructor");
    $("#btnSubmitInstructor").text("Guardar instructor");

    $("#inst_password")
      .prop("required", true)
      .attr("placeholder", "Se usará para iniciar sesión");
    $("#ayudaPassword").addClass("d-none");
  });

  // 3. Botón EDITAR instructor (Delegado en la tabla)
  $("#tablaInstructores").on("click", ".btn-editar-instructor", function () {
    let btn = $(this);

    $("#formInstructor").attr(
      "action",
      BASE_URL + "/index.php?accion=actualizarInstructor",
    );
    $("#modalInstructorTitulo").text("Actualizar instructor");
    $("#btnSubmitInstructor").text("Guardar cambios");

    $("#inst_id_usuario").val(btn.data("id"));
    $("#inst_nombre").val(btn.data("nombre"));
    $("#inst_usuario").val(btn.data("usuario") || btn.data("documento"));
    $("#inst_area").val(btn.data("area"));
    $("#inst_correo").val(btn.data("correo"));

    $("#inst_password")
      .val("")
      .prop("required", false)
      .attr("placeholder", "Dejar en blanco para mantener la actual");
    $("#ayudaPassword").removeClass("d-none");

    if (bsModalInstructor) bsModalInstructor.show();
  });

  // 4. Botón ACTIVAR / INACTIVAR (Delegado en la tabla)
  $("#tablaInstructores").on("click", ".btn-estado-instructor", function () {
    let btn = $(this);
    let id = btn.data("id");
    let nombre = btn.data("nombre") || "este instructor";
    let accionTipo = btn.data("accion-tipo");

    let esDesactivar = accionTipo === "desactivar";
    let estadoActualParaControlador = esDesactivar ? "Activo" : "Inactivo";

    let titulo = esDesactivar
      ? "¿Inactivar instructor?"
      : "¿Activar instructor?";
    let texto = esDesactivar
      ? "El instructor <b>" +
        $("<div>").text(nombre).html() +
        "</b> perderá el acceso al sistema."
      : "El instructor <b>" +
        $("<div>").text(nombre).html() +
        "</b> volverá a tener acceso.";
    let botonColor = esDesactivar ? "#dc3545" : "#39a900";
    let botonTexto = esDesactivar ? "Sí, inactivar" : "Sí, activar";

    if (typeof Swal !== "undefined") {
      Swal.fire({
        title: titulo,
        html: texto,
        icon: esDesactivar ? "warning" : "question",
        showCancelButton: true,
        confirmButtonColor: botonColor,
        cancelButtonColor: "#6c757d",
        confirmButtonText: botonTexto,
        cancelButtonText: "Cancelar",
      }).then(function (result) {
        if (result.isConfirmed) {
          $("#estado_inst_id_usuario").val(id);
          $("#estado_inst_actual").val(estadoActualParaControlador);
          $("#formCambiarEstadoInstructor").submit();
        }
      });
    } else {
      if (
        confirm(
          esDesactivar
            ? "¿Estás seguro de inactivar a " + nombre + "?"
            : "¿Estás seguro de activar a " + nombre + "?",
        )
      ) {
        $("#estado_inst_id_usuario").val(id);
        $("#estado_inst_actual").val(estadoActualParaControlador);
        $("#formCambiarEstadoInstructor").submit();
      }
    }
  });
});
