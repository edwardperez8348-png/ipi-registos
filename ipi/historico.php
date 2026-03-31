<?php
session_start();
require_once __DIR__ . '/session_check.php';

$tutor_atual = $_SESSION['utilizador'];
$conn = new mysqli('localhost', 'root', '', 'registos');
if ($conn->connect_error) die('Erro BD');

$filtro_tutor    = trim($_GET['tutor']    ?? '');
$filtro_cliente  = trim($_GET['cliente']  ?? '');
$filtro_data     = trim($_GET['data']     ?? '');
$filtro_processo = trim($_GET['processo'] ?? '');

$tutores = [];
$res = $conn->query("SELECT DISTINCT tutor FROM clientes ORDER BY tutor ASC");
if ($res) while ($row = $res->fetch_assoc()) $tutores[] = $row['tutor'];

$clientes_todos = [];
$res = $conn->query("SELECT id, nome, num_processo FROM clientes ORDER BY nome ASC");
if ($res) while ($row = $res->fetch_assoc()) $clientes_todos[] = $row;

$where = []; $params = []; $types = '';
if (!empty($filtro_tutor))    { $where[] = 'c.tutor = ?';           $params[] = $filtro_tutor;             $types .= 's'; }
if (!empty($filtro_cliente))  { $where[] = 'c.nome = ?';            $params[] = $filtro_cliente;           $types .= 's'; }
if (!empty($filtro_data))     { $where[] = 'DATE(d.data_hora) = ?'; $params[] = $filtro_data;              $types .= 's'; }
if (!empty($filtro_processo)) { $where[] = 'c.num_processo LIKE ?'; $params[] = '%'.$filtro_processo.'%'; $types .= 's'; }

$sql = "SELECT d.id, d.cliente_id, d.descricao, d.foto, d.audio, d.data_hora,
               c.nome AS nome_cliente, c.tutor, c.num_processo
        FROM dados d INNER JOIN clientes c ON d.cliente_id = c.id";
if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY d.data_hora DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$registos = [];
while ($row = $result->fetch_assoc()) $registos[] = $row;
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Histórico — "LOCAL"</title>
  <link rel="stylesheet" href="index.css"/>
  <style>
    .hist-filtros{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
    .hist-filtros select,.hist-filtros input{background:var(--dark3);border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;color:var(--text);font-family:'Outfit',sans-serif;font-size:0.88rem;outline:none;flex:1;min-width:130px;transition:border-color 0.2s;appearance:none;}
    .hist-filtros select:focus,.hist-filtros input:focus{border-color:var(--yellow);}
    .btn-filtrar{background:var(--red);color:white;border:none;border-radius:10px;padding:10px 20px;font-family:'Outfit',sans-serif;font-size:0.88rem;font-weight:600;cursor:pointer;transition:opacity 0.2s;}
    .btn-filtrar:hover{opacity:0.85;}
    .btn-limpar{background:transparent;color:var(--muted);border:1px solid var(--border);border-radius:10px;padding:10px 16px;font-family:'Outfit',sans-serif;font-size:0.85rem;cursor:pointer;text-decoration:none;display:flex;align-items:center;transition:color 0.2s,border-color 0.2s;}
    .btn-limpar:hover{color:var(--text);border-color:var(--muted);}
    .hist-tabela-wrap{overflow-x:auto;border-radius:12px;border:1px solid var(--border);}
    .hist-tabela{width:100%;border-collapse:collapse;font-size:0.88rem;}
    .hist-tabela thead tr{background:var(--dark3);}
    .hist-tabela thead th{padding:12px 14px;text-align:left;font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:1.5px;color:var(--muted);white-space:nowrap;}
    .hist-tabela tbody tr{border-top:1px solid var(--border);transition:background 0.15s;}
    .hist-tabela tbody tr:hover{background:rgba(255,255,255,0.02);}
    .hist-tabela tbody td{padding:12px 14px;vertical-align:middle;color:var(--text);}
    .td-nome{font-weight:600;color:var(--white);}
    .td-processo{font-size:0.8rem;color:var(--yellow);font-weight:500;}
    .td-tutor{font-size:0.8rem;color:var(--muted);}
    .td-tutor-proprio{color:var(--yellow)!important;font-weight:500;}
    .td-data{color:var(--muted);font-size:0.82rem;white-space:nowrap;}
    .td-anexos{display:flex;gap:6px;flex-wrap:wrap;}
    .badge-foto,.badge-audio{font-size:0.7rem;padding:2px 8px;border-radius:6px;font-weight:500;}
    .badge-foto{background:rgba(245,200,0,0.12);color:var(--yellow);border:1px solid rgba(245,200,0,0.2);}
    .badge-audio{background:rgba(192,19,26,0.12);color:var(--red-light);border:1px solid rgba(192,19,26,0.2);}
    .btn-editar{background:rgba(245,200,0,0.1);border:1px solid rgba(245,200,0,0.25);color:var(--yellow);padding:5px 10px;border-radius:8px;font-size:0.78rem;font-weight:600;cursor:pointer;text-decoration:none;transition:background 0.2s;white-space:nowrap;font-family:'Outfit',sans-serif;}
    .btn-editar:hover{background:rgba(245,200,0,0.2);}
    .btn-ver{background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--muted);padding:5px 10px;border-radius:8px;font-size:0.78rem;font-weight:500;cursor:pointer;text-decoration:none;transition:background 0.2s,color 0.2s;white-space:nowrap;font-family:'Outfit',sans-serif;}
    .btn-ver:hover{background:rgba(255,255,255,0.08);color:var(--text);}
    .acoes{display:flex;gap:6px;}
    .hist-vazio{text-align:center;padding:48px 20px;color:var(--muted);font-size:0.9rem;}
    .hist-total{font-size:0.8rem;color:var(--muted);margin-bottom:12px;}
    .hist-total strong{color:var(--yellow);}
  </style>
