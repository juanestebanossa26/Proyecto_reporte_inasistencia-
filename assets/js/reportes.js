/* ============================================================
   Lógica Interactiva y Persistencia para Reportes de Asistencias
   ============================================================ */

$(document).ready(function () {
  // 1. Inicialización de DataTable con soporte en español
  if ($('#tablaReportes').length) {
    $('#tablaReportes').DataTable({
      language: typeof DATATABLES_ESPANOL !== 'undefined' ? DATATABLES_ESPANOL : {},
      order: [],
      pageLength: 10,
      autoWidth: false,
    });
  }

  // 2. Cálculo automático e interactivo de rangos de fechas
  const selectRango = $('select[name="rango"]');
  const inputDesde  = $('input[name="desde"]');
  const inputHasta  = $('input[name="hasta"]');

  function formatearFecha(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  selectRango.on('change', function () {
    const val = $(this).val();
    const hoy = new Date();

    if (val === 'dia') {
      const fechaHoy = formatearFecha(hoy);
      inputDesde.val(fechaHoy);
      inputHasta.val(fechaHoy);
    } else if (val === 'semana') {
      const diaSemana = hoy.getDay(); // 0 = Domingo, 1 = Lunes...
      const diffLunes = hoy.getDate() - (diaSemana === 0 ? 6 : diaSemana - 1);
      const lunes = new Date(hoy.setDate(diffLunes));
      const domingo = new Date(lunes);
      domingo.setDate(lunes.getDate() + 6);

      inputDesde.val(formatearFecha(lunes));
      inputHasta.val(formatearFecha(domingo));
    }
  });

  // 3. Persistencia de última ficha seleccionada en sessionStorage
  const selectFicha = $('select[name="ficha_id"]');
  if (selectFicha.length) {
    selectFicha.on('change', function () {
      sessionStorage.setItem('sena_reporte_ultima_ficha', $(this).val());
    });
  }
});
