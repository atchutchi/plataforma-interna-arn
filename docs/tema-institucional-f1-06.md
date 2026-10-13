# F1-06: identidade institucional local

13 de outubro de 2026. Preparação visual autorizada pelo responsável. O pedido de thumbnail foi esclarecido como ícone do browser. Não foram criados módulos fictícios, métricas inventadas ou funcionalidades de F2.

## Tema de blocos

O tema Intranet ARN mantém os conteúdos no WordPress. O cabeçalho usa o logótipo original, título e descrição editáveis, navegação nativa e loginout. O rodapé e a introdução são blocos editáveis. A barra editorial fica visível apenas para quem pode editar conteúdos, sem modificar competências. A entrada nativa recebe a mesma marca. A autenticação não foi alterada.

O ficheiro arn-logo.png é uma cópia exacta do ficheiro fornecido. SHA256: 17edf7be7c9fb545bf5e31c7f46ad0818bba5e9bb64b853ba758ee7a23b9afbb. Dimensões 1491 x 684. O PNG não foi redesenhado, recolorido ou recortado. O favicon é um SVG quadrado que incorpora os mesmos bytes do PNG, com margem branca e proporção original. O tema preserva um ícone já configurado no CMS.

Manrope serve os títulos e Source Sans 3 o corpo. Os ficheiros variáveis são locais e incluem as licenças OFL. Não há pedido de fontes ao Google. Os eixos 200..800 e 200..900 foram conferidos. A escala fixa em rem diferencia texto auxiliar, corpo de 1.0625rem, subtítulos e títulos. Pesos 400, 600 e 700 têm papéis definidos. O texto corrido é limitado a 65ch. As cores e superfícies mantêm contraste alto e a paleta existente, sem efeitos decorativos.

O Impeccable foi aplicado com duas avaliações isoladas em paralelo, tipográfica e mecânica. Ambas detectaram escala incompleta. A avaliação tipográfica também identificou duplicação de h1 e variabilidade da pilha de sistema. Estas lacunas foram corrigidas. O detector final devolveu []. UI UX Pro Max orientou acessibilidade, foco, hierarquia e navegação. Sugestões comerciais genéricas foram descartadas por não corresponderem à intranet.

## Verificação real

O laboratório usou WordPress 7.1.3/PHP 8.4.26/MariaDB 11.8.3 portáteis, separados dos servidores institucionais e apenas em loopback. A conta fictícia Ana Teste foi usada no frontend. Uma conta administrativa fictícia do laboratório foi usada no editor. O conteúdo de demonstração da página só existe na base local, sem dados institucionais importados.

Playwright CLI, com Node 24.21.0, verificou:

| Ensaio | Resultado |
| --- | --- |
| Larguras 375, 768, 1024 e 1440 px | Sem overflow horizontal. |
| Fontes efectivas | Source Sans 3 e Manrope carregadas localmente. |
| Cabeçalho e título | Um h1 e um salto para o conteúdo. Proporção da marca preservada. |
| Menu mobile | Abre como diálogo, fecha com Escape e tem alvo 44 x 44 px. |
| Teclado | Primeiro Tab no salto, Enter coloca foco em main#conteudo. |
| Texto a 200%, 375 e 1440 px | Corpo 34px, cabeçalho refluído, sem overflow. Não equivale a certificação formal de acessibilidade. |
| Favicon | Ligação ao SVG e recurso disponível no tema. |
| Editor WordPress | O padrão da marca foi analisado por wp.blocks.parse. core/image isValid=true, sem validationIssues. |

A revisão independente identificou dois problemas, corrigidos antes da entrega: HTML da marca incompatível com a serialização de core/image e cabeçalho sem reflow de texto ampliado em mobile. A revisão final de código e capturas foi aprovada. O editor real confirmou depois a validade do bloco.

Os artefactos Playwright ficam em output/playwright e não são versionados. As sessões e logs do browser também estão excluídos. PHPUnit 151/459, testes Node 54, PHPCS 49/49, lint e build foram aprovados. A CI do SHA final será conferida antes da integração.

## Limites

O aviso local continua sujeito à guarda em tempo de renderização. A guarda de sessão do plugin foi preservada. O tema não implementa Google login ou armazenamento privado de ficheiros. Não substitui a homologação Windows/IIS/MySQL 8.4.

F1-06 fica Em curso apenas na preparação de identidade, navegação e componentes CMS. A navegação por permissões e as páginas funcionais dependem de F1-03 e dos módulos respectivos. F1-02 mantém cliente, callback e sessão pendentes. F2 não começou. Não houve deploy institucional.
