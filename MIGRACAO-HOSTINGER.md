# Migração para a Hostinger

O sistema foi feito com PHP e MySQL comuns, sem nada específico de
servidor local. Publicar na Hostinger é só enviar arquivos, criar o
banco e configurar o `.env`.

> **Resumo em 5 passos**
> 1. Enviar os arquivos (sem `_demo/`)
> 2. Criar banco e usuário no hPanel
> 3. Criar o `.env` no servidor
> 4. Terminal: `php database/instalar.php`
> 5. Trocar senha → remover `INSTALAR_TOKEN` → apagar `instalar.php` → SSL
>
> O passo 4 **exige o Terminal** do hPanel (Avançado → Terminal).
> O instalador é bloqueado pela web de propósito.

---

## 0. Antes de começar, confira aqui

- [ ] Não está enviando a pasta `_demo/` (1,5 GB)
- [ ] Não está enviando o arquivo `database/.instalado`
- [ ] O `.htaccess` e o `.env` **subiram** — muitos clientes de FTP
      escondem arquivos que começam com ponto

### Pacote pronto

Na raiz do projeto há o **`public_html-hostinger.zip`** (14,4 MB), já
montado com o que vai para o servidor:

- inclui `.htaccess`, `favicon.svg`, `assets/`, `css/`, `js/`, `app/`,
  `api/`, `auth/`, `admin/`, `database/` e as pastas `logs/` e
  `backups/` **vazias** (só a proteção)
- **não** inclui `_demo/`, `.git/`, o `.env` com os dados locais,
  `database/.instalado`, nem os registros de `logs/`
- 67 arquivos, 15,6 MB descompactado

É só descompactar dentro de `public_html` no hPanel. Se enviar por FTP,
envie os arquivos soltos em vez do zip.

---

## 1. Requisitos na Hostinger

- Plano de hospedagem com **PHP 8.1 ou superior**. O sistema usa o tipo
  de retorno `never`, que chegou no 8.1. O padrão da Hostinger hoje é
  8.3, então está tranquilo.
- Extensões `pdo_mysql`, `curl`, `mbstring` e `openssl` — todas vêm
  habilitadas por padrão
- Um banco de dados MySQL criado pelo hPanel
- Acesso ao **Terminal** do hPanel (para instalar e para backup)

---

## 2. O que enviar e o que NÃO enviar

Envie **tudo**, exceto a pasta `_demo`:

```
index.html
.htaccess
css/  js/  assets/
app/  api/  auth/  admin/  database/
logs/     (só o .htaccess e o .gitkeep, vazia)
backups/  (só o .htaccess e o .gitkeep, vazia)
.env      (você cria lá com os dados do servidor)
```

São **67 arquivos, cerca de 15,6 MB** — quase tudo são as fotos e o vídeo.
Com a pasta `_demo` seria 1,5 GB.

**NÃO envie:**
- `_demo/` — servidor portátil, só serve na sua máquina
- `.git/` — histórico do Git
- `database/.instalado` — marcador local. Se subir, o `instalar.php`
  acha que o servidor já foi instalado e **pula a criação das tabelas**

**Cuidado com o `.env`:** envie o arquivo, mas nunca o versione no Git.
Ele já está no `.gitignore`.

**Cuidado com `backups/`:** essa pasta guarda cópias do banco com
**dados pessoais dos pacientes**. Nunca versione, nunca deixe
downloadável e apague do servidor depois de baixar.

---

## 3. Passo a passo

### 3.1 Criar o banco

No hPanel: **Bancos de Dados → Gerenciar**. Crie o banco e anote:

- Nome do banco
- Usuário
- Senha

### 3.2 Importar a estrutura

**phpMyAdmin → selecione o banco → Importar** e envie:

```
database/estrutura.sql
```

Isso cria as 7 tabelas: `usuarios`, `pacientes`, `servicos`, `horarios`,
`bloqueios`, `agendamentos` e `reset_tokens`.

Depois rode `database/dados-iniciais.sql` também na importação, para já
cadastrar os 9 serviços e os horários de segunda a sábado.

> Dá para pular esta importação e deixar o passo 3.3 criar tudo de uma vez.

### 3.3 Criar o usuário administrador

