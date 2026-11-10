/**
 * crud.js — motor genérico de CRUD.
 *
 * Uma única tela serve para categorias, produtos, clientes, usuários e pagamentos.
 * Tudo é guiado pela documentação devolvida por GET / da API: campos (tipo,
 * obrigatório, mínimo, máximo...), filtros, colunas ordenáveis e permissões.
 * Se a API ganhar um campo novo, o formulário e a tabela passam a exibi-lo sem
 * alterar este arquivo.
 */
const Crud = {
  rec: null,        // recurso aberto (ex.: "produtos")
  estado: {},       // por recurso: { pagina, filtros, ordem, direcao } — lembra a busca ao trocar de aba
  meta: null,       // paginação da última listagem
  editando: null,   // registro em edição (null = novo)
  temporizador: null,

  get doc() { return App.docs[this.rec]; },

  // -------------------------------------------------------------------------
  // Abertura da tela e listagem
  // -------------------------------------------------------------------------
  async abrir(rec) {
    this.rec = rec;
    if (!this.estado[rec]) this.estado[rec] = { pagina: 1, filtros: {}, ordem: '', direcao: 'asc' };
    document.getElementById('crudTitulo').textContent = rec;
    document.getElementById('crudMsg').innerHTML = '';
    document.getElementById('crudNovo').hidden = !App.pode(rec, 'criar');
    await this.montarFiltros();
    await this.listar();
  },

  /** Monta os campos de filtro a partir da lista "filtros" da documentação. */
  async montarFiltros() {
    const form = document.getElementById('crudFiltros');
    form.innerHTML = '';
    const salvo = this.estado[this.rec].filtros;

    for (const nome of this.doc.filtros) {
      const campo = this.doc.campos[nome];               // pode não existir (ex.: q, data_inicio)
      const div = document.createElement('div');
      const rotulo = { q: 'Buscar', data_inicio: 'De', data_fim: 'Até' }[nome] || nome;
      div.innerHTML = '<label>' + Util.esc(rotulo) + '</label>';

      let entrada;
      if (campo && ['enum', 'bool', 'fk'].includes(campo.tipo)) {
        entrada = await this.criarSelect(campo, true);
      } else {
        entrada = document.createElement('input');
        entrada.type = nome.startsWith('data_') ? 'date' : 'text';
        if (nome === 'q') entrada.placeholder = 'digite para buscar…';
      }
      entrada.name = nome;
      if (salvo[nome] !== undefined) entrada.value = salvo[nome];
      div.appendChild(entrada);
      form.appendChild(div);
    }
  },

  async listar() {
    const e = this.estado[this.rec];
    const params = { ...e.filtros, pagina: e.pagina, limite: 10, ordem: e.ordem, direcao: e.ordem ? e.direcao : '' };
    try {
      const r = await Api.get('/' + this.rec + Api.consulta(params));
      this.meta = r.meta;
      this.desenharTabela(r.dados);
    } catch (erro) {
      document.getElementById('crudLista').innerHTML = '';
      this.mensagem('erro', Api.mensagemErro(erro));
    }
  },

  mensagem(tipo, texto) { Util.aviso(document.getElementById('crudMsg'), tipo, texto); },

  desenharTabela(linhas) {
    const e = this.estado[this.rec];
    const lista = document.getElementById('crudLista');
    this.atualizarPaginacao();
    if (!linhas.length) { lista.innerHTML = '<p class="suave">Nenhum registro encontrado.</p>'; return; }

    // colunas = campos simples (sem listas/objetos) da primeira linha
    const colunas = Object.keys(linhas[0]).filter(c => typeof linhas[0][c] !== 'object' || linhas[0][c] === null);
    const podeEditar = App.pode(this.rec, 'atualizar'), podeExcluir = App.pode(this.rec, 'excluir');

    let h = '<table><thead><tr>';
    colunas.forEach(c => {
      const ordenavel = this.doc.ordenaveis.includes(c);
      const seta = e.ordem === c ? (e.direcao === 'asc' ? ' ▲' : ' ▼') : '';
      h += '<th' + (ordenavel ? ' class="ord" data-col="' + Util.esc(c) + '" title="Clique para ordenar"' : '') + '>' + Util.esc(c) + seta + '</th>';
    });
    h += (podeEditar || podeExcluir ? '<th></th>' : '') + '</tr></thead><tbody>';

    linhas.forEach(l => {
      h += '<tr>' + colunas.map(c => '<td>' + this.celula(c, l[c]) + '</td>').join('');
      if (podeEditar || podeExcluir) {
        h += '<td class="acoes">' +
          (podeEditar ? '<button class="peq" data-a="editar" data-id="' + l.id + '">Editar</button>' : '') +
          (podeExcluir ? '<button class="peq perigo" data-a="excluir" data-id="' + l.id + '">Excluir</button>' : '') + '</td>';
      }
      h += '</tr>';
    });
    lista.innerHTML = h + '</tbody></table>';
  },

  /** Formata o valor de uma célula (já escapado). */
  celula(coluna, valor) {
    if (valor === null || valor === undefined) return '—';
    const campo = this.doc.campos[coluna] || {};
    if (coluna === 'imagem') {
      // o caminho gravado no banco é relativo à pasta dev/ (um nível acima do protótipo)
      return '<img class="miniatura" src="../' + Util.esc(valor) + '" alt="" loading="lazy"> ' + Util.esc(valor);
    }
    if (typeof valor === 'boolean') return valor ? 'Sim' : 'Não';
    if (campo.tipo === 'decimal') return Util.moeda(valor);
    if (coluna.endsWith('_em') && typeof valor === 'string') return Util.esc(Util.dataHora(valor));
    return Util.esc(valor);
  },

  atualizarPaginacao() {
    const m = this.meta;
    document.getElementById('crudPag').textContent = 'Página ' + m.pagina + ' de ' + Math.max(1, m.paginas) + ' · ' + m.total + ' registro(s)';
    document.getElementById('crudAnt').disabled = m.pagina <= 1;
    document.getElementById('crudProx').disabled = m.pagina >= m.paginas;
  },

  // -------------------------------------------------------------------------
  // Formulário gerado a partir dos campos da documentação
  // -------------------------------------------------------------------------
  async abrirForm(registro) {
    this.editando = registro;
    document.getElementById('crudFormTitulo').textContent =
      registro ? 'Editar ' + this.rec + ' #' + registro.id : 'Novo registro em ' + this.rec;
    document.getElementById('crudFormMsg').innerHTML = '';
    const area = document.getElementById('crudCampos');
    area.innerHTML = '';

    for (const [nome, c] of Object.entries(this.doc.campos)) {
      const div = document.createElement('div');
      const obrigatorio = c.obrigatorio || (!registro && c.obrigatorioCriacao);
      div.innerHTML = '<label>' + Util.esc(nome) + (obrigatorio ? ' *' : '') +
        (c.descricao ? ' <small>' + Util.esc(c.descricao) + '</small>' : '') + '</label>';
      const entrada = await this.criarEntrada(nome, c, registro ? registro[nome] : undefined);
      if (registro && c.somenteCriacao) entrada.disabled = true;   // não muda no PUT
      div.appendChild(entrada);
      div.insertAdjacentHTML('beforeend', '<div class="erro-campo" data-erro="' + Util.esc(nome) + '"></div>');
      area.appendChild(div);
    }
    document.getElementById('dlgForm').showModal();
  },

  /** <select> para enum, bool e chave estrangeira. */
  async criarSelect(campo, filtro) {
    const sel = document.createElement('select');
    sel.innerHTML = '<option value="">' + (filtro ? 'Todos' : '— (padrão)') + '</option>';
    let opcoes = [];
    if (campo.tipo === 'enum') {
      opcoes = campo.valores.map(v => [v, v]);
    } else if (campo.tipo === 'bool') {
      opcoes = filtro ? [['1', 'Sim'], ['0', 'Não']] : [['true', 'Sim'], ['false', 'Não']];
    } else {
      try {
        const dados = await Api.listarTodos(campo.tabela);
        opcoes = dados.map(d => [d.id, '#' + d.id + ' ' + (d.nome || d.cliente_nome || '') + (d.status ? ' (' + d.status + ')' : '')]);
      } catch (e) { /* a mensagem de erro já aparece no painel de log */ }
    }
    opcoes.forEach(([v, t]) => {
      const o = document.createElement('option'); o.value = v; o.textContent = t; sel.appendChild(o);
    });
    return sel;
  },

  async criarEntrada(nome, c, valor) {
    let el;
    if (['enum', 'bool', 'fk'].includes(c.tipo)) {
      el = await this.criarSelect(c, false);
    } else {
      el = document.createElement('input');
      el.type = { int: 'number', decimal: 'number', email: 'email' }[c.tipo] || 'text';
      if (nome === 'senha') { el.type = 'password'; el.autocomplete = 'new-password'; }
      if (c.tipo === 'decimal') el.step = '0.01';
      if (c.tipo === 'int') el.step = '1';
      if (el.type === 'number') el.min = c.min ?? 0;                  // não aceita negativos
      if (c.max) el.maxLength = c.max;
      if (c.tipo === 'datetime') el.placeholder = 'AAAA-MM-DD HH:MM:SS';
    }
    el.name = nome;
    el.dataset.tipo = c.tipo;
    if (valor !== undefined && valor !== null && nome !== 'senha') el.value = String(valor);
    return el;
  },

  /** Validação no cliente. Devolve { corpo, erros }. A API valida de novo (a regra final é dela). */
  validar() {
    const corpo = {}, erros = {};
    document.querySelectorAll('#crudCampos [name]').forEach(el => {
      el.classList.remove('invalido');
      if (el.disabled) return;
      const nome = el.name, c = this.doc.campos[nome], v = el.value.trim();
      const obrigatorio = c.obrigatorio || (!this.editando && c.obrigatorioCriacao);

      if (v === '') {
        if (obrigatorio) erros[nome] = 'Campo obrigatório.';
        else if (this.editando && c.anulavel) corpo[nome] = '';     // limpar um campo opcional
        return;
      }
      if (['int', 'fk', 'decimal'].includes(c.tipo)) {
        const n = Number(v);
        if (Number.isNaN(n)) { erros[nome] = 'Informe um número.'; return; }
        if (n < 0) { erros[nome] = 'Não pode ser negativo.'; return; }
        if (c.min !== undefined && n < c.min) { erros[nome] = 'Mínimo: ' + c.min + '.'; return; }
        corpo[nome] = n;
      } else if (c.tipo === 'bool') {
        corpo[nome] = v === 'true';
      } else if (c.tipo === 'email') {
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) { erros[nome] = 'E-mail inválido.'; return; }
        corpo[nome] = v;
      } else if (c.tipo === 'datetime') {
        if (!/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}:\d{2})?$/.test(v)) { erros[nome] = 'Use AAAA-MM-DD HH:MM:SS.'; return; }
        corpo[nome] = v;
      } else {
        if (c.min && v.length < c.min) { erros[nome] = 'Mínimo de ' + c.min + ' caracteres.'; return; }
        if (c.max && v.length > c.max) { erros[nome] = 'Máximo de ' + c.max + ' caracteres.'; return; }
        corpo[nome] = v;
      }
    });
    return { corpo, erros };
  },

  mostrarErrosCampos(erros) {
    Object.entries(erros).forEach(([nome, texto]) => {
      const alvo = document.querySelector('#crudCampos [data-erro="' + nome + '"]');
      const campo = document.querySelector('#crudCampos [name="' + nome + '"]');
      if (alvo) alvo.textContent = texto;
      if (campo) campo.classList.add('invalido');
    });
  },

  async salvar() {
    document.querySelectorAll('#crudCampos .erro-campo').forEach(d => d.textContent = '');
    document.getElementById('crudFormMsg').innerHTML = '';
    const { corpo, erros } = this.validar();
    if (Object.keys(erros).length) { this.mostrarErrosCampos(erros); return; }

    try {
      if (this.editando) await Api.put('/' + this.rec + '/' + this.editando.id, corpo);
      else await Api.post('/' + this.rec, corpo);
      document.getElementById('dlgForm').close();
      this.mensagem('ok', 'Registro salvo com sucesso.');
      await this.listar();
    } catch (e) {
      if (e.status === 422 && e.detalhes) this.mostrarErrosCampos(e.detalhes);   // erro por campo vindo da API
      Util.aviso(document.getElementById('crudFormMsg'), 'erro', Api.mensagemErro(e));
    }
  },

  async excluir(id) {
    if (!confirm('Excluir o registro ' + id + ' de ' + this.rec + '?')) return;
    try {
      await Api.delete('/' + this.rec + '/' + id);
      this.mensagem('ok', 'Registro excluído.');
      // se a página ficou vazia, volta uma página
      const e = this.estado[this.rec];
      if (this.meta.total - 1 <= (e.pagina - 1) * 10 && e.pagina > 1) e.pagina--;
      await this.listar();
    } catch (erro) { this.mensagem('erro', Api.mensagemErro(erro)); }   // ex.: 409 produto com vendas
  },

  // -------------------------------------------------------------------------
  // Eventos (ligados uma única vez pelo App)
  // -------------------------------------------------------------------------
  iniciar() {
    const $ = id => document.getElementById(id);

    $('crudNovo').addEventListener('click', () => this.abrirForm(null));
    $('crudAnt').addEventListener('click', () => { this.estado[this.rec].pagina--; this.listar(); });
    $('crudProx').addEventListener('click', () => { this.estado[this.rec].pagina++; this.listar(); });
    $('crudForm').addEventListener('submit', ev => { ev.preventDefault(); this.salvar(); });

    // filtros: aplica 300 ms depois da última digitação
    $('crudFiltros').addEventListener('input', () => {
      clearTimeout(this.temporizador);
      this.temporizador = setTimeout(() => {
        const e = this.estado[this.rec];
        e.filtros = {};
        new FormData($('crudFiltros')).forEach((v, k) => { if (v !== '') e.filtros[k] = v; });
        e.pagina = 1;
        this.listar();
      }, 300);
    });
    $('crudFiltros').addEventListener('submit', ev => ev.preventDefault());

    // cliques na tabela: ordenar, editar, excluir
    $('crudLista').addEventListener('click', async ev => {
      const th = ev.target.closest('th.ord');
      if (th) {
        const e = this.estado[this.rec];
        e.direcao = (e.ordem === th.dataset.col && e.direcao === 'asc') ? 'desc' : 'asc';
        e.ordem = th.dataset.col; e.pagina = 1;
        return this.listar();
      }
      const b = ev.target.closest('button[data-a]');
      if (!b) return;
      if (b.dataset.a === 'excluir') return this.excluir(b.dataset.id);
      try {
        const r = await Api.get('/' + this.rec + '/' + b.dataset.id);
        this.abrirForm(r.dados);
      } catch (e) { this.mensagem('erro', Api.mensagemErro(e)); }
    });
  },
};
