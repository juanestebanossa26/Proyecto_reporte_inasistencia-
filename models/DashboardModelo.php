<?php
// Acceso a datos para los paneles de control (Dashboards)
// Proporciona métricas, estadísticas, ingresos recientes y excusas
// adaptados para los roles Administrador e Instructor.

declare(strict_types=1);

class DashboardModelo
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::obtener();
    }

    /* =========================================================================
       MÉTODOS PARA EL DASHBOARD DE ADMINISTRADOR
       ========================================================================= */

    /**
     * Obtiene los indicadores clave de desempeño (KPIs) globales del sistema.
     */
    public function obtenerKpisAdmin(?string $fecha = null): array
    {
        $fecha = $fecha ?: date('Y-m-d');

        $sql = "SELECT 
            (SELECT COUNT(*) FROM aprendices) AS total_aprendices,
            (SELECT COUNT(*) FROM usuarios WHERE id_role = 2 AND estado = 'Activo') AS total_instructores,
            (SELECT COUNT(*) FROM fichas) AS total_fichas,
            (SELECT COUNT(*) FROM horarios WHERE dia_semana = :fecha_horarios) AS horarios_hoy,
            (SELECT COUNT(*) FROM asistencias WHERE fecha = :fecha_asis) AS asistencias_hoy,
            (SELECT COUNT(*) FROM asistencias WHERE fecha = :fecha_atiempo AND LOWER(estado) IN ('a tiempo', 'asistencia')) AS a_tiempo_hoy,
            (SELECT COUNT(*) FROM asistencias WHERE fecha = :fecha_retardo AND LOWER(estado) = 'retardo') AS retardos_hoy,
            (SELECT COUNT(*) FROM asistencias WHERE fecha = :fecha_inasistencia AND LOWER(estado) = 'inasistencia') AS inasistencias_hoy,
            (SELECT COUNT(*) FROM revision_excusas WHERE LOWER(estado) = 'pendiente') AS excusas_pendientes";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':fecha_horarios'     => $fecha,
            ':fecha_asis'         => $fecha,
            ':fecha_atiempo'      => $fecha,
            ':fecha_retardo'      => $fecha,
            ':fecha_inasistencia' => $fecha,
        ]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_aprendices'   => (int) ($res['total_aprendices'] ?? 0),
            'total_instructores' => (int) ($res['total_instructores'] ?? 0),
            'total_fichas'       => (int) ($res['total_fichas'] ?? 0),
            'horarios_hoy'       => (int) ($res['horarios_hoy'] ?? 0),
            'asistencias_hoy'    => (int) ($res['asistencias_hoy'] ?? 0),
            'a_tiempo_hoy'       => (int) ($res['a_tiempo_hoy'] ?? 0),
            'retardos_hoy'       => (int) ($res['retardos_hoy'] ?? 0),
            'inasistencias_hoy'  => (int) ($res['inasistencias_hoy'] ?? 0),
            'excusas_pendientes' => (int) ($res['excusas_pendientes'] ?? 0),
        ];
    }

    /**
     * Obtiene los registros de marcajes/asistencias más recientes del sistema.
     */
    public function obtenerIngresosRecientesAdmin(int $limite = 10): array
    {
        $sql = "SELECT 
                    a.id_asistencia,
                    a.fecha,
                    a.hora_entrada_real,
                    a.hora_salida_real,
                    a.tiempo_retraso,
                    a.estado,
                    u.nombre_completo,
                    u.usuario AS documento,
                    f.id_ficha,
                    f.numero_ficha,
                    f.nombre_programa
                FROM asistencias a
                JOIN aprendices ap ON ap.id_aprendiz = a.id_aprendiz
                JOIN usuarios u ON u.id_usuario = ap.id_usuario
                LEFT JOIN fichas f ON f.id_ficha = ap.id_ficha
                ORDER BY a.fecha DESC, (CASE WHEN a.hora_entrada_real IS NOT NULL THEN a.hora_entrada_real ELSE '00:00:00' END) DESC, a.id_asistencia DESC
                LIMIT :limite";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las excusas médicas pendientes o más recientes a nivel global.
     */
    public function obtenerExcusasRecientesAdmin(int $limite = 5): array
    {
        $sql = "SELECT 
                    em.id_excusa,
                    em.motivo,
                    em.fecha_creacion,
                    em.archivo_adjunto,
                    a.fecha AS fecha_inasistencia,
                    u.nombre_completo AS aprendiz_nombre,
                    u.usuario AS aprendiz_documento,
                    f.numero_ficha,
                    f.nombre_programa,
                    COALESCE(re.estado, 'Pendiente') AS estado_revision,
                    re.id_revision
                FROM excusas_medicas em
                JOIN asistencias a ON a.id_asistencia = em.id_asistencia
                JOIN aprendices ap ON ap.id_aprendiz = em.aprendices_id_aprendiz
                JOIN usuarios u ON u.id_usuario = ap.id_usuario
                LEFT JOIN fichas f ON f.id_ficha = ap.id_ficha
                LEFT JOIN revision_excusas re ON re.id_excusa = em.id_excusa
                ORDER BY (CASE WHEN LOWER(re.estado) = 'pendiente' OR re.estado IS NULL THEN 0 ELSE 1 END) ASC,
                         em.fecha_creacion DESC
                LIMIT :limite";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumen de las fichas con su cantidad de aprendices e instructores asignados.
     */
    public function obtenerResumenFichasAdmin(int $limite = 6): array
    {
        $sql = "SELECT 
                    f.id_ficha,
                    f.numero_ficha,
                    f.nombre_programa,
                    COUNT(DISTINCT ap.id_aprendiz) AS total_aprendices,
                    GROUP_CONCAT(DISTINCT u_inst.nombre_completo SEPARATOR ', ') AS instructores
                FROM fichas f
                LEFT JOIN aprendices ap ON ap.id_ficha = f.id_ficha
                LEFT JOIN fichas_has_usuarios fhu ON fhu.fichas_id_ficha = f.id_ficha
                LEFT JOIN usuarios u_inst ON u_inst.id_usuario = fhu.id_instructor
                GROUP BY f.id_ficha
                ORDER BY total_aprendices DESC, f.numero_ficha ASC
                LIMIT :limite";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================================
       MÉTODOS PARA EL DASHBOARD DE INSTRUCTOR
       ========================================================================= */

    /**
     * Obtiene los KPIs acotados a las fichas asignadas al instructor.
     */
    public function obtenerKpisInstructor(int $idInstructor, ?string $fecha = null): array
    {
        $fecha = $fecha ?: date('Y-m-d');

        // Total de fichas asignadas al instructor
        $sqlFichas = "SELECT COUNT(DISTINCT f.id_ficha)
                      FROM fichas f
                      LEFT JOIN fichas_has_usuarios fhu ON fhu.fichas_id_ficha = f.id_ficha
                      LEFT JOIN horarios h ON h.id_ficha = f.id_ficha
                      WHERE fhu.id_instructor = :i1 OR h.id_instructor = :i2";
        $stmtFichas = $this->db->prepare($sqlFichas);
        $stmtFichas->execute([':i1' => $idInstructor, ':i2' => $idInstructor]);
        $totalFichas = (int) $stmtFichas->fetchColumn();

        // Total aprendices en sus fichas
        $sqlAprendices = "SELECT COUNT(DISTINCT ap.id_aprendiz)
                          FROM aprendices ap
                          WHERE ap.id_ficha IN (
                              SELECT fhu.fichas_id_ficha FROM fichas_has_usuarios fhu WHERE fhu.id_instructor = :i1
                              UNION
                              SELECT h.id_ficha FROM horarios h WHERE h.id_instructor = :i2
                          )";
        $stmtAprendices = $this->db->prepare($sqlAprendices);
        $stmtAprendices->execute([':i1' => $idInstructor, ':i2' => $idInstructor]);
        $totalAprendices = (int) $stmtAprendices->fetchColumn();

        // Clases programadas hoy para este instructor
        $sqlClases = "SELECT COUNT(*)
                      FROM horarios h
                      WHERE (h.id_instructor = :i1 OR h.id_ficha IN (
                          SELECT fhu.fichas_id_ficha FROM fichas_has_usuarios fhu WHERE fhu.id_instructor = :i2
                      )) AND h.dia_semana = :fecha";
        $stmtClases = $this->db->prepare($sqlClases);
        $stmtClases->execute([':i1' => $idInstructor, ':i2' => $idInstructor, ':fecha' => $fecha]);
        $clasesHoy = (int) $stmtClases->fetchColumn();

        // Asistencias registradas hoy en las fichas del instructor
        $sqlAsistencias = "SELECT 
                               COUNT(a.id_asistencia) AS total_hoy,
                               SUM(CASE WHEN LOWER(a.estado) IN ('a tiempo', 'asistencia') THEN 1 ELSE 0 END) AS a_tiempo_hoy,
                               SUM(CASE WHEN LOWER(a.estado) = 'retardo' THEN 1 ELSE 0 END) AS retardos_hoy,
                               SUM(CASE WHEN LOWER(a.estado) = 'inasistencia' THEN 1 ELSE 0 END) AS inasistencias_hoy
                           FROM asistencias a
                           JOIN aprendices ap ON ap.id_aprendiz = a.id_aprendiz
                           WHERE a.fecha = :fecha
                             AND ap.id_ficha IN (
                                 SELECT fhu.fichas_id_ficha FROM fichas_has_usuarios fhu WHERE fhu.id_instructor = :i1
                                 UNION
                                 SELECT h.id_ficha FROM horarios h WHERE h.id_instructor = :i2
                             )";
        $stmtAsistencias = $this->db->prepare($sqlAsistencias);
        $stmtAsistencias->execute([':fecha' => $fecha, ':i1' => $idInstructor, ':i2' => $idInstructor]);
        $asis = $stmtAsistencias->fetch(PDO::FETCH_ASSOC) ?: [];

        // Excusas pendientes por validar para este instructor
        $sqlExcusas = "SELECT COUNT(id_revision)
                       FROM revision_excusas
                       WHERE id_instructor = :i1 AND LOWER(estado) = 'pendiente'";
        $stmtExcusas = $this->db->prepare($sqlExcusas);
        $stmtExcusas->execute([':i1' => $idInstructor]);
        $excusasPendientes = (int) $stmtExcusas->fetchColumn();

        return [
            'total_fichas'       => $totalFichas,
            'total_aprendices'   => $totalAprendices,
            'clases_hoy'         => $clasesHoy,
            'asistencias_hoy'    => (int) ($asis['total_hoy'] ?? 0),
            'a_tiempo_hoy'       => (int) ($asis['a_tiempo_hoy'] ?? 0),
            'retardos_hoy'       => (int) ($asis['retardos_hoy'] ?? 0),
            'inasistencias_hoy'  => (int) ($asis['inasistencias_hoy'] ?? 0),
            'excusas_pendientes' => $excusasPendientes,
        ];
    }

    /**
     * Obtiene los horarios/clases programadas hoy para el instructor.
     */
    public function obtenerHorariosHoyInstructor(int $idInstructor, ?string $fecha = null): array
    {
        $fecha = $fecha ?: date('Y-m-d');

        $sql = "SELECT 
                    h.id_horario,
                    h.id_ficha,
                    h.dia_semana,
                    h.hora_entrada_programada,
                    h.hora_salida_programada,
                    f.numero_ficha,
                    f.nombre_programa,
                    (SELECT COUNT(*) FROM aprendices ap WHERE ap.id_ficha = f.id_ficha) AS total_aprendices
                FROM horarios h
                JOIN fichas f ON f.id_ficha = h.id_ficha
                WHERE (h.id_instructor = :i1 OR f.id_ficha IN (
                    SELECT fhu.fichas_id_ficha FROM fichas_has_usuarios fhu WHERE fhu.id_instructor = :i2
                )) AND h.dia_semana = :fecha
                ORDER BY h.hora_entrada_programada ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':i1' => $idInstructor, ':i2' => $idInstructor, ':fecha' => $fecha]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ingresos recientes correspondientes únicamente a aprendices de fichas del instructor.
     */
    public function obtenerIngresosRecientesInstructor(int $idInstructor, int $limite = 10): array
    {
        $sql = "SELECT 
                    a.id_asistencia,
                    a.fecha,
                    a.hora_entrada_real,
                    a.hora_salida_real,
                    a.tiempo_retraso,
                    a.estado,
                    u.nombre_completo,
                    u.usuario AS documento,
                    f.numero_ficha,
                    f.nombre_programa
                FROM asistencias a
                JOIN aprendices ap ON ap.id_aprendiz = a.id_aprendiz
                JOIN usuarios u ON u.id_usuario = ap.id_usuario
                JOIN fichas f ON f.id_ficha = ap.id_ficha
                WHERE ap.id_ficha IN (
                    SELECT fhu.fichas_id_ficha FROM fichas_has_usuarios fhu WHERE fhu.id_instructor = :i1
                    UNION
                    SELECT h.id_ficha FROM horarios h WHERE h.id_instructor = :i2
                )
                ORDER BY a.fecha DESC, (CASE WHEN a.hora_entrada_real IS NOT NULL THEN a.hora_entrada_real ELSE '00:00:00' END) DESC, a.id_asistencia DESC
                LIMIT :limite";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':i1', $idInstructor, PDO::PARAM_INT);
        $stmt->bindValue(':i2', $idInstructor, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Excusas pendientes asignadas específicamente al instructor para revisión.
     */
    public function obtenerExcusasPendientesInstructor(int $idInstructor, int $limite = 6): array
    {
        $sql = "SELECT 
                    re.id_revision,
                    re.estado AS estado_revision,
                    em.id_excusa,
                    em.motivo,
                    em.archivo_adjunto,
                    em.fecha_creacion,
                    a.fecha AS fecha_inasistencia,
                    u.nombre_completo AS aprendiz_nombre,
                    u.usuario AS aprendiz_documento,
                    f.numero_ficha,
                    f.nombre_programa
                FROM revision_excusas re
                JOIN excusas_medicas em ON em.id_excusa = re.id_excusa
                JOIN asistencias a ON a.id_asistencia = em.id_asistencia
                JOIN aprendices ap ON ap.id_aprendiz = em.aprendices_id_aprendiz
                JOIN usuarios u ON u.id_usuario = ap.id_usuario
                LEFT JOIN fichas f ON f.id_ficha = ap.id_ficha
                WHERE re.id_instructor = :id_inst
                  AND LOWER(re.estado) = 'pendiente'
                ORDER BY em.fecha_creacion DESC
                LIMIT :limite";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_inst', $idInstructor, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista de fichas asociadas al instructor con métricas de aprendices y horarios.
     */
    public function obtenerMisFichas(int $idInstructor): array
    {
        $sql = "SELECT 
                    f.id_ficha,
                    f.numero_ficha,
                    f.nombre_programa,
                    COUNT(DISTINCT ap.id_aprendiz) AS total_aprendices,
                    COUNT(DISTINCT h.id_horario) AS total_horarios
                FROM fichas f
                LEFT JOIN aprendices ap ON ap.id_ficha = f.id_ficha
                LEFT JOIN horarios h ON h.id_ficha = f.id_ficha AND h.id_instructor = :i1
                LEFT JOIN fichas_has_usuarios fhu ON fhu.fichas_id_ficha = f.id_ficha
                WHERE fhu.id_instructor = :i2 OR h.id_instructor = :i3
                GROUP BY f.id_ficha
                ORDER BY f.numero_ficha ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':i1' => $idInstructor, ':i2' => $idInstructor, ':i3' => $idInstructor]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
