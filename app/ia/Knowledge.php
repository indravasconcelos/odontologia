<?php
/**
 * Knowledge — o que o assistente sabe
 *
 * IMPORTANTE
 * Tudo aqui foi copiado do conteudo que ja existe no site.
 * Nao inventamos preco, prazo de tratamento, diagnostico ou promessa
 * de resultado. Se a duvida nao esta nesta lista, o assistente
 * encaminha para o atendimento humano.
 */

declare(strict_types=1);

final class Knowledge
{
    public const WHATSAPP = 'https://wa.me/5569999880560';
    public const TELEFONE = '(69) 99988-0560';

    /** dados fixos que constam no site */
    public static function dados(): array
    {
        return [
            'nome'        => 'Dr. Maycon Gomes',
            'especialidade'=> 'Ortodontia / Clínico Geral',
            'cro'         => 'CRO/RO 3795',
            'cidade'      => 'Ariquemes - RO',
            'endereco'    => 'Rua Salvador, 2076',
            'cep'         => '76870-416',
            'telefone'    => self::TELEFONE,
            'whatsapp'    => self::WHATSAPP,
        ];
    }

    /**
     * os 8 tratamentos do site, com o texto original.
     *
     * 'apelidos' é como o paciente realmente escreve no chat, que quase
     * nunca é igual ao nome do site ("canal" em vez de "endodontia",
     * "protese" sem o h de "prothese"). Sem isso a dúvida não era
     * reconhecida e a oferta da avaliação nunca chegava.
     */
    public static function tratamentos(): array
    {
        return [
            [
                'nome'      => 'aparelhos',
                'titulo'    => 'Aparelhos ortopédicos',
                'descricao' => 'Guiam a posição dos dentes e o desenvolvimento da mordida, com uso definido caso a caso.',
                'apelidos'  => ['aparelho', 'ortopedico', 'torto', 'tortos', 'torta', 'tortas',
                    'travar os dentes', 'dentes fora de lugar'],
            ],
            [
                'nome'      => 'alinhadores',
                'titulo'    => 'Alinhadores invisíveis',
                'descricao' => 'Alinham os dentes de forma discreta, sem aparecer no dia a dia. Cada caso recebe um plano próprio, definido após a avaliação.',
                'apelidos'  => ['alinhar', 'alinhavel', 'invisivel', 'transparente', 'nao aparecer'],
            ],
            [
                'nome'      => 'clareamento',
                'titulo'    => 'Clareamento dental',
                'descricao' => 'Para quem deseja iluminar o sorriso.',
                'apelidos'  => ['clarear', 'clarear os dentes', 'esclarecer', 'esbranquicar',
                    'mancha', 'manchas', 'amarelado', 'branqueamento'],
            ],
            [
                'nome'      => 'profilaxia',
                'titulo'    => 'Profilaxia e prevenção',
                'descricao' => 'Limpeza e revisão para prevenir problemas.',
                'apelidos'  => ['limpeza', 'limpar os dentes', 'tartaro', 'prevencao', 'higiene',
                    'limpeza dos dentes'],
            ],
            [
                'nome'      => 'restauracoes',
                'titulo'    => 'Restaurações',
                'descricao' => 'Recupera a função do dente com conforto.',
                'apelidos'  => ['restauracao', 'restaurar', 'carie', 'obturar', 'obturacao',
                    'material no dente', 'encher o dente'],
            ],
            [
                'nome'      => 'endodontia',
                'titulo'    => 'Endodontia',
                'descricao' => 'Tratamento de canal para aliviar o desconforto.',
                'apelidos'  => ['canal', 'tratamento de canal', 'canal no dente', 'molar',
                    'dente com dor forte'],
            ],
            [
                'nome'      => 'contencao',
                'titulo'    => 'Contenção',
                'descricao' => 'Mantém a posição conquistada após o alinhamento.',
                'apelidos'  => ['contencao', 'manter apos o aparelho', 'regressao',
                    'dente voltou a torto'],
            ],
            [
                'nome'      => 'protese',
                'titulo'    => 'Prótese dentária',
                'descricao' => 'Recupera forma e função quando faltam dentes.',
                'apelidos'  => ['protese', 'protese dentaria', 'dente quebrado', 'dente quebrou',
                    'quebrei meu dente', 'quebrou meu dente', 'falta dente',
                    'faltando dente', 'perdi um dente', 'perdi dente', 'implante'],
            ],
        ];
    }

