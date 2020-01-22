import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['icon'];

    connect() {
        this.#apply(this.#resolvedTheme());
    }

    toggle() {
        const current = document.documentElement.getAttribute('data-bs-theme') || 'light';
        this.#apply(current === 'dark' ? 'light' : 'dark');
    }

    #resolvedTheme() {
        return localStorage.getItem('theme')
            || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    }

    #apply(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        if (this.hasIconTarget) {
            this.iconTarget.className = `bi bi-${theme === 'dark' ? 'sun-fill' : 'moon-fill'}`;
        }
    }
}
