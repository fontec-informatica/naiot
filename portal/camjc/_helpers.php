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

/* ── Duração do programa terapêutico ── */
const CAMJC_DURACAO_PROGRAMA_MESES = 9;
const CAMJC_STATUS_SAIDA = ['alta', 'evasao', 'transferencia', 'nao_admitida'];

/** Previsão de conclusão do programa: data de acolhimento + 9 meses. */
function camjc_previsao_saida(string $data_acolhimento): string {
    $d = new DateTime($data_acolhimento);
    $d->modify('+' . CAMJC_DURACAO_PROGRAMA_MESES . ' months');
    return $d->format('Y-m-d');
}

/* ── Evolução mensal / PAS — escalas e listas compartilhadas ── */
const CAMJC_ESCALA_SATISFACAO = [
    'muito_insatisfatorio' => 'Muito insatisfatório',
    'insatisfatorio'       => 'Insatisfatório',
    'regular'               => 'Regular',
    'satisfatorio'          => 'Satisfatório',
    'muito_satisfatorio'    => 'Muito satisfatório',
];

const CAMJC_VINCULO_SITUACOES = [
    'visitas_regulares_acompanha'   => 'Visitas regulares e acompanha o processo de recuperação',
    'contato_distancia_acompanha'   => 'Contatos a distância e acompanha o processo de recuperação',
    'nao_fez_visitas_nao_acompanha' => 'Não fez visitas, contatos e não acompanha a recuperação',
    'nenhum_contato_ainda'          => 'Ainda não foi realizado contato familiar',
    'acolhida_nao_permite_contato'  => 'Acolhida não permite contato com familiares',
    'familia_sem_paradeiro'         => 'Família sem paradeiro / desconhecida',
    'familia_cadunico'              => 'Família inserida no CadÚnico',
    'nao_acompanhada_rede_protecao' => 'Não acompanhada por equipe da rede de proteção social',
];

const CAMJC_AVALIACAO_ACOLHIDA = [
    'aval_rotina'                       => 'Adesão e cumprimento ao manual de rotinas / normas de moradia',
    'aval_equipe'                       => 'Relacionamento com a equipe de trabalho',
    'aval_grupo'                        => 'Relacionamento com o grupo de acolhidas',
    'aval_organizacao'                  => 'Organização e higienização dos objetos e pertences pessoais',
    'aval_autocuidado'                  => 'Participação e pontualidade — atividades de autocuidado e sociabilidade',
    'aval_conscientizacao'              => 'Participação e pontualidade — conscientização sobre dependência química',
    'aval_atividades_fisicas'           => 'Participação e pontualidade — atividades físicas e desportivas',
    'aval_atendimentos_psicossociais'   => 'Participação e pontualidade — atendimentos psicossociais individuais/grupo',
    'aval_capacitacao_profissional'     => 'Participação e pontualidade — capacitação profissional',
    'aval_atividades_externas'          => 'Comportamento e disciplina nas atividades externas',
    'aval_geral'                        => 'Avaliação geral',
];

const CAMJC_ATIVIDADES_PAS = [
    'atend_psicologico_individual' => 'Atendimento psicológico individual',
    'atend_psicossocial_grupal'    => 'Atendimento psicossocial grupal / escuta terapêutica',
    'conscientizacao_dependencia'  => 'Atividades de conscientização sobre dependência química',
    'prevencao_recaida'            => 'Prevenção da recaída / treinamento de habilidades sociais',
    'grupos_mutua_ajuda'           => 'Grupos de mútua-ajuda — 12 passos (AA/NA)',
    'autocuidado_sociabilidade'    => 'Atividades de promoção de autocuidado e sociabilidade',
    'atividades_fisicas'           => 'Atividades físicas e desportivas',
    'atividades_recreativas'       => 'Atividades recreativas, artísticas ou culturais',
    'fortalecimento_vinculos'      => 'Fortalecimento e restabelecimento de vínculos familiares',
    'elevacao_escolaridade'        => 'Atividades de elevação da escolaridade',
    'qualificacao_profissional'    => 'Atividades de qualificação e requalificação profissional',
    'atividades_remuneradas'       => 'Atividades remuneradas (autonomia e auto sustento)',
    'rede_servicos_locais'         => 'Participação nos atendimentos da rede de serviços locais',
];

const CAMJC_ENCAMINHAMENTOS_PAS = [
    'cadastramento_cadunico'  => 'Cadastramento CadÚnico',
    'rede_saude'               => 'Rede saúde',
    'grupos_apoio'             => 'Grupos de apoio',
    'retirada_documentos'      => 'Retirada de documentos',
    'escolarizacao'            => 'Escolarização / qualificação profissional',
    'rede_protecao_social'     => 'Rede proteção social',
    'insercao_mercado_trabalho' => 'Inserção no mercado de trabalho',
];

const CAMJC_PERCEPCAO_AREAS = [
    'emocional'    => 'Emocional / psicológica',
    'intelectual'  => 'Intelectual / acadêmica',
    'fisica'       => 'Física',
    'familiar'     => 'Familiar (relacionamentos)',
    'afetiva'      => 'Afetiva (relacionamentos íntimos)',
    'espiritual'   => 'Espiritual',
    'profissional' => 'Profissional',
    'social'       => 'Social / lazer',
];

function camjc_escala_label(?string $valor): string {
    return $valor ? (CAMJC_ESCALA_SATISFACAO[$valor] ?? $valor) : '';
}
