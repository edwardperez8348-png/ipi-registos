<?php
session_start();

// Logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['utilizador'])) {
    header('Location: index.php');
    exit;
}

// =============================================
// CONFIGURAÇÃO LDAP
// =============================================
// =============================================
// CONFIGURAÇÃO LDAP
// =============================================
$ldap_servidor = 'ldaps://SEU_SERVIDOR_LDAP';
$ldap_porta    = 636;
$ldap_base_dn  = 'DC=SEU_DOMINIO,DC=local';
$ldap_svc_user = 'utilizador@SEU_DOMINIO.local';
$ldap_svc_pass = 'SUA_PASSWORD';

$erro = '';

// =============================================
// PROCESSAR LOGIN
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $utilizador = trim($_POST['utilizador'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($utilizador) || empty($password)) {
        $erro = 'Por favor preencha todos os campos.';
    } else {
        putenv('LDAPTLS_REQCERT=never');
        ldap_set_option(NULL, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

        $conn = @ldap_connect($ldap_servidor, $ldap_porta);

        if (!$conn) {
            $erro = 'Não foi possível ligar ao servidor LDAP. Contacte o administrador.';
        } else {
            ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($conn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

            $bind_svc = @ldap_bind($conn, $ldap_svc_user, $ldap_svc_pass);

            if (!$bind_svc) {
                $erro = 'Erro de configuração LDAP. Contacte o administrador.';
            } else {
                $filtro  = '(sAMAccountName=' . ldap_escape($utilizador, '', LDAP_ESCAPE_FILTER) . ')';
                $result  = @ldap_search($conn, $ldap_base_dn, $filtro, ['dn', 'cn', 'displayName']);
                $entries = $result ? @ldap_get_entries($conn, $result) : ['count' => 0];

                if ($entries['count'] === 0) {
                    $erro = 'Utilizador não encontrado no sistema.';
                } else {
                    $user_dn   = $entries[0]['dn'];
                    $bind_user = @ldap_bind($conn, $user_dn, $password);

                    if ($bind_user) {
                        $_SESSION['utilizador']    = $utilizador;
                        $_SESSION['nome_completo'] = $entries[0]['displayname'][0]
                            ?? $entries[0]['cn'][0]
                            ?? $utilizador;
                        $_SESSION['ultimo_acesso'] = time();

                        @ldap_unbind($conn);
                        header('Location: index.php');
                        exit;
                    } else {
                        $erro = 'Utilizador ou password incorretos.';
                    }
                }
            }
            @ldap_unbind($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login </title>
  <link rel="stylesheet" href="login.css"/>
</head>
<body>

<div class="brand-panel">
  <div class="brand-emblem"><img src="logo.png" alt="logo"/></div>
  <h2>SISTEMA DE REGISTO <em></em></h2>
  <div class="brand-line"></div>
  <div class="brand-dots">
    <span></span><span></span><span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span><span></span><span></span>
  </div>
</div>

<div class="login-box">
  <div class="login-inner">
    <div class="login-logo">
      <div class="logo-icon"><img src="logo.png" alt="logo"/></div>
      <h1>Login</h1>
    </div>

    <form id="form-login" action="login.php" method="POST">
      <div class="field">
        <label for="utilizador">Utilizador</label>
        <input type="text" name="utilizador" id="utilizador"
          placeholder=""
          value="<?= htmlspecialchars($_POST['utilizador'] ?? '') ?>"
          autocomplete="username" autofocus/>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div class="password-wrapper">
          <input type="password" name="password" id="password"
            placeholder="••••••••" autocomplete="current-password"/>
          <button type="button" class="toggle-pass" onclick="togglePassword()" title="Mostrar/ocultar password">👁</button>
        </div>
      </div>

      <?php if (isset($_GET['timeout'])): ?>
        <div class="msg-erro">⏱ Sessão expirada por inatividade. Por favor faça login novamente.</div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="msg-erro"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>

      <button type="submit" class="btn-login">Entrar</button>
    </form>

    <div class="ldap-badge">
    </div>
  </div>
  <div class="login-footer">
    <span>© 2026 · <strong>"LOCAL"</strong> · Sistema de registo </span>
  </div>
</div>

<script src="login.script.js"></script>
</body>
</html>