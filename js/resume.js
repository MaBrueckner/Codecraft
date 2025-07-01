document.addEventListener("DOMContentLoaded", function () {
    const resumeContainer = document.getElementById("resume-container");

    fetch("html/resume.html")
        .then(response => {
            if (!response.ok) throw new Error("Resume could not be loaded.");
            return response.text();
        })
        .then(html => {
            resumeContainer.innerHTML = html;
        })
        .catch(error => {
            resumeContainer.innerHTML = `<p style="color: red;">Error loading resume: ${error.message}</p>`;
        });
    });