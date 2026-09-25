<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Convergence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="icon" type="image/png" href="../Images/Convergence Logo.png">
</head>
<body>

    <?php include 'components/left_sidebar.php'; ?>
    <div class="main-area">
        <?php include 'components/header.php'; ?>
        <main class="content">
            
            <div class="page-header">
                <div class="page-heading">
                    <h1>Dashboard</h1>
                    <p>View download statistics for all issues or an individual article</p>
                </div>
            </div>

            <section class="dashboard-section">

                <div class="analytics-selector-layout">
                    <!-- ARTICLE SELECTOR -->
                    <div class="journal-selector">
                        <span class="journal-selector-label">Select Article</span>

                        <!-- ISSUE DROPDOWN -->
                        <div class="selector-group">
                            <label for="issueSelect">Issue</label>
                            <select id="issueSelect" class="dashboard-select">
                                <option value="all">All Articles</option>
                            </select>
                        </div>

                        <!-- ARTICLE DROPDOWN -->
                        <div class="selector-group">
                            <label for="articleSelect">Article</label>
                            <select id="articleSelect" class="dashboard-select" disabled>
                                <option value="all">Select an article</option>
                            </select>
                        </div>


                        <!-- CURRENT SELECTION -->
                        <div class="selection-display" id="selectionDisplay">
                            <span>Currently viewing:</span>
                            <strong id="selectedIssueText">All Articles</strong>
                        </div>
                    </div>

                    <!-- TOTAL DOWNLOADS -->
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fa-solid fa-download"></i>
                        </div>

                        <div class="stat-info">
                            <span class="stat-label">Total Downloads</span>
                            <strong id="totalDownloads">0</strong>
                            <span class="stat-context" id="downloadContext">All Articles</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- DOWNLOAD DISTRIBUTION -->
            <section class="dashboard-section">
                <div class="section-header">
                    <div>
                        <h2>Download Distribution</h2>
                        <p id="chartDescription">Download distribution across all articles</p>
                    </div>
                </div>

                <div class="chart-card">
                    <!-- DOUGHNUT CHART -->
                    <div class="chart-container">
                        <canvas id="continentChart"></canvas>

                        <!-- CENTER TEXT -->
                        <div class="chart-center-text" id="chartCenterText">
                            <strong id="chartCenterValue">0</strong>
                            <span>Downloads</span>
                        </div>


                        <!-- EMPTY STATE -->
                        <div class="chart-empty-state" id="chartEmptyState" hidden>
                            No identified download data
                        </div>
                    </div>

                    <div class="chart-legend" id="chartLegend"></div>
                </div>
            </section>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../javascript/dashboard.js"></script>
</body>
</html>