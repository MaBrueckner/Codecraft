// Cookie setzen
function setLanguageCookie(lang) {
    document.cookie = `lang=${lang}; path=/; max-age=31536000`; // 1 Jahr gültig
}

// Cookie lesen
function getLanguageCookie() {
    const match = document.cookie.match(/(^| )lang=([^;]+)/);
    return match ? match[2] : null;
}

// Sprache laden und Texte aktualisieren
function loadLanguage(lang) {
    setLanguageCookie(lang);
    updateLogo(lang); // Logo aktualisieren
    reloadProject(); // Projekte neu laden
    highlightCurrentLanguage(lang); // Aktuelle Sprache hervorheben

    fetch(`./lang/${lang}.json`)
        .then(response => response.json())
        .then(data => {
            updateTexts(data);
            initTyped(); // Typed Text initialisieren
            loadResume(lang); // Lebenslauf laden
        })
        .catch(error => {
            console.error("Fehler beim Laden der Sprachdatei:", error);
        });
}

// Texte auf der Seite aktualisieren
function updateTexts(translations) {
    for (const section in translations) {
        const fields = translations[section];
        for (const field in fields) {
            const id = `${section}-${field}`;
            const el = document.getElementById(id);
            const value = fields[field];

            if (el) {
                if (id === "home-text") {
                    el.setAttribute("data-typed-items", value.join(", "));
                }
                else {
                    el.innerHTML = value;
                }
            }
        }
    }
}

// Aktuelle Sprache hervorheben
function highlightCurrentLanguage(lang) {
    document.querySelectorAll('.dropdown ul li a').forEach(link => {
        link.classList.remove('active-language'); // Entferne vorherige Markierung
        if (link.getAttribute('onclick')?.includes(`loadLanguage('${lang}')`)) {
            link.classList.add('active-language'); // Markiere aktuelle Sprache
        }
    });
}

// Logo aktualisieren
function updateLogo(lang) {
    const logo = document.getElementById("logo");
    if (logo) {
        if (lang === 'de') {
            logo.src = 'img/logo_h_de.svg';
        } else {
            logo.src = `img/logo_h_int.svg`;
        }
    }
}

// Beim Laden der Seite Sprache aus Cookie laden
document.addEventListener("DOMContentLoaded", () => {
    const lang = getLanguageCookie() || 'de'; // Fallback: Deutsch
    loadLanguage(lang);
});