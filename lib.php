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
 * Library of interface functions and constants for module courseblog
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the courseblog specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_courseblog
 * @copyright  2011 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** example constant */
//define('NEWMODULE_ULTIMATE_ANSWER', 42);

////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function courseblog_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                    return true;
        case FEATURE_GROUPINGS:                 return true;
        case FEATURE_MOD_INTRO:                 return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:   return true;
        case FEATURE_COMPLETION_HAS_RULES:      return true;
        case FEATURE_GRADE_HAS_GRADE:           return null;
        case FEATURE_GRADE_OUTCOMES:            return null;
        case FEATURE_BACKUP_MOODLE2:            return true;

        default:                        return null;
    }
}

/**
 * Saves a new instance of the courseblog into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $courseblog An object from the form in mod_form.php
 * @param mod_courseblog_mod_form $mform
 * @return int The id of the newly inserted courseblog record
 */
function courseblog_add_instance(stdClass $courseblog, mod_courseblog_mod_form $mform = null) {
    global $DB;

    $courseblog->timecreated = time();

    # You may have to add extra stuff in here #

    return $DB->insert_record('courseblog', $courseblog);
}

/**
 * Updates an instance of the courseblog in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $courseblog An object from the form in mod_form.php
 * @param mod_courseblog_mod_form $mform
 * @return boolean Success/Fail
 */
function courseblog_update_instance(stdClass $courseblog, mod_courseblog_mod_form $mform = null) {
    global $DB;

    $courseblog->timemodified = time();
    $courseblog->id = $courseblog->instance;

    # You may have to add extra stuff in here #

    return $DB->update_record('courseblog', $courseblog);
}

/**
 * Removes an instance of the courseblog from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function courseblog_delete_instance($id) {
    global $DB;

    if (! $courseblog = $DB->get_record('courseblog', array('id' => $id))) {
        return false;
    }

    # Delete any dependent records here #

    $DB->delete_records('courseblog', array('id' => $courseblog->id));

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null
 */
function courseblog_user_outline($course, $user, $mod, $courseblog) {
    global $DB, $CFG;

    // Get user grades.
    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'courseblog', $courseblog->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
        if ($grade->str_grade == '-') {
            $grade = false;
        }
    }

    // Get user posts.
    $params = array(
        'userid' => $user->id,
        'courseblog' => $courseblog->id
    );

    $vsql = "SELECT cbe.id, p.lastmodified
            FROM {courseblog_entries} cbe
                JOIN {post} p ON p.id = cbe.blogid
            WHERE cbe.userid = :userid
                AND cbe.courseblogid = :courseblog
            ORDER BY p.lastmodified ASC";

    $posts = $DB->get_records_sql($vsql, $params);

    $hsql = "SELECT cbe.id, p.lastmodified
            FROM {courseblog_entries} cbe
                JOIN {post} p ON p.id = cbe.blogid
            WHERE cbe.userid = :userid
                AND cbe.courseblogid = :courseblog
                AND p.publishstate = 'draft'
            ORDER BY p.lastmodified ASC";

    $hposts = $DB->get_records_sql($hsql, $params);

    $result = null;

    if (!empty($posts)) {
        $result = new stdClass();
        $result->info = get_string('numposts', 'courseblog', count($posts));

        if (!empty($hposts)) {
            $result->info .= " (".get_string('numhiddenposts', 'courseblog', count($hposts)).")";
        }

        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }

        $result->time = end($posts)->lastmodified;
    } else if ($grade) {
        $result = new stdClass();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;
        // If grade was last modified by the user themselves use date graded. Otherwise use date submitted.
        if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
            $result->time = $grade->dategraded;
        } else {
            $result->time = $grade->datesubmitted;
        }
    }

    return $result;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $courseblog the module instance record
 * @return void, is supposed to echp directly
 */
