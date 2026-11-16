-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 17/11/2026 às 00:05
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `sistema_gestao_a3`
--

DELIMITER $$
--
-- Procedimentos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_adicionar_item` (IN `p_comanda` INT UNSIGNED, IN `p_produto` INT UNSIGNED, IN `p_qtd` INT UNSIGNED)   BEGIN
  DECLARE v_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_valor  DECIMAL(10,2);
  DECLARE v_ativo  TINYINT DEFAULT NULL;
  
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
  SELECT status INTO v_status FROM comandas WHERE id = p_comanda FOR UPDATE;
  IF v_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Comanda não encontrada.';
  END IF;
  IF v_status <> 'aberta' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Só é possível alterar itens de comanda aberta.';
  END IF;
  SELECT valor, ativo INTO v_valor, v_ativo FROM produtos WHERE id = p_produto;
  IF v_ativo IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Produto não encontrado.';
  END IF;
  IF v_ativo = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Produto inativo não pode ser vendido.';
  END IF;
  INSERT INTO itens_comanda (comanda_id, produto_id, quantidade, valor_unitario)
  VALUES (p_comanda, p_produto, p_qtd, v_valor);
  COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cancelar_comanda` (IN `p_comanda` INT UNSIGNED)   BEGIN
  DECLARE v_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_pagos  INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
  SELECT status INTO v_status FROM comandas WHERE id = p_comanda FOR UPDATE;
  IF v_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Comanda não encontrada.';
  END IF;
  IF v_status NOT IN ('aberta', 'fiado') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Só comandas abertas ou em fiado podem ser canceladas.';
  END IF;
  SELECT COUNT(*) INTO v_pagos FROM pagamentos WHERE comanda_id = p_comanda;
  IF v_pagos > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Comanda com pagamentos não pode ser cancelada.';
  END IF;
  UPDATE comandas SET status = 'cancelada', fechada_em = NOW() WHERE id = p_comanda;
  COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_pagamento` (IN `p_comanda` INT UNSIGNED, IN `p_usuario` INT UNSIGNED, IN `p_metodo` VARCHAR(20), IN `p_valor` DECIMAL(10,2))   BEGIN
  DECLARE v_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_total  DECIMAL(10,2);
  DECLARE v_pago   DECIMAL(10,2);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
  SELECT status INTO v_status FROM comandas WHERE id = p_comanda FOR UPDATE;
  IF v_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Comanda não encontrada.';
  END IF;
  IF v_status NOT IN ('aberta', 'fiado') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Não é possível pagar uma comanda fechada ou cancelada.';
  END IF;
  SELECT total, pago INTO v_total, v_pago FROM vw_resumo_comandas WHERE comanda_id = p_comanda;
  IF v_total <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A comanda não possui itens.';
  END IF;
  IF p_valor > v_total - v_pago THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O valor excede o saldo da comanda.';
  END IF;

  INSERT INTO pagamentos (comanda_id, usuario_id, metodo, valor) VALUES (p_comanda, p_usuario, p_metodo, p_valor);
  IF p_valor = v_total - v_pago THEN
    UPDATE comandas SET status = 'fechada', fechada_em = NOW() WHERE id = p_comanda;
  END IF;
  COMMIT;
  SELECT status, total, pago FROM vw_resumo_comandas WHERE comanda_id = p_comanda;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id`, `nome`) VALUES
(3, 'Águas e Energéticos'),
(1, 'Cervejas'),
(7, 'Destilados e Drinks'),
(6, 'Jogos'),
(4, 'Porções e Lanches'),
(2, 'Refrigerantes'),
(5, 'Salgadinhos e Doces');

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(100) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`id`, `nome`, `telefone`, `criado_em`) VALUES
(1, 'Carlos Almeida', '(41) 99000-0001', '2026-11-10 19:36:52'),
(2, 'Fernanda Souza', '(41) 99000-0002', '2026-11-10 19:36:52'),
(3, 'João Pereira', '(41) 99000-0003', '2026-11-10 19:36:52'),
(4, 'Mariana Costa', '(41) 99000-0004', '2026-11-10 19:36:52'),
(5, 'Ricardo Lima', '(41) 99000-0005', '2026-11-10 19:36:52'),
(6, 'Patrícia Rocha', NULL, '2026-11-10 19:36:52'),
(7, 'Bruno Carvalho', '(41) 99000-0007', '2026-11-10 19:36:52'),
(8, 'Juliana Martins', '(41) 99000-0008', '2026-11-10 19:36:52');

-- --------------------------------------------------------

--
-- Estrutura para tabela `comandas`
--

