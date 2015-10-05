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
 * Define all the backup steps that will be used by the backup_courseblog_activity_task
 */

/**
 * Define the complete courseblog structure for backup, with file and id annotations
 */
class backup_courseblog_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $courseblog = new backup_nested_element('courseblog', array('id'), array(
            'name', 'intro', 'introformat', 'comments'));

        $entries = new backup_nested_element('entries');

        $entry = new backup_nested_element('entry', array('id'), array(
            'courseblogid', 'courseid', 'userid', 'groupid', 'blogid'));

        $courseblog->add_child($entries);
        $entries->add_child($entry);

        // Define sources
        $courseblog->set_source_table('courseblog', array('id' => backup::VAR_ACTIVITYID));

        // Define file annotations
        $courseblog->annotate_files('mod_courseblog', 'intro', null); // This file area hasn't itemid

        // Return the root element (courseblog), wrapped into standard activity structure
        return $this->prepare_activity_structure($courseblog);
    }
}
