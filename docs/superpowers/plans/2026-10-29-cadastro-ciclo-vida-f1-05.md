# Cadastro profissional e acesso no laboratório

Decisão confirmada pelo responsável em 29 de outubro: suspender ou encerrar o cadastro suspende a conta associada e invalida as suas sessões. Reactivar o cadastro não reactiva o acesso. Conservar histórico. Associação explícita, sem inferir identidades pelo email e sem conceder competências.

## Entrega

1. Manter o catálogo/importação e os seus hashes intactos. Acrescentar três tabelas próprias para estados, recibos e auditoria, com instalação CLI explícita. Um registo sem decisão permanece Por definir.
2. Permitir a RH fictício associar um registo a uma conta de ensaio dedicada, uma única vez e sem reutilizar essa conta noutro registo. Não implementar desligação ou substituição implícita. Estados: Activo, Suspenso e Encerrado. Uma nova decisão explícita pode reactivar o registo, nunca a conta.
3. Transacção única com autoridade RH, bloqueio do catálogo, CAS, vínculo, estado da conta, revisão de sessão, recibo e auditoria. Reenvio devolve recibo anterior sem repetir efeitos. Recusa de falhas, sessão caducada, conta real, autoridade técnica, revisões antigas e schema incompatível.
4. Página privada com POST, nonce, justificação, estado e associação identificáveis, histórico e mensagem sobre reactivação separada. Flag local própria. Sem migração por GET ou activação.
5. Testes agrupados no fecho: domínio/transporte, SQL real com fixtures exclusivas, falha de auditoria e rollback, reenvio, concorrência e sessão. Playwright no laboratório 8892. Preservar dados anteriores. Revisão independente única, README, commit, push, CI exacta, merge e confirmação de main.

## Limites e revisão

Só conta fictícia dedicada, sem dados reais, permissões automáticas, Google ou produção. A guarda nativa existente recusa sessões pela revisão canónica e estado Suspended. Não apagar o histórico de sessões para simular revogação. Rever composição transaccional, ordem de bloqueios, reenvio depois de mudanças posteriores, duplicação de vínculos, rollback de autorização, separação entre reactivação profissional e decisão de acesso e preservação do catálogo antigo. Os testes locais não provam capacidade institucional ou IIS.
