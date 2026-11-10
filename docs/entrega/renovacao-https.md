# Renovação HTTPS da ARN

Estado em 10 de novembro de 2026. O responsável confirmou a página estática por HTTPS. As imagens confirmam DNS interno para 192.168.17.205, certificado de produção importado com chave privada e associação SNI exclusiva no site ARN-Intranet. A validade observada termina em 7 de fevereiro de 2027. Não há ainda prova de renovação automática, isolamento externo ou funcionamento institucional da aplicação.

## Script e limites

Usar scripts/renew-arn-certificate.ps1 no servidor .205, numa pasta privada C:\ProgramData\ARN\CertificateAutomation. Não colocar no document root. A conta Windows deve ser a mesma que criou o pedido ACME, com perfil carregado e elevação. Não usar SYSTEM: a configuração Posh-ACME e a protecção das credenciais pertencem ao perfil original. Confirmar o nome completo com whoami. A versão do módulo é fixada em 4.35.0, já observada no servidor.

O script selecciona LE_PROD e apenas o pedido plataforma-arn.duckdns.org. Não força emissão. Confere site, pool, associação exacta, SNI, SAN, validade, confiança e chave privada. Importa o certificado actual quando necessário e reconcilia a associação mesmo quando uma renovação anterior teve falha de instalação. Não reinicia o IIS, não remove certificados antigos e não modifica Default Web Site/RDWeb. O modo -CheckOnly não renova nem altera certificados/associações, mas selecciona o contexto Posh-ACME e escreve lock e log privados.

Os registos contêm apenas fase, resultado, validade e impressão digital. Excepções completas não são registadas porque podem conter URLs com tokens. Uma falha devolve código 1. A validade inferior a 14 dias também falha. Não existe ainda envio de alertas: o operador deve acompanhar o resultado e a data da última execução. O teste HTTPS usa /teste-https.html e deve ser substituído por uma sonda aprovada antes de retirar a página estática. Não usar um redireccionamento para login como prova de saúde dos módulos.

## Preparação pelo responsável

Criar a pasta privada e restringir ACL a SYSTEM e Administradores (SIDs independentes do idioma), sem permissões para identidades IIS. Copiar o script revisto. Confirmar que não existem junctions ou ficheiros preexistentes inesperados. Guardar também os backups ACME e as chaves fora da raiz pública com acesso administrativo. Não incluir segredos no script, argumentos da tarefa, imagens ou Git. O responsável decidiu conservar o token actual apesar da exposição anterior.

Executar primeiro com powershell.exe -NoProfile -NonInteractive -File C:\ProgramData\ARN\CertificateAutomation\renew-arn-certificate.ps1 -CheckOnly. Deve terminar com OK e código 0. Depois executar sem -CheckOnly, sem -Force. Um certificado recente deve ser reutilizado. Esta passagem não prova uma futura emissão: manter o ensaio de staging já efectuado e verificar uma renovação efectiva quando devida.

## Agendador de Tarefas

Criar ARN - Renovacao HTTPS com a conta indicada por whoami, Executar independentemente de o utilizador ter sessão iniciada e Executar com privilégios mais elevados. Introduzir a palavra-passe Windows apenas no diálogo do servidor. Não seleccionar a opção que impede guardar a palavra-passe e limita o acesso a recursos locais. Acção: Windows PowerShell 5.1 de 64 bits, argumentos -NoProfile -NonInteractive -File "C:\ProgramData\ARN\CertificateAutomation\renew-arn-certificate.ps1". Não alterar a política de execução global para contornar bloqueios.

Disparador diário às 08:17 na hora local do servidor. Executar logo que possível após início perdido. Não iniciar nova instância se já estiver em execução. Limite de uma hora, repetição após 30 minutos até três tentativas. Executar manualmente a tarefa e exigir resultado 0x0, log recente OK e página HTTPS válida. Confirmar também execução sem sessão interactiva. O bloqueio de ficheiro impede concorrência entre execuções do script.

## Validação e instalação da plataforma

No desenvolvimento foi verificada apenas a sintaxe pelo parser Windows PowerShell e git diff --check. Não foi executada renovação ou alteração IIS pelo agente. Antes de considerar esta operação homologada, testar no servidor exacto, conferir que as associações dos outros sites permanecem iguais e validar recuperação após falha de implantação. Não declarar alertas automáticos configurados.

Depois: inventário PHP/FastCGI/SQL e volumes, isolamento LAN/VPN sem interferir com acesso remoto, adaptação institucional e migrações próprias, candidato com CI verde e hashes, instalação vazia com dados fictícios, login Google real com sessões/recusas, recuperação e piloto aprovado. Não copiar a base do laboratório ou activar flags fictícias para abrir a plataforma em produção.
