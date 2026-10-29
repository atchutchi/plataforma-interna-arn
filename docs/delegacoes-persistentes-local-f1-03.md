# Delegações de acesso no laboratório

## Regra confirmada

A autoridade inicial pertence a um gestor de acessos explicitamente designado. O papel de administrador WordPress não estabelece essa autoridade. O fim ou revogação de uma delegação impede novas atribuições e conserva a validade própria das concessões anteriores.

Esta parcela usa exclusivamente três contas fictícias no laboratório 8892. A designação é uma fixture privada explícita, adicional à competência canónica de gerir acessos. Não existe designação institucional a partir de emails, cargos, papéis WordPress ou do primeiro utilizador que abrir o painel.

## Circuito entregue

O gestor designado emite e revoga delegações. O delegado atribui uma função ao destinatário indicado, apenas com competência canónica, sessão actual e uma delegação vigente que cubra integralmente função e âmbito. A delegação não cria a competência de gestão. O destinatário e o administrador técnico não têm acesso a este painel.

Cada delegação fixa um delegado, um destinatário, uma função, um âmbito e datas UTC. No laboratório estão disponíveis Funcionário com âmbito próprio e Responsável de unidade com LAB-A ou LAB-B activa. Não é possível atribuir funções administrativas, promover o próprio delegado ou ampliar os limites de uma delegação. Uma alteração dos limites exige uma nova decisão.

O início é inclusivo e o fim exclusivo. As datas da concessão são independentes das datas da delegação. A criação da concessão incrementa a revisão de autorização do destinatário e invalida a geração anterior das suas sessões. A revogação ou expiração da delegação não incrementa essa revisão nem retira a concessão anterior.

## Persistência e auditoria

Três tabelas próprias guardam estado, recibos e auditoria. Instalação explícita por `wp arn delegations install` com ambiente local e `ARN_ALLOW_LOCAL_DELEGATIONS=true`. O comando instala o schema vazio. Não cria contas, gestores, delegações ou concessões. Exige também as flags locais de autorização, acesso fictício e organização.

O circuito bloqueia as fixtures, contas e estado numa transacção. A criação canónica da concessão participa nessa mesma transacção, sem commit aninhado. Revalida sessão, competência, vigência, destinatário e unidade antes de confirmar. Revisões desactualizadas, auditoria indisponível, corrupção, perda de sessão e limites divergentes impedem a escrita. Não há concessão parcialmente confirmada.

O recibo identifica decisor e comando. Repetir o mesmo pedido devolve o resultado original sem repetir efeitos, incluindo depois de expirar ou revogar. Um pedido diferente com o mesmo identificador é recusado. Consultar o histórico exige ainda autoridade e sessão correntes. Os motivos conservam o texto literal, incluindo entidades HTML.

## Verificação

A verificação local inclui 120 verificações SQL de cadastro/ciclo/delegações, 97 de autorização, 61 testes Node, PHPCS nos 12 ficheiros PHP alterados e lint/build. O percurso Playwright aprovou 34 verificações, incluindo cinco negativas repetidas após corrigir um selector do próprio ensaio. Foram inspeccionadas as vistas desktop e móvel a 390 px. Provas, credenciais e snapshots permanecem no arquivo privado do laboratório, fora do Git e do document root. Os ensaios SQL usam um prefixo exclusivo e removem apenas as suas próprias tabelas.

A revisão independente identificou duas lacunas corrigidas com reprodução SQL RED/GREEN: recusa da ausência de uma decisão antiga e expiração durante as consultas finais de unidades. A leitura valida agora a cadeia completa desde o estado vazio. A verificação temporal final é pura e ocorre depois de todas as leituras de sessão/SQL, imediatamente antes do commit.

A comparação privada passou 510 verificações: preservadas 133 tabelas exteriores, todas as linhas anteriores das 49 tabelas de negócio, 23 contas e 365 ficheiros. Foram acrescentadas três contas fictícias, três tabelas, três concessões e nove eventos de autorização. A sessão administrativa do browser do utilizador e a demonstração 8888 não foram alteradas. Os metadados transitórios de sessão e as opções nativas não estão incluídos na afirmação de igualdade dos registos.

## Limites de entrega

A fonte institucional que designa gestores, a matriz efectiva de competências, os destinatários e o procedimento de substituição do gestor designado continuam sujeitos a validação. As três fixtures não são um mecanismo de provisionamento institucional. O circuito está limitado a 50 delegações e 50 concessões por delegação no laboratório. Não constitui aprovação para deploy ou para atribuir permissões a contas reais.
