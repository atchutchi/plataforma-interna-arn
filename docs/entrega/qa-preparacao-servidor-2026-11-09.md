# Preparação F1-17, evidência de 9 de novembro

Base integrada: 79afcaf58455a6997d49d1f9f5d8587033466321. Branch de trabalho: feat/preparacao-entrega-servidor-f1-17. Não houve instalação no servidor, modificação de DNS, aplicação de código aos laboratórios, migrações ou operações de contas/conteúdo.

## Resultado técnico

Preparação de 459 fontes por blobs Git e instalação isolada de 29 dependências Composer de produção. O candidato contém 1568 ficheiros incluindo vendor. Requisitos Composer verificados em PHP 8.4.26. Estes números descrevem o candidato da base, não uma homologação Windows/IIS/MySQL. Os artefactos, logs e recibos ficam na área privada, fora do Git e do document root.

Suite Node: 70 testes aprovados, sem omissões. Inclui nove testes do candidato com subcasos de destino existente/interno, caminhos Windows, colisão de nomes, fontes alteradas, dependências divergentes/de desenvolvimento, ficheiros adicionados/removidos, adulteração do manifesto, junctions/symlinks e hard links. Lint e build da fundação aprovados. Não há alteração PHP nesta parcela.

A primeira corrida detectou dois problemas de preparação dos testes: NODE_PATH ausente para a dependência wp-env deste worktree e fixture de colisão fora do âmbito inventariado. Foi reposto o caminho da dependência já instalada e o cenário passou a criar duas grafias dentro do mesmo tema. A suite completa seguinte passou. Não foram enfraquecidas as recusas.

Playwright: três verificações aprovadas, login disponível, início protegido para anónimo e administração protegida para anónimo. Inicialmente a porta 8892 estava fechada. O responsável arrancou HTTP, mas o primeiro acesso devolveu erro de base de dados. Foi arrancada a instância MariaDB local já preparada, confirmada em loopback na configuração existente, sem reinstalação ou alteração de configuração. O ensaio final passou. A parcela não altera páginas ou código PHP. Não se usou a demonstração 8888 como substituto. Estes três controlos não repetem a aceitação integral dos módulos.

## Bloqueio de segurança para entrada em serviço

`composer audit --locked --no-dev --format=json` terminou com código 1. O pacote symfony/http-client v8.0.16 tem dois avisos publicados em 4 de novembro:

| Aviso oficial | Descrição |
| --- | --- |
| [GHSA-gq53-v8w2-gjhj](https://github.com/symfony/symfony/security/advisories/GHSA-gq53-v8w2-gjhj) | ScopingHttpClient pode enviar credenciais para outra origem através de uma referência de rede. |
| [GHSA-c7hm-wqwp-f9v8](https://github.com/symfony/symfony/security/advisories/GHSA-c7hm-wqwp-f9v8) | NoPrivateNetworkHttpClient não fixa o IP já verificado em pedidos posteriores, permitindo enumeração de portas por DNS rebinding. |

A presença da dependência não demonstra exploração ou uso desses adaptadores pela aplicação. A pesquisa directa no src não encontrou estas classes. Isso não dispensa corrigir a dependência antes do deploy. A versão corrigida para a linha 8 indicada pelos avisos é 8.1.11. A versão compatível efectiva deve ser resolvida e ensaiada, não escrita manualmente no lock.

O Docker está instalado, mas o motor Linux não responde. AGENTS.md exige a geração do lock no ambiente de contentor do projecto. Após tentativa de arranque do Docker Desktop, o log reporta falha no serviço Inference manager ao abrir o socket dockerInference. Não se executou reset de fábrica nem se apagaram ficheiros do Docker. O lock, o vendor dos laboratórios e as versões existentes não foram alterados. O candidato mantém deployReady=false e não está aprovado para instalação. Uma futura CI verde desta ferramenta não elimina os avisos.

## Limites da entrega

O plano global, responsáveis conhecidos, proposta DNS/HTTPS, mapa de adaptação e comandos de preparação estão documentados. Continuam pendentes a correcção da dependência, adaptação institucional, migrações sem fixtures, operação persistente, Calendar, servidor autorizado, restauro e aceite piloto. Não confundir selagem de hashes com auditoria de segurança, pacote autónomo ou prontidão para uso.
