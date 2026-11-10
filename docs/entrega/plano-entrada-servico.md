# Plano de entrada em serviço

Estado em 9 de novembro de 2026, actualizado após confirmação do responsável e imagem dos comandos no servidor. O DNS interno 192.168.17.151 serve o domínio arn.local. A plataforma será alojada no IIS de 192.168.17.205, fora do domínio. O nome reservado é plataforma-arn.duckdns.org. A proposta anterior plataforma-interna.arn.gw foi abandonada porque não existe acesso à gestão desse domínio. Esta actualização é documental, sem alterações remotas aos servidores.

## Sequência de entrega

| Ordem | Entrega | Execução | Prova que permite fechar |
| --- | --- | --- | --- |
| 0 | Correcção da dependência transitiva com avisos | Concluída localmente, 9 de novembro | symfony/http-client resolvido para v7.4.23 no contentor, auditoria sem avisos e regressões PHP/OIDC aprovadas. Docker recuperado e guarda de descoberta de testes acrescentada. [Evidência](qa-dependencia-http-2026-11-09.md). |
| 1 | Candidato reproduzível e inventário de dependências | Codex, local | Fontes do commit exacto, vendor de produção segundo o lock, hashes verificados, exclusão de configuração/dados e limites explícitos. Não equivale a instalador. |
| 2 | Adaptação institucional e migrações | Codex, local com fonte fictícia separada | Fornecedor de pessoas/unidades/competências configurável, fonte ausente fecha acesso, migrations sem fixtures, instalação vazia e upgrade ensaiados. Não activar flags locais no servidor. |
| 3 | Operação de media | Codex, ensaio local e depois homologação autorizada | Worker supervisionado persistente, manutenção de assinaturas/descodificadores, falhas e alertas, dimensionamento, retenção aprovada e recuperação manual sem apagar originais. |
| 4 | Publicação Google Calendar | Codex com transporte simulado, depois configuração aprovada | Calendário dedicado, envio/actualização/revogação idempotentes, reconciliação, isolamento de audiências e tratamento de falhas. Login Google não concede Calendar. |
| 5 | Configuração do servidor de homologação | Atchutchi e DCSI, execução técnica autorizada | IIS/PHP/SQL, volumes privados e ACL, HTTPS, callback, saídas de rede e integração de identidade verificados com dados fictícios. |
| 6 | Recuperação e QA transversal | Codex no laboratório, depois homologação | Backup consistente e restauro integral, autorização após restauro, rollback compatível, medições de carga e latência, acessibilidade e segundo PC. |
| 7 | Piloto institucional e entrada em serviço | Responsáveis de negócio e Atchutchi | Matriz aprovada, fontes reais revistas, responsáveis operacionais, formação, aceite dos circuitos e release exacta autorizada. |

O pacote de preparação pode ser produzido antes da adaptação institucional. Não deve ser instalado como produto concluído. A conclusão de uma linha não altera o estado das seguintes. Google Calendar faz parte do âmbito actual e não é removido para declarar o produto concluído.

## Decisões e informações a recolher

| Assunto | Conhecido | Ainda necessário | Responsável |
| --- | --- | --- | --- |
| Destino | 192.168.17.205, Windows Server 2019 Standard, build 17763, 64 bits, fora do domínio. IIS instalado, W3SVC Running/Automatic, conforme imagem do responsável | Capacidade, actualizações, restantes serviços, volumes, bindings IIS e janela de acesso | Atchutchi/DCSI |
| Nome | plataforma-arn.duckdns.org reservado na conta institucional, conforme imagem | Resolução interna e acesso HTTPS em dois equipamentos | Atchutchi |
| DNS | 192.168.17.151 serve arn.local. DuckDNS fornece a zona pública do nome reservado | Registo interno exacto para 192.168.17.205 e validação DNS-01 pública | Atchutchi/DCSI |
| HTTPS | Certificado ainda não demonstrado | Emissão, instalação IIS, renovação automática e responsável pelos alertas | Atchutchi/DCSI |
| Identidade | OIDC local existente | Cliente/callback institucional, sessões/MFA e fonte autorizada de pessoas | Atchutchi/RH/DCSI |
| Competências | Regras técnicas e delegações ensaiadas | Organograma, matriz, gestores designados e processo de reactivação | RH/DCSI |
| Conteúdo | Circuitos locais existentes | Editores, audiências, classificação e aprovação dos documentos reais | Editorial/RH |
| Serviços | Pedidos, tickets e férias locais | Tipos/circuitos, saldos oficiais, calendário laboral, responsáveis e excepções | RH/TI |
| Calendar | Plataforma publica para calendário dedicado | Calendário, audiência, principal autorizado e consentimento específico | Atchutchi |
| Operação | Diagnóstico e supervisão local finita | Horário, alertas, retenção, backups, RPO/RTO e responsável por incidentes | DCSI |

