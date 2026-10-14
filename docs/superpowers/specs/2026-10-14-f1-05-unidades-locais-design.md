# F1-05: unidades persistentes de laboratório

## Objectivo e limites

Primeira parcela de US-A03 e US-ARN-01. O utilizador autorizou avanço autónomo por fases no laboratório, com contas fictícias, auditoria, Playwright, commits e push. A estrutura persistente permite ensaiar criação, renomeação, mudança de superior e desactivação sem transformar o catálogo documental em organograma aprovado.

Só ambiente efectivo `local`, `ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE === true` e actor corrente com `manage_options` permitem consulta e alteração. Uma flag ausente, texto ou falsa fecha a funcionalidade. O actor é obtido no servidor. A instalação é explícita por WP-CLI com a mesma guarda e `WP_CLI === true`. Sem instalação por GET ou activação. Não alterar Google, sessões, concessões, pessoas ou catálogo documental. Nenhuma relação local atribui permissões. Sem emails, ficheiros de pessoas reais, REST ou dependências novas.

## Modelo

Reutilizar `Unit` e `UnitHierarchy` sem alterar os seus contratos. Guardar apenas as unidades sintéticas `LAB-ROOT`, `LAB-A` e `LAB-B`, com designações explicitamente fictícias. Não usar as siglas do catálogo institucional. Identificadores estáveis, tipo, superior, estado da relação e estado activo persistem. Um snapshot contém todas as unidades e uma revisão global inteira inicialmente zero. A revisão global serializa a validação da hierarquia inteira e evita ciclos por duas alterações concorrentes. Não apagar unidades.

Três tabelas próprias InnoDB: cabeçalho singleton com revisão, unidades e auditoria append-only da aplicação. O schema valida estruturas existentes e recusa incompatibilidades sem reparar. Usar ligação `IdentityWpdb` dedicada sem reconexão, identificadores validados e SQL preparado. A instalação idempotente não reinicia dados existentes. A leitura falha de forma fechada perante dados inválidos ou schema ausente.

Cada mutação bloqueia o cabeçalho, verifica revisão esperada, carrega e valida a árvore actual, aplica o cenário, valida a árvore resultante e grava unidades, nova revisão e evento na mesma transacção. Qualquer falha reverte tudo. Auditoria: revisão, actor, operação, snapshots anterior e posterior, justificação e instante. Repetição com revisão antiga não duplica o evento. Operação sem alteração é recusada. Corrupção ou unidades fora do catálogo sintético impedem a operação.

## Operações e interface

Página Ferramentas > Unidades locais, com lista, revisão, formulário e últimos eventos da auditoria. Aviso explícito de que são unidades fictícias sem efeito nas permissões. GET só lê, usa no-store e revalida a guarda antes de renderizar. A lista e histórico ficam apenas acessíveis ao administrador local autorizado.

O browser envia só cenário fechado, revisão e justificação curta obrigatória (1 a 500 caracteres), com POST e nonce. Cenários: `initialize` cria ROOT, A e B sob ROOT apenas quando vazio; `rename-a` muda A para uma segunda designação fictícia fixa; `move-b-to-a` muda B para A; `move-b-to-root` devolve B a ROOT; `deactivate-b` conserva B e relações no histórico. Não aceitar IDs, nomes, tipos, superiores, actores ou payloads arbitrários. O formulário explica cada cenário. Erros não expõem SQL, payloads ou segredos. Sem escrita durante render. Conflitos exigem recarregar, sem repetição automática.

## Verificação e sequência

TDD puro para os cenários, invariantes e limites de inputs. Integração WordPress/MariaDB com prefixo aleatório e contas exclusivas, limpeza em finally. Verificar criação, roundtrip, renomeação e ID preservado, mudança de superior, desactivação, duplicação, revisão antiga, falha de auditoria e rollback integral, schema incompatível, concorrência via bloqueio, autoridade e flags. Testar ciclos e superior inactivo através das regras reutilizadas. Não tocar nas fixtures persistentes durante integração.

Root instala a tabela local explicitamente após revisão do código, activa apenas a flag local e testa UI com Playwright: administrador permitido, conta fictícia comum e anónimo recusados, nonce/revisão/cenário adulterados, persistência após recarregar e apresentação móvel. Confirmar ausência de alterações na autorização e sessões de Ana. CI e revisão no SHA exacto antes de merge.

Cadastro e importação nesta parcela são apenas contratos documentados: validação prévia sem escrever, identificador profissional explícito, duplicados e unidades desconhecidas por linha, resumo de alterações e confirmação futura vinculada à revisão. Não inferir emails, identidades ou privilégios, nem desactivar ausentes. O formato e os campos institucionais aguardam RH. F1-05 permanece Em curso.
