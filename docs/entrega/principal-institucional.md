# Parcela de identidade institucional

12 de novembro de 2026. Implementação isolada do valor Principal e da política SessionPrincipalPolicy. Não activa rotas nem altera SQL, contas, cookies ou configurações do servidor.

A política exige conta activa, prova nativa válida fornecida pelo adaptador, vínculo autorizado e activo e correspondência estrita de titular, issuer/sub, geração e revisão de autorização. Os metadados têm versão explícita e não aceitam coerção de tipos. A sessão local legada não basta. Os limites temporais reutilizam a política local de uma hora e trinta minutos de inactividade. Estes limites ainda exigem validação institucional.

A fonte futura tem de obter os valores correntes no servidor e fechar em falha de leitura. A política pura não prova que essa leitura ocorreu. O principal não contém concessões de negócio e a sua construção não autentica ninguém.

## Verificação

Testes fictícios específicos: seis testes e 74 asserções aprovados em PHP portátil 8.4.26. Incluem dados incompletos, tipos incorrectos, alterações de geração/revisão, suspensão, sessão legada, tempos e construção inválida. Revisão independente sem achados bloqueantes. As duas melhorias de testes identificadas foram aplicadas.

Não existe alteração HTTP nesta parcela. Não há prova nova de Playwright, SQL, IIS, emissão de cookies ou concorrência. A regressão global é registada no fecho da entrega, incluindo falhas, sem converter testes unitários em homologação.

## Continuação obrigatória

Implementar geração persistente e migração explícita sem fixtures. Seguem-se a fonte institucional, emissão e revalidação da sessão nativa e testes de concorrência e Playwright com fornecedor simulado. Depois adaptar cada consumidor e instalar um candidato sem dados de laboratório. O contrato de identidade mantém os seus 16 critérios abertos até prova integrada.

Esta parcela antecipa apenas a política pura da segunda entrega do contrato. Não substitui nem conclui a primeira entrega de persistência. A activação institucional permanece bloqueada.
