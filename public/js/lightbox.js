document.addEventListener('DOMContentLoaded', function () {
    const triggers = Array.from(document.querySelectorAll('a.lightbox-trigger'));
    if (!triggers.length) return;

    let currentIndex = -1;
    let modal = null;

    function buildModal(src) {
        modal = document.createElement('div');
        modal.className = 'lightbox-modal';
        modal.innerHTML = '' +
            '<div class="lightbox-backdrop"></div>' +
            '<div class="lightbox-content">' +
                '<button class="lightbox-close" aria-label="Закрыть">\u2715</button>' +
                '<button class="lightbox-prev" aria-label="Назад">&#10094;</button>' +
                '<img src="' + src + '" alt="Просмотр изображения" />' +
                '<button class="lightbox-next" aria-label="Вперёд">&#10095;</button>' +
            '</div>';
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';

        // listeners (old browser compatible)
        var closeBtn = modal.querySelector('.lightbox-close');
        var backdrop = modal.querySelector('.lightbox-backdrop');
        var prevBtn = modal.querySelector('.lightbox-prev');
        var nextBtn = modal.querySelector('.lightbox-next');
        if (closeBtn) {
            if (closeBtn.addEventListener) closeBtn.addEventListener('click', closeModal);
            else closeBtn.attachEvent('onclick', closeModal);
        }
        if (backdrop) {
            if (backdrop.addEventListener) backdrop.addEventListener('click', closeModal);
            else backdrop.attachEvent('onclick', closeModal);
        }
        if (prevBtn) {
            if (prevBtn.addEventListener) prevBtn.addEventListener('click', showPrev);
            else prevBtn.attachEvent('onclick', showPrev);
        }
        if (nextBtn) {
            if (nextBtn.addEventListener) nextBtn.addEventListener('click', showNext);
            else nextBtn.attachEvent('onclick', showNext);
        }
        // Клик по модалке вне .lightbox-content закрывает окно
        if (modal.addEventListener) {
            modal.addEventListener('click', function(e) {
                var content = modal.querySelector('.lightbox-content');
                if (!content) return;
                if (e.target === modal) closeModal();
            });
        } else {
            modal.attachEvent('onclick', function(e) {
                var content = modal.querySelector('.lightbox-content');
                if (!content) return;
                if (window.event && window.event.srcElement === modal) closeModal();
            });
        }
        document.addEventListener('keydown', onKey);
    }

    function closeModal() {
        if (!modal) return;
        document.body.style.overflow = '';
        document.removeEventListener('keydown', onKey);
        modal.remove();
        modal = null;
        currentIndex = -1;
    }

    function onKey(e) {
        if (!modal) return;
        if (e.key === 'Escape') closeModal();
        if (e.key === 'ArrowLeft') showPrev();
        if (e.key === 'ArrowRight') showNext();
    }

    function showAt(index) {
        if (index < 0 || index >= triggers.length) return;
        currentIndex = index;
        const src = triggers[index].getAttribute('href');
        if (!src) return;

        if (!modal) {
            buildModal(src);
        } else {
            const img = modal.querySelector('img');
            img.src = src;
        }
    }

    function showPrev() {
        if (currentIndex <= 0) {
            showAt(triggers.length - 1);
        } else {
            showAt(currentIndex - 1);
        }
    }

    function showNext() {
        if (currentIndex >= triggers.length - 1) {
            showAt(0);
        } else {
            showAt(currentIndex + 1);
        }
    }

    triggers.forEach((el, idx) => {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            showAt(idx);
        });
    });
});
