/**
 * Traducción oficial al español de DataTables (Local, sin dependencia de CDN)
 */
const DATATABLES_ESPANOL = {
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
  zeroRecords: "No se encontraron resultados coincidentes",
  paginate: {
    first: "Primero",
    last: "Último",
    next: "Siguiente",
    previous: "Anterior",
  },
  aria: {
    sortAscending: ": activar para ordenar la columna de manera ascendente",
    sortDescending: ": activar para ordenar la columna de manera descendente",
  },
};

// Establecer por defecto en DataTables para cualquier tabla
if (typeof jQuery !== "undefined" && typeof jQuery.fn.dataTable !== "undefined") {
  jQuery.extend(true, jQuery.fn.dataTable.defaults, {
    language: DATATABLES_ESPANOL,
  });
}
