<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use ReflectionFunction;
use ReflectionMethod;
use Throwable;

final class Renderer
{
    public static function render(ExceptionData $error): void
    {
        $context = ContextCollector::collect($error);
        ErrorHistory::store($error, $context);

        if (PHP_SAPI === 'cli' || (defined('WP_CLI') && WP_CLI)) {
            self::renderCli($error, $context);
            return;
        }

        if (! headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Robots-Tag: noindex, nofollow', true);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        while (ob_get_level() > 0) @ob_end_clean();

        $frames = self::frames($error);
        $code = SourceReader::around($error->file, $error->line);
        $solutions = SolutionManager::solutions($error, $context);
        echo self::page($error, $context, $frames, $code, $solutions);
    }

    /** @param array<string,mixed> $context */
    private static function renderCli(ExceptionData $error, array $context): void
    {
        $source = (array) ($context['likely_source'] ?? []);
        $hooks = HookRecorder::activeStack();
        $out = "\nWP Aware Errors · " . Runtime::installation()->label . "\n" . str_repeat('=', 78) . "\n";
        $out .= $error->type . ': ' . $error->message . "\n";
        $out .= FrameClassifier::relative($error->file) . ':' . $error->line . "\n";
        $out .= 'Source: ' . ($source['label'] ?? 'Unknown') . "\n";
        if ($hooks !== []) $out .= 'Hooks: ' . implode(' > ', $hooks) . "\n";
        $request = (array) ($context['request'] ?? []);
        if (! empty($request['rest_route'])) $out .= 'REST: ' . $request['rest_route'] . "\n";
        if (! empty($request['ajax_action'])) $out .= 'AJAX: ' . $request['ajax_action'] . "\n";
        $out .= str_repeat('-', 78) . "\n";
        foreach (array_slice(self::frames($error), 0, 15) as $index => $frame) {
            $out .= sprintf("#%d [%s] %s:%d %s\n", $index, $frame['label'], $frame['relative'], $frame['line'], $frame['call']);
        }
        fwrite(STDERR, $out . "\n");
    }

    /** @return list<array<string,mixed>> */
    private static function frames(ExceptionData $error): array
    {
        $frames = [];
        foreach ($error->trace as $index => $frame) {
            $file = (string) ($frame['file'] ?? '');
            $line = (int) ($frame['line'] ?? 0);
            $classification = FrameClassifier::classify($file);
            $call = '';
            if (isset($frame['class'])) $call .= (string) $frame['class'] . (string) ($frame['type'] ?? '::');
            $call .= (string) ($frame['function'] ?? '{main}');
            $frames[] = [
                'index' => $index,
                'file' => $file,
                'relative' => FrameClassifier::relative($file),
                'line' => $line,
                'call' => $call,
                'kind' => (string) ($classification['kind'] ?? 'app'),
                'label' => (string) ($classification['label'] ?? 'Application'),
                'internal' => (bool) ($classification['internal'] ?? false),
            ];
        }
        return $frames;
    }

    /** @param array<string,mixed> $context @param list<array<string,mixed>> $frames @param list<array{line:int,text:string,active:bool}> $code @param list<array{title:string,description:string,action:string}> $solutions */
    private static function page(ExceptionData $error, array $context, array $frames, array $code, array $solutions): string
    {
        $source = (array) ($context['likely_source'] ?? []);
        $environment = (array) ($context['environment'] ?? []);
        $request = (array) ($context['request'] ?? []);
        $hooks = (array) ($context['hooks'] ?? []);
        $db = (array) ($context['database'] ?? []);
        $woo = (array) ($context['woocommerce'] ?? []);
        $compat = (array) ($context['compatibility'] ?? []);
        $installation = (array) ($context['installation'] ?? []);

        $codeHtml = '';
        foreach ($code as $row) {
            $codeHtml .= '<div class="code-line' . ($row['active'] ? ' active' : '') . '"><span class="ln">' . $row['line'] . '</span><code>' . self::e($row['text']) . '</code></div>';
        }
        if ($codeHtml === '') $codeHtml = '<div class="empty pad">Source file could not be read.</div>';

        $framesHtml = '';
        foreach ($frames as $frame) {
            $classes = 'frame kind-' . self::e((string) $frame['kind']) . ($frame['internal'] ? ' internal' : ' app-frame');
            $path = (string) $frame['file'];
            $line = (int) $frame['line'];
            $cursor = $path !== '' ? 'cursor://file/' . rawurlencode($path) . ':' . $line : '#';
            $vscode = $path !== '' ? 'vscode://file/' . rawurlencode($path) . ':' . $line : '#';
            $framesHtml .= '<div class="' . $classes . '"><div class="frame-no">#' . (int) $frame['index'] . '</div><div class="frame-main"><div class="frame-call">' . self::e((string) $frame['call']) . '</div><div class="frame-path">' . self::e((string) $frame['relative']) . ($line > 0 ? ':' . $line : '') . '</div></div><div class="badge">' . self::e((string) $frame['label']) . '</div>' . ($path !== '' ? '<div class="frame-actions"><a href="' . self::e($cursor) . '">Cursor</a><a href="' . self::e($vscode) . '">VS Code</a><button data-copy="' . self::e($path . ':' . $line) . '">Copy</button></div>' : '') . '</div>';
        }

        $solutionsHtml = '';
        foreach ($solutions as $solution) {
            $solutionsHtml .= '<div class="solution"><strong>' . self::e($solution['title']) . '</strong><p>' . self::e($solution['description']) . '</p><small>' . self::e($solution['action']) . '</small></div>';
        }
        if ($solutionsHtml === '') $solutionsHtml = '<div class="empty">No high-confidence solution provider matched this error.</div>';

        $hookHtml = '';
        foreach (array_slice((array) ($hooks['recent'] ?? []), -8) as $hook) {
            $args = (array) ($hook['args'] ?? []);
            $hookHtml .= '<details class="hook"><summary><span>' . self::e((string) ($hook['name'] ?? '')) . '</span><small>' . count($args) . ' args captured</small></summary><pre>' . self::e(self::json($args)) . '</pre></details>';
        }
        if ($hookHtml === '') $hookHtml = '<div class="empty">No hooks captured before the error.</div>';

        $ownershipHtml = '';
        foreach ((array) ($hooks['ownership'] ?? []) as $owner) {
            $ownershipHtml .= '<div class="owner"><span>' . self::e((string) ($owner['hook'] ?? '')) . '</span><code>' . self::e((string) ($owner['callback'] ?? '')) . '</code><strong>' . self::e((string) ($owner['component'] ?? '')) . '</strong><small>p' . (int) ($owner['priority'] ?? 10) . '</small></div>';
        }
        if ($ownershipHtml === '') $ownershipHtml = '<div class="empty">No inspectable callbacks found for the active/recent hooks.</div>';

        $compatHtml = '';
        foreach ((array) ($compat['issues'] ?? []) as $issue) {
            $severity = self::e((string) ($issue['severity'] ?? 'info'));
            $compatHtml .= '<div class="compat ' . $severity . '"><span>' . strtoupper($severity) . '</span><strong>' . self::e((string) ($issue['plugin'] ?? '')) . '</strong><p>' . self::e((string) ($issue['message'] ?? '')) . '</p></div>';
        }
        if ($compatHtml === '') $compatHtml = '<div class="empty">No dependency/version conflicts detected from declared plugin metadata.</div>';

        $queryHtml = '';
        foreach ((array) ($db['queries'] ?? []) as $query) {
            $queryHtml .= '<div class="query"><div><code>' . self::e((string) ($query['sql'] ?? '')) . '</code><small>' . self::e((string) ($query['caller'] ?? '')) . '</small></div><span>' . (isset($query['seconds']) ? number_format((float) $query['seconds'] * 1000, 2) . 'ms' : '') . '</span></div>';
        }
        if ($queryHtml === '') $queryHtml = '<div class="empty">Enable <code>SAVEQUERIES</code> in development to include recent queries.</div>';

        $wooHtml = empty($woo['active']) ? '<div class="empty">WooCommerce is not active/detected.</div>' : self::rows([
            'Version' => $woo['version'] ?? '',
            'wc-ajax' => $woo['ajax_endpoint'] ?? '',
            'HPOS' => isset($woo['hpos']) && $woo['hpos'] !== null ? ($woo['hpos'] ? 'Enabled' : 'Disabled') : 'Unknown',
            'Cart count' => $woo['cart_count'] ?? null,
            'Cart total' => $woo['cart_total'] ?? null,
            'Checkout' => $woo['checkout'] ?? null,
            'Cart page' => $woo['cart_page'] ?? null,
            'Account page' => $woo['account_page'] ?? null,
        ]);

        $envRows = [
            'WordPress' => $environment['wordpress'] ?? 'unknown',
            'PHP' => $environment['php'] ?? PHP_VERSION,
            'Environment' => $environment['environment_type'] ?? 'unknown',
            'Development mode' => $environment['development_mode'] ?? '',
            'Multisite' => $environment['multisite'] ?? false,
            'PHP memory' => $environment['memory_limit'] ?? '',
            'WP memory' => $environment['wp_memory_limit'] ?? '',
            'WP_DEBUG' => $environment['debug'] ?? false,
            'SAVEQUERIES' => $environment['savequeries'] ?? false,
            'Theme' => is_array($environment['theme'] ?? null) ? (($environment['theme']['name'] ?? '') . ' ' . ($environment['theme']['version'] ?? '')) : '',
        ];
        $requestRows = [
            'Method' => $request['method'] ?? '',
            'URI' => $request['uri'] ?? '',
            'AJAX' => $request['ajax'] ?? false,
            'AJAX action' => $request['ajax_action'] ?? '',
            'REST' => $request['rest'] ?? false,
            'REST route' => $request['rest_route'] ?? '',
            'Cron' => $request['cron'] ?? false,
            'CLI' => $request['cli'] ?? false,
        ];

        $ai = self::aiPayload($error, $context, $frames, $solutions);
        $activeHook = end($hooks['active']) ?: (($hooks['recent'] !== []) ? end($hooks['recent'])['name'] ?? '—' : '—');

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . self::e($error->type) . ' — WP Aware Errors</title>' . self::styles() . '</head><body><main class="shell">'
            . '<header class="top"><div><div class="eyebrow">WP Aware Errors · ' . self::e((string) ($installation['label'] ?? 'Unknown')) . ' · v' . self::e((string) ($installation['version'] ?? '')) . '</div><h1>' . self::e($error->type) . '</h1><p class="message">' . self::e($error->message) . '</p></div><div class="top-actions"><button id="copy-ai">Copy AI context</button><button data-copy="' . self::e($error->file . ':' . $error->line) . '">Copy location</button></div></header>'
            . '<section class="summary"><div><span>Likely source</span><strong>' . self::e((string) ($source['label'] ?? 'Unknown')) . '</strong><small>' . self::e((string) ($source['kind'] ?? 'unknown')) . '</small></div><div><span>Location</span><strong>' . self::e(FrameClassifier::relative($error->file)) . ':' . $error->line . '</strong><small>' . self::e((string) ($installation['mode'] ?? '')) . '</small></div><div><span>Current hook</span><strong>' . self::e((string) $activeHook) . '</strong><small>' . self::e((string) ($request['rest_route'] ?: $request['ajax_action'] ?: '')) . '</small></div></section>'
            . '<section class="panel"><div class="panel-head"><h2>Code</h2><span>' . self::e(FrameClassifier::relative($error->file)) . '</span></div><div class="code">' . $codeHtml . '</div></section>'
            . '<section class="grid"><div class="panel"><div class="panel-head"><h2>Solutions</h2><span>Provider-based</span></div><div class="panel-body">' . $solutionsHtml . '</div></div><div class="panel"><div class="panel-head"><h2>Compatibility intelligence</h2><span>WP / PHP / dependencies / Woo</span></div><div class="panel-body">' . $compatHtml . '</div></div></section>'
            . '<section class="panel"><div class="panel-head"><h2>Stack trace</h2><span>Component ownership attached to each frame</span></div><div class="frames">' . $framesHtml . '</div></section>'
            . '<section class="grid"><div class="panel"><div class="panel-head"><h2>Hook argument inspection</h2><span>Sanitised snapshots</span></div><div class="panel-body">' . $hookHtml . '</div></div><div class="panel"><div class="panel-head"><h2>Hook callback ownership</h2><span>Query Monitor-style component attribution</span></div><div class="panel-body ownership">' . $ownershipHtml . '</div></div></section>'
            . '<section class="grid"><div class="panel"><div class="panel-head"><h2>Request / route</h2></div><div class="panel-body kv">' . self::rows($requestRows) . '<details><summary>GET / POST / REST params / headers</summary><pre>' . self::e(self::json(['query' => $request['query'] ?? [], 'post' => $request['post'] ?? [], 'rest_params' => $request['rest_params'] ?? [], 'headers' => $request['headers'] ?? []])) . '</pre></details></div></div><div class="panel"><div class="panel-head"><h2>WooCommerce</h2></div><div class="panel-body kv">' . $wooHtml . '</div></div></section>'
            . '<section class="grid"><div class="panel"><div class="panel-head"><h2>Environment</h2></div><div class="panel-body kv">' . self::rows($envRows) . '</div></div><div class="panel"><div class="panel-head"><h2>Recent database queries</h2></div><div class="panel-body queries">' . $queryHtml . '</div></div></section>'
            . '<footer>Error history is stored locally under Tools → WP Aware Errors. Never expose this renderer publicly; production display requires explicit opt-in.</footer></main>'
            . '<script>window.__wpAwarePayload=' . json_encode($ai, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';document.addEventListener("click",async(e)=>{const b=e.target.closest("[data-copy]");if(b){await navigator.clipboard.writeText(b.dataset.copy||"");const t=b.textContent;b.textContent="Copied";setTimeout(()=>b.textContent=t,900)}});document.getElementById("copy-ai")?.addEventListener("click",async(e)=>{await navigator.clipboard.writeText(window.__wpAwarePayload||"");const b=e.currentTarget;const t=b.textContent;b.textContent="Copied";setTimeout(()=>b.textContent=t,900)});</script></body></html>';
    }

    /** @param array<string,mixed> $context @param list<array<string,mixed>> $frames @param list<array{title:string,description:string,action:string}> $solutions */
    private static function aiPayload(ExceptionData $error, array $context, array $frames, array $solutions): string
    {
        $environment = (array) ($context['environment'] ?? []);
        $hooks = (array) ($context['hooks'] ?? []);
        $source = (array) ($context['likely_source'] ?? []);
        $request = (array) ($context['request'] ?? []);
        $woo = (array) ($context['woocommerce'] ?? []);
        $installation = (array) ($context['installation'] ?? []);
        $lines = [
            '# WP Aware Errors context','',
            '**Error:** ' . $error->type . ': ' . $error->message,
            '**Location:** ' . FrameClassifier::relative($error->file) . ':' . $error->line,
            '**Likely source:** ' . ($source['label'] ?? 'Unknown'),
            '**Installation:** ' . ($installation['label'] ?? 'Unknown') . ' (' . ($installation['mode'] ?? '') . ')',
            '**WordPress:** ' . ($environment['wordpress'] ?? 'unknown'),
            '**PHP:** ' . ($environment['php'] ?? PHP_VERSION),
            '**Environment:** ' . ($environment['environment_type'] ?? 'unknown'),
            '**AJAX action:** ' . ($request['ajax_action'] ?? ''),
            '**REST route:** ' . ($request['rest_route'] ?? ''),
            '**Hook stack:** ' . implode(' > ', (array) ($hooks['active'] ?? [])),
        ];
        if (! empty($woo['active'])) $lines[] = '**WooCommerce:** ' . ($woo['version'] ?? 'unknown') . ' · HPOS ' . (($woo['hpos'] ?? null) === true ? 'enabled' : (($woo['hpos'] ?? null) === false ? 'disabled' : 'unknown'));
        $lines[] = '';
        $lines[] = '## Stack';
        foreach (array_slice($frames, 0, 20) as $frame) $lines[] = '- [' . $frame['label'] . '] ' . $frame['relative'] . ':' . $frame['line'] . ' — ' . $frame['call'];
        if ($solutions !== []) {
            $lines[] = '';
            $lines[] = '## Suggested checks';
            foreach ($solutions as $solution) $lines[] = '- **' . $solution['title'] . ':** ' . $solution['action'];
        }
        $recent = (array) ($hooks['recent'] ?? []);
        if ($recent !== []) {
            $lines[] = '';
            $lines[] = '## Recent hook arguments (sanitised)';
            foreach (array_slice($recent, -4) as $hook) $lines[] = '- `' . ($hook['name'] ?? '') . '` ' . self::json($hook['args'] ?? []);
        }
        return implode("\n", $lines);
    }

    /** @param array<string,mixed> $rows */
    private static function rows(array $rows): string
    {
        $html = '';
        foreach ($rows as $key => $value) {
            if (is_bool($value)) $value = $value ? 'Yes' : 'No';
            elseif ($value === null || $value === '') $value = '—';
            elseif (is_array($value)) $value = self::json($value);
            $html .= '<div><span>' . self::e((string) $key) . '</span><strong>' . self::e((string) $value) . '</strong></div>';
        }
        return $html;
    }

    private static function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    private static function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    private static function styles(): string
    {
        return <<<'HTML'
<style>
:root{color-scheme:dark;--bg:#0b0d10;--panel:#11151a;--line:#252b33;--text:#eef2f7;--muted:#8e99a8;--accent:#ff5a36;--good:#41d6a3;--warn:#f3b73f;--bad:#ff6678}*{box-sizing:border-box}html,body{margin:0;padding:0;width:100%;max-width:none;min-height:100%;min-height:100vh;min-height:100dvh;background:var(--bg)}body{color:var(--text);font:14px/1.5 ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.shell{width:100%;max-width:none;min-height:100vh;min-height:100dvh;margin:0;padding:32px 40px;display:flex;flex-direction:column}.top{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;margin-bottom:28px}.eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:11px;color:var(--accent);font-weight:800}.top h1{font-size:20px;margin:8px 0 4px}.message{font-size:30px;line-height:1.15;max-width:none;margin:0;font-weight:700;letter-spacing:-.025em}.top-actions{display:flex;gap:8px;flex-wrap:wrap}button,.frame-actions a{background:#171c22;border:1px solid #303842;color:#dce3eb;border-radius:8px;padding:8px 11px;text-decoration:none;font:inherit;cursor:pointer}.top-actions button:first-child{background:var(--accent);border-color:var(--accent);color:white}.summary{display:grid;grid-template-columns:1fr 2fr 1fr;border:1px solid var(--line);border-radius:13px;overflow:hidden;margin-bottom:18px;background:var(--panel)}.summary>div{padding:16px 18px;border-right:1px solid var(--line);min-width:0}.summary>div:last-child{border:0}.summary span,.summary small{display:block;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.07em}.summary strong{display:block;margin:4px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.panel{border:1px solid var(--line);background:var(--panel);border-radius:13px;overflow:hidden;margin-bottom:18px}.panel-head{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:13px 17px;border-bottom:1px solid var(--line);color:var(--muted)}.panel-head h2{font-size:13px;color:#fff;margin:0;text-transform:uppercase;letter-spacing:.08em}.panel-body{padding:17px}.pad{padding:17px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.grid>.panel{margin-bottom:18px}.code{font:13px/1.65 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;overflow:auto;padding:10px 0}.code-line{display:grid;grid-template-columns:65px minmax(700px,1fr);padding:0 16px}.code-line .ln{color:#5e6976;text-align:right;padding-right:18px;user-select:none}.code-line code{white-space:pre}.code-line.active{background:rgba(255,90,54,.12);box-shadow:inset 3px 0 var(--accent)}.code-line.active .ln{color:var(--accent);font-weight:800}.frames{padding:6px}.frame{display:grid;grid-template-columns:42px minmax(0,1fr) auto auto;gap:12px;align-items:center;border-bottom:1px solid #1d232a;padding:11px}.frame:last-child{border:0}.frame.internal{opacity:.46}.frame.internal:hover{opacity:1}.frame-no{color:#67717d;font-family:ui-monospace,monospace}.frame-call{font-weight:700;overflow:hidden;text-overflow:ellipsis}.frame-path{color:var(--muted);font:12px ui-monospace,monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.badge{font-size:11px;padding:4px 8px;border-radius:999px;background:#1b222a;border:1px solid #2d3742;white-space:nowrap}.app-frame .badge{border-color:rgba(65,214,163,.35);color:var(--good)}.frame-actions{display:flex;gap:6px}.frame-actions a,.frame-actions button{padding:5px 7px;font-size:11px}.solution{padding:12px 0;border-bottom:1px solid #20262d}.solution:first-child{padding-top:0}.solution:last-child{border:0;padding-bottom:0}.solution strong{display:block;color:#fff}.solution p{margin:4px 0;color:#c8d0da}.solution small{display:block;color:var(--good)}.compat{border:1px solid #2b333c;border-radius:9px;padding:10px 12px;margin-bottom:9px}.compat span{font-size:10px;letter-spacing:.1em;color:var(--muted)}.compat strong{display:block;margin-top:2px}.compat p{margin:4px 0 0;color:#cbd3dc}.compat.warning{border-color:rgba(243,183,63,.35)}.compat.warning span{color:var(--warn)}.compat.error{border-color:rgba(255,102,120,.38)}.compat.error span{color:var(--bad)}.hook{border-bottom:1px solid #20262d;padding:8px 0}.hook summary{display:flex;justify-content:space-between;gap:12px}.hook summary span{font-weight:700}.hook summary small{color:var(--muted)}.hook pre{margin-bottom:3px}.owner{display:grid;grid-template-columns:minmax(100px,.7fr) minmax(170px,1.2fr) minmax(140px,1fr) 35px;gap:10px;padding:8px 0;border-bottom:1px solid #20262d;align-items:center}.owner span{color:var(--muted)}.owner code{overflow:hidden;text-overflow:ellipsis}.owner strong{overflow:hidden;text-overflow:ellipsis}.owner small{color:var(--muted);text-align:right}.kv>div{display:grid;grid-template-columns:155px minmax(0,1fr);gap:12px;padding:8px 0;border-bottom:1px solid #1f252c}.kv>div:last-of-type{border:0}.kv span{color:var(--muted)}.kv strong{font-weight:600;overflow-wrap:anywhere}details{margin-top:12px}summary{cursor:pointer;color:#cfd7df}pre{white-space:pre-wrap;overflow-wrap:anywhere;font:12px/1.55 ui-monospace,monospace;background:#0d1014;border:1px solid #20262d;border-radius:8px;padding:12px}.query{display:grid;grid-template-columns:minmax(0,1fr) 70px;gap:12px;padding:9px 0;border-bottom:1px solid #1f252c}.query code{display:block;font:12px/1.4 ui-monospace,monospace;overflow-wrap:anywhere}.query small{display:block;color:#65707d;margin-top:4px}.query span{color:var(--muted);text-align:right}.empty{color:var(--muted)}footer{color:#687380;text-align:center;padding:8px 0 12px;margin-top:auto}footer code{color:#a8b2be}@media(max-width:940px){.shell{padding:20px}.top{display:block}.top-actions{margin-top:18px}.message{font-size:23px}.summary,.grid{grid-template-columns:1fr}.summary>div{border-right:0;border-bottom:1px solid var(--line)}.frame{grid-template-columns:34px minmax(0,1fr)}.frame .badge,.frame-actions{grid-column:2}.owner{grid-template-columns:1fr}.owner small{text-align:left}.code-line{grid-template-columns:50px minmax(650px,1fr)}}
</style>
HTML;
    }
}
