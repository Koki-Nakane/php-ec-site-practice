<?php
/* 7. UserMapper クラスの作成:
ProductMapper と同様に、users テーブルと User オブジェクトをマッピングする UserMapper クラスを作成してください。findByEmail(string $email) のような、メールアドレスでユーザーを検索するメソッドも実装しましょう。
*/


declare(strict_types=1);

use App\Model\Database;
use App\Model\User;
use PDO;

require_once __DIR__ . '/vendor/autoload.php';

final class UserMapper
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    

    public function find(int $id): ?User
    {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row){
            return null;
        }
        
        return User::createFromDbRow($row);
    }

    public function findByEmail(string $email): ?User
    {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row){
            return null;
        }
        
        return User::createFromDbRow($row);
    }

    public function save(User $user): void
    {
        if ($user->getId() === null || $user->getId() === 0) {
            $this->insert($user);
        } else {
            $this->update($user);
        }
    }

    private function insert(User $user): void
    {
        $sql = "INSERT INTO users (name, email, password, address) VALUES (:name, :email, :password, :address)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $user->getName(),
            ':email' => $user->getEmail(),
            ':password' => $user->getHashedPassword(),
            ':address' => $user->getAddress(),
        ]);

        $id = $this->pdo->lastInsertId();
        $user->setId((int)$id);
    }

    private function update(User $user): void
    {
        $sql = "UPDATE users SET name = :name, email = :email, password = :password, address = :address WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':name' => $user->getName(),
            ':email' => $user->getEmail(),
            ':password' => $user->getHashedPassword(),
            ':address' => $user->getAddress(),
            ':id' => $user->getId(),
        ]);
    }

    public function delete(User $user): void
    {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user->getId()]);
    }

}
