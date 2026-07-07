'use strict';

/**
 * Chips-based editor for Swiftcomplete address field formats on the settings page.
 * Enhances each [data-sc-format-field] container (which wraps a hidden input holding
 * the canonical format string) with add/remove/reorder chips, a per-gap separator
 * toggle (", " vs " "), and a per-chip uppercase toggle. Serialises back to the input.
 *
 * Config is injected as `SC_FORMAT_BUILDER = { tokens: [...], defaults: {...} }`.
 */
(function () {
    const config = typeof SC_FORMAT_BUILDER !== 'undefined' ? SC_FORMAT_BUILDER : { tokens: [] };
    const TOKENS = Array.isArray(config.tokens) ? config.tokens : [];

    // Only one field's "add token" menu may be open at a time.
    let closeActiveMenu = null;

    function canonicalMap() {
        const map = {};
        TOKENS.forEach((t) => {
            map[t.toLowerCase()] = t;
        });
        return map;
    }

    function parse(value) {
        const canonical = canonicalMap();
        const chips = [];
        const seps = [];
        let pendingSep = null;

        String(value == null ? '' : value)
            .split(/(\s*,\s*|\s+)/)
            .forEach((part) => {
                if (part === '' || part === undefined) {
                    return;
                }
                if (/^\s*,\s*$/.test(part)) {
                    pendingSep = ', ';
                    return;
                }
                if (/^\s+$/.test(part)) {
                    pendingSep = ' ';
                    return;
                }
                const key = part.toLowerCase();
                if (!canonical[key]) {
                    pendingSep = null;
                    return;
                }
                if (chips.length) {
                    seps.push(pendingSep || ', ');
                }
                chips.push({ token: canonical[key], upper: part === part.toUpperCase() });
                pendingSep = null;
            });

        return { chips, seps };
    }

    function serialize(state) {
        return state.chips
            .map((chip, i) => {
                const text = chip.upper ? chip.token.toUpperCase() : chip.token;
                return (i === 0 ? '' : state.seps[i - 1]) + text;
            })
            .join('');
    }

    function buildField(container) {
        const input = container.querySelector('input[type="hidden"]');
        if (!input) {
            return;
        }
        const fieldKey = container.getAttribute('data-sc-format-field') || '';
        const state = parse(input.value);

        function commit() {
            input.value = serialize(state);
            render();
        }

        function makeButton(className, text, title, onClick) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = className;
            btn.textContent = text;
            btn.title = title;
            btn.addEventListener('click', onClick);
            return btn;
        }

        function render() {
            Array.from(container.querySelectorAll('.scfb-ui')).forEach((el) => el.remove());

            const ui = document.createElement('div');
            ui.className = 'scfb-ui';

            const chipsWrap = document.createElement('div');
            chipsWrap.className = 'scfb-chips';

            state.chips.forEach((chip, i) => {
                if (i > 0) {
                    const sep = makeButton(
                        'scfb-sep',
                        state.seps[i - 1] === ' ' ? '␣' : ',',
                        'Toggle separator (comma / space)',
                        () => {
                            state.seps[i - 1] = state.seps[i - 1] === ' ' ? ', ' : ' ';
                            commit();
                        }
                    );
                    chipsWrap.appendChild(sep);
                }

                const el = document.createElement('span');
                el.className = 'scfb-chip';
                el.setAttribute('draggable', 'true');
                el.dataset.index = String(i);

                const label = document.createElement('span');
                label.className = 'scfb-chip-label';
                label.textContent = chip.upper ? chip.token.toUpperCase() : chip.token;
                el.appendChild(label);

                el.appendChild(
                    makeButton('scfb-upper', chip.upper ? 'AA' : 'Aa', 'Toggle uppercase', () => {
                        state.chips[i].upper = !state.chips[i].upper;
                        commit();
                    })
                );

                el.appendChild(
                    makeButton('scfb-remove', '×', 'Remove token', () => {
                        // Drop the separator on the side that collapses: the trailing
                        // one for a first/middle chip, the leading one for the last chip.
                        const sepIndex = i === state.chips.length - 1 ? i - 1 : i;
                        state.chips.splice(i, 1);
                        if (sepIndex >= 0) {
                            state.seps.splice(sepIndex, 1);
                        }
                        commit();
                    })
                );

                el.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('text/plain', JSON.stringify({ field: fieldKey, index: i }));
                });
                el.addEventListener('dragover', (e) => e.preventDefault());
                el.addEventListener('drop', (e) => {
                    e.preventDefault();
                    let payload;
                    try {
                        payload = JSON.parse(e.dataTransfer.getData('text/plain'));
                    } catch (err) {
                        return;
                    }
                    // Reorder within a field only; ignore drags from a different field.
                    if (!payload || payload.field !== fieldKey) {
                        return;
                    }
                    const from = payload.index;
                    const to = i;
                    if (typeof from !== 'number' || from === to) {
                        return;
                    }
                    const moved = state.chips.splice(from, 1)[0];
                    state.chips.splice(to, 0, moved);
                    state.seps = state.chips.slice(1).map((_, k) => state.seps[k] || ', ');
                    commit();
                });

                chipsWrap.appendChild(el);
            });

            ui.appendChild(chipsWrap);

            const addWrap = document.createElement('div');
            addWrap.className = 'scfb-add-wrap';

            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'scfb-add';
            addBtn.title = 'Add token';
            addBtn.setAttribute('aria-label', 'Add token');
            addBtn.setAttribute('aria-haspopup', 'true');
            addBtn.setAttribute('aria-expanded', 'false');

            const menu = document.createElement('div');
            menu.className = 'scfb-add-menu';
            menu.hidden = true;

            function closeMenu() {
                menu.hidden = true;
                addBtn.setAttribute('aria-expanded', 'false');
                document.removeEventListener('click', onDocClick);
                if (closeActiveMenu === closeMenu) {
                    closeActiveMenu = null;
                }
            }

            function onDocClick(e) {
                if (!addWrap.contains(e.target)) {
                    closeMenu();
                }
            }

            TOKENS.forEach((token) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'scfb-add-item';
                item.textContent = token;
                item.addEventListener('click', () => {
                    closeMenu();
                    if (state.chips.length) {
                        state.seps.push(', ');
                    }
                    state.chips.push({ token: token, upper: false });
                    commit();
                });
                menu.appendChild(item);
            });

            addBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (menu.hidden) {
                    // Dismiss any other field's open menu first.
                    if (closeActiveMenu && closeActiveMenu !== closeMenu) {
                        closeActiveMenu();
                    }
                    menu.hidden = false;
                    // Open upward when there isn't room below (e.g. last field), so the
                    // popover overlays existing content instead of extending the page.
                    menu.classList.remove('scfb-add-menu--up');
                    const rect = addBtn.getBoundingClientRect();
                    const spaceBelow = window.innerHeight - rect.bottom;
                    if (spaceBelow < menu.offsetHeight + 8 && rect.top > spaceBelow) {
                        menu.classList.add('scfb-add-menu--up');
                    }
                    addBtn.setAttribute('aria-expanded', 'true');
                    document.addEventListener('click', onDocClick);
                    closeActiveMenu = closeMenu;
                } else {
                    closeMenu();
                }
            });

            addWrap.appendChild(addBtn);
            addWrap.appendChild(menu);
            ui.appendChild(addWrap);

            container.appendChild(ui);
        }

        render();
    }

    function resetToDefaults() {
        const defaults = (typeof SC_FORMAT_BUILDER !== 'undefined' && SC_FORMAT_BUILDER.defaults) || {};
        document.querySelectorAll('[data-sc-format-field]').forEach((container) => {
            const key = container.getAttribute('data-sc-format-field');
            const input = container.querySelector('input[type="hidden"]');
            if (input && typeof defaults[key] === 'string') {
                input.value = defaults[key];
                buildField(container);
            }
        });
    }

    function init() {
        document.querySelectorAll('[data-sc-format-field]').forEach(buildField);
        const resetBtn = document.querySelector('[data-sc-format-reset]');
        if (resetBtn) {
            resetBtn.addEventListener('click', resetToDefaults);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
