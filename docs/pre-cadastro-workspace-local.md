# Pré-cadastro Workspace no laboratório

Implementação da especificação aprovada na PR #33. Exclusiva de WordPress local e site único. A execução não constitui aprovação de RH, classificação institucional de contas ou homologação de IIS/MySQL. Os nomes, emails, inventários, configuração, capturas e dumps ficam fora do GitHub e do document root.

## Preparação e aplicação

O operador usa a conta administrativa técnica anterior. Não se converte essa conta numa fixture e não se inicia uma sessão nativa para ensaiar o lote. A instalação exige PHP 8.4, InnoDB, ligação dedicada sem reconexão, tema arn-intranet e apenas o plugin principal activo. Object cache externo, MU plugins ou filtros SQL fazem a aplicação recusar.

A configuração local define ARN_ALLOW_LOCAL_WORKSPACE_PREREGISTRATION estritamente true e ARN_WORKSPACE_PRIVATE_DIR para o directório privado. A fonte e a selecção têm de estar nesse directório com ACL restrita ao operador, SYSTEM e Administrators no Windows. Em POSIX, sem permissões de grupo ou outros. Não usar symlinks, junctions, hard links, caminhos de rede, checkouts Git ou ficheiros dentro da raiz pública.

Os argumentos comuns são --inventory, --source, --inventory-sha256 e --source-sha256. O operador obtém os hashes dos bytes aprovados e indica caminhos privados absolutos. Estes argumentos não contêm um segredo OAuth. Nunca os copiar para issues se revelarem dados pessoais. Os comandos seguintes usam wp-cli no WordPress local, com --user da conta técnica já autorizada.

1. Guardar backup e snapshots imutáveis antes de activar o candidato revisto com CI verde no SHA exacto.
2. Preparar as tabelas de identidade pelo serviço administrativo existente. Executar `wp arn workspace install-schema` para o esquema próprio, sem contas.
3. Executar `wp arn workspace preflight` com os quatro argumentos comuns. Verifica exactamente 73 seleccionados e quatro excluídos, sem escrita de dados de negócio.
4. Executar `wp arn workspace ready` com os mesmos argumentos. Verifica fonte privada, configuração, esquemas, tema e seis guardas nativas. Só então grava o marcador final arn_workspace_prereg_ready=1.
5. Executar `wp arn workspace provision --apply` com os mesmos argumentos. Repetir o comando e comparar registos, revisões, passwords técnicas, eventos e todas as linhas anteriores. A transacção é por conta. Uma interrupção permite retoma sem adoptar contas anteriores.

Não criar contas genéricas, aceitar aliases ou derivar nomes e emails. Cada conta recebe login opaco, nome e email primário exactos, password aleatória não entregue, capabilities vazias e user_level zero. A criação não chama user_register, wp_mail ou emissão de sessão. A role por defeito do WordPress é ignorada. Não cria perfis People, funções, unidades ou grants.

## Primeiro login e estado

O callback mantém o cliente OIDC mantido e os âmbitos openid e email. Só depois de validar o protocolo resolve issuer/sub. Um par existente é sempre tratado primeiro, incluindo quando está revogado. O email não reassocia esse par. Uma colisão com um pré-cadastro pendente produz um relatório privado deduplicado com referências opacas. Não inclui email, sub ou tokens.

Só um par ainda desconhecido pode reclamar um pré-cadastro AwaitingGoogle exacto, dentro de 24 horas da observação aprovada, com email_verified booleano true, hd exacto e email primário seleccionado. O tempo em espera por bloqueios SQL conta para esse prazo. Estado Linked, revisão, vínculo e eventos confirmam na mesma ligação/transacção. A sessão só é emitida depois, com a revisão persistida. O login não concede permissões de negócio.

`wp arn workspace disable ID --revision=N --reason="Razão"` e `enable` exigem revisão corrente e razão. A mudança de estado e a auditoria são atómicas. A destruição de sessões acontece depois do commit. Mesmo que essa destruição falhe, a revisão antiga não é aceite. Reactivar exige uma entrada nova. Não reactiva um vínculo Google revogado por decisão independente.

