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

  function iniciar() {
    initTabs();
    initFormGuard();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();
