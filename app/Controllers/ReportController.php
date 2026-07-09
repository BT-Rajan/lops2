<?php

namespace Lops2\Controllers;

class ReportController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        require_once dirname(__DIR__, 2) . '/libs/Invoicing.php';
        $pdo = $this->pdo;
        $today = date('Y-m-d');
        $thisMonthStart = date('Y-m-01');
        $lastMonthStart = date('Y-m-01', strtotime('-1 month'));

        // Every figure below is grouped by currency — invoices span INR/AED/USD
        // (three different billing entities), and summing across currencies
        // would produce a meaningless blended number. "Revenue" here also
        // means invoiced (issued), not collected — see the Collected section,
        // which is intentionally kept separate since it's a real different
        // number for a firm that doesn't get paid on invoice date.

        // ── Invoiced this month / last month, by currency ──────────────────
        $invoicedThisMonth = $pdo->prepare(
            "SELECT currency, SUM(grand_total) total, COUNT(*) cnt FROM legalops_invoices
             WHERE status='issued' AND invoice_date >= ? GROUP BY currency"
        );
        $invoicedThisMonth->execute([$thisMonthStart]);
        $invoicedThisMonth = $invoicedThisMonth->fetchAll();

        $invoicedLastMonth = $pdo->prepare(
            "SELECT currency, SUM(grand_total) total FROM legalops_invoices
             WHERE status='issued' AND invoice_date >= ? AND invoice_date < ? GROUP BY currency"
        );
        $invoicedLastMonth->execute([$lastMonthStart, $thisMonthStart]);
        $invoicedLastMonthByCcy = array_column($invoicedLastMonth->fetchAll(), 'total', 'currency');

        // ── Payments recorded this month, by currency ──────────────────────
        // Caveat, and it's a real one: amount_paid is a running total with a
        // single paid_at (last-touched) date, not a line-by-line payment
        // ledger. A partial payment last month topped up to "fully paid" this
        // month will show its FULL amount here, not just this month's
        // instalment. Good enough to see "money is coming in", not something
        // to reconcile a bank statement against.
        $collectedThisMonth = $pdo->prepare(
            "SELECT currency, SUM(amount_paid) total FROM legalops_invoices
             WHERE amount_paid > 0 AND paid_at >= ? GROUP BY currency"
        );
        $collectedThisMonth->execute([$thisMonthStart]);
        $collectedThisMonth = $collectedThisMonth->fetchAll();

        // ── Outstanding + overdue, by currency ──────────────────────────────
        $outstandingRows = $pdo->query(
            "SELECT currency, (grand_total - amount_paid) AS balance, due_date FROM legalops_invoices
             WHERE status='issued' AND (grand_total - amount_paid) > 0.01"
        )->fetchAll();

        $outstandingByCcy = [];
        $overdueByCcy = [];
        $agingByCcy = [];
        foreach ($outstandingRows as $r) {
            $ccy = $r['currency'];
            $bal = (float)$r['balance'];
            $outstandingByCcy[$ccy] = ($outstandingByCcy[$ccy] ?? 0) + $bal;

            $daysOverdue = $r['due_date'] ? (int)((strtotime($today) - strtotime($r['due_date'])) / 86400) : -1;
            $bucket = $daysOverdue <= 0 ? 'current' : ($daysOverdue <= 30 ? 'd1_30' : ($daysOverdue <= 60 ? 'd31_60' : ($daysOverdue <= 90 ? 'd61_90' : 'd90_plus')));
            if ($bucket !== 'current') { $overdueByCcy[$ccy] = ($overdueByCcy[$ccy] ?? 0) + $bal; }
            $agingByCcy[$ccy][$bucket] = ($agingByCcy[$ccy][$bucket] ?? 0) + $bal;
        }

        // ── Revenue by practice area (issued invoices, joined via case_id) ──
        $byPracticeArea = $pdo->query(
            "SELECT COALESCE(c.practice_area, 'No matter linked') AS practice_area, i.currency, SUM(i.grand_total) total, COUNT(*) cnt
             FROM legalops_invoices i LEFT JOIN legalops_cases c ON c.id = i.case_id
             WHERE i.status = 'issued'
             GROUP BY practice_area, i.currency
             ORDER BY total DESC"
        )->fetchAll();

        // ── Top clients by invoiced revenue ─────────────────────────────────
        $topClients = $pdo->query(
            "SELECT client_name, currency, SUM(grand_total) total, COUNT(*) cnt
             FROM legalops_invoices WHERE status='issued'
             GROUP BY client_name, currency
             ORDER BY total DESC LIMIT 10"
        )->fetchAll();

        // ── Matter throughput: opened vs closed per month, last 6 months ───
        $throughput = $pdo->query(
            "SELECT DATE_FORMAT(opened_on, '%Y-%m') ym, COUNT(*) cnt FROM legalops_cases
             WHERE opened_on >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        $closedThroughput = $pdo->query(
            "SELECT DATE_FORMAT(disposal_date, '%Y-%m') ym, COUNT(*) cnt FROM legalops_cases
             WHERE disposal_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-{$i} months"));
            $months[] = ['ym' => $ym, 'label' => date('M Y', strtotime("-{$i} months")), 'opened' => (int)($throughput[$ym] ?? 0), 'closed' => (int)($closedThroughput[$ym] ?? 0)];
        }

        $this->view('reports/index', [
            'pageTitle'           => 'Reports',
            'activeNav'           => 'reports',
            'invoicedThisMonth'   => $invoicedThisMonth,
            'invoicedLastMonthByCcy' => $invoicedLastMonthByCcy,
            'collectedThisMonth'  => $collectedThisMonth,
            'outstandingByCcy'    => $outstandingByCcy,
            'overdueByCcy'        => $overdueByCcy,
            'agingByCcy'          => $agingByCcy,
            'byPracticeArea'      => $byPracticeArea,
            'topClients'          => $topClients,
            'months'              => $months,
        ]);
    }
}
