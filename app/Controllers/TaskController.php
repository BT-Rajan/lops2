<?php

namespace Lops2\Controllers;

class TaskController extends BaseController
{
    private const STATUSES   = ['pending', 'in_progress', 'hold', 'done'];
    private const PRIORITIES = ['low', 'medium', 'high'];

    public function index(): void
    {
        $user = $this->requireLogin();
        $uid  = $this->uid();
        $adm  = $this->isAdmin();

        $search   = trim($_GET['q'] ?? '');
        $statusF  = $_GET['status'] ?? 'all';
        $assigneeF = (int)($_GET['assignee'] ?? 0);
        $caseIdF   = (int)($_GET['case_id'] ?? 0);

        $sql    = 'SELECT t.*,c.case_number,c.title AS case_title,u.full_name AS assignee_name,u.avatar_color AS assignee_color
                   FROM legalops_tasks t
                   LEFT JOIN legalops_cases c ON c.id=t.case_id
                   LEFT JOIN phpauth_users u ON u.id=t.assigned_to
                   WHERE 1=1';
        $params = [];

        if (!$adm) {
            $sql .= ' AND (t.assigned_to=? OR t.created_by=?)';
            $params[] = $uid; $params[] = $uid;
        } elseif ($assigneeF > 0) {
            $sql .= ' AND t.assigned_to=?'; $params[] = $assigneeF;
        }
        if ($caseIdF > 0) {
            $sql .= ' AND t.case_id = ?'; $params[] = $caseIdF;
        }
        if ($statusF === 'open') {
            $sql .= " AND t.status != 'done'";
        } elseif (in_array($statusF, self::STATUSES, true)) {
            $sql .= ' AND t.status=?'; $params[] = $statusF;
        }
        if ($search !== '') {
            $sql .= ' AND (t.title LIKE ? OR t.notes LIKE ? OR c.case_number LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        $sql .= " ORDER BY (t.status='done'), (t.due_on IS NULL), t.due_on ASC, FIELD(t.priority,'high','medium','low')";
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        $cases = $this->pdo->query("SELECT id,case_number,title FROM legalops_cases WHERE status!='closed' ORDER BY case_number")->fetchAll();
        $team  = $adm ? $this->pdo->query("SELECT id,full_name,email FROM phpauth_users ORDER BY full_name")->fetchAll()
                      : [['id' => $uid, 'full_name' => $user['full_name'] ?? $user['email'], 'email' => $user['email']]];

        $caseIdFilterLabel = null;
        if ($caseIdF > 0) {
            $stmt = $this->pdo->prepare('SELECT case_number, title FROM legalops_cases WHERE id = ?');
            $stmt->execute([$caseIdF]);
            if ($row = $stmt->fetch()) { $caseIdFilterLabel = $row['case_number'] . ' — ' . $row['title']; }
        }

        $this->view('tasks/index', [
            'pageTitle'    => 'Tasks',
            'activeNav'    => 'tasks',
            'tasks'        => $tasks,
            'cases'        => $cases,
            'team'         => $team,
            'search'       => $search,
            'statusFilter' => $statusF,
            'assigneeFilter' => $assigneeF,
            'caseIdFilter'   => $caseIdF,
            'caseIdFilterLabel' => $caseIdFilterLabel,
        ]);
    }

    /**
     * Where to land after a task CRUD action. Defaults to /tasks (unchanged
     * behavior). The calendar's day modal posts _redirect_to=calendar (plus
     * the month/year/viewed-user it was showing) so saving a task from
     * there returns to that same calendar view instead of jumping away to
     * the Tasks list.
     */
    private function redirectBack(): never
    {
        if (($_POST['_redirect_to'] ?? '') === 'calendar') {
            $y = max(2000, min(2100, (int)($_POST['_redirect_y'] ?? date('Y'))));
            $m = max(1, min(12, (int)($_POST['_redirect_m'] ?? date('n'))));
            $qs = 'y=' . $y . '&m=' . $m;
            if ($this->isAdmin() && (int)($_POST['_redirect_user'] ?? 0) > 0) {
                $qs .= '&user=' . (int)$_POST['_redirect_user'];
            }
            $this->redirect('calendar?' . $qs);
        }
        $this->redirect('tasks');
    }

    public function store(): void
    {
        $user = $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirectBack(); }

        $title      = trim($_POST['title'] ?? '');
        $notes      = trim($_POST['notes'] ?? '');
        $caseId     = (int)($_POST['case_id'] ?? 0) ?: null;
        $dueOn      = ($_POST['due_on'] ?? '') ?: null;
        $dueTime    = ($_POST['due_time'] ?? '') ?: null;
        $priority   = $this->postEnum('priority', self::PRIORITIES, 'medium');
        $assignedTo = $this->isAdmin() ? (int)($_POST['assigned_to'] ?? $this->uid()) : $this->uid();

        if (!$title) { flash('error', 'Task title is required.'); $this->redirectBack(); }

        $this->pdo->prepare("INSERT INTO legalops_tasks (case_id,title,notes,due_on,due_time,priority,status,assigned_to,created_by,source) VALUES (?,?,?,?,?,?,'pending',?,?,'manual')")
            ->execute([$caseId, $title, $notes ?: null, $dueOn, $dueTime, $priority, $assignedTo, $this->uid()]);
        log_activity($this->pdo, $this->uid(), 'task_created', 'Created task "' . $title . '"');
        flash('success', 'Task created.');
        $this->redirectBack();
    }

