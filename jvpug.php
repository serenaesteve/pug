<?php

final class JVpug
{
    public static function renderFile(string $file, array $vars = []): string
    {
        $src = @file_get_contents($file);
        if ($src === false) {
            throw new RuntimeException("No se pudo leer: {$file}");
        }
        return self::render($src, $vars, dirname($file));
    }

    public static function render(string $src, array $vars = [], ?string $baseDir = null): string
    {
        $lines = preg_split("/\r\n|\r|\n/", $src);
        $root = self::parseToTree($lines);
        $ctx = $vars;
        return self::renderNodes($root, $ctx, $baseDir ?? getcwd());
    }


    private static function parseToTree(array $lines): array
    {
        $root = [];
        $stack = [ [ 'level' => -1, 'children' => &$root ] ];

        foreach ($lines as $raw) {
            if (trim($raw) === '') continue;

            preg_match('/^( *)/', $raw, $m);
            $spaces = strlen($m[1]);
            $level  = intdiv($spaces, 2);
            $line   = trim($raw);

            while (count($stack) > 0 && $stack[count($stack)-1]['level'] >= $level) {
                array_pop($stack);
            }
            $parentChildren = &$stack[count($stack)-1]['children'];

            $node = self::parseLine($line);
            if ($node === null) continue;

            $node['level'] = $level;
            $node['children'] = [];
            $parentChildren[] = $node;

            if ($node['type'] === 'tag' && !$node['selfClose'] && !$node['hasInlineText']) {
                $idx = count($parentChildren) - 1;
                $stack[] = [ 'level' => $level, 'children' => &$parentChildren[$idx]['children'] ];
            }
            if ($node['type'] === 'dir') {
                $idx = count($parentChildren) - 1;
                $stack[] = [ 'level' => $level, 'children' => &$parentChildren[$idx]['children'] ];
            }
        }

        return $root;
    }

    private static function parseLine(string $line): ?array
    {
        if (str_starts_with($line, '//')) return null;

        if (preg_match('/^doctype\b/i', $line)) {
            return ['type' => 'doctype'];
        }

        if (preg_match('/^\|\s*(.*)$/', $line, $m)) {
            return ['type' => 'text', 'text' => $m[1]];
        }

        if (str_starts_with($line, '@')) {
            if (!preg_match('/^@([a-zA-Z_][a-zA-Z0-9_]*)\b(.*)$/', $line, $m)) return null;
            return ['type' => 'dir', 'name' => strtolower($m[1]), 'args' => trim($m[2])];
        }

        if (preg_match('/^(if|elseif|else|for|foreach|include)\b(.*)$/i', $line, $dm)) {
            return ['type' => 'dir', 'name' => strtolower($dm[1]), 'args' => trim($dm[2])];
        }

        if (!preg_match('/^([a-zA-Z][a-zA-Z0-9]*)\b(.*)$/', $line, $mm)) return null;

        $tag  = $mm[1];
        $rest = trim($mm[2]);

        $selfClosingTags = ['br','img','hr','input','meta','link','source','area','base','col','embed','param','track','wbr'];
        $selfClose = in_array(strtolower($tag), $selfClosingTags, true);

        $attrs = [];
        $text  = '';

        if ($rest !== '') {
            $tokens = self::tokenize($rest);

            $i = 0;
            for (; $i < count($tokens); $i++) {
                $t = $tokens[$i];
                if (preg_match('/^([a-zA-Z_:][a-zA-Z0-9_:\-]*)=(.+)$/', $t, $am)) {
                    $attrs[$am[1]] = $am[2];
                } elseif (preg_match('/^([a-zA-Z_:][a-zA-Z0-9_:\-]*)$/', $t)) {
                    $attrs[$t] = 'true';
                } else {
                    break;
                }
            }
            if ($i < count($tokens)) $text = implode(' ', array_slice($tokens, $i));
        }

        return [
            'type' => 'tag',
            'tag' => $tag,
            'attrs' => $attrs,
            'text' => $text,
            'hasInlineText' => ($text !== ''),
            'selfClose' => $selfClose,
        ];
    }

