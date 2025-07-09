<?php

namespace App\Services\Message;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;


class MessageContentValidator
{
    public static function validate(array $content): ?array
    {
        $rules = [
            'text' => 'nullable|array',
            'text.ciphertext' => 'required_with:text|string',
            'text.iv' => 'required_with:text|string',
            'text.tag' => 'required_with:text|string',

            'attachments' => 'nullable|array',
            'attachments.*.uuid' => 'required_with:attachments|string',
            'attachments.*.name' => 'required_with:attachments|string',
            'attachments.*.mime' => 'required_with:attachments|string',
        ];

        $validator = Validator::make($content, $rules);

        $validator->after(function ($validator) use ($content) {
            $textEmpty = empty($content['text']);
            $attachmentsEmpty = empty($content['attachments']);
            if ($textEmpty && $attachmentsEmpty) {
                $validator->errors()->add('content', 'Either text or attachments must be provided in content.');
            }
        });

        $validated = $validator->validate();
        return $validated;
    }
}
