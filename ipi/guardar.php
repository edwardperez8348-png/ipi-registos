<?php
session_start();
require_once __DIR__ . '/session_check.php';

$tutor_atual = $_SESSION['utilizador'];
$conn = new mysqli('localhost', 'root', '', 'registos');
if ($conn->connect_error) {
    header('Location: index.php?status=erro&msg=' . urlencode('Erro de ligação à base de dados.'));
    exit;
}

$nome         = trim($_POST['nome']         ?? '');
$descricao    = trim($_POST['descricao']    ?? '');
$num_processo = trim($_POST['num_processo'] ?? '');

if (empty($nome)) {
    header('Location: index.php?status=erro&msg=' . urlencode('O campo Nome é obrigatório.'));
    exit;
}
if (empty($descricao)) {
    header('Location: index.php?status=erro&msg=' . urlencode('O campo Descrição é obrigatório.'));
    exit;
}

// =============================================
// VERIFICAR / CRIAR CLIENTE
// =============================================
$stmt_check = $conn->prepare("SELECT id, tutor FROM clientes WHERE nome = ?");
$stmt_check->bind_param("s", $nome);
$stmt_check->execute();
$res_check = $stmt_check->get_result();

if ($res_check->num_rows > 0) {
    $row_check = $res_check->fetch_assoc();
    if ($row_check['tutor'] !== $tutor_atual) {
        $msg = urlencode('Este cliente já está atribuído ao tutor "' . $row_check['tutor'] . '".');
        header('Location: index.php?status=erro&msg=' . $msg);
        $stmt_check->close(); $conn->close(); exit;
    }
    $cliente_id = $row_check['id'];
} else {
    if (empty($num_processo)) {
        header('Location: index.php?status=erro&msg=' . urlencode('O Nº do Processo é obrigatório para novos clientes.'));
        $stmt_check->close(); $conn->close(); exit;
    }
    $stmt_ins = $conn->prepare("INSERT INTO clientes (nome, tutor, num_processo) VALUES (?, ?, ?)");
    $stmt_ins->bind_param("sss", $nome, $tutor_atual, $num_processo);
    $stmt_ins->execute();
    $cliente_id = $conn->insert_id;
    $stmt_ins->close();
}
$stmt_check->close();

// =============================================
// PASTAS
// =============================================
$pasta_fotos  = __DIR__ . '/uploads/fotos/';
$pasta_audios = __DIR__ . '/uploads/audios/';
foreach ([$pasta_fotos, $pasta_audios] as $pasta) {
    if (!is_dir($pasta)) mkdir($pasta, 0755, true);
}

// =============================================
// COMPRESSÃO IMAGEM
// =============================================
function comprimirImagem($origem, $destino, $maxLarg, $maxAlt, $qualidade) {
    $info = getimagesize($origem); if (!$info) return false;
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $img = imagecreatefromjpeg($origem); break;
        case 'image/png':  $img = imagecreatefrompng($origem);  break;
        case 'image/gif':  $img = imagecreatefromgif($origem);  break;
        case 'image/webp': $img = imagecreatefromwebp($origem); break;
        default: return false;
    }
    $ratio = min($maxLarg/$info[0], $maxAlt/$info[1], 1.0);
    $nL = (int)($info[0]*$ratio); $nA = (int)($info[1]*$ratio);
    $imgNova = imagecreatetruecolor($nL, $nA);
    if ($mime==='image/png'||$mime==='image/gif') {
        imagealphablending($imgNova,false); imagesavealpha($imgNova,true);
        imagefilledrectangle($imgNova,0,0,$nL,$nA,imagecolorallocatealpha($imgNova,0,0,0,127));
    }
    imagecopyresampled($imgNova,$img,0,0,0,0,$nL,$nA,$info[0],$info[1]);
    if ($mime==='image/png') imagepng($imgNova,$destino,(int)(9-($qualidade/100*9)));
    else { $destino=preg_replace('/\.(gif|webp)$/i','.jpg',$destino); imagejpeg($imgNova,$destino,$qualidade); }
    imagedestroy($img); imagedestroy($imgNova);
    return basename($destino);
}

