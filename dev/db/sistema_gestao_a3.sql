-- =====================================================================
-- Projeto A3 - UC Banco de Dados (2026/2)
-- Sistema de Gestão de Comandas (bar/restaurante)
-- Banco: sistema_gestao_a3  (MySQL 8+ / MariaDB 10.4+)
--
-- Este script contém, em ordem:
--   1) DDL - criação do banco, tabelas, chaves e restrições
--   2) DML - carga inicial fictícia, com UPDATE e DELETE de exemplo
--   3) DQL - consultas estratégicas com JOIN
-- Pode ser executado inteiro, mais de uma vez (recria apenas este banco).
-- =====================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS sistema_gestao_a3
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE sistema_gestao_a3;

-- =====================================================================
-- 1) DDL
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS pagamentos;
DROP TABLE IF EXISTS itens_comanda;
DROP TABLE IF EXISTS comandas;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS produtos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS usuarios;
DROP VIEW  IF EXISTS vw_resumo_comandas;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome         VARCHAR(100) NOT NULL,
  email        VARCHAR(255) NOT NULL,
  senha_hash   VARCHAR(255) NOT NULL,
  perfil       ENUM('admin','atendente') NOT NULL DEFAULT 'atendente',
  status_conta ENUM('ativo','inativo')   NOT NULL DEFAULT 'ativo',
  criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_usuarios PRIMARY KEY (id),
  CONSTRAINT uq_usuarios_email UNIQUE (email)
) ENGINE=InnoDB;

CREATE TABLE categorias (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome  VARCHAR(50) NOT NULL,
  CONSTRAINT pk_categorias PRIMARY KEY (id),
  CONSTRAINT uq_categorias_nome UNIQUE (nome)
) ENGINE=InnoDB;

CREATE TABLE produtos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id INT UNSIGNED NOT NULL,
  nome         VARCHAR(100) NOT NULL,
  valor        DECIMAL(10,2) NOT NULL,
  imagem       VARCHAR(255) NOT NULL DEFAULT 'assets/default.png',
  ativo        TINYINT(1) NOT NULL DEFAULT 1,
  criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_produtos PRIMARY KEY (id),
  CONSTRAINT ck_produtos_valor CHECK (valor >= 0)
) ENGINE=InnoDB;

