@echo off
:: ============================================================================
:: Ejecutor de Tarea Programada: Sincronización de Inasistencias · SENA
:: Compatible con Programador de Tareas de Windows (sin bloqueos ni pause)
:: ============================================================================
cd /d "%~dp0"

set PHP_EXE=C:\xampp\php\php.exe
if not exist "%PHP_EXE%" (
    set PHP_EXE=php
)

"%PHP_EXE%" cron_inasistencias.php

:: Si se ejecutó interactivamente en consola directa, mostrar código de salida
if "%1"=="--interactive" (
    echo.
    echo Proceso finalizado con codigo de salida: %ERRORLEVEL%
)
exit /b %ERRORLEVEL%
