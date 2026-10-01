<?php
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
 * English language strings.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcolour'] = 'Add colour';
$string['colour'] = 'Colour';
$string['coursesettings'] = 'Grade heatmap';
$string['coursesettings_help'] = 'How the grader report of this course is shaded, for everyone who sees the heatmap here. Settings left at the site default follow the site settings.';
$string['enabled'] = 'Enable grade heatmap';
$string['enabled_desc'] = 'Shade the cells of the grader report by grade percentage, for users with the capability to view the heatmap.';
$string['gradeheatmap:manage'] = 'Change the grade heatmap settings of a course';
$string['gradeheatmap:view'] = 'View the grade heatmap on the grader report';
$string['invalidpalette'] = 'The palette was not saved. A palette needs 2 to {$a} colours, and each colour must start at a higher percentage (0 to 100) than the one before it.';
$string['mode'] = 'Colouring mode';
$string['mode_bands'] = 'Discrete bands';
$string['mode_desc'] = 'A smooth gradient between the palette colours, or one solid colour per band. Teachers can choose a different mode for a course.';
$string['mode_gradient'] = 'Smooth gradient';
$string['palette'] = 'Palette';
$string['palette_desc'] = 'The colours used to shade grades, from lowest to highest. Text in shaded cells is shown dark or light, whichever contrasts better with each colour. Teachers can choose their own palette for a course.';
$string['palette_help'] = 'Colours from lowest to highest. Each colour starts at the given percentage of the grade range; the lowest starts at 0%. A colour starting at 100% is used only for full marks. In gradient mode, colours blend from one start to the next.';
$string['pluginname'] = 'Grade heatmap';
$string['privacy:metadata:preference:enabled'] = 'Whether the user has switched the grade heatmap on in the grader report.';
$string['removecolour'] = 'Remove colour';
$string['sitedefault'] = 'Site default ({$a})';
$string['startsat'] = 'starts at';
$string['toggle'] = 'Grade heatmap';
$string['toggle_help'] = 'Shade grade cells by percentage, using the palette colours from the lowest grades to full marks.';
$string['usecustompalette'] = 'Use a custom palette for this course';
