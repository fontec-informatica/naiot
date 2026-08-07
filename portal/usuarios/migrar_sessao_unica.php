<?php
require_once dirname(__DIR__) . '/auth.php';
requer_admin();

$pdo = db();
$msgs = [];

try {
    $existe = $pdo->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'usuarios'
          AND COLUMN_NAME  = 'sessao_token'
    ")->fetchColumn();

    if ($existe) {
        $msgs[] = ['ok', 'Coluna sessao_token já existe. Nenhuma alteração necessária.'];
    } else {
        $pdo->exec("
            ALTER TABLE usuarios
            ADD COLUMN sessao_token VARCHAR(64) NULL DEFAULT NULL
            AFTER senha_hash
        ");
        $msgs[] = ['ok', 'Coluna sessao_token adicionada com sucesso.'];
        $msgs[] = ['ok', 'A partir de agora, um novo login de um usuário encerra automaticamente qualquer sessão anterior dele em outro dispositivo/navegador.'];
    }
} catch (Exception $e) {
    $msgs[] = ['erro', 'Erro: ' . $e->getMessage()];
}

$titulo = 'Migração — Sessão única por usuário';
$pagina_ativa = 'usuarios';
include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Migração: sessão única por usuário</h2>
  <?php foreach ($msgs as [$tipo, $msg]): ?>
    <div class="alerta <?= $tipo === 'ok' ? 'alerta-ok' : 'alerta-erro' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>
  <a href="/portal/usuarios/" class="btn btn-primary" style="margin-top:16px">← Voltar para Usuários</a>
</div>
<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
