# Requisitos funcionais e técnicos da plataforma

Referência consolidada a partir do README integrado em 97274ac. Este documento conserva requisitos, decisões, histórias e fontes, não o estado de execução. O [README](../README.md) é a única lista actual de entregas e prioridades. As referências a secções mantêm a numeração original. Registos de passagem entre colaboradores e instruções de tarefas antigas foram retirados do README.

Google Calendar é um requisito confirmado de conclusão juntamente com F1 e F2. Esta decisão posterior prevalece sobre referências históricas a calendários externos como evolução opcional. Decisões institucionais pendentes não impedem implementar mecanismos configuráveis e ensaios fictícios.

<a id="objetivo"></a>
## 1. Objetivo e âmbito

A plataforma deve funcionar como ponto de entrada para a informação e os serviços internos da ARN. Deve organizar funcionários por unidade orgânica, permitir publicação de conteúdos através do CMS e aplicar permissões sobre cada ação e cada registo.

Os módulos previstos são:

- Autenticação e controlo de acesso.
- Painel pessoal.
- Diretório de funcionários, unidades orgânicas e organograma.
- Notícias com aprovação editorial, galerias e anexos.
- Comunicados institucionais e de equipa.
- Documentos institucionais e administrativos.
- Tarefas e acompanhamento de equipas.
- Calendário de eventos.
- Pedidos internos, férias e calendário de ausências.
- Suporte informático através de tickets.
- Notificações, pesquisa e perfil pessoal.
- Administração, configuração e auditoria.

A implementação seguirá as 46 histórias do anexo User Stories Portal Interno Empresa Com Notícias, adaptadas às decisões confirmadas neste documento. As melhorias da secção 10 complementam essas histórias e conservam a rastreabilidade dos códigos originais.

O arranque local segue a secção 16.6. A configuração institucional do Google Workspace, a importação de dados reais e a instalação na rede ARN continuam sujeitas às dependências das respetivas tarefas.

<a id="decisoes"></a>
## 2. Decisões confirmadas e propostas

### 2.1 Decisões confirmadas pelo responsável do projeto

| ID | Tema | Decisão |
| --- | --- | --- |
| DEC-01 | Plataforma | WordPress como CMS. |
| DEC-02 | Entrega actual | Desenvolver código e documentação por tarefas, branches e PRs verificadas através de ChatGPT Work/Codex e GitHub. A decisão documental inicial fica no histórico. Cursor é opcional. Sem deploy nesta sessão. |
| DEC-03 | Organização | Integrar os funcionários por direção e demais unidades orgânicas. |
| DEC-04 | Autenticação | Usar as contas institucionais do Google Workspace já utilizado pela ARN, no domínio arn.gw. |
| DEC-05 | Alojamento | Usar um servidor Windows na infraestrutura interna da ARN. A natureza física ou virtual do novo host será inventariada. |
| DEC-06 | Rede | Permitir acesso apenas pela rede ARN ou por VPN. |
| DEC-07 | Desenvolvimento | Entregar por fases. |
| DEC-08 | Notícias | Submissão pelos funcionários e aprovação por equipa editorial designada. |
| DEC-09 | Catálogo institucional | A lista Departamentos-ARN.docx, recebida em 9 de outubro, detalha o catálogo inicial. Transcrição na secção 4.1 e ambiguidades na secção 4.6. |
| DEC-10 | Requisitos | Rever as histórias fornecidas e documentar melhorias necessárias. |
| DEC-11 | Servidor do portal | Panthera-Onca, 192.168.17.205, Windows Server 2019 Standard, versão 1809, build 17763, 64 bits. Fora de arn.local por decisão de 13 de outubro de 2026. Produção nativa com IIS, PHP 8.4 x64 NTS/FastCGI e MySQL 8.4 LTS, após homologação. |
| DEC-12 | Definições institucionais | A arquitetura está documentada e a nova lista fornece unidades e nomes. Permanecem para validação as lacunas de hierarquia, cargos e identidade, a equipa editorial, a distribuição nominal de tarefas, as fontes operacionais, os circuitos e o calendário. |
| DEC-13 | Controlador de domínio | O responsável confirmou que 192.168.17.151 é controlador de domínio de arn.local. Mantém as funções AD DS e DNS. |
| DEC-14 | Associação ao domínio | Panthera-Onca foi retirado de arn.local e deve permanecer fora do domínio. Não depender de GPO, Kerberos, LDAP, conta de computador ou autenticação AD. .151 mantém AD DS/DNS. DNS institucional apenas se aprovado pela DCSI. Esta decisão substitui a versão 0.5. |
| DEC-15 | Administração inicial | Registar as três contas administrativas indicadas pelo responsável na secção 5.4, com autenticação Google e atribuição explícita de permissões no portal. |
| DEC-16 | Lista de departamentos e pessoal | Incluir no README a lista fornecida pelo responsável. Registar a grafia de origem, as faltas de informação e os pontos de validação, sem criar contas ou atribuir cargos por inferência. |
| DEC-17 | Trabalho conjunto | Atchutchi e um colaborador trabalharão em computadores separados. Usar clones independentes, branches por tarefa, commits por avanço verificável e push da branch de trabalho, com revisão antes de integrar em main. |

| DEC-18 | Desenvolvimento | ChatGPT Work/Codex, GitHub e ambiente local separado. Cursor é opcional e não acrescenta dependências ao código. |
| DEC-19 | Férias | 22 dias úteis por funcionário e por ano civil, repartíveis por vários pedidos prévios. Aprovação final por RH. Sem cadeia adicional presumida. Regras na secção 11.5. |

DEC-04 atualiza a forma de executar US-F01. A página da ARN disponibiliza a entrada institucional e encaminha a autenticação para o Google. A palavra-passe é introduzida no serviço Google, segundo a política da instituição.

### 2.2 Propostas técnicas desta versão

São recomendações para orientar o desenvolvimento: WordPress numa instalação única, tema próprio, plugin institucional, integração OIDC, IIS com PHP por FastCGI, MySQL 8.4 LTS, armazenamento privado de anexos e ferramentas de desenvolvimento reproduzíveis.

As propostas de melhoria ainda dependentes de decisão funcional estão identificadas na secção 10. Os pontos que precisam de informação institucional adicional estão na secção 18, com responsável e momento de resolução.

Uma decisão confirmada não significa que a configuração ou funcionalidade já esteja implementada.

<a id="arquitetura"></a>
## 3. Arquitetura e tecnologias

### 3.1 Arquitetura proposta

Uma instalação WordPress servirá o portal, o CMS e os módulos internos. O tema tratará da apresentação. O plugin institucional concentrará regras de negócio, autorização, dados e processos.

Esta separação permite alterar o design sem perder a estrutura das unidades, os conteúdos ou os processos. A documentação WordPress recomenda manter tipos de conteúdo no plugin quando precisam de sobreviver à mudança de tema. [W2]

~~~mermaid
flowchart TD
    Rede["Clientes na rede ARN ou VPN"] -->|"HTTPS"| IIS["IIS 10 em 192.168.17.205"]
    Rede -->|"DNS institucional se aprovado"| DNS["DNS e AD DS em 192.168.17.151"]
    IIS --> PHP["PHP x64 NTS por FastCGI"]
    PHP --> Portal["WordPress ARN"]
    Google["Google Workspace"] -->|"Identidade OIDC"| Portal
    Portal --> Tema["Tema de blocos ARN"]
    Portal --> Core["Plugin institucional"]
    Core --> Base["MySQL 8.4 LTS local"]
    Core --> Arquivo["Ficheiros privados"]
    Core --> Logs["Auditoria"]
~~~

A ligação ao Google representa a autenticação dos utilizadores. Panthera-Onca permanece fora de arn.local. O uso de DNS institucional depende de aprovação da DCSI e não cria dependência de AD. O acesso será limitado à rede ARN ou VPN. A aplicação usa Google Workspace e autorização local WordPress, sem GPO, Kerberos ou LDAP.

### 3.2 Stack de referência

| Camada | Tecnologia proposta | Função e condição de adoção |
| --- | --- | --- |
| CMS | WordPress estável e mantido | Conteúdos, administração, editor, utilizadores e mecanismos nativos. Versão exata validada no início da implementação. |
| Backend | PHP 8.4 x64 Non-Thread Safe, com atualização mantida | Executar através de php-cgi.exe e FastCGI no IIS. Validar extensões, runtime C++ e compatibilidade das dependências. [W13] |
| Regras da ARN | Plugin próprio arn-intranet-core | Unidades, vínculos, permissões, fluxos, dados de trabalho, ficheiros privados e auditoria. |
| Frontend | Tema de blocos arn-intranet | HTML, CSS, theme.json, padrões, templates e JavaScript para interação. |
| Editor | Editor de blocos integrado no WordPress | Edição de conteúdos por utilizadores autorizados. Blocos e opções disponíveis conforme a função. |
| Componentes dinâmicos | JavaScript e pacotes WordPress | React quando necessário para blocos ou componentes do editor. Sem aplicação frontend autónoma na arquitetura base. |
| Base de dados | MySQL Community Server 8.4 LTS x64 | Serviço Windows. A matriz MySQL inclui Windows Server 2019. Base própria do portal, acesso local e credenciais restritas. [W14] |
| Servidor | Windows Server 2019 Standard no host 192.168.17.205, fora de arn.local | Sistema reportado no anexo. IP e associação ao domínio confirmados pelo responsável nas correções posteriores. Inventariar configuração, recursos, atualizações, serviços e coexistência. |
| Servidor web | IIS 10, CGI/FastCGI e URL Rewrite | Site e Application Pool exclusivos do portal. Regras de encaminhamento em web.config e HTTPS no nome canónico. [W13] [W16] |
| Identidade | Google Workspace por OpenID Connect | Identidade institucional, com autorização local no portal. |
| Cliente OIDC | Facile PHP OpenID Client 1.0.0 | Biblioteca mantida com adaptador ARN. Homologada localmente. Falta HTTPS/IIS/MySQL/LAN/VPN. Ver docs/google-login-local-f1-02.md. |
| Ficheiros | Armazenamento privado sob controlo da ARN | Entrega através de autorização no servidor. Abrange anexos, imagens, miniaturas e versões. |
| Ambiente local | ChatGPT Work/Codex e GitHub, com ambiente local separado em WSL 2, Ubuntu 24.04 LTS e wp-env com Docker | Percurso comum para os dois PCs. Node.js 24 LTS e Git no Ubuntu. wp-env usa MariaDB, com versão fixada, e PHP 8.4. A homologação valida Windows/IIS e MySQL 8.4. Docker Desktop requer licença adequada à utilização institucional e não será instalado no servidor de produção. [W10] [W31] [W32] [W33] [W34] [W35] |
| Ferramentas | WP-CLI, Composer e npm | Gestão técnica, dependências PHP e construção de recursos do tema e dos blocos. |
| Qualidade | WordPress Coding Standards, PHPUnit e testes de navegador | Verificar regras de acesso, estados, integrações e fluxos completos. |
| Colaboração | GitHub, branches e pull requests | Histórico de alterações e revisão. As tarefas ficam inicialmente registadas neste README. |

O Hosting Handbook do WordPress recomenda PHP 8.4 ou superior e distingue versões mantidas de versões aceites apenas por compatibilidade. A seleção das versões de produção deve seguir suporte ativo ou de segurança e compatibilidade verificada. O mínimo técnico de execução não serve, por si só, como critério para uma instalação nova. [W1]

MySQL 8.4 LTS substitui a preferência inicial por MariaDB neste ambiente Windows. A política de pacotes MariaDB Community identifica Windows Server 2019 como plataforma descontinuada em janeiro de 2024. Por isso, esta revisão não assume MariaDB 11.4 como combinação oficialmente suportada nesse sistema. [W14] [W15]

### 3.3 Alternativas consideradas

| Opção | Avaliação para este projeto |
| --- | --- |
| WordPress integrado, tema próprio e plugin institucional | Proposta base. Reúne CMS e aplicação, preserva os mecanismos WordPress e concentra a manutenção. |
| Windows com IIS, PHP por FastCGI e MySQL | Proposta de alojamento nativo para o ambiente indicado. A homologação cobre WordPress, extensões e controlos específicos do IIS. |
| WordPress como backend e frontend separado em Next.js | Acrescenta aplicação, integração, sessões e manutenção. Reavaliar apenas se uma necessidade concreta o justificar. |
| Conjunto de plugins independentes para RH, tarefas, suporte e intranet | Exige validar integração de permissões, dados e atualizações. Aproveitar componentes adequados, mantendo as regras da ARN centralizadas. |

As direções serão unidades da mesma instituição dentro da mesma instalação. Não se propõe um site WordPress separado por direção.

### 3.4 Política de dependências

Cada dependência deve ter função identificada, licença conhecida, manutenção verificável e compatibilidade testada. Registar versão, origem, responsável pela atualização e dependências relevantes.

O cliente OIDC é um candidato, ainda sem aprovação técnica para produção. A homologação deve validar autenticação Google, assinatura e claims, bloqueio de contas não autorizadas, associação de identidades e revogação local. Se falhar os critérios, avaliar uma integração com biblioteca oficial mantida, sem escrever um protocolo de autenticação próprio.

Plugins de pagamento, licenças comerciais e serviços externos adicionais exigem uma necessidade definida e decisão de aquisição. O percurso local proposto utiliza Docker Desktop, cuja utilização por entidades governamentais requer subscrição paga. Confirmar a cobertura institucional antes de o utilizar. Esta documentação não compra nem ativa licenças. [W34]

