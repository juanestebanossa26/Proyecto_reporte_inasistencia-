@echo off
:: ============================================================================
:: Ejecutor del Cron de Inasistencias · SENA Control de Ingreso
:: ============================================================================
cd /d "%~dp0"
"C:\xampp\php\php.exe" cron_inasistencias.php
pause
