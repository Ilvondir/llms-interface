<?php

namespace App\Rules\Chat;

use App\Support\Chat\MessageContent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class MessageContentRule implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $error = MessageContent::validationError($value);

        if ($error !== null) {
            $fail($error);
        }
    }
}
