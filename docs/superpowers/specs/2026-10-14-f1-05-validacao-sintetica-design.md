# F1-05: pré-validação sintética sem persistência

## Objectivo e autorização

O utilizador pediu o validador de importações sintéticas, sem escrever dados, no laboratório, com auditoria, Playwright, commits e push. Esta parcela entrega análise e pré-visualização. Não entrega importação executável. A auditoria é um relatório em memória apresentado na resposta, sem INSERT, eventos persistentes, logs, ficheiros de lote, opções, transients ou sessões da aplicação. A preparação e limpeza de fixtures exclusivas nos testes são separadas do percurso sujeito a validação.

## Fronteira

Só ambiente efectivo local, actor autenticado com manage_options e os booleanos ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE e ARN_ALLOW_LOCAL_IMPORT_PREVIEW iguais a true. Revalidar antes de preparar e renderizar. Sem excepção por email. A página aceita apenas uma chave de cenário fechado, revisão das unidades e nonce. Não há upload, textarea editável, CSV recebido do browser ou confirmação de execução. Rejeitar ficheiros e campos de payload arbitrário. Sem novo schema, dependências, concessões ou efeitos em contas, pessoas, sessões, unidades e auditorias existentes. Não alterar os seus contratos. Bootstrap0.9.0.

## Dados e regras do ensaio

Catálogo de pessoas existente apenas em memória, revisão 1: LAB-PER-001 e LAB-PER-002, nomes Pessoa fictícia 001 e Pessoa fictícia 002, emails pessoa001@example.invalid e pessoa002@example.invalid, ambos em LAB-A. Não pesquisar empregados ou contas WordPress para resolver duplicados. As unidades provêm de LocalUnitInstaller::repository()->snapshot(), apenas leitura, e conservam a revisão e o estado activo corrente. Não inicializar ou reparar tabelas.

CSV UTF-8 com BOM inicial opcional, separador vírgula, campos entre aspas e aspas duplicadas suportados, LF/CRLF, sem aspas malformadas ou texto depois de fechar um campo entre aspas. Cabeçalho exacto e ordenado professional_id,name,email,unit_id, sem campos extras, repetidos ou ausentes. Limites 16384 bytes e 20 registos de dados. Vazio, UTF-8 inválido, NUL, excesso de tamanho/linhas, header inválido e CSV malformado bloqueiam o lote com erro constante. Processar em memória, sem ficheiros temporários. Conservar o número físico da linha inicial de cada registo, incluindo quando há campos entre aspas com newline. Não aceitar lixo depois das aspas nem truncar silenciosamente.

Por registo: exactamente quatro campos. ID canónico LAB-PER- seguido de três dígitos, 001..999. Nome entre 1 e 100 caracteres, sem controlos ou espaços externos, começado por Pessoa fictícia . Email canónico minúsculo válido no domínio exacto example.invalid, sem espaços externos. Unidade existente e activa. Não normalizar, deduzir emails ou fundir nomes iguais. Identificadores repetidos no lote invalidam todas as suas ocorrências. Emails repetidos em IDs diferentes no lote ou pertencentes a outro ID no catálogo existente invalidam as linhas correspondentes. Mesmo ID existente permite comparar e propor alteração. Um nome repetido não é critério de identidade.

Cada linha devolve erros por campo ou proposta de criação, alteração com nomes dos campos diferentes, ou sem mudança. Linhas inválidas não contam como alteração. O resumo apresenta os quatro totais e validade do lote. Erros globais ou de linha invalidam o lote inteiro, sem deixar executar nada. Ausentes no CSV não são suspensos ou eliminados. Um lote válido neste laboratório continua sem autoridade para importação institucional.

## Cenários e relatório

Catálogo fechado de CSVs do servidor: válido (001 sem mudança, 002 renomeada, 003 nova em LAB-ROOT), duplicados/conflitos, unidades desconhecida e LAB-B, formato malformado e limite excedido. LAB-B pode estar inactiva ou activa conforme o snapshot, sem a alterar. Mostrar os dados sintéticos e resultados escapados. Labels e mensagens pt-PT. Não aceitar valores alternativos destes dados pelo browser.

Relatório de auditoria sem persistência: hash SHA-256 do CSV, hash do catálogo sintético, hash/revisão do snapshot de unidades, versão do validador, identificador determinístico do lote ligado a esses valores, contagens e erros. Actor e instante UTC de execução são acrescentados no servidor à apresentação, sem participarem na identidade determinística. Não armazenar motivo ou payload em logs. A resposta usa no-store. Um GET posterior não recupera o relatório anterior.

Página Ferramentas > Validar importação sintética, slug arn-local-import-preview. POST na própria página com nonce arn-local-import-preview, chave scenario e revision canónica das unidades. Preparar antes dos headers, recusar método, nonce ou campos inválidos antes de abrir a leitura. Revisão esperada diferente do snapshot corrente devolve conflito sem relatório. Se indisponível, falhar fechado com erro constante. Render verifica novamente a guarda e não recalcula nem escreve. Não existe botão ou endpoint de aplicar/importar. O relatório identifica o snapshot usado sem prometer validade após mudanças posteriores.

## Provas

TDD para parser, limites, erros, duplicados em todas as ocorrências, conflitos no catálogo, propostas e identidade determinística. Ensaiar escaping e rejeição de campos de privilégios/payload em POST. Integração WordPress/MariaDB usa três tabelas exclusivas de unidades e catálogo estático. Depois do setup, bloquear e detectar DML/DDL no percurso de validação e comparar estado antes/depois. CI development conserva a guarda operacional fechada, sem activar flags. PHP focado, uma suite completa, Node, PHPCS e integração nova.

Root Playwright no laboratório: válido, erros por linha, malformado, limite, nonce inválido, cenário/payload/revisão adulterados, conta comum e visitante recusados, repetição determinística e GET sem relatório guardado, no-store e mobile. Comparar tabelas de unidades, auditoria, autorização e cadastro de utilizadores antes/depois. Não criar pessoas ou contas para testar a importação. O relatório local documenta os resultados sem gravar eventos na base. Revisão independente e CI exacta antes de merge, sem deploy.
