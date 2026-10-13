# Painel administrativo ARN, F1-06

13 de outubro de 2026. O responsável escolheu dar prioridade ao painel de administração WordPress. Esta entrega personaliza essa superfície no plugin existente. O tema Intranet ARN 0.2.0 já estava instalado e activo. O Núcleo da intranet ARN passa a 0.2.0 e mantém-se activo no laboratório.

## Apresentação

AdminDashboard acrescenta o widget Painel ARN com prioridade alta na coluna normal. Usa o logótipo original do tema e as fontes locais Source Sans 3 e Manrope, com fallback de sistema se o tema não estiver instalado. Não copia ou altera os ficheiros da marca. Os restantes widgets e o menu nativo conservam os seus mecanismos de organização e acesso. As boas-vindas genéricas e o widget de eventos/notícias WordPress deixam de aparecer nesta superfície. Não se apagam conteúdos ou preferências de organização.

DashboardLinks filtra os atalhos com as capacidades nativas efectivas: read, edit_pages, edit_posts, upload_files e manage_options. Páginas, artigos, multimédia e perfil apontam para os destinos nativos. Google e ensaios exigem também ambiente local e a presença da ferramenta no menu já registado. A renderização reconsulta as capacidades. Cada destino mantém a sua autorização própria. A escolha de atalhos não substitui o motor, as concessões ou a matriz institucional de F1-03.

O CSS do widget carrega apenas no index.php administrativo normal, não no perfil, nas ferramentas, nos editores ou no frontend. A paleta usa azul #0b3a5b, texto #1b242c e secundário #526373. As ligações têm foco visível, alvos de pelo menos 44 px, texto de apoio e ícones nativos com aria-hidden. O layout reflowa e a transição de hover respeita reduced-motion.

O esquema nativo ARN institucional fica disponível no perfil. Usa azul no menu e botões e verde na selecção. Foi escolhido apenas para lab_admin, como preferência visual de laboratório. A preferência anterior foi guardada fora do Git. As outras contas conservam o seu esquema. Não se forçam preferências por filtro nem se alteram capacidades, papéis ou vínculos. O esquema de cores aplica-se aos ecrãs administrativos dessa conta. É separado do CSS restrito do widget.

Não foram instalados plugins comerciais, page builders ou serviços externos. O trabalho está no plugin próprio da ARN. Não há novas dependências, alteração de composer.lock, acesso a produção ou configuração Google nova. A sessão Google de laboratório expirou durante o intervalo entre fases e foi renovada pelo fluxo já autorizado. O callback devolveu lab_admin e o painel abriu sem nova associação ou atribuição.

## Verificação

TDD: antes de DashboardLinks existir, os cinco testes apresentaram cinco erros de classe ausente. Depois da implementação, passaram cinco testes com 20 asserções. Cobrem ausência de read, subscriber, editor, ferramentas locais registadas e capacidades mal tipadas. A suite completa passou com 415 testes e 1344 asserções em PHP 8.4.26. PHPCS verificou 84 ficheiros sem erros ou warnings. Passaram os 54 testes Node, lint e build.

WordPress 7.1.3 e MariaDB 11.8.3 portáteis: persistência com 21 asserções e sessão federada com 24. Os ensaios conservam fixtures e tabelas exclusivas. Não homologam IIS ou MySQL 8.4.

Playwright no browser do Codex, com administrador Google: seis atalhos, marca carregada e ausência de overflow a 375, 768 e 1440 px. O menu efectivo foi rgb(11,58,91), a selecção rgb(31,107,74) e os botões primários rgb(11,58,91). As duas fontes locais carregaram. O atalho Páginas abriu o ecrã nativo e o dos ensaios mostrou 15/15 Coincide. O CSS do widget não carregou nesses destinos. Não foram registados erros de consola neste fluxo. A viewport temporária foi reposta e o painel ficou numa nova aba.

Playwright CLI, em sessão isolada: anónimo encaminhado para login. A fixture subscriber viu apenas O meu perfil e manteve a preferência de cores. Não viu as boas-vindas nem o widget de notícias WordPress. O foco de teclado teve outline de 3 px. Passou a 375, 768 e 1440 px e com texto do componente a 200%, corpo 32 px sem overflow. O atalho abriu profile.php, com o esquema ARN disponível e sem CSS do widget. O acesso directo aos ensaios respondeu HTTP 403. O fluxo final teve zero erros JavaScript. O erro de recurso HTTP 403 foi deliberado nesse teste de recusa.

As capturas ficam ignoradas em output/playwright/painel-arn-administrador.png e output/playwright/painel-arn-subscriber-mobile.png. Os logs, scripts de ensaio e preferências locais ficam fora do Git. Impeccable orientou o registo de produto, a hierarquia, as fontes e o reflow. Foram aplicadas as orientações de acessibilidade do UI UX Pro Max. O seu helper de pesquisa não correu: scripts e data estão guardados como ficheiros de ligação textual nesta instalação e o destino não existe. Essa limitação não impede o uso das orientações disponíveis no SKILL.md.

A revisão independente identificou uma sobreposição do selector nativo #dashboard-widgets h3. Foi corrigida apenas no widget ARN. O título efectivo passou de peso 400 a 700 e ficou a 20 px, com Manrope. Medições de contraste no browser: menu seleccionado 6,44:1, botão primário 11,86:1 e texto secundário 6,19:1. Os ensaios responsivos e de texto ampliado foram repetidos depois desta correcção.

F1-06 continua Em curso. Esta entrega melhora a administração CMS já existente. Não implementa o painel funcional F1-14, o circuito editorial F1-13, o diretório, a persistência de permissões ou F2. A integração da PR exige revisão independente e CI verde no SHA exacto.
