/**
 * relatorios.js — tela de relatórios.
 *
 * Consome GET /relatorios/{nome}. A lista de relatórios e os parâmetros que
 * cada um aceita vêm de GET / (documentação da API), então o seletor de
 * período só aparece nos relatórios que realmente o aceitam.
 */
const Relatorios = {
  docs: {},   // documentação dos relatórios (GET /)

  $(id) { return document.getElementById(id); },

  /** Colunas que representam dinheiro (formatadas como R$). */
  MONETARIAS: ['total', 'pago', 'receita', 'faturamento', 'total_recebido', 'saldo_devedor', 'valor_total'],

  /** Rótulos dos parâmetros. */
  ROTULOS: { data_inicio: 'De', data_fim: 'Até', limite: 'Limite de linhas', minimo: 'Faturamento mínimo (R$)' },

  abrir() {
    this.docs = App.relatorios;
    const sel = this.$('relNome');
    if (!sel.options.length) {
      sel.innerHTML = Object.keys(this.docs).map(n => '<option value="' + Util.esc(n) + '">' + Util.esc(n) + '</option>').join('');
    }
    this.montarParametros();
    this.$('relTabela').innerHTML = '';
    this.$('relMsg').innerHTML = '';
  },

  /** Mostra apenas os campos de filtro que o relatório escolhido aceita. */
  montarParametros() {
    const doc = this.docs[this.$('relNome').value];
    this.$('relDescricao').textContent = doc.descricao;
    this.$('relParams').innerHTML = doc.parametros.map(p => {
      const tipo = p.startsWith('data_') ? 'date' : 'number';
      const extra = p === 'limite' ? ' min="1" max="100" step="1"' : (p === 'minimo' ? ' min="0" step="0.01"' : '');
      return '<div><label>' + Util.esc(this.ROTULOS[p] || p) + '</label><input name="' + Util.esc(p) + '" type="' + tipo + '"' + extra + '></div>';
    }).join('');
  },

  async gerar() {
    const nome = this.$('relNome').value;
    const params = {};
    new FormData(this.$('relForm')).forEach((v, k) => { if (k !== 'relNome' && v !== '') params[k] = v; });

    // validação no cliente
    if (params.data_inicio && params.data_fim && params.data_inicio > params.data_fim) {
      return Util.aviso(this.$('relMsg'), 'erro', 'A data final deve ser maior ou igual à inicial.');
    }
    if (params.minimo !== undefined && Number(params.minimo) < 0) {
      return Util.aviso(this.$('relMsg'), 'erro', 'O valor mínimo não pode ser negativo.');
    }
    this.$('relMsg').innerHTML = '';
    try {
      const r = await Api.get('/relatorios/' + nome + Api.consulta(params));
      this.desenhar(r.dados);
    } catch (e) {
      this.$('relTabela').innerHTML = '';
      Util.aviso(this.$('relMsg'), 'erro', Api.mensagemErro(e));
    }
  },

  desenhar(linhas) {
    if (!linhas.length) { this.$('relTabela').innerHTML = '<p class="suave">Nenhum resultado para os filtros informados.</p>'; return; }
    const colunas = Object.keys(linhas[0]);
    let h = '<table><thead><tr>' + colunas.map(c => '<th>' + Util.esc(c.replace(/_/g, ' ')) + '</th>').join('') + '</tr></thead><tbody>';
    linhas.forEach(l => {
      h += '<tr>' + colunas.map(c => {
        let v = l[c];
        if (v === null) v = '—';
        else if (this.MONETARIAS.includes(c)) v = Util.moeda(v);
        else if (c.endsWith('_em')) v = Util.dataHora(v);
        return '<td' + (typeof l[c] === 'number' ? ' class="num"' : '') + '>' + Util.esc(v) + '</td>';
      }).join('') + '</tr>';
    });
    this.$('relTabela').innerHTML = h + '</tbody></table><p class="suave">' + linhas.length + ' linha(s).</p>';
  },

  iniciar() {
    this.$('relNome').addEventListener('change', () => { this.montarParametros(); this.$('relTabela').innerHTML = ''; });
    this.$('relForm').addEventListener('submit', ev => { ev.preventDefault(); this.gerar(); });
  },
};
