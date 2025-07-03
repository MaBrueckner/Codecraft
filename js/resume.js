document.addEventListener("DOMContentLoaded", function () {
    const resumeContainer = document.getElementById("resume-container");

    // Shadow Root erstellen
    const shadowRoot = resumeContainer.attachShadow({ mode: "open" });

    fetch("html/resume.html")
        .then(response => {
            if (!response.ok) throw new Error("Resume could not be loaded.");
            return response.text();
        })
        .then(html => {
            // CSS-Link erstellen
            const styleLink = document.createElement("link");
            styleLink.setAttribute("rel", "stylesheet");
            styleLink.setAttribute("href", "css/timeline.css");
            
            // Container für HTML-Inhalt
            const wrapper = document.createElement("div");
            wrapper.innerHTML = html;

            // CSS und HTML in Shadow DOM einfügen
            shadowRoot.appendChild(styleLink);
            shadowRoot.appendChild(wrapper);

            //resumeContainer.innerHTML = html;
        })
        .catch(error => {
            resumeContainer.innerHTML = `<p style="color: red;">Error loading resume: ${error.message}</p>`;
        });
    });