<a id="infraestrutura-windows"></a>
### 3.5 Infraestrutura Windows e rede observada

O responsável confirmou que .151 mantém AD DS e DNS de arn.local. Em 13 de outubro de 2026 confirmou que Panthera-Onca, .205, foi retirado do domínio e deve permanecer fora dele. Windows Server 2019 Standard, WindowsVersion 1809, build 17763 e arquitectura 64 bits foram comunicados nessa decisão. Hyper-V está instalado. WSL não está instalado e wsl.exe não existe no servidor. Não instalar WSL ou Docker Desktop em .205 para executar a intranet. Não foi feita inspeção remota. Os recursos do anexo anterior continuam sujeitos a inventário.

| Equipamento | Papel definido | Relação com o domínio |
| --- | --- | --- |
| 192.168.17.151 | Controlador de domínio e DNS institucional | Controlador de arn.local, confirmado pelo responsável. |
| 192.168.17.205, Panthera-Onca | Host escolhido para o futuro portal WordPress e a sua base de dados | Servidor fora de arn.local, conforme decisão do responsável em 13 de outubro de 2026. |

Dados comunicados para o servidor do portal. A decisão de 13 de outubro de 2026 substitui as referências anteriores à pertença ao domínio:

| Elemento | Informação disponível | Tratamento no plano |
| --- | --- | --- |
| Sistema operativo | Windows Server 2019 Standard, 1809, build 17763, 64 bits | Informação confirmada pelo responsável. Actualizações e recursos efectivos por inventariar. |
| Hyper-V | Instalado | Não implica que WSL ou Docker estejam disponíveis. |
| WSL | Não instalado. wsl.exe ausente | Não instalar WSL ou Docker Desktop para alojar o portal. |
| Nome do servidor | Panthera-Onca | Referência para inventário e administração. O nome web do portal será próprio. |
| Fabricante | Hewlett Packard Enterprise | Informação do anexo. Não permite concluir se o sistema apresentado é físico ou virtual. |
| Natureza do host | Ainda não confirmada para este servidor | Inventariar hardware ou recursos atribuídos, conforme se trate de instalação física ou VM. |
| Memória | 32 GB reportados | Não representam RAM livre ou reservada ao portal. Medir utilização e margem disponível. |
| Processador | Intel Xeon Silver 4114 reportado | Confirmar capacidade disponível e processadores lógicos ou vCPU, conforme o host. |
| IPv4 do alvo | 192.168.17.205 | Endereço de referência corrigido pelo responsável. Conferir configuração efetiva, estabilidade e ausência de conflito no inventário. |
| Máscara | 255.255.255.0 | Rede local 192.168.17.0/24. |
| Gateway | 192.168.17.1 | Equipamento de encaminhamento indicado na configuração. |
| Estabilidade do endereço | Método de atribuição ainda por inventariar em .205 | Validar endereço fixo ou reserva institucional adequada, sem presumir a configuração DHCP deste host. |
| DNS do servidor | Configuração efectiva por validar pela DCSI | Pode consultar .151 se aprovado. Validar nomes internos e externos sem dependência de serviços AD. |
| Domínio Windows | Fora de arn.local | Manter fora do domínio. Usar configuração local e identidades de serviço locais autorizadas. |
| VPN | Acesso por VPN confirmado como requisito | Sub-rede de clientes, rotas, DNS e regras de firewall ainda por inventariar. |

O gateway .1 não define o conjunto de endereços livres. O endereço de rede é 192.168.17.0 e o de broadcast é 192.168.17.255. Qualquer novo endereço deve ser verificado no inventário e nos serviços de atribuição existentes.

### 3.6 Localização da aplicação e funções de domínio

O host escolhido para a aplicação é 192.168.17.205. O papel de .151 como controlador de domínio está confirmado pelo responsável e deixa de ser uma questão em aberto. A preparação de IIS, PHP, MySQL e ficheiros privados será feita no servidor .205, após inventariar serviços existentes, portas, volumes, atualizações e capacidade.

| Componente | Localização e responsabilidade |
| --- | --- |
| WordPress, PHP, IIS, MySQL e documentos do portal | Servidor 192.168.17.205, fora de arn.local. |
| Active Directory e DNS | Servidor 192.168.17.151, no domínio arn.local. |
| Autenticação dos funcionários | Google Workspace por OIDC. |
| Autorizações funcionais | WordPress e plugin institucional, conforme função, unidade e objeto. |

A gestão de .205 usa contas e configurações locais autorizadas. O Application Pool terá identidade local própria e a base de dados ficará no mesmo servidor. As permissões funcionais são do WordPress e do plugin. Configurar explicitamente hora, certificados, firewall, manutenção e recuperação, sem pressupor políticas do domínio. [W17] [W5]

O portal em .205 e o controlador em .151 conservam funções separadas. A aplicação não exige associação ao domínio nem alterações a AD DS. [W19]

### 3.7 Configuração proposta para IIS e serviços

Criar um site IIS e um Application Pool exclusivos do portal em .205. Usar ApplicationPoolIdentity e conceder ACL NTFS à identidade local IIS AppPool\ARNPortal, se ARNPortal for o nome aprovado para o pool. O nome é uma proposta de configuração. As identidades de pool permitem separar o acesso de cada aplicação aos ficheiros sem criar uma conta de domínio. [W17]

Definir contas administrativas locais nominativas e grupos de acesso aprovados pela DCSI. Usar identidades locais restritas para serviços e tarefas. As contas Windows são distintas das contas Google dos funcionários e das funções de gestão do CMS. Não pressupor autenticação ou contas AD em .205.

Configuração de referência:

- IIS 10 com CGI/FastCGI, conteúdo estático, documentos predefinidos, logging e URL Rewrite.
- PHP 8.4 x64 NTS, obtido de distribuição oficial, associado ao handler php-cgi.exe. Usar php.exe apenas nas tarefas CLI.
- Runtime Visual C++ v14 x64 mantido e compatível com o binário PHP. Homologar o conjunto na tarefa INF-06. [W13] [W24]
- Extensões necessárias ao conjunto escolhido, incluindo mysqli, curl, openssl, mbstring, fileinfo, zip, intl e tratamento de imagens. Validar OPcache e os limites de memória e uploads.
- Pool em 64 bits e sem runtime .NET para a aplicação PHP. Definir reciclagem e limites a partir dos ensaios, evitando interromper trabalhos longos.
- Autenticação anónima no IIS configurada para usar a identidade do pool, permitindo à aplicação processar a entrada e o callback OIDC. A autorização de conteúdos pertence ao WordPress. Não ativar implicitamente autenticação integrada Windows ou Basic para o portal. [W25]
- Binding HTTPS associado ao nome canónico aprovado, com certificado correspondente. Configurar WordPress para usar esse mesmo endereço.
- Regras web.config limitadas ao site, preservando o encaminhamento de permalinks, REST, administração e callback real do plugin.
- MySQL 8.4 LTS como serviço Windows, com diretório de dados e conta de serviço próprios. Escutar em loopback para o acesso local do PHP.
- Base de dados exclusiva do portal, conta da aplicação limitada a essa base e procedimento de manutenção para alterações de esquema.

O Application Pool limita o processo e os acessos ao sistema de ficheiros. As permissões por funcionário e por direção continuam a exigir verificação no plugin.

<a id="dns-identidade"></a>
### 3.8 DNS, HTTPS e identidade

| Nome ou sistema | Finalidade |
| --- | --- |
| arn.local | Domínio Active Directory existente, com controlador em 192.168.17.151. |
| Google Workspace institucional | Autenticação dos funcionários, conforme DEC-04. |
| intranet.arn.gw | Nome canónico proposto para o portal, a resolver internamente para 192.168.17.205. Precisa de validação e configuração. |
| 192.168.17.205 | IP escolhido para o servidor do portal, fora de arn.local. |
| 192.168.17.151 | Controlador AD DS/DNS. Uso como DNS de .205 sujeito a aprovação da DCSI. |

As regras Google para aplicações web exigem HTTPS, host admissível e sufixo público válido. O callback de produção não deve usar o IP privado nem um nome terminado em .local. A proposta intranet.arn.gw permite manter arn.local para AD e utilizar um nome adequado ao login Google. [W5]

O nome de produção proposto deve resolver para 192.168.17.205 através do DNS usado na rede ARN e na VPN. O nome e o endereço de homologação serão próprios desse ambiente. O caminho exato do callback será o fornecido pelo plugin homologado, não um caminho inventado nesta fase. O browser regressa ao portal pela rede ou VPN. O servidor efetua a troca de código com o Google através de ligação de saída.

A alteração DNS deve limitar-se ao nome necessário e respeitar as zonas atuais. No DNS institucional .151, se não existir configuração equivalente, avaliar uma zona interna intranet.arn.gw com registo A na raiz apontado para .205. A DCSI cria e gere o registo do nome web do portal, sem criar conta de computador ou associação de .205 ao domínio. Evitar criar uma zona arn.gw incompleta que oculte os registos públicos utilizados pelo correio e pelos restantes serviços. A documentação Microsoft descreve zonas e resolução diferenciada por contexto. [W20]

Para HTTPS, a proposta preferencial é certificado público para o nome do portal, com emissão e renovação ACME DNS-01. O desafio usa um registo TXT público e funciona sem expor o servidor web à Internet. A emissão depende de controlo autorizado do DNS e de um procedimento de renovação e instalação no IIS. [W21]

Como alternativa, usar PKI interna se a ARN assegurar confiança na cadeia em todos os dispositivos autorizados, incluindo VPN. Em .205, instalar e validar explicitamente a confiança local, sem depender de GPO. Nos clientes de domínio, a DCSI pode usar políticas próprias de distribuição. Nos restantes clientes, definir procedimento autorizado. Estabelecer VPN não instala confiança. Emissão, instalação e renovação do certificado do site exigem procedimento próprio. [W22] [W28]

Nas chamadas HTTPS originadas por WordPress/PHP, validar a cadeia no cliente HTTP utilizado. O WordPress admite um ficheiro próprio de autoridades de certificação. A homologação deve testar estes pedidos, além do browser, mantendo ativa a verificação TLS. [W29]

O domínio AD e as OUs não fazem parte da autenticação nem da operação obrigatória da intranet. Qualquer proposta futura de integração AD exige decisão expressa e não altera DEC-14 automaticamente.

### 3.9 Fluxos de rede e firewall

As regras serão aplicadas localmente no Windows Firewall de .205 e nos equipamentos competentes, preservando AD DS, DNS e VPN. Confirmar o perfil de rede e as regras locais efectivas sem pressupor perfil Domain ou GPO. Delimitar explicitamente as origens LAN e VPN autorizadas. [W26]

| Fluxo | Política proposta |
| --- | --- |
| Clientes ARN para o portal | TCP 443, a partir das redes internas autorizadas. A rede observada é 192.168.17.0/24. |
| Clientes VPN para o portal | TCP 443, a partir da sub-rede ou origem efetiva da VPN, depois de verificada. Não presumir que pertence ao mesmo /24. |
| HTTP para o portal | TCP 80 apenas para redirecionamento interno, se necessário. ACME DNS-01 não exige abertura pública desta porta. |
| PHP para MySQL no mesmo servidor .205 | Ligação por loopback, com MySQL sem escuta na LAN. Sem publicação da porta 3306. |
| Administração Windows | RDP apenas a partir de origens e contas administrativas autorizadas pela DCSI. |
| Resolução DNS | Consultar o DNS institucional .151, se aprovado pela DCSI, a partir de .205 e dos clientes autorizados, por UDP/TCP 53. Preservar a resolução dos nomes externos necessários. |
| Dependências AD de .205 | Não exigir GPO, Kerberos, LDAP, conta de computador nem autenticação AD. Apenas DNS institucional se aprovado. Hora e administração locais com procedimento definido pela DCSI. |
| Saída para Google | HTTPS dos browsers e do servidor para os serviços necessários ao OIDC. Considerar proxies, validação de certificados e sincronização de hora. |
| Certificados e atualizações | Saída estritamente necessária pelos mecanismos aprovados de emissão, renovação e distribuição. |
| Internet para o portal | Sem publicação ou encaminhamento público para o IIS, a base de dados ou o RDP. |

Acesso por VPN, DNS interno e sessão WordPress são controlos complementares. A resolução do nome não substitui as regras de rede, e estar na rede não concede acesso ao conteúdo.

<a id="organizacao"></a>
## 4. Estrutura institucional e funcionários

<a id="lista-pessoal"></a>
### 4.1 Fonte documental de unidades e pessoas

A transcrição nominal fornecida pelo responsável permanece no histórico privado do repositório, no README do commit 97274ac, secção 4.1. O catálogo de unidades está em Organization/DocumentaryCatalog. A transcrição é uma fonte documental, não uma atribuição de contas, cargos ou competências. Dados operacionais e inventários Workspace ficam fora do Git.

### 4.2 Modelo da unidade orgânica

Cada unidade deve ter identificador estável, designação, sigla, tipo, unidade superior quando aplicável, descrição funcional, responsável designado, estado e datas de validade.

O modelo deve suportar órgão, direção, departamento, núcleo, serviço, secretariado, gabinete, fundo e outro tipo aprovado. A hierarquia deve impedir ciclos. Alterar o nome de uma unidade não deve alterar o seu identificador nem perder associações.

