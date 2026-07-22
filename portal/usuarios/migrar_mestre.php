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
          AND COLUMN_NAME  = 'mestre'
    ")->fetchColumn();

    if (!$existe) {
        $pdo->exec("
            ALTER TABLE usuarios
            ADD COLUMN mestre TINYINT(1) NOT NULL DEFAULT 0
            AFTER perfil
        ");
        $msgs[] = ['ok', 'Coluna mestre adicionada com sucesso.'];
    } else {
        $msgs[] = ['ok', 'Coluna mestre já existe. Nenhuma alteração necessária.'];
    }

    $ja_tem_mestre = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE mestre = 1")->fetchColumn();

    if ($ja_tem_mestre) {
        $msgs[] = ['ok', 'Já existe uma conta marcada como mestre. Nenhuma alteração necessária.'];
    } else {
        $st = $pdo->prepare("UPDATE usuarios SET mestre = 1 WHERE usuario = 'admin' LIMIT 1");
        $st->execute();

        if ($st->rowCount() === 0) {
            $st = $pdo->prepare("UPDATE usuarios SET mestre = 1 WHERE email = 'admin@naiot.com.br' LIMIT 1");
            $st->execute();
        }

        if ($st->rowCount() > 0) {
            $msgs[] = ['ok', 'Conta administradora principal marcada como mestre com sucesso.'];
        } else {
            $msgs[] = ['erro', 'Nenhuma conta com usuário "admin" ou e-mail "admin@naiot.com.br" foi encontrada. Marque manualmente qual conta é a mestre.'];
        }
    }
} catch (Exception $e) {
    $msgs[] = ['erro', 'Erro: ' . $e->getMessage()];
}

$titulo = 'Migração — Conta Mestre';
$pagina_ativa = 'usuarios';
include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Migração: conta mestre</h2>
  <?php foreach ($msgs as [$tipo, $msg]): ?>
    <div class="alerta <?= $tipo === 'ok' ? 'alerta-ok' : 'alerta-erro' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>
  <a href="/portal/usuarios/" class="btn btn-primary" style="margin-top:16px">← Voltar para Usuários</a>
</div>
<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