> **A conta do servidor é nova.** O banco de produção nasce vazio: trocar a
> senha no painel da sua máquina **não** viaja com os arquivos. Quem decide
> o e-mail e a senha do acesso na Hostinger é o instalador, lendo o
> `ADMIN_*` do `.env` **do servidor**.

Antes de rodar o instalador, deixe o `.env` do servidor com o e-mail que
você quer usar e uma senha nova (o mesmo e-mail que você já usa aqui):

```ini
ADMIN_NOME=Dr. Maycon Gomes
ADMIN_EMAIL=indrasantos867@gmail.com
ADMIN_SENHA=uma_senha_nova_e_forte
```

Use o **Terminal** do hPanel (menu *Avançado → Terminal*):

```
cd ~/public_html
php database/instalar.php
```

Isso cria as tabelas que faltarem, importa os dados iniciais e cadastra
o administrador com esses valores.

**Por que não pela web:** o `.htaccess` bloqueia a pasta `database/`
com um 404 de propósito, justamente para ninguém rodar o instalador
pela internet. É uma proteção, não um defeito.

### 3.4 Encher a agenda

Crie os serviços, os horários e os primeiros pacientes pelo painel em
`/admin`. É o que faz a agenda aparecer preenchida quando alguém abre
o painel.

Depois de instalar:

1. Entre em `/admin` e confira que o e-mail é o certo
2. Apague as linhas `ADMIN_SENHA` e `INSTALAR_TOKEN` do `.env`
3. Apague o arquivo `database/instalar.php` do servidor

O arquivo `database/.instalado` também impede que o instalador volte a
rodar, mesmo que ele continue lá por engano.

### 3.5 Configurar o `.env`

Edite o `.env` que está no servidor:

```ini
APP_URL=https://www.seudominio.com.br

DB_HOST=localhost
DB_PORT=3306
DB_NAME=u123456789_odontologia
DB_USER=u123456789_odont
DB_PASSWORD=a_senha_que_voce_definir
```

Tudo o mais continua igual.

### 3.6 Ativar o HTTPS

No hPanel: **Segurança → SSL**, instale o certificado gratuito e ative o
"Force HTTPS". O `.htaccess` já redireciona para `https://`.

### 3.7 E-mail (recuperação de senha)

Na Hostinger o `mail()` do PHP já funciona com o e-mail da conta. Para
usar SMTP:

```ini
MAIL_FROM=contato@seudominio.com.br
MAIL_FROM_NAME=Dr. Maycon Gomes

SMTP_HOST=smtp.hostinger.com
SMTP_PORT=465
SMTP_SECURE=ssl
SMTP_USER=contato@seudominio.com.br
SMTP_PASSWORD=sua_senha
```

Se o envio falhar, o conteúdo fica em `logs/mail.log` para conferência.

### 3.8 Chave da DeepSeek

```ini
AI_MODO=deepseek
DEEPSEEK_API_KEY=sk-...
DEEPSEEK_MODEL=deepseek-chat
```

Depois de colocar a chave, teste o chat no site. Se a conta não aceitar
o modelo informado, troque `DEEPSEEK_MODEL` por outro valor da conta
(`deepseek-chat`, `deepseek-v4-flash` ou `deepseek-v4-pro`).

Se deixar `AI_MODO=local`, o site continua funcionando com o assistente
próprio, sem chave e sem internet. É um bom estado para a semana de
testes antes de contratar a API.

---

## 4. Backup dos dados

**Faça isso antes de cadastrar paciente de verdade.**

Pelo Terminal do hPanel:

```
cd ~/public_html
php database/backup.php
```

Sai um arquivo em `backups/backup-AAAAMMDD_HHMMSS.sql` com a estrutura
e todos os dados das 7 tabelas.

Depois:

1. Baixe o arquivo para o seu computador
2. Apague o arquivo do servidor (ele fica bloqueado pela web, mas
   ocupa espaço e contém dados pessoais)

**Para restaurar:** phpMyAdmin → selecione o banco → Importar → o `.sql`.

 Como complemento: em *Bancos de Dados → Gerenciar* do hPanel existe
backup automático. Ative e mantenha ligado — é a sua rede de segurança.

---

## 5. Checklist da publicação

Entre em `/admin` e confira: **enquanto aparecer qualquer aviso amarelo
no topo do painel, ainda não está pronto.**