A sigla não é uma chave globalmente única. Os dois departamentos DGE terão identificadores distintos, com códigos de referência como DRE-DGE e DREC-DGE, conservando a sigla apresentada no anexo. Registar fonte e estado de validação das relações superiores. Uma relação desconhecida deve permanecer pendente e não ser apresentada como posição definitiva no organograma.

Uma unidade desativada conserva o histórico. A desativação exige resolver vínculos ativos e encaminhar processos pendentes. Não se deve eliminar uma unidade apenas para a retirar do organograma corrente.

### 4.3 Registo do funcionário

| Grupo | Dados previstos |
| --- | --- |
| Identificação profissional | Identificador interno, nome e email institucional. |
| Enquadramento | Unidade principal, departamento quando aplicável, cargo ou função e responsável hierárquico. |
| Contacto de trabalho | Contacto profissional e localização de trabalho, quando aplicáveis. |
| Perfil | Fotografia e campos de apresentação autorizados pela ARN. |
| Acesso | Conta WordPress associada, identificador estável da identidade Google, estado e funções autorizadas. |
| Histórico | Datas de entrada, alteração de vínculo, transferência e desativação necessárias ao portal. |
| Participação transversal | Equipas ou comissões autorizadas, quando esta funcionalidade for ativada. |

O diretório geral apresenta apenas os campos profissionais aprovados para partilha interna. Dados de saúde, bancários, documentos de identificação e processos individuais não pertencem ao diretório.

RH valida os dados profissionais. A administração de acessos atribui permissões segundo a decisão funcional autorizada. O funcionário altera apenas os campos de autosserviço definidos.

### 4.4 Entrada dos funcionários no sistema

A lista da secção 4.1 é a referência documental inicial para o cadastro. Antes de uma importação operacional, RH valida a grafia, identificadores profissionais, unidades, cargos e emails institucionais. Prever importação controlada por CSV para evitar criação manual repetitiva.

O código de importação e os exemplos usam dados fictícios. O README nominativo não é um ficheiro executável de criação de contas. Não gerar emails a partir dos nomes, associar contas por semelhança de nome ou deduzir a identidade Google a partir da ordem das linhas.

A importação deve oferecer validação prévia, detetar duplicados e unidades inexistentes, apresentar erros por registo e produzir resumo de alterações. Uma importação não deve atribuir privilégios técnicos nem desativar pessoas por mera ausência numa lista incompleta.

A correspondência com a conta Google exige identidade validada e registo local autorizado. Ficheiros reais de importação permanecem nos sistemas internos aprovados.

### 4.5 OUs do Active Directory como referência futura

A imagem do Active Directory mostra uma OU ARN com várias OUs filhas. As OUs representam organização técnica, delegação e políticas, sem equivalência automática com o organograma institucional. [W18]

O catálogo documental atual segue a lista da secção 4.1. A designação anterior NIC.gw, a entrada NIC no novo anexo e as OUs com nomes próximos precisam de correspondência validada. As entradas observadas DCT-QOS e DCGT&QoS não serão fundidas automaticamente com DCT-Q&S.

Uma eventual análise documental de OUs só pode ocorrer numa fase posterior expressamente autorizada. A intranet não depende de importação ou sincronização AD. A pertença a OU não atribui funções WordPress. DEC-14 mantém o servidor fora do domínio.

### 4.6 Pontos de validação da lista

| Ponto | Informação recebida | Tratamento no desenvolvimento |
| --- | --- | --- |
| Agrupamento das direções | DSU, DSGI e DSIC seguem DCSI. DFC e DPL seguem DF. DRS, DGE e DFV seguem DRE. DQ&S e DCT seguem DCT-Q&S. DSAU, DMTC e DEEP seguem DMAO. DRB e DARHF seguem DRH. DLR e DAJDC seguem DRAJDC. DGE, DCRP e DRIC seguem DREC. | Usar este agrupamento documental para preparar o modelo e a validação. RH confirma a hierarquia operacional antes de publicar o organograma. |
| Duas entradas DGE | Departamento de Gestão de Espetro na DRE e Departamento de Gestão e Estratégia na DREC. | Manter unidades e identificadores distintos. Não deduplicar pela sigla. |
| DSU, DSGI e DSIC | O anexo não preenche o significado. | Conservar as siglas e deixar a designação extensa pendente. |
| NIC e NIC.gw | O novo anexo indica NIC, Núcleo Informático e Comunicação. O responsável tinha indicado NIC.gw no catálogo inicial. | Confirmar equivalência, designação canónica e eventuais aliases. Não criar duas unidades operacionais nem fundi-las sem validação. |
| SOS-DNS e SGND | Aparecem depois de NIC, sem hífen ou indicação expressa da unidade superior. | Registar ambos os serviços. Manter a ligação superior por confirmar. |
| Arquivo e Recepção | Aparecem após as linhas da DRH, sem indicação expressa de subordinação. | Representar as entradas sem assumir dependência da DRH. |
| CA, Secretaria CA, Conselheiros CA e FAU | As designações e o pessoal foram fornecidos. Não há descrição de competências, presidência ou circuito de aprovação. | Preservar as designações e validar as relações de governação, cargos e limites de acesso. |
| Grafia e nomes incompletos | Entre outros, o anexo contém Cigei Lopes a, Epinafanio fernandes, Yasmine, Tchungana e Michel. Também contém Direção Financeiro, Renumerações e Institutionais. | Manter a transcrição de origem. RH valida a grafia canónica e a identificação completa antes da importação. |
| Contas e permissões | A lista não contém emails, identificadores de identidade ou funções no portal. | Completar o cadastro por fonte aprovada e atribuir permissões explicitamente. Não derivar privilégios da linha, cargo presumido ou OU. |

Estes pontos esclarecem parcialmente PEN-01 e PEN-05. Não impedem construir a fundação técnica nem testar o modelo com dados fictícios. Continuam necessários antes de importar pessoas reais, publicar o organograma definitivo ou ativar autorizações dependentes da hierarquia.

<a id="permissoes"></a>
## 5. Perfis e permissões

### 5.1 Três dimensões de autorização

A autorização combina:

1. Função: quais as ações permitidas.
2. Âmbito: quais as unidades, equipas ou pessoas abrangidas.
3. Registo: relação do utilizador com o conteúdo e estado do processo.

Pertencer à DCSI, DRH ou outra direção não atribui automaticamente todas as permissões funcionais dessa área. Ser diretor também não atribui o papel técnico Administrator do WordPress.

WordPress fornece papéis e capabilities personalizadas. As regras por unidade, titular, destinatário e estado terão de ser implementadas no plugin institucional. [W3]

### 5.2 Matriz funcional

| Perfil funcional | Ações principais | Âmbito |
| --- | --- | --- |
| Funcionário | Consultar portal, gerir campos próprios, acompanhar tarefas, criar pedidos e tickets, preparar notícias | Próprio registo e conteúdos autorizados. |
| Responsável de unidade | Acompanhar equipa, atribuir tarefas, validar pedidos e publicar comunicados de equipa | Unidade ou equipa para a qual recebeu responsabilidade. |
| RH | Gerir dados profissionais, documentos administrativos, férias e ausências | Processos e pessoas incluídos na competência atribuída. |
| Suporte TI | Consultar, assumir, responder e resolver tickets | Fila e tickets autorizados para o serviço de suporte. |
| Equipa editorial | Analisar notícias, aprovar, rejeitar e gerir publicação institucional | Fila editorial e públicos para os quais recebeu autorização. |
| Administração de acessos | Gerir contas locais, funções, âmbitos e bloqueios | Competências administrativas delegadas. |
| Administração técnica | Manter WordPress, tema, plugins, ambientes e operação | Acesso privilegiado restrito aos responsáveis técnicos designados. |
| Órgãos de direção e fiscalização | Consultar ou decidir matérias atribuídas | Perfil a definir por necessidade. A posição institucional não implica acesso universal a processos RH. |

Uma pessoa pode acumular funções autorizadas. O CMS deve permitir identificar a origem, o âmbito e a duração das permissões.

Contas com controlo do servidor, da base de dados ou com capacidade de instalar código têm acesso técnico privilegiado. A matriz funcional não constitui isolamento contra esses administradores. Esse acesso exige gestão própria, atribuição restrita e registo das operações.

### 5.3 Audiência e confidencialidade

Os conteúdos devem distinguir classificação e destinatários.

| Audiência | Exemplos |
| --- | --- |
| Toda a ARN | Notícias institucionais aprovadas, procedimentos gerais e eventos comuns. |
| Uma ou várias unidades autorizadas | Documentação de trabalho e comunicados de equipas. |
| Pessoas ou intervenientes expressamente autorizados | Pedido individual, tarefa atribuída, ticket e documento pessoal de RH. |

Todas estas audiências são internas. “Publicado” significa disponível aos utilizadores autorizados dentro do portal.

A autorização deve ser aplicada nas páginas, consultas à base de dados, APIs, pesquisa, exportações, contagens, notificações e ficheiros. Ocultar um botão ou filtrar apenas o menu não cumpre este requisito.

<a id="administradores-iniciais"></a>
### 5.4 Contas administrativas iniciais

As designações nominais permanecem no histórico privado, README do commit 97274ac, secção 5.4. Uma designação não activa privilégios. Confirmar identidade issuer/sub e concessão local auditada. O domínio, nome de email ou privilégio Google não concede competências no portal.

<a id="paginas"></a>
## 6. Estrutura de páginas e gestão pelo CMS

### 6.1 Navegação prevista

| Área | Conteúdo e ações | Fase |
| --- | --- | --- |
| Entrar | Identidade ARN, entrada Google Workspace e instrução de acesso pela rede ou VPN | F1 |
| Painel | Nome, notificações, tarefas, próximos eventos e informação recente autorizada | F1 |
| ARN e unidades | Apresentação interna, organograma validado e páginas das unidades | F1 |
| Funcionários | Pesquisa por nome, unidade e cargo. Perfil profissional autorizado | F1 |
| Notícias | Notícias aprovadas, pesquisa e filtros básicos | F1 |
| As minhas notícias | Rascunhos, submissões, decisões e correções do autor | F1 |
| Revisão editorial | Fila de análise e decisão da equipa editorial | F1 |
| Comunicados | Avisos da instituição e das equipas autorizadas | F1 |
| Documentos | Categorias, versões atuais e acesso protegido | F1 |
| Tarefas | Minhas tarefas e gestão de equipa quando autorizada | F1 |
| Calendário | Consulta de eventos. Criação por responsáveis designados | F1 |
| Pedidos | Submissão, acompanhamento e decisões dos processos definidos | F2 |
| Férias e ausências | Pedidos de férias, tratamento RH e disponibilidade autorizada | F2 |
| Suporte | Tickets do funcionário e fila do serviço TI | F2 |
| Notificações | Avisos por destinatário e marcação como lidos | F1 |
| Meu perfil | Consulta e edição dos campos autorizados | F1 |
| Administração | Utilizadores, estrutura, funções, configurações e auditoria | F1 |

A pesquisa básica acompanha cada módulo quando este entra em serviço. Pesquisa avançada entre múltiplos sistemas fica para evolução.

O painel apresenta apenas módulos ativados. Estados sem dados devem explicar a situação e oferecer a ação adequada. Módulos de fases futuras não devem apresentar números fictícios nem ações inoperantes.

### 6.2 Requisitos para ser um CMS

Os utilizadores autorizados devem conseguir executar pelo painel de gestão, sem editar código:

- Criar e atualizar páginas institucionais, notícias, comunicados e eventos.
- Editar menus, blocos e padrões dentro dos limites da identidade visual aprovada.
- Gerir unidades, funcionários e associações.
- Organizar documentos, categorias, versões e destinatários.
- Gerir a fila editorial e consultar o histórico de decisões.
- Atribuir funções e âmbitos através de operações administrativas controladas.
- Configurar parâmetros expostos pela aplicação, incluindo tipos e limites de anexos.

As configurações críticas devem exigir permissões próprias e registo de alteração. As regras de segurança e a validação de processos permanecem no plugin, mesmo quando o utilizador muda opções pela interface.

Os funcionários usam uma interface de portal com as ações adequadas à sua função. O acesso a wp-admin é concedido conforme a necessidade. As APIs e ações de administração mantêm verificações de autorização em ambos os casos.

<a id="autenticacao"></a>
## 7. Autenticação e ciclo de vida do acesso

### 7.1 Sequência de entrada

1. O funcionário estabelece acesso pela rede ARN ou VPN.
2. Abre a página de entrada do portal.
3. Escolhe a autenticação institucional Google Workspace.
4. O Google autentica a pessoa segundo a política institucional.
5. O browser regressa ao endereço de retorno registado para o portal.
6. O servidor valida a resposta de identidade.
7. O plugin confirma a associação a um funcionário autorizado e ativo.
8. WordPress estabelece a sessão e abre o painel com as permissões aplicáveis.

O endereço de retorno OIDC precisa de estar acessível ao browser na rede interna ou VPN. A troca de código por tokens é uma ligação de saída do servidor. Esta arquitetura não exige tornar o portal acessível publicamente. O fluxo e a validação do endereço de retorno seguem a documentação Google. [W5]

### 7.2 Requisitos da integração Google

Usar Authorization Code Flow, endereço HTTPS de retorno registado exatamente e cliente OAuth institucional. Validar assinatura, emissor, destinatário, expiração, state e nonce. Para a configuração inicial da ARN, validar a claim hd com o valor arn.gw e associar a identidade estável por sub. O sufixo do email não substitui estas verificações. Pedir apenas openid e email. [W5]

