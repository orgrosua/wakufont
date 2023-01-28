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

    /**
     * @see https://developers.google.com/fonts/docs/css2#api_url_specification
     */
    public function fetchVariableFontFile(Font $font, int $italic, array $axes): array
    {
        $axis_tag_list = [];
        $axis_tuple_list = [];
        $family = str_replace(' ', '+', $font->getFamily());

        // add ital axis
        $axes[] = [
            'tag' => 'ital',
            'defaultValue' => $italic,
        ];

        // sort axes by "tag" alphabetically (e.g. a,b,c,A,B,C)
        usort($axes, fn (array $a, array $b) => strcmp((string) $this->negative_case($a['tag']), (string) $this->negative_case($b['tag'])));

        foreach ($axes as $a) {
            $axis_tag_list[] = $a['tag'];

            if (array_key_exists('min', $a) && array_key_exists('max', $a)) {
                $axis_tuple_list[] = sprintf('%s..%s', $a['min'], $a['max']);
            } else {
                $axis_tuple_list[] = sprintf('%s', $a['defaultValue']);
            }
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

    private function negative_case(string $string): string
    {
        $arr = str_split($string);

        foreach ($arr as $key => $char) {
            $arr[$key] = ctype_upper($char) ? strtolower($char) : strtoupper($char);
        }

        return implode('', $arr);
    }
}
