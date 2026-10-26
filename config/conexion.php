<?php
// Conexión única a la base de datos usando PDO.
// Todos los Modelos obtienen la conexión a través de Conexion::obtener(),
// nunca creando su propio new PDO(...).

require_once __DIR__ . '/config.php';

class Conexion
{
    private static ?PDO $instancia = null;

    // Evita que la clase se instancie desde fuera.
    private function __construct()
    {
    }

    public static function obtener(): PDO
    {
        if (self::$instancia === null) {
            $puerto = defined('DB_PORT') && DB_PORT ? ';port=' . DB_PORT : '';
            $dsn = 'mysql:host=' . DB_HOST . $puerto . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

            try {
                self::$instancia = new PDO($dsn, DB_USER, DB_PASS, [
                    // Los errores de PDO se convierten en excepciones capturables.
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    // Los resultados llegan como arreglos asociativos por defecto.
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                    // Las consultas preparadas se envían de forma nativa al motor,
                    // en vez de ser emuladas por PHP.
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                // Mismo ajuste que tenía el proyecto original: nombres de
                // fecha (día/mes) en español al usar funciones de fecha de MySQL.
                self::$instancia->exec("SET lc_time_names = 'es_ES'");

                // Sincronización estricta de zona horaria con America/Bogota (UTC-5)
                self::$instancia->exec("SET time_zone = '-05:00'");
            } catch (PDOException $e) {
                error_log('Error de conexión a la base de datos: ' . $e->getMessage());
                if (defined('MODO_DEBUG') && MODO_DEBUG) {
                    die('Error de conexión a la base de datos: ' . htmlspecialchars($e->getMessage()));
                }
                http_response_code(500);
                die('No fue posible conectar con la base de datos. Por favor, verifica la configuración o contacta al administrador.');
            }
        }

        return self::$instancia;
    }
}
