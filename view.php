<?php
///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.org                                            //
//                                                                       //
// Copyright (C) 2005 Martin Dougiamas  http://dougiamas.com             //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

    require_once(dirname(__FILE__) . '/../../config.php');
    require_once($CFG->dirroot . '/mod/courseblog/lib.php');
    require_once($CFG->dirroot . '/blog/locallib.php');
    require_once($CFG->libdir . '/rsslib.php');
    require_once($CFG->libdir . '/completionlib.php');
    require_once($CFG->dirroot .'/comment/lib.php');

/// One of these is necessary!
    $id = optional_param('id', 0, PARAM_INT);  // course module id
    $d = optional_param('d', 0, PARAM_INT);   // courseblog id
    $search = optional_param('s', '', PARAM_CLEAN);
    $rid = optional_param('rid', 0, PARAM_INT);    //record id
    $mode = optional_param('mode', 'allblogs', PARAM_ALPHA);    // Force the browse mode  ('single')
    $filter = optional_param('filter', 0, PARAM_BOOL);
    $action = optional_param('action', '', PARAM_CLEAN);
    $paging = optional_param('paging', 10, PARAM_INT);
    // search filter will only be applied when $filter is true

    $edit = optional_param('edit', -1, PARAM_BOOL);
    $page = optional_param('page', 0, PARAM_INT);

    comment::init();

    if ($id) {
        if (! $cm = get_coursemodule_from_id('courseblog', $id)) {
            print_error('invalidcoursemodule');
        }
        if (! $course = $DB->get_record('course', array('id'=>$cm->course))) {
            print_error('coursemisconf');
        }
        if (! $courseblog = $DB->get_record('courseblog', array('id'=>$cm->instance))) {
            print_error('invalidcoursemodule');
        }
        $record = NULL;

    } else {   // We must have $d
        if (! $courseblog = $DB->get_record('courseblog', array('id'=>$d))) {
            print_error('invalidid', 'courseblog');
        }
        if (! $course = $DB->get_record('course', array('id'=>$courseblog->course))) {
            print_error('coursemisconf');
        }
        if (! $cm = get_coursemodule_from_instance('courseblog', $courseblog->id, $course->id)) {
            print_error('invalidcoursemodule');
        }
        $record = NULL;
    }

    require_course_login($course, true, $cm);

    $context = context_module::instance($cm->id);
    require_capability('mod/courseblog:viewentry', $context);

/// Check further parameters that set browsing preferences
    if (!isset($SESSION->courseblogprefs)) {
        $SESSION->courseblogprefs = array();
    }
    if (!isset($SESSION->courseblogprefs[$courseblog->id])) {
        $SESSION->courseblogprefs[$courseblog->id] = array();
        $SESSION->courseblogprefs[$courseblog->id]['search'] = '';
        $SESSION->courseblogprefs[$courseblog->id]['search_array'] = array();
        $SESSION->courseblogprefs[$courseblog->id]['sort'] = 1;
        $SESSION->courseblogprefs[$courseblog->id]['advanced'] = 0;
        $SESSION->courseblogprefs[$courseblog->id]['order'] = 'ASC';
    }

    $sort = optional_param('sort', $SESSION->courseblogprefs[$courseblog->id]['sort'], PARAM_INT);
    $SESSION->courseblogprefs[$courseblog->id]['sort'] = $sort;       // Make it sticky

    $order = (optional_param('order', $SESSION->courseblogprefs[$courseblog->id]['order'], PARAM_ALPHA) == 'ASC') ? 'ASC': 'DESC';
    $SESSION->courseblogprefs[$courseblog->id]['order'] = $order;     // Make it sticky


    $oldperpage = get_user_preferences('courseblog_perpage_'.$courseblog->id, 10);
    $perpage = optional_param('perpage', $oldperpage, PARAM_INT);

    if ($perpage < 2) {
        $perpage = 2;
    }
    if ($perpage != $oldperpage) {
        set_user_preference('courseblog_perpage_'.$courseblog->id, $perpage);
    }

    $params = array(
        'contextid' => $context->id,
        'objectid' => $courseblog->id
    );
    /*$event = \mod_courseblog\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('courseblog', $courseblog);
    $event->trigger();*/

    $urlparams = array('d' => $courseblog->id);
    if ($record) {
        $urlparams['rid'] = $record->id;
    }
    if ($page) {
        $urlparams['page'] = $page;
    }
    if ($mode) {
        $urlparams['mode'] = $mode;
    }
    if ($filter) {
        $urlparams['filter'] = $filter;
    }
