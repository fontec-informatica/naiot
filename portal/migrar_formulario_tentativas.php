<?php
require_once __DIR__ . '/auth.php';
requer_admin();

$pdo = db();
$msgs = [];

try {
    // Verifica se a tabela já existe
    $existe = $pdo->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'formulario_tentativas'
    ")->fetchColumn();

    if ($existe) {
        $msgs[] = ['ok', 'Tabela formulario_tentativas já existe. Nenhuma alteração necessária.'];
    } else {
        $pdo->exec("
            CREATE TABLE formulario_tentativas (
                id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip  VARCHAR(45) NOT NULL,
                em  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip (ip),
                INDEX idx_em (em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $msgs[] = ['ok', 'Tabela formulario_tentativas criada com sucesso.'];
        $msgs[] = ['ok', 'Agora os formulários públicos de oração e testemunho têm limite de envios por IP.'];
    }
} catch (Exception $e) {
    $msgs[] = ['erro', 'Erro: ' . $e->getMessage()];
}

$titulo = 'Migração — Rate Limit de Formulários Públicos';
$pagina_ativa = '';
include __DIR__ . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Migração: tabela formulario_tentativas</h2>
  <?php foreach ($msgs as [$tipo, $msg]): ?>
    <div class="alerta <?= $tipo === 'ok' ? 'alerta-ok' : 'alerta-erro' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>
  <a href="/portal/" class="btn btn-primary" style="margin-top:16px">← Voltar ao Dashboard</a>
</div>
<?php include __DIR__ . '/_layout_end.php'; ?>