    /** as 3 etapas do "Como funciona", texto do site */
    public static function etapas(): array
    {
        return [
            ['titulo' => 'A avaliação', 'texto' => 'Observamos mordida e alinhamento — e ouvimos o que você sente.'],
            ['titulo' => 'O planejamento', 'texto' => 'O que será feito, em qual ordem e o que esperar de cada etapa.'],
            ['titulo' => 'O acompanhamento', 'texto' => 'Retornos para avaliar a evolução e ajustar o que for preciso.'],
        ];
    }

    /** perguntas e respostas do FAQ do site */
    public static function faq(): array
    {
        return [
            [
                'chaves' => ['aparelho', 'ortopedico', 'ortopédico', 'indicacao', 'indicado'],
                'pergunta' => 'Para quem são indicados os aparelhos ortopédicos?',
                'resposta' => 'Depende de cada caso. A indicação é definida após uma avaliação da mordida, do alinhamento e da fase de desenvolvimento do paciente.',
            ],
            [
                'chaves' => ['avaliacao', 'consulta', 'primeira', 'como funciona'],
                'pergunta' => 'Como funciona a avaliação?',
                'resposta' => 'Um encontro para ouvir suas queixas, observar dentes e mordida e explicar os caminhos possíveis e os próximos passos.',
            ],
            [
                'chaves' => ['quanto tempo', 'duracao', 'demora', 'prazo', 'tempo de tratamento'],
                'pergunta' => 'Quanto tempo dura um tratamento?',
                'resposta' => 'Varia conforme o caso e a resposta do paciente. A estimativa é conversada após a avaliação.',
            ],
            [
                'chaves' => ['acompanhamento', 'retorno', 'seguimento'],
                'pergunta' => 'Como funciona o acompanhamento?',
                'resposta' => 'Os retornos seguem a etapa do tratamento: a evolução é observada, os ajustes são feitos e suas dúvidas são tiradas.',
            ],
            [
                'chaves' => ['igual', 'todos', 'caso', 'diferente'],
                'pergunta' => 'O tratamento é igual para todos?',
                'resposta' => 'Não. Cada caso é único — por isso o planejamento é sempre individual e a avaliação vem primeiro.',
            ],
            [
                'chaves' => ['levar', 'documento', 'exame', 'primeira consulta'],
                'pergunta' => 'Preciso levar alguma coisa na primeira consulta?',
                'resposta' => 'Basta chegar com tempo. Se tiver exames anteriores ou informações de outros tratamentos, traga junto.',
            ],
        ];
    }

    /** dia de atendimento: vem do banco (tabela horarios) */
    public static function diasDeAtendimento(): array
    {
        $dias = Database::todos('SELECT DISTINCT dia_semana FROM horarios WHERE ativo = 1 ORDER BY dia_semana');
        $nomes = [];
        foreach ($dias as $d) {
            $nomes[] = (string) Helpers::diaSemana((int) $d['dia_semana']);
        }
        return $nomes;
    }

    public static function textoDias(): string
    {
        $dias = self::diasDeAtendimento();
        if ($dias === []) {
            return '';
        }
        $ultimo = array_pop($dias);
        return $dias === [] ? $ultimo : implode(', ', $dias) . ' e ' . $ultimo;
    }

