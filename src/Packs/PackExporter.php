<?php

declare(strict_types=1);

final class PackExporter
{
    private const CSV_HEADER = [
        'pack_name', 'category_slot', 'category_key', 'category_name', 'category_color',
        'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct',
    ];

    public static function toJson(array $definition): string
    {
        $pack = self::portableDefinition($definition);

        return json_encode(
            ['format_version' => 1, 'pack' => $pack],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    public static function toCsv(array $definition): string
    {
        $pack = self::portableDefinition($definition);
        $categories = [];
        foreach ($pack['categories'] as $category) {
            $categories[(int) $category['slot']] = $category;
        }

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('No se pudo preparar el CSV.');
        }
        fputcsv($stream, self::CSV_HEADER);
        foreach ($pack['questions'] as $question) {
            $slot = (int) $question['slot'];
            $category = $categories[$slot] ?? throw new InvalidArgumentException('Pregunta con categoria desconocida.');
            fputcsv($stream, [
                $pack['name'],
                $slot,
                $category['key'],
                $category['name'],
                $category['color'],
                $question['question'],
                $question['options'][0],
                $question['options'][1],
                $question['options'][2],
                $question['options'][3],
                $question['correct'],
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        if ($csv === false) {
            throw new RuntimeException('No se pudo generar el CSV.');
        }

        return $csv;
    }

    private static function portableDefinition(array $definition): array
    {
        return [
            'name' => (string) ($definition['name'] ?? ''),
            'categories' => array_values($definition['categories'] ?? []),
            'questions' => array_values($definition['questions'] ?? []),
        ];
    }
}
