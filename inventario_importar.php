<?php
// Archivo: inventario_importar.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php';

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php"); exit();
}

// Obtener categorías existentes para el select de carga de datos
$tipos = $pdo->query("SELECT * FROM inventario_tipos_bien ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Centro de Importación | Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-file-import text-primary"></i> Centro de Importación</h2>
            <a href="inventario_lista.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Tablero</a>
        </div>

        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <ul class="nav nav-tabs card-header-tabs" id="importTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active text-dark bg-white fw-bold" id="struct-tab" data-bs-toggle="tab" data-bs-target="#struct" type="button">1. Importar Estructura (Categorías)</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link text-white" id="data-tab" data-bs-toggle="tab" data-bs-target="#data" type="button">2. Importar Datos (Bienes)</button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-4">
                <div class="tab-content" id="myTabContent">
                    
                    <div class="tab-pane fade show active" id="struct" role="tabpanel">
                        <div class="alert alert-info border-info">
                            <i class="fas fa-info-circle"></i> <strong>¿Para qué sirve?</strong> 
                            Aquí creas categorías nuevas (ej: "Refrigeración", "Herramientas") definiendo qué columnas (campos técnicos) tendrán.
                            <br>El archivo CSV solo debe tener <strong>una fila con los títulos</strong> de las características.
                        </div>

                        <form action="importar_estructura_procesar.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nombre de la Nueva Categoría</label>
                                <input type="text" name="nombre_categoria" class="form-control" placeholder="Ej: Refrigeración" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Archivo de Estructura (.csv)</label>
                                <input type="file" name="archivo_estructura" class="form-control" accept=".csv" required>
                            </div>
                            <button type="submit" class="btn btn-primary px-4 fw-bold">Crear Estructura</button>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="data" role="tabpanel">
                        <div class="alert alert-success border-success">
                            <i class="fas fa-info-circle"></i> <strong>Carga Masiva</strong>
                            Aquí subes los bienes reales a una categoría que ya exista.
                        </div>

                        <form action="importar_datos_procesar.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Seleccione Categoría Destino</label>
                                <select name="id_tipo_bien" class="form-select" required>
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach($tipos as $t): ?>
                                        <option value="<?php echo $t['id_tipo_bien']; ?>"><?php echo htmlspecialchars($t['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Archivo de Datos (.csv)</label>
                                <input type="file" name="archivo_datos" class="form-control" accept=".csv" required>
                            </div>
                            <button type="submit" class="btn btn-success px-4 fw-bold">Importar Bienes</button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
        
        <?php if(isset($_GET['msg']) && $_GET['msg']=='importado_ok'): ?>
            <div class="alert alert-success mt-3 fw-bold">
                <i class="fas fa-check"></i> ¡Importación de datos completada exitosamente! Se cargaron <?php echo $_GET['cant'] ?? 'varios'; ?> registros.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script simple para cambiar estilos de las pestañas
        const tabs = document.querySelectorAll('.nav-link');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => { t.classList.remove('text-dark', 'bg-white', 'fw-bold'); t.classList.add('text-white'); });
                this.classList.remove('text-white');
                this.classList.add('text-dark', 'bg-white', 'fw-bold');
            });
        });
    </script>
</body>
</html>