### .CONFIG ###
Configura��es gerais do sistema

JWT_KEY = Chave para gera��o do token de autentica��o.
DISPLAY_ERROR_DETAILS = Mostra erros na tela. Deve ser desabilitado em produ��o.
LOG_PATH = Pasta onde ser�o gravados os logs
LOG_NAME = Identificador da aplica��o que gerou log


SMTP_HOST="your.host.com"  // your email host, to test I use localhost and check emails using test mail server application (catches all  sent mails)
SMTP_AUTH=true // I set false for localhost
SMTP_SECURE="ssl" // set blank for localhost
SMTP_PORT="25" // 25 for local host
SMTP_USERNAME="wagnerggg@gmail.com" // I set sender email in my mailer call
SMTP_PASSWORD="yourPassword"
SMTP_ISHTML=1