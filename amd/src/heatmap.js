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
 * Shades the grader report cells by grade percentage.
 *
 * Percentages are computed server-side (local_gradeheatmap_get_percentages) for the users whose
 * rows are on the page; the displayed cell text is never parsed. Colours are read from CSS custom
 * properties (styles.css and the admin settings); this module never defines a colour itself.
 *
 * @module     local_gradeheatmap/heatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {setUserPreference} from 'core_user/repository';

const PREFERENCE = 'local_gradeheatmap_enabled';

const SELECTORS = {
    TABLE: '#user-grades',
    GRADEPARENT: '.gradeparent',
    CELL: 'td.gradecell[id]',
    ACTIONBAR: '.tertiary-navigation > .d-flex',
    ACTIONBAR_END: ':scope > .ms-auto',
    TOGGLE_REGION: '[data-region="local-gradeheatmap-toggle"]',
    TOGGLE: '[data-action="local-gradeheatmap-toggle"]',
};

const CLASSES = {
    CELL: 'local-gradeheatmap-cell',
    TEXT_DARK: 'local-gradeheatmap-text-dark',
    TEXT_LIGHT: 'local-gradeheatmap-text-light',
    BAND_PREFIX: 'local-gradeheatmap-band-',
};

const CELL_PROPERTY = '--local-gradeheatmap-cell-bg';

/** Grader report cell ids are u<userid>i<itemid> (grade/report/grader/lib.php). */
const CELL_ID = /^u(\d+)i(\d+)$/;

const state = {
    courseid: 0,
    enabled: false,
    mode: 'gradient',
    starts: [],
    table: null,
    palette: null,
    styleCache: new Map(),
    percentages: new Map(),
    requestedUsers: new Set(),
    scheduled: false,
};

/**
 * Resolves a CSS colour custom property to its RGB components.
 *
 * @param {HTMLElement} container Element inside the scope where the property is defined.
 * @param {String} name Custom property name without the leading dashes.
 * @returns {Number[]} [r, g, b]
 */
const resolveColour = (container, name) => {
    const probe = document.createElement('span');
    probe.hidden = true;
    probe.style.color = `var(--${name})`;
    container.appendChild(probe);
    const computed = window.getComputedStyle(probe).color;
    probe.remove();
    const parts = (computed.match(/[\d.]+/g) || ['0', '0', '0']).slice(0, 3).map(Number);
    return parts;
};

/**
 * Reads all colours from the custom properties.
 *
 * @returns {Object}
 */
const readPalette = () => {
    return {
        // Palette colours, lowest first: --local-gradeheatmap-colour-1 ... -N.
        colours: state.starts.map((start, i) => resolveColour(state.table, `local-gradeheatmap-colour-${i + 1}`)),
        textDark: resolveColour(state.table, 'local-gradeheatmap-text-dark'),
        textLight: resolveColour(state.table, 'local-gradeheatmap-text-light'),
    };
};

/**
 * WCAG relative luminance of an RGB colour.
 *
 * @param {Number[]} rgb
 * @returns {Number}
 */
