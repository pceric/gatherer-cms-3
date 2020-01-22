import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.element.querySelectorAll('time[datetime]').forEach(el => {
            const date = new Date(el.getAttribute('datetime'));
            if (isNaN(date)) return;

            const fmt = el.dataset.format;
            el.textContent = fmt === 'date'
                ? date.toLocaleDateString(navigator.languages, { dateStyle: 'short' })
                : date.toLocaleString(navigator.languages, {
                    dateStyle: fmt === 'short' ? 'short' : 'medium',
                    timeStyle: 'short',
                  });
        });
    }
}
