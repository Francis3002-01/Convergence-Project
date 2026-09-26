<?php

class Admin
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    public function login(string $email, string $password): bool
    {
        $sql = '
            SELECT
                "adminID",
                "username",
                "email",
                "password",
                "profilePic",
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

        // Admin account not found or password is incorrect.
        if (!$admin || !password_verify($password, $admin['password'])) {
            return false;
        }

        // Prevent session fixation.
        session_regenerate_id(true);

        // Store admin information in the session.
        $_SESSION['adminID'] = $admin['adminID'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['email'] = $admin['email'];
        $_SESSION['profilePic'] = $admin['profilePic'];
        $_SESSION['mustChangePassword'] = $admin['mustChangePassword'];

        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK LOGIN STATUS
    |--------------------------------------------------------------------------
    */

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['adminID']);
    }


    /*
    |--------------------------------------------------------------------------
    | GET CURRENT ADMIN FROM SESSION
    |--------------------------------------------------------------------------
    */

    public static function getCurrentAdmin(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        return [
            'adminID' => $_SESSION['adminID'],
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'profilePic' => $_SESSION['profilePic'] ?? null,
            'mustChangePassword' => $_SESSION['mustChangePassword'] ?? false
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | GET CURRENT ADMIN PROFILE FROM DATABASE
    |--------------------------------------------------------------------------
    */

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
                "profilePic",
                "mustChangePassword"
            FROM "Admin"
            WHERE "adminID" = :adminID
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':adminID' => $_SESSION['adminID']
        ]);

        $admin = $stmt->fetch();

        if (!$admin) {
            return null;
        }

        return $admin;
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE ADMIN PROFILE
    |--------------------------------------------------------------------------
    */

    public function updateProfile(
        string $username,
        string $email,
        ?string $profilePic = null
    ): bool {
        if (!self::isLoggedIn()) {
            return false;
        }

        if ($profilePic !== null) {

            $sql = '
                UPDATE "Admin"
                SET
                    "username" = :username,
                    "email" = :email,
                    "profilePic" = :profilePic
                WHERE "adminID" = :adminID
            ';

            $stmt = $this->pdo->prepare($sql);

            $success = $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':profilePic' => $profilePic,
                ':adminID' => $_SESSION['adminID']
            ]);

        } else {

            $sql = '
                UPDATE "Admin"
                SET
                    "username" = :username,
                    "email" = :email
                WHERE "adminID" = :adminID
            ';

            $stmt = $this->pdo->prepare($sql);

            $success = $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':adminID' => $_SESSION['adminID']
            ]);
        }

        if (!$success) {
            return false;
        }

        // Keep the session synchronized with the database.
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;

        if ($profilePic !== null) {
            $_SESSION['profilePic'] = $profilePic;
        }

        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | CHANGE PASSWORD
    |--------------------------------------------------------------------------
    */

    public function changePassword(
        string $currentPassword,
        string $newPassword
    ): bool {
        if (!self::isLoggedIn()) {
            return false;
        }

        // Get the current password hash.
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

        if (!$admin) {
            return false;
        }

        // Verify the current password.
        if (!password_verify($currentPassword, $admin['password'])) {
            return false;
        }

        // Hash the new password.
        $newPasswordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        // Save the new password and remove the forced-change flag.
        $sql = '
            UPDATE "Admin"
            SET
                "password" = :password,
                "mustChangePassword" = FALSE
            WHERE "adminID" = :adminID
        ';

        $stmt = $this->pdo->prepare($sql);

        $success = $stmt->execute([
            ':password' => $newPasswordHash,
            ':adminID' => $_SESSION['adminID']
        ]);

        if (!$success) {
            return false;
        }

        // Update the session.
        $_SESSION['mustChangePassword'] = false;

        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

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