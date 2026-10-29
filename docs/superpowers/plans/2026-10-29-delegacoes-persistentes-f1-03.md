# Delegações persistentes no laboratório

Decisões do responsável: a delegação inicial é autorizada por um gestor de acessos explicitamente designado. O fim ou revogação impede novas atribuições, conservando a validade própria das permissões já atribuídas. Não inferir autoridade do administrador WordPress. Apenas contas fictícias locais.

## Contrato

Três fixtures novas e isoladas: gestor designado, gestor delegado e destinatário. Ambos os gestores têm concessão canónica explícita AccessAdministrator sobre os destinatários de ensaio. A delegação não cria essa competência. O designado pode emitir e revogar a delegação. O delegado pode usar uma delegação vigente para criar uma concessão dentro dos seus limites, sem promoção própria, atribuição de papéis administrativos ou combinação de delegações parciais.

Cada delegação imutável identifica o delegado, um destinatário, uma função e um âmbito, com início e fim UTC. O formulário fictício ensaia Employee/UnitManager e âmbito próprio/LAB-A/LAB-B. Alterar limites exige uma nova delegação, nunca uma ampliação silenciosa. Concessões têm validade própria explícita e podem continuar válidas após terminar a delegação. Não as revogar, modificar ou reemitir automaticamente.

## Execução

1. Persistir estado, recibos e auditoria em três tabelas próprias, com instalação CLI e flag local explícitas. Reconstituir o histórico e recusar corrupção ou revisões divergentes.
2. Compor criação canónica de concessões na transacção do proprietário, sem commit aninhado. Bloquear fixtures/contas, estado e unidades. Revalidar competência, sessão, vigência e limites imediatamente antes de confirmar. CAS da conta destinatária e estado global, recibo ligado ao comando e decisor.
3. Interface privada com emissão/revogação pelo designado e atribuição pelo delegado. POST, nonce, justificação, datas UTC e histórico. O administrador técnico e destinatários não recebem este painel.
4. Agrupar testes de domínio/transporte, SQL real com concorrência/falhas/expiração e Playwright no laboratório8892. Preservar os49 conjuntos de negócio anteriores,23 contas e365 ficheiros. Nenhuma produção, conta real, Google ou email.
5. Uma revisão independente no fim. README/manual, commit/push e PR. Merge apenas com CI do commit verde, seguido de confirmação de main.

## Foco da revisão

Autoridade inicial designada versus capacidade de usar uma delegação. Autopromoção, destinatários não abrangidos, datas caducadas durante a escrita, perda de sessão, revogação concorrente, concessões anteriores intactas e recibos antigos sem repetir efeitos. Atomicidade da revisão/autorização/auditoria e ausência de reactivação ou criação implícita de competências. A fonte institucional e a homologação continuam separadas.
