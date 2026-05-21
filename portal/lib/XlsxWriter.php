<?php
/**
 * Minimal XLSX writer using ZipArchive (sem dependências externas).
 * Suporta múltiplas abas, cabeçalhos coloridos e formatação de data.
 */
class XlsxWriter
{
    /** @var XlsxSheet[] */
    private array $sheets = [];
    private array $strings = [];
    private array $stringIndex = [];

    public function addSheet(string $name): XlsxSheet
    {
        $sheet = new XlsxSheet($name, $this);
        $this->sheets[] = $sheet;
        return $sheet;
    }

    /** Interna uma string no dicionário de strings compartilhadas, retorna o índice. */
    public function internString(string $s): int
    {
        if (!isset($this->stringIndex[$s])) {
            $this->stringIndex[$s] = count($this->strings);
            $this->strings[] = $s;
        }
        return $this->stringIndex[$s];
    }

    public function download(string $filename): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $this->save($tmp);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: no-cache, no-store');
        header('Pragma: no-cache');
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    public function save(string $path): void
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Extensão ZipArchive não disponível no PHP.');
        }
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->buildContentTypes());
        $zip->addFromString('_rels/.rels',          $this->buildRels());
        $zip->addFromString('xl/workbook.xml',       $this->buildWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRels());
        $zip->addFromString('xl/styles.xml',         $this->buildStyles());

        // Constrói XMLs das abas ANTES de sharedStrings (as strings são coletadas aqui)
        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $sheet->buildXml());
        }

        $zip->addFromString('xl/sharedStrings.xml', $this->buildSharedStrings());
        $zip->close();
    }

    // ── Construtores XML internos ────────────────────────────────────────────

    private function buildContentTypes(): string
    {
        $overrides = '';
        foreach ($this->sheets as $i => $_) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml"'
                . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function buildRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function buildWorkbook(): string
    {
        $sheetsXml = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheetsXml .= '<sheet name="' . htmlspecialchars($sheet->name, ENT_XML1) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetsXml . '</sheets>'
            . '</workbook>';
    }

    private function buildWorkbookRels(): string
    {
        $rels = '';
        $n = count($this->sheets);
        foreach ($this->sheets as $i => $_) {
            $rels .= '<Relationship Id="rId' . ($i + 1) . '"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                . ' Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $rels .= '<Relationship Id="rId' . ($n + 1) . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"'
            . ' Target="sharedStrings.xml"/>';
        $rels .= '<Relationship Id="rId' . ($n + 2) . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            . ' Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    /**
     * Estilos disponíveis (índice do xf):
     *  0 = normal
     *  1 = negrito
     *  2 = negrito + texto branco + fundo verde (#1e6b35)   — cabeçalho padrão
     *  3 = negrito + texto branco + fundo verde-escuro (#0f4020) — título/dashboard
     *  4 = negrito + texto branco + fundo ouro (#a87d28)    — cargos
     *  5 = data DD/MM/AAAA
     *  6 = fundo verde-claro + borda fina                   — linha de dado par
     *  7 = negrito + texto branco + fundo roxo (#8b44a8)    — pastoreio
     *  8 = negrito + fundo verde-claro + borda fina         — sub-cabeçalho
     *  9 = normal + borda fina
     * 10 = negrito + texto branco + fundo azul (#1a6b8a)    — habilidades
     */
    private function buildStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="DD/MM/YYYY"/></numFmts>'
            . '<fonts count="4">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><sz val="11"/><b/><name val="Calibri"/></font>'
            . '<font><sz val="11"/><b/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>'
            . '<font><sz val="13"/><b/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>'
            . '</fonts>'
            . '<fills count="9">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1e6b35"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0f4020"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFa87d28"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFe8f5ed"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF8b44a8"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1a6b8a"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFf9f3e8"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFcccccc"/></left>'
            .   '<right style="thin"><color rgb="FFcccccc"/></right>'
            .   '<top style="thin"><color rgb="FFcccccc"/></top>'
            .   '<bottom style="thin"><color rgb="FFcccccc"/></bottom>'
            .   '<diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="11">'
            . '<xf numFmtId="0"   fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                    // 0 normal
            . '<xf numFmtId="0"   fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                      // 1 negrito
            . '<xf numFmtId="0"   fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'                         // 2 negrito branco verde
            . '<xf numFmtId="0"   fontId="3" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'                         // 3 negrito branco verde-escuro
            . '<xf numFmtId="0"   fontId="2" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'                         // 4 negrito branco ouro
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                              // 5 data
            . '<xf numFmtId="0"   fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>'                       // 6 verde-claro borda
            . '<xf numFmtId="0"   fontId="2" fillId="6" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'                         // 7 negrito branco roxo
            . '<xf numFmtId="0"   fontId="1" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'         // 8 negrito verde-claro borda
            . '<xf numFmtId="0"   fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'                                    // 9 normal borda
            . '<xf numFmtId="0"   fontId="2" fillId="7" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'                         // 10 negrito branco azul
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private function buildSharedStrings(): string
    {
        $count = count($this->strings);
        $items = '';
        foreach ($this->strings as $s) {
            $items .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' count="' . $count . '" uniqueCount="' . $count . '">'
            . $items . '</sst>';
    }
}

// ── Aba individual ──────────────────────────────────────────────────────────

class XlsxSheet
{
    public string $name;
    private XlsxWriter $writer;
    /** @var array<int,array<int,array{0:mixed,1:int}>> $rows[rowNum][colNum] = [value, styleId] */
    private array $rows = [];
    private array $colWidths = [];

    public function __construct(string $name, XlsxWriter $writer)
    {
        $this->name   = $name;
        $this->writer = $writer;
    }

    /** Define largura de uma coluna (1-indexed). */
    public function setColWidth(int $col, float $width): self
    {
        $this->colWidths[$col] = $width;
        return $this;
    }

    /** Escreve uma célula. Row e col são 1-indexed. */
    public function writeCell(int $row, int $col, mixed $value, int $styleId = 0): self
    {
        $this->rows[$row][$col] = [$value, $styleId];
        return $this;
    }

    /**
     * Escreve uma linha inteira. $values é array associativo [col => valor] ou lista [valor, valor, ...].
     * Se for lista, colunas começam em 1.
     */
    public function writeRow(int $row, array $values, int $styleId = 0): self
    {
        // Se lista (chaves 0, 1, 2...), converte para 1-indexed
        if (array_key_exists(0, $values)) {
            $values = array_combine(range(1, count($values)), array_values($values));
        }
        foreach ($values as $col => $value) {
            $this->writeCell($row, (int)$col, $value, $styleId);
        }
        return $this;
    }

    public function buildXml(): string
    {
        $colDefs = '';
        if ($this->colWidths) {
            ksort($this->colWidths);
            $colDefs = '<cols>';
            foreach ($this->colWidths as $col => $width) {
                $colDefs .= '<col min="' . $col . '" max="' . $col . '" width="' . $width . '" customWidth="1"/>';
            }
            $colDefs .= '</cols>';
        }

        $rowsXml = '';
        ksort($this->rows);
        foreach ($this->rows as $rowNum => $cols) {
            $rowsXml .= '<row r="' . $rowNum . '">';
            ksort($cols);
            foreach ($cols as $colNum => [$value, $styleId]) {
                $ref = $this->colLetter($colNum) . $rowNum;
                $rowsXml .= $this->buildCell($ref, $value, $styleId);
            }
            $rowsXml .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $colDefs
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . '</worksheet>';
    }

    private function buildCell(string $ref, mixed $value, int $styleId): string
    {
        if ($value === null || $value === '') {
            return '<c r="' . $ref . '" s="' . $styleId . '"/>';
        }
        if (is_int($value) || is_float($value)) {
            return '<c r="' . $ref . '" s="' . $styleId . '"><v>' . $value . '</v></c>';
        }
        $idx = $this->writer->internString((string)$value);
        return '<c r="' . $ref . '" t="s" s="' . $styleId . '"><v>' . $idx . '</v></c>';
    }

    /** Converte número de coluna (1-indexed) para letra(s): 1→A, 26→Z, 27→AA. */
    private function colLetter(int $col): string
    {
        $letter = '';
        while ($col > 0) {
            $col--;
            $letter = chr(65 + ($col % 26)) . $letter;
            $col    = (int)($col / 26);
        }
        return $letter;
    }
}
