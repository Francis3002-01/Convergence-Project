const pdfSection = document.getElementById("pdfSection");
const pdfUrl = pdfSection?.dataset.pdfUrl || "";
const journalId = Number(pdfSection?.dataset.journalId || 0);
const readOnline = document.getElementById("readOnline");
const downloadPdf = document.getElementById("downloadPdf");
const pdfViewer = document.getElementById("pdfViewer");
const pdfViewerContainer = document.getElementById("pdfViewerContainer");
const copyCitation = document.getElementById("copyCitation");
const citation = document.getElementById("citation");

console.log("article_details.js loaded");
console.log("PDF URL:", pdfUrl);
console.log("Read Online button:", readOnline);
console.log("PDF Viewer:", pdfViewer);
console.log("PDF Viewer Container:", pdfViewerContainer);

if (pdfUrl) {

    // Download PDF
    if (downloadPdf) {

        downloadPdf.href = pdfUrl.includes("?")
            ? `${pdfUrl}&download`
            : `${pdfUrl}?download`;

        downloadPdf.addEventListener("click", function (event) {

            event.preventDefault();
            const pdfWindow = window.open(downloadPdf.href,"_blank");

            if (!journalId || journalId <= 0) {

                console.error(
                    "Download tracking failed: invalid journal ID."
                );

                if (!pdfWindow) {
                    window.location.href = downloadPdf.href;
                }

                return;
            }

            const payload = JSON.stringify({journalID: journalId});

            fetch("track_download.php", {
                method: "POST",
                credentials: "same-origin",
                keepalive: true,
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: payload
            })
                .then(async function (response) {
                    const responseText =await response.text();
                    console.log("track_download.php status:",response.status);
                    console.log("track_download.php response:",responseText);
                    let data;

                    try {
                        data = JSON.parse(responseText);
                    } 
                    
                    catch (jsonError) {
                        console.error("track_download.php did not return valid JSON.");
                        console.error("Raw response:",responseText);
                        return null;
                    }

                    return data;
                })
                .then(function (data) {

                    if (!data) {
                        return;
                    }

                    if (!data.success) {
                        console.error("Download tracking failed:",data);
                        console.error(
                            "Backend error:",
                            data.data?.error ||
                            "No error details returned."
                        );
                    } 
                    
                    else {

                        console.log(
                            "Download recorded successfully:",
                            data
                        );
                    }
                })
                .catch(function (error) {

                    console.error(
                        "Download tracking request failed:",
                        error
                    );
                });

            if (!pdfWindow) {
                window.location.href = downloadPdf.href;
            }
        });
    }


    // Read Online
    if (readOnline && pdfViewer &&pdfViewerContainer) {

        readOnline.addEventListener(
            "click",
            function () {

                console.log("Read Online clicked");
                console.log("Opening PDF:", pdfUrl);

                if (!pdfUrl) {
                    console.error("No PDF URL found.");
                    return;
                }

                pdfViewer.src = pdfUrl;
                pdfViewerContainer.style.display = "block";
                pdfViewerContainer.scrollIntoView({behavior: "smooth",block: "start"});
            }
        );
    }
}

// Copy APA citation
if (copyCitation && citation) {

    copyCitation.addEventListener(
        "click",
        async function () {

            const citationText =citation.textContent.trim();

            if (!citationText) {
                return;
            }

            try {
                await navigator.clipboard.writeText(citationText);
                const originalText =copyCitation.textContent;
                copyCitation.textContent = "Copied!";
                setTimeout(() => {
                    copyCitation.textContent =originalText;}, 1500);
            }
            
            catch (err) {
                console.error("Copy citation error:",err);
                const textArea = document.createElement("textarea");
                textArea.value = citationText;
                textArea.style.position = "fixed";
                textArea.style.opacity = "0";
                textArea.style.pointerEvents = "none";
                document.body.appendChild(textArea);
                textArea.select();

                try {
                    document.execCommand("copy");
                    const originalText = copyCitation.textContent;
                    copyCitation.textContent = "Copied!";
                    setTimeout(() => {
                        copyCitation.textContent =originalText;
                    }, 1500);

                } 
                
                catch (fallbackError) {
                    console.error("Fallback copy failed:",fallbackError);
                    alert("Unable to copy the citation.");
                } 
                
                finally {
                    document.body.removeChild(textArea);
                }
            }
        }
    );
}