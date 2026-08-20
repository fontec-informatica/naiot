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
        complemento            VARCHAR(100) NULL,
        bairro                 VARCHAR(100) NULL,
        cep                    VARCHAR(10) NULL,
        cidade                 VARCHAR(100) NULL,
        estado                 VARCHAR(2) NULL,
        telefone               VARCHAR(20) NULL,
        celular                VARCHAR(20) NULL,
        responsavel_nome       VARCHAR(150) NULL,
        responsavel_endereco   VARCHAR(200) NULL,
        responsavel_complemento VARCHAR(100) NULL,
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

    // Migração idempotente: complemento de endereço (acolhida e responsável)
    $col = $db->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camjc_acolhidas' AND COLUMN_NAME = 'complemento'
    ")->fetchColumn();
    if (!$col) {
        $db->exec("ALTER TABLE camjc_acolhidas ADD COLUMN complemento VARCHAR(100) NULL AFTER endereco");
        $msgs[] = ['ok', 'Coluna complemento adicionada em camjc_acolhidas'];
    }
    $col = $db->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camjc_acolhidas' AND COLUMN_NAME = 'responsavel_complemento'
    ")->fetchColumn();
    if (!$col) {
        $db->exec("ALTER TABLE camjc_acolhidas ADD COLUMN responsavel_complemento VARCHAR(100) NULL AFTER responsavel_endereco");
        $msgs[] = ['ok', 'Coluna responsavel_complemento adicionada em camjc_acolhidas'];
    }

    // Migração idempotente: data e motivo de saída (alta/evasão/transferência/não admitida)
    $col = $db->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camjc_acolhidas' AND COLUMN_NAME = 'data_saida'
    ")->fetchColumn();
    if (!$col) {
        $db->exec("ALTER TABLE camjc_acolhidas ADD COLUMN data_saida DATE NULL AFTER data_acolhimento");
        $db->exec("ALTER TABLE camjc_acolhidas ADD COLUMN motivo_saida TEXT NULL AFTER data_saida");
        $msgs[] = ['ok', 'Colunas data_saida e motivo_saida adicionadas em camjc_acolhidas'];
    }

    // Migração idempotente: exclusão (lixeira) — nunca apaga dado sensível sem antes
    // passar por soft-delete; excluido_em preenchido = registro na lixeira.
    $col = $db->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camjc_acolhidas' AND COLUMN_NAME = 'excluido_em'
    ")->fetchColumn();
    if (!$col) {
        $db->exec("ALTER TABLE camjc_acolhidas ADD COLUMN excluido_em DATETIME NULL AFTER atualizado_em");
        $db->exec("ALTER TABLE camjc_acolhidas ADD COLUMN excluido_por INT UNSIGNED NULL AFTER excluido_em");
        $db->exec("ALTER TABLE camjc_acolhidas ADD KEY idx_excluido (excluido_em)");
        $msgs[] = ['ok', 'Colunas excluido_em e excluido_por adicionadas em camjc_acolhidas (lixeira)'];
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

    $db->exec("CREATE TABLE IF NOT EXISTS camjc_anamneses (
        id                              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        acolhida_id                     INT UNSIGNED NOT NULL,
        data_anamnese                   DATE NOT NULL,
        nascimento_complicacoes         TEXT NULL,
        familia_obitos                  TEXT NULL,
        familia_uso_substancias         TEXT NULL,
        familia_atitudes                TEXT NULL,
        familia_ambiente                TEXT NULL,
        infancia                        TEXT NULL,
        marital_sexual                  TEXT NULL,
        filhos                          TEXT NULL,
        historia_forense                TEXT NULL,
        droga_primeira_vez              TEXT NULL,
        droga_evolucao                  TEXT NULL,
        droga_percepcao_problema        TEXT NULL,
        droga_padrao_atual              TEXT NULL,
        droga_abstinencia_primeira_vez  TEXT NULL,
        droga_periodos_sobriedade       TEXT NULL,
        droga_ultimo_uso                TEXT NULL,
        parecer_equipe                  TEXT NULL,
        criado_por                      INT UNSIGNED NULL,
        criado_em                       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em                   DATETIME NULL,
        KEY idx_acolhida (acolhida_id),
        CONSTRAINT fk_anamnese_acolhida FOREIGN KEY (acolhida_id) REFERENCES camjc_acolhidas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $msgs[] = ['ok', 'Tabela camjc_anamneses OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS camjc_pas (
        id                              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        acolhida_id                     INT UNSIGNED NOT NULL,
        data_avaliacao                  DATE NOT NULL,
        houve_alteracao_medicacao       VARCHAR(10) NULL,
        medicamentos                    TEXT NULL,
        vinculo_situacao                VARCHAR(50) NULL,
        vinculo_qualidade               VARCHAR(30) NULL,
        familiares_contato              TEXT NULL,
        aval_rotina                     VARCHAR(30) NULL,
        aval_equipe                     VARCHAR(30) NULL,
        aval_grupo                      VARCHAR(30) NULL,
        aval_organizacao                VARCHAR(30) NULL,
        aval_autocuidado                VARCHAR(30) NULL,
        aval_conscientizacao            VARCHAR(30) NULL,
        aval_atividades_fisicas         VARCHAR(30) NULL,
        aval_atendimentos_psicossociais VARCHAR(30) NULL,
        aval_capacitacao_profissional   VARCHAR(30) NULL,
        aval_atividades_externas        VARCHAR(30) NULL,
        aval_geral                      VARCHAR(30) NULL,
        atividades                      TEXT NULL,
        encaminhamentos                 TEXT NULL,
        percepcao_emocional             VARCHAR(30) NULL,
        percepcao_emocional_melhorar    TEXT NULL,
        percepcao_intelectual           VARCHAR(30) NULL,
        percepcao_intelectual_melhorar  TEXT NULL,
        percepcao_fisica                VARCHAR(30) NULL,
        percepcao_fisica_melhorar       TEXT NULL,
        percepcao_familiar              VARCHAR(30) NULL,
        percepcao_familiar_melhorar     TEXT NULL,
        percepcao_afetiva               VARCHAR(30) NULL,
        percepcao_afetiva_melhorar      TEXT NULL,
        percepcao_espiritual            VARCHAR(30) NULL,
        percepcao_espiritual_melhorar   TEXT NULL,
        percepcao_profissional          VARCHAR(30) NULL,
        percepcao_profissional_melhorar TEXT NULL,
        percepcao_social                VARCHAR(30) NULL,
        percepcao_social_melhorar       TEXT NULL,
        importancia_mudanca             TINYINT UNSIGNED NULL,
        confianca_abstinencia           TINYINT UNSIGNED NULL,
        requerimentos_acolhida          TEXT NULL,
        parecer_profissional            TEXT NULL,
        profissional_nome               VARCHAR(150) NULL,
        criado_por                      INT UNSIGNED NULL,
        criado_em                       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em                   DATETIME NULL,
        KEY idx_acolhida (acolhida_id),
        CONSTRAINT fk_pas_acolhida FOREIGN KEY (acolhida_id) REFERENCES camjc_acolhidas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $msgs[] = ['ok', 'Tabela camjc_pas OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS camjc_projetos_vida (
        id                              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        acolhida_id                     INT UNSIGNED NOT NULL,
        data_projeto                    DATE NOT NULL,
        valores_base                    TEXT NULL,
        pontos_fortes                   TEXT NULL,
        pontos_melhorar                 TEXT NULL,
        oportunidades                   TEXT NULL,
        ameacas                         TEXT NULL,
        tempo_planejamento              TEXT NULL,
        recursos_financeiros            TEXT NULL,
        missao                          TEXT NULL,
        saude_fisica_atual              TEXT NULL,
        saude_fisica_alimentacao        TEXT NULL,
        saude_fisica_sono               TEXT NULL,
        saude_fisica_meta               TEXT NULL,
        saude_fisica_pratica            TEXT NULL,
        saude_espiritual_estabilidade   TEXT NULL,
        saude_espiritual_interior       TEXT NULL,
        saude_espiritual_capela         TEXT NULL,
        saude_espiritual_oracao         TEXT NULL,
        saude_espiritual_metas          TEXT NULL,
        saude_intelectual_leituras      TEXT NULL,
        saude_intelectual_curso         TEXT NULL,
        saude_intelectual_estudo        TEXT NULL,
        saude_familiar_tratamento       TEXT NULL,
        saude_familiar_impedimento      TEXT NULL,
        saude_familiar_reconciliacao    TEXT NULL,
        saude_social_sociedade          TEXT NULL,
        saude_social_ajuda              TEXT NULL,
        saude_social_cidadania          TEXT NULL,
        saude_financeira_recursos       TEXT NULL,
        saude_financeira_profissao      TEXT NULL,
        saude_financeira_planejamento   TEXT NULL,
        metas                           TEXT NULL,
        criado_por                      INT UNSIGNED NULL,
        criado_em                       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em                   DATETIME NULL,
        KEY idx_acolhida (acolhida_id),
        CONSTRAINT fk_projetovida_acolhida FOREIGN KEY (acolhida_id) REFERENCES camjc_acolhidas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $msgs[] = ['ok', 'Tabela camjc_projetos_vida OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS camjc_ressocializacao (
        id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        acolhida_id          INT UNSIGNED NOT NULL,
        nome_familiar        VARCHAR(150) NULL,
        grau_parentesco      VARCHAR(80) NULL,
        numero_visita        VARCHAR(10) NULL,
        data_resposta        DATE NOT NULL,
        respostas             TEXT NULL,
        observacoes_finais   TEXT NULL,
        criado_por           INT UNSIGNED NULL,
        criado_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em        DATETIME NULL,
        KEY idx_acolhida (acolhida_id),
        CONSTRAINT fk_ressoc_acolhida FOREIGN KEY (acolhida_id) REFERENCES camjc_acolhidas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $msgs[] = ['ok', 'Tabela camjc_ressocializacao OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS camjc_termos_assinados (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        acolhida_id     INT UNSIGNED NOT NULL,
        tipo            VARCHAR(40) NOT NULL,
        data_assinatura DATE NOT NULL,
        observacoes     TEXT NULL,
        criado_por      INT UNSIGNED NULL,
        criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_acolhida (acolhida_id),
        KEY idx_tipo (tipo),
        CONSTRAINT fk_termo_acolhida FOREIGN KEY (acolhida_id) REFERENCES camjc_acolhidas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $msgs[] = ['ok', 'Tabela camjc_termos_assinados OK'];

    $db->exec("CREATE TABLE IF NOT EXISTS camjc_saidas_temporarias (
        id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        acolhida_id            INT UNSIGNED NOT NULL,
        data_saida             DATE NOT NULL,
        destino_motivo         TEXT NULL,
        responsavel_nome       VARCHAR(150) NULL,
        responsavel_rg         VARCHAR(20) NULL,
        responsavel_cpf        VARCHAR(14) NULL,
        responsavel_telefone   VARCHAR(20) NULL,
        data_retorno_prevista  DATE NULL,
        data_retorno_real      DATE NULL,
        observacoes            TEXT NULL,
        criado_por             INT UNSIGNED NULL,
        criado_em              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em          DATETIME NULL,
        KEY idx_acolhida (acolhida_id),
        CONSTRAINT fk_saidatemp_acolhida FOREIGN KEY (acolhida_id) REFERENCES camjc_acolhidas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $msgs[] = ['ok', 'Tabela camjc_saidas_temporarias OK'];

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
