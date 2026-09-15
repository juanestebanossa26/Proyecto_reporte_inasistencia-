<?php

require_once __DIR__ . '/config.php';

class mysql {
    private $conexion;

    public function __construct() {
        $this->conectar();
    }

    public function conectar() {
        if ($this->conexion !== null) {
            return;
        }

        try {
            $origen = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            
            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Si usas constantes en config.php:
            $this->conexion = new PDO($origen, DB_USER, DB_PASS, $opciones);
            
            $this->conexion->exec("SET lc_time_names = 'es_ES'");

        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public function prepare($sql) {
        return $this->conexion->prepare($sql);
    }

    public function efectuarconsulta($consulta) {
        try {
            return $this->conexion->query($consulta);
        } catch (PDOException $e) {
            die("Error en la consulta: " . $e->getMessage());
        }
    }

    public function desconectar() {
        $this->conexion = null;
    }
}