# Migrações institucionais: diagnóstico inicial

Entrega de 12 de novembro de 2026. Primeiro passo operacional, exclusivamente de leitura. Não instala tabelas nem activa login ou módulos. Não importa o laboratório.

## Comando

Com o núcleo ARN carregado, executar no WP-CLI com a conta administradora técnica explicitamente seleccionada:

```text
wp arn institutional preflight --user=<administrador-tecnico> --path=<raiz-wordpress>
```

O comando recusa argumentos e opções adicionais, execução sem manage_options e multisite. Não há opção apply. A ligação dedicada usa a configuração privada do próprio WordPress e o prefixo desse site. A saída JSON contém apenas a versão do diagnóstico e estados estruturais, sem identidades, credenciais ou conteúdo de contas. Erros usam uma mensagem constante e código de falha.

identity pode ser absent, current, legacy ou partial-generations. Os dois últimos exigem migração explícita antes do acesso. authorization só pode ser absent ou current. Autorização parcial, versões desconhecidas e estruturas incompatíveis fazem o comando falhar. O diagnóstico reutiliza os validadores persistentes e acrescenta recusa de colunas inesperadas nas tabelas de identidade. Não é uma auditoria completa de privilégios, triggers ou integridade dos dados.

write_performed e activation_permitted são sempre false. Uma resposta current não prova homologação de Google, competências institucionais ou prontidão de produção. O diagnóstico não bloqueia escritores concorrentes nem constitui um plano aplicável posteriormente sem nova validação.

## Próxima entrega

Preparar aplicação explícita versionada, manutenção exclusiva verificável, bloqueio de concorrência, recibo durável e retoma de DDL interrompida. Validar instalação vazia, upgrade e restauro antes de disponibilizar execução no servidor. DDL não tem rollback transaccional. Uma tabela de autorização parcial continua recusada nesta entrega, exigindo diagnóstico próprio.

Os pré-cadastros existentes, os seus prazos e as sessões antigas não são convertidos automaticamente. Nenhum instalador local de fixtures faz parte deste circuito. Depois da base de identidade seguem as migrações e a composição explícita de cada módulo.

## Verificação

O ensaio SQL institucional usa prefixo aleatório e dados fictícios descartáveis. Cobre ausência, schema actual, legado, gerações parcialmente migradas, autorização parcial, colunas inesperadas, versão desconhecida, repetição e conservação do histórico. Observa as queries submetidas ao wrapper para exigir apenas SELECT e SHOW. As sondas internas directas de IdentityWpdb, incluindo SAVEPOINT e RELEASE, ficam fora desse observador e não escrevem dados ou schema persistentes. A suite continua a testar callback, sessões e revogação. Esta entrega não altera páginas HTTP e não exige um novo percurso Playwright.

Nenhum comando desta entrega foi aplicado ao servidor .205. Wamp, dados reais e laboratório de demonstração permanecem fora do âmbito.

Fecho local: 77 verificações SQL aprovadas com remoção dos dados fictícios, CLI autorizada com resposta JSON e CLI sem utilizador recusada com código 1. Descoberta completa de 149 ficheiros PHPUnit, 1230 testes e 6082 asserções aprovados, com sete omissões preexistentes. Node 72, lint, build e PHPCS dos cinco ficheiros PHP alterados aprovados. Revisão independente do delta sem bloqueantes. CI e integração são verificadas na PR deste commit.
