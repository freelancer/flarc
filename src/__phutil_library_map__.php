<?php

/**
 * This file is automatically generated. Use 'arc liberate' to rebuild it.
 *
 * @generated
 * @phutil-library-version 2
 */
phutil_register_library_map(array(
  '__library_version__' => 2,
  'class' => array(
    'ArcanistBatchExternalLinter' => 'lint/linter/ArcanistBatchExternalLinter.php',
    'ArcanistBlackLinter' => 'lint/linter/ArcanistBlackLinter.php',
    'ArcanistBlackLinterTestCase' => 'lint/linter/__tests__/ArcanistBlackLinterTestCase.php',
    'ArcanistComposerOutdatedLinter' => 'lint/linter/ArcanistComposerOutdatedLinter.php',
    'ArcanistDockerContainerLinterProxy' => 'lint/linter/ArcanistDockerContainerLinterProxy.php',
    'ArcanistDockerContainerLinterProxyTestCase' => 'lint/linter/__tests__/ArcanistDockerContainerLinterProxyTestCase.php',
    'ArcanistESLintLinter' => 'lint/linter/ArcanistESLintLinter.php',
    'ArcanistESLintLinterTestCase' => 'lint/linter/__tests__/ArcanistESLintLinterTestCase.php',
    'ArcanistGroovyLinter' => 'lint/linter/ArcanistGroovyLinter.php',
    'ArcanistHadolintLinter' => 'lint/linter/ArcanistHadolintLinter.php',
    'ArcanistHadolintLinterTestCase' => 'lint/linter/__tests__/ArcanistHadolintLinterTestCase.php',
    'ArcanistJenkinsfileLintLinter' => 'lint/linter/ArcanistJenkinsfileLintLinter.php',
    'ArcanistJenkinsfileLintLinterTestCase' => 'lint/linter/__tests__/ArcanistJenkinsfileLintLinterTestCase.php',
    'ArcanistKTLintLinter' => 'lint/linter/ArcanistKTLintLinter.php',
    'ArcanistKTLintLinterTestCase' => 'lint/linter/__tests__/ArcanistKTLintLinterTestCase.php',
    'ArcanistMergeQueuePushWorkflow' => 'workflow/ArcanistMergeQueuePushWorkflow.php',
    'ArcanistMergeQueueRevertWorkflow' => 'workflow/ArcanistMergeQueueRevertWorkflow.php',
    'ArcanistMergeQueueWorkflow' => 'workflow/ArcanistMergeQueueWorkflow.php',
    'ArcanistMypyLinter' => 'lint/linter/ArcanistMypyLinter.php',
    'ArcanistMypyLinterTestCase' => 'lint/linter/__tests__/ArcanistMypyLinterTestCase.php',
    'ArcanistPHPCSFixerLinter' => 'lint/linter/ArcanistPHPCSFixerLinter.php',
    'ArcanistPHPCSFixerLinterTestCase' => 'lint/linter/__tests__/ArcanistPHPCSFixerLinterTestCase.php',
    'ArcanistPHPMDLinter' => 'lint/linter/ArcanistPHPMDLinter.php',
    'ArcanistPHPMDLinterTestCase' => 'lint/linter/__tests__/ArcanistPHPMDLinterTestCase.php',
    'ArcanistPHPStanLinter' => 'lint/linter/ArcanistPHPStanLinter.php',
    'ArcanistPHPStanLinterTestCase' => 'lint/linter/__tests__/ArcanistPHPStanLinterTestCase.php',
    'ArcanistPsalmLinter' => 'lint/linter/ArcanistPsalmLinter.php',
    'ArcanistPsalmLinterTestCase' => 'lint/linter/__tests__/ArcanistPsalmLinterTestCase.php',
    'ArcanistRequestReviewWorkflow' => 'workflow/ArcanistRequestReviewWorkflow.php',
    'ArcanistRobotFrameworkLintLinter' => 'lint/linter/ArcanistRobotFrameworkLintLinter.php',
    'ArcanistRobotFrameworkLintLinterTestCase' => 'lint/linter/__tests__/ArcanistRobotFrameworkLintLinterTestCase.php',
    'ArcanistShellCheckLinter' => 'lint/linter/ArcanistShellCheckLinter.php',
    'ArcanistShellCheckLinterTestCase' => 'lint/linter/__tests__/ArcanistShellCheckLinterTestCase.php',
    'ArcanistStylelintLinter' => 'lint/linter/ArcanistStylelintLinter.php',
    'ArcanistStylelintLinterTestCase' => 'lint/linter/__tests__/ArcanistStylelintLinterTestCase.php',
    'ArcanistTSLintLinter' => 'lint/linter/ArcanistTSLintLinter.php',
    'ArcanistTSLintLinterTestCase' => 'lint/linter/__tests__/ArcanistTSLintLinterTestCase.php',
    'ArcanistTerraformFmtLinter' => 'lint/linter/ArcanistTerraformFmtLinter.php',
    'ArcanistTerraformFmtLinterTestCase' => 'lint/linter/__tests__/ArcanistTerraformFmtLinterTestCase.php',
    'ArcanistYAMLLintLinter' => 'lint/linter/ArcanistYAMLLintLinter.php',
    'ArcanistYAMLLintLinterTestCase' => 'lint/linter/__tests__/ArcanistYAMLLintLinterTestCase.php',
    'FlarcDiffHunk' => 'parser/FlarcDiffHunk.php',
    'FlarcDiffParser' => 'parser/FlarcDiffParser.php',
    'FlarcDiffParserTestCase' => 'parser/__tests__/FlarcDiffParserTestCase.php',
    'FlarcFilesystem' => 'filesystem/FlarcFilesystem.php',
    'FlarcFilesystemTestCase' => 'filesystem/__tests__/FlarcFilesystemTestCase.php',
    'FlarcJSHintLinter' => 'lint/linter/FlarcJSHintLinter.php',
    'FlarcJSHintLinterTestCase' => 'lint/linter/__tests__/FlarcJSHintLinterTestCase.php',
    'FlarcJunitTestResultParser' => 'unit/parser/FlarcJunitTestResultParser.php',
    'FlarcJunitTestResultParserTestCase' => 'unit/parser/__tests__/FlarcJunitTestResultParserTestCase.php',
    'FlarcLibraryTestCase' => '__tests__/FlarcLibraryTestCase.php',
    'FlarcPuppetLintLinter' => 'lint/linter/FlarcPuppetLintLinter.php',
    'FlarcPuppetLintLinterTestCase' => 'lint/linter/__tests__/FlarcPuppetLintLinterTestCase.php',
    'FreelancerPhpunitConsoleRenderer' => 'unit/engine/FreelancerPhpunitConsoleRenderer.php',
    'FreelancerPhpunitTestEngine' => 'unit/engine/FreelancerPhpunitTestEngine.php',
    'FreelancerPhpunitTestEngineTestCase' => 'unit/engine/__tests__/FreelancerPhpunitTestEngineTestCase.php',
  ),
  'function' => array(),
  'xmap' => array(
    'ArcanistBatchExternalLinter' => 'ArcanistExternalLinter',
    'ArcanistBlackLinter' => 'ArcanistExternalLinter',
    'ArcanistBlackLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistComposerOutdatedLinter' => 'ArcanistExternalLinter',
    'ArcanistDockerContainerLinterProxy' => 'ArcanistExternalLinter',
    'ArcanistDockerContainerLinterProxyTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistESLintLinter' => 'ArcanistBatchExternalLinter',
    'ArcanistESLintLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistGroovyLinter' => 'ArcanistExternalLinter',
    'ArcanistHadolintLinter' => 'ArcanistExternalLinter',
    'ArcanistHadolintLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistJenkinsfileLintLinter' => 'ArcanistExternalLinter',
    'ArcanistJenkinsfileLintLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistKTLintLinter' => 'ArcanistExternalLinter',
    'ArcanistKTLintLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistMergeQueuePushWorkflow' => 'ArcanistWorkflow',
    'ArcanistMergeQueueRevertWorkflow' => 'ArcanistWorkflow',
    'ArcanistMergeQueueWorkflow' => 'ArcanistWorkflow',
    'ArcanistMypyLinter' => 'ArcanistExternalLinter',
    'ArcanistMypyLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistPHPCSFixerLinter' => 'ArcanistBatchExternalLinter',
    'ArcanistPHPCSFixerLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistPHPMDLinter' => 'ArcanistExternalLinter',
    'ArcanistPHPMDLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistPHPStanLinter' => 'ArcanistBatchExternalLinter',
    'ArcanistPHPStanLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistPsalmLinter' => 'ArcanistBatchExternalLinter',
    'ArcanistPsalmLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistRequestReviewWorkflow' => 'ArcanistWorkflow',
    'ArcanistRobotFrameworkLintLinter' => 'ArcanistExternalLinter',
    'ArcanistRobotFrameworkLintLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistShellCheckLinter' => 'ArcanistExternalLinter',
    'ArcanistShellCheckLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistStylelintLinter' => 'ArcanistExternalLinter',
    'ArcanistStylelintLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistTSLintLinter' => 'ArcanistBatchExternalLinter',
    'ArcanistTSLintLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistTerraformFmtLinter' => 'ArcanistExternalLinter',
    'ArcanistTerraformFmtLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'ArcanistYAMLLintLinter' => 'ArcanistExternalLinter',
    'ArcanistYAMLLintLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'FlarcDiffHunk' => 'Phobject',
    'FlarcDiffParser' => 'Phobject',
    'FlarcDiffParserTestCase' => 'PhutilTestCase',
    'FlarcFilesystem' => 'Phobject',
    'FlarcFilesystemTestCase' => 'PhutilTestCase',
    'FlarcJSHintLinter' => 'ArcanistExternalLinter',
    'FlarcJSHintLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'FlarcJunitTestResultParser' => 'ArcanistTestResultParser',
    'FlarcJunitTestResultParserTestCase' => 'PhutilTestCase',
    'FlarcLibraryTestCase' => 'PhutilLibraryTestCase',
    'FlarcPuppetLintLinter' => 'ArcanistExternalLinter',
    'FlarcPuppetLintLinterTestCase' => 'ArcanistExternalLinterTestCase',
    'FreelancerPhpunitConsoleRenderer' => 'ArcanistUnitRenderer',
    'FreelancerPhpunitTestEngine' => 'ArcanistUnitTestEngine',
    'FreelancerPhpunitTestEngineTestCase' => 'PhutilTestCase',
  ),
));
