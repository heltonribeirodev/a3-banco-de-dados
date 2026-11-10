/**
 * app.js — inicialização: login, sessão, abas e permissões.
 * É o único arquivo que conhece todas as telas.
 */
const App = {
  usuario: null,       // { id, nome, email, perfil }
  docs: {},            // recursos documentados em GET /
  relatorios: {},      // relatórios documentados em GET /
  aba: null,

  /** Recursos atendidos pelo motor genérico de CRUD (comandas e itens têm tela própria). */
  CRUD: ['categorias', 'produtos', 'clientes', 'usuarios', 'pagamentos'],

  $(id) { return document.getElementById(id); },

  /** O usuário logado pode executar esta ação neste recurso? (regra vem da API, em GET /) */
  pode(recurso, acao) {
    const regra = this.docs[recurso] && this.docs[recurso].permissoes[acao];
    return regra === 'auth' || (regra === 'admin' && this.usuario.perfil === 'admin');
  },

  // ---- Login / sessão ----
  async entrar(ev) {
    ev.preventDefault();
    const email = this.$('email').value.trim(), senha = this.$('senha').value;
    if (!email || !senha) return Util.aviso(this.$('msgLogin'), 'erro', 'Informe e-mail e senha.');
    try {
      const r = await Api.post('/auth/login', { email, senha });
      Api.guardarToken(r.token);
      sessionStorage.setItem('usuario', JSON.stringify(r.usuario));
      this.$('senha').value = '';
      await this.iniciarSessao(r.usuario);
    } catch (e) { Util.aviso(this.$('msgLogin'), 'erro', Api.mensagemErro(e)); }
  },

  async iniciarSessao(usuario) {
    this.usuario = usuario;
    const raiz = await Api.get('/');            // documentação: campos, filtros, permissões, relatórios
    this.docs = raiz.recursos;
    this.relatorios = raiz.relatorios;
    this.$('login').hidden = true;
    this.$('app').hidden = false;
    this.$('sair').hidden = false;
    this.$('quem').textContent = usuario.nome + ' (' + usuario.perfil + ')';
    this.montarAbas();
    this.abrirAba(this.abasVisiveis()[0]);
  },

  /** Volta para a tela de login (sair ou sessão expirada). */
  sair(motivo) {
    Api.guardarToken(null);
    sessionStorage.removeItem('usuario');
    this.usuario = null;
    this.$('app').hidden = true;
    this.$('sair').hidden = true;
    this.$('quem').textContent = '';
    this.$('login').hidden = false;
    this.$('msgLogin').innerHTML = '';
    if (motivo) Util.aviso(this.$('msgLogin'), 'aviso', motivo);
  },

  // ---- Abas ----
  abasVisiveis() {
    // 'usuarios' só aparece para quem tem permissão de listar (administrador)
    return ['comandas', 'relatorios', ...this.CRUD.filter(r => this.docs[r] && this.pode(r, 'listar'))];
  },

  montarAbas() {
    this.$('abas').innerHTML = this.abasVisiveis().map(n =>
      '<button data-aba="' + Util.esc(n) + '">' + Util.esc(n) + '</button>').join('');
  },

  abrirAba(nome) {
    this.aba = nome;
    document.querySelectorAll('#abas button').forEach(b => b.classList.toggle('ativa', b.dataset.aba === nome));
    this.$('viewComandas').hidden = nome !== 'comandas';
    this.$('viewRelatorios').hidden = nome !== 'relatorios';
    this.$('viewCrud').hidden = !this.CRUD.includes(nome);
    if (nome === 'comandas') Comandas.abrir();
    else if (nome === 'relatorios') Relatorios.abrir();
    else Crud.abrir(nome);
  },

  // ---- Inicialização da página ----
  async iniciar() {
    Api.aoExpirar = () => this.sair('Sua sessão expirou. Entre novamente.');
    this.$('formLogin').addEventListener('submit', ev => this.entrar(ev));
    this.$('sair').addEventListener('click', () => this.sair());
    this.$('abas').addEventListener('click', ev => {
      const b = ev.target.closest('button[data-aba]');
      if (b) this.abrirAba(b.dataset.aba);
    });
    // botões "Cancelar" de todas as janelas
    document.querySelectorAll('dialog [data-fechar]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));

    Crud.iniciar(); Comandas.iniciar(); Relatorios.iniciar();

    // sessão já aberta nesta aba? (token em sessionStorage)
    if (Api.token) {
      try {
        await this.iniciarSessao(JSON.parse(sessionStorage.getItem('usuario')));
      } catch (e) { this.sair(); }
    }
  },
};

App.iniciar();
