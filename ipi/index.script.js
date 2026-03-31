// =============================================
// index.script.js
// =============================================

let indiceSelecionado = -1;
let fotosFiles = [];
const MAX_FOTOS = 12;
let clienteIsNovo = false;

const soloLetras = /^[a-zA-ZÀ-ÿ\s]*$/;

// =============================================
// AUTOCOMPLETE — CLIENTES
// =============================================

function filtrarClientes(valor) {
  if (!soloLetras.test(valor)) {
    document.getElementById('cliente_busca').value = valor.replace(/[^a-zA-ZÀ-ÿ\s]/g, '');
    return;
  }

  const lista = document.getElementById('sugestoes-lista');
  lista.innerHTML = '';
  indiceSelecionado = -1;

  document.getElementById('nome_final').value = '';
  document.getElementById('cliente-badge').classList.remove('show');
  esconderProcesso();

  if (!valor.trim()) {
    lista.classList.remove('aberta');
    return;
  }

  const termo = valor.toLowerCase();
  const encontrados = clientesExistentes.filter(c => c.toLowerCase().includes(termo));

  encontrados.forEach(nome => {
    const item = document.createElement('div');
    item.className = 'sugestao-item';
    const proc = clientesProcessos[nome] ? ` — Proc. ${clientesProcessos[nome]}` : '';
    item.innerHTML = `${nome}<span style="font-size:0.78rem;color:var(--muted);margin-left:6px;">${proc}</span>`;
    item.onclick = () => selecionarCliente(nome, false);
    lista.appendChild(item);
  });

  // Só mostra "adicionar novo" se NÃO existe exatamente esse nome na BD
  const jaExisteNaBD = clientesExistentes.some(c => c.toLowerCase() === termo);
  if (!jaExisteNaBD && valor.trim()) {
    const itemNovo = document.createElement('div');
    itemNovo.className = 'sugestao-item novo';
    itemNovo.textContent = `➕ Adicionar "${valor.trim()}" como novo cliente`;
    itemNovo.onclick = () => selecionarCliente(valor.trim(), true);
    lista.appendChild(itemNovo);
  }

  lista.classList.toggle('aberta', lista.children.length > 0);
}

function selecionarCliente(nome, isNovo) {
  clienteIsNovo = isNovo;

  document.getElementById('cliente_busca').value = nome;
  document.getElementById('nome_final').value = nome;
  document.getElementById('badge-nome').textContent = isNovo ? `✨ Novo: ${nome}` : nome;
  document.getElementById('cliente-badge').classList.add('show');

  if (isNovo) {
    // NÃO adiciona ao array clientesExistentes aqui
    // Só é adicionado depois de guardar na BD
    document.getElementById('campo-processo').style.display = 'block';
    document.getElementById('info-processo').style.display = 'none';
    document.getElementById('num_processo').value = '';
    document.getElementById('num_processo').focus();
  } else {
    document.getElementById('campo-processo').style.display = 'none';
    const proc = clientesProcessos[nome];
    if (proc) {
      document.getElementById('info-processo-valor').textContent = proc;
      document.getElementById('info-processo').style.display = 'block';
    } else {
      document.getElementById('info-processo').style.display = 'none';
    }
  }

  document.getElementById('sugestoes-lista').classList.remove('aberta');
  document.getElementById('cliente_busca').classList.remove('erro');
}

function limparCliente() {
  document.getElementById('cliente_busca').value = '';
  document.getElementById('nome_final').value = '';
  document.getElementById('cliente-badge').classList.remove('show');
  esconderProcesso();
  clienteIsNovo = false;
  document.getElementById('cliente_busca').focus();
}

function esconderProcesso() {
  document.getElementById('campo-processo').style.display = 'none';
  document.getElementById('info-processo').style.display = 'none';
}

function navegarLista(e) {
  const lista = document.getElementById('sugestoes-lista');
  const items = lista.querySelectorAll('.sugestao-item');
  if (!items.length) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    indiceSelecionado = Math.min(indiceSelecionado + 1, items.length - 1);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    indiceSelecionado = Math.max(indiceSelecionado - 1, 0);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (indiceSelecionado >= 0) items[indiceSelecionado].click();
    else if (items.length === 1) items[0].click();
    return;
  } else if (e.key === 'Escape') {
    lista.classList.remove('aberta');
    return;
  }

  items.forEach((item, i) => item.classList.toggle('selecionado', i === indiceSelecionado));
}

document.addEventListener('click', (e) => {
  if (!e.target.closest('.autocomplete-wrapper')) {
    document.getElementById('sugestoes-lista').classList.remove('aberta');
  }
});

// =============================================
// FOTOS
// =============================================

function adicionarFotos(input) {
  const novos = Array.from(input.files);
  novos.forEach(file => {
    if (fotosFiles.length >= MAX_FOTOS) return;
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['jpg','jpeg','png','gif','webp'].includes(ext)) return;
    fotosFiles.push(file);
  });
  input.value = '';
  renderizarGaleria();
  atualizarInputFotos();
}

function removerFoto(index) {
  fotosFiles.splice(index, 1);
  renderizarGaleria();
  atualizarInputFotos();
}

