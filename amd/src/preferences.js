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
 * Places the course grade heatmap section inside the grader report preferences form.
 *
 * @module     local_gradeheatmap/preferences
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init as initEditors, setEnabled} from 'local_gradeheatmap/palette_editor';

const SELECTORS = {
    SECTION: '[data-region="local-gradeheatmap-coursesettings"]',
    USECUSTOM: '[data-action="local-gradeheatmap-usecustom"]',
    EDITOR: '[data-region="local-gradeheatmap-palette"]',
    // Core's grader_report_preferences_form, identified by its formslib marker field.
    FORM: 'input[name="_qf__grader_report_preferences_form"]',
    SUBMIT: '#id_submitbutton',
};

/**
 * Moves the section before the form's Save button, so the form submits it.
 */
export const init = () => {
    const section = document.querySelector(SELECTORS.SECTION);
    const marker = document.querySelector(SELECTORS.FORM);
    if (!section || !marker) {
        return;
    }
    const form = marker.form;
    const submit = form.querySelector(SELECTORS.SUBMIT);
    const buttons = submit?.closest('.fitem') || submit?.parentNode;
    if (buttons && buttons.parentNode && form.contains(buttons)) {
        buttons.parentNode.insertBefore(section, buttons);
    } else {
        form.appendChild(section);
    }
    section.hidden = false;

    initEditors();
    const usecustom = section.querySelector(SELECTORS.USECUSTOM);
    const editor = section.querySelector(SELECTORS.EDITOR);
    // Without a custom palette the editor shows the site palette, disabled so it is not submitted.
    usecustom.addEventListener('change', () => setEnabled(editor, usecustom.checked));
};