    private static function tokenize(string $s): array
    {
        $out = [];
        $buf = '';
        $q = null;
        $len = strlen($s);

        for ($i=0; $i<$len; $i++) {
            $ch = $s[$i];

            if ($q !== null) {
                $buf .= $ch;
                if ($ch === $q) $q = null;
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $q = $ch;
                $buf .= $ch;
                continue;
            }
            if ($ch === ' ') {
                if ($buf !== '') { $out[] = $buf; $buf = ''; }
                continue;
            }
            $buf .= $ch;
        }
        if ($buf !== '') $out[] = $buf;
        return $out;
    }


    private static function renderNodes(array $nodes, array &$ctx, string $baseDir): string
    {
        $html = '';

        for ($i=0; $i<count($nodes); $i++) {
            $n = $nodes[$i];

            if ($n['type'] === 'doctype') {
                $html .= "<!DOCTYPE html>\n";
                continue;
            }

            if ($n['type'] === 'text') {
                $html .= htmlspecialchars((string)$n['text'], ENT_QUOTES, 'UTF-8') . "\n";
                continue;
            }

            if ($n['type'] === 'tag') {
                $html .= self::renderTagNode($n, $ctx, $baseDir);
                continue;
            }

            if ($n['type'] === 'dir') {
                $name = $n['name'];

                if ($name === 'include') {
                    $html .= self::renderInclude($n['args'], $ctx, $baseDir);
                    continue;
                }

                if ($name === 'if') {
                    [$chunk, $jump] = self::renderIfChain($nodes, $i, $ctx, $baseDir);
                    $html .= $chunk;
                    $i = $jump;
                    continue;
                }

                if ($name === 'elseif' || $name === 'else') continue;

                if ($name === 'for' || $name === 'foreach') {
                    $html .= self::renderForEach($n['args'], $n['children'], $ctx, $baseDir);
                    continue;
                }
            }
        }

        return $html;
    }

    private static function renderTagNode(array $n, array &$ctx, string $baseDir): string
    {
        $tag = $n['tag'];
        $attrs = self::renderAttrs($n['attrs'], $ctx);

        if ($n['selfClose']) return "<{$tag}{$attrs}>\n";

        $open = "<{$tag}{$attrs}>";

        if ($n['hasInlineText']) {
            $text = self::renderText($n['text'], $ctx);
            return "{$open}{$text}</{$tag}>\n";
        }

        $inner = self::renderNodes($n['children'], $ctx, $baseDir);
        return "{$open}\n{$inner}</{$tag}>\n";
    }