function renderizarGaleria() {
  const galeria    = document.getElementById('fotos-galeria');
  const contador   = document.getElementById('fotos-contador');
  const uploadArea = document.getElementById('upload-area-foto');
  galeria.innerHTML = '';

  if (fotosFiles.length === 0) {
    contador.style.display = 'none';
    uploadArea.classList.remove('tem-fotos');
    return;
  }

  fotosFiles.forEach((file, index) => {
    const item = document.createElement('div');
    item.className = 'foto-item';
    const reader = new FileReader();
    reader.onload = (e) => {
      item.innerHTML = `
        <img src="${e.target.result}" alt="Foto ${index + 1}"/>
        <button type="button" class="foto-remover" onclick="removerFoto(${index})" title="Remover">✕</button>
        <span class="foto-numero">${index + 1}</span>
      `;
    };
    reader.readAsDataURL(file);
    galeria.appendChild(item);
  });

  if (fotosFiles.length < MAX_FOTOS) {
    const btnMais = document.createElement('div');
    btnMais.className = 'foto-adicionar-mais';
    btnMais.innerHTML = `<span>+</span><p>Adicionar</p>`;
    btnMais.onclick = () => document.getElementById('foto-input').click();
    galeria.appendChild(btnMais);
  }

  contador.textContent = `${fotosFiles.length} de ${MAX_FOTOS} fotos selecionadas`;
  contador.style.display = 'block';
  uploadArea.classList.add('tem-fotos');
}

function atualizarInputFotos() {
  const input = document.getElementById('foto-input');
  const dt = new DataTransfer();
  fotosFiles.forEach(file => dt.items.add(file));
  input.files = dt.files;
}

// =============================================
// ÁUDIO
// =============================================

function previewAudio(input) {
  const file = input.files[0];
  if (!file) return;
  document.getElementById('audio-nome').textContent = file.name;
  document.getElementById('audio-nome').style.display = 'block';
  const preview = document.getElementById('audio-preview');
  preview.src = URL.createObjectURL(file);
  preview.style.display = 'block';
  document.getElementById('quitar-audio').style.display = 'inline-block';
}

function quitarAudio() {
  document.getElementById('audio-input').value = '';
  document.getElementById('audio-preview').style.display = 'none';
  document.getElementById('audio-preview').src = '';
  document.getElementById('audio-nome').style.display = 'none';
  document.getElementById('audio-nome').textContent = '';
  document.getElementById('quitar-audio').style.display = 'none';
}

// =============================================
// VALIDAÇÃO
// =============================================

function validarFormulario() {
  const nome      = document.getElementById('nome_final').value.trim();
  const descricao = document.getElementById('descricao').value.trim();
  const inputBusca = document.getElementById('cliente_busca');

  inputBusca.classList.remove('erro');
  document.getElementById('descricao').classList.remove('erro');

  if (!nome) {
    inputBusca.classList.add('erro');
    inputBusca.focus();
    mostrarMensagem('error', '❌ Por favor selecione ou adicione um cliente.');
    return false;
  }

  const soloLetrasValidar = /^[a-zA-ZÀ-ÿ\s]+$/;
  if (!soloLetrasValidar.test(nome)) {
    inputBusca.classList.add('erro');
    inputBusca.focus();
    mostrarMensagem('error', '❌ O nome do cliente só pode conter letras e espaços.');
    return false;
  }

  if (clienteIsNovo) {
    const proc = document.getElementById('num_processo').value.trim();
    if (!proc) {
      document.getElementById('num_processo').focus();
      document.getElementById('num_processo').style.borderColor = 'var(--danger)';
      mostrarMensagem('error', '❌ O Nº do Processo é obrigatório para novos clientes.');
      return false;
    }
  }

  if (!descricao) {
    document.getElementById('descricao').classList.add('erro');
    document.getElementById('descricao').focus();
    mostrarMensagem('error', '❌ O campo Descrição é obrigatório.');
    return false;
  }

  // Mostrar loading se há áudio (whisper pode demorar)
  const audioInput = document.getElementById('audio-input');
  if (audioInput && audioInput.files.length > 0) {
    mostrarMensagem('success', '⏳ A guardar e a transcrever o áudio... por favor aguarde.');
  }

  return true;
}

// =============================================
// MENSAGENS
// =============================================

function mostrarMensagem(tipo, texto) {
  const msg = document.getElementById('msg');
  msg.className = 'msg ' + tipo + ' show';
  msg.textContent = texto;
  if (tipo === 'success' && !texto.includes('aguarde')) {
    setTimeout(() => msg.classList.remove('show'), 5000);
  }
}

// =============================================
// INICIALIZAÇÃO
// =============================================

document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const status    = urlParams.get('status');
  const msgParam  = urlParams.get('msg');

  if (status === 'ok') {
    mostrarMensagem('success', '✅ Registo guardado com sucesso!');
    window.history.replaceState({}, '', 'index.php');
  } else if (status === 'erro') {
    mostrarMensagem('error', '❌ ' + (msgParam || 'Erro ao guardar.'));
    window.history.replaceState({}, '', 'index.php');
  }
});