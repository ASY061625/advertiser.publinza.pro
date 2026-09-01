<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\WebsiteCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The lookup tables every other seeder depends on. Idempotent — each row is
 * upserted on its natural key, so re-running never duplicates.
 */
class ReferenceDataSeeder extends Seeder
{
    /** 14 categories. */
    public const CATEGORIES = [
        'Technology', 'Finance', 'Health & Fitness', 'Marketing & SEO', 'Business',
        'Travel', 'Home & Garden', 'Fashion & Beauty', 'Food & Drink', 'Automotive',
        'Education', 'Real Estate', 'Gaming', 'Legal',
    ];

    /** 8 languages. */
    public const LANGUAGES = [
        ['en', 'English', 'English'],
        ['es', 'Spanish', 'Español'],
        ['de', 'German', 'Deutsch'],
        ['fr', 'French', 'Français'],
        ['it', 'Italian', 'Italiano'],
        ['pt', 'Portuguese', 'Português'],
        ['nl', 'Dutch', 'Nederlands'],
        ['pl', 'Polish', 'Polski'],
    ];

    /** 30 countries. */
    public const COUNTRIES = [
        ['US', 'United States', 'North America'],
        ['CA', 'Canada', 'North America'],
        ['MX', 'Mexico', 'North America'],
        ['GB', 'United Kingdom', 'Europe'],
        ['IE', 'Ireland', 'Europe'],
        ['DE', 'Germany', 'Europe'],
        ['FR', 'France', 'Europe'],
        ['ES', 'Spain', 'Europe'],
        ['IT', 'Italy', 'Europe'],
        ['PT', 'Portugal', 'Europe'],
        ['NL', 'Netherlands', 'Europe'],
        ['BE', 'Belgium', 'Europe'],
        ['PL', 'Poland', 'Europe'],
        ['SE', 'Sweden', 'Europe'],
        ['NO', 'Norway', 'Europe'],
        ['DK', 'Denmark', 'Europe'],
        ['FI', 'Finland', 'Europe'],
        ['CH', 'Switzerland', 'Europe'],
        ['AT', 'Austria', 'Europe'],
        ['CZ', 'Czechia', 'Europe'],
        ['RO', 'Romania', 'Europe'],
        ['GR', 'Greece', 'Europe'],
        ['AU', 'Australia', 'Oceania'],
        ['NZ', 'New Zealand', 'Oceania'],
        ['IN', 'India', 'Asia'],
        ['SG', 'Singapore', 'Asia'],
        ['JP', 'Japan', 'Asia'],
        ['BR', 'Brazil', 'South America'],
        ['AR', 'Argentina', 'South America'],
        ['ZA', 'South Africa', 'Africa'],
    ];

    /** 12 sensitive topics. */
    public const SENSITIVE_TOPICS = [
        ['Gambling', 'Casino, betting and lottery content.'],
        ['CBD & cannabis', 'CBD, hemp and cannabis products.'],
        ['Adult', 'Adult and dating content.'],
        ['Cryptocurrency', 'Crypto, tokens and exchanges.'],
        ['Forex & trading', 'Leveraged trading and forex brokers.'],
        ['Pharmaceuticals', 'Prescription and over-the-counter medication.'],
        ['Vaping & tobacco', 'E-cigarettes, vaping and tobacco.'],
        ['Alcohol', 'Beer, wine and spirits.'],
        ['Firearms', 'Guns, ammunition and accessories.'],
        ['Payday loans', 'High-interest short-term lending.'],
        ['Essay writing', 'Academic writing services.'],
        ['Politics', 'Party-political and campaign content.'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $name) {
            WebsiteCategory::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $index],
            );
        }

        foreach (self::LANGUAGES as [$code, $name, $native]) {
            Language::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'native_name' => $native],
            );
        }

        foreach (self::COUNTRIES as [$code, $name, $region]) {
            Country::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'region' => $region],
            );
        }

        foreach (self::SENSITIVE_TOPICS as [$name, $description]) {
            SensitiveTopic::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description],
            );
        }
    }
}
