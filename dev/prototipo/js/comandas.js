/**
 * comandas.js — tela dedicada de comandas.
 *
 * Lista com filtros (status, cliente, período) e tela de detalhe onde se
 * adicionam/editam/removem itens, registram pagamentos (inclusive divididos),
 * marca-se como fiado e cancela. As regras de negócio ficam na API; aqui
 * apenas se esconde o que não faz sentido e se mostram os erros com clareza
 * (409 = comanda não permite a ação; 422 = valor inválido, ex.: acima do saldo).
 */
const Comandas = {
  filtros: {},
  pagina: 1,
  meta: null,
  atual: null,          // comanda aberta na tela de detalhe
  itemEditando: null,   // item em edição (null = novo item)

  $(id) { return document.getElementById(id); },

  // -------------------------------------------------------------------------
  // Lista
  // -------------------------------------------------------------------------
  async abrir() {
    this.$('comandasLista').hidden = false;
    this.$('comandaDetalhe').hidden = true;
    this.$('cmdNova').hidden = !App.pode('comandas', 'criar');
    await this.carregarClientesFiltro();
    await this.listar();
  },

  async carregarClientesFiltro() {
    const sel = this.$('fCliente'), escolhido = sel.value;
    try {
      const clientes = await Api.listarTodos('clientes');
      sel.innerHTML = '<option value="">Todos</option>' +
        clientes.map(c => '<option value="' + c.id + '">' + Util.esc(c.nome) + '</option>').join('');
      sel.value = escolhido;
    } catch (e) { /* o erro já aparece no painel de log */ }
  },

  async listar() {
    try {
      const r = await Api.get('/comandas' + Api.consulta({ ...this.filtros, pagina: this.pagina, limite: 10, ordem: 'id', direcao: 'desc' }));
      this.meta = r.meta;
      this.desenharLista(r.dados);
    } catch (e) {
      this.$('cmdTabela').innerHTML = '';
      Util.aviso(this.$('cmdMsg'), 'erro', Api.mensagemErro(e));
    }
  },

  desenharLista(linhas) {
    const m = this.meta;
    this.$('cmdPag').textContent = 'Página ' + m.pagina + ' de ' + Math.max(1, m.paginas) + ' · ' + m.total + ' comanda(s)';
    this.$('cmdAnt').disabled = m.pagina <= 1;
    this.$('cmdProx').disabled = m.pagina >= m.paginas;
    if (!linhas.length) { this.$('cmdTabela').innerHTML = '<p class="suave">Nenhuma comanda encontrada.</p>'; return; }

    const podeExcluir = App.pode('comandas', 'excluir');
    let h = '<table><thead><tr><th>#</th><th>Cliente</th><th>Status</th><th>Aberta em</th><th class="num">Total</th><th class="num">Pago</th><th class="num">Saldo</th><th></th></tr></thead><tbody>';
    linhas.forEach(c => {
      h += '<tr><td>' + c.id + '</td><td>' + Util.esc(c.cliente_nome) + '</td>' +
        '<td>' + this.badge(c.status) + '</td><td>' + Util.dataHora(c.aberta_em) + '</td>' +
        '<td class="num">' + Util.moeda(c.total) + '</td><td class="num">' + Util.moeda(c.pago) + '</td>' +
        '<td class="num">' + Util.moeda(c.saldo) + '</td>' +
        '<td class="acoes"><button class="peq pri" data-a="ver" data-id="' + c.id + '">Detalhes</button>' +
        (podeExcluir ? '<button class="peq perigo" data-a="excluir" data-id="' + c.id + '">Excluir</button>' : '') + '</td></tr>';
    });
    this.$('cmdTabela').innerHTML = h + '</tbody></table>';
  },

  badge(status) { return '<span class="badge ' + Util.esc(status) + '">' + Util.esc(status) + '</span>'; },

  async excluirComanda(id) {
    if (!confirm('Excluir a comanda #' + id + ' e todos os seus itens?')) return;
    try {
      await Api.delete('/comandas/' + id);
      Util.aviso(this.$('cmdMsg'), 'ok', 'Comanda excluída.');
      await this.listar();
    } catch (e) { Util.aviso(this.$('cmdMsg'), 'erro', Api.mensagemErro(e)); }   // 409 se houver pagamentos
  },

  // -------------------------------------------------------------------------
  // Abrir comanda nova
  // -------------------------------------------------------------------------
  async abrirDialogNova() {
    this.$('formComandaMsg').innerHTML = '';
    try {
      const clientes = await Api.listarTodos('clientes');
      this.$('novoCliente').innerHTML = '<option value="">Selecione…</option>' +
        clientes.map(c => '<option value="' + c.id + '">' + Util.esc(c.nome) + '</option>').join('');
    } catch (e) { Util.aviso(this.$('cmdMsg'), 'erro', Api.mensagemErro(e)); return; }
    this.$('dlgComanda').showModal();
  },

  async criarComanda() {
    const cliente = this.$('novoCliente').value;
    if (!cliente) { Util.aviso(this.$('formComandaMsg'), 'erro', 'Selecione o cliente.'); return; }
    try {
      const r = await Api.post('/comandas', { cliente_id: Number(cliente) });
      this.$('dlgComanda').close();
      await this.verDetalhe(r.dados.id, 'Comanda aberta com sucesso.');
    } catch (e) { Util.aviso(this.$('formComandaMsg'), 'erro', Api.mensagemErro(e)); }
  },

  // -------------------------------------------------------------------------
  // Detalhe
  // -------------------------------------------------------------------------
  async verDetalhe(id, mensagemOk) {
    try {
      const r = await Api.get('/comandas/' + id);
      this.atual = r.dados;
      this.$('comandasLista').hidden = true;
      this.$('comandaDetalhe').hidden = false;
      this.desenharDetalhe();
      if (mensagemOk) Util.aviso(this.$('detMsg'), 'ok', mensagemOk);
    } catch (e) { Util.aviso(this.$('cmdMsg'), 'erro', Api.mensagemErro(e)); }
  },

  desenharDetalhe() {
    const c = this.atual;
    const aberta = c.status === 'aberta';
    const aceitaPagto = ['aberta', 'fiado'].includes(c.status);
    const podeExcluirPagto = App.pode('pagamentos', 'excluir');

    const notas = {
      fechada: 'Comanda fechada: itens e pagamentos não podem mais ser alterados (a API responde 409).',
      cancelada: 'Comanda cancelada: nenhuma alteração é permitida.',
      fiado: 'Comanda em fiado: aceita novos pagamentos, mas não novos itens.',
    };

    let h = '<div class="barra"><button data-a="voltar">‹ Voltar à lista</button>' +
      '<h2>Comanda #' + c.id + ' ' + this.badge(c.status) + '</h2></div>' +
      '<p>Cliente: <strong>' + Util.esc(c.cliente_nome) + '</strong> · Aberta em ' + Util.dataHora(c.aberta_em) +
      (c.fechada_em ? ' · Encerrada em ' + Util.dataHora(c.fechada_em) : '') + '</p>' +
      '<div class="resumo"><div><span>Total</span><strong>' + Util.moeda(c.total) + '</strong></div>' +
      '<div><span>Pago</span><strong>' + Util.moeda(c.pago) + '</strong></div>' +
      '<div><span>Saldo</span><strong>' + Util.moeda(c.saldo) + '</strong></div></div>' +
      '<div id="detMsg"></div>' + (notas[c.status] ? '<div class="msg aviso">' + notas[c.status] + '</div>' : '');

    // ---- itens ----
    h += '<div class="barra"><h3>Itens</h3>' + (aberta ? '<button class="pri" data-a="novoItem">+ Adicionar item</button>' : '') + '</div>';
    if (!c.itens.length) h += '<p class="suave">Nenhum item.</p>';
    else {
      h += '<div class="tab"><table><thead><tr><th>Produto</th><th class="num">Qtd</th><th class="num">Valor unit.</th><th class="num">Subtotal</th><th></th></tr></thead><tbody>';
      c.itens.forEach(i => {
        h += '<tr><td>' + Util.esc(i.produto_nome) + '</td><td class="num">' + i.quantidade + '</td>' +
          '<td class="num">' + Util.moeda(i.valor_unitario) + '</td><td class="num">' + Util.moeda(i.subtotal) + '</td>' +
          '<td class="acoes">' + (aberta ? '<button class="peq" data-a="editarItem" data-id="' + i.id + '">Editar</button>' +
          '<button class="peq perigo" data-a="removerItem" data-id="' + i.id + '">Remover</button>' : '') + '</td></tr>';
      });
      h += '</tbody></table></div>';
    }

    // ---- pagamentos ----
    h += '<div class="barra"><h3>Pagamentos</h3>' + (aceitaPagto ? '<button class="pri" data-a="novoPagto">+ Registrar pagamento</button>' : '') + '</div>';
    if (!c.pagamentos.length) h += '<p class="suave">Nenhum pagamento registrado.</p>';
    else {
      h += '<div class="tab"><table><thead><tr><th>Forma</th><th class="num">Valor</th><th>Data</th><th></th></tr></thead><tbody>';
      c.pagamentos.forEach(p => {
        h += '<tr><td>' + Util.esc(p.metodo) + '</td><td class="num">' + Util.moeda(p.valor) + '</td><td>' + Util.dataHora(p.pago_em) + '</td>' +
          '<td class="acoes">' + (podeExcluirPagto ? '<button class="peq perigo" data-a="removerPagto" data-id="' + p.id + '">Excluir</button>' : '') + '</td></tr>';
      });
      h += '</tbody></table></div>';
    }

    // ---- ações sobre a comanda ----
    if (aceitaPagto) {
      h += '<div class="botoes">' + (aberta ? '<button data-a="fiado">Marcar como fiado</button>' : '') +
        '<button class="perigo" data-a="cancelar">Cancelar comanda</button></div>';
    }
    this.$('comandaDetalhe').innerHTML = h;
  },

  /** Mensagem no topo do detalhe (erros 409/422 etc.). */
  msgDetalhe(tipo, texto) { Util.aviso(this.$('detMsg'), tipo, texto); },

  // -------------------------------------------------------------------------
  // Itens
  // -------------------------------------------------------------------------
  async abrirDialogItem(item) {
    this.itemEditando = item;
    this.$('itemTitulo').textContent = item ? 'Editar item' : 'Adicionar item';
    this.$('formItemMsg').innerHTML = '';
    let produtos = [];
    try { produtos = await Api.listarTodos('produtos', { ativo: 1 }); }
    catch (e) { this.msgDetalhe('erro', Api.mensagemErro(e)); return; }

    const sel = this.$('itemProduto');
    sel.innerHTML = '<option value="">Selecione…</option>' + produtos.map(p =>
      '<option value="' + p.id + '">' + Util.esc(p.nome) + ' — ' + Util.moeda(p.valor) + '</option>').join('');
    if (item && !produtos.some(p => p.id === item.produto_id)) {   // produto inativado depois da venda
      sel.insertAdjacentHTML('beforeend', '<option value="' + item.produto_id + '">' + Util.esc(item.produto_nome) + ' (inativo)</option>');
    }
    sel.value = item ? item.produto_id : '';
    this.$('itemQtd').value = item ? item.quantidade : 1;
    this.$('itemValor').value = item ? item.valor_unitario : '';
    this.$('dlgItem').showModal();
  },

  async salvarItem() {
    const produto = this.$('itemProduto').value;
    const qtd = Number(this.$('itemQtd').value);
    const valorTexto = this.$('itemValor').value.trim();
    const msg = this.$('formItemMsg');

    // validação no cliente
    if (!produto) return Util.aviso(msg, 'erro', 'Selecione o produto.');
    if (!Number.isInteger(qtd) || qtd < 1) return Util.aviso(msg, 'erro', 'A quantidade deve ser um número inteiro maior que zero.');
    if (valorTexto !== '' && (Number.isNaN(Number(valorTexto)) || Number(valorTexto) < 0)) {
      return Util.aviso(msg, 'erro', 'O valor unitário não pode ser negativo.');
    }

    const corpo = { produto_id: Number(produto), quantidade: qtd };
    if (valorTexto !== '') corpo.valor_unitario = Number(valorTexto);
    try {
      if (this.itemEditando) await Api.put('/itens_comanda/' + this.itemEditando.id, corpo);
      else await Api.post('/itens_comanda', { ...corpo, comanda_id: this.atual.id });
      this.$('dlgItem').close();
      await this.verDetalhe(this.atual.id, 'Item salvo.');
    } catch (e) { Util.aviso(msg, 'erro', Api.mensagemErro(e)); }
  },

  async removerItem(id) {
    if (!confirm('Remover este item da comanda?')) return;
    try {
      await Api.delete('/itens_comanda/' + id);
      await this.verDetalhe(this.atual.id, 'Item removido.');
    } catch (e) { this.msgDetalhe('erro', Api.mensagemErro(e)); }
  },

  // -------------------------------------------------------------------------
  // Pagamentos
  // -------------------------------------------------------------------------
  abrirDialogPagto() {
    const c = this.atual;
    this.$('pagtoInfo').textContent = 'Total ' + Util.moeda(c.total) + ' · Pago ' + Util.moeda(c.pago) + ' · Saldo ' + Util.moeda(c.saldo);
    this.$('pagtoValor').value = c.saldo > 0 ? c.saldo.toFixed(2) : '';
    this.$('formPagtoMsg').innerHTML = '';
    this.$('dlgPagto').showModal();
  },

  async salvarPagto() {
    const valor = Number(this.$('pagtoValor').value);
    const msg = this.$('formPagtoMsg');
    if (!(valor > 0)) return Util.aviso(msg, 'erro', 'Informe um valor maior que zero.');
    // O limite do saldo é verificado pela API (422): assim a regra fica em um só lugar.
    const quita = this.atual.saldo - valor <= 0.005;   // este pagamento cobre o saldo?
    try {
      await Api.post('/pagamentos', { comanda_id: this.atual.id, metodo: this.$('pagtoMetodo').value, valor });
      this.$('dlgPagto').close();
      await this.verDetalhe(this.atual.id, 'Pagamento registrado.' + (quita ? ' Comanda quitada e fechada.' : ''));
    } catch (e) { Util.aviso(msg, 'erro', Api.mensagemErro(e)); }
  },

  async removerPagto(id) {
    if (!confirm('Excluir este pagamento?')) return;
    try {
      await Api.delete('/pagamentos/' + id);
      await this.verDetalhe(this.atual.id, 'Pagamento excluído.');
    } catch (e) { this.msgDetalhe('erro', Api.mensagemErro(e)); }
  },

  // -------------------------------------------------------------------------
  // Mudança de status (PUT exige cliente_id, pois é substituição do registro)
  // -------------------------------------------------------------------------
  async mudarStatus(status, pergunta, ok) {
    if (!confirm(pergunta)) return;
    try {
      await Api.put('/comandas/' + this.atual.id, { cliente_id: this.atual.cliente_id, status });
      await this.verDetalhe(this.atual.id, ok);
    } catch (e) { this.msgDetalhe('erro', Api.mensagemErro(e)); }   // ex.: 409 cancelar com pagamentos
  },

  // -------------------------------------------------------------------------
  // Eventos (ligados uma única vez pelo App)
  // -------------------------------------------------------------------------
  iniciar() {
    this.$('cmdFiltros').addEventListener('submit', ev => {
      ev.preventDefault();
      this.filtros = { status: this.$('fStatus').value, cliente_id: this.$('fCliente').value,
                       data_inicio: this.$('fDe').value, data_fim: this.$('fAte').value };
      this.pagina = 1;
      this.listar();
    });
    this.$('cmdLimpar').addEventListener('click', () => {
      this.$('cmdFiltros').reset(); this.filtros = {}; this.pagina = 1; this.listar();
    });
    this.$('cmdAnt').addEventListener('click', () => { this.pagina--; this.listar(); });
    this.$('cmdProx').addEventListener('click', () => { this.pagina++; this.listar(); });
    this.$('cmdNova').addEventListener('click', () => this.abrirDialogNova());
    this.$('formComanda').addEventListener('submit', ev => { ev.preventDefault(); this.criarComanda(); });
    this.$('formItem').addEventListener('submit', ev => { ev.preventDefault(); this.salvarItem(); });
    this.$('formPagto').addEventListener('submit', ev => { ev.preventDefault(); this.salvarPagto(); });
    // ao trocar o produto de um item existente, o preço antigo deixa de valer
    this.$('itemProduto').addEventListener('change', () => { if (this.itemEditando) this.$('itemValor').value = ''; });

    this.$('cmdTabela').addEventListener('click', ev => {
      const b = ev.target.closest('button[data-a]');
      if (!b) return;
      if (b.dataset.a === 'ver') this.verDetalhe(Number(b.dataset.id));
      else if (b.dataset.a === 'excluir') this.excluirComanda(Number(b.dataset.id));
    });

    this.$('comandaDetalhe').addEventListener('click', ev => {
      const b = ev.target.closest('button[data-a]');
      if (!b) return;
      const id = Number(b.dataset.id);
      switch (b.dataset.a) {
        case 'voltar': this.abrir(); break;
        case 'novoItem': this.abrirDialogItem(null); break;
        case 'editarItem': this.abrirDialogItem(this.atual.itens.find(i => i.id === id)); break;
        case 'removerItem': this.removerItem(id); break;
        case 'novoPagto': this.abrirDialogPagto(); break;
        case 'removerPagto': this.removerPagto(id); break;
        case 'fiado': this.mudarStatus('fiado', 'Marcar a comanda como fiado?', 'Comanda marcada como fiado.'); break;
        case 'cancelar': this.mudarStatus('cancelada', 'Cancelar esta comanda?', 'Comanda cancelada.'); break;
      }
    });
  },
};
