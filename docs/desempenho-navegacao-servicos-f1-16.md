# Navegação dos serviços e desempenho, F1-16

Entrega local de 29 de outubro de 2026, baseada em main 1ba4385. Apenas laboratório fictício 8892. Não altera schema, concessões, contas, dados de negócio ou configuração de produção.

Pedidos e suporte resolviam as vistas pessoal e de equipa através de duas chamadas independentes a access. Cada chamada repetia a transacção e as verificações de esquema, conta, sessão e unidades. Repository::navigation calcula agora as duas vistas numa única transacção, conservando os mesmos bloqueios e a validação final. Portal e Navigation usam esse resultado apenas na leitura corrente. Não existe cache de autorização nem aumento do limite PHP.

O padrão coincide com a navegação já entregue nas férias. As chamadas access existentes mantêm o contrato para os restantes consumidores. Se a preparação ou a transacção falhar, não são apresentadas ligações desse módulo. Abrir uma página ou executar uma operação continua a exigir autorização própria.

## Verificação

O novo ensaio SQL compara os resultados com as duas leituras anteriores nos quatro perfis fictícios de cada módulo. Confirma uma transacção, menos consultas, ausência de escrita, recusa de sessão ausente e expiração antes do commit. O primeiro ensaio contra a cópia anterior do laboratório falhou porque o método novo não existia. Depois de aplicar os seis ficheiros do runtime, passaram 86 verificações de pedidos e 76 de suporte. Cada harness preservou as 191 tabelas anteriores e removeu apenas os seus recursos exclusivos.

PHPUnit: 1166 testes, 5806 asserções e sete omissões existentes no Windows. Node: 61 testes. Lint, build, PHPCS dos nove ficheiros PHP e git diff --check passaram. Os testes foram agrupados no fecho conforme o pedido.

O diagnóstico da conta fictícia Alfa mediu a navegação em 355 consultas antes e 246 depois. A composição dos avisos passou de 679 para 570. São 109 consultas a menos por leitura, aproximadamente 31% e 16%, respectivamente. O resumo dos serviços conservou 228 consultas. Este resultado não representa todos os perfis nem um ensaio de carga.

Os tempos não são directamente comparáveis: o primeiro diagnóstico posterior correu em paralelo com o browser sobre as mesmas contas. A página atingiu o limite PHP e esse ensaio não permite separar o efeito da concorrência. A ocorrência fica conservada na evidência privada, sem atribuir ao código uma melhoria de latência ainda não demonstrada.

O browser identificou também uma ambiguidade no teste: a ligação de avisos pessoais tinha o rótulo Suporte TI. A asserção passou a distinguir o destino com notices da vista de gestão. Não se alteraram as concessões ou a navegação para fazer o teste passar.

O fecho sequencial passou 17 verificações Playwright: quatro perfis nos dois módulos, vistas pessoais/equipa, abertura das páginas, painel sem timeout, pesquisa, recibo já lido conservado e recusa anónima. O painel do solicitante de suporte abriu em 15,280 segundos e o do responsável em 18,635 segundos neste PC. Estes tempos ainda exigem melhoria. Não constituem uma meta institucional aceite. A prova dos perfis de pedidos foi conservada na primeira passagem sequencial e a retoma corrigiu apenas a asserção dos destinos de suporte.

A comparação final preservou integralmente os 133 conjuntos de linhas exteriores, os 46 conjuntos de negócio, os 22 registos de utilizadores e os 365 ficheiros inventariados. Os logins de ensaio alteram as sessões nativas das contas fictícias, por isso não se afirma igualdade de todos os metadados de sessão. Os backups de código, resultados e credenciais ficam fora do Git. Só integrar depois da revisão e CI do SHA exacto, seguida de confirmação da CI de main.

## Limites

Esta parcela reduz trabalho repetido, mas não fecha F1-16. Persistem consultas repetidas entre menu, directório de serviços, resumos e avisos. A optimização seguinte deve manter a autoridade e os dados no mesmo contexto transaccional, sem reutilizar permissões antigas. Faltam medições por perfil e volume, capacidade concorrente e metas aceites. O README conserva as restantes lacunas de recuperação, instalação institucional e homologação.
