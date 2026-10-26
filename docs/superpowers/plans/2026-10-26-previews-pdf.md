# Pré-visualizações privadas de PDF

Base: main 73b6b39, branch feat/previews-pdf-privados. Execução directa autorizada no laboratório fictício 8892. Testes agrupados no fecho conforme pedido. Sem alteração da demonstração, originais antigos, contas reais ou produção.

## Contrato

Cada novo PDF admitido com a opção explícita produz PNG da primeira página com lado máximo 1280 e miniatura de 320. O original mantém os seus bytes. A cópia recebe análise ClamAV antes do descodificador e cada derivado também é analisado antes do registo atómico do consumidor. Ficheiros encriptados, com formulários interactivos, inválidos ou acima dos limites ficam em quarentena. A imagem não valida assinaturas nem substitui o documento completo.

PDFium corre num processo Python separado, sem inicialização de formulários ou JavaScript. Versões fixas pypdfium2 5.14.0 e Pillow 12.3.0. Limites por processo de memória 384 MiB, CPU oito segundos e parede quinze segundos. Uma página, até 500 páginas no documento, dimensões até 14400 pontos e entrada/derivado até 1 MiB. Estes limites não constituem uma sandbox completa de sistema operativo.

O leitor da fila aceita resultados PDF antigos só com original e novos com as três variantes exactas. Não reescreve diários, não converte em GET e não reprocessa automaticamente o arquivo. Flag ausente mantém o comportamento anterior. Flag activada com runtime indisponível recusa a conversão. Notícias, documentos e tarefas usam as rotas privadas existentes. A UI verifica disponibilidade pelo leitor autorizado e fecha o handle, sem expor identificadores dos derivados.

## Execução e aceitação

1. Implementar renderer, composição opt-in, contrato de variantes e disponibilidade da UI.
2. Integrar componente acessível de primeira página nos três consumidores, preservando download.
3. Cobrir rasterização real, fonte intacta, segunda página excluída, recusa, limites, quarentena, auditoria, legado e autorização. Executar PHP/Node/lint/build, integração e Playwright com dados fictícios.
4. Rever a branch com contexto independente, corrigir achados e actualizar README/evidência. Commit/push, PR e merge apenas com CI verde no SHA exacto. Confirmar main.

Decisão: o componente consulta o leitor nativo, pois os agregados históricos de notícias/documentos não transportam derivados. Assim não se altera o esquema nem se infere existência a partir do MIME. O custo é uma leitura privada adicional apenas nos PDFs apresentados em detalhe.

Fecho: implementação e circuito local concluídos. Python nove testes, SQL 66, Playwright 86, Node 55 e PHPCS dezoito ficheiros aprovados. Regressão da reconciliação reproduzida antes da correcção e confirmada depois, incluindo recusa por autor divergente. Duas revisões independentes sem achados pendentes. Evidência e limites em `docs/previews-pdf-privados-local-f1-08.md`. Integração condicionada à CI do SHA final e à confirmação de main.