CREATE TABLE clientes (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome      VARCHAR(100) NOT NULL,
  telefone  VARCHAR(20) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_clientes PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE comandas (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  status     ENUM('aberta','fechada','fiado','cancelada') NOT NULL DEFAULT 'aberta',
  aberta_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fechada_em DATETIME NULL,
  CONSTRAINT pk_comandas PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE itens_comanda (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  comanda_id     INT UNSIGNED NOT NULL,
  produto_id     INT UNSIGNED NOT NULL,
  quantidade     INT UNSIGNED NOT NULL,
  valor_unitario DECIMAL(10,2) NOT NULL,
  CONSTRAINT pk_itens_comanda PRIMARY KEY (id),
  CONSTRAINT ck_itens_quantidade CHECK (quantidade > 0),
  CONSTRAINT ck_itens_valor CHECK (valor_unitario >= 0)
) ENGINE=InnoDB;

CREATE TABLE pagamentos (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  comanda_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  metodo     ENUM('pix','dinheiro','cartao_credito','cartao_debito') NOT NULL,
  valor      DECIMAL(10,2) NOT NULL,
  pago_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_pagamentos PRIMARY KEY (id),
  CONSTRAINT ck_pagamentos_valor CHECK (valor > 0)
) ENGINE=InnoDB;

-- Chaves estrangeiras (ALTER TABLE)
ALTER TABLE produtos
  ADD CONSTRAINT fk_produtos_categoria
  FOREIGN KEY (categoria_id) REFERENCES categorias (id)
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE comandas
  ADD CONSTRAINT fk_comandas_cliente
  FOREIGN KEY (cliente_id) REFERENCES clientes (id)
  ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT fk_comandas_usuario
  FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- Os itens pertencem à comanda (exclui junto); o produto vendido nunca é
-- apagado enquanto houver histórico de venda (RESTRICT).
ALTER TABLE itens_comanda
  ADD CONSTRAINT fk_itens_comanda
  FOREIGN KEY (comanda_id) REFERENCES comandas (id)
  ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_itens_produto
  FOREIGN KEY (produto_id) REFERENCES produtos (id)
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE pagamentos
  ADD CONSTRAINT fk_pagamentos_comanda
  FOREIGN KEY (comanda_id) REFERENCES comandas (id)
  ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT fk_pagamentos_usuario
  FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- Índices de apoio às consultas e filtros mais frequentes
ALTER TABLE comandas       ADD INDEX idx_comandas_status_data (status, aberta_em);
ALTER TABLE produtos       ADD INDEX idx_produtos_nome (nome);
ALTER TABLE itens_comanda  ADD INDEX idx_itens_comanda (comanda_id);

-- Visão com o total de cada comanda (soma dos itens) e o valor já pago
CREATE VIEW vw_resumo_comandas AS
SELECT c.id AS comanda_id,
       c.status,
       c.aberta_em,
       COALESCE((SELECT SUM(i.quantidade * i.valor_unitario)
                   FROM itens_comanda i WHERE i.comanda_id = c.id), 0) AS total,
       COALESCE((SELECT SUM(p.valor)
                   FROM pagamentos p WHERE p.comanda_id = c.id), 0)    AS pago
  FROM comandas c;

-- =====================================================================
-- 2) DML - carga inicial com dados fictícios para homologação
-- =====================================================================
-- Categorias
INSERT INTO categorias (id, nome) VALUES
  (1, 'Cervejas'),
  (2, 'Refrigerantes'),
  (3, 'Águas e Energéticos'),
  (4, 'Porções e Lanches'),
  (5, 'Salgadinhos e Doces'),
  (6, 'Jogos');

-- Produtos
INSERT INTO produtos (id, categoria_id, nome, valor, imagem) VALUES
  (1, 1, 'Cerveja Lata 350ml', 6.00, 'assets/uploads/cerveja-lata.jpg'),
  (2, 1, 'Cerveja Long Neck', 8.50, 'assets/uploads/long-neck.jpg'),
  (3, 1, 'Cerveja Garrafa 600ml', 12.00, 'assets/uploads/cerveja-600.jpg'),
  (4, 2, 'Refrigerante Lata 350ml', 5.00, 'assets/uploads/refri-lata.jpg'),
  (5, 2, 'Refrigerante 2L', 12.00, 'assets/uploads/refri-2l.jpg'),
  (6, 3, 'Água Mineral 500ml', 3.50, 'assets/uploads/agua.jpg'),
  (7, 3, 'Energético 473ml', 11.00, 'assets/uploads/energetico.jpg'),
  (8, 4, 'Porção de Fritas', 25.00, 'assets/uploads/fritas.png'),
  (9, 4, 'X-Bacon', 22.00, 'assets/uploads/x-bacon.png'),
  (10, 4, 'Porção de Calabresa', 30.00, 'assets/uploads/calabresa.jpg'),
  (11, 5, 'Salgadinho 60g', 7.00, 'assets/uploads/salgadinho.jpg'),
  (12, 5, 'Chocolate em Barra', 6.50, 'assets/uploads/chocolate.jpg'),
  (13, 6, 'Ficha de Sinuca', 2.00, 'assets/uploads/ficha-sinuca.webp'),
  (14, 4, 'Petisco da Casa', 18.00, 'assets/default.png');

-- Clientes
INSERT INTO clientes (id, nome, telefone) VALUES
  (1, 'Carlos Almeida', '(41) 99000-0001'),
  (2, 'Fernanda Souza', '(41) 99000-0002'),
  (3, 'João Pereira', '(41) 99000-0003'),
  (4, 'Mariana Costa', NULL),
  (5, 'Ricardo Lima', '(41) 99000-0005'),
  (6, 'Patrícia Rocha', NULL),
  (7, 'Bruno Carvalho', '(41) 99000-0007'),
  (8, 'Juliana Martins', '(41) 99000-0008');

-- Usuários (senha de todos: Senha@123, armazenada com hash bcrypt)
INSERT INTO usuarios (id, nome, email, senha_hash, perfil, status_conta) VALUES
  (1, 'Administrador', 'admin@a3.local', '$2y$10$xac2TOJEFL5AOVStYhUOJelIbKH.3PYnKQW7IztkP/RyOWT6L6j1i', 'admin', 'ativo'),
  (2, 'Ana Atendente', 'ana@a3.local', '$2y$10$xac2TOJEFL5AOVStYhUOJelIbKH.3PYnKQW7IztkP/RyOWT6L6j1i', 'atendente', 'ativo'),
  (3, 'Pedro Atendente', 'pedro@a3.local', '$2y$10$xac2TOJEFL5AOVStYhUOJelIbKH.3PYnKQW7IztkP/RyOWT6L6j1i', 'atendente', 'ativo'),
  (4, 'Lucas Desligado', 'lucas@a3.local', '$2y$10$xac2TOJEFL5AOVStYhUOJelIbKH.3PYnKQW7IztkP/RyOWT6L6j1i', 'atendente', 'inativo');

-- Comandas
INSERT INTO comandas (id, cliente_id, usuario_id, status, aberta_em, fechada_em) VALUES
  (1, 1, 2, 'fechada', '2026-10-05 19:10:00', '2026-10-05 20:45:00'),
  (2, 2, 2, 'fechada', '2026-10-05 20:30:00', '2026-10-05 21:50:00'),
  (3, 3, 3, 'fechada', '2026-10-06 18:45:00', '2026-10-06 20:35:00'),
  (4, 4, 3, 'fechada', '2026-10-10 21:00:00', '2026-10-10 22:00:00'),
  (5, 5, 2, 'fiado', '2026-10-12 17:20:00', NULL),
  (6, 6, 2, 'fechada', '2026-10-14 19:50:00', '2026-10-14 20:35:00'),
  (7, 7, 3, 'fechada', '2026-10-17 20:15:00', '2026-10-17 22:25:00'),
  (8, 8, 3, 'fechada', '2026-10-19 22:00:00', '2026-10-19 23:10:00'),
  (9, 1, 2, 'fechada', '2026-10-24 18:30:00', '2026-10-24 19:20:00'),
  (10, 2, 3, 'aberta', '2026-10-26 19:40:00', NULL),
  (11, 3, 2, 'aberta', '2026-10-28 20:05:00', NULL),
  (12, 4, 3, 'cancelada', '2026-10-29 21:10:00', NULL);

-- Itens das comandas (valor_unitario guarda o preço praticado na venda)
INSERT INTO itens_comanda (id, comanda_id, produto_id, quantidade, valor_unitario) VALUES
  (1, 1, 1, 4, 5.50),
  (2, 1, 8, 1, 25.00),
  (3, 2, 2, 3, 8.50),
  (4, 2, 11, 2, 7.00),
  (5, 3, 3, 2, 12.00),
  (6, 3, 9, 1, 22.00),
  (7, 3, 5, 1, 12.00),
  (8, 4, 4, 2, 5.00),
  (9, 4, 13, 5, 2.00),
  (10, 5, 1, 6, 5.50),
  (11, 5, 10, 1, 30.00),
  (12, 6, 7, 2, 11.00),
  (13, 6, 12, 2, 6.50),
  (14, 7, 2, 6, 8.50),
  (15, 7, 8, 2, 25.00),
  (16, 8, 3, 3, 12.00),
  (17, 8, 6, 2, 3.50),
  (18, 9, 1, 5, 6.00),
  (19, 9, 11, 1, 7.00),
  (20, 10, 2, 2, 8.50),
  (21, 10, 9, 1, 22.00),
  (22, 11, 5, 1, 12.00),
  (23, 11, 8, 1, 25.00),
  (24, 12, 4, 1, 5.00);

-- Pagamentos (a comanda 5 está em fiado, com pagamento parcial)
INSERT INTO pagamentos (id, comanda_id, usuario_id, metodo, valor, pago_em) VALUES
  (1, 1, 2, 'pix', 47.00, '2026-10-05 20:45:00'),
  (2, 2, 2, 'dinheiro', 39.50, '2026-10-05 21:50:00'),
  (3, 3, 3, 'pix', 30.00, '2026-10-06 20:25:00'),
  (4, 3, 3, 'cartao_debito', 28.00, '2026-10-06 20:35:00'),
  (5, 4, 3, 'dinheiro', 20.00, '2026-10-10 22:00:00'),
  (6, 5, 2, 'dinheiro', 20.00, '2026-10-12 19:20:00'),
  (7, 6, 2, 'cartao_debito', 35.00, '2026-10-14 20:35:00'),
  (8, 7, 3, 'pix', 101.00, '2026-10-17 22:25:00'),
  (9, 8, 3, 'cartao_credito', 43.00, '2026-10-19 23:10:00'),
  (10, 9, 2, 'dinheiro', 37.00, '2026-10-24 19:20:00');

-- Ajustes de exemplo executados após a carga (UPDATE e DELETE)
-- Reajuste do preço da cerveja lata: as comandas antigas mantêm o valor
-- original em itens_comanda.valor_unitario.
UPDATE produtos SET valor = 6.00 WHERE id = 1;
-- Inativa um produto (em vez de excluir, preservando o histórico)
UPDATE produtos SET ativo = 0 WHERE id = 14;
-- Correção de cadastro
UPDATE clientes SET telefone = '(41) 99000-0004' WHERE id = 4;
-- Exclusão: cadastro duplicado de cliente criado por engano
INSERT INTO clientes (id, nome) VALUES (9, 'Cliente Duplicado');
DELETE FROM clientes WHERE id = 9;

-- =====================================================================
-- 3) DQL - consultas estratégicas
-- =====================================================================

