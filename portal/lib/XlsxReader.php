<?php
/**
 * Leitor mínimo de XLSX usando ZipArchive + SimpleXML (sem dependências externas).
 * Lê uma aba por vez e retorna um array de linhas.
 */
class XlsxReader
{
    /**
     * Lê uma aba do arquivo .xlsx.
     *
     * @param string $filepath   Caminho para o arquivo .xlsx
     * @param int    $sheetIndex Índice da aba (0 = primeira)
     * @return array[]           Array de linhas; cada linha é array de valores (strings/números)
     */
    public function readSheet(string $filepath, int $sheetIndex = 0): array
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Extensão ZipArchive não disponível no PHP.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            return [];
        }

        // Lê strings compartilhadas
        $sharedStrings = [];
        $ssRaw = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssRaw !== false) {
            libxml_use_internal_errors(true);
            $ss = simplexml_load_string($ssRaw);
            if ($ss) {
                foreach ($ss->si as $si) {
                    if (isset($si->t)) {
                        // Texto simples
                        $sharedStrings[] = (string)$si->t;
                    } else {
                        // Rich text: concatena todos os <r><t>
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                        $sharedStrings[] = $text;
                    }
                }
            }
        }

        // Lê a aba solicitada
        $wsRaw = $zip->getFromName('xl/worksheets/sheet' . ($sheetIndex + 1) . '.xml');
        $zip->close();

        if ($wsRaw === false) {
            return [];
        }

        libxml_use_internal_errors(true);
        $ws = simplexml_load_string($wsRaw);
        if (!$ws) {
            return [];
        }

        $rows = [];

        foreach ($ws->sheetData->row as $row) {
            $rowNum    = (int)$row['r'];
            $cellsByCol = [];
            $maxCol    = 0;

            foreach ($row->c as $cell) {
                $cellRef = (string)$cell['r'];
                // Separa letras de números: "AB12" → coluna "AB"
                preg_match('/^([A-Z]+)(\d+)$/', $cellRef, $m);
                if (!$m) continue;
                $colNum = $this->colNum($m[1]);
                $maxCol = max($maxCol, $colNum);

                $type = (string)$cell['t'];
                $raw  = isset($cell->v) ? (string)$cell->v : '';

                if ($type === 's') {
                    // String compartilhada
                    $cellsByCol[$colNum] = $sharedStrings[(int)$raw] ?? '';
                } elseif ($type === 'inlineStr') {
                    $cellsByCol[$colNum] = isset($cell->is->t) ? (string)$cell->is->t : '';
                } else {
                    // Número, data, booleano — retorna como string bruta
                    $cellsByCol[$colNum] = $raw;
                }
            }

            // Monta linha como array 0-indexed com todas as colunas até maxCol
            $rowArr = [];
            for ($c = 1; $c <= $maxCol; $c++) {
                $rowArr[] = $cellsByCol[$c] ?? '';
            }

            $rows[$rowNum] = $rowArr;
        }

        ksort($rows);
        return array_values($rows);
    }

    /** Converte referência de coluna em letra(s) para número 1-indexed: A→1, Z→26, AA→27. */
    private function colNum(string $col): int
    {
        $col = strtoupper(trim($col));
        $num = 0;
        for ($i = 0, $len = strlen($col); $i < $len; $i++) {
            $num = $num * 26 + (ord($col[$i]) - 64);
        }
        return $num;
    }
}
