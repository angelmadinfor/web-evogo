<?php
// Copia este archivo como mail-config.php y rellena la contraseña real.
// mail-config.php NUNCA debe subirse al repositorio (está en .gitignore);
// en producción lo genera automáticamente el workflow de despliegue
// a partir del secret de GitHub SMTP_PASSWORD.
return [
    'host' => 'servicios.evogo.app',
    'port' => 465,           // 465 = SMTPS (TLS implícito) · 587 = STARTTLS
    'secure' => 'ssl',       // 'ssl' para el puerto 465, 'tls' para el 587
    'username' => 'hola@evogo.app',
    'password' => 'CAMBIA_ESTO',
    'from_email' => 'hola@evogo.app',
    'from_name' => 'EvoGo',
    'notify_to' => 'madinfor@madinfor.com',
];
