# Portal editorial local F1-06

14 de outubro de 2026. Preparação do front end pedida pelo responsável, seguindo o README. O responsável confirmou Comunicação sindical como categoria própria e os anexos reais apenas para revisão administrativa local. Esta entrega não activa publicação institucional nem conclui F1-08, F1-09, F1-10, F1-11 ou F2.

## Páginas e apresentação

Início reúne pesquisa, comunicação em destaque, notícias recentes e acesso documental. Notícias filtra Circular, Nota interna, Despacho e Comunicação sindical. Documentos reúne as cinco categorias e Regulamentos tem uma colecção própria. Serviços identifica capacidades previstas como Em preparação. Não apresenta acções fictícias, saldos ou indicadores de produção.

O tema 0.3.0 mantém o logótipo, favicon, fontes locais e paleta já existentes. PRODUCT.md e DESIGN.md registam o contexto e a escala. O menu nativo WordPress indica a página corrente e usa diálogo em mobile. Pesquisa, categorias, ano, paginação e detalhe são funcionais. A ligação de regresso conserva os filtros e a página. Os parâmetros arn_year e arn_page evitam colisões com a consulta principal do WordPress. O modelo interno mantém year e page.

O plugin 0.10.0 regista arn/portal-view com vistas home, news, documents, regulations e services. O plugin prepara dados autorizados e o tema apresenta-os com get_template_part. Não há uma aplicação frontend separada. O bloco tem registo Gutenberg nativo e selector de secção, sem pré-visualizar anexos reais no editor. A selecção da vista pertence à página CMS. Os originais e os textos documentais não são posts, anexos públicos ou novos registos de pessoal.

## Fonte reservada

Foram preparados 22 originais: cinco circulares, quatro notas internas, onze despachos, um regulamento e uma comunicação sindical. A classificação segue o título visível no original. Datas incompletas e nomes de ficheiros discordantes ficam assinalados em notas privadas. Os resumos não transcrevem níveis salariais ou outros dados individuais desnecessários. Eventos passados permanecem documentos de arquivo. O regulamento não recebe data completa, aprovação ou vigência que a cópia não demonstra.

Manifesto e ficheiros ficam apenas no laboratório, fora do document root e do Git. A constante externa ARN_LOCAL_EDITORIAL_DIR aponta para a pasta que contém manifest.json e files. A flag ARN_ALLOW_LOCAL_EDITORIAL_PREVIEW tem de ser o booleano true. O acesso exige também ambiente efectivo local e manage_options, antes de carregar a fonte, calcular contagens ou seleccionar detalhes. Ana Teste recebe estrutura e estados vazios, sem dados ou metadados dos anexos reais. A capacidade administrativa técnica só autoriza esta pré-visualização, não atribui competência editorial institucional.

O manifesto exige version 1 e items, com os campos definidos na especificação. Limites: 1 MiB, 200 itens, originais até 20 MiB e colecções de dez itens por página. A leitura valida IDs, categorias, datas e basenames. A descarga resolve o ID no servidor e verifica caminho canónico, assinatura, extensão, MIME quando Fileinfo está disponível, tamanho e SHA-256. Não aceita um caminho do browser. Responde como attachment com nosniff e no-store. Ausência e recusa de original devolvem o mesmo 404 sem corpo. Não foram acrescentados endpoints REST ou uploads. O serviço não altera pessoas, contas, unidades ou permissões.

## Preparação do CMS local

As páginas contêm apenas o bloco dinâmico com a vista correspondente. Exemplo de conteúdo da página Notícias: `<!-- wp:arn/portal-view {"view":"news"} /-->`. As restantes usam home, documents, regulations e services. São criadas no WordPress local por administração autorizada. A preparação conserva uma cópia privada do conteúdo anterior do início e usa marcas de propriedade nos novos elementos. O menu anterior e páginas alheias são preservados. O cabeçalho local referencia o novo menu nativo. A segunda execução da preparação não duplicou páginas ou menus.

