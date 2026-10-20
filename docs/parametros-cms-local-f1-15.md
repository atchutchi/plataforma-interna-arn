# Próxima entrega: parâmetros autorizados do CMS

Preparação técnica de 20 de outubro de 2026 para US-A05 e secção 6.2 do README. Não é código implementado nem aprovação institucional. O Painel ARN e os destinos de gestão já existem. A lacuna seguinte é configurar parâmetros expostos pela aplicação com competência própria, validação e histórico de alterações.

## Escopo da primeira parcela

Criar um catálogo fechado de limites em bytes e tipos MIME admitidos para novos uploads nos destinos de notícias e catálogo documental. Os valores iniciais devem reproduzir as políticas actualmente implementadas. A configuração pode restringir esses valores dentro dos limites do código, do PHP e do servidor. Não pode alargar tipos suportados, ultrapassar esses limites ou alterar permissões de leitura/publicação.

PrivateBlobStore suporta PDF, JPEG e PNG, mas cada destino conserva as suas restrições adicionais. A propriedade max_bytes do armazenamento também é usada na leitura de ficheiros. Não a substituir directamente pelo novo limite configurável, pois isso poderia impedir downloads de versões publicadas anteriormente. A nova política aplica-se à admissão de bytes novos. Leituras e referências já registadas conservam validação por descritor imutável, versão e autoridade. Quarentena de ficheiros existentes é uma operação distinta, fora desta parcela.

Contagens de galeria/anexos, audiências, circuitos editoriais, funções, autenticação, segredos, URLs OIDC, raízes privadas, configurações SQL, rede e parâmetros do servidor mantêm os respectivos contratos. Não há editor genérico de wp_options nem aceitação de chaves livres.

## Autoridade, persistência e interface

A configuração exige uma operação funcional própria e uma concessão explícita. Role::TechnicalAdministrator tem actualmente uma lista vazia de acções de negócio em AuthorizationPolicy. A posse de manage_options ou um menu WordPress não substitui essa concessão. O mecanismo será configurável e ensaiado com fixtures, sem atribuir competências a contas reais ou inferir quem administra a ARN.

O instalador explícito deve validar a fronteira local e preparar o esquema e um elenco fechado example.invalid, com novas fixtures apenas quando necessárias e sem alterar as contas existentes. Não há DDL ou concessões em pedidos HTTP. Cada leitura/gravação revalida estado da conta, sessão, concessão e esquema corrente. A instalação institucional fica pendente da matriz aprovada e da homologação.

As alterações usam uma revisão corrente, comparação concorrente e motivo obrigatório. Estado anterior, estado novo, interveniente e data UTC confirmam na mesma transacção. Uma tentativa sem alterações não acrescenta auditoria. Falha ou conflito não deixa uma parte da configuração gravada. O histórico não aceita edição ou apagamento pela interface.

A página no Painel ARN usa campos tipados e associados a labels, unidades explícitas, motivo, revisão e POST com nonce ligado à operação e ao titular. Preserva os campos após erro sem guardar payloads em URL, transients ou opções temporárias. Valores actuais, limites efectivos e proveniência devem ser claros. Uma acção não disponível não reaparece como formulário executável depois de revogação. Impeccable e UI UX Pro Max orientam a apresentação existente, sem substituir o tema.

O consumidor tem de revalidar a política corrente antes de confirmar a admissão do upload. Uma alteração concorrente não pode deixar referências ou auditoria parcial. O tratamento dos temporários da tentativa deve ser definido em conjunto com o protocolo de ficheiros privados. Não apagar blobs registados ou versões anteriores para limpar uma tentativa recusada. A configuração não substitui MIME efectivo, assinatura, protecção de caminhos ou autorização por objecto.

## Critérios de aceitação, todos por executar

| ID | Resultado exigido |
| --- | --- |
| CFG-01 | Catálogo fechado e valores iniciais equivalentes ao comportamento actual, sem novas opções ou segredos na interface |
| CFG-02 | Tipos e tamanhos inválidos, chaves extra, arrays, valores fora dos limites e alargamento de MIME recusados no servidor |
| CFG-03 | Administrador WordPress sem concessão própria, outra conta, âmbito incompatível e sessão antiga não lêem ou alteram a configuração |
| CFG-04 | Suspensão, expiração e revogação entre GET e POST são revalidadas e não gravam |
| CFG-05 | Revisão, valores e auditoria confirmam atomicamente. Falha injectada na auditoria faz rollback |
| CFG-06 | Duas alterações concorrentes produzem uma só revisão e um conflito com recuperação dos campos do utilizador |
| CFG-07 | No-op conserva revisão e auditoria. Motivo e histórico ficam protegidos e escapados |
| CFG-08 | Novo upload respeita a política efectiva nos dois módulos, mesmo com cliente ou pedido forjado |
| CFG-09 | Reduzir limites não altera a disponibilidade de ficheiros registados/publicados anteriormente ou amplia a sua audiência |
| CFG-10 | Alteração de política durante upload recusa a confirmação incoerente e trata temporários sem tocar em blobs registados |
| CFG-11 | Instalação explícita, idempotência e fronteira local comprovadas. Esquema ausente/incompatível recusa HTTP sem reparação |
| CFG-12 | GET, REST e acessos indevidos não escrevem e não expõem configuração, motivos ou dados a titulares alheios |
| CFG-13 | Playwright cobre configuração, conflito, revogação e upload permitido/recusado, com teclado, ecrã pequeno e alvos de 44px |
| CFG-14 | Testes agrupados no fecho, preservação das tabelas anteriores, revisão independente e CI verde no SHA exacto antes do merge |

Implementar domínio/política, persistência e autoridade, transporte/interface e consumidores, por essa ordem. Acrescentar ensaios de concorrência e admissão reais antes de declarar a parcela concluída. Não repetir a instalação de tarefas, People, notícias ou F1-12 no laboratório de demonstração. Dados concretos e capturas continuam fora do Git.