    private static function renderAttrs(array $attrs, array &$ctx): string
    {
        if (empty($attrs)) return '';

        $out = '';
        foreach ($attrs as $k => $rawV) {
            $v = self::evalAttrValue($rawV, $ctx);

            if ($v === true) { $out .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); continue; }
            if ($v === false || $v === null) continue;

            $out .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
                 . '="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '"';
        }
        return $out;
    }

    private static function evalAttrValue(string $raw, array &$ctx): mixed
    {
        $raw = trim($raw);

        if ((str_starts_with($raw, '"') && str_ends_with($raw, '"')) ||
            (str_starts_with($raw, "'") && str_ends_with($raw, "'"))) {
            return substr($raw, 1, -1);
        }

        if ($raw === 'true') return true;
        if ($raw === 'false') return false;
        if ($raw === 'null') return null;

        return self::evalExpr($raw, $ctx);
    }

    // #{ expr } escapado, !{ expr } sin escape
    private static function renderText(string $text, array &$ctx): string
    {
        $pattern = '/(!\{.*?\}|#\{.*?\})/s';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $out = '';
        foreach ($parts as $p) {
            if (preg_match('/^!\{\s*(.*?)\s*\}$/s', $p, $m)) {
                $val = self::evalExpr(trim($m[1]), $ctx);
                $out .= (string)$val;
                continue;
            }
            if (preg_match('/^#\{\s*(.*?)\s*\}$/s', $p, $m)) {
                $val = self::evalExpr(trim($m[1]), $ctx);
                $out .= htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
                continue;
            }
            $out .= htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
        }

        return $out;
    }

    private static function renderInclude(string $args, array &$ctx, string $baseDir): string
    {
        $tokens = self::tokenize($args);
        if (count($tokens) < 1) return '';

        $fileTok = array_shift($tokens);
        $file = self::stripQuotes($fileTok);

        $params = [];
        foreach ($tokens as $t) {
            if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)=(.+)$/', $t, $m)) continue;
            $params[$m[1]] = self::evalAttrValue(trim($m[2]), $ctx);
        }

        $merged = $ctx;
        foreach ($params as $k => $v) $merged[$k] = $v;

        $path = $file;
        if (!str_starts_with($path, '/') && !preg_match('~^[A-Za-z]:\\\\~', $path)) {
            $path = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
        }

        return self::renderFile($path, $merged);
    }

    private static function stripQuotes(string $s): string
    {
        $s = trim($s);
        if ((str_starts_with($s, '"') && str_ends_with($s, '"')) ||
            (str_starts_with($s, "'") && str_ends_with($s, "'"))) {
            return substr($s, 1, -1);
        }
        return $s;
    }

    private static function renderIfChain(array $nodes, int $startIdx, array &$ctx, string $baseDir): array
    {
        $baseLevel = $nodes[$startIdx]['level'];
        $chosenChildren = null;

        $i = $startIdx;
        $end = $startIdx;

        while ($i < count($nodes)) {
            $n = $nodes[$i];
            if ($n['type'] !== 'dir') break;
            if ($n['level'] !== $baseLevel) break;

            $name = $n['name'];
            if (!in_array($name, ['if','elseif','else'], true)) break;

            if ($name === 'else') {
                if ($chosenChildren === null) $chosenChildren = $n['children'];
                $end = $i;
                $i++;
                break;
            }

            $cond = trim($n['args']);
            $ok = $cond !== '' ? (bool) self::evalExpr($cond, $ctx) : false;

            if ($chosenChildren === null && $ok) $chosenChildren = $n['children'];

            $end = $i;
            $i++;
        }

        $html = $chosenChildren ? self::renderNodes($chosenChildren, $ctx, $baseDir) : '';
        return [$html, $end];
    }

    private static function renderForEach(string $args, array $children, array &$ctx, string $baseDir): string
    {
        $a = trim($args);

        $var1 = null; $var2 = null; $expr = null;

        // item in expr
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)(\s*,\s*([a-zA-Z_][a-zA-Z0-9_]*))?\s+in\s+(.+)$/', $a, $m)) {
            $var1 = $m[1];
            $var2 = $m[3] ?? null;
            $expr = trim($m[4]);
        }
        // expr as $v
        else if (preg_match('/^(.+)\s+as\s+\$?([a-zA-Z_][a-zA-Z0-9_]*)\s*$/i', $a, $m)) {
            $expr = trim($m[1]);
            $var1 = $m[2];
        }
        // expr as $k => $v
        else if (preg_match('/^(.+)\s+as\s+\$?([a-zA-Z_][a-zA-Z0-9_]*)\s*=>\s*\$?([a-zA-Z_][a-zA-Z0-9_]*)\s*$/i', $a, $m)) {
            $expr = trim($m[1]);
            $var1 = $m[2];
            $var2 = $m[3];
        } else {
            return '';
        }

        $iter = self::evalExpr($expr, $ctx);
        if (!is_array($iter) && !($iter instanceof Traversable)) return '';

        $html = '';
        foreach ($iter as $k => $v) {
            $old1 = $ctx[$var1] ?? null; $had1 = array_key_exists($var1, $ctx);
            $old2 = $var2 !== null ? ($ctx[$var2] ?? null) : null;
            $had2 = $var2 !== null ? array_key_exists($var2, $ctx) : false;

            if ($var2 === null) {
                $ctx[$var1] = $v;
            } else {
                $ctx[$var1] = $k;
                $ctx[$var2] = $v;
            }

            $html .= self::renderNodes($children, $ctx, $baseDir);

            if ($had1) $ctx[$var1] = $old1; else unset($ctx[$var1]);
            if ($var2 !== null) {
                if ($had2) $ctx[$var2] = $old2; else unset($ctx[$var2]);
            }
        }

        return $html;
    }

    private static function evalExpr(string $expr, array &$ctx): mixed
    {
        $expr = trim($expr);

        if (preg_match('/^\$([a-zA-Z_][a-zA-Z0-9_]*)$/', $expr, $m)) {
            return $ctx[$m[1]] ?? null;
        }

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $expr) && array_key_exists($expr, $ctx)) {
            return $ctx[$expr];
        }

        try {
            return (function() use (&$ctx, $expr) {
                extract($ctx, EXTR_SKIP);
                return eval('return (' . $expr . ');');
            })();
        } catch (Throwable $e) {
            return null;
        }
    }
}

