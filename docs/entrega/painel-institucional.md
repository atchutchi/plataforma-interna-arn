# Painel institucional: sessão, permissões e leitura de comunicados

Esta parcela liga o painel à fonte institucional persistente. O responsável confirmou em 16 de novembro de 2026 que a primeira audiência será a instituição inteira. Comunicados por direcção ou unidade ficam fechados até existir organograma aprovado. Não foram alterados o Wamp ou o servidor .205.

## Contrato entregue

O endereço `/?arn_panel=1` exige as duas flags privadas booleanas `ARN_INSTITUTIONAL_IDENTITY_ENABLED` e `ARN_INSTITUTIONAL_PANEL_ENABLED`. O callback Google institucional encaminha para esse endereço quando o painel está activado. O painel usa o token nativo WordPress, vínculo Google corrente, geração e revisão de autorização. Um administrador WordPress sem concessão Employee válida não recebe acesso de negócio.

`InstitutionalPanel` pede ReadContent sobre o próprio colaborador ao motor existente. Revalida a fonte depois da leitura SQL. Uma revogação durante a consulta impede a devolução do resultado. Não há fallback para actores fictícios, concessões por email ou contorno por perfil WordPress.

`NoticeReader` é partilhado com o laboratório e mantém a validação de agregado, digest, âncora privada, versões e auditoria. Só a versão efectivamente publicada, ainda válida, com audiência `all`, sem unidade e circuito `institutional-review` chega ao HTML e JSON. Revisões em rascunho não substituem a última publicação aprovada. Arquivo, expiração e mudança publicada para uma unidade retiram o comunicado deste painel.

A leitura SQL usa uma transacção consistente, até 100 candidatos por pedido e até 12 comunicados visíveis. A continuação usa um cursor de objecto. Uma página pode estar vazia e ainda oferecer continuação se os candidatos analisados não forem visíveis. A pesquisa é explicitamente limitada à página corrente. Não há contagem total de rascunhos ou de outras audiências.

O endpoint responde sem cache e sem indexação. A resposta de erro não inclui SQL, identidade ou títulos privados. Não existe endpoint REST paralelo. O conteúdo continua legível no HTML sem JavaScript. O painel não cria, altera, aprova ou publica comunicados.

## Interface e construção

React está limitado a esta superfície do tema WordPress. Button, Badge, Accordion e Carousel provêm do registo oficial shadcn/ui, variante Radix new-york. As fontes estão em `wp-content/themes/arn-intranet/ui`, com licença MIT preservada. A adaptação traduz os rótulos, retira os destaques ocultos da navegação por teclado e respeita movimento reduzido. A marca original, Manrope, Source Sans 3 e cores ARN são preservadas.

`npm ci` e `npm run build` compilam JavaScript e Tailwind localmente. Os ficheiros distribuíveis ficam em `assets/panel`. Não se carregam componentes, tipos de letra ou scripts através de CDN. A compilação não inclui dados de ensaio. O servidor IIS não precisa de Node para servir o painel. As versões de dependências estão fixadas no lockfile. `npm audit --omit=dev` não apresentou vulnerabilidades nesta entrega.

Os destaques são manuais, sem reprodução automática. Os acordeões mostram o texto completo. Não se acrescentou uma galeria com fotografias fictícias. Uma galeria institucional exige conteúdo e ligações ao circuito de media autorizado, que permanecem fora desta parcela.

## Migração e continuidade

Com `ARN_INSTITUTIONAL_PANEL_ENABLED=true`, o comando existente `wp arn institutional apply --backup-ref=REFERENCIA --user=OPERADOR` passa a incluir as cinco tabelas de comunicados vazias. Continua a exigir o opt-in de migrações, a guarda precoce exclusiva, administrador técnico CLI e bloqueio SQL. Não cria colaboradores, vínculos, concessões, publicações ou fixtures.

O recibo `communications` precede `complete`. A libertação valida o schema de comunicados e exige esse recibo na última aplicação. Um recibo antigo de identidade/autorização não basta para activar esta parcela. A aplicação interrompida conserva manutenção. Uma retoma com a mesma referência de backup pode completar tabelas parciais apenas quando existe recibo anterior de aplicação em curso e todas as tabelas existentes correspondem ao contrato exacto. Um schema parcial sem essa prova continua recusado.

Depois de uma aplicação desta versão, não voltar ao leitor antigo de recibos: este desconhece a fase `communications`. Preservar o candidato completo, backup e recibos. A referência de backup identifica uma prova externa, não verifica por si só a integridade ou o restauro do backup.

No .205, manter primeiro a manutenção por IP e executar o [ensaio isolado da guarda, ACL e restauro](homologacao-guarda-restauro-205.md). Só depois preparar um candidato sem dados locais, aplicar a migração sob a guarda e validar a sessão institucional real. A libertação da guarda de migração não substitui a decisão de abrir a manutenção externa.

## Ensaios e limites

O teste `npm run test:institutional-panel:integration` usa prefixo aleatório, utilizador e comunicados exclusivamente fictícios. Verifica instalação interrompida, fecho/reabertura da guarda, retoma, recusa de referência diferente, idempotência, manutenção, ausência de dados semeados pela migração, permissão independente do administrador nativo, invalidação de sessão, publicação, rascunho, audiência por unidade, expiração, cursor, corrupção e revogação durante a leitura. Remove os recursos próprios no finally. Resultado local: 42 verificações.

Regressões de migrações anteriores: 109 verificações. Identidade institucional: 77 verificações. PHPUnit local: 1233 testes, 6100 asserções e sete omissões dependentes do ambiente Windows. Node: 72 testes. A suite PHPUnit inclui a projecção da última versão aprovada e a substituição de audiência. A CI executa também o novo ensaio SQL.

O ensaio visual em 127.0.0.1:8890 usa uma página descartável, dados fictícios e os assets reais compilados. Verifica leitura, pesquisa, limpeza, destaque seguinte, foco apenas no destaque visível, largura móvel, teclado, texto ampliado a 200% e movimento reduzido. O cabeçalho adapta-se a uma segunda linha quando o texto ampliado o exige. Esta prova visual não equivale a login Google real ou homologação HTTP no IIS.

A gestão editorial institucional ainda precisa de adaptação própria. Os comandos e formulários editoriais existentes continuam restritos ao laboratório. Esta entrega conclui a leitura institucional no painel, não todos os módulos da plataforma. OAuth real, provisionamento institucional aprovado, organograma, media, Calendar, permissões efectivas IIS, restauro e piloto continuam sujeitos aos respectivos critérios de entrada em serviço. Nenhum dado do laboratório deve ser transportado para produção.
