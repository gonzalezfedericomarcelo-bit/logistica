<?php
// Archivo: inventario_baja.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; // Incluir para mantener consistencia

if (!isset($_SESSION['usuario_id']) || !tiene_permiso('inventario_baja', $pdo)) {
    header("Location: inventario_lista.php"); exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$bien = null;
$error = '';

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM inventario_cargos WHERE id_cargo = ?");
        $stmt->execute([$id]);
        $bien = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$bien) {
        $error = "El bien solicitado no existe o no se pudo cargar.";
    }
} catch (Exception $e) {
    $error = "Error de base de datos: " . $e->getMessage();
}

if($_SERVER['REQUEST_METHOD']=='POST' && $bien){
    try {
        $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : 'Sin motivo especificado';
        
        // Historial
        $pdo->prepare("INSERT INTO historial_movimientos (id_bien, tipo_movimiento, usuario_registro, observacion_movimiento) VALUES (?, 'Baja', ?, ?)")->execute([$id, $_SESSION['usuario_id'], $motivo]);
        
        // Update Estado
        $pdo->prepare("UPDATE inventario_cargos SET estado='Baja' WHERE id_cargo=?")->execute([$id]);
        
        header("Location: inventario_lista.php"); 
        exit();
    } catch (Exception $e) {
        $error = "Error al procesar la baja: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dar de Baja | Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { background-color: #f8f9fa; }</style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                
                <?php if($error): ?>
                    <div class="alert alert-danger shadow-sm">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                        <div class="mt-2"><a href="inventario_lista.php" class="btn btn-sm btn-outline-danger">Volver al listado</a></div>
                    </div>
                <?php elseif($bien): ?>
                    
                    <div class="card shadow border-0">
                        <div class="card-header bg-danger text-white py-3">
                            <h4 class="mb-0"><i class="fas fa-ban me-2"></i> Confirmar Baja de Bien</h4>
                        </div>
                        <div class="card-body p-4">
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i> Estás a punto de dar de baja un bien del inventario. Esto cambiará su estado a "Baja" pero mantendrá el registro histórico.
                            </div>

                            <div class="mb-4">
                                <label class="text-muted small fw-bold text-uppercase">Elemento</label>
                                <div class="fs-5 fw-bold"><?php echo htmlspecialchars($bien['elemento']); ?></div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-6">
                                    <label class="text-muted small fw-bold text-uppercase">Código</label>
                                    <div class="text-dark"><?php echo htmlspecialchars($bien['codigo_inventario']); ?></div>
                                </div>
                                <div class="col-6">
                                    <label class="text-muted small fw-bold text-uppercase">Ubicación Actual</label>
                                    <div class="text-dark"><?php echo htmlspecialchars($bien['servicio_ubicacion']); ?></div>
                                </div>
                            </div>

                            <form method="POST">
                                <div class="mb-4">
                                    <label for="motivo" class="form-label fw-bold">Motivo de la Baja <span class="text-danger">*</span></label>
                                    <textarea name="motivo" id="motivo" class="form-control" rows="4" placeholder="Describa por qué se da de baja (rotura, pérdida, desuso, etc.)" required></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="inventario_lista.php" class="btn btn-light border px-4">Cancelar</a>
                                    <button type="submit" class="btn btn-danger px-4 fw-bold"><i class="fas fa-check-circle me-2"></i> Confirmar Baja</button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>