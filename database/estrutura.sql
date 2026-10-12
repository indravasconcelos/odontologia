-- ===================================================================
--  ESTRUTURA DO BANCO - Dr. Maycon Gomes
--  MySQL 8.x · utf8mb4 · compativel com Hostinger
--
--  Como usar:
--   Local  : via "database/instalar.php" (cria o banco e importa)
--   Hostinger: phpMyAdmin > Importar > escolher este arquivo
-- ===================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------
-- USUARIOS - acesso a area administrativa
-- A senha NUNCA e guardada em texto: apenas o hash de password_hash()
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  senha_hash    VARCHAR(255) NOT NULL,
  tipo          ENUM('admin', 'atendente') NOT NULL DEFAULT 'admin',
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- PACIENTES
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pacientes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome          VARCHAR(120) NOT NULL,
  telefone      VARCHAR(20) DEFAULT NULL,
  email         VARCHAR(190) DEFAULT NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pacientes_nome (nome),
  KEY ix_pacientes_telefone (telefone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- SERVICOS - duracao define o tamanho do horario reservado
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS servicos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome            VARCHAR(120) NOT NULL,
  duracao_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  ativo           TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_servicos_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- HORARIOS - faixa de atendimento de cada dia da semana
-- dia_semana: 0 domingo ... 6 sabado (padrao de date('w'))
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS horarios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dia_semana    TINYINT UNSIGNED NOT NULL,
  hora_inicio   TIME NOT NULL,
  hora_fim      TIME NOT NULL,
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY ix_horarios_dia (dia_semana, ativo),
  -- sem esta unique, rodar o instalador de novo duplicava as faixas
  -- e a lista de horarios mostrava cada hora varias vezes
  UNIQUE KEY uq_horarios_faixa (dia_semana, hora_inicio, hora_fim)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- BLOQUEIOS - indisponibilidade pontual (ferias, almooco, cursos)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bloqueios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data          DATE NOT NULL,
  hora_inicio   TIME NOT NULL,
  hora_fim      TIME NOT NULL,
  motivo        VARCHAR(160) DEFAULT NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_bloqueios_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- AGENDAMENTOS
-- status: confirmado · cancelado · concluido · faltou
-- origem: site (chat) · admin (painel) · ia (agente)
-- A regra de conflito e validada no PHP, dentro de transacao.
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS agendamentos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  paciente_id   INT UNSIGNED NOT NULL,
  servico_id    INT UNSIGNED NOT NULL,
  data          DATE NOT NULL,
  hora_inicio   TIME NOT NULL,
  hora_fim      TIME NOT NULL,
  status        ENUM('confirmado','cancelado','concluido','faltou') NOT NULL DEFAULT 'confirmado',
  origem        ENUM('site','admin','ia') NOT NULL DEFAULT 'admin',
  observacoes   VARCHAR(500) DEFAULT NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_agendamentos_data (data, status),
  KEY ix_agendamentos_paciente (paciente_id),
  KEY ix_agendamentos_servico (servico_id),
  CONSTRAINT fk_agendamentos_paciente FOREIGN KEY (paciente_id)
    REFERENCES pacientes (id) ON DELETE CASCADE,
  CONSTRAINT fk_agendamentos_servico FOREIGN KEY (servico_id)
    REFERENCES servicos (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- RESET_TOKENS - recuperacao de senha
-- Guardamos apenas o SHA-256 do token, nunca o token em si.
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reset_tokens (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id    INT UNSIGNED NOT NULL,
  token_hash    CHAR(64) NOT NULL,
  expira_em     DATETIME NOT NULL,
  usado_em      DATETIME DEFAULT NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reset_token_hash (token_hash),
  KEY ix_reset_usuario (usuario_id),
  CONSTRAINT fk_reset_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;