<?php
// Archivo: generar_simulacion.php (CORREGIDO: Columna 'usuario', excluye 'admin', incluye a Federico)
session_start();
require 'conexion.php'; // Asegúrate de que conecta a la BD correcta

header('Content-Type: text/html; charset=utf-8');
echo "<h2>⚙️ Iniciando simulación de datos...</h2>";

try {
    // 1. LIMPIEZA DE DATOS
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("DELETE FROM asistencia_detalles");
    $pdo->exec("DELETE FROM asistencia_partes");
    $pdo->exec("ALTER TABLE asistencia_partes AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE asistencia_detalles AUTO_INCREMENT = 1"); 
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    echo "✅ Tablas de asistencia limpiadas correctamente.<br>";

    // 2. OBTENER USUARIOS REALES
    // CORRECCIÓN: Usamos 'usuario' en el WHERE en lugar de 'username'.
    // Excluimos explícitamente al que se llame 'admin'.
    $sql_users = "
        SELECT id_usuario, nombre_completo 
        FROM usuarios 
        WHERE activo = 1 AND usuario != 'admin' 
        ORDER BY id_usuario ASC
    ";
    $stmt_users = $pdo->query($sql_users);
    $usuarios_reales = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    if (count($usuarios_reales) == 0) {
        die("❌ ERROR: No se encontraron usuarios activos (excluyendo 'admin').");
    }

    echo "✅ Se encontraron <strong>" . count($usuarios_reales) . "</strong> usuarios reales para simular:<br>";
    echo "<ul style='font-size:0.8em; color:#555; max-height:100px; overflow-y:scroll; border:1px solid #ccc;'>";
    foreach($usuarios_reales as $u) {
        echo "<li>ID " . $u['id_usuario'] . ": " . htmlspecialchars($u['nombre_completo']) . "</li>";
    }
    echo "</ul>";

    // 3. OBTENER MOTIVOS
    $motivos = ['Parte de Enfermo', 'Licencia Anual', 'Trámites Personales', 'Comisión del Servicio', 'Ausente con Aviso'];
    try {
        $stmt_nov = $pdo->query("SELECT descripcion FROM configuracion_novedades");
        $motivos_db = $stmt_nov->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($motivos_db)) $motivos = $motivos_db; 
    } catch (Exception $e) { }

    // 4. GENERAR ASISTENCIA
    $pdo->beginTransaction();
    
    // Usamos al primer usuario disponible como creador
    $id_creador_parte = $usuarios_reales[0]['id_usuario']; 
    $dias_generados = 0;
    $detalles_generados = 0;
    $fecha_inicio = date('Y-m-d', strtotime('-35 days'));

    $stmt_parte = $pdo->prepare("INSERT INTO asistencia_partes (fecha, id_creador, observaciones_generales, estado, fecha_creacion) VALUES (:f, :c, :o, 'aprobado', NOW())");
    $stmt_det   = $pdo->prepare("INSERT INTO asistencia_detalles (id_parte, id_usuario, presente, observacion_individual) VALUES (:idp, :idu, :pres, :obs)");

    for ($i = 0; $i <= 35; $i++) {
        $fecha_actual = date('Y-m-d', strtotime("$fecha_inicio +$i days"));
        $dia_semana = date('N', strtotime($fecha_actual)); 

        if ($dia_semana >= 6) continue; // Omitir fin de semana

        // Crear Parte
        $obs_gral = "Parte diario simulado $fecha_actual";
        $stmt_parte->execute([':f' => $fecha_actual, ':c' => $id_creador_parte, ':o' => $obs_gral]);
        $id_parte = $pdo->lastInsertId();
        $dias_generados++;

        // Detalles
        foreach ($usuarios_reales as $user) {
            $azar = rand(1, 100);
            if ($azar > 85) { // 15% Ausente
                $es_presente = 0;
                $motivo = $motivos[array_rand($motivos)];
            } else {
                $es_presente = 1;
                $motivo = null; 
            }

            $stmt_det->execute([
                ':idp' => $id_parte,
                ':idu' => $user['id_usuario'],
                ':pres' => $es_presente,
                ':obs' => $motivo
            ]);
            $detalles_generados++;
        }
    }

    $pdo->commit();
    echo "<hr><h3 style='color:green'>🎉 Simulación Exitosa!</h3>";
    echo "<p>Se generaron datos para Federico y los demás (admin excluido).</p>";
    echo "<a href='asistencia_estadisticas.php'>Ver Estadísticas</a>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<h3 style='color:red'>❌ Error Fatal: " . $e->getMessage() . "</h3>";
}
?>