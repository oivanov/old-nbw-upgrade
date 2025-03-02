<?php

namespace Drupal\nbw_users_registration\Plugin\WebformHandler;

use Drupal\Component\Utility\ToStringTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\webform_user_registration\Plugin\WebformHandler\UserRegistrationWebformHandler;
use Symfony\Component\Validator\Constraints\IsNull;

/**
 * Webform handler.
 *
 * @WebformHandler(
 *   id = "nbw_users_registration",
 *   label = @Translation("NBW Users Registration"),
 *   category = @Translation("User"),
 *   description = @Translation("Creates NBW user accounts based on Webform submission values."), cardinality =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission =
 *   \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class NbwUsersRegistrationWebformHandler extends UserRegistrationWebformHandler {

  /**
   * The user Account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $userAccount;

  private $form;

  /**
   * The entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->entityFieldManager = $container->get('entity_field.manager');
    $plugin->languageManager = $container->get('language_manager');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    // Get the mapping between webform elements and user entity properties or
    // fields.
    $user_field_mapping = $form_state->getValue('user_field_mapping', []);

    // Ensure we have a valid mapping for e-mail and username if we are creating
    // new users.
    $create_user_enabled = $form_state->getValue(['create_user', 'enabled'], FALSE);
    if ($create_user_enabled) {
      // User Account creation requires at least a unique e-mail address.
      // Assert we have a webform element as the source for a user e-mail
      // address.
      if (!in_array('mail', $user_field_mapping)) {
        $form_state->setErrorByName('user_field_mapping', $this->t(
          'User creation requires at least a source for e-mail address'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    WebformHandlerBase::validateForm($form, $form_state, $webform_submission);

    /** @var \Drupal\user\UserInterface $account */
    $account = NULL;

    // Get the user data from the webform.
    $user_data = $this->getWebformUserData($webform_submission);

    // Skip further validation if no user data is present.
    if (empty($user_data)) {
      return;
    }

    if ($this->configuration['create_user']['enabled']) {

      $request = \Drupal::request();
      $session = $request->getSession();

      // Generate a unique dummy email function
      $generateDummyEmail = function () {
        return "new_youth_" . time() . "@neighborhoodbikeworks.org";
      };

      // Check if email exists
      $existingUser = !empty($user_data['mail']) ? user_load_by_mail($user_data['mail']) : null;

      if ($existingUser) {
        if ($this->handler_id === 'nbw_guardian_user_registration') {
          // Store guardian ID in session and return early
          $session->set('guardian_id', $existingUser->id());
          return;
        }
        // Replace email if the handler is for youth registration
        if ($this->handler_id === 'nbw_youth_user_registration') {
          $user_data['mail'] = $generateDummyEmail();
        }
      }

      // Ensure email is set for youth registrations if it was empty
      if ($this->handler_id === 'nbw_youth_user_registration' && empty($user_data['mail'])) {
        $user_data['mail'] = $generateDummyEmail();
      }

      if($form['#webform_id'] == 'nbw_youth_application_waiver') {
            if (!is_null($user_data['field_address']) && !isset($user_data['field_address']['country_code'])) {
               if (isset($user_data['field_address']['country'])) {
                 if ($user_data['field_address']['country'] == 'United States') {
                   $user_data['field_address']['country_code'] = "US";
                 }
               }
             }
      }



        $account = $this->createUserAccount($user_data);

      }

    // If no account is created or updated we do not want to proceed with
    // validation.
    if (empty($account)) {
      return;
    }

    // Flag violations of user fields and properties.
    /** @var \Drupal\Core\Entity\EntityConstraintViolationListInterface $violations */
    $violations = $account->validate();
    // Display any user entity validation messages on the webform.
    if (count($violations) > 0) {
      // Load the mapping between webform elements and the user entity fields.
      $user_field_mapping = $this->configuration['user_field_mapping'];
      foreach ($violations as $violation) {
        list($user_field_name) = explode('.', $violation->getPropertyPath(), 2);
        $webform_element_name = array_search($user_field_name, $user_field_mapping);
        $form_state->setErrorByName($webform_element_name, $violation->getMessage());
      }
    }

    // Store the user account for further handling.
    // See postSave();
    $this->userAccount = $account;
    $this->form = $form;

  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    /** @var \Drupal\user\UserInterface $account */
    $account = $this->userAccount;

    if (isset($account)) {
      $result = $account->save();

      // If this is a newly created user account.
      if ($result === SAVED_NEW) {
        $message = '';
        $admin_approval = $this->configuration['create_user']['admin_approval'];
        $email_verification = $this->configuration['create_user']['email_verification'];

        // Does the registration require admin approval?
        if ($admin_approval) {
          $message = $this->configuration['create_user']['admin_approval_message'];

          //_user_mail_notify('register_pending_approval', $account); O.I. - blocking emails for now

          // As it's a new account and the user will not be automatically logged
          // in - as admin approval is required - set the submission owner.
          $webform_submission->setOwner($account);
          $webform_submission->save();
        }
        // Do we need to send an email verification to the user?
        elseif ($email_verification) {
          $message = $this->configuration['create_user']['email_verification_message'];

          //_user_mail_notify('register_no_approval_required', $account); O.I. - blocking emails for now 

          // As it's a new account and the user will not be automatically logged
          // in - as email verification is required - set the submission owner.
          $webform_submission->setOwner($account);
          $webform_submission->save();
        }
        else {
          $message = $this->configuration['create_user']['success_message'];
          // @todo the below call is problematic when using AJAX to handle webform
          // submissions. Drupal suspects the form is being submitted in a
          // suspicious way. See setInvalidTokenError() which is being called
          // in FormBuilder->doBuildForm().
        }

        if (!empty($message)) {
          $this->messenger()->addStatus($this->t($message));
        }
      }
/*      if($form['#webform_id'] == 'nbw_youth_application_waiver') {


      }*/
      // Create Profile(s)
      if($this->form['#webform_id'] == 'nbw_youth_application_waiver'){
        /***
         * For some reason this function is called 2 times for each handler. We'll put the User IDs in the session on the first go
         * and on the second will check and if found the same ID - return out of it.
         */

        $request = \Drupal::request();
        $session = $request->getSession();

        if ($this->handler_id == 'nbw_guardian_user_registration'){

          $guardId = $session->get('guardian_id');
          if(!is_null($guardId) && $guardId == $account->id())
            return;

          $session->set('guardian_id', $account->id()); // we will need this for creation of the Youth user next
          $messageGuard = "Save new Guardian, Guardian ID TO session: " . $account->id();
          \Drupal::logger('NbwUsersRegistrationWebformHandler::guardian')->notice($messageGuard);

        } else { //'nbw_youth_user_registration'

          $youthId = $session->get('youth_id');
          if(!is_null($youthId) && $youthId == $account->id())
            return;
          else
            $session->set('youth_id', $account->id());
          //Fill Profiles
          $guardId = $session->get('guardian_id');
          $mess = "Save Youth Profile: Guardian ID FROM session: " . $guardId;
          \Drupal::logger('NbwUsersRegistrationWebformHandler::youth')->notice($mess);

          $nbw_youth_profile = \Drupal::entityTypeManager()
            ->getStorage('profile')
            ->loadByProperties([
              'uid' => $account->id(),
              'type' => 'nbw_youth_profile',
            ]);
/*
field__consent_to_liability_waiv  ::    	consent_to_liability_waiver
field__emergency_contact_name   ::  	emergency_contact_name
field__emergency_contact_relatio  ::   	emergency_contact_relationship_to_participant
field__i_identify_my_gender_as     ::    	i_identify_my_gender_as ::   	Fieldset ::  	male, female, transgender , nonconforming, other
field_able_to_ride_a_bike      ::    	participant_able_to_ride_a_bike
field_able_to_ride_for_20     ::   participant_able_to_ride_for_20_minutes
field_allergies_the_participant_   ::  please_list_any_allergies_the_participant_has
field_any_additional_concerns    ::  	any_medical_mobility_or_mental_health_concerns
field_any_medications_we_should_   ::   	participant_taking_any_medications_we_should_know
field_bicycle_by_themsel          ::    may_participant_leave_a_bicycle_by_themsel
field_can_leave_with     ::    	list_everyone_the_participant_can_leave_with
field_current_grade_level       ::   current_grade_level
field_date_of_birth             ::  	date_of_birth
field_describe_if_other => (Gender) Describe if other      ::     describe_if_other
field_describe_if_other_race => (Race) Describe if other    ::    	describe_if_other_race
field_emergency_contact_phone_nu   ::  emergency_contact_phone_number
field_guardian
field_guardian_relationship_to_y :: guardian_relationship_to_participant
field_have_a_weekly_transit_pass   ::  does_the_participant_have_a_weekly_transit_pass
field_height                           ::    height
field_hours_total
field_how_did_you_hear                 ::    	how_did_your_family_hear_about_nbw   ::   	Fieldset  ::  participant_s_school,  	family,   	friend,   	flyer_in_the_neighborhood
                                                                                                        nbw_website, facebook ,  	twitter,  	instagram, other_way_hear_about
field_other_way_to_hear => (Other way to hear) Describe if other:   ::   	describe_if_other_way_to_hear

field_i_identify_my_race_as            ::   	i_identify_my_race_as   ::  Fieldset  :: black_or_african_american, white_or_caucasian, hispanic_or_latinx, asian,
                                                                                           	american_indian_or_alaska_native,  	native_hawaiian_or_other_pacific_islander, other_race
field_interested_in  ::  	i_m_interested_in :: Fieldset ::  	earn_a_bike_monday_wednesday_ ,  	earn_a_bike_tuesday_thursday_
                                                 	leadership_advanced_mechanics_class,  	job_opportunities_for_youth
field_leave_by_themselves   ::  may_the_participant_leave_nbw_activities_by_themselves

field_may_administer_benadryl      ::  may_nbw_staff_administer_benadryl
field_may_take_septa       :: may_the_participant_take_septa
field_may_wade_in_water    ::  may_participant_wade_in_water_while_supervised
field_media_feedback_release  ::    	consent_to_media_feedback_release
field_miles_total
field_need_a_token    ::      	participant_need_a_token

field_permission_to_contact               ::   nbw_has_permission_to_contact ::  	Fieldset ::  	email_to_contact,  	text_to_contact,  	phone_call_to_contact
field_public_assistance_eligible
field_school                        ::    school
field_use_an_asthma_inhaler_dail   ::   	participant_have_and_use_an_asthma_inhaler_daily
*/




          if(empty($nbw_youth_profile)){
            // Word On The Street - check all that apply
            $webform_data = $webform_submission->getData();
            $howDidYouHear = array();
            if (!empty($webform_data["participant_s_school"]) && $webform_data["participant_s_school"] == 1 ){
              $howDidYouHear[] = "Participant's School";
            }
            if (!empty($webform_data["family"]) && $webform_data["family"] == 1 ){
              $howDidYouHear[] = "Family";
            }
            if (!empty($webform_data["friend"]) && $webform_data["friend"] == 1 ){
              $howDidYouHear[] = "Friend";
            }
            if (!empty($webform_data["flyer_in_the_neighborhood"]) && $webform_data["flyer_in_the_neighborhood"] == 1 ){
              $howDidYouHear[] = "Flyer in the Neighborhood";
            }
            if (!empty($webform_data["nbw_website"]) && $webform_data["nbw_website"] == 1 ){
              $howDidYouHear[] = "NBW Website";
            }
            if (!empty($webform_data["facebook"]) && $webform_data["facebook"] == 1 ){
              $howDidYouHear[] = "Facebook";
            }
            if (!empty($webform_data["twitter"]) && $webform_data["twitter"] == 1 ){
              $howDidYouHear[] = "Twitter";
            }
            if (!empty($webform_data["instagram"]) && $webform_data["instagram"] == 1 ){
              $howDidYouHear[] = "Instagram";
            }
/*            if (!empty($webform_data["other_way_hear_about"]) && $webform_data["other_way_hear_about"] == 1 ){
              $howDidYouHear[] = "Other";
            }*/
            // Gender - check all that apply
            $gender = array();
            if (!empty($webform_data["male"]) && $webform_data["male"] == 1 ){
              $gender[] = "Male";
            }
            if (!empty($webform_data["female"]) && $webform_data["female"] == 1 ){
              $gender[] = "Female";
            }
            if (!empty($webform_data["transgender"]) && $webform_data["transgender"] == 1 ){
              $gender[] = "Transgender";
            }
            if (!empty($webform_data["nonconforming"]) && $webform_data["nonconforming"] == 1 ){
              $gender[] = "Nonconforming";
            }
/*            if (!empty($webform_data["other"]) && $webform_data["other"] == 1 ){
              $gender[] = "Other";
            }*/

            // Race - check all that apply
            $race = array();
            if (!empty($webform_data["black_or_african_american"]) && $webform_data["black_or_african_american"] == 1 ){
              $race[] = "Black or African American";
            }
            if (!empty($webform_data["white_or_caucasian"]) && $webform_data["white_or_caucasian"] == 1 ){
              $race[] = "White or Caucasian";
            }
            if (!empty($webform_data["hispanic_or_latinx"]) && $webform_data["hispanic_or_latinx"] == 1 ){
              $race[] = "Hispanic or Latinx";
            }
            if (!empty($webform_data["asian"]) && $webform_data["asian"] == 1 ){
              $race[] = "Asian";
            }
            if (!empty($webform_data["american_indian_or_alaska_native"]) && $webform_data["american_indian_or_alaska_native"] == 1 ){
              $race[] = "American Indian or Alaska Native";
            }
            if (!empty($webform_data["native_hawaiian_or_other_pacific_islander"]) && $webform_data["native_hawaiian_or_other_pacific_islander"] == 1 ){
              $race[] = "Native Hawaiian or other Pacific Islander";
            }
/*            if (!empty($webform_data["other_race"]) && $webform_data["other_race"] == 1 ){
              $race[] = "Other";
            }*/

            // Interested In - check all that apply
            $interestedIn = array();
            if (!empty($webform_data["earn_a_bike_monday_wednesday_"]) && $webform_data["earn_a_bike_monday_wednesday_"] == 1 ){
              $interestedIn[] = "Earn-A-Bike (Monday & Wednesday)";
            }
            if (!empty($webform_data["earn_a_bike_tuesday_thursday_"]) && $webform_data["earn_a_bike_tuesday_thursday_"] == 1 ){
              $interestedIn[] = "Earn-A-Bike (Tuesday & Thursday)";
            }
            if (!empty($webform_data["leadership_advanced_mechanics_class"]) && $webform_data["leadership_advanced_mechanics_class"] == 1 ){
              $interestedIn[] = "Leadership & Advanced Mechanics Class";
            }
            if (!empty($webform_data["job_opportunities_for_youth"]) && $webform_data["job_opportunities_for_youth"] == 1 ){
              $interestedIn[] = "Job Opportunities for Youth";
            }
            if (!empty($webform_data["summer_camp_mondays_and_wednesdays"]) && $webform_data["summer_camp_mondays_and_wednesdays"] == 1 ){
              $interestedIn[] = "summer_camp_mondays_and_wednesdays";//"Summer Camp: Mondays and Wednesdays";
            }
            if (!empty($webform_data["summer_camp_tuesday_and_thursday"]) && $webform_data["summer_camp_tuesday_and_thursday"] == 1 ){
              $interestedIn[] = "summer_camp_tuesday_and_thursday";//"Summer Camp: Tuesday and Thursday";
            }
            if (!empty($webform_data["ride_club_monday_and_wednesday"]) && $webform_data["ride_club_monday_and_wednesday"] == 1 ){
              $interestedIn[] = "ride_club_monday_and_wednesday";
            }
            if (!empty($webform_data["ride_club_tuesday_and_thursdays"]) && $webform_data["ride_club_tuesday_and_thursdays"] == 1 ){
              $interestedIn[] = "ride_club_tuesday_and_thursdays";
            }

            //Permissions to contact
            $permission_to_contact = array();
            if (!empty($webform_data["email_to_contact"]) && $webform_data["email_to_contact"] == 1 ){
              $permission_to_contact[] = "Email";
            }
            if (!empty($webform_data["text_to_contact"]) && $webform_data["text_to_contact"] == 1 ){
              $permission_to_contact[] = "Text";
            }
            if (!empty($webform_data["phone_call_to_contact"]) && $webform_data["phone_call_to_contact"] == 1 ){
              $permission_to_contact[] = "Phone Call";
            }


            $strSchool = (strlen($webform_data['school']) > 254) ? substr($webform_data['school'],0,253).'...' : $webform_data['school'];

            $messageGuard = "School length: " . strlen($webform_data['school']) . " data: " . $webform_data['school'];
            \Drupal::logger('NbwUsersRegistrationWebformHandler::school stuff')->notice($messageGuard);

            $nbw_youth_profile = Profile::create([
              'type' => 'nbw_youth_profile',
              'uid' => $account->id(),
              'field_guardian' => $guardId,
              'field_how_did_you_hear' => $howDidYouHear,
              'field_other_way_to_hear' => $webform_data['describe_if_other_way_to_hear'],
              'field_use_an_asthma_inhaler_dail' => $webform_data['participant_have_and_use_an_asthma_inhaler_daily'],
              'field_school' => $strSchool,
              'field_public_assistance_eligible' => $webform_data['public_assistance'],
              'field_need_a_token' => $webform_data['participant_need_a_token'],
              'field_media_feedback_release' => $webform_data['consent_to_media_feedback_release'],
              'field_may_wade_in_water' => $webform_data['may_participant_wade_in_water_while_supervised'],
              'field_may_take_septa' => $webform_data['may_the_participant_take_septa'],
              'field_may_administer_benadryl' => $webform_data['may_nbw_staff_administer_benadryl'],
              'field_leave_by_themselves' => $webform_data['may_the_participant_leave_nbw_activities_by_themselves'],
              'field_have_a_weekly_transit_pass' => $webform_data['does_the_participant_have_a_weekly_transit_pass'],
              'field_guardian_relationship_to_y' => $webform_data['guardian_relationship_to_participant'],
              'field_emergency_contact_phone_nu' => $webform_data['emergency_contact_phone_number'],
              'field_date_of_birth' => $webform_data['date_of_birth'],
              'field_current_grade_level' => $webform_data['current_grade_level'],
              'field_can_leave_with' => $webform_data['list_everyone_the_participant_can_leave_with'],
              'field_bicycle_by_themsel' => $webform_data['may_participant_leave_a_bicycle_by_themsel'],
              'field_any_medications_we_should_' => $webform_data['participant_taking_any_medications_we_should_know'],
              'field_any_additional_concerns' => $webform_data['any_medical_mobility_or_mental_health_concerns'],
              'field_allergies_the_participant_' => $webform_data['please_list_any_allergies_the_participant_has'],
              'field_able_to_ride_for_20' => $webform_data['participant_able_to_ride_for_20_minutes'],
              'field_able_to_ride_a_bike' => $webform_data['participant_able_to_ride_a_bike'],
              'field__emergency_contact_relatio' => $webform_data['emergency_contact_relationship_to_participant'],
              'field__emergency_contact_name' => $webform_data['emergency_contact_name'],
              'field__consent_to_liability_waiv' => $webform_data['consent_to_liability_waiver'],
              'field_height' => $webform_data['height'],
              'field_permission_to_contact' => $permission_to_contact,
             // 'field__i_identify_my_gender_as' => $gender,
              //'field_describe_if_other' => $webform_data['describe_if_other'],
              //'field_i_identify_my_race_as' => $race,
              //'field_describe_if_other_race' => $webform_data['describe_if_other_race'],
              'field_interested_in' => $interestedIn,

            ]);
            \Drupal::logger('NbwUsersRegistrationWebformHandler::youth')->notice(" Created a Profile");
            $nbw_youth_profile->save();
            $account->set('field_gender', $gender);
            $account->set('field_gender_other', $webform_data['describe_if_other']);
            $account->set('field_race', $race);
            $account->set('field_race_other', $webform_data['describe_if_other_race']);
            $account->save();
          }


          /*        $nbw_youth_profile = Profile::create([
                    'type' => 'nbw_youth_profile',
                    'uid' => $account->id(),
                  ]);
                  $nbw_youth_profile->get('field_guardian')->setValue($session->get('guardian_id'));
                  $nbw_youth_profile->save();*/
          // Get NBW profiles for the YouthID
          /*        $nbw_youth_profile = \Drupal::entityTypeManager()
                    ->getStorage('profile')
                    ->loadByProperties([
                      'uid' => $account->id(),
                      'type' => 'nbw_youth_profile',
                    ]);
                  $nbw_youth_profile->get('field_guardian')->setValue($session->get('guardian_id'));*/

        }
      }
    }
  }

  /**
   * Creates a new user account based on a list of values.
   *
   * This does NOT save the user entity. This happens in the postSave()
   * function.
   *
   * @param array $user_data
   *   Associative array of user data, keyed by user entity property/field.
   *
   * @return \Drupal\user\UserInterface
   *   The user account entity, populated with values.
   */
  protected function createUserAccount(array $user_data) {
    $lang = $this->languageManager->getCurrentLanguage()->getId();
    $mail = $user_data['mail'];
    $default_user_data = [
      'init' => $mail,
      'name' => str_replace('@', '.', $mail),
      'pass' => \Drupal::service('password_generator')->generate(),
      'langcode' => $lang,
      'preferred_langcode' => $lang,
      'preferred_admin_langcode' => $lang,
      'roles' => array_keys(array_filter($this->configuration['create_user']['roles'])) ?? [],
      'status' => 0, // Blocked by default
    ];
    $user_data = array_merge($default_user_data, $user_data);

    $account = User::create();
    $account->enforceIsNew();

    foreach ($user_data as $name => $value) {
      $account->set($name, $value);
    }

    // Does the account require admin approval?
    $admin_approval = $this->configuration['create_user']['admin_approval'];
    if ($admin_approval) {
      // The account registration requires further approval.
      $account->block();
    }

    return $account;
  }

}
