<?php

/**
 * Naming hook to override arcanist naming convention with Freelancer
 * naming convention.
 *
 * The Freelancer naming convention is as below:
 * ```lang=php
 * class UpperCamelCase {
 *     const UPPER_SNAKE_CASE = 1
 *     private $lowerCamelCase;
 *     public function lowerCamelCase($lowerCamelCase) {
 *         $lowerCamelCase = 1;
 *     }
 * }
 * interface UpperCamelCase {}
 * function snake_case () {
 *     $lowerCamelCase = 1;
 * }
 * ```
 * There is no naming convention for globals since their usages are
 * discouraged.
 */
final class FlarcXHPASTLintNamingHook
  extends ArcanistXHPASTLintNamingHook {

  public function lintSymbolName($type, $name, $default) {
    switch ($type) {
      case 'parameter':
      case 'variable':
        if (self::isLowerCamelCase(self::stripPHPVariable($name))) {
          return null;
        } else {
          return pht(
            'Follow naming conventions: variables '.
            'should be named using `%s`.',
            'lowerCamelCase');
        }
      default:
        return $default;
    }
  }

}
