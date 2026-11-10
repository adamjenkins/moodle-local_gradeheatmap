## v0.1.5

- Uninstalling the plugin now also removes each user's grader report heatmap on/off choice, which Moodle's
  own uninstall left behind in the user preferences.
- The plugin now has its own icon on the Plugins overview page (Site administration > Plugins).
- New automated tests check that a restored course whose backup carries an unknown heatmap mode or an
  unsafe palette falls back to the site settings.

No database or capability changes. No action is required after upgrading.
