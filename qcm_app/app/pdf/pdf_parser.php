<?php
// app/pdf/pdf_parser.php (moved from app/pdf_parser.php)
// Charger l'autoload de Smalot\PdfParser (placé dans public/vendor_pdfparser/ ou public/pdfparser/)
$pdfAutoloadCandidates = [
    __DIR__ . '/../../public/vendor_pdfparser/autoload.php',
    __DIR__ . '/../../public/pdfparser/autoload.php',
];
$loaded = false;
foreach ($pdfAutoloadCandidates as $auto) {
    if (is_file($auto)) {
        require_once $auto;
        $loaded = true;
        break;
    }
}
// Fallback : autoload minimal si pas d'autoload.php (chargement direct depuis src)
if (!$loaded) {
    $srcDirCandidates = [
        __DIR__ . '/../../public/vendor_pdfparser/src',
        __DIR__ . '/../../public/pdfparser/src',
    ];
    $srcDir = null;
    foreach ($srcDirCandidates as $cand) {
        if (is_dir($cand)) {
            $srcDir = $cand;
            break;
        }
    }
    if ($srcDir) {
        spl_autoload_register(function ($class) use ($srcDir) {
            $file = $srcDir . '/' . str_replace('\\', '/', $class) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
        $loaded = true;
    }
}
if (!$loaded) {
    throw new RuntimeException("Autoload PDFParser introuvable. Placez la lib dans public/vendor_pdfparser/ ou public/pdfparser/.");
}
require_once __DIR__ . '/../core/database.php';

use Smalot\PdfParser\Parser;

function importQuestionsFromPdf(int $examId, string $pdfPath): int
{
    if (!is_file($pdfPath)) {
        return 0;
    }

    try {
        $parser = new Parser();
        $pdf    = $parser->parseFile($pdfPath);
        $content = $pdf->getText();
    } catch (Exception $e) {
        return 0;
    }

    if (!is_string($content) || trim($content) === '') {
        return 0;
    }

    $content = str_replace(["\r\n", "\r", "\f"], "\n", $content);
    $blocks = preg_split('/QUESTION\s*NO:\s*/i', $content);
    $importedCount = 0;

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;

    $lines = preg_split("/\n+/", $block);
        $enonceLines = [];
        $options = [];
        $correctLetters = [];
        $explicationLines = [];
        $seenAnswer = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if (preg_match('/^[A-Z][\.\)]\s*/', $line)) {
                $label = substr($line, 0, 1);
                $texte = trim(preg_replace('/^[A-Z][\.\)]\s*/', '', $line));
                $options[$label] = $texte;
                continue;
            }

            if (stripos($line, 'Correct Answer:') === 0 || stripos($line, 'Correct Answers:') === 0) {
                $seenAnswer = true;
                $answerPart = trim(preg_replace('/Correct Answers?:\s*/i', '', $line));
                $answerPart = str_replace([';', '/', '\\'], ',', $answerPart);
                $correctLetters = array_filter(array_map(
                    'trim',
                    explode(',', strtoupper($answerPart))
                ));
                continue;
            }

            if ($seenAnswer) {
                $explicationLines[] = $line;
                continue;
            }

            $enonceLines[] = $line;
        }

        if (empty($enonceLines) || empty($options) || empty($correctLetters)) {
            continue;
        }

        $enonce = implode("\n", $enonceLines);
        $explication = empty($explicationLines) ? null : implode("\n", $explicationLines);
        $type = (count($correctLetters) > 1) ? 'qcm_multiple' : 'qcm_simple';

        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "INSERT INTO questions (exam_id, type, enonce, explication) 
             VALUES (:exam_id, :type, :enonce, :explication)"
        );
        $stmt->execute([
            ':exam_id'     => $examId,
            ':type'        => $type,
            ':enonce'      => $enonce,
            ':explication' => $explication,
        ]);
        $questionId = (int) $pdo->lastInsertId();

        foreach ($options as $label => $texteOption) {
            $isCorrect = in_array($label, $correctLetters, true) ? 1 : 0;
            $stmtOpt = $pdo->prepare(
                "INSERT INTO options (question_id, label, texte, is_correct) 
                 VALUES (:qid, :label, :texte, :is_correct)"
            );
            $stmtOpt->execute([
                ':qid'        => $questionId,
                ':label'      => $label,
                ':texte'      => $texteOption,
                ':is_correct' => $isCorrect,
            ]);
        }

        $importedCount++;
    }

    return $importedCount;
}
