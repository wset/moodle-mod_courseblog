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

// This file to be included so we can assume config.php has already been included.
// We also assume that $user, $course, $currenttab have been set


    if (empty($currenttab) or empty($courseblog) or empty($course)) {
        print_error('cannotcallscript');
    }

    $context = context_module::instance($cm->id);

    $row = array();

    if (isloggedin()) { // just a perf shortcut
        $addstring = empty($editentry) ? get_string('add', 'courseblog') : get_string('editentry', 'courseblog');
        $row[] = new tabobject('allblogs', new moodle_url('/mod/courseblog/view.php', array('id' => $id, 'd' => $courseblog->id, 'mode' => 'allblogs')),
                     get_string('allblogs', 'courseblog'));
        $row[] = new tabobject('myblogs', new moodle_url('/mod/courseblog/view.php', array('id' => $id, 'd' => $courseblog->id, 'mode' => 'myblogs')),
                     get_string('myblogs','courseblog'));
        $row[] = new tabobject('add', new moodle_url('/mod/courseblog/edit.php', array('id' => $id, 'd' => $courseblog->id, 'new' => 1)), $addstring);
    }

// Print out the tabs and continue!
    echo $OUTPUT->tabtree($row, $currenttab);


