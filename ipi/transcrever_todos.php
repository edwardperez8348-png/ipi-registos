<?php
$conn = new mysqli('localhost', 'root', '', 'registos');
$pasta_audios  = __DIR__ . '/uploads/audios/';
$whisper_bin   = '/opt/whisper.cpp/build/bin/whisper-cli';
$whisper_model = '/opt/whisper.cpp/models/ggml-small.bin';

$res = $conn->query("SELECT id, audio FROM dados WHERE audio != '' AND (transcricao IS NULL OR transcricao = '')");

while ($row = $res->fetch_assoc()) {
    $caminho_audio = $pasta_audios . $row['audio'];
    if (!file_exists($caminho_audio)) { echo "❌ Ficheiro não encontrado: {$row['audio']}<br>"; continue; }

    $caminho_wav = $pasta_audios . 'tmp_' . $row['id'] . '.wav';
    exec("ffmpeg -i " . escapeshellarg($caminho_audio) . " -ar 16000 -ac 1 -c:a pcm_s16le " . escapeshellarg($caminho_wav) . " 2>/dev/null");

    if (!file_exists($caminho_wav)) { echo "❌ Erro ao converter: {$row['audio']}<br>"; continue; }

    $output = [];
    exec(escapeshellarg($whisper_bin) . ' -m ' . escapeshellarg($whisper_model) . ' -f ' . escapeshellarg($caminho_wav) . ' -l pt --no-timestamps -nt 2>/dev/null', $output);

    $linhas = array_filter($output, fn($l) => trim($l)!=='' && !preg_match('/^\[[\d:,\. \->]+\]/', $l));
    $transcricao = trim(implode(' ', $linhas));

    unlink($caminho_wav);

    $stmt = $conn->prepare("UPDATE dados SET transcricao=? WHERE id=?");
    $stmt->bind_param("si", $transcricao, $row['id']);
    $stmt->execute();
    $stmt->close();

    echo "✅ Registo #{$row['id']} transcrito: " . mb_substr($transcricao, 0, 80) . "...<br>";
    flush();
}
echo "<br><strong>Concluído!</strong>";
$conn->close();
?>
