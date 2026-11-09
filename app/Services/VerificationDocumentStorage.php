<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class VerificationDocumentStorage
{
    /** Decode and re-encode a single ID image to strip metadata before private storage. */
    public function store(UploadedFile $file): string
    {
        if (!$file->isValid() || $file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['id_photo' => 'Choose a valid image no larger than 5 MB.']);
        }

        $bytes = @file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes ?: '');
        $imageInfo = $bytes === false ? false : @getimagesizefromstring($bytes);
        $allowed = [
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            'image/webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : -1,
        ];

        if (!is_array($imageInfo) || !isset($allowed[$mime]) || (int) $imageInfo[2] !== $allowed[$mime]) {
            throw ValidationException::withMessages(['id_photo' => 'Use a genuine JPEG, PNG, or WebP image. SVG and other files are not accepted.']);
        }

        [$width, $height] = [(int) $imageInfo[0], (int) $imageInfo[1]];
        if ($width < 200 || $height < 120 || $width > 5000 || $height > 5000 || ($width * $height) > 12_000_000) {
            throw ValidationException::withMessages(['id_photo' => 'The image must be at least 200 x 120 pixels, no larger than 5000 x 5000 pixels, and within the safe image-processing limit.']);
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            throw ValidationException::withMessages(['id_photo' => 'Secure image processing is unavailable on this server. Please use the alternative review method.']);
        }

        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            throw ValidationException::withMessages(['id_photo' => 'The image could not be decoded safely.']);
        }

        try {
            ob_start();
            $encoded = imagewebp($image, null, 88);
            $webp = ob_get_clean();
            if (!$encoded || !is_string($webp) || $webp === '') {
                throw ValidationException::withMessages(['id_photo' => 'The image could not be prepared for secure storage.']);
            }

            $path = 'verification-documents/' . bin2hex(random_bytes(24)) . '.webp';
            if (!Storage::disk('local')->put($path, $webp)) {
                throw ValidationException::withMessages(['id_photo' => 'The private document store is unavailable. Please try again.']);
            }
            return $path;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['id_photo' => 'The image could not be processed securely.']);
        } finally {
            imagedestroy($image);
        }
    }

    public function delete(?string $path): bool
    {
        if (!is_string($path) || !preg_match('/^verification-documents\/[a-f0-9]{48}\.webp$/', $path)) {
            return false;
        }

        $disk = Storage::disk('local');
        return $disk->exists($path) && $disk->delete($path);
    }
}
