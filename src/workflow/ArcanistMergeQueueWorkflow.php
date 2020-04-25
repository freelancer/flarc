<?php

/**
 * Puts a diff into the Merge Queue.
 */

final class ArcanistMergeQueueWorkflow extends ArcanistWorkflow {

  private $branch;
  private $revisions;
  private $messageFile;
  private $skipTests;

  const ONTO_BRANCH = 'master'; // Only merge to master
  const JENKINS_URL = 'https://ci.tools.flnltd.com';
  const API_BUILD_URL = '/job/GAF/job/mergequeue-submit';


  public function getWorkflowName() {
    return 'mergequeue';
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Publish an accepted revision to Merge Queue after review.

EOTEXT
      );
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **mergequeue** [__options__] [__ref__]
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
      'skip-tests' => array(
        'help' => pht(
          'Skip tests on the merge queue.'),
        ),
      '*' => 'revisions',
    );
  }

  public function run() {
    $this->readArguments();

    $this->validate();

    $submitter = $this->getUserName();

    $this->findRevisions();
    $this->jenkinsSubmit($submitter);

    echo pht('Done submitting to Merge Queue.'), "\n";
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
        pht('Specify exactly one branch to land changes from.'));
    }

    $this->skipTests = $this->getArgument('skip-tests');
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
      $this->checkRevisionState($revision);

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

      $diff_phid = idx($revision, 'activeDiffPHID');
      if ($diff_phid) {
        $this->checkForBuildables($diff_phid);
      }
    }
  }

  private function jenkinsSubmit($submitter) {
    $this->writeInfo(
      pht('SUBMIT'),
      pht('Submitting to merge queue...'));

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
    $gaf_diff_ids = array_map(function ($revision) { return 'D'.$revision['id']; }, $this->revisions);
    $build_data = array(
      'author' => $submitter,
      'gafDiffIds' => implode(',', $gaf_diff_ids),
    );

    if ($this->skipTests) {
      $build_data['skipTest'] = 'true';
    }

    $build_data_http = http_build_query($build_data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $build_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $build_data);
    curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$token);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
  }

  private function checkRevisionState($revision) {
    $rev_status = $revision['status'];
    $rev_id = $revision['id'];
    $rev_title = $revision['title'];
    $rev_auxiliary = idx($revision, 'auxiliary', array());

    $full_name = pht('D%d: %s', $rev_id, $rev_title);

    if ($revision['authorPHID'] != $this->getUserPHID()) {
      $other_author = $this->getConduit()->callMethodSynchronous(
        'user.query',
        array(
          'phids' => array($revision['authorPHID']),
        ));
      $other_author = ipull($other_author, 'userName', 'phid');
      $other_author = $other_author[$revision['authorPHID']];
      $ok = phutil_console_confirm(pht(
        "This branch has revision '%s' but you are not the author. ".
        "Submit this ".
        "revision by %s to Merge Queue?",
        $full_name,
        $other_author));
      if (!$ok) {
        throw new ArcanistUserAbortException();
      }
    }

    $state_warning = null;
    $state_header = null;
    if ($rev_status == ArcanistDifferentialRevisionStatus::CHANGES_PLANNED) {
      $state_header = pht('REVISION HAS CHANGES PLANNED');
      $state_warning = pht(
        'The revision you are submitting to Merge Queue ("%s") is '.
        'currently in the "%s" state, '.
        'indicating that you expect to revise it before moving forward.'.
        "\n\n".
        'Normally, you should resubmit it for review and wait until it is '.
        '"%s" by reviewers before you continue.'.
        "\n\n".
        'To resubmit the revision for review, either: update the revision '.
        'with revised changes; or use "Request Review" from the web interface.',
        $full_name,
        pht('Changes Planned'),
        pht('Accepted'));
    } else if ($rev_status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      $state_header = pht('REVISION HAS NOT BEEN ACCEPTED');
      $state_warning = pht(
        'The revision you are submitting to Merge Queue ("%s") has '.
        'not been "%s" by reviewers.',
        $full_name,
        pht('Accepted'));
    }

    if ($state_warning !== null) {
      id(new PhutilConsoleBlock())
        ->addParagraph(tsprintf('<bg:yellow>** %s **</bg>', $state_header))
        ->addParagraph(tsprintf('%B', $state_warning))
        ->draw();

        throw new ArcanistUsageException(
          pht('Revision is not in an accepted state.'));
    }

    if ($rev_auxiliary) {
      $phids = idx($rev_auxiliary, 'phabricator:depends-on', array());
      if ($phids) {
        $dep_on_revs = $this->getConduit()->callMethodSynchronous(
          'differential.query',
           array(
             'phids' => $phids,
             'status' => 'status-open',
           ));

        $open_dep_revs = array();
        foreach ($dep_on_revs as $dep_on_rev) {
          $dep_on_rev_id = $dep_on_rev['id'];
          $dep_on_rev_title = $dep_on_rev['title'];
          $dep_on_rev_status = $dep_on_rev['status'];
          $open_dep_revs[$dep_on_rev_id] = $dep_on_rev_title;
        }

        if (!empty($open_dep_revs)) {
          $open_revs = array();
          foreach ($open_dep_revs as $id => $title) {
            $open_revs[] = '    - D'.$id.': '.$title;
          }
          $open_revs = implode("\n", $open_revs);

          echo pht(
            "Revision '%s' depends on open revisions:\n\n%s",
            "D{$rev_id}: {$rev_title}",
            $open_revs);

          $ok = phutil_console_confirm(pht('Continue anyway?'));
          if (!$ok) {
            throw new ArcanistUserAbortException();
          }
        }
      }
    }
  }

    /**
   * Check if a diff has a running or failed buildable, and prompt the user
   * before landing if it does.
   */
  private function checkForBuildables($diff_phid) {
    // Try to use the more modern check which respects the "Warn on Land"
    // behavioral flag on build plans if we can. This newer check won't work
    // unless the server is running code from March 2019 or newer since the
    // API methods we need won't exist yet. We'll fall back to the older check
    // if this one doesn't work out.
    try {
      $this->checkForBuildablesWithPlanBehaviors($diff_phid);
      return;
    } catch (ArcanistUsageException $usage_ex) {
      throw $usage_ex;
    } catch (Exception $ex) {
      // Continue with the older approach, below.
    }

    // NOTE: Since Harbormaster is still beta and this stuff all got added
    // recently, just bail if we can't find a buildable. This is just an
    // advisory check intended to prevent human error.

    try {
      $buildables = $this->getConduit()->callMethodSynchronous(
        'harbormaster.querybuildables',
        array(
          'buildablePHIDs' => array($diff_phid),
          'manualBuildables' => false,
        ));
    } catch (ConduitClientException $ex) {
      return;
    }

    if (!$buildables['data']) {
      // If there's no corresponding buildable, we're done.
      return;
    }

    $console = PhutilConsole::getConsole();

    $buildable = head($buildables['data']);

    if ($buildable['buildableStatus'] == 'passed') {
      $console->writeOut(
        "**<bg:green> %s </bg>** %s\n",
        pht('BUILDS PASSED'),
        pht('Harbormaster builds for the active diff completed successfully.'));
      return;
    }
    switch ($buildable['buildableStatus']) {
      case 'building':
        $message = pht(
          'Harbormaster is still building the active diff for this revision.');
        break;
      case 'failed':
        $message = pht(
          'Harbormaster failed to build the active diff for this revision.');
        break;
      default:
        // If we don't recognize the status, just bail.
        return;
    }

    $builds = $this->queryBuilds(
      array(
        'buildablePHIDs' => array($buildable['phid']),
      ));

    $console->writeOut($message."\n\n");

    $builds = msortv($builds, 'getStatusSortVector');
    foreach ($builds as $build) {
      $ansi_color = $build->getStatusANSIColor();
      $status_name = $build->getStatusName();
      $object_name = $build->getObjectName();
      $build_name = $build->getName();

      echo tsprintf(
        "    **<bg:".$ansi_color."> %s </bg>** %s: %s\n",
        $status_name,
        $object_name,
        $build_name);
    }

    $console->writeOut(
      "\n%s\n\n    **%s**: __%s__",
      pht('You can review build details here:'),
      pht('Harbormaster URI'),
      $buildable['uri']);

    throw new ArcanistUsageException('Harbormaster builds ongoing or failed.');
  }

  private function checkForBuildablesWithPlanBehaviors($diff_phid) {
    // TODO: These queries should page through all results instead of fetching
    // only the first page, but we don't have good primitives to support that
    // in "master" yet.

    $this->writeInfo(
      pht('BUILDS'),
      pht('Checking build status...'));

    $raw_buildables = $this->getConduit()->callMethodSynchronous(
      'harbormaster.buildable.search',
      array(
        'constraints' => array(
          'objectPHIDs' => array(
            $diff_phid,
          ),
          'manual' => false,
        ),
      ));

    if (!$raw_buildables['data']) {
      return;
    }

    $buildables = $raw_buildables['data'];
    $buildable_phids = ipull($buildables, 'phid');

    $raw_builds = $this->getConduit()->callMethodSynchronous(
      'harbormaster.build.search',
      array(
        'constraints' => array(
          'buildables' => $buildable_phids,
        ),
      ));

    if (!$raw_builds['data']) {
      return;
    }

    $builds = array();
    foreach ($raw_builds['data'] as $raw_build) {
      $build_ref = ArcanistBuildRef::newFromConduit($raw_build);
      $build_phid = $build_ref->getPHID();
      $builds[$build_phid] = $build_ref;
    }

    $plan_phids = mpull($builds, 'getBuildPlanPHID');
    $plan_phids = array_values($plan_phids);

    $raw_plans = $this->getConduit()->callMethodSynchronous(
      'harbormaster.buildplan.search',
      array(
        'constraints' => array(
          'phids' => $plan_phids,
        ),
      ));

    $plans = array();
    foreach ($raw_plans['data'] as $raw_plan) {
      $plan_ref = ArcanistBuildPlanRef::newFromConduit($raw_plan);
      $plan_phid = $plan_ref->getPHID();
      $plans[$plan_phid] = $plan_ref;
    }

    $ongoing_builds = array();
    $failed_builds = array();

    $builds = msortv($builds, 'getStatusSortVector');
    foreach ($builds as $build_ref) {
      $plan = idx($plans, $build_ref->getBuildPlanPHID());
      if (!$plan) {
        continue;
      }

      $plan_behavior = $plan->getBehavior('arc-land', 'always');
      $if_building = ($plan_behavior == 'building');
      $if_complete = ($plan_behavior == 'complete');
      $if_never = ($plan_behavior == 'never');

      // If the build plan "Never" warns when landing, skip it.
      if ($if_never) {
        continue;
      }

      // If the build plan warns when landing "If Complete" but the build is
      // not complete, skip it.
      if ($if_complete && !$build_ref->isComplete()) {
        continue;
      }

      // If the build plan warns when landing "If Building" but the build is
      // complete, skip it.
      if ($if_building && $build_ref->isComplete()) {
        continue;
      }

      // Ignore passing builds.
      if ($build_ref->isPassed()) {
        continue;
      }

      if (!$build_ref->isComplete()) {
        $ongoing_builds[] = $build_ref;
      } else {
        $failed_builds[] = $build_ref;
      }
    }

    if (!$ongoing_builds && !$failed_builds) {
      return;
    }

    if ($failed_builds) {
      $this->writeWarn(
        pht('BUILD FAILURES'),
        pht(
          'Harbormaster failed to build the active diff for this revision:'));
    } else if ($ongoing_builds) {
      $this->writeWarn(
        pht('ONGOING BUILDS'),
        pht(
          'Harbormaster is still building the active diff for this revision:'));
    }

    $show_builds = array_merge($failed_builds, $ongoing_builds);
    echo "\n";
    foreach ($show_builds as $build_ref) {
      $ansi_color = $build_ref->getStatusANSIColor();
      $status_name = $build_ref->getStatusName();
      $object_name = $build_ref->getObjectName();
      $build_name = $build_ref->getName();

      echo tsprintf(
        "    **<bg:".$ansi_color."> %s </bg>** %s: %s\n",
        $status_name,
        $object_name,
        $build_name);
    }

    echo tsprintf(
      "\n%s\n\n",
      pht('You can review build details here:'));

    foreach ($buildables as $buildable) {
      $buildable_uri = id(new PhutilURI($this->getConduitURI()))
        ->setPath(sprintf('/B%d', $buildable['id']));

      echo tsprintf(
        "          **%s**: __%s__\n",
        pht('Buildable %d', $buildable['id']),
        $buildable_uri);
    }

    throw new ArcanistUsageException('Harbormaster builds ongoing or failed.');
  }
}
