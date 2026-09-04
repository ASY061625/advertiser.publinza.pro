<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Throwable;
use ZipArchive;

/**
 * Getting readable text out of what an advertiser uploads.
 *
 * The word count is the point. A publisher's minimum is a real acceptance
 * criterion, and telling somebody their 400-word draft is fine because nobody
 * looked inside the .docx wastes a round trip through the publisher.
 *
 * .docx is a zip of XML, so the text comes out with the zip extension already
 * in PHP rather than a document library. Anything that cannot be read is
 * treated as an unknown length rather than as zero — see fromFile.
 */
final class ArticleText
{
    /** Paragraph and line breaks in WordprocessingML, in reading order. */
    private const DOCX_BREAKS = ['</w:p>' => "\n\n", '<w:br/>' => "\n", '<w:br />' => "\n"];

    /**
     * Plain text from an upload, or an empty string when it cannot be read.
     */
    public static function fromFile(UploadedFile $file): string
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if ($extension === 'docx') {
            return self::fromDocx($file->getRealPath() ?: '');
        }

        // .md, .markdown and .txt are already text. Read as-is: the word count
        // treats markdown syntax as part of the prose, which is what a
        // publisher counting words would also do.
        $contents = @file_get_contents($file->getRealPath() ?: '');

        return $contents === false ? '' : $contents;
    }

    /**
     * Words, counted the way a person would.
     *
     * Tags are stripped first so a pasted rich-text body is not credited for
     * its markup, and the split is on runs of whitespace so hyphenated and
     * apostrophised words count once.
     */
    public static function countWords(string $text): int
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        if ($plain === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $plain) ?: []);
    }

    /**
     * The document body of a .docx.
     *
     * A .docx is a zip; `word/document.xml` holds the body. Reading it directly
     * beats adding a document library for one field, and a file that is not
     * actually a docx returns an empty string rather than throwing — an upload
     * that cannot be parsed is a word count of zero, which the content step
     * already renders as "too short" and asks about.
     */
    private static function fromDocx(string $path): string
    {
        if ($path === '' || ! class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive;

        try {
            if ($zip->open($path) !== true) {
                return '';
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
        } catch (Throwable) {
            return '';
        }

        if ($xml === false) {
            return '';
        }

        // Turn the structural tags into whitespace before stripping the rest,
        // or every paragraph runs into the next and two words become one.
        $spaced = str_replace(array_keys(self::DOCX_BREAKS), array_values(self::DOCX_BREAKS), $xml);

        return trim(html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }
}
