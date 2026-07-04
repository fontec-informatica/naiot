<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'estoque']);

$titulo       = 'Configurações — Estoque';
$pagina_ativa = 'estoque';
$erro = '';

$config = db()->query('SELECT * FROM estoque_config LIMIT 1')->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $loja_nome              = trim($_POST['loja_nome'] ?? '');
        $loja_documento         = trim($_POST['loja_documento'] ?? '');
        $loja_endereco          = trim($_POST['loja_endereco'] ?? '');
        $loja_telefone          = trim($_POST['loja_telefone'] ?? '');
        $cupom_largura_mm       = (int)($_POST['cupom_largura_mm'] ?? 80);
        $cupom_rodape_texto     = trim($_POST['cupom_rodape_texto'] ?? '');
        $prefixo_codigo_interno = trim($_POST['prefixo_codigo_interno'] ?? '') ?: 'INT';
        $estoque_minimo_padrao  = (int)($_POST['estoque_minimo_padrao'] ?? 5);

        if (!$loja_nome) {
            $erro = 'O nome da loja é obrigatório.';
        } else {
            db()->prepare('UPDATE estoque_config SET
                loja_nome=?, loja_documento=?, loja_endereco=?, loja_telefone=?,
                cupom_largura_mm=?, cupom_rodape_texto=?, prefixo_codigo_interno=?, estoque_minimo_padrao=?
                WHERE id=?')
                ->execute([
                    $loja_nome,
                    $loja_documento ?: null,
                    $loja_endereco ?: null,
                    $loja_telefone ?: null,
                    $cupom_largura_mm,
                    $cupom_rodape_texto ?: null,
                    $prefixo_codigo_interno,
                    $estoque_minimo_padrao,
                    $config['id'],
                ]);
            header('Location: /portal/estoque/configuracoes.php?salvo=1');
            exit;
        }
    }
}

include dirname(__DIR__) . '/_layout.php';
?>

<div style="margin-bottom:16px">
  <a href="/portal/estoque/" style="font-size:.82rem;color:var(--cinza3)">← Voltar para produtos</a>
</div>

<?php if (!empty($_GET['salvo'])): ?>
  <div class="alerta alerta-ok" style="margin-bottom:16px">Configurações salvas com sucesso.</div>
<?php endif; ?>

<div class="form-wrap">
  <h2>Configurações do módulo Estoque</h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <p style="font-size:.82rem;color:var(--cinza3);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Dados da loja (impressos no cupom)</p>

    <div class="form-group">
      <label for="loja_nome">Nome da loja</label>
      <input type="text" id="loja_nome" name="loja_nome"
             value="<?= htmlspecialchars($_POST['loja_nome'] ?? $config['loja_nome']) ?>" required>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="loja_documento">CNPJ/CPF <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="text" id="loja_documento" name="loja_documento"
               value="<?= htmlspecialchars($_POST['loja_documento'] ?? $config['loja_documento'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="loja_telefone">Telefone <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="text" id="loja_telefone" name="loja_telefone"
               value="<?= htmlspecialchars($_POST['loja_telefone'] ?? $config['loja_telefone'] ?? '') ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="loja_endereco">Endereço <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="text" id="loja_endereco" name="loja_endereco"
             value="<?= htmlspecialchars($_POST['loja_endereco'] ?? $config['loja_endereco'] ?? '') ?>">
    </div>

    <hr style="border:none;border-top:1px solid var(--cinza2);margin:8px 0 16px">
    <p style="font-size:.82rem;color:var(--cinza3);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Cupom não fiscal</p>

    <div class="form-row">
      <div class="form-group">
        <label for="cupom_largura_mm">Largura do papel</label>
        <select id="cupom_largura_mm" name="cupom_largura_mm">
          <?php foreach ([58, 80] as $mm): ?>
            <option value="<?= $mm ?>" <?= (int)($_POST['cupom_largura_mm'] ?? $config['cupom_largura_mm']) === $mm ? 'selected' : '' ?>><?= $mm ?>mm</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="cupom_rodape_texto">Texto de rodapé <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="text" id="cupom_rodape_texto" name="cupom_rodape_texto" placeholder="Ex: Volte sempre!"
               value="<?= htmlspecialchars($_POST['cupom_rodape_texto'] ?? $config['cupom_rodape_texto'] ?? '') ?>">
      </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--cinza2);margin:8px 0 16px">
    <p style="font-size:.82rem;color:var(--cinza3);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Produtos</p>

    <div class="form-row">
      <div class="form-group">
        <label for="prefixo_codigo_interno">Prefixo do código interno</label>
        <input type="text" id="prefixo_codigo_interno" name="prefixo_codigo_interno" maxlength="10"
               value="<?= htmlspecialchars($_POST['prefixo_codigo_interno'] ?? $config['prefixo_codigo_interno']) ?>">
        <span class="form-hint">Usado quando o produto não tem código de barras. Próximo número: <?= (int)$config['proximo_codigo_interno'] ?>.</span>
      </div>
      <div class="form-group">
        <label for="estoque_minimo_padrao">Estoque mínimo padrão</label>
        <input type="number" id="estoque_minimo_padrao" name="estoque_minimo_padrao" min="0"
               value="<?= htmlspecialchars($_POST['estoque_minimo_padrao'] ?? $config['estoque_minimo_padrao']) ?>">
        <span class="form-hint">Valor sugerido ao cadastrar um novo produto.</span>
      </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--cinza2);margin:8px 0 16px">
    <p style="font-size:.82rem;color:var(--cinza3);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Pagamento</p>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;color:var(--cinza3)">
        <input type="checkbox" disabled>
        Integração automática com maquininha (Stone Connect/TEF)
      </label>
      <span class="form-hint">Disponível quando a integração com a Stone for homologada. Por enquanto, registre manualmente a forma de pagamento em cada venda do PDV.</span>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Salvar configurações</button>
    </div>
  </form>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
