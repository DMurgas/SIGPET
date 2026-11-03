<?php
/** Protección CSRF para formularios que modifican información. */
function obtenerTokenCSRF(): string
{
    if (empty($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_csrf'];
}

function validarTokenCSRF(): bool
{
    return isset($_SESSION['token_csrf'], $_POST['token_csrf'])
        && hash_equals($_SESSION['token_csrf'], (string) $_POST['token_csrf']);
}
