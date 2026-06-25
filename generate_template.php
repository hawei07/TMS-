<?php
/**
 * 生成 Excel 导入模板文件 (static/导入模板.xlsx)
 * 纯 PHP 实现，使用 ZipArchive，无需 Composer 依赖
 */

function generateXlsxTemplate($filepath) {
    $zip = new ZipArchive();
    if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("无法创建 ZIP 文件: $filepath\n");
    }

    // 表头
    $headers = ['姓名', '电话', '来源', '来源详情', '意向等级', '负责人', '性别', '出生日期'];

    // 构建 sharedStrings.xml
    $sharedStrings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($headers) . '" uniqueCount="' . count($headers) . '">';
    foreach ($headers as $h) {
        $sharedStrings .= '<si><t>' . htmlspecialchars($h, ENT_XML1, 'UTF-8') . '</t></si>';
    }
    $sharedStrings .= '</sst>';
    $zip->addFromString('xl/sharedStrings.xml', $sharedStrings);

    // 构建 sheet1.xml (第1行 = 表头，引用 sharedStrings 索引 0-7)
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<cols>';
    // 设置列宽
    $colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    $colWidths = [12, 16, 14, 20, 14, 12, 8, 14];
    for ($i = 0; $i < 8; $i++) {
        $sheetXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $colWidths[$i] . '" customWidth="1"/>';
    }
    $sheetXml .= '</cols><sheetData>';
    // 第1行
    $sheetXml .= '<row r="1">';
    for ($i = 0; $i < 8; $i++) {
        $col = $colLetters[$i] . '1';
        $sheetXml .= '<c r="' . $col . '" t="s"><v>' . $i . '</v></c>';
    }
    $sheetXml .= '</row>';
    // 第2行（示例数据）
    $sheetXml .= '<row r="2">'
        . '<c r="A2" t="s"><v>0</v></c>'  // will point to shared string - no, need inline for example
        . '</row>';
    $sheetXml .= '</sheetData></worksheet>';

    // Actually, let's keep it simple: just the header row
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<cols>';
    for ($i = 0; $i < 8; $i++) {
        $sheetXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $colWidths[$i] . '" customWidth="1"/>';
    }
    $sheetXml .= '</cols><sheetData>';
    $sheetXml .= '<row r="1">';
    for ($i = 0; $i < 8; $i++) {
        $col = $colLetters[$i] . '1';
        $sheetXml .= '<c r="' . $col . '" t="s"><v>' . $i . '</v></c>';
    }
    $sheetXml .= '</row>';
    $sheetXml .= '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

    // styles.xml
    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><sz val="11"/><name val="Microsoft YaHei"/></font>'
        . '<font><b/><sz val="11"/><name val="Microsoft YaHei"/><color rgb="FFFFFFFF"/></font>'
        . '</fonts>'
        . '<fills count="2">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
        . '</styleSheet>';
    $zip->addFromString('xl/styles.xml', $stylesXml);

    // workbook.xml
    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>'
        . '<sheet name="Sheet1" sheetId="1" r:id="rId1"/>'
        . '</sheets>'
        . '</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbookXml);

    // workbook.xml.rels
    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $relsXml);

    // [Content_Types].xml
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);

    // _rels/.rels
    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
    $zip->addFromString('_rels/.rels', $rootRels);

    $zip->close();
    echo "模板生成成功: $filepath\n";
}

$outputPath = __DIR__ . '/static/导入模板.xlsx';
generateXlsxTemplate($outputPath);
