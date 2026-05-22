<?php
require_once dirname(__DIR__) . '/auth.php';
requer_admin();

$pdo = db();
$msgs = [];

try {
    // Verifica se coluna já existe
    $existe = $pdo->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'usuarios'
          AND COLUMN_NAME  = 'usuario'
    ")->fetchColumn();

    if ($existe) {
        $msgs[] = ['ok', 'Coluna usuario já existe. Nenhuma alteração necessária.'];
    } else {
        $pdo->exec("
            ALTER TABLE usuarios
            ADD COLUMN usuario VARCHAR(50) NULL UNIQUE
            AFTER nome
        ");
        $msgs[] = ['ok', 'Coluna usuario adicionada com sucesso.'];
        $msgs[] = ['ok', 'Usuários existentes ficam sem nome de usuário até que um admin defina na edição.'];
    }
} catch (Exception $e) {
    $msgs[] = ['erro', 'Erro: ' . $e->getMessage()];
}

$titulo = 'Migração — Nome de Usuário';
$pagina_ativa = 'usuarios';
include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Migração: coluna usuario</h2>
  <?php foreach ($msgs as [$tipo, $msg]): ?>
    <div class="alerta <?= $tipo === 'ok' ? 'alerta-ok' : 'alerta-erro' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>
  <a href="/portal/usuarios/" class="btn btn-primary" style="margin-top:16px">← Voltar para Usuários</a>
</div>
<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
