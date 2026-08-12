# Sistema de Control de Ingreso de Aprendices — Front-end (PHP + Bootstrap 5)

Este paquete contiene **solo el diseño/maquetación** en archivos `.php`, listo para que
conectes tu lógica de backend (PHP + MySQL). No hay conexión a base de datos: los datos
que ves en las tablas son de ejemplo (arrays PHP) para que puedas visualizar el diseño real.

## Estructura

```
control-ingreso/
├── index.php               → Login (punto de entrada del sistema)
├── dashboard.php            → Panel principal (KPIs, ingresos recientes)
├── aprendices.php           → CRUD de aprendices (HU 1, 14)
├── instructores.php         → CRUD de instructores (HU 20)
├── fichas.php                → Fichas y horarios (HU 9, 10)
├── marcaje.php               → Simulación de lectura RFID (HU 8, 16)
├── excusas.php                → Anexar excusa médica — vista aprendiz (HU 7)
├── validar_excusas.php       → Aprobar/rechazar excusas — vista instructor/admin (HU 13)
├── historial.php              → Historial personal de asistencia — vista aprendiz (HU 6, 15)
├── reportes.php                → Reportes con filtros y exportación PDF/Excel (HU 11, 12)
├── perfil.php                   → Perfil del usuario
├── includes/
│   ├── head.php              → <head> común (Bootstrap 5, Bootstrap Icons, fuentes, CSS propio)
│   ├── sidebar.php            → Menú lateral colapsable y adaptado por rol
│   ├── topbar.php              → Barra superior (botón ocultar sidebar, notificaciones, usuario)
│   └── scripts.php              → JS de Bootstrap + main.js al final de cada página
└── assets/
    ├── css/style.css            → Todos los tokens de diseño (colores, tipografía, sidebar, etc.)
    └── js/main.js                → Lógica de colapsar/expandir el sidebar (con localStorage)
```

## Cómo funciona la barra lateral ocultable

- En **escritorio**, el botón del ícono `☰` (junto al título) colapsa el sidebar a solo
  íconos y el contenido se ajusta automáticamente (`main.js` + clase `.sidebar-collapsed`).
  El estado se guarda en `localStorage`, así que se mantiene al recargar o cambiar de página.
- En **móvil**, el mismo botón abre el sidebar como un panel deslizante (drawer) con fondo
  oscuro (`.sidebar-mobile-open`).

## Login como `index.php`

`index.php` es la puerta de entrada. Tiene un selector visual de rol (Admin / Instructor /
Aprendiz) que **es solo de interfaz**: quien realmente decide el rol es tu backend, leyendo
la tabla `usuarios` (columna `rol`) como está definido en el contrato de datos del proyecto.

## Cómo conectar tu backend (resumen de los `TODO`)

Cada archivo `.php` tiene comentarios `TODO (backend):` en la parte superior indicando
exactamente qué reemplazar. En términos generales:

1. **Sesión y rol**: en cada página, la variable `$rolActivo` está fijada a mano
   (o tomada de `$_GET['rol']` solo para que puedas previsualizar el diseño, ej.
   `dashboard.php?rol=aprendiz`). Debes reemplazarla por `$_SESSION['rol']` una vez tengas
   el login funcionando, y agregar la validación de sesión (`if (!isset($_SESSION['usuario'])) { header('Location: index.php'); exit; }`) al inicio de cada página protegida.
2. **Datos de ejemplo**: los arrays PHP (`$aprendices`, `$instructores`, `$fichas`, etc.)
   deben reemplazarse por tus consultas `SELECT` a MySQL.
3. **Formularios**: cada `<form>` tiene un comentario indicando a qué script de backend
   debería apuntar (`guardar_aprendiz.php`, `registrar_marcaje.php`, `guardar_excusa.php`, etc.).

## Paleta y tipografía

- Verde institucional SENA `#39A900` como color primario de acción.
- Riel del sidebar en degradado verde oscuro `#10241C → #16332A`.
- Tipografía de títulos: **Sora** (Google Fonts). Tipografía de texto: **Inter**.
- Estados de asistencia con badges de color: a tiempo (verde), retardo (ámbar),
  inasistencia (rojo), salida anticipada (azul) — mismos colores para excusas
  (pendiente / aprobada / rechazada).

## Dependencias (todas por CDN, no requieren instalación)

- Bootstrap 5.3.3
- Bootstrap Icons 1.11.3
- Google Fonts: Sora + Inter
