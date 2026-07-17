<?php

// Copia este archivo fuera de public_html como:
// private/bienestar-database.php
// Reemplaza los valores únicamente en el servidor y nunca subas ese archivo.
return [
    // Mantén json durante la importación inicial; cambia a mysql al verificarla.
    'storage' => 'json',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'PREFIJO_bienestar',
    'username' => 'PREFIJO_bienestar',
    'password' => 'CONTRASENA_NUEVA_Y_PRIVADA',
    'charset' => 'utf8mb4',

    // Recuperación opcional del admin. Usa un valor aleatorio de 32 caracteres
    // o más, úsalo una sola vez en /admin/recover.php y después déjalo vacío.
    'admin_recovery_token' => '',
];
