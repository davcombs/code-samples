<?php

class Controller_Learner extends Controller_MalsTemplate
{
    /**
     * Register new learners or edit their registration information.
     */
    public function action_index()
    {
        $this->template->content = View::factory('learner/registration');
        $this->template->scripts = array('scripts/learner_registration.js', 'scripts/availability.js');
        $this->template->content->countries = Helper_Country::getCountries();
        $this->template->content->payor_individuals = Model_Accounting_PayorIndividual::getAll();
        $this->template->content->payor_organizations = Model_Accounting_PayorOrganization::getAll();

        // User is clearing the form to register a new learner.
        if (isset($_POST['new_learner'])) {
            $this->session->delete('learner_id');
            $this->session->setMessage(array('success' => 'Begin registering new learner'));
            $this->request->redirect('learner');
        } elseif ($_POST) {
            // Store the post values in a session variable to prevent resending values when refreshing or using the back button.
            $this->session->set('post', $_POST);

            // Redirect the user so they don't have to resend post values when refreshing or using the back button.
            $this->request->redirect('learner');
        } else {
            $_POST = $this->session->get('post');
            $this->session->delete('post');

            if ($_POST) {
                // Save the learner to the database.
                $this->saveLearner($_POST);
            }
            else {
                // Get learner data to display.
                $_POST = $this->getLearner();
            }
        }
    }

