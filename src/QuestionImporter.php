<?php

declare(strict_types=1);

require_once __DIR__ . '/GameEngine.php';

final class QuestionImporter
{
    public static function fromCsv(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        if ($lines === false || count($lines) < 2) {
            throw new InvalidArgumentException('El CSV debe incluir cabecera y al menos una pregunta.');
        }

        $header = str_getcsv(array_shift($lines));
        $expected = ['category', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct'];
        if ($header !== $expected) {
            throw new InvalidArgumentException('La cabecera CSV debe ser: ' . implode(',', $expected));
        }

        $rows = [];
        foreach ($lines as $lineNumber => $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            if (count($values) !== count($expected)) {
                throw new InvalidArgumentException('Fila ' . ($lineNumber + 2) . ': numero de columnas incorrecto.');
            }
            $row = array_combine($expected, $values);
            if ($row === false) {
                throw new InvalidArgumentException('Fila ' . ($lineNumber + 2) . ': no se pudo leer.');
            }
            $rows[] = self::normalizeRow($row, $lineNumber + 2);
        }

        if ($rows === []) {
            throw new InvalidArgumentException('El CSV no contiene preguntas validas.');
        }

        return $rows;
    }

    public static function normalizeRow(array $row, int $lineNumber = 1): array
    {
        $category = trim((string) ($row['category'] ?? ''));
        $question = trim((string) ($row['question'] ?? ''));
        $options = [
            trim((string) ($row['option_a'] ?? '')),
            trim((string) ($row['option_b'] ?? '')),
            trim((string) ($row['option_c'] ?? '')),
            trim((string) ($row['option_d'] ?? '')),
        ];
        $correct = filter_var($row['correct'] ?? null, FILTER_VALIDATE_INT);

        if (!self::isKnownCategory($category)) {
            throw new InvalidArgumentException("Fila {$lineNumber}: categoria desconocida.");
        }
        if ($question === '') {
            throw new InvalidArgumentException("Fila {$lineNumber}: la pregunta es obligatoria.");
        }
        foreach ($options as $option) {
            if ($option === '') {
                throw new InvalidArgumentException("Fila {$lineNumber}: cada pregunta debe tener cuatro opciones.");
            }
        }
        if ($correct === false || $correct < 0 || $correct > 3) {
            throw new InvalidArgumentException("Fila {$lineNumber}: la respuesta correcta debe ser 0, 1, 2 o 3.");
        }

        return [
            'category' => $category,
            'question' => $question,
            'options' => $options,
            'correct' => $correct,
        ];
    }

    private static function isKnownCategory(string $category): bool
    {
        foreach (GameEngine::categories() as $known) {
            if ($known['slug'] === $category) {
                return true;
            }
        }

        return false;
    }
}
