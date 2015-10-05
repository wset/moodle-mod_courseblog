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
 * The main courseblog configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_courseblog
 * @copyright  2011 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 */
class mod_courseblog_add_entry_form extends moodleform {

    protected $availableblogs = array();

    public function __construct($actionurl, $availableblogs) {
        $this->availableblogs = $availableblogs;
        parent::moodleform($actionurl);
    }

    /**
     * Defines forms elements
     */
    public function definition() {

        $mform = $this->_form;

        //-------------------------------------------------------------------------------
        // Add the header.
        $mform->addElement('header', 'general', get_string('selectblogs', 'mod_courseblog'));

        $mform->addElement('html', '<table class="generaltable"><tr><th>' . get_string('select') . '</th>' .
                                   '<th>' . get_string('subject', 'mod_courseblog') . '</th>' .
                                   '<th>' . get_string('summary') . '</th></tr>');

        foreach ($this->availableblogs as $blog) {
            $mform->addElement('html', '<tr><td><input type="checkbox" name="blog_group[]" value="' . $blog->id . '" /> </td>' .
                                       '<td>' . $blog->subject . '</td>' .
                                       '<td>' . $blog->summary . '</td></tr>');
        }

        $mform->addElement('html', '</table>');

        //-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons(true, get_string('addselected', 'mod_courseblog'));
    }
}
