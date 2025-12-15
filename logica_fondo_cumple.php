<?php
// Archivo: logica_fondo_cumple.php

if (isset($_SESSION['usuario_id'])) {
    
    // 1. Consultar si ya se saludó este año (confirmado)
    $stmt_c = $pdo->prepare("SELECT fecha_nacimiento, ultimo_saludo_cumple FROM usuarios WHERE id_usuario = :id");
    $stmt_c->execute([':id' => $_SESSION['usuario_id']]);
    $u_data = $stmt_c->fetch(PDO::FETCH_ASSOC);

    if ($u_data && !empty($u_data['fecha_nacimiento'])) {
        
        // 2. Calcular fechas (Igual que en el modal)
        $hoy = new DateTime('today');
        $fecha_nac = new DateTime($u_data['fecha_nacimiento']);
        $anio_actual = $hoy->format('Y');
        $cumple_actual = new DateTime($anio_actual . '-' . $fecha_nac->format('m-d'));
        $diff = $cumple_actual->diff($hoy);
        
        $anio_objetivo = $anio_actual;
        $es_fecha_valida = false;

        // Lógica de 3 días
        if ($diff->invert == 0 && $diff->days <= 2) {
            $es_fecha_valida = true;
        } elseif ($hoy->format('m') == '01' && $fecha_nac->format('m') == '12') {
            $anio_pasado = $anio_actual - 1;
            $cumple_pasado = new DateTime($anio_pasado . '-' . $fecha_nac->format('m-d'));
            $diff_past = $cumple_pasado->diff($hoy);
            if ($diff_past->invert == 0 && $diff_past->days <= 2) {
                $es_fecha_valida = true;
                $anio_objetivo = $anio_pasado;
            }
        }

        // 3. CONDICIÓN CLAVE: Si es la fecha válida Y el usuario YA CONFIRMÓ (ultimo_saludo == anio_objetivo)
        // Entonces mostramos el modo fiesta en el dashboard
        if ($es_fecha_valida && (int)$u_data['ultimo_saludo_cumple'] == $anio_objetivo) {
            ?>
            <style>
                /* Cambiar fondo del dashboard a rosadito suave */
                body {
                    background-color: #fff0f5 !important;
                    background-image: linear-gradient(to bottom, #fff0f5, #ffe4e1) !important;
                    background-repeat: no-repeat;
                    background-attachment: fixed;
                }

                /* La rosa fija en la esquina (Mismo estilo que el modal pero fija) */
                .dashboard-rose-fixed {
                    position: fixed;
                    bottom: -20px;
                    right: 10px; /* Un poco metida hacia afuera para que no tape botones del footer si hay */
                    width: 200px;
                    height: auto;
                    z-index: 0; /* Al fondo, para que no tape clicks de nada */
                    pointer-events: none; /* Te deja hacer clic a través de ella */
                    opacity: 0.8; /* Un poco transparente para que no moleste */
                    filter: drop-shadow(0 0 10px rgba(0,0,0,0.1));
                }
                
                /* Ajuste móvil */
                @media (max-width: 768px) {
                    .dashboard-rose-fixed {
                        width: 120px;
                        right: -20px;
                    }
                }
            </style>
            
            <img src="assets/rosas.png" class="dashboard-rose-fixed" alt="Modo Cumpleaños">
            <?php
        }
    }
}
?>