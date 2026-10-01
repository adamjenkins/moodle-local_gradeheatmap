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

namespace local_gradeheatmap\local;

use grade_grade;
use grade_item;

/**
 * Computes grade percentages for the grader report heatmap.
 *
 * Percentage = (finalgrade - grademin) / (grademax - grademin) * 100, where the min and max are the
 * ones the grader report itself displays against (grade_grade::get_grade_min()/get_grade_max(),
 * which use the per-grade raw min/max for aggregated category and course totals).
 *
 * @package    local_gradeheatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class percentage_calculator {
    /** @var float Tolerance for floating point noise when comparing a grade with the maximum. */
    public const EPSILON = 0.0000001;

    /** @var float The highest percentage reported for a grade that is below the maximum. */
    public const BELOW_MAX_CAP = 99.99;

    /** @var int Number of users fetched per grade query. */
    protected const USER_CHUNK = 500;

    /**
     * Converts a value within a range into a percentage of that range.
     *
     * Exactly 100 is only returned when the value reaches the maximum, so a grade just below
     * the maximum can never be rounded up into the "perfect" colour.
     *
     * @param float|null $value The grade value, or null when there is no grade.
     * @param float $min The lowest value of the range.
     * @param float $max The highest value of the range.
     * @return float|null The percentage (0-100, two decimals), or null when it cannot be computed.
     */
    public static function percentage(?float $value, float $min, float $max): ?float {
        if ($value === null || !is_finite($value)) {
            return null;
        }
        $range = $max - $min;
        if ($range <= self::EPSILON) {
            return null;
        }
        if ($value >= $max - self::EPSILON) {
            return 100.0;
        }
        $percentage = ($value - $min) / $range * 100;
        return max(0.0, min(round($percentage, 2), self::BELOW_MAX_CAP));
    }

    /**
     * Whether a grade item can be shaded at all (numeric or scale items only).
     *
     * @param grade_item $item The grade item.
     * @return bool
     */
    public static function is_shadeable_item(grade_item $item): bool {
        if ($item->gradetype != GRADE_TYPE_VALUE && $item->gradetype != GRADE_TYPE_SCALE) {
            return false;
        }
        return empty($item->needsupdate);
    }

    /**
     * Computes the percentage of a single grade.
     *
     * @param grade_grade $grade The grade, with its grade_item property loaded.
     * @param float|null $value Value to use instead of the final grade (e.g. altered by hiding), or null for the final grade.
     * @param float|null $min Minimum to use instead of the grade's own, or null.
     * @param float|null $max Maximum to use instead of the grade's own, or null.
     * @return float|null The percentage, or null when the cell must stay uncoloured.
     */
    public static function for_grade(
        grade_grade $grade,
        ?float $value = null,
        ?float $min = null,
        ?float $max = null
    ): ?float {
        $item = $grade->load_grade_item();
        if (!$item || !self::is_shadeable_item($item)) {
            return null;
        }
        if ($grade->is_excluded()) {
            return null;
        }
        if ($value === null) {
            if ($grade->finalgrade === null || $grade->finalgrade === '') {
                return null;
            }
            // An overridden grade's finalgrade already holds the overridden value.
            $value = (float) $grade->finalgrade;
        }
        $min = $min ?? (float) $grade->get_grade_min();
        $max = $max ?? (float) $grade->get_grade_max();
        return self::percentage($value, $min, $max);
    }

    /**
     * Computes the percentages of all shadeable grades of the given users in a course.
     *
     * Grades are fetched in bulk (one query per chunk of users), never per cell.
     *
     * @param int $courseid The course id.
     * @param int[] $userids The users whose grades are needed.
     * @param bool $canviewhidden Whether the viewer may see hidden grades (moodle/grade:viewhidden).
     * @return array Percentages keyed by user id, then by grade item id.
     */
    public static function get_percentages(int $courseid, array $userids, bool $canviewhidden): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if (!$userids) {
            return [];
        }

        $items = grade_item::fetch_all(['courseid' => $courseid]) ?: [];
        // Items that can hold a grade at all, as the grader report uses for hiding calculations.
        $gradableitems = array_filter($items, fn(grade_item $item): bool => $item->gradetype != GRADE_TYPE_NONE);
        if (!$gradableitems) {
            return [];
        }

        $result = [];
        foreach (array_chunk($userids, self::USER_CHUNK) as $chunk) {
            [$usersql, $userparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'u');
            $sql = "SELECT g.*
                      FROM {grade_grades} g
                      JOIN {grade_items} gi ON gi.id = g.itemid
                     WHERE gi.courseid = :courseid AND g.userid {$usersql}";
            $params = ['courseid' => $courseid] + $userparams;

            $usergrades = [];
            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $record) {
                if (!isset($gradableitems[$record->itemid])) {
                    continue;
                }
                $grade = new grade_grade($record, false);
                $grade->grade_item = $gradableitems[$record->itemid];
                $usergrades[$record->userid][$record->itemid] = $grade;
            }
            $rs->close();

            foreach ($chunk as $userid) {
                $grades = $usergrades[$userid] ?? [];
                $percentages = $canviewhidden
                    ? self::user_percentages($grades)
                    : self::user_percentages_without_hidden($userid, $grades, $gradableitems);
                if ($percentages) {
                    $result[$userid] = $percentages;
                }
            }
        }
        return $result;
    }

    /**
     * Percentages of one user's grades, for a viewer who can see hidden grades.
     *
     * @param grade_grade[] $grades The user's grades keyed by item id.
     * @return array Percentages keyed by item id.
     */
    protected static function user_percentages(array $grades): array {
        $percentages = [];
        foreach ($grades as $itemid => $grade) {
            $percentage = self::for_grade($grade);
            if ($percentage !== null) {
                $percentages[$itemid] = $percentage;
            }
        }
        return $percentages;
    }

    /**
     * Percentages of one user's grades, for a viewer who cannot see hidden grades.
     *
     * Mirrors the grader report: hidden grades are not shown, totals that depend on hidden
     * grades show the adjusted value, and totals that cannot be determined are not shown.
     *
     * @param int $userid The user id.
     * @param grade_grade[] $grades The user's grades keyed by item id.
     * @param grade_item[] $items All gradable items of the course keyed by id.
     * @return array Percentages keyed by item id.
     */
    protected static function user_percentages_without_hidden(int $userid, array $grades, array $items): array {
        // The hiding calculation needs a grade object for every item, as the grader report provides.
        $allgrades = $grades;
        foreach ($items as $itemid => $item) {
            if (!isset($allgrades[$itemid])) {
                $empty = new grade_grade();
                $empty->itemid = $itemid;
                $empty->userid = $userid;
                $empty->grade_item = $item;
                $allgrades[$itemid] = $empty;
            }
        }
        $hiding = grade_grade::get_hiding_affected($allgrades, $items);

        $percentages = [];
        foreach ($grades as $itemid => $grade) {
            if ($grade->is_hidden() || array_key_exists($itemid, $hiding['unknowngrades'])) {
                continue;
            }
            if (array_key_exists($itemid, $hiding['altered'])) {
                $value = $hiding['altered'][$itemid];
                if ($value === null) {
                    continue;
                }
                $percentage = self::for_grade(
                    $grade,
                    (float) $value,
                    isset($hiding['alteredgrademin'][$itemid]) ? (float) $hiding['alteredgrademin'][$itemid] : null,
                    isset($hiding['alteredgrademax'][$itemid]) ? (float) $hiding['alteredgrademax'][$itemid] : null
                );
            } else {
                $percentage = self::for_grade($grade);
            }
            if ($percentage !== null) {
                $percentages[$itemid] = $percentage;
            }
        }
        return $percentages;
    }
}