// =============================================
// UPLOAD FOTOS
// =============================================
$nomes_fotos = [];
if (!empty($_FILES['fotos']['name'][0])) {
    $total_f = min(count($_FILES['fotos']['name']), 12);
    for ($i = 0; $i < $total_f; $i++) {
        if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($_FILES['fotos']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            header('Location: index.php?status=erro&msg=' . urlencode('Formato de imagem não permitido.'));
            exit;
        }
        $nomeTemp = uniqid('foto_');
        $destExt  = in_array($ext, ['jpg','jpeg']) ? 'jpg' : $ext;
        $caminho  = $pasta_fotos . $nomeTemp . '.' . $destExt;
        $res = comprimirImagem($_FILES['fotos']['tmp_name'][$i], $caminho, 1280, 1280, 60);
        if ($res) $nomes_fotos[] = $res;
        else { $nf=$nomeTemp.'.'.$ext; move_uploaded_file($_FILES['fotos']['tmp_name'][$i],$pasta_fotos.$nf); $nomes_fotos[]=$nf; }
    }
}
$nome_foto = implode(',', $nomes_fotos);

// =============================================
// UPLOAD ÁUDIO + TRANSCRIÇÃO COM WHISPER
// =============================================
$nome_audio  = '';
$transcricao = '';

if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['mp3','wav','ogg','m4a','aac'])) {
        header('Location: index.php?status=erro&msg=' . urlencode('Formato de áudio não permitido.'));
        exit;
    }

    $nome_audio = uniqid('audio_') . '.' . $ext;
    $caminho_audio = $pasta_audios . $nome_audio;
    move_uploaded_file($_FILES['audio']['tmp_name'], $caminho_audio);

    // Converter para WAV se necessário (whisper precisa de WAV 16kHz)
    $caminho_wav = $pasta_audios . uniqid('tmp_wav_') . '.wav';
    $ffmpeg_cmd = "ffmpeg -i " . escapeshellarg($caminho_audio) .
                  " -ar 16000 -ac 1 -c:a pcm_s16le " .
                  escapeshellarg($caminho_wav) . " 2>/dev/null";
    exec($ffmpeg_cmd);

    // Transcrever com Whisper.cpp
    if (file_exists($caminho_wav)) {
        $whisper_bin   = '/opt/whisper.cpp/build/bin/whisper-cli';
        $whisper_model = '/opt/whisper.cpp/models/ggml-tiny.bin';

        $whisper_cmd = escapeshellarg($whisper_bin) .
                       ' -m ' . escapeshellarg($whisper_model) .
                       ' -f ' . escapeshellarg($caminho_wav) .
                       ' -l pt' .
                    ' -t 2' .
                       ' -bs 1' .
                       ' --no-timestamps' .
                       ' -nt' .
                       ' 2>/dev/null';

        $output = [];
        exec($whisper_cmd, $output);

        // Limpar output — remover linhas vazias e timestamps residuais
        $linhas = array_filter($output, fn($l) => trim($l) !== '' && !preg_match('/^\[[\d:,\. \->]+\]/', $l));
        $transcricao = trim(implode(' ', $linhas));

        // Apagar WAV temporário
        unlink($caminho_wav);
    }
}

// =============================================
// GUARDAR NA BD
// =============================================
$stmt = $conn->prepare("INSERT INTO dados (cliente_id, descricao, foto, audio, transcricao) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issss", $cliente_id, $descricao, $nome_foto, $nome_audio, $transcricao);

if ($stmt->execute()) {
    header('Location: index.php?status=ok');
} else {
    header('Location: index.php?status=erro&msg=' . urlencode('Erro ao guardar: ' . $stmt->error));
}
$stmt->close();
$conn->close();
?>