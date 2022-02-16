<?php

namespace Drupal\bookish_speed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure bookish_speed settings for this site.
 */
class BookishSpeedSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bookish_speed_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['bookish_speed.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bookish_speed.settings');

    $form['wait_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Wait time'),
      '#default_value' => $config->get('wait_time'),
      '#min' => 0,
      '#description' => $this->t('The maximum number of miliseconds to wait for CSS to load before showing new HTML.'),
      '#required' => TRUE,
    ];

    $form['exclude_regex'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exclude paths'),
      '#default_value' => $config->get('exclude_regex'),
      '#description' => $this->t('A regular expression matching paths to exclude. Should be in JavaScript compatible format.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('bookish_speed.settings')
      ->set('wait_time', $form_state->getValue('wait_time'))
      ->set('exclude_regex', $form_state->getValue('exclude_regex'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
