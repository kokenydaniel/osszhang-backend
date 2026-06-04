<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait ValidatesFeedbackAttachments
{
    /** @param  list<string>  $extensions */
    protected function validateFeedbackFile(UploadedFile $file, int $maxKb, array $extensions, int $index = 0): void
    {
        $mimesRule = 'mimes:'.implode(',', $extensions);

        $validator = validator(
            ['file' => $file],
            ['file' => ['required', 'file', 'max:'.$maxKb, $mimesRule]],
        );

        if ($validator->fails() && $this->feedbackFileAllowedByExtension($file, $extensions)) {
            $sizeValidator = validator(
                ['file' => $file],
                ['file' => ['required', 'file', 'max:'.$maxKb]],
            );
            if ($sizeValidator->fails()) {
                throw ValidationException::withMessages([
                    $index > 0 ? "files.{$index}" : 'file' => $sizeValidator->errors()->get('file'),
                ]);
            }

            return;
        }

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                $index > 0 ? "files.{$index}" : 'file' => $validator->errors()->get('file'),
            ]);
        }
    }

    /** @param  list<string>  $extensions */
    private function feedbackFileAllowedByExtension(UploadedFile $file, array $extensions): bool
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        if ($ext === '' || ! in_array($ext, $extensions, true)) {
            return false;
        }

        $maxBytes = ((int) (config('feedback.attachment_max_kb') ?? 10240)) * 1024;

        return $file->getSize() <= $maxBytes;
    }
}
