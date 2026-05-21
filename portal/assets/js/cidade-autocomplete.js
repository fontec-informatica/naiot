(function () {
  'use strict';

  var cidadesCache = null;
  var CACHE_KEY    = 'naiot_cidades_br_v1';

  /* ── normaliza string para comparação (remove acentos, lowercase) ── */
  function norm(s) {
    return s.toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '');
  }

  /* ── filtra cidades que COMEÇAM com a query; se não achar, tenta contém ── */
  function filtrar(lista, q) {
    var nq = norm(q);
    var começa = lista.filter(function (c) {
      return norm(c.nome).indexOf(nq) === 0;
    });
    if (começa.length) return começa.slice(0, 10);
    return lista.filter(function (c) {
      return norm(c.nome).indexOf(nq) !== -1;
    }).slice(0, 10);
  }

  /* ── cria o widget de sugestões ── */
  function initInput(input) {
    var wrapper = input.parentNode;
    wrapper.style.position = 'relative';

    var box = document.createElement('ul');
    box.className = 'cidade-ac-box';
    wrapper.appendChild(box);

    var debounce;
    var selecionando = false;

    function fechar() { box.innerHTML = ''; box.style.display = 'none'; }

    function mostrar(lista) {
      box.innerHTML = '';
      if (!lista.length) { fechar(); return; }
      lista.forEach(function (c) {
        var li = document.createElement('li');
        li.className = 'cidade-ac-item';
        li.innerHTML =
          '<span class="cidade-ac-nome">' + c.nome + '</span>' +
          '<span class="cidade-ac-uf">' + c.uf + '</span>';
        li.addEventListener('mousedown', function (e) {
          e.preventDefault();
          selecionando = true;
          input.value = c.nome;
          fechar();
          input.dispatchEvent(new Event('change'));
          selecionando = false;
        });
        box.appendChild(li);
      });
      box.style.display = 'block';
    }

    function buscar(q) {
      if (q.length < 2) { fechar(); return; }
      if (cidadesCache) { mostrar(filtrar(cidadesCache, q)); return; }

      /* primeiro uso: carrega da API do IBGE */
      fetch('https://servicodados.ibge.gov.br/api/v1/localidades/municipios?orderBy=nome')
        .then(function (r) { return r.json(); })
        .then(function (data) {
          cidadesCache = data.map(function (m) {
            return {
              nome: m.nome,
              uf:   m.microrregiao.mesorregiao.UF.sigla
            };
          });
          try { sessionStorage.setItem(CACHE_KEY, JSON.stringify(cidadesCache)); } catch (_) {}
          mostrar(filtrar(cidadesCache, q));
        })
        .catch(function () { /* offline: sem sugestões */ });
    }

    input.addEventListener('input', function () {
      clearTimeout(debounce);
      var q = this.value.trim();
      debounce = setTimeout(function () { buscar(q); }, 220);
    });

    input.addEventListener('focus', function () {
      if (this.value.trim().length >= 2) buscar(this.value.trim());
    });

    input.addEventListener('blur', function () {
      if (!selecionando) setTimeout(fechar, 150);
    });

    /* teclado: ↑ ↓ Enter Esc */
    input.addEventListener('keydown', function (e) {
      var items = box.querySelectorAll('.cidade-ac-item');
      var ativo = box.querySelector('.cidade-ac-item.ativo');
      var idx   = Array.prototype.indexOf.call(items, ativo);
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (ativo) ativo.classList.remove('ativo');
        var next = items[idx + 1] || items[0];
        if (next) next.classList.add('ativo');
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (ativo) ativo.classList.remove('ativo');
        var prev = items[idx - 1] || items[items.length - 1];
        if (prev) prev.classList.add('ativo');
      } else if (e.key === 'Enter' && ativo) {
        e.preventDefault();
        input.value = ativo.querySelector('.cidade-ac-nome').textContent;
        fechar();
      } else if (e.key === 'Escape') {
        fechar();
      }
    });
  }

  /* ── inicializa ao carregar ── */
  document.addEventListener('DOMContentLoaded', function () {
    /* tenta carregar do sessionStorage (evita re-fetch na mesma sessão) */
    try {
      var stored = sessionStorage.getItem(CACHE_KEY);
      if (stored) cidadesCache = JSON.parse(stored);
    } catch (_) {}

    document.querySelectorAll('[data-cidade-ac]').forEach(initInput);
  });
})();
