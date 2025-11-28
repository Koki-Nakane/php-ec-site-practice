<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PasswordValidator;
use PHPUnit\Framework\TestCase;

final class PasswordValidatorTest extends TestCase
{
    private PasswordValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PasswordValidator();
    }

    public function testValidPasswordPasses(): void
    {
        $errors = $this->validator->validate('Secure123', 'Secure123');
        $this->assertSame([], $errors);
    }

    public function testTooShortIsRejected(): void
    {
        $errors = $this->validator->validate('Ab1', 'Ab1');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('最低', $errors[0]);
    }

    public function testMismatchIsRejected(): void
    {
        $errors = $this->validator->validate('Secure123', 'Secure124');
        $this->assertContains('パスワードが確認用と一致しません。', $errors);
    }

    public function testMissingCharacterClassesIsRejected(): void
    {
        $errors = $this->validator->validate('aaaaaaaa', 'aaaaaaaa');
        $this->assertStringContainsString('2種類以上', $errors[array_key_last($errors)]);
    }
}
