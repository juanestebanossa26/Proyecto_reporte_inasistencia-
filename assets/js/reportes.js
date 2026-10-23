/* ============================================================
   Inicialización de DataTable para Reportes de Asistencias
   ============================================================ */

$(document).ready(function () {
  $('#tablaReportes').DataTable({
    language: typeof DATATABLES_ESPANOL !== 'undefined' ? DATATABLES_ESPANOL : {},
    order: [],
    pageLength: 10,
    responsive: true
  });
});
