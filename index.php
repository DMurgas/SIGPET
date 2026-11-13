<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header(
    'Location: ' .
    (isset($_SESSION['id_usuario']) ? 'dashboard.php' : 'login.php')
);

exit();