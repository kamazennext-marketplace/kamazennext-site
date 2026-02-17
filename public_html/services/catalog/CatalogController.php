<?php

namespace Services\Catalog;

use Core\Response;

class CatalogController
{
    private CatalogService $service;

    public function __construct()
    {
        $this->service = new CatalogService();
    }

    public function handleRequest(string $uri, string $method)
    {
        // /catalog/tools
        if ($uri === '/catalog/tools' || $uri === '/catalog/tools/') {
            if ($method === 'GET') {
                $filters = [];
                if (isset($_GET['category']))
                    $filters['category'] = $_GET['category'];
                if (isset($_GET['featured']))
                    $filters['featured'] = $_GET['featured'];

                $tools = $this->service->getAllTools($filters);
                Response::success(['tools' => $tools]);
            }
        }

        // /catalog/categories
        if ($uri === '/catalog/categories' || $uri === '/catalog/categories/') {
            if ($method === 'GET') {
                $categories = $this->service->getCategories();
                Response::success(['categories' => $categories]);
            }
        }

        // /catalog/tools/{id}
        if (preg_match('#^/catalog/tools/([^/]+)$#', $uri, $matches)) {
            if ($method === 'GET') {
                $id = $matches[1];
                $tool = $this->service->getToolById($id);
                if ($tool) {
                    Response::success(['tool' => $tool]);
                } else {
                    Response::error('Tool not found', 404);
                }
            }
        }
    }
}
