-- ===================================================================
--  DADOS INICIAIS
--  Servicos e horarios derivados do conteudo que ja existe no site.
--  Tudo pode ser alterado depois pelo painel /admin, sem mexer em codigo.
-- ===================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------
-- SERVICOS (9) - duracoes em minutos
-- -------------------------------------------------------------------
INSERT IGNORE INTO servicos (nome, duracao_minutos, ativo) VALUES
  ('Avaliação',              30, 1),
  ('Aparelhos ortopédicos',  60, 1),
  ('Alinhadores invisíveis', 60, 1),
  ('Clareamento dental',     90, 1),
  ('Profilaxia e prevenção', 40, 1),
  ('Restaurações',           50, 1),
  ('Endodontia',             90, 1),
  ('Contenção',              30, 1),
  ('Prótese dentária',       90, 1)
ON DUPLICATE KEY UPDATE
  duracao_minutos = VALUES(duracao_minutos),
  ativo           = VALUES(ativo);

-- -------------------------------------------------------------------
-- HORARIOS - Segunda a Sabado, como consta no rodape do site
-- 0 domingo | 1 segunda | 2 terca | 3 quarta | 4 quinta | 5 sexta | 6 sabado
-- -------------------------------------------------------------------
INSERT IGNORE INTO horarios (dia_semana, hora_inicio, hora_fim, ativo) VALUES
  (1, '08:00:00', '12:00:00', 1),
  (1, '13:30:00', '18:00:00', 1),
  (2, '08:00:00', '12:00:00', 1),
  (2, '13:30:00', '18:00:00', 1),
  (3, '08:00:00', '12:00:00', 1),
  (3, '13:30:00', '18:00:00', 1),
  (4, '08:00:00', '12:00:00', 1),
  (4, '13:30:00', '18:00:00', 1),
  (5, '08:00:00', '12:00:00', 1),
  (5, '13:30:00', '18:00:00', 1),
  (6, '08:00:00', '12:00:00', 1);