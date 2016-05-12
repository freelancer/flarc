<?php

/**
 * @todo Submit this upstream after T27678.
 */
final class ArcanistDeprecatedClassXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1008;

  private $deprecatedClasses = array();

  public function getLintName() {
    return pht('Use of Deprecated Class');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function getLinterConfigurationOptions() {
    return parent::getLinterConfigurationOptions() + array(
      'xhpast.deprecated.class' => array(
        'type' => 'optional map<string, string | bool>',
        'help' => pht('Classes which should should be considered deprecated.'),
      ),
    );
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'xhpast.deprecated.class':
        $this->deprecatedClasses = $value;
        return;
      default:
        return parent::setLinterConfigurationValue($key, $value);
    }
  }

  public function process(XHPASTNode $root) {
    $map = $this->deprecatedClasses;
    foreach ($root->selectDescendantsOfType('n_CLASS_NAME') as $node) {
      $name = $node->getConcreteString();
      if (!isset($map[$name])
        || $node->getParentNode()->getTypeName() === 'n_CLASS_DECLARATION') {

        continue;
      }
      $message = pht('The `%s` class is deprecated.', $name);
      if (is_string($map[$name])) {
        $message .= ' '.$map[$name];
      }
      $this->raiseLintAtNode($node, $message);
    }
  }
}
