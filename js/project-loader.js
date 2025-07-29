async function loadProjectsFromJSON(language = "de") {
    language = getLanguageCookie() || "de";
    try {
        const response = await fetch(`./lang/${language}_projects.json`);
        if (!response.ok) throw new Error("JSON konnte nicht geladen werden.");
        const json = await response.json();
        return json.projects;
    } catch (error) {
        console.error("Fehler beim Laden der Projektdatei:", error);
        return {};
    }
}

function renderProject(projectId, projectData) {
    const container = document.getElementById("projectContainer");
    container.innerHTML = ""; // Inhalt zurücksetzen

    if (!projectData[projectId]) {
        container.innerHTML = "<p>Projekt nicht gefunden.</p>";
        return;
    }

    const project = projectData[projectId];
    
    // Titel
    const title = document.createElement("h4");
    title.textContent = project.title;
    container.appendChild(title);

    // Paragraphs
    if (project.paragraphs) {
        project.paragraphs.forEach(text => {
            const p = document.createElement("p");
            p.textContent = text;
            container.appendChild(p);
        });
    }

    // Listen
    if (project.lists) {
        project.lists.forEach(list => {
            if (list.intro) {
                const p = document.createElement("p");
                p.textContent = list.intro;
                container.appendChild(p);
            }

            const ul = document.createElement("ul");
            list.items.forEach(item => {
                const li = document.createElement("li");
                li.textContent = item;
                ul.appendChild(li);
            });
            container.appendChild(ul);
        });
    }

    // Zusätzliche Absätze
    if (project.additional_paragraphs) {
        project.additional_paragraphs.forEach(text => {
            const p = document.createElement("p");
            p.textContent = text;
            container.appendChild(p);
        });
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    const language = "de"; // z. B. dynamisch änderbar für Mehrsprachigkeit
    const projects = await loadProjectsFromJSON(language);

    const dropdownLinks = document.querySelectorAll(".dropdown ul li a[data-id]");
    dropdownLinks.forEach(link => {
        link.addEventListener("click", (e) => {
            e.preventDefault();
            const projectId = link.getAttribute("data-id");
            renderProject(projectId, projects);
        });
    });

    const firstLink = document.querySelector(".dropdown ul li a[data-id]");
    if (firstLink) {
        const firstProjectId = firstLink.getAttribute("data-id");
        renderProject(firstProjectId, projects);
    }
});