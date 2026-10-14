# Evidência do laboratório para notícias F1-10

14 de outubro de 2026. Validação no WordPress portátil servido apenas em 127.0.0.1:8888, PHP 8.4.26 e MariaDB local. Esta evidência não homologa IIS ou MySQL 8.4 e não autoriza publicação institucional.

## Percurso no browser

Playwright usou sessões separadas de autor fictício, editor fictício, Ana Teste e visitante sem sessão. As duas novas contas são Subscriber, marcadas como fixtures e recebem apenas as concessões explícitas previstas no adaptador. Não se associaram identidades Google, nem se alteraram papéis ou concessões das contas anteriores.

O autor criou um rascunho incompleto e guardou uma segunda versão com PNG de destaque, galeria JPEG/PNG e PDF sintéticos. Os quatro ficheiros foram entregues por endpoint privado com MIME, comprimento, disposição inline ou attachment, no-store/private e nosniff. A pré-visualização apresentou três imagens efectivamente descodificadas de 1200 por 675 píxeis e o PDF de 610 bytes.

A reordenação por teclado inverteu a galeria. O autor retirou o PDF e guardou uma terceira versão. Todos os IDs dos ficheiros conservados foram substituídos por novos IDs. Os ficheiros anteriores continuaram disponíveis para o próprio autor, mediante autorização, e foram recusados ao editor.

Um segundo rascunho recebeu os 14 ficheiros permitidos num único POST de 13 335 149 bytes. Cada JPEG sintético tinha 952 033 bytes. A resposta foi um redireccionamento de sucesso e foram guardados 14 IDs distintos. O PHP HTTP local foi previamente confirmado com post_max_size=20M, upload_max_filesize=2M e max_file_uploads=20. O limite individual da aplicação manteve-se em 1 MiB.

A notícia da terceira versão foi enviada para revisão. A revisão passou de 3 para 4, o estado ficou Pendente e a transacção criou exactamente um evento de submissão e um aviso editorial. Repetir submissão ou tentar rever a notícia Pendente foi recusado. O editor viu apenas a terceira versão e não viu o outro rascunho ou os seus 14 ficheiros. A leitura do aviso foi guardada e a repetição a partir de um separador antigo não mudou o recibo, a revisão ou a auditoria.

## Recusas e conservação

Foram verificados 51 pedidos negativos iniciais, distribuídos pelas sessões de Ana Teste, visitante e editor antes da submissão. Incluíram GET, HEAD e POST da pré-visualização e dos ficheiros, além das duas rotas privadas anteriores. Após a submissão, mais 28 pedidos das sessões de Ana Teste e visitante recusaram a versão corrente com 404 vazio. O editor recebeu 404 vazio para os quatro ficheiros anteriores, os 14 ficheiros do outro rascunho e a sua pré-visualização. A REST nativa respondeu 401 sem conteúdo editorial e a âncora WordPress não expôs título, corpo ou imagens.

POST com nonce inválido, actor forjado ou CAS antigo não alterou os dados. O CMS conserva respostas HTML genéricas próprias para a recusa do formulário. O contrato de 404 vazio aplica-se às rotas personalizadas de pré-visualização e de ficheiros. Um erro de justificação após retirar todos os media conservou a remoção, o texto escapado, a categoria e o CAS antigo. Nenhum script recebido no texto foi executado.

Um PDF acima de 1 MiB enviado no campo de anexo, uma imagem PNG de 5000 por 10 píxeis e texto PHP disfarçado de JPEG foram recusados. A comparação dos registos de notícias e âncoras antes e depois foi exacta. Ficheiros novos precisam de nova selecção após erro.

As linhas preexistentes de utilizadores, metadados, posts e tabelas ARN mantiveram-se exactas, excluindo apenas tokens de sessão necessários ao login de teste. Os 22 originais administrativos conservaram hashes e manifesto. As contagens continuam a ser cinco circulares, quatro notas internas, onze despachos, um regulamento e uma comunicação sindical. O regulamento continua separado das 21 comunicações. Ana Teste não recebeu acesso a estes anexos reais. O catálogo documental anterior manteve 12 documentos, 15 versões, 37 eventos de auditoria e 15 ficheiros documentais.

## Apresentação e limites

