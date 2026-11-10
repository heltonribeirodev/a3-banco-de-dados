/**
 * api.js — comunicação com a API REST e utilitários de apresentação.
 *
 *  - Api:  fetch com token, tratamento de erros, indicador de carregamento
 *          e registro da requisição/resposta no painel de log.
 *  - Util: funções pequenas de formatação e de proteção contra XSS.
 */

// ---------------------------------------------------------------------------
// Util
// ---------------------------------------------------------------------------
const Util = {
  /** Escapa HTML: TODO texto vindo da API ou do usuário passa por aqui antes de entrar em innerHTML (evita XSS). */
  esc(valor) {
    return String(valor == null ? '' : valor)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  },

  /** 12.5 -> "R$ 12,50" */
  moeda(n) {
    return Number(n || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  },

  /** "2026-10-05 19:10:00" -> "05/10/2026 19:10" */
  dataHora(s) {
    if (!s) return '—';
    const [data, hora] = String(s).split(' ');
    const [a, m, d] = data.split('-');
    return d + '/' + m + '/' + a + (hora ? ' ' + hora.slice(0, 5) : '');
  },

  /** Mostra uma mensagem (tipo: 'ok', 'erro' ou 'aviso') dentro de um elemento. */
  aviso(el, tipo, texto) {
    el.innerHTML = '<div class="msg ' + tipo + '" role="alert">' + Util.esc(texto) + '</div>';
  },
};

// ---------------------------------------------------------------------------
// Api
// ---------------------------------------------------------------------------

/** Erro devolvido pela API (ou falha de rede, com status 0). */
class ErroApi extends Error {
  constructor(status, mensagem, detalhes) {
    super(mensagem);
    this.status = status;
    this.detalhes = detalhes || null;
  }
}

const Api = {
  base: '../api/v1',
  token: sessionStorage.getItem('token'),   // o token fica só na aba aberta
  aoExpirar: null,                          // função chamada quando a API responde 401
  pendentes: 0,                             // requisições em andamento (indicador de carregamento)

  guardarToken(token) {
    this.token = token;
    if (token) sessionStorage.setItem('token', token); else sessionStorage.removeItem('token');
  },

  /**
   * Faz uma chamada à API e devolve o JSON da resposta.
   * Lança ErroApi para qualquer status fora de 2xx.
   */
  async chamar(metodo, caminho, corpo) {
    const url = this.base + caminho;
    const opcoes = { method: metodo, headers: { Accept: 'application/json' } };
    if (this.token) opcoes.headers.Authorization = 'Bearer ' + this.token;
    if (corpo !== undefined) {
      opcoes.headers['Content-Type'] = 'application/json';
      opcoes.body = JSON.stringify(corpo);
    }

    this.pendentes++;
    document.getElementById('carregando').hidden = false;
    let resposta, json = null;
    try {
      resposta = await fetch(url, opcoes);
      const texto = await resposta.text();
      try { json = texto ? JSON.parse(texto) : null; } catch (e) { /* resposta sem JSON */ }
    } catch (e) {
      this.registrar(metodo, url, corpo, 0, 'Falha de conexão', null);
      throw new ErroApi(0, 'Não foi possível conectar à API. Verifique se o Apache e o MySQL estão ligados.');
    } finally {
      if (--this.pendentes === 0) document.getElementById('carregando').hidden = true;
    }

    this.registrar(metodo, url, corpo, resposta.status, resposta.statusText, json);
    if (!resposta.ok) {
      // 401 fora do login = sessão expirada: volta para a tela de entrada
      if (resposta.status === 401 && caminho !== '/auth/login' && this.aoExpirar) this.aoExpirar();
      throw new ErroApi(resposta.status, (json && json.erro) || 'Erro ' + resposta.status, json && json.detalhes);
    }
    return json;
  },

  get(caminho)         { return this.chamar('GET', caminho); },
  post(caminho, corpo) { return this.chamar('POST', caminho, corpo); },
  put(caminho, corpo)  { return this.chamar('PUT', caminho, corpo); },
  delete(caminho)      { return this.chamar('DELETE', caminho); },

  /** Monta "?a=1&b=2" ignorando valores vazios. */
  consulta(params) {
    const p = new URLSearchParams();
    Object.entries(params || {}).forEach(([k, v]) => { if (v !== '' && v != null) p.set(k, v); });
    const s = p.toString();
    return s ? '?' + s : '';
  },

  /** Lista todos os registros de um recurso (página a página, 100 por vez). Usado nos <select>. */
  async listarTodos(recurso, params) {
    let pagina = 1, todos = [], meta;
    do {
      const r = await this.get('/' + recurso + this.consulta({ ...params, limite: 100, pagina }));
      todos = todos.concat(r.dados);
      meta = r.meta;
      pagina++;
    } while (pagina <= meta.paginas && pagina <= 5);
    return todos;
  },

  /** Texto de erro para o usuário, sempre com o código HTTP visível. */
  mensagemErro(e) {
    const nomes = {
      0: 'Sem conexão', 400: 'Requisição inválida (400)', 401: 'Não autenticado (401)',
      403: 'Sem permissão (403)', 404: 'Não encontrado (404)', 405: 'Método não permitido (405)',
      409: 'Regra de negócio / conflito (409)', 422: 'Dados inválidos (422)', 500: 'Erro no servidor (500)',
    };
    if (!(e instanceof ErroApi)) return 'Erro inesperado: ' + e.message;
    let t = nomes[e.status] || 'Erro ' + e.status;
    if (e.message !== 'Dados inválidos.') t += ': ' + e.message;
    if (e.detalhes) t += ' — ' + Object.entries(e.detalhes).map(([k, v]) => k + ': ' + v).join('; ');
    return t;
  },

  /** Painel de requisição/resposta, útil para acompanhar o CRUD na demonstração. */
  registrar(metodo, url, corpo, status, statusTexto, json) {
    document.getElementById('logCard').hidden = false;
    document.getElementById('reqlinha').innerHTML =
      '<b class="' + Util.esc(metodo) + '">' + Util.esc(metodo) + '</b> ' + Util.esc(url) +
      ' → <span class="st' + (status >= 400 || status === 0 ? ' ruim' : '') + '">' + status + ' ' + Util.esc(statusTexto) + '</span>';
    let enviado = '';
    if (corpo !== undefined) {
      const copia = { ...corpo };
      if ('senha' in copia) copia.senha = '********';        // não exibe senhas no painel
      enviado = '// corpo enviado\n' + JSON.stringify(copia, null, 2) + '\n\n';
    }
    document.getElementById('log').textContent =
      enviado + '// resposta\n' + (json ? JSON.stringify(json, null, 2) : '(sem corpo)');
  },
};