O primeiro vínculo a uma conta previamente cadastrada deve ser controlado. Uma mudança de email não deve criar outro funcionário. Uma conta com o mesmo email e identificador Google diferente exige nova validação.

A criação automática irrestrita de utilizadores deve ficar desativada. Uma identidade Google válida, por si só, não autoriza entrada no portal nem atribui funções.

O login exige conectividade de saída para o Google nos dispositivos e no servidor. Durante indisponibilidade do fornecedor de identidade, mostrar erro controlado e canal de suporte. A rede interna não elimina esta dependência.

A página de entrada, o retorno OIDC e os recursos estritamente necessários ao login precisam de exceções ao bloqueio por sessão. Essas exceções mantêm a restrição de rede e não expõem conteúdos institucionais.

### 7.3 Sessões, recuperação e saída

A palavra-passe institucional e a sua recuperação pertencem ao Google Workspace. A aplicação não deve apresentar um formulário para recolher a palavra-passe Google.

A sessão Google e a sessão do portal são distintas. Suspender uma conta Google não deve ser tratado como revogação automática das sessões WordPress. [W7]

O procedimento de saída de um funcionário deve desativar a autorização local, revogar todas as sessões WordPress, tratar as permissões e processos pendentes e coordenar a suspensão no Workspace. WordPress dispõe de mecanismos de revogação de sessões por utilizador. [W8]

Verificar o estado local da conta em cada pedido autenticado. Aplicar as alterações de âmbito nas consultas seguintes e invalidar caches associados. O histórico dos documentos e decisões deve conservar a autoria necessária.

Definir antes da entrada em serviço duração máxima, inatividade, encerramento de sessão e gestão de dispositivos partilhados. A política MFA será aplicada no Google Workspace, com prioridade aos acessos privilegiados. Não pressupor que um plugin WordPress ativa MFA no Workspace.

Uma eventual conta técnica de recuperação local exige procedimento próprio e acesso restrito. A necessidade, guarda, utilização e auditoria dessa exceção estão em PEN-10. O acesso corrente dos funcionários segue Google Workspace.

### 7.4 Autenticação da API

A interface integrada utiliza as sessões WordPress. Pedidos de alteração precisam de proteção contra CSRF e verificação de capabilities e do registo. Um nonce não substitui autorização.

Cada endpoint personalizado deve definir a respetiva verificação de acesso. A REST API necessária ao editor deve continuar a funcionar para os utilizadores autorizados. [W4]

As contas dos funcionários utilizam exclusivamente a autenticação Google. Bloquear o login WordPress por palavra-passe local e a recuperação local de palavra-passe para essas contas, incluindo rotas nativas e alternativas. A eventual exceção técnica de recuperação segue PEN-10 e não se aplica ao acesso corrente dos funcionários.

Desativar XML-RPC e application passwords enquanto não existir uma integração expressamente autorizada e documentada. A instalação do cliente OIDC, por si só, não demonstra que estas vias ficaram bloqueadas.

<a id="dados"></a>
## 8. Dados, documentos e armazenamento

### 8.1 Entidades e persistência proposta

| Entidade | Persistência prevista | Responsabilidade |
| --- | --- | --- |
| Conta | Utilizador WordPress e metadados autorizados | Ligação à identidade Google, estado e preferências. |
| Unidade | Tipo de conteúdo hierárquico arn_unidade | Estrutura institucional gerida no CMS. |
| Vínculo profissional | Metadados para o vínculo corrente e histórico versionado pelo plugin | Unidade principal, função e alterações aprovadas. |
| Notícia | Tipo de conteúdo arn_noticia | Conteúdo, versões, aprovação, público e histórico editorial. |
| Comunicado | Tipo de conteúdo arn_comunicado | Mensagem, destinatários, validade e circuito aplicável. |
| Documento | Tipo de conteúdo arn_documento e registo de versões | Metadados, proprietário institucional, ficheiro e autorização. |
| Evento | Tipo de conteúdo arn_evento | Datas, local ou ligação, organizador e audiência. |
| Tarefa | Tabelas do módulo de tarefas | Responsável, prazo, estado e histórico. |
| Pedido | Tabelas de pedidos e decisões | Referência, tipo, formulário, etapas e intervenientes. |
| Pedido de férias | Extensão transaccional de pedidos em F2 | Funcionário, ano de referência, início, fim, dias úteis calculados, estado, submissão, decisão, responsável e data da decisão, observação e histórico. |
| Direito anual e movimentos de férias | Registo por funcionário e ano civil | Direito padrão 22, dias gozados, futuros aprovados, saldo disponível e movimentos auditados. Aprovação e saldo gravados atomicamente para impedir excesso concorrente. |
| Calendário RH | Configuração versionada de feriados e dias de trabalho | Feriados oficiais validados por RH. Guardar referência à versão usada no cálculo e no histórico. |
| Ticket | Tabelas de tickets e mensagens | Solicitante, técnico, respostas e resolução. |
| Notificação | Tabela por destinatário | Evento de origem, leitura e entrega. |
| Auditoria | Registo específico com acesso restrito | Ação, interveniente, objeto, resultado e data. |
| Ficheiro privado | Armazenamento privado e metadados ligados ao objeto | Conteúdo binário, versões e regras de entrega. |

Os nomes de tabelas, índices, migrações e relações serão especificados na tarefa F0-06. Usar o prefixo configurado do WordPress. Avaliar metadados nativos antes de criar tabelas, reservando tabelas próprias para relações e consultas transacionais que as justifiquem. [W9]

O plugin deve separar acesso aos dados, regras de negócio, autorização e apresentação. Todos os registos precisam de identificadores estáveis e datas. Usar UTC nos registos técnicos e apresentação de datas em português.

### 8.2 Regras de integridade

- Não permitir decisões contraditórias sobre a mesma versão de um pedido ou notícia.
- Verificar novamente estado, versão e autorização ao gravar uma alteração.
- Guardar a decisão e o evento de notificação de forma consistente.
- Preservar autor, aprovador e contexto histórico quando um funcionário muda de unidade.
- Evitar referências sem objeto válido, atribuições a contas desativadas e unidades hierárquicas circulares.
- Versionar alterações de estrutura de dados e prever atualização e recuperação de versão.

### 8.3 Ficheiros efetivamente privados

WordPress dispõe de diretórios e URLs próprios para uploads. Proteger a página de um documento não demonstra proteção do ficheiro. A arquitetura deve tratar a entrega do binário como uma operação autorizada. [W11]

Proposta: guardar ficheiros institucionais fora da raiz física do site IIS. O plugin valida sessão, conta, permissão e objeto e entrega o ficheiro através de streaming controlado em PHP. Não criar um diretório virtual IIS nem uma partilha de utilizadores que permita contornar esta verificação.

A entrega deve validar o identificador do ficheiro, resolver apenas caminhos internos autorizados, impedir travessia de diretórios e emitir cabeçalhos adequados ao tipo de conteúdo. Homologar ficheiros maiores, pedidos parciais quando necessários e consumo de memória. Uma futura otimização da entrega exige manter a autorização por objeto.

Imagens, miniaturas, pré-visualizações, galerias, anexos de notícias em análise e versões anteriores seguem a mesma regra. A biblioteca de media, endpoints e listagens não devem permitir a um autor consultar os ficheiros privados de outro.

O autor de uma notícia mantém acesso aos próprios ficheiros durante edição. A equipa editorial autorizada acede ao material submetido. Os restantes funcionários só recebem acesso quando a versão correspondente é aprovada e a audiência os inclui.

Definir lista de tipos admitidos, limites por ficheiro e por envio, validação do tipo real e tratamento de ficheiros suspeitos. Bloquear executáveis e ficheiros ativos não autorizados. Os valores operacionais serão aprovados em PEN-08.

Uma alteração ao documento pai deve atualizar a autorização das versões, derivados e caches. A aprovação não deve ser contornada pela substituição posterior do ficheiro já aprovado.

#### Organização proposta das pastas Windows

Os caminhos seguintes são exemplos de planeamento. A existência e capacidade do volume D: não foram verificadas. Usar o volume de dados aprovado durante o inventário.

| Caminho de referência | Finalidade | Acesso de serviço |
| --- | --- | --- |
| D:\ARN\Portal\public | Raiz física IIS com WordPress, tema e plugins | Leitura e execução para o pool. Escrita de código apenas pelo processo de manutenção autorizado. |
| D:\ARN\Portal\private | Ficheiros institucionais e derivados privados | Acesso do serviço para guardar e transmitir após autorização. Sem diretório virtual IIS nem partilha para funcionários. |
| D:\ARN\Portal\runtime | Temporários de upload, processamento e cache local controlada | Escrita limitada ao serviço. Sem execução de uploads como código. |
| D:\ARN\Portal\config | Configuração protegida e segredos necessários ao serviço | Leitura mínima pelo processo. Alteração apenas por administradores autorizados. |
| D:\ARN\Portal\logs | Registos de aplicação e processamento | Escrita pelo serviço e leitura por operadores autorizados. |
| D:\ARN\Data\MySQL | Dados do serviço MySQL | Acesso pela identidade do MySQL. Sem acesso direto pelo pool IIS. |
| Destino de backup fora do servidor da aplicação | Cópias protegidas e recuperáveis | Identidade de backup e operadores autorizados. Destino e autenticação a definir. |

Rever heranças NTFS para evitar permissões amplas de escrita. As ACL protegem o sistema de ficheiros entre processos e contas do Windows. O plugin continua responsável por decidir qual funcionário pode receber cada ficheiro.

As regras de localização e entrega abrangem também os uploads nativos e as imagens que o editor de blocos produz. Não deve existir uma segunda cópia desprotegida em wp-content/uploads.

O CMS mantém a edição de conteúdos, menus e configurações autorizadas na base de dados. Alterações a ficheiros de código seguem o procedimento de entrega técnica.

### 8.4 Relação com os sistemas existentes

As fontes do projeto referem Google Workspace, Primavera e Alfresco. A existência dessas referências não confirma as configurações, interfaces ou licenças atualmente disponíveis.

| Domínio | Orientação inicial |
| --- | --- |
| Identidade | Google Workspace é a decisão confirmada para autenticação. |
| Dados profissionais mínimos do portal | Validação por RH. Definir origem e processo de atualização em PEN-05. |
| Notícias, tarefas e conteúdo criado no portal | WordPress e módulos institucionais são a origem desses registos. |
| Documentos oficiais | Definir por categoria se a origem é o portal ou Alfresco/outro repositório aprovado. |
| Férias e saldos | RH identifica o sistema oficial e as regras antes de ativar o módulo. |
| Calendários externos | Integração futura, mediante necessidade e regras de partilha. |

Uma ligação a um documento no Alfresco ou Drive conserva as permissões do sistema de origem. A autenticação no portal não concede acesso automaticamente ao sistema externo.

Qualquer integração futura deve identificar origem oficial, proprietário dos dados, campos, sentido de sincronização, frequência, autorização, tratamento de erros e reconciliação. O login Google não exige acesso a Gmail, Drive ou ao diretório completo de funcionários.

<a id="historias"></a>
## 9. Catálogo das 46 histórias originais

Esta secção consolida os comportamentos do anexo. Os códigos originais mantêm-se. Os critérios devem ser lidos com as regras transversais de acesso, ficheiros, auditoria e estados deste README.

F1 corresponde ao núcleo do portal. F2 corresponde aos serviços administrativos e suporte. A atribuição por fases é uma proposta de execução coerente com a opção confirmada de desenvolvimento faseado.

### 9.1 Funcionário

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-F01 | Iniciar sessão para aceder ao espaço de trabalho | Página de entrada institucional. Autenticação Google validada. Conta local autorizada e ativa. Erro controlado em falha. Acesso ao painel e bloqueio de áreas não autorizadas. Atualização expressa do formulário de credenciais previsto no anexo. | F1 |
| US-F02 | Consultar painel pessoal | Apresenta nome, notificações, tarefas pendentes, próximos eventos, comunicados recentes e acessos rápidos. Todos os dados respeitam a autorização. Prevê estados sem dados. | F1 |
| US-F03 | Consultar colegas | Pesquisa por nome. Apresenta nome, cargo e unidade. Mostra apenas os dados profissionais autorizados. | F1 |
| US-F04 | Consultar tarefas | Mostra título, descrição, prioridade, prazo e estado. Permite filtrar por estado. Apresenta apenas tarefas acessíveis à pessoa. | F1 |
| US-F05 | Atualizar uma tarefa própria | O funcionário atualiza o estado das tarefas atribuídas. O servidor valida a relação e a transição. A alteração fica no histórico e é visível ao responsável autorizado. | F1 |
| US-F06 | Consultar documentos | Organização por categorias. Consulta e descarga autorizadas. Documentos restritos não surgem para pessoas sem acesso. Proteção inclui ficheiro, versões e pré-visualizações. | F1 |
| US-F07 | Consultar calendário | Eventos apresentam título, data, hora e local ou ligação. É possível consultar eventos futuros. Eventos privados respeitam a audiência. | F1 |
| US-F08 | Submeter e acompanhar pedidos | Seleção do tipo de pedido. Validação dos campos e anexos permitidos. Emissão de referência única. Consulta do estado e histórico autorizado. | F2 |
| US-F09 | Abrir ticket de suporte | Assunto, descrição e prioridade. Anexo permitido. Identificação única. Acompanhamento do estado pelo solicitante. | F2 |
| US-F10 | Consultar e atualizar perfil | Apresenta os dados da pessoa. Apenas campos de autosserviço são editáveis. As alterações são validadas e guardadas. Unidade, cargo e funções exigem o processo institucional competente. | F1 |

