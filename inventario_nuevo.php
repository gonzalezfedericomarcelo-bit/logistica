<?php
// Archivo: inventario_nuevo.php
session_start();
include 'conexion.php';
include 'funciones_permisos.php'; 

// Validar permiso (usamos la nueva columna)
if (!isset($_SESSION['usuario_id']) || !tiene_permiso('acceso_inventario', $pdo)) {
    header("Location: dashboard.php");
    exit();
}

include 'navbar.php';
?>

<div class="container mt-4 mb-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-boxes me-2"></i> Nuevo Cargo / Relevamiento de Inventario</h4>
        </div>
        <div class="card-body">
            <form id="formInventario" method="POST" action="inventario_guardar.php">
                
                <h5 class="text-secondary border-bottom pb-2 mb-3">1. Datos del Elemento</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Elemento / Mueble</label>
                        <input type="text" name="elemento" class="form-control" placeholder="Ej: Escritorio Tipo L, PC Dell..." required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Código Patrimonial / Serie</label>
                        <input type="text" name="codigo_inventario" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Servicio / Ubicación</label>
                        <input type="text" name="servicio_ubicacion" class="form-control" placeholder="Ej: Laboratorio Central" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observaciones / Estado</label>
                        <textarea name="observaciones" class="form-control" rows="2" placeholder="Ej: Buen estado, falta una manija..."></textarea>
                    </div>
                </div>

                <h5 class="text-secondary border-bottom pb-2 mb-3">2. Responsables</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Nombre del Responsable (Quien lo usa)</label>
                        <input type="text" name="nombre_responsable" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nombre del Jefe de Servicio</label>
                        <input type="text" name="nombre_jefe_servicio" class="form-control" required>
                    </div>
                </div>

                <h5 class="text-secondary border-bottom pb-2 mb-3">3. Firmas Digitales</h5>
                <div class="row text-center">
                    
                    <div class="col-md-4 mb-3">
                        <label class="fw-bold mb-2">Firma Responsable (Usuario)</label>
                        <div class="border rounded bg-light" style="height: 200px;">
                            <canvas id="sigResponsable" class="w-100 h-100"></canvas>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="clearPad('Responsable')">Borrar</button>
                        <input type="hidden" name="base64_responsable" id="base64_responsable">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold mb-2">Firma Relevador (Logística)</label>
                        <div class="border rounded bg-light" style="height: 200px;">
                            <canvas id="sigRelevador" class="w-100 h-100"></canvas>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="clearPad('Relevador')">Borrar</button>
                        <input type="hidden" name="base64_relevador" id="base64_relevador">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold mb-2">Firma Jefe de Servicio</label>
                        <div class="border rounded bg-light" style="height: 200px;">
                            <canvas id="sigJefe" class="w-100 h-100"></canvas>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="clearPad('Jefe')">Borrar</button>
                        <input type="hidden" name="base64_jefe" id="base64_jefe">
                    </div>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i> Guardar y Generar PDF</button>
                </div>

            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
    // Inicializar Pads
    const padResponsable = new SignaturePad(document.getElementById('sigResponsable'));
    const padRelevador = new SignaturePad(document.getElementById('sigRelevador'));
    const padJefe = new SignaturePad(document.getElementById('sigJefe'));

    function clearPad(quien) {
        if(quien === 'Responsable') padResponsable.clear();
        if(quien === 'Relevador') padRelevador.clear();
        if(quien === 'Jefe') padJefe.clear();
    }

    // Antes de enviar, guardar las firmas en los inputs hidden
    document.getElementById('formInventario').addEventListener('submit', function(e) {
        if (padResponsable.isEmpty() || padRelevador.isEmpty() || padJefe.isEmpty()) {
            e.preventDefault();
            alert("Por favor, se requieren las tres firmas.");
            return;
        }
        document.getElementById('base64_responsable').value = padResponsable.toDataURL();
        document.getElementById('base64_relevador').value = padRelevador.toDataURL();
        document.getElementById('base64_jefe').value = padJefe.toDataURL();
    });

    // Ajuste para resize
    window.addEventListener("resize", function() {
        // En una implementación real completa, aquí se debería redimensionar el canvas sin perder el dibujo
        // Por simplicidad en este ejemplo, no borramos, pero el canvas html5 a veces necesita cuidado.
    });
</script>

<?php include 'footer.php'; ?>