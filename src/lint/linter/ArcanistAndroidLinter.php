<?php

// This code is copied from https://github.com/DheerendraRathor/Arcanist-Android-Lint/blob/master/.arclint
// With some minor config changes for fl-gaf
// Currently lint and checkstyle are enabled


/*

Copyright 2012-2015 iMobile3, LLC. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, is permitted provided that adherence to the following
conditions is maintained. If you do not agree with these terms,
please do not use, install, modify or redistribute this software.

1. Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY IMOBILE3, LLC "AS IS" AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO
EVENT SHALL IMOBILE3, LLC OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

/**
 * Uses Android Lint to detect various errors in Java code. To use this linter,
 * you must install the Android SDK and configure which codes you want to be
 * reported as errors, warnings and advice.
 *
 * @group linter
 */
final class ArcanistAndroidLinter extends ArcanistLinter {
  private $gradleModules = array('app');
  private $lintEnabled = true;
  private $findbugsEnabled = true;
  private $checkstyleEnabled = true;
  private $pmdEnabled = true;
  private $project = null;

  public function getInfoName() {
    return 'Android';
  }

  public function getLinterConfigurationName() {
    return 'android';
  }

  public function getLinterConfigurationOptions() {
    $options = parent::getLinterConfigurationOptions();

    $options['modules'] = array(
      'type' => 'optional list<string>',
      'help' => pht('List of all gradle modules. Default is [\'app\']'),
    );

    $options['lint'] = array(
      'type' => 'optional bool',
      'help' => pht('Enable lint. Enabled by default'),
    );

    $options['findbugs'] = array(
      'type' => 'optional bool',
      'help' => pht('Enable findBugs. Enabled by default'),
    );

    $options['checkstyle'] = array(
      'type' => 'optional bool',
      'help' => pht('Enable Checkstyle. Enabled by default'),
    );

    $options['pmd'] = array(
      'type' => 'optional bool',
      'help' => pht('Enable pmd. Enabled by default'),
    );

    $options['project'] = array(
      'type' => 'optional string',
      'help' => pht('project directory. current directory by default'),
    );

    return $options;
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'modules':
        $this->gradleModules = $value;
        return;
      case 'lint':
        $this->lintEnabled = $value;
        return;
      case 'findbugs':
        $this->findbugsEnabled = $value;
        return;
      case 'checkstyle':
        $this->checkstyleEnabled = $value;
        return;
      case 'pmd':
        $this->pmdEnabled = $value;
        return;
      case 'project':
        $this->project = trim($value);
        return;
    }
    parent::setLinterConfigurationValue($key, $value);
  }

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'AndroidLint';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array();
  }

  protected function shouldLintDirectories() {
    return true;
  }

  public function lintPath($path) {
    $lint_xml_files = $this->runGradle($path);

    $lint_files       = $lint_xml_files[0];
    $findbugs_files   = $lint_xml_files[1];
    $checkstyle_files = $lint_xml_files[2];
    $pmd_files        = $lint_xml_files[3];

    $absolute_path = $this->getEngine()->getFilePathOnDisk($path);

    $lint_messages       = $this->getGradleLintMessages($lint_files, $absolute_path);
    $findbugs_messages   = $this->getFindbugsMessages($findbugs_files, $absolute_path);
    $pmd_messages        = $this->getPMDMessages($pmd_files, $absolute_path);
    $checkstyle_messages = $this->getCheckStyleMessages($checkstyle_files, $absolute_path);

    foreach ($lint_messages as $message) {
      $this->addLintMessage($message);
    }
    foreach ($findbugs_messages as $message) {
      $this->addLintMessage($message);
    }
    foreach ($pmd_messages as $message) {
      $this->addLintMessage($message);
    }
    foreach ($checkstyle_messages as $message) {
      $this->addLintMessage($message);
    }

    putenv('_JAVA_OPTIONS');
  }

  private function getProjectPath($root, $project) {
    if (empty($project)) {
      return $root;
    }
    if (substr($project, 0, 1) == '/') {
      return $project;
    }
    return realpath(implode('/', [$root, $project]));
  }

  private function shouldProcess($path, $output_path) {
    if (file_exists($output_path)) {
      $output_accessed_time = fileatime($output_path);
      $path_modified_time   = Filesystem::getModifiedTime($path);
      if ($path_modified_time > $output_accessed_time) {
        unlink($output_path);
        return true;
      }
    } else {
      return true;
    }
    return false;
  }

  /**
   * Run gradle command if certain lint is enabled and return the report path to be parsed.
   *
   * NOTE: gradle command is NOT executed if nothing changes in the path. A old version of report is returned.
   *
   * @param $path
   * @return array[]
   */
  private function runGradle($path) {
    $root = $this->getEngine()->getWorkingCopy()->getProjectRoot();
    $project_path = $this->getProjectPath($root, $this->project);
    $gradle_bin = implode('/', [$project_path, 'gradlew']);

    if (!file_exists($gradle_bin)) {
      throw new ArcanistUsageException('gradlew does not exists in project root. Please check project settings in arclint.');
    }

    $cwd = getcwd();
    chdir($root);
    $lint_command     = '';
    $output_paths     = array();
    $findbugs_paths   = array();
    $checkstyle_paths = array();
    $pmd_paths        = array();

    foreach ($this->gradleModules as $module) {
      if ($this->lintEnabled) {
        $output_path = $project_path.'/'.str_replace(':', '/', $module);
        $output_path .= '/build/reports/lint-results-baseRelease.xml';
        $output_paths[] = $output_path;
        if ($this->shouldProcess($path, $output_path)) {
          $lint_command .= 'lintBaseRelease ';
        }
      }
      if ($this->findbugsEnabled) {
        $findbugs_output_path = $project_path.'/'.str_replace(':', '/', $module);
        $findbugs_output_path .= '/build/reports/findbugs/findbugs.xml';
        $findbugs_paths[] = $findbugs_output_path;
        if ($this->shouldProcess($path, $findbugs_output_path)) {
          $lint_command .= ':'.$module.':findbugs ';
        }
      }
      if ($this->checkstyleEnabled) {
        $checkstyle_output_path = $project_path.'/'.str_replace(':', '/', $module);
        $checkstyle_output_path .= '/build/reports/checkstyle/checkstyle.xml';
        $checkstyle_paths[] = $checkstyle_output_path;
        if ($this->shouldProcess($path, $checkstyle_output_path)) {
          $lint_command .= ':'.$module.':checkStyle ';
        }
      }
      if ($this->pmdEnabled) {
        $pmd_output_path = $project_path.'/'.str_replace(':', '/', $module);
        $pmd_output_path .= '/build/reports/pmd/pmd.xml';
        $pmd_paths[] = $pmd_output_path;
        if ($this->shouldProcess($path, $pmd_output_path)) {
          $lint_command .= ':'.$module.':pmd ';
        }
      }

      if (!empty($lint_command)) {
        $final_lint_command = "$gradle_bin -p $project_path $lint_command";
        echo "Linting $path \n";
        echo "Executing: $final_lint_command \n";
        exec_manual($final_lint_command);
      }
    }

    chdir($cwd);

    foreach ($output_paths as $output_path) {
      if (!file_exists($output_path)) {
        throw new ArcanistUsageException("Error executing gradle command!\n $output_path could not be found.");
      }
    }

    return array(
      $output_paths,
      $findbugs_paths,
      $checkstyle_paths,
      $pmd_paths,
    );
  }

  private function getGradleLintMessages($lint_files, $absolute_path) {
    $messages = array();
    foreach ($lint_files as $file) {
      $filexml = simplexml_load_string(file_get_contents($file));

      foreach ($filexml as $issue) {
        $loc_attrs = $issue->location->attributes();
        $filename  = (string)$loc_attrs->file;

        if ($filename != $absolute_path) {
          continue;
        }

        $issue_attrs = $issue->attributes();

        $message = new ArcanistLintMessage();
        $message->setPath($filename);
        // Line number and column are irrelevant for
        // artwork and other assets
        if (isset($loc_attrs->line)) {
          $message->setLine((int)$loc_attrs->line);
        }
        if (isset($loc_attrs->column)) {
          $message->setChar((int)$loc_attrs->column);
        }
        $message->setName((string)$issue_attrs->id);
        $message->setCode((string)$issue_attrs->category);
        $message->setDescription(preg_replace('/^\[.*?\]\s*/', '', $issue_attrs->message));

        // Setting Severity
        if ($issue_attrs->severity == 'Error' || $issue_attrs->severity == 'Fatal') {
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
        } else if ($issue_attrs->severity == 'Warning') {
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
        } else {
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
        }

        $messages[$message->getPath().':'.$message->getLine().':'.$message->getChar().':'.$message->getName().':'.$message->getDescription()] = $message;
      }
    }

    return $messages;
  }

  private function getFindbugsMessages($findbugs_files, $absolute_path) {
    $messages = array();
    foreach ($findbugs_files as $file) {
      $filexml = simplexml_load_string(file_get_contents($file));

      $bug_instances = $filexml->xpath('//BugInstance');
      foreach ($bug_instances as $bug_instance) {
        $source_line      = $bug_instance->SourceLine;
        $source_line_attrs = $source_line->attributes();
        $path            = (string)$source_line_attrs->sourcepath;
        if (strpos($absolute_path, $path) === false) {
          continue;
        }

        $bug_instance_attrs = $bug_instance->attributes();

        $message = new ArcanistLintMessage();
        $message->setPath($absolute_path);
        if (isset($source_line_attrs->start)) {
          $message->setLine((int)$source_line_attrs->start);
        }
        $message->setName((string)$bug_instance_attrs->type);
        $message->setCode((string)$bug_instance_attrs->category);
        $message->setDescription(preg_replace('/^\[.*?\]\s*/', '', (string)$bug_instance->LongMessage));

        // Setting Severity
        $rank = (int)$bug_instance_attrs->rank;
        if ($rank >= 1 && $rank <= 4) {
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
        } else if ($rank > 4 && $rank < 15) {
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
        } else {
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
        }

        $messages[$message->getPath().':'.$message->getLine().':'.$message->getName().':'.$message->getDescription()] = $message;
      }
    }

    return $messages;
  }

  private function getPMDMessages($pmd_files, $absolute_path) {
    $messages = array();
    foreach ($pmd_files as $file) {
      $filexml = simplexml_load_string(file_get_contents($file));

      $violations_nodes = $filexml->xpath('//file[@name="'.$absolute_path.'"]');
      foreach ($violations_nodes as $violations_node) {
        foreach ($violations_node->children() as $violation) {
          $text       = (string)$violation;
          $attributes = $violation->attributes();

          $message = new ArcanistLintMessage();
          $message->setPath($absolute_path);
          if (isset($attributes->beginline)) {
            $message->setLine((int)$attributes->beginline);
          }
          if (isset($attributes->begincolumn)) {
            $message->setChar((int)$attributes->begincolumn);
          }
          $message->setName((string)$attributes->rule);
          $message->setCode((string)$attributes->ruleset);
          $message->setDescription(trim(preg_replace('/^\[.*?\]\s*/', '', $text)));

          $priority = (int)$attributes->priority;

          if ($priority == 1 || $priority == 2) {
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
          } else if ($priority == 3 || $priority == 4) {
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
          } else {
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
          }

          $messages[$message->getPath().':'.$message->getLine().':'.$message->getChar().':'.$message->getDescription()] = $message;
        }
      }
    }

    return $messages;
  }

  private function getCheckStyleMessages($checkstyle_files, $absolute_path) {
    $messages = array();
    foreach ($checkstyle_files as $file) {
      $filexml = simplexml_load_string(file_get_contents($file));

      $error_nodes = $filexml->xpath('//file[@name="'.$absolute_path.'"]');
      foreach ($error_nodes as $error_node) {
        foreach ($error_node->children() as $error) {
          $attributes = $error->attributes();

          $message = new ArcanistLintMessage();
          $message->setPath($absolute_path);
          if (isset($attributes->line)) {
            $message->setLine((int)$attributes->line);
          }
          if (isset($attributes->column)) {
            $message->setChar((int)$attributes->column);
          }
          $message->setName('Checkstyle');
          $source          = (string)$attributes->source;
          $source_packaage = explode('.', $source);
          $code            = end($source_packaage);

          $message->setCode($code);
          $message->setDescription(trim(preg_replace('/^\[.*?\]\s*/', '', $attributes->message)));

          $priority = (string)$attributes->severity;

          // Treat warning as error to align with CI. Some warning doesn't show up by default config.
          if ($priority == 'error' || $priority == 'warning') {
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
          } else if ($priority == 'info') {
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
          } else {
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
          }
          $messages[$message->getPath().':'.$message->getLine().':'.$message->getChar().':'.$message->getName().':'.$message->getDescription()] = $message;
        }
      }
    }

    return $messages;
  }
}
