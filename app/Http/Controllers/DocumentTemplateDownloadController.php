<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentTemplateDownloadController extends Controller
{
    public function __invoke(Request $request, DocumentTemplate $template)
    {
        Gate::authorize('view', $template);

        $path = $template->source_file;

        if (! is_string($path) || $path === '' || ! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'bin';

        $mime = match ($extension) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'html', 'htm' => 'text/html',
            default => 'application/octet-stream',
        };

        return Storage::disk('private')->download(
            $path,
            Str::slug($template->name).'.'.$extension,
            ['Content-Type' => $mime],
        );
    }
}
