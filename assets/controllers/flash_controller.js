import { Controller } from '@hotwired/stimulus';
import { Toast } from 'bootstrap';

export default class extends Controller {
    connect() {
        this.element.querySelectorAll('.toast').forEach((toastEl) => {
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
            Toast.getOrCreateInstance(toastEl).show();
        });
    }
}
