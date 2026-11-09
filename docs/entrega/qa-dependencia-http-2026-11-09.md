# Correcção de segurança da dependência HTTP

Continuação da PR #80 sobre 8af1969, em 9 de novembro de 2026. Âmbito local e fictício. Não altera a configuração Google, contas reais, DNS, servidor .205 ou runtimes dos laboratórios 8888/8892.

## Resolução e auditoria

O wrapper do projecto arrancou um ambiente wp-env exclusivo na porta 8890. A verificação efectiva confirmou WordPress e MariaDB em execução e todas as portas publicadas em loopback. O primeiro controlo, realizado ainda durante a inicialização, recusou correctamente um ambiente incompleto. O controlo após o arranque passou.

`npm run composer:update:http-client` gerou o lock dentro do contentor. O resolver substituiu somente symfony/http-client v8.0.16 por v7.4.23, sem instalar ou remover pacotes. Os restantes nomes, versões e referências dos pacotes de produção e desenvolvimento foram conservados. Composer acrescentou metadados published-time e actualizou plugin-api-version. Não houve edição manual do lock nem alteração de composer.json.

O requisito declarado continua a ser PHP 8.4.0. `composer why-not symfony/http-client 8.1.11` confirmou que essa versão exige PHP >=8.4.1. A resolução seleccionou a versão corrigida 7.4.23 compatível com o requisito existente. Mantêm-se Facile 1.0.0 e Guzzle 7.15.5. O runtime efectivamente ensaiado no contentor é PHP 8.4.27, não uma homologação de todas as versões PHP admitidas.

`npm run composer:install` confirmou que o novo lock não exige outras alterações. `npm run composer:audit`, com `--locked --no-dev`, passou sem avisos. A CI executa agora a mesma auditoria depois da instalação e antes de PHPUnit. Não foram introduzidas excepções, avisos ignorados ou desactivação do bloqueio de segurança.

