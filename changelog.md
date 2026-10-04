# Changelog

All notable changes to the Grade heatmap plugin are documented in this file.

## [0.1.2] - 2026-10-04

### Changed

- Continuous integration tests against the released Moodle 5.3 (MOODLE_503_STABLE) instead of
  Moodle's development branch.
- composer.json: the moodle/moodle requirement uses a caret constraint (`^5.2`), so later
  Moodle 5.x releases are no longer excluded.

## [0.1.1] - 2026-10-04

### Added

- Grader report heatmap: grade cells are shaded by the grade's percentage of its item's range,
  computed on the server, for regular items, category totals and the course total. Scales use
  the grade's position in the scale. Empty grades, text-only items, items with no range and
  excluded grades are left uncoloured.
- Smooth gradient or discrete bands, with a palette of 2 to 20 colours from lowest to highest;
  a colour starting at 100% is used only for full marks.
- Site settings (enable, mode, palette) and per-course mode and palette for teachers, in a new
  section of the grader report preferences page.
- Per-user on/off switch on the grader report, saved as a user preference.
- Capabilities `local/gradeheatmap:view` and `local/gradeheatmap:manage`.
- Course backup and restore of the course settings; privacy provider for the user preference.
- Supports Moodle 5.2 and 5.3; installable with Composer (`composer.json`).