// Initialize $PAGE, compute blocks
    $PAGE->set_url('/mod/courseblog/view.php', $urlparams);

    if (($edit != -1) and $PAGE->user_allowed_editing()) {
        $USER->editing = $edit;
    }

    $courseshortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

/// Print the page header
    $title = $courseshortname.': ' . format_string($courseblog->name);

    if ($PAGE->user_allowed_editing()) {
        // Change URL parameter and block display string value depending on whether editing is enabled or not
        if ($PAGE->user_is_editing()) {
            $urlediting = 'off';
            $strediting = get_string('blockseditoff');
        } else {
            $urlediting = 'on';
            $strediting = get_string('blocksediton');
        }
        $url = new moodle_url($CFG->wwwroot.'/mod/courseblog/view.php', array('id' => $cm->id, 'edit' => $urlediting));
        $PAGE->set_button($OUTPUT->single_button($url, $strediting));
    }

    $PAGE->set_title($title);
    $PAGE->set_heading($course->fullname);

    echo $OUTPUT->header();

    // Check to see if groups are being used here.
    // We need the most up to date current group value. Make sure it is updated at this point.
    $currentgroup = groups_get_activity_group($cm, true);
    $groupmode = groups_get_activity_groupmode($cm);
    $canmanageentries = has_capability('mod/courseblog:manageentries', $context);
    // If a student is not part of a group and seperate groups is enabled, we don't
    // want them seeing all records.
    if ($currentgroup == 0 && $groupmode == 1 && !$canmanageentries) {
        $canviewallrecords = false;
    } else {
        $canviewallrecords = true;
    }

    // detect entries not approved yet and show hint instead of not found error
    if ($record and $courseblog->approval and !$record->approved and $record->userid != $USER->id and !$canmanageentries) {
        if (!$currentgroup or $record->groupid == $currentgroup or $record->groupid == 0) {
            print_error('notapproved', 'courseblog');
        }
    }

    echo $OUTPUT->heading(format_string($courseblog->name), 2);

    if ($courseblog->intro and empty($page) and empty($record)) {
        $options = new stdClass();
        $options->noclean = true;
    }
    echo $OUTPUT->box(format_module_intro('courseblog', $courseblog, $cm->id), 'generalbox', 'intro');

    $returnurl = $CFG->wwwroot . '/mod/courseblog/view.php?d='.$courseblog->id.'&amp;search='.s($search).'&amp;sort='.s($sort).'&amp;order='.s($order).'&amp;';
    groups_print_activity_menu($cm, $returnurl);

