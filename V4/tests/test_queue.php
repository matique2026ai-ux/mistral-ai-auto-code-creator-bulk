<?php
require_once __DIR__ . '/framework.php';
require_once __DIR__ . '/../queue.php';
require_once __DIR__ . '/../helpers.php';

$db = getDB();
$queue = new JobQueue();

echo "╔════════════════════════════════════════╗\n";
echo "║   Tests JobQueue                      ║\n";
echo "╚════════════════════════════════════════╝\n\n";

$slug = 'test-queue-' . substr(uniqid(), -6);
$folder = 'builds/' . $slug;
$id = createProject($db, [
    'title' => 'Queue Test', 'folder' => $folder,
    'type' => 'static', 'frontend' => 'html_css_js',
    'backend' => 'none', 'database' => 'none',
    'css' => 'vanilla', 'lang' => 'fr',
    'brief' => json_encode(['master_prompt' => 'Test']),
    'slug' => $slug,
]);

test('enqueueProject — crée 7 jobs', function () use ($queue, $id) {
    $jobs = $queue->enqueueProject($id);
    assert_eq(7, count($jobs));
    assert_true(isset($jobs['cto'], $jobs['backend'], $jobs['frontend'], $jobs['qa'], $jobs['devops']));
});

test('getJobById — retourne le job', function () use ($queue, $db, $id) {
    $row = $db->query("SELECT id FROM jobs WHERE project_id = $id AND job_name = 'cto' LIMIT 1")->fetch();
    $job = $queue->getJobById((int)$row['id']);
    assert_true($job !== null);
    assert_eq('cto', $job['job_name']);
});

test('getReadyJobs — CTO est prêt en premier', function () use ($queue, $id) {
    $ready = $queue->getReadyJobs($id, 2);
    assert_true(count($ready) > 0);
    assert_eq('cto', $ready[0]['job_name']);
});

test('claimJob — passe de pending à running', function () use ($queue, $db, $id) {
    $row = $db->query("SELECT id FROM jobs WHERE project_id = $id AND job_name = 'cto' LIMIT 1")->fetch();
    assert_true($queue->claimJob((int)$row['id'], 'test_worker'));
    $job = $queue->getJobById((int)$row['id']);
    assert_eq('running', $job['status']);
});

test('completeJob — passe de running à done', function () use ($queue, $db, $id) {
    $row = $db->query("SELECT id FROM jobs WHERE project_id = $id AND job_name = 'cto' LIMIT 1")->fetch();
    $queue->completeJob((int)$row['id']);
    $job = $queue->getJobById((int)$row['id']);
    assert_eq('done', $job['status']);
});

test('getReadyJobs — CTO done, Architect ready', function () use ($queue, $id) {
    $ready = $queue->getReadyJobs($id, 2);
    $names = array_map(fn($j) => $j['job_name'], $ready);
    assert_true(in_array('architect', $names));
});

test('failJob — retry puis failed', function () use ($queue, $db, $id) {
    $row = $db->query("SELECT id FROM jobs WHERE project_id = $id AND job_name = 'frontend' LIMIT 1")->fetch();
    $jobId = (int)$row['id'];
    $queue->failJob($jobId, 'Test error');
    assert_eq('pending', $queue->getJobById($jobId)['status']);
    assert_eq(1, $queue->getJobById($jobId)['retry_count']);
    $queue->failJob($jobId, 'Test error 2');
    assert_eq(2, $queue->getJobById($jobId)['retry_count']);
    $queue->failJob($jobId, 'Test error 3');
    assert_eq('failed', $queue->getJobById($jobId)['status']);
});

test('cancelProject — annule les jobs pending', function () use ($queue, $db, $id) {
    $queue->enqueueProject($id);
    $queue->cancelProject($id);
    $pending = $db->query("SELECT COUNT(*) FROM jobs WHERE project_id = $id AND status = 'pending'")->fetchColumn();
    assert_eq(0, (int)$pending);
});

test('allDone — false quand jobs pending', function () use ($queue, $id) {
    $queue->enqueueProject($id);
    assert_true(!$queue->allDone($id));
});

$db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
$dir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . $slug;
if (is_dir($dir)) _rmdir_recursive($dir);

printSummary('Résumé JobQueue');
exit($failed > 0 ? 1 : 0);
