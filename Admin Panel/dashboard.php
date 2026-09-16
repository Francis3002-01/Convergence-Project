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
</head>

<body>

    <?php include 'components/left_sidebar.php'; ?>
    <div class="main-area">
        <?php include 'components/header.php'; ?>
        <main class="content">

            <!-- PAGE HEADER -->
            <div class="page-header">

                <div class="page-heading">

                    <h1>Dashboard</h1>

                    <p>
                        View journal download statistics and download activity.
                    </p>

                </div>

            </div>


            <!-- TOTAL DOWNLOADS -->
            <section class="dashboard-section">

                <div class="total-download-card">

                    <div class="total-download-icon">
                        <i class="fa-solid fa-download"></i>
                    </div>

                    <div class="total-download-info">

                        <span class="stat-label">
                            Total Journal Downloads
                        </span>

                        <strong id="totalDownloads">
                            0
                        </strong>

                    </div>

                </div>

            </section>


            <!-- JOURNAL SELECTOR -->
            <section class="dashboard-section">

                <div class="section-header">

                    <div>
                        <h2>Download Statistics</h2>

                        <p>
                            Select an issue, then choose an article to view its download statistics.
                        </p>
                    </div>

                </div>

                <div class="journal-selector">

                    <span class="journal-selector-label">Select Article</span>

                    <div class="issue-menu" id="issueMenu">
                        <button type="button" class="issue-menu-trigger" data-journal-id="all">
                            All Issues
                        </button>
                    </div>

                </div>

            </section>


            <!-- DOWNLOADS BY CONTINENT -->
            <section class="dashboard-section">

                <div class="section-header">

                    <div>
                        <h2>Downloads by Continent</h2>

                        <p>
                            Number of downloads recorded from each continent.
                        </p>
                    </div>

                </div>


                <div class="continent-card">

                    <div class="continent-list-header">

                        <span>Continent</span>

                        <span>Downloads</span>

                    </div>


                    <div class="continent-list" id="continentList">

                        <div class="continent-row">

                            <span>Africa</span>

                            <strong>0</strong>

                        </div>

                        <div class="continent-row">

                            <span>Antarctica</span>

                            <strong>0</strong>

                        </div>

                        <div class="continent-row">

                            <span>Asia</span>

                            <strong>0</strong>

                        </div>

                        <div class="continent-row">

                            <span>Europe</span>

                            <strong>0</strong>

                        </div>

                        <div class="continent-row">

                            <span>North America</span>

                            <strong>0</strong>

                        </div>

                        <div class="continent-row">

                            <span>Oceania</span>

                            <strong>0</strong>

                        </div>

                        <div class="continent-row">

                            <span>South America</span>

                            <strong>0</strong>

                        </div>

                    </div>

                </div>

            </section>


            <!-- PERCENTAGE GRAPH -->
            <section class="dashboard-section">

                <div class="section-header">

                    <div>
                        <h2>Percentage of Downloads by Continent</h2>

                        <p>
                            Percentage distribution of identified downloads.
                        </p>
                    </div>

                </div>


                <div class="chart-card">

                    <div class="chart-container">

                        <div id="chartEmptyState" hidden style="display:flex; align-items:center; justify-content:center; height:100%; color:#6b7280; font-weight:600;">
                            No data available
                        </div>

                        <canvas id="continentChart"></canvas>

                    </div>


                    <div class="chart-legend" id="chartLegend">

                        <div class="legend-item">

                            <span class="legend-name">
                                Africa
                            </span>

                            <strong>0%</strong>

                        </div>

                        <div class="legend-item">

                            <span class="legend-name">
                                Antarctica
                            </span>

                            <strong>0%</strong>

                        </div>

                        <div class="legend-item">

                            <span class="legend-name">
                                Asia
                            </span>

                            <strong>0%</strong>

                        </div>

                        <div class="legend-item">

                            <span class="legend-name">
                                Europe
                            </span>

                            <strong>0%</strong>

                        </div>

                        <div class="legend-item">

                            <span class="legend-name">
                                North America
                            </span>

                            <strong>0%</strong>

                        </div>

                        <div class="legend-item">

                            <span class="legend-name">
                                Oceania
                            </span>

                            <strong>0%</strong>

                        </div>

                        <div class="legend-item">

                            <span class="legend-name">
                                South America
                            </span>

                            <strong>0%</strong>

                        </div>

                    </div>

                </div>

            </section>

        </main>

    </div>


    <!-- CHART LIBRARY -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- DASHBOARD JAVASCRIPT -->
    <script src="../javascript/dashboard.js"></script>

</body>

</html>