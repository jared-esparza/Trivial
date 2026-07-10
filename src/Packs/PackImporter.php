<?php

declare(strict_types=1);

final class PackImporter
{
    private const CSV_HEADER = [
        'pack_name', 'category_slot', 'category_key', 'category_name', 'category_color',
        'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct',
    ];

    public static function fromJson(string $json): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['format_version'] ?? null) !== 1 || !is_array($data['pack'] ?? null)) {
            throw new InvalidArgumentException('Formato JSON de pack no valido.');
        }

        return self::normalize($data['pack']);
    }

    public static function fromCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('No se pudo leer el CSV.');
        }
        fwrite($stream, $csv);
        rewind($stream);
        $header = fgetcsv($stream);
        if ($header !== self::CSV_HEADER) {
            fclose($stream);
            throw new InvalidArgumentException('Cabecera CSV de pack no valida.');
        }

        $name = null;
        $categories = [];
        $questions = [];
        $line = 1;
        while (($values = fgetcsv($stream)) !== false) {
            $line++;
            if ($values === [null] || $values === []) {
                continue;
            }
            if (count($values) !== count(self::CSV_HEADER)) {
                fclose($stream);
                throw new InvalidArgumentException("Fila {$line}: numero de columnas incorrecto.");
            }
            $row = array_combine(self::CSV_HEADER, $values);
            if ($row === false) {
                fclose($stream);
                throw new InvalidArgumentException("Fila {$line}: no se pudo interpretar.");
            }
            $rowName = trim((string) $row['pack_name']);
            if ($name === null) {
                $name = $rowName;
            } elseif ($name !== $rowName) {
                fclose($stream);
                throw new InvalidArgumentException('El CSV solo puede contener un pack.');
            }
            $slot = filter_var($row['category_slot'], FILTER_VALIDATE_INT);
            if ($slot === false) {
                fclose($stream);
                throw new InvalidArgumentException("Fila {$line}: slot no valido.");
            }
            $category = [
                'slot' => $slot,
                'key' => (string) $row['category_key'],
                'name' => (string) $row['category_name'],
                'color' => (string) $row['category_color'],
            ];
            if (isset($categories[$slot]) && $categories[$slot] !== $category) {
                fclose($stream);
                throw new InvalidArgumentException("Fila {$line}: metadatos de categoria contradictorios.");
            }
            $categories[$slot] = $category;
            $questions[] = [
                'slot' => $slot,
                'question' => (string) $row['question'],
                'options' => [(string) $row['option_a'], (string) $row['option_b'], (string) $row['option_c'], (string) $row['option_d']],
                'correct' => filter_var($row['correct'], FILTER_VALIDATE_INT),
            ];
        }
        fclose($stream);
        ksort($categories);

        return self::normalize([
            'name' => $name ?? '',
            'categories' => array_values($categories),
            'questions' => $questions,
        ]);
    }

    private static function normalize(array $pack): array
    {
        $name = trim((string) ($pack['name'] ?? ''));
        $categories = array_values($pack['categories'] ?? []);
        $questions = array_values($pack['questions'] ?? []);
        if ($name === '' || count($categories) !== 6 || $questions === []) {
            throw new InvalidArgumentException('El pack debe incluir nombre, seis categorias y preguntas.');
        }

        $normalizedCategories = [];
        $keys = [];
        foreach ($categories as $category) {
            $slot = filter_var($category['slot'] ?? null, FILTER_VALIDATE_INT);
            $key = strtolower(trim((string) ($category['key'] ?? '')));
            $categoryName = trim((string) ($category['name'] ?? ''));
            $color = trim((string) ($category['color'] ?? ''));
            if ($slot === false || $slot < 0 || $slot > 5 || isset($normalizedCategories[$slot])) {
                throw new InvalidArgumentException('Slots de categoria no validos.');
            }
            if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,59}$/', $key) || isset($keys[$key])) {
                throw new InvalidArgumentException('Claves de categoria no validas.');
            }
            if ($categoryName === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                throw new InvalidArgumentException('Nombre o color de categoria no valido.');
            }
            $normalizedCategories[$slot] = ['slot' => $slot, 'key' => $key, 'name' => $categoryName, 'color' => $color];
            $keys[$key] = true;
        }
        ksort($normalizedCategories);

        $normalizedQuestions = [];
        foreach ($questions as $question) {
            $slot = filter_var($question['slot'] ?? null, FILTER_VALIDATE_INT);
            $text = trim((string) ($question['question'] ?? ''));
            $options = array_map(fn ($value): string => trim((string) $value), array_values($question['options'] ?? []));
            $correct = filter_var($question['correct'] ?? null, FILTER_VALIDATE_INT);
            if ($slot === false || !isset($normalizedCategories[$slot]) || $text === '' || count($options) !== 4 || in_array('', $options, true)) {
                throw new InvalidArgumentException('Pregunta de pack no valida.');
            }
            if ($correct === false || $correct < 0 || $correct > 3) {
                throw new InvalidArgumentException('Respuesta correcta no valida.');
            }
            $normalizedQuestions[] = compact('slot', 'question', 'options', 'correct');
            $normalizedQuestions[array_key_last($normalizedQuestions)]['question'] = $text;
        }

        return [
            'name' => $name,
            'categories' => array_values($normalizedCategories),
            'questions' => $normalizedQuestions,
        ];
    }
}
