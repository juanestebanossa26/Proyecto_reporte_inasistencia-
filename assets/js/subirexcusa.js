$(document).ready(function () {
  $("#tablaExcusas").DataTable({
    language: typeof DATATABLES_ESPANOL !== "undefined" ? DATATABLES_ESPANOL : {},
    pageLength: 5,
    lengthMenu: [5, 10, 25, 50],
    ordering: true,
    autoWidth: false,
  });

  // "Marcar todas" las fechas
  $("#btnSeleccionarTodas").on("click", function () {
    var checks = $(".fecha-excusa-check");
    var faltanPorMarcar = checks.filter(":not(:checked)").length > 0;
    checks.prop("checked", faltanPorMarcar);
    checks.each(function () {
      $(this).closest(".fecha-item-card").toggleClass("is-selected", faltanPorMarcar);
    });
    $(this).text(faltanPorMarcar ? "Desmarcar todas" : "Marcar todas");
  });

  // Resaltar tarjeta al marcar checkbox individual
  $(document).on("change", ".fecha-excusa-check", function () {
    $(this).closest(".fecha-item-card").toggleClass("is-selected", this.checked);
  });

  // Manejo del Dropzone Minimalista
  var archivoInput = document.getElementById("archivoInput");
  var dropzonePrompt = document.getElementById("dropzonePrompt");
  var dropzonePreview = document.getElementById("dropzonePreview");
  var fileNamePreview = document.getElementById("fileNamePreview");
  var btnQuitarArchivo = document.getElementById("btnQuitarArchivo");

  if (archivoInput) {
    archivoInput.addEventListener("change", function () {
      if (this.files && this.files.length > 0) {
        var file = this.files[0];
        if (fileNamePreview) fileNamePreview.textContent = file.name;
        if (dropzonePrompt) {
          dropzonePrompt.classList.add("d-none");
          dropzonePrompt.classList.remove("d-flex");
        }
        if (dropzonePreview) {
          dropzonePreview.classList.remove("d-none");
          dropzonePreview.classList.add("d-flex");
        }
      }
    });

    if (btnQuitarArchivo) {
      btnQuitarArchivo.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        archivoInput.value = "";
        if (dropzonePreview) {
          dropzonePreview.classList.add("d-none");
          dropzonePreview.classList.remove("d-flex");
        }
        if (dropzonePrompt) {
          dropzonePrompt.classList.remove("d-none");
          dropzonePrompt.classList.add("d-flex");
        }
      });
    }
  }

  // Drag and drop visual feedback
  var dropzoneBox = document.getElementById("dropzoneBox");
  if (dropzoneBox) {
    ["dragenter", "dragover"].forEach(function (eventName) {
      dropzoneBox.addEventListener(eventName, function (e) {
        e.preventDefault();
        dropzoneBox.classList.add("drag-over");
      });
    });
    ["dragleave", "drop"].forEach(function (eventName) {
      dropzoneBox.addEventListener(eventName, function (e) {
        e.preventDefault();
        dropzoneBox.classList.remove("drag-over");
      });
    });
  }

  // Botón "Editar y reenviar": precarga el modal con el motivo actual
  var modalReenviarEl = document.getElementById("modalReenviarExcusa");
  var modalReenviarBs = modalReenviarEl
    ? new bootstrap.Modal(modalReenviarEl)
    : null;

  $("#tablaExcusas").on("click", ".btn-reenviar-excusa", function () {
    var btn = $(this);
    $("#reenvio_id_excusa").val(btn.data("id"));
    $("#reenvio_motivo").val(btn.data("motivo"));

    var obs = btn.data("observacion");
    if (obs && String(obs).trim() !== "") {
      $("#reenvio_obs_texto").text(obs);
      $("#reenvio_obs_container").removeClass("d-none");
    } else {
      $("#reenvio_obs_container").addClass("d-none");
    }

    if (modalReenviarBs) modalReenviarBs.show();
  });
});
