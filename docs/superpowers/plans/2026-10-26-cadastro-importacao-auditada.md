# Cadastro e importação auditada, laboratório F1-05

Pedido autorizado: continuar o backlog com dados fictícios, testes agrupados no fecho, revisão, commit/push e integração apenas com CI verde. Esta entrega não altera o validador sintético anterior para lhe dar escrita. Acrescenta um circuito próprio.

## Contrato da entrega

Registo profissional independente de WordPress e Google. Quatro campos: professional_id, name, email, unit_id. CSV UTF-8, 16 KiB e 20 linhas por lote. Apenas LAB-PER-001 a LAB-PER-999, nomes Pessoa fictícia e emails example.invalid. Não há ficheiros temporários ou uploads persistidos. Fonte colada num formulário privado.

O gestor fictício dedicado tem concessão explícita HumanResources institucional. Administração técnica isolada não consulta nem importa o cadastro. Este primeiro circuito exige ReadHrRecord e WriteHrRecord institucionais, sem agregar âmbitos parciais. Instalação de schema e fixture apenas por CLI local explícita.

Pré-validação sem escrita. Confirmação HMAC ligada ao actor, sessão, conteúdo e revisões de cadastro/unidades, com validade de dez minutos. Confirmação revalida tudo na transacção. Não confiar em propostas devolvidas pelo browser. Um erro bloqueia o lote inteiro. A importação cria ou actualiza pelo identificador profissional e conserva todos os ausentes. Não cria contas, vínculos Google ou permissões. Não faz saída ou suspensão.

Persistência em três tabelas InnoDB próprias: singleton de versão/revisão/cadastro, recibos de lote e auditoria por revisão. Bloqueios na ordem actor, unidades e cadastro. CAS, mudança, recibo e antes/depois auditado no mesmo commit. Reenvio confirmado devolve o recibo sem repetir alterações, depois de verificar novamente a autoridade e o conteúdo. Recusar schema divergente e falhas de leitura. Nada é instalado por GET.

Página nativa privada com formulário, erros por linha, resumo da proposta, confirmação, cadastro e histórico limitado. POST com nonce, parser de campos fechado e 404 indistinto para acesso recusado. UI responsiva e texto em pt-PT. Não expor em REST, pesquisa ou directório enquanto não existir associação explícita aprovada.

## Execução e aceitação

1. Modelo, validação sobre snapshot corrente e prova de confirmação.
2. Schema, autoridade transaccional, recibos e auditoria.
3. Transporte WordPress, página e instalador local explícito.
4. Testes agrupados: domínio e fronteira HTTP, SQL real com prefixo exclusivo, concorrência/CAS/rollback/reenvio, Playwright com conta RH fictícia e negativas de administrador/leitor/anónimo. Conservar contas, tabelas e ficheiros anteriores.
5. Revisão independente da branch e correcção dos achados. README e relatório de aceitação. Commit/push, CI do SHA exacto, merge e verificação de main.

Continuam separados: decisões RH sobre dados reais, manutenção individual de estados, delegações persistentes e associação profissional-conta. O circuito novo não executa esses efeitos implicitamente. O pedido de agrupar testes prevalece sobre a sequência TDD da skill. Não alegar prova RED/GREEN anterior à implementação.
