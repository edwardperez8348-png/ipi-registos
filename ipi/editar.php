<?php
session_start();
require_once __DIR__ . '/session_check.php';

$tutor_atual = $_SESSION['utilizador'];
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: historico.php'); exit; }

$conn = new mysqli('localhost', 'root', '', 'registos');
if ($conn->connect_error) die('Erro BD');

$stmt = $conn->prepare(
    "SELECT d.*, c.nome AS nome_cliente, c.tutor, c.num_processo, c.id AS cliente_id_real
     FROM dados d INNER JOIN clientes c ON d.cliente_id = c.id
     WHERE d.id = ? AND c.tutor = ?"
);
$stmt->bind_param("is", $id, $tutor_atual);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reg) { $conn->close(); header('Location: historico.php?erro=sem_permissao'); exit; }

$msg = ''; $msg_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_descricao    = trim($_POST['descricao']    ?? '');
    $novo_num_processo = trim($_POST['num_processo'] ?? '');

    if (empty($nova_descricao)) {
        $msg = 'O campo Descrição é obrigatório.'; $msg_tipo = 'error';
    } else {
        $pasta_fotos  = __DIR__ . '/uploads/fotos/';
        $pasta_audios = __DIR__ . '/uploads/audios/';

        // Fotos
        $fotos_atuais   = !empty($reg['foto']) ? array_filter(array_map('trim', explode(',', $reg['foto']))) : [];
        $fotos_remover  = $_POST['remover_fotos'] ?? [];
        $fotos_mantidas = array_values(array_filter($fotos_atuais, fn($f) => !in_array($f, $fotos_remover)));
        foreach ($fotos_remover as $f) { $p=$pasta_fotos.$f; if(file_exists($p)) unlink($p); }

        function comprimirImagem($origem, $destino, $maxLarg, $maxAlt, $qualidade) {
            $info = getimagesize($origem); if (!$info) return false;
            $mime = $info['mime'];
            switch ($mime) {
                case 'image/jpeg': $img=imagecreatefromjpeg($origem); break;
                case 'image/png':  $img=imagecreatefrompng($origem);  break;
                case 'image/gif':  $img=imagecreatefromgif($origem);  break;
                case 'image/webp': $img=imagecreatefromwebp($origem); break;
                default: return false;
            }
            $ratio=min($maxLarg/$info[0],$maxAlt/$info[1],1.0);
            $nL=(int)($info[0]*$ratio); $nA=(int)($info[1]*$ratio);
            $imgNova=imagecreatetruecolor($nL,$nA);
            if($mime==='image/png'||$mime==='image/gif'){imagealphablending($imgNova,false);imagesavealpha($imgNova,true);imagefilledrectangle($imgNova,0,0,$nL,$nA,imagecolorallocatealpha($imgNova,0,0,0,127));}
            imagecopyresampled($imgNova,$img,0,0,0,0,$nL,$nA,$info[0],$info[1]);
            if($mime==='image/png') imagepng($imgNova,$destino,(int)(9-($qualidade/100*9)));
            else{$destino=preg_replace('/\.(gif|webp)$/i','.jpg',$destino);imagejpeg($imgNova,$destino,$qualidade);}
            imagedestroy($img);imagedestroy($imgNova);
            return basename($destino);
        }

        $novas_fotos = [];
        if (!empty($_FILES['novas_fotos']['name'][0])) {
            $max = min(count($_FILES['novas_fotos']['name']), 12 - count($fotos_mantidas));
            for ($i=0; $i<$max; $i++) {
                if ($_FILES['novas_fotos']['error'][$i]!==UPLOAD_ERR_OK) continue;
                $ext=strtolower(pathinfo($_FILES['novas_fotos']['name'][$i],PATHINFO_EXTENSION));
                if (!in_array($ext,['jpg','jpeg','png','gif','webp'])) continue;
                $nomeTemp=uniqid('foto_'); $destExt=in_array($ext,['jpg','jpeg'])?'jpg':$ext;
                $caminho=$pasta_fotos.$nomeTemp.'.'.$destExt;
                $res=comprimirImagem($_FILES['novas_fotos']['tmp_name'][$i],$caminho,1280,1280,60);
                if($res) $novas_fotos[]=$res;
                else{$nf=$nomeTemp.'.'.$ext;move_uploaded_file($_FILES['novas_fotos']['tmp_name'][$i],$pasta_fotos.$nf);$novas_fotos[]=$nf;}
            }
        }
        $nova_foto = implode(',', array_merge($fotos_mantidas, $novas_fotos));

        // Áudio
        $novo_audio       = $reg['audio'];
        $nova_transcricao = $reg['transcricao'] ?? '';

        if (!empty($_POST['remover_audio'])) {
            if (!empty($reg['audio']) && file_exists($pasta_audios.$reg['audio'])) unlink($pasta_audios.$reg['audio']);
            $novo_audio       = '';
            $nova_transcricao = '';
        }

        // Novo áudio — mesmo modelo que guardar.php (tiny + bs 1)
        if (isset($_FILES['novo_audio']) && $_FILES['novo_audio']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['novo_audio']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['mp3','wav','ogg','m4a','aac'])) {
                if (!empty($novo_audio) && file_exists($pasta_audios.$novo_audio)) unlink($pasta_audios.$novo_audio);

                $novo_audio    = uniqid('audio_') . '.' . $ext;
                $caminho_audio = $pasta_audios . $novo_audio;
                move_uploaded_file($_FILES['novo_audio']['tmp_name'], $caminho_audio);

                // Converter para WAV
                $caminho_wav = $pasta_audios . uniqid('tmp_wav_') . '.wav';
                exec("ffmpeg -i " . escapeshellarg($caminho_audio) .
                     " -ar 16000 -ac 1 -c:a pcm_s16le " .
                     escapeshellarg($caminho_wav) . " 2>/dev/null");

                // Transcrever — tiny + bs 1 (rápido)
                if (file_exists($caminho_wav)) {
                    $whisper_bin   = '/opt/whisper.cpp/build/bin/whisper-cli';
                    $whisper_model = '/opt/whisper.cpp/models/ggml-tiny.bin';

                    $output = [];
                    exec(escapeshellarg($whisper_bin) .
                         ' -m ' . escapeshellarg($whisper_model) .
                         ' -f ' . escapeshellarg($caminho_wav) .
                         ' -l pt -t 2 -bs 1 --no-timestamps -nt 2>/dev/null', $output);

                    $linhas = array_filter($output, fn($l) => trim($l)!=='' && !preg_match('/^\[[\d:,\. \->]+\]/', $l));
                    $nova_transcricao = trim(implode(' ', $linhas));
                    unlink($caminho_wav);
                }
            }
        }

        // Atualizar dados
        $stmt_up = $conn->prepare("UPDATE dados SET descricao=?, foto=?, audio=?, transcricao=? WHERE id=?");
        $stmt_up->bind_param("ssssi", $nova_descricao, $nova_foto, $novo_audio, $nova_transcricao, $id);
        $stmt_up->execute(); $stmt_up->close();

        // Atualizar num_processo
        $stmt_proc = $conn->prepare("UPDATE clientes SET num_processo=? WHERE id=?");
        $stmt_proc->bind_param("si", $novo_num_processo, $reg['cliente_id_real']);
        $stmt_proc->execute(); $stmt_proc->close();

        // Recarregar
        $stmt2 = $conn->prepare("SELECT d.*, c.nome AS nome_cliente, c.tutor, c.num_processo, c.id AS cliente_id_real FROM dados d INNER JOIN clientes c ON d.cliente_id=c.id WHERE d.id=?");
        $stmt2->bind_param("i", $id); $stmt2->execute();
        $reg = $stmt2->get_result()->fetch_assoc(); $stmt2->close();
        $msg = 'Registo atualizado com sucesso!'; $msg_tipo = 'success';
    }
}

