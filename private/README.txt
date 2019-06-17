### .CONFIG ###
Configurações gerais do sistema

JWT_KEY = Chave para geração do token de autenticação.
DISPLAY_ERROR_DETAILS = Mostra erros na tela. Deve ser desabilitado em produção.
LOG_PATH = Pasta onde serão gravados os logs
LOG_NAME = Identificador da aplicação que gerou log


SMTP_HOST="your.host.com"  // your email host, to test I use localhost and check emails using test mail server application (catches all  sent mails)
SMTP_AUTH=true // I set false for localhost
SMTP_SECURE="ssl" // set blank for localhost
SMTP_PORT="25" // 25 for local host
SMTP_USERNAME="wagnerggg@gmail.com" // I set sender email in my mailer call
SMTP_PASSWORD="yourPassword"
SMTP_ISHTML=1