<?php
// Archivo: inventario_nuevo.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

// Validar permiso
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Inventario | Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .signature-pad-container {
            border: 2px dashed #ccc;
            border-radius: 5px;
            background-color: #fff;
            cursor: crosshair;
        }
        canvas {
            width: 100%;
            height: 150px;
            touch-action: none; /* Importante para firmar en móvil */
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-boxes me-2"></i> Nuevo Cargo Patrimonial</h4>
                <small>Formulario de Asignación</small>
            </div>
            <div class="card-body p-4">
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">Error al guardar. Intente nuevamente.</div>
                <?php endif; ?>

                <form id="formInventario" method="POST" action="inventario_guardar.php">
                    
                    <div class="row mb-4">
                        <div class="col-12"><h5 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-desktop me-2"></i>1. Datos del Elemento</h5></div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Elemento / Mueble</label>
                            <input type="text" name="elemento" class="form-control" placeholder="Ej: Escritorio Tipo L, PC Dell..." required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Código Patrimonial</label>
                            <input type="text" name="codigo_inventario" class="form-control" placeholder="Opcional">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Servicio / Ubicación</label>
                            <input type="text" name="servicio_ubicacion" class="form-control" placeholder="Ej: Laboratorio Central" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="2" placeholder="Estado del bien, detalles, etc."></textarea>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12"><h5 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-users me-2"></i>2. Responsables</h5></div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Responsable (Usuario Final)</label>
                            <input type="text" name="nombre_responsable" class="form-control" placeholder="Nombre y Apellido" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Jefe de Servicio (Aval)</label>
                            <input type="text" name="nombre_jefe_servicio" class="form-control" placeholder="Nombre y Apellido" required>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12"><h5 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-file-signature me-2"></i>3. Firmas Digitales</h5></div>
                        
                        <div class="col-md-4 mb-4 text-center">
                            <label class="fw-bold mb-2 text-muted">Firma Responsable</label>
                            <div class="signature-pad-container">
                                <canvas id="sigResponsable"></canvas>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 w-100" onclick="clearPad('Responsable')"><i class="fas fa-eraser"></i> Borrar</button>
                            <input type="hidden" name="base64_responsable" id="base64_responsable">
                        </div>

                        <div class="col-md-4 mb-4 text-center">
                            <label class="fw-bold mb-2 text-muted">Firma Relevador (Logística)</label>
                            <div class="signature-pad-container">
                                <canvas id="sigRelevador"></canvas>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 w-100" onclick="clearPad('Relevador')"><i class="fas fa-eraser"></i> Borrar</button>
                            <input type="hidden" name="base64_relevador" id="base64_relevador">
                        </div>

                        <div class="col-md-4 mb-4 text-center">
                            <label class="fw-bold mb-2 text-muted">Firma Jefe Servicio</label>
                            <div class="signature-pad-container">
                                <canvas id="sigJefe"></canvas>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 w-100" onclick="clearPad('Jefe')"><i class="fas fa-eraser"></i> Borrar</button>
                            <input type="hidden" name="base64_jefe" id="base64_jefe">
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg py-3 fw-bold shadow">
                            <i class="fas fa-save me-2"></i> GUARDAR Y GENERAR PDF
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    
    <script>
        // Configuración de los pads de firma
        function initPad(id) {
            const canvas = document.getElementById(id);
            // Ajustar tamaño del canvas al contenedor visualmente
            canvas.width = canvas.parentElement.offsetWidth;
            canvas.height = 150; 
            return new SignaturePad(canvas, { backgroundColor: 'rgba(255, 255, 255, 0)' });
        }

        let padResponsable, padRelevador, padJefe;

        // Inicializar al cargar
        window.addEventListener('DOMContentLoaded', () => {
            padResponsable = initPad('sigResponsable');
            padRelevador = initPad('sigRelevador');
            padJefe = initPad('sigJefe');
        });

        // Función para borrar
        window.clearPad = function(quien) {
            if(quien === 'Responsable') padResponsable.clear();
            if(quien === 'Relevador') padRelevador.clear();
            if(quien === 'Jefe') padJefe.clear();
        }

        // Antes de enviar, poner las firmas en los inputs ocultos
        document.getElementById('formInventario').addEventListener('submit', function(e) {
            // Validar que al menos haya una firma o las tres (según tu regla de negocio)
            if (padResponsable.isEmpty() || padRelevador.isEmpty() || padJefe.isEmpty()) {
                e.preventDefault();
                alert("Por favor, complete las tres firmas antes de guardar.");
                return;
            }
            
            document.getElementById('base64_responsable').value = padResponsable.toDataURL();
            document.getElementById('base64_relevador').value = padRelevador.toDataURL();
            document.getElementById('base64_jefe').value = padJefe.toDataURL();
        });

        // Reajustar si cambian el tamaño de la ventana (básico)
        window.addEventListener('resize', () => {
            // Nota: Al redimensionar se borra el canvas en esta implementación simple.
            // Lo ideal es redimensionar antes de firmar.
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>