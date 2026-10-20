# Revisão da PR #53 e fecho de SET NAMES

20 de outubro de 2026. Base main c049c881a7770d00213900bd73cacebc386285cd. Branch própria fix/fronteira-set-names-local. A PR #53 em b7609351be29339f3d9343a8f26ca33a6effc7ca tem CI aprovada, mas a revisão final encontrou um bloqueador. Não integrar esse head sem esta correcção.

A recusa inicial de GLOBAL, PERSIST, PERSIST_ONLY, SESSION/LOCAL TRANSACTION e das quatro variáveis transaction_*/tx_* é aproveitada dos três ficheiros da proposta. A branch original permanece intacta. As PRs #39/#41 continuam excluídas.

O ramo SET NAMES terminava antes de analisar opções posteriores. Instruções como SET NAMES utf8mb4, GLOBAL max_connections=100 ou SET NAMES utf8mb4, SESSION tx_isolation='READ-UNCOMMITTED' eram classificadas como trusted. Uma ligação mysqli exclusiva confirmou que PREPARE aceita a gramática MariaDB, sem EXECUTE. Não foram alteradas variáveis globais.

A correcção fecha SET NAMES à forma isolada UTF8/UTF8MB3/UTF8MB4, com COLLATE opcional. Conserva os valores citados usados pelo WordPress e SET TRANSACTION ISOLATION LEVEL READ COMMITTED sem âmbito. Uma segunda opção, mesmo uma variável de utilizador, é recusada nesta forma. Atribuições de utilizador continuam disponíveis por SET separado.

A regressão reproduziu a falha no primeiro caso novo: 15 testes, 257 asserções e uma falha. Depois da correcção, 15 testes e 316 asserções passaram. PHPCS dos três ficheiros sem erros ou avisos. A revisão independente final confirmou o fecho do achado e não identificou regressões concretas.

O complemento na MariaDB local passou 461 verificações. As instruções proibidas são classificadas antes de chamar o motor. A contagem de queries confirma que não foram enviadas, dentro ou fora de transacção. As duas transacções seguintes conservam escrita e isolamento, sem leitura suja. O ensaio remove os seus recursos exclusivos. Configuração, logs e provas permanecem privados.

A suite completa passou 903 testes e 4276 asserções, com quatro omissões Windows preexistentes. Os 55 testes Node passaram. A CI do novo head continua obrigatória antes de integrar. Não equivalem a homologação IIS/MySQL institucional, autorização de produção ou conclusão F1-13. O laboratório estável não recebe código com falhas. Depois da integração validada, retomar o fecho das tarefas na branch própria.
