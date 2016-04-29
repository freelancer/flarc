<?php

/**
*/
final class ArcanistRobotFrameworkLintLinter extends ArcanistExternalLinter {
    protected $maxLineLength = 100;
    protected $ruleFile;

    public function getInfoName() {
        return 'Robot Framework Lint';
    }

    public function getInfoURI() {
        return 'https://github.com/boakley/robotframework-lint/wiki';
    }

    public function getInfoDescription() {
        return 'The pluggable linting utility for Robot Framework.';
    }

    public function getLinterName() {
        return 'RobotFrameworkLint';
    }

    public function getLinterConfigurationName() {
        return 'rflint';
    }

    public function getDefaultBinary() {
        return 'rflint';
    }

    protected function getMandatoryFlags() {
        $options = array();

        $options[] = '--configure';
        $options[] = 'LineTooLong:'.$this->maxLineLength;

        if ($this->ruleFile) {
            $options[] = '-R';
            $options[] = $this->ruleFile;
        }

        return $options;
    }

    public function getVersion() {
        list($stdout) = execx(
            '%C --version',
            $this->getExecutableCommand());

        $matches = null;
        if (!preg_match('/^(?P<version>\d+\.\d+\.\d+)$/', $stdout, $matches)) {
            return false;
        }

        return $matches['version'];
    }

    public function getInstallInstructions() {
        return pht(
            'Install `%s` with `%s`.',
            'robotframework-lint',
            'pip install robotframework-lint');
    }

    public function getUpdateInstructions() {
        return pht(
            'Update `%s` with `%s`.',
            'robotframework-lint',
            'pip install --upgrade robotframework-lint');
    }

    public function getLinterConfigurationOptions() {
        $options = array(
            'rflint.max-line-length' => array(
                'type' => 'optional int',
                'help' => pht(
                    'Adjust the maximum line length before a warning is '.
                    'raised. By default, a warning is raised on lines '.
                    'exceeding 100 characters.'
                ),
            ),
            'rflint.rule-file' => array(
                'type' => 'optional string',
                'help' => pht(
                    'Load additional lint rules from the specified file'
                ),
            ),
        );
        return $options + parent::getLinterConfigurationOptions();
    }

    public function setLinterConfigurationValue($key, $value) {
        switch ($key) {
            case 'rflint.max-line-length':
                $this->maxLineLength = $value;
                return;
            case 'rflint.rule-file':
                $this->ruleFile = $value;
                return;
            default:
                return parent::setLinterConfigurationValue($key, $value);
        }
    }

    protected function parseLinterOutput($path, $err, $stdout, $stderr) {
        $lines = phutil_split_lines($stdout, false);

        $messages = array();

        foreach ($lines as $line) {
            $matches = null;
            $regexp = sprintf(
                '/^%s:\\s*%s,\\s*%s:\\s*%s\\s*\\(%s\\)$/',
                '(?P<severity>.*)',
                '(?P<line>\\d+)',
                '(?P<char>\\d+)',
                '(?P<message>.*)',
                '(?P<code>.*)'
            );

            if (!preg_match($regexp, $line, $matches)) {
                continue;
            }

            foreach ($matches as $key => $match) {
                $matches[$key] = trim($match);
            }

            $severity = $this->getLintMessageSeverity($matches['code']);

            $message = id(new ArcanistLintMessage())
                ->setPath($path)
                ->setLine($matches['line'])
                ->setChar($matches['char'])
                ->setCode($matches['code'])
                ->setName($this->getLinterName())
                ->setDescription($matches['message'])
                ->setSeverity($severity);

            $messages[] = $message;
        }

        return $messages;
    }
}
