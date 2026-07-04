<?php

namespace Lops2\Controllers;

class ApiController extends BaseController
{
    public function search(): void
    {
        if (!$this->auth->isLogged()) { $this->json(['results' => []], 401); }

        $q       = trim($_GET['q'] ?? '');
        $results = [];

        if (mb_strlen($q) >= 2) {
            $like = '%' . $q . '%';

            $stmt = $this->pdo->prepare("SELECT id,case_number,title,client_name,status FROM legalops_cases WHERE title LIKE ? OR client_name LIKE ? OR case_number LIKE ? ORDER BY created_at DESC LIMIT 6");
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll() as $c) {
                $results[] = ['type' => 'case', 'title' => $c['title'], 'sub' => $c['case_number'] . ' · ' . $c['client_name'], 'badge' => $c['status'], 'url' => url('cases/' . $c['id'])];
            }

            $uid = (int)$this->auth->getCurrentUser()['uid'];
            $stmt = $this->pdo->prepare("SELECT t.id,t.title,t.status,c.title AS case_title FROM legalops_tasks t LEFT JOIN legalops_cases c ON c.id=t.case_id WHERE t.title LIKE ? AND (t.assigned_to=? OR t.created_by=?) LIMIT 4");
            $stmt->execute([$like, $uid, $uid]);
            foreach ($stmt->fetchAll() as $t) {
                $results[] = ['type' => 'task', 'title' => $t['title'], 'sub' => $t['case_title'] ? 'In: ' . $t['case_title'] : 'No matter', 'badge' => $t['status'], 'url' => url('tasks')];
            }

            $stmt = $this->pdo->prepare("SELECT id,display_name,entity_type,city FROM legalops_clients WHERE display_name LIKE ? OR pan LIKE ? LIMIT 4");
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll() as $cl) {
                $results[] = ['type' => 'client', 'title' => $cl['display_name'], 'sub' => $cl['entity_type'] . ($cl['city'] ? ' · ' . $cl['city'] : ''), 'badge' => $cl['entity_type'], 'url' => url('clients/' . $cl['id'])];
            }
        }

        $this->json(['results' => $results]);
    }
}
