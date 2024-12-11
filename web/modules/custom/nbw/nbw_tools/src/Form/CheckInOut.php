<?php

declare(strict_types=1);

namespace Drupal\nbw_tools\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a NBW tools form (Check In/Check Out).
 */
final class CheckInOut extends FormBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, RequestStack $requestStack) {
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'nbw_tools_check_in_out';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Get the selected date or default to today's date if not set.
    //$selected_date = $form_state->getValue('date') ?? date('Y-m-d');

    $triggering_element = $form_state->getTriggeringElement();
    // Get classes.
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery();
    $query->condition('type', 'class_roster');
    $query->condition('status', 1);
    $query->sort('field_class_name.entity:node.title');
    $node_classes = Node::loadMultiple($query->accessCheck(TRUE)
      ->execute());
    $class_list = [];
    foreach ($node_classes as $nid => $node) {
      if ($node->hasField('field_class_name') && !$node->get('field_class_name')->isEmpty()) {
        $target_id = $node->get('field_class_name')->target_id; // Get the target ID.
        if ($target_id) {
          $referenced_entity = \Drupal::entityTypeManager()->getStorage('node')->load($target_id); // Load the entity manually.
          if ($referenced_entity) {
            $class_list[$nid] = $referenced_entity->label();
          } else {
            \Drupal::logger('mymodule')->warning('Entity with ID @id could not be loaded.', ['@id' => $target_id]);
          }
        }
      } else {
        \Drupal::logger('mymodule')->warning('field_class_name is empty or missing for node @nid.', ['@nid' => $nid]);
      }
    }
    $form['class'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Class Name'),
      '#empty_option' => $this->t('- Select -'),
      '#empty_value' => '',
      '#options' => $class_list,
      '#default_value' => $this->requestStack->getCurrentRequest()->query->get('class'),
      '#ajax' => [
        'callback' => '::ajaxStudents',
      ],
    ];