Recolher referências de configuração, não palavras-passe neste documento. Guardar credenciais e ficheiros de pessoas fora do Git. As decisões que alteram direitos ou dados reais exigem confirmação própria.

## DNS, servidores e certificado

Separação operacional obrigatória:

| Local | Função | Acção prevista |
| --- | --- | --- |
| 192.168.17.151 | DNS interno de arn.local | Criar ou conferir apenas a zona plataforma-arn.duckdns.org e o registo A na raiz para 192.168.17.205. Não alterar arn.local. |
| 192.168.17.205 | Servidor da aplicação, Windows Server 2019 e IIS | Alojar site, certificado e configuração privada. Não instalar DNS nem promover a controlador de domínio. |
| DuckDNS | Gestão pública do nome reservado | Validar controlo pelo TXT DNS-01 e renovar certificado. Não usar actualizador de IP público para publicar o servidor. |
| Google Cloud, projecto arn-platform | Cliente OAuth da plataforma | Cliente separado do laboratório e callback exacto, sem concessões de negócio implícitas. |

Actualização de 10 de novembro: as consultas fornecidas pelo responsável passaram a devolver 192.168.17.205 pelo DNS 192.168.17.151. O certificado de produção foi importado e a associação SNI do site ARN-Intranet foi confirmada. O responsável confirmou a página de teste HTTPS. Seguir a [preparação de renovação automática](renovacao-https.md), ainda sem execução agendada demonstrada. O texto Server: Unknown não prova falha de resolução. As várias interfaces do servidor devem ser inventariadas antes de qualquer mudança de bindings, rotas ou firewall. Não assumir a máscara da rede a partir do endereço IP.

No DNS interno existente, criar ou conferir uma zona primária com o nome completo plataforma-arn.duckdns.org. Na raiz dessa zona, usar um único registo A para 192.168.17.205, sem actualizações dinâmicas. Não criar uma zona geral duckdns.org nem modificar arn.local, arn.gw, email ou Workspace. A execução no DNS fica a cargo do responsável. Não substituir os resolvedores dos computadores do domínio por DNS públicos.

Validar no servidor da aplicação e num segundo computador:

~~~powershell
nslookup plataforma-arn.duckdns.org 192.168.17.151
nslookup plataforma-arn.duckdns.org
~~~

Ambas as consultas devem devolver 192.168.17.205. Confirmar também a resolução dos serviços existentes de arn.local após a alteração.

Para HTTPS, emitir certificado por DNS-01 usando o TXT público _acme-challenge.plataforma-arn.duckdns.org e automatizar a renovação. O responsável decidiu manter o token DuckDNS actual após ser informado da exposição. Guardá-lo fora do Git, logs e document root. Não copiar o token nem as imagens para o repositório. Como a zona interna exacta oculta os subnomes públicos, o cliente ACME deve verificar a propagação TXT através de resolução pública apropriada. Não criar o desafio apenas no DNS interno. Ver [DuckDNS](https://www.duckdns.org/spec.jsp) e [Let’s Encrypt DNS-01](https://letsencrypt.org/docs/challenge-types/#dns-01-challenge).

Inventariar sites e bindings existentes no IIS antes de associar o certificado ao nome aprovado, porta 443 e endereço de destino previsto. Verificar confiança, nome, validade e renovação a partir de um segundo PC, sem ignorar erros TLS. Manter acesso de entrada limitado às redes internas autorizadas, sem encaminhamento de portas no router. Rever regras amplas existentes e IPv6, quando aplicável. O registo público observado não demonstra exposição do site, mas também não substitui a verificação de isolamento LAN/VPN. A configuração pública A/AAAA deve ser revista separadamente, sem alterar os registos TXT necessários ao certificado.

Callback preparado: https://plataforma-arn.duckdns.org/wp-login.php?action=arn_google_callback. O formulário Google não mostrou erro e habilitou Criar, mas não existe ainda prova de criação do cliente, aceitação pelo servidor OAuth ou login completo. A criação foi entregue ao responsável. Preservar o cliente localhost existente. Credenciais fora do Git e da raiz pública. Ver [regras OAuth](https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation).

Ordem de execução: confirmar DNS interno, inventariar IIS e rede, emitir certificado e provar renovação, configurar binding HTTPS, concluir cliente OAuth e adaptação institucional, ensaiar login/sessões/recusas com dados fictícios e confirmar isolamento fora da rede. DNS e HTTPS não encerram as lacunas de identidade, migrações, operação ou aceite piloto.

## Critério de disponibilização

O responsável recebe uma ficha com SHA e CI exactos, hashes do candidato e dependências, configuração privada identificada, versões de runtime, migrações, resultados de homologação, backup/restauro, rollback e aceite piloto. Só depois se decide a janela e a abertura aos utilizadores. Um manifesto íntegro ou uma CI verde não substituem essas provas.
