<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin']);

$msgs = [];

// Nota: diferente da maioria dos módulos deste portal (que usam MyISAM), aqui usamos
// InnoDB de propósito — dados de saúde/dependência química exigem integridade
// referencial (chaves estrangeiras) e trava em nível de linha, não de tabela.
try {
    $db = db();

    $db->exec("CREATE TABLE IF NOT EXISTS unidades (
        id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nome      VARCHAR(120) NOT NULL,
        ativo     TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $msgs[] = ['ok', 'Tabela unidades OK'];

    $n = (int)$db->query("SELECT COUNT(*) FROM unidades")->fetchColumn();
    if ($n === 0) {
        $db->prepare("INSERT INTO unidades (nome) VALUES (?)")->execute(['Casa das Mulheres']);
        $msgs[] = ['ok', 'Unidade "Casa das Mulheres" cadastrada'];
    }

    $db->exec("CREATE TABLE IF NOT EXISTS camjc_acolhidas (
        id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        unidade_id             INT UNSIGNED NOT NULL,
        nome                   VARCHAR(150) NOT NULL,
        data_nasc              DATE NULL,
        estado_civil           VARCHAR(30) NULL,
        rg                     VARCHAR(20) NULL,
        cpf                    VARCHAR(14) NULL,
        endereco               VARCHAR(200) NULL,
        bairro                 VARCHAR(100) NULL,
        cep                    VARCHAR(10) NULL,
        cidade                 VARCHAR(100) NULL,
        estado                 VARCHAR(2) NULL,
        telefone               VARCHAR(20) NULL,
        celular                VARCHAR(20) NULL,
        responsavel_nome       VARCHAR(150) NULL,
        responsavel_endereco   VARCHAR(200) NULL,
        responsavel_rg         VARCHAR(20) NULL,
        responsavel_cpf        VARCHAR(14) NULL,
        responsavel_data_nasc  DATE NULL,
        responsavel_telefone   VARCHAR(20) NULL,
        status                 VARCHAR(20) NOT NULL DEFAULT 'em_triagem',
        data_acolhimento       DATE NULL,
        criado_por             INT UNSIGNED NULL,
        criado_em              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em          DATETIME NULL,
        KEY idx_unidade (unidade_id),
        KEY idx_status (status),
        KEY idx_nome (nome),
        CONSTRAINT fk_acolhida_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $msgs[] = ['ok', 'Tabela camjc_acolhidas OK'];

    // Migração idempotente: adiciona coluna foto se ainda não existir
    // (foto é opcional na triagem, importante ao admitir — ver camjc/editar.php)
    $col = $db->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camjc_acolhidas' AND COLUMN_NAME = 'foto'
    ")->fetchColumn();
    if (!$col) {
        $db->exec("ALTER TABLE camjc_acolhidas ADD COLUMN foto VARCHAR(255) NULL AFTER nome");
        $msgs[] = ['ok', 'Coluna foto adicionada em camjc_acolhidas'];
    }

    $db->exec("CREATE TABLE IF NOT EXISTS camjc_triagens (
        id                              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        acolhida_id                     INT UNSIGNED NOT NULL,
        data_triagem                    DATE NOT NULL,
        motivo_encaminhamento           TEXT NULL,
        tipo_droga_padrao_uso           TEXT NULL,
        tentativas_tratamento           TEXT NULL,
        internacao_hospitalar           TEXT NULL,
        historico_sobriedade            TEXT NULL,
        transtorno_mental               TEXT NULL,
        comprometimento_biologico       TEXT NULL,
        deficiencia_fisica              TEXT NULL,
        hematomas_fraturas              TEXT NULL,
        tatuagens_cicatriz_piercing     TEXT NULL,
        restricao_atividade_fisica      TEXT NULL,
        tratamento_medico_outra_doenca  TEXT NULL,
        comprometimento_psicologico     TEXT NULL,
        tentativa_suicidio              TEXT NULL,
        beneficio_loas_aposentadoria    TEXT NULL,
        antecedentes_criminais          TEXT NULL,
        vinculos_familiares             TEXT NULL,
        mora_com_quem                   TEXT NULL,
        filhos                          TEXT NULL,
        rede_apoio                      TEXT NULL,
        moradia_pos_tratamento          TEXT NULL,
        observacoes                     TEXT NULL,
        responsavel_triagem_nome        VARCHAR(150) NULL,
        criado_por                      INT UNSIGNED NULL,
        criado_em                       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em                   DATETIME NULL,
        KEY idx_acolhida (acolhida_id),
        CONSTRAINT fk_triagem_acolhida FOREIGN KEY (acolhida_id) REFERENCES camjc_acolhidas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $msgs[] = ['ok', 'Tabela camjc_triagens OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS camjc_anexos (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        acolhida_id    INT UNSIGNED NOT NULL,
        triagem_id     INT UNSIGNED NULL,
        nome_original  VARCHAR(255) NOT NULL,
        nome_arquivo   VARCHAR(255) NOT NULL,
        tipo_mime      VARCHAR(100) NOT NULL,
        tamanho        INT UNSIGNED NOT NULL DEFAULT 0,
        tipo_doc       VARCHAR(50) NULL,
        criado_por     INT UNSIGNED NULL,
        criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_acolhida (acolhida_id),
        KEY idx_triagem (triagem_id),
        CONSTRAINT fk_anexo_acolhida FOREIGN KEY (acolhida_id) REFERENCES camjc_acolhidas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $msgs[] = ['ok', 'Tabela camjc_anexos OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS camjc_acesso_log (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id   INT UNSIGNED NULL,
        acolhida_id  INT UNSIGNED NULL,
        acao         VARCHAR(30) NOT NULL,
        detalhes     VARCHAR(255) NULL,
        ip           VARCHAR(45) NULL,
        criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_acolhida (acolhida_id),
        KEY idx_usuario (usuario_id),
        KEY idx_criado (criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $msgs[] = ['ok', 'Tabela camjc_acesso_log OK (auditoria de acesso)'];

} catch (Exception $e) {
    $msgs[] = ['erro', $e->getMessage()];
}

$titulo = 'Setup — Casa das Mulheres';
$pagina_ativa = 'camjc';
include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Setup — Módulo Casa das Mulheres (CAMJC)</h2>
  <?php foreach ($msgs as [$tipo, $msg]): ?>
    <div class="alerta <?= $tipo === 'ok' ? 'alerta-ok' : 'alerta-erro' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>
  <a href="/portal/camjc/" class="btn btn-primary" style="margin-top:16px">Ir para Casa das Mulheres →</a>
</div>
<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
