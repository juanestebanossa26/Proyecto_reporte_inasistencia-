<?php
class mysql{

    private $conexion;
    private $ipServidor = "localhost";
    private $usuarioBase = 'root';
    private $contrasena = '';
    private $bdnombre = 'control_asistencia';
    public $resultadoconsulta;

    public function conectar() {
        try {
            //este es el origen de donde traigo la informacion
            $origen = "mysql:host={$this->ipServidor};dbname={$this->bdnombre};charset=utf8";
            
            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
             // Aca genero la conexion con la base de datos
            $this->conexion = new PDO($origen, $this->usuarioBase, $this->contrasena, $opciones);
            
            // Aca Configuramos  el idioma de las fechas en español
            $this->conexion->exec("SET lc_time_names = 'es_ES'");

        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    public function prepare($sql) {
    return $this->conexion->prepare($sql);
}

    public function desconectar() {
        $this->conexion = null;
    }

    public function efectuarconsulta($consulta) {
        try {
            $this->resultadoconsulta = $this->conexion->query($consulta);
            return $this->resultadoconsulta;
        } catch (PDOException $e) {
            die("Error en la consulta: " . $e->getMessage());
        }
    }
}
