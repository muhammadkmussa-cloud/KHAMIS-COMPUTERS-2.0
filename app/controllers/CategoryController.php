<?php
declare(strict_types=1);

class CategoryController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('categories/index', [
            'title'      => 'Categories',
            'categories' => Category::all(true),
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Category name is required.');
            redirect('categories');
        }
        Category::create($name, (string) ($_POST['description'] ?? ''));
        flash('success', 'Category added.');
        redirect('categories');
    }

    public function update(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Category name is required.');
            redirect('categories');
        }
        Category::update($id, $name, (string) ($_POST['description'] ?? ''));
        flash('success', 'Category updated.');
        redirect('categories');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();
        // schema.sql declares ON DELETE SET NULL, but MySQL ignores inline
        // REFERENCES — null the link explicitly so products become
        // "uncategorised" on both engines.
        Database::update('products', ['category_id' => null], 'category_id = :id', ['id' => $id]);
        Database::delete('categories', 'id = ?', [$id]);
        flash('success', 'Category deleted.');
        redirect('categories');
    }
}
