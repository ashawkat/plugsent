<?php

namespace App\Actions;

use App\Models\Site;

/**
 * Derive the security check list and score for a site from the raw facts
 * reported by the connector's security.scan command plus inventory data
 * (pending updates, vulnerability counts). Keeping the logic on the panel
 * means it can evolve without updating every connected site.
 */
class EvaluateSiteSecurity
{
    /**
     * PHP versions still receiving upstream security fixes.
     *
     * @var array<int, string>
     */
    private const SUPPORTED_PHP = ['8.5', '8.4', '8.3', '8.2'];

    /**
     * Persist the scan payload on the site and evaluate it.
     *
     * @param  array{facts?: array<string, mixed>, hardening?: array<string, bool>}  $payload
     * @return array{checks: array<int, array{key: string, label: string, passed: bool, detail: string, fix: ?string}>, score: int}
     */
    public function __invoke(Site $site, array $payload): array
    {
        $site->forceFill([
            'security_facts' => $payload['facts'] ?? [],
            'hardening' => $payload['hardening'] ?? [],
            'security_scanned_at' => now(),
        ])->save();

        $result = $this->evaluate($site);

        $site->forceFill(['security_score' => $result['score']])->save();

        return $result;
    }

    /**
     * Evaluate from the site's stored facts (no persistence).
     *
     * @return array{checks: array<int, array{key: string, label: string, passed: bool, detail: string, fix: ?string}>, score: int}
     */
    public function evaluate(Site $site): array
    {
        $facts = (array) ($site->security_facts ?? []);
        $hardening = (array) ($site->hardening ?? []);

        $checks = [
            $this->check('ssl', 'HTTPS enabled', (bool) ($facts['ssl'] ?? false), $facts['ssl'] ?? false
                ? 'HTTPS is properly configured for this site.'
                : 'The site is not served over HTTPS — logins and data travel unencrypted.', null),

            $this->check('wp_debug', 'WP_DEBUG disabled', ! ($facts['wp_debug'] ?? false), ($facts['wp_debug'] ?? false)
                ? 'Debug mode can leak file paths, database queries, and stack traces to visitors.'
                : 'Debug mode is off.', null),

            $this->check('core_updated', 'WordPress core up to date', $site->inventory()->where('context', 'core')->where('update_available', true)->doesntExist()
                && $site->inventory()->where('context', 'core')->exists(), 'Running WordPress '.($site->wp_version ?? '?').'.', null),

            $this->check('php_supported', 'PHP version receives security fixes', $this->phpSupported((string) ($facts['php_version'] ?? $site->php_version ?? '')), 'Running PHP '.($facts['php_version'] ?? $site->php_version ?? '?').'.', null),

            $this->check('indexable_or_intentional', 'Search-engine visibility configured', true, 'blog_public is '.(($facts['blog_public'] ?? true) ? 'on (site indexable)' : 'off (discouraging search engines — fine if intentional).'), null),

            $this->check('no_inactive_plugins', 'No inactive plugins', (int) ($facts['inactive_plugins'] ?? 0) === 0, (int) ($facts['inactive_plugins'] ?? 0).' inactive plugin(s). Deactivated plugins still expose code that attackers can probe.', null),

            $this->check('no_inactive_themes', 'No inactive themes', (int) ($facts['inactive_themes'] ?? 0) === 0, (int) ($facts['inactive_themes'] ?? 0).' inactive theme(s) on disk.', null),

            $this->check('no_vulnerable_software', 'No known vulnerable software', $site->inventory()->where('vuln_count', '>', 0)->doesntExist(), $site->inventory()->where('vuln_count', '>', 0)->count().' item(s) with known vulnerabilities.', null),

            $this->check('file_editor', 'File editor disabled', ! empty($facts['disallow_file_edit']) || ! empty($hardening['disable_file_editor']), ! empty($facts['disallow_file_edit']) || ! empty($hardening['disable_file_editor'])
                ? 'The built-in wp-admin file editor is disabled.'
                : 'A compromised admin account could inject PHP through the built-in plugin/theme editor.', 'disable_file_editor'),

            $this->check('xmlrpc', 'XML-RPC disabled', empty($facts['xmlrpc_enabled']) || ! empty($hardening['disable_xmlrpc']), empty($facts['xmlrpc_enabled']) || ! empty($hardening['disable_xmlrpc'])
                ? 'XML-RPC is closed.'
                : 'XML-RPC is open — a common amplifier for brute-force and pingback attacks. Leave off if the site uses Jetpack or the WordPress mobile app.', 'disable_xmlrpc'),

            $this->check('user_enum', 'User enumeration blocked', ! empty($hardening['block_user_enum']), ! empty($hardening['block_user_enum'])
                ? 'Author scans and public user lists are blocked.'
                : 'Usernames can be listed via author archives and the REST API — the first step of a brute-force attack.', 'block_user_enum'),

            $this->check('login_errors', 'Login errors masked', ! empty($hardening['mask_login_errors']), ! empty($hardening['mask_login_errors'])
                ? 'Failed logins show a single generic message.'
                : 'Failed logins reveal whether a username exists.', 'mask_login_errors'),

            $this->check('headers', 'Security headers enabled', ! empty($hardening['security_headers']), ! empty($hardening['security_headers'])
                ? 'X-Frame-Options, X-Content-Type-Options and friends are sent.'
                : 'No hardening headers — the site can be framed (clickjacking) and MIME-sniffed.', 'security_headers'),

            $this->check('version_hidden', 'WordPress version hidden', ! empty($hardening['hide_version']), ! empty($hardening['hide_version'])
                ? 'The version is removed from the site HTML and feeds.'
                : 'The WordPress version is public in the page HTML, enabling version-specific exploits.', 'hide_version'),
        ];

        $passed = count(array_filter($checks, fn (array $check) => $check['passed']));

        return [
            'checks' => $checks,
            'score' => (int) round($passed / max(1, count($checks)) * 100),
        ];
    }

    /**
     * @return array{key: string, label: string, passed: bool, detail: string, fix: ?string}
     */
    private function check(string $key, string $label, bool $passed, string $detail, ?string $fix): array
    {
        return ['key' => $key, 'label' => $label, 'passed' => $passed, 'detail' => $detail, 'fix' => $fix];
    }

    private function phpSupported(string $version): bool
    {
        $major = preg_match('/^(\d+\.\d+)/', $version, $m) ? $m[1] : '';

        return $major !== '' && in_array($major, self::SUPPORTED_PHP, true);
    }
}
