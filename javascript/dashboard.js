document.addEventListener("DOMContentLoaded", function () {

    let continentChart = null;
    let dashboardIssues = [];
    const continentColors = [
        "#4E79A7", // Africa
        "#F28E2B", // Antarctica
        "#59A14F", // Asia
        "#E15759", // Europe
        "#B07AA1", // North America
        "#76B7B2", // Oceania
        "#EDC948"  // South America
    ];

    // LOAD DASHBOARD DATA
    async function loadDashboardData(journalID = "all",selectedLabel = "All Articles") {
        try {

            let apiUrl = "dashboard_api.php";

            // Add journal filter only when a specific article is selected.
            if (journalID !== "all") {
                apiUrl +="?journalID=" +encodeURIComponent(journalID);
            }

            const response = await fetch(apiUrl, {
                method: "GET",
                cache: "no-store",
                headers: {
                    "Accept": "application/json"
                }
            });

            if (!response.ok) {
                throw new Error("Server returned status " +response.status);
            }

            const contentType =response.headers.get("content-type") || "";

            if (!contentType.includes("application/json")) {
                throw new Error("The API did not return JSON.");
            }


            const data = await response.json();
            console.log("Dashboard API response:",data);

            if (!data.success) {
                throw new Error(
                    data.message ||
                    "Failed to load dashboard data."
                );

            }

            // SAVE ISSUES
           dashboardIssues = data.issues || [];

            if (journalID === "all") {
                updateIssueMenu(dashboardIssues);
            }

            // UPDATE TOTAL DOWNLOADS
            const totalDownloadsElement =document.getElementById("totalDownloads");

            if (totalDownloadsElement) {
                totalDownloadsElement.textContent =Number(data.totalDownloads || 0).toLocaleString();
            }

            // UPDATE DOWNLOAD CONTEXT
            const downloadContext =document.getElementById("downloadContext");

            if (downloadContext) {
                downloadContext.textContent =selectedLabel;
            }

            // UPDATE STATISTICS DESCRIPTION
            const statisticsDescription =document.getElementById("statisticsDescription");

            if (statisticsDescription) {
                if (journalID === "all") {
                    statisticsDescription.textContent ="Total downloads across all articles.";
                } 
                
                else {
                    statisticsDescription.textContent ="Downloads for the selected article.";
                }

            }

            // UPDATE CHART CENTER NUMBER
            const chartCenterValue =document.getElementById("chartCenterValue");

            if (chartCenterValue) {
                chartCenterValue.textContent =Number(data.totalDownloads || 0).toLocaleString();
            }

            // UPDATE CHART DESCRIPTION
            const chartDescription =document.getElementById("chartDescription");

            if (chartDescription) {
                if (journalID === "all") {
                    chartDescription.textContent ="Download distribution across all articles.";
                } 
                
                else {
                    chartDescription.textContent ="Download distribution for the selected article.";
                }

            }

            // UPDATE PERCENTAGE LEGEND
            updatePercentageList(data.continents || []);
            createDoughnutChart(data.continents || []);

        } 
        
        catch (error) {
            console.error("Dashboard error:",error);
        }
    }

    function updateIssueMenu(issues) {

        const issueSelect =document.getElementById("issueSelect");
        const articleSelect =document.getElementById("articleSelect");
        if (!issueSelect || !articleSelect) {
            return;
        }

        // Clear existing Issue options.
        issueSelect.innerHTML = "";

        // Clear existing Article options.
        articleSelect.innerHTML = "";

        // ALL ARTICLES OPTION
        const allArticlesOption = document.createElement("option");

        allArticlesOption.value = "all";
        allArticlesOption.textContent = "All Articles";
        issueSelect.appendChild(allArticlesOption);

        // ADD ISSUES
        issues.forEach(function (issue) {
            // Skip issues that do not have articles.
            if (!issue.articles ||issue.articles.length === 0) {
                return;
            }

            const option = document.createElement("option");
            option.value = issue.publicationID;

            option.textContent =
                "Volume " +
                issue.volume +
                ", Issue " +
                issue.number +
                " (" +
                issue.year +
                ")";

            issueSelect.appendChild(option);
        });

        // RESET ARTICLE DROPDOWN
        const defaultArticleOption = document.createElement("option");
        defaultArticleOption.value = "all";
        defaultArticleOption.textContent = "Select an article";
        articleSelect.appendChild(defaultArticleOption);
        articleSelect.disabled = true;
    }

    // ISSUE DROPDOWN CHANGE
    const issueSelect = document.getElementById("issueSelect");
    const articleSelect = document.getElementById("articleSelect");

    if (issueSelect) {

        issueSelect.addEventListener(
            "change",
            function () {

                const selectedIssueID = this.value;

                // RESET ARTICLE DROPDOWN
                articleSelect.innerHTML = "";

                // ALL ARTICLES
                if (selectedIssueID === "all") {
                    const allArticlesOption = document.createElement("option");
                    allArticlesOption.value ="all";
                    allArticlesOption.textContent = "Select an article";
                    articleSelect.appendChild(allArticlesOption);
                    articleSelect.disabled = true;
                    updateSelectionDisplay("All Articles","");
                    loadDashboardData("all","All Articles");
                    return;

                }

                // FIND SELECTED ISSUE
                const selectedIssue =
                    dashboardIssues.find(
                        function (issue) {

                            return String(
                                issue.publicationID
                            ) === selectedIssueID;

                        }
                    );


                if (!selectedIssue) {
                    return;
                }

                // DEFAULT ARTICLE OPTION
                const defaultArticleOption = document.createElement("option");
                defaultArticleOption.value = "all";
                defaultArticleOption.textContent ="Select an article";
                articleSelect.appendChild(defaultArticleOption);

                // ADD ARTICLES FOR SELECTED ISSUE
                selectedIssue.articles.forEach(
                    function (article) {
                        const option =document.createElement("option");
                        option.value =article.journalID;
                        option.textContent =article.title;
                        articleSelect.appendChild(option);
                    }
                );

                // Enable Article dropdown.
                articleSelect.disabled = false;

                // Show selected issue.
                const selectedIssueText =
                        issueSelect.options[
                        issueSelect.selectedIndex
                    ].text;


                updateSelectionDisplay(selectedIssueText,"");
            }
        );

    }

    // ARTICLE DROPDOWN CHANGE
    if (articleSelect) {

        articleSelect.addEventListener(
            "change",
            function () {

                const articleID =this.value;

                // Do nothing if no article is selected.
                if (articleID === "all") {
                    return;
                }

                // Get selected article title.
                const selectedArticleText =
                    this.options[
                        this.selectedIndex
                    ].text;


                // Get selected issue
                const selectedIssueText =
                    issueSelect.options[
                        issueSelect.selectedIndex
                    ].text;


                // Show the current selection
                updateSelectionDisplay(
                    selectedIssueText,
                    selectedArticleText
                );


                // Load statistics for selected article
                loadDashboardData(
                    String(articleID),
                    selectedArticleText
                );

            }
        );

    }

    // UPDATE CURRENT SELECTION DISPLAY
    function updateSelectionDisplay(issueText,articleText) {
        const selectedIssueText =document.getElementById("selectedIssueText");

        if (!selectedIssueText) {
            return;
        }

        // NO SPECIFIC ISSUE
        if (issueText === "All Articles") {
            selectedIssueText.textContent ="All Articles";
            return;
        }

        // ISSUE SELECTED BUT NO ARTICLE YET
        if (!articleText ||articleText === "Select an article") {
            selectedIssueText.textContent =issueText;
            return;
        }

        // ISSUE + ARTICLE SELECTED
        selectedIssueText.textContent = issueText +" — " +articleText;
    }

    // UPDATE PERCENTAGE LEGEND
    function updatePercentageList(continents) {

        const chartLegend =document.getElementById("chartLegend");
        if (!chartLegend) {
            return;
        }

        chartLegend.innerHTML = "";

        const totalDownloads =
            continents.reduce(

                function (sum, continent) {
                    return sum +Number(continent.downloads || 0);
                },
                0

            );


        // NO DATA
        if (totalDownloads <= 0) {
            const item =document.createElement("div");
            item.className ="legend-item";
            const nameWrapper =document.createElement("div");
            nameWrapper.className ="legend-name-wrapper";
            const name =document.createElement("span");
            name.className ="legend-name";
            name.textContent ="No data available";
            nameWrapper.appendChild(name);
            const percentage = document.createElement("strong");
            percentage.textContent = "0%";
            item.appendChild(nameWrapper);
            item.appendChild(percentage);
            chartLegend.appendChild(item);
            return;
        }

        // CONTINENTS
        continents.forEach(function (continent, index) {
                const item = document.createElement("div");
                item.className = "legend-item";
                const nameWrapper = document.createElement("div");
                nameWrapper.className = "legend-name-wrapper";
                const dot = document.createElement("span");
                dot.className = "legend-dot";
                dot.style.backgroundColor = continentColors[index];
                const name = document.createElement("span");
                name.className = "legend-name";
                name.textContent = continent.continentName;
                nameWrapper.appendChild(dot);
                nameWrapper.appendChild(name);
                const percentage = document.createElement("strong");
                percentage.textContent = Number(continent.percentage || 0).toFixed(1) +"%";
                item.appendChild(nameWrapper);
                item.appendChild(percentage);
                chartLegend.appendChild(item);
            }
        );

    }
    function createDoughnutChart(continents) {
        const canvas = document.getElementById( "continentChart");

        if (!canvas) {
            console.error("Could not find #continentChart");
            return;
        }

        const labels = continents.map(function (continent) 
                {
                    return continent.continentName;
                }
            );

        const downloads = continents.map(function (continent) 
                {
                    return Number(continent.downloads || 0);
                }
            );

        const totalDownloads = downloads.reduce(
                function (sum, value) {
                    return sum + value;

                },
                0
            );

        const emptyState = document.getElementById( "chartEmptyState");

        // NO IDENTIFIED DOWNLOAD DATA
        if (totalDownloads <= 0) {
            if (continentChart) {
                continentChart.destroy();
                continentChart = null;
            }

            canvas.style.display ="none";

            const centerText =document.getElementById("chartCenterText");

            if (centerText) {
                centerText.style.display ="none";
            }


            if (emptyState) {
                emptyState.hidden =false;
                emptyState.style.display ="flex";
            }

            return;
        }

        // HAS DATA
        canvas.style.display ="block";
        const centerText =document.getElementById("chartCenterText");

        if (centerText) {
            centerText.style.display ="flex";
        }

        if (emptyState) {
            emptyState.hidden =true;
            emptyState.style.display ="none";
        }

        // DESTROY PREVIOUS CHART
        if (continentChart) {
            continentChart.destroy();
        }


        // CREATE DOUGHNUT
        continentChart = new Chart(canvas, {

                    type: "doughnut",
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                data: downloads,
                                backgroundColor: continentColors,
                                borderWidth: 0
                            }
                        ]
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: "68%",
                        plugins: {
                            // Hide Chart.js built-in legend because we use our own legend.
                            legend: {
                                display: false
                            },

                            // Tooltip
                            tooltip: {
                                callbacks: {
                                    label:
                                        function (context) {
                                            const value =Number(context.raw || 0);

                                            const total = downloads.reduce(
                                                    function (sum,number) {
                                                        return sum +
                                                            Number(number || 0);
                                                    },
                                                    0
                                                );

                                            let percentage = 0;

                                            if (total > 0) {
                                                percentage =(value /total) *100;
                                            }

                                            return (context.label +": " +value.toLocaleString() +" (" +percentage.toFixed(1) +"%)");
                                        }

                                }

                            }

                        }

                    }

                }
            );

    }

    loadDashboardData("all","All Articles");
});