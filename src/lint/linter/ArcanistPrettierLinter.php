<?php

final class ArcanistPrettierLinter extends ArcanistExternalLinter {
    public function getInfoName(): string {
        return 'Prettier';
    }

    public function getInfoURI(): string {
        return 'https://prettier.io/';
    }

    public function getInfoDescription(): string {
        return pht('An opinionated code formatter with canonicalized AST-derived output');
    }

    public function getLinterName(): string {
        return 'PRETTIER';
    }

    public function getLinterConfigurationName(): string {
        return 'prettier';
    }

    public function getDefaultBinary(): string {
        return 'prettier';
    }

    public function getInstallInstructions(): string {
        return pht(
            'Install prettier locally using `%s`.',
            'yarn');
    }

    public function getVersion() {
        // Many node packages follow this same convention
        list($stdout) = execx('%C --version', $this->getExecutableCommand());

        $matches = [];
        $regex = '/^(?<version>\d\.\d\.\d)$/';

        if (preg_match($regex, $stdout, $matches)) {
            return $matches['version'];
        } else {
            return false;
        }
    }

    public function shouldExpectCommandErrors(): bool {
        return true;
    }

    public function getLinterPriority() {
      return 10;
    }

    /**
     * Copyright 2018 Pinterest, Inc.
     *
     * Licensed under the Apache License, Version 2.0 (the "License");
     * you may not use this file except in compliance with the License.
     * You may obtain a copy of the License at
     *
     *     http://www.apache.org/licenses/LICENSE-2.0
     *
     * Unless required by applicable law or agreed to in writing, software
     * distributed under the License is distributed on an "AS IS" BASIS,
     * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
     * See the License for the specific language governing permissions and
     * limitations under the License.
     */
    protected function parseLinterOutput($path, $err, $stdout, $stderr) {
        if ($err) {
            return false;
        }

        if ($this->getData($path) == $stdout) {
            return array();
        }

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setSeverity(ArcanistLintSeverity::SEVERITY_AUTOFIX);
        $message->setName('Prettier Format');
        $message->setLine(1);
        $message->setCode($this->getLinterName());
        $message->setChar(1);
        $message->setDescription('This file has not been prettier-ified');
        $message->setOriginalText($this->getData($path));
        $message->setReplacementText($stdout);
        $message->setBypassChangedLineFiltering(true);

        $messages = [$message];

        return $messages;
    }
}
