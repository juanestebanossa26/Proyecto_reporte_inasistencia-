<?php
// Acceso a datos de las tablas horarios y fichas.
// Permite consultar y registrar horarios asociados a fichas para el calendario.

class HorarioModelo
{
    // Lista todos los horarios registrados con datos de la ficha, instructor y aprendices.
    public function listarHorarios(): array
    {
        $sql = "SELECT
                    horarios.id_horario,
                    horarios.id_ficha,
                    horarios.hora_entrada_programada,
                    horarios.hora_salida_programada,
                    horarios.dia_semana AS fecha,
                    fichas.numero_ficha,
                    fichas.nombre_programa,
                    fichas_has_usuarios.id_instructor,
                    CONCAT(COALESCE(usuarios.nombre_completo, ''), ' ', COALESCE(usuarios.apellido, '')) AS instructor_nombre,
                    (SELECT COUNT(*) FROM aprendices WHERE aprendices.id_ficha = fichas.id_ficha) AS total_aprendices
                FROM horarios
                JOIN fichas ON fichas.id_ficha = horarios.id_ficha
                LEFT JOIN fichas_has_usuarios ON fichas_has_usuarios.fichas_id_ficha = fichas.id_ficha
                LEFT JOIN usuarios ON usuarios.id_usuario = fichas_has_usuarios.id_instructor
                ORDER BY horarios.dia_semana ASC, horarios.hora_entrada_programada ASC";

        return Conexion::obtener()->query($sql)->fetchAll();
    }

    // Busca una ficha por su número o la crea si no existe
    public function obtenerOCrearFicha(string $numeroFicha, string $nombrePrograma): int
    {
        $db = Conexion::obtener();

        $sqlBuscar = "SELECT id_ficha FROM fichas WHERE numero_ficha = :num LIMIT 1";
        $stmt = $db->prepare($sqlBuscar);
        $stmt->bindParam(':num', $numeroFicha);
        $stmt->execute();
        $ficha = $stmt->fetch();

    }

    // Asocia un instructor a una ficha en la tabla  fichas_has_usuarios
    public function asociarInstructor(int $idFicha, int $idInstructor): void
    {
        $db = Conexion::obtener();
        $sqlVerificar = "SELECT 1 FROM fichas_has_usuarios WHERE fichas_id_ficha = :id_ficha AND id_instructor = :id_inst LIMIT 1";
        $stmtVerificar = $db->prepare($sqlVerificar);
        $stmtVerificar->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
        $stmtVerificar->bindParam(':id_inst', $idInstructor, PDO::PARAM_INT);
        $stmtVerificar->execute();
    }

    // Registra un nuevo horario asociado a una ficha
    public function guardarHorario(int $idFicha, string $horaEntrada, string $horaSalida, string $fecha): void
    {
        $sql = "INSERT INTO horarios (id_ficha, hora_entrada_programada, hora_salida_programada, dia_semana)
                VALUES (:id_ficha, :entrada, :salida, :fecha)";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
        $stmt->bindParam(':entrada',  $horaEntrada);
        $stmt->bindParam(':salida',   $horaSalida);
        $stmt->bindParam(':fecha',    $fecha);
        $stmt->execute();
    }

    // Lista instructores activos para el selector del formulario
    public function listarInstructores(): array
    {
        $sql = "SELECT id_usuario, nombre_completo, apellido
                FROM usuarios
                WHERE id_role = 2 AND estado = 'Activo'
                ORDER BY nombre_completo ASC";

        return Conexion::obtener()->query($sql)->fetchAll();
    }
}
