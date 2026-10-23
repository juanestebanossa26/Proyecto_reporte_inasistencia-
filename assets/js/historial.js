/* ============================================================
   Inicialización de DataTable para Mi Historial de Asistencias
   ============================================================ */

$(document).ready(function () {
  $('#tablaHistorial').DataTable({
    language: typeof DATATABLES_ESPANOL !== 'undefined' ? DATATABLES_ESPANOL : {},
    order: [[0, 'desc']],
    pageLength: 10,
    lengthMenu: [10, 25, 50],
    responsive: true
  });
});
