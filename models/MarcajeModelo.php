<?php
// Acceso a datos para el marcaje RFID.
//
// Un día puede tener 1 o 2 "bloques" (filas en horarios con el mismo
// id_ficha y la misma fecha en dia_semana) — por ejemplo mañana y tarde.
// Cada bloque es una fila independiente en asistencias, enlazada por
// id_horario (ver migración en migracion_marcaje.sql).

class MarcajeModelo
{
    // Identifica al aprendiz por su tag RFID. Solo aprendices Activos
    // pueden marcar.
    public function buscarAprendizPorTag(string $tagRfid): array|false
    {
        $sql = "SELECT
                    aprendices.id_aprendiz,
                    aprendices.id_ficha,
                    usuarios.id_usuario,
                    usuarios.nombre_completo,
                    usuarios.apellido,
                    usuarios.estado,
                    fichas.numero_ficha,
                    fichas.nombre_programa
                FROM aprendices
                JOIN usuarios ON usuarios.id_usuario = aprendices.id_usuario
                JOIN fichas   ON fichas.id_ficha      = aprendices.id_ficha
                WHERE aprendices.tag_rfid = :tag
                LIMIT 1";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':tag', $tagRfid);
        $stmt->execute();

        return $stmt->fetch();
    }

    // Bloques de horario de una ficha para una fecha puntual, en orden
    // cronológico (1 fila = todo el día en un solo bloque; 2 filas = el
    // día se dividió en mañana/tarde, u otro reparto).
    public function obtenerBloquesDelDia(int $idFicha, string $fecha): array
    {
        $sql = "SELECT id_horario, hora_entrada_programada, hora_salida_programada
                FROM horarios
                WHERE id_ficha = :id_ficha AND dia_semana = :fecha
                ORDER BY hora_entrada_programada ASC";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Todas las asistencias YA registradas hoy para este aprendiz,
    // indexadas por id_horario, para saber de un vistazo qué bloques
    // tienen entrada/salida marcada y cuáles no.
    public function obtenerAsistenciasDelDia(int $idAprendiz, string $fecha): array
    {
        $sql = "SELECT id_asistencia, id_horario, hora_entrada_real, hora_salida_real
                FROM asistencias
                WHERE id_aprendiz = :id_aprendiz AND fecha = :fecha";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_aprendiz', $idAprendiz, PDO::PARAM_INT);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();

        $porHorario = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porHorario[(int) $fila['id_horario']] = $fila;
        }

        return $porHorario;
    }

    // Registra la ENTRADA a un bloque (fila nueva en asistencias).
    public function registrarEntrada(int $idAprendiz, int $idHorario, string $fecha, string $horaActual, string $estado, int $minutosRetraso): int
    {
        $sql = "INSERT INTO asistencias (id_aprendiz, id_horario, fecha, hora_entrada_real, tiempo_retraso, estado)
                VALUES (:id_aprendiz, :id_horario, :fecha, :hora, :retraso, :estado)";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':id_aprendiz', $idAprendiz, PDO::PARAM_INT);
        $stmt->bindParam(':id_horario', $idHorario, PDO::PARAM_INT);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora', $horaActual);
        $stmt->bindParam(':retraso', $minutosRetraso, PDO::PARAM_INT);
        $stmt->bindParam(':estado', $estado);
        $stmt->execute();

        return (int) Conexion::obtener()->lastInsertId();
    }

    // Registra la SALIDA de un bloque que ya tenía entrada marcada.
    public function registrarSalida(int $idAsistencia, string $horaActual): void
    {
        $sql = "UPDATE asistencias SET hora_salida_real = :hora WHERE id_asistencia = :id";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':hora', $horaActual);
        $stmt->bindParam(':id', $idAsistencia, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Últimos marcajes del día (para el panel "Últimos marcajes de hoy").
    // $idFicha opcional: si se pasa, lo acota a una sola ficha (vista de
    // un instructor); si no, trae de todas (vista de administrador).
    public function listarMarcajesDeHoy(string $fecha, ?int $idFicha = null, int $limite = 10): array
    {
        $whereFicha = $idFicha !== null ? ' AND aprendices.id_ficha = :id_ficha ' : '';

        $sql = "SELECT
                    usuarios.nombre_completo,
                    usuarios.apellido,
                    fichas.numero_ficha,
                    fichas.nombre_programa,
                    asistencias.hora_entrada_real,
                    asistencias.hora_salida_real,
                    asistencias.estado,
                    GREATEST(
                        COALESCE(asistencias.hora_entrada_real, '00:00:00'),
                        COALESCE(asistencias.hora_salida_real, '00:00:00')
                    ) AS ultima_hora
                FROM asistencias
                JOIN aprendices ON aprendices.id_aprendiz = asistencias.id_aprendiz
                JOIN usuarios   ON usuarios.id_usuario     = aprendices.id_usuario
                JOIN fichas     ON fichas.id_ficha          = aprendices.id_ficha
                WHERE asistencias.fecha = :fecha
                $whereFicha
                ORDER BY ultima_hora DESC
                LIMIT :limite";

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->bindParam(':fecha', $fecha);
        if ($idFicha !== null) {
            $stmt->bindParam(':id_ficha', $idFicha, PDO::PARAM_INT);
        }
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
