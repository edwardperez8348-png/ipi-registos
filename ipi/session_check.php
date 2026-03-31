<?php
// Timeout de sessão — 10 minutos (600 segundos)
define('SESSION_TIMEOUT', 600);

if (!isset($_SESSION['utilizador'])) {
    header('Location: login.php');
    exit;
}

// Verificar tempo de inatividade
if (isset($_SESSION['ultimo_acesso'])) {
    $inativo = time() - $_SESSION['ultimo_acesso'];
    if ($inativo > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
}

// Atualizar último acesso
$_SESSION['ultimo_acesso'] = time();
?>
