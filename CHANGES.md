## v0.1.2

- Continuous integration now tests against the released Moodle 5.3 (MOODLE_503_STABLE)
  instead of Moodle's development branch.
- composer.json: the moodle/moodle requirement now uses a caret constraint (`^5.2`), so later
  Moodle 5.x releases are no longer excluded.
