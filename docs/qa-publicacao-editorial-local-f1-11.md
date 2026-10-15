# QA de publicação editorial local F1-11

15 de outubro de 2026. A execução da tarefa 5 mantém a configuração normal fechada: quinta flag ausente e esquema 1. Os testes de unidade e de integração exclusiva não substituem a aceitação em browser. O controlador guarda o baseline privado, revê o código e só então activa/migra/instala o elenco normal. Nunca guardar credenciais, cookies, URLs de sessão ou artefactos privados no Git.

## Prova automatizada da implementação

`NewsPublishedPortalTest` verifica versão aprovada durante revisão pendente, audiência legível sem destinatários, exclusão de rascunhos e publicações alheias da pesquisa e contagem, ausência de excepção do autor/editor, datas UTC inclusivas, ordenação antes da paginação e filtros inválidos antes de ler qualquer fonte. `NewsPublishedRenderingTest` renderiza o detalhe e verifica escape do texto, autor, data e ligações de media privados. `NewsPublishedHttpTest` executa o controlador real num servidor PHP loopback descartável com runtime fechado e confirma 404 vazio privado antes de um redireccionamento posterior, incluindo métodos errados, arrays e página errada, além da cache privada no início/lista. Usa adaptadores mínimos de página, sem base de dados, e não substitui o WordPress integral. O ensaio SQL exclusivo `tests/integration/news-editorial.php` verifica as projecções sobre o repositório real com as suas próprias contas e tabelas, incluindo alteração de audiência num rascunho e remoção por arquivo. Conserva as provas de concorrência e rollback existentes.

Os comandos de referência são `npm test`, `npm run lint`, `npm run build`, `npm run test:php`, `npm run lint:php`, `npm run test:news:integration`, `npm run test:news-editorial:integration`, `npm run test:news-editorial-installer:integration` e `npm run test:news-editorial-upgrade:integration`. Sem Docker, PHP local e o WordPress CLI privado podem executar os mesmos testes de laboratório. Isso não prova os bindings wp-env nem a compatibilidade com IIS/MySQL. Resultados exactos e limitações desta tarefa ficam no relatório SDD da tarefa 5.

## Aceitação integrada pelo controlador

| Ensaio | Resultado exigido | Estado no handoff da tarefa 5 |
| --- | --- | --- |
| Baseline e preservação | Três contas anteriores, catálogo documental, 22 originais e respectivos hashes conservados. Confirmar as cinco categorias da fonte real. | A confirmar após activação pelo controlador |
| Autoria e correcção | Criar com media sintéticos, submeter, rejeitar com motivo, guardar nova versão e reenviar. Pending bloqueia edição. | Pendente em browser |
| Dois editores | Editor suplente decide durante ausência do principal. Ambos só vêem versões submetidas. | Pendente em browser |
| Autor e outra autoria | Um autor não altera notícia alheia nem decide a própria. O administrador técnico não recebe competência. | Pendente em browser |
| Audiência A/B e pessoa | A lê publicação A e B recebe 404 vazio. Publicação individual só chega ao destinatário. Avisos seguem a mesma autorização. | Pendente em browser |
| Revisão da audiência | Texto, media e audiência anteriores mantêm-se durante Draft/Pending/Rejected. Só nova aprovação troca a publicação e os leitores. | Pendente em browser |
| Listagem e início | Pesquisa e filtros não revelam texto privado, títulos, contagens ou media fora da audiência. Início mostra últimos aprovados autorizados. | Pendente em browser |
| URLs directas e cache | Detalhe e media recusados, inexistentes, ID array, método errado e URL noutra página dão o mesmo 404 vazio privado antes do login. Sucesso/listas/início usam private/no-store. REST e editor nativo não expõem a notícia. | Pendente em HTTP/browser |
| Erro, CSRF e concorrência | Nonce errado, versão alterada e HMAC/CAS antigo não escrevem. Falha conserva campos autorizados e ordem dos media. Uma única decisão vence. | Pendente em browser, núcleo coberto em testes |
| Avisos e arquivo | Tipo/versão correctos, recibos idempotentes e autorizados. Arquivo retira publicação e leitura dos media pelo leitor comum sem apagar versões. | Pendente em browser |
| Responsividade e teclado | 320, 390, 768 e 1280 px, foco visível, controlos de 44 px, formulários de 16 px, navegação por teclado e leitura de galerias/anexos. | Pendente em browser |
| Ampliação | Registar o método e o resultado. CSS/text zoom simulado não é prova de zoom nativo. | Pendente em browser |
| Console e regressão | Registar erros observados. Não atribuir InvalidStateError de ViewTransition a esta tarefa sem isolar a causa. Confirmar navegação/fontes/logótipo/favicon e arquivo. | Pendente em browser |
| Revisão e CI | Revisão independente, reparações necessárias e CI verde no SHA exacto integrado. | A executar pelo controlador |

F1-11 permanece Em curso até homologação editorial e de infraestrutura. Nenhuma linha desta matriz confirma produção, certificação de acessibilidade ou autorização institucional a partir de uma prova local.
