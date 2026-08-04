<?php
/* ── Auditoria de acesso — dado sensível (LGPD art. 5º, II) exige rastreabilidade ── */
function camjc_log(string $acao, ?int $acolhida_id = null, string $detalhes = ''): void {
    try {
        db()->prepare("INSERT INTO camjc_acesso_log (usuario_id, acolhida_id, acao, detalhes, ip) VALUES (?,?,?,?,?)")
            ->execute([
                $_SESSION['usuario_id'] ?? null,
                $acolhida_id,
                $acao,
                $detalhes ?: null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
    } catch (Exception $e) {}
}

const CAMJC_STATUS = [
    'em_triagem'    => 'Em triagem',
    'acolhida'      => 'Acolhida',
    'alta'          => 'Alta',
    'evasao'        => 'Evasão',
    'transferencia' => 'Transferência',
    'nao_admitida'  => 'Não admitida',
];

function camjc_status_label(string $status): string {
    return CAMJC_STATUS[$status] ?? $status;
}

function camjc_status_cor(string $status): string {
    return [
        'em_triagem'    => '#a87d28',
        'acolhida'      => '#1e6b35',
        'alta'          => '#2563eb',
        'evasao'        => '#dc2626',
        'transferencia' => '#7c3aed',
        'nao_admitida'  => '#6b7280',
    ][$status] ?? '#6b7280';
}

/* ── Unidade padrão (ativa mais antiga) — usada até existir seletor de unidade na UI ── */
function camjc_unidade_padrao(): array {
    static $unidade = null;
    if ($unidade === null) {
        $unidade = db()->query("SELECT * FROM unidades WHERE ativo = 1 ORDER BY id ASC LIMIT 1")->fetch() ?: [];
    }
    return $unidade;
}
