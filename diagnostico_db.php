<?php
// Archivo: diagnostico_db.php
// HERRAMIENTA DE DIAGNÓSTICO Y REPARACIÓN
session_start();
include 'conexion.php';

// Habilitar reporte de errores al máximo para ver fallos SQL
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Diagnóstico DB</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='container mt-5'>";

echo "<h1><i class='fas fa-stethoscope'></i> Diagnóstico de Base de Datos</h1>";
echo "<hr>";

// 1. VERIFICAR CONEXIÓN
if($pdo) {
    echo "<div class='alert alert-success'>✅ Conexión a Base de Datos: OK</div>";
} else {
    die("<div class='alert alert-danger'>❌ Error crítico: No hay conexión a la base de datos.</div>");
}

// 2. VERIFICAR TABLA 'inventario_campos_dinamicos'
echo "<h3>2. Verificando Tabla de Campos...</h3>";
try {
    // Intentamos leer la tabla. Si falla, es que no existe.
    $test = $pdo->query("SELECT 1 FROM inventario_campos_dinamicos LIMIT 1");
    echo "<div class='alert alert-success'>✅ La tabla <b>inventario_campos_dinamicos</b> EXISTE.</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-warning'>⚠️ La tabla <b>inventario_campos_dinamicos</b> NO EXISTE o tiene errores. Intentando crearla...</div>";
    
    // SQL PARA CREAR LA TABLA QUE FALTA
    $sql_create = "CREATE TABLE IF NOT EXISTS `inventario_campos_dinamicos` (
      `id_campo` int(11) NOT NULL AUTO_INCREMENT,
      `id_tipo_bien` int(11) NOT NULL,
      `etiqueta` varchar(100) NOT NULL,
      `tipo_input` varchar(50) DEFAULT 'text',
      `orden` int(11) DEFAULT 0,
      PRIMARY KEY (`id_campo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    try {
        $pdo->exec($sql_create);
        echo "<div class='alert alert-success'>✅ Tabla creada EXITOSAMENTE. Ahora el importador funcionará.</div>";
    } catch (Exception $ex) {
        die("<div class='alert alert-danger'>❌ ERROR AL CREAR TABLA: " . $ex->getMessage() . "</div>");
    }
}

// 3. TABLA DE VALORES (Por si acaso falta también)
$sql_val = "CREATE TABLE IF NOT EXISTS `inventario_valores_dinamicos` (
  `id_valor` int(11) NOT NULL AUTO_INCREMENT,
  `id_cargo` int(11) NOT NULL,
  `id_campo` int(11) NOT NULL,
  `valor` text,
  PRIMARY KEY (`id_valor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$pdo->exec($sql_val);


// 4. ANÁLISIS DE CATEGORÍAS
echo "<h3>3. Análisis de Categorías y Campos</h3>";
echo "<table class='table table-bordered'>";
echo "<thead class='table-dark'><tr><th>ID</th><th>Categoría</th><th>Tipo</th><th>Campos Detectados</th><th>Acción Reparación</th></tr></thead>";
echo "<tbody>";

try {
    $cats = $pdo->query("SELECT * FROM inventario_tipos_bien")->fetchAll(PDO::FETCH_ASSOC);

    foreach($cats as $c) {
        // Contar campos reales en la DB
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?");
        $stmt->execute([$c['id_tipo_bien']]);
        $count = $stmt->fetchColumn();
        
        $estado_campos = ($count > 0) ? "<span class='badge bg-success'>$count Campos</span>" : "<span class='badge bg-danger'>0 Campos (VACÍA)</span>";
        
        // Botón para inyectar campos si está vacía y es dinámica
        $btn = "";
        if ($count == 0 && $c['tiene_campos_tecnicos'] == 2) {
            $btn = "<form method='POST' style='display:inline;'>
                        <input type='hidden' name='reparar_id' value='{$c['id_tipo_bien']}'>
                        <button class='btn btn-sm btn-primary'>🛠️ Inyectar Campos Default</button>
                    </form>";
        }

        echo "<tr>
                <td>{$c['id_tipo_bien']}</td>
                <td>{$c['nombre']}</td>
                <td>{$c['tiene_campos_tecnicos']}</td>
                <td>$estado_campos</td>
                <td>$btn</td>
              </tr>";
    }
} catch (Exception $e) {
    echo "<tr><td colspan='5'>Error leyendo categorías: ".$e->getMessage()."</td></tr>";
}
echo "</tbody></table>";

// 5. LÓGICA DE REPARACIÓN MANUAL (INYECTAR DATOS)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reparar_id'])) {
    $id_rep = $_POST['reparar_id'];
    echo "<div class='alert alert-info'>Intentando reparar ID: $id_rep ...</div>";
    
    // Insertamos campos básicos de prueba para que deje de estar vacía
    // Esto simula lo que haría el importador
    $campos_default = ['Marca', 'Modelo', 'Numero Serie', 'Descripcion Tecnica'];
    $orden = 1;
    
    $stmtIns = $pdo->prepare("INSERT INTO inventario_campos_dinamicos (id_tipo_bien, etiqueta, tipo_input, orden) VALUES (?, ?, 'text', ?)");
    
    foreach($campos_default as $campo) {
        $stmtIns->execute([$id_rep, $campo, $orden++]);
    }
    
    echo "<div class='alert alert-success'>✅ Campos inyectados. Recargando...</div>";
    echo "<script>setTimeout(function(){ window.location.href='diagnostico_db.php'; }, 1000);</script>";
}

echo "<div class='mt-4 p-3 bg-light border rounded'>";
echo "<h4>¿Qué hacer ahora?</h4>";
echo "<ol>";
echo "<li>Si ves alguna <b>Alerta Roja</b> arriba, este script ya intentó arreglarla al ejecutarse.</li>";
echo "<li>Si ves tu categoría 'Informática' con <b>0 Campos</b>, dale al botón azul <b>'Inyectar Campos Default'</b>.</li>";
echo "<li>Cuando diga '4 Campos' (o más), ve a crear el nuevo bien.</li>";
echo "</ol>";
echo "<a href='inventario_nuevo.php' class='btn btn-lg btn-success w-100'>Ir a Nuevo Inventario (Probar ahora)</a>";
echo "</div>";

echo "</body></html>";
?>