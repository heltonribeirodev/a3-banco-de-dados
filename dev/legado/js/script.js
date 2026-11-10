let metodoPagamentoSelecionado = '';

document.addEventListener('DOMContentLoaded', () => {

    // --- LOGIN ---
    const formLogin = document.getElementById('form-login');
    if (formLogin) {
        formLogin.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = document.getElementById('btn-entrar');
            btn.innerText = "Entrando...";
            btn.disabled = true;

            fetch('api/login.php', { method: 'POST', body: new FormData(this) })
                .then(res => {
                    if (!res.ok) throw new Error("Erro na rede: " + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data.status === 'sucesso') {
                        window.location.href = 'home.php';
                    } else {
                        const divAlerta = document.getElementById('mensagem-alerta');
                        if (divAlerta) {
                            divAlerta.innerText = data.mensagem;
                            divAlerta.classList.remove('alerta-oculto');
                            divAlerta.classList.add('alerta-ativo');
                            setTimeout(() => {
                                divAlerta.classList.remove('alerta-ativo');
                                divAlerta.classList.add('alerta-oculto');
                            }, 5000);
                        }
                    }
                })
                .catch(err => {
                    console.error("Erro:", err);
                    alert("Erro ao conectar no servidor.");
                })
                .finally(() => {
                    btn.innerText = "Entrar";
                    btn.disabled = false;
                });
        });
    }

    // --- BOTÃO ABRIR NOVA COMANDA ---
    const btnNovaComanda = document.querySelector('.btn-nova-comanda');
    if (btnNovaComanda) {
        btnNovaComanda.addEventListener('click', (e) => {
            e.preventDefault();
            abrirModal();
        });
    }

    const botoesFiltro = document.querySelectorAll('.status .btn-filtro');
    const inputBuscarComanda = document.getElementById('buscar-comanda');
    const inputBuscarProduto = document.getElementById('buscar-produto');
    const botoesPesquisar = document.querySelectorAll('.btn-pesquisar');

    function atualizarVisualizacao() {
        const botaoAtivo = document.querySelector('.status .btn-filtro.active');
        const filtroAtual = botaoAtivo ? (botaoAtivo.getAttribute('data-filter') || '').trim().toLowerCase() : 'todos';

        const listaComandas = document.querySelectorAll('.cartao-comanda');
        if (listaComandas.length > 0) {
            const termoComanda = inputBuscarComanda ? inputBuscarComanda.value.toLowerCase() : '';

            let countAbertas = 0;
            let countFiado = 0;
            let countFechadas = 0;

            listaComandas.forEach(comanda => {
                const statusComanda = (comanda.getAttribute('data-status') || '').trim().toLowerCase();
                const textoCartao = comanda.textContent.toLowerCase();

                const matchAba = (filtroAtual === statusComanda);
                const matchBusca = (termoComanda === '' || textoCartao.includes(termoComanda));

                if (matchBusca) {
                    if (statusComanda === 'aberta') countAbertas++;
                    else if (statusComanda === 'fiado') countFiado++;
                    else if (statusComanda === 'fechada') countFechadas++;
                }

                comanda.style.display = (matchAba && matchBusca) ? 'block' : 'none';
            });

            botoesFiltro.forEach(botao => {
                const filtroBotao = botao.getAttribute('data-filter');
                if (filtroBotao === 'aberta') {
                    botao.innerHTML = `<span class="status-aberta"></span> Abertas (${countAbertas})`;
                } else if (filtroBotao === 'fiado') {
                    botao.innerHTML = `<span class="status-pendente"></span> Fiado (${countFiado})`;
                } else if (filtroBotao === 'fechada') {
                    botao.innerHTML = `<span class="status-fechada"></span> Fechadas (${countFechadas})`;
                }
            });
        }

        //  PAGINA PRODUTOS
        const listaProdutos = document.querySelectorAll('.card-produto');
        if (listaProdutos.length > 0) {
            const termoProduto = inputBuscarProduto ? inputBuscarProduto.value.toLowerCase() : '';

            listaProdutos.forEach(produto => {
                const categoriaProduto = (produto.getAttribute('data-categoria') || '').trim().toLowerCase();
                const textoProduto = produto.textContent.toLowerCase();

                const matchAba = (filtroAtual === 'todos' || filtroAtual === categoriaProduto);
                const matchBusca = (termoProduto === '' || textoProduto.includes(termoProduto));

                produto.style.display = (matchAba && matchBusca) ? 'block' : 'none';
            });

            botoesFiltro.forEach(botao => {
                const categoriaOriginal = botao.getAttribute('data-filter') || '';
                const categoriaNormalizada = categoriaOriginal.trim().toLowerCase();

                if (categoriaNormalizada === 'todos') {
                    const visiveis = Array.from(listaProdutos).filter(c => {
                        return termoProduto === '' || c.textContent.toLowerCase().includes(termoProduto);
                    }).length;
                    botao.innerHTML = `Todos (${visiveis})`;
                } else {
                    const visiveis = Array.from(listaProdutos).filter(c => {
                        const matchB = termoProduto === '' || c.textContent.toLowerCase().includes(termoProduto);
                        const cat = (c.getAttribute('data-categoria') || '').trim().toLowerCase();
                        return matchB && cat === categoriaNormalizada;
                    }).length;
                    botao.innerHTML = `${categoriaOriginal} (${visiveis})`;
                }
            });
        }
    }

    if (botoesFiltro.length > 0) {
        botoesFiltro.forEach(botao => {
            botao.addEventListener('click', () => {
                botoesFiltro.forEach(b => b.classList.remove('active'));
                botao.classList.add('active');
                atualizarVisualizacao();
            });
        });
        atualizarVisualizacao();
    }

    if (inputBuscarComanda) {
        inputBuscarComanda.addEventListener('input', atualizarVisualizacao);
    }

    if (inputBuscarProduto) {
        inputBuscarProduto.addEventListener('input', atualizarVisualizacao);
    }

    if (botoesPesquisar.length > 0) {
        botoesPesquisar.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                atualizarVisualizacao();
            });
        });
    }

    const btnConfirmarExclusao = document.getElementById('btn-confirmar-exclusao');
    if (btnConfirmarExclusao) {
        btnConfirmarExclusao.addEventListener('click', () => {
            if (idParaExcluir) {
                window.location.href = 'api/acoes_produto.php?acao=excluir&id=' + idParaExcluir;
            }
        });
    }

    // Fecha o menu hambúrguer ao clicar em qualquer link do nav
    document.querySelectorAll('#nav-menu a').forEach(link => {
        link.addEventListener('click', () => {
            const nav = document.getElementById('nav-menu');
            const btn = document.querySelector('.btn-hamburguer');
            if (nav) nav.classList.remove('aberto');
            if (btn) btn.classList.remove('aberto');
        });
    });
});

