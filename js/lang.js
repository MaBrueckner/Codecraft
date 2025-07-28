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

    fetch(`lang/${lang}.json`)
        .then(response => response.json())
        .then(data => {
            updateTexts(data);
        })
        .catch(error => {
            console.error("Fehler beim Laden der Sprachdatei:", error);
        });
}

// Texte auf der Seite aktualisieren
function updateTexts(translations) {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (translations[key]) {
            el.textContent = translations[key];
        }
    });
}

// Beim Laden der Seite Sprache aus Cookie laden
document.addEventListener("DOMContentLoaded", () => {
    const lang = getLanguageCookie() || 'de'; // Fallback: Deutsch
    loadLanguage(lang);
});