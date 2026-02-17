<?php

namespace Services\Catalog;

use Core\Response;

class CatalogService
{
    private string $dataFile;
    private array $data = [];

    public function __construct()
    {
        $this->dataFile = __DIR__ . '/../../data/products.json';
        $this->loadData();
    }

    private function loadData(): void
    {
        if (!file_exists($this->dataFile)) {
            $this->data = [];
            return;
        }

        $json = file_get_contents($this->dataFile);
        $this->data = json_decode($json, true) ?? [];
    }

    public function getAllTools(array $filters = []): array
    {
        $tools = $this->data;

        // Apply filters if needed (e.g., category, featured)
        if (!empty($filters['category'])) {
            $tools = array_filter($tools, fn($t) => strcasecmp($t['category'] ?? '', $filters['category']) === 0);
        }

        if (isset($filters['featured'])) {
            $isFeatured = filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN);
            $tools = array_filter($tools, fn($t) => ($t['featured'] ?? false) === $isFeatured);
        }

        return array_values($tools);
    }

    public function getToolById(string $id): ?array
    {
        foreach ($this->data as $tool) {
            if (($tool['id'] ?? '') === $id) {
                return $tool;
            }
        }
        return null;
    }

    public function getCategories(): array
    {
        $categories = [];
        foreach ($this->data as $tool) {
            if (!empty($tool['category'])) {
                $categories[] = $tool['category'];
            }
        }
        return array_values(array_unique($categories));
    }
}
