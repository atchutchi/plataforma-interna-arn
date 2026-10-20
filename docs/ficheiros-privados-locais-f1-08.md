# Ficheiros privados no laboratório: F1-08

## Âmbito

O núcleo `Documents\PrivateFiles` separa o binário da sua referência no objecto. O ficheiro recebe um ID opaco aleatório e é copiado para uma pasta privada sem substituir um ficheiro existente. O registo liga esse ID a um tipo de objecto, identificador, versão e variante. O registo e o evento `file_registered` são confirmados na mesma transacção SQL. Os metadados não contêm o caminho de origem ou conteúdo dos documentos.

`PrivateFileDelivery` resolve a referência no servidor, chama o resolvedor de autorização do objecto e só depois abre o ficheiro. O armazenamento verifica os bytes reais, o tipo MIME, a assinatura e o SHA-256 no mesmo handle que será entregue. O hash é calculado por blocos. A recusa, a ausência, a corrupção e uma falha interna resultam no mesmo estado vazio para o adaptador HTTP.

O núcleo recusa sobreposições entre a raiz privada e as raízes públicas conhecidas. Recusa também links detectáveis nos caminhos. Estas verificações complementam as ACL e não demonstram que o IIS está correctamente configurado. Não deve existir um alias público, directório virtual ou cópia em uploads para estes binários.

## Dados e instalação

O prefixo WordPress recebe três tabelas: `arn_private_files`, `arn_private_file_audit` e `arn_private_file_schema`. O schema verifica versão, engine transaccional, colunas e índices antes de operar. A chave única de tipo, objecto, versão e variante impede substituir o anexo dessa versão. Não há operações de alteração ou eliminação no registo.

A gravação no sistema de ficheiros e a transacção SQL não formam uma única transacção distribuída. Se o registo SQL falhar depois da cópia, pode ficar um binário órfão privado. Não há publicação de referência sem evento confirmado. A política de reconciliação e eliminação de órfãos continua por definir antes de usar dados institucionais.

A instalação é explícita por CLI. Não há migração, criação de ficheiros ou atribuição de permissões em pedidos GET. Os ensaios integrados usam prefixos aleatórios e pastas exclusivas. A limpeza desses ensaios não remove as tabelas normais ou os anexos da pré-visualização editorial.

## Admissão e limites

PDF, PNG e JPEG são os únicos tipos técnicos suportados por este núcleo. A composição indica explicitamente quais admite e o limite de bytes. O laboratório só prepara conteúdo sintético fechado. A extensão fileinfo é obrigatória. A sua ausência provoca recusa, sem confiar na extensão do nome do ficheiro.

A verificação de MIME e assinatura não equivale a análise antimalware ou aprovação do conteúdo. PEN-08 continua a exigir tipos e limites institucionais, quarentena, tratamento de ficheiros suspeitos e procedimentos de operação. Esta entrega não activa uploads de funcionários ou a biblioteca de media institucional.

## Contrato do adaptador local

O adaptador exige ambiente efectivo `local`, `ARN_ALLOW_LOCAL_PRIVATE_FILES` booleano true e uma pasta privada indicada em `ARN_LOCAL_PRIVATE_FILES_DIR`. A identidade fictícia, o snapshot de autorização e a geração da sessão são verificados através de `CurrentLocalAccount`. O administrador técnico não recebe uma excepção de acesso de negócio.

Os objectos sintéticos são próprio, unidade A e unidade B. Têm original, miniatura, pré-visualização e uma versão antiga. A lista filtra os objectos antes de apresentar conteúdos ou contagens. As variantes correntes passam pela mesma decisão por objecto. A versão antiga fica recusada no cenário fechado desta etapa. Este cenário não substitui as futuras regras institucionais de consulta de versões.

O endpoint recebe apenas `arn_private_file` com um ID estrito. Não aceita caminhos, audiência, âmbito, MIME ou provas de aprovação do browser. Sucesso usa `attachment`, MIME verificado, tamanho exacto, `nosniff` e `no-store`. Recusa e ausência devolvem 404 vazio. Métodos diferentes de GET não entregam bytes. Ranges e cache não estão activados nesta etapa.

## Operação e verificações locais

