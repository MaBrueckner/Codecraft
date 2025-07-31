function loadResume(lang) {
    const resumeContainer = document.getElementById("resume-container");
    if (!resumeContainer) return;

    const shadowRoot = resumeContainer.shadowRoot || resumeContainer.attachShadow({ mode: "open" });
    const jsonPath = `lang/${lang}_resume.json`;

    fetch(jsonPath)
        .then(response => {
            if (!response.ok) throw new Error("Resume JSON could not be loaded.");
            return response.json();
        })
        .then(data => {
            const resume = data.resume;

            // Clear previous content
            shadowRoot.innerHTML = "";

            // Bootstrap
            const bootstrapLink = document.createElement("link");
            bootstrapLink.rel = "stylesheet";
            bootstrapLink.href = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css";
            shadowRoot.appendChild(bootstrapLink);

            // Font Awesome
            const fontAwesomeLink = document.createElement("link");
            fontAwesomeLink.rel = "stylesheet";
            fontAwesomeLink.href = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css";
            shadowRoot.appendChild(fontAwesomeLink);

            // Timeline CSS
            const styleLink = document.createElement("link");
            styleLink.rel = "stylesheet";
            styleLink.href = "css/timeline.css";
            shadowRoot.appendChild(styleLink);

            // Wrapper
            const wrapperContainer = document.createElement("div");
            wrapperContainer.classList.add("container", "py-5");

            const wrapper = document.createElement("div");
            wrapper.classList.add("timeline");

            resume.timeline.forEach(entry => {
                const yearContainer = document.createElement("div");
                yearContainer.classList.add("timeline-year", "text-center", "mb-4");

                const yearHeading = document.createElement("h2");
                yearHeading.textContent = entry.year;
                yearHeading.classList.add("text-secondary");
                yearContainer.appendChild(yearHeading);
                wrapper.appendChild(yearContainer);

                if (!entry.entries || entry.entries.length === 0) return;

                entry.entries.forEach(item => {
                    const rowContainer = document.createElement("div");
                    rowContainer.classList.add("row", "timeline-item", "mb-5");

                    const emptyContainer = document.createElement("div");
                    emptyContainer.classList.add("col-md-6");

                    const itemContainer = document.createElement("div");
                    itemContainer.classList.add("col-md-6");
                    if (item.orientation === "left") {
                        itemContainer.classList.add("text-end");
                    }

                    const itemCard = document.createElement("div");
                    itemCard.classList.add("card", "shadow-sm", "p-3", item.type);

                    const itemTitle = document.createElement("h5");
                    itemTitle.innerHTML = item.img ? `<i class="${item.img}"></i> ${item.title}` : item.title;
                    itemCard.appendChild(itemTitle);

                    const detailList = document.createElement("ul");
                    detailList.classList.add("mb-0");
                    item.details.forEach(detail => {
                        const li = document.createElement("li");
                        li.textContent = detail;
                        detailList.appendChild(li);
                    });

                    itemCard.appendChild(detailList);
                    itemContainer.appendChild(itemCard);

                    if (item.orientation === "left") {
                        rowContainer.appendChild(emptyContainer);
                    }
                    rowContainer.appendChild(itemContainer);
                    wrapper.appendChild(rowContainer);
                });
            });

            wrapperContainer.appendChild(wrapper);
            shadowRoot.appendChild(wrapperContainer);
        })
        .catch(error => {
            resumeContainer.innerHTML = `<p>Error loading resume: ${error.message}</p>`;
        });
}