function courseblog_user_complete($course, $user, $mod, $courseblog) {
    global $DB, $CFG, $OUTPUT, $PAGE;

    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'courseblog', $courseblog->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        if ($grade != '-') {
            echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
            if ($grade->str_feedback) {
                echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
            }
        }
    }

    $context = context_module::instance($mod->id);

    if (!has_capability('moodle/blog:viewdrafts', $context)) {
        $draftsql = " AND p.id NOT IN ( select id from {post} where publishstate = 'draft' AND userid != " . $USER->id .") ";
    } else {
        $draftsql = "";
    }

    // Get user posts.
    $params = array(
        'userid' => $user->id,
        'courseblog' => $courseblog->id
    );

    $vsql = "SELECT cbe.id
        FROM {courseblog_entries} cbe
            JOIN {post} p ON p.id = cbe.blogid
        WHERE cbe.userid = :userid
            AND cbe.courseblogid = :courseblog
            $draftsql
        ORDER BY p.created ASC";

    $posts = $DB->get_records_sql($vsql, $params);

    if (!empty($posts)) {
        require_once($CFG->dirroot . '/blog/locallib.php');
        require_once($CFG->dirroot .'/comment/lib.php');
        comment::init();
        foreach ($posts as $post) {
            $entryrecord = $DB->get_record('courseblog_entries', array('id' => $post->id));
            if ($blog = $DB->get_record('post', array('id' => $entryrecord->blogid))) {
                $blogoutput = $PAGE->get_renderer('blog');
                $blogentry = new blog_entry(null, $blog);

                // Get the required blog entry data to render it.
                $blogentry->prepare_render();
                echo $blogoutput->render($blogentry);
            } else {
                // The associated blog entry was removed elsewhere.
                $DB->delete_records('courseblog_entries', array('id' => $post->id));
            }
        }
    } else {
        echo "<p>".get_string("noposts", "courseblog")."</p>";
    }
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in courseblog activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function courseblog_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link courseblog_print_recent_mod_activity()}.
 *
 * @param array $activities sequentially indexed array of objects with the 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function courseblog_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@see courseblog_get_recent_mod_activity()}

 * @return void
 */
function courseblog_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function courseblog_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function courseblog_get_extra_capabilities() {
    return array();
}


////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function courseblog_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for courseblog file areas
 *
 * @package mod_courseblog
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function courseblog_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the courseblog file areas
 *
 * @package mod_courseblog
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the courseblog's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function courseblog_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding courseblog nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the courseblog module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function courseblog_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
}

/**
 * Extends the settings navigation with the courseblog settings
 *
 * This function is called when the context for the page is a courseblog module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $courseblognode {@link navigation_node}
 */
function courseblog_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $courseblognode=null) {
}

function courseblog_isowner($entryid) {
    global $DB, $USER;

    if ($entry = $DB->get_record('courseblog_entries', array('id' => $entryid))) {
        if ($entry->userid == $USER->id) {
            return true;
        }
    }

    return false;
}

function courseblog_delete_record($deleteid, $courseblog, $courseid, $cmid) {
    global $DB;
    if ($deleterecord = $DB->get_record('courseblog_entries', array('id' => $deleteid))) {
        if ($deleterecord->courseblogid == $courseblog->id) {
            $DB->delete_records('courseblog_entries', array('id'=>$deleterecord->id));

/*                // Trigger an event for deleting this record.
            $event = \mod_data\event\record_deleted::create(array(
                'objectid' => $deleterecord->id,
                'context' => context_module::instance($cmid),
                'courseid' => $courseid,
                'other' => array(
                    'dataid' => $deleterecord->dataid
                )
            ));
            $event->add_record_snapshot('data_records', $deleterecord);
            $event->trigger(); */

            return true;
        }
    }
    return false;
}

function courseblog_comment_validate($comment_param) {
    return true;
}

/**
 * Obtains the automatic completion state for this forum based on any conditions
 * in forum settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function courseblog_get_completion_state($course,$cm,$userid,$type) {
    global $CFG,$DB;

    // Get forum details
    if(!($courseblog=$DB->get_record('courseblog',array('id'=>$cm->instance)))) {
        throw new Exception("Can't find courseblog {$cm->instance}");
    }

    // If completion option is enabled, evaluate it and return true/false 
    if($courseblog->completionposts) {
        return $courseblog->completionposts <= $DB->get_field_sql("
SELECT 
    COUNT(1) 
FROM {courseblog_entries} cbe
    JOIN {post} p ON p.id = cbe.blogid
WHERE cbe.userid = :userid
    AND cbe.courseblogid = :courseblog",
            array('userid'=>$userid,'courseblog'=>$courseblog->id));
    } else {
        // Completion option is not enabled so just return $type
        return $type;
    }
}

