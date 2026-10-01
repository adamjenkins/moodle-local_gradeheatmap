@local @local_gradeheatmap @javascript
Feature: Grade heatmap on the grader report
  In order to see at a glance where students are struggling and excelling
  As a teacher
  I need the grader report cells shaded by grade percentage

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | 1        |
      | teacher2 | Teacher   | 2        |
      | student1 | Student   | 1        |
      | student2 | Student   | 2        |
      | student3 | Student   | 3        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "grade items" exist:
      | itemname   | grademin | grademax | course |
      | Range item | 10       | 20       | C1     |
      | Exam       | 0        | 100      | C1     |
    And the following "grade grades" exist:
      | gradeitem  | user     | grade |
      | Range item | student1 | 11    |
      | Exam       | student1 | 100   |
      | Range item | student2 | 20    |
      | Exam       | student2 | 96    |
      | Exam       | student3 | 60    |

  Scenario: Grade cells receive the band classes for their percentage
    When I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    # 11 of 10-20 is 10%: low, even though 11 of 20 would be 55%.
    Then the grade heatmap cell of "student1" for "Range item" in "C1" should have class "local-gradeheatmap-band-1"
    And the grade heatmap cell of "student1" for "Range item" in "C1" should have class "local-gradeheatmap-cell"
    And the grade heatmap cell of "student1" for "Exam" in "C1" should have class "local-gradeheatmap-band-5"
    And the grade heatmap cell of "student2" for "Range item" in "C1" should have class "local-gradeheatmap-band-5"
    And the grade heatmap cell of "student2" for "Exam" in "C1" should have class "local-gradeheatmap-band-4"
    And the grade heatmap cell of "student3" for "Exam" in "C1" should have class "local-gradeheatmap-band-2"
    And the grade heatmap cell of "student2" for "Course total" in "C1" should have class "local-gradeheatmap-cell"
    # No grade: no shading.
    And the grade heatmap cell of "student3" for "Range item" in "C1" should not have class "local-gradeheatmap-cell"
    # The grade itself is not altered.
    And I should see "96.00" in the "Student 2" "table_row"

  Scenario: Teachers can switch the heatmap off and the choice persists
    Given I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    And the grade heatmap cell of "student1" for "Exam" in "C1" should have class "local-gradeheatmap-cell"
    When I click on "Grade heatmap" "checkbox"
    Then the grade heatmap cell of "student1" for "Exam" in "C1" should not have class "local-gradeheatmap-cell"
    And I reload the page
    And the field "Grade heatmap" matches value "0"
    And the grade heatmap cell of "student1" for "Exam" in "C1" should not have class "local-gradeheatmap-cell"
    And I click on "Grade heatmap" "checkbox"
    And the grade heatmap cell of "student1" for "Exam" in "C1" should have class "local-gradeheatmap-band-5"

  Scenario: Teachers set the palette of a course on the grader report preferences page
    Given I log in as "teacher1"
    And I am on the grader report preferences page of "C1"
    And I should see "Grade heatmap" in the "#id_local_gradeheatmap_coursesettings" "css_element"
    And the field "Use a custom palette for this course" matches value "0"
    When I set the field "Use a custom palette for this course" to "1"
    And I press "Add colour"
    # The new colour goes into the widest gap (0-50%) as colour 2, so the full-marks colour becomes colour 6.
    And I set grade heatmap palette colour "5" to start at "97"
    And I set grade heatmap palette colour "6" to "#0000ff"
    And I press "Save changes"
    And the field "Use a custom palette for this course" matches value "1"
    And I am on the "Course 1" "grades > Grader report > View" page
    Then the grade heatmap cell of "student1" for "Exam" in "C1" should have class "local-gradeheatmap-band-6"
    And the grade heatmap cell of "student1" for "Exam" in "C1" should have background "rgb(0, 0, 255)"
    # White text on the dark blue, for contrast.
    And the grade heatmap cell of "student1" for "Exam" in "C1" should have class "local-gradeheatmap-text-light"
    And I am on the grader report preferences page of "C1"
    And I set the field "Use a custom palette for this course" to "0"
    And I press "Save changes"
    And I am on the "Course 1" "grades > Grader report > View" page
    And the grade heatmap cell of "student1" for "Exam" in "C1" should have class "local-gradeheatmap-band-5"
    And the grade heatmap cell of "student1" for "Exam" in "C1" should have background "rgb(138, 180, 248)"
    And the grade heatmap cell of "student1" for "Exam" in "C1" should have class "local-gradeheatmap-text-dark"

  Scenario: Teachers choose discrete bands for a course
    Given I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher1"
    # 60% in gradient mode blends between the 50% and 75% colours.
    And the grade heatmap cell of "student3" for "Exam" in "C1" should have class "local-gradeheatmap-band-2"
    And the grade heatmap cell of "student3" for "Exam" in "C1" should not have background "rgb(251, 188, 119)"
    When I am on the grader report preferences page of "C1"
    And I set the field "Colouring mode" to "Discrete bands"
    And I press "Save changes"
    And I am on the "Course 1" "grades > Grader report > View" page
    Then the grade heatmap cell of "student3" for "Exam" in "C1" should have background "rgb(251, 188, 119)"

  Scenario: Non-editing teachers see the heatmap but cannot change the course colours
    Given I am on the "Course 1" "grades > Grader report > View" page logged in as "teacher2"
    And the grade heatmap cell of "student1" for "Exam" in "C1" should have class "local-gradeheatmap-band-5"
    When I am on the grader report preferences page of "C1"
    Then "#id_local_gradeheatmap_coursesettings" "css_element" should not exist
