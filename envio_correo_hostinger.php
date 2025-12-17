<?php
// Archivo: envio_correo_hostinger.php

function enviarCorreoNativo($destinatario, $asunto, $cuerpoHTML) {
    // --- TUS DATOS REALES ---
    $usuario_mail = 'info@federicogonzalez.net'; 
    $password_mail = 'Fmg35911@'; 
    $servidor = 'ssl://smtp.hostinger.com';
    $puerto = 465;
    // ------------------------

    $contexto = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    // El @ es vital acá para que si hay una micro-demora no imprima basura
    $socket = @stream_socket_client("$servidor:$puerto", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $contexto);

    if (!$socket) {
        return "Error Conexión: $errstr ($errno)";
    }

    function leer($s) {
        $data = "";
        while($str = @fgets($s, 515)) {
            $data .= $str;
            if(substr($str, 3, 1) == " ") break;
        }
        return $data;
    }

    function escribir($s, $c) {
        @fputs($s, $c . "\r\n");
        return leer($s);
    }

    leer($socket);
    escribir($socket, "EHLO " . $_SERVER['HTTP_HOST']);
    escribir($socket, "AUTH LOGIN");
    escribir($socket, base64_encode($usuario_mail));
    escribir($socket, base64_encode($password_mail));

    escribir($socket, "MAIL FROM: <$usuario_mail>");
    escribir($socket, "RCPT TO: <$destinatario>");
    escribir($socket, "DATA");

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Sistema Logistica <$usuario_mail>\r\n";
    $headers .= "To: $destinatario\r\n";
    $headers .= "Subject: $asunto\r\n";
    $headers .= "Date: " . date("r") . "\r\n";

    @fputs($socket, "$headers\r\n$cuerpoHTML\r\n.\r\n");
    $resultado = leer($socket);
    
    @fputs($socket, "QUIT\r\n");
    @fclose($socket);

    if (strpos($resultado, '250') !== false) {
        return true;
    } else {
        return "Error SMTP: " . $resultado;
    }
}