# Restriction by single quiz question #

[![Build Status](https://github.com/timhunt/moodle-availability_quizquestion/workflows/Moodle%20plugin%20CI/badge.svg?branch=main)](https://github.com/timhunt/moodle-availability_quizquestion/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Amain)
[![PHP Support](https://img.shields.io/badge/php-7.3--8.2-blue)](https://github.com/timhunt/moodle-availability_quizquestion/actions)
[![Moodle Support](https://img.shields.io/badge/Moodle-4.0--4.3-orange)](https://github.com/timhunt/moodle-availability_quizquestion/actions)
[![License GPL-3.0](https://img.shields.io/github/license/timhunt/moodle-availability_quizquestion?color=lightgrey)](https://github.com/timhunt/moodle-availability_quizquestion/blob/main/LICENSE)
[![GitHub contributors](https://img.shields.io/github/contributors/timhunt/moodle-availability_quizquestion)](https://github.com/timhunt/moodle-availability_quizquestion/graphs/contributors)

This is a Moodle conditional availability rule, which makes it possible
to show or hide another resource, based on the state of one particular question
in one quiz. So, if you want show a particular help document to students
who got Question 2 in the quiz wrong, well this plugin lets you do that.

At the moment, this only supports non-random questions, but it may be possible
to extend it in the future.


## To install ##

Install this plugin from https://moodle.org/plugins/availability_quizquestion.

Alternatively you can install using git. Run these commands in the root of your
Moodle site:

    git clone https://github.com/timhunt/moodle-availability_quizquestion.git availability/condition/quizquestion
    echo '/availability/condition/quizquestion/' >> .git/info/exclude

Then visit Admin -> Notifications to complete the installation.


## To use ##

To use this plugin, the 'Enable restricted access' setting must be switched on
for your Moodle site under 'Administration -> Advanced features'.

Then, when you are creating or editing an activity a course, there will be a new
possibility in the 'Restrict access' section of the form. When you click
'Add restriction ...', if there are any quizzes in the course, then you
will see a 'Quiz question' button. You then select a quiz, and one of the questions
in the quiz, and a required state (for example 'Incorrect'). Then, the activity
you are editing will only be available to students where, in their most recent
completed attempt, that question is in that state.

For more information, see the Moodle documentation about
[Restrict access settings](https://docs.moodle.org/en/Restrict_access_settings).


## Credits ##

This plugin was created by Tim Hunt, Shamim Rezaie, Benjamin Schröder, Martin Hanusch, Thomas Lattner
and Alex Keiller at #MootDACH 2020 Dev Camp.


## License ##

2020 Tim Hunt, Shamim Rezaie, Benjamin Schröder, Martin Hanusch, Thomas Lattner, Alex Keiller

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.