/// Delete any requested records

    if ($action == 'delete' && confirm_sesskey() && ($canmanageentries or courseblog_isowner($courseblog->id))) {
        if ($confirm = optional_param('confirm',0,PARAM_INT)) {
            if (courseblog_delete_record($rid, $courseblog, $course->id, $cm->id)) {
                echo $OUTPUT->notification(get_string('recorddeleted','courseblog'), 'notifysuccess');
                $action = '';
            }
        } else {   // Print a confirmation page
            $allnamefields = user_picture::fields('u');
            // Remove the id from the string. This already exists in the sql statement.
            $allnamefields = str_replace('u.id,', '', $allnamefields);
            $dbparams = array($rid);
            if ($deleterecord = $DB->get_record_sql("SELECT cbe.*
                                                       FROM {courseblog_entries} cbe
                                                       WHERE cbe.id = ?", $dbparams, MUST_EXIST)) { // Need to check this is valid.
                if ($deleterecord->courseblogid == $courseblog->id) {                       // Must be from this courseblogbase
                    $deletebutton = new single_button(new moodle_url('/mod/courseblog/view.php', array('d' => $courseblog->id,
                                                                                                       'rid' => $rid,
                                                                                                       'confirm' => 1,
                                                                                                       'action' => 'delete',
                                                                                                       'sesskey' => sesskey(),
                                                                                                       'mode' => $mode)), get_string('delete'), 'get');
                    echo $OUTPUT->confirm(get_string('confirmdeleterecord','courseblog'),
                            $deletebutton, 'view.php?d='.$courseblog->id);

                    $records[] = $deleterecord;

                    echo $OUTPUT->footer();
                    exit;
                }
            }
        }
    }

//if courseblog activity closed dont let students in
$showactivity = true;
if (!$canmanageentries) {
    $timenow = time();
    if (!empty($courseblog->timeavailablefrom) && $courseblog->timeavailablefrom > $timenow) {
        echo $OUTPUT->notification(get_string('notopenyet', 'courseblog', userdate($courseblog->timeavailablefrom)));
        $showactivity = false;
    } else if (!empty($courseblog->timeavailableto) && $timenow > $courseblog->timeavailableto) {
        echo $OUTPUT->notification(get_string('expired', 'courseblog', userdate($courseblog->timeavailableto)));
        $showactivity = false;
    }
}

if ($showactivity) {
    // Print the tabs
    if ($record or $mode == 'edit') {
        $currenttab = 'edit';
    } elseif($mode == 'myblogs') {
        $currenttab = 'myblogs';
    } else {
        $currenttab = 'allblogs';
    }
    include('tabs.php');

    if ($currentgroup) {
        $groupselect = " AND (cbe.groupid = :currentgroup OR cbe.groupid = 0)";
        $params['currentgroup'] = $currentgroup;
        $initialparams['currentgroup'] = $params['currentgroup'];
    } else {
        if ($canviewallrecords) {
            $groupselect = ' ';
        } else {
            // If separate groups are enabled and the user isn't in a group or
            // a teacher, manager, admin etc, then just show them entries for 'All participants'.
            $groupselect = " AND r.groupid = 0";
        }
    }
    if ($action != 'view') {
        $entrysql        = '';
        $namefields = user_picture::fields('u');
        // Remove the id from the string. This already exists in the sql statement.
        $namefields = str_replace('u.id,', '', $namefields);
    
    /*    $sortcontent = $DB->sql_compare_text('p.' . $sortfield->get_sort_field());
        $sortcontentfull = $sortfield->get_sort_sql($sortcontent);
    */
        if (!has_capability('moodle/blog:viewdrafts', $context)) {
            $draftsql = " AND p.id NOT IN ( select id from {post} where publishstate = 'draft' AND userid != " . $USER->id .") "; 
        } else {
            $draftsql = "";
        }
        $what = ' cbe.id, p.subject, p.summary, p.content, p.format, p.summaryformat, p.attachment, cbe.userid, ' . $namefields . ',
                ' . 'p.subject' . ' AS sortorder ';
        $count = ' COUNT(DISTINCT cbe.id) ';
        $tables = '{post} p, {courseblog_entries} cbe, {user} u ';
        $where =  'WHERE p.id = cbe.blogid
                     AND cbe.courseblogid = :courseblogid
                     AND cbe.userid = u.id
                     ' . $draftsql;
        $params['courseblogid'] = $courseblog->id;
        $params['sort'] = $sort;
        $sortorder = ' ORDER BY cbe.id DESC ';
        $searchselect = '';
    
        // If requiredentries is not reached, only show current user's entries
        if ($mode == 'myblogs') {
            $where .= ' AND u.id = :myid2';
            $entrysql = ' AND cbe.userid = :myid3';
            $params['myid2'] = $USER->id;
            $initialparams['myid3'] = $params['myid2'];
        }
        $i = 0;
    
        if (!empty($search)) {
            $searchselect = " AND (".$DB->sql_like('p.content', ':search1', false)." OR ".$DB->sql_like('u.firstname', ':search2', false)." OR ".$DB->sql_like('u.lastname', ':search3', false)." ) ";
            $params['search1'] = "%$search%";
            $params['search2'] = "%$search%";
            $params['search3'] = "%$search%";
        } else {
            $searchselect = ' ';
        }
    
    /// To actually fetch the records
    
        $fromsql    = "FROM $tables $where $groupselect $searchselect";
    
        // Provide initial sql statements and parameters to reduce the number of total records.
        $initialselect = $groupselect . $entrysql;
    
        $recordids = array(); //courseblog_get_all_recordids($courseblog->id, $initialselect, $initialparams);
        $newrecordids = array(); //courseblog_get_advance_search_ids($recordids, $search_array, $courseblog->id);
        $totalcount = count($newrecordids);
        $selectcourseblog = $where . $groupselect;
    
        $sqlselect  = "SELECT $what $fromsql $sortorder";
    
        /// Work out the paging numbers and counts
        if (empty($searchselect)) {
            $maxcount = $totalcount;
        } else {
            $maxcount = count($recordids);
        }
    
    /// Get the actual records
        if (!$records = $DB->get_records_sql($sqlselect, $params, $page * $perpage, $perpage)) {
            // Nothing to show!
            if ($record) {         // Something was requested so try to show that at least (bug 5132)
                if ($canmanageentries || (isloggedin() && $record->userid == $USER->id)) {
                    if (!$currentgroup || $record->groupid == $currentgroup || $record->groupid == 0) {
                        // OK, we can show this one
                        $records = array($record->id => $record);
                        $totalcount = 1;
                    }
                }
            }
        }
        if (empty($records)) {
            if ($maxcount){
                $a = new stdClass();
                $a->max = $maxcount;
                $a->reseturl = "view.php?id=$cm->id&amp;mode=$mode&amp;search=&amp;advanced=0";
                echo $OUTPUT->notification(get_string('foundnorecords','courseblog', $a));
            } else {
                echo $OUTPUT->notification(get_string('norecords','courseblog'));
            }
    
        } else {
            //  We have some records to print.
            $url = new moodle_url('/mod/courseblog/view.php', array('id' => $id, 'd' => $courseblog->id, 'sesskey' => sesskey()));
            echo html_writer::start_tag('form', array('action' => $url, 'method' => 'post'));
    
            if ($maxcount != $totalcount) {
                $a = new stdClass();
                $a->num = $totalcount;
                $a->max = $maxcount;
                $a->reseturl = "view.php?id=$cm->id&amp;mode=$mode&amp;search=&amp;advanced=0";
                echo $OUTPUT->notification(get_string('foundrecords', 'courseblog', $a), 'notifysuccess');
            }
    
            $baseurl = 'view.php?d='.$courseblog->id.'&amp;';
            //send the advanced flag through the URL so it is remembered while paging.
            if (!empty($search)) {
                $baseurl .= 'filter=1&amp;';
            }
            //pass variable to allow determining whether or not we are paging through results.
            $baseurl .= 'paging='.$paging.'&amp;';
    
            echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $baseurl);
            if ($mode == 'myblogs') {
                foreach ($records as $record) {
                    $entryrecord = $DB->get_record('courseblog_entries', array('id' => $record->id));
                    if ($blog = $DB->get_record('post', array('id' => $entryrecord->blogid))) {
                        $blogoutput = $PAGE->get_renderer('blog');
                        $blogentry = new blog_entry(null, $blog);
        
                        // Get the required blog entry data to render it.
                        $blogentry->prepare_render();
                        echo $blogoutput->render($blogentry);
                    } else {
                        // The associated blog entry was removed elsewhere.
                        $DB->delete_records('courseblog_entries', array('id' => $record->id));
                    }
                }
                echo html_writer::end_tag('form');
                echo $OUTPUT->single_button(new moodle_url('/blog/index.php',
                                                           array('courseid' => $COURSE->id,
                                                                 'userid' => $USER->id)),
                                                           get_string('viewalluserentries', 'mod_courseblog'));
            } else if ($mode == 'allblogs') {
                foreach ($records as $record) {
                    $entryrecord = $DB->get_record('courseblog_entries', array('id' => $record->id));
                    if ($blog = $DB->get_record('post', array('id' => $entryrecord->blogid))) {
                        $blogoutput = $PAGE->get_renderer('blog');
                        $blogentry = new blog_entry(null, $blog);
        
                        // Get the required blog entry data to render it.
                        $blogentry->prepare_render();
                        echo $blogoutput->render($blogentry);
                    } else {
                        // The associated blog entry was removed elsewhere.
                        $DB->delete_records('courseblog_entries', array('id' => $record->id));
                    }
                }
                echo html_writer::end_tag('form');
                echo $OUTPUT->single_button(new moodle_url('/blog/index.php',
                                                           array('courseid' => $COURSE->id)),
                                                           get_string('viewallcourseentries', 'mod_courseblog'));
            }
        }
    } else {
        // We are showing the blog entry.
        if ($record = $DB->get_record('courseblog_entries', array('id' => $rid))) {
            if ($blog = $DB->get_record('post', array('id' => $record->blogid))) {
                $blogoutput = $PAGE->get_renderer('blog');
                $blogentry = new blog_entry(null, $blog);

                // Get the required blog entry data to render it.
                $blogentry->prepare_render();
                echo $blogoutput->render($blogentry);
            }
            echo $OUTPUT->single_button(new moodle_url('/mod/courseblog/view.php', array('id' => $id, 'd' => $courseblog->id, 'mode' => $currenttab)), get_string("back"), 'get');
        }
    }
}

echo $OUTPUT->footer();