A ferramenta fica em Ferramentas/Ficheiros privados fictícios, com o slug `arn-local-private-files`. Exige também as flags booleanas de acesso fictício e persistência de autorização já usadas por F1-03. O limite técnico desta composição sintética é 1 MiB por ficheiro. Os segredos e o caminho local ficam no `wp-config.php` não versionado. O valor de exemplo para uma pasta privada é `C:/ARN-Lab/private`, previamente criada fora das raízes públicas.

Após preparar a configuração, o administrador do laboratório executa por WP-CLI:

```sh
wp eval '$owner = get_user_by("login", "ana.teste"); if (!$owner instanceof WP_User) { throw new RuntimeException("Fixture ausente."); } (new \Arn\Intranet\Documents\PrivateFiles\LocalFileInstaller())->install((int) $owner->ID);' --user=lab_admin
```

A instalação prepara 12 referências e 12 eventos. A repetição conserva os mesmos IDs e a mesma contagem. Não define permissões ou contas. A primeira fixture PDF foi corrigida durante a QA porque não abria num leitor. A primeira coorte de ensaio foi arquivada com os binários, metadados e auditoria fora do Git antes da regeneração. Nenhum anexo real ou registo anterior de autorização foi removido.

Verificação em PHP 8.4.26 e Node 24.21.0: 655 testes PHPUnit e 2189 asserções, PHPCS em 170 ficheiros, 55 testes Node, lint e build aprovados. Existem quatro testes de symlink ignorados neste Windows por falta de privilégio. Os casos cobrem raiz, ancestral, fonte e blob e devem correr na CI Linux. A depreciação PHPUnit observada já existia e vem do data provider em doc-comment de `ImportPreviewTest::testMalformedCsvIsRejected`, fora do novo módulo.

O ensaio `test:private-files:integration` passou 12 verificações. `test:local-private-files:integration` passou 39 verificações no laboratório, incluindo instalação desde tabelas e pasta vazias e reinstalação idempotente. Em development, a CI testa a recusa efectiva do instalador e usa dependências internas exclusivas para os positivos. Não se alterou a configuração global do wp-env para contornar o gate local.

Playwright passou 29 verificações HTTP e de interface: três variantes próprias com MIME, tamanho e cabeçalhos correctos, nove referências restritas, ID desconhecido, travessia, ID composto, métodos HEAD/POST, visitante e download pela interface. Uma alteração ao texto do PDF, mantendo o tamanho, foi recusada com 404 vazio. Após restauro, o mesmo ID voltou a entregar 610 bytes com sucesso. Após logout, esse ID voltou a dar 404 vazio. O PDF descarregado abriu com uma página no pypdf, foi renderizado e o SHA-256 coincidiu com o registo. As vistas de computador e de 390 px foram inspeccionadas.

O principal `lab_admin` foi recusado pelo adaptador normal em WP-CLI. A navegação de recusa no browser interno apresentou a página de erro do próprio browser, pelo que não se usa esse ensaio para afirmar tamanho ou cabeçalhos da resposta administrativa. O portal foi reaberto e o login Google administrativo normal foi renovado sem alteração de permissões. A comparação final confirmou tabelas ARN anteriores, utilizadores, metadados e sessões de Ana inalterados. As três novas tabelas de ficheiros, separadas nessa comparação, conservam apenas as 12 referências sintéticas e respectivos eventos.

Os artefactos de QA e os binários ficam em pastas ignoradas. Não são conteúdo editorial aprovado. A integração da branch depende ainda da revisão final e CI no SHA exacto.

## Critérios ainda abertos

Actualização de 20 de outubro: o núcleo e a ligação aos módulos de documentos e notícias estão concluídos localmente. A admissão configurável e transaccional está aceite na PR #59. A [fronteira nativa](fronteira-media-nativa-f1-08.md) fecha a alternativa pública do WordPress. F1-08 ainda precisa de reconciliação, derivados privados, antimalware/quarentena e política institucional. Faltam ensaios representativos de tamanho, concorrência, reparse points, ACL, IIS, MySQL 8.4 e caminhos estáticos alternativos. O README distingue estas lacunas das parcelas já concluídas.

Os 22 anexos reais conservam o circuito de revisão administrativa em `EditorialPreview`. Não são migrados ou disponibilizados à Ana Teste por esta etapa. A configuração e os dados de laboratório ficam fora do Git.