Git não transporta páginas, menus, manifesto ou documentos reais para outro PC. No segundo laboratório, preparar as páginas e configuração externa e transferir os originais por um processo privado autorizado. Nunca copiar a configuração com segredos para o repositório. A activação institucional exige o armazenamento privado e o circuito editorial próprios do README.

## Verificação

No laboratório WordPress 7.1.3, PHP 8.4.26 e MariaDB 11.8.3 passaram 636 testes PHP com 2119 asserções, 55 testes Node, PHPCS global de 155 ficheiros, lint e construção. Mantém-se uma depreciação da configuração PHPUnit existente. O runtime PHP portátil usa a configuração OpenSSL local já preparada. A integração editorial passou nove verificações com ficheiros sintéticos exclusivos. A CI executa-a em development sem abrir a guarda operacional.

O ensaio privado verificou os 22 originais por assinatura, formato e SHA-256. As contagens foram 22 documentos e 21 notícias. Testaram-se todas as categorias, ano documental, pesquisa sem resultados, páginas de dez itens e a última página. A leitura deixou o manifesto igual. A descarga de um PDF pelo browser administrador produziu o mesmo hash do anexo fornecido. Uma sessão anónima e Ana Teste receberam 404 sem corpo para IDs existentes e inexistentes. Ana não recebeu título, detalhe, resultado ou contagem dos conteúdos reais.

Playwright verificou os cinco destinos de navegação, pesquisa a partir do início, filtros, paginação, abertura do detalhe e regresso com a página e filtros preservados. A categoria sindical ficou separada. Houve verificações a 375, 768 e 1440 píxeis sem transbordo horizontal. O menu móvel abriu e fechou com Escape. O primeiro Tab de Ana focou o salto para o conteúdo e Enter colocou o foco em main#conteudo. Na estrutura sem dados reais de Ana, texto a 200% produziu corpo de 34px sem transbordo a 375 e 1440 píxeis e com reduced motion. Isto não equivale a certificação formal nem a testar todos os documentos a 200%.

No editor real, a vista foi inicialmente identificada como Não suportado. A correcção acrescentou o registo cliente e a verificação da serialização. O Gutenberg passou a identificar Vista do portal ARN. O conteúdo da página foi guardado no editor de código, recebeu confirmação Página actualizada e reabriu no editor visual com o bloco reconhecido e sem alterações por guardar. O selector e o save null têm teste Node próprio. A QA não afirma ter percorrido todos os controlos do canvas visual.

Impeccable teve duas avaliações isoladas, visual e mecânica. A avaliação visual aprovou a composição, hierarquia e tipografia nas capturas. Os scans layout e type ficaram sem findings. A pesquisa complementar levou à harmonização do título com a escala existente e à remoção da faixa lateral no aviso. O controlo de entrada existente de 1rem mantém-se como excepção documentada. As orientações disponíveis do UI UX Pro Max foram aplicadas a legibilidade, alvos, estados e navegação. O search.py dessa instalação aponta para uma pasta ausente e não foi executado. Não se inventa um resultado dessa ferramenta.

As revisões independentes detectaram e levaram à correcção da perda de contexto no detalhe, pesquisa com aspas, pesquisa literal 0 e colisões de nomes com queries WordPress. Os testes de regressão falharam antes das correcções e passaram depois. A revisão das correcções e a revisão específica do editor ficaram aprovadas. O parecer final da branch e a CI do SHA exacto ficam associados à PR antes da integração.

Os hashes e contagens das tabelas ARN e utilizadores e o hash das sessões de Ana conservaram o estado anterior à preparação. A preparação alterou páginas e menu locais e o ensaio Gutenberg actualizou preferências nativas do editor. Não se afirma que todos os metadados WordPress ficaram iguais. Não foram alteradas as concessões, o estado das contas, as unidades ou a auditoria de negócio anteriores. As capturas e os relatórios privados ficam fora do Git, em output/playwright e .git/tools.
