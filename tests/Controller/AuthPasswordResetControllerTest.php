<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AuthController;
use App\Controller\PasswordResetController;
use App\Http\Request;
use App\Mapper\UserMapper;
use App\Service\CsrfTokenManager;
use App\Service\MailSenderInterface;
use App\Service\PasswordResetService;
use App\Service\PasswordValidator;
use App\Service\TemplateRenderer;
use PHPUnit\Framework\TestCase;

final class AuthPasswordResetControllerTest extends TestCase
{
    private TemplateRenderer $views;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $this->views = new TemplateRenderer(__DIR__ . '/../../views');
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    public function testShowLoginRendersTemplate(): void
    {
        $controller = $this->makeAuthController();

        $response = $controller->showLogin(new Request('GET', '/login', ['redirect' => '/cart']));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('ログイン', $response->body);
        $this->assertStringContainsString('name="redirect"', $response->body);
        $this->assertStringContainsString('/cart', $response->body);
    }

    public function testShowRegisterRendersTemplateWithPolicy(): void
    {
        $controller = $this->makeAuthController();

        $_SESSION['flash'] = ['error message'];
        $response = $controller->showRegister(new Request('GET', '/register'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('ユーザー登録', $response->body);
        $this->assertStringContainsString('パスワード', $response->body);
        $this->assertStringContainsString('error message', $response->body);
    }

    public function testShowPasswordResetRequestFormRendersTemplate(): void
    {
        $controller = $this->makePasswordResetController();

        $response = $controller->showRequestForm(new Request('GET', '/password/forgot'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('パスワード再設定', $response->body);
        $this->assertStringContainsString('name="_token"', $response->body);
    }

    public function testShowPasswordResetSentRendersTemplate(): void
    {
        $controller = $this->makePasswordResetController();

        $response = $controller->showRequestSent(new Request('GET', '/password/forgot/sent'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('パスワード再設定メールを送信しました', $response->body);
    }

    public function testShowResetFormWithoutTokenShowsInvalidTemplate(): void
    {
        $controller = $this->makePasswordResetController();

        $response = $controller->showResetForm(new Request('GET', '/password/reset', ['token' => '']));

        $this->assertSame(410, $response->status);
        $this->assertStringContainsString('リンクが無効', $response->body);
    }

    private function makeAuthController(): AuthController
    {
        return new AuthController(
            new UserMapper(new AuthFakePdo()),
            new CsrfTokenManager(),
            new PasswordValidator(),
            $this->views,
        );
    }

    private function makePasswordResetController(): PasswordResetController
    {
        $mailer = $this->createMock(MailSenderInterface::class);

        return new PasswordResetController(
            new UserMapper(new AuthFakePdo()),
            new CsrfTokenManager(),
            new PasswordResetService(new AuthFakePdo(), $mailer),
            new PasswordValidator(),
            $this->views,
        );
    }
}

class AuthFakePdo extends \PDO
{
    public function __construct()
    {
    }

    public function prepare($query, $options = null): AuthFakeStatement
    {
        return new AuthFakeStatement();
    }

    public function beginTransaction(): bool
    {
        return true;
    }
    public function commit(): bool
    {
        return true;
    }
    public function rollBack(): bool
    {
        return true;
    }
}

class AuthFakeStatement extends \PDOStatement
{
    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return false;
    }
}