// MODAL NOVA COMANDA
function abrirModal() {
    const modal = document.getElementById('modal-comanda');
    if (modal) modal.style.display = 'flex';
}

function fecharModal() {
    const modal = document.getElementById('modal-comanda');
    if (modal) modal.style.display = 'none';

    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');
    const erroCliente = document.getElementById('erro-cliente');
    const formNovaComanda = document.getElementById('form-nova-comanda');

    if (step1) step1.style.display = 'block';
    if (step2) step2.style.display = 'none';
    if (erroCliente) erroCliente.style.display = 'none';
    if (formNovaComanda) formNovaComanda.reset();
}

function proximoPasso() {
    const inputNome = document.getElementById('cliente');
    const erroDiv = document.getElementById('erro-cliente');

    if (inputNome.value.trim() === "") {
        erroDiv.innerText = "Por favor, preencha o nome do cliente.";
        erroDiv.style.display = "block";
        inputNome.focus();
        return;
    }

    erroDiv.style.display = "none";
    document.getElementById('step-1').style.display = 'none';
    document.getElementById('step-2').style.display = 'flex';
}

function voltarPasso() {
    document.getElementById('erro-cliente').style.display = 'none';
    document.getElementById('step-2').style.display = 'none';
    document.getElementById('step-1').style.display = 'block';
}

