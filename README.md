📘 Sistema de Registo 
📌 Descrição

O Sistema de Registo (IPI) é uma aplicação web desenvolvida em PHP e MySQL, destinada à gestão de registos de clientes em contexto institucional
Este sistema permite que cada utilizador (tutor) registe, consulte, edite e exporte informações relacionadas com os seus clientes, incluindo conteúdos multimédia (imagens e áudio), com funcionalidades avançadas como transcrição automática de áudio e geração de documentos PDF profissionais.

O principal objetivo é centralizar e organizar a informação de forma eficiente, segura e acessível.

🚀 Funcionalidades Principais


🔐 Autenticação com LDAP
Integração com Active Directory
Login com credenciais institucionais
Identificação através de sAMAccountName
Sessões seguras com controlo de expiração por inatividade
👤 Gestão de Utilizadores e Sessões
Cada utilizador corresponde a um tutor
Sessões protegidas em todas as páginas
Logout seguro com destruição completa da sessão
Expiração automática após 10 minutos de inatividade


📝 Criação de Registos
Permite criar novos registos com:

Cliente (com sistema de pesquisa/autocomplete)
Número de processo (obrigatório para novos clientes)
Descrição textual
Upload de até 12 imagens
Upload de 1 ficheiro de áudio


📌 Regras importantes:
Cada cliente está associado a um único tutor
Não é possível criar ou usar clientes de outros utilizadores
🖼️ Processamento de Imagens
Redimensionamento automático (resize)
Compressão para otimização de desempenho
Suporte para:
JPG
PNG
GIF
WEBP
Armazenamento eficiente no servidor


🎵 Áudio e Transcrição Automática
Upload de ficheiros de áudio:
MP3, WAV, OGG, M4A, AAC
Conversão automática para formato compatível (WAV 16kHz) usando ffmpeg
Transcrição automática com Whisper.cpp (IA local)
Armazenamento da transcrição na base de dados


🔄 Script de Transcrição de Áudios Antigos
Inclui um script adicional que permite:

Identificar registos sem transcrição
Processar automaticamente os áudios existentes
Atualizar a base de dados com o texto transcrito
📊 Histórico de Registos
Visualização de todos os registos do sistema
Filtros avançados por:
Tutor
Cliente
Data
Número de processo
Informação apresentada:
Nome do cliente
Tutor responsável
Data do registo
Indicadores de anexos (fotos/áudio)


✏️ Edição de Registos
Permite modificar registos existentes:

Alterar descrição
Atualizar número de processo (afeta todos os registos do cliente)
Gerir ficheiros:
Remover imagens existentes
Adicionar novas imagens
Substituir ou remover áudio
Reprocessar automaticamente a transcrição ao alterar o áudio
📌 Apenas o tutor responsável pode editar os seus registos.


📄 Geração de Documentos PDF
Criação de relatórios completos por cliente
Utilização da biblioteca TCPDF
Layout totalmente personalizado (sem templates automáticos)

Inclui:

Dados do cliente
Número de processo
Responsável (tutor)
Lista cronológica de registos
Integração de imagens no documento
Paginação automática


🧠 Funcionamento do Sistema
Fluxo geral:
O utilizador autentica-se via LDAP
É criada uma sessão segura
O utilizador cria um registo
O sistema:
Processa imagens
Converte e transcreve áudio
Guarda os dados na base de dados
O utilizador pode:
Consultar histórico
Editar registos
Gerar relatórios em PDF


🔒 Segurança
Utilização de Prepared Statements (proteção contra SQL Injection)
Sanitização de dados com htmlspecialchars
Controlo de permissões por tutor
Sessões protegidas
Timeout automático
Validação de ficheiros enviados


📌 Considerações Finais
Este sistema foi desenvolvido com foco em:

Utilização real em ambiente institucional
Organização eficiente de informação
Integração com tecnologias modernas (IA, LDAP, PDF)

Destaca-se pela combinação de:

Gestão de dados
Processamento multimédia
Automação inteligente


⚙️ Tecnologias Utilizadas
Backend: PHP (mysqli)
Base de Dados: MySQL
Autenticação: LDAP (Active Directory)
PDF: TCPDF
Áudio:
ffmpeg (conversão)
whisper.cpp (transcrição)
Frontend:
HTML5
CSS3
JavaScript
