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
 * External mod_pumukitlabel functions unit tests
 *
 * @package    mod_pumukitlabel
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * External mod_pumukitlabel functions unit tests
 *
 * @package    mod_pumukitlabel
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */
class mod_pumukitlabel_external_testcase extends externallib_advanced_testcase {

    /**
     * Test test_mod_pumukitlabel_get_pumukitlabels_by_courses
     */
    public function test_mod_pumukitlabel_get_pumukitlabels_by_courses() {
        global $DB;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        $student = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, $studentrole->id);

        // First pumukitlabel.
        $record = new stdClass();
        $record->course = $course1->id;
        $pumukitlabel1 = self::getDataGenerator()->create_module('pumukitlabel', $record);

        // Second pumukitlabel.
        $record = new stdClass();
        $record->course = $course2->id;
        $pumukitlabel2 = self::getDataGenerator()->create_module('pumukitlabel', $record);

        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $student->id, $studentrole->id);

        self::setUser($student);

        $returndescription = mod_pumukitlabel_external::get_pumukitlabels_by_courses_returns();

        // Create what we expect to be returned when querying the two courses.
        $expectedfields = array('id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'introfiles', 'timemodified',
                                'section', 'visible', 'groupmode', 'groupingid');

        // Add expected coursemodule and data.
        $pumukitlabel1->coursemodule = $pumukitlabel1->cmid;
        $pumukitlabel1->introformat = 1;
        $pumukitlabel1->section = 0;
        $pumukitlabel1->visible = true;
        $pumukitlabel1->groupmode = 0;
        $pumukitlabel1->groupingid = 0;
        $pumukitlabel1->introfiles = [];

        $pumukitlabel2->coursemodule = $pumukitlabel2->cmid;
        $pumukitlabel2->introformat = 1;
        $pumukitlabel2->section = 0;
        $pumukitlabel2->visible = true;
        $pumukitlabel2->groupmode = 0;
        $pumukitlabel2->groupingid = 0;
        $pumukitlabel2->introfiles = [];

        foreach ($expectedfields as $field) {
            $expected1[$field] = $pumukitlabel1->{$field};
            $expected2[$field] = $pumukitlabel2->{$field};
        }

        $expectedpumukitlabels = array($expected2, $expected1);

        // Call the external function passing course ids.
        $result = mod_pumukitlabel_external::get_pumukitlabels_by_courses(array($course2->id, $course1->id));
        $result = external_api::clean_returnvalue($returndescription, $result);

        $this->assertEquals($expectedpumukitlabels, $result['pumukitlabels']);
        $this->assertCount(0, $result['warnings']);

        // Call the external function without passing course id.
        $result = mod_pumukitlabel_external::get_pumukitlabels_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedpumukitlabels, $result['pumukitlabels']);
        $this->assertCount(0, $result['warnings']);

        // Add a file to the intro.
        $filename = "file.txt";
        $filerecordinline = array(
            'contextid' => context_module::instance($pumukitlabel2->cmid)->id,
            'component' => 'mod_pumukitlabel',
            'filearea'  => 'intro',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        );
        $fs = get_file_storage();
        $timepost = time();
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');

        $result = mod_pumukitlabel_external::get_pumukitlabels_by_courses(array($course2->id, $course1->id));
        $result = external_api::clean_returnvalue($returndescription, $result);

        $this->assertCount(1, $result['pumukitlabels'][0]['introfiles']);
        $this->assertEquals($filename, $result['pumukitlabels'][0]['introfiles'][0]['filename']);

        // Unenrol user from second course.
        $enrol->unenrol_user($instance2, $student->id);
        array_shift($expectedpumukitlabels);

        // Call the external function without passing course id.
        $result = mod_pumukitlabel_external::get_pumukitlabels_by_courses();
        $result = external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedpumukitlabels, $result['pumukitlabels']);

        // Call for the second course we unenrolled the user from, expected warning.
        $result = mod_pumukitlabel_external::get_pumukitlabels_by_courses(array($course2->id));
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('1', $result['warnings'][0]['warningcode']);
        $this->assertEquals($course2->id, $result['warnings'][0]['itemid']);
    }
}
