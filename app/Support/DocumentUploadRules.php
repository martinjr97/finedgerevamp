<?php

namespace App\Support;

class DocumentUploadRules
{
    /** Laravel file `max` rule is in kilobytes. */
    public const MAX_KILOBYTES = 15360;

    public const MAX_BYTES = 15728640;

    public const HINT_IMAGE = 'JPEG, PNG, JPG (Max: 15MB)';

    public const HINT_PDF_IMAGE = 'PDF, JPEG, PNG, JPG (Max: 15MB)';

    public static function requiredImage(): string
    {
        return 'required|image|mimes:jpeg,png,jpg|max:'.self::MAX_KILOBYTES;
    }

    public static function nullableImage(): string
    {
        return 'nullable|image|mimes:jpeg,png,jpg|max:'.self::MAX_KILOBYTES;
    }

    public static function nullableMultipleImages(): string
    {
        return 'nullable|image|mimes:jpeg,jpg,png,gif|max:'.self::MAX_KILOBYTES;
    }

    public static function requiredPdfOrImage(): string
    {
        return 'required|file|mimes:pdf,jpeg,png,jpg|max:'.self::MAX_KILOBYTES;
    }

    public static function nullablePdfOrImage(): string
    {
        return 'nullable|file|mimes:pdf,jpeg,png,jpg|max:'.self::MAX_KILOBYTES;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function registrationFileRules(): array
    {
        return [
            'front_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:'.self::MAX_KILOBYTES],
            'back_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:'.self::MAX_KILOBYTES],
            'profile_picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:'.self::MAX_KILOBYTES],
            'bank_statement' => ['nullable', 'file', 'mimes:pdf,jpeg,png,jpg', 'max:'.self::MAX_KILOBYTES],
            'payslip' => ['nullable', 'file', 'mimes:pdf,jpeg,png,jpg', 'max:'.self::MAX_KILOBYTES],
            'stand_picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function nullableImageArray(bool $includeGif = false): array
    {
        $mimes = $includeGif ? 'jpeg,jpg,png,gif' : 'jpeg,jpg,png';

        return ['nullable', 'image', 'mimes:'.$mimes, 'max:'.self::MAX_KILOBYTES];
    }

    /**
     * @return array<int, string>
     */
    public static function avatarRule(): array
    {
        return ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES];
    }

    /**
     * @return array<int, string>
     */
    public static function groupLoanDocumentRule(): array
    {
        return ['required', 'file', 'mimes:jpeg,jpg,png,pdf,doc,docx', 'max:'.self::MAX_KILOBYTES];
    }

    /**
     * @return array<int, string>
     */
    public static function nullableSupportAttachment(): array
    {
        return ['nullable', 'file', 'mimes:pdf,jpeg,jpg,png', 'max:'.self::MAX_KILOBYTES];
    }
}
