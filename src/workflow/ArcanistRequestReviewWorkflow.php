<?php

/**
 * Put revisions in `needs-review` state.
 */

final class ArcanistRequestReviewWorkflow extends ArcanistWorkflow {

  private $branch;
  private $revisions;


  public function getWorkflowName() {
    return 'request-review';
  }

  public function supportsToolset(ArcanistToolset $toolset) {
    return $toolset instanceof ArcanistArcToolset;
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
    Put revisions in `needs-review` state.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->addExample(pht('**request-review** [__revisions__]'))
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
        Put revisions in `needs-review` state.
EOTEXT
      );
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **request-review** [__revisions__]
      **request-review** D12345
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
    $use_new_conduit_engine = true;
    $this->readArguments();
    $this->findRevisions($use_new_conduit_engine);
    $this->requestReview($use_new_conduit_engine);

    $diff_ids = array_map(function ($revision) { return 'D'.$revision['id']; }, $this->revisions);
    $this->writeOkay('DONE', '');
    return 0;
  }

  public function run() {
    $this->readArguments();
    $this->findRevisions();
    $this->requestReview();

    $diff_ids = array_map(function ($revision) { return 'D'.$revision['id']; }, $this->revisions);
    echo pht('Done requesting review');
    return 0;
  }


  private function readArguments() {
    $repository_api = $this->getRepositoryAPI();

    $branch = $repository_api->getBranchName();
    if (!strlen($branch)) {
      $branch = $repository_api->getWorkingCopyRevision();
    }
    $branch = array($branch);

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

        $this->revisions = array_merge($this->revisions, $revisions['data']);
      }
    } else {
      $branch = $repository_api->getBranchName();
      if (!strlen($branch)) {
        $branch = $repository_api->getWorkingCopyRevision();
      }
      $branch = array($branch);
    }

    if (!count($this->revisions)) {
      throw new ArcanistUsageException(pht(
        "arc cannot identify which revision exists on branch '%s'. ".
        "Update the revision with recent changes to synchronize the ".
        "branch name and hashes or pass in revisions explicitly.",
        $this->branch));
    }
  }

  private function requestReview($use_new_conduit_engine = false) {
    echo pht("Requesting review for...\n");
    foreach ($this->revisions as $revision) {
      $rev_id = $revision['id'];
      $rev_title = $revision['fields']['title'];

      $this->writeInfo(pht('%s', "D{$rev_id}"), $rev_title);

      if ($use_new_conduit_engine) {
        $conduit_engine = $this->getConduitEngine();
        $conduit_future = $conduit_engine->newFuture(
          'differential.revision.edit',
          array(
            'transactions' => [
              [
                'type' => 'request-review',
                'value' => true,
              ],
            ],
            'objectIdentifier' => $revision['phid'],
          ));
        $conduit_future->resolve();
      } else {
        $this->getConduit()->callMethodSynchronous(
          'differential.revision.edit',
          array(
            'transactions' => [
              [
                'type' => 'request-review',
                'value' => true,
              ],
            ],
            'objectIdentifier' => $revision['phid'],
          ));
      }
    }
  }
}
