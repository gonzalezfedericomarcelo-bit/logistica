<?php
// Archivo: login.php (VERSIÓN FINAL: LUCES ARRIBA/ABAJO + GUIRNALDAS 1 y 2)
session_start();
include 'conexion.php';

if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];

    if (!empty($usuario) && !empty($password)) {
        try {
            $sql = "SELECT id_usuario, nombre_completo, password, rol, activo, foto_perfil FROM usuarios WHERE usuario = :usu";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':usu' => $usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if ($user['activo'] == 1) {
                    $_SESSION['usuario_id'] = $user['id_usuario'];
                    $_SESSION['usuario_nombre'] = $user['nombre_completo'];
                    $_SESSION['usuario_rol'] = $user['rol'];
                    $_SESSION['usuario_perfil'] = $user['foto_perfil']; 
                    $_SESSION['foto_perfil'] = $user['foto_perfil']; 
                    
                    try { $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id")->execute([':id' => $user['id_usuario']]); } catch (Exception $e) {}
                    
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Usuario desactivado. Contacte al superior.";
                }
            } else {
                $error = "Credenciales inválidas.";
            }
        } catch (PDOException $e) {
            $error = "Error de conexión. Intente nuevamente.";
        }
    } else {
        $error = "Complete todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - SGALP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        :root {
            --military-green: #1a2f1a;
            --tactical-dark: #0f1410;
            --gold-accent: #d4af37;
            --text-grey: #b0b3b8;
        }

        body, html {
            height: 100%; margin: 0;
            font-family: 'Segoe UI', Roboto, sans-serif;
            background-color: var(--tactical-dark);
            overflow: hidden;
        }

        .bg-tactical {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-size: cover; background-position: center; z-index: 0;
        }
        .bg-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(15, 20, 15, 0.96) 0%, rgba(26, 47, 26, 0.92) 100%);
            z-index: 1;
        }

        /* --- MARCAS DE AGUA --- */
        .decorations-container {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 1; pointer-events: none; overflow: hidden;
        }
        .deco-icon { position: absolute; color: rgba(255, 255, 255, 0.04); font-size: 18rem; }
        .deco-sanidad { top: -50px; left: -50px; transform: rotate(-15deg); }
        .deco-logistica { top: -50px; right: -50px; transform: rotate(15deg); }
        .deco-camaraderia { bottom: -50px; left: -50px; transform: rotate(10deg); font-size: 15rem; }
        .deco-militar { bottom: -50px; right: -50px; transform: rotate(-10deg); }

        /* --- LUCES DE NAVIDAD ARRIBA --- */
        .top-lights-container {
            position: fixed; top: -15px; left: -5%; width: 110%; height: 60px;
            z-index: 100; pointer-events: none;
            border-top: 2px solid #222; border-radius: 50%;
            display: flex; justify-content: space-around; align-items: flex-start;
        }
        .bulb-hang {
            width: 12px; height: 12px; border-radius: 50%; position: relative;
            animation: flash 1.5s infinite alternate;
        }
        .bulb-hang::before {
            content: ''; position: absolute; top: -10px; left: 5px; width: 2px; height: 10px; background: #222;
        }
        .bulb-hang:nth-child(odd) { margin-top: 15px; } 
        .bulb-hang:nth-child(even) { margin-top: 35px; }
        .bulb-hang:nth-child(3n) { margin-top: 20px; }

        /* --- LUCES DE NAVIDAD ABAJO --- */
        .bottom-lights-container {
            position: fixed; bottom: 20px; left: -5%; width: 110%; height: 60px;
            z-index: 100; pointer-events: none;
            border-bottom: 2px solid #333; border-radius: 50%;
            display: flex; justify-content: space-around; align-items: flex-end;
        }
        .bulb-floor {
            width: 12px; height: 12px; border-radius: 50%; position: relative;
            animation: flash 1.5s infinite alternate;
        }
        .bulb-floor::before {
            content: ''; position: absolute; top: -4px; left: 3px; width: 6px; height: 6px; background: #222;
        }
        .bulb-floor:nth-child(odd) { transform: translateY(15px); } 
        .bulb-floor:nth-child(even) { transform: translateY(-5px); }

        /* COLORES COMUNES */
        .b-red { background-color: #c0392b; box-shadow: 0 0 10px #c0392b; animation-name: flash-red; }
        .b-gold { background-color: #f1c40f; box-shadow: 0 0 10px #f1c40f; animation-name: flash-gold; animation-delay: 0.3s; }
        .b-green { background-color: #2ecc71; box-shadow: 0 0 10px #2ecc71; animation-name: flash-green; animation-delay: 0.7s; }

        @keyframes flash-red { from { opacity: 0.3; } to { opacity: 1; box-shadow: 0 0 20px #e74c3c; } }
        @keyframes flash-gold { from { opacity: 0.3; } to { opacity: 1; box-shadow: 0 0 20px #f1c40f; } }
        @keyframes flash-green { from { opacity: 0.3; } to { opacity: 1; box-shadow: 0 0 20px #2ecc71; } }

        /* --- FORMULARIO --- */
        .login-container {
            position: relative; z-index: 2; height: 100%;
            display: flex; align-items: center; justify-content: center;
            flex-direction: column; overflow-y: auto; padding: 20px;
        }

        .card-login {
            background: rgba(25, 25, 25, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid var(--gold-accent);
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            width: 100%; max-width: 450px;
            padding: 2.5rem; color: #fff;
            margin-bottom: 1rem;
            position: relative;
            overflow: visible; 
        }

        /* --- GUIRNALDAS PNG --- */
        .garland-img {
            position: absolute;
            width: 130px; /* Tamaño ajustable */
            height: auto;
            z-index: 20;
            pointer-events: none;
            filter: drop-shadow(0 5px 5px rgba(0,0,0,0.5));
        }
        /* Guirnalda 1: Arriba Izquierda */
        .garland-left {
            top: -30px;
            left: -30px;
        }
        /* Guirnalda 2: Arriba Derecha (Espejada si es la misma, o normal si es otra) */
        .garland-right {
            top: -30px;
            right: -30px;
            /* Si necesitas espejarla descomenta esto: transform: scaleX(-1); */
        }

        .login-logo-container { text-align: center; margin-bottom: 1.5rem; margin-top: 1rem; }
        .login-logo {
            max-width: 160px; height: auto;
            filter: drop-shadow(0 0 1px rgba(255, 255, 255, 0.8));
        }

        .system-header { text-align: center; margin-bottom: 2rem; color: #fff; }
        .system-header h4 { font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; margin: 0; line-height: 1.4; }

        .form-floating > .form-control { background-color: rgba(0, 0, 0, 0.3) !important; border: 1px solid #444; color: #fff !important; }
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus, input:-webkit-autofill:active { -webkit-box-shadow: 0 0 0 30px rgba(0, 0, 0, 0.3) inset !important; -webkit-text-fill-color: white !important; transition: background-color 5000s ease-in-out 0s; }
        .form-floating > .form-control:focus { background-color: rgba(0, 0, 0, 0.5) !important; border-color: var(--gold-accent); box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25); color: #fff !important; }
        .form-floating > label { color: var(--text-grey); }
        .form-floating > .form-control:focus ~ label, .form-floating > .form-control:not(:placeholder-shown) ~ label { color: var(--gold-accent); }

        .btn-login { background: var(--gold-accent); color: #1a1a1a; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; border: none; padding: 12px; transition: all 0.3s ease; }
        .btn-login:hover { background: #c5a028; color: #000; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4); }

        .login-footer-container { text-align: center; color: rgba(255, 255, 255, 0.5); font-size: 0.75rem; line-height: 1.6; max-width: 500px; }
        .footer-divider { width: 50px; height: 1px; background: rgba(255, 255, 255, 0.2); margin: 10px auto; }
        .dev-credits strong { color: rgba(255, 255, 255, 0.8); }
    </style>
</head>
<body>

    <div class="bg-tactical"></div>
    <div class="bg-overlay"></div>

    <div class="decorations-container">
        <i class="fas fa-briefcase-medical deco-icon deco-sanidad"></i>
        <i class="fas fa-boxes deco-icon deco-logistica"></i>
        <i class="fas fa-users deco-icon deco-camaraderia"></i>
        <i class="fas fa-shield-alt deco-icon deco-militar"></i>
    </div>

    <div class="top-lights-container">
        <div class="bulb-hang b-red"></div><div class="bulb-hang b-green"></div><div class="bulb-hang b-gold"></div>
        <div class="bulb-hang b-red"></div><div class="bulb-hang b-green"></div><div class="bulb-hang b-gold"></div>
        <div class="bulb-hang b-red"></div><div class="bulb-hang b-green"></div><div class="bulb-hang b-gold"></div>
        <div class="bulb-hang b-red"></div><div class="bulb-hang b-green"></div><div class="bulb-hang b-gold"></div>
    </div>

    <div class="login-container">
        <div class="card-login animate__animated animate__fadeInDown">
            
            <img src="assets/img/guirnalda1.png" alt="Navidad" class="garland-img garland-left">
            
            <img src="assets/img/guirnalda2.png" alt="Navidad" class="garland-img garland-right">

            <div class="login-logo-container">
                <img src="assets/img/sgalp.png" alt="SGALP" class="login-logo" onerror="this.style.display='none'; document.getElementById('logo-text').style.display='block';">
                <h2 id="logo-text" class="text-white fw-bold mt-2" style="display:none;">SGALP</h2>
            </div>

            <div class="system-header">
                <h4>SISTEMA DE GESTIÓN AVANZADO<br>DE LOGÍSTICA Y PERSONAL</h4>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger border-0 bg-danger bg-opacity-25 text-white text-center small py-2 mb-4 animate__animated animate__headShake">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Usuario" required autocomplete="off">
                    <label for="usuario"><i class="fas fa-user me-2"></i>Usuario</label>
                </div>

                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                    <label for="password"><i class="fas fa-lock me-2"></i>Contraseña</label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-login btn-lg">
                        INGRESAR <i class="fas fa-sign-in-alt ms-2"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="login-footer-container animate__animated animate__fadeInUp animate__delay-1s">
            <div>
                &copy; <?php echo date('Y'); ?> Departamento de Logística<br>
                <i class="fas fa-shield-alt me-1"></i> Uso exclusivo personal autorizado
            </div>
            <div class="footer-divider"></div>
            <div class="dev-credits">
                Sistema desarrollado por el <strong>SG Mec Info Federico González</strong><br>
                Encargado de Informática<br>
                <span class="text-uppercase" style="letter-spacing: 0.5px;">Subgerencia Efectores Sanitarios Propios IOSFA</span>
            </div>
        </div>
    </div>

    <div class="bottom-lights-container">
        <div class="bulb-floor b-red"></div><div class="bulb-floor b-green"></div><div class="bulb-floor b-gold"></div>
        <div class="bulb-floor b-red"></div><div class="bulb-floor b-green"></div><div class="bulb-floor b-gold"></div>
        <div class="bulb-floor b-red"></div><div class="bulb-floor b-green"></div><div class="bulb-floor b-gold"></div>
        <div class="bulb-floor b-red"></div><div class="bulb-floor b-green"></div><div class="bulb-floor b-gold"></div>
    </div>

</body>
</html>