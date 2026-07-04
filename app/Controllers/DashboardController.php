<?php

namespace Lops2\Controllers;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = $this->requireLogin();
        $uid  = $this->uid();
        $adm  = $this->isAdmin();

        // KPI counts
        $openCases    = (int)$this->pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE status='open'")->fetchColumn();
        $pendingCases = (int)$this->pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE status='pending'")->fetchColumn();
        $closedCases  = (int)$this->pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE status='closed'")->fetchColumn();

        // Real month-over-month deltas
        $openedThisMonth = (int)$this->pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
        $openedLastMonth = (int)$this->pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE created_at >= DATE_FORMAT(NOW()-INTERVAL 1 MONTH,'%Y-%m-01') AND created_at < DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();

        $tasksThisMonth = (int)$this->pdo->query("SELECT COUNT(*) FROM legalops_tasks WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
        $tasksLastMonth = (int)$this->pdo->query("SELECT COUNT(*) FROM legalops_tasks WHERE created_at >= DATE_FORMAT(NOW()-INTERVAL 1 MONTH,'%Y-%m-01') AND created_at < DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();

        $scopeSql  = $adm ? '' : ' AND (assigned_to=? OR created_by=?)';
        $scopePrms = $adm ? [] : [$uid, $uid];

        $s = $this->pdo->prepare("SELECT COUNT(*) FROM legalops_tasks WHERE status!='done'" . $scopeSql);
        $s->execute($scopePrms);
        $openTasks = (int)$s->fetchColumn();

        $s = $this->pdo->prepare("SELECT COUNT(*) FROM legalops_tasks WHERE status!='done' AND due_on<=CURDATE()+INTERVAL 7 DAY" . $scopeSql);
        $s->execute($scopePrms);
        $dueSoon = (int)$s->fetchColumn();

        $recentCases = $this->pdo->query("SELECT * FROM legalops_cases ORDER BY created_at DESC LIMIT 5")->fetchAll();

        $s = $this->pdo->prepare(
            "SELECT t.*, c.title AS case_title FROM legalops_tasks t
             LEFT JOIN legalops_cases c ON c.id=t.case_id
             WHERE 1=1" . ($adm ? '' : ' AND (t.assigned_to=? OR t.created_by=?)') . "
             ORDER BY (t.status='done') ASC, t.due_on ASC LIMIT 6"
        );
        $s->execute($scopePrms);
        $upcomingTasks = $s->fetchAll();

        if ($adm) {
            $activity = $this->pdo->query("SELECT a.*, u.full_name FROM legalops_activity a LEFT JOIN phpauth_users u ON u.id=a.uid ORDER BY a.created_at DESC LIMIT 20")->fetchAll();
        } else {
            $s = $this->pdo->prepare("SELECT a.*, u.full_name FROM legalops_activity a LEFT JOIN phpauth_users u ON u.id=a.uid WHERE a.uid=? ORDER BY a.created_at DESC LIMIT 10");
            $s->execute([$uid]);
            $activity = $s->fetchAll();
        }

        $this->view('dashboard/index', [
            'pageTitle'       => 'Dashboard',
            'activeNav'       => 'dashboard',
            'openCases'       => $openCases,
            'pendingCases'    => $pendingCases,
            'closedCases'     => $closedCases,
            'openTasks'       => $openTasks,
            'dueSoon'         => $dueSoon,
            'openedThisMonth' => $openedThisMonth,
            'openedLastMonth' => $openedLastMonth,
            'tasksThisMonth'  => $tasksThisMonth,
            'tasksLastMonth'  => $tasksLastMonth,
            'recentCases'     => $recentCases,
            'upcomingTasks'   => $upcomingTasks,
            'activity'        => $activity,
        ]);
    }
}