-- Q1. Relatório de comandas: cliente, atendente e total (JOIN de 4 tabelas)
SELECT c.id AS comanda, cl.nome AS cliente, u.nome AS atendente, c.status,
       c.aberta_em, SUM(i.quantidade * i.valor_unitario) AS total
  FROM comandas c
  JOIN clientes cl ON cl.id = c.cliente_id
  JOIN usuarios u  ON u.id  = c.usuario_id
  JOIN itens_comanda i ON i.comanda_id = c.id
 GROUP BY c.id, cl.nome, u.nome, c.status, c.aberta_em
 ORDER BY c.aberta_em;

-- Q2. Comandas de um período (outubro/2026) com a quantidade de itens
SELECT c.id AS comanda, cl.nome AS cliente, c.aberta_em,
       SUM(i.quantidade) AS qtd_itens
  FROM comandas c
  JOIN clientes cl ON cl.id = c.cliente_id
  JOIN itens_comanda i ON i.comanda_id = c.id
 WHERE c.aberta_em BETWEEN '2026-10-01 00:00:00' AND '2026-10-15 23:59:59'
 GROUP BY c.id, cl.nome, c.aberta_em
 ORDER BY c.aberta_em;

-- Q3. Produtos mais vendidos (somente comandas fechadas ou em fiado)
SELECT p.nome AS produto, ct.nome AS categoria,
       SUM(i.quantidade) AS qtd_vendida,
       SUM(i.quantidade * i.valor_unitario) AS receita
  FROM itens_comanda i
  JOIN produtos p    ON p.id  = i.produto_id
  JOIN categorias ct ON ct.id = p.categoria_id
  JOIN comandas c    ON c.id  = i.comanda_id
 WHERE c.status IN ('fechada','fiado')
 GROUP BY p.id, p.nome, ct.nome
 ORDER BY qtd_vendida DESC
 LIMIT 5;