### 9.2 Responsável de unidade

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-M01 | Consultar a equipa | Lista pessoas da unidade autorizada e os dados profissionais necessários. Dados de gestão de outras unidades ficam restritos. Mantém acesso ao diretório institucional comum. | F1 |
| US-M02 | Criar e atribuir tarefas | Indica título, descrição, responsável, prioridade e prazo. Atribui dentro do âmbito autorizado. O funcionário recebe notificação e vê a tarefa no painel. | F1 |
| US-M03 | Acompanhar tarefas da equipa | Filtra por funcionário e estado. Identifica prazos e atrasos. Permite abrir detalhes autorizados. | F1 |
| US-M04 | Validar pedidos | Consulta pendências da etapa atribuída. Aprova, rejeita ou devolve segundo o circuito definido. Justifica rejeição ou devolução. O funcionário recebe a decisão. | F2 |
| US-M05 | Publicar comunicados | Regista título, conteúdo e destinatários. Publicação limitada ao âmbito autorizado. Notifica destinatários. Comunicado institucional segue regra editorial própria. | F1 |

### 9.3 Recursos Humanos

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-RH01 | Gerir registos de funcionários | Pesquisa e consulta dos dados autorizados. Criação, alteração e desativação conforme a competência atribuída. Operações importantes registadas. Coordenação com contas e vínculos. | F1 |
| US-RH02 | Processar pedidos de férias | RH decide o período com aprovação final, sem cadeia adicional presumida. Direito anual de 22 dias úteis, repartível por vários pedidos prévios. Validar saldo, feriados e sobreposição. Auditar aprovação, rejeição, alteração e cancelamento. | F2 |
| US-RH03 | Gerir documentos administrativos | Carrega documentos permitidos, organiza categorias e define destinatários. Documentos confidenciais têm acesso restrito, incluindo os binários. | F1 |
| US-RH04 | Consultar ausências | Calendário colectivo mostra apenas ausências aprovadas, com funcionário, unidade e datas. Filtra por unidade e período. Não expõe motivos nem documentos confidenciais. | F2 |

### 9.4 Suporte informático

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-TI01 | Consultar tickets do suporte | Lista tickets novos, em tratamento e resolvidos da fila autorizada. Filtra por prioridade e estado. Apresenta solicitante e descrição. | F2 |
| US-TI02 | Assumir um ticket | Técnico autorizado assume atendimento. O sistema regista responsável e transição para Em tratamento. Impede dupla atribuição contraditória. | F2 |
| US-TI03 | Responder e atualizar ticket | Acrescenta resposta técnica e altera estado conforme permissão. O solicitante consulta as atualizações que lhe são destinadas. | F2 |
| US-TI04 | Resolver ticket | Regista solução, data de resolução e técnico. Altera estado e notifica o solicitante. | F2 |

### 9.5 Administração

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-A01 | Gerir contas | Criar, consultar, editar e desativar registos locais autorizados. Associar funções. Conta desativada perde novos acessos e sessões ativas. Operações administrativas ficam registadas. | F1 |
| US-A02 | Gerir funções e permissões | Define ações para funcionários, responsáveis, RH, TI, equipa editorial e administração. Bloqueia acesso direto indevido. Aplica verificações na interface e no servidor. | F1 |
| US-A03 | Gerir unidades | Cria, edita e desativa unidades hierárquicas. Associa funcionários. Preserva relações válidas e histórico. Adapta a referência genérica a departamentos à organização ARN. | F1 |
| US-A04 | Consultar auditoria | Apresenta interveniente, ação, objeto, data e hora. Acesso restrito. Funcionários comuns não alteram nem apagam registos. | F1 |
| US-A05 | Configurar portal | Permite gerir parâmetros autorizados. Configurações críticas exigem competência administrativa e ficam registadas. | F1 |

### 9.6 Histórias transversais

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-S01 | Receber notificações | Notifica atribuições, decisões, tickets e comunicações conforme o módulo ativo. Permite marcar como lida. Entrega apenas a destinatários autorizados. | F1, com eventos F2 acrescentados nessa fase |
| US-S02 | Pesquisar informação | Pesquisa funcionários e conteúdos dos módulos ativos. Identifica tipo de resultado. Exclui registos, excertos e contagens restritos. | F1, com índices F2 acrescentados nessa fase |
| US-S03 | Proteger a sessão | Permite terminar sessão. Aplica autenticação, autorização, expiração e revogação. Prevê acesso através de rede ARN ou VPN. | F1 |
| US-S04 | Usar vários dispositivos | Páginas adaptadas a computador, tablet e telemóvel. Menus, formulários e botões mantêm utilização e legibilidade. O dispositivo necessita de acesso à rede autorizada. | F1 |

### 9.7 Notícias

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-NOT-01 | Criar notícia | Título, texto e imagem de destaque. Guarda rascunho sem publicação. Regista autor e data. | F1 |
| US-NOT-02 | Adicionar galeria | Permite várias imagens ligadas à notícia, pré-visualização, remoção e reordenação antes de submissão. Valida formato e tamanho. A galeria aprovada apresenta-se organizada. | F1 |
| US-NOT-03 | Anexar documentos | Adiciona um ou mais ficheiros admitidos. Mostra e permite remover antes de submeter. Valida limites configurados. Mantém associação durante revisão e acesso igual ao da notícia. | F1 |
| US-NOT-04 | Pré-visualizar notícia | Mostra título, texto, destaque, galeria e anexos. Pré-visualização não publica conteúdo. Autor regressa à edição autorizada. | F1 |
| US-NOT-05 | Enviar para aprovação | Ação de submissão altera estado para Pendente. Regista data e notifica equipa editorial. Autor não publica diretamente. | F1 |
| US-NOT-06 | Consultar o estado das próprias notícias | Mostra Rascunho, Pendente, Publicada e Rejeitada. Apresenta motivo da decisão. Permite corrigir e reenviar uma rejeitada. Não dá acesso aos rascunhos alheios. | F1 |
| US-NOT-07 | Receber decisão editorial | Notifica aprovação ou rejeição ao autor. Inclui motivo quando a decisão exige correção e ligação para o conteúdo autorizado. | F1 |
| US-NOT-08 | Consultar fila editorial | Equipa editorial consulta pendências e filtra por autor, data e estado. Abre texto, imagens e anexos completos. Acesso exige permissão explícita. | F1 |
| US-NOT-09 | Aprovar e publicar | Ação editorial autorizada aprova uma versão e disponibiliza-a à audiência interna. Regista aprovador e data. Conteúdo, imagens e anexos dessa versão seguem a decisão. | F1 |
| US-NOT-10 | Rejeitar notícia | Ação editorial impede publicação, regista motivo e notifica autor. Autor corrige e reenvia. A justificação obrigatória é melhoria proposta nesta versão. | F1 |
| US-NOT-11 | Editar antes da aprovação | Autor edita Rascunho ou Rejeitada. Pendente fica bloqueada até devolução autorizada para edição. Alterações à versão publicada seguem nova aprovação. | F1 |
| US-NOT-12 | Consultar notícias publicadas | Lista apenas versões aprovadas acessíveis. Mostra autor, data, texto, imagem, galeria e anexos existentes. Exclui estados privados de outros autores. | F1 |
| US-NOT-13 | Pesquisar e filtrar notícias | Pesquisa por título e texto. Filtros básicos por data e categoria. Resultados respeitam audiência e estado editorial. | F1 |
| US-NOT-14 | Registar auditoria editorial | Regista criação, submissão, aprovação, rejeição, alterações e eliminação autorizada. Inclui interveniente, data, ação e notícia. Funcionários comuns não modificam o histórico. | F1 |

<a id="melhorias"></a>
## 10. Melhorias propostas às histórias

### 10.1 Correções de coerência

| Ponto do anexo | Tratamento proposto |
| --- | --- |
| Formulário de utilizador e palavra-passe em US-F01 | Adaptar à decisão de autenticação Google Workspace. O portal conserva página própria de entrada. |
| “Pública” e “área pública” nas notícias | Usar “publicada no portal para utilizadores autorizados”. |
| Administrador como aprovador de notícias | Substituir pela competência da equipa editorial designada, sem exigir administração técnica. |
| Departamento tratado como lista simples | Representar unidades e relações hierárquicas validadas. |
| Responsável não acede a dados de outras unidades, mas existe diretório comum | Separar contactos profissionais partilhados de processos de gestão e RH restritos. |
| Motivo de rejeição opcional em alguns critérios | Propor motivo obrigatório em rejeições e devoluções para permitir correção e auditoria. |
| Notificações classificadas como prioridade média | Incluir notificação básica na mesma fase das tarefas e decisões que dela dependem. |
| Notícias ausentes do menu e da prioridade inicial | Incluir menu, fila editorial e módulo completo em F1. |
| Pesquisa avançada futura e pesquisa transversal obrigatória | Entregar pesquisa básica por módulo desde a sua ativação. |
| Painel exige eventos, mas calendário é classificado como médio | Incluir calendário básico e regras de gestão em F1. |
| Mistura de português, francês e inglês nos menus | Usar português de Portugal na interface inicial. |
| Edição de notícia publicada sem regra para versão anterior | Manter a versão aprovada enquanto uma revisão segue análise, com retirada autorizada quando necessária. |

### 10.2 Histórias complementares

Estas 12 histórias são propostas para validação funcional. Não substituem os códigos originais. P0 indica um requisito para disponibilizar o núcleo com acesso correto. P1 indica um requisito necessário à ativação do processo correspondente.

| Código | História proposta e critérios principais | Complementa | Prioridade e fase |
| --- | --- | --- | --- |
| US-ARN-01 | Como gestor da estrutura, quero representar unidades hierárquicas. Identificador estável, tipo, unidade superior, responsável e estado. Impedir ciclos. Preservar vínculos e histórico ao renomear ou desativar. Usar o catálogo confirmado sem presumir o tipo de NIC.gw ou DCT-Q&S. | US-A03, US-F03, US-M01 | P0, F1 |
| US-ARN-02 | Como responsável de RH e acessos, quero gerir entrada, transferência, suspensão e saída. Importar lista validada sem duplicados. Associar identidade Google e conta local autorizada. Atualizar âmbitos. Revogar sessões. Encaminhar tarefas pendentes e preservar autoria. Recuperação da identidade segue Workspace. | US-RH01, US-A01, US-F10, US-S03 | P0, F1 |
| US-ARN-03 | Como gestor de permissões, quero atribuir funções com âmbito. Permitir acumulação autorizada e duração definida. Distinguir diretório de dados confidenciais. Aplicar regras a páginas, API, pesquisa, exportações e notificações. Revogar privilégios anteriores nas mudanças aprovadas. | US-A02, US-M01, US-S02 | P0, F1 |
| US-ARN-04 | Como editor designado, quero aprovar versões identificadas. Bloquear alterações durante análise. Exigir motivo para rejeição. Reapreciar revisões. Manter versão publicada aprovada. Registar aprovador, data e público. Outro editor trata pendências durante ausências. | US-NOT-05 a US-NOT-11, US-NOT-14 | P0, F1 |
| US-ARN-05 | Como utilizador autorizado, quero proteção efetiva dos ficheiros. Uma URL direta, miniatura, versão anterior ou endpoint de media não contorna a autorização. Ficheiros de rascunhos e revisões mantêm acesso restrito. Validar tipos e limites antes de disponibilizar. | US-F06, US-RH03, US-NOT-02/03/04 | P0, F1 |
| US-ARN-06 | Como responsável de processo, quero definir etapas e substituições. Cada tipo de pedido tem formulário, circuito e aprovador. Substituição tem âmbito e prazo. Rejeição e devolução exigem motivo. Autoaprovação segue regra expressa. Decisões concorrentes não produzem resultados contraditórios. | US-F08, US-M04, US-RH02 | P1, F2 |
| US-ARN-07 | Como funcionário, quero gerir vários pedidos prévios de férias no ano civil. Direito de 22 dias úteis, sem obrigatoriedade de continuidade. Ver os próprios pedidos, direito anual, dias gozados, dias futuros aprovados e saldo disponível. RH decide dentro da competência. Rejeição ou retirada não consome saldo. Cancelamento futuro aprovado por RH repõe apenas dias não gozados e fica auditado. Calendário colectivo apenas com ausências aprovadas. | US-F08, US-RH02, US-RH04 | P1, F2 |
| US-ARN-08 | Como responsável, quero manter continuidade das tarefas. Definir responsável principal. Registar mudanças de prazo e atribuição. Reatribuir após transferência ou saída. Comentários e anexos seguem acesso da tarefa. Colaboração entre unidades exige grupo autorizado. | US-F04/05, US-M02/03 | P1, continuidade em F1. Grupos transversais em F3 |
| US-ARN-09 | Como responsável documental, quero identificar versão, origem e validade. Mostrar proprietário institucional, versão atual e data de revisão. Arquivar versões sem as apresentar como atuais. Definir destinatários individuais quando necessário. Preservar acesso e referência à fonte oficial. | US-F06, US-RH03 | P1, núcleo em F1. Integrações em F3 |
| US-ARN-10 | Como destinatário, quero receber notificações coerentes. Gerar o aviso apenas após sucesso da operação. Evitar duplicados em reenvios. Registar falhas de entrega. Marcar como lida não equivale a aprovação nem confirmação formal do conteúdo. | US-S01, US-M02, US-NOT-07 | P0, F1. Canais adicionais por decisão |
| US-ARN-11 | Como funcionário, quero usar o portal com teclado e tecnologias de apoio. Campos têm rótulos e erros claros. Foco visível, contraste legível, texto alternativo e comunicação de estados. Validar fluxos principais em vários tamanhos de ecrã. | US-S04 e formulários | P0, F1 |
| US-ARN-12 | Como responsável técnico, quero recuperar o serviço após falha. Copiar base de dados, ficheiros e configuração necessária. Proteger backups e credenciais. Testar restauro isolado. Confirmar dados, permissões e ficheiros após recuperação e registar evidência. | US-A04/05 e operação | P0, antes de disponibilizar F1 |

