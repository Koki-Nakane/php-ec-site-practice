<?php
/* 11. ログイン処理の実装:
AuthController というクラスを作成し、login(string $email, string $password) メソッドを実装してください。UserMapper を使ってユーザーを検索し、パスワードが一致すれば、セッションにユーザーIDを保存してください。
*/

declare(strict_types=1);

use App\Mapper\UserMapper;

final class AuthController
{
    private UserMapper $userMapper;

    public function __construct(
        UserMapper $userMapper
    ) {
        $this->userMapper = $userMapper;
    }

    public function login(string $email, string $password): bool
    {
        $user = $this->userMapper->findByEmail($email);

        if ($user === null) {
            return false;
        }

        if (password_verify($password, $user->getHashedPassword()) ) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user->getId();
            return true;
        }

        return false;
    }
}