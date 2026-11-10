# Projeto A3 — Pendências e acessos (para a equipe)

**Entrega:** 28/11/2026 às 23:59 (Classroom; só **um** integrante envia) · **Apresentação:** 01/12/2026 (15 a 20 min)
**Repositório:** branch `a3-finalizacao` · **Resumo técnico:** `dev/README_A3.md`

---

## 1. Como rodar o projeto (XAMPP, Windows)

1. Abra o XAMPP e inicie **Apache** e **MySQL**.
2. Importe o banco: phpMyAdmin → *Importar* → `dev/db/sistema_gestao_a3.sql`.
   (Pode importar de novo a qualquer momento: volta tudo ao estado inicial.)
3. Coloque a pasta do projeto em `C:\xampp\htdocs\gestao-comandas-a3`.
4. Abra os endereços abaixo.

| O quê | Endereço |
|---|---|
| Protótipo (interface) | http://localhost/gestao-comandas-a3/dev/prototipo/ |
| API (documentação em JSON) | http://localhost/gestao-comandas-a3/dev/api/v1/ |
| phpMyAdmin | http://localhost/phpmyadmin |

## 2. Logins

Senha de **todos** os usuários: `Senha@123`

| Usuário | E-mail | Perfil | Observação |
|---|---|---|---|
| Administrador | `admin@a3.local` | admin | acessa tudo, inclusive a aba "usuarios" |
| Ana Atendente | `ana@a3.local` | atendente | não vê "usuarios"; não cria/edita produtos |
| Pedro Atendente | `pedro@a3.local` | atendente | idem |
| Lucas Desligado | `lucas@a3.local` | atendente **inativo** | o login é recusado de propósito (para testar) |

Banco de dados (XAMPP padrão): usuário `root`, **sem senha** (configurado em `dev/api/v1/config.php`).

## 3. O que já está pronto

- Banco `sistema_gestao_a3`: DDL, DML e DQL (12 consultas), view e 3 procedures com transação.
- API REST com GET/POST/PUT/DELETE nas 7 tabelas, JWT, permissões e 7 relatórios.
- Protótipo: login, CRUD, comandas (itens, pagamento dividido, fiado, cancelar), relatórios, responsivo.
- Testes: `C:\xampp\php\php.exe dev\tests\teste_api.php` → 74 verificações, 0 falhas.
- Rascunhos em `docs/`: relatório técnico, roteiro da apresentação, perguntas da banca.
- Collection do Postman (opcional): `dev/postman/`.

## 4. O que FALTA (checklist)

Preencha o responsável e a data de cada item.

| # | Tarefa | Responsável | Prazo | Feito |
|---|---|---|---|---|
| 1 | **Gerar os PNGs dos DERs.** Abrir `dev/db/DER_conceitual.mmd` e `DER_logico.mmd` em https://mermaid.live, exportar PNG e substituir os arquivos de `dev/db/` (os atuais estão desatualizados). | | | ☐ |
| 2 | **Relatório técnico final.** Reescrever `docs/relatorio_tecnico.md` com as palavras do grupo, preencher os trechos **[AJUSTAR]** (nomes, RA, professor, referências ABNT), inserir os DERs e exportar para Word/PDF. | | | ☐ |
| 3 | **PowerPoint.** Montar a partir de `docs/apresentacao_roteiro.md` (poucos textos por slide). O arquivo apresentado em 01/12 deve ser **o mesmo** enviado em 28/11. | | | ☐ |
| 4 | **Ensaiar a demonstração** (roteiro no fim de `docs/apresentacao_roteiro.md`) no computador que será usado na apresentação, e uma vez num celular. | | | ☐ |
| 5 | **Estudar as perguntas e os 6 exercícios** de `docs/perguntas_banca.md`. A banca pode pedir alteração de código ao vivo. Todos devem saber explicar o projeto. | todos | | ☐ |
| 6 | **Testar a collection do Postman** no Runner (opcional; se der problema, tirar da entrega). | | | ☐ |
| 7 | **Fazer o merge** da branch `a3-finalizacao` na `master` (ou entregar o zip dessa branch). | | | ☐ |
| 8 | **Montar o pacote de entrega** e enviar no Classroom (um integrante): ver lista abaixo. | | 28/11 | ☐ |

### Pacote de entrega

- [ ] Relatório técnico (Word/PDF) com os DERs conceitual e lógico
- [ ] `dev/db/sistema_gestao_a3.sql` (DDL + DML + DQL executáveis)
- [ ] Código-fonte (zip do repositório: `dev/api`, `dev/prototipo`, `dev/db`, `dev/tests`, `dev/assets`, `README_A3.md`)
- [ ] Apresentação em PowerPoint
- [ ] Collection do Postman (`dev/postman/`) — opcional

## 5. Regras do enunciado que vale lembrar

- Trabalho integralmente feito por IA é **zerado**: os textos de `docs/` são **rascunhos**. Reescrevam, conferindo cada afirmação no código.
- A API é opcional (aviso do Prof. Flávio, 04/11), mas o projeto tem API **e** procedures com transação; o professor confirmou que pode usar juntas.
- A banca faz perguntas e pode pedir mudança no código durante a apresentação.

## 6. Em caso de problema na hora

| Sintoma | O que fazer |
|---|---|
| Página não abre ou a API não responde | Conferir se Apache e MySQL estão ligados no XAMPP |
| Login recusado | Reimportar o `.sql`; usar a senha `Senha@123`; `lucas@a3.local` é inativo de propósito |
| Dados bagunçados após a demonstração | Reimportar `dev/db/sistema_gestao_a3.sql` |
| Tela mostrando algo estranho | Recarregar a página (Ctrl+F5) |
| Rotas da API dão 404 | Usar `.../api/v1/index.php/produtos` (sem o `mod_rewrite`) |
