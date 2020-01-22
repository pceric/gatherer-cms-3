/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

const TINYMCE_URL = 'https://cdn.jsdelivr.net/npm/tinymce@8.6.0/tinymce.min.js';

export default class extends Controller {
    static values = { url: String };
    static targets = ['display', 'editor', 'textarea', 'label'];

    #editor = null;

    async #loadTinyMCE() {
        if (window.tinymce) return;
        await new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = TINYMCE_URL;
            script.integrity = 'sha256-3/Z/zZH4Qojv3EWPyqvN8hhwTq638HiBKscESnRbwA0=';
            script.crossOrigin = 'anonymous';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async edit(event) {
        event.preventDefault();
        this.displayTarget.classList.add('d-none');
        this.editorTarget.classList.remove('d-none');

        await this.#loadTinyMCE();

        const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const editors = await window.tinymce.init({
            license_key: 'gpl',
            promotion: false,
            target: this.textareaTarget,
            height: 200,
            menubar: false,
            plugins: ['link', 'lists'],
            toolbar: 'bold italic | bullist numlist | link | removeformat',
            skin: dark ? 'oxide-dark' : 'oxide',
            content_css: dark ? 'dark' : 'default',
        });
        this.#editor = editors[0];
        this.#editor.focus();
    }

    cancel(event) {
        event.preventDefault();
        this.#destroyEditor();
        this.editorTarget.classList.add('d-none');
        this.displayTarget.classList.remove('d-none');
    }

    async save(event) {
        event.preventDefault();
        const annotation = this.#editor
            ? this.#editor.getContent()
            : this.textareaTarget.value.trim();

        if (!annotation) {
            this.#destroyEditor();
            this.editorTarget.classList.add('d-none');
            this.displayTarget.classList.remove('d-none');
            return;
        }

        const response = await fetch(this.urlValue, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ annotation }),
        });

        if (!response.ok) return;

        const data = await response.json();

        let p = this.displayTarget.querySelector('.annotation');
        if (data.annotation) {
            if (!p) {
                p = document.createElement('p');
                p.className = 'shadow-none p-3 bg-light rounded annotation';
                this.displayTarget.prepend(p);
            }
            p.innerHTML = data.annotation;
        } else if (p) {
            p.remove();
        }

        this.labelTarget.textContent = data.annotation ? 'Edit annotation' : 'Add annotation';

        this.#destroyEditor();
        this.textareaTarget.value = data.annotation ?? '';

        this.editorTarget.classList.add('d-none');
        this.displayTarget.classList.remove('d-none');
    }

    #destroyEditor() {
        if (this.#editor) {
            this.#editor.remove();
            this.#editor = null;
        }
    }
}
