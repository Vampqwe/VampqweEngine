/**
 * VampqweEngine Admin — базовые скрипты
 */
document.addEventListener('DOMContentLoaded', () => {
    // Мобильное меню (toggle sidebar)
    const menuToggle = document.querySelector('[data-toggle="sidebar"]');
    const sidebar    = document.querySelector('.admin-sidebar');
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // Закрытие модалок по клику на overlay
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });
    });

    // Открытие модалки по data-modal="id"
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-modal');
            const modal = document.getElementById(id);
            if (modal) modal.classList.add('active');
        });
    });

    // Закрытие модалки по кнопке .modal-close
    document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal-overlay').classList.remove('active');
        });
    });

    // Подтверждение удаления
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            const message = el.getAttribute('data-confirm') || 'Вы уверены?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Авто-скрытие алертов через 5 секунд
    document.querySelectorAll('.alert[data-autohide]').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});