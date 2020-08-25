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
 * Restriction by single quiz question condition main class.
 *
 * @package availability_quizquestion
 * @copyright 2020 Tim Hunt, Shamim Rezaie, Benjamin Schröder, Benjamin Schröder, Thomas Lattner, Alex Keiller
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_quizquestion;

defined('MOODLE_INTERNAL') || die();

/**
 * Restriction by single quiz question condition main class.
 */
class condition extends \core_availability\condition {
    /** @var array these are the types of state we recognise. */
    const STATES_USED = ['gradedright', 'gradedpartial', 'gradedwrong'];

    /** @var int the id of the quiz this depends on. */
    protected $quizid;

    /** @var int the id of the question in the quiz that this depends on. */
    protected $questionid;

    /** @var \question_state the state the target question must be in. */
    protected $requiredstate;

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode
     * @throws \coding_exception If invalid data structure.
     */
    public function __construct($structure) {

        if (isset($structure->quizid) && is_int($structure->quizid)) {
            $this->quizid = $structure->quizid;
        } else {
            throw new \coding_exception('Invalid quizid for quizquestion condition');
        }

        if (isset($structure->questionid) && is_int($structure->questionid)) {
            $this->questionid = $structure->questionid;
        } else {
            throw new \coding_exception('Invalid questionid for quizquestion condition');
        }

        if (isset($structure->requiredstate)) {
            $state = \question_state::get($structure->requiredstate);
            if ($state && in_array((string) $state, self::STATES_USED)) {
                $this->requiredstate = $state;
            }
        }
        if (!isset($this->requiredstate)) {
            throw new \coding_exception('Invalid requiredstate for quizquestion condition');
        }

    }

    public function save() {
        return self::get_json($this->quizid, $this->questionid, $this->requiredstate);
    }

    public static function get_json(int $quizid, int $questionid,
            \question_state $requiredstate): \stdClass {
        return (object)[
            'type' => 'quizquestion',
            'quizid' => $quizid,
            'questionid' => $questionid,
            'requiredstate' => (string) $requiredstate
        ];
    }

    protected function get_debug_string() {
        return " quiz:#{$this->quizid}, question:#{$this->questionid}, {$this->requiredstate}";
    }

    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {

        return $this->requirements_fullfilled($userid);
    }

    /**
     * Gets the question result
     *
     * @param int \userid
     * @param int course
     * @return bool
     */
    protected function requirements_fullfilled($userid) {

        $attempts = quiz_get_user_attempts($this->quizid, $userid, 'finished', true);

        if (count($attempts) > 0) {

            $attemptobj = \quiz_attempt::create(end($attempts)->id);

            foreach ($attemptobj->get_slots() as $slot) {

                $qa = $attemptobj->get_question_attempt($slot);

                if ($qa->get_question()->id == $this->questionid) {

                    $result;
                    if ($qa->get_state() == question_state::$gradedright) {
                        $result = "gradedright";
                    } else if ($qa->get_state() == question_state::$gradedpartial) {
                        $result = "gradedpartial";
                    } else if ($qa->get_state() == question_state::$gradedwrong) {
                        $result = "gradedwrong";
                    }

                    return $result == $this->requiredstate;
                }
            }
        }

        return false;
    }

    public function get_description($full, $not, \core_availability\info $info) {

        if (!isset($modinfo->instances['quiz'][$this->$quizid])) {
            return '';
        }

        // Todo: Retrieve quiz / question data

        return get_string('requires_quizquestion', 'availibility_quizquestion',
                ['quizid' => $this->quizid, 'questionid' => $this->questionid, 'requiredstate' => $this->requiredstate]);

    }

    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name): bool {
        global $DB;

        // Recode question id.
        $questionidchanged = false;
        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'question', $this->questionid);
        if ($rec && $rec->newitemid) {
            // New question id found.
            $this->questionid = (int) $rec->newitemid;
            $questionidchanged = true;
        }
        // If we don't find the new questionid, it is not ideal, but for
        // now do nothing. The check below will probably generate a warning
        // about the situation.

        // Recode quiz id.
        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'quiz', $this->quizid);
        if ($rec && $rec->newitemid) {
            // New quiz id found.
            $this->quizid = (int) $rec->newitemid;
            return true;
        }

        // If we are on the same course (e.g. duplicate) then we can just
        // use the existing one.
        if ($DB->record_exists('quiz',
                ['id' => $this->quizid, 'course' => $courseid])) {
            return $questionidchanged;
        }

        // Otherwise it's a warning.
        $this->quizid = 0;
        $logger->process('Restored item (' . $name .
                ') has availability condition on module that was not restored',
                \backup::LOG_WARNING);
        return $questionidchanged;
    }

    public function update_dependency_id($table, $oldid, $newid) {
        if ($table === 'groupings' && (int)$this->groupingid === (int)$oldid) {
            $this->groupingid = $newid;
            return true;
        } else {
            return false;
        }
    }
}
