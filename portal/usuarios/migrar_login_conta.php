<?php
require_once dirname(__DIR__) . '/auth.php';
requer_admin();

$pdo = db();
$msgs = [];

try {
    $existe = $pdo->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'login_tentativas'
          AND COLUMN_NAME  = 'login'
    ")->fetchColumn();

    if ($existe) {
        $msgs[] = ['ok', 'Coluna login já existe. Nenhuma alteração necessária.'];
    } else {
        $pdo->exec("
            ALTER TABLE login_tentativas
            ADD COLUMN login VARCHAR(190) NULL DEFAULT NULL AFTER ip
        ");
        $pdo->exec("CREATE INDEX idx_login ON login_tentativas (login)");
        $msgs[] = ['ok', 'Coluna login adicionada com sucesso.'];
        $msgs[] = ['ok', 'A partir de agora, o rate limit de login passa a valer também por conta (e-mail/usuário), além de por IP — protege contra brute force distribuído por vários IPs contra uma mesma conta.'];
    }
} catch (Exception $e) {
    $msgs[] = ['erro', 'Erro: ' . $e->getMessage()];
}

$titulo = 'Migração — Rate limit por conta';
$pagina_ativa = 'usuarios';
include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Migração: rate limit por conta</h2>
  <?php foreach ($msgs as [$tipo, $msg]): ?>
    <div class="alerta <?= $tipo === 'ok' ? 'alerta-ok' : 'alerta-erro' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>
  <a href="/portal/usuarios/" class="btn btn-primary" style="margin-top:16px">← Voltar para Usuários</a>
</div>
<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
