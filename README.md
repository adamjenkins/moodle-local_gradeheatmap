# Grade heatmap (local_gradeheatmap)

Colour-codes the Moodle gradebook **grader report** so teachers can see at a glance where
students are struggling and excelling. Each grade cell is shaded by the grade's percentage of
its item's range, from the lowest palette colour (red by default) through orange and yellow to
green, with a distinct colour (blue by default) only for full marks.

Supports Moodle 5.2 and 5.3.

## Features

- Shades regular grade items, category totals and the course total on
  *Grades › Grader report*.
- Percentage = (final grade − minimum) / (maximum − minimum) × 100, computed on the server with
  the same minimum and maximum the grader report itself uses (for totals, the per-grade range
  from aggregation). The displayed cell text is never parsed, so letter, percentage and real
  display types all work.
- Scales use the grade's position in the scale (the first item is 0%, the last 100%).
- Left uncoloured: empty grades, text-only items, items with no numeric range, excluded
  grades, and items that are waiting to be recalculated.
- Hidden and locked grades are shaded normally and keep their icons. On a shaded cell a hidden
  grade is shown in italics instead of core's muted grey, which would be hard to read on the colour.
  Teachers without *moodle/grade:viewhidden* get no shading for grades the report hides from
  them, and totals follow the values the report shows them.
- Overridden grades use the overridden final grade.
- The grade value itself is never changed. Text in shaded cells is shown dark or light,
  whichever contrasts better with the cell's colour.
- Survives the grader report's dynamic behaviour (collapsing columns, editing mode, sorting,
  paging, sticky header and footer).

## Settings

### Site (Site administration › Plugins › Local plugins › Grade heatmap)

- **Enable grade heatmap**: site-wide on/off.
- **Colouring mode**: *Smooth gradient* (colours blend from one start to the next) or
  *Discrete bands* (one solid colour per band).
- **Palette**: the colours from lowest to highest, each with the percentage it starts at. The
  lowest starts at 0%. A colour that starts at 100% is used only for full marks. Colours can
  be added and removed (2 to 20 colours). Default:

  | Colour | Starts at |
  |---|---|
  | `#f28b82` | 0% |
  | `#fbbc77` | 50% |
  | `#fde68a` | 75% |
  | `#8fd19e` | 95% |
  | `#8ab4f8` | 100% (full marks only) |

### Course (Grader report › Preferences: Grader report › "Grade heatmap" section)

Teachers with *local/gradeheatmap:manage* can choose, for their course:

- **Colouring mode**: site default, smooth gradient or discrete bands.
- **Palette**: tick *Use a custom palette for this course* and edit the colours as above.

These course settings apply to everyone who sees the heatmap in that course. They are included
in course backups and removed when the course is deleted.

### Per user

A **Grade heatmap** switch in the grader report's action bar turns the shading on and off for
the current user. The choice is saved as a user preference.

## Capabilities

| Capability | Default roles | Purpose |
|---|---|---|
| `local/gradeheatmap:view` | Teacher, Non-editing teacher, Manager | See the heatmap on the grader report (also needs *gradereport/grader:view* and *moodle/grade:viewall*) |
| `local/gradeheatmap:manage` | Teacher, Manager | Change the course's mode and palette |

## How it works

A hook callback on the grader report checks the capabilities and loads an AMD module. The
module reads the user and item ids from the report's cells (`id="u<userid>i<itemid>"`) and
fetches the percentages of the users on the page with one call to the
`local_gradeheatmap_get_percentages` web service. The service checks the capabilities and
restricts the users to those the caller may see in the grader report, including separate
groups. All grades are fetched in bulk, not per cell. The palette colours are CSS custom
properties (`--local-gradeheatmap-colour-1` … `-N`). The module only reads them and sets each
cell's colour.

## Privacy

The plugin stores one user preference (the heatmap switch), declared and exported through the
Privacy API. The course settings table holds no personal data.

## Licence

GPL-3.0-or-later. Copyright 2026 Adam Jenkins.
