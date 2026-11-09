# Plano de entrada em serviço

Estado em 9 de novembro de 2026. Este plano organiza o fecho do produto e a instalação. O servidor Windows identificado pelo responsável com o sufixo .205, fora do domínio, continua a ser o destino. «plataforma-interna» é o nome curto escolhido. `https://plataforma-interna.arn.gw` é uma proposta, não um endereço aprovado ou configurado. Atchutchi confirmou que gere o DNS de arn.gw. Não há autorização implícita para alterar DNS, produção ou contas reais.

## Sequência de entrega

| Ordem | Entrega | Execução | Prova que permite fechar |
| --- | --- | --- | --- |
| 0 | Correcção da dependência transitiva com avisos | Codex, ambiente Composer do projecto | Actualizar symfony/http-client fixado em v8.0.16, auditoria sem os avisos e regressões OIDC/PHP aprovadas. O motor Docker estava indisponível no levantamento. |
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
| Destino | Windows .205, fora do domínio | Endereço completo, versão Windows, capacidade, serviços existentes, volume e janela de acesso | Atchutchi/DCSI |
| Nome | Nome curto plataforma-interna | Aprovar FQDN proposto, confirmar disponibilidade e acesso via LAN/VPN | Atchutchi |
| DNS | Atchutchi gere arn.gw | Fornecedor/painel DNS, responsável pela resolução interna e validação DNS-01 | Atchutchi/DCSI |
| HTTPS | Certificado ainda não demonstrado | Emissão, instalação IIS, renovação automática e responsável pelos alertas | Atchutchi/DCSI |
| Identidade | OIDC local existente | Cliente/callback institucional, sessões/MFA e fonte autorizada de pessoas | Atchutchi/RH/DCSI |
| Competências | Regras técnicas e delegações ensaiadas | Organograma, matriz, gestores designados e processo de reactivação | RH/DCSI |
| Conteúdo | Circuitos locais existentes | Editores, audiências, classificação e aprovação dos documentos reais | Editorial/RH |
| Serviços | Pedidos, tickets e férias locais | Tipos/circuitos, saldos oficiais, calendário laboral, responsáveis e excepções | RH/TI |
| Calendar | Plataforma publica para calendário dedicado | Calendário, audiência, principal autorizado e consentimento específico | Atchutchi |
| Operação | Diagnóstico e supervisão local finita | Horário, alertas, retenção, backups, RPO/RTO e responsável por incidentes | DCSI |

Recolher referências de configuração, não palavras-passe neste documento. Guardar credenciais e ficheiros de pessoas fora do Git. As decisões que alteram direitos ou dados reais exigem confirmação própria.

## DNS e certificado

Proposta: usar o FQDN público `plataforma-interna.arn.gw`, resolvido para o endereço privado completo do servidor no DNS utilizado pela LAN/VPN. Não criar uma nova zona interna arn.gw sem estudar a resolução dos serviços existentes. Não mudar os registos do site, email ou Workspace.

O site pode continuar privado. Uma autoridade de certificação pública pode validar o controlo do nome pelo desafio DNS-01. O cliente ACME cria um TXT em `_acme-challenge.plataforma-interna.arn.gw` através do DNS público e renova o certificado. O mecanismo de renovação, acesso ao fornecedor e protecção das credenciais ainda devem ser escolhidos e ensaiados. Ver [Let’s Encrypt DNS-01](https://letsencrypt.org/docs/challenge-types/#dns-01-challenge).

No IIS, associar o certificado ao site HTTPS com o FQDN aprovado. Confirmar cadeia de confiança, nome e validade a partir de um segundo PC. Não desactivar a verificação TLS para fazer o login funcionar. O callback proposto é `https://plataforma-interna.arn.gw/wp-login.php?action=arn_google_callback`, sujeito à confirmação do nome e da rota institucional. Registar exactamente esse URI no cliente Google aprovado. Nomes curtos, .local e IPs privados não satisfazem as regras do callback web institucional do Google. Ver [regras OAuth](https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation).

## Critério de disponibilização

O responsável recebe uma ficha com SHA e CI exactos, hashes do candidato e dependências, configuração privada identificada, versões de runtime, migrações, resultados de homologação, backup/restauro, rollback e aceite piloto. Só depois se decide a janela e a abertura aos utilizadores. Um manifesto íntegro ou uma CI verde não substituem essas provas.