- [ ] `APP_URL` é o domínio com `https://`
- [ ] Dados do banco conferidos no `.env`
- [ ] `instalar.php` executado pelo Terminal
- [ ] `database/.instalado` **não** foi enviado
- [ ] `database/instalar.php` apagado do servidor
- [ ] `INSTALAR_TOKEN` removido do `.env`
- [ ] `ADMIN_SENHA` removida do `.env`
- [ ] Login do painel testado com a senha definida no `.env` do servidor
- [ ] HTTPS ativo (sem aviso amarelo no painel)
- [ ] `_demo/` **não** foi enviado
- [ ] `.htaccess` e `.env` chegaram ao servidor (FTP esconde arquivos com ponto)
- [ ] `backups/` e `logs/` subiram vazios, só com o `.htaccess`
- [ ] `AI_MODO` e a chave da IA configurados
- [ ] E-mail de recuperação testado em `/auth/esqueci-senha.php`
- [ ] Agendamento de teste criado pelo chat e visto no painel
- [ ] Primeiro backup feito e baixado

### Antes de publicar de verdade

Itens que precisam estar resolvidos antes do site ir para o ar:

- [ ] Depoimentos de exemplo substituídos por depoimentos reais
- [ ] Agenda limpa: pacientes e agendamentos de teste apagados pelo painel
- [ ] Telefone e endereço reais conferidos no site

Os depoimentos hoje são marcados como "Depoimento modelo 01" e "Paciente
demonstrativo". Isso evita que o cliente acredite que foram pacientes
reais, mas **não pode ir para o ar**.

---

## 6. Estrutura no servidor

```
public_html/
├── index.html          site
├── .htaccess           proteção
├── .env                credenciais (nunca versionar)
├── assets/ css/ js/    site e chat
├── app/                classes (bloqueado pelo .htaccess)
├── api/chat.php        endpoint do chat
├── auth/               login e recuperação de senha
├── admin/              painel
├── logs/               registros internos (bloqueado)
├── backups/            cópias do banco (bloqueado)
└── database/           SQL, instalador e backup (bloqueado)
```

---

## 7. Manutenção em produção

**Trocar a senha do administrador:** `/admin` → Minha conta.

**Limpar a agenda antes de usar de verdade:** apague os pacientes, os
agendamentos e os bloqueios de teste pelo próprio painel, em `/admin`.
Serviços, horários e o usuário administrador permanecem.

**Atualizar os horários de atendimento:** `/admin` → Horários. É a fonte
da verdade usada pela assistente.

**Atendimento fora do expediente:** em `/admin` → novo agendamento,
marque a opção correspondente. O sistema libera qualquer dia e horário,
mas nunca permite dois atendimentos no mesmo horário.

**Conferir o que a assistente fez:** `logs/agente.log`.

**Backup:** `php database/backup.php`, e ative o backup automático do
hPanel. Guarde uma cópia do `.env` em local seguro, fora do site.

---

## 8. Segurança — o que já está feito

Nada disso precisa ser configurado, mas vale saber que existe:

| Proteção | Como funciona |
|---|---|
| Senhas | `password_hash()` / `password_verify()`, nunca texto puro |
| Força bruta | 5 tentativas por IP **e** por e-mail, bloqueio de 15 min, contador em arquivo no servidor (não na sessão, que seria burlável) |
| Sessão | Cookie `HttpOnly` + `SameSite`, `secure` automático sob HTTPS, expiração por inatividade |
| Token de recuperação | SHA-256 no banco, validade de 60 min, uso único |
| CSRF | Token por sessão em todos os formulários do painel |
| Chat | Token anti-CSRF próprio, limite de 30 mensagens por minuto |
| SQL | 100% prepared statement, emulação desligada |
| Saída na tela | `htmlspecialchars` em todas as páginas do painel |
| Erros PHP | `display_errors` desligado: nada de SQL ou caminho de arquivo vaza para o visitante |
| Pastas internas | `app/`, `database/`, `logs/`, `backups/` e `_demo/` retornam 404 pela web |
| Arquivos | `.env`, `.sql`, `.log`, `.bat`, `.md` bloqueados por tipo |
| Chave da IA | Fica só no servidor, nunca aparece no HTML nem no JavaScript |
| Dados no log | Sobrenome do paciente é mascarado (`Renata S***`) antes de gravar |
| Agente com IA | A resposta do DeepSeek também passa pela máscara, mesmo em modo `deepseek` |