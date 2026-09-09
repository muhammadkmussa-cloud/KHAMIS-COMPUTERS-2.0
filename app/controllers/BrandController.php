<?php
declare(strict_types=1);

class BrandController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('brands/index', [
            'title'  => 'Brands',
            'brands' => Brand::all(true),
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Brand name is required.');
            redirect('brands');
        }
        if (Database::fetchValue('SELECT COUNT(*) FROM brands WHERE name = ?', [$name]) > 0) {
            flash('error', 'That brand already exists.');
            redirect('brands');
        }
        Brand::create($name);
        flash('success', 'Brand added.');
        redirect('brands');
    }

    public function update(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Brand name is required.');
            redirect('brands');
        }
        // brands.name is UNIQUE — a duplicate would otherwise throw a raw
        // database error (500).
        if (Database::fetchValue('SELECT COUNT(*) FROM brands WHERE name = ? AND id != ?', [$name, $id]) > 0) {
            flash('error', 'That brand already exists.');
            redirect('brands');
        }
        Brand::update($id, $name);
        flash('success', 'Brand updated.');
        redirect('brands');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();
        // Null the link explicitly (MySQL ignores the inline REFERENCES).
        Database::update('products', ['brand_id' => null], 'brand_id = :id', ['id' => $id]);
        Database::delete('brands', 'id = ?', [$id]);
        flash('success', 'Brand deleted.');
        redirect('brands');
    }
}