    public function update(array $params): void
    {
        $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirectBack(); }

        $id     = (int)$params['id'];
        $action = $_POST['_action'] ?? 'save';
        $task   = $this->findAndAuthorise($id);

        if ($action === 'set_status') {
            $status = $this->postEnum('status', ['pending', 'in_progress', 'done'], 'pending');
            $this->pdo->prepare("UPDATE legalops_tasks SET status=?,hold_reason=NULL WHERE id=?")->execute([$status, $id]);
            flash('success', 'Status updated.');
        } elseif ($action === 'set_hold') {
            $reason = trim($_POST['hold_reason'] ?? '');
            $this->pdo->prepare("UPDATE legalops_tasks SET status='hold',hold_reason=? WHERE id=?")->execute([$reason ?: null, $id]);
            log_activity($this->pdo, $this->uid(), 'task_hold', 'Put task "' . $task['title'] . '" on hold');
            flash('success', 'Task put on hold.');
        } else {
            $title      = trim($_POST['title'] ?? '');
            $assignedTo = $this->isAdmin() ? (int)($_POST['assigned_to'] ?? $this->uid()) : $this->uid();
            if (!$title) { flash('error', 'Title required.'); $this->redirectBack(); }
            $this->pdo->prepare('UPDATE legalops_tasks SET title=?,notes=?,case_id=?,due_on=?,due_time=?,priority=?,assigned_to=? WHERE id=?')
                ->execute([$title, trim($_POST['notes'] ?? '') ?: null, (int)($_POST['case_id'] ?? 0) ?: null,
                           ($_POST['due_on'] ?? '') ?: null, ($_POST['due_time'] ?? '') ?: null,
                           $this->postEnum('priority', self::PRIORITIES, 'medium'), $assignedTo, $id]);
            flash('success', 'Task updated.');
        }
        $this->redirectBack();
    }

    public function destroy(array $params): void
    {
        $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirectBack(); }

        $this->findAndAuthorise((int)$params['id']);
        $this->pdo->prepare('DELETE FROM legalops_tasks WHERE id=?')->execute([(int)$params['id']]);
        flash('success', 'Task deleted.');
        $this->redirectBack();
    }

    private function findAndAuthorise(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM legalops_tasks WHERE id=?');
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if (!$task) { flash('error', 'Task not found.'); $this->redirectBack(); }
        if (!$this->isAdmin() && $task['assigned_to'] != $this->uid() && $task['created_by'] != $this->uid()) {
            flash('error', "You don't have access to that task."); $this->redirectBack();
        }
        return $task;
    }
}
