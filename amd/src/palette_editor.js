// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Adds and removes colours in the palette editor (local_gradeheatmap/palette_editor template).
 *
 * The server validates the submitted palette; this only keeps the list tidy: numbering, the lowest
 * colour fixed at 0%, and the minimum and maximum number of colours.
 *
 * @module     local_gradeheatmap/palette_editor
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    EDITOR: '[data-region="local-gradeheatmap-palette"]',
    ROWS: '[data-region="rows"]',
    ROW: '[data-region="row"]',
    NEWROW: 'template[data-region="newrow"]',
    NUMBER: '[data-region="number"]',
    FROM: '[data-region="from"]',
    ADD: '[data-action="add"]',
    REMOVE: '[data-action="remove"]',
};

/**
 * Renumbers the colours and updates which controls are available.
 *
 * @param {HTMLElement} editor
 */
const refresh = (editor) => {
    const rows = [...editor.querySelectorAll(SELECTORS.ROW)];
    const min = Number(editor.dataset.mincolours);
    const max = Number(editor.dataset.maxcolours);
    const disabled = editor.querySelector(SELECTORS.ADD).hasAttribute('data-locked');
    rows.forEach((row, index) => {
        // Rows added from the template carry its initial state, so every row follows the editor's.
        row.querySelectorAll('input').forEach((input) => {
            input.disabled = disabled;
        });
        row.querySelectorAll(SELECTORS.NUMBER).forEach((number) => {
            number.textContent = String(index + 1);
        });
        const from = row.querySelector(SELECTORS.FROM);
        if (index === 0) {
            from.value = '0';
            from.readOnly = true;
        } else {
            from.readOnly = false;
        }
        row.querySelector(SELECTORS.REMOVE).disabled = disabled || rows.length <= min;
    });
    editor.querySelector(SELECTORS.ADD).disabled = disabled || rows.length >= max;
};

/**
 * Adds a colour in the middle of the widest gap between colour starts (the space above the highest
 * colour counts as a gap up to 100%), so the new start always lies strictly between its neighbours.
 *
 * @param {HTMLElement} editor
 */
const addRow = (editor) => {
    const list = editor.querySelector(SELECTORS.ROWS);
    const template = editor.querySelector(SELECTORS.NEWROW);
    const rows = [...list.querySelectorAll(SELECTORS.ROW)];
    const starts = rows.map((element) => Number(element.querySelector(SELECTORS.FROM).value) || 0);
    // Gaps as [index to insert before (null = append), lower start, upper start].
    const gaps = starts.slice(1).map((upper, i) => [rows[i + 1], starts[i], upper]);
    if (!starts.length || starts[starts.length - 1] < 100) {
        gaps.push([null, starts.length ? starts[starts.length - 1] : 0, 100]);
    }
    const widest = gaps.reduce((best, gap) => (!best || gap[2] - gap[1] > best[2] - best[1]) ? gap : best, null);
    const start = widest ? Math.round((widest[1] + (widest[2] - widest[1]) / 2) * 100) / 100 : null;
    if (start === null || start <= widest[1] || start >= widest[2]) {
        // No room left for another start (two decimals).
        return;
    }
    const row = template.content.querySelector(SELECTORS.ROW).cloneNode(true);
    row.querySelector(SELECTORS.FROM).value = String(start);
    list.insertBefore(row, widest[0]);
    refresh(editor);
    row.querySelector('input').focus();
};

/**
 * Enables or disables an editor (e.g. when a course switches between the site palette and its own).
 *
 * @param {HTMLElement} editor
 * @param {Boolean} enabled
 */
export const setEnabled = (editor, enabled) => {
    const add = editor.querySelector(SELECTORS.ADD);
    if (enabled) {
        add.removeAttribute('data-locked');
    } else {
        add.setAttribute('data-locked', '1');
    }
    refresh(editor);
};

/**
 * Initialises every palette editor on the page.
 */
export const init = () => {
    document.querySelectorAll(SELECTORS.EDITOR).forEach((editor) => {
        if (editor.dataset.initialised) {
            return;
        }
        editor.dataset.initialised = '1';
        if (editor.querySelector(SELECTORS.ADD).disabled) {
            editor.querySelector(SELECTORS.ADD).setAttribute('data-locked', '1');
        }
        editor.addEventListener('click', (e) => {
            if (e.target.closest(SELECTORS.ADD)) {
                e.preventDefault();
                addRow(editor);
                return;
            }
            const remove = e.target.closest(SELECTORS.REMOVE);
            if (remove) {
                e.preventDefault();
                const row = remove.closest(SELECTORS.ROW);
                const next = row.nextElementSibling || row.previousElementSibling;
                row.remove();
                refresh(editor);
                next?.querySelector('input')?.focus();
            }
        });
        refresh(editor);
    });
};
