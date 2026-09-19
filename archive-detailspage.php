<?php
$issue_id = isset($_GET['issue_id']) ? $_GET['issue_id'] : '1';

// Dynamic issue details mapping
$issue_data = [
    '1' => [
        'year' => '2026',
        'volume' => 'Volume 11',
        'number' => 'Number 1',
        'editorial_note' => 'Welcome to the current issue of Convergence Journal. This volume gathers innovative research on educational technology, community sustainability, and social digital identity.',
        'pdf_link' => 'assets/docs/editorial_note_v11n1.pdf'
    ],
    '2' => [
        'year' => '2025',
        'volume' => 'Volume 10',
        'number' => 'Number 1',
        'editorial_note' => 'Volume 10 highlights adaptive learning models and modern ecological preservation techniques.',
        'pdf_link' => 'assets/docs/editorial_note_v10n1.pdf'
    ],
    '3' => [
        'year' => '2024',
        'volume' => 'Volume 9',
        'number' => 'Number 1',
        'editorial_note' => 'In Volume 9, contributors explore emerging data systems and community initiatives.',
        'pdf_link' => 'assets/docs/editorial_note_v9n1.pdf'
    ]
];

$current_issue = isset($issue_data[$issue_id]) ? $issue_data[$issue_id] : $issue_data['1'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_issue['year']; ?> | <?php echo $current_issue['volume']; ?>, <?php echo $current_issue['number']; ?> - Convergence Journal</title>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="includes_css/header.css">
    <link rel="stylesheet" href="includes_css/footer.css">
    <link rel="stylesheet" href="css/transition.css">
    <link rel="stylesheet" href="css/archive_detailspage.css">
</head>

<body>

    <?php include 'includes/header.php'; ?>

    <main class="archive-details-container">
        
        <!-- Back Link Navigation -->
        <div class="back-nav-container">
            <a href="archive.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to Archive
            </a>
        </div>

        <!-- Issue Header Card (Matches Reference Image) -->
        <section class="issue-header-card">
            <p class="issue-category">CONVERGENCE JOURNAL</p>
            <h1 class="issue-title">Archive Issue</h1>
            
            <div class="issue-meta">
                <span><?php echo $current_issue['year']; ?></span>
                <span class="divider">|</span>
                <span><?php echo $current_issue['volume']; ?></span>
                <span class="divider">|</span>
                <span><?php echo $current_issue['number']; ?></span>
            </div>

            <div class="issue-actions">
                <button onclick="toggleModal(true)" class="btn-solid-red">
                    <i class="fa-solid fa-book-open"></i> Read Editorial Note
                </button>
                <a href="<?php echo $current_issue['pdf_link']; ?>" download class="btn-outline-red">
                    <i class="fa-solid fa-download"></i> Download Editorial Note
                </a>
            </div>
        </section>

        <!-- Articles List Section -->
        <h2 class="section-title">Articles in this Issue</h2>

        <div class="article-list">

            <!-- Article Item 1 -->
            <article class="article-card">
                <h3 class="article-title">
                    <a href="article_details.php?id=1">The Role of Technology in Enhancing Student Engagement</a>
                </h3>
                <p class="article-authors"><i class="fa-solid fa-user"></i> John Doe, Jane Smith</p>
                <p class="article-meta"><i class="fa-solid fa-bookmark"></i> Education &nbsp;·&nbsp; Pages 1–15</p>
                
                <div class="article-actions">
                    <a href="article_details.php?id=1" class="btn-action btn-view">
                        <i class="fa-solid fa-eye"></i> View Article
                    </a>
                    <a href="#" class="btn-action btn-pdf">
                        <i class="fa-solid fa-file-pdf"></i> Download PDF
                    </a>
                </div>
            </article>

            <!-- Article Item 2 -->
            <article class="article-card">
                <h3 class="article-title">
                    <a href="article_details.php?id=2">Sustainable Practices in Local Communities</a>
                </h3>
                <p class="article-authors"><i class="fa-solid fa-user"></i> Robert Johnson, Emily Davis</p>
                <p class="article-meta"><i class="fa-solid fa-bookmark"></i> Environmental Science &nbsp;·&nbsp; Pages 16–30</p>
                
                <div class="article-actions">
                    <a href="article_details.php?id=2" class="btn-action btn-view">
                        <i class="fa-solid fa-eye"></i> View Article
                    </a>
                    <a href="#" class="btn-action btn-pdf">
                        <i class="fa-solid fa-file-pdf"></i> Download PDF
                    </a>
                </div>
            </article>

        </div>
    </main>

    <!-- Editorial Note Modal Overlay -->
    <div id="editorialModal" class="modal-overlay">
        <div class="modal-card">
            <button onclick="toggleModal(false)" class="modal-close">&times;</button>
            <h3 style="color: #a42821; font-size: 20px; margin-bottom: 12px;">
                <i class="fa-solid fa-pen-nib"></i> Editorial Note
            </h3>
            <p style="font-size: 15px; line-height: 1.6; color: #4a5568; margin-bottom: 15px;">
                <?php echo $current_issue['editorial_note']; ?>
            </p>
            <p style="text-align: right; font-weight: 600; font-style: italic; color: #2d3748;">
                — Convergence Editorial Board
            </p>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        function toggleModal(show) {
            document.getElementById('editorialModal').style.display = show ? 'flex' : 'none';
        }
    </script>

</body>

</html>