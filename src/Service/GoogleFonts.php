<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Font;
use App\Service\Enum\UserAgent;
use Sabberworm\CSS\Parser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleFonts
{
    public function __construct(
        private readonly HttpClientInterface $client
    ) {
    }

    public function fetchFontFile(Font $font, string $format, string $variant, array $subsets): string
    {
        $userAgent = match ($format) {
            'woff2' => UserAgent::WOFF2,
            'woff' => UserAgent::WOFF,
            'ttf' => UserAgent::TTF,
            default => UserAgent::CURRENT,
        };

        $family = str_replace(' ', '+', $font->getFamily());

        $url = sprintf(
            'https://fonts.googleapis.com/css?family=%s:%s&subset=%s',
            $family,
            $variant,
            implode(',', $subsets)
        );

        $response = $this->client->request('GET', $url, [
            'headers' => [
                'User-Agent' => $userAgent->value,
            ],
        ]);

        $cssDocument = (new Parser($response->getContent()))->parse();

        $fontUrl = $cssDocument->getContents()[0]->getRules('src')[0]->getValue()->getListComponents()[0]->getURL()->getString();

        return $fontUrl;
    }

    public function fetchVariableFontFile(Font $font, int $italic, array $axes): array
    {
        $family = str_replace(' ', '+', $font->getFamily());

        $axis_tag_list[] = ['ital'];
        $axis_tuple_list[] = [sprintf('%d', $italic)];

        foreach ($axes as $a) {
            $axis_tag_list[] = $a['tag'];
            $axis_tuple_list[] = sprintf('%d..%d', intval($a['min']), intval($a['max']));
        }

        $url = sprintf(
            'https://fonts.googleapis.com/css2?family=%s:%s@%s',
            $family,
            implode(',', $axis_tag_list),
            implode(',', $axis_tuple_list)
        );

        $response = $this->client->request('GET', $url, [
            'headers' => [
                'User-Agent' => UserAgent::CURRENT->value,
            ],
        ]);

        // parse the css
        $cssDocument = (new Parser($response->getContent()))->parse();

        $parsedFiles = [];

        // match all comment /* comment */
        preg_match_all('/\/\*(.*?)\*\//s', $response->getContent(), $parsedComments);

        $comments = array_map(static fn (string $comment) => trim($comment), $parsedComments[1]);

        $cssContents = $cssDocument->getContents();

        for ($i = 0; $i < count($cssContents); $i++) {
            $subset = $comments[$i];
            $url = $cssContents[$i]->getRules('src')[0]->getValue()->getListComponents()[0]->getURL()->getString();

            $unicodeRangeRuleSet = $cssContents[$i]->getRules('unicode-range')[0]->getValue();

            $unicodeRange = $unicodeRangeRuleSet instanceof \Sabberworm\CSS\Value\RuleValueList
                ? implode(', ', $unicodeRangeRuleSet->getListComponents())
                : $unicodeRangeRuleSet;

            $parsedFiles[] = [
                'subset' => $subset,
                'url' => $url,
                'unicodeRange' => $unicodeRange,
            ];
        }

        return $parsedFiles;
    }
}
