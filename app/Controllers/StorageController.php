<?php

namespace Lops2\Controllers;

class StorageController extends BaseController
{
    public function serve(array $params): void
    {
        $this->requireLogin();

        $scope = $params['scope'] ?? '';
        $id    = (int)($params['id'] ?? 0);
        $file  = $params['file'] ?? '';

        // Validate scope and look up the DB record
        if ($scope === 'clients') {
            $stmt = $this->pdo->prepare('SELECT * FROM legalops_client_documents WHERE stored_name=? AND client_id=?');
            $stmt->execute([$file, $id]);
        } elseif ($scope === 'cases') {
            $stmt = $this->pdo->prepare('SELECT * FROM legalops_case_documents WHERE stored_name=? AND case_id=?');
            $stmt->execute([$file, $id]);
        } else {
            $this->abort(404);
        }

        $doc = $stmt->fetch();
        if (!$doc) { $this->abort(404); }

        $path     = rtrim(STORAGE_PATH, '/') . '/uploads/' . $scope . '/' . $id . '/' . $file;
        $realBase = realpath(rtrim(STORAGE_PATH, '/') . '/uploads/' . $scope);
        $realPath = realpath($path);

        if (!$realBase || !$realPath || !str_starts_with($realPath, $realBase) || !is_file($realPath)) {
            $this->abort(404);
        }

        $download = isset($_GET['dl']);
        header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . basename($doc['original_name']) . '"');
        header('Content-Length: ' . filesize($realPath));
        header('X-Content-Type-Options: nosniff');
        readfile($realPath);
        exit;
    }
}
