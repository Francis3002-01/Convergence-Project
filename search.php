<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use Dotenv\Dotenv;

/*Load Environment Variables*/
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$database = new Database();
$pdo = $database->getConnection();

$query = trim($_GET['q'] ?? '');
$results = [];

if (mb_strlen($query) < 2) {
    $query = '';
}

if ($query !== '') {

    $searchTerm = '%' . $query . '%';

    $stmt = $pdo->prepare(
        'SELECT
            ja."journalID",
            ja."title",
            pi."year",
            pi."volume",
            pi."number",
            pi."is_current",
            a."firstName",
            a."lastName"

        FROM "JournalArticle" ja

        LEFT JOIN "PublicationIssue" pi
            ON pi."publicationID" = ja."publicationID"

        LEFT JOIN "ArticleAuthor" aa
            ON aa."journalID" = ja."journalID"

        LEFT JOIN "Author" a
            ON a."authorID" = aa."authorID"

        WHERE
            ja."title" ILIKE :search
            AND pi."is_draft" = FALSE

        ORDER BY
            pi."is_current" DESC,
            pi."year" DESC,
            pi."volume" DESC,
            pi."number" DESC,
            ja."journalID" ASC'
    );

    $stmt->execute([
        ':search' => $searchTerm
    ]);

    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {

        $journalID = (int) $row['journalID'];

        if (!isset($results[$journalID])) {

            $results[$journalID] = [
                'journalID' => $journalID,
                'title' => $row['title'] ?? '',
                'year' => $row['year'] ?? '',
                'volume' => $row['volume'] ?? '',
                'number' => $row['number'] ?? '',
                'is_current' => (bool) $row['is_current'],
                'authors' => []
            ];
        }

        $firstName = trim((string) ($row['firstName'] ?? ''));
        $lastName = trim((string) ($row['lastName'] ?? ''));

        if ($firstName !== '' || $lastName !== '') {

            $authorName = trim($firstName . ' ' . $lastName);

            if (!in_array($authorName,$results[$journalID]['authors'],true)) {
                $results[$journalID]['authors'][] =
                    $authorName;
            }
        }
    }

    $results = array_values($results);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search | Convergence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="includes_css/header.css">
    <link rel="stylesheet" href="css/search.css">
    <link rel="stylesheet"href="includes_css/footer.css">
    <link rel="icon" type="image/jpeg" href="Images/Convergence Logo.png">
</head>

<body>

    <?php include 'includes/header.php'; ?>

    <main class="search-page">

        <section class="search-results">

            <h1>Search Articles</h1>

            <?php if ($query !== ''): ?>

                <p class="search-summary">
                    Search results for:
                    <strong>
                        <?= htmlspecialchars($query) ?>
                    </strong>
                </p>

                <?php if (!empty($results)): ?>

                    <div class="search-result-list">

                        <?php foreach ($results as $result): ?>

                            <article class="search-result">

                                <!-- Current Issue / Archive -->
                                <div class="issue-type">

                                    <?php if ($result['is_current']): ?>

                                        Current Issue

                                    <?php else: ?>

                                        Archive

                                    <?php endif; ?>

                                </div>

                                <!-- Article Title -->
                                <h2>

                                    <a href="article_details.php?journalID=<?= (int) $result['journalID'] ?>">
                                        <?= htmlspecialchars(
                                            $result['title'] ?: 'Untitled Article'
                                        ) ?>
                                    </a>

                                </h2>

                                <!-- Authors -->
                                <?php if (!empty($result['authors'])): ?>

                                    <div class="search-authors">

                                        <?= htmlspecialchars(
                                            implode(
                                                ', ',
                                                $result['authors']
                                            )
                                        ) ?>

                                    </div>

                                <?php else: ?>

                                    <div class="search-authors">
                                        Unspecified
                                    </div>

                                <?php endif; ?>

                                <!-- Issue Information -->
                                <div class="search-issue">

                                    <?= htmlspecialchars(
                                        (string) $result['year']
                                    ) ?>

                                    |

                                    Volume
                                    <?= htmlspecialchars(
                                        (string) $result['volume']
                                    ) ?>

                                    |

                                    Number
                                    <?= htmlspecialchars(
                                        (string) $result['number']
                                    ) ?>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="no-results">

                        <h2>No Articles Found</h2>

                        <p>
                            No articles matched your search.
                            Try using different keywords.
                        </p>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <div class="no-results">

                    <h2>Search for an Article</h2>

                    <p>
                        Enter an article title or keyword
                        in the search bar above.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

    <?php include 'includes/footer.php'; ?>

</body>
</html>
