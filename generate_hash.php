<?php

$hash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';

    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Generate Password Hash</title>
</head>

<body>

    <h1>Generate Password Hash</h1>

    <form method="POST">

        <label for="password">
            Password:
        </label>

        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <button type="submit">
            Generate Hash
        </button>

    </form>

    <?php if ($hash !== ''): ?>

        <h2>Generated Hash</h2>

        <textarea
            rows="4"
            cols="80"
            readonly
        ><?php echo htmlspecialchars($hash, ENT_QUOTES, 'UTF-8'); ?></textarea>

    <?php endif; ?>

</body>
</html>