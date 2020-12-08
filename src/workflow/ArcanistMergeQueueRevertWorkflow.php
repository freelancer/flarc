<?php

/**
 * Revert commit hash(es) on the API or GAF master branch.
 */

final class ArcanistMergeQueueRevertWorkflow extends ArcanistWorkflow {

  private $jobUrl;

  const JENKINS_URL = 'https://ci.tools.flnltd.com';
  const API_JOB_URL = '/job/API/job/mergequeue-revert';
  const GAF_JOB_URL = '/job/GAF/job/mergequeue-revert';

  const API_PHID = 'PHID-REPO-enzn73futkcv4eqfsgnz';
  const GAF_PHID = 'PHID-REPO-e7qvu3z7a3uhk7akjy7y';


  public function getWorkflowName() {
    return 'mergequeue-revert';
  }

  public function supportsToolset(ArcanistToolset $toolset) {
    return $toolset instanceof ArcanistArcToolset;
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Reverts the input commit hash(es) in the GAF/API master branch.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->addExample(pht('**mergequeue-revert** --reason="reason" [__commits__]'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
        $this->newWorkflowArgument('reason')
          ->setParameter('reason')
          ->setHelp(pht('The reason for the revert.')),
        $this->newWorkflowArgument('commits')
          ->setWildcard(true)
          ->setHelp(pht('Space separated individual commit hashes')),
      );
  }

  public function getArguments() {
    return array(
      'reason' => array(
          'param' => 'reason',
          'help' => pht(
              'The reason for the revert.'),
          ),
      'commits' => array(
          'help' => pht(
              'Space separated individual commit hashes.'),
          ),
    );
  }


  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Reverts the input commit hash(es) in the GAF/API master branch.
EOTEXT
      );
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **mergequeue-revert** --reason "reason here" commit1 commit2...
EOTEXT
      );
  }

  public function requiresWorkingCopy() {
    return true;
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function runWorkflow() {
    $submitter = $this->getCurrentPhabUserName();
    $reason = $this->getArgument('reason');
    $commit_ids = $this->getArgument('commits');

    if (empty($commit_ids)) {
      throw new ArcanistUsageException(
        pht('Please specify atleast one commit hash.'));
    }

    $commit_ids = $this->findCommits();
    $this->jenkinsSubmit($submitter, $reason, $commit_ids);
    echo pht('Done submitting to mergequeue-revert job.'), "\n";
    return 0;
  }

  public function getCurrentPhabUserName() {
    $conduit_engine = $this->getConduitEngine();
    $conduit_future = $conduit_engine->newFuture(
      'user.whoami',
      array());
    $user = $conduit_future->resolve();

    if (idx($user, 'userName')) {
      return $user['userName'];
    }

    throw new ArcanistUsageException('Cannot identify current user!');
  }

  private function findCommits($use_new_conduit_engine = true) {
    $commit_ids = $this->getArgument('commits');
    if (empty($commit_ids)) {
      throw new ArcanistUsageException('No commit hashes entered!');
    }

    if ($use_new_conduit_engine) {
        $conduit_engine = $this->getConduitEngine();
        $conduit_future = $conduit_engine->newFuture(
        'diffusion.commit.search',
        array(
            'constraints' => array(
                'identifiers' => $commit_ids,
            ),
        ));
        $commits_valid = $conduit_future->resolve();
    } else {
        $commits_valid = $this->getConduit()->callMethodSynchronous(
            'diffusion.commit.search',
            array(
              'constraints' => array(
                'ids' => array($commit_ids),
              ),
            ));
    }

    $commits_valid = $commits_valid['data'];
    if (count($commit_ids) != count($commits_valid)) {
      throw new ArcanistUsageException(
        'One or more commit hashes are invalid!');
    }

    $repo_phid = $commits_valid[0]['fields']['repositoryPHID'];
    if ($repo_phid == self::GAF_PHID) {
      $this->jobUrl = self::GAF_JOB_URL;
      $this->writeInfo(pht('      '),
        pht('Found commits on the GAF repository...'));
    } else {
      $this->jobUrl = self::API_JOB_URL;
      $this->writeInfo(pht('      '),
        pht('Found commits on the API repository...'));
    }
    $repo_phid_count = 0;
    foreach ($commits_valid as $commit) {
      if ($commit['fields']['repositoryPHID'] == $repo_phid) {
        $repo_phid_count++;
      }
    }

    if (count($commit_ids) != $repo_phid_count) {
      throw new ArcanistUsageException(
        'All commit hashes must be in the same repository!');
    }

    $commit_ids = array();
    foreach ($commits_valid as $commit) {
        array_push($commit_ids, $commit['fields']['identifier']);
    }
    return implode(',', $commit_ids);
  }

  private function jenkinsSubmit($submitter, $reason, $commit_ids) {
    $this->writeInfo(
      pht('SUBMIT'),
      pht('Submitting to merge queue revert...'));

    $home = getenv('HOME');
    try {
      $fli_config_path = $home.'/.fli.conf';
    } catch (Exception $e) {
      throw new ArcanistUsageException(
        pht('Failed to parse fli.conf. Make sure your fli conf is '.
          'defined in "%s".',
          $fli_config_path)
      );
    }

    $jenkins_config = parse_ini_file($fli_config_path);
    $username = $jenkins_config['username'];
    $token = $jenkins_config['token'];

    if (!($username && $token)) {
      $error_msg = 'Jenkins credentials not found. Please make sure '.
      'the username and token are defined in'.
      $fli_config_path;

      throw new ArcanistUsageException($error_msg);
    }

    $build_data = array(
      'commits' => "{$commit_ids}",
      'channel' => "@{$submitter}",
      'author' => "@{$submitter}",
      'reason' => "\"{$reason}\"",
    );

    $build_url = self::JENKINS_URL.$this->jobUrl.'/buildWithParameters';
    $build_data_http = http_build_query($build_data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $build_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $build_data);
    curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$token);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    print_r(curl_exec($ch));
  }
}