function ajustarQtd(id, delta) {
    let input = document.getElementById(id)
        || document.getElementById('qtd-' + id)
        || document.getElementById('edit-' + id);

    if (input) {
        let novoValor = (parseInt(input.value) || 0) + delta;
        if (novoValor >= 0) input.value = novoValor;
    } else {
        console.error("Input não encontrado para o ID: " + id);
    }
}

function filtrarProdutos(categoria) { // FILTRAR PRODUTOS DENTRO DA COMANDA
    const containerModal = document.getElementById('modal-comanda');
    if (!containerModal) return;

    containerModal.querySelectorAll('.card-produto-sel').forEach(card => {
        const categoriaCard = (card.getAttribute('data-categoria') || '').trim().toLowerCase();
        const catFiltro = categoria.trim().toLowerCase();
        card.style.display = (catFiltro === 'todos' || categoriaCard === catFiltro) ? 'flex' : 'none';
    });

    containerModal.querySelectorAll('.btn-filtro-modal').forEach(btn => {
        btn.classList.remove('active');
        if (btn.innerText.trim().toLowerCase() === categoria.trim().toLowerCase() ||
            (categoria === 'todos' && btn.innerText.trim().toLowerCase() === 'todos')) {
            btn.classList.add('active');
        }
    });
}

// MODAL NOVO PRODUTO
function abrirModalProduto() {
    const modal = document.getElementById('modal-novo-produto');
    if (modal) {
        modal.style.display = 'flex';
        const h2 = modal.querySelector('h2');
        if (h2) h2.innerText = 'Novo Produto';
        const form = modal.querySelector('form');
        if (form) {
            form.action = 'api/salvar_produto.php';
            form.reset();
        }
    }
}

function fecharModalProduto() {
    const modal = document.getElementById('modal-novo-produto');
    if (modal) modal.style.display = 'none';
}

let idParaExcluir = null;
function excluirProduto(id) {
    idParaExcluir = id;
    const modal = document.getElementById('modal-confirmacao');
    if (modal) modal.style.display = 'flex';
}

function fecharConfirmacao() {
    const modal = document.getElementById('modal-confirmacao');
    if (modal) modal.style.display = 'none';
}