$conn->close();
$fotos_atuais = !empty($reg['foto']) ? array_filter(array_map('trim', explode(',', $reg['foto']))) : [];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Editar Registo — "LOCAL"</title>
  <link rel="stylesheet" href="index.css"/>
  <style>
    .fotos-editar{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:10px;margin-top:10px;}
    .foto-editar-item{position:relative;border-radius:10px;overflow:hidden;aspect-ratio:1;border:1px solid var(--border);}
    .foto-editar-item img{width:100%;height:100%;object-fit:cover;display:block;}
    .foto-editar-check{position:absolute;top:6px;right:6px;}
    .foto-editar-check input{width:18px;height:18px;accent-color:var(--danger);cursor:pointer;}
    .foto-remover-label{position:absolute;bottom:0;left:0;right:0;background:rgba(245,68,68,0.85);color:white;font-size:0.65rem;font-weight:600;text-align:center;padding:3px;opacity:0;transition:opacity 0.2s;pointer-events:none;}
    .foto-editar-item:has(input:checked) .foto-remover-label{opacity:1;}
    .foto-editar-item:has(input:checked) img{opacity:0.4;}
    .transcricao-bloco{background:rgba(69,245,168,0.05);border:1px solid rgba(69,245,168,0.2);border-radius:10px;padding:14px 18px;margin-top:12px;}
    .transcricao-label{font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:2px;color:var(--accent2);margin-bottom:8px;display:block;}
    .transcricao-texto{color:var(--text);font-size:0.9rem;line-height:1.7;font-style:italic;}
  </style>
