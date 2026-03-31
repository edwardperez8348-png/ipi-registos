<?php
session_start();
require_once __DIR__ . '/session_check.php';

$tcpdf_paths = [
    '/usr/share/php/tcpdf/tcpdf.php',
    '/usr/share/tcpdf/tcpdf.php',
    '/usr/share/php/TCPDF/tcpdf.php',
    '/usr/share/php8.3/tcpdf/tcpdf.php',
];
$tcpdf_loaded = false;
foreach ($tcpdf_paths as $path) {
    if (file_exists($path)) { require_once($path); $tcpdf_loaded = true; break; }
}
if (!$tcpdf_loaded) die('<p style="font-family:sans-serif;color:red;padding:40px;">Erro: TCPDF não encontrado.</p>');

$conn = new mysqli('localhost', 'root', '', 'registos');
if ($conn->connect_error) die('Erro BD: ' . $conn->connect_error);

$nome_cliente = trim($_GET['cliente'] ?? $_POST['cliente'] ?? '');

// =============================================
// SELECTOR HTML
// =============================================
if (empty($nome_cliente)) {
    $clientes = [];
    $res = $conn->query("SELECT nome, num_processo FROM clientes ORDER BY nome ASC");
    if ($res) while ($row = $res->fetch_assoc()) $clientes[] = $row;
    $conn->close();
    ?><!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Resumo Doc — ELI Espinho</title>
  <link rel="stylesheet" href="index.css"/>
  <style>
    .resumo-selector{width:100%;max-width:480px;background:var(--dark2);border:1px solid var(--border);border-radius:20px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,0.5);position:relative;}
    .resumo-selector::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#9b181b,#bdcb0f,#9b181b);background-size:200% 100%;animation:shimmer 3s linear infinite;}
    @keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}
    .resumo-inner{padding:40px 40px 36px;}
    .resumo-icon{width:52px;height:52px;background:linear-gradient(135deg,#9b181b,#6a0010);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;margin-bottom:20px;}
    .resumo-inner h2{font-size:1.5rem;font-weight:700;color:var(--white);margin-bottom:6px;}
    .resumo-inner p{color:var(--muted);font-size:0.85rem;margin-bottom:28px;}
    .resumo-select{width:100%;background:var(--dark3);border:1.5px solid var(--border);border-radius:10px;padding:14px 18px;color:var(--text);font-size:0.97rem;outline:none;margin-bottom:16px;appearance:none;cursor:pointer;}
    .resumo-footer{padding:16px 40px;border-top:1px solid var(--border);background:rgba(0,0,0,0.15);text-align:center;}
    .resumo-footer span{font-size:0.7rem;color:rgba(138,127,127,0.45);}
    .resumo-footer strong{color:rgba(189,203,15,0.7);font-weight:500;}
    .btn-voltar{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-size:0.82rem;text-decoration:none;margin-bottom:28px;transition:color .2s;}
    .btn-voltar:hover{color:#bdcb0f;}
  </style>
</head>
<body>
  <div style="width:100%;max-width:480px;margin-bottom:16px;padding-top:10px;">
    <a href="index.php" class="btn-voltar">&larr; Voltar ao registo</a>
  </div>
  <div class="resumo-selector">
    <div class="resumo-inner">
      <div class="resumo-icon">&#128196;</div>
      <h2>Resumo do Cliente</h2>
      <p>Selecione o cliente para gerar o documento PDF com todos os seus registos.</p>
      <?php if (empty($clientes)): ?>
        <p style="text-align:center;color:var(--muted);">Não existem clientes registados ainda.</p>
      <?php else: ?>
        <form method="GET" action="resumo.php" target="_blank">
          <select name="cliente" class="resumo-select" required>
            <option value="" disabled selected>&mdash; Selecione um cliente &mdash;</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= htmlspecialchars($c['nome']) ?>">
                <?= htmlspecialchars($c['nome']) ?><?= $c['num_processo'] ? ' — Proc. ' . $c['num_processo'] : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-submit" style="margin-top:0;">&#128196; Gerar PDF</button>
        </form>
      <?php endif; ?>
    </div>
    <div class="resumo-footer">
      <span>&copy; 2026 &middot; <strong>ELI Espinho</strong> &middot; Sistema de Registos</span>
    </div>
  </div>
</body>
</html><?php
    exit;
}

// =============================================
// BUSCAR REGISTOS
// =============================================
$stmt = $conn->prepare("
    SELECT d.*, c.nome AS nome_cliente, c.tutor, c.num_processo
    FROM dados d INNER JOIN clientes c ON d.cliente_id = c.id
    WHERE c.nome = ? ORDER BY d.data_hora ASC
");
$stmt->bind_param("s", $nome_cliente);
$stmt->execute();
$result = $stmt->get_result();
$registos = [];
while ($row = $result->fetch_assoc()) $registos[] = $row;
$stmt->close();
$conn->close();

if (empty($registos)) die(
    '<p style="font-family:sans-serif;padding:40px;">Nenhum registo encontrado para: <strong>'
    . htmlspecialchars($nome_cliente) . '</strong></p>'
);

$num_processo = $registos[0]['num_processo'] ?? '';
$responsavel  = $registos[0]['tutor'] ?? '';
$pasta_fotos  = __DIR__ . '/uploads/fotos/';
$logo_path    = __DIR__ . '/logo1.webp';

// =============================================
// CORES EXATAS DO FICHEIRO .DOC ORIGINAL
// =============================================
// Vermelho:   #9B181B
// Verde:      #BDCB0F
// Cinza campos: #E6E6E6
// Cinza cab tabela: #D9D9D9
// Bordas: preto #000000

// =============================================
// MEDIDAS (extraídas do .doc, convertidas DXA→mm)
// =============================================
$xL   = 12.7;   // margem esquerda (720 DXA)
$wTot = 184.6;  // largura total conteúdo
$wDg  = 142.0;  // col Diligências
$wDt  = 42.6;   // col Data
$wLbl = 45.2;   // col label campos identificação
$wVal = $wTot - $wLbl;
$yMax = 268.0;  // limite inferior

// =============================================
// CLASSE PDF — Header e Footer DESLIGADOS
// Tudo desenhado manualmente para controlo total
// =============================================
class PDF extends TCPDF {
    public function Header() {
        // COMPLETAMENTE VAZIO — sem nada automático
        // O conteúdo é 100% controlado no corpo do script
    }
    public function Footer() {
        $this->SetY(-19);
        // Linha separadora
        $this->SetDrawColor(160, 160, 160);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.1);
        $this->Line(12.7, $this->GetY(), 197.3, $this->GetY());
        $this->Ln(1.5);
        // ELI Espinho — vermelho
        $this->SetFont('helvetica', 'B', 7.5);
        $this->SetTextColor(155, 24, 27);
        $this->SetX(0);
        $this->Cell(0, 3.5, 'ELI Espinho', 0, 1, 'C');
        // Detalhes — verde
        $this->SetFont('helvetica', '', 6.5);
        $this->SetTextColor(189, 203, 15);
        $this->SetX(0);
        $this->Cell(0, 3, 'Morada: Rua 37, n. 700, 4500-330 Espinho   |   Email: eliespinho@gmail.pt   |   Telefone: 227 334020', 0, 1, 'C');
        // Paginação
        $this->SetFont('helvetica', '', 6);
        $this->SetTextColor(140, 140, 140);
        $this->SetXY(0, $this->GetY() - 6);
        $this->Cell(204, 4,
            'P' . "\xc3\xa1" . 'g. ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(),
            0, 0, 'R');
    }
}

// =============================================
// INSTANCIAR PDF
// setPrintHeader(false) — elimina qualquer header automático
// =============================================
$pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('ELI Espinho');
$pdf->SetAuthor('Sistema de Registos');
$pdf->SetTitle('Ficha de Registo — ' . $nome_cliente);
$pdf->SetMargins($xL, 10, $xL);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(21);
$pdf->SetAutoPageBreak(false);

// CRÍTICO: desligar o header automático completamente
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);

$pdf->AddPage();
// ==========================
// Logo centrado arriba
// ==========================
// ==========================
// Logo ancho completo (de margen a margen)
// ==========================
$pdf->SetDrawColor(255,255,255);
$pdf->SetLineWidth(0);

// Ancho disponible entre márgenes
$leftMargin = $pdf->getMargins()['left'];
$rightMargin = $pdf->getMargins()['right'];
$availableWidth = $pdf->getPageWidth() - $leftMargin - $rightMargin;

// Altura del logo
$logoHeight = 32; // mm
$logoY = 12;      // posición vertical

// Fondo blanco detrás del logo
$pdf->SetFillColor(255,255,255);
$pdf->Rect($leftMargin, 10, $availableWidth, $logoHeight, 'F');

// Insertar logo expandido
if (file_exists($logo_path)) {
    $pdf->Image(
        $logo_path,
        $leftMargin,       // X = margen izquierdo
        $logoY,            // Y fijo
        $availableWidth,   // ancho = ancho disponible
        $logoHeight,       // altura
        'WEBP',
        '',
        '',
        false,
        350
        ,
        '',
        false,
        false,
        0
    );
}
// "Sistema Nacional de Intervenção Precoce na Infância"
// Texto preto simples, ao lado do logo
// Logo webp com 26mm de altura → largura ~26mm
// Deixamos 30mm de offset para o texto começar
$pdf->SetFont('helvetica', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY($xL + 32, 21);  // Y=21 para centrar com logo de 26mm (12+13=25 ~centro)

// Posição após o bloco do logo: Y=12+26+8 = 46
$pdf->SetY(46);

// =============================================
// TÍTULO VERMELHO — sem borda alguma
// Fundo #9B181B, texto branco, negrito
// =============================================
$pdf->SetFillColor(155, 24, 27);   // #9B181B
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(155, 24, 27);   // draw = fill → sem borda visível
$pdf->SetLineWidth(0);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetX($xL);
$pdf->Cell($wTot, 10, 'Ficha de Registo /Contactos', 0, 1, 'C', true);
// border=0 → zero bordas

// =============================================
// FAIXA VERDE — colada imediatamente abaixo do vermelho
// #BDCB0F, sem gap, sem borda
// =============================================
$yGreen = $pdf->GetY(); // Y exacto logo a seguir ao vermelho
$pdf->SetFillColor(189, 203, 15);  // #BDCB0F
$pdf->SetDrawColor(189, 203, 15);
$pdf->Rect($xL, $yGreen, $wTot, 7, 'F'); // 'F' = fill only, sem borda
$pdf->SetY($yGreen + 7);

// =============================================
// ESPAÇO ENTRE FAIXA VERDE E CAMPOS
// No modelo Word são ~6mm de espaço branco
// =============================================
$pdf->Ln(6);

// =============================================
// CAMPOS DE IDENTIFICAÇÃO
// [label cinza #E6E6E6 | valor branco]
// Borda preta fina, espaço ~3mm entre cada campo
// Igual ao modelo Word
// =============================================
$hCampo = 8.0;

$drawCampo = function(string $label, string $valor) use (
    $pdf, $xL, $wLbl, $wVal, $hCampo
): void {
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);
    // Label cinza
    $pdf->SetFillColor(230, 230, 230); // #E6E6E6
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetX($xL);
    $pdf->Cell($wLbl, $hCampo, $label, 1, 0, 'L', true);
    // Valor branco
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($wVal, $hCampo, $valor, 1, 1, 'L', true);
};

$drawCampo('Nome da Crian' . "\xc3\xa7" . 'a:', $nome_cliente);
$pdf->Ln(3);
$drawCampo('Proc. n' . "\xc2\xba" . '.:', $num_processo);
$pdf->Ln(3);
$drawCampo('Respons' . "\xc3\xa1" . 'vel de Caso:', $responsavel);

// Espaço entre campos e tabela (~5mm como no Word)
$pdf->Ln(5);

// =============================================
// CABEÇALHO DA TABELA (reutilizável nas pgs seguintes)
// Cinza #D9D9D9, negrito, bordas pretas
// =============================================
$drawCabecalho = function() use ($pdf, $xL, $wDg, $wDt): void {
    $pdf->SetFillColor(217, 217, 217); // #D9D9D9
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);
    $pdf->SetX($xL);
    $pdf->Cell($wDg, 9,
        'Dilig' . "\xc3\xaa" . 'ncias/ Contactos efetuados',
        1, 0, 'C', true);
    $pdf->Cell($wDt, 9, 'Data', 1, 1, 'C', true);
};

