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
 * This file is part of the Database module for Moodle
 *
 * @copyright 2005 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package mod_courseblog
 */

require_once('../../config.php');
require_once('lib.php');
require_once("$CFG->libdir/rsslib.php");
require_once("$CFG->libdir/form/filemanager.php");
require_once('edit_form.php');
require_once('newblog_form.php');
require_once($CFG->dirroot . '/blog/lib.php');
require_once($CFG->dirroot . '/blog/locallib.php');

$id    = optional_param('id', 0, PARAM_INT);    // course module id
$d     = optional_param('d', 0, PARAM_INT);    // courseblog id
$cancel   = optional_param('cancel', '', PARAM_RAW);    // cancel an add
$bloggroup = optional_param_array('blog_group', array(), PARAM_INT);
$new = optional_param('new', 0, PARAM_BOOL);

$url = new moodle_url('/mod/courseblog/edit.php');
if ($cancel !== '') {
    $url->param('cancel', $cancel);
}

if ($id) {
    $url->param('id', $id);
    $PAGE->set_url($url);
    if (! $cm = get_coursemodule_from_id('courseblog', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record('course', array('id'=>$cm->course))) {
        print_error('coursemisconf');
    }
    if (! $courseblog = $DB->get_record('courseblog', array('id'=>$cm->instance))) {
        print_error('invalidcoursemodule');
    }

} else {
    $url->param('d', $d);
    $PAGE->set_url($url);
    if (! $courseblog = $DB->get_record('courseblog', array('id'=>$d))) {
        print_error('invalidid', 'courseblog');
    }
    if (! $course = $DB->get_record('course', array('id'=>$courseblog->course))) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance('courseblog', $courseblog->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

require_login($course, false, $cm);

if (isguestuser()) {
    redirect('view.php?d='.$courseblog->id);
}

$context = context_module::instance($cm->id);

/// If it's hidden then it doesn't show anything.  :)
if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
    $strcourseblogbases = get_string("modulenameplural", "courseblog");

    $PAGE->set_title($courseblog->name);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    notice(get_string("activityiscurrentlyhidden"));
}

// Get Group information for permission testing and record creation
$currentgroup = groups_get_activity_group($cm);
$groupmode = groups_get_activity_groupmode($cm);

if ($cancel) {
    redirect('view.php?d='.$courseblog->id);
}

$possibleentries = $DB->get_records_sql("SELECT * from {post} WHERE
                                         userid = :userid
                                         AND module = 'blog'
                                         AND id NOT IN (
                                          SELECT blogid FROM {courseblog_entries}
                                          WHERE courseblogid = :courseblogid
                                         )" , array('userid' => $USER->id,
                                                    'courseblogid'=>$courseblog->id));

/// Define page variables
$strcourseblog = get_string('modulenameplural','courseblog');

$PAGE->navbar->add(get_string('editentry', 'courseblog'));

$PAGE->set_title($courseblog->name);
$PAGE->set_heading($course->fullname);

// Process incoming courseblog for adding/updating records.

// Keep track of any notifications.
$generalnotifications = array();

if (!$new) {
    $mform = new mod_courseblog_add_entry_form($url, $possibleentries);
    if ($data = $mform->get_data()) {
        foreach($bloggroup as $blogid) {
            $entry = array();
            $entry['userid'] = $USER->id;
            $entry['blogid'] = $blogid;
            $entry['courseid'] = $courseblog->course;
            $entry['courseblogid'] = $courseblog->id;
            $entry['groupid'] = $currentgroup;
            if (!$DB->get_record('courseblog_entries', $entry)) {
                $DB->insert_record('courseblog_entries', $entry);
                // Update completion state
                $completion=new completion_info($course);
                if($completion->is_enabled($cm) && $courseblog->completionposts) {
                    $completion->update_state($cm,COMPLETION_COMPLETE);
                }
            }
        }
        redirect(new moodle_url('/mod/courseblog/view.php', array('id' => $id, 'd' => $courseblog->id)));
    }
} else {
    $entry = new stdClass();
    $entry->id = null;
    $entry->d = $d;

    $modid = $cm->id;
    $courseid = $COURSE->id;

    $sitecontext = context_system::instance();
    $coursecontext = context_course::instance($courseid);
    $usercontext = context_user::instance($USER->id);

    $entry->courseassoc = $coursecontext->id;

    $summaryoptions = array('maxfiles'=> 99,
                            'maxbytes'=>$CFG->maxbytes,
                            'trusttext'=>true,
                            'context'=>$sitecontext,
                            'subdirs'=>file_area_contains_subdirs($sitecontext, 'blog', 'post', $entry->id));
    $attachmentoptions = array('subdirs'=>false,
                               'maxfiles'=> 99,
                               'maxbytes'=>$CFG->maxbytes);

    $mform = new courseblog_edit_form(null, compact('entry',
                                                    'summaryoptions',
                                                    'attachmentoptions',
                                                    'sitecontext',
                                                    'courseid',
                                                    'modid'));

    $entry = file_prepare_standard_editor($entry, 'summary', $summaryoptions, $sitecontext, 'blog', 'post', $entry->id);
    $entry = file_prepare_standard_filemanager($entry,
                                               'attachment',
                                               $attachmentoptions,
                                               $sitecontext,
                                               'blog',
                                               'attachment',
                                               $entry->id);

    $entry->action = 'add';

    $mform->set_data($entry);

    if ($mform->is_cancelled()) {
        // Process the form.
        redirect(new moodle_url('/mod/courseblog/view.php', array('id' => $id, 'd' => $courseblog->id)));
    } else if ($data = $mform->get_data()) {
        // Process the form.
        $blogentry = new blog_entry(null, $data, $mform);
        $blogentry->add();
        $blogentry->edit($data, $mform, $summaryoptions, $attachmentoptions);

        // Associate the new blog entry.
        $newentry = array();
        $newentry['userid'] = $USER->id;
        $newentry['blogid'] = $blogentry->id;
        $newentry['courseid'] = $courseblog->course;
        $newentry['courseblogid'] = $courseblog->id;
        $newentry['groupid'] = $currentgroup;
        if (!$DB->get_record('courseblog_entries', $newentry)) {
            $DB->insert_record('courseblog_entries', $newentry);
            // Update completion state
            $completion=new completion_info($course);
            if($completion->is_enabled($cm) && $courseblog->completionposts) {
                $completion->update_state($cm,COMPLETION_COMPLETE);
            }
        }
        redirect(new moodle_url('/mod/courseblog/view.php', array('id' => $id, 'd' => $courseblog->id)));
    }
}

/// Print the page header

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($courseblog->name), 2);
echo $OUTPUT->box(format_module_intro('courseblog', $courseblog, $cm->id), 'generalbox', 'intro');
groups_print_activity_menu($cm, $CFG->wwwroot.'/mod/courseblog/edit.php?d='.$courseblog->id);

/// Print the tabs

$currenttab = 'add';
include('tabs.php');

if (!$new) {
    echo $OUTPUT->single_button(new moodle_url('/mod/courseblog/edit.php', array('d' => $d, 'new' => 1)), get_string('addanewentry'), 'get');
    echo 'Or';
}

// Print the form.
$mform->display();

echo $OUTPUT->footer();