</head>
<body>

<header>
  <div class="header-brand">
    <div class="header-emblem"><img src="logo.png" alt="logo"/></div>
    <div class="header-titles"><h1>"LOCAL"</h1><span>Editar Registo</span></div>
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
      <h2>Editar Registo #<?= $reg['id'] ?></h2>
      <p>Cliente: <strong style="color:var(--yellow)"><?= htmlspecialchars($reg['nome_cliente']) ?></strong>
        <?php if (!empty($reg['num_processo'])): ?>
          &nbsp;·&nbsp;<span style="color:var(--yellow);opacity:0.7;">📋 Proc. <?= htmlspecialchars($reg['num_processo']) ?></span>
        <?php endif; ?>
        &nbsp;·&nbsp;<?= date('d/m/Y H:i', strtotime($reg['data_hora'])) ?>
      </p>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="msg <?= $msg_tipo ?> show" style="margin-bottom:20px;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST" action="editar.php?id=<?= $id ?>" enctype="multipart/form-data">

      <div class="field">
        <label><span class="num" style="background:var(--accent2);color:#0f0f0f;">📋</span> Nº do Processo <span class="opcional">(editável)</span></label>
        <input type="text" name="num_processo" value="<?= htmlspecialchars($reg['num_processo'] ?? '') ?>" placeholder="Ex: 2024/001"/>
        <p style="font-size:0.76rem;color:var(--muted);margin-top:6px;">Alterar aqui muda o número para todos os registos deste cliente.</p>
      </div>

      <div class="divider"></div>

      <div class="field">
        <label><span class="num">1</span> Descrição <span class="obrigatorio">* obrigatório</span></label>
        <textarea name="descricao" id="descricao"><?= htmlspecialchars($reg['descricao']) ?></textarea>
      </div>

      <div class="divider"></div>

      <div class="field">
        <label><span class="num">2</span> Fotos atuais <span class="opcional">(marque para remover)</span></label>
        <?php if (empty($fotos_atuais)): ?>
          <p style="color:var(--muted);font-size:0.85rem;">Sem fotos neste registo.</p>
        <?php else: ?>
          <div class="fotos-editar">
            <?php foreach ($fotos_atuais as $foto): ?>
              <div class="foto-editar-item">
                <img src="uploads/fotos/<?= htmlspecialchars($foto) ?>" alt="foto"/>
                <div class="foto-editar-check">
                  <input type="checkbox" name="remover_fotos[]" value="<?= htmlspecialchars($foto) ?>"/>
                </div>
                <div class="foto-remover-label">Remover</div>
              </div>
            <?php endforeach; ?>
          </div>
          <p style="color:var(--muted);font-size:0.78rem;margin-top:6px;">Marque as fotos que quer remover e guarde.</p>
        <?php endif; ?>
      </div>

      <div class="divider"></div>

      <?php $espaco = 12 - count($fotos_atuais); ?>
      <?php if ($espaco > 0): ?>
      <div class="field">
        <label><span class="num">3</span> Adicionar fotos <span class="opcional">(até <?= $espaco ?> novas)</span></label>
        <div class="upload-area" onclick="document.getElementById('novas-fotos-input').click()">
          <input type="file" name="novas_fotos[]" id="novas-fotos-input" accept="image/*" multiple style="display:none" onchange="previewNovasFotos(this)"/>
          <div class="upload-icon">📷</div>
          <p>Clique para adicionar imagens</p>
        </div>
        <div id="novas-fotos-preview" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;"></div>
      </div>
      <div class="divider"></div>
      <?php endif; ?>

      <div class="field">
        <label><span class="num">4</span> Áudio</label>

        <?php if (!empty($reg['audio'])): ?>
          <div style="background:var(--dark3);border:1px solid var(--border);border-radius:10px;padding:14px 18px;margin-bottom:10px;">
            <p style="color:var(--text);font-size:0.88rem;margin-bottom:8px;">🎵 <?= htmlspecialchars($reg['audio']) ?></p>
            <audio controls src="uploads/audios/<?= htmlspecialchars($reg['audio']) ?>" style="width:100%;"></audio>

            <?php if (!empty($reg['transcricao'])): ?>
            <div class="transcricao-bloco">
              <span class="transcricao-label">🎙 Transcrição automática</span>
              <p class="transcricao-texto"><?= htmlspecialchars($reg['transcricao']) ?></p>
            </div>
            <?php endif; ?>

            <label style="display:flex;align-items:center;gap:8px;margin-top:12px;cursor:pointer;color:var(--danger);font-size:0.82rem;">
              <input type="checkbox" name="remover_audio" value="1" style="accent-color:var(--danger);width:16px;height:16px;"/>
              Remover este áudio e transcrição
            </label>
          </div>
        <?php else: ?>
          <p style="color:var(--muted);font-size:0.85rem;margin-bottom:10px;">Sem áudio neste registo.</p>
        <?php endif; ?>

        <div class="upload-area">
          <input type="file" name="novo_audio" accept="audio/*" onchange="previewNovoAudio(this)"/>
          <div class="upload-icon">🎵</div>
          <p>Clique para <?= !empty($reg['audio']) ? 'substituir' : 'adicionar' ?> áudio</p>
          <p style="font-size:0.75rem;color:var(--muted);margin-top:4px;">O áudio será transcrito automaticamente</p>
        </div>
        <p class="upload-hint">MP3, WAV, OGG, M4A</p>
        <audio id="novo-audio-preview" controls style="display:none;width:100%;margin-top:10px;"></audio>
      </div>

      <div style="display:flex;gap:12px;margin-top:8px;">
        <button type="submit" class="btn-submit" style="flex:1;">💾 Guardar Alterações</button>
        <a href="historico.php" style="flex:0 0 auto;padding:16px 20px;background:transparent;border:1px solid var(--border);border-radius:12px;color:var(--muted);font-family:'Outfit',sans-serif;font-size:0.92rem;text-decoration:none;display:flex;align-items:center;">Cancelar</a>
      </div>
    </form>
  </div>
  <div class="card-footer">
    <span>© 2026 · <strong>"LOCAL"</strong> · Sistema de Registos</span>
  </div>
</div>

<script>
function previewNovasFotos(input) {
  const preview = document.getElementById('novas-fotos-preview');
  preview.innerHTML = '';
  Array.from(input.files).forEach(file => {
    const reader = new FileReader();
    reader.onload = e => {
      const div = document.createElement('div');
      div.style.cssText = 'width:80px;height:80px;border-radius:8px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);';
      const img = document.createElement('img');
      img.src = e.target.result;
      img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
      div.appendChild(img); preview.appendChild(div);
    };
    reader.readAsDataURL(file);
  });
}
function previewNovoAudio(input) {
  const file = input.files[0]; if (!file) return;
  const preview = document.getElementById('novo-audio-preview');
  preview.src = URL.createObjectURL(file); preview.style.display = 'block';
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