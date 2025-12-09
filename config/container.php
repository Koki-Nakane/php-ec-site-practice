<?php

declare(strict_types=1);

use App\Contracts\ContainerInterface;
use App\Contracts\EventDispatcherInterface;
use App\Controller\Admin\DashboardController;
use App\Controller\Admin\OrderController as AdminOrderController;
use App\Controller\Admin\ProductController as AdminProductController;
use App\Controller\Admin\UserController as AdminUserController;
use App\Controller\AuthController;
use App\Controller\CartController;
use App\Controller\HomeController;
use App\Controller\OrderController;
use App\Controller\PasswordResetController;
use App\Controller\PostalCodeController;
use App\Controller\PostController;
use App\Controller\PostPageController;
use App\Controller\ProductReviewController;
use App\Controller\SecurityController;
use App\Infrastructure\Container;
use App\Infrastructure\EventDispatcher;
use App\Infrastructure\Middleware\CommentAuthorOrAdminMiddleware;
use App\Infrastructure\Middleware\PostAuthorOrAdminMiddleware;
use App\Listener\LogUserCreatedListener;
use App\Listener\SendWelcomeEmailListener;
use App\Mapper\CommentMapper;
use App\Mapper\OrderMapper;
use App\Mapper\PostMapper;
use App\Mapper\ProductMapper;
use App\Mapper\ReviewMapper;
use App\Mapper\UserMapper;
use App\Model\Database;
use App\Service\CsrfTokenManager;
use App\Service\MailSenderInterface;
use App\Service\OrderCsvExporter;
use App\Service\PasswordResetService;
use App\Service\PasswordValidator;
use App\Service\PhpMailSender;
use App\Service\ReviewService;
use App\Service\TemplateRenderer;
use App\Service\ZipcloudClient;

// Build and return a DI container with core services.
$container = new Container();

// PDO (shared)
$container->set(\PDO::class, function (): \PDO {
    return Database::getInstance()->getConnection();
}, shared: true);

// Mappers (shared)
$container->set(UserMapper::class, function (ContainerInterface $c): UserMapper {
    return new UserMapper(
        $c->get(\PDO::class),
        $c->get(EventDispatcherInterface::class)
    );
}, shared: true);

$container->set(ProductMapper::class, function (ContainerInterface $c): ProductMapper {
    return new ProductMapper($c->get(\PDO::class));
}, shared: true);

$container->set(PostMapper::class, function (ContainerInterface $c): PostMapper {
    return new PostMapper($c->get(\PDO::class));
}, shared: true);

$container->set(CommentMapper::class, function (ContainerInterface $c): CommentMapper {
    return new CommentMapper($c->get(\PDO::class));
}, shared: true);

$container->set(ReviewMapper::class, function (ContainerInterface $c): ReviewMapper {
    return new ReviewMapper($c->get(\PDO::class));
}, shared: true);

$container->set(OrderMapper::class, function (ContainerInterface $c): OrderMapper {
    return new OrderMapper($c->get(\PDO::class), $c->get(ProductMapper::class));
}, shared: true);

$container->set(OrderCsvExporter::class, function (ContainerInterface $c): OrderCsvExporter {
    return new OrderCsvExporter($c->get(OrderMapper::class));
}, shared: true);

$container->set(MailSenderInterface::class, function (): MailSenderInterface {
    $env = static function (string $key, ?string $default = null): ?string {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? $default : $trimmed;
    };

    $from = $env('MAIL_FROM', 'no-reply@example.com');
    $fromName = $env('MAIL_FROM_NAME', 'EC Practice App');
    $host = $env('MAIL_HOST', 'mailhog');
    $port = (int) ($env('MAIL_PORT', '1025'));
    $username = $env('MAIL_USERNAME', '');
    $password = $env('MAIL_PASSWORD', '');
    $encryption = $env('MAIL_ENCRYPTION');
    $timeout = (float) ($env('MAIL_TIMEOUT', '5'));

    $smtpAuthDefault = $username !== '' ? 'true' : 'false';
    $smtpAuthFlag = strtolower((string) $env('MAIL_SMTP_AUTH', $smtpAuthDefault));
    $smtpAuth = in_array($smtpAuthFlag, ['1', 'true', 'on'], true);

    return new PhpMailSender(
        $from,
        $fromName,
        $host,
        $port,
        $smtpAuth,
        $username ?: null,
        $password ?: null,
        $encryption ?: null,
        $timeout
    );
}, shared: true);

$container->set(CsrfTokenManager::class, function (): CsrfTokenManager {
    return new CsrfTokenManager();
}, shared: true);

$container->set(PasswordValidator::class, function (): PasswordValidator {
    return new PasswordValidator();
}, shared: true);

$container->set(ZipcloudClient::class, function (): ZipcloudClient {
    return new ZipcloudClient();
}, shared: true);

$container->set(PasswordResetService::class, function (ContainerInterface $c): PasswordResetService {
    return new PasswordResetService(
        $c->get(\PDO::class),
        $c->get(MailSenderInterface::class)
    );
}, shared: true);

