<?php

class Admin
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function login(string $email, string $password): bool
    {
        $sql = '
            SELECT
                "adminID",
                "username",
                "email",
                "password",
                "mustChangePassword"
            FROM "Admin"
            WHERE "email" = :email
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':email' => $email
        ]);

        $admin = $stmt->fetch();

        // Same result whether the email does not exist
        // or the password is incorrect.
        if (!$admin || !password_verify($password, $admin['password'])) {
            return false;
        }

        // Regenerate session ID after successful login.
        session_regenerate_id(true);

        // Store authenticated admin information.
        $_SESSION['adminID'] = $admin['adminID'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['email'] = $admin['email'];
        $_SESSION['mustChangePassword'] = $admin['mustChangePassword'];

        return true;
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['adminID']);
    }

    public static function getCurrentAdmin(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        return [
            'adminID' => $_SESSION['adminID'],
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'mustChangePassword' => $_SESSION['mustChangePassword'] ?? false
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}