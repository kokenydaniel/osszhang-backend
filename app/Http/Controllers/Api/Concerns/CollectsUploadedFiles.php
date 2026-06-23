<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

trait CollectsUploadedFiles
{

    protected function collectUploadedFiles(Request $request, string $key = 'files'): array
    {
        $uploaded = $request->file($key);

        if ($uploaded instanceof UploadedFile) {
            return $uploaded->isValid() ? [$uploaded] : [];
        }

        if (is_array($uploaded)) {
            return array_values(array_filter(
                $uploaded,
                fn ($file) => $file instanceof UploadedFile && $file->isValid(),
            ));
        }

        $collected = [];
        foreach ($request->allFiles() as $name => $file) {
            if ($name !== $key && ! str_starts_with((string) $name, $key.'.') && ! str_starts_with((string) $name, $key.'[')) {
                continue;
            }

            if (is_array($file)) {
                foreach ($file as $item) {
                    if ($item instanceof UploadedFile && $item->isValid()) {
                        $collected[] = $item;
                    }
                }
            } elseif ($file instanceof UploadedFile && $file->isValid()) {
                $collected[] = $file;
            }
        }

        return $collected;
    }
}