Os avisos corrigidos são [GHSA-gq53-v8w2-gjhj](https://github.com/symfony/symfony/security/advisories/GHSA-gq53-v8w2-gjhj) e [GHSA-c7hm-wqwp-f9v8](https://github.com/symfony/symfony/security/advisories/GHSA-c7hm-wqwp-f9v8). A auditoria reflecte a base de avisos disponível na execução, não uma garantia de ausência de vulnerabilidades futuras.

## Regressão e validação

Antes da actualização, o novo HttpClientSecurityRegressionTest executou três cenários com MockHttpClient, sem rede e com uma credencial fictícia. A referência de rede para outra origem recebeu indevidamente o cabeçalho Authorization. O teste falhou nesse caso e passou nos controlos relativo e absoluto. Isso reproduziu o primeiro aviso no cliente real da dependência. A falha não demonstra exploração na plataforma, cujo adaptador OIDC usa Guzzle explicitamente.

PHPUnit na cópia Linux verificada: 1217 testes, 5991 asserções e uma omissão no teste FIFO de MediaHealth por indisponibilidade de pcntl_alarm nesse runtime. Inclui os três cenários da nova regressão, agora aprovados. A CI Linux conserva a validação da plataforma do seu runner. Integração WordPress/MariaDB: 21 verificações de persistência, constraints, auditoria e rollback, mais 24 da sessão federada com fornecedor simulado. Não foram usadas credenciais Google reais.

Playwright na porta 8890: seis verificações aprovadas. Formulário de login disponível, botão Google ausente sem configuração, início e administração protegidos para anónimo e início/callback Google recusados com HTTP 403 e Referrer-Policy no-referrer. Browser de testes separado e fechado após captura. Não se alterou a sessão do utilizador na demonstração.

### Descoberta incompleta no mount Windows/WSL

A primeira execução no bind mount passou apenas 440 testes e 2168 asserções. Não foi aceite como suite completa. A comparação com Windows mostrou 1217 testes esperados. No mesmo directório, glob encontrou 150 entradas e scandir 152 incluindo os pontos, mas DirectoryIterator encontrou apenas 109. O iterador usado por PHPUnit seleccionou 65 dos 145 ficheiros Test.php. O problema corresponde à limitação documentada de seek/readdir em v9fs, [WSL #5074](https://github.com/microsoft/WSL/issues/5074). A evidência local demonstra a divergência de enumeração, sem pretender diagnosticar todo o kernel.

Foi criada uma cópia privada em /tmp, dentro do mesmo contentor, com fontes e vendor. Os hashes dos 6295 ficheiros foram conferidos contra o manifesto criado no Windows antes de executar. A cópia Linux descobriu os 145 ficheiros e executou os 1217 testes. Não houve resolução Composer fora do contentor nem alterações ao código de terceiros para contornar o problema.

scripts/check-php-test-discovery.php compara uma enumeração por scandir com a lista de ficheiros usada por PHPUnit, recusando divergência, catálogo vazio ou ligações simbólicas. A guarda falhou no mount afectado, com 145 esperados e 65 encontrados, e passou na cópia Linux. Corre antes de PHPUnit no comando npm e na CI. Não se deve remover esta guarda para obter uma execução verde. A execução PHPCS parcial no mount também não é a prova final de cobertura.

PHPCS final na cópia Linux: 662 ficheiros aprovados. Node: 72 testes aprovados, sem omissões. Lint e build aprovados.

## Revisão independente

O delta 8af1969..00d2b9b foi revisto em leitura, sem achados críticos, importantes ou menores. A revisão confirmou a alteração de um só pacote, a referência oficial da tag corrigida, os dois avisos, os requisitos PHP, a regressão e o uso do mesmo iterador de PHPUnit na guarda. Não repetiu as suites ou os hashes privados. A ferramenta de candidatos anterior já tinha sido revista e corrigida separadamente.

As decisões mantidas são a resolução dirigida no contentor, a conservação de PHP 8.4.0 como requisito e a recusa do verde parcial no mount, substituído por prova numa cópia Linux conferida. A guarda verifica o catálogo de testes actual, não eventuais exclusões futuras nem toda a enumeração de PHPCS. A prova de PHPCS nesta execução é a corrida nos 662 ficheiros da cópia Linux.

A revisão não homologou Google real, exploração efectiva, o segundo aviso através de rede, runtime PHP 8.4.0, servidor, contas reais ou recuperação de discos. Esses limites ficam conservados. A selecção da versão corrigida e a auditoria sustentam a correcção do segundo aviso, sem alegar ensaio de rede. A CI do SHA exacto e a confirmação de main são verificadas separadamente antes de declarar integração.

## Recuperação do ambiente Docker

O Docker Desktop 4.83 não conseguia iniciar o motor devido ao endpoint dockerInference. Foi instalada a versão oficial 4.98.0, depois de verificar SHA-256 publicado e assinatura Authenticode válida de Docker Inc. Ver [registo do fornecedor](https://github.com/docker/desktop-feedback/issues/625) e [notas oficiais](https://docs.docker.com/desktop/release-notes/).

Antes da instalação, os dois discos VHDX existentes e a configuração foram copiados para a área privada. Os hashes das cópias foram comparados aos originais. Após a instalação e antes do primeiro arranque, os hashes dos originais continuavam iguais. O motor 29.9.2 arrancou. Não se fez reset, eliminação de volumes, unregister de WSL ou aceitação automática de novos termos. O crescimento posterior do disco com imagens de testes é esperado e não está abrangido pela comparação anterior ao arranque.

Backups, instalador, logs e artefactos de QA ficam fora do Git. A recuperação do Docker e esta correcção não fecham a adaptação institucional, migrações sem fixtures, operação persistente, Calendar, IIS/MySQL, restauro ou aceite piloto. O candidato antigo com o lock vulnerável continua impróprio para instalação e não é actualizado implicitamente.
