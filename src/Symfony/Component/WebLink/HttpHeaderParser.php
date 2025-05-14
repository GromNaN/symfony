<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink;

/**
 * Parse a list of HTTP Link headers into a list of Link instances.
 *
 * @see https://tools.ietf.org/html/rfc5988
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class HttpHeaderParser
{
    // Regex to match each link entry: <...>; param1=...; param2=...
    private const LINK_PATTERN = '/<([^>]*)>\s*((?:\s*;\s*[a-zA-Z0-9\-_]+(?:\s*=\s*(?:"(?:[^"\\\\]|\\\\.)*"|[^";,\s]+))?)*)/';

    // Regex to match parameters: ; key[=value]
    private const PARAM_PATTERN = '/;\s*([a-zA-Z0-9\-_]+)(?:\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|([^";,\s]+)))?/';

    /**
     * @param string|string[] $headers Value of the "Link" HTTP header
     */
    public function parse(string|array $headers): GenericLinkProvider
    {
        $headerString = is_array($headers) ? implode(',', $headers) : $headers;
        $links = new GenericLinkProvider();

        if (!preg_match_all(self::LINK_PATTERN, $headerString, $matches, PREG_SET_ORDER)) {
            return $links;
        }

        foreach ($matches as $match) {
            $href = $match[1];
            $paramsString = $match[2];

            $params = [];
            if (preg_match_all(self::PARAM_PATTERN, $paramsString, $paramMatches, PREG_SET_ORDER)) {
                foreach ($paramMatches as $pm) {
                    $key = $pm[1];
                    $value = match (true) {
                        // Quoted value, unescape quotes
                        ($pm[2] ?? '') !== '' => stripcslashes($pm[2]),
                        ($pm[3] ?? '') !== '' => $pm[3],
                        default => true,
                    };

                    if (is_array($params[$key] ?? null)) {
                        $params[$key][] = $value;
                    } elseif (isset($params[$key])) {
                        $params[$key] = [$params[$key], $value];
                    } else {
                        $params[$key] = $value;
                    }
                }
            }

            if (!isset($params['rel'])) {
                continue;
            }
            $rels = preg_split('/\s+/', trim($params['rel']));
            unset($params['rel']);

            $link = new Link(null, $href);
            foreach ($rels as $r) {
                $link = $link->withRel($r);
            }
            foreach ($params as $k => $v) {
                $link = $link->withAttribute($k, $v);
            }
            $links = $links->withLink($link);
        }

        return $links;
    }
}
