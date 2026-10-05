document.addEventListener("DOMContentLoaded", function () {
  const pdfSection = document.getElementById("pdfSection");

  const readOnline = document.getElementById("readOnline");
  const downloadPdf = document.getElementById("downloadPdf");

  const pdfViewer = document.getElementById("pdfViewer");
  const pdfViewerContainer = document.getElementById("pdfViewerContainer");

  const copyCitation = document.getElementById("copyCitation");
  const citation = document.getElementById("citation");

  /*
   * Get PDF URL and journal ID.
   */
  const pdfUrl = pdfSection ? pdfSection.dataset.pdfUrl || "" : "";
  const journalId = pdfSection ? pdfSection.dataset.journalId || "" : "";
  console.log("PDF URL:", pdfUrl);
  console.log("Journal ID:", journalId);

  /*
   * ---------------------------------------------------------
   * DEVICE DETECTION
   * ---------------------------------------------------------
   *
   * Mobile/tablet devices use their browser's native PDF
   * viewer instead of the embedded iframe.
   *
   * This includes:
   * - Chrome on iPad
   * - Chrome on Android
   * - Safari on iPhone/iPad
   * - Other mobile browsers
   *
   * The iPad check also covers newer iPads that identify
   * themselves as desktop-class devices.
   */
  const isMobileOrTablet =
    /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent,) ||
    (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);

  /*
   * ---------------------------------------------------------
   * READ ONLINE
   * ---------------------------------------------------------
   *
   * DESKTOP:
   *     Open PDF inside the article page using iframe.
   *
   * MOBILE/TABLET:
   *     Open PDF directly using the browser's native
   *     PDF viewer.
   */
  if (readOnline) {
    readOnline.addEventListener("click", function () {
      if (!pdfUrl) {
        console.error("No PDF URL found.");
        return;
      }

      console.log("Read Online clicked.");
      console.log("PDF URL:", pdfUrl);

      /*
       * Mobile/tablet:
       * Let Chrome/Safari handle the PDF directly.
       */
      if (isMobileOrTablet) {
        window.location.href = pdfUrl;
        return;
      }

      /*
       * Desktop:
       * Keep the existing same-page iframe viewer.
       */
      if (!pdfViewer || !pdfViewerContainer) {
        console.error("PDF viewer elements were not found.");
        return;
      }

      pdfViewer.src = pdfUrl;
      pdfViewerContainer.style.display = "block";
      pdfViewerContainer.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    });
  }

  /*
   * ---------------------------------------------------------
   * DOWNLOAD PDF
   * ---------------------------------------------------------
   *
   * Opens the PDF/download URL and records the article
   * download.
   *
   * On mobile devices, the browser may open the PDF instead
   * of immediately downloading it. This is controlled by
   * the browser and the cross-origin Supabase response.
   */
  if (downloadPdf) {
    downloadPdf.addEventListener("click", function (event) {
      event.preventDefault();

      const downloadUrl = downloadPdf.href;

      if (!downloadUrl) {
        return;
      }

      /*
       * Record the article download.
       */
      /*if (journalId) {
            fetch("track_download.php", {
                method: "POST",
                credentials: "same-origin",
                keepalive: true,
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    journalID: journalId
                })
            })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(
                        "Download tracking request failed."
                    );
                }

                return response.json().catch(function () {
                    return null;
                });
            })
            .then(function (data) {
                console.log("Download tracked.", data);
            })
            .catch(function (error) {
                console.error(
                    "Download tracking error:",
                    error
                );
            });
        }*/

      if (journalId) {
        fetch("track_download.php", {
          method: "POST",
          credentials: "same-origin",
          keepalive: true,
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            journalID: Number(journalId),
          }),
        })
          .then(function (response) {
            return response.json().then(function (data) {
              if (!response.ok) {
                throw new Error(
                  data.message || "Download tracking request failed.",
                );
              }
              return data;
            });
          })

          .then(function (data) {
            console.log("Download tracked successfully:", data);
          })
          .catch(function (error) {
            console.error("Download tracking error:", error);
          });
      } 
      
      else {
        console.error("Cannot track download: journalID is missing.");
      }

      /*
       * Send the browser to the PHP download endpoint.
       *
       * download_article.php sends
       * Content-Disposition: attachment,
       * which forces the PDF to download.
       */
      window.location.href = downloadUrl;
    });
  }

  /*COPY APA CITATION*/
  if (copyCitation && citation) {
    copyCitation.addEventListener("click", function () {
      const citationText = citation.textContent.trim();

      if (!citationText) {
        return;
      }

      /*
       * Use the modern Clipboard API when available.
       */
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard
          .writeText(citationText)
          .then(function () {
            showCopySuccess();
          })
          .catch(function () {
            fallbackCopy(citationText);
          });

        return;
      }

      /*
       * Fallback for browsers without Clipboard API.
       */
      fallbackCopy(citationText);
    });
  }

  /*COPY SUCCESS MESSAGE*/
  function showCopySuccess() {
    const originalText = copyCitation.innerHTML;
    copyCitation.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';

    setTimeout(function () {
      copyCitation.innerHTML = originalText;
    }, 1500);
  }

  /*FALLBACK COPy*/
  function fallbackCopy(text) {
    const textarea = document.createElement("textarea");

    textarea.value = text;
    textarea.style.position = "fixed";
    textarea.style.left = "-9999px";
    textarea.style.top = "0";

    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    let copied = false;

    try {
      copied = document.execCommand("copy");
    } 
    
    catch (error) {
      console.error("Fallback copy failed:", error);
    }

    document.body.removeChild(textarea);

    if (copied) {
      showCopySuccess();
    } 
    
    else {
      console.error("Unable to copy citation.");
    }
  }
});