function editarProduto(id) {
    fetch(`api/get_produtos.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            const modal = document.getElementById('modal-novo-produto');
            const form = modal.querySelector('form');

            modal.querySelector('h2').innerText = 'Editar Produto';
            form.action = 'api/salvar_produto.php';

            const inputId = form.querySelector('input[name="id"]');
            if (inputId) inputId.value = id;

            form.querySelector('input[name="nome"]').value = data.nome;
            form.querySelector('input[name="valor"]').value = data.valor;
            form.querySelector('input[name="categoria"]').value = data.categoria;

            modal.style.display = 'flex';
        });
}

// AÇÕES DENTRO DA COMANDA
function alternarPagamento(mostrarPagamento) {
    const vis = document.getElementById('visualizacao-conta');
    const menu = document.getElementById('menu-pagamento');
    if (vis) vis.style.display = mostrarPagamento ? 'none' : 'block';
    if (menu) menu.style.display = mostrarPagamento ? 'block' : 'none';
}

function abrirAcoes(id, cliente, status, metodoPagamento = '') {
    document.getElementById('num-comanda').innerText = id;
    document.getElementById('nome-cliente').innerText = cliente;

    alternarPagamento(false);

    const btnEditar = document.getElementById('btn-editar-produtos'); // SE SITUAÇÃO = FECHADA, BLOQUEIA EDIÇÃO DE ITENS
    if (btnEditar) {
        if (status === 'fechada') {
            btnEditar.disabled = true;
            btnEditar.style.backgroundColor = '#adb5bd';
            btnEditar.style.cursor = 'not-allowed';
            btnEditar.title = 'Não é possível editar uma comanda fechada';
        } else {
            btnEditar.disabled = false;
            btnEditar.style.backgroundColor = '#28a745';
            btnEditar.style.cursor = 'pointer';
            btnEditar.title = '';
        }
    }

    const podePagar = (status === 'aberta' || status === 'fiado');
    const botoes = document.querySelectorAll('#menu-pagamento .btn-pagar');

    botoes.forEach(btn => {
        btn.disabled = !podePagar;

        btn.style.backgroundColor = '';
        btn.style.color = '';
        btn.style.borderColor = '';

        if (metodoPagamento !== '' && btn.getAttribute('data-metodo') === metodoPagamento) { // DESTACA EM VERDE QUAL FOI O METÓDO DE PAGAMENTO REALIZADO
            btn.style.backgroundColor = '#28a745';
            btn.style.color = '#ffffff';
            btn.style.borderColor = '#28a745';
        }
    });

    fetch('api/get_comanda.php?id=' + id) // CARDS DE PRODUTOS AO VISUALIZAR A COMANDA
        .then(res => res.json())
        .then(data => {
            let totalGeral = 0;
            let html = '<div class="itens-comanda-lista">';

            if (!data.itens || data.itens.length === 0) {
                html += '<p style="text-align:center; color:#aaa; padding: 16px 0;">Nenhum item nesta comanda.</p>';
            } else {
                data.itens.forEach(item => {
                    // data_adicao vem do alias definido no get_comanda.php
                    let dataFormatada = '—';
                    const dataRaw = item.data_adicao || item.data_criacao || null;
                    if (dataRaw) {
                        dataFormatada = new Date(dataRaw.replace(' ', 'T')).toLocaleDateString('pt-BR');
                    }

                    const qtd   = item.quantidade ?? 0;
                    const valor = parseFloat(item.valor_unitario ?? 0);
                    const total = qtd * valor;
                    totalGeral += total;

                    html += `
                    <div class="item-comanda-card">
                        <div class="item-comanda-info">
                            <span class="item-comanda-nome">${item.nome_produto ?? 'Sem nome'}</span>
                            <span class="item-comanda-data">${dataFormatada}</span>
                        </div>
                        <div class="item-comanda-valores">
                            <span class="item-comanda-qtd">${qtd}x</span>
                            <span class="item-comanda-preco">R$ ${valor.toFixed(2).replace('.', ',')}</span>
                            <span class="item-comanda-total">R$ ${total.toFixed(2).replace('.', ',')}</span>
                        </div>
                    </div>`;
                });
            }

            html += '</div>';
            html += `<div class="item-comanda-total-geral">
                        <span>Total</span>
                        <strong>R$ ${totalGeral.toFixed(2).replace('.', ',')}</strong>
                     </div>`;

            const lista = document.getElementById('lista-itens-edit');
            if (lista) lista.innerHTML = html;

            const modal = document.getElementById('modal-acoes');
            if (modal) modal.style.display = 'flex';
        })
        .catch(err => console.error("Erro ao carregar comanda:", err));
}

function fecharModalAcoes() {
    const modal = document.getElementById('modal-acoes');
    if (modal) modal.style.display = 'none';
}

// PROCESSAMENTO DE PAGAMENTO
function prepararPagamento(metodo) {
    metodoPagamentoSelecionado = metodo;
    const el = document.getElementById('metodo-selecionado');
    if (el) el.innerText = metodo.replace('_', ' ').toUpperCase();

    const modal = document.getElementById('modal-confirmacao-pagamento');
    if (modal) modal.style.display = 'flex';
}

function executarPagamento() {
    const comandaId = document.getElementById('num-comanda').innerText;

    if (metodoPagamentoSelecionado === '') {
        alert("Erro: Forma de pagamento não selecionada.");
        return;
    }

    fetch('api/processar_pagamento.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            comanda_id: comandaId,
            metodo: metodoPagamentoSelecionado
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'sucesso') {
                location.reload();
            } else {
                alert("Erro: " + (data.mensagem || "Falha no processamento"));
            }
        })
        .catch(err => {
            console.error("Erro no fetch:", err);
            alert("Erro ao conectar no servidor. Veja o console (F12).");
        });
}

// MODAL PARA EDITAR ITENS NA COMANDA
window.abrirModalEdicao = function (id) {
    const modal = document.getElementById('modal-editar-itens');
    const grid = document.getElementById('grid-itens-edicao');
    const filtros = document.getElementById('filtros-edicao');
    const editId = document.getElementById('edit-num-comanda');
    const editComandaId = document.getElementById('edit-comanda-id');

    if (!modal) return;

    if (editId) editId.innerText = id;
    if (editComandaId) editComandaId.value = id;

    grid.innerHTML = '<p style="text-align:center; color:#888;">Carregando produtos...</p>';
    filtros.innerHTML = '';

    Promise.all([
        fetch('api/get_produtos.php').then(r => r.json()),
        fetch('api/get_comanda.php?id=' + id).then(r => r.json())
    ])
        .then(([dadosProdutos, dadosComanda]) => {
            const produtos = dadosProdutos.produtos || [];
            const itens = dadosComanda.itens || [];

            // Soma quantidades de todas as linhas do mesmo produto
            // (um produto pode ter múltiplas linhas com datas diferentes)
            const qtdSalva = {};
            itens.forEach(item => {
                const pid = item.produto_id;
                qtdSalva[pid] = (qtdSalva[pid] || 0) + (item.quantidade || 0);
            });

            const categorias = [...new Set(produtos.map(p => p.categoria).filter(Boolean))];

            let htmlFiltros = `<button type="button" class="btn-filtro-modal active" onclick="filtrarProdutosEdicao('todos')">Todos</button>`;
            categorias.forEach(cat => {
                const catEscapada = cat.replace(/'/g, "\\'");
                htmlFiltros += `<button type="button" class="btn-filtro-modal" onclick="filtrarProdutosEdicao('${catEscapada}')">${cat}</button>`;
            });
            filtros.innerHTML = htmlFiltros;

            let htmlGrid = '';
            produtos.forEach(p => {
                const qtdAtual = qtdSalva[p.id] || 0;
                htmlGrid += `
                <div class="card-produto-sel" data-categoria="${p.categoria ?? ''}">
                    <img src="${p.imagem ?? ''}" alt="Produto">
                    <p class="nome-produto">${p.nome}</p>
                    <p class="valor-produto">R$ ${parseFloat(p.valor).toFixed(2).replace('.', ',')}</p>
                    <div class="controles-qtd">
                        <button type="button" class="btn-qtd" onclick="ajustarQtd('edit-${p.id}', -1)">-</button>
                        <input type="number" name="qtd-${p.id}" id="edit-${p.id}" value="${qtdAtual}" min="0">
                        <button type="button" class="btn-qtd" onclick="ajustarQtd('edit-${p.id}', 1)">+</button>
                    </div>
                </div>`;
            });
            grid.innerHTML = htmlGrid;

            modal.style.display = 'flex';
        })
        .catch(err => {
            console.error('Erro ao carregar modal de edição:', err);
            grid.innerHTML = '<p style="color:red;">Erro ao carregar produtos.</p>';
        });
};

// FILTRO DE CATEGORIAS DENTRO DO MODAL DE EDIÇÃO
function filtrarProdutosEdicao(categoria) {
    const modal = document.getElementById('modal-editar-itens');
    if (!modal) return;

    modal.querySelectorAll('.card-produto-sel').forEach(card => {
        const cat = (card.getAttribute('data-categoria') || '').trim().toLowerCase();
        const filtro = categoria.trim().toLowerCase();
        card.style.display = (filtro === 'todos' || cat === filtro) ? 'flex' : 'none';
    });

    modal.querySelectorAll('.btn-filtro-modal').forEach(btn => {
        btn.classList.toggle('active',
            btn.innerText.trim().toLowerCase() === categoria.trim().toLowerCase() ||
            (categoria === 'todos' && btn.innerText.trim().toLowerCase() === 'todos')
        );
    });
}

window.fecharModalEdicao = function () {
    const modal = document.getElementById('modal-editar-itens');
    if (modal) modal.style.display = 'none';
};

// MODAL ADICIONAR ITENS
window.abrirSelecaoAdicao = function () {
    const modal = document.getElementById('modal-adicionar-itens');
    if (modal) modal.style.display = 'flex';
};

window.fecharAdicao = function () {
    const modal = document.getElementById('modal-adicionar-itens');
    if (modal) modal.style.display = 'none';
};




// ==========================================
// PAINEL ADMINISTRATIVO — admin.js
// ==========================================

let usuarioIdParaExcluir = null;

// Abre o modal para criar um novo usuário
function abrirModalNovoUsuario() {
    document.getElementById('modal-usuario-titulo').innerText = 'Novo Usuário';
    document.getElementById('usuario-id').value       = '';
    document.getElementById('usuario-nome').value     = '';
    document.getElementById('usuario-email').value    = '';
    document.getElementById('usuario-senha').value    = '';
    document.getElementById('usuario-perfil').value   = 'atendente';
    document.getElementById('usuario-status').value   = 'ativo';
    document.getElementById('label-senha-hint').style.display = 'none';
    document.getElementById('modal-usuario').style.display    = 'flex';
}

// Abre o modal pré-preenchido para editar um usuário existente
function abrirModalEditarUsuario(id, nome, email, perfil, status) {
    document.getElementById('modal-usuario-titulo').innerText = 'Editar Usuário';
    document.getElementById('usuario-id').value       = id;
    document.getElementById('usuario-nome').value     = nome;
    document.getElementById('usuario-email').value    = email;
    document.getElementById('usuario-senha').value    = '';
    document.getElementById('usuario-perfil').value   = perfil  || 'atendente';
    document.getElementById('usuario-status').value   = status  || 'ativo';
    document.getElementById('label-senha-hint').style.display = 'inline';
    document.getElementById('modal-usuario').style.display    = 'flex';
}

// Fecha o modal de criar/editar usuário
function fecharModalUsuario() {
    document.getElementById('modal-usuario').style.display = 'none';
}

// Abre o modal de confirmação de exclusão
function confirmarExclusaoUsuario(id, nome) {
    usuarioIdParaExcluir = id;
    document.getElementById('nome-usuario-excluir').innerText = nome;
    document.getElementById('modal-confirmar-exclusao-usuario').style.display = 'flex';
}

// Fecha o modal de confirmação de exclusão
function fecharModalConfirmarExclusao() {
    document.getElementById('modal-confirmar-exclusao-usuario').style.display = 'none';
}

// Executa a exclusão redirecionando para a API
function executarExclusaoUsuario() {
    if (!usuarioIdParaExcluir) return;
    window.location.href = 'api/excluir_usuario.php?id=' + usuarioIdParaExcluir;
}

// ==========================================
// MENU HAMBÚRGUER — MOBILE
// ==========================================
function toggleMenu() {
    const nav = document.getElementById('nav-menu');
    const btn = document.querySelector('.btn-hamburguer');
    if (!nav || !btn) return;
    nav.classList.toggle('aberto');
    btn.classList.toggle('aberto');
}

// Fecha o menu ao clicar em qualquer link — inicializado no DOMContentLoaded principal acima