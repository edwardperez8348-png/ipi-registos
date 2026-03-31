<?php
session_start();
require_once __DIR__ . '/session_check.php';

$tutor_atual = $_SESSION['utilizador'];

$clientes_proprios = [];
$conn = @new mysqli('localhost', 'root', '', 'registos');
if ($conn && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT nome, num_processo FROM clientes WHERE tutor = ? ORDER BY nome ASC");
    $stmt->bind_param("s", $tutor_atual);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $clientes_proprios[] = $row;
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sistema de Registo</title>
  <link rel="stylesheet" href="index.css"/>
</head>
<body>

<header>
  <div class="header-brand">
    <div class="header-emblem"><img src="logo.png" alt="logo"/></div>
    <div class="header-titles">
      <h1>"local "</h1>
      <span>Sistema de Registo</span>
    </div>
  </div>
  <div class="header-user">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($tutor_atual, 0, 2)) ?></div>
      <span><?= htmlspecialchars($tutor_atual) ?></span>
    </div>
    <a href="login.php?logout=1" class="btn-sair">Sair</a>
  </div>
</header>

<div class="header-line"></div>

<div class="nav-principal" style="width:100%;max-width:680px;display:flex;gap:10px;margin-bottom:24px;position:relative;z-index:1;">
  <a href="index.php" class="nav-btn nav-btn-active">
    <span class="nav-btn-icon">✏️</span><span>Novo Registo</span>
  </a>
  <a href="historico.php" class="nav-btn">
    <span class="nav-btn-icon">📋</span><span>Histórico</span>
  </a>
  <a href="resumo.php" class="nav-btn">
    <span class="nav-btn-icon">📄</span><span>Resumo Doc</span>
  </a>
</div>

<div class="card">
  <div class="card-inner">
    <div class="card-header">
      <h2>Novo Registo</h2>
      <p>Os clientes listados são os seus. Novos clientes ficam automaticamente atribuídos a si.</p>
    </div>

    <form id="form-registo" action="guardar.php" method="POST" enctype="multipart/form-data" onsubmit="return validarFormulario()">

      <div class="field">
        <label>
          <span class="num">1</span> Cliente
          <span class="obrigatorio">* obrigatório</span>
        </label>
        <div class="autocomplete-wrapper">
          <input type="text" id="cliente_busca" placeholder="Escreva o nome do cliente..."
            autocomplete="off" oninput="filtrarClientes(this.value)" onkeydown="navegarLista(event)"/>
          <div class="sugestoes-lista" id="sugestoes-lista"></div>
          <div class="cliente-selecionado-badge" id="cliente-badge">
            <span id="badge-nome"></span>
            <button type="button" onclick="limparCliente()" title="Remover">✕</button>
          </div>
          <input type="hidden" name="nome" id="nome_final"/>
        </div>
      </div>

      <div class="field" id="campo-processo" style="display:none;">
        <label>
          <span class="num" style="background:var(--accent2);color:#0f0f0f;">✦</span> Nº do Processo
          <span class="obrigatorio">* obrigatório para novo cliente</span>
        </label>
        <input type="text" name="num_processo" id="num_processo" placeholder="Ex: 2024/001"/>
        <p style="font-size:0.76rem;color:var(--muted);margin-top:6px;">Este número ficará associado ao cliente permanentemente.</p>
      </div>

      <div id="info-processo" style="display:none;margin-bottom:20px;">
        <div style="background:rgba(69,245,168,0.06);border:1px solid rgba(69,245,168,0.2);border-radius:10px;padding:10px 16px;font-size:0.85rem;color:var(--accent2);">
          📋 Nº Processo: <strong id="info-processo-valor">—</strong>
        </div>
      </div>

      <div class="divider"></div>

      <div class="field">
        <label>
          <span class="num">2</span> Descrição
          <span class="obrigatorio">* obrigatório</span>
        </label>
        <textarea name="descricao" id="descricao" placeholder="Insira o texto ou descrição..."></textarea>
      </div>

      <div class="divider"></div>

      <div class="field">
        <label>
          <span class="num">3</span> Fotos
          <span class="opcional">(opcional — até 12 imagens)</span>
        </label>
        <div class="upload-area" id="upload-area-foto" onclick="document.getElementById('foto-input').click()">
          <input type="file" name="fotos[]" id="foto-input" accept="image/*" multiple
            style="display:none" onchange="adicionarFotos(this)"/>
          <div class="upload-icon">📷</div>
          <p>Clique para selecionar imagens</p>
          <p style="font-size:0.78rem;color:var(--muted);margin-top:4px;">Pode selecionar várias de uma vez</p>
        </div>
        <p class="upload-hint">Formatos aceites: JPG, PNG, GIF, WEBP · Máximo 12 fotos</p>
        <div class="fotos-galeria" id="fotos-galeria"></div>
        <p class="fotos-contador" id="fotos-contador" style="display:none"></p>
      </div>

      <div class="divider"></div>

      <div class="field">
        <label>
          <span class="num">4</span> Áudio
          <span class="opcional">(opcional)</span>
        </label>
        <div class="upload-area">
          <input type="file" name="audio" id="audio-input" accept="audio/*" onchange="previewAudio(this)"/>
          <div class="upload-icon">🎵</div>
          <p>Clique para selecionar um ficheiro de áudio</p>
          <p class="preview-name" id="audio-nome"></p>
        </div>
        <p class="upload-hint">Formatos aceites: MP3, WAV, OGG, M4A</p>
        <button type="button" class="btn-quitar-file" id="quitar-audio" onclick="quitarAudio()" style="display:none">✕ Remover áudio</button>
        <audio id="audio-preview" controls></audio>
      </div>

      <button type="submit" class="btn-submit">Guardar Registo</button>
    </form>

    <div class="msg" id="msg"></div>
  </div>
  <div class="card-footer">
    <span>© 2026 · <strong>"LOCAL"</strong> · Sistema de Registo </span>
  </div>
</div>

<script>
  const clientesExistentes = <?= json_encode(array_map(fn($c) => $c['nome'], $clientes_proprios)) ?>;
  const clientesProcessos  = <?= json_encode(array_column($clientes_proprios, 'num_processo', 'nome')) ?>;
</script>
<script src="index.script.js"></script>
<script>
// Logout automático após 10 minutos de inatividade
let timerInatividade = setTimeout(() => {
    window.location.href = 'login.php?timeout=1';
}, 600000);

['mousemove','keydown','click','scroll','touchstart'].forEach(evt => {
    document.addEventListener(evt, () => {
        clearTimeout(timerInatividade);
        timerInatividade = setTimeout(() => {
            window.location.href = 'login.php?timeout=1';
        }, 600000);
    });
});
</script>
</body>
</html>