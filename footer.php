<?php
// Archivo: footer.php (VERSIÓN FINAL: CON LOGO)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$version_sistema = "v2.5.1 (Build 2025)";
$anio_actual = date('Y');
?>
<style>
    /* Estilos exclusivos del Footer */
    .main-footer {
        background-color: #1a1a1a; /* Oscuro táctico */
        color: #b0b3b8; /* Color base legible */
        font-size: 0.85rem;
        border-top: 3px solid #d4af37; /* Línea dorada */
        margin-top: auto;
    }
    .footer-heading {
        color: #fff;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 1rem;
        font-size: 0.9rem;
        letter-spacing: 1px;
    }
    .footer-link {
        color: #b0b3b8;
        text-decoration: none;
        display: block;
        margin-bottom: 0.5rem;
        transition: color 0.2s;
    }
    .footer-link:hover {
        color: #d4af37; /* Dorado al pasar mouse */
        padding-left: 5px;
    }
    .footer-text {
        color: #b0b3b8; 
    }
    .dev-signature {
        font-size: 0.75rem;
        opacity: 0.8; 
    }
    .system-status {
        display: inline-block;
        width: 10px;
        height: 10px;
        background-color: #2ecc71; /* Verde operativo */
        border-radius: 50%;
        margin-right: 5px;
        box-shadow: 0 0 5px #2ecc71;
    }
    /* ESTILO DEL LOGO EN FOOTER */
    .footer-logo {
        height: 40px; /* Tamaño ideal para footer */
        width: auto;
        margin-bottom: 1rem;
        /* Filtro para que se vea blanco/brillante sobre fondo oscuro */
        filter: brightness(0) invert(1); 
        opacity: 0.8;
        transition: opacity 0.3s ease;
    }
    .footer-logo:hover {
        opacity: 1;
    }

    #btn-back-to-top {
        position: fixed;
        bottom: 20px;
        right: 20px;
        display: none;
        z-index: 99;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        background-color: #d4af37;
        color: #000;
        border: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.3);
    }
    #btn-back-to-top:hover { background-color: #fff; }
</style>

<footer class="main-footer pt-5 pb-3 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <img src="assets/img/sgalp.png" alt="SGALP Logo" class="footer-logo">
                
                <p class="small mb-2 footer-text">Sistema de Gestión Avanzado de Logística y Personal.</p>
                <p class="small mb-2 footer-text"><span class="system-status"></span> Estado: <strong>Operativo</strong></p>
                <p class="small mb-0 footer-text">Versión: <?php echo $version_sistema; ?></p>
                <p class="small footer-text" id="reloj-footer">--:--:--</p>
            </div>

            <div class="col-md-4 mb-4">
                <h6 class="footer-heading">Navegación Rápida</h6>
                <div class="row">
                    <div class="col-6">
                        <a href="dashboard.php" class="footer-link"><i class="fas fa-home me-2"></i>Inicio</a>
                        <a href="tareas_lista.php" class="footer-link"><i class="fas fa-tasks me-2"></i>Tareas</a>
                        <a href="pedidos_lista_usuario.php" class="footer-link"><i class="fas fa-file-signature me-2"></i>Pedidos</a>
                        <a href="asistencia_estadisticas.php" class="footer-link"><i class="fas fa-clipboard-check me-2"></i>Personal</a>
                    </div>
                    <div class="col-6">
                        <a href="avisos.php" class="footer-link"><i class="fas fa-newspaper me-2"></i>Novedades</a>
                        <a href="chat.php" class="footer-link"><i class="fas fa-comments me-2"></i>Chat</a>
                        <a href="perfil.php" class="footer-link"><i class="fas fa-user-cog me-2"></i>Mi Perfil</a>
                    </div>
                </div>
            </div>

           <div class="col-md-4 mb-4">
    <h6 class="footer-heading"><i class="fas fa-headset me-2 text-warning"></i>Soporte Informático</h6>
    <ul class="list-unstyled small footer-text">
        <li class="mb-2"><i class="fas fa-user-shield me-2"></i> <strong>SG Mec Info Fede González</strong></li>
        <li class="mb-2"><i class="fas fa-building me-2"></i> Encargado de Informática</li>
        
        <li class="whatsapp-contacto">
            <i class="fab fa-whatsapp me-2 fa-lg"></i> 
            <a href="https://web.whatsapp.com/send?phone=541166116861&text=Hola%20Federico.%20Me%20comunico%20a%20trav%C3%A9s%20de%20SGALP%20..." class="footer-link d-inline-block p-0">
                Contactar por WhatsApp
            </a>
        </li>
        
        <li class="mb-2"><i class="fas fa-phone-alt me-2"></i> Int: <strong>2024</strong> (Logística)</li>
        
        <li><a href="mailto:soporte@iosfa.mil.ar" class="footer-link d-inline-block p-0"><i class="fas fa-envelope me-2"></i>soporte@federicogonzalez.net</a></li>
    </ul>
</div>
        </div>

        <hr style="border-color: rgba(255,255,255,0.1);">

        <div class="row">
            <div class="col-12 text-center">
                <p class="mb-1 dev-signature">
                    &copy; <?php echo $anio_actual; ?> <strong>Dpto Logística</strong> - Uso Oficial Exclusivo.
                    <span class="mx-2">|</span>
                    Desarrollo <i class="fas fa-bolt text-warning"></i> SG Mec Info Federico González - Policlínica Grl Actis - IOSFA
                </p>
            </div>
        </div>
    </div>
</footer>

<button type="button" id="btn-back-to-top" title="Volver arriba">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
    function actualizarRelojFooter() {
        const ahora = new Date();
        const opciones = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
        const horaString = ahora.toLocaleTimeString('es-AR', opciones);
        const fechaString = ahora.toLocaleDateString('es-AR', { weekday: 'short', day: 'numeric', month: 'short' });
        const reloj = document.getElementById('reloj-footer');
        if(reloj) reloj.innerText = `${fechaString} | ${horaString}`;
    }
    setInterval(actualizarRelojFooter, 1000);
    actualizarRelojFooter();

    const btnBack = document.getElementById("btn-back-to-top");
    window.onscroll = function () {
        if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
            btnBack.style.display = "block";
        } else {
            btnBack.style.display = "none";
        }
    };
    btnBack.addEventListener("click", function () {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });
</script>