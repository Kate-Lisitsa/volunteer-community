document.addEventListener('DOMContentLoaded', function () {
    var themeToggle = document.getElementById('themeToggle');
    var root = document.documentElement;
    var storageKey = 'dobrohub-theme';

    function applyTheme(theme) {
        var isDark = theme === 'dark';
        if (isDark) {
            root.setAttribute('data-theme', 'dark');
        } else {
            root.removeAttribute('data-theme');
        }
        if (themeToggle) {
            themeToggle.setAttribute('aria-label', isDark ? 'Включить светлую тему' : 'Включить тёмную тему');
            themeToggle.setAttribute('title', isDark ? 'Светлая тема' : 'Тёмная тема');
            themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        }
    }

    function getStoredTheme() {
        try {
            return localStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light';
        } catch (e) {
            return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        }
    }

    function setStoredTheme(theme) {
        try {
            localStorage.setItem(storageKey, theme);
        } catch (e) {}
    }

    applyTheme(getStoredTheme());

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var next = getStoredTheme() === 'dark' ? 'light' : 'dark';
            setStoredTheme(next);
            applyTheme(next);
        });
    }

    var toggle = document.getElementById('navToggle');
    var nav = document.getElementById('mainNav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    document.querySelectorAll('form.filters-bar[data-auto-submit-filters]').forEach(function (filtersForm) {
        filtersForm.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                filtersForm.submit();
            });
        });
    });

    document.querySelectorAll('[data-hero-slider]').forEach(function (root) {
        var slides = root.querySelectorAll('.hero-main__slide');
        var dots = root.querySelectorAll('.hero-main__dot');
        if (!slides.length) return;

        var index = 0;
        var timer = null;
        var delay = 6000;

        function setSlide(nextIndex) {
            index = (nextIndex + slides.length) % slides.length;
            slides.forEach(function (slide, i) {
                slide.classList.toggle('is-active', i === index);
            });
            dots.forEach(function (dot, i) {
                var active = i === index;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        function stopAutoplay() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        function startAutoplay() {
            stopAutoplay();
            if (slides.length < 2) return;
            timer = setInterval(function () {
                setSlide(index + 1);
            }, delay);
        }

        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () {
                setSlide(i);
                startAutoplay();
            });
        });

        root.addEventListener('mouseenter', stopAutoplay);
        root.addEventListener('mouseleave', startAutoplay);

        startAutoplay();
    });

    document.querySelectorAll('.main-nav a[href], .admin-tabs a[href]').forEach(function (link) {
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#') {
            return;
        }
        if (/logout\.php/i.test(href) || /login\.php/i.test(href) || /register\.php/i.test(href)) {
            return;
        }
        try {
            var target = new URL(href, window.location.href);
            if (target.origin !== window.location.origin) {
                return;
            }
            href = target.href;
        } catch (e) {
            return;
        }
        link.addEventListener('mouseenter', function () {
            if (document.querySelector('link[rel="prefetch"][href="' + href + '"]')) {
                return;
            }
            var prefetch = document.createElement('link');
            prefetch.rel = 'prefetch';
            prefetch.href = href;
            document.head.appendChild(prefetch);
        }, { once: true });
    });
});
