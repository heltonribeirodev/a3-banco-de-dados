-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 30/10/2026 às 23:18
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
-- Banco de dados: `sistema_gestao`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `comandas`
--

CREATE TABLE `comandas` (
  `id` int(11) NOT NULL,
  `cliente` varchar(100) NOT NULL,
  `status` varchar(20) DEFAULT 'aberta',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `metodo_pagamento` varchar(50) DEFAULT NULL,
  `data_pagamento` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `comandas`
--

INSERT INTO `comandas` (`id`, `cliente`, `status`, `data_criacao`, `metodo_pagamento`, `data_pagamento`) VALUES
(6, 'Helton Ribeiro', 'fechada', '2026-06-07 14:54:35', 'cartao_credito', '2026-06-07 12:05:24'),
(7, 'Evelin', 'fechada', '2026-06-07 15:07:59', 'cartao_debito', '2026-06-07 12:08:10'),
(8, 'Matheus', 'fechada', '2026-06-07 15:09:05', 'dinheiro', '2026-06-07 12:09:10'),
(9, 'Helton Ribeiro', 'fechada', '2026-06-07 15:13:52', 'dinheiro', '2026-06-07 12:35:26'),
(10, 'Gustavo', 'fechada', '2026-06-07 15:14:27', 'pix', '2026-06-07 12:34:27'),
(11, 'Claúdio', 'fechada', '2026-06-07 15:36:32', 'dinheiro', '2026-06-08 08:27:27'),
(12, 'evelin lindona', 'fechada', '2026-06-07 23:11:55', 'pix', '2026-06-08 07:49:26'),
(13, 'xx', 'fechada', '2026-06-07 23:14:13', 'dinheiro', '2026-06-08 07:23:25'),
(14, 'Agnaldo', 'fechada', '2026-06-08 11:27:12', 'cartao_credito', '2026-06-08 08:27:20'),
(15, 'Gabriel', 'fechada', '2026-06-08 11:39:40', 'cartao_credito', '2026-06-08 08:40:25'),
(16, 'Davi', 'fechada', '2026-06-08 11:40:10', 'cartao_debito', '2026-06-08 08:40:17'),
(17, 'Robson Martelo', 'fechada', '2026-06-08 16:16:05', 'pix', '2026-06-08 20:13:05'),
(18, 'Juliano', 'fiado', '2026-06-08 16:36:48', 'fiado', '2026-06-09 08:02:49'),
(19, 'Anderson', 'fechada', '2026-06-08 22:11:17', 'cartao_credito', '2026-06-08 19:11:35'),
(20, 'Helton Ribeiro', 'fechada', '2026-06-09 10:54:19', 'cartao_debito', '2026-06-12 07:31:25'),
(21, 'teste', 'aberta', '2026-06-12 10:31:43', NULL, NULL),
(22, 'Antedegmon', 'aberta', '2026-06-27 18:20:40', NULL, NULL),
(23, 'Agnaldo', 'aberta', '2026-06-27 18:23:08', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_comanda`
--

CREATE TABLE `itens_comanda` (
  `id` int(11) NOT NULL,
  `comanda_id` int(11) DEFAULT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `quantidade` int(11) NOT NULL,
  `valor_unitario` decimal(10,2) NOT NULL,
  `data_criacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `itens_comanda`
--

INSERT INTO `itens_comanda` (`id`, `comanda_id`, `produto_id`, `quantidade`, `valor_unitario`, `data_criacao`) VALUES
(10, 6, 22, 2, 15.50, '2026-06-07 11:54:35'),
(11, 6, 23, 1, 18.99, '2026-06-07 11:54:35'),
(12, 7, 22, 1, 15.50, '2026-06-07 12:07:59'),
(13, 7, 23, 2, 18.99, '2026-06-07 12:07:59'),
(14, 8, 22, 1, 13.50, '2026-06-07 12:09:05'),
(15, 8, 23, 2, 18.99, '2026-06-07 12:09:05'),
(16, 9, 22, 3, 13.50, '2026-06-07 12:13:52'),
(17, 9, 23, 2, 18.99, '2026-06-07 12:13:52'),
(26, 12, 22, 150, 13.50, '2026-06-07 20:12:07'),
(30, 13, 22, 1, 13.50, '2026-06-08 07:23:18'),
(31, 13, 23, 2, 18.99, '2026-06-08 07:23:18'),
(32, 11, 22, 3, 13.50, '2026-06-08 07:47:43'),
(33, 11, 23, 2, 18.99, '2026-06-08 07:47:43'),
(35, 11, 25, 1, 25.00, '2026-06-08 07:47:43'),
(36, 11, 31, 1, 11.50, '2026-06-08 07:47:43'),
(39, 10, 22, 7, 13.50, '2026-06-08 07:49:48'),
(40, 14, 22, 7, 13.50, '2026-06-08 08:27:12'),
(41, 14, 25, 3, 25.00, '2026-06-08 08:27:12'),
(42, 14, 27, 2, 7.00, '2026-06-08 08:27:12'),
(43, 14, 30, 3, 6.00, '2026-06-08 08:27:12'),
(44, 14, 33, 2, 3.00, '2026-06-08 08:27:12'),
(45, 14, 35, 1, 15.00, '2026-06-08 08:27:12'),
(46, 15, 22, 5, 13.50, '2026-06-08 08:39:40'),
(47, 15, 23, 2, 18.99, '2026-06-08 08:39:40'),
(48, 15, 28, 1, 15.00, '2026-06-08 08:39:40'),
(49, 15, 35, 1, 15.00, '2026-06-08 08:39:40'),
(50, 15, 36, 2, 7.00, '2026-06-08 08:39:40'),
(51, 16, 22, 8, 13.50, '2026-06-08 08:40:10'),
(52, 16, 25, 2, 25.00, '2026-06-08 08:40:10'),
(53, 16, 30, 1, 6.00, '2026-06-08 08:40:10'),
(72, 19, 26, 10, 11.00, '2026-06-08 19:11:17'),
(73, 19, 30, 3, 6.00, '2026-06-08 19:11:17'),
(74, 19, 35, 1, 15.00, '2026-06-08 19:11:17'),
(75, 19, 36, 5, 7.00, '2026-06-08 19:11:17'),
(76, 19, 37, 8, 2.00, '2026-06-08 19:11:17'),
(77, 17, 26, 15, 11.00, '2026-06-08 20:12:54'),
(78, 17, 22, 2, 13.50, '2026-06-08 20:12:54'),
(79, 17, 30, 2, 6.00, '2026-06-08 20:12:54'),
(80, 17, 37, 5, 2.00, '2026-06-08 20:12:54'),
(81, 17, 23, 1, 18.99, '2026-06-08 20:12:54'),
(82, 17, 25, 1, 25.00, '2026-06-08 20:12:54'),
(83, 17, 31, 1, 11.50, '2026-06-08 20:12:54'),
(84, 18, 33, 1, 3.00, '2026-06-09 07:35:03'),
(85, 18, 34, 1, 55.00, '2026-06-09 07:35:03'),
(86, 18, 22, 7, 13.50, '2026-06-09 07:35:03'),
(87, 18, 23, 2, 18.99, '2026-06-09 07:35:03'),
(88, 18, 25, 1, 25.00, '2026-06-09 07:35:03'),
(89, 18, 31, 2, 11.50, '2026-06-09 07:35:03'),
(95, 20, 26, 10, 11.00, '2026-06-12 07:31:21'),
(96, 20, 30, 2, 6.00, '2026-06-12 07:31:21'),
(97, 20, 37, 3, 2.00, '2026-06-12 07:31:21'),
(98, 20, 23, 2, 18.99, '2026-06-12 07:31:21'),
(99, 20, 25, 1, 25.00, '2026-06-12 07:31:21'),
(100, 20, 31, 1, 11.50, '2026-06-12 07:31:21'),
(105, 22, 34, 1, 55.00, '2026-06-27 15:22:13'),
(106, 22, 26, 2, 15.00, '2026-06-27 15:22:13'),
(107, 22, 22, 3, 13.50, '2026-06-27 15:22:13'),
(108, 23, 22, 1, 13.50, '2026-06-27 15:23:08'),
(109, 23, 23, 2, 18.99, '2026-06-27 15:23:08'),
(110, 23, 26, 5, 15.00, '2026-06-27 15:23:08'),
(111, 21, 33, 2, 3.00, '2026-06-27 16:34:54'),
(112, 21, 26, 10, 15.00, '2026-06-27 16:34:54'),
(113, 21, 27, 2, 7.00, '2026-06-27 16:34:54'),
(114, 23, 30, 1, 6.00, '2026-06-27 16:40:06'),
(115, 18, 30, 1, 6.00, '2026-06-27 16:40:32'),
(116, 21, 22, 2, 13.50, '2026-06-29 14:59:10'),
(117, 21, 26, 2, 15.00, '2026-06-29 15:08:43'),
(118, 23, 22, 2, 13.50, '2026-06-29 15:14:03'),
(119, 23, 30, 1, 6.00, '2026-06-29 15:14:03'),
(120, 23, 23, 1, 18.99, '2026-06-29 15:14:03'),
(121, 18, 22, 2, 13.50, '2026-06-29 15:19:15'),
(122, 18, 35, 4, 15.00, '2026-06-29 15:19:40'),
(123, 18, 37, 5, 2.00, '2026-06-29 15:19:40'),
(124, 18, 32, 5, 14.50, '2026-06-29 15:19:40'),
(125, 18, 28, 2, 15.00, '2026-06-29 15:19:40'),
(126, 23, 22, 1, 13.50, '2026-10-06 20:57:22');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `imagem` varchar(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `nome`, `valor`, `categoria`, `data_criacao`, `imagem`) VALUES
(22, 'Brahma Litrão', 13.50, 'Cerveja', '2026-06-06 15:38:48', 'assets/uploads/brahma-litr--o-1780760328.png'),
(23, 'X-Bacon', 18.99, 'Lanches', '2026-06-06 15:54:46', 'assets/uploads/x-bacon-1780761286.png'),
(25, 'Fritas ', 25.00, 'Porções', '2026-06-08 10:28:21', 'assets/uploads/fritas--1780914501.png'),
(26, 'Brahma Garrafa', 15.00, 'Cerveja', '2026-06-08 10:30:18', 'assets/uploads/brahma-garrafa-1780914618.jpg'),
(27, 'Long Neck Sol', 7.00, 'Cerveja', '2026-06-08 10:31:09', 'assets/uploads/long-neck-sol-1780914669.webp'),
(28, 'Doritos', 15.00, 'Salgadinhos', '2026-06-08 10:32:17', 'assets/uploads/doritos-1780914737.jpg'),
(29, 'Cebolitos', 13.00, 'Salgadinhos', '2026-06-08 10:32:59', 'assets/uploads/cebolitos-1780914779.jpg'),
(30, 'KitKat', 6.00, 'Doces', '2026-06-08 10:34:02', 'assets/uploads/kitkat-1780914842.jpg'),
(31, 'Coca-Cola 1L', 11.50, 'Refrigerante', '2026-06-08 10:35:40', 'assets/uploads/coca-cola-1l-1780914940.jpg'),
(32, 'Sprite 2L', 14.50, 'Refrigerante', '2026-06-08 10:36:12', 'assets/uploads/sprite-2l-1780914972.jpg'),
(33, 'Água Mineral 500ml', 3.00, 'Água', '2026-06-08 10:37:29', 'assets/uploads/--gua-mineral-500ml-1780915049.jpg'),
(34, 'Smirnoff 1L', 35.00, 'Bebidas', '2026-06-08 10:38:34', 'assets/uploads/smirnoff-1l-1780915114.jpg'),
(35, 'Monster 473ml', 15.00, 'Energeticos', '2026-06-08 10:40:05', 'assets/uploads/monster-473ml-1780915205.png'),
(36, 'Ice Kislla Big Apple', 7.00, 'Ice', '2026-06-08 10:40:50', 'assets/uploads/ice-kislla-big-apple-1780915250.jpg'),
(37, 'Ficha de Sinuca', 1.00, 'Jogos', '2026-06-08 11:50:45', 'assets/uploads/ficha-de-sinuca-1780919445.webp');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo_perfil` enum('admin','atendente') NOT NULL DEFAULT 'atendente',
  `status_conta` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `tipo_perfil`, `status_conta`, `criado_em`) VALUES
(1, 'Administrador', 'admin@teste.com', '$2y$10$4QQed4/jdQN7FfB5Xny3tODJH16HZch5Tpycqx7l22OLG6PclbVka', 'admin', 'ativo', '2026-06-05 21:59:11');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `comandas`
--
ALTER TABLE `comandas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `itens_comanda`
--
ALTER TABLE `itens_comanda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comanda_id` (`comanda_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `comandas`
--
ALTER TABLE `comandas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de tabela `itens_comanda`
--
ALTER TABLE `itens_comanda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `itens_comanda`
--
ALTER TABLE `itens_comanda`
  ADD CONSTRAINT `itens_comanda_ibfk_1` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itens_comanda_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
