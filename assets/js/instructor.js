document.addEventListener("DOMContentLoaded", () => {
  const modalElement = document.getElementById("modalInstructor");
  if (!modalElement) return;

  const modalBootstrap = new bootstrap.Modal(modalElement);
  const form = document.getElementById("formInstructor");
  const modalTitulo = document.getElementById("modalInstructorTitulo");
  const btnSubmit = document.getElementById("btnSubmitInstructor");
  const ayudaPassword = document.getElementById("ayudaPassword");

  // Inputs y selects del formulario
  const inputId = document.getElementById("inst_id_usuario");
  const inputNombre = document.getElementById("inst_nombre");
  const inputApellido = document.getElementById("inst_apellido");
  const inputDoc = document.getElementById("inst_documento");
  const selectArea = document.getElementById("inst_area"); // Ahora es el select
  const inputCorreo = document.getElementById("inst_correo");
  const inputPass = document.getElementById("inst_password");

  // Modo 1: Nuevo Instructor
  document.querySelectorAll(".btn-nuevo-instructor").forEach((btn) => {
    btn.addEventListener("click", () => {
      form.reset();
      form.action = `${BASE_URL}/index.php?accion=guardarInstructor`;
      modalTitulo.textContent = "Nuevo instructor";
      btnSubmit.textContent = "Guardar instructor";

      inputId.value = "";
      selectArea.value = ""; // Reinicia a la opción deshabilitada
      inputPass.required = true;
      ayudaPassword.classList.add("d-none");

      modalBootstrap.show();
    });
  });

  // Modo 2: Actualizar Instructor
  document.querySelectorAll(".btn-editar-instructor").forEach((btn) => {
    btn.addEventListener("click", function () {
      form.reset();
      form.action = `${BASE_URL}/index.php?accion=actualizarInstructor`;
      modalTitulo.textContent = "Actualizar instructor";
      btnSubmit.textContent = "Guardar cambios";

      // Poblar valores desde los atributos data-* del botón
      inputId.value = this.dataset.id || "";
      inputNombre.value = this.dataset.nombre || "";
      inputApellido.value = this.dataset.apellido || "";
      inputDoc.value = this.dataset.documento || "";
      selectArea.value = this.dataset.area || ""; // Selecciona automáticamente 'TIC' o 'Comercio'
      inputCorreo.value = this.dataset.correo || "";

      // En edición la contraseña no es obligatoria
      inputPass.required = false;
      ayudaPassword.classList.remove("d-none");

      modalBootstrap.show();
    });
  });
});
