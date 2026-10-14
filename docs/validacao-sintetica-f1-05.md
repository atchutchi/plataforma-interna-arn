# Pré-validação sintética F1-05

14 de outubro de 2026. Parcela de preparação de US-RH01 e US-ARN-02. O validador analisa apenas dados sintéticos em memória e apresenta propostas. Não cria ou altera pessoas, contas, vínculos, permissões ou unidades. Não existe confirmação de importação.

## Fronteira e funcionamento

A página Validar importação sintética fica em Ferramentas. Exige ambiente efectivo local, manage_options e as flags booleanas ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE e ARN_ALLOW_LOCAL_IMPORT_PREVIEW. A nova flag fica na configuração local fora do Git. O formulário aceita só cenário fechado, revisão das unidades e nonce. Não recebe uploads ou conteúdo CSV livre.

O catálogo fictício existente vive apenas no código do ensaio e tem revisão 1. As unidades são lidas do snapshot local existente. A leitura não instala, repara ou altera tabelas. O resultado identifica a revisão da estrutura usada. Um formulário com revisão antiga exige recarregar antes de analisar.

O CSV tem quatro colunas, professional_id,name,email,unit_id, em UTF-8, com vírgula e aspas estritas. O limite é 16384 bytes e 20 registos de dados. IDs LAB-PER-001 a LAB-PER-999, nomes começados por Pessoa fictícia e emails no domínio example.invalid mantêm o ensaio separado de dados reais. Estes campos não são aprovação do esquema institucional.

O relatório identifica erros por linha e campo, unidades desconhecidas ou inactivas e conflitos de identificador ou email. Duplicados no lote não são fundidos e todas as ocorrências afectadas são inválidas. Registos válidos são classificados como criação proposta, alteração proposta ou sem mudança. A ausência de uma pessoa no lote não gera desactivação.

## Auditoria sem persistência

A auditoria é apresentada na própria resposta. Contém hashes do CSV, do catálogo fictício e das unidades, revisões, versão do validador, identificador determinístico do lote, actor, instante UTC, contagens e erros. Actor e instante vêm do servidor. Não participam na identidade determinística do lote.

Não há escrita de auditoria na base de dados, logs de payloads, ficheiros de lote, opções, transients ou sessões da aplicação. Um GET posterior não recupera o relatório anterior. A resposta usa no-store. A documentação de QA regista as verificações locais sem converter uma pré-visualização em evento institucional.

## Verificação

No laboratório WordPress/PHP 8.4/MariaDB, a suite final passou 627 testes PHP e 2101 asserções. O aviso de depreciação da configuração PHPUnit já existente mantém-se. O runtime portátil usa a configuração OpenSSL local já preparada. Passaram também os 54 testes Node, lint, construção e PHPCS global de 145 ficheiros. As últimas alterações PHP passaram ainda PHPCS focado.

A integração reproduzível npm run test:import-preview:integration passou sete verificações. Cria três tabelas com prefixo aleatório exclusivo para preparar o cenário, bloqueia DML e DDL durante a pré-validação e compara snapshot e auditoria. Remove apenas as tabelas exclusivas no finally. A ferramenta operacional não instala tabelas nem escreve dados.

Um ensaio adicional por WP-CLI no WordPress local passou 25 asserções do adaptador com nonce nativo e leitor do snapshot existente. Confirmou as recusas de nonce inválido ou não escalar, método indevido, cenário livre ou não escalar, revisão não canónica, upload e campos adicionais antes da leitura. Uma revisão antiga respondeu 409 depois de uma única leitura. Os cinco cenários produziram relatório, um GET novo não recuperou o resultado anterior e a conta subscriber foi recusada. O filtro de queries registou zero tentativas de escrita. Estas recusas foram ensaiadas no adaptador nativo por CLI, não como pedidos HTTP adulterados no browser.

Os ensaios Playwright na sessão administrativa local confirmaram o formulário e os resultados dos cinco cenários. O lote válido propôs uma criação, uma alteração e um registo sem mudança. O cenário de duplicados apresentou quatro linhas inválidas e o das unidades duas. Aspas malformadas e tamanho excedido geraram erro global. A interface foi verificada a 375, 768 e 1440 píxeis, sem transbordo horizontal após correcção do select e da quebra dos hashes. As propostas e os campos aparecem em pt-PT. Não foram observados erros de consola na página final. As imagens de QA ficam apenas no directório local ignorado output/playwright.

Comparações por contagens e hashes antes e depois dos ensaios confirmaram as tabelas ARN, os utilizadores, os metadados fora de session_tokens e as sessões de Ana Teste inalterados. O relatório não se tornou evento de auditoria persistente. A verificação não exige que a autenticação nativa deixe de manter as suas próprias sessões.

A revisão independente encontrou e levou à correcção de emails com pontos mal colocados, parte local superior a 64 caracteres e duplicados presentes em linhas com aridade incorrecta. Os testes de regressão falharam antes das correcções e passaram depois. Os testes iniciais de gramática só foram executados após a implementação inicial. O registo de execução conserva essa lacuna e não a apresenta como TDD integral. A revisão independente das correcções não encontrou regressões concretas.

## Limites e continuação

Um lote válido neste ensaio não fica autorizado para importação institucional. RH tem de aprovar os campos, identificadores, estrutura e fonte dos dados. A execução futura continua dependente da autoridade F1-03, armazenamento privado e contrato de confirmação ligado ao conteúdo e às revisões. Os contratos de preparação permanecem em contratos-cadastro-importacao-f1-05.md. Não existe upload de pessoas reais nesta entrega.
