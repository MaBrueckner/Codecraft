(function () {
    "use strict";

    document.querySelector('.php-email-form').addEventListener('submit', function (e) {
        e.preventDefault();

        let form = this;
        let action = form.getAttribute('action');
        let formData = new FormData(form);

        let loading = form.querySelector('.loading');
        let errorMessage = form.querySelector('.error-message');
        let sentMessage = form.querySelector('.sent-message');

        loading.style.display = 'block';
        errorMessage.style.display = 'none';
        sentMessage.style.display = 'none';

        fetch(action, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            loading.style.display = 'none';
            if (response.ok) {
                sentMessage.style.display = 'block';
                form.reset();
                setTimeout(() => {
                    sentMessage.style.display = 'none';
                }, 10000);
            } else {
                return response.text().then(text => { throw new Error(text) });
            }
        })
        .catch(error => {
            loading.style.display = 'none';
            errorMessage.style.display = 'block';
            errorMessage.innerHTML = error.message;
            setTimeout(() => {
                errorMessage.style.display = 'none';
            }, 10000);
        });
    });
})();
