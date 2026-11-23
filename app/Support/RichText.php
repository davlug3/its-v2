<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizes rich text (HTML) submitted through the ticket editor and derives
 * plain text from it for validation and previews.
 */
class RichText
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('blockquote')
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('a', ['href', 'title'])
            ->allowElement('img', ['src'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['https', 'http', 'data'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        $this->sanitizer = new HtmlSanitizer($config);
    }

    /**
     * Sanitize untrusted editor HTML into an allowlisted subset.
     */
    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }

    /**
     * Return the plain-text content of sanitized HTML, with collapsed
     * whitespace and no markup.
     */
    public function plainText(string $html): string
    {
        $text = strip_tags($html);
        $text = (string) html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