    /**
     * Create or edit a learner in the database.
     *
     * @param array $learner_data The learner's data to save.
     */
    private function saveLearner($learner_data)
    {
        $errors = array();

        $post = validation::factory($learner_data)->
            rule('date', 'not_empty')->
            rule('date', 'date')->
            rule('date', 'Helper_Rules::date_in_order', array(':value', date('Y-m-d')))->
            rule('first_name', 'max_length', array(':value', '64'))->
            rule('first_name', 'not_empty')->
            rule('last_name', 'max_length', array(':value', '64'))->
            rule('last_name', 'not_empty')->
            rule('address', 'not_empty')->
            rule('city', 'not_empty')->
            rule('zip_code', 'not_empty')->
            rule('zip_code', 'Valid::valid_zip')->
            rule('home_phone', 'phone')->
            rule('work_phone', 'phone')->
            rule('cell_phone', 'phone')->
            rule('emergency_phone', 'phone')->
            rule('email', 'email')->
            rule('gender', 'not_empty')->
            rule('date_of_birth', 'not_empty')->
            rule('date_of_birth', 'date')->
            rule('date_of_birth', 'Helper_Rules::date_in_order', array(':value', date('m/d/Y')))->
            rule('primary_language', 'not_empty')->
            rule('birth_country', 'not_empty')->
            rule('goal', 'not_empty')->
            rule('adult_learning_programs', 'not_empty')->
            rule('availability', 'not_empty')->
            rule('children_under_18', 'digit')->
            rule('mals_center', 'not_empty');

        // Users with a GED goal must also select a session type.
        if (
            (isset($learner_data['goal']['language_arts']) ||
                isset($learner_data['goal']['writing']) ||
                isset($learner_data['goal']['science']) ||
                isset($learner_data['goal']['social_studies']) ||
                isset($learner_data['goal']['math'])) &&
            !isset($learner_data['mini_lab']) &&
            !isset($learner_data['one_on_one'])
        ) {
            $errors['class_type'] = 'Please select whether you wish to be taught through a mini lab, one on one, or both.';
        }

        if ($post->check() && empty($errors)) {
            // No validation errors occurred.
            if (isset($learner_data['learner_id'])) {
                // User is editing an already existing learner.
                $learner = ORM::factory('learner', $learner_data['learner_id']);

                // The learner must already exist.
                if (!$learner->loaded()) {
                    Security::invalid_access();
                }
            } else {
                // User is registering a new learner.
                $learner = new Model_Learner;

                // Get the new learner's unique id.
                $unique_id = new Model_UniqueID(array('id' => '0'));
                $learner->unique_id = $unique_id->unique_id + 1;
                $unique_id->unique_id = $unique_id->unique_id + 1;
                $unique_id->save();
            }

            $learner->date_registered = date('Y-m-d', strtotime($learner_data['date']));
            $learner->title = $learner_data['title'];
            $learner->first_name = $learner_data['first_name'];
            $learner->middle_name = $learner_data['middle_name'];
            $learner->last_name = $learner_data['last_name'];
            $learner->suffix = $learner_data['suffix'];
            $learner->address = $learner_data['address'];
            $learner->apt_suite = $learner_data['apt_suite'];
            $learner->city = $learner_data['city'];
            $learner->state = $learner_data['state'];
            $learner->zip_code = $learner_data['zip_code'];
            $learner->home_phone = $learner_data['home_phone'];
            $learner->work_phone = $learner_data['work_phone'];
            $learner->cell_phone = $learner_data['cell_phone'];
            $learner->email = $learner_data['email'];
            $learner->date_of_birth = date('Y-m-d', strtotime($learner_data['date_of_birth']));
            if (isset($learner_data['literate_native_language'])) {
                $learner->literate_native_language = $learner_data['literate_native_language'];
            }
            if (isset($learner_data['children_under_18'])) {
                $learner->children_under_18 = $learner_data['children_under_18'];
            }
            if (isset($learner_data['government_assistance'])) {
                $learner->government_assistance = $learner_data['government_assistance'];
            }
            $learner->annual_income = !empty($learner_data['annual_income']) ? $learner_data['annual_income'] : null;
            $learner->emergency_name = $learner_data['emergency_name'];
            $learner->emergency_phone = $learner_data['emergency_phone'];
            $learner->emergency_relationship = $learner_data['emergency_relationship'];
            $learner->gender = $learner_data['gender'];
            $learner->hear_about = $learner_data['hear_about'];

            if ($learner_data['hear_about'] == 'Other') {
                $learner->hear_about_text = isset($learner_data['hear_about_text']) ? $learner_data['hear_about_text'] : null;
            } elseif ($learner_data['hear_about'] == 'Referral') {
                $learner->hear_about_referral = isset($learner_data['hear_about_text']) ? $learner_data['hear_about_text'] : null;
            }

            $learner->media_release = isset($learner_data['media_release']) ? intval($learner_data['media_release']) : null;

            if (isset($learner_data['refugee'])) {
                $learner->refugee_country = isset($learner_data['refugee_country']) ? $learner_data['refugee_country'] : null;
            } else {
                $learner->refugee_country = null;
            }

            $learner->center = $learner_data['mals_center'];
            $learner->save();

            if ($learner->saved()) {
                if (isset($learner_data['learner_id'])) {
                    // User is editing an already existing learner.

                    // Get the learner's ethnicity record or create a new one if the learner doesn't have one.
                    $ethnicity = Model_Learner_Ethnicity::getByLearnerID($learner->id);
                    if (!$ethnicity->loaded()) {
                        $ethnicity = new Model_Learner_Ethnicity;
                        $ethnicity->learner_id = $learner->id;
                    }

                    // Get the learner's education record or create a new one if the learner doesn't have one.
                    $education = Model_Learner_Education::getByLearnerID($learner->id);
                    if (!$education->loaded()) {
                        $education = new Model_Learner_Education;
                        $education->learner_id = $learner->id;
                    }

                    // Get the learner's employment record or create a new one if the learner doesn't have one.
                    $employment = Model_Learner_Employment::getByLearnerID($learner->id);
                    if (!$employment->loaded()) {
                        $employment = new Model_Learner_Employment;
                        $employment->learner_id = $learner->id;
                    }
                } else {
                    // User is registering a new learner.
                    $ethnicity = new Model_Learner_Ethnicity;
                    $ethnicity->learner_id = $learner->id;

                    $education = new Model_Learner_Education;
                    $education->learner_id = $learner->id;

                    $employment = new Model_Learner_Employment;
                    $employment->learner_id = $learner->id;
                }

                // Learners may choose not to provide an ethnicity.
                if (isset($learner_data['ethnicity'])) {
                    $ethnicity->african_american = in_array('african_american', $learner_data['ethnicity']) ? 1 : null;
                    $ethnicity->african = in_array('african', $learner_data['ethnicity']) ? 1 : null;
                    $ethnicity->asian = in_array('asian', $learner_data['ethnicity']) ? 1 : null;
                    $ethnicity->hispanic = in_array('hispanic', $learner_data['ethnicity']) ? 1 : null;
                    $ethnicity->native_american = in_array('native_american', $learner_data['ethnicity']) ? 1 : null;
                    $ethnicity->european_caucasian = in_array('european_caucasian', $learner_data['ethnicity']) ? 1 : null;
                    $ethnicity->us_caucasian = in_array('us_caucasian', $learner_data['ethnicity']) ? 1 : null;

                    $ethnicity->other = $learner_data['other_ethnicity'];
                    $ethnicity->primary_language = $learner_data['primary_language'];
                    $ethnicity->birth_country = $learner_data['birth_country'];
                }

                $ethnicity->save();

                if (!$ethnicity->saved()) {
                    $this->session->setMessage(
                        array(
                            'error' => 'DATABASE ERROR: Learner not saved. Please contact ' . Kohana::config('admin.email') . ' for assistance'
                        )
                    );
                }

                // Learners may choose not to provide an education.
                if (isset($learner_data['education'])) {
                    $education->high_school = in_array('high_school', $learner_data['education']) ? 1 : null;
                    $education->ged_hsed = in_array('ged_hsed', $learner_data['education']) ? 1 : null;
                    $education->technical_college = in_array('technical_college', $learner_data['education']) ? 1 : null;
                    $education->university = in_array('university', $learner_data['education']) ? 1 : null;

                    $education->other = $learner_data['other_education'];
                    $education->less_than_highschool = isset($learner_data['less_than_highschool']) ? $learner_data['less_than_highschool'] : null;
                    $education->adult_learning_programs = $learner_data['adult_learning_programs'];
                    $education->adult_learning_location = isset($learner_data['adult_learning_location']) ? $learner_data['adult_learning_location'] : null;
                }

                $education->save();

                if (!$education->saved()) {
                    $this->session->setMessage(
                        array(
                            'error' => 'DATABASE ERROR: Learner not saved. Please contact ' . Kohana::config('admin.email') . ' for assistance'
                        )
                    );
                }

                // Learners may choose not to provide employment.
                if (isset($learner_data['employment'])) {
                    $employment->full_time = in_array('full_time', $learner_data['employment']) ? 1 : null;
                    $employment->part_time = in_array('part_time', $learner_data['employment']) ? 1 : null;
                    $employment->disabled = in_array('disabled', $learner_data['employment']) ? 1 : null;
                    $employment->student = in_array('student', $learner_data['employment']) ? 1 : null;
                    $employment->unemployed = in_array('unemployed', $learner_data['employment']) ? 1 : null;
                    $employment->retired = in_array('retired', $learner_data['employment']) ? 1 : null;
                    $employment->student = in_array('student', $learner_data['employment']) ? 1 : null;
                    $employment->homemaker = in_array('homemaker', $learner_data['employment']) ? 1 : null;
                    $employment->not_seeking_work = in_array('not_seeking_work', $learner_data['employment']) ? 1 : null;
                    $employment->employer = isset($learner_data['employer']) ? $learner_data['employer'] : null;
                }

                $employment->save();

                if (!$employment->saved()) {
                    $this->session->setMessage(
                        array(
                            'error' => 'DATABASE ERROR: Learner not saved. Please contact ' . Kohana::config('admin.email') . ' for assistance'
                        )
                    );
                }

                // Save the learner's availability and create the learner's schedule record.
                if (isset($learner_data['availability'])) {
                    // Delete the learner's previous availability and schedule.
                    $learner_availabilities = Model_Learner_Availability::getAllByLearnerID($learner->id);
                    $learner_schedule = Model_Learner_Schedule::getAllByLearnerID($learner->id);

                    foreach ($learner_availabilities as $availability) {
                        $availability->delete();
                    }

                    foreach ($learner_schedule as $schedule) {
                        $schedule->delete();
                    }


                    foreach ($learner_data['availability'] as $day) {
                        // Number of time slots for each day that was checked.
                        $count = 1;

                        // Times a learner is available.
                        $times_available = array(
                            '8.0' => -1,
                            '8.5' => -1,
                            '9.0' => -1,
                            '9.5' => -1,
                            '10.0' => -1,
                            '10.5' => -1,
                            '11.0' => -1,
                            '11.5' => -1,
                            '12.0' => -1,
                            '12.5' => -1,
                            '13.0' => -1,
                            '13.5' => -1,
                            '14.0' => -1,
                            '14.5' => -1,
                            '15.0' => -1,
                            '15.5' => -1,
                            '16.0' => -1,
                            '16.5' => -1,
                            '17.0' => -1,
                            '17.5' => -1,
                            '18.0' => -1,
                            '18.5' => -1
                        );

                        // If a from and until value were provided
                        while (!empty($learner_data[$day . $count . '_from'])) {
                            $availability = new Model_Learner_Availability;
                            $availability->learner_id = $learner->id;
                            $availability->day = $day;
                            $availability->from_time = $learner_data[$day . $count . '_from'];
                            $availability->until_time = $learner_data[$day . $count . '_until'];
                            $availability->save();

                            // set the time a learner is available
                            for ($i = $availability->from_time; $i < $availability->until_time - 1; $i += 0.5) {
                                $times_available[(string)$i] = 1;
                            }

                            ++$count;
                        }

                        $schedule = new Model_Learner_Schedule;
                        $schedule->day = $day;
                        $schedule->learner_id = $learner->id;

                        // Save the times a learner is available in their schedule record.
                        foreach ($times_available as $key => $value) {
                            if (substr($key, -2) == '.0') {
                                $column = (int)$key;
                            } else {
                                $column = $key;
                            }

                            $schedule->$column = $value;
                        }

                        $schedule->save();
                    } // end foreach ($learner_data['availability'] as $day)
                } // end if (isset($learner_data['availability']))

                // Save the learner's goals.
                if (isset($learner_data['goal'])) {
                    // The user is editing an existing learner.
                    if (isset($learner_data['learner_id'])) {
                        $goals = Model_Learner_Goal::getAllByLearnerID($learner->id);

                        foreach ($goals as $goal) {
                            // Check if the user is deleting an already saved goal.
                            $goal_index = array_search($goal->goal, $learner_data['goal']);
                            if ($goal_index) {
                                unset($learner_data['goal'][$goal_index]);
                            } else {
                                // The user is deleting a goal.

                                // Delete the learner's session association.
                                $session_learner = Model_Session_Learners::getByLearnerGoal($learner->id, $goal->goal);

                                if ($session_learner->loaded()) {
                                    // Update the learner's schedule since the session associated with the goal being deleted is being removed.
                                    $learner_schedule = Model_Learner_Schedule::getByLearnerDay($learner->id, $session_learner->day);

                                    if ($learner_schedule->loaded()) {
                                        if (isset($learner_schedule->$session_learner->start_time)) {
                                            $learner_schedule->$session_learner->start_time = 1;
                                        }
                                    }

                                    $session_learner->delete();
                                }

                                $goal->delete();
                            }
                        }
                    }// end if(isset($learner_data['learner_id']))

                    // Save the learner's goals.
                    foreach ($learner_data['goal'] as $session) {
                        $learner_goal = new Model_Learner_Goal;
                        $learner_goal->learner_id = $learner->id;
                        $learner_goal->goal = $session;

                        if ($session == 'other') {
                            $learner_goal->other_career_readiness = $learner_data['clarify_textbox'];
                        }

                        // The user is saving a learner's GED goals.
                        if ($session == 'language_arts' || $session == 'writing' || $session == 'science' || $session == 'social_studies' || $session == 'math') {
                            if (isset($learner_data['mini_lab']) && isset($learner_data['one_on_one'])) {
                                $learner_goal->session_type = 'both';
                            } elseif (isset($learner_data['mini_lab'])) {
                                $learner_goal->session_type = 'mini_lab';
                            } elseif (isset($learner_data['one_on_one'])) {
                                $learner_goal->session_type = 'one_on_one';
                            }
                        }

                        $learner_goal->save();

                        if (!$learner_goal->saved()) {
                            $this->session->setMessage(
                                array(
                                    'error' => 'DATABASE ERROR: Learner not saved. Please contact ' . Kohana::config('admin.email') . ' for assistance'
                                )
                            );
                            return;
                        }
                    }
                }// if(isset($learner_data['goal']))

                $this->session->setMessage(array('success' => 'Learner registration successfully saved'));
                $this->request->redirect('learner');
            }// end if($learner->saved())

            $this->session->setMessage(
                array(
                    'error' => 'DATABASE ERROR: Learner not saved. Please contact ' . Kohana::config('admin.email') . ' for assistance'
                )
            );
        }// end if ($post->check() && empty($errors))
        else {
            // Validation errors occurred
            $this->template->content->errors = array_merge($post->errors('learner/registration'), $errors);
            $this->template->message = array('error' => 'Please correct all errors that are in red');
        }
    }

