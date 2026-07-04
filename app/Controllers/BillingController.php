<?php

namespace Lops2\Controllers;

class BillingController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();
        // Delegate to the existing billing.php logic for now — the billing
        // module was built separately and is already robust.
        // In a future sprint this will be fully migrated here.
        require_once dirname(__DIR__, 2) . '/billing.php';
        exit;
    }

    public function store(): void   { $this->index(); }
    public function show(array $p): void   { $this->index(); }
    public function update(array $p): void { $this->index(); }
}
