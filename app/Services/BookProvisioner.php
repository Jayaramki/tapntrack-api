<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Book;
use App\Models\ExpenseCategory;
use App\Models\Line;

/**
 * Seeds the default app settings, expense categories and collection lines for a
 * freshly created book. Shared by BookController@store (admin creates a book)
 * and AuthController@register (self-signup creates the tenant's starter book) so
 * the two never drift.
 */
class BookProvisioner
{
    public function seedDefaults(Book $book, ?string $appName = null, ?string $updatedBy = null): void
    {
        $settings = [
            'APP_NAME' => $appName ?: 'TapNTrack',
            'DAYS_TO_PAY' => '120',
            'INTEREST_PERCENTAGE' => '20',
        ];
        foreach ($settings as $key => $value) {
            AppSetting::create([
                'book_id' => $book->id,
                'key' => $key,
                'value' => $value,
                'updated_by' => $updatedBy,
            ]);
        }

        $categories = [
            ['name' => 'Cheetu', 'color' => '#E65100'],
            ['name' => 'Vatti', 'color' => '#C62828'],
            ['name' => 'GPay', 'color' => '#1565C0'],
            ['name' => 'Other', 'color' => '#546E7A'],
        ];
        foreach ($categories as $cat) {
            ExpenseCategory::create([
                'book_id' => $book->id,
                'name' => $cat['name'],
                'color' => $cat['color'],
                'is_active' => true,
            ]);
        }

        foreach (['Line 1', 'Line 2', 'Line 3', 'Line 4', 'Line 5', 'Line 6'] as $lineName) {
            Line::create([
                'book_id' => $book->id,
                'name' => $lineName,
                'color' => '#546E7A',
                'is_active' => true,
            ]);
        }
    }
}
