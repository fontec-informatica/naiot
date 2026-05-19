<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin']);

$msgs = [];

try {
    $db = db();

    $db->exec("CREATE TABLE IF NOT EXISTS financeiro_categorias (
        id      INT AUTO_INCREMENT PRIMARY KEY,
        nome    VARCHAR(100) NOT NULL,
        tipo    ENUM('receita','despesa') NOT NULL,
        cor     VARCHAR(7) DEFAULT '#6b7280',
        ativo   TINYINT(1) DEFAULT 1,
        ordem   INT DEFAULT 0
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
    $msgs[] = ['ok','Tabela financeiro_categorias OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS financeiro_lancamentos (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        tipo              ENUM('receita','despesa') NOT NULL,
        categoria_id      INT NOT NULL,
        descricao         VARCHAR(255) NOT NULL,
        valor             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        data_lancamento   DATE NOT NULL,
        competencia_mes   TINYINT NOT NULL,
        competencia_ano   SMALLINT NOT NULL,
        status            ENUM('realizado','pendente','cancelado') DEFAULT 'realizado',
        forma_pagamento   ENUM('dinheiro','pix','transferencia','boleto','cartao','cheque','outro') DEFAULT 'dinheiro',
        origem            VARCHAR(50) DEFAULT NULL,
        recorrente_id     INT DEFAULT NULL,
        observacoes       TEXT DEFAULT NULL,
        usuario_id        INT DEFAULT NULL,
        created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_competencia (competencia_ano, competencia_mes),
        KEY idx_data (data_lancamento),
        KEY idx_tipo (tipo),
        KEY idx_status (status)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
    $msgs[] = ['ok','Tabela financeiro_lancamentos OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS financeiro_anexos (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        lancamento_id  INT NOT NULL,
        nome_original  VARCHAR(255) NOT NULL,
        nome_arquivo   VARCHAR(255) NOT NULL,
        tipo_mime      VARCHAR(100) NOT NULL,
        tamanho        INT NOT NULL DEFAULT 0,
        tipo_doc       ENUM('nf','comprovante','recibo','foto','outro') DEFAULT 'outro',
        created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_lanc (lancamento_id)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
    $msgs[] = ['ok','Tabela financeiro_anexos OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS financeiro_recorrentes (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        descricao        VARCHAR(255) NOT NULL,
        tipo             ENUM('receita','despesa') NOT NULL,
        categoria_id     INT NOT NULL,
        valor            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        dia_vencimento   TINYINT NOT NULL DEFAULT 1,
        forma_pagamento  ENUM('dinheiro','pix','transferencia','boleto','cartao','cheque','outro') DEFAULT 'pix',
        status           ENUM('ativo','inativo') DEFAULT 'ativo',
        proximo_vencimento DATE DEFAULT NULL,
        observacoes      TEXT DEFAULT NULL,
        created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_status (status),
        KEY idx_proximo (proximo_vencimento)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
    $msgs[] = ['ok','Tabela financeiro_recorrentes OK'];

    $n = (int)$db->query("SELECT COUNT(*) FROM financeiro_categorias")->fetchColumn();
    if ($n === 0) {
        $cats = [
            ['Doações',               'receita', '#16a34a', 1],
            ['Dízimos',               'receita', '#15803d', 2],
            ['Contribuições',         'receita', '#0d9488', 3],
            ['Inscrições em eventos', 'receita', '#0891b2', 4],
            ['Mensalidades',          'receita', '#2563eb', 5],
            ['Outras receitas',       'receita', '#6b7280', 6],
            ['Alimentação',           'despesa', '#dc2626', 1],
            ['Material pastoral',     'despesa', '#9333ea', 2],
            ['Comunicação/Marketing', 'despesa', '#7c3aed', 3],
            ['Sede / Aluguel',        'despesa', '#d97706', 4],
            ['Água / Luz / Internet', 'despesa', '#0284c7', 5],
            ['Transporte',            'despesa', '#0f766e', 6],
            ['Outras despesas',       'despesa', '#6b7280', 7],
        ];
        $st = $db->prepare("INSERT INTO financeiro_categorias (nome,tipo,cor,ordem) VALUES (?,?,?,?)");
        foreach ($cats as $c) $st->execute($c);
        $msgs[] = ['ok', count($cats) . ' categorias padrão inseridas'];
    }

} catch (Exception $e) {
    $msgs[] = ['erro', $e->getMessage()];
}

$titulo = 'Setup Financeiro';
$pagina_ativa = 'financeiro';
include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Setup — Módulo Financeiro</h2>
  <?php foreach ($msgs as [$tipo, $msg]): ?>
    <div class="alerta <?= $tipo === 'ok' ? 'alerta-ok' : 'alerta-erro' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>
  <a href="/portal/financeiro/" class="btn btn-primary" style="margin-top:16px">Ir para o Financeiro →</a>
</div>
<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
