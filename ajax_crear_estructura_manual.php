<?php
// Archivo: ajax_crear_estructura_manual.php
session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_categoria']);
    $campos = $_POST['campos'] ?? []; // Array de nombres de columnas

    if (empty($nombre)) {
        echo json_encode(['status' => 'error', 'msg' => 'Falta el nombre de la categoría']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Crear la Categoría
        // Verificamos si existe
        $stmt = $pdo->prepare("SELECT id_tipo_bien FROM inventario_tipos_bien WHERE nombre = ?");
        $stmt->execute([$nombre]);
        $existe = $stmt->fetch();

        if ($existe) {
            $id_tipo = $existe['id_tipo_bien'];
             // Aseguramos que sea tipo dinámico (2)
            $pdo->prepare("UPDATE inventario_tipos_bien SET tiene_campos_tecnicos = 2 WHERE id_tipo_bien = ?")->execute([$id_tipo]);
        } else {
            // Creamos nueva
            $stmt = $pdo->prepare("INSERT INTO inventario_tipos_bien (nombre, descripcion, icono, tiene_campos_tecnicos) VALUES (?, 'Creado manualmente', 'fas fa-box', 2)");
            $stmt->execute([$nombre]);
            $id_tipo = $pdo->lastInsertId();
        }

        // 2. Insertar los Campos
        // Obtenemos el último orden
        $stmtOrden = $pdo->prepare("SELECT MAX(orden) FROM inventario_campos_dinamicos WHERE id_tipo_bien = ?");
        $stmtOrden->execute([$id_tipo]);
        $orden = ($stmtOrden->fetchColumn() ?: 0) + 1;

        foreach ($campos as $campo) {
            $etiqueta = trim($campo);
            if (!empty($etiqueta)) {
                // Verificar duplicados
                $check = $pdo->prepare("SELECT id_campo FROM inventario_campos_dinamicos WHERE id_tipo_bien = ? AND etiqueta = ?");
                $check->execute([$id_tipo, $etiqueta]);
                if (!$check->fetch()) {
                    $sql = "INSERT INTO inventario_campos_dinamicos (id_tipo_bien, etiqueta, tipo_input, orden) VALUES (?, ?, 'text', ?)";
                    $pdo->prepare($sql)->execute([$id_tipo, $etiqueta, $orden++]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'ok']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
}
?>