A pré-visualização foi inspeccionada em 1280, 768, 390 e 320 píxeis, sem extravasamento horizontal. As imagens foram descodificadas em cada largura. A simulação de tamanho de texto a 200% por alteração do tamanho de fonte raiz em 390 píxeis também não produziu extravasamento. Esta simulação não é uma verificação do zoom nativo do browser. Foram vistos os screenshots gerados. A identidade visual conserva logótipo, menu, Manrope e Source Sans 3 locais.

O CMS foi medido com a lista máxima de ficheiros em 1280, 768 e 390 píxeis. Todos os controlos visíveis tinham pelo menos 44 píxeis e fonte de 16 píxeis. A QA identificou mensagens de ficheiro pouco úteis, ausência de regresso ao painel, botões de adição indevidamente visíveis no limite e um extravasamento de sete píxeis em mobile. Estas observações originaram uma ronda de correcção antes da revisão final.

A admissão técnica não substitui antimalware. F1-10 permanece Em curso até classificação, autoridade e aceitação editoriais institucionais, política de armazenamento, media nativa e homologação IIS/PHP/MySQL. Aprovação, rejeição, reenvio e publicação permanecem em F1-11. Não houve deploy, alterações ao servidor ARN, AD ou DNS e não foram enviados emails.

## Fecho da QA da parcela local

Após a correcção, os três ficheiros negativos voltaram a ser recusados no POST real com mensagens junto do campo sobre 1 MiB, dimensões ou formato válido. Um novo rascunho fictício da categoria Comunicação sindical aceitou no mesmo POST o destaque PNG, a galeria JPEG/PNG e o PDF, com redireccionamento de sucesso. Não foi publicado. Autor e editor clicaram a ligação de retorno da pré-visualização e regressaram ao detalhe autorizado no CMS.

As quatro larguras do CMS, 1280, 768, 390 e 320 píxeis, ficaram sem extravasamento. Os controlos visíveis mantiveram pelo menos 44 píxeis e fonte de 16 píxeis. Os botões de adição com hidden ficaram efectivamente ocultos no limite. O foco de teclado no campo seguinte mostrou contorno sólido de 3 píxeis. Uma simulação CSS zoom=2 com viewport de 1280 píxeis não produziu extravasamento. Foram inspeccionados os screenshots do fieldset móvel e da ampliação. Não se apresenta esta simulação como ensaio de zoom nativo.

A revisão independente da segunda correcção aprovou os quatro problemas encontrados sem novas regressões. A verificação PHP completa do controlador passou com 690 testes e 2470 asserções, quatro skips Windows e uma depreciação preexistentes. PHPCS passou os 220 ficheiros. A execução final do implementador passou os 55 testes Node, lint e build. A integração WordPress/MariaDB da fase tinha passado 47 verificações e a instalação positiva isolada 14 verificações antes de existirem as contas normais. Não se repetiu a instalação positiva sobre as contas normais já criadas. A integração em Git continua sujeita à revisão global e ao CI no SHA exacto.

Na sessão editorial foi observado InvalidStateError com a mensagem «Transition was aborted because of invalid state. ViewTransition opt-in disabled» após navegação rápida do CMS para trás. O percurso concluiu com resposta 200 e a pré-visualização correcta. A repetição instrumentada voltou a registar a excepção. Não se identificou uma chamada ViewTransition no plugin ou tema e não se afirma que a consola editorial esteve sem erros. A causa desta transição visual continua por isolar.

O CI detectou uma falha intermitente no ensaio de concorrência. A investigação reproduziu a mesma asserção expondo o marcador do worker ainda vazio. A existência do ficheiro, por si só, não garante que o ID da ligação já pode ser lido. A correcção desta sincronização mantém a contenção SQL real e a prova de que a notícia continua livre enquanto a conta está bloqueada. A integração depende da validação desta correcção e do novo CI.

A correcção do ensaio passou as 47 verificações de integração. A leitura do sinal usa agora bloqueio partilhado e exige um ID positivo antes de consultar as esperas SQL. A regressão expõe intencionalmente um marcador vazio antes de o worker avançar. O código anterior reproduziu a falha e o código corrigido passou, conservando as consultas INNODB_LOCK_WAITS e FOR UPDATE NOWAIT. O log do CI original não guardou o valor lido naquele momento, pelo que esta reprodução demonstra uma causa possível e elimina essa fragilidade, sem afirmar que recuperou o valor original. O novo CI continua a ser necessário antes da integração.
