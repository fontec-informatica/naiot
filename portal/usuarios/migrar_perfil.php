<?php
require_once dirname(__DIR__) . '/auth.php';
requer_admin();

$pdo = db();
$msgs = [];

try {
    // Verifica o tipo atual da coluna
    $col = $pdo->query("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'usuarios'
          AND COLUMN_NAME  = 'perfil'
    ")->fetchColumn();

    if (!$col) {
        $msgs[] = ['erro', 'Coluna perfil não encontrada na tabela usuarios.'];
    } elseif (stripos($col, 'varchar') !== false) {
        $msgs[] = ['ok', 'Coluna perfil já é VARCHAR(' . $col . '). Nenhuma alteração necessária.'];
    } else {
        // Altera de ENUM para VARCHAR(255) mantendo os dados existentes
        $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN perfil VARCHAR(255) NOT NULL DEFAULT 'secretaria'");
        $msgs[] = ['ok', "Coluna perfil migrada de {$col} para VARCHAR(255) com sucesso."];
        $msgs[] = ['ok', 'Agora é possível salvar permissões personalizadas por módulo.'];
    }
} catch (Exception $e) {
    $msgs[] = ['erro', 'Erro: ' . $e->getMessage()];
}

$titulo = 'Migração — Perfil de Usuário';
$pagina_ativa = 'usuarios';
include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Migração: coluna perfil</h2>
  <?php foreach ($msgs as [$tipo, $msg]): ?>
    <div class="alerta <?= $tipo === 'ok' ? 'alerta-ok' : 'alerta-erro' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>
  <a href="/portal/usuarios/" class="btn btn-primary" style="margin-top:16px">← Voltar para Usuários</a>
</div>
<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
