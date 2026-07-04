<?php

namespace Lops2\Controllers;

class BillingEntityController extends BaseController
{
    private const COUNTRIES = [
        'IN' => 'India', 'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'BH' => 'Bahrain',
        'OM' => 'Oman', 'KW' => 'Kuwait', 'QA' => 'Qatar',
    ];

    private const ENTITY_TYPES = [
        'IN_GST'  => 'India — GST registered',
        'GCC_VAT' => 'GCC — VAT registered',
        'NO_VAT'  => 'No VAT/GST registration',
    ];

    public function index(): void
    {
        $this->requireLogin();

        $entities = $this->pdo->query(
            'SELECT * FROM legalops_billing_entities ORDER BY is_active DESC, name'
        )->fetchAll();

        $this->view('billing/entities', [
            'pageTitle'   => 'Billing entities',
            'activeNav'   => 'billing',
            'entities'    => $entities,
            'countries'   => self::COUNTRIES,
            'entityTypes' => self::ENTITY_TYPES,
        ]);
    }

    public function store(): void
    {
        $user = $this->requireLogin();
        if (!csrf_valid()) {
            flash('error', 'Session expired.');
            $this->redirect('billing/entities');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id      = (int)($_POST['id'] ?? 0);
            $name    = trim($_POST['name'] ?? '');
            $country = trim($_POST['country'] ?? '');

            if ($name === '' || $country === '') {
                flash('error', 'Name and country are required.');
                $this->redirect('billing/entities');
            }

            $fields = [
                $name, $country, $_POST['entity_type'] ?? 'NO_VAT',
                trim($_POST['tax_reg_no'] ?? '') ?: null,
                trim($_POST['state_or_emirate'] ?? '') ?: null,
                trim($_POST['address'] ?? '') ?: null,
                trim($_POST['default_currency'] ?? '') ?: 'INR',
                trim($_POST['invoice_prefix'] ?? '') ?: 'INV',
                trim($_POST['bank_details'] ?? '') ?: null,
            ];
            if ($id > 0) {
                $this->pdo->prepare(
                    'UPDATE legalops_billing_entities SET name=?, country=?, entity_type=?, tax_reg_no=?, '
                    . 'state_or_emirate=?, address=?, default_currency=?, invoice_prefix=?, bank_details=? WHERE id=?'
                )->execute([...$fields, $id]);
                flash('success', 'Billing entity updated.');
            } else {
                $this->pdo->prepare(
                    'INSERT INTO legalops_billing_entities '
                    . '(name, country, entity_type, tax_reg_no, state_or_emirate, address, default_currency, invoice_prefix, bank_details) '
                    . 'VALUES (?,?,?,?,?,?,?,?,?)'
                )->execute($fields);
                flash('success', 'Billing entity added.');
            }
            log_activity($this->pdo, (int)$user['uid'], 'billing_entity_saved', 'Saved billing entity ' . $name);
        } elseif ($action === 'deactivate') {
            $id = (int)($_POST['id'] ?? 0);
            $this->pdo->prepare('UPDATE legalops_billing_entities SET is_active = 0 WHERE id = ?')->execute([$id]);
            flash('success', 'Billing entity deactivated — past invoices are unaffected.');
        }

        $this->redirect('billing/entities');
    }
}