$drawCabecalho();

// =============================================
// REGISTOS
// =============================================
foreach ($registos as $reg) {

    $texto    = trim($reg['descricao'] ?? '');
    if (!empty($reg['transcricao'])) {
        $texto .= "\n[" . trim($reg['transcricao']) . "]";
    }
    $data_fmt = date('d/m/Y', strtotime($reg['data_hora']));

    $pdf->SetFont('helvetica', '', 9);
    $altTexto = $pdf->getStringHeight($wDg - 4, $texto);
    $altLinha = max(8.0, $altTexto + 3);

    // Quebra de página
    if ($pdf->GetY() + $altLinha > $yMax) {
        $pdf->AddPage();

        // Mini cabeçalho de continuação no topo da nova página
        $pdf->SetY(12);
        if (file_exists($logo_path)) {
            $pdf->Image($logo_path, $xL, 12, 0, 18, 'WEBP', '', '', true, 96, '', false, false, 0);
        }
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($xL + 24, 17);
        $pdf->Cell($wTot - 24, 7,
            'Sistema Nacional de Interven' . "\xc3\xa7\xc3\xa3" . 'o Precoce na Inf' . "\xc3\xa2" . 'ncia',
            0, 0, 'L');
        $pdf->SetY(34);

        $drawCabecalho();
    }

    $yLinha = $pdf->GetY();

    // Célula Diligências
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($xL, $yLinha, $wDg, $altLinha, 'DF');

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($xL + 2, $yLinha + 1.5);
    $pdf->MultiCell($wDg - 4, 5, $texto, 0, 'L', false, 0);

    // Célula Data
    $pdf->SetXY($xL + $wDg, $yLinha);
    $pdf->SetFillColor(230, 230, 230); // #E6E6E6
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($wDt, $altLinha, $data_fmt, 1, 0, 'C', true);

    $pdf->SetY($yLinha + $altLinha);

    // ---- Fotos ----
    if (!empty($reg['foto'])) {
        $fotos_lista   = array_filter(array_map('trim', explode(',', $reg['foto'])));
        $fotos_validas = [];
        foreach ($fotos_lista as $fn) {
            $fp = $pasta_fotos . $fn;
            if (file_exists($fp)) $fotos_validas[] = $fp;
        }

        if (!empty($fotos_validas)) {
            $maxFotos  = min(count($fotos_validas), 5);
            $padH      = 2.5;
            $gap       = 2.0;
            $espacoImg = $wDg - ($padH * 2) - (($maxFotos - 1) * $gap);
            $fW        = floor($espacoImg / $maxFotos);
            $fH        = round($fW * 0.70);
            $altBloco  = $fH + ($padH * 2);

            if ($pdf->GetY() + $altBloco > $yMax) {
                $pdf->AddPage();
                $pdf->SetY(12);
                if (file_exists($logo_path)) {
                    $pdf->Image($logo_path, $xL, 12, 0, 18, 'WEBP', '', '', true, 96, '', false, false, 0);
                }
                $pdf->SetFont('helvetica', '', 9);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY($xL + 24, 17);
                $pdf->Cell($wTot - 24, 7,
                    'Sistema Nacional de Interven' . "\xc3\xa7\xc3\xa3" . 'o Precoce na Inf' . "\xc3\xa2" . 'ncia',
                    0, 0, 'L');
                $pdf->SetY(34);
                $drawCabecalho();
            }

            $yFoto   = $pdf->GetY();
            $xLimDir = $xL + $wDg - $padH;

            // Célula fotos
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.3);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($xL, $yFoto, $wDg, $altBloco, 'DF');

            // Célula data vazia ao lado
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Rect($xL + $wDg, $yFoto, $wDt, $altBloco, 'DF');

            // Fotos dentro da célula
            $xImg = $xL + $padH;
            $yImg = $yFoto + $padH;

            foreach (array_slice($fotos_validas, 0, $maxFotos) as $fp) {
                if ($xImg + $fW > $xLimDir) break;
                try {
                    $ext  = strtolower(pathinfo($fp, PATHINFO_EXTENSION));
                    $tipo = match($ext) {
                        'jpg', 'jpeg' => 'JPEG',
                        'png'         => 'PNG',
                        'gif'         => 'GIF',
                        'webp'        => 'WEBP',
                        default       => ''
                    };
                    if ($tipo !== '') {
                        $pdf->Image($fp, $xImg, $yImg, $fW, $fH, $tipo,
                            '', '', true, 96, '', false, false, 0);
                    }
                } catch (Exception $e) {}
                $xImg += $fW + $gap;
            }

            $pdf->SetY($yFoto + $altBloco);
        }
    }
}

// =============================================
// OUTPUT
// =============================================
$nome_fich = 'Registo_'
    . preg_replace('/[^a-zA-Z0-9_]/', '_', $nome_cliente)
    . '_' . date('Ymd_Hi') . '.pdf';

$pdf->Output($nome_fich, 'I');
?>