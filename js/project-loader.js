async function loadProjectsFromJSON(language = "de") {
    try {
        const response = await fetch(`./lang/${language}_projects_merged.json`);
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

    const title = document.createElement("h4");
    title.textContent = project.title;

    const description = document.createElement("div");
    project.description.forEach(line => {
        const p = document.createElement("p");
        p.textContent = line;
        description.appendChild(p);
    });

    container.appendChild(title);
    container.appendChild(description);
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