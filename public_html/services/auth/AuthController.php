<?php

namespace Services\Auth;

use Core\Response;

class AuthController
{
    private AuthService $service;

    public function __construct()
    {
        $this->service = new AuthService();
    }

    public function handleRequest(string $uri, string $method)
    {
        // /auth/login
        if ($uri === '/auth/login' && $method === 'POST') {
            $this->handleLogin();
            return;
        }

        // /auth/register
        if ($uri === '/auth/register' && $method === 'POST') {
            $this->handleRegister();
            return;
        }

        // /auth/me
        if ($uri === '/auth/me' && $method === 'GET') {
            $this->handleMe();
            return;
        }

        // /auth/logout
        if ($uri === '/auth/logout' && $method === 'POST') {
            $this->handleLogout();
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

        $user = $this->service->login($email, $password);

        if ($user) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];

            Response::success([
                'message' => 'Login successful',
                'user' => $user,
                'redirect' => '/user/dashboard.php'
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

    private function handleMe()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            Response::error('Unauthorized', 401);
        }

        $user = $this->service->getUser($_SESSION['user_id']);
        if ($user) {
            Response::success(['user' => $user]);
        } else {
            Response::error('User not found', 404);
        }
    }

    private function handleLogout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        Response::success(['message' => 'Logged out successfully']);
    }
}
