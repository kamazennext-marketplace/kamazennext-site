<?php

namespace Services\Vendor;

use Core\Response;

class VendorController
{
    private VendorService $service;

    public function __construct()
    {
        $this->service = new VendorService();
    }

    public function handleRequest(string $uri, string $method)
    {
        // /vendor/login
        if ($uri === '/vendor/login' && $method === 'POST') {
            $this->handleLogin();
            return;
        }

        // /vendor/register
        if ($uri === '/vendor/register' && $method === 'POST') {
            $this->handleRegister();
            return;
        }

        // /vendor/dashboard
        if ($uri === '/vendor/dashboard' && $method === 'GET') {
            $this->handleDashboard();
            return;
        }

        // /vendor/software (Submission)
        if ($uri === '/vendor/software' && $method === 'POST') {
            $this->handleSoftwareSubmission();
            return;
        }

        Response::error('Endpoint not found', 404);
    }

    private function handleLogin()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (!$email || !$password) {
            Response::error('Email and password are required', 400);
        }

        $vendor = $this->service->login($email, $password);

        if ($vendor) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['vendor_id'] = $vendor['id'];
            $_SESSION['vendor_name'] = $vendor['name'];
            $_SESSION['vendor_email'] = $vendor['email'];
            $_SESSION['vendor_company'] = $vendor['company'];

            Response::success([
                'message' => 'Login successful',
                'vendor' => $vendor,
                'redirect' => '/vendor/dashboard.php'
            ]);
        } else {
            Response::error('Invalid credentials', 401);
        }
    }

    private function handleRegister()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $result = $this->service->register($input);

        if (isset($result['error'])) {
            Response::error($result['error'], 400);
        } else {
            Response::success($result, 201);
        }
    }

    private function handleDashboard()
    {
        // In a real app, verify authentication token/session here
        // For simplicity, assuming vendor_id is passed or handled via session
        // Let's use a query param for now for testing, but in prod use session

        $vendorId = $_GET['vendor_id'] ?? 0;
        if (!$vendorId) {
            Response::error('Unauthorized', 401);
        }

        $data = $this->service->getDashboardData((int) $vendorId);
        Response::success($data);
    }

    private function handleSoftwareSubmission()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['vendor_id'])) {
            Response::error('Unauthorized', 401);
        }

        $vendorId = $_SESSION['vendor_id'];

        // Since we are handling file uploads, we use $_POST and $_FILES
        $input = $_POST;
        $file = $_FILES['image'] ?? null;

        $result = $this->service->submitSoftware($vendorId, $input, $file);

        if (isset($result['error'])) {
            Response::error($result['error'], 400);
        } else {
            Response::success($result);
        }
    }
}
