<?php

namespace Drupal\bookish_image\Form;

use Drupal\bookish_image\Ajax\BookishImageCKEditorCommand;
use Drupal\bookish_image\BookishImageFormTrait;
use Drupal\bookish_image\Plugin\ImageEffect\BookishImageScaleAndCrop;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for editing a file's bookish_image_data.
 *
 * Currently only used by CKEditor 5.
 */
class BookishImageForm extends FormBase {

  use BookishImageFormTrait;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bookish_image_image_form';
  }

  /**
   * Constructs a new BookishImageForm object.
   *
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ImageFactory $image_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->imageFactory = $image_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('image.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FileInterface $file = NULL) {
    $form['status_messages'] = [
      '#type' => 'status_messages',
    ];
    if (!$file) {
      $this->messenger()->addError('You cannot access this form.');
      return $form;
    }
    $image_style_storage = $this->entityTypeManager->getStorage('image_style');
    $user_input = $form_state->getUserInput();
    $image_style_name = isset($user_input['image_style']) ? (string) $user_input['image_style'] : '';
    if (!$image_style_name && $image_style_name !== 'none') {
      $image_style_name = $this->getRequest()->query->get('imageStyle', NULL);
    }

    $image_style = NULL;
    if ($image_style_name && $image_style_name !== 'none') {
      /** @var \Drupal\image\Entity\ImageStyle $image_style */
      $image_style = $image_style_storage->load($image_style_name);
      if (!$image_style) {
        $this->messenger()->addError('Could not load an image style use with the preview.');
        return $form;
      }
    }

    $image_data = _bookish_image_coerce_data(json_decode($file->bookish_image_data->getString(), TRUE));

    $unique_id = $file->id() . '-modal';
    $preview_id = 'bookish-image-preview-' . $unique_id;

    /** @var \Drupal\image\Entity\ImageStyle[] $image_styles */
    $image_styles = $image_style_storage->loadMultiple();
    ksort($image_styles);
    $bookish_styles = [];
    $other_styles = [];
    $other_styles_states = [['value' => 'none']];
    $bookish_scale_states = [];
    foreach ($image_styles as $name => $style) {
      $is_bookish = FALSE;
      $is_bookish_scale = FALSE;
      foreach ($style->getEffects() as $effect) {
        if ($effect instanceof BookishImageScaleAndCrop) {
          $is_bookish_scale = TRUE;
        }
        if (strpos(get_class($effect), 'bookish_image') !== FALSE) {
          $is_bookish = TRUE;
        }
      }
      if (!$is_bookish_scale) {
        $bookish_scale_states[] = ['value' => $name];
      }
      if ($is_bookish) {
        $bookish_styles[$name] = $style->label();
      }
      else {
        $other_styles[$name] = $style->label();
        $other_styles_states[] = ['value' => $name];
      }
    }

    $options = [
      'none' => 'None',
      'Bookish image styles' => $bookish_styles,
      'Other image styles' => $other_styles,
    ];

    $form['image_style'] = [
      '#title' => t('Image Style'),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $image_style ? $image_style->getName() : 'none',
      '#ajax' => static::getAjaxSettings($form, $preview_id),
      '#attributes' => [
        'class' => [
          'bookish-image-image-style',
          'bookish-image-image-style-' . $unique_id,
        ],
      ],
    ];

    $form['preview_wrapper'] = [
      '#type' => 'container',
      'preview' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['bookish-image-preview'],
        ],
        '#id' => $preview_id,
        'image' => static::getPreviewElement($file, $image_style, $image_data, $this->imageFactory),
      ],
    ];

    $form['#file'] = $file;
    $form['#image_style'] = $image_style;

    $form = $this->buildImageForm($form, $unique_id, $file, $this->imageFactory, $image_style_storage->load('bookish_image_thumbnail'));

    $form['bookish_image']['#states'] = [
      'invisible' => [
        ".bookish-image-image-style-$unique_id" => $other_styles_states,
      ],
    ];

    $form['bookish_image']['bookish_image_data']['zoom']['#states']['visible'][] = 'and';
    $form['bookish_image']['bookish_image_data']['zoom']['#states']['visible'][".bookish-image-image-style-$unique_id"] = $bookish_scale_states;

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'class' => [
          'bookish-image-form-actions',
        ],
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Update'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => [$this, 'submitAjax'],
        'event' => 'click',
        'disable-refocus' => TRUE,
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#cancel' => TRUE,
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [$this, 'cancelAjax'],
        'event' => 'click',
        'disable-refocus' => TRUE,
      ],
    ];

    return $form;
  }

  /**
   * Updates the preview element after an AJAX call.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The render array representing the preview.
   *
   * @see \Drupal\bookish_image\BookishImageFormTrait::getAjaxSettings
   */
  public static function updatePreview(array &$form, FormStateInterface &$form_state) {
    $file = $form['#file'];
    $image_data = json_decode($file->bookish_image_data->getString(), TRUE);
    $new_image_data = $form_state->getValue([
      'bookish_image',
      'bookish_image_data',
    ]);
    $image_data = array_merge(_bookish_image_coerce_data($image_data), _bookish_image_coerce_data($new_image_data));

    $form['preview_wrapper']['preview']['image'] = static::getPreviewElement($file, $form['#image_style'], $image_data, \Drupal::service('image.factory'));

    return $form['preview_wrapper']['preview'];
  }

  /**
   * Closes the modal after the save button has been pressed.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public static function submitAjax(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $form['#file'];
    $url = $file->getFileUri();
    /** @var \Drupal\image\ImageStyleInterface|NULL $image_style  */
    $image_style = $form['#image_style'];
    $image_style_name = NULL;
    if ($image_style) {
      $url = $image_style->buildUrl($url);
      $image_style_name = $image_style->getName();
    }
    else {
      $url = $file->createFileUrl(FALSE);
    }
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new BookishImageCKEditorCommand($file->uuid(), $url, $image_style_name));
    return $response;
  }

  /**
   * Closes the modal after the cancel button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public static function cancelAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    if (isset($button['#cancel'])) {
      return;
    }
    $file = $form['#file'];
    $new_image_data = $form_state->getValue([
      'bookish_image',
      'bookish_image_data',
    ]);
    _bookish_image_update_data($file, $new_image_data);
    _bookish_image_flush_image_styles($file->getFileUri());
    $file->save();
  }

}
