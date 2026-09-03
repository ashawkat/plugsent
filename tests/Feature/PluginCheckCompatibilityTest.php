<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Static audit of the WordPress plugin against the rule categories that
 * Plugin Check (PCP) enforces for wordpress.org submission:
 * security (nonces/capabilities/sanitization), i18n, direct access guards,
 * obfuscation, plugin headers, and readme.txt completeness.
 *
 * Running the official PCP tool additionally requires a live WP install with
 * the plugin-check plugin; this suite keeps the static bar green in CI.
 */
class PluginCheckCompatibilityTest extends TestCase
{
    private string $pluginDir;

    protected function setUp(): void
    {
        $this->pluginDir = realpath(__DIR__.'/../../plugins/plugsent-connector');
    }

    public function test_every_php_file_prevents_direct_access(): void
    {
        foreach ($this->pluginFiles() as $file) {
            $content = (string) file_get_contents($file);

            // uninstall.php is correctly guarded by WP_UNINSTALL_PLUGIN instead.
            $guarded = str_contains($content, "defined('ABSPATH')")
                || str_contains($content, "defined('WP_UNINSTALL_PLUGIN')");

            $this->assertTrue($guarded, "{$file} must guard against direct access.");
        }
    }

    public function test_no_direct_database_or_obfuscated_code(): void
    {
        $forbidden = ['eval(', 'base64_decode(', 'str_rot13(', 'gzinflate(', '$wpdb', 'curl_exec'];

        foreach ($this->pluginFiles() as $file) {
            $content = (string) file_get_contents($file);

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $content,
                    "{$file} uses {$needle}, which Plugin Check flags.",
                );
            }
        }
    }

    public function test_raw_input_is_unslashed_and_sanitized(): void
    {
        foreach ($this->pluginFiles() as $file) {
            foreach (file($file) as $line) {
                if (! preg_match('/\$_(GET|POST|REQUEST|COOKIE)\b/', $line)) {
                    continue;
                }

                $this->assertStringContainsString(
                    'wp_unslash',
                    $line,
                    "{$file} reads superglobals without wp_unslash(): ".trim($line),
                );
                $this->assertMatchesRegularExpression(
                    '/(sanitize_|esc_|absint|intval|wp_validate_boolean)/',
                    $line,
                    "{$file} reads superglobals without sanitization: ".trim($line),
                );
            }
        }
    }

    public function test_admin_post_handlers_have_nonce_and_capability_checks(): void
    {
        $main = (string) file_get_contents($this->pluginDir.'/includes/class-plugsent-connector.php');

        $handlers = ['handle_pair', 'handle_sync', 'handle_disconnect'];

        foreach ($handlers as $handler) {
            $start = strpos($main, "function {$handler}(");
            $this->assertNotFalse($start, "{$handler}() must exist.");
            $body = substr($main, $start, 800);

            $this->assertStringContainsString('check_admin_referer', $body, "{$handler}() must verify a nonce.");
            $this->assertStringContainsString('current_user_can', $body, "{$handler}() must check capabilities.");
        }
    }

    public function test_all_translation_calls_use_the_plugin_text_domain(): void
    {
        $domain = Str::before(basename((string) glob($this->pluginDir.'/*.php')[0]), '.php');

        $this->assertSame('plugsent-connector', $domain, 'Plugin slug and text domain must match.');

        foreach ($this->pluginFiles() as $file) {
            foreach (file($file) as $line) {
                if (! preg_match('/(?:__|_e|_x|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(/', $line)) {
                    continue;
                }

                $this->assertStringContainsString(
                    "'plugsent-connector'",
                    $line,
                    "{$file} has a translation call without the text domain: ".trim($line),
                );
            }
        }
    }

    public function test_readme_has_the_sections_plugin_check_requires(): void
    {
        $readme = (string) file_get_contents($this->pluginDir.'/readme.txt');

        foreach ([
            '=== Plugsent Connector ===',
            'Contributors:',
            'Requires at least:',
            'Tested up to:',
            'Requires PHP:',
            'Stable tag:',
            'License: GPL-2.0-or-later',
            '== Description ==',
            '== Installation ==',
            '== Frequently Asked Questions ==',
            '== Screenshots ==',
            '== Changelog ==',
        ] as $needle) {
            $this->assertStringContainsString($needle, $readme, "readme.txt is missing {$needle}.");
        }
    }

    public function test_plugin_header_is_complete(): void
    {
        $main = (string) file_get_contents($this->pluginDir.'/plugsent-connector.php');
        $normalized = preg_replace('/\s+/', ' ', $main);

        foreach ([
            'Plugin Name: Plugsent Connector',
            'Version:',
            'Requires at least:',
            'Requires PHP:',
            'Author:',
            'License: GPL-2.0-or-later',
            'Text Domain: plugsent-connector',
        ] as $needle) {
            $this->assertStringContainsString($needle, $normalized, "Plugin header is missing {$needle}.");
        }
    }

    /**
     * @return array<string>
     */
    private function pluginFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->pluginDir),
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        $this->assertGreaterThanOrEqual(3, count($files), 'Expected the plugin PHP files to exist.');

        return $files;
    }
}
