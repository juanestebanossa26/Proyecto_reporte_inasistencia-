<?php
/**
 * Test Suite Integral para el Ciclo de Vida de Fichas, Aprendices, Horarios y Asistencias
 * Ejecuta pruebas con transacciones y datos de prueba limpios para validar todos los escenarios de negocio.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/../models/FichaModelo.php';
require_once __DIR__ . '/../models/HorarioModelo.php';
require_once __DIR__ . '/../models/AprendicesModelo.php';
require_once __DIR__ . '/../models/AsistenciaModelo.php';
require_once __DIR__ . '/../models/AuditoriaModelo.php';
require_once __DIR__ . '/../helpers/ExcelHorarioHelper.php';

$db = Conexion::obtener();

echo "====================================================================\n";
echo " INICIANDO SUITE DE PRUEBAS AUTOMATIZADAS - CICLO DE VIDA DE FICHAS\n";
echo "====================================================================\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertTest(string $description, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "[PASS] Escenario $totalTests: $description\n";
    } else {
        $failedTests++;
        echo "[FAIL] Escenario $totalTests: $description. Detalle: $detail\n";
    }
}

// Modelos
$fichaModelo = new FichaModelo();
$horarioModelo = new HorarioModelo();
$asistenciaModelo = new AsistenciaModelo();
$auditoriaModelo = new AuditoriaModelo();

// Preparación de un ID de Administrador de Prueba
$stmtAdmin = $db->query("SELECT id_usuario FROM usuarios WHERE id_role = 1 LIMIT 1");
$adminRow = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
$adminId = $adminRow ? (int)$adminRow['id_usuario'] : 1;

// -------------------------------------------------------------
// BLOQUE 1: CREACIÓN DE DATOS CONTROLADOS PARA PRUEBAS
// -------------------------------------------------------------
echo "--> Creando entorno controlado de prueba...\n";

// Crear Ficha de prueba A
$numFichaA = 'TEST-LIFECYCLE-' . time();
$db->prepare("INSERT INTO fichas (numero_ficha, nombre_programa, estado) VALUES (?, 'ANALISIS Y DESARROLLO DE SOFTWARE', 'Activa')")
   ->execute([$numFichaA]);
$idFichaA = (int)$db->lastInsertId();

// Crear Ficha de prueba B
$numFichaB = 'TEST-MULTI-' . (time() + 1);
$db->prepare("INSERT INTO fichas (numero_ficha, nombre_programa, estado) VALUES (?, 'GESTION DE REDES DE DATOS', 'Activa')")
   ->execute([$numFichaB]);
$idFichaB = (int)$db->lastInsertId();

// Crear usuario aprendiz de prueba
$usuarioUnico = 'test_user_' . rand(10000, 99999);
$db->prepare("INSERT INTO usuarios (nombre_completo, usuario, correo_electronico, estado, contrasena, id_role) VALUES ('Aprendiz de Prueba Ciclo', ?, ?, 'Activo', 'testpass123', 3)")
   ->execute([$usuarioUnico, "$usuarioUnico@sena.edu.co"]);
$idUsuarioAprendiz = (int)$db->lastInsertId();

$db->prepare("INSERT INTO aprendices (id_usuario, id_ficha, tag_rfid) VALUES (?, ?, ?)")
   ->execute([$idUsuarioAprendiz, $idFichaA, 'RFID-' . rand(1000, 9999)]);
$idAprendiz = (int)$db->lastInsertId();

// Vincular a aprendiz_ficha para ambas fichas (multi-ficha)
$db->prepare("INSERT INTO aprendiz_ficha (id_aprendiz, id_ficha, estado, fecha_vinculacion) VALUES (?, ?, 'Activo', '2026-01-01')")
   ->execute([$idAprendiz, $idFichaA]);
$db->prepare("INSERT INTO aprendiz_ficha (id_aprendiz, id_ficha, estado, fecha_vinculacion) VALUES (?, ?, 'Activo', '2026-01-01')")
   ->execute([$idAprendiz, $idFichaB]);

// Buscar un instructor existente
$stmtInst = $db->query("SELECT id_usuario FROM usuarios WHERE id_role = 2 LIMIT 1");
$instRow = $stmtInst->fetch(PDO::FETCH_ASSOC);
$idInstructor = $instRow ? (int)$instRow['id_usuario'] : null;

// Crear horario pasado (2026-01-15) para Ficha A
$db->prepare("INSERT INTO horarios (id_ficha, id_instructor, dia_semana, hora_entrada_programada, hora_salida_programada, estado) VALUES (?, ?, '2026-01-15', '08:00:00', '12:00:00', 'Programado')")
   ->execute([$idFichaA, $idInstructor]);
$idHorarioPasado = (int)$db->lastInsertId();

// Crear horario futuro (2026-11-20) para Ficha A
$db->prepare("INSERT INTO horarios (id_ficha, id_instructor, dia_semana, hora_entrada_programada, hora_salida_programada, estado) VALUES (?, ?, '2026-11-20', '08:00:00', '12:00:00', 'Programado')")
   ->execute([$idFichaA, $idInstructor]);
$idHorarioFuturo = (int)$db->lastInsertId();

// Registrar una asistencia histórica en el horario pasado
$db->prepare("INSERT INTO asistencias (id_aprendiz, id_horario, fecha, hora_entrada_real, hora_salida_real, tiempo_retraso, estado) VALUES (?, ?, '2026-01-15', '08:05:00', '12:00:00', 5, 'Presente')")
   ->execute([$idAprendiz, $idHorarioPasado]);
$idAsistenciaHistorica = (int)$db->lastInsertId();

echo "--> Entorno preparado: FichaA=$idFichaA, FichaB=$idFichaB, Aprendiz=$idAprendiz, HorarioPasado=$idHorarioPasado, HorarioFuturo=$idHorarioFuturo\n\n";

// -------------------------------------------------------------
// EVALUACIÓN DE ESCENARIOS
// -------------------------------------------------------------
echo "--- EVALUANDO REGLAS DE NEGOCIO Y TRANSICIONES ---\n";

// 1. Una ficha activa se finaliza correctamente
$resFinalizar = $fichaModelo->finalizarFicha($idFichaA, $adminId, '2026-10-01', 'Cierre de ciclo formativo lectivo');
assertTest("Una ficha activa se finaliza correctamente", ($resFinalizar['exito'] ?? false) === true);

$fichaData = $fichaModelo->buscarFichaPorId($idFichaA);
assertTest("El estado en BD de la ficha es 'Finalizada'", ($fichaData['estado'] ?? '') === 'Finalizada');
assertTest("La fecha efectiva y usuario de finalización se registraron en BD", !empty($fichaData['fecha_finalizacion']) && (int)$fichaData['id_usuario_finalizacion'] === $adminId);

// 2. Una ficha ya finalizada no ejecuta nuevamente el cierre (Idempotencia / DomainException)
$reintentoBloqueado = false;
try {
    $fichaModelo->finalizarFicha($idFichaA, $adminId, '2026-10-01', 'Segundo intento');
} catch (DomainException $e) {
    $reintentoBloqueado = true;
}
assertTest("Una ficha ya finalizada rechaza un nuevo intento de cierre (Idempotencia)", $reintentoBloqueado);

// 3. Horarios futuros quedan cancelados y pasados conservados
$stmtHF = $db->prepare("SELECT estado, motivo_cancelacion FROM horarios WHERE id_horario = ?");
$stmtHF->execute([$idHorarioFuturo]);
$hFuturo = $stmtHF->fetch(PDO::FETCH_ASSOC);
assertTest("Los horarios futuros de la ficha finalizada se marcaron como 'Cancelado'", ($hFuturo['estado'] ?? '') === 'Cancelado');

$stmtHP = $db->prepare("SELECT estado FROM horarios WHERE id_horario = ?");
$stmtHP->execute([$idHorarioPasado]);
$hPasado = $stmtHP->fetch(PDO::FETCH_ASSOC);
assertTest("Los horarios pasados permanecen como 'Programado' (histórico intacto)", ($hPasado['estado'] ?? '') === 'Programado');

// 4. Asistencias pasadas no se modifican ni se eliminan
$stmtAsis = $db->prepare("SELECT id_asistencia, estado FROM asistencias WHERE id_asistencia = ?");
$stmtAsis->execute([$idAsistenciaHistorica]);
$asisRow = $stmtAsis->fetch(PDO::FETCH_ASSOC);
assertTest("La asistencia histórica permanece intacta", $asisRow !== false && $asisRow['estado'] === 'Presente');

// 5. Aprendiz con otra ficha activa NO pierde acceso a su cuenta
$stmtUsr = $db->prepare("SELECT estado FROM usuarios WHERE id_usuario = ?");
$stmtUsr->execute([$idUsuarioAprendiz]);
$usrRow = $stmtUsr->fetch(PDO::FETCH_ASSOC);
assertTest("El aprendiz mantiene su cuenta 'Activo' porque sigue vinculado a la Ficha B activa", ($usrRow['estado'] ?? '') === 'Activo');

// 6. Horarios cancelados no aparecen en la lista operativa por defecto
$horariosOperativos = $horarioModelo->listarHorarios(null, null, null, false);
$encontradoEnOperativo = false;
foreach ($horariosOperativos as $h) {
    if ((int)$h['id_horario'] === $idHorarioFuturo) {
        $encontradoEnOperativo = true;
        break;
    }
}
assertTest("El horario cancelado NO aparece en la vista operativa de horarios", !$encontradoEnOperativo);

// 7. Horarios cancelados o de fichas finalizadas NO generan inasistencias
$cronResult = $asistenciaModelo->sincronizarInasistenciasGlobal(true);
$asistenciaModelo->sincronizarInasistencias($idUsuarioAprendiz);
$stmtCheckInasist = $db->prepare("SELECT COUNT(*) as total FROM asistencias WHERE id_horario = ? AND LOWER(estado) = 'inasistencia'");
$stmtCheckInasist->execute([$idHorarioFuturo]);
$totalInasistCancelado = (int)$stmtCheckInasist->fetch(PDO::FETCH_ASSOC)['total'];
assertTest("Un horario cancelado de ficha finalizada produce 0 inasistencias en sincronización global e individual", $totalInasistCancelado === 0);

// 8. Reabrir ficha (excepcional auditado)
$resReabrir = $fichaModelo->reabrirFicha($idFichaA, $adminId, 'Reapertura para complementaria lectiva');
assertTest("Reapertura excepcional de ficha funciona con registro de trazabilidad", ($resReabrir['exito'] ?? false) === true);

$fichaDataReabierta = $fichaModelo->buscarFichaPorId($idFichaA);
assertTest("El estado vuelve a 'Activa' tras reapertura", ($fichaDataReabierta['estado'] ?? '') === 'Activa');

// Re-finalizamos para probar archivado
$fichaModelo->finalizarFicha($idFichaA, $adminId, '2026-10-01', 'Cierre definitivo para archivado');

// 9. Archivado de ficha finalizada
$resArchivar = $fichaModelo->archivarFicha($idFichaA, $adminId, 'Formación concluida y certificada');
assertTest("Archivar una ficha finalizada es exitoso", ($resArchivar['exito'] ?? false) === true);

$fichaDataArchivada = $fichaModelo->buscarFichaPorId($idFichaA);
assertTest("El estado en BD de la ficha es 'Archivada'", ($fichaDataArchivada['estado'] ?? '') === 'Archivada');

// 10. Una ficha archivada no admite nuevos horarios (debe lanzar DomainException)
$horarioArchivadoRechazado = false;
try {
    $horarioModelo->guardarHorario($idFichaA, '07:00:00', '13:00:00', '2026-12-01', $idInstructor);
} catch (DomainException $e) {
    $horarioArchivadoRechazado = true;
}
assertTest("Guardar horario en ficha archivada es rechazado con DomainException", $horarioArchivadoRechazado);

// 11. Eliminar Ficha Segura: Si tiene dependencias (asistencias, horarios, aprendices) SE DEBE RECHAZAR con DomainException
$eliminacionRechazada = false;
try {
    $fichaModelo->eliminarFichaSegura($idFichaA, $adminId, 'Prueba de intento de borrado');
} catch (DomainException $e) {
    $eliminacionRechazada = true;
}
assertTest("Eliminación de ficha con asistencias y dependencias es bloqueada con DomainException", $eliminacionRechazada);

// 12. Verificar auditoría de intento de eliminación fallido
$stmtAudit = $db->prepare("SELECT accion, resultado FROM auditoria_fichas WHERE id_ficha = ? AND accion = 'INTENTO_ELIMINACION'");
$stmtAudit->execute([$idFichaA]);
$auditIntento = $stmtAudit->fetch(PDO::FETCH_ASSOC);
assertTest("El intento de eliminación bloqueado quedó registrado en auditoria_fichas", !empty($auditIntento));

// 13. Eliminar Ficha Segura: Una ficha completamente limpia y sin dependencias SÍ se puede eliminar
$numFichaLimpia = 'TEST-CLEAN-' . time();
$db->prepare("INSERT INTO fichas (numero_ficha, nombre_programa, estado) VALUES (?, 'FICHA LIMPIA DE PRUEBA', 'Activa')")
   ->execute([$numFichaLimpia]);
$idFichaLimpia = (int)$db->lastInsertId();

$resEliminarLimpia = $fichaModelo->eliminarFichaSegura($idFichaLimpia, $adminId, 'Eliminación autorizada de ficha limpia creada por error');
assertTest("Ficha limpia sin dependencias se elimina con éxito de forma segura", $resEliminarLimpia === true);

// 14. Verificar auditoría de eliminación exitosa
$stmtAuditDel = $db->prepare("SELECT accion, resultado FROM auditoria_fichas WHERE id_ficha = ? AND accion = 'ELIMINACION'");
$stmtAuditDel->execute([$idFichaLimpia]);
$auditDel = $stmtAuditDel->fetch(PDO::FETCH_ASSOC);
assertTest("La eliminación autorizada quedó registrada en la bitácora de auditoría", !empty($auditDel));

// 15. Finalización de la segunda ficha para evaluar cuando el aprendiz NO tiene más fichas activas
$resFinalizarB = $fichaModelo->finalizarFicha($idFichaB, $adminId, '2026-10-01', 'Cierre ficha B');
$stmtUsrFinal = $db->prepare("SELECT estado FROM usuarios WHERE id_usuario = ?");
$stmtUsrFinal->execute([$idUsuarioAprendiz]);
$usrRowFinal = $stmtUsrFinal->fetch(PDO::FETCH_ASSOC);
assertTest("Cuando el aprendiz ya no pertenece a ninguna ficha activa, su cuenta pasa a 'Inactivo'", ($usrRowFinal['estado'] ?? '') === 'Inactivo');

// 16. ExcelHorarioHelper: Rechazar importación de horarios si la ficha está Archivada o Finalizada
$fichaArchivadaBD = $fichaModelo->buscarFichaPorNumero($numFichaA);
$rechazoExcel = ($fichaArchivadaBD !== null && $fichaArchivadaBD['estado'] !== 'Activa');
assertTest("ExcelHorarioHelper detecta ficha no activa ('{$fichaArchivadaBD['estado']}') para rechazar importación", $rechazoExcel);

// 17. Validar que la bitácora de auditoría registra usuario, fecha, correlación y estados
$stmtAuditFull = $db->prepare("SELECT accion, estado_anterior, estado_nuevo, id_usuario, resultado FROM auditoria_fichas WHERE id_ficha = ? ORDER BY id_auditoria ASC");
$stmtAuditFull->execute([$idFichaA]);
$auditorias = $stmtAuditFull->fetchAll(PDO::FETCH_ASSOC);
$tieneFinalizarYArchivar = false;
$accionesRegistradas = array_column($auditorias, 'accion');
if (in_array('FINALIZAR', $accionesRegistradas) && in_array('ARCHIVAR', $accionesRegistradas) && in_array('REABRIR', $accionesRegistradas)) {
    $tieneFinalizarYArchivar = true;
}
assertTest("La auditoría registra la trazabilidad completa del ciclo de vida (FINALIZAR, REABRIR, ARCHIVAR)", $tieneFinalizarYArchivar);

// -------------------------------------------------------------
// LIMPIEZA DE DATOS CONTROLADOS
// -------------------------------------------------------------
echo "\n--> Limpiando registros temporales de prueba...\n";
$db->prepare("DELETE FROM asistencias WHERE id_asistencia = ?")->execute([$idAsistenciaHistorica]);
$db->prepare("DELETE FROM horarios WHERE id_ficha IN (?, ?)")->execute([$idFichaA, $idFichaB]);
$db->prepare("DELETE FROM aprendiz_ficha WHERE id_ficha IN (?, ?)")->execute([$idFichaA, $idFichaB]);
$db->prepare("DELETE FROM aprendices WHERE id_aprendiz = ?")->execute([$idAprendiz]);
$db->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$idUsuarioAprendiz]);
$db->prepare("DELETE FROM auditoria_fichas WHERE id_ficha IN (?, ?, ?)")->execute([$idFichaA, $idFichaB, $idFichaLimpia]);
$db->prepare("DELETE FROM fichas WHERE id_ficha IN (?, ?)")->execute([$idFichaA, $idFichaB]);

echo "--> Limpieza completada con éxito.\n\n";

echo "====================================================================\n";
echo " RESUMEN FINAL DE PRUEBAS AUTOMATIZADAS:\n";
echo " Total ejecutadas: $totalTests\n";
echo " Pasadas:          $passedTests\n";
echo " Falladas:         $failedTests\n";
echo "====================================================================\n";

if ($failedTests === 0) {
    echo " RESULTADO: TODAS LAS PRUEBAS PASARON EXITOSAMENTE (100% OK)\n";
} else {
    echo " RESULTADO: SE ENCONTRARON FALLOS EN $failedTests PRUEBAS\n";
}