    // Date
    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('date') ?? date('Y-m-d'),
      '#ajax' => [
        'callback' => '::ajaxStudents',
        'event' => 'change',
        'wrapper' => 'students-container',
      ],
    ];


    // Duplicate Submit button near Date control.
    $form['actions_above_checkin'] = [
      'submit_above' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#submit' => ['::submitForm'], // Same submit handler.
        '#weight' => -8,
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
    ];


    $form['check_in_out_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Check in / Out'),
    ];
    $form['check_in_out_wrapper']['checkin_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];
    $form['check_in_out_wrapper']['checkin_wrapper']['checkin'] = [
      '#type' => 'radio',
      '#required' => TRUE,
      '#title' => $this->t('Check In'),
      '#parents' => ['check_in_out_wrapper'],
      '#return_value' => 'checkin',
      '#ajax' => [
        'callback' => '::ajaxStudents',
      ],
    ];
    $form['check_in_out_wrapper']['checkin_wrapper']['checkin_time'] = [
      '#type' => 'datetime',
      '#date_date_element' => 'none',
      '#date_time_element' => 'time',
      '#default_value' => new DrupalDateTime('now'),
      '#attributes' => [ //- this disables the seconds, but they are still there
        'step' => 60,
      ],
    ];
    $form['check_in_out_wrapper']['checkout_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];
    $form['check_in_out_wrapper']['checkout_wrapper']['checkout'] = [
      '#type' => 'radio',
      '#required' => TRUE,
      '#title' => $this->t('Check Out'),
      '#parents' => ['check_in_out_wrapper'],
      '#return_value' => 'checkout',
      '#ajax' => [
        'callback' => '::ajaxStudents',
      ],
    ];
    $form['check_in_out_wrapper']['checkout_wrapper']['checkout_time'] = [
      '#type' => 'datetime',
      '#date_date_element' => 'none',
      '#date_time_element' => 'time',
      '#default_value' => new DrupalDateTime('now'),
      '#attributes' => [ //- this disables the seconds, but they are still there
        'step' => 60,
      ],
    ];

    // Get students.
    $form['students_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'students-container'],
    ];
    if ($form_state->hasValue('class')) {
      $sclass_roster_id = $form_state->getValue('class');
    }
    elseif ($this->requestStack->getCurrentRequest()->query->has('class')) {
      // Get prepopulated class roster ID.
      $sclass_roster_id = $this->requestStack
        ->getCurrentRequest()
        ->query
        ->get('class');
    }
    else {
      $sclass_roster_id = NULL;
    }
    $class_duration_hours = "";
    if ($sclass_roster_id) {
      $form['students_container']['students_select_all'] = [
        '#type' => 'button',
        '#value' => $this->t('Select All'),
        '#internal_id' => 'students_select_all',
        '#executes_submit_callback' => FALSE,
        '#limit_validation_errors' => [['class']],
        '#ajax' => [
          'callback' => '::ajaxStudents',
          'wrapper' => 'students-container',
        ],
      ];
      $form['students_container']['students'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Students'),
        '#tree' => TRUE,
      ];
      $class_roster = Node::load($sclass_roster_id);
      if (!$class_roster || $class_roster->getType() !== 'class_roster') {
        throw new NotFoundHttpException();
      }
      // Change "checkin" <-> "checkout" radios, get radiobutton value
      $complete_form = $form_state->getCompleteForm();
      if ($complete_form) {
        $checkin_out_type = $complete_form['check_in_out_wrapper']['checkin_wrapper']['checkin']['#value'];
        $selected_date = $complete_form['date']['#value'];
      }
      else {
        // default value
        $checkin_out_type = 'checkin';
        $selected_date = $form_state->getValue('date') ?? date('Y-m-d');
      }

      // Ensure we have a valid class roster.
      if ($class_roster->hasField('field_class_name')) {
        $class_id = $class_roster->field_class_name->target_id;
        $class_node = Node::load($class_id);

        // Ensure the class node exists and has a valid date range.
        if ($class_node && $class_node->hasField('field_when') && !$class_node->get('field_when')->isEmpty()) {
          $class_date_range = $class_node->get('field_when')->first()->getValue();
          //$start_time = strtotime($class_date_range['value']);
          //$end_time = strtotime($class_date_range['end_value']);
          $class_duration_hours = round(($class_date_range['end_value'] - $class_date_range['value']) / 3600, 2); // duration is in minutes.

          // Store the class duration in the form state for later use in submission.
          $form_state->set('class_duration_hours', $class_duration_hours);
        }
      }

      foreach ($class_roster->field_students->referencedEntities() as $student) {

        if ($checkin_out_type === 'checkout') {
          $attendance_record = $this->getAttendanceRecord($class_roster, $student,$selected_date);
          if (!$attendance_record) {
            // If you are not check in, you cannot check out.
            continue;
          }
        }
        // Create student real name.
        $name = [
          $student->field_address->given_name,
          $student->field_address->family_name,
          $student->field_address->additional_name,
        ];
        $name = implode(' ', $name);
        $form['students_container']['students'][$student->id()] = [
          '#type' => 'checkbox',
          '#title' => $name,
          '#disabled' => $this->studentCheckInOutAlreadyTest($class_roster, $student, $checkin_out_type,$selected_date),
        ];
        if (isset($triggering_element['#internal_id'])
          && $triggering_element['#internal_id'] === 'students_select_all'
          && !$form['students_container']['students'][$student->id()]['#disabled'])
        {
          // Set check value for current student checkbox.
          // TODO: Workaround, see: https://www.drupal.org/project/drupal/issues/1100170#comment-15586080
          $form['students_container']['students'][$student->id()]['#attributes']['checked'] = 'checked';
        }
      }
    }
    else {
      // Class not selected / prepopulated.
      $form['students_container']['students'] = [
        '#markup' => $this->t('Please select a class.'),
        '#prefix' => '<strong>',
        '#suffix' => '</strong>',
      ];
    }
    //$form['hours_earned_lost_wrapper']['class_hours_earned'] = [

    // Hours & Miles.
    $form['hours_earned_lost_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Hours'),
    ];
    // Add the calculated class duration to the form.
    $form['hours_earned_lost_wrapper']['class_hours_earned'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Earned @hours hours by attending this class.', ['@hours' => $class_duration_hours]),
      '#prefix' => '<p><strong>',
      '#suffix' => '</strong></p>',
    ];
    $form['hours_earned_lost_wrapper']['hours_earned_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'disabled' => 'disabled', // Initially set as disabled using attributes.
      ],
      '#states' => [
        'enabled' => [
          ':input[name="check_in_out_wrapper"]' => ['value' => 'checkout'],
        ],
      ],
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];

    $form['hours_earned_lost_wrapper']['hours_earned_wrapper']['hours_earned'] = [
      '#type' => 'number',
      '#default_value' => 0,
      '#title' => $this->t('Additional Hours Earned'),
    ];
    $form['hours_earned_lost_wrapper']['hours_lost_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'disabled' => 'disabled', // Initially set as disabled using attributes.
      ],
      '#states' => [
        'enabled' => [
          ':input[name="check_in_out_wrapper"]' => ['value' => 'checkout'],
        ],
      ],
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];
    $form['hours_earned_lost_wrapper']['hours_lost_wrapper']['hours_lost'] = [
      '#type' => 'number',
      '#default_value' => 0,
      '#title' => $this->t('Hours lost'),
    ];
    $form['miles_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Miles'),
      //'#type' => 'container',
      '#attributes' => [
        'disabled' => 'disabled', // Initially set as disabled using attributes.
      ],
      '#states' => [
        'enabled' => [
          ':input[name="check_in_out_wrapper"]' => ['value' => 'checkout'],
        ],
      ],
    ];
    $form['miles_wrapper']['miles'] = [
      '#type' => 'number',
      '#default_value' => 0,
      //'#title' => $this->t('Miles'),
      '#description' => $this->t('Miles Ridden'),
    ];
    // Notes & Submit.
    $form['notes_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Notes'),
      '#attributes' => [
        'disabled' => 'disabled', // Initially set as disabled using attributes.
        'class' => ['notes-fieldset'], // Add a custom class.
      ],
      '#states' => [
        'enabled' => [
          ':input[name="check_in_out_wrapper"]' => ['value' => 'checkout'],
        ],
      ],
    ];
    $form['notes_wrapper']['notes'] = [
      '#type' => 'textarea',
      '#required' => FALSE,
      '#cols' => 60,
      '#rows' => 5,
      '#attributes' => [
        'class' => ['notes-textarea'], // Add a custom class.
      ],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ],
    ];

    return $form;
  }

  /**
   * Return with students container.
   */
  public function ajaxStudents($form, FormStateInterface $form_state) {
    $selected_date = $form_state->getValue('date');
    // Ensure the selected date is passed to the form rebuild process.
    $form_state->setValue('date', $selected_date);
    // Update the default value of the date field
    $form['date']['#default_value'] = $selected_date;
    $form_state->setRebuild();

    $rebuild_view = [
      '#type' => 'view',
      '#name' => 'sign_in_for_class',
      '#display_id' => 'block_1',
      '#arguments' => [$form_state->getValue('class')],
    ];
    $ajax_response = new AjaxResponse();
    // Refresh student checkboxes and result set view.
    $ajax_response->addCommand(new ReplaceCommand('#students-container', $form['students_container']));
    $ajax_response->addCommand(new ReplaceCommand('#view-sign-in-for-class-result-wrapper', $rebuild_view));
    return $ajax_response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#type'] === 'submit') {
      // Check students list.
      $empty_students = TRUE;
      foreach ($form_state->getValue('students') as $item) {
        if ($item) {
          $empty_students = FALSE;
          break;
        }
      }
      if ($empty_students) {
        // At least one student is required.
        $form_state->setErrorByName('students', 'Students should not be empty.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Retrieve the selected date from the form state.
    $selected_date = $form_state->getValue('date');

    // Ensure the date is preserved after submission.
    $form_state->setValue('date', $selected_date);
    // Prepopulate class
    $this->requestStack
      ->getCurrentRequest()
      ->query
      ->add([
      'class' => $form_state->getValue('class')
    ]);
    // Get the selected date
    $selected_date = $form_state->getValue('date');
    // Create nodes
    $class_roster = Node::load($form_state->getValue('class'));

    // check if a Class Session Record for this class session exists, if it doesn't - create
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery();
    $query->condition('type', 'class_session_record');
    $query->condition('field_checkin_time', $selected_date);
    $query->condition('field_class_name', $class_roster->field_class_name->target_id);
    $query->condition('status', 1);
    $resultClassSession = $query->accessCheck(TRUE)->execute();
//    if ($result) {
//      return current(Node::loadMultiple($result));
//    }
//    else {
//      return NULL;
//    }

    // Neet to make sure we don't get here if there are no Students selected
    $checkout_datetime = "";
    $class_session_nid = "";

    if ($form_state->getValue('check_in_out_wrapper') === 'checkin') {
      //Check if Class Session Record for this day and class already exists, if not - create
      if (empty($resultClassSession)) {
        // Check in, create new "class_session_record" node.
        $node_csr_values = [
          'type' => 'class_session_record',
          'field_class_name' => $class_roster->field_class_name->target_id,
          'field_checkin_time' => $selected_date,
        ];
        // Create the node.
        $new_class_session_node = Node::create($node_csr_values);

        // Save the node.
        $new_class_session_node->save();

        // Get the node ID after saving.
        $class_session_nid = $new_class_session_node->id();
        $mess = "Created & saved a Cleass Session Record, node ID: " . $new_class_session_nid;
        \Drupal::logger('CheckInCheckOut.php::submitForm')->notice($mess);
        //$resultClassSession = $numResult; //So it would not to try creating a session again for the same "Submit" click
      } else {
        $currentClassSessionRecord = current(Node::loadMultiple($resultClassSession));
        $class_session_nid = $currentClassSessionRecord->id();
      }

    } else {
      $currentClassSessionRecord = current(Node::loadMultiple($resultClassSession));
      $class_session_nid = $currentClassSessionRecord->id();
      if ($currentClassSessionRecord) {
        if (empty($checkout_datetime)) {
          $checkout_datetime = new DrupalDateTime($selected_date . ' ' . $form_state->getValue('checkout_time')->format('H:i:s'));
        }
        // Get the current timestamp in human-readable form.
        $current_timestamp = date('Y-m-d H:i:s', $checkout_datetime->getTimestamp());

        // Get the new notes from the form state.
        $new_notes = $form_state->getValue('notes');

        // Fetch the existing notes from the field.
        $existing_notes = $currentClassSessionRecord->body->value ?? '';

        // Check if there are existing notes and prepare the updated notes accordingly.
        if (!empty($existing_notes)) {
          // If existing notes are present, append new notes with the timestamp and an empty line.
          $updated_notes = trim($existing_notes) . "\n\n" . $current_timestamp . "\n" . $new_notes;
        } else {
          // If no existing notes, just add the timestamp and new notes.
          $updated_notes = $current_timestamp . "\n" . $new_notes;
        }
        // Wrap the notes in <pre> tags to preserve formatting.
        //$updated_notes = '<pre>' . htmlspecialchars($updated_notes) . '</pre>';
        
        // Update the body field with the new notes.
        //$currentClassSessionRecord->body->value = $updated_notes;
        $currentClassSessionRecord->field_time_out = $checkout_datetime->getTimestamp();
        $currentClassSessionRecord->body = $updated_notes;
        $currentClassSessionRecord->save();
      }
    }

    // Get the class duration from the form state.
    $class_duration_hours = $form_state->get('class_duration_hours') ?? 0;

    // Get the additional hours entered by the user.
    //$additional_hours = $form_state->getValue(['hours_earned_lost_wrapper', 'hours_earned_wrapper', 'hours_earned']) ?? 0;

    // Calculate the total hours earned.
    //$total_hours_earned = $class_duration_hours + $additional_hours;

    foreach ($form_state->getValue('students') as $student_uid => $value) {
      if ($value != 0) {
        // Combine selected date with the time for checkin
        $checkin_datetime = new DrupalDateTime($selected_date . ' ' . $form_state->getValue('checkin_time')->format('H:i:s'));
        if (empty($checkout_datetime)) {
          $checkout_datetime = new DrupalDateTime($selected_date . ' ' . $form_state->getValue('checkout_time')->format('H:i:s'));
        }

        if ($form_state->getValue('check_in_out_wrapper') === 'checkin') {

          // Check in, create new "attendance_record" node.
          $node_values = [
            'type' => 'attendance_record',
            'field_class_name' => $class_roster->field_class_name->target_id,
            'field_checkin_time' => $selected_date,
            'field_student' => $student_uid,
            'field_time_in' => $checkin_datetime->getTimestamp(),
            'field_class_session_record' => $class_session_nid,  // Reference the Class Session Record.
          ];
          Node::create($node_values)->save();
        }
        else {
          // check out, get corresponding 'attendance_record' node.
          $student = User::load($student_uid);
          $attendance_record = $this->getAttendanceRecord($class_roster, $student, $selected_date);
          if ($attendance_record) {
            $attendance_record->field_time_out = $checkout_datetime->getTimestamp();
            $attendance_record->field_hours_earned = $class_duration_hours + $form_state->getValue('hours_earned');
            $attendance_record->field_hours_lost = $form_state->getValue('hours_lost');
            $attendance_record->field_miles_ridden = $form_state->getValue('miles');
            $attendance_record->body = $form_state->getValue('notes');
            $attendance_record->save();
          }

          // Get NBW profiles by the YouthID
          $nbw_youth_profiles = \Drupal::entityTypeManager()
            ->getStorage('profile')
            ->loadByProperties([
              'uid' => $student_uid,
              'type' => 'nbw_youth_profile',
            ]);

          if(empty($nbw_youth_profiles)){
            $nbw_youth_profile = Profile::create([
             'type' => 'nbw_youth_profile',
             'uid' => $student_uid,
            ]);
            $nbw_youth_profiles[] = $nbw_youth_profile;
          }

          foreach ($nbw_youth_profiles as $profile) {
            //dpm($profile->get('field_miles_total')->value);
            $miles_total_new = floatval($profile->get('field_miles_total')->value) + $form_state->getValue('miles');
            $hours_total_new = floatval($profile->get('field_hours_total')->value) + $class_duration_hours + $form_state->getValue('hours_earned') - $form_state->getValue('hours_lost');
            $profile->get('field_miles_total')->setValue($miles_total_new);
            $profile->get('field_hours_total')->setValue($hours_total_new);
            $profile->save();
          }
        }
      }
    }
  }

  /**
   * Check if the student has already applied to the roster?
   *
   * @param \Drupal\node\Entity\Node $class_roster
   *   Class roster node object. (type = class_roster)
   * @param \Drupal\user\Entity\User $student
   *   Student account object.
   * @param $type
   *   "checkin", "checkout" or "both"
   * @param $date
   *   Date to check (Y-m-d format), or NULL to use current day.
   *
   * @return bool|NULL
   *   - TRUE, if student checked in or checked out
   *   - NULL: error
   */
  private function studentCheckInOutAlreadyTest(Node $class_roster, User $student, string $type, string $date = NULL) : ?bool {
    $attendance_record = $this->getAttendanceRecord($class_roster, $student, $date);
    if ($attendance_record) {
      $query = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery();
      $query->condition('nid', $attendance_record->id());
      switch ($type) {
        case 'checkin':
          $query->exists('field_time_in');
          break;
        case 'checkout':
          $query->exists('field_time_out');
          break;
        case 'both':
          $query->exists('field_time_in');
          $query->exists('field_time_out');
          break;
      }
      $attendance_record_ids = $query->accessCheck(TRUE)->execute();
      return !empty($attendance_record_ids);
    }
    return NULL;
  }

  /**
   * Get corresponding attendance_record node.
   *
   * @param \Drupal\node\Entity\Node $class_roster
   *   Class roster node object. (type = class_roster)
   * @param \Drupal\user\Entity\User $student
   *   Student account object.
   * @param $date
   *   Date to check (Y-m-d format), or NULL to use current day.
   *
   * * @return \Drupal\node\Entity\Node|NULL
   *   - Corresponding attendance_record, if available, or NULL.
   */
  private function getAttendanceRecord(Node $class_roster, User $student, string $date = NULL) : ?Node {
    if (!$date) {
      $date = date('Y-m-d');
    }
    if ($class_roster->getType() !== 'class_roster') {
      return NULL;
    }
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery();
    $query->condition('type', 'attendance_record');
    $query->condition('field_checkin_time', $date);
    $query->condition('field_class_name', $class_roster->field_class_name->target_id);
    $query->condition('field_student', $student->id());
    $query->condition('status', 1);
    $result = $query->accessCheck(TRUE)->execute();
    if ($result) {
      return current(Node::loadMultiple($result));
    }
    else {
      return NULL;
    }
  }

}
