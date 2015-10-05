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
 * @package    mod_courseblog
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_courseblog_activity_task
 */

/**
 * Structure step to restore one courseblog activity
 */
class restore_courseblog_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('courseblog', '/activity/courseblog');
        if ($userinfo) {
            $paths[] = new restore_path_element('courseblog_entries', '/activity/courseblog/entries');
            $paths[] = new restore_path_element('courseblog_entry', '/activity/courseblog/entries/entry');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_courseblog($courseblog) {
        global $DB;

        $courseblog = (object)$courseblog;
        $oldid = $courseblog->id;
        $courseblog->course = $this->get_courseid();
        $courseblog->timecreated = time();
        $courseblog->timemodified = $courseblog->timecreated;

        // insert the courseblog record
        $newitemid = $DB->insert_record('courseblog', $courseblog);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_courseblog_entries($entries) {
        // nothing here.
    }

    protected function process_courseblog_entry($entry) {
        global $DB;
        // Process any entries.
        $entry = (object)$entry;
        $oldid = $entry->id;

        $entry->userid = $this->get_mappingid('user', $entry->userid);
        $entry->groupid = $this->get_mappingid('group', $entry->groupid);
        $entry->courseid = $this->get_courseid();
        $entry->courseblogid = $this->get_new_parentid('courseblog');

        // insert the courseblog_entry record
        $newitemid = $DB->insert_record('courseblog_entries', $entry);
        $this->set_mapping('courseblog_entries', $oldid, $newitemid, false); // no files associated
    }

    protected function after_execute() {
        // Add courseblog related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_courseblog', 'intro', null);
    }
}
