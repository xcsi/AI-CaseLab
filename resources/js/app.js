import './bootstrap';

import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal[data-show="1"]').forEach((el) => {
        bootstrap.Modal.getOrCreateInstance(el).show();
    });
});
