const App = {
  usuario: null, 
  docs: {},
  relatorios: {},
  aba: null,

  CRUD: ['categorias', 'produtos', 'clientes', 'usuarios', 'pagamentos'],

  $(id) { return document.getElementById(id); },

  pode(recurso, acao) {
    const regra = this.docs[recurso] && this.docs[recurso].permissoes[acao];
    return regra === 'auth' || (regra === 'admin' && this.usuario.perfil === 'admin');
  },

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
    const raiz = await Api.get('/');
    this.docs = raiz.recursos;
    this.relatorios = raiz.relatorios;
    this.$('login').hidden = true;
    this.$('app').hidden = false;
    this.$('sair').hidden = false;
    this.$('quem').textContent = usuario.nome + ' (' + usuario.perfil + ')';
    this.montarAbas();
    this.abrirAba(this.abasVisiveis()[0]);
  },
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

  abasVisiveis() {
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

  async iniciar() {
    Api.aoExpirar = () => this.sair('Sua sessão expirou. Entre novamente.');
    this.$('formLogin').addEventListener('submit', ev => this.entrar(ev));
    this.$('sair').addEventListener('click', () => this.sair());
    this.$('abas').addEventListener('click', ev => {
      const b = ev.target.closest('button[data-aba]');
      if (b) this.abrirAba(b.dataset.aba);
    });
    document.querySelectorAll('dialog [data-fechar]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));

    Crud.iniciar(); Comandas.iniciar(); Relatorios.iniciar();

    if (Api.token) {
      try {
        await this.iniciarSessao(JSON.parse(sessionStorage.getItem('usuario')));
      } catch (e) { this.sair(); }
    }
  },
};

App.iniciar();