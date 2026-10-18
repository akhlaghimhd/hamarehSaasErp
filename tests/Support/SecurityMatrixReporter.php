<?php

namespace Tests\Support;

/**
 * Collects holding access security matrix results for local → review pipeline.
 */
final class SecurityMatrixReporter
{
    /** @var list<array<string, mixed>> */
    private static array $rows = [];

    public static function reset(): void
    {
        self::$rows = [];
    }

    public static function record(
        string $id,
        string $category,
        string $severity,
        string $title,
        string $expected,
        string $result,
        string $law = '',
        string $detail = ''
    ): void {
        self::$rows[] = [
            'id'          => $id,
            'category'    => $category,
            'severity'    => $severity,
            'title'       => $title,
            'expected'    => $expected,
            'result'      => $result,
            'law'         => $law,
            'detail'      => $detail,
            'recorded_at' => date('c'),
        ];
    }

    public static function flush(string $suite = 'holding_access'): void
    {
        $dir = storage_path('app/security_matrix');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $pass = count(array_filter(self::$rows, fn ($r) => ($r['result'] ?? '') === 'PASS'));
        $fail = count(array_filter(self::$rows, fn ($r) => ($r['result'] ?? '') === 'FAIL'));
        $gap = count(array_filter(self::$rows, fn ($r) => ($r['result'] ?? '') === 'GAP'));
        $skip = count(array_filter(self::$rows, fn ($r) => ($r['result'] ?? '') === 'SKIP'));

        $payload = [
            'suite'        => $suite,
            'generated_at' => date('c'),
            'summary'      => [
                'total' => count(self::$rows),
                'pass'  => $pass,
                'fail'  => $fail,
                'gap'   => $gap,
                'skip'  => $skip,
            ],
            'scenarios'    => self::$rows,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($dir.'/'.$suite.'_latest.json', $json);
        file_put_contents($dir.'/'.$suite.'_'.date('Ymd_His').'.json', $json);
    }
}
