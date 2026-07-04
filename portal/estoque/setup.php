<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin']);

$msgs = [];

try {
    $db = db();

    $db->exec("CREATE TABLE IF NOT EXISTS estoque_config (
        id                          INT AUTO_INCREMENT PRIMARY KEY,
        loja_nome                   VARCHAR(150) NOT NULL DEFAULT 'NAIOT',
        loja_documento              VARCHAR(30)  DEFAULT NULL,
        loja_endereco               VARCHAR(255) DEFAULT NULL,
        loja_telefone               VARCHAR(30)  DEFAULT NULL,
        cupom_largura_mm            SMALLINT NOT NULL DEFAULT 80,
        cupom_rodape_texto          VARCHAR(255) DEFAULT NULL,
        prefixo_codigo_interno      VARCHAR(10) NOT NULL DEFAULT 'INT',
        proximo_codigo_interno      INT NOT NULL DEFAULT 1,
        estoque_minimo_padrao       INT NOT NULL DEFAULT 5,
        integracao_pagamento_ativa  TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $msgs[] = ['ok', 'Tabela estoque_config OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS estoque_categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        cor VARCHAR(7) DEFAULT '#6b7280',
        ativo TINYINT(1) DEFAULT 1,
        ordem INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $msgs[] = ['ok', 'Tabela estoque_categorias OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS estoque_produtos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        categoria_id INT DEFAULT NULL,
        nome VARCHAR(150) NOT NULL,
        descricao VARCHAR(255) DEFAULT NULL,
        codigo_barras VARCHAR(50) DEFAULT NULL,
        codigo_interno VARCHAR(30) NOT NULL,
        unidade VARCHAR(10) NOT NULL DEFAULT 'un',
        preco_custo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        preco_venda DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        estoque_atual INT NOT NULL DEFAULT 0,
        estoque_minimo INT NOT NULL DEFAULT 0,
        imagem VARCHAR(255) DEFAULT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_categoria (categoria_id),
        KEY idx_codigo_barras (codigo_barras),
        KEY idx_codigo_interno (codigo_interno),
        KEY idx_ativo (ativo),
        KEY idx_nome (nome),
        CONSTRAINT fk_produto_categoria FOREIGN KEY (categoria_id) REFERENCES estoque_categorias(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $msgs[] = ['ok', 'Tabela estoque_produtos OK'];

    $n = (int)$db->query("SELECT COUNT(*) FROM estoque_config")->fetchColumn();
    if ($n === 0) {
        $db->exec("INSERT INTO estoque_config (loja_nome) VALUES ('NAIOT')");
        $msgs[] = ['ok', 'Configuração padrão inserida'];
    }

    $n = (int)$db->query("SELECT COUNT(*) FROM estoque_categorias")->fetchColumn();
    if ($n === 0) {
        $cats = [
            ['Alimentos', '#d97706', 1],
            ['Bebidas',   '#2563eb', 2],
            ['Limpeza',   '#0d9488', 3],
            ['Outros',    '#6b7280', 4],
        ];
        $st = $db->prepare("INSERT INTO estoque_categorias (nome, cor, ordem) VALUES (?,?,?)");
        foreach ($cats as $c) $st->execute($c);
        $msgs[] = ['ok', count($cats) . ' categorias padrão inseridas'];
    }

} catch (Exception $e) {
    $msgs[] = ['erro', $e->getMessage()];
}

$titulo = 'Setup Livraria';
$pagina_ativa = 'estoque';
include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Setup — Módulo Livraria</h2>
  <?php foreach ($msgs as [$tipo, $msg]): ?>
    <div class="alerta <?= $tipo === 'ok' ? 'alerta-ok' : 'alerta-erro' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>
  <a href="/portal/estoque/" class="btn btn-primary" style="margin-top:16px">Ir para o Estoque →</a>
</div>
<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
