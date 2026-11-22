
<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>404 - Page Not Found | Convergence</title>

    <meta name="robots" content="noindex, follow">

    <link rel="icon" type="image/png" href="Images/Convergence Logo.png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
            background: #FAF3E3;
            font-family: Arial, Helvetica, sans-serif;
            color: #222;
            line-height: 1.6;
        }

        /* =========================
           404 CONTENT
        ========================= */

        .error-container {
            width: 100%;
            max-width: 750px;
            margin: auto;
            text-align: center;
        }

        .error-number {
            color: #a42821;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(130px, 22vw, 190px);
            font-weight: normal;
            line-height: 1.1;
            letter-spacing: -5px;
        }

        .error-divider {
            width: 55px;
            height: 2px;
            margin: 20px auto 25px;
            background: #a42821;
        }

        .error-container h1 {
            margin-bottom: 15px;
            color: #333;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 36px;
            font-weight: normal;
        }

        .error-description {
            max-width: 580px;
            margin: 0 auto 12px;
            color: #555;
            font-size: 17px;
            line-height: 1.8;
        }

        .error-message {
            max-width: 550px;
            margin: 0 auto;
            color: #777;
            font-size: 15px;
            line-height: 1.8;
        }

        /* =========================
           BUTTONS
        ========================= */

        .error-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 30px;
        }

        .home-button,
        .back-button {
            display: inline-block;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .home-button {
            background: #a42821;
            border: 1px solid #a42821;
            color: #fff;
        }

        .home-button:hover {
            background: #7f1f1a;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
        }

        .back-button {
            background: transparent;
            border: 1px solid #a42821;
            color: #a42821;
        }

        .back-button:hover {
            background: #a42821;
            color: #fff;
            transform: translateY(-2px);
        }

        .home-button i,
        .back-button i {
            margin-right: 7px;
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 650px) {

            body {
                padding: 20px;
            }

            .error-number {
                font-size: 125px;
            }

            .error-container h1 {
                font-size: 30px;
            }

            .error-description {
                font-size: 16px;
            }

            .error-message {
                font-size: 14px;
            }

            .error-actions {
                flex-direction: column;
                gap: 12px;
            }

            .error-actions a {
                width: 100%;
                max-width: 280px;
            }
        }
    </style>
</head>

<body>

    <main class="error-container">

        <div class="error-number">404</div>

        <div class="error-divider"></div>

        <h1>Page Not Found</h1>

        <p class="error-description">
            The page you are looking for may have been moved,
            removed, or is temporarily unavailable.
        </p>

        <p class="error-message">
            Please check the URL or return to the Convergence homepage
            to continue exploring our journal.
        </p>

        <div class="error-actions">

            <a href="home.php" class="home-button">
                <i class="fa-solid fa-house"></i>
                Return to Homepage
            </a>

            <a href="javascript:history.back()" class="back-button">
                <i class="fa-solid fa-arrow-left"></i>
                Go Back
            </a>

        </div>

    </main>

</body>
</html>