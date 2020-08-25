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
 * Restriction by single quiz question unit tests for the condition class.
 *
 * @package availability_quizquestion
 * @copyright 2020 Tim Hunt, Shamim Rezaie, Benjamin Schröder, Benjamin Schröder, Thomas Lattner, Alex Keiller
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_quizquestion;
defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the condition class.
 */
class condition_testcase extends \advanced_testcase {
    /**
     * Load required classes.
     */
    public function setUp() {
        // Load the mock info class so that it can be used.
        global $CFG;
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
    }

    public function test_constructor_working_case() {
        $cond = new condition((object) [
                'quizid' => 123, 'questionid' => 456, 'requiredstate' => 'gradedwrong']);
        $this->assertEquals('{quizquestion: quiz:#123, question:#456, gradedwrong}', (string) $cond);
    }

    public function test_constructor_invalid_quizid() {
        $this->expectExceptionMessage('Invalid quizid for quizquestion condition');
        new condition((object) [
                'quizid' => 'wrong', 'questionid' => 456, 'requiredstate' => 'gradedwrong']);
    }

    public function test_constructor_invalid_questionid() {
        $this->expectExceptionMessage('Invalid questionid for quizquestion condition');
        new condition((object) [
                'quizid' => 123, 'questionid' => 'wrong', 'requiredstate' => 'gradedwrong']);
    }

    public function test_constructor_invalid_state() {
        $this->expectExceptionMessage('Invalid requiredstate for quizquestion condition');
        new condition((object) [
                'quizid' => 123, 'questionid' => 456, 'requiredstate' => 'todo']);
    }

    public function test_save() {
        $structure = (object) ['quizid' => 123, 'questionid' => 456, 'requiredstate' => 'gradedwrong'];
        $cond = new condition($structure);
        $structure->type = 'quizquestion';
        $this->assertEquals($structure, $cond->save());
    }

    public function xtest_usage() {
        global $CFG, $USER;
        $this->resetAfterTest();
        $CFG->enableavailability = true;

        // Make a test course and user.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id);
        $info = new \core_availability\mock_info($course, $user->id);

        // Create a quiz with a question.
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(
                ['contextid' => $context->id]);
        $question = $questiongenerator->create_question('multichoice', null,
                ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);

        // Do test (user has not attempted the quiz yet).
        $cond = new condition((object) [
                'quizid' => (int) $quiz->id, 'questionid' => (int) $question->id,
                'state' => (string) \question_state::$gradedwrong]);

        // Check if available (when not available).
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $information = $cond->get_description(false, false, $info);
        $this->assertRegExp('~You belong to.*G1!~', $information);
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));

        // Add user to groups and refresh cache.
        groups_add_member($group1, $user);
        groups_add_member($group2, $user);
        get_fast_modinfo($course->id, 0, true);

        // Recheck.
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $information = $cond->get_description(false, true, $info);
        $this->assertRegExp('~do not belong to.*G1!~', $information);

        // Check group 2 works also.
        $cond = new condition((object)array('id' => (int)$group2->id));
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));

        // What about an 'any group' condition?
        $cond = new condition((object)array());
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $information = $cond->get_description(false, true, $info);
        $this->assertRegExp('~do not belong to any~', $information);

        // Admin user doesn't belong to a group, but they can access it
        // either way (positive or NOT).
        $this->setAdminUser();
        $this->assertTrue($cond->is_available(false, $info, true, $USER->id));
        $this->assertTrue($cond->is_available(true, $info, true, $USER->id));

        // Group that doesn't exist uses 'missing' text.
        $cond = new condition((object)array('id' => $group2->id + 1000));
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $information = $cond->get_description(false, false, $info);
        $this->assertRegExp('~You belong to.*\(Missing group\)~', $information);
    }

    /**
     * Tests the filter_users (bulk checking) function. Also tests the SQL
     * variant get_user_list_sql.
     */
    public function xtest_filter_users() {
        // TODO.
        global $DB;
        $this->resetAfterTest();

        // Erase static cache before test.
        condition::wipe_static_cache();

        // Make a test course and some users.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, $roleids['editingteacher']);
        $allusers = array($teacher->id => $teacher);
        $students = array();
        for ($i = 0; $i < 3; $i++) {
            $student = $generator->create_user();
            $students[$i] = $student;
            $generator->enrol_user($student->id, $course->id, $roleids['student']);
            $allusers[$student->id] = $student;
        }
        $info = new \core_availability\mock_info($course);

        // Make test groups.
        $group1 = $generator->create_group(array('courseid' => $course->id));
        $group2 = $generator->create_group(array('courseid' => $course->id));

        // Assign students to groups as follows (teacher is not in a group):
        // 0: no groups.
        // 1: in group 1.
        // 2: in group 2.
        groups_add_member($group1, $students[1]);
        groups_add_member($group2, $students[2]);

        // Test 'any group' condition.
        $checker = new \core_availability\capability_checker($info->get_context());
        $cond = new condition((object)array());
        $result = array_keys($cond->filter_user_list($allusers, false, $info, $checker));
        ksort($result);
        $expected = array($teacher->id, $students[1]->id, $students[2]->id);
        $this->assertEquals($expected, $result);

        // Test it with get_user_list_sql.
        list ($sql, $params) = $cond->get_user_list_sql(false, $info, true);
        $result = $DB->get_fieldset_sql($sql, $params);
        sort($result);
        $this->assertEquals($expected, $result);

        // Test NOT version (note that teacher can still access because AAG works
        // both ways).
        $result = array_keys($cond->filter_user_list($allusers, true, $info, $checker));
        ksort($result);
        $expected = array($teacher->id, $students[0]->id);
        $this->assertEquals($expected, $result);

        // Test with get_user_list_sql.
        list ($sql, $params) = $cond->get_user_list_sql(true, $info, true);
        $result = $DB->get_fieldset_sql($sql, $params);
        sort($result);
        $this->assertEquals($expected, $result);

        // Test specific group.
        $cond = new condition((object)array('id' => (int)$group1->id));
        $result = array_keys($cond->filter_user_list($allusers, false, $info, $checker));
        ksort($result);
        $expected = array($teacher->id, $students[1]->id);
        $this->assertEquals($expected, $result);

        list ($sql, $params) = $cond->get_user_list_sql(false, $info, true);
        $result = $DB->get_fieldset_sql($sql, $params);
        sort($result);
        $this->assertEquals($expected, $result);

        $result = array_keys($cond->filter_user_list($allusers, true, $info, $checker));
        ksort($result);
        $expected = array($teacher->id, $students[0]->id, $students[2]->id);
        $this->assertEquals($expected, $result);

        list ($sql, $params) = $cond->get_user_list_sql(true, $info, true);
        $result = $DB->get_fieldset_sql($sql, $params);
        sort($result);
        $this->assertEquals($expected, $result);
    }
}
