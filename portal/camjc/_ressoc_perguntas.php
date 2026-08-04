<?php
/**
 * Estrutura de perguntas da Avaliação de Ressocialização (Família).
 * ~90 perguntas em 23 seções, agrupadas em 8 abas temáticas.
 * Cada pergunta: 'texto' + ('opcoes' => múltipla escolha) ou ('tipo' => 'texto' para livre).
 */

const CAMJC_RESSOC_GRUPOS = [

    'risco' => [
        'label' => 'Situações de risco e uso',
        'secoes' => [
            '1' => [
                'titulo' => '1. Situações de risco espontâneas',
                'perguntas' => [
                    '1_1' => ['texto' => '1.1 A acolhida se encontrou, sem intenção prévia, com algum companheiro de uso ativo?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '1_2' => ['texto' => '1.2 Qual a atitude dela para com eles? (não responder se anterior for "não sei"/"não")', 'opcoes' => ['a'=>'Não deu atenção','b'=>'Cumprimentou, mas logo saiu','c'=>'Cumprimentou, conversou um tempo e saiu sem demora','d'=>'Conversou demoradamente e marcou novo encontro','e'=>'Outra']],
                    '1_3' => ['texto' => '1.3 Teve contato com álcool e/ou drogas (sem uso) sem intenção prévia?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '1_4' => ['texto' => '1.4 Qual a atitude dela perante esse contato?', 'opcoes' => ['a'=>'Não deu atenção','b'=>'Teve desejo de consumir, mas se controlou','c'=>'Teve desejo de consumir, e falou a respeito','d'=>'Ficou abalada, mas não disse nada','e'=>'Ficou abalada e mudou o comportamento depois','f'=>'Outra']],
                ],
            ],
            '2' => [
                'titulo' => '2. Situações de risco provocadas',
                'perguntas' => [
                    '2_1' => ['texto' => '2.1 Se encontrou, com intenção prévia, com algum companheiro de uso ativo?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '2_2' => ['texto' => '2.2 Qual a atitude para com eles?', 'opcoes' => ['a'=>'Não deu muita atenção','b'=>'Cumprimentou, mas logo saiu','c'=>'Cumprimentou, conversou um tempo e saiu sem demora','d'=>'Conversou demoradamente e marcou novo encontro','e'=>'Saiu com eles, atitude suspeita','f'=>'Outra']],
                    '2_3' => ['texto' => '2.3 Esteve em locais de uso (bares, bocas, etc.)?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '2_4' => ['texto' => '2.4 Esteve em baladas/barzinhos sem companhia apropriada?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '2_5' => ['texto' => '2.5 Teve contato com álcool/drogas (sem uso) com intenção prévia?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '2_6' => ['texto' => '2.6 Qual a atitude dela perante esse contato?', 'opcoes' => ['a'=>'Não deu atenção','b'=>'Teve desejo de consumir, mas se controlou','c'=>'Teve desejo de consumir, e falou a respeito','d'=>'Ficou abalada, mas não disse nada','e'=>'Ficou abalada e mudou o comportamento depois','f'=>'Outra']],
                ],
            ],
            '3' => [
                'titulo' => '3. Uso de álcool/drogas',
                'perguntas' => [
                    '3_1' => ['texto' => '3.1 Fez uso de álcool/drogas em algum momento durante a visita?', 'opcoes' => ['a'=>'Tenho certeza que não','b'=>'Acho que não, sem sinais','c'=>'Sinto que fez uso em ao menos 1 ocasião, sem certeza','d'=>'Tenho certeza que fez uso em 1 ocasião','e'=>'Tenho certeza que fez uso em mais de 1 ocasião']],
                ],
            ],
        ],
    ],

    'retorno' => [
        'label' => 'Retorno, mentiras e emoções',
        'secoes' => [
            '4' => [
                'titulo' => '4. Retorno à Comunidade',
                'perguntas' => [
                    '4_1' => ['texto' => '4.1 Como foi o retorno à Comunidade para a acolhida?', 'opcoes' => ['a'=>'Normal, não relutou','b'=>'Relutou um pouco, mas se acalmou','c'=>'Não queria voltar, precisou convencer, depois ficou bem','d'=>'Não queria voltar, muita dificuldade, voltou chateada/com raiva','e'=>'Voltou antes do tempo, por vontade própria','f'=>'Queria voltar antes, mas se acalmou','g'=>'Queria voltar antes, ficou ansiosa']],
                    '4_2' => ['texto' => '4.2 Como foi o retorno da acolhida para a família?', 'opcoes' => ['a'=>'Normal, queríamos que voltasse no tempo certo','b'=>'Relutamos um pouco, mas compreendemos','c'=>'Alguns familiares não queriam, precisou convencer','d'=>'Queríamos que voltasse antes do tempo indicado']],
                ],
            ],
            '5' => [
                'titulo' => '5. Mentiras',
                'perguntas' => [
                    '5_1' => ['texto' => '5.1 Perceberam alguma mentira para se beneficiar ou evitar consequências?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '5_2' => ['texto' => '5.2 Que tipo de mentira?', 'tipo' => 'texto'],
                    '5_3' => ['texto' => '5.3 Fez alguma atividade escondida, ou esteve com alguém/local que depois não quis contar?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '5_4' => ['texto' => '5.4 Que tipo de atividade, pessoa ou local?', 'tipo' => 'texto'],
                    '5_5' => ['texto' => '5.5 Assumiu alguma mentira ou omissão anterior?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '5_6' => ['texto' => '5.6 Que tipo de mentira ou omissão?', 'tipo' => 'texto'],
                ],
            ],
            '9' => [
                'titulo' => '9. Emoções descontroladas',
                'perguntas' => [
                    '9_1' => ['texto' => '9.1 Sintomas de euforia (fala agitada, agitação, desconforto)?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '9_2' => ['texto' => '9.2 Sintomas de depressão (quietude excessiva, isolamento, choro sem motivo)?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '9_3' => ['texto' => '9.3 Raiva ou reações violentas sem motivo aparente ou desproporcional?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '9_4' => ['texto' => '9.4 Tristeza/desânimo sem motivo aparente ou desproporcional?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '9_5' => ['texto' => '9.5 Alguma frustração que a abalou exageradamente?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '9_6' => ['texto' => '9.6 Qual?', 'tipo' => 'texto'],
                ],
            ],
        ],
    ],

    'espiritual' => [
        'label' => 'Espiritualidade e grupos de apoio',
        'secoes' => [
            '6' => [
                'titulo' => '6. Busca espiritual individual',
                'perguntas' => [
                    '6_1' => ['texto' => '6.1 Fez oração, meditação ou reflexão?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '6_2' => ['texto' => '6.2 Onde geralmente fez isso?', 'opcoes' => ['a'=>'Em casa','b'=>'Numa Igreja/Templo','c'=>'Em casa e na Igreja','d'=>'Outros']],
                    '6_3' => ['texto' => '6.3 Foi cuidadosa nas atitudes com os outros (evitando agressões/desrespeito)?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Mais cuidadosa com pessoas em geral, menos com a família','d'=>'Mais cuidadosa com a família, menos com outras pessoas','e'=>'Cuidadosa com todos']],
                    '6_4' => ['texto' => '6.4 Fez coisas só para agradar, contrariando a própria vontade?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez, sem parecer se incomodar','d'=>'Sim, algumas vezes, sem parecer se incomodar','e'=>'Sim, 1 vez, parecendo incomodada','f'=>'Sim, algumas vezes, parecendo incomodada']],
                ],
            ],
            '7' => [
                'titulo' => '7. Participação religiosa',
                'perguntas' => [
                    '7_1' => ['texto' => '7.1 Participou de alguma atividade religiosa?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '7_2' => ['texto' => '7.2 Teve interesse verdadeiro em participar?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Pouco interesse, precisou insistir','d'=>'Participou com naturalidade, sem muito entusiasmo','e'=>'Muito entusiasmo, sem precisar insistir']],
                    '7_3' => ['texto' => '7.3 Fez orações individuais ou leitura/estudo religioso?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '7_4' => ['texto' => '7.4 Esteve com pessoas da Igreja dela?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                ],
            ],
            '8' => [
                'titulo' => '8. Participação em grupos de apoio',
                'perguntas' => [
                    '8_1' => ['texto' => '8.1 Participou de algum grupo de apoio?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '8_2' => ['texto' => '8.2 Teve interesse verdadeiro em participar?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Pouco interesse, precisou insistir','d'=>'Participou com naturalidade, sem muito entusiasmo','e'=>'Muito entusiasmo, sem precisar insistir']],
                    '8_3' => ['texto' => '8.3 Esteve com pessoas do grupo de apoio?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                ],
            ],
        ],
    ],

    'familia' => [
        'label' => 'Família de origem e constituída',
        'secoes' => [
            '10' => [
                'titulo' => '10. Família de origem (pais e irmãos)',
                'perguntas' => [
                    '10_1' => ['texto' => '10.1 Teve contato com o pai/padrasto?', 'opcoes' => ['a'=>'Não teve — faleceu/sem informação','b'=>'Não teve — pai não quer vê-la (dependência química)','c'=>'Não teve — por vontade própria','d'=>'Restrito — barreiras do pai','e'=>'Restrito — por vontade própria','f'=>'Frequente']],
                    '10_2' => ['texto' => '10.2 Como foi o relacionamento com o pai?', 'opcoes' => ['a'=>'Não sei','b'=>'Superficial, sem muito diálogo','c'=>'Conflitivo, muitas brigas/cobranças','d'=>'Saudável, diálogo natural']],
                    '10_3' => ['texto' => '10.3 Teve contato com a mãe/madrasta?', 'opcoes' => ['a'=>'Não teve — faleceu/sem informação','b'=>'Não teve — mãe não quer vê-la (dependência química)','c'=>'Não teve — por vontade própria','d'=>'Restrito — barreiras da mãe','e'=>'Restrito — por vontade própria','f'=>'Frequente']],
                    '10_4' => ['texto' => '10.4 Como foi o relacionamento com a mãe?', 'opcoes' => ['a'=>'Não sei','b'=>'Superficial, sem muito diálogo','c'=>'Conflitivo, muitas brigas/cobranças','d'=>'Saudável, diálogo natural']],
                    '10_5' => ['texto' => '10.5 Teve contato com os irmãos?', 'opcoes' => ['a'=>'Não teve — faleceram/sem informação','b'=>'Não teve — irmãos não querem vê-la','c'=>'Não teve — por vontade própria','d'=>'Restrito com alguns — outros não querem vê-la','e'=>'Restrito com alguns — por vontade própria','f'=>'Frequente com alguns — outros não querem vê-la','g'=>'Frequente com alguns — por vontade própria','h'=>'Frequente com todos']],
                    '10_6' => ['texto' => '10.6 Como foi o relacionamento com os irmãos?', 'opcoes' => ['a'=>'Não sei','b'=>'Superficial com todos','c'=>'Conflitivo com todos','d'=>'Superficial com alguns, conflitivo com outros','e'=>'Superficial com alguns, saudável com outros','f'=>'Conflitivo com alguns, saudável com outros','g'=>'Superficial/conflitivo/saudável misto','h'=>'Saudável com todos']],
                ],
            ],
            '11' => [
                'titulo' => '11. Família constituída (esposo/ex e filhos)',
                'perguntas' => [
                    '11_1' => ['texto' => '11.1 Teve contato com o esposo/ex esposo?', 'opcoes' => ['a'=>'Não tem esposo/ex','b'=>'Não teve — faleceu/sem informação','c'=>'Não teve — ele não quer vê-la (dependência química)','d'=>'Não teve — por vontade própria','e'=>'Restrito — resistência dele','f'=>'Restrito — resistência própria','g'=>'Frequente']],
                    '11_2' => ['texto' => '11.2 Como foi o relacionamento com o esposo/ex?', 'opcoes' => ['a'=>'Não sei','b'=>'Superficial, sem muito diálogo','c'=>'Conflitivo, muitas brigas/cobranças','d'=>'Saudável, diálogo natural']],
                    '11_3' => ['texto' => '11.3 Teve contato com os filhos?', 'opcoes' => ['a'=>'Não tem filhos','b'=>'Não teve — faleceram/sem informação','c'=>'Não teve — eles não querem vê-la','d'=>'Não teve — por vontade própria','e'=>'Restrito com alguns — outros não querem vê-la','f'=>'Restrito com alguns — por vontade própria','g'=>'Frequente com alguns — outros não querem vê-la','h'=>'Frequente com alguns — por vontade própria','i'=>'Frequente com todos']],
                    '11_4' => ['texto' => '11.4 Como foi o relacionamento com os filhos?', 'opcoes' => ['a'=>'Não sei','b'=>'Superficial com todos','c'=>'Conflitivo com todos','d'=>'Superficial com alguns, conflitivo com outros','e'=>'Superficial com alguns, saudável com outros','f'=>'Conflitivo com alguns, saudável com outros','g'=>'Misto','h'=>'Saudável com todos']],
                ],
            ],
        ],
    ],

    'relacionamentos' => [
        'label' => 'Relacionamentos e sexualidade',
        'secoes' => [
            '12' => [
                'titulo' => '12. Desempenho sexual (relacionamento estável — responder só o companheiro)',
                'perguntas' => [
                    '12_1' => ['texto' => '12.1 Como avalia a acolhida durante a relação sexual?', 'opcoes' => ['a'=>'Não quero responder','b'=>'Não manteve — vontade própria','c'=>'Não manteve — vontade da companheira','d'=>'Muito ansiosa','e'=>'Insegura e com medo','f'=>'Natural, igual que antes','g'=>'Melhor que antes']],
                    '12_2' => ['texto' => '12.2 Teve algum problema durante a relação?', 'opcoes' => ['a'=>'Não','b'=>'Não conseguiu o orgasmo','c'=>'Desejos/fantasias diferentes do habitual','d'=>'Outros']],
                ],
            ],
            '13' => [
                'titulo' => '13. Relação com homens/mulheres (se não estável)',
                'perguntas' => [
                    '13_1' => ['texto' => '13.1 Saiu de visita focada em arrumar alguém, como objetivo principal?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Parcialmente','d'=>'Sim, com certeza']],
                    '13_2' => ['texto' => '13.2 Se expôs a situações de risco para conhecer alguém?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Parcialmente','d'=>'Sim, com certeza']],
                    '13_3' => ['texto' => '13.3 Conheceu alguém com intuito amoroso/sexual, sem relação sexual?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, uma','d'=>'Sim, várias']],
                    '13_4' => ['texto' => '13.4 Conheceu alguém com quem teve relação sexual?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, uma','d'=>'Sim, várias']],
                    '13_5' => ['texto' => '13.5 Pretende iniciar relacionamento duradouro?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Aparentemente sim','d'=>'Sim, com certeza']],
                    '13_6' => ['texto' => '13.6 Essas pessoas podem ser fator de risco para recaída?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Talvez algumas','d'=>'Com certeza algumas','e'=>'Talvez todas','f'=>'Com certeza todas']],
                    '13_7' => ['texto' => '13.7 A acolhida reconhece esse risco?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Parcialmente','d'=>'Sim']],
                    '13_8' => ['texto' => '13.8 Voltou a se encontrar com essas pessoas?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, com as de risco','d'=>'Sim, com as sem risco','e'=>'Sim, com todas']],
                ],
            ],
        ],
    ],

    'trabalho' => [
        'label' => 'Trabalho e dinheiro',
        'secoes' => [
            '14' => [
                'titulo' => '14. Trabalho — se empregada',
                'perguntas' => [
                    '14_1' => ['texto' => '14.1 Visitou o local de trabalho?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, uma vez','d'=>'Sim, algumas vezes']],
                    '14_2' => ['texto' => '14.2 Como se sentiu ao visitar o trabalho?', 'opcoes' => ['a'=>'Não sei','b'=>'Insegura, pouco à vontade','c'=>'Envergonhada por estar em tratamento','d'=>'Tranquila, mas sem muita liberdade','e'=>'Tranquila, muito à vontade']],
                    '14_3' => ['texto' => '14.3 O emprego está seguro ou em risco?', 'opcoes' => ['a'=>'Não sei','b'=>'Grande risco de perder','c'=>'Baixo risco','d'=>'Seguro, com dúvidas','e'=>'Totalmente seguro']],
                    '14_4' => ['texto' => '14.4 Trabalhou durante a visita?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, um dia','d'=>'Sim, de 2 a 3 dias','e'=>'Sim, mais de 3 dias']],
                    '14_5' => ['texto' => '14.5 Como foi o comportamento em relação ao trabalho?', 'opcoes' => ['a'=>'Não sei','b'=>'Dificuldade em cumprir horários, irresponsável','c'=>'Dificuldade nas atividades, pouco motivada','d'=>'Dificuldade em horários e atividades','e'=>'Sem dificuldades, tudo normal']],
                    '14_6' => ['texto' => '14.6 Como foi a relação com os colegas de trabalho?', 'opcoes' => ['a'=>'Não sei','b'=>'Não se entrosou, isolada','c'=>'Vários desentendimentos, tensa/hostil','d'=>'Boa, natural']],
                ],
            ],
            '15' => [
                'titulo' => '15. Trabalho — se desempregada',
                'perguntas' => [
                    '15_1' => ['texto' => '15.1 Procurou serviço durante a visita?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, um dia','d'=>'Sim, de 2 a 3 dias','e'=>'Sim, mais de 3 dias']],
                    '15_2' => ['texto' => '15.2 Como se sentiu ao procurar serviço?', 'opcoes' => ['a'=>'Não sei','b'=>'Ansiosa, nervosa','c'=>'Desmotivada','d'=>'Irritada com as recusas','e'=>'Sentiu discriminação pela dependência química','f'=>'Todas as anteriores','g'=>'Tranquila e autoconfiante']],
                    '15_3' => ['texto' => '15.3 A busca de serviço foi um objetivo principal da visita?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Aparentemente sim','d'=>'Sim, com certeza']],
                ],
            ],
            '16' => [
                'titulo' => '16. Relação com dinheiro',
                'perguntas' => [
                    '16_1' => ['texto' => '16.1 Teve acesso a dinheiro durante a visita?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim']],
                    '16_2' => ['texto' => '16.2 Quem forneceu o dinheiro?', 'opcoes' => ['a'=>'Não sei','b'=>'Ela mesma (trabalho, poupança)','c'=>'Pai','d'=>'Mãe','e'=>'Irmãos','f'=>'Filhos','g'=>'Outros']],
                    '16_3' => ['texto' => '16.3 Qual valor ela teve em mãos?', 'tipo' => 'texto'],
                    '16_4' => ['texto' => '16.4 Em que gastou o dinheiro principalmente?', 'opcoes' => ['a'=>'Não sei','b'=>'Não sei, acho que não fez bom uso (pode ter comprado álcool/drogas)','c'=>'Não sei, mas acho que foi em coisas boas','d'=>'Principalmente festas','e'=>'Principalmente lanches/alimentos','f'=>'Principalmente roupas','g'=>'Preferiu guardar']],
                    '16_5' => ['texto' => '16.5 Poderia ter economizado ou gasto de forma mais produtiva?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Provavelmente sim','d'=>'Sim, com certeza']],
                    '16_6' => ['texto' => '16.6 Comprou algo escondido da família?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Aparentemente não','d'=>'Sim']],
                    '16_7' => ['texto' => '16.7 Teve briga/discussão com a família por causa de dinheiro?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                ],
            ],
        ],
    ],

    'rotina' => [
        'label' => 'Rotina — lazer, amizades, alimentação, sono',
        'secoes' => [
            '17' => [
                'titulo' => '17. Tempo livre e lazer',
                'perguntas' => [
                    '17_1' => ['texto' => '17.1 Como ocupou o tempo livre?', 'opcoes' => ['a'=>'Não sei','b'=>'Dormindo o tempo todo','c'=>'TV, filmes e jogos','d'=>'Saindo sozinha sem rumo','e'=>'Com amigos e/ou namorado','f'=>'Em grupos religiosos/de apoio','g'=>'Leituras e estudos','h'=>'Com a família']],
                    '17_2' => ['texto' => '17.2 Procurou atividades saudáveis e produtivas?', 'opcoes' => ['a'=>'Não sei','b'=>'Não, nada produtivo','c'=>'Sim, mas muito pouco','d'=>'Sim, poderia ter feito mais','e'=>'Sim, bastante saudável e produtivo']],
                    '17_3' => ['texto' => '17.3 Quantas horas por dia na TV?', 'opcoes' => ['a'=>'Não sei','b'=>'Não assistiu','c'=>'Até 2h','d'=>'De 2 a 5h','e'=>'Mais de 5h']],
                    '17_4' => ['texto' => '17.4 Quantas horas por dia no computador (exceto trabalho)?', 'opcoes' => ['a'=>'Não sei','b'=>'Não utilizou','c'=>'Até 2h','d'=>'De 2 a 5h','e'=>'Mais de 5h']],
                    '17_5' => ['texto' => '17.5 Fez leitura ou estudo nesse tempo?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 5 vezes','e'=>'Sim, mais de 5 vezes']],
                ],
            ],
            '18' => [
                'titulo' => '18. Novas amizades',
                'perguntas' => [
                    '18_1' => ['texto' => '18.1 Fez novas amizades durante a visita?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, uma','d'=>'Sim, várias']],
                    '18_2' => ['texto' => '18.2 Essas amizades podem ser fator de risco?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Talvez algumas','d'=>'Com certeza algumas','e'=>'Talvez todas','f'=>'Com certeza todas']],
                    '18_3' => ['texto' => '18.3 A acolhida reconhece esse risco?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Parcialmente','d'=>'Sim']],
                    '18_4' => ['texto' => '18.4 Voltou a se encontrar com essas amizades?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, com as de risco','d'=>'Sim, com as sem risco','e'=>'Sim, com todas']],
                ],
            ],
            '19' => [
                'titulo' => '19. Alimentação',
                'perguntas' => [
                    '19_1' => ['texto' => '19.1 Como foi a alimentação durante a visita?', 'opcoes' => ['a'=>'Não sei','b'=>'Comeu muito pouco','c'=>'Comeu muito e de forma compulsiva','d'=>'Moderado, mas só alimentos pouco saudáveis','e'=>'Moderado e balanceado']],
                    '19_2' => ['texto' => '19.2 Como a família se comportou quanto à alimentação dela?', 'opcoes' => ['a'=>'Não sei','b'=>'Fizeram todas as vontades dela','c'=>'Insistiram para comer tudo','d'=>'Natural, alimentação normal da família']],
                    '19_3' => ['texto' => '19.3 Teve algum problema de saúde relacionado à alimentação?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, por exagero','d'=>'Sim, por alimentação desregrada','e'=>'Sim, por falta de alimentação']],
                    '19_4' => ['texto' => '19.4 Expressou vontade de comer algo que não teve acesso?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, uma vez','d'=>'Sim, várias vezes']],
                    '19_5' => ['texto' => '19.5 Por que não teve acesso a esse alimento?', 'opcoes' => ['a'=>'Não sei','b'=>'Falta de dinheiro','c'=>'Falta de tempo','d'=>'A família achou que não merecia']],
                ],
            ],
            '20' => [
                'titulo' => '20. Sono',
                'perguntas' => [
                    '20_1' => ['texto' => '20.1 Como foi o horário de sono?', 'opcoes' => ['a'=>'Não sei','b'=>'Desregrado','c'=>'Dormiu e acordou muito tarde','d'=>'Dormiu muito tarde, acordou cedo','e'=>'Dormiu cedo, acordou tarde','f'=>'Normal e regrado, como na CT']],
                    '20_2' => ['texto' => '20.2 Dormiu durante o dia?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Só no fim de semana','d'=>'1 a 2 dias na semana','e'=>'1 a 2 dias + fim de semana','f'=>'Mais de 2 dias na semana','g'=>'Mais de 2 dias + fim de semana']],
                    '20_3' => ['texto' => '20.3 Onde dormiu durante a visita?', 'opcoes' => ['a'=>'Não sei','b'=>'No seu quarto','c'=>'Na sala, sem quarto próprio','d'=>'Na sala, por vontade própria','e'=>'Outros']],
                    '20_4' => ['texto' => '20.4 Dormiu na frente da TV alguma noite?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Sim, 1 vez','d'=>'Sim, de 2 a 3 vezes','e'=>'Sim, mais de 3 vezes']],
                    '20_5' => ['texto' => '20.5 Últimas atividades antes de dormir?', 'opcoes' => ['a'=>'Não sei','b'=>'TV ou internet','c'=>'Comer','d'=>'Leitura','e'=>'Orações','f'=>'Conversar com a família','g'=>'Outros']],
                    '20_6' => ['texto' => '20.6 Relatou algum sonho durante a visita?', 'tipo' => 'texto'],
                ],
            ],
        ],
    ],

    'social' => [
        'label' => 'Habilidades sociais e avaliação geral',
        'secoes' => [
            '21' => [
                'titulo' => '21. Habilidades sociais',
                'perguntas' => [
                    '21_1' => ['texto' => '21.1 Conseguiu iniciar e manter diálogos?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Poucas vezes','d'=>'Algumas vezes','e'=>'Frequentemente','f'=>'Sempre']],
                    '21_2' => ['texto' => '21.2 Falou o que pensava com clareza, mesmo arriscando constrangimento?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Poucas vezes','d'=>'Algumas vezes','e'=>'Frequentemente','f'=>'Sempre']],
                    '21_3' => ['texto' => '21.3 Deu sua opinião e pediu a opinião dos outros?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Poucas vezes','d'=>'Algumas vezes','e'=>'Frequentemente','f'=>'Sempre']],
                    '21_4' => ['texto' => '21.4 Soube ouvir e falar no tempo certo?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Poucas vezes','d'=>'Algumas vezes','e'=>'Frequentemente','f'=>'Sempre']],
                    '21_5' => ['texto' => '21.5 Soube expressar sentimentos de forma adequada?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Poucas vezes','d'=>'Algumas vezes','e'=>'Frequentemente','f'=>'Sempre']],
                    '21_6' => ['texto' => '21.6 Se expôs a situações/lugares novos sem risco?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Poucas vezes','d'=>'Algumas vezes','e'=>'Frequentemente','f'=>'Sempre']],
                    '21_7' => ['texto' => '21.7 Teve autocontrole da agressividade?', 'opcoes' => ['a'=>'Não sei','b'=>'Não','c'=>'Poucas vezes','d'=>'Algumas vezes','e'=>'Frequentemente','f'=>'Sempre']],
                ],
            ],
            '22' => [
                'titulo' => '22. Comparação com a visita anterior',
                'perguntas' => [
                    '22_1' => ['texto' => '22.1 Como avalia esta visita em relação à anterior?', 'opcoes' => ['a'=>'Não sei','b'=>'Pior que a anterior','c'=>'Igual à anterior','d'=>'Melhor que a anterior']],
                    '22_2' => ['texto' => '22.2 Se PIOR, por quê?', 'opcoes' => ['a'=>'Não sei','b'=>'Comportamento com a família','c'=>'Comportamento com álcool/drogas','d'=>'Companhias e lugares frequentados','e'=>'Falta de disciplina em atividades/horários','f'=>'Falta de comprometimento espiritual/recuperação','g'=>'Todos ou maioria dos anteriores','h'=>'Outros']],
                    '22_3' => ['texto' => '22.3 Se MELHOR, por quê?', 'opcoes' => ['a'=>'Não sei','b'=>'Comportamento com a família','c'=>'Comportamento com álcool/drogas','d'=>'Companhias e lugares frequentados','e'=>'Disciplina em atividades/horários','f'=>'Comprometimento espiritual/recuperação','g'=>'Todos ou maioria dos anteriores','h'=>'Outros']],
                ],
            ],
            '23' => [
                'titulo' => '23. Considerações finais',
                'perguntas' => [
                    '23_1' => ['texto' => 'Outras considerações da família', 'tipo' => 'texto'],
                ],
            ],
        ],
    ],

];
