<?php

declare(strict_types=1);

namespace App\Service;

final class PasswordValidator
{
    public const MIN_LENGTH = 8;

    /**
     * @return string[] list of validation error messages
     */
    public function validate(string $password, string $confirmation): array
    {
        $password = trim($password);
        $confirmation = trim($confirmation);

        $errors = [];

        if ($password === '' || $confirmation === '') {
            $errors[] = 'パスワードと確認用パスワードを入力してください。';
            return $errors;
        }

        if ($password !== $confirmation) {
            $errors[] = 'パスワードが確認用と一致しません。';
        }

        if (mb_strlen($password, 'UTF-8') < self::MIN_LENGTH) {
            $errors[] = sprintf('パスワードは最低 %d 文字以上で入力してください。', self::MIN_LENGTH);
        }

        $classes = 0;
        if (preg_match('/[a-z]/', $password)) {
            $classes++;
        }
        if (preg_match('/[A-Z]/', $password)) {
            $classes++;
        }
        if (preg_match('/\d/', $password)) {
            $classes++;
        }
        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $classes++;
        }

        if ($classes < 2) {
            $errors[] = '英大文字・英小文字・数字・記号のうち2種類以上を組み合わせてください。';
        }

        return $errors;
    }

    public function getPolicyDescription(): string
    {
        return sprintf(
            'パスワードは最低 %d 文字以上で、英大文字・英小文字・数字・記号のうち2種類以上を組み合わせてください。',
            self::MIN_LENGTH
        );
    }
}
