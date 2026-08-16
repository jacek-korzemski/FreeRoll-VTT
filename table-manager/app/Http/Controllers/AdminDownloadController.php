<?php

namespace App\Http\Controllers;

use App\Models\VttTable;
use App\Services\Admin\TableDiskReader;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDownloadController extends Controller
{
    public function json(VttTable $table, string $kind): StreamedResponse
    {
        $name = $kind === 'rolls' ? 'rolls.json' : 'state.json';
        $reader = app(TableDiskReader::class);
        $file = $reader->readStateFile($table->loadMissing('user'), $name);
        $filename = $table->slug.'-'.$name;

        return response()->streamDownload(function () use ($file) {
            echo $file['raw'] !== '' ? $file['raw'] : '{}';
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function asset(VttTable $table, Request $request, TableDiskReader $reader): BinaryFileResponse
    {
        $table->loadMissing('user');
        $relative = (string) $request->query('path', '');
        $path = $reader->resolveAssetPath($table, $relative);
        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }
}