    /**
     * Procura um tratamento pelo nome digitado pelo visitante.
     * Aceita abreviacoes e erros de digitacao simples:
     * "aparelho", "alinham", "clareamento", "canal"...
     */
    public static function tratamentoPorNome(string $texto): ?array
    {
        $alvo = self::normalizar($texto);
        if ($alvo === '') {
            return null;
        }

        $tratamentos = self::tratamentos();

        // 1. casamento direto pelo nome do tratamento
        foreach ($tratamentos as $t) {
            if (str_contains($alvo, $t['nome'])) {
                return $t;
            }
        }

        // 1b. casamento pelos apelidos: como o paciente escreve mesmo
        foreach ($tratamentos as $t) {
            foreach ((array) ($t['apelidos'] ?? []) as $apelido) {
                if ($apelido !== '' && str_contains($alvo, $apelido)) {
                    return $t;
                }
            }
        }

        // 2._radical: "alinham" x "alinhadores" (prefixo comum >= 5 letras)
        $palavras = explode(' ', $alvo);
        foreach ($tratamentos as $t) {
            foreach ($palavras as $p) {
                if (mb_strlen($p) < 4) {
                    continue;
                }
                $comum = 0;
                $limite = min(mb_strlen($p), mb_strlen($t['nome']));
                while ($comum < $limite && $p[$comum] === $t['nome'][$comum]) {
                    $comum++;
                }
                if ($comum >= 5 || $comum === $limite && $limite >= 6) {
                    return $t;
                }
            }
        }

        // 3. o nome do tratamento esta dentro do texto do visitante
        foreach ($tratamentos as $t) {
            if (mb_strlen($t['nome']) >= 6 && str_contains($alvo, $t['nome'])) {
                return $t;
            }
        }

        return null;
    }

    /** procura no FAQ pelas palavras da frase */
    public static function faqPorTexto(string $texto): ?array
    {
        $alvo = self::normalizar($texto);
        $melhor = null;
        $pontos = 0;

        foreach (self::faq() as $f) {
            foreach ($f['chaves'] as $chave) {
                $chaveNorm = self::normalizar($chave);
                if ($chaveNorm !== '' && str_contains($alvo, $chaveNorm)) {
                    $pontosChave = mb_strlen($chaveNorm);
                    if ($pontosChave > $pontos) {
                        $pontos = $pontosChave;
                        $melhor = $f;
                    }
                }
            }
        }

        return $melhor;
    }

    /** texto de sistema para a IA, sem precios e sem promessas */
    public static function paraIa(): string
    {
        $linhas = [];

        $linhas[] = 'DADOS DO CONSULTORIO';
        foreach (self::dados() as $chave => $valor) {
            $linhas[] = '- ' . $chave . ': ' . $valor;
        }
        $linhas[] = '- dias de atendimento: ' . self::textoDias();

        $linhas[] = '';
        $linhas[] = 'TRATAMENTOS QUE EXISTEM NO SITE';
        foreach (self::tratamentos() as $t) {
            $linhas[] = '- ' . $t['titulo'] . ': ' . $t['descricao'];
        }

        $linhas[] = '';
        $linhas[] = 'ETAPAS DO ATENDIMENTO';
        foreach (self::etapas() as $e) {
            $linhas[] = '- ' . $e['titulo'] . ': ' . $e['texto'];
        }

        $linhas[] = '';
        $linhas[] = 'PERGUNTAS FREQUENTES (texto exato do site)';
        foreach (self::faq() as $f) {
            $linhas[] = '- ' . $f['pergunta'];
            $linhas[] = '  ' . $f['resposta'];
        }

        $linhas[] = '';
        $linhas[] = 'OBSERVACOES IMPORTANTES';
        $linhas[] = '- O site informa que o atendimento e de segunda a sabado.';
        $linhas[] = '- Nao existe informacao de preco, valor, parcelamento ou prazo exato de tratamento.';
        $linhas[] = '- Nao existe promessa de resultado: cada caso e individual.';
        $linhas[] = '- A avaliacao e sempre o primeiro passo.';

        return implode("\n", $linhas);
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'ê' => 'e', 'è' => 'e',
            'í' => 'i', 'î' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o',
            'ú' => 'u', 'û' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
        $texto = preg_replace('/[^a-z0-9\s:]/', ' ', $texto) ?? $texto;
        return trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
    }
}