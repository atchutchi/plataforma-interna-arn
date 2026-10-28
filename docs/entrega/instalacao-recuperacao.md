# Versão, instalação e recuperação

Preparação F1-17, 28 de outubro de 2026. Este é um guião para preparar e aceitar a entrega. Não é um instalador institucional. A aplicação continua com opções e instaladores limitados ao laboratório. Faltam as decisões e provas da secção de deploy do README.

## Inventário reproduzível

A partir da raiz do checkout, executar `node scripts/release-inventory.mjs SHA_COMPLETO`, substituindo o argumento pelo commit exacto aprovado. O programa escreve JSON no stdout. Guardar o resultado num ficheiro privado fora do repositório. Exige Git e Node 24.21.0.

O resultado contém commit, árvore Git, caminhos, modo, tamanho e SHA256 das fontes Composer, plugin e tema. Lê blobs do Git, não os ficheiros modificados do checkout. Recusa referências abreviadas, objectos que não sejam commits, fontes obrigatórias ausentes, ligações simbólicas, submódulos e caminhos privados reconhecidos dentro do âmbito. Dados, dependências geradas e WordPress exteriores ao âmbito não entram no inventário.

O inventário não contém bytes dos ficheiros nem segredos locais. Não é um scanner universal de segredos: material indevidamente versionado com nome comum exige revisão própria. Não confirma a segurança dos conteúdos, a aprovação da CI ou a integridade de uma instalação. O atributo deployReady permanece false. Para verificar ficheiros instalados, comparar separadamente os seus hashes com os blobs aprovados e registar o resultado. Alterações locais deliberadas exigem tratamento explícito, nunca substituição silenciosa do manifesto.

Não chamar `npm run build` um empacotamento. Esse comando verifica a fundação, sem produzir um ZIP instalável. O inventário de fontes também não inclui vendor, núcleo WordPress, configuração, base de dados, armazenamento privado ou assinaturas do scanner.

## Ficha de release a preencher

| Campo | Evidência exigida |
| --- | --- |
| Versão | SHA completo, PR integrada e SHA de main |
| CI | Todos os controlos aprovados no SHA exacto e em main |
| Fontes | Inventário JSON e hashes conferidos |
| Dependências | composer.lock, instalação sem dependências de desenvolvimento e inventário de vendor |
| Runtime | Versões efectivas de WordPress, PHP/extensões, SQL, scanner, assinaturas, imagem e PDF |
| Configuração | Referências privadas, responsáveis e diferenças por ambiente, sem valores secretos |
| Dados | Schema por módulo, migrações revistas e compatibilidade com a versão anterior |
| Recuperação | Backup consistente, prova de restauro, RPO/RTO aprovados e responsável |
| Aceitação | Resultados por perfil, recusas, desempenho, acessibilidade e decisão piloto |

A selecção de um commit é apenas o início desta ficha. Não pré-preencher aceitação institucional com resultados de laboratório.

## Preparar instalação representativa

Confirmar primeiro o servidor autorizado, rede, HTTPS, callback institucional, acesso administrativo, responsáveis e janela. No desenvolvimento actual não se acede a produção, DNS ou controlador de domínio.

Preparar um destino isolado com versões aprovadas e volumes privados fora do document root. Usar apenas dados fictícios na primeira instalação. Manter saída de notificações externas e Calendar desactivada. Conferir permissões de leitura/escrita do processo e recusa de acesso HTTP aos volumes privados.

Obter o commit aprovado numa origem limpa. Instalar as dependências fixadas em composer.lock num ambiente de preparação compatível, sem dependências de desenvolvimento, e validar os requisitos reais da plataforma. Não usar `composer update` para montar uma release. O plugin aceita o caminho privado ARN_OIDC_VENDOR_AUTOLOAD ou procura vendor/autoload.php na raiz WordPress. Confirmar o autoload efectivo sem expor caminhos e credenciais na interface pública.

Inventariar cada schema e a versão instalada antes de qualquer mudança. Rever os instaladores LocalInstaller e os relatórios dos módulos. Os comandos `arn ... install` actuais são circuitos de laboratório, alguns com criação de fixtures. Não executá-los sobre dados reais nem tratá-los como upgrade. Uma instalação institucional precisa de migrações próprias revistas e de ensaio de versão anterior para seguinte. Essa entrega ainda falta.

Só activar módulos após instalação íntegra e verificação das suas dependências. Não activar flags fictícias para contornar os controlos de ambiente. Confirmar que contas técnicas não ganham competências de negócio e que o utilizador sem função não vê objectos de outros perfis.

## Aceitação piloto

| Perfil/circuito | Resultado exigido |
| --- | --- |
| Anónimo e conta suspensa | Conteúdo, pesquisa, avisos, downloads, REST/AJAX e sessão anterior recusados |
| Funcionário | Conteúdo da sua audiência e objectos próprios, com recusa de unidades alheias |
| Editorial/documental | Rascunho, decisão, versão publicada, anexos privados e conflito de revisão |
| RH | Pedido, devolução, decisão e férias com saldo/overlap, sem autoaprovação |
| TI | Abertura, assunção, resposta, solução e isolamento de tickets |
| Operador de media | Análise, retenção, limites, falha de dependência e indeterminação conservada |
| Administrador técnico | Diagnóstico/configuração sem acesso de negócio implícito |
| Recuperação | Histórico íntegro, recusas e competências correctas após restauro |

Executar também testes de concorrência, carga, reflow, zoom, teclado, leitor de ecrã e segundo PC. Fixar metas de capacidade e latência antes da aceitação. A medição actual de 12,6 segundos no painel não fecha este requisito. Google Calendar exige calendário dedicado, audiência e consentimento específico, além de envio/revogação/reconciliação idempotentes ainda por implementar.

## Backup e reversão

Preparar um backup consistente de base de dados, configuração protegida, volumes privados, diários, recibos e versão do código. Suspender escritas e workers durante a captura quando não existir mecanismo comprovado de snapshot consistente. Registar o ponto de captura e verificar os hashes. Proteger chaves e segredos por canal próprio, nunca num arquivo público.

Ensaiar o restauro num destino isolado, com integrações externas desactivadas. Restaurar o conjunto consistente, não apenas a base de dados. Conferir versões, blobs, referências, auditoria, filas e autorização antes de permitir sessões. Sessões recuperadas não devem ser assumidas válidas sem verificação da política e do estado corrente.

Para um incidente, conservar primeiro o estado posterior e impedir novas escritas. Determinar se o código anterior consegue ler os schemas, históricos e diários novos. Se essa compatibilidade não estiver demonstrada, não repor só o tema/plugin. A alternativa exige um restauro conjunto aprovado e a avaliação explícita dos dados posteriores ao ponto de captura. Não apagar originais ou decisões para ajustar o backup.

O restauro só é aceite após reconciliação, testes de permissões e circuitos por perfil. Guardar tempos medidos e perdas identificadas. Não há ainda prova de restauro institucional nem RPO/RTO aprovados. Recuperação automática de conclusões indeterminadas continua fora desta entrega.