CREATE TABLE `comandas` (
  `id` int(10) UNSIGNED NOT NULL,
  `cliente_id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `status` enum('aberta','fechada','fiado','cancelada') NOT NULL DEFAULT 'aberta',
  `aberta_em` datetime NOT NULL DEFAULT current_timestamp(),
  `fechada_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `comandas`
--

INSERT INTO `comandas` (`id`, `cliente_id`, `usuario_id`, `status`, `aberta_em`, `fechada_em`) VALUES
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
(12, 4, 3, 'cancelada', '2026-10-29 21:10:00', NULL),
(13, 5, 2, 'fechada', '2026-09-18 19:00:00', '2026-09-18 20:10:00'),
(14, 6, 3, 'fechada', '2026-09-27 21:30:00', '2026-09-27 22:40:00'),
(15, 7, 2, 'fechada', '2026-11-02 18:20:00', '2026-11-02 19:30:00'),
(16, 8, 3, 'fechada', '2026-11-05 20:00:00', '2026-11-10 19:36:52');

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_comanda`
--

CREATE TABLE `itens_comanda` (
  `id` int(10) UNSIGNED NOT NULL,
  `comanda_id` int(10) UNSIGNED NOT NULL,
  `produto_id` int(10) UNSIGNED NOT NULL,
  `quantidade` int(10) UNSIGNED NOT NULL,
  `valor_unitario` decimal(10,2) NOT NULL
) ;

--
-- Despejando dados para a tabela `itens_comanda`
--

INSERT INTO `itens_comanda` (`id`, `comanda_id`, `produto_id`, `quantidade`, `valor_unitario`) VALUES
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
(24, 12, 4, 1, 5.00),
(25, 13, 3, 4, 12.00),
(26, 13, 8, 1, 25.00),
(27, 14, 15, 2, 14.00),
(28, 14, 16, 1, 9.00),
(29, 15, 18, 1, 55.00),
(30, 15, 19, 2, 12.00),
(31, 16, 2, 2, 8.50),
(32, 16, 8, 1, 25.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos`
--

CREATE TABLE `pagamentos` (
  `id` int(10) UNSIGNED NOT NULL,
  `comanda_id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `metodo` enum('pix','dinheiro','cartao_credito','cartao_debito') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `pago_em` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Despejando dados para a tabela `pagamentos`
--

INSERT INTO `pagamentos` (`id`, `comanda_id`, `usuario_id`, `metodo`, `valor`, `pago_em`) VALUES
(1, 1, 2, 'pix', 47.00, '2026-10-05 20:45:00'),
(2, 2, 2, 'dinheiro', 39.50, '2026-10-05 21:50:00'),
(3, 3, 3, 'pix', 30.00, '2026-10-06 20:25:00'),
(4, 3, 3, 'cartao_debito', 28.00, '2026-10-06 20:35:00'),
(5, 4, 3, 'dinheiro', 20.00, '2026-10-10 22:00:00'),
(6, 5, 2, 'dinheiro', 20.00, '2026-10-12 19:20:00'),
(7, 6, 2, 'cartao_debito', 35.00, '2026-10-14 20:35:00'),
(8, 7, 3, 'pix', 101.00, '2026-10-17 22:25:00'),
(9, 8, 3, 'cartao_credito', 43.00, '2026-10-19 23:10:00'),
(10, 9, 2, 'dinheiro', 37.00, '2026-10-24 19:20:00'),
(11, 13, 2, 'pix', 73.00, '2026-09-18 20:10:00'),
(12, 14, 3, 'cartao_credito', 37.00, '2026-09-27 22:40:00'),
(13, 15, 2, 'pix', 79.00, '2026-11-02 19:30:00'),
(14, 16, 3, 'pix', 20.00, '2026-11-10 19:36:52'),
(15, 16, 3, 'dinheiro', 22.00, '2026-11-10 19:36:52');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(10) UNSIGNED NOT NULL,
  `categoria_id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(100) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `imagem` varchar(255) NOT NULL DEFAULT 'assets/default.png',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `categoria_id`, `nome`, `valor`, `imagem`, `ativo`, `criado_em`) VALUES
(1, 1, 'Cerveja Lata 350ml', 6.00, 'assets/uploads/cerveja-lata.png', 1, '2026-11-10 19:36:52'),
(2, 1, 'Cerveja Long Neck', 8.50, 'assets/uploads/long-neck-sol-1780914669.webp', 1, '2026-11-10 19:36:52'),
(3, 1, 'Cerveja Garrafa 600ml', 12.00, 'assets/uploads/brahma-garrafa-1780914618.jpg', 1, '2026-11-10 19:36:52'),
(4, 2, 'Refrigerante Lata 350ml', 5.00, 'assets/uploads/refri.png', 1, '2026-11-10 19:36:52'),
(5, 2, 'Refrigerante 2L', 12.00, 'assets/uploads/sprite-2l-1780914972.jpg', 1, '2026-11-10 19:36:52'),
(6, 3, 'Água Mineral 500ml', 3.50, 'assets/uploads/--gua-mineral-500ml-1780915049.jpg', 1, '2026-11-10 19:36:52'),
(7, 3, 'Energético 473ml', 11.00, 'assets/uploads/monster-473ml-1780915205.png', 1, '2026-11-10 19:36:52'),
(8, 4, 'Porção de Fritas', 25.00, 'assets/uploads/fritas--1780914501.png', 1, '2026-11-10 19:36:52'),
(9, 4, 'X-Bacon', 22.00, 'assets/uploads/x-bacon-1780761286.png', 1, '2026-11-10 19:36:52'),
(10, 4, 'Porção de Calabresa', 30.00, 'assets/uploads/calabresa.png', 1, '2026-11-10 19:36:52'),
(11, 5, 'Salgadinho 60g', 7.00, 'assets/uploads/doritos-1780914737.jpg', 1, '2026-11-10 19:36:52'),
(12, 5, 'Chocolate em Barra', 6.50, 'assets/uploads/kitkat-1780914842.jpg', 1, '2026-11-10 19:36:52'),
(13, 6, 'Ficha de Sinuca', 2.00, 'assets/uploads/ficha-de-sinuca-1780919445.webp', 1, '2026-11-10 19:36:52'),
(14, 4, 'Petisco da Casa', 18.00, 'assets/default.png', 0, '2026-11-10 19:36:52'),
(15, 1, 'Cerveja Litrão', 14.00, 'assets/uploads/brahma-litr--o-1780760328.png', 1, '2026-11-10 19:36:52'),
(16, 2, 'Coca-Cola 1L', 9.00, 'assets/uploads/coca-cola-1l-1780914940.jpg', 1, '2026-11-10 19:36:52'),
(17, 5, 'Cebolitos 60g', 6.50, 'assets/uploads/cebolitos-1780914779.jpg', 1, '2026-11-10 19:36:52'),
(18, 7, 'Smirnoff 1L', 55.00, 'assets/uploads/smirnoff-1l-1780915114.jpg', 1, '2026-11-10 19:36:52'),
(19, 7, 'Ice Big Apple', 12.00, 'assets/uploads/ice-kislla-big-apple-1780915250.jpg', 1, '2026-11-10 19:36:52');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `perfil` enum('admin','atendente') NOT NULL DEFAULT 'atendente',
  `status_conta` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha_hash`, `perfil`, `status_conta`, `criado_em`) VALUES
(1, 'Administrador', 'admin@a3.local', '$2y$10$xac2TOJEFL5AOVStYhUOJelIbKH.3PYnKQW7IztkP/RyOWT6L6j1i', 'admin', 'ativo', '2026-11-10 19:36:52'),
(2, 'Ana Atendente', 'ana@a3.local', '$2y$10$xac2TOJEFL5AOVStYhUOJelIbKH.3PYnKQW7IztkP/RyOWT6L6j1i', 'atendente', 'ativo', '2026-11-10 19:36:52'),
(3, 'Pedro Atendente', 'pedro@a3.local', '$2y$10$xac2TOJEFL5AOVStYhUOJelIbKH.3PYnKQW7IztkP/RyOWT6L6j1i', 'atendente', 'ativo', '2026-11-10 19:36:52'),
(4, 'Lucas Desligado', 'lucas@a3.local', '$2y$10$xac2TOJEFL5AOVStYhUOJelIbKH.3PYnKQW7IztkP/RyOWT6L6j1i', 'atendente', 'inativo', '2026-11-10 19:36:52');

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_resumo_comandas`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_resumo_comandas` (
`comanda_id` int(10) unsigned
,`status` enum('aberta','fechada','fiado','cancelada')
,`aberta_em` datetime
,`total` decimal(42,2)
,`pago` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Estrutura para view `vw_resumo_comandas`
--
DROP TABLE IF EXISTS `vw_resumo_comandas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_resumo_comandas`  AS SELECT `c`.`id` AS `comanda_id`, `c`.`status` AS `status`, `c`.`aberta_em` AS `aberta_em`, coalesce((select sum(`i`.`quantidade` * `i`.`valor_unitario`) from `itens_comanda` `i` where `i`.`comanda_id` = `c`.`id`),0) AS `total`, coalesce((select sum(`p`.`valor`) from `pagamentos` `p` where `p`.`comanda_id` = `c`.`id`),0) AS `pago` FROM `comandas` AS `c` ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categorias_nome` (`nome`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `comandas`
--
ALTER TABLE `comandas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comandas_cliente` (`cliente_id`),
  ADD KEY `fk_comandas_usuario` (`usuario_id`),
  ADD KEY `idx_comandas_status_data` (`status`,`aberta_em`);

--
-- Índices de tabela `itens_comanda`
--
ALTER TABLE `itens_comanda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_itens_comanda` (`comanda_id`),
  ADD KEY `fk_itens_produto` (`produto_id`);

--
-- Índices de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pagamentos_comanda` (`comanda_id`),
  ADD KEY `fk_pagamentos_usuario` (`usuario_id`),
  ADD KEY `idx_pagamentos_pago_em` (`pago_em`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_produtos_categoria` (`categoria_id`),
  ADD KEY `idx_produtos_nome` (`nome`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuarios_email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `comandas`
--
ALTER TABLE `comandas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `itens_comanda`
--
ALTER TABLE `itens_comanda`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `comandas`
--
ALTER TABLE `comandas`
  ADD CONSTRAINT `fk_comandas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_comandas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `itens_comanda`
--
ALTER TABLE `itens_comanda`
  ADD CONSTRAINT `fk_itens_comanda` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_itens_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD CONSTRAINT `fk_pagamentos_comanda` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagamentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `fk_produtos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
