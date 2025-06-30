
async function loadProjects() {
    const urls = ["html/projects_part1.html", "html/projects_part2.html"];
    const container = document.getElementById("projectContainer");

    for (const url of urls) {
        const response = await fetch(url);
        const html = await response.text();
        const temp = document.createElement("div");
        temp.innerHTML = html;

        const entries = temp.querySelectorAll(".project-entry");
        entries.forEach(entry => container.appendChild(entry));
    }
}

function showSelectedProjectByName(projectName) {
    const projects = document.querySelectorAll(".project-entry");
    projects.forEach(project => {
        const isMatch = project.textContent.includes(projectName);
        project.style.display = isMatch ? "block" : "none";
    });
}

function showSelectedProjectById(projectId) {
    const projects = document.querySelectorAll(".project-entry");
    projects.forEach(project => {
        project.style.display = project.id === projectId ? "block" : "none";
    });
}

window.addEventListener("DOMContentLoaded", () => {
    loadProjects().then(() => {
        const projectLinks = document.querySelectorAll(".dropdown ul li a");
        projectLinks.forEach(link => {
            link.addEventListener("click", (e) => {
                e.preventDefault();
                //const projectName = link.textContent.trim();
                //showSelectedProjectByName(projectName);
                const projectId = link.getAttribute("data-id");
                showSelectedProjectById(projectId);
            });
        });

        // Ersten Projekte-Link automatisch auslösen
        const firstLink = document.querySelector(".dropdown ul li a[data-id]");
        if (firstLink) {
            const firstProjectId = firstLink.getAttribute("data-id");
            showSelectedProjectById(firstProjectId);
        }
    });
});