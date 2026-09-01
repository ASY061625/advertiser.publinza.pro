<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Support\LegalDocuments;
use App\Support\Seo;
use Illuminate\Contracts\View\View;

class LegalController extends Controller
{
    public function terms(): View
    {
        return $this->render('Terms of service', LegalDocuments::terms(),
            'The terms governing use of Publinza, placements bought through it, and the obligations on both sides.');
    }

    public function privacy(): View
    {
        return $this->render('Privacy policy', LegalDocuments::privacy(),
            'What personal data Publinza collects, why, how long it is kept, and the rights you have over it.');
    }

    public function refunds(): View
    {
        return $this->render('Refund policy', LegalDocuments::refunds(),
            'When Publinza refunds a placement, when it replaces one instead, and how the 12-month link guarantee works.');
    }

    /**
     * @param  list<array{heading: string, paragraphs: list<string>, list?: list<string>}>  $sections
     */
    private function render(string $title, array $sections, string $description): View
    {
        return view('marketing.pages.legal.document', [
            'title' => $title,
            'description' => $description,
            'sections' => $sections,
            'updatedAt' => LegalDocuments::UPDATED_AT,
            'schema' => [Seo::organization(), Seo::website()],
        ]);
    }
}
