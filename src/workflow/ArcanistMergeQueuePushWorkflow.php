<?php

/**
 * Push diff into a remote branch.
 */

final class ArcanistMergeQueuePushWorkflow extends ArcanistWorkflow {

  private $branch;
  private $jobUrl;
  private $repoPHID;
  private $revisions;
  private $messageFile;
  private $skipTests;

  const JENKINS_URL = 'https://ci.tools.flnltd.com';
  const API_JOB_URL = '/job/Developers/job/api-mergequeue-push';
  const GAF_JOB_URL = '/job/Developers/job/mergequeue-push';

  const API_PHID = 'PHID-REPO-enzn73futkcv4eqfsgnz';
  const GAF_PHID = 'PHID-REPO-e7qvu3z7a3uhk7akjy7y';


  public function getWorkflowName() {
    return 'mergequeue-push';
  }

  public function supportsToolset(ArcanistToolset $toolset) {
    return $toolset instanceof ArcanistArcToolset;
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Push a diff in rGAF into a branch with format <jenkins username>-<diff ID>.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->addExample(pht('**mergequeue-push** [__revisions__]'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('revisions')
        ->setWildcard(true),
    );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Push diff into a branch with your jenkins username
EOTEXT
      );
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **mergequeue-push** [__revisions__]
      **mergequeue-push** D12345
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

  public function getArguments() {
    return array(
      '*' => 'revisions',
    );
  }

  public function runWorkflow() {
    $submitter = $this->getCurrentPhabUserName();
    $use_new_conduit_engine = true;
    $this->readArguments();
    $this->findRevisions($use_new_conduit_engine);
    $this->jenkinsSubmit($submitter);

    $diff_ids = array_map(function ($revision) { return 'D'.$revision['id']; }, $this->revisions);
    $branch_name = $submitter.'-'.implode(',', $diff_ids);
    echo pht('Done submitting to mergequeue-push job. Branch: %s', $branch_name), "\n";
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

  public function run() {
    $submitter = $this->getUserName();
    $this->readArguments();
    $this->findRevisions();
    $this->jenkinsSubmit($submitter);

    $diff_ids = array_map(function ($revision) { return 'D'.$revision['id']; }, $this->revisions);
    $branch_name = $submitter.'-'.implode(',', $diff_ids);
    echo pht('Done submitting to mergequeue-push job. Branch: %s', $branch_name), "\n";
    return 0;
  }


  private function readArguments() {
    $repository_api = $this->getRepositoryAPI();

    if (empty($branch)) {
      $branch = $repository_api->getBranchName();
      if (!strlen($branch)) {
        $branch = $repository_api->getWorkingCopyRevision();
      }
      $branch = array($branch);
    }

    if (count($branch) !== 1) {
      throw new ArcanistUsageException(
        pht('Specify exactly one branch to push changes from.'));
    }

    $this->branch = head($branch);
    return $branch;
  }

  private function findRevisions($use_new_conduit_engine = false) {
    $this->revisions = array();
    $repository_api = $this->getRepositoryAPI();

    $revision_ids = $this->getArgument('revisions');
    if ($revision_ids) {
      if (count($revision_ids) > 1) {
        throw new ArcanistUsageException('Merge Queue Push for multiple diffs are disabled for now!');
      }

      foreach ($revision_ids as $revision_id) {
        $revision_id = $this->normalizeRevisionID($revision_id);

        if ($use_new_conduit_engine) {
          $conduit_engine = $this->getConduitEngine();
          $conduit_future = $conduit_engine->newFuture(
            'differential.revision.search',
            array(
              'constraints' => array(
                'ids' => array((int)$revision_id),
              ),
            ));
          $revisions = $conduit_future->resolve();
        } else {
          $revisions = $this->getConduit()->callMethodSynchronous(
            'differential.revision.search',
            array(
              'constraints' => array(
                'ids' => array((int)$revision_id),
              ),
            ));
        }

        if (!idx($revisions, 'data')) {
          throw new ArcanistUsageException(pht(
            "No such revision '%s'!",
            "D{$revision_id}"));
        }

        if (!$this->repoPHID) {
          $this->repoPHID = $revisions['data'][0]['fields']['repositoryPHID'];
          $this->jobUrl = ($this->repoPHID == self::API_PHID)
            ? self::API_JOB_URL
            : self::GAF_JOB_URL;
        }

        if ($revisions['data'][0]['fields']['repositoryPHID'] != $this->repoPHID) {
          throw new ArcanistUsageException(pht(
            '%s must be in the same repository as the other revisions.',
            "D{$revision_id}"));
        }

        $this->revisions = array_merge($this->revisions, $revisions['data']);
      }
    } else {
      throw new ArcanistUsageException('Revisions are required parameters!');
    }

    if (!count($this->revisions)) {
      throw new ArcanistUsageException(pht(
        "arc cannot identify which revision exists on branch '%s'.".
        "Update the revision with recent changes to synchronize the ".
        "branch name and hashes, ".
        "or use '%s' to amend the commit message at HEAD, or use ".
        "'%s' to select a revision explicitly.",
        $this->branch,
        'arc amend',
        '--revision <id>'));
    }

    foreach ($this->revisions as $revision) {
      $rev_id = $revision['id'];
      $rev_title = $revision['fields']['title'];

      if ($use_new_conduit_engine) {
        $conduit_future = $conduit_engine->newFuture(
          'differential.getcommitmessage',
          array(
            'revision_id' => $rev_id,
          ));
        $message = $conduit_future->resolve();
      } else {
        $message = $this->getConduit()->callMethodSynchronous(
          'differential.getcommitmessage',
          array(
            'revision_id' => $rev_id,
          ));
      }

      $this->messageFile = new TempFile();
      Filesystem::writeFile($this->messageFile, $message);

      echo pht(
        "Submitting revision '%s'...",
        "D{$rev_id}: {$rev_title}")."\n";
    }
  }

  private function jenkinsSubmit($submitter) {
    $this->writeInfo(
      pht('SUBMIT'),
      pht('Submitting to merge queue push...'));

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

    $jenkins_config = parse_ini_file($fli_config_path, true);
    $username = $jenkins_config['jenkins']['username'];
    $token = $jenkins_config['jenkins']['token'];

    if (!($username && $token)) {
      throw new ArcanistUsageException(
        pht('Jenkins credentials not found. Please make sure '.
          'the username and token are defined in "$s".',
          $fli_config_path)
      );
    }

    $build_url = self::JENKINS_URL.$this->jobUrl.'/buildWithParameters';
    $diff_ids = array_map(function ($revision) { return 'D'.$revision['id']; }, $this->revisions);
    $build_data = array(
      'username' => $submitter,
      'diff' => implode(',', $diff_ids),
      'channel' => "@{$submitter}",
    );

    $build_data_http = http_build_query($build_data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $build_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $build_data);
    curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$token);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
  }
}