-- Q4. Faturamento por categoria
SELECT ct.nome AS categoria,
       SUM(i.quantidade * i.valor_unitario) AS faturamento
  FROM categorias ct
  JOIN produtos p       ON p.categoria_id = ct.id
  JOIN itens_comanda i  ON i.produto_id = p.id
  JOIN comandas c       ON c.id = i.comanda_id
 WHERE c.status <> 'cancelada'
 GROUP BY ct.id, ct.nome
 ORDER BY faturamento DESC;

-- Q5. Recebimentos por forma de pagamento
SELECT p.metodo, COUNT(*) AS qtd_pagamentos, SUM(p.valor) AS total_recebido
  FROM pagamentos p
 GROUP BY p.metodo
 ORDER BY total_recebido DESC;

-- Q6. Desempenho dos atendentes (inclui quem não abriu nenhuma comanda)
SELECT u.nome AS atendente, u.status_conta,
       COUNT(DISTINCT c.id) AS comandas_abertas,
       COALESCE(SUM(v.total), 0) AS valor_total
  FROM usuarios u
  LEFT JOIN comandas c ON c.usuario_id = u.id
  LEFT JOIN vw_resumo_comandas v ON v.comanda_id = c.id
 WHERE u.perfil = 'atendente'
 GROUP BY u.id, u.nome, u.status_conta
 ORDER BY valor_total DESC;

-- Q7. Saldo devedor das comandas em fiado (total - pago), por cliente
SELECT cl.nome AS cliente, cl.telefone, v.comanda_id,
       v.total, v.pago, (v.total - v.pago) AS saldo_devedor
  FROM vw_resumo_comandas v
  JOIN comandas c   ON c.id = v.comanda_id
  JOIN clientes cl  ON cl.id = c.cliente_id
 WHERE c.status = 'fiado' AND v.total > v.pago;

-- Q8. Produtos que nunca foram vendidos (LEFT JOIN + IS NULL)
SELECT p.id, p.nome, p.valor, p.ativo
  FROM produtos p
  LEFT JOIN itens_comanda i ON i.produto_id = p.id
 WHERE i.id IS NULL;

-- Q9. Clientes que gastaram acima da média das comandas (subconsulta)
SELECT cl.nome AS cliente, SUM(v.total) AS total_gasto
  FROM clientes cl
  JOIN comandas c ON c.cliente_id = cl.id
  JOIN vw_resumo_comandas v ON v.comanda_id = c.id
 WHERE c.status <> 'cancelada'
 GROUP BY cl.id, cl.nome
HAVING SUM(v.total) > (SELECT AVG(total) FROM vw_resumo_comandas WHERE status <> 'cancelada')
 ORDER BY total_gasto DESC;
