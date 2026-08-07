(function () {
  'use strict';

  // Navegação por abas com "Voltar"/"Próximo" — Salvar fica sempre visível,
  // os botões de navegação são só um atalho de conveniência.
  function initTabs() {
    var botoes = Array.from(document.querySelectorAll('.form-tabs button'));
    var panes  = Array.from(document.querySelectorAll('.tab-pane'));
    if (!botoes.length) return;

    var btnVoltar  = document.getElementById('btn-voltar');
    var btnProximo = document.getElementById('btn-proximo');
    var atual = 0;

    function irPara(idx) {
      atual = Math.max(0, Math.min(idx, botoes.length - 1));
      botoes.forEach(function (b) { b.classList.remove('ativo'); });
      panes.forEach(function (p) { p.classList.remove('ativo'); });
      botoes[atual].classList.add('ativo');
      panes[atual].classList.add('ativo');
      if (btnVoltar)  btnVoltar.style.display  = atual === 0 ? 'none' : '';
      if (btnProximo) btnProximo.style.display = atual === botoes.length - 1 ? 'none' : '';
    }

    // No mobile quem rola é o container ".content" (não a janela) — usar
    // scrollIntoView deixa o navegador escolher o ancestral correto em vez
    // de forçar window.scrollTo, que "briga" com o scroll interno e faz a
    // página trepidar no iOS Safari.
    function rolarParaTopo() {
      var wrap = document.querySelector('.form-wrap');
      if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    botoes.forEach(function (btn, idx) {
      btn.addEventListener('click', function () { irPara(idx); rolarParaTopo(); });
    });
    if (btnProximo) btnProximo.addEventListener('click', function () { irPara(atual + 1); rolarParaTopo(); });
    if (btnVoltar)  btnVoltar.addEventListener('click', function () { irPara(atual - 1); rolarParaTopo(); });

    irPara(0);
  }

  // Avisa antes de sair da página (fechar aba, recarregar, clicar em outro
  // link/menu) se houver alteração não salva no formulário.
  function initFormGuard() {
    var form = document.querySelector('.form-wrap form[method="post"]');
    if (!form) return;
    var dirty = false;

    form.addEventListener('input',  function () { dirty = true; });
    form.addEventListener('change', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });

    window.addEventListener('beforeunload', function (e) {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = '';
      return '';
    });
  }

  // O widget nativo de input[type=date] no WebKit (Safari e Chrome no
  // iOS usam o mesmo motor) às vezes ignora a largura definida em CSS e
  // desenha mais largo que a caixa, estourando o card — nenhuma
  // combinação de width/min-width/overflow no próprio input resolve de
  // forma confiável (testado). A solução definitiva é parar de depender
  // do desenho nativo: o input real continua no DOM (mesmo id/name,
  // recebe toque e abre o calendário normalmente), mas fica invisível;
  // quem aparece é um <div> comum por cima mostrando o valor formatado —
  // um <div> sempre respeita a largura do container, sem exceções.
  function blindarDatas() {
    document.querySelectorAll('input[type="date"]').forEach(function (input) {
      if (input.dataset.blindado) return;
      input.dataset.blindado = '1';

      var wrap = document.createElement('div');
      wrap.className = 'data-wrap';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);

      var visor = document.createElement('div');
      visor.className = 'data-visor';
      wrap.appendChild(visor);

      function formatar(iso) {
        var p = (iso || '').split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : '';
      }
      function atualizar() {
        var f = formatar(input.value);
        visor.textContent = f || 'dd/mm/aaaa';
        visor.classList.toggle('vazio', !f);
      }

      // Além dos eventos normais, intercepta "input.value = ..." feito por
      // outros scripts do formulário (ex: preenchimento automático de
      // data ao mudar o status), pra o visor nunca ficar dessincronizado.
      var descNativo = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
      Object.defineProperty(input, 'value', {
        configurable: true,
        get: function () { return descNativo.get.call(this); },
        set: function (v) { descNativo.set.call(this, v); atualizar(); },
      });

      input.addEventListener('input', atualizar);
      input.addEventListener('change', atualizar);
      atualizar();
    });
  }

  // Preenche endereço/bairro/cidade/estado a partir do CEP (ViaCEP, via
  // proxy do servidor) — funciona como redundância ao preenchimento
  // manual/autocomplete de cidade: digitou o CEP certo, o resto vem junto.
  function autoPreencherPorCep() {
    var cepInput = document.getElementById('cep');
    if (!cepInput) return;

    var enderecoInput = document.getElementById('endereco');
    var bairroInput   = document.getElementById('bairro');
    var cidadeInput   = document.getElementById('cidade');
    var estadoSelect  = document.getElementById('estado');
    var ultimoCep     = '';

    function buscar() {
      var cep = cepInput.value.replace(/\D/g, '');
      if (cep.length !== 8 || cep === ultimoCep) return;
      ultimoCep = cep;

      fetch('/portal/membros/cep_lookup.php?cep=' + cep)
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.erro) return;
          if (enderecoInput && d.logradouro) enderecoInput.value = d.logradouro;
          if (bairroInput   && d.bairro)     bairroInput.value   = d.bairro;
          if (cidadeInput   && d.localidade) cidadeInput.value   = d.localidade;
          if (estadoSelect  && d.uf)         estadoSelect.value  = d.uf;
        })
        .catch(function () {});
    }

    cepInput.addEventListener('blur', buscar);
    cepInput.addEventListener('input', function () {
      if (this.value.replace(/\D/g, '').length === 8) buscar();
    });
  }

  function iniciar() {
    initTabs();
    initFormGuard();
    blindarDatas();
    autoPreencherPorCep();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();
