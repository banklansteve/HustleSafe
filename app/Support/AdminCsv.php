<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminCsv
{
    /**
     * @param  list<string>  $header
     * @param  callable(resource $out): void  $writeRows
     */
    public static function download(string $filename, array $header, callable $writeRows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $writeRows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            $writeRows($out);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
