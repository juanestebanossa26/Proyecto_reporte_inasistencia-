/* ============================================================
   Lógica interactiva para los paneles Dashboard (Admin e Instructor)
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
  function setupPagination(contenedorId, controlesId, selectorItem, porPagina, unidad) {
    const contenedor = document.getElementById(contenedorId);
    const controles = document.getElementById(controlesId);
    if (!contenedor || !controles) return;

    const items = Array.from(contenedor.querySelectorAll(selectorItem));
    const total = items.length;
    if (total === 0) return;

    let pagina = 1;
    const maxPag = Math.ceil(total / porPagina);

    const btnPrev = controles.querySelector('.btn-prev');
    const btnNext = controles.querySelector('.btn-next');
    const info = controles.querySelector('.info-paginacion');
    const ind = controles.querySelector('.indicador-pagina');

    function cambiar(p) {
      pagina = p;
      const ini = (pagina - 1) * porPagina;
      const fin = ini + porPagina;

      items.forEach((item, i) => {
        item.style.display = (i >= ini && i < fin) ? '' : 'none';
      });

      if (info) {
        info.textContent = `Mostrando ${ini + 1} - ${Math.min(fin, total)} de ${total} ${unidad}`;
      }
      if (ind) {
        ind.textContent = `Pág. ${pagina} de ${maxPag}`;
      }
      if (btnPrev) btnPrev.disabled = (pagina <= 1);
      if (btnNext) btnNext.disabled = (pagina >= maxPag);

      if (maxPag <= 1) {
        if (btnPrev) btnPrev.style.display = 'none';
        if (btnNext) btnNext.style.display = 'none';
        if (ind) ind.style.display = 'none';
      }
    }

    if (btnPrev) {
      btnPrev.onclick = function () {
        if (pagina > 1) cambiar(pagina - 1);
      };
    }
    if (btnNext) {
      btnNext.onclick = function () {
        if (pagina < maxPag) cambiar(pagina + 1);
      };
    }

    cambiar(1);
  }

  // Inicialización condicional para Dashboard de Administrador
  setupPagination('tablaIngresosAdmin', 'paginacionIngresosAdmin', 'tbody tr.fila-dashboard', 5, 'registros');
  setupPagination('tablaFichasAdmin', 'paginacionFichasAdmin', 'tbody tr.fila-ficha-admin', 5, 'fichas');

  // Inicialización condicional para Dashboard de Instructor
  setupPagination('tablaIngresosInstructor', 'paginacionIngresosInstructor', 'tbody tr.fila-dashboard', 5, 'registros');
  setupPagination('listaMisFichas', 'paginacionFichasInstructor', '.fila-ficha-dashboard', 3, 'fichas');
});