A autoaprovação editorial, os circuitos de pedidos e os critérios de conclusão das tarefas precisam de decisão funcional. As propostas não criam níveis de aprovação adicionais sem necessidade institucional.

<a id="fluxos"></a>
## 11. Fluxos de trabalho

### 11.1 Notícias

~~~mermaid
flowchart TD
    R["Rascunho"] -->|"Submeter"| P["Pendente de aprovação"]
    P -->|"Aprovar versão"| A["Publicada internamente"]
    P -->|"Rejeitar com motivo"| J["Rejeitada"]
    J -->|"Corrigir"| R
    P -->|"Devolver para edição"| R
    A -->|"Criar revisão"| V["Revisão em rascunho"]
    V -->|"Submeter revisão"| P
    A -->|"Retirar por autorização"| F["Arquivada"]
~~~

O processo editorial trabalha sobre uma versão identificada. Quando existe revisão de notícia publicada, a versão anterior aprovada mantém-se disponível até decisão sobre a nova versão. A audiência recebe apenas o conteúdo efetivamente aprovado.

A equipa editorial visualiza conteúdo, anexos e audiência antes da decisão. As alterações relevantes incluem substituição de imagens, documentos e destinatários. A aprovação verifica que a versão analisada continua a ser a versão submetida.

A autoria original e o interveniente editorial ficam registados separadamente. A eliminação definitiva segue a política de conservação. Arquivar não elimina o histórico nem torna ficheiros acessíveis.

### 11.2 Comunicados

Proposta funcional: responsáveis autorizados publicam comunicados operacionais para a própria equipa. Comunicados dirigidos a toda a ARN passam pela equipa editorial ou por um publicador institucional expressamente designado.

Cada comunicado identifica autor, destinatários, data e eventual validade. Confirmar esta distinção em PEN-07. As notícias conservam sempre o seu circuito de aprovação.

### 11.3 Tarefas

Estados iniciais: A fazer, Em curso e Concluída.

O responsável cria e atribui. O funcionário acompanha e atualiza. Mudanças de prazo, responsável ou estado ficam no histórico. Atraso é calculado a partir do prazo e estado, sem substituir o estado de execução.

Se a ARN exigir validação formal da conclusão, definir a transição e o papel responsável antes de ativar essa opção. Reabertura ou reatribuição exige competência e motivo.

### 11.4 Pedidos internos

Proposta de estados genéricos: Rascunho, Submetido, Em análise, Devolvido para correção, Aprovado, Rejeitado, Retirado e Cancelado. O processo de férias conserva os comportamentos da secção 11.5 e não herda etapas adicionais de outros pedidos.

Os tipos de pedido não usam automaticamente o mesmo circuito. Para cada tipo, definir campos, anexos, referência, sequência de decisão, responsável, substituto, prazo de tratamento e efeito da aprovação.

Guardar o histórico das etapas e impedir uma pessoa de decidir fora da etapa ou âmbito atribuído. Retirada de pedido pendente e cancelamento de pedido aprovado precisam de regras distintas.

O conjunto inicial inclui o processo de férias previsto no anexo. Outros tipos de pedido serão escolhidos em PEN-06. Os exemplos presentes nas fontes, como autorização de despesa, não constituem inclusão automática no âmbito.

### 11.5 Férias e ausências

Regra confirmada em 13 de outubro de 2026: cada funcionário dispõe de 22 dias úteis por ano civil. Os dias podem ser repartidos por vários pedidos e não precisam de ser consecutivos. Cada período exige solicitação prévia. A aprovação final pertence ao RH, dentro da competência atribuída, sem presumir etapa de chefia ou outra cadeia adicional.

Exemplo: 5 dias úteis em maio e 15 em dezembro totalizam 20 dias aprovados e deixam 2 dias disponíveis. Sábados, domingos e feriados oficiais não consomem saldo. O calendário de feriados deve ser configurável, versionado e validado pelo RH. Não usar um calendário inventado como fonte oficial. Horários especiais e eventual proporcionalidade do direito ficam pendentes de decisão RH.

Cada pedido identifica funcionário, ano de referência, data inicial, data final, número calculado de dias úteis, estado, data de submissão, decisão, responsável e data da decisão, observação quando aplicável e histórico. Estados mínimos: rascunho, submetido, em análise, aprovado, rejeitado, retirado e cancelado. A modelação genérica pode acrescentar devolução para correcção sem perder estes comportamentos.

Fluxo mínimo: rascunho passa a submetido após validação e depois a em análise. RH aprova ou rejeita. Um pedido pendente pode ser retirado segundo a regra a detalhar em F2-01. Retirada e rejeição não reduzem o saldo. Um pedido aprovado reserva os dias úteis correspondentes. Alterar ou cancelar um período aprovado exige decisão RH e histórico, sem substituir silenciosamente a versão aprovada.

O saldo apresenta separadamente direito anual, dias gozados, dias futuros aprovados e saldo disponível. O disponível corresponde ao direito menos gozados e futuros aprovados, sem dupla contagem. Não aprovar mais dias que o saldo disponível. Validar novamente o saldo e a sobreposição na mesma transacção da aprovação, incluindo decisões concorrentes. O sistema não aceita períodos do mesmo funcionário sobrepostos a pedidos aprovados.

Proposta a validar por RH antes de implementar F2: bloquear submissão de períodos sobrepostos a pedidos do mesmo funcionário em submetido ou em análise. Rascunhos, rejeitados, retirados e cancelados não bloqueiam. Pedidos pendentes não reservam saldo. A aprovação revalida sempre datas e saldo. Esta proposta evita pedidos concorrentes duplicados e ainda não é uma decisão institucional confirmada.

Cancelamento de férias futuras aprovado por RH repõe apenas os dias ainda não gozados. Não devolver automaticamente dias gozados. Qualquer correcção administrativa desses dias exige competência RH, motivo e auditoria. Para períodos já iniciados, o tratamento de alteração ou cancelamento parcial e o momento de classificação como gozado precisam de detalhe RH em F2-01.

O funcionário vê os próprios pedidos e saldo. RH gere apenas pessoas e pedidos abrangidos pela competência atribuída. O calendário colectivo mostra apenas ausências aprovadas e pode apresentar funcionário, unidade e datas. Não apresenta motivos clínicos, observações confidenciais ou documentos individuais. Todas as decisões ficam auditadas.

Não implementar transporte para o ano seguinte, antecipação do ano seguinte, venda, compensação financeira ou acumulação plurianual. O direito é anual. O saldo remanescente em 31 de dezembro não tem perda ou transporte presumidos. Pedidos que cruzem anos exigem uma regra expressa RH antes de implementação, sem consumir automaticamente o direito de um ano pelo outro. A origem operacional dos saldos, eventual reconciliação e decisões pendentes estão em PEN-05/06/14.

F2 só começa quando os pré-requisitos do backlog estiverem prontos. Estas regras ficam especificadas agora e não constituem implementação do módulo.

### 11.6 Suporte

Estados iniciais: Novo, Em tratamento e Resolvido. Fecho e reabertura serão definidos com o serviço TI.

O solicitante recebe referência. Um técnico autorizado assume o ticket, responde e regista a solução. Caso existam notas internas, a interface deve distingui-las das respostas visíveis ao solicitante, incluindo nos emails e anexos.

### 11.7 Notificações

Notificações internas no portal acompanham F1. Emails adicionais dependem de configuração e decisão sobre conteúdo e canal.

Os eventos incluem atribuição de tarefa, submissão editorial, decisão editorial, publicação para destinatários, nova etapa de pedido e atualização de ticket. A entrega respeita a autorização atual e evita revelar dados sensíveis através do título ou assunto.

<a id="qualidade"></a>
## 12. Requisitos de qualidade e operação

### 12.1 Acesso e proteção de dados

- Exigir HTTPS em todos os ambientes acessíveis pela rede.
- Restringir o portal, a administração e os ficheiros à rede ARN ou VPN.
- Bloquear consulta anónima do conteúdo através de páginas, REST, AJAX, feeds, sitemaps, oEmbed e rotas equivalentes.
- Aplicar autorização no servidor por ação, objeto, audiência e estado.
- Validar entradas, escapar saídas e utilizar consultas preparadas.
- Tratar uploads, exportações e ligações diretas como operações protegidas.
- Não usar cache partilhada de páginas autenticadas. Caches de objetos exigem separação por autorização e invalidação correta.
- Guardar segredos fora do Git e limitar acesso aos ambientes.
- Definir retenção e arquivo por categoria documental e de registo.
- Registar ações relevantes sem guardar palavras-passe, tokens ou cópias integrais desnecessárias de dados sensíveis.

Os anteprojetos e políticas fornecidos orientam a análise de requisitos. Este README não afirma que a escolha de tecnologias, por si só, demonstra conformidade legal.

### 12.2 Usabilidade e acessibilidade

Interface inicial em português de Portugal, navegação consistente, linguagem clara e adaptação a telemóvel. O design deve usar a identidade visual da ARN sem comprometer contraste, leitura e utilização por teclado.

Definir estados de carregamento, ausência de dados, erro, sessão expirada e ação sem permissão. Preservar rascunhos quando um erro recuperável impede a submissão. Confirmar as ações com impacto antes de as executar.

### 12.3 Desempenho

Aplicar paginação em listas, filtros no servidor e processamento eficiente de imagens. Evitar carregar todos os funcionários, documentos ou eventos numa única resposta.

Definir volume de utilizadores, simultaneidade, documentos, tamanhos e metas de resposta em PEN-04. A aceitação de desempenho usa esse cenário validado e mede rede interna e VPN.

### 12.4 Ambientes e operação

Separar desenvolvimento, homologação e produção. A homologação deve representar as versões de produção e usar dados fictícios ou devidamente preparados.

Prever execução de tarefas agendadas pelo Agendador de Tarefas do Windows, monitorização de disponibilidade, erros de aplicação, armazenamento, autenticação e falhas de notificações.

Atualizações de WordPress, tema, plugins e dependências devem passar por homologação. Preparar restauro e procedimento de recuperação antes de uma alteração com impacto na base de dados.

Backups devem incluir base de dados, ficheiros privados e configuração recuperável. Manter cópia fora do servidor da aplicação sob controlo autorizado. Definir frequência, retenção, RPO e RTO com a DCSI antes da entrada em serviço.

Um teste de restauro precisa de confirmar permissões e acesso a anexos, além de confirmar a existência dos ficheiros.

### 12.5 Operação específica no Windows

O inventário de .205 deve confirmar os 32 GB reportados, medir RAM disponível, carga de CPU, armazenamento, latência de disco e consumo dos serviços existentes. Confirmar se o host é físico ou virtual e, quando aplicável, os recursos atribuídos. A memória total comunicada não demonstra capacidade livre nem reserva exclusiva para o portal.

Dimensionar WordPress, processos PHP, MySQL, documentos e crescimento a partir dessa medição. Definir limites de memória e concorrência compatíveis com as outras aplicações do servidor. Homologar com volume e utilização representativos antes de fixar os parâmetros de produção.

Como .205 está fora de arn.local, a DCSI valida administração local, actualizações, proteção do sistema, certificados e regras locais de firewall. Definir uma fonte de hora autorizada e testá-la sem depender da hierarquia AD. Hora correcta suporta TLS, expiração dos tokens e auditoria. [W26] [W27]

Configurar tarefas agendadas para executar trabalhos pendentes, manutenção e verificações de backup. Usar php.exe ou WP-CLI sob identidade restrita definida pela DCSI, local e com os acessos mínimos necessários, registar o resultado e impedir execuções sobrepostas. Quando o agendador assumir integralmente os eventos WordPress, ajustar WP-Cron para evitar duplicação.

Recolher logs IIS, erros PHP, eventos Windows, estado de MySQL e auditoria da aplicação. Definir rotação, retenção, alertas de espaço e monitorização da validade do certificado. Manter os registos fora da raiz publicável.

Usar homologação Windows/IIS para verificar caminhos, ACL NTFS, web.config, FastCGI, extensões, certificados e downloads. Um teste bem-sucedido num contentor de desenvolvimento não prova funcionamento equivalente no IIS.

O processo inicial de entrega será conduzido pela DCSI através de um pacote de versão revisto e identificado pelo commit. Os runners GitHub alojados externamente não têm, por pressuposto, acesso à rede 192.168.17.0/24. Não criar exposição pública do servidor .205 para permitir implantação. Uma futura automação exige agente ou canal interno aprovado e isolado da infraestrutura de domínio.

Antes de atualizar, preparar cópias consistentes da base de dados, ficheiros, configuração e ACL necessárias. Separar os dados persistentes do pacote de código. Repor a aplicação e a sua base de dados segundo o procedimento validado.

A identidade do processo IIS tem permissões locais sobre os dados da aplicação. Não pressupor conta de computador do domínio ou acesso remoto herdado. O Application Pool não deve usar credenciais AD. [W17]

