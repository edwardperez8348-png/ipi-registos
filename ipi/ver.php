<?php
session_start();
require_once __DIR__ . '/session_check.php';

$tutor_atual = $_SESSION['utilizador'];
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: historico.php'); exit; }

$conn = new mysqli('localhost', 'root', '', 'registos');
if ($conn->connect_error) die('Erro BD');

$stmt = $conn->prepare(
    "SELECT d.*, c.nome AS nome_cliente, c.tutor, c.num_processo
     FROM dados d INNER JOIN clientes c ON d.cliente_id = c.id WHERE d.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$reg) { header('Location: historico.php'); exit; }

$fotos = !empty($reg['foto']) ? array_filter(array_map('trim', explode(',', $reg['foto']))) : [];
$e_meu = ($reg['tutor'] === $tutor_atual);

if ($e_meu) { header('Location: editar.php?id=' . $id); exit; }
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Ver Registo — Cerciespinho</title>
  <link rel="stylesheet" href="index.css"/>
  <style>
    .ver-bloco{background:var(--dark3);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px;}
    .ver-label{font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:2px;color:var(--red-light);margin-bottom:10px;display:block;}
    .ver-texto{color:var(--text);font-size:0.95rem;line-height:1.7;white-space:pre-wrap;}
    .ver-fotos{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;}
    .ver-fotos img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px;border:1px solid var(--border);}
    .readonly-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;padding:6px 12px;font-size:0.78rem;color:var(--muted);margin-bottom:20px;}
    .processo-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(245,200,0,0.08);border:1px solid rgba(245,200,0,0.2);border-radius:8px;padding:6px 12px;font-size:0.82rem;color:var(--yellow);margin-bottom:20px;margin-left:8px;}
    .transcricao-bloco{background:rgba(69,245,168,0.05);border:1px solid rgba(69,245,168,0.2);border-radius:10px;padding:14px 18px;margin-top:12px;}
    .transcricao-label{font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:2px;color:var(--accent2);margin-bottom:8px;display:block;}
    .transcricao-texto{color:var(--text);font-size:0.9rem;line-height:1.7;font-style:italic;}
  </style>
</head>
<body>

<header>
  <div class="header-brand">
    <div class="header-emblem"><img src="logo.png" alt="logo"/></div>
    <div class="header-titles"><h1>"LOCAL"</h1><span>Ver Registo</span></div>
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
  <a href="index.php" class="nav-btn"><span class="nav-btn-icon">✏️</span><span>Novo Registo</span></a>
  <a href="historico.php" class="nav-btn"><span class="nav-btn-icon">📋</span><span>Histórico</span></a>
  <a href="resumo.php" class="nav-btn"><span class="nav-btn-icon">📄</span><span>Resumo Doc</span></a>
</div>

<div class="card">
  <div class="card-inner">
    <div class="card-header">
      <h2><?= htmlspecialchars($reg['nome_cliente']) ?></h2>
      <p>Registo #<?= $reg['id'] ?> · <?= date('d/m/Y H:i', strtotime($reg['data_hora'])) ?></p>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:0;margin-bottom:20px;">
      <div class="readonly-badge" style="margin-bottom:0;">
        👁 Modo leitura — pertence a <strong style="color:var(--text);margin-left:4px;"><?= htmlspecialchars($reg['tutor'] ?? '—') ?></strong>
      </div>
      <?php if (!empty($reg['num_processo'])): ?>
      <div class="processo-badge">
        📋 Nº Processo: <strong><?= htmlspecialchars($reg['num_processo']) ?></strong>
      </div>
      <?php endif; ?>
    </div>

    <!-- Descrição -->
    <div class="ver-bloco">
      <span class="ver-label">Descrição</span>
      <p class="ver-texto"><?= htmlspecialchars($reg['descricao']) ?></p>
    </div>

    <!-- Fotos -->
    <?php if (!empty($fotos)): ?>
    <div class="ver-bloco">
      <span class="ver-label">Fotos (<?= count($fotos) ?>)</span>
      <div class="ver-fotos">
        <?php foreach ($fotos as $foto): ?>
          <img src="uploads/fotos/<?= htmlspecialchars($foto) ?>" alt="foto"
               onclick="abrirFoto(this.src)"
               style="cursor:zoom-in;"/>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Áudio + Transcrição -->
    <?php if (!empty($reg['audio'])): ?>
    <div class="ver-bloco">
      <span class="ver-label">Áudio</span>
      <audio controls src="uploads/audios/<?= htmlspecialchars($reg['audio']) ?>" style="width:100%;"></audio>

      <?php if (!empty($reg['transcricao'])): ?>
      <div class="transcricao-bloco">
        <span class="transcricao-label">🎙 Transcrição automática</span>
        <p class="transcricao-texto"><?= htmlspecialchars($reg['transcricao']) ?></p>
      </div>
      <?php else: ?>
      <p style="color:var(--muted);font-size:0.8rem;margin-top:10px;font-style:italic;">Sem transcrição disponível.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <a href="historico.php" style="display:inline-flex;align-items:center;gap:8px;color:var(--muted);font-size:0.85rem;text-decoration:none;margin-top:8px;transition:color 0.2s;"
       onmouseover="this.style.color='var(--yellow)'" onmouseout="this.style.color='var(--muted)'">
      ← Voltar ao Histórico
    </a>
  </div>
  <div class="card-footer">
    <span>© 2026 · <strong>"LOCAL"</strong> · Sistema de Registos</span>
  </div>
</div>

<script>
function abrirFoto(src) {
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.92);display:flex;align-items:center;justify-content:center;cursor:zoom-out;';
  const img = document.createElement('img');
  img.src = src;
  img.style.cssText = 'max-width:95vw;max-height:92vh;object-fit:contain;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,0.6);';
  const btn = document.createElement('button');
  btn.textContent = '✕';
  btn.style.cssText = 'position:fixed;top:20px;right:24px;background:rgba(255,255,255,0.15);border:none;color:white;font-size:1.4rem;width:44px;height:44px;border-radius:50%;cursor:pointer;z-index:10000;transition:background 0.2s;';
  btn.onmouseover = () => btn.style.background = 'rgba(255,255,255,0.3)';
  btn.onmouseout  = () => btn.style.background = 'rgba(255,255,255,0.15)';
  btn.onclick = (e) => { e.stopPropagation(); document.body.removeChild(overlay); };
  overlay.onclick = () => document.body.removeChild(overlay);
  img.onclick = (e) => e.stopPropagation();
  overlay.appendChild(img);
  overlay.appendChild(btn);
  document.body.appendChild(overlay);
}

let timerInatividade = setTimeout(() => { window.location.href = 'login.php?timeout=1'; }, 600000);
['mousemove','keydown','click','scroll','touchstart'].forEach(evt => {
    document.addEventListener(evt, () => {
        clearTimeout(timerInatividade);
        timerInatividade = setTimeout(() => { window.location.href = 'login.php?timeout=1'; }, 600000);
    });
});
</script>
</body>
</html>