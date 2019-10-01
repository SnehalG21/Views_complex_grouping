<?php
namespace Drupal\views_complex_grouping\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
/**
 * Style plugin for the complex grouping.
 *
 * @ViewsStyle(
 *   id = "complex_grouping",
 *   title = @Translation("Complex Grouping"),
 *   help = @Translation("Limit the number of rows under each grouping field"),
 *   theme = "views_complex_grouping_leave",
 *   display_types = {"normal"}
 * )
 */
class ViewsComplexGrouping extends StylePluginBase
{
  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;
  

  /**
   * {@inheritdoc}
   */
  protected function defineOptions()
  {
    $options = parent::defineOptions();
    $options['complex_grouping'] = array('default' => '');
    return $options;
  }

  /**
   * Render the given style.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state)
  {
    parent::buildOptionsForm($form, $form_state);
//    $field_labels = $this->display->handler->get_field_labels();
    $field_labels = [
        'abc' => 'abc',
      'dvgh' => 'bdvg'
    ];
    $a = $form;
    foreach ($form['grouping'] as $index => $info) {
      $grouping_fields_default = (isset($this->options['grouping'][$index]['grouping-complex']['grouping-fields'])) ? $this->options['grouping'][$index]['grouping-complex']['grouping-fields'] : NULL;
      $grouping_limit_default = (isset($this->options['grouping'][$index]['grouping-complex']['grouping-limit'])) ? $this->options['grouping'][$index]['grouping-complex']['grouping-limit'] : 1;
      $grouping_offset_default = (isset($this->options['grouping'][$index]['grouping-complex']['grouping-offset'])) ? $this->options['grouping'][$index]['grouping-complex']['grouping-offset'] : 1;

      $form['grouping'][$index]['complex_grouping'] = array(
          '#type' => 'fieldset',
          '#title' => t('Limit and extra fields for grouping field Nr.!num', array('!num' => $index + 1)),
          '#collapsible' => TRUE,
          '#collapsed' => TRUE,
          'grouping-fields' => array(
              '#type' => 'select',
              '#multiple' => TRUE,
              '#title' => t('Selected'),
              '#options' => $field_labels,
              '#default_value' => $grouping_fields_default,
              '#description' => t('Select which fields will be displayed alongside the field Nr.!num', array('!num' => $index + 1)),
          ),
          'grouping-limit' => array(
              '#type' => 'textfield',
              '#title' => t('Items to display:'),
              '#default_value' => $grouping_limit_default,
              '#size' => 6,
              '#element_validate' => array('views_complex_grouping_validate'),
              '#description' => t('The number of rows to show under the field Nr.!num. Leave 0 to show all of them.', array('!num' => $index + 1)),
          ),
          'grouping-offset' => array(
              '#type' => 'textfield',
              '#title' => t('Offset:'),
              '#default_value' => $grouping_offset_default,
              '#size' => 6,
              '#element_validate' => array('views_complex_grouping_validate'),
              '#description' => t('The row to start on.'),
          ),
      );
    }
  }
}