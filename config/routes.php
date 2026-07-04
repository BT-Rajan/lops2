<?php
/**
 * lops2 route map.
 *
 * GET  /path          → ControllerName@method
 * POST /path          → ControllerName@method
 *
 * Param capture: /cases/{id}  →  $params['id'] passed to method.
 *
 * All controller class names resolve to Lops2\Controllers\{Name}.
 */

use Lops2\Core\Router;

// ── Auth ──────────────────────────────────────────────────────────────────────
Router::get('/',                     'AuthController@index');
Router::get('/login',                'AuthController@loginForm');
Router::post('/login',               'AuthController@login');
Router::get('/register',             'AuthController@registerForm');
Router::post('/register',            'AuthController@register');
Router::get('/forgot-password',      'AuthController@forgotForm');
Router::post('/forgot-password',     'AuthController@forgot');
Router::get('/reset-password',       'AuthController@resetForm');
Router::post('/reset-password',      'AuthController@reset');
Router::get('/logout',               'AuthController@logout');

// ── Dashboard ─────────────────────────────────────────────────────────────────
Router::get('/dashboard',            'DashboardController@index');

// ── Cases ─────────────────────────────────────────────────────────────────────
Router::get('/cases',                'CaseController@index');
Router::post('/cases',               'CaseController@store');
Router::get('/cases/{id}',           'CaseController@show');
Router::post('/cases/{id}',          'CaseController@update');
Router::post('/cases/{id}/delete',   'CaseController@destroy');

// ── Clients ───────────────────────────────────────────────────────────────────
Router::get('/clients',              'ClientController@index');
Router::post('/clients',             'ClientController@store');
Router::get('/clients/{id}',         'ClientController@show');
Router::post('/clients/{id}',        'ClientController@update');
Router::post('/clients/{id}/delete', 'ClientController@destroy');

// ── Tasks ─────────────────────────────────────────────────────────────────────
Router::get('/tasks',                'TaskController@index');
Router::post('/tasks',               'TaskController@store');
Router::post('/tasks/{id}',          'TaskController@update');
Router::post('/tasks/{id}/delete',   'TaskController@destroy');

// ── Calendar ──────────────────────────────────────────────────────────────────
Router::get('/calendar',             'CalendarController@index');
Router::post('/calendar/sync',       'CalendarController@sync');
Router::post('/calendar/disconnect', 'CalendarController@disconnect');
Router::get('/calendar/connect',     'CalendarController@oauthStart');
Router::get('/calendar/callback',    'CalendarController@oauthCallback');

// ── Billing ───────────────────────────────────────────────────────────────────
Router::get('/billing',                      'BillingController@index');
Router::post('/billing',                     'BillingController@store');
Router::get('/billing/entities',             'BillingEntityController@index');
Router::post('/billing/entities',            'BillingEntityController@store');
Router::get('/billing/invoices/{id}/items',  'BillingController@items');
Router::get('/billing/invoices/{id}/pdf',    'BillingController@pdf');

// ── Settings (admin only) ─────────────────────────────────────────────────────
Router::get('/settings',             'SettingsController@index');
Router::post('/settings',            'SettingsController@update');

// ── Profile ───────────────────────────────────────────────────────────────────
Router::get('/profile',              'ProfileController@index');
Router::post('/profile',             'ProfileController@update');

// ── Storage — secure file serving ────────────────────────────────────────────
Router::get('/storage/{scope}/{id}/{file}', 'StorageController@serve');

// ── API ───────────────────────────────────────────────────────────────────────
Router::get('/api/search',           'ApiController@search');
