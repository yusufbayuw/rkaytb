<?php

namespace App\Http\Controllers;

use App\Models\RkaPlatform\Book;
use App\Services\RkaPlatform\{BookExportService, TenantAccess};
use Illuminate\Http\Request;

class RkaPlatformExportController extends Controller
{
    public function __invoke(Request $request, Book $book, string $format,
        TenantAccess $access, BookExportService $export)
    {
        abort_unless(in_array($format, ['pdf','docx','xlsx'], true), 404);
        $access->requireRole($book->organization_id, (int)$request->user()->id,
            ['owner','admin','editor','publisher','reviewer']);
        return $export->export($book, $format);
    }
}