    /**
     * Get a learner's data to display on the registration page.
     *
     * @return array $learner_data The learner's data.
     */
    private function getLearner()
    {
        // The learner's id if one was found in action_find_learner().
        $learner_id = $this->session->get('learner_id');

        if (isset($learner_id)) {
            $learner = ORM::factory('learner', $learner_id);

            // The learner must exist in the database.
            if (!$learner->loaded()) {
                Security::invalid_access();
            }

            $this->template->content->learner_id = $learner_id;

            $learner_ethnicity = Model_Learner_Ethnicity::getByLearnerID($learner_id);
            $learner_education = Model_Learner_Education::getByLearnerID($learner_id);
            $learner_employment = Model_Learner_Employment::getByLearnerID($learner_id);
            $learner_goal = Model_Learner_Goal::getAllByLearnerID($learner_id);
            $learner_availability = Model_Learner_Availability::getAllByLearnerID($learner_id);

            if (count($learner_availability)) {
                $availability_array = array();
                $i = 0;
                foreach ($learner_availability as $availability) {
                    $availability_array[$i]['day'] = $availability->day;
                    $availability_array[$i]['from_time'] = $availability->from_time;
                    $availability_array[$i]['until_time'] = $availability->until_time;
                    ++$i;
                }
                $this->template->content->availability = json_encode($availability_array);
            }

            $this->template->content->learner_track = $learner->get_track();

            $learner_data['date'] = date('m/d/Y', strtotime($learner->date_registered));
            $learner_data['title'] = $learner->title;
            $learner_data['first_name'] = $learner->first_name;
            $learner_data['middle_name'] = $learner->middle_name;
            $learner_data['last_name'] = $learner->last_name;
            $learner_data['suffix'] = $learner->suffix;
            $learner_data['address'] = $learner->address;
            $learner_data['apt_suite'] = $learner->apt_suite;
            $learner_data['city'] = $learner->city;
            $learner_data['state'] = $learner->state;
            $learner_data['zip_code'] = $learner->zip_code;
            $learner_data['home_phone'] = Helper_Phone::format($learner->home_phone);
            $learner_data['work_phone'] = Helper_Phone::format($learner->work_phone);
            $learner_data['cell_phone'] = Helper_Phone::format($learner->cell_phone);
            $learner_data['email'] = $learner->email;
            $learner_data['date_of_birth'] = $learner->date_of_birth;
            $learner_data['literate_native_language'] = $learner->literate_native_language;
            $learner_data['children_under_18'] = $learner->children_under_18;
            $learner_data['government_assistance'] = $learner->government_assistance;
            $learner_data['annual_income'] = $learner->annual_income;
            $learner_data['emergency_name'] = $learner->emergency_name;
            $learner_data['emergency_phone'] = Helper_Phone::format($learner->emergency_phone);
            $learner_data['emergency_relationship'] = $learner->emergency_relationship;
            $learner_data['primary_language'] = $learner_ethnicity->primary_language;
            $learner_data['media_release'] = $learner->media_release;
            $learner_data['refugee_country'] = $learner->refugee_country;
            $learner_data['status'] = $learner->inactive;
            $learner_data['mals_center'] = $learner->center;

            if ($learner_ethnicity->african_american == 1) {
                $learner_data['ethnicity'][] = "african_american";
            }
            if ($learner_ethnicity->african == 1) {
                $learner_data['ethnicity'][] = "african";
            }
            if ($learner_ethnicity->asian == 1) {
                $learner_data['ethnicity'][] = "asian";
            }
            if ($learner_ethnicity->hispanic == 1) {
                $learner_data['ethnicity'][] = "hispanic";
            }
            if ($learner_ethnicity->native_american == 1) {
                $learner_data['ethnicity'][] = "native_american";
            }
            if ($learner_ethnicity->european_caucasian == 1) {
                $learner_data['ethnicity'][] = "european_caucasian";
            }
            if ($learner_ethnicity->us_caucasian == 1) {
                $learner_data['ethnicity'][] = "us_caucasian";
            }
            if (!empty($learner_ethnicity->other)) {
                $learner_data['other_ethnicity'] = $learner_ethnicity->other;
            }

            $learner_data['birth_country'] = $learner_ethnicity->birth_country;

            if ($learner->gender == "M") {
                $learner_data['gender'] = "M";
            }
            if ($learner->gender == "F") {
                $learner_data['gender'] = "F";
            }
            if ($learner->gender == "T") {
                $learner_data['gender'] = "T";
            }

            if ($learner_education->high_school == 1) {
                $learner_data['education'][] = "high_school";
            }
            if ($learner_education->ged_hsed == 1) {
                $learner_data['education'][] = "ged_hsed";
            }
            if ($learner_education->technical_college == 1) {
                $learner_data['education'][] = "technical_college";
            }
            if ($learner_education->university == 1) {
                $learner_data['education'][] = "university";
            }
            $learner_data['less_than_highschool'] = $learner_education->less_than_highschool;
            $learner_data['other_education'] = $learner_education->other;
            if ($learner_education->adult_learning_programs == "Y") {
                $learner_data['adult_learning_programs'] = "Y";
            }
            if ($learner_education->adult_learning_programs == "N") {
                $learner_data['adult_learning_programs'] = "N";
            }
            $learner_data['adult_learning_location'] = $learner_education->adult_learning_location;

            $learner_data['hear_about'] = $learner->hear_about;
            if ($learner_data['hear_about'] == 'Other') {
                $learner_data['hear_about_text'] = $learner->hear_about_text;
            } elseif ($learner_data['hear_about'] == 'Referral') {
                $learner_data['hear_about_text'] = $learner->hear_about_referral;
            }

            if ($learner_employment->full_time == 1) {
                $learner_data['employment'][] = "full_time";
            }
            if ($learner_employment->part_time == 1) {
                $learner_data['employment'][] = "part_time";
            }
            if ($learner_employment->disabled == 1) {
                $learner_data['employment'][] = "disabled";
            }
            if ($learner_employment->student == 1) {
                $learner_data['employment'][] = "student";
            }
            if ($learner_employment->unemployed == 1) {
                $learner_data['employment'][] = "unemployed";
            }
            if ($learner_employment->homemaker == 1) {
                $learner_data['employment'][] = "homemaker";
            }
            if ($learner_employment->retired == 1) {
                $learner_data['employment'][] = "retired";
            }
            if ($learner_employment->not_seeking_work == 1) {
                $learner_data['employment'][] = "not_seeking_work";
            }
            $learner_data['employer'] = $learner_employment->employer;

            $availability_array = array();
            $i = 0;
            foreach ($learner_availability as $availability) {
                $availability_array[$i]['day'] = $availability->day;
                $availability_array[$i]['from_time'] = $availability->from_time;
                $availability_array[$i]['until_time'] = $availability->until_time;

                $learner_data['availability'][] = $availability->day;

                ++$i;
            }
            $this->template->content->availability = json_encode($availability_array);


            $learner_data['goal'] = null;
            foreach ($learner_goal as $goal) {
                $learner_data['goal'][] = $goal->goal;

                if (!empty($goal->session_type)) {
                    if ($goal->session_type == 'both') {
                        $learner_data['mini_lab'] = true;
                        $learner_data['one_on_one'] = true;
                    } elseif ($goal->session_type == 'mini_lab') {
                        $learner_data['mini_lab'] = true;
                        $learner_data['one_on_one'] = false;
                    } elseif ($goal->session_type == 'one_on_one') {
                        $learner_data['mini_lab'] = false;
                        $learner_data['one_on_one'] = true;
                    }
                }

                if ($goal->goal == "other") {
                    $learner_data['clarify_textbox'] = $goal->other_career_readiness;
                    $this->template->content->other_readiness = true;
                }
            }

            $learner_data['learner_notes'] = Model_Learner_Note::getByLearnerID($learner_id);
        }// end if (isset($learner_id))
        else {
            $learner_data = null;
        }

        return $learner_data;
    }
} // end Controller_Learner

