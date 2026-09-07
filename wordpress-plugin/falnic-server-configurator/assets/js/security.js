// assets/js/security.js
// Shared escaping helpers for every dynamic HTML render path.

var security = window.security = {
    escapeHTML: (value) => String(value ?? '').replace(/[&<>'\"]/g, ch => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
    }[ch])),

    attr: (value) => String(value ?? '').replace(/[&<>'\"]/g, ch => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
    }[ch])),

    inlineJson: (value) => security.escapeHTML(JSON.stringify(value ?? ''))
};

var h = window.h = security.escapeHTML;
