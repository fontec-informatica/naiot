// Máscara de moeda em tempo real (estilo "centavos primeiro").
// Uso: <input class="money-input" ...> — aplicada automaticamente ao carregar a página.
(function () {
  function formatar(digitos) {
    if (!digitos) digitos = '0';
    var centavos = parseInt(digitos, 10);
    var valor = (centavos / 100).toFixed(2);
    var partes = valor.split('.');
    var inteiro = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return inteiro + ',' + partes[1];
  }

  function aplicarMascara(input) {
    input.addEventListener('input', function () {
      var digitos = this.value.replace(/\D/g, '');
      this.value = formatar(digitos);
    });
    if (input.value) {
      var digitosIniciais = input.value.replace(/\D/g, '');
      input.value = formatar(digitosIniciais);
    }
  }

  document.querySelectorAll('.money-input').forEach(aplicarMascara);
})();
