<?php
/* 2. User クラスの機能追加:
id, name, email, password (ハッシュ化して保存)、address (住所) のプロパティを持つ User クラスを作成してください。パスワードをハッシュ化して設定するメソッドと、入力されたパスワードがハッシュと一致するか検証するメソッド (verifyPassword) を実装してください。
*/

declare(strict_types=1);

final class User
{
    private string $hashedPassword;

    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        string $plainPassword,
        private string $address,
    ) {
        $this->setPassword($plainPassword);
    }

    public function setPassword($plainPassword): void
    {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $this->hashedPassword = $hashedPassword;
    }

    public function verifyPassword($plainPassword): bool
    {
        return password_verify($plainPassword, $this->hashedPassword);
    }
}
