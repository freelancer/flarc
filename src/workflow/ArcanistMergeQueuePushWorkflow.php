<?php

/**
 * Push diff into a remote branch.
 */

final class ArcanistMergeQueuePushWorkflow extends ArcanistWorkflow {

  private $branch;
  private $revisions;
  private $messageFile;
  private $skipTests;

  const JENKINS_URL = 'https://ci.tools.flnltd.com';
  const API_BUILD_URL = '/job/Developers/job/mergequeue-push';


  public function getWorkflowName() {
    return 'mergequeue-push';
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Push a diff into a remote branch.
EOTEXT
      );
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **mergequeue-push** [__options__] [__ref__]
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

  public function run() {
    $this->readArguments();

    $this->validate();

    $submitter = $this->getUserName();

    $this->findRevisions();
    $this->jenkinsSubmit($submitter);

    $diff_ids = array_map(function ($revision) { return 'D'.$revision['id']; }, $this->revisions);
    $branch_name = $submitter.'-'.implode(',', $diff_ids);
    echo pht('Done submitting to mergequeue-push job. Branch: %s', $branch_name), "\n";
    return 0;
  }

  private function readArguments() {
    $repository_api = $this->getRepositoryAPI();

    $branch = $this->getArgument('branch');
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

  public function validate() {
    $repository_api = $this->getRepositoryAPI();

    list($err) = $repository_api->execManualLocal(
      'rev-parse --verify %s',
      $this->branch);

    if ($err) {
      throw new ArcanistUsageException(
        pht("Branch '%s' does not exist.", $this->branch));
    }

    $this->requireCleanWorkingCopy();
  }

  private function findRevisions() {
    $this->revisions = array();
    $repository_api = $this->getRepositoryAPI();

    $revision_ids = $this->getArgument('revisions');
    if ($revision_ids) {
      if (count($revision_ids) > 1) {
        throw new ArcanistUsageException('Merge Queue Push for multiple diffs are disabled for now!');
      }

      foreach ($revision_ids as $revision_id) {
        $revision_id = $this->normalizeRevisionID($revision_id);
        $revisions = $this->getConduit()->callMethodSynchronous(
          'differential.query',
          array(
            'ids' => array($revision_id),
          ));
        if (!$revisions) {
          throw new ArcanistUsageException(pht(
            "No such revision '%s'!",
            "D{$revision_id}"));
        }
        $this->revisions = array_merge($this->revisions, $revisions);
      }
    } else {
      $this->revisions = $repository_api->loadWorkingCopyDifferentialRevisions(
        $this->getConduit(),
        array());
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
      $rev_title = $revision['title'];

      $message = $this->getConduit()->callMethodSynchronous(
        'differential.getcommitmessage',
        array(
          'revision_id' => $rev_id,
        ));

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

    $jenkins_config = parse_ini_file($fli_config_path);
    $username = $jenkins_config['username'];
    $token = $jenkins_config['token'];

    if (!($username && $token)) {
      throw new ArcanistUsageException(
        pht('Jenkins credentials not found. Please make sure '.
          'the username and token are defined in "$s".',
          $fli_config_path)
      );
    }

    $build_url = self::JENKINS_URL.self::API_BUILD_URL.'/buildWithParameters';
    $diff_ids = array_map(function ($revision) { return 'D'.$revision['id']; }, $this->revisions);
    $build_data = array(
      'author' => $submitter,
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
