# Contratos de preparação do cadastro e importação F1-05

14 de outubro de 2026. Proposta técnica baseada no README 4.3 e 4.4, US-RH01, US-A03 e US-ARN-02. Não constitui aprovação de campos ou de acesso a dados pessoais. O único modelo persistente entregue nesta parcela é o de unidades fictícias.

## Separação de responsabilidades

Um registo profissional, uma conta WordPress e uma identidade Google são entidades distintas. A existência de um registo de pessoa não cria uma conta nem uma concessão. O identificador profissional tem de ser explícito e validado por RH. Nome, posição numa folha e semelhança de email não identificam uma pessoa de forma segura. A associação Google conserva o vínculo autorizado por emissor e subject já previsto na F1-02.

O identificador estável da unidade referencia a estrutura aprovada. Siglas repetidas não são chaves. Renomear uma unidade não altera o identificador. Desactivar uma unidade preserva o histórico e exige uma política própria para pessoas, tarefas e vínculos ainda activos. Esta política não está implementada nem se deduz da desactivação de uma unidade fictícia sem pessoas.

## Validação prévia

O futuro importador deverá ter uma primeira etapa sem escrita. Essa etapa valida o formato, a codificação, cabeçalhos permitidos, limites de tamanho e número de linhas, campos obrigatórios e tipos. RH tem de confirmar o esquema de campos e a fonte autorizada antes de aceitar ficheiros reais.

Cada linha deve produzir erros identificados e um resultado proposto. Detectar identificadores duplicados no ficheiro e no cadastro, unidades inexistentes ou inactivas e campos desconhecidos. Um nome repetido não basta para fundir pessoas. Não gerar emails a partir de nomes. Não aceitar colunas de papel WordPress, permissões ou identidades federadas como concessões automáticas.

A pré-visualização distingue criação, alteração e ausência de mudança. Linhas inválidas bloqueiam a confirmação do lote nesta proposta. A ausência de uma pessoa no ficheiro nunca significa suspensão, saída ou eliminação. A operação futura de saída necessita de autoridade, justificação e tratamento explícito de autoria e tarefas pendentes.

## Confirmação e concorrência

A confirmação deve estar vinculada ao conteúdo validado, à revisão do cadastro e da estrutura e ao actor autorizado. Alterações entretanto ocorridas exigem nova validação. Os valores recebidos de volta do browser não substituem a proposta validada no servidor. Autoridade e validade do nonce devem ser verificadas novamente.

O resultado deve registar o identificador do lote, contagens de criação/alteração/sem mudança e auditoria das mudanças. Repetições não podem duplicar pessoas ou alterações. O desenho transaccional e os limites concretos do lote deverão ser definidos na implementação, antes de criar um importador executável. Nunca incluir ficheiros reais, segredos ou payloads pessoais em logs públicos ou no GitHub.

## Dependências antes de dados reais

RH confirma os campos, identificadores, unidades, tipos, superiores, estados e responsáveis. RH/DCSI confirma quem pode consultar, alterar, importar e aprovar, segundo a matriz F1-03. A retenção, o armazenamento privado e a remoção dos ficheiros temporários precisam de contrato antes de activar uploads. F0-01, F0-02/06 e homologação do ambiente mantêm-se pendentes. Os testes seguintes usam exclusivamente dados sintéticos.
