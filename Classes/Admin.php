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

        if (!$admin || !password_verify($password, $admin['password'])) {
            return false;
        }

        session_regenerate_id(true);

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

    public function getProfile(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        $sql = '
            SELECT
                "adminID",
                "username",
                "email",
                "mustChangePassword",
                "pendingEmail",
                "emailVerificationExpires"
            FROM "Admin"
            WHERE "adminID" = :adminID
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':adminID' => $_SESSION['adminID']
        ]);

        $profile = $stmt->fetch();

        if (!$profile){
            return null;
        }

        $profile['profilePic'] = $this->getProfilePictureUrl();

        return $profile;
    }

    public function getEmailVerificationStatus(): array
    {
        if (!self::isLoggedIn()) {
            return [
                'pending' => false,
                'email' => null,
                'expired' => false
            ];
        }

        $sql = '
            SELECT
                "pendingEmail",
                "emailVerificationExpires"
            FROM "Admin"
            WHERE "adminID" = :adminID
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':adminID' => $_SESSION['adminID']
        ]);

        $result = $stmt->fetch();

        if (!$result || empty($result['pendingEmail'])) {
            return [
                'pending' => false,
                'email' => null,
                'expired' => false
            ];
        }

        if (
            empty($result['emailVerificationExpires']) ||
            strtotime($result['emailVerificationExpires']) <= time()
        ) {

            $clearQuery = '
                UPDATE "Admin"
                SET
                    "pendingEmail" = NULL,
                    "emailVerificationToken" = NULL,
                    "emailVerificationExpires" = NULL
                WHERE "adminID" = :adminID
            ';

            $clearStmt = $this->pdo->prepare($clearQuery);

            $clearStmt->execute([
                ':adminID' => $_SESSION['adminID']
            ]);

            return [
                'pending' => false,
                'email' => null,
                'expired' => true
            ];
        }

        return [
            'pending' => true,
            'email' => $result['pendingEmail'],
            'expired' => false
        ];
    }

    public function changePassword(
        string $currentPassword,
        string $newPassword
    ): bool {
        if (!self::isLoggedIn()) {
            return false;
        }

        $sql = '
            SELECT "password"
            FROM "Admin"
            WHERE "adminID" = :adminID
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':adminID' => $_SESSION['adminID']
        ]);

        $admin = $stmt->fetch();

        if (!$admin || !password_verify($currentPassword, $admin['password'])) {
            return false;
        }

        $hashedPassword = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        $updateSql = '
            UPDATE "Admin"
            SET
                "password" = :password,
                "mustChangePassword" = FALSE
            WHERE "adminID" = :adminID
        ';

        $updateStmt = $this->pdo->prepare($updateSql);

        $updateStmt->execute([
            ':password' => $hashedPassword,
            ':adminID' => $_SESSION['adminID']
        ]);

        $_SESSION['mustChangePassword'] = false;

        return true;
    }

        public function getProfilePictureUrl(): ?string
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        $supabaseUrl = rtrim(
            $_ENV['SUPABASE_URL'] ?? '',
            '/'
        );

        $bucket = $_ENV['SUPABASE_PICTURE_BUCKET'] ?? '';

        if (
            $supabaseUrl === '' ||
            $bucket === ''
        ) {
            return null;
        }

        return
            $supabaseUrl .
            '/storage/v1/object/public/' .
            rawurlencode($bucket) .
            '/admin/profile.jpg';
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