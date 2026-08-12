/* ============================================================
   Sidebar colapsable — recuerda el estado en localStorage
   ============================================================ */
(function () {
  const shell = document.querySelector(".app-shell");
  const toggleBtn = document.getElementById("sidebarToggle");
  const mobileToggleBtn = document.getElementById("sidebarMobileToggle");
  const backdrop = document.querySelector(".sidebar-backdrop");
  const STORAGE_KEY = "sidebar_collapsed";

  if (!shell) return;

  // Restaurar estado guardado (solo escritorio)
  if (window.innerWidth > 991) {
    const collapsed = localStorage.getItem(STORAGE_KEY) === "1";
    if (collapsed) shell.classList.add("sidebar-collapsed");
  }

  // Toggle escritorio: colapsa a solo íconos
  if (toggleBtn) {
    toggleBtn.addEventListener("click", function () {
      shell.classList.toggle("sidebar-collapsed");
      localStorage.setItem(
        STORAGE_KEY,
        shell.classList.contains("sidebar-collapsed") ? "1" : "0"
      );
    });
  }

  // Toggle móvil: abre/cierra el sidebar como drawer
  if (mobileToggleBtn) {
    mobileToggleBtn.addEventListener("click", function () {
      shell.classList.toggle("sidebar-mobile-open");
    });
  }
  if (backdrop) {
    backdrop.addEventListener("click", function () {
      shell.classList.remove("sidebar-mobile-open");
    });
  }

  // Activar tooltips de Bootstrap (útiles cuando el sidebar está colapsado)
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
    new bootstrap.Tooltip(el);
  });
})();
