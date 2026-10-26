# Pedidos e suporte no painel, pesquisa e avisos

Entrega local de 26 de outubro de 2026. A composição usa os módulos Requests e Support já integrados. Não cria competências, contas ou permissões. O administrador técnico não adquire acesso aos processos de negócio.

O painel apresenta até três pedidos próprios ainda não terminados e três tickets próprios novos ou em tratamento. A escolha aplica a autorização e o estado antes da paginação. Mostra assunto, referência, estado e data de actualização. Não projecta descrições, conversas, motivos ou anexos. A lista própria não se transforma numa lista da equipa quando o utilizador também possui competências de gestão.

A pesquisa integrada acrescenta Pedidos internos e Suporte TI. Os repositórios nativos revalidam a sessão, o estado da conta, as unidades e as competências correntes. Unem resultados próprios e de equipa sem duplicados. Os rascunhos de pedidos permanecem privados. A ligação prefere a vista própria quando ambas são autorizadas. As contagens só incluem resultados permitidos e a falha de uma fonte não remove as restantes.

Os avisos conservam a data e a leitura do módulo original. Consultar o painel não marca avisos como lidos. As ligações abrem páginas nativas autorizadas e voltam a validar o acesso. Não há envio de emails nem novas rotas REST com processos privados.

## Verificação

A revisão independente identificou um erro que trocava as chaves simbólicas de navegação pelos identificadores numéricos das páginas. O teste de regressão falhou antes da correcção e passou depois. O adaptador conserva agora as chaves dos destinos nativos. A suite PHP passou 1153 testes e 5750 asserções, com sete omissões Windows anteriores. PHPCS passou nos 20 ficheiros PHP alterados ou novos. Os 55 testes Node, lint e construção passaram.

Os ensaios SQL passaram 73 verificações de pedidos e 63 de tickets, incluindo as projecções sem escrita, isolamento, filtragem antes da paginação e união sem duplicados. Preservaram as 186 tabelas anteriores. Uma falha intermitente na fixture de substituição de pedidos foi corrigida usando o mesmo relógio fixo no campo de entrada e na transacção. Não se alterou a regra de validade em produção. Os testes separados de expiração continuam activos.

Playwright passou 30 verificações de painel, pesquisa e ligações nativas, avisos, perfis de titular, outra unidade, suporte e RH, rascunhos privados, recusa directa, anónimos, cache, REST e reflow a 320/390/768/1440 píxeis. Foram inspeccionadas as imagens de desktop, telemóvel e das novas secções. Uma expectativa incorrecta do script exigia HTTP403 em vez do HTTP404 intencional do controlador. A falha ficou conservada e a verificação corrigida passou sem mudar a aplicação.

A comparação final passou 244 asserções. Conservou integralmente 133 tabelas exteriores, as linhas anteriores de 41 tabelas de negócio, 22 contas e 365 ficheiros. Acrescentou apenas um pedido em rascunho, um ticket e os respectivos registos auditados, além de uma página fictícia de painel. Não marcou os avisos anteriores como lidos nem criou novos binários. As credenciais e a evidência HTTP permanecem fora do Git.

Férias entram numa entrega posterior com o âmbito local agora confirmado em [decisões de 26 de outubro](decisoes-laboratorio-2026-10-26.md). A integração exige CI verde no SHA exacto e confirmação de main. Esta entrega não certifica IIS nem autoriza deploy.
