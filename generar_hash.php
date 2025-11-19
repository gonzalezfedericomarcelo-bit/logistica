<?php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "El hash de 'admin123' es: <br><strong>" . $hash . "</strong>";
?>