const luminance = (rgb) => {
    const [r, g, b] = rgb.map((channel) => {
        const c = channel / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

/**
 * WCAG contrast ratio of two RGB colours.
 *
 * @param {Number[]} a
 * @param {Number[]} b
 * @returns {Number}
 */
const contrast = (a, b) => {
    const la = luminance(a);
    const lb = luminance(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
};

/**
 * Linear interpolation between two RGB colours.
 *
 * @param {Number[]} from
 * @param {Number[]} to
 * @param {Number} ratio 0-1
 * @returns {Number[]}
 */
const mix = (from, to, ratio) => from.map((channel, i) => Math.round(channel + (to[i] - channel) * ratio));

/**
 * The palette colour a percentage falls into: the highest colour whose start it has reached.
 *
 * Grades below the maximum are never 100 (the server caps them at 99.99), so a colour starting
 * at 100% is used only for full marks.
 *
 * @param {Number} percentage
 * @returns {Number} Index into the palette.
 */
const bandIndex = (percentage) => {
    let index = 0;
    state.starts.forEach((start, i) => {
        if (percentage >= start) {
            index = i;
        }
    });
    return index;
};

/**
 * Background colour for a percentage in the configured mode.
 *
 * In gradient mode a percentage blends from its colour towards the next one, reaching it at the
 * next colour's start; the highest colour below 100% stays solid up to 100%.
 *
 * @param {Number} percentage
 * @param {Number} band
 * @returns {Number[]}
 */
const backgroundFor = (percentage, band) => {
    const colours = state.palette.colours;
    const next = band + 1;
    if (state.mode !== 'gradient' || next >= state.starts.length || state.starts[next] >= 100) {
        return colours[band];
    }
    const from = state.starts[band];
    const to = state.starts[next];
    return mix(colours[band], colours[next], (percentage - from) / (to - from));
};

/**
 * Computes (and caches) how a percentage is drawn.
 *
 * @param {Number} percentage
 * @returns {Object} {background, band, text}
 */
const styleFor = (percentage) => {
    const key = percentage.toFixed(2);
    if (!state.styleCache.has(key)) {
        const band = bandIndex(percentage);
        const background = backgroundFor(percentage, band);
        const dark = contrast(background, state.palette.textDark) >= contrast(background, state.palette.textLight);
        state.styleCache.set(key, {
            background: `rgb(${background.join(', ')})`,
            band: CLASSES.BAND_PREFIX + (band + 1),
            text: dark ? CLASSES.TEXT_DARK : CLASSES.TEXT_LIGHT,
        });
    }
    return state.styleCache.get(key);
};

/**
 * Removes every heatmap class from a cell, except the ones to keep.
 *
 * @param {HTMLElement} cell
 * @param {String[]} keep
 */
const removeHeatmapClasses = (cell, keep = []) => {
    const remove = [...cell.classList].filter((name) => name.startsWith('local-gradeheatmap-') && !keep.includes(name));
    if (remove.length) {
        cell.classList.remove(...remove);
    }
};

/**
 * Shades one cell. Only writes to the DOM when something changes, so the observer settles.
 *
 * @param {HTMLElement} cell
 * @param {Number} percentage
 */
const shadeCell = (cell, percentage) => {
    const style = styleFor(percentage);
    const wanted = [CLASSES.CELL, style.band, style.text];
    removeHeatmapClasses(cell, wanted);
    const missing = wanted.filter((name) => !cell.classList.contains(name));
    if (missing.length) {
        cell.classList.add(...missing);
    }
    if (cell.style.getPropertyValue(CELL_PROPERTY) !== style.background) {
        cell.style.setProperty(CELL_PROPERTY, style.background);
    }
};

/**
 * Removes the shading from one cell.
 *
 * @param {HTMLElement} cell
 */
const clearCell = (cell) => {
    removeHeatmapClasses(cell);
    if (cell.style.getPropertyValue(CELL_PROPERTY)) {
        cell.style.removeProperty(CELL_PROPERTY);
    }
};

/**
 * All grade cells currently in the table, with their parsed ids.
 *
 * @returns {Object[]} [{cell, userid, itemid}]
 */
const getCells = () => [...state.table.querySelectorAll(SELECTORS.CELL)].map((cell) => {
    const match = CELL_ID.exec(cell.id);
    return match ? {cell, userid: Number(match[1]), itemid: Number(match[2])} : null;
}).filter((entry) => entry !== null);

/**
 * Fetches the percentages of the given users.
 *
 * @param {Number[]} userids
 * @returns {Promise}
 */
const fetchPercentages = (userids) => {
    userids.forEach((userid) => state.requestedUsers.add(userid));
    return Promise.resolve(Ajax.call([{
        methodname: 'local_gradeheatmap_get_percentages',
        args: {courseid: state.courseid, userids},
    }])[0]).then((response) => {
        response.grades.forEach((grade) => {
            state.percentages.set(`${grade.userid}_${grade.itemid}`, grade.percentage);
        });
        return response;
    });
};

/**
 * Shades or clears every cell, and fetches grades for users not seen yet.
 */
const apply = () => {
    state.scheduled = false;
    const cells = getCells();
    if (!state.enabled) {
        cells.forEach(({cell}) => clearCell(cell));
        return;
    }
    const missing = [...new Set(cells.map(({userid}) => userid))].filter((userid) => !state.requestedUsers.has(userid));
    if (missing.length) {
        fetchPercentages(missing).then(schedule).catch(Notification.exception);
    }
    cells.forEach(({cell, userid, itemid}) => {
        const percentage = state.percentages.get(`${userid}_${itemid}`);
        if (percentage === undefined) {
            clearCell(cell);
        } else {
            shadeCell(cell, percentage);
        }
    });
};

/**
 * Schedules one apply() for the next frame, however many changes arrive.
 */
const schedule = () => {
    if (!state.scheduled) {
        state.scheduled = true;
        window.requestAnimationFrame(apply);
    }
};

/**
 * Moves the toggle rendered in the footer into the grader report's action bar.
 */
const placeToggle = () => {
    const region = document.querySelector(SELECTORS.TOGGLE_REGION);
    if (!region) {
        return;
    }
    const actionBar = document.querySelector(SELECTORS.ACTIONBAR);
    if (actionBar) {
        const divider = document.createElement('div');
        divider.className = 'navitem-divider';
        const end = actionBar.querySelector(SELECTORS.ACTIONBAR_END);
        actionBar.insertBefore(region, end);
        actionBar.insertBefore(divider, end);
    } else {
        const gradeParent = document.querySelector(SELECTORS.GRADEPARENT);
        gradeParent.parentNode.insertBefore(region, gradeParent);
    }
    region.hidden = false;

    region.querySelector(SELECTORS.TOGGLE).addEventListener('change', (e) => {
        state.enabled = e.target.checked;
        schedule();
        setUserPreference(PREFERENCE, state.enabled ? 1 : 0).catch(Notification.exception);
    });
};

/**
 * Initialises the heatmap.
 *
 * @param {Object} config
 * @param {Number} config.courseid Course id.
 * @param {Boolean} config.enabled Whether the user has the heatmap switched on.
 * @param {String} config.mode 'gradient' or 'bands'.
 * @param {Number[]} config.starts Start percentage of each palette colour, lowest first.
 */
export const init = ({courseid, enabled, mode, starts}) => {
    state.table = document.querySelector(SELECTORS.TABLE);
    if (!state.table) {
        return;
    }
    state.courseid = courseid;
    state.enabled = enabled;
    state.mode = mode;
    state.starts = starts;
    state.palette = readPalette();

    placeToggle();

    // Re-apply after anything the grader report does to the table: collapsing and expanding columns,
    // re-rendered rows or cells, and class rewrites. apply() is idempotent, so its own writes settle.
    const observer = new MutationObserver(schedule);
    observer.observe(state.table, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'id'],
    });

    schedule();
};
