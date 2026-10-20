# Plano de implementação dos parâmetros do CMS

Execução directa com superpowers:executing-plans. O pedido de continuação autónoma mantém-se. Agrupar verificações no fecho de cada entrega funcional. Este plano não reduz o objectivo de concluir F1, F2 e a preparação para produção.

Objectivo: permitir a gestão autorizada e auditada dos limites de novos uploads, com aplicação efectiva em notícias e documentos e conservação das versões existentes.

Arquitectura: catálogo fechado em Cms, estado imutável, persistência dedicada na ligação IdentityWpdb, autorização institucional explícita e transporte WordPress. A política de admissão é separada do leitor de blobs existentes. Não usar wp_options para guardar segredos, permissões ou estado transitório.

Especificação: docs/parametros-cms-local-f1-15.md, CFG-01 a CFG-14. PHP 8.4 e WordPress existentes. Os limites iniciais são 1048576 bytes por ficheiro. Imagens de notícias admitem JPEG/PNG. Anexos de notícias e documentos admitem PDF/JPEG/PNG. Os destinos continuam a aplicar as suas restantes restrições.

## Restrições globais

Trabalho na branch feat/parametros-cms-f1-15, a partir de main 39e72d5. Nenhum acesso ou alteração de contas reais, Workspace ou produção nesta entrega. Nenhum DDL em HTTP. As novas fixtures usam example.invalid. Não reinstalar módulos existentes. CI verde e revisão antes de activar código no laboratório e antes do merge. TechnicalAdministrator conserva zero acções de negócio. O novo papel CmsAdministrator só é eficaz com concessão explícita de âmbito institucional.

## Foco de revisão

Tipos escalares forjados e chaves extra devem ser recusados. Redução de limites nunca bloqueia ficheiros publicados. Revisão concorrente e revogação devem ser revalidadas na mesma transacção. A falha de auditoria deve anular a configuração. Uma tentativa de upload recusada não apaga blobs já registados.

## Tarefa 1: domínio e autoridade

Criar Cms/UploadPolicy.php, Cms/SettingsState.php e Cms/SettingsActor.php. Acrescentar Action::ManageCmsSettings e Role::CmsAdministrator ao catálogo fechado. UploadPolicy recebe exactamente news_image, news_attachment e document, cada um com max_bytes inteiro e lista MIME estrita. SettingsState conserva revisão, valores, autor, data e motivo, com CAS e no-op. SettingsActor exige sessão actual com expiração e concessão institucional. Testes em tests/phpunit/CmsSettingsTest.php cobrem entradas, limites, conservação de estado, concorrência e autoridade, incluindo TechnicalAdministrator recusado.

- [x] Implementar o domínio e os testes de comportamento.
- [x] Executar os testes agrupados e corrigir divergências. PHP 963 testes, 4924 asserções e quatro omissões anteriores.

## Tarefa 2: persistência e aplicação atómica

Criar Cms/SettingsSchema.php, Cms/SettingsRepository.php, Cms/CurrentActor.php e Cms/Configuration.php. Usar tabelas próprias de estado, auditoria e esquema. O repositório liga-se à autoridade na mesma ligação. read() e history() revalidam acesso. change() bloqueia autoridade e estado, verifica CAS e confirma auditoria/estado juntos. A interface de admissão valida os descritores novos sob bloqueio da revisão até ao commit do módulo consumidor. Testes SQL exclusivos cobrem instalação, rollback, concorrência, revogação e leitura sem escrita.

- [x] Implementar persistência e testes SQL reais. MariaDB passou 69 verificações com admissão atómica em News/Documents, cópias conservadas e expiração durante espera. Autoridades reais usam uma leitura externa por operação. Identidade, sessão e descritores são sintéticos. Falta a prova integrada WordPress.
- [x] Comprovar conservação de tabelas exteriores e limpeza exclusiva. As 40 tabelas de negócio conservaram os hashes.

## Tarefa 3: CMS, instalador e consumidores

Criar instalador explícito, página de parâmetros com motivo e revisão, histórico paginado e recuperação de campos. Registar o destino autorizado no Painel ARN. Ligar a política efectiva aos novos uploads de News e Documents/Catalog. Manter as leituras e duplicação de versões anteriores com os limites estruturais originais. Revalidar admissão no commit dos dois repositórios. Documentar a limpeza de blobs temporários e implementar os ensaios de falha correspondentes.

- [ ] Implementar o circuito completo sem activar fixtures reais.
- [ ] Aplicar Impeccable e UI UX Pro Max à interface existente.

Progresso parcial: SettingsReader e UploadAdmission ligados a create/revise. O runtime envia proveniência, revalidada contra a versão bloqueada. As autoridades implementam assertUploadCurrent, sem repetir leituras de conta noutra ligação. Próximo trabalho obrigatório: ciclo de vida dos blobs após erro SQL, inclusive commit de resultado incerto. Só remover bytes próprios da tentativa e comprovadamente sem referências. Não limpar versões anteriores nem aplicar os limites novos ao leitor existente. Depois seguem instalador, página CMS e circuito HTTP/Playwright.

## Tarefa 4: aceitação e entrega

- [ ] Suite PHP, Node, PHPCS e ensaios SQL agrupados.
- [ ] Revisão independente e correcções.
- [ ] Commit, push, PR e CI no SHA exacto.
- [ ] Instalação explícita no laboratório fictício, Playwright CFG-13, preservação de versões/tabelas e relatório CFG-01 a CFG-14.
- [ ] README com resultados reais, limitações e próximo módulo. Merge apenas depois da CI final verde.

## Continuidade do objectivo completo

O utilizador confirmou em 20 de outubro que o calendário deve funcionar na plataforma e integrar o Google Calendar do Workspace. A implementação dessa integração, os serviços de pedidos/férias/tickets, a sua ligação ao painel/pesquisa/notificações e a homologação de produção continuam obrigatórios. Não confundir a conclusão desta parcela com a conclusão do site.
