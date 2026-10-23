/* ============================================================
   Lógica del cliente para la pantalla de inicio de sesión
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
  // Desactivar retroceso en el historial hacia la sesión cerrada
  if (window.history && window.history.pushState) {
    window.history.pushState(null, null, window.location.href);
    window.onpopstate = function () {
      window.history.pushState(null, null, window.location.href);
    };
  }
});
