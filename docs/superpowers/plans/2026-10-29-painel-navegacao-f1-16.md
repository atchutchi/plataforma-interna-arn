# F1-16: leituras de navegação dos serviços

Execução directa com superpowers:executing-plans e revisão final independente. Especificação: README, desempenho do painel e preservação das regras F1-03/F2. Base main 1ba4385.

Objectivo: eliminar a segunda transacção usada apenas para resolver as vistas pessoal/equipa nos módulos Requests e Support. Conservar todas as validações de esquema, conta, sessão, concessões, unidades e a guarda final. Não introduzir cache de autorização, mudança de permissões ou novo schema.

Implementação: acrescentar Repository::navigation(): array em ambos os módulos, calculando own/team com accessFor dentro de transaction. Expor Portal::navigation e consumir o resultado uma vez em Navigation::destinations. Conservar access para os restantes consumidores. O padrão já existe em Leave.

Verificação agrupada no fecho: acrescentar ensaio SQL comum nos dois harnesses exclusivos. Comparar o resultado com as leituras separadas para quatro perfis, contar uma transacção e menos consultas, verificar ausência de escrita e recusa se a sessão expirar antes do commit. Medir no laboratório antes/depois, validar menu/painel/pesquisa/avisos com Playwright e preservar as fixtures existentes. Não recriar pedidos ou repor revisões.

Ficheiros: Requests e Support, Repository.php, Portal.php e Navigation.php. Testes: tests/integration/service-navigation-cases.php e inclusão nos dois ficheiros de casos. Evidência em docs/desempenho-navegacao-servicos-f1-16.md. Actualizar README, commit/push e integrar apenas com CI exacta verde, seguida de confirmação de main.

Foco de revisão: recusa por sessão expirada, ausência de vista de equipa para funcionário, erro de schema sem exposição de menu, igualdade de âmbitos e preservação da guarda final. A redução de consultas não constitui aceitação global de carga ou latência.