$container->set(ReviewService::class, function (ContainerInterface $c): ReviewService {
    return new ReviewService(
        $c->get(ReviewMapper::class),
        $c->get(\PDO::class)
    );
}, shared: true);

$container->set(TemplateRenderer::class, function (): TemplateRenderer {
    return new TemplateRenderer(__DIR__ . '/../views');
}, shared: true);

// Controllers (shared)
$container->set(AuthController::class, function (ContainerInterface $c): AuthController {
    return new AuthController(
        $c->get(UserMapper::class),
        $c->get(CsrfTokenManager::class),
        $c->get(PasswordValidator::class)
    );
}, shared: true);

$container->set(HomeController::class, function (ContainerInterface $c): HomeController {
    return new HomeController(
        $c->get(ProductMapper::class),
        $c->get(CsrfTokenManager::class),
        $c->get(TemplateRenderer::class)
    );
}, shared: true);

$container->set(OrderController::class, function (ContainerInterface $c): OrderController {
    return new OrderController(
        $c->get(\PDO::class),
        $c->get(UserMapper::class),
        $c->get(ProductMapper::class),
        $c->get(OrderMapper::class),
        $c->get(OrderCsvExporter::class),
        $c->get(CsrfTokenManager::class)
    );
}, shared: true);

$container->set(CartController::class, function (ContainerInterface $c): CartController {
    return new CartController(
        $c->get(ProductMapper::class)
    );
}, shared: true);

$container->set(DashboardController::class, function (ContainerInterface $c): DashboardController {
    return new DashboardController(
        $c->get(TemplateRenderer::class)
    );
}, shared: true);

$container->set(AdminProductController::class, function (ContainerInterface $c): AdminProductController {
    return new AdminProductController(
        $c->get(ProductMapper::class),
        $c->get(TemplateRenderer::class),
        $c->get(CsrfTokenManager::class)
    );
}, shared: true);

$container->set(AdminUserController::class, function (ContainerInterface $c): AdminUserController {
    return new AdminUserController(
        $c->get(UserMapper::class),
        $c->get(TemplateRenderer::class),
        $c->get(CsrfTokenManager::class)
    );
}, shared: true);

$container->set(AdminOrderController::class, function (ContainerInterface $c): AdminOrderController {
    return new AdminOrderController(
        $c->get(OrderMapper::class),
        $c->get(TemplateRenderer::class),
        $c->get(CsrfTokenManager::class)
    );
}, shared: true);

$container->set(PostController::class, function (ContainerInterface $c): PostController {
    return new PostController(
        $c->get(PostMapper::class),
        $c->get(CommentMapper::class),
        $c->get(AuthController::class),
        $c->get(UserMapper::class),
        $c->get(\PDO::class)
    );
}, shared: true);

$container->set(PostPageController::class, function (ContainerInterface $c): PostPageController {
    return new PostPageController(
        $c->get(PostMapper::class),
        $c->get(TemplateRenderer::class),
        $c->get(AuthController::class),
    );
}, shared: true);

$container->set(SecurityController::class, function (ContainerInterface $c): SecurityController {
    return new SecurityController($c->get(CsrfTokenManager::class));
}, shared: true);

$container->set(PasswordResetController::class, function (ContainerInterface $c): PasswordResetController {
    return new PasswordResetController(
        $c->get(UserMapper::class),
        $c->get(CsrfTokenManager::class),
        $c->get(PasswordResetService::class),
        $c->get(PasswordValidator::class)
    );
}, shared: true);

$container->set(ProductReviewController::class, function (ContainerInterface $c): ProductReviewController {
    return new ProductReviewController(
        $c->get(ProductMapper::class),
        $c->get(ReviewService::class),
        $c->get(TemplateRenderer::class),
        $c->get(CsrfTokenManager::class),
        $c->get(AuthController::class)
    );
}, shared: true);

$container->set(PostalCodeController::class, function (ContainerInterface $c): PostalCodeController {
    return new PostalCodeController(
        $c->get(ZipcloudClient::class)
    );
}, shared: true);

$container->set(PostAuthorOrAdminMiddleware::class, function (ContainerInterface $c): PostAuthorOrAdminMiddleware {
    return new PostAuthorOrAdminMiddleware(
        $c->get(AuthController::class),
        $c->get(PostMapper::class)
    );
}, shared: true);

$container->set(CommentAuthorOrAdminMiddleware::class, function (ContainerInterface $c): CommentAuthorOrAdminMiddleware {
    return new CommentAuthorOrAdminMiddleware(
        $c->get(AuthController::class),
        $c->get(\PDO::class)
    );
}, shared: true);

// Event dispatcher and default listeners
$container->set(EventDispatcherInterface::class, function (): EventDispatcherInterface {
    return new EventDispatcher();
}, shared: true);

// Register default listeners once
/** @var EventDispatcherInterface $dispatcher */
$dispatcher = $container->get(EventDispatcherInterface::class);
$dispatcher->on('user.created', new LogUserCreatedListener(), priority: 10);
$dispatcher->on('user.created', new SendWelcomeEmailListener(), priority: 0);

return $container;