</head>
<body>

<header>
  <div class="header-brand">
    <div class="header-emblem"><img src="logo.png" alt="logo"/></div>
    <div class="header-titles"><h1>"LOCAL"</h1><span>Histórico de Registos</span></div>
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

<div class="nav-principal" style="width:100%;max-width:900px;display:flex;gap:10px;margin-bottom:24px;position:relative;z-index:1;">
  <a href="index.php" class="nav-btn"><span class="nav-btn-icon">✏️</span><span>Novo Registo</span></a>
  <a href="historico.php" class="nav-btn nav-btn-active"><span class="nav-btn-icon">📋</span><span>Histórico</span></a>
  <a href="resumo.php" class="nav-btn"><span class="nav-btn-icon">📄</span><span>Resumo Doc</span></a>
</div>

<div class="card" style="max-width:900px;">
  <div class="card-inner">
    <div class="card-header">
      <h2>Histórico de Registos</h2>
      <p>Todos os registos de todos os tutores.</p>
    </div>

    <form method="GET" action="historico.php">
      <div class="hist-filtros">
        <select name="tutor">
          <option value="">Todos os tutores</option>
          <?php foreach ($tutores as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= $filtro_tutor===$t?'selected':'' ?>>
              <?= htmlspecialchars($t) ?><?= $t===$tutor_atual?' (eu)':'' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="cliente">
          <option value="">Todos os clientes</option>
          <?php foreach ($clientes_todos as $c): ?>
            <option value="<?= htmlspecialchars($c['nome']) ?>" <?= $filtro_cliente===$c['nome']?'selected':'' ?>>
              <?= htmlspecialchars($c['nome']) ?><?= $c['num_processo']?' — '.$c['num_processo']:'' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="processo" placeholder="Nº Processo..." value="<?= htmlspecialchars($filtro_processo) ?>"/>
        <input type="date" name="data" value="<?= htmlspecialchars($filtro_data) ?>"/>
        <button type="submit" class="btn-filtrar">Filtrar</button>
        <a href="historico.php" class="btn-limpar">Limpar</a>
      </div>
    </form>

    <p class="hist-total">Mostrando <strong><?= count($registos) ?></strong> registo<?= count($registos)!==1?'s':'' ?></p>

    <div class="hist-tabela-wrap">
      <?php if (empty($registos)): ?>
        <div class="hist-vazio">Nenhum registo encontrado.</div>
      <?php else: ?>
        <table class="hist-tabela">
          <thead>
            <tr>
              <th>#</th><th>Cliente</th><th>Nº Processo</th><th>Tutor</th>
              <th>Anexos</th><th>Data</th><th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($registos as $reg): ?>
              <?php
                $e_meu  = ($reg['tutor'] === $tutor_atual);
                $nFotos = 0;
                if (!empty($reg['foto'])) {
                    $fl = array_filter(array_map('trim', explode(',', $reg['foto'])));
                    $nFotos = count($fl);
                }
              ?>
              <tr>
                <td style="color:var(--muted);font-size:0.8rem;"><?= $reg['id'] ?></td>
                <td class="td-nome"><?= htmlspecialchars($reg['nome_cliente']) ?></td>
                <td class="td-processo"><?= htmlspecialchars($reg['num_processo'] ?? '—') ?></td>
                <td class="td-tutor <?= $e_meu?'td-tutor-proprio':'' ?>">
                  <?= htmlspecialchars($reg['tutor'] ?? '—') ?><?= $e_meu?' ★':'' ?>
                </td>
                <td>
                  <div class="td-anexos">
                    <?php if ($nFotos > 0): ?><span class="badge-foto">📷 <?= $nFotos ?></span><?php endif; ?>
                    <?php if (!empty($reg['audio'])): ?><span class="badge-audio">🎵</span><?php endif; ?>
                    <?php if ($nFotos===0 && empty($reg['audio'])): ?><span style="color:var(--muted);font-size:0.78rem;">—</span><?php endif; ?>
                  </div>
                </td>
                <td class="td-data"><?= date('d/m/Y H:i', strtotime($reg['data_hora'])) ?></td>
                <td>
                  <div class="acoes">
                    <!-- Ver — disponível para TODOS os registos -->
                    <a href="ver.php?id=<?= $reg['id'] ?>" class="btn-ver">👁 Ver</a>
                    <!-- Editar — só para os meus -->
                    <?php if ($e_meu): ?>
                      <a href="editar.php?id=<?= $reg['id'] ?>" class="btn-editar">✏️ Editar</a>
                    <?php endif; ?>
                  </div> 
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-footer">
    <span>© 2026 · <strong>"LOCAL"</strong> · Sistema de Registos</span>
  </div>
</div>

<script>
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