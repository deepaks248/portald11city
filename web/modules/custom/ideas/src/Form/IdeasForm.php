<?php

namespace Drupal\ideas\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\global_module\Service\FileUploadService;
use Symfony\Component\HttpFoundation\JsonResponse;

class IdeasForm extends FormBase
{
  protected $fileUploadService;
  protected $request;

  public function __construct(FileUploadService $fileUploadService, RequestStack $request_stack)
  {
    $this->fileUploadService = $fileUploadService;
    $this->request = $request_stack->getCurrentRequest();
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('global_module.file_upload_service'),
      $container->get('request_stack')
    );
  }

  public function getFormId()
  {
    return 'ideas_form';
  }

  private function getIdeaCategoryOptions()
  {
    // Static variable to avoid rebuilding in the same request
    static $options = null;
    if ($options !== null) {
      return $options;
    }

    $cid = 'ideas_form:category_options';
    $cache = \Drupal::cache()->get($cid);
    if ($cache) {
      $options = $cache->data;
      return $options;
    }

    $options = ['' => $this->t('Select Category')];
    $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadTree('idea_category');
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    // Cache permanently with taxonomy_term_list tag for automatic invalidation
    \Drupal::cache()->set($cid, $options, CacheBackendInterface::CACHE_PERMANENT, ['taxonomy_term_list']);

    return $options;
  }

  public function buildForm(array $form, FormStateInterface $form_state, $srcId = false)
  {
    $form['#prefix'] = '<div id="ideas-form-wrapper">';
    $form['#suffix'] = '</div>';

    // $floatLabelClass = explode(' ', 'absolute text-sm text-medium_dark dark:text-white duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white dark:bg-gray-900 px-2 peer-focus:px-2 peer-focus:text-amber-600 peer-focus:dark:text-blue-500 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-1');

    $inputFieldClass = explode(' ', 'px-2.5 pb-2.5 pt-4 text-sm text-medium_dark bg-transparent rounded-lg border border-1 border-gray-300 appearance-none dark:border-gray-600 dark:focus:border-blue-500 focus:focus:border-amber-300 focus:outline-none focus:ring-0 focus:border-yellow-600 peer');

    $form['#attributes']['class'][] = 'form-sec p-4 lg:px-10 lg:py-12 bg-white text-center lg:text-start s:mb-24 xs:mb-20';

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#attributes' => [
        'minlength' => 2,
        'maxlength' => 50,
        'autocomplete' => 'off',
        'class' => $inputFieldClass,
        'placeholder' => ' ',
      ],
      '#prefix' => '<div class="relative mb-4">',
      '#suffix' => '</div>'
    ];

    $form['author'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Author'),
      '#attributes' => [
        'autocomplete' => 'off',
        'class' => $inputFieldClass,
        'placeholder' => ' ',
      ],
      '#prefix' => '<div class="relative mb-4">',
      '#suffix' => '</div>'
    ];

    $form['category_idea'] = [
      '#type' => 'select',
      '#title' => $this->t('Idea Categories'),
      '#required' => TRUE,
      // '#title_display' => 'invisible',
      '#options' => $this->getIdeaCategoryOptions(),
      '#attributes' => [
        'class' => array_merge(['select', 'font-Open_Sans', 'font-Open_Sans_Bold', 'text-base'], $inputFieldClass),
        'autocomplete' => 'off',
      ],
      '#prefix' => '<div class="relative mb-4">',
      '#suffix' => '</div>'
    ];

    $form['idea_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('<div class="font-nevis text-gray-500">Idea Content</div>'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['peer', 'w-full', 'px-2.5', 'pb-2.5', 'pt-4', 'text-sm', 'text-gray-700', 'bg-transparent', 'rounded-lg', 'border', 'border-gray-300', 'appearance-none', 'focus:outline-none', 'focus:ring-0', 'focus:!border-yellow-500'],
        'autocomplete' => 'off',
        'rows' => 5,
        'placeholder' => '',
      ],
      '#prefix' => '<div class="relative mt-4 flex flex-col text-left">',
      '#suffix' => '</div>'
    ];

    $form['upload_file'] = [
      '#type' => 'file',
      '#title' => $this->t('<span class="font-nevis text-gray-500">Upload Picture</span>'),
      '#description' => $this->t('<span class="text-xs">Supported file types: JPG, JPEG, PNG, max size 2MB.</span>'),
      '#required' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png pdf'],
        'file_validate_size' => [2 * 1024 * 1024],
      ],
      '#attributes' => [
        'class' => [
          'peer',
          'w-1/2',
          'lg:max-w-lg',
          'px-2.5',
          'py-2.5',
          // 'pt-4',
          'text-sm',
          'text-medium_dark',
          'bg-transparent',
          'rounded-lg',
          'text-base',
          's:text-sm',
          'xs:text-sm',
          'rounded-lg',
          'border',
          'border-gray-300 '
        ]
      ],
      '#prefix' => '<div class="relative mb-4 flex flex-col text-left">',
      '#suffix' => '</div>'
    ];

    $form['upload_file_hidden'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'uploaded_file_url'],
    ];

    $form['terms'] = [
      '#type' => 'checkbox',
      // '#title' => $this->t('I agree on <a href="@url" target="_blank">Terms and Conditions</a>', ['@url' => 'https://www.trinitymobility.com/']),
      '#required' => TRUE,
      '#prefix' => '<div id="checkboxBtn">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => ['checkbox', 'just-validate-success-field', 'border', 'border-2', 's:w-6', 's:h-6', 'xs:w-4', 'xs:h-4'],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#prefix' => '<div class="submit-btns btns flex lg:gap-8 mt-5 flex-col sm:flex-row s:gap-5 xs:gap-5">',
      '#suffix' => '</div>',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'class' => ['btn', 'buttoning', 'btn-warning', 'lg:h-14', 'lg:w-44', 'xs:h-10', 'text-white', 'capitalize', 'text-lg', 'font-Open_Sans', 'submitBtn'],
      ],
      '#ajax' => [
        'callback' => '::ajaxSubmitCallback',
        'wrapper' => 'ideas-form-wrapper',
        'effect' => 'fade',
      ],
    ];

    $form['actions']['reset'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#attributes' => [
        'class' => [
          'btn',
          'bg-transparent',
          'text-black/75',
          'px-14',
          'text-[1.125rem]',
          "font-['Open_Sans']",
          'rounded-[10px]',
          'transition-colors',
          'duration-200',
          'ease-in-out',
          'border',
          'border-black/25',
          'cursor-pointer',
          'inline',
          'font-bold',
          'btn-outline',
          'lg:h-14',
          'lg:w-44',
          'xs:h-10',
          'capitalize',
          'text-medium_dark',
          'button',
          'rounded-lg',
          'cancelBtn'

        ],
        'onclick' => 'window.location.reload()',
      ],
    ];
    $form['#theme'] = 'ideas';
    // $form['#attributes']['class'][] = 'cv-validate-before-ajax';
    $form['#attached']['library'][] = 'ideas/ideas-library';
    $form['#attached']['library'][] = 'global_module/ajax_loader';
    $form['#attributes']['enctype'] = 'multipart/form-data';
    return $form;
  }


  // public function submitForm(array &$form, FormStateInterface $form_state)
  // {
  //   $image_url = NULL;
  //   $response_data = [];

  //   $image_url = $form_state->getValue('upload_file_hidden');
  //   if (empty($image_url)) {
  //     $this->messenger()->addError($this->t('Please upload a file before submitting.'));
  //     return;
  //   }

  //   // Get form values
  //   $title = $form_state->getValue('first_name');
  //   $author = $form_state->getValue('author');
  //   $category_id = $form_state->getValue('category_idea');
  //   $body = $form_state->getValue('idea_content');

  //   // Basic validation
  //   if (empty($title) || empty($author) || empty($category_id) || empty($body) || empty($image_url)) {
  //     $this->messenger()->addError($this->t('Please fill in all required fields.'));
  //     return;
  //   }

  //   try {
  //     // Create a new idea node
  //     $node = Node::create([
  //       'type' => 'ideas',
  //       'title' => $title,
  //       'field_idea_author' => $author,
  //       'field_idea_content' => [
  //         'value' => $body,
  //         'format' => 'basic_html',
  //       ],
  //       'field_ideas_categories' => [
  //         'target_id' => $category_id,
  //       ],
  //       'field_idea_image' => $image_url, // Plain text field
  //       'status' => 0,
  //     ]);
  //     $node->save();

  //     $this->messenger()->addStatus($this->t('Your idea has been submitted successfully.'));
  //   } catch (\Exception $e) {
  //     \Drupal::logger('ideas_form')->error($e->getMessage());
  //     $this->messenger()->addError($this->t('An error occurred while submitting your idea.'));
  //   }
  // }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $image_url = $form_state->getValue('upload_file_hidden');
    if (empty($image_url)) {
      $this->messenger()->addError($this->t('Please upload a file before submitting.'));
      return;
    }

    // Gather form values.
    $title = $form_state->getValue('first_name');
    $author = $form_state->getValue('author');
    $category_id = $form_state->getValue('category_idea');
    $body = $form_state->getValue('idea_content');

    if (empty($title) || empty($author) || empty($category_id) || empty($body)) {
      $this->messenger()->addError($this->t('Please fill in all required fields.'));
      return;
    }

    // Queue the data instead of saving directly.
    try {
      $queue = \Drupal::queue('ideas_create_queue');

      $queue->createItem([
        'title' => $title,
        'author' => $author,
        'category_id' => $category_id,
        'body' => $body,
        'image_url' => $image_url,
        'submitted_at' => \Drupal::time()->getRequestTime(),
        'uid' => \Drupal::currentUser()->id(),
      ]);

      $this->messenger()->addStatus($this->t('Your idea has been submitted and will be processed shortly.'));
    } catch (\Exception $e) {
      \Drupal::logger('ideas_form')->error($e->getMessage());
      $this->messenger()->addError($this->t('An error occurred while queuing your idea.'));
    }
  }

  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state)
  {
    $form['#attached']['drupalSettings']['ideas']['submissionSuccess'] = TRUE;
    return $form;
  }
}
