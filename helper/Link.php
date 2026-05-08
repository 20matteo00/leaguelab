<?php

class Link
{
    public static function a(
        string $page,
        string $text,
        array $params = [],
        array $attrs = [],
        ?string $anchor = null
    ): string {
        $href = self::url($page, $params, $anchor);

        $attrs['href'] = $href;

        $html = '<a';

        foreach ($attrs as $key => $value) {
            $html .= ' ' . htmlspecialchars($key)
                . '="' . htmlspecialchars($value) . '"';
        }

        $html .= '>' . $text . '</a>';

        return $html;
    }

    public static function url(
        string $page,
        array $params,
        ?string $anchor
    ): string {
        $query = array_merge([
            'page' => $page
        ], $params);

        $url = 'index.php?' . http_build_query($query);

        if ($anchor) {
            $url .= '#' . $anchor;
        }

        return $url;
    }
}