Para backups remotos, definir um agente ou uma identidade técnica autorizada no destino, com credenciais protegidas e acesso limitado às cópias necessárias. Verificar as permissões no destino para a identidade escolhida, sem depender da pertença de .205 ao domínio. Testar a cópia e o restauro no contexto real dessa identidade.

A recuperação do portal incide sobre a aplicação em .205 e preserva os outros serviços eventualmente alojados nesse servidor. O controlador e DNS em .151 mantêm o seu procedimento de recuperação independente. Desfazer uma atualização WordPress não inclui reverter o controlador de domínio.

Windows Server 2019 encontra-se em suporte alargado, com término previsto em janeiro de 2029. Registar revisão do ciclo de vida e plano de atualização antes desse limite. [W23]

### 12.6 Condições do desenvolvimento local

Cada computador executa a sua instância WordPress e possui base de dados, ficheiros e configuração local próprios. Git sincroniza código e configuração sem segredos. Os conteúdos criados pelo editor WordPress ficam na base de dados local e não são transmitidos por commit ou push. Elementos do tema que devam ser partilhados precisam de exportação para templates, padrões ou theme.json versionados.

No wp-env, fixar a versão WordPress, PHP 8.4 e a versão MariaDB escolhida, e versionar a configuração e os lockfiles. O esquema documentado utiliza mariadbVersion. Não usar uma propriedade mysqlVersion inexistente no esquema consultado. A diferença para MySQL 8.4 de produção exige verificar consultas, collations e migrações na homologação. [W10]

Manter dados de desenvolvimento e de testes separados. Usar configuração de testes própria e seguir os comandos da versão wp-env efetivamente fixada. A configuração atual documenta testsEnvironment como opção descontinuada, pelo que não se deve pressupor a criação automática de um segundo ambiente. [W10]

Durante a fundação, contas locais fictícias podem ser usadas apenas na instância de desenvolvimento restrita ao computador. Nunca usar passwords institucionais, contas reais, segredos Google de produção ou dumps reais para esse fim. A configuração de desenvolvimento deve ser explícita e não permitir esse modo em homologação institucional ou produção. A ativação do acesso real depende de F1-02 e dos critérios OIDC, sessões e permissões das secções 5 e 7.

Verificar a exposição efetiva das portas do wp-env e do Docker. Uma URL localhost, por si só, não prova que os serviços estejam inacessíveis pela LAN. Restringir o acesso local e não publicar os contentores na rede ARN nem na Internet. Não executar reset, destroy, limpeza de volumes ou substituição de bases existentes sem decisão explícita do dono desses dados.

<a id="fases"></a>
## 13. Entregas por fases

| Fase | Entrega | Condição de conclusão |
| --- | --- | --- |
| F0 | Documentação, validações institucionais e desenho de execução | Decisões essenciais registadas, responsáveis designados e condições técnicas verificadas. |
| F1 | Núcleo do portal | Google Workspace, rede/VPN, CMS, unidades, funcionários, notícias, documentos, comunicados, tarefas, calendário básico, perfil, pesquisa, notificações e auditoria verificados. |
| F2 | Serviços administrativos e suporte | Pedidos, circuito de aprovação, férias, ausências e tickets completos com permissões e notificações. |
| F3 | Integrações e evolução | Apenas melhorias priorizadas, com origem dos dados, critérios de aceitação e responsável definidos. |

### 13.1 Execução

A ordem e o estado actual constam exclusivamente do README. Os códigos F1/F2 continuam a identificar os requisitos e os relatórios de aceitação.

<a id="codigo"></a>
## 14. Organização futura do código

A tabela seguinte descreve a organização prevista. A fundação local versiona o plugin, o tema, a configuração wp-env e as ferramentas indicadas abaixo. Já existem `src/Autoloader.php`, `src/Access/` com a política de portal privado e o modo fictício local, e `src/Organization/` com o modelo de unidade, a hierarquia sem ciclos e o catálogo documental. Os restantes módulos continuam por criar. ChatGPT Work/Codex deve conferir a árvore, main, branches, PRs e CI antes de acrescentar ficheiros.

| Caminho previsto | Finalidade |
| --- | --- |
| README.md | Referência funcional, técnica e de acompanhamento. |
| wp-content/themes/arn-intranet/ | Tema e identidade da interface. |
| wp-content/themes/arn-intranet/theme.json | Configuração de estilos e opções do editor. |
| wp-content/themes/arn-intranet/templates/ | Templates de páginas e conteúdos. |
| wp-content/themes/arn-intranet/parts/ | Partes comuns da interface. |
| wp-content/themes/arn-intranet/patterns/ | Padrões editoriais da ARN. |
| wp-content/themes/arn-intranet/assets/ | Recursos CSS, JavaScript e imagens próprias. |
| wp-content/plugins/arn-intranet-core/ | Plugin institucional. |
| wp-content/plugins/arn-intranet-core/src/Identity/ | Associação Google, estado de conta e sessões. |
| wp-content/plugins/arn-intranet-core/src/Access/ | Capabilities, âmbitos e políticas por objeto. |
| wp-content/plugins/arn-intranet-core/src/Organization/ | Unidades, funcionários e vínculos. |
| wp-content/plugins/arn-intranet-core/src/Editorial/ | Notícias, comunicados e decisões. |
| wp-content/plugins/arn-intranet-core/src/Documents/ | Documentos, versões e entrega privada. |
| wp-content/plugins/arn-intranet-core/src/Tasks/ | Tarefas e histórico. |
| wp-content/plugins/arn-intranet-core/src/Calendar/ | Eventos e visibilidade. |
| wp-content/plugins/arn-intranet-core/src/Requests/ | Pedidos, aprovações, férias e ausências. |
| wp-content/plugins/arn-intranet-core/src/Support/ | Tickets e respostas. |
| wp-content/plugins/arn-intranet-core/src/Notifications/ | Eventos e notificações por destinatário. |
| wp-content/plugins/arn-intranet-core/src/Audit/ | Registo e consulta de operações. |
| wp-content/plugins/arn-intranet-core/src/Infrastructure/ | Acesso a dados, migrações e serviços partilhados. |
| wp-content/plugins/arn-intranet-core/src/Rest/ | Endpoints e ligação aos serviços de domínio. |
| wp-content/plugins/arn-intranet-core/blocks/ | Blocos específicos do portal. |
| tests/ | Verificação das regras, integrações e fluxos. |
| .wp-env.json e configuração de testes | Ambiente local e ambiente de testes separado, com versões documentadas. |
| .nvmrc | Versão Node.js partilhada pelos dois computadores. |
| .editorconfig e .gitattributes | Formatação comum e tratamento consistente de finais de linha. |
| AGENTS.md | Instruções curtas para ChatGPT Work/Codex e outros agentes. Cursor opcional, com referência ao README. |
| composer.json e composer.lock | Dependências PHP e versões reproduzíveis. |
| package.json e package-lock.json | Ferramentas JavaScript e versões reproduzíveis. |
| .github/workflows/ | Verificações automáticas de código, quando implementadas. |
| .gitignore | Exclusão de segredos, dependências geradas, dados reais e ficheiros de execução. |

WordPress core e dependências de terceiros devem ser obtidos por um processo reproduzível. Ficheiros operacionais de funcionários, uploads, backups, exportações e configurações com segredos pertencem aos ambientes autorizados. A lista nominal incluída expressamente na secção 4.1 é uma referência documental e não um conjunto de dados de execução.

O padrão de estrutura do tema segue a documentação WordPress. A divisão interna do plugin é proposta específica deste projeto. [W12]

F1-02 acrescenta classes puras e adaptadores WordPress em `src/Identity/`. Persistência auditada, hooks Google, callback e sessão foram homologados no laboratório local. A homologação institucional continua pendente. Ver docs/google-login-local-f1-02.md.

<a id="verificacao"></a>
## 17. Verificação e critérios de conclusão

### 17.1 Cenários obrigatórios antes de disponibilizar F1

| Cenário | Resultado esperado |
| --- | --- |
| Pessoa fora da rede ARN e sem VPN | Não acede ao serviço interno. |
| Pessoa na rede sem sessão | Acede apenas ao processo de entrada e recursos necessários. |
| Conta Google externa ao domínio autorizado | Entrada recusada. |
| Conta Google institucional sem autorização local | Entrada recusada. |
| Conta federada tenta login ou recuperação local de palavra-passe | Operação recusada mesmo conhecendo uma palavra-passe WordPress. Autenticação corrente passa pelo Google. |
| Funcionário desativado com sessão anterior | Pedido seguinte recusado e sessões revogadas pelo procedimento de saída. |
| Funcionário tenta abrir documento de outra unidade sem autorização | Nenhum conteúdo ou metadado restrito é devolvido. |
| Link direto de anexo, miniatura ou versão antiga | Mantém a autorização do objeto e da versão. |
| Autor tenta publicar por API ou alterar notícia pendente | Operação recusada e estado preservado. |
| Dois editores decidem sobre versões diferentes | Apenas a decisão válida sobre a versão corrente produz publicação. |
| Notícia publicada recebe revisão | Leitores continuam a ver a versão aprovada até nova decisão. |
| Pesquisa, contagem ou cache cruza utilizadores | Informação restrita não é divulgada. |
| Funcionário transfere de unidade | Âmbitos mudam segundo a decisão. Histórico e encaminhamento permanecem coerentes. |
| Importação contém duplicados ou unidades inválidas | Erros identificados e ausência de alterações indevidas. |
| Utilizador opera por teclado e em telemóvel | Fluxos principais concluídos com foco, rótulos e mensagens compreensíveis. |
| Restauro em ambiente isolado | Dados, ficheiros, versões e permissões recuperados conforme o plano. |

### 17.2 Verificação de F2

Verificar pedido submetido, devolução, correção, aprovação, rejeição, cancelamento autorizado e substituição de aprovador. Confirmar tratamento de autoaprovação conforme regra aprovada.

Nas férias, criar testes unitários de cálculo ao iniciar F2 e verificar: pedido de 5 dias úteis, dois pedidos parciais no mesmo ano, 5 + 15 = 20 e saldo 2, pedido que completa 22, tentativa de exceder 22, fim de semana, feriado RH, períodos sobrepostos, rejeição, retirada, cancelamento futuro, funcionários distintos com saldos independentes, transição entre anos e acesso indevido ao pedido alheio. Testar reserva concorrente, classificação de gozados/futuros e reposição apenas dos dias não gozados. A regra proposta para pendentes e a passagem de ano só são implementadas depois de validadas pelo RH.

Nos tickets, verificar atribuição, resposta, anexos, resolução e diferença entre nota interna e resposta ao solicitante, caso notas internas sejam adotadas.

### 17.3 Condição para concluir uma funcionalidade

A funcionalidade deve cumprir a história, aplicar autorização no servidor, validar entradas, tratar erros e estados vazios, registar operações relevantes e manter ficheiros e notificações dentro do âmbito correto.

A revisão deve incluir testes das regras críticas e validação funcional pela área responsável. O README, decisões e evidência devem refletir o comportamento entregue.

Os cenários desta secção são critérios de aceitação futura. A fundação existente tem testes de código e validação local descritos na secção 16.6, que não demonstram homologação nem disponibilidade institucional.

### 17.4 Homologação específica Windows e rede

| Verificação | Resultado esperado |
| --- | --- |
| Alvo e separação de serviços | Produção prevista em .205 fora de arn.local, com coexistência validada e sem dependências AD. AD DS/DNS permanecem em .151. Homologação usa endereço próprio. |
| DNS na LAN e VPN | Nome do ambiente em teste resolve para o IP correspondente. O nome de produção proposto aponta para .205. Registos públicos necessários de arn.gw continuam a resolver. |
| HTTPS | Certificado corresponde ao nome e cadeia é confiável. Procedimento de renovação e instalação no binding IIS verificado em homologação. |
| IIS e WordPress | Permalinks, REST, CMS e callback OIDC funcionam com web.config e FastCGI. |
| Login Google | Fluxo completo a partir da LAN e da VPN, incluindo regresso do browser ao portal. |
| Administração inicial | As três contas da secção 5.4 recebem apenas as competências locais atribuídas. Um funcionário comum do mesmo domínio não recebe privilégios administrativos. |
| Bloqueio de rede | Acesso fora das origens autorizadas recusado. Perfil de rede e regras locais efectivas verificados sem depender de perfil Domain ou GPO. |
| MySQL | Serviço acessível pela aplicação local. Porta da base de dados não acessível a clientes da LAN. |
| Pastas e media | Pool tem apenas os acessos necessários. Ficheiros e derivados privados não existem em caminhos públicos alternativos. |
| Gestão do servidor fora do domínio | Ausência de associação a arn.local confirmada. Administração e identidades locais, certificados, fonte de hora e firewall validados. Sem GPO, Kerberos, LDAP ou conta de computador AD. Sem WSL ou Docker Desktop para alojar o portal. |
| Tarefas agendadas | Trabalhos executam com a identidade restrita autorizada, sem sobreposição e com registo de falha. |
| Desempenho | Comportamento dentro das metas aprovadas, sem degradar funções de infraestrutura existentes. |
| Recuperação | Backup remoto acessível pela identidade autorizada e dados, configuração e ACL recuperados em ambiente isolado. Outros serviços de .205 e AD DS/DNS em .151 preservados. |

<a id="pendencias"></a>
## 18. Decisões pendentes

Estas pendências têm resultado esperado e momento de resolução. Não impedem documentar a arquitetura, mas condicionam a implementação correspondente.