`wp arn workspace renew ID --revision=N --reason="Razão"` usa ainda os quatro argumentos comuns de uma nova fonte verificada. Só renova AwaitingGoogle exacto, com observação posterior e selecção autorizada. Não muda nome, email, conta, password ou permissões. O actor da renovação passa a ser a autorização corrente para o primeiro vínculo. Uma mudança da população exige revisão antes de aplicar. Reaplicar o lote nunca renova o prazo.

## Superfícies fechadas

Password, recuperação nova ou antiga, application passwords e cookies nativos ficam bloqueados desde a criação, mesmo sem flag ou configuração OIDC. Marcador incoerente, esquema incompatível, capabilities acrescentadas ou estado Disabled fecham a conta. Guardas registam antes da dependência OIDC.

Uma conta Linked sem competências recebe apenas a página pessoal de preparação no início e o logout nativo. CMS, módulos, REST, AJAX, feeds, XML-RPC, pesquisa e ficheiros continuam recusados antes de consultar fontes ou contagens. Não há excepção administrativa para um pré-cadastro. A vista reutiliza logo, favicon, Manrope, Source Sans 3 e cores do tema. Não carrega menus, scripts de módulos ou fontes externas.

GET/HEAD dessa vista não instalam esquema, renovam origem ou escrevem dados de negócio/auditoria. Conservam a actualização de actividade da sessão federada existente, necessária para o limite aprovado de 30 minutos de inactividade. O limite absoluto continua de uma hora. Não descrever essa actividade de sessão como ausência integral de DML. Respostas pessoais usam private/no-store e não devem entrar em cache partilhada.

## Limites de aceitação

A aplicação das 73 contas exige resultados agregados, comparação integral de estado anterior e evidência privada. O primeiro vínculo real só pode ser comprovado pelo titular autorizado. Se o seu issuer/sub já pertence à conta administrativa anterior, preserva-se esse vínculo e o pré-cadastro novo fica pendente. Esse caso não prova o primeiro vínculo de uma conta nova. Os restantes percursos são ensaiados com identidades fictícias.

A pré-validação exige agora o esquema instalado e íntegro. Além de verificar os ficheiros, analisa as 73 entradas contra as contas nativas e os pré-cadastros persistidos. Apresenta apenas totais create/resume/conflict. Cada execução guarda um ficheiro preflight com nome único na raiz privada. Os detalhes de colisão ficam nesse ficheiro. Um conflito recusa o lote antes de criar contas. O comando provision repete esta análise. Se ocorrer uma alteração concorrente durante a aplicação por conta, executar novamente preflight para distinguir as entradas confirmadas do conflito. A pré-validação não substitui as verificações dentro da transacção.
## Estado aplicado neste PC em 16 de outubro

A PR33 está integrada e as 73 contas seleccionadas foram criadas/auditadas sem funções. Todas permanecem AwaitingGoogle. Readiness e as duas constantes próprias estão activas apenas no laboratório. O site continua em loopback na porta 8888. A instalação tem agora 52 tabelas e conserva 89 originais privados. A retoma foi integralmente idempotente.

A observação da origem termina a sua janela inicial em 17 de outubro de 2026 às 10:55:33 UTC. Não renovar implicitamente. Depois é necessária uma exportação novamente verificada, selecção exacta e renew explícito para cada entrada ainda pendente. A aplicação usou uma cópia privada do inventário com o digest em minúsculas. A exportação, os nomes, os emails e a selecção são os originais. Não substituir esses valores por exemplos deste repositório.

A entrada Google real preservou a conta administrativa anterior. O pré-cadastro correspondente continua fechado para revisão privada de conflito. Não trocar o seu alvo, remover o vínculo anterior ou adoptar a conta por email. A prova de primeiro vínculo novo continua exclusivamente fictícia. Consultar a QA para a comparação por intervalo e os deltas nativos de cron, transientes e sessão.