# Recuperação de confirmações de envios, F1-08

## Contrato

O laboratório pode confirmar um envio em estado `applying` apenas quando existe um recibo SQL gravado na mesma transacção que o conteúdo e a auditoria do módulo. A recuperação nunca chama novamente a criação, revisão ou anexação. A ausência, corrupção ou divergência do recibo conserva o estado indeterminado.

O recibo liga o identificador opaco do lote, autor, contexto completo, descritores dos originais, módulo, operação, objecto e revisão resultante. Existe no máximo um recibo por lote. A escrita exige transacção activa na ligação dedicada do repositório. Leituras não instalam tabelas nem escrevem dados.

O próprio autor confirma a recuperação por POST com sessão fictícia activa, nonce e revisão actual do lote. A autorização de leitura do objecto é novamente verificada. O operador técnico não ganha autoridade sobre os conteúdos de terceiros. O diário da fila acrescenta um evento distinto `reconciled`, conservando o histórico e os ficheiros. GET e HEAD nunca recuperam envios.

São abrangidos os novos envios de notícias, documentos, tarefas, pedidos internos e suporte TI. Os envios antigos sem recibo não são migrados por inferência. Um diário truncado ou incompatível permanece recusado. O recibo não publica conteúdos nem muda audiências.

## Aceitação

Uma falha depois do COMMIT e antes da confirmação da fila permite confirmar exactamente o objecto já gravado. Uma falha antes do COMMIT não permite confirmar nem repetir automaticamente. Autor diferente, sessão revogada, nonce inválido, revisão antiga, recibo divergente e diário alterado não modificam dados. Repetir a confirmação concluída não duplica conteúdo nem auditoria. Os testes usam exclusivamente dados fictícios e preservam os restantes registos e originais.

Esta entrega não certifica operação contínua no servidor, dimensionamento, actualização de assinaturas antimalware, retenção ou prontidão de produção.

## Instalação explícita no laboratório

O operador fictício autorizado executa `wp arn media install-receipts` com a configuração privada e as guardas locais existentes. O comando instala apenas a tabela própria de recibos e recusa esquemas incompatíveis. Não executa DDL dentro de uma transacção. A conclusão de novos envios exige o esquema validado antes de marcar `applying`. Sem essa preparação, a operação é recusada antes de escrever conteúdo.

Os consumidores anteriores sem fila mantêm o contrato. Não executar este instalador como migração institucional. O leitor anterior não reconhece o evento `reconciled`. Um rollback exige conservar o código compatível com os diários entretanto escritos, sem repor ou truncar o histórico.

## Evidência de 2 de novembro de 2026

Validação no laboratório fictício 8892, com relatórios, fotografias de ecrã e configuração privados fora do Git:

| Verificação | Resultado |
| --- | --- |
| PHPUnit completo | 1209 testes, 5898 asserções, sem falhas e sete omissões no runtime Windows. |
| SQL de media e recibos | 91 verificações, incluindo transacção obrigatória, invisibilidade antes de COMMIT, rollback, unicidade, divergência de fingerprint e recusa de schema incompatível. |
| Repositórios nativos | Documentos 28, notícias 50, tarefas 136, pedidos 91 e suporte 81 verificações. Exercitam a passagem do recibo pela operação real dos cinco módulos. |
| Node, lint e build | 61 testes sem falhas, lint e construção aprovados. |
| PHPCS | Ficheiros PHP alterados e acrescentados aprovados. |
| Playwright, recuperação real | Envio de PNG fictício, worker com ClamAV e GD, criação de rascunho e perda simulada da resposta após COMMIT. Um POST posterior do autor acrescentou `reconciled`. O ID, revisão, auditoria editorial e digest das tabelas de negócio permaneceram iguais. |
| Playwright, acesso e apresentação | Nove verificações finais: leitura concluída, HEAD vazio, nonce inválido, parâmetro extra, PUT, lote desconhecido, largura móvel, outro autor e anónimo. Capturas desktop e móvel revistas. |
| Preservação | 133 tabelas exteriores, 26 contas, 443 linhas anteriores de negócio e 365 ficheiros anteriores intactos. Criada uma tabela de recibos e acrescentados apenas artefactos fictícios desta validação. |
| Revisão independente | Uma revisão do diff não encontrou defeitos concretos. A lacuna de cobertura indicada foi resolvida com testes dos cinco repositórios e prova HTTP de perda da resposta. |

A simulação temporária de perda de resposta foi removida do laboratório. A revisão da fila passou de `applying` para `applied` sem repetir a criação. Uma segunda confirmação não altera negócio nem duplica a auditoria.

### Limites observados

O scanner recusou inicialmente assinaturas antigas. Foram preservadas as definições anteriores e actualizadas as assinaturas pelo `freshclam` oficial. Esta actualização pontual não substitui a manutenção operacional.

O ensaio editorial antigo continha um PDF de cabeçalho fictício. A fixture passou a usar um PDF válido de uma página. Com processamento síncrono real de vários anexos, o ensaio atingiu o limite existente de 120 segundos. Esse limite não foi aumentado. As 50 verificações SQL editoriais passaram no perfil SQL habitual, sem processamento nesse processo CLI isolado. O processamento HTTP e o worker continuaram activos e foram verificados com o PNG fictício. Não se declara aceite a capacidade síncrona de múltiplos anexos.

Os ensaios SQL usam tabelas exclusivas e removem apenas as suas próprias fixtures. Os ficheiros originais anteriores não foram apagados. A prova local MariaDB não homologa IIS/MySQL, migrações institucionais ou operação persistente.