A lista recebida esclarece parcialmente PEN-01 e PEN-05, e o responsável confirmou trabalho de dois colaboradores em PCs separados. As restantes validações institucionais continuam adiadas até ao momento necessário. O arranque local da secção 16.6 pode avançar com dados fictícios, executante identificado e ferramentas disponíveis, sem exigir previamente decisões editoriais, circuitos administrativos ou configuração de produção.

| ID | Informação ou decisão necessária | Responsável | Resolver antes de |
| --- | --- | --- | --- |
| PEN-01 | Lista transcrita na secção 4.1. Confirmar as lacunas da secção 4.6, cargos individuais e relações superiores. DCT-Q&S está identificada como direção no anexo. Confirmar NIC/NIC.gw, as designações DSU/DSGI/DSIC e a posição dos serviços, Arquivo, Recepção e FAU. | RH + coordenação | Publicar organograma definitivo e importar pessoas reais. |
| PEN-02 | As três contas administrativas iniciais estão identificadas na secção 5.4. Confirmar a identidade autenticável de cada uma e que os restantes funcionários autorizados possuem conta Workspace. Definir tratamento de exceções e responsáveis pela identidade. | DCSI + RH | Ativar login. |
| PEN-03 | Aprovar nome do portal, desenho DNS, estratégia de certificado, origens e rotas VPN e saída Google. Prever callback conforme o plugin escolhido. A comprovação do callback real pertence a F1-02 e INF-07. | DCSI | Preparar DNS e integração OIDC. |
| PEN-04 | Inventariar recursos, armazenamento e serviços de .205, incluindo natureza física ou virtual e capacidade disponível. Aprovar versões candidatas de IIS, PHP 8.4 x64 NTS e MySQL 8.4 LTS, carga e metas de resposta. A compatibilidade executada pertence a INF-06/F1-01 e a homologação integrada a INF-09. | DCSI | Dimensionar e preparar homologação. |
| PEN-05 | Departamentos-ARN.docx é a fonte nominal inicial. Falta completar identificadores, emails e validação de RH, definir manutenção do cadastro e identificar as fontes operacionais de documentos, férias e saldos. | RH + responsáveis documentais | Importação operacional e cada módulo dependente. |
| PEN-06 | Validar tipos de pedido, substituições e tratamento dos pedidos do próprio aprovador. Nas férias, direito de 22 dias úteis repartíveis e decisão final RH já estão confirmados. Falta validar o detalhe de PEN-14, sem inventar cadeias adicionais. | RH + responsáveis institucionais | Desenvolver F2. |
| PEN-07 | Nomear equipa editorial. Confirmar públicos, comunicados institucionais, rejeição com motivo e regra de aprovação da própria notícia. | Coordenação + editorial | Ativar publicação. |
| PEN-08 | Aprovar dados partilhados no perfil, tipos e limites de anexos, classificação, conservação e arquivo. | RH + DCSI + responsáveis documentais | Carregar dados reais e documentos. |
| PEN-09 | Definir backups, retenção, RPO, RTO e responsabilidade por recuperação e incidentes. | DCSI | Disponibilizar F1. |
| PEN-10 | Definir duração e inatividade de sessão, MFA, encerramento, desativação e eventual recuperação técnica local. | DCSI | Disponibilizar F1. |
| PEN-11 | A continuação do desenvolvimento foi assumida pelo responsável. Não há dependência de novas tarefas da colaboradora. Falta definir calendário de piloto, operação e formação. | Coordenação | Aceitação e disponibilização institucional. |
| PEN-12 | Decidir se serão usados emails de notificação, em que eventos e por que canal institucional. | DCSI + donos dos processos | Ativar canal adicional. |

| PEN-14 | Férias: validar calendário de feriados, fonte dos saldos, proposta de bloqueio de sobreposição pendente, horários especiais, pedidos entre anos, saldo de 31 de dezembro, classificação de dias gozados, cancelamento parcial e correcções auditadas. Sem transporte, antecipação, compensação ou acumulação automáticos. | RH | Implementar e activar F2-03. |

PEN-13 resolvida quanto à identificação e localização: .151 é o controlador e DNS de arn.local. Panthera-Onca, .205, está fora do domínio por decisão de 13 de outubro de 2026, conforme DEC-11/13/14. O inventário e a validação operacional de .205 permanecem Por iniciar em INF-01/02 e PEN-04. A confirmação da localização não substitui a validação operacional nem significa que a instalação foi executada.

<a id="fontes"></a>
## 19. Fontes e histórico de decisões

### 19.1 Base funcional e institucional

A fonte funcional principal é o documento User Stories Portal Interno Empresa Com Notícias, fornecido para este projeto. Contém 46 histórias: 10 de funcionário, 5 de responsável, 4 de RH, 4 de suporte TI, 5 de administração, 4 transversais e 14 de notícias.

Foram analisadas as fontes institucionais disponibilizadas no projeto, incluindo Plano Estratégico, Revisão Funcional, Relatório Final, regulamentos, documentos de interoperabilidade, proteção de dados, cibersegurança e acesso universal, além da identidade visual e do template institucional.

O Plano Estratégico associa a intranet a comunicação interna, partilha de conhecimento e serviços. Os documentos de organização incluem diagnósticos e propostas com nomenclaturas diferentes. A lista inicial deste README seguiu a confirmação do responsável em 8 de outubro de 2026. A versão 0.6 incorpora Departamentos-ARN.docx, fornecido em 9 de outubro, com 37 entradas e 73 menções nominais. Foram conferidos o texto da tabela e as duas páginas do documento. A grafia de origem foi preservada e as lacunas estão na secção 4.6.

As referências internas servem de contexto. O repositório não incorpora os documentos de origem nem transforma propostas institucionais em atos aprovados.

As capturas iniciais mostram .151 e um resumo anterior de Panthera-Onca. As decisões de 8 de outubro sobre o domínio ficam como histórico substituído. Em 13 de outubro, o responsável confirmou .205 fora de arn.local, Windows Server 2019 Standard/1809/build 17763/64 bits, Hyper-V instalado e WSL ausente. Esta informação prevalece no plano actual e não constitui inspeção remota. As três contas administrativas foram indicadas pelo responsável e não recebem privilégios por email. Identificadores de produto, MAC e outros dados sem utilidade para o plano não são reproduzidos.

### 19.2 Referências técnicas

Referências de infraestrutura consultadas em 8 de outubro de 2026 e de desenvolvimento local atualizadas em 9 de outubro de 2026. Confirmar versões e compatibilidade no início da implementação.

- [W1] [WordPress Hosting Handbook, Server Environment](https://make.wordpress.org/hosting/handbook/server-environment/).
- [W2] [WordPress, Registering Custom Post Types](https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/).
- [W3] [WordPress, Roles and Capabilities](https://developer.wordpress.org/plugins/users/roles-and-capabilities/).
- [W4] [WordPress REST API, Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) e [Adding Custom Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/).
- [W5] [Google, OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect) e [OAuth 2.0 for Web Server Applications](https://developers.google.com/identity/protocols/oauth2/web-server).
- [W6] [OpenID Connect Generic Client, diretório WordPress](https://wordpress.org/plugins/daggerhart-openid-connect-generic/).
- [W7] [Google, Security bundle, sessões da identidade e da aplicação](https://developers.google.com/identity/siwg/security-bundle).
- [W8] [WordPress, revogação das sessões de um utilizador](https://developer.wordpress.org/reference/classes/wp_session_tokens/destroy_all/).
- [W9] [WordPress, Creating Tables with Plugins](https://developer.wordpress.org/plugins/creating-tables-with-plugins/).
- [W10] [WordPress, wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) e [WP-CLI](https://developer.wordpress.org/cli/commands/).
- [W11] [WordPress, wp_upload_dir](https://developer.wordpress.org/reference/functions/wp_upload_dir/).
- [W12] [WordPress, Theme Structure](https://developer.wordpress.org/themes/core-concepts/theme-structure/).
- [W13] [PHP, Installation with IIS for Windows](https://www.php.net/manual/en/install.windows.iis.php) e [distribuições oficiais PHP para Windows](https://www.php.net/downloads.php?os=windows&version=8.4).
- [W14] [MySQL, Supported Platforms](https://www.mysql.com/support/supportedplatforms/database.html) e [instalação MySQL 8.4 no Windows](https://dev.mysql.com/doc/refman/8.4/en/windows-installation.html).
- [W15] [MariaDB Community, Platform Deprecation Policy](https://mariadb.com/docs/release-notes/community-server/about/platform-deprecation-policy).
- [W16] [Microsoft IIS, URL Rewrite](https://www.iis.net/downloads/microsoft/url-rewrite).
- [W17] [Microsoft IIS, Application Pool Identities](https://learn.microsoft.com/en-us/iis/manage/configuring-security/application-pool-identities).
- [W18] [Microsoft, Reviewing OU Design Concepts](https://learn.microsoft.com/en-us/windows-server/identity/ad-ds/plan/reviewing-ou-design-concepts).
- [W19] [Microsoft, Securing Domain Controllers Against Attack](https://learn.microsoft.com/en-us/windows-server/identity/ad-ds/plan/security-best-practices/securing-domain-controllers-against-attack).
- [W20] [Microsoft, DNS Architecture](https://learn.microsoft.com/en-us/windows-server/networking/dns/dns-architecture) e [Split-Brain DNS Deployment](https://learn.microsoft.com/en-us/windows-server/networking/dns/deploy/split-brain-dns-deployment).
- [W21] [Let's Encrypt, DNS-01 Challenge](https://letsencrypt.org/docs/challenge-types/#dns-01-challenge).
- [W22] [Microsoft, distribuição de certificados por Group Policy](https://learn.microsoft.com/en-us/windows-server/identity/ad-fs/deployment/distribute-certificates-to-client-computers-by-using-group-policy).
- [W23] [Microsoft, Windows Server 2019 Lifecycle](https://learn.microsoft.com/en-us/lifecycle/products/windows-server-2019).
- [W24] [Microsoft, Visual C++ Redistributable suportado](https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist?view=msvc-170).
- [W25] [Microsoft IIS, Anonymous Authentication](https://learn.microsoft.com/en-us/iis/configuration/system.webserver/security/authentication/anonymousauthentication).
- [W26] [Microsoft, Windows Firewall e perfis de rede](https://learn.microsoft.com/en-us/windows/security/operating-system-security/network-security/windows-firewall/).
- [W27] [Microsoft, funcionamento do Windows Time Service](https://learn.microsoft.com/en-us/windows-server/networking/windows-time-service/how-the-windows-time-service-works).
- [W28] [Microsoft, repositórios de certificados do computador e do utilizador](https://learn.microsoft.com/en-us/windows-hardware/drivers/install/local-machine-and-current-user-certificate-stores).
- [W29] [WordPress HTTP API, verificação TLS e autoridades de certificação](https://developer.wordpress.org/reference/classes/wp_http/request/).
- [W30] [Microsoft, configuração de firewall para domínios Active Directory](https://learn.microsoft.com/en-us/troubleshoot/windows-server/active-directory/config-firewall-for-ad-domains-and-trusts).

- [W31] [Cursor, download](https://cursor.com/download), [regras e AGENTS.md](https://cursor.com/docs/rules) e [orientação do suporte sobre WSL integrado](https://forum.cursor.com/t/extentions-icons-dont-load-under-the-extention-tab-on-the-sidebar/159019).
- [W32] [Microsoft, instalar WSL](https://learn.microsoft.com/en-us/windows/wsl/install) e [Canonical, instalar Ubuntu no WSL 2](https://ubuntu.com/wsl/docs/stable/howto/install-ubuntu-wsl2/).
- [W33] [Docker Desktop, instalação no Windows](https://docs.docker.com/desktop/setup/install/windows-install/), [integração WSL 2](https://docs.docker.com/desktop/features/wsl/) e [Docker Compose](https://docs.docker.com/compose/install/).
- [W34] [Docker, licença Desktop e entidades governamentais](https://docs.docker.com/subscription-billing/desktop-license/).
- [W35] [Node.js, versões e suporte](https://nodejs.org/en/about/previous-releases), [download](https://nodejs.org/en/download) e [Microsoft, Node.js no WSL](https://learn.microsoft.com/en-us/windows/dev-environment/javascript/nodejs-on-wsl).
- [W36] [Git, instalação no Linux](https://git-scm.com/install/linux) e [git pull](https://git-scm.com/docs/git-pull).
- [W37] [GitHub CLI, instalação Linux](https://github.com/cli/cli/blob/trunk/docs/install_linux.md), [gh auth login](https://cli.github.com/manual/gh_auth_login) e [gh auth setup-git](https://cli.github.com/manual/gh_auth_setup-git).
- [W38] [GitHub flow](https://docs.github.com/en/get-started/using-github/github-flow), [convidar colaboradores](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/repository-access-and-collaboration/inviting-collaborators-to-a-personal-repository) e [branches protegidas](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches).
- [W39] [Microsoft, sistemas de ficheiros WSL](https://learn.microsoft.com/en-us/windows/wsl/filesystems) e [GitHub, email de commits](https://docs.github.com/en/account-and-profile/how-tos/email-preferences/setting-your-commit-email-address).
- [W40] [npm, npm ci](https://docs.npmjs.com/cli/v11/commands/npm-ci/).
- [W41] [Docker Compose, COMPOSE_ENV_FILES e precedência das variáveis](https://docs.docker.com/compose/how-tos/environment-variables/envvars/#compose_env_files).
