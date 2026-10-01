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

namespace local_gradeheatmap\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_gradeheatmap\local\percentage_calculator;

/**
 * Returns the grade percentages used to shade the grader report.
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_percentages extends external_api {
    /**
     * @var int Most users per request when $CFG->maxgradesperpage is not higher: the grader report's own
     * grade_report_grader::MAX_GRADES_PER_PAGE (public/grade/report/grader/lib.php:119 in 5.2 and 5.3), the
     * most users it can show on one page (with one grade item).
     */
    public const MAX_USERS = 200000;

    /** @var int Users per filtering query, well below every database's bound-parameter limit. */
    protected const USER_CHUNK = 1000;

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'userids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User id shown in the grader report'),
                'Users whose grades are needed'
            ),
        ]);
    }

    /**
     * Returns the percentages of the requested users' grades.
     *
     * Users the caller may not see in the grader report (not gradable in the course, or outside
     * the caller's groups in separate groups mode) are silently left out.
     *
     * @param int $courseid Course id.
     * @param int[] $userids User ids.
     * @return array
     */
    public static function execute(int $courseid, array $userids): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        ['courseid' => $courseid, 'userids' => $userids] = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'userids' => $userids]
        );

        // The grader report never shows more users than this on one page; refuse oversized requests.
        $maxusers = max(self::MAX_USERS, (int) ($CFG->maxgradesperpage ?? 0));
        if (count($userids) > $maxusers) {
            throw new \invalid_parameter_exception('Too many users requested (at most ' . $maxusers . ')');
        }

        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('gradereport/grader:view', $context);
        require_capability('moodle/grade:viewall', $context);
        require_capability('local/gradeheatmap:view', $context);

        if (!get_config('local_gradeheatmap', 'enabled')) {
            return ['grades' => []];
        }

        $userids = self::filter_visible_users($courseid, $context, $userids);
        $canviewhidden = has_capability('moodle/grade:viewhidden', $context);
        $percentages = percentage_calculator::get_percentages($courseid, $userids, $canviewhidden);

        $grades = [];
        foreach ($percentages as $userid => $items) {
            foreach ($items as $itemid => $percentage) {
                $grades[] = ['userid' => $userid, 'itemid' => $itemid, 'percentage' => $percentage];
            }
        }
        return ['grades' => $grades];
    }

    /**
     * Restricts client-supplied user ids to users whose grades the caller can see in the grader report.
     *
     * @param int $courseid Course id.
     * @param context_course $context Course context.
     * @param int[] $userids Requested user ids.
     * @return int[] The permitted user ids.
     */
    protected static function filter_visible_users(int $courseid, context_course $context, array $userids): array {
        global $CFG, $DB, $USER;

        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        $gradebookroles = array_filter(array_map('intval', explode(',', (string) $CFG->gradebookroles)));
        if (!$userids || !$gradebookroles) {
            return [];
        }

        // The users the gradebook shows: enrolled in this course with a gradebook role. Not get_gradable_users(),
        // whose iterator throws while any grade in the course needs regrading.
        $onlyactive = !has_capability('moodle/course:viewsuspendedusers', $context);
        [$enrolledsql, $enrolledparams] = get_enrolled_sql($context, '', 0, $onlyactive);
        [$rolesql, $roleparams] = $DB->get_in_or_equal($gradebookroles, SQL_PARAMS_NAMED, 'grbr');
        [$contextsql, $contextparams] = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'ctx');
        $permitted = [];
        foreach (array_chunk($userids, self::USER_CHUNK) as $chunk) {
            [$usersql, $userparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'usr');
            $sql = "SELECT DISTINCT u.id
                      FROM {user} u
                      JOIN ($enrolledsql) je ON je.id = u.id
                      JOIN {role_assignments} ra ON ra.userid = u.id
                     WHERE u.id $usersql
                       AND u.deleted = 0
                       AND ra.roleid $rolesql
                       AND ra.contextid $contextsql";
            $params = $enrolledparams + $roleparams + $contextparams + $userparams;
            $permitted = array_merge($permitted, array_map('intval', $DB->get_fieldset_sql($sql, $params)));
        }
        $userids = $permitted;

        // Separate groups: only members of the caller's own groups, as in the grader report.
        $course = get_course($courseid);
        if (
            $userids && groups_get_course_groupmode($course) == SEPARATEGROUPS
                && !has_capability('moodle/site:accessallgroups', $context)
        ) {
            $allowed = [];
            foreach (groups_get_all_groups($courseid, $USER->id, $course->defaultgroupingid) as $group) {
                foreach (groups_get_members($group->id, 'u.id') as $member) {
                    $allowed[$member->id] = true;
                }
            }
            $userids = array_values(array_filter($userids, fn(int $userid): bool => isset($allowed[$userid])));
        }
        return $userids;
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'grades' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User id'),
                    'itemid' => new external_value(PARAM_INT, 'Grade item id'),
                    'percentage' => new external_value(PARAM_FLOAT, 'Percentage of the grade range, 0-100'),
                ])
            ),
        ]);
    }
}
