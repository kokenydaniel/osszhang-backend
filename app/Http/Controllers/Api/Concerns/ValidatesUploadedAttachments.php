<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

trait ValidatesUploadedAttachments
{

    protected function validateUploadedAttachment(UploadedFile $file, int $maxKb, array $extensions, string $field = 'file'): void
    {
        $mimesRule = 'mimes:'.implode(',', $extensions);

        $validator = validator(
            [$field => $file],
            [$field => ['required', 'file', 'max:'.$maxKb, $mimesRule]],
        );

        if ($validator->fails() && $this->uploadedFileAllowedByExtension($file, $extensions, $maxKb)) {
            return;
        }

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                $field => $validator->errors()->get($field),
            ]);
        }
    }

    private function uploadedFileAllowedByExtension(UploadedFile $file, array $extensions, int $maxKb): bool
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        if ($ext === '' || ! in_array($ext, $extensions, true)) {
            return false;
        }

        $size = $file->getSize();

        return $size !== false && $size <= ($maxKb * 1024);
    }
}
