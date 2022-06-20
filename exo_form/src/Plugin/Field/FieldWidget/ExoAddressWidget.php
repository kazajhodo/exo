<?php

namespace Drupal\exo_form\Plugin\Field\FieldWidget;

use CommerceGuys\Addressing\AddressFormat\AddressFormatHelper;
use Drupal\address\Plugin\Field\FieldWidget\AddressDefaultWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\address\FieldHelper;
use Drupal\Core\Render\Element;

/**
 * Plugin implementation of the 'address_exo' widget.
 *
 * @FieldWidget(
 *   id = "address_exo",
 *   label = @Translation("eXo Address"),
 *   field_types = {
 *     "address"
 *   },
 *   provider = "address",
 * )
 */
class ExoAddressWidget extends AddressDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'wrapper' => 'fieldset',
      'force_address' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['wrapper'] = [
      '#type' => 'select',
      '#title' => $this->t('Wrapper'),
      '#default_value' => $this->getSetting('wrapper'),
      '#options' => $this->getWrapperOptions(),
      '#required' => TRUE,
    ];

    $element['force_address'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force display of address fields'),
      '#description' => $this->t('This will hide the country field and show the address fields.'),
      '#default_value' => $this->getSetting('force_address'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Wrapper type: @value', ['@value' => $this->getWrapperOptions()[$this->getSetting('wrapper')]]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#type'] = $this->getSetting('wrapper');
    if ($this->getSetting('force_address') && empty($element['#required']) && count($element['address']['#available_countries']) === 1) {
      $element['address']['#default_value']['country_code'] = key($element['address']['#available_countries']);
      $element['address']['#hide_country'] = TRUE;
      $element['address']['#after_build'][] = [
        get_class($this),
        'showAddressFieldByDefault',
      ];
      $element['address']['#element_validate'][] = [
        get_class($this),
        'validateAddressFieldByDefault',
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getWrapperOptions() {
    return [
      'fieldset' => $this->t('Fieldset'),
      'details' => $this->t('Details'),
      'container' => $this->t('Container'),
      'item' => $this->t('Item'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function validateAddressFieldByDefault(array $element, FormStateInterface $form_state) {
    $value = [];
    foreach (Element::children($element) as $key) {
      if (isset($element[$key]['#value'])) {
        $value[$key] = $element[$key]['#value'];
      }
    }

    $field_overrides = $element['#parsed_field_overrides'];
    /** @var \CommerceGuys\Addressing\AddressFormat\AddressFormat $address_format */
    $address_format = \Drupal::service('address.address_format_repository')->get($value['country_code']);
    $required_fields = AddressFormatHelper::getRequiredFields($address_format, $field_overrides);
    $has_value = FALSE;
    foreach ($required_fields as $field) {
      $property = FieldHelper::getPropertyName($field);
      if (!empty($element[$property]['#value'])) {
        $has_value = TRUE;
      }
    }
    if ($has_value) {
      foreach ($required_fields as $field) {
        $property = FieldHelper::getPropertyName($field);
        if (empty($element[$property]['#value'])) {
          $form_state->setError($element[$property], t('@label is required.', [
            '@label' => $element[$property]['#title'],
          ]));
        }
      }
    }
    else {
      $value['country_code'] = '';
    }

    $form_state->setValue($element['#parents'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public static function showAddressFieldByDefault(array $element, FormStateInterface $form_state) {
    if (!empty($element['#hide_country'])) {
      $value = $element['#value'];
      $element['country_code']['#access'] = FALSE;
      $field_overrides = $element['#parsed_field_overrides'];
      /** @var \CommerceGuys\Addressing\AddressFormat\AddressFormat $address_format */
      $address_format = \Drupal::service('address.address_format_repository')->get($value['country_code']);
      $required_fields = AddressFormatHelper::getRequiredFields($address_format, $field_overrides);
      foreach ($required_fields as $field) {
        $property = FieldHelper::getPropertyName($field);
        $element[$property]['#required'] = FALSE;
        $element[$property]['#validate_required'] = TRUE;
      }
    }
    return $element;
  }

}
