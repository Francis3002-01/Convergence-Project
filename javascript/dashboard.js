document.addEventListener("DOMContentLoaded", function () {

    let continentChart = null;

    // LOAD DASHBOARD DATA
    async function loadDashboardData(journalID = "all") {

        try {

            let apiUrl = "dashboard_api.php";

            // Add journal filter only when a specific journal is selected
            if (journalID !== "all") {
                apiUrl += "?journalID=" + encodeURIComponent(journalID);
            }

            const response = await fetch(apiUrl, {
                method: "GET",
                cache: "no-store",
                headers: {
                    "Accept": "application/json"
                }
            });

            if (!response.ok) {
                throw new Error("Server returned status " + response.status);
            }

            const contentType =response.headers.get("content-type") || "";

            if (!contentType.includes("application/json")) {
                throw new Error("The API did not return JSON.");
            }

            const data = await response.json();
            console.log("Dashboard API response:", data);

            if (!data.success) {
                throw new Error(data.message ||"Failed to load dashboard data.");
            }

            updateIssueMenu(data.issues || []);

            // UPDATE TOTAL DOWNLOADS
            const totalDownloadsElement = document.getElementById("totalDownloads");

            if (totalDownloadsElement) {
                totalDownloadsElement.textContent =data.totalDownloads;
            }

            // UPDATE CONTINENT LIST
            updateContinentList(data.continents);

            // UPDATE PERCENTAGES
            updatePercentageList(data.continents);
            
            // CREATE / UPDATE PIE CHART
            createPieChart(data.continents);

        } 
        
        catch (error) {
            console.error("Dashboard error:",error);
        }
    }

    function updateIssueMenu(issues) {
        const issueMenu = document.getElementById("issueMenu");

        if (!issueMenu) {
            return;
        }

        issueMenu.innerHTML = "";

        const allIssuesButton = document.createElement("button");
        allIssuesButton.type = "button";
        allIssuesButton.className = "issue-menu-trigger";
        allIssuesButton.dataset.journalId = "all";
        allIssuesButton.textContent = "All Issues";
        allIssuesButton.addEventListener("click", function () {
            loadDashboardData("all");
        });
        issueMenu.appendChild(allIssuesButton);

        issues.forEach(function (issue) {
            if (!issue.articles || issue.articles.length === 0) {
                return;
            }

            const issueItem = document.createElement("div");
            issueItem.className = "issue-menu-item";

            const issueButton = document.createElement("button");
            issueButton.type = "button";
            issueButton.className = "issue-menu-trigger";
            issueButton.textContent =
                "Volume " + issue.volume +
                ", Issue " + issue.number +
                " (" + issue.year + ")";
            issueItem.appendChild(issueButton);

            const articleMenu = document.createElement("div");
            articleMenu.className = "article-menu";

            issue.articles.forEach(function (article) {
                const articleButton = document.createElement("button");
                articleButton.type = "button";
                articleButton.className = "article-menu-item";
                articleButton.textContent = article.title;
                articleButton.addEventListener("click", function () {
                    loadDashboardData(String(article.journalID));
                });
                articleMenu.appendChild(articleButton);
            });

            issueItem.appendChild(articleMenu);
            issueMenu.appendChild(issueItem);
        });
    }

    // UPDATE CONTINENT LIST
    function updateContinentList(continents) {

        const continentList = document.getElementById("continentList");

        if (!continentList) {
            return;
        }

        continentList.innerHTML = "";
        continents.forEach(function (continent) {

            const row = document.createElement("div");
            row.className = "continent-row";
            const name = document.createElement("span");
            name.textContent = continent.continentName;
            const count = document.createElement("strong");
            count.textContent = continent.downloads;
            row.appendChild(name);
            row.appendChild(count);
            continentList.appendChild(row);

        });

    }

    // UPDATE PERCENTAGE LIST
    function updatePercentageList(continents) {

        const chartLegend = document.getElementById("chartLegend");

        if (!chartLegend) {
            return;
        }

        const totalDownloads =
            continents.reduce(function (sum,continent) {
                return sum +
                    Number(continent.downloads || 0);
            }, 0);


        chartLegend.innerHTML = "";

        if (totalDownloads <= 0) {

            const item =document.createElement("div");
            item.className ="legend-item";
            const name =document.createElement("span");
            name.className ="legend-name";
            name.textContent ="No data available";
            const percentage =document.createElement("strong");
            percentage.textContent = "0%";
            item.appendChild(name);
            item.appendChild(percentage);
            chartLegend.appendChild(item);
            return;
        }


        continents.forEach(function (continent) {

            const item = document.createElement("div");
            item.className = "legend-item";
            const name = document.createElement("span");
            name.className = "legend-name";
            name.textContent = continent.continentName;

            const percentage = document.createElement("strong");

            percentage.textContent =
                Number(
                    continent.percentage || 0
                ).toFixed(1) + "%";


            item.appendChild(name);
            item.appendChild(percentage);

            chartLegend.appendChild(item);

        });

    }

    // CREATE PIE CHART
    function createPieChart(continents) {

        const canvas = document.getElementById("continentChart");

        if (!canvas) {
            console.error("Could not find #continentChart");
            return;
        }

        const labels = continents.map(function (continent) {
                return continent.continentName;
            });


        const downloads = continents.map(function (continent) {
                return Number(continent.downloads || 0);
            });


        const totalDownloads =
            downloads.reduce(function (sum,value) {
                return sum + value;
            }, 0);


        const emptyState = document.getElementById("chartEmptyState");

        // NO DATA
        if (totalDownloads <= 0) {
            if (continentChart) {
                continentChart.destroy();
                continentChart = null;
            }

            if (emptyState) {
                emptyState.hidden = false;
            }

            canvas.style.display = "none";
            emptyState.style.display = "flex";
            return;
        }

        // HAS DATA
        if (emptyState) {
            emptyState.hidden = true;
        }

        canvas.style.display = "block";

                emptyState.style.display = "none";
        if (continentChart) {
            continentChart.destroy();
        }

        continentChart = new Chart(canvas, {

                type: "pie",
                data: {
                    labels: labels,
                    datasets: [{
                        data: downloads
                    }]
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },

                        tooltip: {
                            callbacks: {

                                label: function (context) {

                                    const value =
                                        Number(
                                            context.raw || 0
                                        );

                                    const total =
                                        downloads.reduce(
                                            function (sum,number) {
                                                return sum +  Number(number || 0);

                                            },
                                            0
                                        );


                                    let percentage = 0;


                                    if (total > 0) {
                                        percentage =(value /total) * 100;
                                    }


                                    return (
                                        context.label +
                                        ": " +
                                        value +
                                        " (" +
                                        percentage.toFixed(1) +
                                        "%)"
                                    );

                                }

                            }

                        }

                    }

                }

            });

    }

    // JOURNAL SELECTOR
    // INITIAL LOAD
    loadDashboardData();
});