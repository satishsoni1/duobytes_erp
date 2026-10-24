<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /**
     * Stream a CSV download without loading everything in memory.
     *
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly
            fputcsv($out, $headings, escape: '\\');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row), escape: '\\');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Read an uploaded CSV into associative rows keyed by snake_case headings.
     *
     * @return array<int, array<string, string>>
     */
    public static function read(string $path, int $limit = 5000): array
    {
        $handle = fopen($path, 'r');
        $headings = array_map(
            fn ($h) => str(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))->trim()->snake()->toString(),
            fgetcsv($handle, escape: '\\') ?: []
        );

        $rows = [];
        while (($line = fgetcsv($handle, escape: '\\')) !== false && count($rows) < $limit) {
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($headings, array_pad(array_slice($line, 0, count($headings)), count($headings), ''));
        }
        fclose($handle);

        return $rows;
    }
}
