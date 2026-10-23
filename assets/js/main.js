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


/* ============================================================
   Paginación interactiva para tablas de Dashboard (ej. Ingresos Recientes)
   ============================================================ */
function initDashboardTablePagination(tablaId, controlesId, porPagina = 5) {
  const tabla = document.getElementById(tablaId);
  const controles = document.getElementById(controlesId);
  if (!tabla || !controles) return;

  const filas = Array.from(tabla.querySelectorAll('tbody tr.fila-dashboard'));
  if (filas.length === 0) {
    controles.classList.add('d-none');
    return;
  }

  let paginaActual = 1;
  const totalPaginas = Math.ceil(filas.length / porPagina);

  const btnPrev = controles.querySelector('.btn-prev');
  const btnNext = controles.querySelector('.btn-next');
  const infoTexto = controles.querySelector('.info-paginacion');
  const indicador = controles.querySelector('.indicador-pagina');

  function render() {
    const inicio = (paginaActual - 1) * porPagina;
    const fin = inicio + porPagina;

    filas.forEach((fila, idx) => {
      fila.style.display = (idx >= inicio && idx < fin) ? '' : 'none';
    });

    if (infoTexto) {
      const hasta = Math.min(fin, filas.length);
      infoTexto.textContent = `Mostrando ${inicio + 1} - ${hasta} de ${filas.length} registros`;
    }

    if (indicador) {
      indicador.textContent = `Pág. ${paginaActual} de ${totalPaginas}`;
    }

    if (btnPrev) btnPrev.disabled = (paginaActual <= 1);
    if (btnNext) btnNext.disabled = (paginaActual >= totalPaginas);

    if (totalPaginas <= 1) {
      if (btnPrev) btnPrev.style.display = 'none';
      if (btnNext) btnNext.style.display = 'none';
      if (indicador) indicador.style.display = 'none';
    } else {
      if (btnPrev) btnPrev.style.display = '';
      if (btnNext) btnNext.style.display = '';
      if (indicador) indicador.style.display = '';
    }
  }

  if (btnPrev) {
    btnPrev.addEventListener('click', function () {
      if (paginaActual > 1) {
        paginaActual--;
        render();
      }
    });
  }

  if (btnNext) {
    btnNext.addEventListener('click', function () {
      if (paginaActual < totalPaginas) {
        paginaActual++;
        render();
      }
    });
  }

  render();
}
