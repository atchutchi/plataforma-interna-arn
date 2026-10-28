# Administrar o laboratório ARN

Este guia abrange a operação já implementada. Não autoriza alterações ao servidor, contas reais, pré-cadastros Workspace ou audiências institucionais. O laboratório fictício é 8892. A demonstração 8888 permanece separada.

## Separação de responsabilidades

O administrador técnico mantém WordPress, tema, plugin, configuração e diagnóstico. Não recebe por isso competência para decidir pedidos, férias, publicar notícias ou gerir documentos. RH valida cadastro, unidade, estados e concessões. A equipa editorial valida conteúdo e audiência. TI trata suporte e operação. A lista de emails não atribui funções.

Usar a conta apropriada a cada ensaio. Nunca promover contas reais para tornar um teste acessível. Para testar recusas, utilizar fixtures autorizadas e conservar as alterações auditadas. Não repor revisões, históricos ou sessões antigas para repetir um cenário.

## Identidade e acessos

Guardar configuração OIDC e segredos fora do Git e do document root. O vínculo definitivo usa issuer/sub. O login Google não fornece autorização Calendar. Uma configuração local não serve de callback institucional. O pré-cadastro real e os dados privados têm procedimentos próprios e não fazem parte deste pacote.

O motor verifica conta activa, sessão, competências e âmbito. Alterações devem usar os circuitos auditados existentes. A gestão individual do cadastro, a associação explícita profissional-conta e as delegações persistentes ainda têm lacunas no README. Não substituir esses circuitos por alterações SQL ou papéis WordPress. O fim de uma delegação deve impedir novas atribuições sem retirar a validade própria das concessões anteriores.

## Media privada

Os originais, derivados, quarentena, diários e recibos ficam em armazenamento privado. Não copiar estes ficheiros para a biblioteca pública WordPress ou para o repositório. A fila analisa e prepara os ficheiros. A conclusão nos formulários volta a verificar actor, sessão, competência e revisão. A análise não publica o conteúdo.

Consultar o diagnóstico operacional antes de uma sessão de processamento. Os ensaios de scanner, imagem e PDF são explícitos e produzem recibos. A supervisão existente é finita e não constitui um serviço permanente. Consultar [supervisão](../supervisao-fila-local-f1-08.md), [diagnóstico](../estado-operacional-media-local-f1-08.md) e [fila](../fila-media-quarentena-local-f1-08.md) para limites e contratos.

Em quarentena, conservar os bytes e a razão da retenção. Não converter uma suspeita em ficheiro aprovado por renomeação ou cópia manual. Uma conclusão indeterminada não deve ser repetida automaticamente. Reconciliação detecta diferenças entre armazenamento, registo e referências, mas não repara nem apaga. A recuperação auditada e a política de conservação continuam por concluir.

A manutenção de assinaturas, versões do scanner/descodificadores, capacidade de disco, isolamento e supervisão persistente exige operação definida e ensaiada. Não declarar essas tarefas concluídas porque o teste sintético passou.

## Configuração funcional

Os limites de uploads usam os parâmetros auditados do CMS. Os tipos de pedido têm versão, unidade, responsável e política de anexos. As férias usam calendário versionado. A alteração de uma configuração não reescreve decisões passadas. Um responsável substituto precisa de competência existente e não pode decidir o próprio pedido.

As páginas de serviço são recursos nativos instalados explicitamente. Uma visita não instala schemas. Não executar novamente instaladores para reparar uma instalação parcial ou recuperar um menu em falta. Conferir a documentação e o estado persistente primeiro. Os comandos de instalação local criam recursos fictícios e não constituem migrações de produção.

## Rotina de operação

Antes do trabalho, confirmar o ambiente, o commit, a disponibilidade dos serviços e o armazenamento privado. Verificar falhas de análise e inconsistências sem alterar originais. Após uma alteração autorizada, conferir o resultado, histórico, avisos e recusas de acesso com contas fictícias. Guardar evidência privada e publicar no Git apenas relatórios sem dados pessoais.

Se a auditoria ou a integridade falhar, interromper as escritas afectadas e conservar a evidência. Não apagar linhas para desbloquear a aplicação. Diagnosticar numa cópia isolada e definir recuperação controlada. A autorização deve continuar a falhar de forma fechada enquanto a integridade não estiver demonstrada.

## Entrega e incidentes

Seguir o [guião de instalação e recuperação](instalacao-recuperacao.md). O inventário identifica fontes, mas não instala dependências, não confirma assinaturas antimalware e não valida o servidor. Uma reversão de ficheiros isolada pode deixar schemas, diários e autorizações incompatíveis. Não a executar sem prova de compatibilidade ou restauro consistente.

Registar o responsável, versão, janela, impacto, evidência e decisão de recuperação. Não incluir segredos no ticket ou no GitHub. RPO, RTO, retenção e responsáveis de produção permanecem pendentes. O registo de incidente deve distinguir o que foi observado, o que foi corrigido e o que ainda não foi ensaiado.
