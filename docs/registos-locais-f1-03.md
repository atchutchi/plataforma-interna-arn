# Leitura de registos fictícios por âmbito F1-03

14 de outubro de 2026. Esta etapa liga as concessões guardadas à autorização de uma lista e de um detalhe no WordPress local. Usa três registos sintéticos em código. Não implementa um cadastro de funcionários ou um módulo documental institucional.

## Contrato

Ferramentas, Registos fictícios, só funciona para Ana Teste marcada, com sessão nativa válida, estado Active e revisão corrente. Exige ambiente local e as duas flags booleanas do laboratório. A capacidade nativa read não substitui estas condições. lab_admin não recebe acesso funcional por ser administrador WordPress.

O catálogo fixa um registo próprio e dois de unidades fictícias A e B. O servidor define titular, âmbito, acção ReadDirectory e provas do objecto. O browser escolhe apenas uma chave de registo. A lista filtra antes de contar e o detalhe directo reavalia a mesma política. Um objecto recusado e uma chave inexistente recebem a mesma resposta genérica404. Falhas de persistência ou de sessão recusam acesso. Não existem novos endpoints REST, escritas ou exportações.

As alterações de concessões continuam auditadas no serviço transaccional existente. Consultar um registo não altera esse histórico. O ensaio mantém a concessão própria original e revoga no fim a concessão de unidade criada para testar.

## Verificação

PHPUnit completo local: 576 testes e 1995 asserções. Depois foi acrescentado um teste explícito do catálogo, com verificação focada de 19 testes e 95 asserções. Node: 54 testes. PHPCS global: 118 ficheiros sem erros. Lint e build concluídos. A integração de sessões WordPress/MariaDB passou 78 asserções, incluindo snapshot real, leitura por âmbito, geração de sessão, auditoria de concessões, falha SQL e preservação do papel nativo. Usa contas e tabelas exclusivas e limpeza no fim, sem activar flags de laboratório na CI.

Playwright verificou a lista e o detalhe próprio, inicialmente com um registo disponível. A unidade A, a unidade B, uma chave inexistente, uma chave em array e parâmetros de autoridade forjados receberam 404 com o mesmo texto, sem títulos dos alvos. A resposta definiu Cache-Control com no-store. O fluxo não apresentou erros JavaScript.

O administrador criou a concessão fictícia de unidade A com justificação, produzindo o evento grant_created e a revisão 18. A sessão anterior foi recusada. Após novo login, a lista mostrou apenas o registo próprio e o da unidade A, com contagem dois. O detalhe A abriu e B continuou a devolver 404. Revogar a concessão produziu grant_revoked e revisão 19. A sessão anterior voltou a ser recusada. Após novo login, a lista regressou a um registo e A ficou novamente inacessível por URL directa.

O visitante foi encaminhado para login sem dados da fixture. lab_admin recebeu a recusa nativa de acesso à página. A interface foi inspeccionada a 375, 768 e 1440 pixels, sem deslocamento horizontal. Capturas locais ignoradas pelo Git: output/playwright/registos-locais-unidade-a.png, registos-locais-375.png e registos-locais-1440.png.

Estado final confirmado por leitura da persistência: Ana Teste Active, revisão 19, papel subscriber, seis concessões históricas e 19 eventos. A concessão própria original ID 3 é a única vigente. A concessão de ensaio ID 6 foi revogada. O histórico anterior foi preservado e as consultas não criaram eventos de mutação. Os serviços portáteis continuaram ligados exclusivamente a 127.0.0.1, portas 8888 e 33317.

## Limites

A autorização institucional por objecto ainda exige contratos dos módulos, matriz validada, F0-02/F0-06 e homologação. Este ensaio confirma a ligação local entre sessão, snapshot persistente e motor. Não conclui F1-03 institucional nem autoriza F2 ou produção.
