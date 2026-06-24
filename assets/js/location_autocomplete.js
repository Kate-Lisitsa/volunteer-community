/**
 * Подсказки места через Nominatim (поле #location + скрытые #location_osm_type, #location_osm_id).
 * Контейнер: .location-field-wrap[data-suggest-url]
 */
(function () {
    'use strict';

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, ms);
        };
    }

    function init() {
        var wrap = document.querySelector('.location-field-wrap');
        if (!wrap) return;

        var input = document.getElementById('location');
        if (!input) return;

        var url = wrap.getAttribute('data-suggest-url') || '';
        if (!url) return;

        var typeEl = document.getElementById('location_osm_type');
        var idEl = document.getElementById('location_osm_id');
        if (!typeEl || !idEl) return;

        var box = document.createElement('div');
        box.className = 'loc-suggest';
        box.setAttribute('role', 'listbox');
        box.setAttribute('aria-label', 'Подсказки адреса');
        box.hidden = true;
        wrap.appendChild(box);

        function clearMapPick() {
            typeEl.value = '';
            idEl.value = '';
        }

        function hideBox() {
            box.hidden = true;
            box.innerHTML = '';
        }

        function selectItem(item) {
            input.value = item.display_name;
            typeEl.value = item.osm_type;
            idEl.value = String(item.osm_id);
            hideBox();
            input.focus();
        }

        function renderItems(items) {
            box.innerHTML = '';
            if (!items || !items.length) {
                box.hidden = true;
                return;
            }
            items.forEach(function (it) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'loc-suggest__item';
                btn.setAttribute('role', 'option');
                btn.textContent = it.display_name;
                btn.addEventListener('click', function () {
                    selectItem(it);
                });
                box.appendChild(btn);
            });
            box.hidden = false;
        }

        var fetchSuggestions = debounce(function () {
            var q = (input.value || '').trim();
            if (q.length < 3) {
                hideBox();
                return;
            }

            clearMapPick();

            fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.wait) {
                        box.innerHTML = '';
                        var p = document.createElement('p');
                        p.className = 'loc-suggest__wait muted small';
                        p.textContent = 'Подождите около секунды и продолжайте ввод (лимит сервиса карт).';
                        box.appendChild(p);
                        box.hidden = false;
                        return;
                    }
                    renderItems((data && data.items) ? data.items : []);
                })
                .catch(function () {
                    hideBox();
                });
        }, 450);

        input.addEventListener('input', function () {
            fetchSuggestions();
        });

        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') hideBox();
        });

        document.addEventListener('click', function (ev) {
            if (!wrap.contains(ev.target)) hideBox();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
